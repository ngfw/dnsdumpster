<?php
namespace Ngfw\DNSDumpster;

use Ngfw\DNSDumpster\DNSDumpster;
use Illuminate\Support\ServiceProvider;
/**
 * Class DNSDumpsterServiceProvider
 * A wrapper for the DNSDumpster API with rate limiting and retry mechanisms.
 * Provides secure and efficient access to DNS reconnaissance data.
 *
 * @package Ngfw\DNSDumpster
 * @author Nick Gejadze
 * @version 1.1
 * @license MIT
 */
class DNSDumpsterServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->publishConfig();
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'DNSDumpster');
        $this->registerDNSDumpster();
    }

    /**
     * Publish config file.
     */
    private function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path('DNSDumpster.php'),
            ], 'dnsdumpster-config');
        }
    }

    /**
     * Register DNSDumpster singleton.
     */
    private function registerDNSDumpster(): void
    {
        $this->app->singleton(DNSDumpster::class, function ($app): DNSDumpster {
            return new DNSDumpster(config('DNSDumpster'));
        });
        $this->app->alias(DNSDumpster::class, 'DNSDumpster');
    }

    /**
     * Get config file path.
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/../config/config.php';
    }
}
