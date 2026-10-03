<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\NewsService as N;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static function (): void {
        if (($_POST['action'] ?? '') !== 'hide') {
            throw new DomainException('Thao tác không hợp lệ.');
        }
        N::hide(request_id());
    }, 'admin/news.php');
}
$count = (int) D::one('SELECT COUNT(*) AS n FROM cinema_news')['n'];
[$page, $offset] = admin_page($count);
$articles = D::all("SELECT * FROM cinema_news ORDER BY updated_at DESC,id DESC LIMIT 20 OFFSET $offset");
admin_header('Tin mới & ưu đãi');
?>
<div class="section-heading page-heading"><div><span class="eyebrow">NỘI DUNG TRANG CHỦ</span><h1>Tin mới & ưu đãi</h1><p class="muted">Đăng tin, hẹn giờ hoặc ẩn bài đã hết chương trình.</p></div><a class="button" href="<?= e(url('admin/news-edit.php')) ?>">+ Thêm bài viết</a></div>
<div class="panel table-scroll"><table><thead><tr><th>Bài viết</th><th>Loại</th><th>Hiển thị</th><th>Thời gian</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($articles as $article): ?>
<tr><td><div class="inline-title"><img class="admin-thumb news-thumb" src="<?= e(image_url($article['image'])) ?>" alt=""><div><strong><?= e($article['title']) ?></strong><?php if ($article['is_demo']): ?><small>Bài minh họa</small><?php endif; ?></div></div></td><td><?= e(N::CATEGORIES[$article['category']]) ?></td><td><span class="badge"><?= e(N::state($article)) ?></span></td><td><?= date('H:i · d/m/Y', strtotime($article['published_at'])) ?><?php if ($article['expires_at']): ?><br><small>Hết hạn <?= date('H:i · d/m/Y', strtotime($article['expires_at'])) ?></small><?php endif; ?></td><td><a class="button button-outline button-small" href="<?= e(url('admin/news-edit.php', ['id' => $article['id']])) ?>">Sửa</a><?php if ($article['status'] !== 'hidden'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hide"><input type="hidden" name="id" value="<?= (int) $article['id'] ?>"><button class="button button-danger button-small" type="submit" data-confirm="Ẩn bài này khỏi trang khách hàng?">Ẩn bài</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
</tbody></table><?php if (!$articles): ?><div class="empty-state">Chưa có bài viết. Bấm “Thêm bài viết” để đăng tin đầu tiên.</div><?php endif; ?></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/news.php', 'query' => []]); view('layouts/footer'); ?>
