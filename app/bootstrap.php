<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
$config = require APP_ROOT . '/config/app.php';
if (is_file(APP_ROOT . '/config/local.php')) {
    $config = array_replace_recursive($config, require APP_ROOT . '/config/local.php');
}
$GLOBALS['app_config'] = $config;
date_default_timezone_set($config['timezone']);
require_once __DIR__ . '/Support/functions.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
if (PHP_SAPI !== 'cli') {
    session_name('cinema_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => public_base() ?: '/',
    ]);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; media-src 'self'; form-action 'self'; frame-ancestors 'self'; base-uri 'self'");
}
set_exception_handler(static function (Throwable $error): void {
    error_log((string) $error);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Lỗi: " . $error->getMessage() . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Lỗi hệ thống</title><h1>Không thể xử lý yêu cầu</h1><p>Vui lòng thử lại. Quản trị viên có thể kiểm tra nhật ký máy chủ và cấu hình cơ sở dữ liệu.</p></html>';
});
