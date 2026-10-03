<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\NewsService as N;
$category = is_string($_GET['category'] ?? null) && isset(N::CATEGORIES[$_GET['category']]) ? $_GET['category'] : 'all';
['rows' => $articles, 'count' => $count, 'page' => $page] = N::listing($category, max(1, (int) ($_GET['page'] ?? 1)));
view('layouts/header', ['title' => 'Tin mới & ưu đãi']);
?>
<div class="page-heading"><span class="eyebrow">GÓC ĐIỆN ẢNH</span><h1>Tin mới & ưu đãi</h1><p class="muted">Cập nhật hoạt động tại rạp và các chương trình dành cho bạn.</p></div>
<nav class="tabs news-tabs" aria-label="Loại bài viết"><?php foreach (['all' => 'Tất cả', ...N::CATEGORIES] as $value => $label): ?><a href="<?= e(url('news.php', ['category' => $value])) ?>" <?= $category === $value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav>
<div class="promotion-grid news-grid"><?php foreach ($articles as $article): ?><?php view('news/card', ['article' => $article]); ?><?php endforeach; ?></div>
<?php if (!$articles): ?><div class="empty-state"><h2>Chưa có bài viết</h2><p>Tin mới sẽ được cập nhật tại đây.</p></div><?php endif; ?>
<?php if ($count > 12): ?><nav class="pagination" aria-label="Phân trang bài viết"><?php for ($number = 1; $number <= ceil($count / 12); $number++): ?><a class="<?= $number === $page ? 'active' : '' ?>" href="<?= e(url('news.php', ['category' => $category, 'page' => $number])) ?>"><?= $number ?></a><?php endfor; ?></nav><?php endif; ?>
<?php view('layouts/footer'); ?>
