<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Services\BookingService as B;

$show = B::showtime(request_id());
if (!$show) {
    fail_not_found();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(array_map(static fn ($seat) => ['id' => (int)$seat['id'], 'reserved' => (bool)$seat['reserved']], B::seats($show)), JSON_THROW_ON_ERROR);
