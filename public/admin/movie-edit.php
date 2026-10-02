<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

$id = isset($_GET['id']) ? request_id() : null;
$movie = $id ? D::one('SELECT * FROM cinema_movies WHERE id=? AND active=1', [$id]) : [];
if ($id && !$movie) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => C::saveMovie($id, ['title' => text_input('title', 190), 'genre' => text_input('genre', 120), 'age_rating' => (int)text_input('age_rating', 2), 'duration' => (int)text_input('duration', 3), 'release_date' => text_input('release_date', 10), 'end_date' => text_input('end_date', 10), 'description' => text_input('description', 10000)]), 'admin/movie-edit.php', $id ? ['id' => $id] : []);
}
admin_header($id ? 'Sửa phim' : 'Thêm phim');
?>
<a class="back-link" href="<?= e(url('admin/movies.php')) ?>">← Danh sách phim</a><div class="page-heading"><h1><?= $id ? 'Sửa thông tin phim' : 'Thêm phim mới' ?></h1></div>
<section class="panel admin-form"><form method="post" enctype="multipart/form-data" class="form-stack"><?= csrf_field() ?><label>Tên phim<input name="title" maxlength="190" required value="<?= e($movie['title'] ?? '') ?>"></label><div class="form-grid"><label>Thể loại<input name="genre" maxlength="120" required value="<?= e($movie['genre'] ?? '') ?>"></label><label>Thời lượng (phút)<input type="number" name="duration" min="1" max="600" required value="<?= (int)($movie['duration'] ?? 100) ?>"></label><label>Giới hạn tuổi<select name="age_rating"><?php foreach ([0 => 'P · Mọi lứa tuổi', 13 => 'T13', 16 => 'T16', 18 => 'T18'] as $value => $label): ?><option value="<?= $value ?>" <?= (int)($movie['age_rating'] ?? 0) === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Ngày khởi chiếu<input type="date" name="release_date" required value="<?= e($movie['release_date'] ?? date('Y-m-d')) ?>"></label><label>Ngày kết thúc<input type="date" name="end_date" required value="<?= e($movie['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>"></label></div><label>Mô tả<textarea name="description" maxlength="10000" required><?= e($movie['description'] ?? '') ?></textarea></label><div class="form-grid"><label>Ảnh bìa · JPG/PNG/WebP<input type="file" name="poster" accept="image/jpeg,image/png,image/webp"></label><label>Trailer · MP4/WebM<input type="file" name="trailer" accept="video/mp4,video/webm"></label></div><p class="muted">Tối đa 25 MB mỗi file. Khi sửa, để trống file để giữ nội dung hiện tại.</p><div class="actions"><button class="button" type="submit">Lưu phim</button><a class="button button-outline" href="<?= e(url('admin/movies.php')) ?>">Về danh sách</a></div></form></section>
<?php view('layouts/footer'); ?>
