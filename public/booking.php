<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Auth;
use App\Support\Database as D;
use App\Services\BookingService as B;

$id = request_id();
if (!Auth::user()) {
    $_SESSION['next_booking'] = $id;
}
$user = Auth::requireUser();
$show = B::showtime($id);
if (!$show) {
    fail_not_found();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        $nonce = text_input('nonce', 64);
        if (!isset($_SESSION['booking_nonce'][$id]) || !hash_equals($_SESSION['booking_nonce'][$id], $nonce)) {
            throw new DomainException('Phiên chọn ghế đã đổi. Vui lòng tải lại trang.');
        }
        if (!is_array($_POST['seats'] ?? []) || !is_array($_POST['combos'] ?? [])) {
            throw new DomainException('Danh sách ghế hoặc combo không hợp lệ.');
        }
        $orderId = B::placeOrder($id, $user, $_POST['seats'] ?? [], $_POST['combos'] ?? [], text_input('method', 16), $nonce);
        redirect('order.php', ['id' => $orderId]);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
} else {
    $_SESSION['booking_nonce'][$id] = bin2hex(random_bytes(32));
}
$seats = B::seats($show);
$combos = D::all('SELECT * FROM cinema_combos WHERE active=1 ORDER BY id');
view('layouts/header', ['title' => 'Chọn ghế']);
?>
<a class="back-link" href="<?= e(url('movie.php', ['id' => $show['movie_id']])) ?>">← Quay lại lịch chiếu</a>
<div class="page-heading"><span class="eyebrow">ĐẶT VÉ · 01 CHỌN GHẾ → 02 XÁC NHẬN</span><h1><?= e($show['title']) ?></h1><p class="muted"><?= e($show['cinema_name']) ?> · <?= e($show['room_name']) ?> · <?= date('H:i · d/m/Y', strtotime($show['starts_at'])) ?></p></div>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" data-booking-form data-base-price="<?= (int)$show['base_price'] ?>" data-seat-url="<?= e(url('api/seats.php', ['id' => $id])) ?>" class="booking-layout"><?= csrf_field() ?><input type="hidden" name="nonce" value="<?= e($_SESSION['booking_nonce'][$id]) ?>">
<div><section class="panel"><div class="section-heading"><h2>Chọn ghế</h2><span class="muted">Tối đa 8 ghế</span></div><div class="screen">MÀN HÌNH</div><div class="seat-scroll"><div class="seat-map">
<?php $lastRow = null;
foreach ($seats as $seat):if ($lastRow !== $seat['row_label']):if ($lastRow !== null): ?></div><?php endif; ?><div class="seat-row"><span class="row-label"><?= e($seat['row_label']) ?></span><?php $lastRow = $seat['row_label'];endif; ?>
<label class="seat-choice" title="<?= e($seat['label']) ?> · <?= money((int)$show['base_price'] + (int)$seat['price_extra']) ?>"><input type="checkbox" name="seats[]" value="<?= (int)$seat['id'] ?>" data-label="<?= e($seat['label']) ?>" data-extra="<?= (int)$seat['price_extra'] ?>" <?= $seat['reserved'] ? 'disabled' : '' ?>><span><?= (int)$seat['seat_number'] ?></span><span class="sr-only">Ghế <?= e($seat['label']) ?><?= $seat['reserved'] ? ', đã có người đặt' : '' ?></span></label>
<?php endforeach;
if ($lastRow !== null): ?></div><?php endif; ?>
</div></div><div class="seat-legend"><span><i class="legend-dot"></i>Trống</span><span><i class="legend-dot selected"></i>Đang chọn</span><span><i class="legend-dot reserved"></i>Đã giữ / đã bán</span></div><p class="muted" data-seat-message role="status">Ghế được kiểm tra lại khi xác nhận đặt vé.</p></section>
<section class="panel section"><h2>Thêm bắp & nước</h2><?php foreach ($combos as $combo): ?><label class="combo-row"><span><strong><?= e($combo['name']) ?></strong><small><?= money($combo['price']) ?></small></span><input type="number" name="combos[<?= (int)$combo['id'] ?>]" min="0" max="10" value="0" data-combo-price="<?= (int)$combo['price'] ?>" aria-label="Số lượng <?= e($combo['name']) ?>"></label><?php endforeach; ?></section></div>
<aside class="panel booking-summary"><span class="eyebrow">ĐƠN VÉ CỦA BẠN</span><h2><?= e($show['title']) ?></h2><dl class="details"><dt>Rạp</dt><dd><?= e($show['cinema_name']) ?></dd><dt>Phòng</dt><dd><?= e($show['room_name']) ?></dd><dt>Suất chiếu</dt><dd><?= date('H:i · d/m/Y', strtotime($show['starts_at'])) ?></dd><dt>Ghế</dt><dd data-selected-seats>Chưa chọn ghế</dd></dl><div class="total-row"><span>Tổng cộng</span><strong data-total>0 ₫</strong></div><label>Thanh toán<select name="method"><option value="counter">Thanh toán tại quầy</option><?php if (config('allow_demo_payment')): ?><option value="demo">Thử nghiệm · không thu tiền</option><?php endif; ?></select></label><p class="muted">Giữ ghế tối đa <?= (int)config('booking_minutes') ?> phút hoặc đến giờ chiếu. Đến quầy thanh toán trước khi hết hạn.</p><button class="button button-block" type="submit" data-booking-submit disabled>Xác nhận đặt vé</button></aside>
</form>
<?php view('layouts/footer'); ?>
