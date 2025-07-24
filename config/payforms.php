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
];
