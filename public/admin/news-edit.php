<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\NewsService as N;

$id = isset($_GET['id']) ? request_id() : null;
$article = $id ? D::one('SELECT * FROM cinema_news WHERE id=?', [$id]) : [];
if ($id && !$article) {
    fail_not_found();
}
$form = $article;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    foreach (['title', 'summary', 'body', 'category', 'status', 'published_at', 'expires_at', 'stock_image', 'is_demo'] as $field) {
        $form[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
    }
    try {
        $saved = N::save($id, $form);
        flash('Đã lưu bài viết.');
        redirect('admin/news-edit.php', ['id' => $saved]);
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
} else {
    $form['published_at'] = date('Y-m-d\TH:i', strtotime($article['published_at'] ?? 'now'));
    $form['expires_at'] = !empty($article['expires_at']) ? date('Y-m-d\TH:i', strtotime($article['expires_at'])) : '';
}
admin_header($id ? 'Sửa bài viết' : 'Thêm bài viết');
?>
<a class="back-link" href="<?= e(url('admin/news.php')) ?>">← Danh sách tin & ưu đãi</a>
<div class="page-heading"><h1><?= $id ? 'Sửa bài viết' : 'Thêm bài viết' ?></h1></div>
<section class="panel admin-form">
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-stack" data-news-form><?= csrf_field() ?>
    <label>Tiêu đề<input name="title" maxlength="190" required value="<?= e($form['title'] ?? '') ?>"></label>
    <label>Mô tả ngắn<textarea class="news-summary-input" name="summary" maxlength="300" rows="3"><?= e($form['summary'] ?? '') ?></textarea></label>
    <label>Nội dung bài viết<textarea class="news-body-input" name="body" maxlength="20000" rows="12" required><?= e($form['body'] ?? '') ?></textarea></label>
    <div class="form-grid"><label>Loại bài<select name="category"><?php foreach (N::CATEGORIES as $value => $label): ?><option value="<?= e($value) ?>" <?= ($form['category'] ?? 'news') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label>Trạng thái<select name="status"><?php foreach (N::STATUSES as $value => $label): ?><option value="<?= e($value) ?>" <?= ($form['status'] ?? 'draft') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div>
    <div class="form-grid"><label>Thời gian đăng<input type="datetime-local" name="published_at" required value="<?= e($form['published_at']) ?>"></label><label>Thời gian hết hạn · tùy chọn<input type="datetime-local" name="expires_at" value="<?= e($form['expires_at']) ?>"></label></div>
    <div class="form-grid"><label>Ảnh bìa · JPG/PNG/WebP<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><label>Hoặc chọn ảnh có sẵn<select name="stock_image"><option value=""><?= $id ? 'Giữ ảnh hiện tại' : 'Chọn ảnh bìa' ?></option><?php foreach (N::stockImages() as $file => $label): ?><option value="<?= e($file) ?>" <?= ($form['stock_image'] ?? '') === $file ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></div>
    <p class="muted">Ảnh tải lên được ưu tiên. Tối đa 25 MB; để trống cả hai mục ảnh khi sửa để giữ ảnh hiện tại.</p>
    <?php if (!empty($article['image'])): ?><img class="news-cover-preview" src="<?= e(image_url($article['image'])) ?>" alt="Ảnh bìa hiện tại"><?php endif; ?>
    <label class="checkbox-label"><input type="checkbox" name="is_demo" value="1" <?= !empty($form['is_demo']) ? 'checked' : '' ?>>Đánh dấu là bài minh họa</label>
    <div class="actions"><button class="button" type="submit">Lưu bài viết</button><a class="button button-outline" href="<?= e(url('admin/news.php')) ?>">Về danh sách</a><?php if ($id && N::state($article) === 'Đang hiển thị'): ?><a class="button button-outline" href="<?= e(url('news-detail.php', ['id' => $id])) ?>">Xem bài đã đăng</a><?php endif; ?></div>
</form></section>
<?php view('layouts/footer'); ?>
