<?php if (!$showtimes): ?>
<div class="empty-state"><span class="empty-icon"><?= icon('calendar') ?></span><h3>Chưa có suất chiếu phù hợp</h3><p>Thử chọn ngày hoặc rạp khác để xem lịch mở bán.</p></div>
<?php endif; ?>
<div class="showtime-list">
<?php foreach ($showtimes as $show): ?>
    <article class="showtime-card">
        <img class="showtime-thumb" loading="lazy" width="56" height="80" src="<?= e(image_url($show['poster'])) ?>" alt="">
        <div class="showtime-date"><span><?= date('d/m', strtotime($show['starts_at'])) ?></span><strong><?= date('H:i', strtotime($show['starts_at'])) ?></strong></div>
        <div class="grow"><h3><?= e($show['title']) ?></h3><p><?= e($show['cinema_name']) ?> · <?= e($show['room_name']) ?></p><span class="muted"><?= e($show['language']) ?></span></div>
        <div class="showtime-price"><span class="muted">Giá vé từ</span><strong><?= money($show['base_price']) ?></strong><a class="button button-small" href="<?= e(url('booking.php', ['id' => $show['id']])) ?>"><?= icon('ticket') ?>Chọn ghế</a></div>
    </article>
<?php endforeach; ?>
</div>
