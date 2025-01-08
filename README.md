# DNSDumpster - Laravel Service Provider

A Laravel package for fetching and managing DNS reconnaissance data using the DNSDumpster API. This package simplifies integration with the API, enabling you to query domain-related data directly within your Laravel application.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ngfw/DNSDumpster.svg?style=flat-square)](https://packagist.org/packages/ngfw/DNSDumpster)  
[![Total Downloads](https://img.shields.io/packagist/dt/ngfw/DNSDumpster.svg?style=flat-square)](https://packagist.org/packages/ngfw/DNSDumpster)  
[![StyleCI](https://styleci.io/repos/913631740/shield?branch=main)](https://styleci.io/repos/913631740)

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
```

You can obtain your key here: [dnsdumpster api](https://dnsdumpster.com/developer/)

Alternatively, you can provide the API key and URL dynamically when instantiating the class.

## Usage

Here’s how you can fetch domain data using this package:


1. Using the Facade-like Access via App::make() or resolve():
```php
use Illuminate\Support\Facades\App;

$dnsDumpster = App::make('DNSDumpster');
// or
$dnsDumpster = resolve('DNSDumpster');

// Use the service
$data = $dnsDumpster->fetchData('gm-sunshine.com');
```

2. Using Dependency Injection

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
        $data = $this->dnsDumpster->fetchData($domain);
        return response()->json($data);
    }
}
```
3. Using the `app()` Helper

```php
$dnsDumpster = app('DNSDumpster');
$data = $dnsDumpster->fetchData('gm-sunshine.com');
```

### Rate Limiting

The package includes built-in rate-limiting logic to prevent exceeding the API’s limit of 1 request per 2 seconds. 

### Pagination

For domains with more than 200 host records, use pagination to retrieve additional results. Example:

```php
$domainInfoPage2 = $dnsDumpster->fetchData('gm-sunshine.com', 2);
```

The `fetchData` method accepts an optional `$page` parameter to specify the page number.


## Changelog

Refer to the [CHANGELOG](CHANGELOG.md) for details on recent changes.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for guidelines.

## Credits

-   [Nick Gejadze](https://github.com/ngfw)
-   [All Contributors](../../contributors)

## License

This package is open-sourced software licensed under the [MIT License](LICENSE.md).
