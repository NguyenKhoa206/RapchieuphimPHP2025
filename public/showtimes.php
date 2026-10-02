<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CinemaService;
use App\Support\Database as D;

$date = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    $date = '';
}
$cinema = filter_var($_GET['cinema'] ?? null, FILTER_VALIDATE_INT);
$cinema = $cinema && $cinema > 0 ? $cinema : null;
$cinemas = D::all('SELECT id,name FROM cinema_cinemas WHERE active=1 ORDER BY name');
$showtimes = CinemaService::showtimes(null, $date ?: null, $cinema);
view('layouts/header', ['title' => 'Lịch chiếu']);
?>
<div class="page-heading">
    <span class="eyebrow">MỘT BUỔI HẸN VỚI ĐIỆN ẢNH</span>
    <h1>Lịch chiếu & đặt vé</h1>
    <p class="muted">Chọn ngày, chọn rạp. Ghế đẹp đang chờ bạn.</p>
</div>
<form method="get" class="filter-bar schedule-filters">
    <label>Ngày chiếu<input type="date" name="date" value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>"></label>
    <label class="grow">Rạp chiếu<select name="cinema"><option value="">Tất cả rạp</option>
        <?php foreach ($cinemas as $item): ?><option value="<?= (int) $item['id'] ?>" <?= $cinema === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php endforeach; ?>
    </select></label>
    <button class="button" type="submit"><?= icon('search') ?>Xem lịch chiếu</button>
    <?php if ($date || $cinema): ?><a class="muted" href="<?= e(url('showtimes.php')) ?>">Xóa bộ lọc</a><?php endif; ?>
</form>
<div class="date-strip" aria-label="Chọn nhanh ngày chiếu">
    <a href="<?= e(url('showtimes.php', ['cinema' => $cinema])) ?>" <?= !$date ? 'aria-current="date"' : '' ?>>Tất cả<span>Ngày sắp tới</span></a>
    <?php for ($i = 0; $i < 7; $i++): $day = date('Y-m-d', strtotime('+' . $i . ' days')); ?>
        <a href="<?= e(url('showtimes.php', ['date' => $day, 'cinema' => $cinema])) ?>" <?= $date === $day ? 'aria-current="date"' : '' ?>><strong><?= $i === 0 ? 'Hôm nay' : ($i === 1 ? 'Ngày mai' : (date('N', strtotime($day)) === '7' ? 'Chủ nhật' : 'Thứ ' . ((int) date('N', strtotime($day)) + 1))) ?></strong><span><?= date('d/m', strtotime($day)) ?></span></a>
    <?php endfor; ?>
</div>
<?php view('movies/showtimes', ['showtimes' => $showtimes]);
view('layouts/footer'); ?>
