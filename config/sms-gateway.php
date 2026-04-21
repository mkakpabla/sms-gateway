<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | The default driver used to send SMS messages.
    |
    */
    'default' => env('SMS_DRIVER', 'faster-message'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Chain
    |--------------------------------------------------------------------------
    |
    | Ordered list of drivers to try. If the first fails, the next is used.
    | Leave empty to only use the default driver without fallback.
    |
    */
    'fallback' => [
        'faster-message',
        'afriksms',
        'natyabip'
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each SMS driver. Register new drivers in the
    | SmsGatewayServiceProvider::registerDrivers() method.
    |
    */
    'drivers' => [

        'faster-message' => [
            'from' => env('FASTER_MESSAGE_FROM', ''),
            'api_url' => env('FASTER_MESSAGE_API_URL', ''),
            'username' => env('FASTER_MESSAGE_USERNAME', ''),
            'password' => env('FASTER_MESSAGE_PASSWORD', ''),
        ],

        'afriksms' => [
            // AfrikSMS requires ClientId/ApiKey in query params for the send endpoint.
            // Use HTTPS and configure infrastructure log redaction for these values.
            'client_id' => env('AFRIKSMS_CLIENT_ID', ''),
            'api_key' => env('AFRIKSMS_API_KEY', ''),
            'sender_id' => env('AFRIKSMS_SENDER_ID', ''),
            'api_url' => env('AFRIKSMS_API_URL', 'https://api.afriksms.com/api/web/web_v1'),
        ],

        'natyabip' => [
            'username' => env('NATYABIP_USERNAME', ''),
            'password' => env('NATYABIP_PASSWORD', ''),
            'from' => env('NATYABIP_FROM', ''),
            'api_url' => env('NATYABIP_API_URL', 'https://api.natyabip.com/smsapiprod_web/FR/api.awp'),
        ],

    ],

];
