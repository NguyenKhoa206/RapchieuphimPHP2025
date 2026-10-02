<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CinemaService;

$search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 120) : '');
$status = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$result = CinemaService::movies($search, $status, max(1, (int) ($_GET['page'] ?? 1)));
$hero = $result['items'][0] ?? null;
view('layouts/header', ['title' => 'Khám phá phim']);
?>
<section class="hero cinema-hero">
    <div class="hero-copy">
        <span class="eyebrow"><span class="live-dot"></span>HẸN BẠN TẠI MÀN ẢNH LỚN</span>
        <h1>Thêm một buổi tối.<br><em>Thêm một câu chuyện.</em></h1>
        <p>Chạm vào thế giới điện ảnh. Chọn phim yêu thích, tìm ghế đẹp và tận hưởng khoảnh khắc của bạn.</p>
        <div class="hero-actions"><a class="button" href="<?= e(url('showtimes.php')) ?>"><?= icon('ticket') ?>Đặt vé ngay<?= icon('arrow') ?></a><a class="hero-text-link" href="#movies">Khám phá phim</a></div>
        <div class="hero-note"><?= icon('check') ?>Chọn ghế trực tiếp<span>·</span>Giá vé rõ ràng</div>
    </div>
    <div class="hero-poster">
        <div class="hero-orbit" aria-hidden="true"></div>
        <?php if ($hero): ?>
        <a href="<?= e(url('movie.php', ['id' => $hero['id']])) ?>" class="hero-feature" aria-label="Xem <?= e($hero['title']) ?>">
            <img fetchpriority="high" width="280" height="400" src="<?= e(image_url($hero['poster'])) ?>" alt="<?= e($hero['title']) ?>">
            <div class="hero-feature-caption"><span><?= $status === 'upcoming' ? 'SẮP RA MẮT' : 'KHÁM PHÁ TẠI GALAXY' ?></span><strong><?= e($hero['title']) ?></strong></div>
        </a>
        <span class="hero-stamp"><?= icon('film') ?>Mỗi bộ phim<br>một thế giới mới</span>
        <?php else: ?>
        <div class="hero-no-film"><?= icon('film') ?><strong>Buổi hẹn tiếp theo<br>đang được chuẩn bị.</strong></div>
        <?php endif; ?>
    </div>
</section>
<section class="section" id="movies">
    <div class="section-heading"><div><span class="eyebrow">CHỌN CÂU CHUYỆN CỦA BẠN</span><h2>Phim tại Galaxy</h2></div><span class="muted"><?= $result['total'] ?> bộ phim</span></div>
    <div class="catalog-toolbar">
        <nav class="tabs" aria-label="Danh mục phim">
            <?php foreach (['showing' => 'Đang chiếu', 'upcoming' => 'Sắp chiếu', 'all' => 'Tất cả'] as $value => $label): ?>
            <a href="<?= e(url('index.php', ['status' => $value, 'q' => $search])) ?>#movies" <?= $status === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="get" class="search-form">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <label class="search-field"><?= icon('search') ?><span class="sr-only">Tìm tên phim hoặc thể loại</span><input type="search" name="q" maxlength="120" placeholder="Tìm phim, thể loại…" value="<?= e($search) ?>"></label>
            <button class="button button-small" type="submit">Tìm</button>
        </form>
    </div>
    <div class="movie-grid">
    <?php foreach ($result['items'] as $movie): ?>
        <article class="movie-card">
            <a class="poster-link" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>">
                <img loading="lazy" decoding="async" width="300" height="430" src="<?= e(image_url($movie['poster'])) ?>" alt="<?= e($movie['title']) ?>">
                <span class="age-badge"><?= $movie['age_rating'] ? 'T' . (int) $movie['age_rating'] : 'P' ?></span>
                <span class="poster-cta"><?= icon('ticket') ?>Xem lịch & đặt vé</span>
            </a>
            <div class="movie-card-copy">
                <p class="movie-meta"><?= e($movie['genre']) ?> <span>·</span> <?= (int) $movie['duration'] ?> phút</p>
                <h3><a href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><?= e($movie['title']) ?></a></h3>
                <a class="button button-outline button-small" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><?= icon('ticket') ?><?= $status === 'upcoming' ? 'Xem chi tiết' : 'Đặt vé' ?></a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if (!$result['items']): ?><div class="empty-state"><span class="empty-icon"><?= icon('search') ?></span><h3>Chưa tìm thấy phim</h3><p>Thử đổi từ khóa hoặc chọn danh mục khác.</p><a class="button button-outline" href="<?= e(url('index.php', ['status' => 'all'])) ?>">Xem tất cả phim</a></div><?php endif; ?>
    <?php if ($result['total'] > 12): ?><nav class="pagination" aria-label="Phân trang"><?php for ($page = 1; $page <= ceil($result['total'] / 12); $page++): ?><a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="<?= e(url('index.php', ['q' => $search, 'status' => $status, 'page' => $page])) ?>#movies"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<section class="visit-banner"><div><?= icon('ticket') ?><h2>Ghế đẹp. Phim hay. Sẵn sàng?</h2><p>Chọn suất chiếu và giữ chỗ cho buổi hẹn tiếp theo.</p></div><a class="button" href="<?= e(url('showtimes.php')) ?>">Xem lịch chiếu<?= icon('arrow') ?></a></section>
<?php view('layouts/footer'); ?>
