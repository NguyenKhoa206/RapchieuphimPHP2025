<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\CinemaService;

$search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 120) : '');
$status = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$result = CinemaService::movies($search, $status, max(1, (int)($_GET['page'] ?? 1)));
view('layouts/header', ['title' => 'Khám phá phim']);
?>
<section class="hero"><div class="hero-copy"><span class="eyebrow">TRẢI NGHIỆM ĐIỆN ẢNH</span><h1>Chọn bộ phim.<br>Giữ chỗ cho buổi tối.</h1><p>Khám phá phim đang chiếu, chọn suất phù hợp và đặt ghế yêu thích của bạn.</p><a class="button" href="<?= e(url('showtimes.php')) ?>">Khám phá lịch chiếu <span aria-hidden="true">→</span></a><div class="hero-note">Ghế theo từng phòng · Giá vé rõ ràng · Vé trong tài khoản</div></div><div class="hero-poster"><?php $hero = $result['items'][0] ?? null; ?><img src="<?= e(image_url($hero['poster'] ?? null)) ?>" alt="<?= e($hero['title'] ?? 'Galaxy Cinema') ?>"><span class="hero-stamp">HẸN BẠN<br>TẠI RẠP</span></div></section>
<section class="section"><div class="section-heading"><div><span class="eyebrow">MÀN ẢNH LỚN ĐANG CHỜ</span><h2>Phim dành cho bạn</h2></div><span class="muted"><?= $result['total'] ?> bộ phim</span></div>
<form method="get" class="filter-bar"><label class="grow"><span class="sr-only">Tìm tên phim hoặc thể loại</span><input type="search" name="q" maxlength="120" placeholder="Tìm phim hoặc thể loại…" value="<?= e($search) ?>"></label><label><span class="sr-only">Trạng thái</span><select name="status"><option value="showing" <?= $status === 'showing' ? 'selected' : '' ?>>Đang chiếu</option><option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Sắp chiếu</option><option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tất cả phim</option></select></label><button class="button" type="submit">Tìm phim</button></form>
<div class="movie-grid">
<?php foreach ($result['items'] as $movie): ?>
<article class="movie-card"><a class="poster-link" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><img loading="lazy" src="<?= e(image_url($movie['poster'])) ?>" alt="<?= e($movie['title']) ?>"><span class="age-badge"><?= $movie['age_rating'] ? 'T' . (int)$movie['age_rating'] : 'P' ?></span></a><div class="movie-card-copy"><p class="movie-meta"><?= e($movie['genre']) ?> · <?= (int)$movie['duration'] ?> phút</p><h3><a href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><?= e($movie['title']) ?></a></h3><a class="button button-outline button-small" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>">Xem chi tiết & lịch chiếu</a></div></article>
<?php endforeach; ?>
</div>
<?php if (!$result['items']): ?><div class="empty-state"><h3>Chưa tìm thấy phim</h3><p>Thử đổi từ khóa hoặc chọn trạng thái khác.</p></div><?php endif; ?>
<?php if ($result['total'] > 12): ?><nav class="pagination" aria-label="Phân trang"><?php for ($page = 1;$page <= ceil($result['total'] / 12);$page++): ?><a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="<?= e(url('index.php', ['q' => $search, 'status' => $status, 'page' => $page])) ?>"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php view('layouts/footer'); ?>
