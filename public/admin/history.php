<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';

use App\Services\HistoryService as H;

$kind = ($_GET['kind'] ?? '') === 'tickets' ? 'tickets' : 'invoices';
if (isset($_GET['id'])) {
    $record = H::detail($kind, request_id());
    if (!$record) {
        fail_not_found();
    }
    admin_header('Lịch sử hóa đơn & vé cũ');
    view('account/history-detail', ['record' => $record, 'kind' => $kind, 'adminPage' => true]);
    view('layouts/footer');
    exit;
}
$summary = H::summary();
$unresolved = ($_GET['unresolved'] ?? '') === '1';
['rows' => $rows, 'count' => $count, 'page' => $page] = H::listing($kind, max(1, (int) ($_GET['page'] ?? 1)), null, $unresolved);
admin_header('Lịch sử hóa đơn & vé cũ');
?>
<div class="page-heading"><span class="eyebrow">LƯU TRỮ & ĐỐI CHIẾU</span><h1>Lịch sử hóa đơn & vé cũ</h1><p class="muted">Dữ liệu gốc được giữ riêng, không tác động ghế hoặc doanh thu mới.</p></div>
<div class="stats-grid">
    <article class="stat-card"><span>Hóa đơn cũ</span><strong><?= (int) $summary['invoices']['n'] ?></strong><small><?= (int) $summary['invoices']['unresolved'] ?> chưa liên kết khách hàng</small></article>
    <article class="stat-card"><span>Vé cũ</span><strong><?= (int) $summary['tickets']['n'] ?></strong><small><?= (int) $summary['tickets']['unlinked'] ?> chưa xác định hóa đơn</small></article>
    <article class="stat-card"><span>Tổng tiền trên hóa đơn nguồn</span><strong><?= money($summary['invoices']['amount']) ?></strong><small>Chưa đối soát thanh toán · <?= (int) $summary['invoices']['missing_amount'] ?> thiếu số tiền</small></article>
</div>
<div class="catalog-toolbar"><nav class="tabs" aria-label="Loại lịch sử">
    <?php foreach (['invoices' => 'Hóa đơn cũ', 'tickets' => 'Vé cũ'] as $value => $label): ?><a href="<?= e(url('admin/history.php', ['kind' => $value])) ?>" <?= $kind === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a><?php endforeach; ?>
</nav><a class="button button-outline button-small" href="<?= e(url('admin/history.php', ['kind' => $kind, 'unresolved' => $unresolved ? '0' : '1'])) ?>"><?= $unresolved ? 'Xem tất cả' : 'Khách hàng chưa liên kết' ?></a></div>
<section class="panel table-scroll"><table><thead><tr><th>Mã gốc</th><th>Khách hàng</th><th>Nguồn</th><th><?= $kind === 'invoices' ? 'Ngày lập' : 'Phim' ?></th><th>Số tiền nguồn</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['source_key']) ?></td><td><?= e($row['customer_name'] ?? 'Chưa liên kết') ?><small class="muted table-note">Mã cũ: <?= e($row['legacy_customer_key'] ?? '—') ?></small></td><td><?= e($row['source_name']) ?></td><td><?= $kind === 'invoices' ? ($row['issued_at'] ? date('d/m/Y', strtotime($row['issued_at'])) : '—') : e($row['movie_title'] ?? '—') ?></td><td><?= $row['amount'] !== null ? money($row['amount']) : '—' ?></td><td><a href="<?= e(url('admin/history.php', ['kind' => $kind, 'id' => $row['id']])) ?>">Đối chiếu →</a></td></tr><?php endforeach; ?>
</tbody></table><?php if (!$rows): ?><div class="empty-state"><span class="empty-icon"><?= icon('archive') ?></span><h3>Chưa có dữ liệu lịch sử phù hợp</h3><p>Nhập dữ liệu bằng công cụ import-history sau khi đối chiếu schema và báo cáo chạy thử.</p></div><?php endif; ?></section>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/history.php', 'query' => ['kind' => $kind, 'unresolved' => $unresolved ? '1' : '0']]);
view('layouts/footer'); ?>
