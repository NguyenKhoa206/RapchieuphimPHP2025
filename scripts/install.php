<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$c = config('database');
if (!preg_match('/^[a-zA-Z0-9_]+$/', $c['name'])) {
    throw new RuntimeException('Tên CSDL không hợp lệ.');
}
$server = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . $c['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$sql = file_get_contents(APP_ROOT . '/database/schema.sql');
foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
    if (trim($statement) !== '') {
        db()->exec($statement);
    }
}
echo "Đã tạo các bảng cinema_ trong {$c['name']}. Các bảng cũ được giữ nguyên.\n";
