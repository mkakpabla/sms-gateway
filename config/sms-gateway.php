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

    ],

];
