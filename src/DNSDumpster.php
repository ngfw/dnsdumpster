<?php

namespace Ngfw\DNSDumpster;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Class DNSDumpster
 * A wrapper for the DNSDumpster API with rate limiting and retry mechanisms.
 * Provides secure and efficient access to DNS reconnaissance data.
 *
 * @author Nick Gejadze
 *
 * @version 1.1
 *
 * @license MIT
 */
class DNSDumpster
{
    private const REQUEST_TIMEOUT = 30;
    private const RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY = 100;
    private const RATE_LIMIT_SECONDS = 2; // Rate limit: 1 request per 2 seconds

    private string $apiKey;
    private string $host;
    private ?int $lastRequestTime = null;

    /**
     * Initializes the DNSDumpster instance with the provided options.
     *
     * @param  array  $options  An associative array containing the API key and URL configuration.
     */
    public function __construct(array $options)
    {
        $this->initialize($options);
    }

    /**
     * Validates the configuration of the DNSDumpster instance.
     * Ensures that the API key and API URL are both provided.
     * Throws an InvalidArgumentException if either is missing.
     */
    private function validateConfiguration(): void
    {
        if (empty($this->apiKey) || empty($this->host)) {
            throw new InvalidArgumentException(
                'Missing required API configuration. Both DNSDumpster_API_KEY and DNSDumpster_API_URL must be provided.'
            );
        }
    }

    /**
     * Initializes the DNSDumpster instance with the provided options.
     *
     * This private method sets the API key and API URL for the DNSDumpster instance
     * based on the provided options array. The API key is trimmed, and the API URL
     * has any trailing slash removed.
     *
     * @param  array  $options  An associative array containing the API key and URL configuration.
     */
    private function initialize(array $options): void
    {
        $this->apiKey = trim($options['DNSDumpster_API_KEY']);
        $this->host = rtrim($options['DNSDumpster_API_URL'], '/');
    }

    /**
     * Retrieve domain information from DNSDumpster API.
     *
     * @param  string  $domain  The domain to lookup
     * @param  int  $page  Page number for paginated results (default: 1)
     * @return array The domain information
     *
     * @throws InvalidArgumentException If domain is invalid
     * @throws Exception If API request fails or rate limit is exceeded
     */
    public function fetchData(string $domain, int $page = 1): array
    {
        $this->validateConfiguration();
        $this->validateDomain($domain);

        if ($this->isRateLimited()) {
            sleep(self::RATE_LIMIT_SECONDS);
        }

        $data = $this->makeApiRequest($domain, $page);
        $this->updateRateLimit();

        return $data;
    }

    /**
     * Validates the provided domain string.
     *
     * This private method checks if the given domain string is not empty and is a valid domain name.
     * If the domain is invalid, an InvalidArgumentException is thrown.
     *
     * @param  string  $domain  The domain to validate.
     *
     * @throws InvalidArgumentException If the domain is invalid.
     */
    private function validateDomain(string $domain): void
    {
        if (empty($domain) || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidArgumentException('Invalid domain provided');
        }
    }

    /**
     * Makes an API request to the DNSDumpster service to retrieve domain information.
     *
     * This private method constructs the API request URL, sends the request using the provided API key,
     * and handles any errors that may occur during the request. If the request is successful, the
     * response data is returned as an array.
     *
     * @param  string  $domain  The domain to look up.
     * @param  int  $page  The page number for paginated results.
     * @return array The domain information retrieved from the API.
     *
     * @throws Exception If the API request fails or the rate limit is exceeded.
     */
    private function makeApiRequest(string $domain, int $page): array
    {
        try {
            $url = sprintf('%s/domain/%s?page=%d', $this->host, $domain, $page);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(self::REQUEST_TIMEOUT)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY, function ($exception) {
                    return $exception instanceof ConnectionException ||
                           $exception instanceof RequestException;
                })
                ->get($url);

            if ($response->failed()) {
                $statusCode = $response->status();
                $errorMessage = $response->json()['error'] ?? $response->body();

                if ($statusCode === 429) {
                    throw new Exception('Rate limit exceeded');
                }

                throw new Exception(sprintf('API request failed with status %d: %s',
                    $statusCode,
                    $errorMessage
                ));
            }

            return $response->json();
        } catch (Exception $e) {
            throw new Exception(
                sprintf('Failed to fetch domain info for %s (page %d): %s', $domain, $page, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Checks if the rate limit has been exceeded.
     *
     * @return bool
     */
    private function isRateLimited(): bool
    {
        if ($this->lastRequestTime === null) {
            return false;
        }

        return (time() - $this->lastRequestTime) < self::RATE_LIMIT_SECONDS;
    }

    /**
     * Updates the timestamp of the last API request.
     */
    private function updateRateLimit(): void
    {
        $this->lastRequestTime = time();
    }
}
