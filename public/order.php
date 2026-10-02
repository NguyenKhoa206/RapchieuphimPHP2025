<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Auth;
use App\Services\BookingService as B;

$user = Auth::requireUser();
$id = request_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        B::transition($id, text_input('action', 16), (int)$user['id']);
        flash('Đơn vé đã được cập nhật.');
    } catch (DomainException $exception) {
        flash($exception->getMessage(), 'error');
    }
    redirect('order.php', ['id' => $id]);
}
$order = B::order($id, (int)$user['id']);
if (!$order) {
    fail_not_found();
}
view('layouts/header', ['title' => 'Đơn vé ' . $order['reference']]);
view('booking/order', ['order' => $order, 'adminPage' => false]);
view('layouts/footer');
