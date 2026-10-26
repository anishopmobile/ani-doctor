<?php

return [
    'local_simulator' => env('LOCAL_PAYMENT_SIMULATOR', false),
    'zarinpal' => [
        'enabled' => (bool) env('ZARINPAL_ENABLED', false),
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => (bool) env('ZARINPAL_SANDBOX', true),
        // Fixed deployment setting, never derived from the incoming Host header.
        'callback_url' => env('ZARINPAL_CALLBACK_URL'),
    ],
];
