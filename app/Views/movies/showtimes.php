<?php if (!$showtimes): ?><div class="empty-state"><h3>Chưa có suất chiếu mở bán</h3><p>Vui lòng quay lại sau hoặc chọn phim khác.</p></div><?php endif; ?>
<div class="showtime-list">
<?php foreach ($showtimes as $show): ?>
<article class="showtime-card"><div class="showtime-date"><strong><?= date('d/m', strtotime($show['starts_at'])) ?></strong><span><?= date('H:i', strtotime($show['starts_at'])) ?></span></div><div class="grow"><h3><?= e($show['title']) ?></h3><p><?= e($show['cinema_name']) ?> · <?= e($show['room_name']) ?></p><span class="muted"><?= e($show['language']) ?></span></div><div class="showtime-price"><strong>Từ <?= money($show['base_price']) ?></strong><a class="button button-small" href="<?= e(url('booking.php', ['id' => $show['id']])) ?>">Chọn ghế</a></div></article>
<?php endforeach; ?>
</div>
