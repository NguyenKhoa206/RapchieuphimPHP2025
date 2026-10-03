<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\NewsService as N;
$article = N::published(request_id());
if (!$article) {
    fail_not_found();
}
view('layouts/header', ['title' => $article['title']]);
?>
<a class="back-link" href="<?= e(url('news.php')) ?>">← Tin mới & ưu đãi</a>
<article class="news-detail">
    <header><span class="eyebrow"><?= e(N::CATEGORIES[$article['category']]) ?></span><h1><?= e($article['title']) ?></h1><p class="muted">Đăng <?= date('d/m/Y', strtotime($article['published_at'])) ?><?php if ($article['expires_at']): ?> · Áp dụng đến <?= date('H:i · d/m/Y', strtotime($article['expires_at'])) ?><?php endif; ?></p></header>
    <?php if ($article['is_demo']): ?><div class="alert alert-info">Bài minh họa từ giao diện cũ. Liên hệ rạp để biết các ưu đãi đang áp dụng.</div><?php endif; ?>
    <img class="news-cover" src="<?= e(image_url($article['image'])) ?>" width="750" height="500" alt="<?= e($article['title']) ?>">
    <p class="news-lead"><?= e($article['summary']) ?></p><div class="news-body"><?= nl2br(e($article['body'])) ?></div>
</article>
<?php view('layouts/footer'); ?>
