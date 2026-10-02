<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

$id = isset($_GET['id']) ? request_id() : null;
$show = $id ? D::one('SELECT * FROM cinema_showtimes WHERE id=? AND active=1', [$id]) : [];
if ($id && !$show) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => C::saveShowtime($id, (int)text_input('movie_id', 10), (int)text_input('room_id', 10), text_input('starts_at', 16), (int)text_input('base_price', 7), text_input('language', 60)), 'admin/showtimes.php');
}
$movies = D::all('SELECT id,title FROM cinema_movies WHERE active=1 AND end_date>=CURDATE() ORDER BY title');
$rooms = D::all('SELECT r.id,r.name,c.name AS cinema_name FROM cinema_rooms r JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE r.active=1 AND c.active=1 ORDER BY c.name,r.name');
admin_header('Thiết lập lịch chiếu');
?>
<a class="back-link" href="<?= e(url('admin/showtimes.php')) ?>">← Lịch chiếu</a><div class="page-heading"><h1><?= $id ? 'Sửa lịch chiếu' : 'Thêm lịch chiếu' ?></h1></div><section class="panel admin-form"><form method="post" class="form-stack"><?= csrf_field() ?><div class="form-grid"><label>Phim<select name="movie_id" required><?php foreach ($movies as $movie): ?><option value="<?= (int)$movie['id'] ?>" <?= ($show['movie_id'] ?? null) == $movie['id'] ? 'selected' : '' ?>><?= e($movie['title']) ?></option><?php endforeach; ?></select></label><label>Rạp & phòng<select name="room_id" required><?php foreach ($rooms as $room): ?><option value="<?= (int)$room['id'] ?>" <?= ($show['room_id'] ?? null) == $room['id'] ? 'selected' : '' ?>><?= e($room['cinema_name'] . ' · ' . $room['name']) ?></option><?php endforeach; ?></select></label><label>Giờ bắt đầu<input type="datetime-local" name="starts_at" required value="<?= e(isset($show['starts_at']) ? date('Y-m-d\TH:i', strtotime($show['starts_at'])) : date('Y-m-d\T19:00', strtotime('+1 day'))) ?>"></label><label>Giá cơ bản (đồng)<input type="number" name="base_price" min="1000" max="5000000" step="1000" required value="<?= (int)($show['base_price'] ?? 90000) ?>"></label><label>Ngôn ngữ<select name="language"><?php foreach (['Phụ đề tiếng Việt', 'Lồng tiếng Việt'] as $language): ?><option <?= ($show['language'] ?? '') === $language ? 'selected' : '' ?>><?= e($language) ?></option><?php endforeach; ?></select></label></div><p class="muted">Giờ Việt Nam. Thời gian kết thúc tính theo thời lượng phim; cần 15 phút dọn phòng giữa hai suất. Lịch đã có đơn vé được giữ nguyên.</p><button class="button" type="submit" <?= !$movies || !$rooms ? 'disabled' : '' ?>>Lưu lịch chiếu</button></form></section>
<?php view('layouts/footer'); ?>
