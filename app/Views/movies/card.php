<?php $upcoming = $movie['release_date'] > date('Y-m-d'); ?>
<article class="movie-card">
    <div class="movie-poster-wrap">
        <a class="poster-link" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>">
            <img loading="lazy" decoding="async" width="500" height="750" src="<?= e(image_url($movie['poster'])) ?>" alt="<?= e($movie['title']) ?>">
            <span class="age-badge"><?= $movie['age_rating'] ? 'T' . (int) $movie['age_rating'] : 'P' ?></span>
        </a>
        <div class="movie-poster-actions">
            <?php if ($movie['trailer']): ?><a class="button movie-trailer-link" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>#trailer"><?= icon('play') ?>Trailer<span class="sr-only"> <?= e($movie['title']) ?></span></a><?php endif; ?>
            <a class="button" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?><?= !$upcoming ? '#showtimes' : '' ?>"><?= icon('ticket') ?><?= $upcoming ? 'Xem chi tiết' : 'Mua vé' ?><span class="sr-only"> <?= e($movie['title']) ?></span></a>
        </div>
    </div>
    <div class="movie-card-copy">
        <h3><a href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>"><?= e($movie['title']) ?></a></h3>
        <p class="movie-meta"><?= e($movie['genre']) ?> <span>·</span> <?= (int) $movie['duration'] ?> phút</p>
        <p class="movie-release">Khởi chiếu: <?= date('d/m/Y', strtotime($movie['release_date'])) ?></p>
        <div class="movie-card-buttons"><?php if ($movie['trailer']): ?><a class="button button-outline button-small" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?>#trailer"><?= icon('play') ?>Trailer<span class="sr-only"> <?= e($movie['title']) ?></span></a><?php endif; ?><a class="button button-outline button-small" href="<?= e(url('movie.php', ['id' => $movie['id']])) ?><?= !$upcoming ? '#showtimes' : '' ?>"><?= icon('ticket') ?><?= $upcoming ? 'Sắp chiếu' : 'Mua vé' ?><span class="sr-only"> <?= e($movie['title']) ?></span></a></div>
    </div>
</article>
