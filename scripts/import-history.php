<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\HistoryImportService;

$options = getopt('', ['mapping:', 'apply']);
$path = $options['mapping'] ?? APP_ROOT . '/config/history.local.json';
if (!is_string($path) || !is_file($path)) {
    throw new DomainException('Sao chép config/history.example.json thành config/history.local.json và đối chiếu schema. Chạy: php scripts/import-history.php --mapping=path.json [--apply]');
}
$mapping = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
$report = (new HistoryImportService($mapping))->import(array_key_exists('apply', $options));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
