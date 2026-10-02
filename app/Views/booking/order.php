<?php
use App\Services\PaymentService as P;

$statuses = ['pending' => 'Đang giữ ghế', 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn'];
$payment = $order['payment'];
$online = $order['payment_method'] === 'vnpay';
$sandbox = $online && (($payment['environment'] ?? config('payments.vnpay.environment')) === 'sandbox');
$testOrder = $sandbox || $order['payment_method'] === 'demo';
$seatNames = array_column(array_filter($order['items'], static fn ($item) => $item['kind'] === 'seat'), 'item_name');
?>
<a class="back-link" href="<?= e(url($adminPage ? 'admin/orders.php' : 'account/tickets.php')) ?>">← Danh sách đơn vé</a>
<div class="page-heading">
    <span class="eyebrow">ĐƠN VÉ <?= e($order['reference']) ?></span><h1><?= e($order['title']) ?></h1>
    <span class="badge badge-<?= e($order['status']) ?>"><?= e($statuses[$order['status']] ?? $order['status']) ?></span>
    <?php if ($testOrder): ?><span class="badge badge-warning">Vé thử nghiệm<?= $sandbox ? ' · VNPay Sandbox' : '' ?> · không thu tiền thực</span><?php endif; ?>
</div>
<?php if (($payment['status'] ?? '') === 'review'): ?><div class="alert alert-warning" role="alert">Cổng đã ghi nhận thanh toán nhưng đơn không đủ điều kiện cấp vé. Vui lòng liên hệ rạp với mã đơn <?= e($order['reference']) ?> để đối soát và xử lý hoàn tiền. Mã giao dịch: <?= e($payment['transaction_no']) ?>.</div><?php endif; ?>
<div class="two-columns order-layout">
    <section class="panel ticket-panel">
        <div class="ticket-film"><img width="100" height="145" src="<?= e(image_url($order['poster'])) ?>" alt=""><div><span class="eyebrow">THÔNG TIN SUẤT CHIẾU</span><h2><?= e($order['title']) ?></h2><span class="muted"><?= e($order['language']) ?></span></div></div>
        <dl class="details"><dt>Rạp</dt><dd><?= e($order['cinema_name']) ?></dd><dt>Địa chỉ</dt><dd><?= e($order['address']) ?></dd><dt>Phòng</dt><dd><?= e($order['room_name']) ?></dd><dt>Suất chiếu</dt><dd><?= date('H:i · d/m/Y', strtotime($order['starts_at'])) ?></dd><dt>Người đặt</dt><dd><?= e($order['customer_name']) ?></dd><dt>Thanh toán</dt><dd><?= $online ? 'VNPay' : ($order['payment_method'] === 'demo' ? 'Thử nghiệm' : 'Tại quầy') ?></dd></dl>
        <div class="ticket-seats"><span>GHẾ CỦA BẠN</span><strong><?= e(implode(', ', $seatNames)) ?></strong></div>
        <div class="ticket-reference"><span>MÃ ĐƠN VÉ</span><strong><?= e($order['reference']) ?></strong><small><?= $order['status'] === 'paid' && !$testOrder ? 'Xuất trình mã này tại quầy để kiểm tra vé.' : ($testOrder ? 'Mã thử nghiệm · không dùng để vào rạp.' : 'Mã đặt chỗ · chỉ cấp vé sau khi thanh toán.') ?></small></div>
        <?php if ($order['status'] === 'paid'): ?><button type="button" class="button button-outline" data-print><?= icon('ticket') ?>In thông tin <?= $testOrder ? 'thử nghiệm' : 'vé' ?></button><?php endif; ?>
    </section>
    <section class="panel payment-panel">
        <h2>Chi tiết thanh toán</h2>
        <div class="order-items"><?php foreach ($order['items'] as $item): ?><div><span><?= $item['kind'] === 'seat' ? 'Ghế ' : '' ?><?= e($item['item_name']) ?><small><?= (int) $item['quantity'] ?> × <?= money($item['unit_price']) ?></small></span><strong><?= money((int) $item['quantity'] * (int) $item['unit_price']) ?></strong></div><?php endforeach; ?></div>
        <div class="total-row"><span>Tổng cộng</span><strong><?= money($order['total']) ?></strong></div>
        <?php if ($order['status'] === 'pending'): ?>
            <div class="hold-timer" data-countdown="<?= date('c', strtotime($order['expires_at'])) ?>"><?= icon('clock') ?><div><span>Thời gian giữ ghế còn lại</span><strong data-time-left><?= date('H:i:s', strtotime($order['expires_at'])) ?></strong></div></div>
            <p class="muted">Hoàn tất thanh toán trước <?= date('H:i:s · d/m/Y', strtotime($order['expires_at'])) ?>.</p>
            <?php if ($online && !$adminPage): ?>
                <?php if (P::available() && ($payment['status'] ?? '') !== 'review'): ?>
                <form method="post" action="<?= e(url('payment/start.php')) ?>" class="payment-start-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><button class="button button-block" type="submit"><?= icon('wallet') ?><?= ($payment['status'] ?? '') === 'failed' ? 'Thử lại qua VNPay' : 'Thanh toán qua VNPay' ?><?= icon('arrow') ?></button></form>
                <?php else: ?><p class="alert alert-info">Liên hệ rạp để kiểm tra giao dịch online.</p><?php endif; ?>
                <?php if ($sandbox): ?><p class="muted payment-hint">Bạn sẽ được chuyển đến môi trường thử nghiệm VNPay.</p><?php endif; ?>
            <?php elseif (!$online && !$adminPage && $order['payment_method'] === 'counter'): ?>
                <div class="alert alert-info">Thanh toán tại quầy trước khi thời gian giữ ghế kết thúc.</div>
            <?php endif; ?>
            <?php if ($online && $payment && !$adminPage): ?><p class="payment-progress" role="status" data-order-poll="<?= e(url('api/order-status.php', ['id' => $order['id']])) ?>" data-payment-state="<?= e($payment['status']) ?>"><?= icon('clock') ?><?= $payment['status'] === 'failed' ? 'Giao dịch chưa thành công. Bạn có thể thử lại.' : 'Đang theo dõi xác nhận từ cổng thanh toán…' ?></p><?php endif; ?>
            <form method="post" class="actions"><?= csrf_field() ?>
                <?php if (!$online && ($adminPage || ($order['payment_method'] === 'demo' && config('allow_demo_payment')))): ?><button class="button" name="action" value="pay" type="submit"><?= $adminPage ? 'Xác nhận đã thu tiền' : 'Xác nhận thanh toán thử nghiệm' ?></button><?php endif; ?>
                <button class="button button-danger" name="action" value="cancel" type="submit" data-confirm="Hủy đơn và trả lại ghế? Nếu bạn đang thanh toán qua VNPay, khoản tiền về sau khi hủy sẽ cần rạp đối soát.">Hủy đơn</button>
            </form>
        <?php elseif ($order['status'] === 'paid'): ?>
            <div class="payment-complete"><?= icon('check') ?><div><strong>Thanh toán đã được xác nhận</strong><small><?= date('H:i · d/m/Y', strtotime($order['paid_at'])) ?></small></div></div>
        <?php else: ?>
            <div class="alert alert-info">Đơn đã <?= $order['status'] === 'expired' ? 'hết hạn giữ ghế' : 'hủy' ?>. Bạn có thể đặt lại suất khác.</div>
            <a class="button button-outline" href="<?= e(url('showtimes.php')) ?>">Xem lịch chiếu</a>
        <?php endif; ?>
        <?php if ($payment): ?><dl class="details payment-meta"><dt>Mã giao dịch</dt><dd><?= e($payment['transaction_no'] ?? 'Chờ xác nhận') ?></dd><dt>Ngân hàng</dt><dd><?= e($payment['bank_code'] ?? '—') ?></dd></dl><?php endif; ?>
    </section>
</div>
