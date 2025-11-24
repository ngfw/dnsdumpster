# DNSDumpster - Laravel Service Provider

A Laravel package for fetching and managing DNS reconnaissance data using the DNSDumpster API. This package simplifies integration with the API, enabling you to query domain-related data directly within your Laravel application.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ngfw/DNSDumpster.svg?style=flat-square)](https://packagist.org/packages/ngfw/DNSDumpster)
[![Total Downloads](https://img.shields.io/packagist/dt/ngfw/DNSDumpster.svg?style=flat-square)](https://packagist.org/packages/ngfw/DNSDumpster)
[![StyleCI](https://styleci.io/repos/913631740/shield?branch=main)](https://styleci.io/repos/913631740)

## Features

- **Rate Limiting**: Built-in rate limiting (1 request per 2 seconds) to comply with API restrictions
- **Caching**: Optional caching support to reduce API calls and improve performance
- **Retry Logic**: Automatic retry mechanism for failed requests
- **Bulk Lookups**: Support for querying multiple domains at once
- **Custom Exceptions**: Specific exception types for better error handling
- **Logging**: Optional logging support for debugging and monitoring
- **CLI Command**: Artisan command for testing and manual lookups
- **Full Test Coverage**: Comprehensive test suite with PHPUnit
- **Static Analysis**: PHPStan integration for code quality
- **CI/CD**: GitHub Actions workflow for automated testing

## Installation

Install the package using Composer:

```bash
composer require ngfw/dnsdumpster
```

The package will automatically register the service provider.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=dnsdumpster-config
```

Add the required environment variables to your `.env` file:

```env
DNSDumpster_API_KEY=your_api_key
DNSDumpster_API_URL=https://api.dnsdumpster.com
DNSDumpster_ENABLE_LOGGING=false
DNSDumpster_CACHE_ENABLED=true
DNSDumpster_CACHE_TTL=3600
```

You can obtain your key here: [dnsdumpster api](https://dnsdumpster.com/developer/)

**Configuration Options:**
- `DNSDumpster_API_KEY`: Your API key (required)
- `DNSDumpster_API_URL`: The API endpoint URL (required)
- `DNSDumpster_ENABLE_LOGGING`: Enable logging for debugging (optional, default: false)
- `DNSDumpster_CACHE_ENABLED`: Enable caching of API responses (optional, default: true)
- `DNSDumpster_CACHE_TTL`: Cache time-to-live in seconds (optional, default: 3600)

## Usage

### Basic Usage

Here's how you can fetch domain data using this package:

#### 1. Using Dependency Injection

```php
namespace App\Http\Controllers;

use Ngfw\DNSDumpster\DNSDumpster;
use Illuminate\Http\JsonResponse;

class DomainController extends Controller
{
    private DNSDumpster $dnsDumpster;

    public function __construct(DNSDumpster $dnsDumpster)
    {
        $this->dnsDumpster = $dnsDumpster;
    }

    public function lookup(string $domain): JsonResponse
    {
        try {
            $data = $this->dnsDumpster->fetchData($domain);
            return response()->json($data);
        } catch (\Ngfw\DNSDumpster\Exceptions\InvalidDomainException $e) {
            return response()->json(['error' => 'Invalid domain'], 400);
        } catch (\Ngfw\DNSDumpster\Exceptions\RateLimitException $e) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        } catch (\Ngfw\DNSDumpster\Exceptions\ApiException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
```

#### 2. Using the Facade-like Access

```php
use Illuminate\Support\Facades\App;

$dnsDumpster = App::make('DNSDumpster');
// or
$dnsDumpster = resolve('DNSDumpster');

// Use the service
$data = $dnsDumpster->fetchData('example.com');
```

#### 3. Using the `app()` Helper

```php
$dnsDumpster = app('DNSDumpster');
$data = $dnsDumpster->fetchData('example.com');
```

### Advanced Features

#### Pagination

For domains with more than 200 host records, use pagination to retrieve additional results:

```php
$domainInfoPage2 = $dnsDumpster->fetchData('example.com', 2);
```

#### Cache Management

Force refresh data from the API, bypassing cache:

```php
$freshData = $dnsDumpster->fetchData('example.com', 1, true);
```

Clear cache for a specific domain:

```php
// Clear cache for page 1
$dnsDumpster->clearCache('example.com', 1);

// Clear cache for all pages
$dnsDumpster->clearCache('example.com');
```

#### Bulk Domain Lookups

Query multiple domains at once:

```php
$domains = ['example.com', 'google.com', 'github.com'];
$result = $dnsDumpster->fetchBulkData($domains);

// Access successful results
foreach ($result['results'] as $domain => $data) {
    echo "Domain: {$domain}\n";
    print_r($data);
}

// Access errors
foreach ($result['errors'] as $domain => $error) {
    echo "Domain: {$domain} - Error: {$error['error']}\n";
}
```

### CLI Command

Use the Artisan command for quick lookups:

```bash
# Single domain lookup
php artisan dnsdumpster:lookup example.com

# With pagination
php artisan dnsdumpster:lookup example.com --page=2

# Force refresh (bypass cache)
php artisan dnsdumpster:lookup example.com --force

# Bulk lookup
php artisan dnsdumpster:lookup "example.com,google.com,github.com" --bulk
```

### Exception Handling

The package provides custom exceptions for better error handling:

- `ConfigurationException`: Thrown when API configuration is missing or invalid
- `InvalidDomainException`: Thrown when an invalid domain is provided
- `RateLimitException`: Thrown when API rate limit is exceeded
- `ApiException`: Thrown when an API request fails

```php
use Ngfw\DNSDumpster\Exceptions\ConfigurationException;
use Ngfw\DNSDumpster\Exceptions\InvalidDomainException;
use Ngfw\DNSDumpster\Exceptions\RateLimitException;
use Ngfw\DNSDumpster\Exceptions\ApiException;

try {
    $data = $dnsDumpster->fetchData('example.com');
} catch (InvalidDomainException $e) {
    // Handle invalid domain
} catch (RateLimitException $e) {
    // Handle rate limit
    $statusCode = $e->getCode(); // HTTP 429
} catch (ApiException $e) {
    // Handle API errors
    $statusCode = $e->getStatusCode();
} catch (ConfigurationException $e) {
    // Handle configuration errors
}
```

### Rate Limiting

The package includes built-in rate-limiting logic to prevent exceeding the API's limit of 1 request per 2 seconds. This is handled automatically and transparently.


## Development

### Running Tests

```bash
composer test
```

### Running PHPStan

```bash
composer phpstan
```

### Generating Code Coverage

```bash
composer test-coverage
```

## API Response Format

The API returns data in the following format:

```json
{
  "domain": "example.com",
  "dns_records": {
    "A": ["192.168.1.1"],
    "MX": ["mail.example.com"]
  },
  "host_records": ["www.example.com", "api.example.com"]
}
```

## Troubleshooting

### Common Issues

**Rate Limit Exceeded**
- The API enforces a rate limit of 1 request per 2 seconds
- The package automatically handles this with built-in rate limiting
- If you still encounter issues, enable caching to reduce API calls

**Invalid API Key**
- Ensure your API key is set correctly in the `.env` file
- Verify the key is valid at [dnsdumpster.com/developer/](https://dnsdumpster.com/developer/)

**Configuration Not Found**
- Run `php artisan vendor:publish --tag=dnsdumpster-config` to publish the config file
- Ensure environment variables are set correctly

### Debugging

Enable logging in your `.env` file:

```env
DNSDumpster_ENABLE_LOGGING=true
```

Then check your Laravel logs for detailed information:

```bash
tail -f storage/logs/laravel.log
```

## Changelog

Refer to the [CHANGELOG](CHANGELOG.md) for details on recent changes.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for guidelines.

## Credits

-   [Nick Gejadze](https://github.com/ngfw)
-   [All Contributors](../../contributors)

## License

This package is open-sourced software licensed under the [MIT License](LICENSE.md).
