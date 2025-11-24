<?php

namespace Ngfw\DNSDumpster\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Ngfw\DNSDumpster\DNSDumpster;
use Ngfw\DNSDumpster\Exceptions\ApiException;
use Ngfw\DNSDumpster\Exceptions\ConfigurationException;
use Ngfw\DNSDumpster\Exceptions\InvalidDomainException;
use Ngfw\DNSDumpster\Exceptions\RateLimitException;
use Orchestra\Testbench\TestCase;

/**
 * Class DNSDumpsterTest.
 */
class DNSDumpsterTest extends TestCase
{
    /**
     * Set up the environment for testing.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Setup any mock data or configurations.
        config(['DNSDumpster.DNSDumpster_API_KEY' => 'test-api-key']);
        config(['DNSDumpster.DNSDumpster_API_URL' => 'https://api.dnsdumpster.com']);
    }

    /**
     * Test valid domain lookup.
     *
     * @return void
     */
    public function testFetchDataWithValidDomain()
    {
        // Mock the HTTP request to DNSDumpster
        Http::fake([
            'https://api.dnsdumpster.com/domain/google.com*' => Http::response([
                'domain' => 'google.com',
                'data' => ['mocked data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));

        // Fetch data
        $result = $dnsDumpster->fetchData('google.com');

        // Assert the result
        $this->assertArrayHasKey('domain', $result);
        $this->assertEquals('google.com', $result['domain']);
    }

    /**
     * Test invalid domain (should throw InvalidDomainException).
     *
     * @return void
     */
    public function testFetchDataWithInvalidDomain()
    {
        $this->expectException(InvalidDomainException::class);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));

        // Trying to fetch data for an invalid domain
        $dnsDumpster->fetchData('invalid_domain');
    }

    /**
     * Test when the API request fails (e.g., rate-limited).
     *
     * @return void
     */
    public function testFetchDataWithApiFailure()
    {
        $this->expectException(RateLimitException::class);

        // Mock the API failure (e.g., rate limit exceeded)
        Http::fake([
            'https://api.dnsdumpster.com/domain/google.com*' => Http::response([
                'error' => 'Rate limit exceeded',
            ], 429),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));

        // Attempt to fetch data which will simulate API failure
        $dnsDumpster->fetchData('google.com');
    }

    /**
     * Test rate limiting behavior.
     *
     * @return void
     */
    public function testRateLimitingEnforcesDelay()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/*' => Http::response([
                'domain' => 'example.com',
                'data' => ['mocked data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));

        // First request
        $startTime = microtime(true);
        $dnsDumpster->fetchData('example.com');

        // Second request should be rate limited
        $dnsDumpster->fetchData('example2.com');
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        // Assert that the second request was delayed by at least 2 seconds
        $this->assertGreaterThanOrEqual(2, $duration);
    }

    /**
     * Test pagination functionality.
     *
     * @return void
     */
    public function testFetchDataWithPagination()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com?page=2' => Http::response([
                'domain' => 'example.com',
                'page' => 2,
                'data' => ['page 2 data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $result = $dnsDumpster->fetchData('example.com', 2);

        $this->assertArrayHasKey('page', $result);
        $this->assertEquals(2, $result['page']);
    }

    /**
     * Test that empty domain throws InvalidDomainException.
     *
     * @return void
     */
    public function testEmptyDomainThrowsException()
    {
        $this->expectException(InvalidDomainException::class);
        $this->expectExceptionMessage('Invalid domain provided');

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $dnsDumpster->fetchData('');
    }

    /**
     * Test missing API key throws ConfigurationException.
     *
     * @return void
     */
    public function testMissingApiKeyThrowsException()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required API configuration');

        $dnsDumpster = new DNSDumpster([
            'DNSDumpster_API_KEY' => '',
            'DNSDumpster_API_URL' => 'https://api.dnsdumpster.com',
        ]);

        $dnsDumpster->fetchData('example.com');
    }

    /**
     * Test missing API URL throws ConfigurationException.
     *
     * @return void
     */
    public function testMissingApiUrlThrowsException()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Missing required API configuration');

        $dnsDumpster = new DNSDumpster([
            'DNSDumpster_API_KEY' => 'test-key',
            'DNSDumpster_API_URL' => '',
        ]);

        $dnsDumpster->fetchData('example.com');
    }

    /**
     * Test HTTP 500 error handling.
     *
     * @return void
     */
    public function testServerErrorHandling()
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API request failed with status 500');

        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com*' => Http::response([
                'error' => 'Internal Server Error',
            ], 500),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $dnsDumpster->fetchData('example.com');
    }

    /**
     * Test HTTP 404 error handling.
     *
     * @return void
     */
    public function testNotFoundErrorHandling()
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API request failed with status 404');

        Http::fake([
            'https://api.dnsdumpster.com/domain/nonexistent.com*' => Http::response([
                'error' => 'Domain not found',
            ], 404),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $dnsDumpster->fetchData('nonexistent.com');
    }

    /**
     * Test retry mechanism on connection failure.
     *
     * @return void
     */
    public function testRetryMechanismOnConnectionFailure()
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new ConnectionException('Connection timeout');
            }

            return Http::response([
                'domain' => 'example.com',
                'data' => ['mocked data'],
            ], 200);
        });

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $result = $dnsDumpster->fetchData('example.com');

        // Should succeed after retries
        $this->assertArrayHasKey('domain', $result);
        $this->assertEquals(3, $callCount);
    }

    /**
     * Test API with subdomain.
     *
     * @return void
     */
    public function testFetchDataWithSubdomain()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/api.example.com*' => Http::response([
                'domain' => 'api.example.com',
                'data' => ['subdomain data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $result = $dnsDumpster->fetchData('api.example.com');

        $this->assertArrayHasKey('domain', $result);
        $this->assertEquals('api.example.com', $result['domain']);
    }

    /**
     * Test that trailing slash in API URL is handled correctly.
     *
     * @return void
     */
    public function testApiUrlTrailingSlashHandling()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com*' => Http::response([
                'domain' => 'example.com',
                'data' => ['mocked data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster([
            'DNSDumpster_API_KEY' => 'test-key',
            'DNSDumpster_API_URL' => 'https://api.dnsdumpster.com/', // With trailing slash
        ]);

        $result = $dnsDumpster->fetchData('example.com');

        $this->assertArrayHasKey('domain', $result);
    }

    /**
     * Test that API key is trimmed properly.
     *
     * @return void
     */
    public function testApiKeyTrimming()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com*' => Http::response([
                'domain' => 'example.com',
                'data' => ['mocked data'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster([
            'DNSDumpster_API_KEY' => '  test-key  ', // With spaces
            'DNSDumpster_API_URL' => 'https://api.dnsdumpster.com',
        ]);

        $result = $dnsDumpster->fetchData('example.com');

        $this->assertArrayHasKey('domain', $result);
    }

    /**
     * Test rate limit exception message.
     *
     * @return void
     */
    public function testRateLimitExceptionMessage()
    {
        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com*' => Http::response([
                'error' => 'Too many requests',
            ], 429),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $dnsDumpster->fetchData('example.com');
    }

    /**
     * Test successful response with all expected fields.
     *
     * @return void
     */
    public function testCompleteApiResponse()
    {
        Http::fake([
            'https://api.dnsdumpster.com/domain/example.com*' => Http::response([
                'domain' => 'example.com',
                'dns_records' => [
                    'A' => ['192.168.1.1'],
                    'MX' => ['mail.example.com'],
                ],
                'host_records' => ['www.example.com', 'api.example.com'],
            ], 200),
        ]);

        $dnsDumpster = new DNSDumpster(config('DNSDumpster'));
        $result = $dnsDumpster->fetchData('example.com');

        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('dns_records', $result);
        $this->assertArrayHasKey('host_records', $result);
    }
}
