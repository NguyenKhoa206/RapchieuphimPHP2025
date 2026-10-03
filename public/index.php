<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\CinemaService as C;
use App\Services\NewsService;

$search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 120) : '');
$status = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'past', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$tag = is_string($_GET['tag'] ?? null) ? mb_substr(trim($_GET['tag']), 0, 32) : '';
$result = C::movies($search, $status, max(1, (int) ($_GET['page'] ?? 1)), $tag);
$browse = $status === 'showing' && $search === '' && $tag === '';
$featured = $browse ? C::featured() : array_slice($result['items'], 0, 3);
$upcoming = $browse ? C::movies('', 'upcoming')['items'] : [];
$hot = $browse ? C::hot() : [];
$top = $browse ? C::monthlyTop() : [];
$past = $browse ? C::movies('', 'past')['items'] : [];
$tags = C::tagOptions();
if ($tag !== '' && !in_array($tag, $tags, true)) {
    $tags[] = $tag;
}
$articles = NewsService::latest();
view('layouts/header', ['title' => 'Khám phá phim', 'homePage' => true]);
?>
<h1 class="sr-only">Galaxy Cinema · Phim & lịch chiếu</h1>
<section class="movie-carousel" data-carousel role="region" aria-roledescription="băng chuyền" aria-label="Phim nổi bật">
    <?php foreach ($featured as $index => $film): $showing = $film['release_date'] <= date('Y-m-d') && $film['end_date'] >= date('Y-m-d'); ?>
    <article class="carousel-slide" data-slide role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> / <?= count($featured) ?>" <?= $index > 0 ? 'hidden' : '' ?>>
        <img class="carousel-image <?= $film['banner'] ? 'carousel-landscape' : '' ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async" width="1600" height="760" src="<?= e(image_url($film['banner'] ?: $film['poster'])) ?>" alt="">
        <div class="carousel-shade" aria-hidden="true"></div>
        <div class="container carousel-caption">
            <span class="carousel-genre"><?= e($film['genre']) ?> · <?= (int) $film['duration'] ?> PHÚT</span>
            <h2><?= e($film['title']) ?></h2>
            <p><?= e(mb_strimwidth($film['description'] ?? '', 0, 190, '…')) ?></p>
            <div class="carousel-actions">
                <a class="button carousel-book" href="<?= e(url('movie.php', ['id' => $film['id']])) ?><?= $showing ? '#showtimes' : '' ?>"><?= icon($showing ? 'ticket' : 'film') ?><?= $showing ? 'Đặt vé ngay' : 'Xem chi tiết' ?></a>
                <?php if ($film['trailer']): ?><a class="button carousel-trailer" href="<?= e(url('movie.php', ['id' => $film['id']])) ?>#trailer"><?= icon('play') ?>PLAY TRAILER</a><?php endif; ?>
            </div>
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
            <?php foreach (['showing' => 'Đang chiếu', 'upcoming' => 'Sắp chiếu', 'past' => 'Phim cũ', 'all' => 'Tất cả'] as $value => $label): ?>
            <a href="<?= e(url('index.php', ['status' => $value, 'q' => $search, 'tag' => $tag])) ?>#movies" <?= $status === $value ? 'aria-current="page"' : '' ?>><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <span class="muted catalog-count"><?= $result['total'] ?> bộ phim</span>
    </div>
    <?php if ($tags): ?><nav class="movie-tag-filter" aria-label="Lọc phim theo tag"><span class="filter-label"><?= icon('filter') ?>Tag</span><a class="tag-chip" href="<?= e(url('index.php', ['status' => $status, 'q' => $search])) ?>#movies" <?= $tag === '' ? 'aria-current="page"' : '' ?>>Tất cả tag</a><?php foreach ($tags as $choice): ?><a class="tag-chip" href="<?= e(url('index.php', ['status' => $status, 'q' => $search, 'tag' => $choice])) ?>#movies" <?= $tag === $choice ? 'aria-current="page"' : '' ?>><?= e($choice) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php if ($browse): ?><nav class="catalog-shortcuts" aria-label="Khám phá thêm phim"><a href="#phimhot"><?= icon('fire') ?>Phim hot</a><a href="#topthang"><?= icon('award') ?>Top tháng <?= date('m') ?></a><a href="#phimcu"><?= icon('clock') ?>Phim cũ</a></nav><?php endif; ?>
    <?php if ($search !== ''): ?><p class="search-result">Kết quả cho “<?= e($search) ?>” <a href="<?= e(url('index.php', ['status' => $status, 'tag' => $tag])) ?>#movies">Xóa tìm kiếm</a></p><?php endif; ?>
    <div class="movie-grid">
    <?php foreach ($result['items'] as $movie): ?><?php view('movies/card', ['movie' => $movie, 'landscape' => $status === 'past']); ?><?php endforeach; ?>
    </div>
    <?php if (!$result['items']): ?><div class="empty-state"><span class="empty-icon"><?= icon('search') ?></span><h3>Chưa tìm thấy phim</h3><p>Thử đổi từ khóa, tag hoặc chọn danh mục khác.</p><a class="button button-outline" href="<?= e(url('index.php', ['status' => 'all'])) ?>#movies">Xem tất cả phim</a></div><?php endif; ?>
    <?php if ($result['total'] > 12): ?><nav class="pagination" aria-label="Phân trang"><?php for ($page = 1; $page <= ceil($result['total'] / 12); $page++): ?><a class="<?= $page === $result['page'] ? 'active' : '' ?>" href="<?= e(url('index.php', ['q' => $search, 'status' => $status, 'tag' => $tag, 'page' => $page])) ?>#movies"><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php if ($browse): ?>
<?php if ($upcoming): ?>
<section class="container upcoming-catalog section movie-collection" id="phimsapchieu">
    <div class="section-heading"><div><span class="collection-kicker">SẮP RA MẮT</span><h2>PHIM SẮP CHIẾU</h2></div><a class="news-all-link" href="<?= e(url('index.php', ['status' => 'upcoming'])) ?>#movies">Xem tất cả<?= icon('arrow') ?></a></div>
    <div class="movie-grid"><?php foreach (array_slice($upcoming, 0, 4) as $movie): ?><?php view('movies/card', ['movie' => $movie]); ?><?php endforeach; ?></div>
</section>
<?php endif; ?>
<section class="hot-movies section movie-collection" id="phimhot"><div class="container">
    <div class="section-heading"><div><span class="collection-kicker">LỰA CHỌN CỦA RẠP</span><h2><?= icon('fire') ?>PHIM HOT</h2></div><a class="news-all-link" href="<?= e(url('index.php', ['status' => 'all'])) ?>#movies">Khám phá thêm<?= icon('arrow') ?></a></div>
    <?php if ($hot): ?><div class="movie-grid"><?php foreach ($hot as $movie): ?><?php view('movies/card', ['movie' => $movie]); ?><?php endforeach; ?></div><?php else: ?><div class="collection-empty"><p>Các phim nổi bật do rạp lựa chọn sẽ được cập nhật tại đây.</p><a href="<?= e(url('index.php', ['status' => 'all'])) ?>#movies">Khám phá danh mục phim <?= icon('arrow') ?></a></div><?php endif; ?>
</div></section>
<section class="container monthly-movies section movie-collection" id="topthang">
    <div class="section-heading"><div><span class="collection-kicker">KHÁN GIẢ TẠI RẠP LỰA CHỌN</span><h2><?= icon('award') ?>TOP THÁNG <?= date('m/Y') ?></h2></div><span class="muted ranking-caption">Theo số vé đã thanh toán trong tháng</span></div>
    <?php if ($top): ?><div class="movie-grid"><?php foreach ($top as $index => $movie): ?><?php view('movies/card', ['movie' => $movie, 'rank' => $index + 1]); ?><?php endforeach; ?></div><?php else: ?><div class="collection-empty"><span class="empty-icon"><?= icon('award') ?></span><h3>Đón chờ top phim tháng này</h3><p>Bảng xếp hạng xuất hiện khi có vé được thanh toán trong tháng.</p><a class="button button-outline" href="<?= e(url('showtimes.php')) ?>">Xem lịch chiếu</a></div><?php endif; ?>
</section>
<section class="past-movies section movie-collection" id="phimcu"><div class="container">
    <div class="section-heading"><div><span class="collection-kicker">KHÁM PHÁ LẠI</span><h2>PHIM CŨ</h2></div><a class="news-all-link" href="<?= e(url('index.php', ['status' => 'past'])) ?>#movies">Xem tất cả<?= icon('arrow') ?></a></div>
    <?php if ($past): ?><div class="movie-grid past-grid"><?php foreach (array_slice($past, 0, 4) as $movie): ?><?php view('movies/card', ['movie' => $movie, 'landscape' => true]); ?><?php endforeach; ?></div><?php else: ?><div class="collection-empty"><p>Các phim đã kết thúc lịch chiếu sẽ xuất hiện tại đây để bạn xem lại thông tin và trailer.</p></div><?php endif; ?>
</div></section>
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
