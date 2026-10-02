<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\BookingService;
use App\Support\Auth;

$user = Auth::requireUser();
$order = BookingService::order(request_id(), (int) $user['id']);
if (!$order) {
    fail_not_found();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_write_close();
echo json_encode(['status' => $order['status'], 'payment_status' => $order['payment']['status'] ?? null], JSON_THROW_ON_ERROR);
