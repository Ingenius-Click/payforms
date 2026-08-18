<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can specify configuration options for the payforms package.
    |
    */

    'name' => 'PayForm',
    'paid_order_status_class' => env('PAYFORM_PAID_ORDER_STATUS_CLASS', 'Ingenius\Payforms\NewOrderStatuses\PaidOrderStatus'),

    /*
    |--------------------------------------------------------------------------
    | Outbound HTTP settings
    |--------------------------------------------------------------------------
    |
    | Timeouts applied to every request a payform makes to an external payment
    | gateway. Guzzle defaults to waiting forever, which would keep a request
    | (and any database transaction wrapping it) open until the socket dies.
    | Individual payforms may override these via their $connectTimeout and
    | $requestTimeout properties.
    |
    */

    'http' => [
        'connect_timeout' => (float) env('PAYFORMS_HTTP_CONNECT_TIMEOUT', 5),
        'timeout' => (float) env('PAYFORMS_HTTP_TIMEOUT', 15),
    ],
];
