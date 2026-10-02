<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';

use App\Services\PaymentService as P;
use App\Support\Database as D;

$status = in_array($_GET['status'] ?? '', ['pending', 'paid', 'failed', 'review'], true) ? $_GET['status'] : '';
$where = $status ? 'p.status=?' : '1=1';
$params = $status ? [$status] : [];
$count = (int) D::one("SELECT COUNT(*) AS n FROM cinema_payments p WHERE $where", $params)['n'];
[$page, $offset] = admin_page($count);
$rows = D::all("SELECT p.*,o.reference,u.name FROM cinema_payments p JOIN cinema_orders o ON o.id=p.order_id JOIN cinema_users u ON u.id=o.user_id WHERE $where ORDER BY p.id DESC LIMIT 20 OFFSET $offset", $params);
$review = D::one("SELECT COUNT(*) AS n,COALESCE(SUM(amount),0) AS amount FROM cinema_payments WHERE status='review' AND environment='production'");
$statuses = ['pending' => 'Chờ cổng xác nhận', 'paid' => 'Thành công', 'failed' => 'Thất bại', 'review' => 'Cần đối soát'];
admin_header('Thanh toán online');
?>
<div class="page-heading"><span class="eyebrow">GIAO DỊCH VNPAY</span><h1>Thanh toán online</h1><p class="muted">Theo dõi thông báo từ cổng và các khoản tiền cần đối soát.</p></div>
<div class="stats-grid">
    <article class="stat-card"><span>Kết nối VNPay</span><strong><?= P::available() ? (P::sandbox() ? 'Sandbox' : 'Production') : 'Chưa cấu hình' ?></strong><small><?= P::available() && P::sandbox() ? 'Thử nghiệm · không phải doanh thu thực' : 'Cấu hình riêng trên máy chủ' ?></small></article>
    <article class="stat-card"><span>Giao dịch thực cần đối soát</span><strong><?= (int) $review['n'] ?></strong><small><?= money($review['amount']) ?> · chưa cấp vé</small></article>
</div>
<?php if ((int) $review['n'] > 0): ?><div class="alert alert-warning">Có khoản tiền về khi đơn không còn đủ điều kiện cấp vé. Đối chiếu mã giao dịch trong hệ thống VNPay và xử lý hoàn tiền theo quy trình của rạp.</div><?php endif; ?>
<form method="get" class="filter-bar"><label>Trạng thái<select name="status"><option value="">Tất cả giao dịch</option><?php foreach ($statuses as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Lọc giao dịch</button></form>
<section class="panel table-scroll"><table><thead><tr><th>Đơn vé</th><th>Khách hàng</th><th>Số tiền</th><th>Mã VNPay / Ngân hàng</th><th>Trạng thái</th><th>Thời gian</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><a href="<?= e(url('admin/order.php', ['id' => $row['order_id']])) ?>"><?= e($row['reference']) ?></a><small class="table-note muted"><?= e($row['txn_ref']) ?></small></td><td><?= e($row['name']) ?></td><td><?= money($row['amount']) ?><?php if ($row['environment'] === 'sandbox'): ?><span class="table-note badge badge-warning">Sandbox</span><?php endif; ?></td><td><?= e($row['transaction_no'] ?? '—') ?><small class="table-note muted"><?= e($row['bank_code'] ?? '—') ?></small></td><td><span class="badge badge-<?= e($row['status'] === 'review' ? 'warning' : ($row['status'] === 'failed' ? 'cancelled' : $row['status'])) ?>"><?= e($statuses[$row['status']]) ?></span><?php if ($row['review_reason']): ?><small class="table-note"><?= e($row['review_reason']) ?></small><?php endif; ?></td><td><?= date('H:i · d/m/Y', strtotime($row['confirmed_at'] ?? $row['created_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table><?php if (!$rows): ?><div class="empty-state"><span class="empty-icon"><?= icon('wallet') ?></span><h3>Chưa có giao dịch phù hợp</h3><p>Giao dịch sẽ xuất hiện khi khách mở trang thanh toán VNPay.</p></div><?php endif; ?></section>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/payments.php', 'query' => ['status' => $status]]);
view('layouts/footer'); ?>
