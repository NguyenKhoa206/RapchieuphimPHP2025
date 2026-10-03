<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CinemaService;
use App\Services\NewsService;

$search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 120) : '');
$status = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$result = CinemaService::movies($search, $status, max(1, (int) ($_GET['page'] ?? 1)));
$featured = array_slice($result['items'], 0, 3);
$upcoming = $status === 'showing' && $search === '' ? CinemaService::movies('', 'upcoming') : null;
$articles = NewsService::latest();
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
            <?php if ($film['trailer']): ?><a class="button carousel-trailer" href="<?= e(url('movie.php', ['id' => $film['id']])) ?>#trailer"><?= icon('play') ?>Trailer</a><?php endif; ?>
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
    <?php foreach ($result['items'] as $movie): ?><?php view('movies/card', ['movie' => $movie]); ?><?php endforeach; ?>
    </div>
    <?php if (!$result['items']): ?><div class="empty-state"><span class="empty-icon"><?= icon('search') ?></span><h3>Chưa tìm thấy phim</h3><p>Thử đổi từ khóa hoặc chọn danh mục khác.</p><a class="button button-outline" href="<?= e(url('index.php', ['status' => 'all'])) ?>">Xem tất cả phim</a></div><?php endif; ?>
    <?php if ($result['total'] > 12): ?><nav class="pagination" aria-label="Phân trang"><?php for ($page = 1; $page <= ceil($result['total'] / 12); $page++): ?><a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="<?= e(url('index.php', ['q' => $search, 'status' => $status, 'page' => $page])) ?>#movies"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php if ($upcoming && $upcoming['items']): ?>
<section class="container upcoming-catalog section" id="phimsapchieu">
    <div class="section-heading"><h2>PHIM SẮP CHIẾU</h2><a class="news-all-link" href="<?= e(url('index.php', ['status' => 'upcoming'])) ?>#movies">Xem tất cả<?= icon('arrow') ?></a></div>
    <div class="movie-grid"><?php foreach (array_slice($upcoming['items'], 0, 4) as $movie): ?><?php view('movies/card', ['movie' => $movie]); ?><?php endforeach; ?></div>
</section>
<?php endif; ?>
<section class="promotions section" id="tinmoi">
    <div class="container">
        <div class="section-heading"><h2>TIN MỚI & ƯU ĐÃI</h2><a class="news-all-link" href="<?= e(url('news.php')) ?>">Xem tất cả<?= icon('arrow') ?></a></div>
        <?php if ($articles): ?>
        <div class="news-slider" data-news-slider>
            <div class="news-track" id="news-track" tabindex="0" role="region" aria-label="Danh sách tin. Vuốt hoặc cuộn ngang để xem bài khác.">
                <?php foreach ($articles as $article): ?><?php view('news/card', ['article' => $article]); ?><?php endforeach; ?>
            </div>
            <div class="news-slider-controls" data-news-controls hidden><button class="button button-outline button-small" type="button" data-news-prev aria-controls="news-track" aria-label="Bài trước"><?= icon('chevron-left') ?></button><span class="muted" data-news-position role="status" aria-live="polite"></span><button class="button button-outline button-small" type="button" data-news-next aria-controls="news-track" aria-label="Bài tiếp theo"><?= icon('chevron-right') ?></button></div>
        </div>
        <?php else: ?><div class="empty-state"><h3>Chưa có tin mới</h3><p>Tin mới và các chương trình tại rạp sẽ được cập nhật tại đây.</p></div><?php endif; ?>
    </div>
</section>
<?php view('layouts/footer'); ?>
