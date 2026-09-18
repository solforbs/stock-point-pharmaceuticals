<?php

/*
|--------------------------------------------------------------------------
| eTIMS — KRA electronic invoicing (Part 13.6)
|--------------------------------------------------------------------------
| Transmission is asynchronous with retry and never blocks a sale. The
| driver is swappable: "log" records every submission locally and issues a
| placeholder control code (development, or while awaiting KRA onboarding),
| "http" transmits to the configured OSCU/VSCU gateway, "null" disables
| transmission while still tracking every document as NOT_CONFIGURED.
*/
return [
    'enabled' => (bool) env('ETIMS_ENABLED', false),

    'driver' => env('ETIMS_DRIVER', 'log'),

    'http' => [
        'base_url' => env('ETIMS_BASE_URL', ''),
        'invoice_path' => env('ETIMS_INVOICE_PATH', '/invoices'),
        'credit_note_path' => env('ETIMS_CREDIT_NOTE_PATH', '/credit-notes'),
        'token' => env('ETIMS_TOKEN', ''),
        'device_serial' => env('ETIMS_DEVICE_SERIAL', ''),
        'timeout_seconds' => (int) env('ETIMS_TIMEOUT', 15),
    ],

    'seller' => [
        'pin' => env('ETIMS_SELLER_PIN', ''),
        'branch_code' => env('ETIMS_BRANCH_CODE', '00'),
    ],

    'retry' => [
        'tries' => (int) env('ETIMS_TRIES', 5),
        'backoff_seconds' => [60, 300, 900, 3600],
    ],
];
