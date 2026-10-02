<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Services\BookingService as B;

$id = request_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => B::transition($id, text_input('action', 16), null, (int)$admin['id']), 'admin/order.php', ['id' => $id]);
}
$order = B::order($id);
if (!$order) {
    fail_not_found();
}admin_header('Chi tiết đơn vé');
view('booking/order', ['order' => $order, 'adminPage' => true]);
view('layouts/footer');
