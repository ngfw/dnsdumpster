<?php

/*
 * DNSDumpster Configuration
 */
return [
    'DNSDumpster_API_KEY' => env('DNSDumpster_API_KEY', ''),
    'DNSDumpster_API_URL' => env('DNSDumpster_API_URL', 'https://api.DNSDumpster.com/'),
    'DNSDumpster_ENABLE_LOGGING' => env('DNSDumpster_ENABLE_LOGGING', false),
    'DNSDumpster_CACHE_ENABLED' => env('DNSDumpster_CACHE_ENABLED', true),
    'DNSDumpster_CACHE_TTL' => env('DNSDumpster_CACHE_TTL', 3600), // 1 hour in seconds
];
