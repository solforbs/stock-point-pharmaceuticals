<?php

/*
|--------------------------------------------------------------------------
| eTIMS — KRA electronic invoicing (Part 13.6)
|--------------------------------------------------------------------------
| Transmission is asynchronous with retry and never blocks a sale. The
| driver is swappable: "log" records every submission locally and issues a
| placeholder control code (development, or while awaiting KRA onboarding),
| "oscu" files KRA's own format with each branch's OSCU device (see below),
| "http" posts the canonical payload to a third-party gateway, "null" disables
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

    /*
    | KRA's OSCU API (driver "oscu"). Each branch's device — PIN, branch id,
    | serial and communication key — lives in etims_devices, so one server
    | files for every institution. The environment of a device picks the
    | base URL. When KRA serves the API through its GavaConnect gateway,
    | set the consumer key and secret and every call carries a bearer token.
    */
    'oscu' => [
        'urls' => [
            'sandbox' => env('ETIMS_OSCU_SANDBOX_URL', 'https://etims-api-sbx.kra.go.ke/etims-api'),
            'production' => env('ETIMS_OSCU_PRODUCTION_URL', 'https://etims-api.kra.go.ke/etims-api'),
        ],
        // The receipt QR opens KRA's own copy of the receipt; tin + bhfId + rcptSign are appended.
        'receipt_urls' => [
            'sandbox' => env('ETIMS_OSCU_SANDBOX_RECEIPT_URL', 'https://etims-sbx.kra.go.ke/common/link/etims/receipt/indexEtimsReceiptData?Data='),
            'production' => env('ETIMS_OSCU_PRODUCTION_RECEIPT_URL', 'https://etims.kra.go.ke/common/link/etims/receipt/indexEtimsReceiptData?Data='),
        ],
        'gateway' => [
            'token_url' => env('ETIMS_GATEWAY_TOKEN_URL', ''),
            'consumer_key' => env('ETIMS_GATEWAY_CONSUMER_KEY', ''),
            'consumer_secret' => env('ETIMS_GATEWAY_CONSUMER_SECRET', ''),
        ],
        'timeout_seconds' => (int) env('ETIMS_TIMEOUT', 20),
        // Used for a product that has not been given its own KRA details.
        'defaults' => [
            'item_class' => env('ETIMS_DEFAULT_ITEM_CLASS', ''),
            'item_type' => env('ETIMS_DEFAULT_ITEM_TYPE', '2'),        // 1 raw material, 2 finished product, 3 service
            'packaging_unit' => env('ETIMS_DEFAULT_PACKAGING_UNIT', 'NT'),
            'quantity_unit' => env('ETIMS_DEFAULT_QUANTITY_UNIT', 'U'),
            'origin_country' => env('ETIMS_DEFAULT_ORIGIN_COUNTRY', 'KE'),
            'refund_reason' => env('ETIMS_DEFAULT_REFUND_REASON', '06'),
        ],
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
