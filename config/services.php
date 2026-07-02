<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openweather' => [
        'api_key' => env('OPENWEATHER_API_KEY'),
    ],

    'openrouteservice' => [
        'api_key' => env('OPENROUTESERVICE_API_KEY'),
        'base_url' => env('OPENROUTESERVICE_BASE_URL', 'https://api.openrouteservice.org'),
        'throttle_ms' => env('OPENROUTESERVICE_THROTTLE_MS', 1000),
    ],

    'census_geocoder' => [
        'base_url' => env('CENSUS_GEOCODER_BASE_URL', 'https://geocoding.geo.census.gov/geocoder'),
        'benchmark' => env('CENSUS_GEOCODER_BENCHMARK', 'Public_AR_Current'),
        'user_agent' => env('CENSUS_GEOCODER_USER_AGENT', env('APP_NAME', 'Christy Vault Admin') . ' location geocoder'),
    ],

    'plant_locations' => [
        'colma_location_id' => env('COLMA_PLANT_LOCATION_ID'),
        'tulare_location_id' => env('TULARE_PLANT_LOCATION_ID'),
    ],

];
