<?php

// Copy to local.php. This local configuration is excluded from Git.
return [
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'RapChieuPhim',
        'user' => 'root',
        'password' => '',
    ],
    // Example for XAMPP: /rapchieuphim/public. Leave blank for auto detection.
    'base_path' => '',
    'allow_demo_payment' => false,
    'payments' => [
        'vnpay' => [
            'enabled' => false,
            'environment' => 'sandbox', // sandbox | production
            'tmn_code' => '',
            'hash_secret' => '',
            'return_url' => 'https://your-domain.example/payment/return.php',
        ],
    ],
];
