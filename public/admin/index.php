<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\ReportService;

$movies = (int)D::one('SELECT COUNT(*) AS n FROM cinema_movies WHERE active=1')['n'];
$shows = (int)D::one('SELECT COUNT(*) AS n FROM cinema_showtimes WHERE active=1 AND starts_at>NOW()')['n'];
$pending = (int)D::one("SELECT COUNT(*) AS n FROM cinema_orders WHERE status='pending' AND expires_at>NOW()")['n'];
$revenueRows = ReportService::revenue('day', (int) date('Y'), null, 'real');
$revenue = array_sum(array_column(array_filter($revenueRows, static fn ($row) => $row['label'] === date('Y-m-d')), 'total'));
$reviewCount = (int) D::one("SELECT COUNT(*) AS n FROM cinema_payments WHERE status='review'")['n'];
$recent = D::all('SELECT o.*,u.name,m.title FROM cinema_orders o JOIN cinema_users u ON u.id=o.user_id JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_movies m ON m.id=s.movie_id ORDER BY o.id DESC LIMIT 8');
admin_header('Tổng quan');
?>
<div class="page-heading"><span class="eyebrow">GALAXY · QUẢN TRỊ</span><h1>Tổng quan hôm nay</h1><p class="muted"><?= date('d/m/Y') ?> · Giờ Việt Nam</p></div>
<?php if ($reviewCount): ?><div class="alert alert-warning">Có <?= $reviewCount ?> giao dịch cần đối soát. <a href="<?= e(url('admin/payments.php', ['status' => 'review'])) ?>">Kiểm tra giao dịch →</a></div><?php endif; ?>
<div class="stats-grid"><?php foreach ([['Phim đang quản lý', $movies, 'Phim hiện hoạt động'], ['Suất chiếu sắp tới', $shows, 'Theo lịch trong hệ thống'], ['Đơn chờ thanh toán', $pending, 'Đang còn thời hạn giữ ghế'], ['Doanh thu hôm nay', money($revenue), 'Tại quầy + VNPay thực · đã xác nhận']] as [$label,$value,$note]): ?><article class="stat-card"><span><?= e($label) ?></span><strong><?= e($value) ?></strong><small><?= e($note) ?></small></article><?php endforeach; ?></div>
<section class="section panel"><div class="section-heading"><h2>Đơn vé gần đây</h2><a href="<?= e(url('admin/orders.php')) ?>">Xem tất cả →</a></div><div class="table-scroll"><table><thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Phim</th><th>Tổng tiền</th><th></th></tr></thead><tbody><?php foreach ($recent as $order): ?><tr><td><?= e($order['reference']) ?></td><td><?= e($order['name']) ?></td><td><?= e($order['title']) ?></td><td><?= money($order['total']) ?></td><td><a href="<?= e(url('admin/order.php', ['id' => $order['id']])) ?>">Chi tiết →</a></td></tr><?php endforeach; ?></tbody></table><?php if (!$recent): ?><div class="empty-state">Chưa có đơn vé.</div><?php endif; ?></div></section>
<?php view('layouts/footer'); ?>
