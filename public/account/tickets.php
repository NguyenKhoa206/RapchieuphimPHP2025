<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Support\Auth;
use App\Support\Database as D;

$user = Auth::requireUser();
$page = max(1, (int)($_GET['page'] ?? 1));
$count = (int)D::one('SELECT COUNT(*) AS n FROM cinema_orders WHERE user_id=?', [$user['id']])['n'];
$page = min($page, max(1, (int)ceil($count / 20)));
$offset = ($page - 1) * 20;
$orders = D::all("SELECT o.*,m.title,s.starts_at FROM cinema_orders o JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_movies m ON m.id=s.movie_id WHERE o.user_id=? ORDER BY o.id DESC LIMIT 20 OFFSET $offset", [$user['id']]);
view('layouts/header', ['title' => 'Vé của tôi']);
$statuses = ['pending' => 'Đang giữ ghế', 'paid' => 'Đã thanh toán', 'expired' => 'Hết hạn', 'cancelled' => 'Đã hủy'];
?>
<div class="page-heading"><span class="eyebrow">TÀI KHOẢN CỦA BẠN</span><h1>Vé của tôi</h1><p class="muted">Theo dõi đơn vé, thời hạn giữ ghế và trạng thái thanh toán.</p></div>
<div class="panel table-scroll"><table><thead><tr><th>Mã đơn</th><th>Phim</th><th>Suất chiếu</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach ($orders as $order): $status = $order['status'] === 'pending' && strtotime($order['expires_at']) <= time() ? 'expired' : $order['status']; ?><tr><td><?= e($order['reference']) ?></td><td><?= e($order['title']) ?><?= $order['payment_method'] === 'demo' ? ' · Thử nghiệm' : '' ?></td><td><?= date('H:i d/m/Y', strtotime($order['starts_at'])) ?></td><td><?= money($order['total']) ?></td><td><span class="badge badge-<?= e($status) ?>"><?= e($statuses[$status]) ?></span></td><td><a class="button button-outline button-small" href="<?= e(url('order.php', ['id' => $order['id']])) ?>">Chi tiết</a></td></tr><?php endforeach; ?></tbody></table><?php if (!$orders): ?><div class="empty-state"><h3>Bạn chưa có đơn vé</h3><a class="button" href="<?= e(url('showtimes.php')) ?>">Chọn một suất chiếu</a></div><?php endif; ?></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'account/tickets.php', 'query' => []]);
view('layouts/footer'); ?>
