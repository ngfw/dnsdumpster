<?php

namespace Ngfw\DNSDumpster\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Ngfw\DNSDumpster\DNSDumpsterServiceProvider;

/**
 * Class TestCase
 * @package Ngfw\DNSDumpster\Tests
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
