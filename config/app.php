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
    'upload_limit' => 25 * 1024 * 1024,
];
