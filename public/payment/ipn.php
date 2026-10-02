<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\PaymentService;

// Provider callbacks have no browser session/CSRF. HMAC and stored amounts authenticate them.
session_write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['RspCode' => '99', 'Message' => 'GET required']);
    exit;
}
echo json_encode(PaymentService::notify($_GET), JSON_THROW_ON_ERROR);
