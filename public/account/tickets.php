<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Support\Auth;
use App\Support\Database as D;
use App\Services\HistoryService;

$user = Auth::requireUser();
$tab = in_array($_GET['tab'] ?? 'current', ['current', 'invoices', 'tickets'], true) ? ($_GET['tab'] ?? 'current') : 'current';
$page = max(1, (int) ($_GET['page'] ?? 1));
if ($tab === 'current') {
    $count = (int) D::one('SELECT COUNT(*) AS n FROM cinema_orders WHERE user_id=?', [$user['id']])['n'];
    $page = min($page, max(1, (int) ceil($count / 20)));
    $offset = ($page - 1) * 20;
    $orders = D::all("SELECT o.*,m.title,m.poster,s.starts_at,c.name AS cinema_name,(SELECT GROUP_CONCAT(i.item_name ORDER BY i.id SEPARATOR ', ') FROM cinema_order_items i WHERE i.order_id=o.id AND i.kind='seat') AS seat_names,(SELECT p.environment FROM cinema_payments p WHERE p.order_id=o.id ORDER BY p.id DESC LIMIT 1) AS payment_environment FROM cinema_orders o JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 20 OFFSET $offset", [$user['id']]);
} else {
    ['rows' => $orders, 'count' => $count, 'page' => $page] = HistoryService::listing($tab, $page, (int) $user['id']);
}
$statuses = ['pending' => 'Đang giữ ghế', 'paid' => 'Đã thanh toán', 'expired' => 'Hết hạn', 'cancelled' => 'Đã hủy'];
view('layouts/header', ['title' => 'Vé & hóa đơn của tôi']);
?>
<div class="page-heading"><span class="eyebrow">NHỮNG BUỔI HẸN ĐIỆN ẢNH</span><h1>Vé & hóa đơn của tôi</h1><p class="muted">Đơn đang đặt, vé đã mua và lịch sử của bạn — trong cùng một nơi.</p></div>
<div class="catalog-toolbar"><nav class="tabs" aria-label="Loại đơn & lịch sử">
    <?php foreach (['current' => 'Đơn vé hiện tại', 'invoices' => 'Hóa đơn cũ', 'tickets' => 'Vé cũ'] as $value => $label): ?><a href="<?= e(url('account/tickets.php', ['tab' => $value])) ?>" <?= $tab === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?>
</nav><span class="muted"><?= $count ?> <?= $tab === 'current' ? 'đơn vé' : 'bản ghi' ?></span></div>
<?php if ($tab !== 'current'): ?><div class="alert alert-info">Dữ liệu lịch sử từ hệ thống cũ, chưa đối soát thanh toán. Các bản ghi này không dùng để vào rạp.</div><?php endif; ?>
<div class="ticket-list">
<?php foreach ($orders as $order): ?>
    <?php if ($tab === 'current'): $status = $order['status'] === 'pending' && strtotime($order['expires_at']) <= time() ? 'expired' : $order['status'];
        $testOrder = $order['payment_method'] === 'demo' || $order['payment_environment'] === 'sandbox'; ?>
    <article class="ticket-card">
        <img loading="lazy" width="90" height="130" src="<?= e(image_url($order['poster'])) ?>" alt="">
        <div class="grow"><div class="ticket-card-top"><span class="muted ticket-code"><?= e($order['reference']) ?></span><span class="badge badge-<?= e($status) ?>"><?= e($statuses[$status]) ?></span></div><h2><?= e($order['title']) ?></h2><p><?= e($order['cinema_name']) ?> · <?= date('H:i · d/m/Y', strtotime($order['starts_at'])) ?></p><span class="muted">Ghế <?= e($order['seat_names']) ?><?= $testOrder ? ' · Thử nghiệm' : '' ?></span></div>
        <div class="ticket-card-action"><strong><?= money($order['total']) ?></strong><a class="button button-outline button-small" href="<?= e(url('order.php', ['id' => $order['id']])) ?>">Chi tiết<?= icon('arrow') ?></a></div>
    </article>
    <?php else: ?>
    <article class="ticket-card archive-card"><span class="archive-icon"><?= icon($tab === 'invoices' ? 'archive' : 'ticket') ?></span><div class="grow"><span class="badge badge-warning">Lịch sử cũ</span><h2><?= $tab === 'invoices' ? 'Hóa đơn ' . e($order['source_key']) : e($order['movie_title'] ?? 'Vé ' . $order['source_key']) ?></h2><p>Mã <?= e($order['source_key']) ?> · Nguồn <?= e($order['source_name']) ?></p><span class="muted"><?= e($order['original_status'] ?? 'Chưa có trạng thái thanh toán gốc') ?></span></div><div class="ticket-card-action"><strong><?= $order['amount'] !== null ? money($order['amount']) : 'Chưa rõ số tiền' ?></strong><a class="button button-outline button-small" href="<?= e(url('account/history.php', ['kind' => $tab, 'id' => $order['id']])) ?>">Chi tiết<?= icon('arrow') ?></a></div></article>
    <?php endif; ?>
<?php endforeach; ?>
</div>
<?php if (!$orders): ?><div class="empty-state"><span class="empty-icon"><?= icon($tab === 'current' ? 'ticket' : 'archive') ?></span><h3><?= $tab === 'current' ? 'Chưa có buổi hẹn nào' : 'Chưa có lịch sử được liên kết' ?></h3><p><?= $tab === 'current' ? 'Chọn một bộ phim và giữ chỗ cho trải nghiệm tiếp theo.' : 'Lịch sử sẽ xuất hiện sau khi rạp nhập dữ liệu và liên kết tài khoản cũ.' ?></p><?php if ($tab === 'current'): ?><a class="button" href="<?= e(url('showtimes.php')) ?>">Khám phá lịch chiếu<?= icon('arrow') ?></a><?php endif; ?></div><?php endif; ?>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'account/tickets.php', 'query' => ['tab' => $tab]]);
view('layouts/footer'); ?>
