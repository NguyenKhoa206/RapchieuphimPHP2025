<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CinemaService;

$search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 120) : '');
$status = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$result = CinemaService::movies($search, $status, max(1, (int) ($_GET['page'] ?? 1)));
$featured = array_slice($result['items'], 0, 3);
$promotions = [
    ['com.jpg', 'Bắp ngọt vị cốm'],
    ['happyday.jpg', 'Ngày hội điện ảnh Happy Day'],
    ['snack.jpg', 'Bắp nước & món ăn nhẹ'],
    ['thanhvien.jpg', 'Thành viên Galaxy'],
];
view('layouts/header', ['title' => 'Khám phá phim', 'homePage' => true]);
?>
<h1 class="sr-only">Galaxy Cinema · Phim & lịch chiếu</h1>
<section class="movie-carousel" data-carousel role="region" aria-roledescription="băng chuyền" aria-label="Phim nổi bật">
    <?php foreach ($featured as $index => $film): ?>
    <article class="carousel-slide" data-slide role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> / <?= count($featured) ?>" <?= $index > 0 ? 'hidden' : '' ?>>
        <img class="carousel-image" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async" width="1440" height="650" src="<?= e(image_url($film['poster'])) ?>" alt="">
        <div class="carousel-shade" aria-hidden="true"></div>
        <div class="container carousel-caption">
            <span class="carousel-genre"><?= e($film['genre']) ?> · <?= (int) $film['duration'] ?> PHÚT</span>
            <h2><?= e($film['title']) ?></h2>
            <p><?= e(mb_strimwidth($film['description'] ?? '', 0, 190, '…')) ?></p>
            <a class="button carousel-book" href="<?= e(url('movie.php', ['id' => $film['id']])) ?>"><?= icon('ticket') ?><?= strtotime($film['release_date']) > strtotime('today') ? 'Xem chi tiết' : 'Đặt vé ngay' ?></a>
        </div>
    </article>
    <?php endforeach; ?>
    <?php if (!$featured): ?>
    <div class="carousel-empty container"><span class="eyebrow">GALAXY CINEMA</span><h2>Mỗi bộ phim,<br>một trải nghiệm mới.</h2><p>Khám phá phim và chọn suất chiếu cho buổi hẹn của bạn.</p><a class="button carousel-book" href="<?= e(url('showtimes.php')) ?>"><?= icon('ticket') ?>Xem lịch chiếu</a></div>
    <?php endif; ?>
    <?php if (count($featured) > 1): ?>
    <div class="carousel-controls" hidden data-carousel-controls>
        <button class="carousel-arrow carousel-prev" type="button" data-carousel-prev aria-label="Phim trước"><?= icon('chevron-left') ?></button>
        <button class="carousel-arrow carousel-next" type="button" data-carousel-next aria-label="Phim tiếp theo"><?= icon('chevron-right') ?></button>
        <div class="carousel-dots">
            <?php foreach ($featured as $index => $film): ?><button type="button" data-carousel-to="<?= $index ?>" aria-label="Hiển thị <?= e($film['title']) ?>" <?= $index === 0 ? 'aria-current="true"' : '' ?>><span></span></button><?php endforeach; ?>
        </div>
        <span class="sr-only" data-carousel-status role="status" aria-live="polite"></span>
    </div>
    <?php endif; ?>
</section>
<section class="container movie-catalog section" id="movies">
    <div class="catalog-heading">
        <h2>PHIM</h2>
        <nav class="tabs" aria-label="Danh mục phim">
            <?php foreach (['showing' => 'Đang chiếu', 'upcoming' => 'Sắp chiếu', 'all' => 'Tất cả'] as $value => $label): ?>
            <a href="<?= e(url('index.php', ['status' => $value, 'q' => $search])) ?>#movies" <?= $status === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="muted catalog-count"><?= $result['total'] ?> bộ phim</span>
    </div>
    <?php if ($search !== ''): ?><p class="search-result">Kết quả cho “<?= e($search) ?>” <a href="<?= e(url('index.php', ['status' => $status])) ?>#movies">Xóa tìm kiếm</a></p><?php endif; ?>
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
                <p class="movie-release">Khởi chiếu: <?= date('d/m/Y', strtotime($movie['release_date'])) ?></p>
                <a class="button button-outline button-small" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><?= icon('ticket') ?><?= $status === 'upcoming' ? 'Xem chi tiết' : 'Đặt vé' ?></a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <?php if (!$result['items']): ?><div class="empty-state"><span class="empty-icon"><?= icon('search') ?></span><h3>Chưa tìm thấy phim</h3><p>Thử đổi từ khóa hoặc chọn danh mục khác.</p><a class="button button-outline" href="<?= e(url('index.php', ['status' => 'all'])) ?>">Xem tất cả phim</a></div><?php endif; ?>
    <?php if ($result['total'] > 12): ?><nav class="pagination" aria-label="Phân trang"><?php for ($page = 1; $page <= ceil($result['total'] / 12); $page++): ?><a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="<?= e(url('index.php', ['q' => $search, 'status' => $status, 'page' => $page])) ?>#movies"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<section class="promotions section" id="tinmoi">
    <div class="container">
        <div class="section-heading"><h2>TIN MỚI & ƯU ĐÃI</h2></div>
        <p class="muted">Hình ảnh giới thiệu. Vui lòng hỏi rạp về thời gian và điều kiện áp dụng.</p>
        <div class="promotion-grid">
            <?php foreach ($promotions as [$file, $label]): ?>
            <article class="promotion-card"><img loading="lazy" decoding="async" width="400" height="267" src="<?= e(url('assets/images/tinmoikhuyenmai/' . $file)) ?>" alt="<?= e($label) ?>"><h3><?= e($label) ?></h3></article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php view('layouts/footer'); ?>
