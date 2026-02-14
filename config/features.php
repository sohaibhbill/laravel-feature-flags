<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Define your application's feature flags here. Each feature can be
    | enabled (true) or disabled (false). When disabled, all associated
    | routes, queries, and relationships will be blocked.
    |
    */

    'flags' => [
        'beta_dashboard' => env('FEATURE_BETA_DASHBOARD', false),
        'advanced_reporting' => env('FEATURE_ADVANCED_REPORTING', false),
        'premium_content' => env('FEATURE_PREMIUM_CONTENT', false),
        'api_v2' => env('FEATURE_API_V2', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Configuration
    |--------------------------------------------------------------------------
    |
    | Future extension: Define different drivers for feature flag storage.
    | Supported: "config", "database", "cache", "redis"
    |
    */

    'driver' => env('FEATURE_DRIVER', 'config'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | When using cache-based drivers, features will be cached to reduce
    | database queries. Set TTL in seconds.
    |
    */

    'cache' => [
        'enabled' => env('FEATURE_CACHE_ENABLED', true),
        'ttl' => env('FEATURE_CACHE_TTL', 3600),
        'prefix' => 'feature_flag:',
    ],

];