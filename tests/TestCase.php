<?php

namespace Ngfw\DNSDumpster\Tests;

use Ngfw\DNSDumpster\DNSDumpsterServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Class TestCase.
 */
class TestCase extends BaseTestCase
{
    /**
     * Get package providers.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            DNSDumpsterServiceProvider::class,
        ];
    }

    /**
     * Get the package aliases.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'DNSDumpster' => \Ngfw\DNSDumpster\DNSDumpsterFacade::class,
        ];
    }
}
