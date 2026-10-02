<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\PaymentService;
use App\Support\Auth;

$user = Auth::requireUser();
$id = PaymentService::returnOrder($_GET, (int) $user['id']);
if ($id === null) {
    flash('Không xác minh được kết quả từ cổng thanh toán. Kiểm tra đơn vé trong tài khoản hoặc liên hệ rạp.', 'error');
    redirect('account/tickets.php');
}
flash('Đã quay về từ VNPay. Trạng thái trên đơn vé được cập nhật khi rạp nhận thông báo từ cổng thanh toán.', 'info');
redirect('order.php', ['id' => $id]);
