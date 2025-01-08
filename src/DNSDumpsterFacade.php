<?php
namespace Ngfw\DNSDumpster;

use Illuminate\Support\Facades\Facade;

/**
 * Class DNSDumpsterFacade
 * A wrapper for the DNSDumpster API with rate limiting and retry mechanisms.
 * Provides secure and efficient access to DNS reconnaissance data.
 *
 * @package Ngfw\DNSDumpster
 * @author Nick Gejadze
 * @version 1.1
 * @license MIT
 */
class DNSDumpsterFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'DNSDumpster';
    }
}
