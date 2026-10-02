<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\ReportService as R;

$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
if (!$year || $year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}
$period = in_array($_GET['period'] ?? 'month', ['day', 'month', 'year'], true) ? ($_GET['period'] ?? 'month') : 'month';
$cinema = filter_var($_GET['cinema'] ?? null, FILTER_VALIDATE_INT);
$cinema = $cinema && $cinema > 0 ? $cinema : null;
$modes = ['real' => 'thu thực tế', 'counter' => 'đã thu tại quầy', 'vnpay' => 'VNPay thực', 'sandbox' => 'VNPay Sandbox', 'demo' => 'thử nghiệm'];
$mode = isset($modes[$_GET['mode'] ?? 'real']) ? ($_GET['mode'] ?? 'real') : 'real';
$demo = in_array($mode, ['sandbox', 'demo'], true);
$rows = R::revenue($period, $year, $cinema, $mode);
$total = array_sum(array_column($rows, 'total'));
$orders = array_sum(array_column($rows, 'orders'));
$max = max([1, ...array_column($rows, 'total')]);
$cinemas = D::all('SELECT id,name FROM cinema_cinemas ORDER BY name');
$dataYears = D::all('SELECT DISTINCT YEAR(paid_at) AS year FROM cinema_orders WHERE paid_at IS NOT NULL ORDER BY year DESC');
$years = array_unique([(int)date('Y'), $year, ...array_map(static fn ($row) => (int)$row['year'], $dataYears)]);
rsort($years);
admin_header('Doanh thu');
?>
<div class="page-heading"><span class="eyebrow">THỐNG KÊ</span><h1>Doanh thu <?= e($modes[$mode]) ?></h1><p class="muted">Tính theo ngày xác nhận thanh toán. Mỗi đơn được cộng đúng một lần.</p></div><form method="get" class="filter-bar"><label><span class="sr-only">Năm</span><select name="year"><?php foreach ($years as $value): ?><option <?= $year === $value ? 'selected' : '' ?>><?= $value ?></option><?php endforeach; ?></select></label><label><span class="sr-only">Chu kỳ</span><select name="period"><?php foreach (['day' => 'Theo ngày', 'month' => 'Theo tháng', 'year' => 'Theo năm'] as $value => $label): ?><option value="<?= $value ?>" <?= $period === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label><span class="sr-only">Rạp</span><select name="cinema"><option value="">Tất cả rạp</option><?php foreach ($cinemas as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $cinema === (int)$item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select></label><label><span class="sr-only">Nguồn thanh toán</span><select name="mode"><?php foreach ($modes as $value => $label): ?><option value="<?= e($value) ?>" <?= $mode === $value ? 'selected' : '' ?>><?= e(ucfirst($label)) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Xem thống kê</button></form>
<div class="stats-grid"><article class="stat-card"><span>Tổng doanh thu <?= $year ?></span><strong><?= money($total) ?></strong><small><?= $demo ? 'Không phải tiền thu thực tế' : 'Đơn đã xác nhận thanh toán' ?></small></article><article class="stat-card"><span>Đơn đã thanh toán</span><strong><?= $orders ?></strong><small>Không cộng đơn hủy hoặc hết hạn</small></article></div><section class="panel section"><h2>Doanh thu theo <?= ['day' => 'ngày', 'month' => 'tháng', 'year' => 'năm'][$period] ?></h2><div class="revenue-bars"><?php foreach ($rows as $row): ?><div class="revenue-bar"><strong><?= e($row['label']) ?></strong><div class="bar-track"><meter min="0" max="<?= (int)$max ?>" value="<?= (int)$row['total'] ?>" aria-label="<?= e($row['label']) ?>: <?= money($row['total']) ?>"></meter></div><span><?= money($row['total']) ?></span></div><?php endforeach; ?></div><?php if (!$rows): ?><div class="empty-state">Chưa có doanh thu cho bộ lọc này.</div><?php endif; ?></section>
<?php view('layouts/footer'); ?>
