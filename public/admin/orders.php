<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;

$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';
$allowed = ['all', 'pending', 'paid', 'cancelled', 'expired'];
if (!in_array($status, $allowed, true)) {
    $status = 'all';
}
$where = '1=1';
$params = [];
if ($status === 'pending') {
    $where = "o.status='pending' AND o.expires_at>NOW()";
} elseif ($status === 'expired') {
    $where = "(o.status='expired' OR (o.status='pending' AND o.expires_at<=NOW()))";
} elseif ($status !== 'all') {
    $where = 'o.status=?';
    $params[] = $status;
}
$count = (int)D::one("SELECT COUNT(*) AS n FROM cinema_orders o WHERE $where", $params)['n'];
[$page,$offset] = admin_page($count);
$orders = D::all("SELECT o.*,u.name,m.title,s.starts_at FROM cinema_orders o JOIN cinema_users u ON u.id=o.user_id JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_movies m ON m.id=s.movie_id WHERE $where ORDER BY o.id DESC LIMIT 20 OFFSET $offset", $params);
$statuses = ['all' => 'Tất cả', 'pending' => 'Đang giữ ghế', 'paid' => 'Đã thanh toán', 'cancelled' => 'Đã hủy', 'expired' => 'Hết hạn'];
admin_header('Quản lý đơn vé');
?>
<div class="page-heading"><span class="eyebrow">ĐẶT VÉ & THANH TOÁN</span><h1>Quản lý đơn vé</h1><p class="muted">Chỉ xác nhận thanh toán sau khi đã thu tiền tại quầy.</p></div><form class="filter-bar" method="get"><label><span class="sr-only">Trạng thái</span><select name="status"><?php foreach ($statuses as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><button class="button button-outline" type="submit">Lọc đơn</button></form><div class="panel table-scroll"><table><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Phim / suất chiếu</th><th>Tổng tiền</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach ($orders as $order):$state = $order['status'] === 'pending' && strtotime($order['expires_at']) <= time() ? 'expired' : $order['status']; ?><tr><td><?= e($order['reference']) ?><?= $order['payment_method'] === 'demo' ? '<br><span class="badge badge-warning">Thử nghiệm</span>' : '' ?></td><td><?= e($order['name']) ?></td><td><?= e($order['title']) ?><br><small class="muted"><?= date('H:i d/m/Y', strtotime($order['starts_at'])) ?></small></td><td><?= money($order['total']) ?></td><td><span class="badge badge-<?= e($state) ?>"><?= e($statuses[$state]) ?></span></td><td><a class="button button-small button-outline" href="<?= e(url('admin/order.php', ['id' => $order['id']])) ?>">Chi tiết</a></td></tr><?php endforeach; ?></tbody></table><?php if (!$orders): ?><div class="empty-state">Chưa có đơn phù hợp.</div><?php endif; ?></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/orders.php', 'query' => ['status' => $status]]);
view('layouts/footer'); ?>
