<?php
$phase = $movie['release_date'] > date('Y-m-d') ? 'upcoming' : ($movie['end_date'] < date('Y-m-d') ? 'past' : 'showing');
$action = $phase === 'showing' ? 'Mua vé' : 'Xem chi tiết';
$target = url('movie.php', ['id' => $movie['id']]);
?>
<article class="movie-card <?= !empty($landscape) ? 'movie-card-landscape' : '' ?>">
    <div class="movie-poster-wrap">
        <a class="poster-link" href="<?= e($target) ?>">
            <img loading="lazy" decoding="async" width="500" height="750" src="<?= e(image_url($movie['poster'])) ?>" alt="<?= e($movie['title']) ?>">
            <span class="age-badge"><?= $movie['age_rating'] ? 'T' . (int) $movie['age_rating'] : 'P' ?></span>
            <?php if (!empty($movie['is_hot'])): ?><span class="movie-hot-badge"><?= icon('fire') ?>HOT</span><?php endif; ?>
            <?php if (isset($rank)): ?><span class="movie-rank" aria-label="Hạng <?= (int) $rank ?> trong tháng"><?= (int) $rank ?></span><?php endif; ?>
        </a>
        <div class="movie-poster-actions">
            <?php if ($movie['trailer']): ?><a class="button movie-trailer-link" href="<?= e($target) ?>#trailer"><?= icon('play') ?>Trailer<span class="sr-only"> <?= e($movie['title']) ?></span></a><?php endif; ?>
            <a class="button" href="<?= e($target) ?><?= $phase === 'showing' ? '#showtimes' : '' ?>"><?= icon($phase === 'showing' ? 'ticket' : 'film') ?><?= $action ?><span class="sr-only"> <?= e($movie['title']) ?></span></a>
        </div>
    </div>
    <div class="movie-card-copy">
        <h3><a href="<?= e($target) ?>"><?= e($movie['title']) ?></a></h3>
        <?php if (!empty($movie['tags'])): ?><div class="movie-card-tags"><?php foreach (array_slice($movie['tags'], 0, 3) as $tag): ?><a class="tag-chip" href="<?= e(url('index.php', ['status' => 'all', 'tag' => $tag])) ?>#movies"><?= e($tag) ?></a><?php endforeach; ?></div><?php endif; ?>
        <p class="movie-meta"><?= e($movie['genre']) ?> <span>·</span> <?= (int) $movie['duration'] ?> phút</p>
        <p class="movie-release"><?= $phase === 'past' ? 'Đã chiếu' : 'Khởi chiếu' ?>: <?= date('d/m/Y', strtotime($movie['release_date'])) ?></p>
        <?php if (isset($movie['tickets'])): ?><p class="movie-ticket-count"><?= number_format((int) $movie['tickets'], 0, ',', '.') ?> vé trong tháng</p><?php endif; ?>
        <div class="movie-card-buttons"><?php if ($movie['trailer']): ?><a class="button button-outline button-small" href="<?= e($target) ?>#trailer"><?= icon('play') ?>Trailer<span class="sr-only"> <?= e($movie['title']) ?></span></a><?php endif; ?><a class="button button-outline button-small" href="<?= e($target) ?><?= $phase === 'showing' ? '#showtimes' : '' ?>"><?= icon($phase === 'showing' ? 'ticket' : 'film') ?><?= $phase === 'upcoming' ? 'Sắp chiếu' : $action ?><span class="sr-only"> <?= e($movie['title']) ?></span></a></div>
    </div>
</article>
