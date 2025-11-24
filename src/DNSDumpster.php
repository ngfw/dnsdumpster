<?php

namespace Ngfw\DNSDumpster;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ngfw\DNSDumpster\Exceptions\ApiException;
use Ngfw\DNSDumpster\Exceptions\ConfigurationException;
use Ngfw\DNSDumpster\Exceptions\InvalidDomainException;
use Ngfw\DNSDumpster\Exceptions\RateLimitException;

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
    private bool $enableLogging = false;
    private bool $cacheEnabled = true;
    private int $cacheTtl = 3600;

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
     * Throws a ConfigurationException if either is missing.
     */
    private function validateConfiguration(): void
    {
        if (empty($this->apiKey) || empty($this->host)) {
            throw new ConfigurationException(
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
        $this->enableLogging = $options['DNSDumpster_ENABLE_LOGGING'] ?? false;
        $this->cacheEnabled = $options['DNSDumpster_CACHE_ENABLED'] ?? true;
        $this->cacheTtl = $options['DNSDumpster_CACHE_TTL'] ?? 3600;

        if ($this->enableLogging) {
            Log::info('DNSDumpster initialized', [
                'host' => $this->host,
                'cache_enabled' => $this->cacheEnabled,
                'cache_ttl' => $this->cacheTtl,
            ]);
        }
    }

    /**
     * Retrieve domain information from DNSDumpster API.
     *
     * @param  string  $domain  The domain to lookup
     * @param  int  $page  Page number for paginated results (default: 1)
     * @param  bool  $forceRefresh  Force refresh from API, bypassing cache (default: false)
     * @return array The domain information
     *
     * @throws InvalidDomainException If domain is invalid
     * @throws ConfigurationException If configuration is missing or invalid
     * @throws RateLimitException If API rate limit is exceeded
     * @throws ApiException If API request fails
     */
    public function fetchData(string $domain, int $page = 1, bool $forceRefresh = false): array
    {
        if ($this->enableLogging) {
            Log::info('Fetching DNS data', [
                'domain' => $domain,
                'page' => $page,
                'force_refresh' => $forceRefresh,
            ]);
        }

        $this->validateConfiguration();
        $this->validateDomain($domain);

        $cacheKey = $this->getCacheKey($domain, $page);

        // Check cache if enabled and not forcing refresh
        if ($this->cacheEnabled && ! $forceRefresh) {
            $cachedData = Cache::get($cacheKey);

            if ($cachedData !== null) {
                if ($this->enableLogging) {
                    Log::info('Returning cached DNS data', [
                        'domain' => $domain,
                        'page' => $page,
                    ]);
                }

                return $cachedData;
            }
        }

        if ($this->isRateLimited()) {
            if ($this->enableLogging) {
                Log::debug('Rate limit enforced, sleeping for ' . self::RATE_LIMIT_SECONDS . ' seconds');
            }
            sleep(self::RATE_LIMIT_SECONDS);
        }

        $data = $this->makeApiRequest($domain, $page);
        $this->updateRateLimit();

        // Store in cache if enabled
        if ($this->cacheEnabled) {
            Cache::put($cacheKey, $data, $this->cacheTtl);

            if ($this->enableLogging) {
                Log::debug('Cached DNS data', [
                    'domain' => $domain,
                    'page' => $page,
                    'ttl' => $this->cacheTtl,
                ]);
            }
        }

        if ($this->enableLogging) {
            Log::info('Successfully fetched DNS data', [
                'domain' => $domain,
                'page' => $page,
            ]);
        }

        return $data;
    }

    /**
     * Validates the provided domain string.
     *
     * This private method checks if the given domain string is not empty and is a valid domain name.
     * If the domain is invalid, an InvalidDomainException is thrown.
     *
     * @param  string  $domain  The domain to validate.
     *
     * @throws InvalidDomainException If the domain is invalid.
     */
    private function validateDomain(string $domain): void
    {
        if (empty($domain) || ! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidDomainException('Invalid domain provided');
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
     * @throws RateLimitException If the API rate limit is exceeded.
     * @throws ApiException If the API request fails.
     */
    private function makeApiRequest(string $domain, int $page): array
    {
        try {
            $url = sprintf('%s/domain/%s?page=%d', $this->host, $domain, $page);

            if ($this->enableLogging) {
                Log::debug('Making API request', ['url' => $url]);
            }

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(self::REQUEST_TIMEOUT)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY, function ($exception) {
                    if ($this->enableLogging) {
                        Log::warning('API request retry', [
                            'exception' => get_class($exception),
                            'message' => $exception->getMessage(),
                        ]);
                    }

                    return $exception instanceof ConnectionException ||
                           $exception instanceof RequestException;
                })
                ->get($url);

            if ($response->failed()) {
                $statusCode = $response->status();
                $errorMessage = $response->json()['error'] ?? $response->body();

                if ($this->enableLogging) {
                    Log::error('API request failed', [
                        'status_code' => $statusCode,
                        'error' => $errorMessage,
                        'domain' => $domain,
                        'page' => $page,
                    ]);
                }

                if ($statusCode === 429) {
                    throw new RateLimitException('Rate limit exceeded', $statusCode);
                }

                throw new ApiException(
                    sprintf('API request failed with status %d: %s', $statusCode, $errorMessage),
                    $statusCode
                );
            }

            return $response->json();
        } catch (RateLimitException | ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($this->enableLogging) {
                Log::error('Unexpected error during API request', [
                    'domain' => $domain,
                    'page' => $page,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }

            throw new ApiException(
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

    /**
     * Generate a cache key for the given domain and page.
     *
     * @param  string  $domain  The domain name
     * @param  int  $page  The page number
     * @return string The cache key
     */
    private function getCacheKey(string $domain, int $page): string
    {
        return sprintf('dnsdumpster:%s:page:%d', strtolower($domain), $page);
    }

    /**
     * Clear cached data for a specific domain.
     *
     * @param  string  $domain  The domain to clear cache for
     * @param  int|null  $page  Optional specific page to clear, or null to clear all pages
     * @return bool
     */
    public function clearCache(string $domain, ?int $page = null): bool
    {
        if ($page !== null) {
            $cacheKey = $this->getCacheKey($domain, $page);

            if ($this->enableLogging) {
                Log::info('Clearing cache for specific page', [
                    'domain' => $domain,
                    'page' => $page,
                ]);
            }

            return Cache::forget($cacheKey);
        }

        // Clear all pages for the domain (approximate approach)
        if ($this->enableLogging) {
            Log::info('Clearing all cache for domain', ['domain' => $domain]);
        }

        $cleared = false;
        for ($i = 1; $i <= 100; $i++) {
            $cacheKey = $this->getCacheKey($domain, $i);
            if (Cache::forget($cacheKey)) {
                $cleared = true;
            }
        }

        return $cleared;
    }

    /**
     * Fetch DNS data for multiple domains.
     *
     * @param  array  $domains  Array of domain names
     * @param  int  $page  Page number for paginated results (default: 1)
     * @param  bool  $forceRefresh  Force refresh from API, bypassing cache (default: false)
     * @return array Array of results keyed by domain name
     *
     * @throws InvalidDomainException If any domain is invalid
     * @throws ConfigurationException If configuration is missing or invalid
     */
    public function fetchBulkData(array $domains, int $page = 1, bool $forceRefresh = false): array
    {
        if ($this->enableLogging) {
            Log::info('Fetching bulk DNS data', [
                'domain_count' => count($domains),
                'page' => $page,
                'force_refresh' => $forceRefresh,
            ]);
        }

        $results = [];
        $errors = [];

        foreach ($domains as $domain) {
            try {
                $results[$domain] = $this->fetchData($domain, $page, $forceRefresh);
            } catch (RateLimitException | ApiException $e) {
                $errors[$domain] = [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ];

                if ($this->enableLogging) {
                    Log::warning('Bulk fetch failed for domain', [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            } catch (InvalidDomainException $e) {
                $errors[$domain] = [
                    'error' => $e->getMessage(),
                    'code' => 0,
                ];

                if ($this->enableLogging) {
                    Log::warning('Invalid domain in bulk fetch', [
                        'domain' => $domain,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($this->enableLogging) {
            Log::info('Bulk DNS data fetch completed', [
                'successful' => count($results),
                'failed' => count($errors),
            ]);
        }

        return [
            'results' => $results,
            'errors' => $errors,
        ];
    }
}
