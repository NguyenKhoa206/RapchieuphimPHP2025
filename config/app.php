<?php

declare(strict_types=1);

return [
    'name' => 'Galaxy Cinema',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'base_path' => getenv('APP_BASE_PATH') ?: '',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'RapChieuPhim',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
    ],
    'booking_minutes' => 15,
    'allow_demo_payment' => getenv('ALLOW_DEMO_PAYMENT') === '1',
    'payments' => [
        'vnpay' => [
            'enabled' => getenv('VNPAY_ENABLED') === '1',
            'environment' => getenv('VNPAY_ENVIRONMENT') ?: 'sandbox',
            'tmn_code' => getenv('VNPAY_TMN_CODE') ?: '',
            'hash_secret' => getenv('VNPAY_HASH_SECRET') ?: '',
            'return_url' => getenv('VNPAY_RETURN_URL') ?: '',
        ],
    ],
    'upload_limit' => 25 * 1024 * 1024,
];
