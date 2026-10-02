<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => C::archive('showtime', request_id()), 'admin/showtimes.php');
}
$count = (int)D::one('SELECT COUNT(*) AS n FROM cinema_showtimes WHERE active=1')['n'];
[$page,$offset] = admin_page($count);
$shows = D::all("SELECT s.*,m.title,c.name AS cinema_name,r.name AS room_name FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE s.active=1 ORDER BY s.starts_at DESC LIMIT 20 OFFSET $offset");
admin_header('Lịch chiếu');
?>
<div class="section-heading page-heading"><div><span class="eyebrow">PHÒNG & THỜI GIAN</span><h1>Quản lý lịch chiếu</h1></div><a class="button" href="<?= e(url('admin/showtime-edit.php')) ?>">+ Thêm lịch chiếu</a></div><div class="panel table-scroll"><table><thead><tr><th>Phim</th><th>Rạp / phòng</th><th>Bắt đầu</th><th>Kết thúc</th><th>Giá vé</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($shows as $show): ?><tr><td><?= e($show['title']) ?><small class="muted"> · <?= e($show['language']) ?></small></td><td><?= e($show['cinema_name']) ?><br><?= e($show['room_name']) ?></td><td><?= date('H:i d/m/Y', strtotime($show['starts_at'])) ?></td><td><?= date('H:i d/m/Y', strtotime($show['ends_at'])) ?></td><td><?= money($show['base_price']) ?></td><td><a class="button button-outline button-small" href="<?= e(url('admin/showtime-edit.php', ['id' => $show['id']])) ?>">Sửa</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$show['id'] ?>"><button class="button button-danger button-small" type="submit" data-confirm="Hủy lịch này? Lịch có vé đã bán không thể hủy.">Hủy lịch</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/showtimes.php', 'query' => []]);
view('layouts/footer'); ?>
