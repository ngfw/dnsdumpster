<?php

namespace Ngfw\DNSDumpster\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Ngfw\DNSDumpster\DNSDumpster;
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
     * Test invalid domain (should throw InvalidArgumentException).
     *
     * @return void
     */
    public function testFetchDataWithInvalidDomain()
    {
        $this->expectException(InvalidArgumentException::class);

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
        $this->expectException(Exception::class);

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
}
