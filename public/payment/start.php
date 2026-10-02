<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\PaymentService;
use App\Support\Auth;

$user = Auth::requireUser();
require_post();
$id = request_id();
try {
    $destination = PaymentService::start($id, (int) $user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
    header('Cache-Control: no-store');
    header('Location: ' . $destination, true, 303);
    exit;
} catch (DomainException $error) {
    flash($error->getMessage(), 'error');
    redirect('order.php', ['id' => $id]);
}
