<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Database as D;
use App\Services\CinemaService;

$movie = CinemaService::movie(request_id());
if (!$movie) {
    fail_not_found();
}
$showtimes = CinemaService::showtimes((int)$movie['id']);
$past = $movie['end_date'] < date('Y-m-d');
view('layouts/header', ['title' => $movie['title']]);
?>
<a class="back-link" href="<?= e(url()) ?>">← Tất cả phim</a>
<section class="movie-detail <?= $past ? 'movie-detail-past' : '' ?>"><img class="detail-poster" src="<?= e(image_url($past && $movie['banner'] ? $movie['banner'] : $movie['poster'])) ?>" alt="<?= e($movie['title']) ?>"><div><span class="eyebrow">PHIM TẠI GALAXY</span><h1><?= e($movie['title']) ?></h1><div class="tag-row"><span class="tag"><?= $movie['age_rating'] ? 'T' . (int)$movie['age_rating'] : 'P' ?></span><span class="tag"><?= (int)$movie['duration'] ?> phút</span><span class="tag"><?= e($movie['genre']) ?></span><?php foreach ($movie['tags'] as $tag): ?><a class="tag-chip" href="<?= e(url('index.php', ['status' => 'all', 'tag' => $tag])) ?>#movies"><?= e($tag) ?></a><?php endforeach; ?></div><p class="description"><?= nl2br(e($movie['description'])) ?></p><p class="muted">Khởi chiếu <?= date('d/m/Y', strtotime($movie['release_date'])) ?></p><?php if ($past): ?><p class="muted">Phim đã kết thúc lịch chiếu. Bạn có thể xem lại thông tin và trailer.</p><a class="button button-outline" href="<?= e(url('index.php')) ?>#movies">Khám phá phim đang chiếu</a><?php else: ?><a class="button" href="#showtimes">Chọn suất chiếu</a><?php endif; ?></div></section>
<?php if ($movie['trailer']): $youtube = youtube_trailer_url($movie['trailer']); ?><section class="section" id="trailer"><h2>Trailer</h2><?php if ($youtube): ?><a class="button" href="<?= e($youtube) ?>" target="_blank" rel="noopener noreferrer"><?= icon('play') ?>Xem trailer trên YouTube</a><?php else: ?><video class="trailer" controls preload="metadata" poster="<?= e(image_url($movie['poster'])) ?>"><source src="<?= e(image_url($movie['trailer'])) ?>"></video><?php endif; ?></section><?php endif; ?>
<section class="section" id="showtimes"><div class="section-heading"><h2>Lịch chiếu</h2><span class="muted">Giờ Việt Nam · Có tên phòng và giá vé</span></div><?php view('movies/showtimes', ['showtimes' => $showtimes]); ?></section>
<?php view('layouts/footer'); ?>
