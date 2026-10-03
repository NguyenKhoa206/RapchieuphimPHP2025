<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Services\CinemaService as C;

$id = isset($_GET['id']) ? request_id() : null;
$movie = $id ? C::movie($id) : [];
if ($id && !$movie) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => C::saveMovie($id, [
        'title' => text_input('title', 190), 'genre' => text_input('genre', 120),
        'age_rating' => (int) text_input('age_rating', 2), 'duration' => (int) text_input('duration', 3),
        'release_date' => text_input('release_date', 10), 'end_date' => text_input('end_date', 10),
        'description' => text_input('description', 10000), 'trailer_url' => text_input('trailer_url', 255, false),
        'tags' => text_input('tags', 300, false),
        'is_hot' => ($_POST['is_hot'] ?? '') === '1' ? '1' : '0',
        'is_featured' => ($_POST['is_featured'] ?? '') === '1' ? '1' : '0',
        'remove_banner' => ($_POST['remove_banner'] ?? '') === '1' ? '1' : '0',
    ]), 'admin/movie-edit.php', $id ? ['id' => $id] : []);
}
admin_header($id ? 'Sửa phim' : 'Thêm phim');
?>
<a class="back-link" href="<?= e(url('admin/movies.php')) ?>">← Danh sách phim</a>
<div class="page-heading"><h1><?= $id ? 'Sửa thông tin phim' : 'Thêm phim mới' ?></h1></div>
<section class="panel admin-form">
<form method="post" enctype="multipart/form-data" class="form-stack">
    <?= csrf_field() ?>
    <label>Tên phim<input name="title" maxlength="190" required value="<?= e($movie['title'] ?? '') ?>"></label>
    <div class="form-grid">
        <label>Thể loại<input name="genre" maxlength="120" required value="<?= e($movie['genre'] ?? '') ?>"></label>
        <label>Thời lượng (phút)<input type="number" name="duration" min="1" max="600" required value="<?= (int) ($movie['duration'] ?? 100) ?>"></label>
        <label>Giới hạn tuổi<select name="age_rating"><?php foreach ([0 => 'P · Mọi lứa tuổi', 13 => 'T13', 16 => 'T16', 18 => 'T18'] as $value => $label): ?><option value="<?= $value ?>" <?= (int) ($movie['age_rating'] ?? 0) === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Ngày khởi chiếu<input type="date" name="release_date" required value="<?= e($movie['release_date'] ?? date('Y-m-d')) ?>"></label>
        <label>Ngày kết thúc<input type="date" name="end_date" required value="<?= e($movie['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>"></label>
    </div>
    <label>Mô tả<textarea name="description" maxlength="10000" required><?= e($movie['description'] ?? '') ?></textarea></label>
    <fieldset class="movie-display-settings">
        <legend>Hiển thị & phân loại</legend>
        <label>Tag · phân cách bằng dấu phẩy<input name="tags" maxlength="300" placeholder="Hành động, Anime, Phim Việt" value="<?= e(implode(', ', $movie['tags'] ?? [])) ?>"></label>
        <p class="muted">Tối đa 8 tag, mỗi tag 32 ký tự. Để trống để bỏ tag.</p>
        <div class="movie-display-options">
            <label class="checkbox-label"><input type="checkbox" name="is_featured" value="1" <?= !empty($movie['is_featured']) ? 'checked' : '' ?>> Ưu tiên trên banner trang chủ</label>
            <label class="checkbox-label"><input type="checkbox" name="is_hot" value="1" <?= !empty($movie['is_hot']) ? 'checked' : '' ?>> Hiển thị trong mục Phim hot</label>
        </div>
        <label>Ảnh banner ngang · JPG/PNG/WebP<input type="file" name="banner" accept="image/jpeg,image/png,image/webp"></label>
        <p class="muted">Nên dùng ảnh ngang khoảng 1600 × 760 px. Để trống để giữ ảnh hiện tại; phim chưa có banner dùng poster.</p>
        <?php if (!empty($movie['banner'])): ?><img class="admin-banner-preview" src="<?= e(image_url($movie['banner'])) ?>" alt="Banner hiện tại"><label class="checkbox-label"><input type="checkbox" name="remove_banner" value="1"> Bỏ ảnh banner hiện tại</label><?php endif; ?>
        <p class="muted">Top tháng tự cập nhật theo số vé đã thanh toán trong tháng.</p>
    </fieldset>
    <div class="form-grid">
        <label>Ảnh bìa · JPG/PNG/WebP<input type="file" name="poster" accept="image/jpeg,image/png,image/webp"></label>
        <label>Trailer · MP4/WebM<input type="file" name="trailer" accept="video/mp4,video/webm"></label>
    </div>
    <label>Hoặc đường dẫn trailer YouTube<input type="url" name="trailer_url" maxlength="255" placeholder="https://www.youtube.com/watch?v=…" value="<?= e(youtube_trailer_url($movie['trailer'] ?? null) ?? '') ?>"></label>
    <p class="muted">Tối đa 25 MB mỗi file. Trailer tải lên được ưu tiên hơn đường dẫn YouTube. Khi sửa, để trống file và đường dẫn để giữ trailer hiện tại.</p>
    <div class="actions"><button class="button" type="submit">Lưu phim</button><a class="button button-outline" href="<?= e(url('admin/movies.php')) ?>">Về danh sách</a></div>
</form>
</section>
<?php view('layouts/footer'); ?>
