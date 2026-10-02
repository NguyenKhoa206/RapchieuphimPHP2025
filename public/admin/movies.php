<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => C::archive('movie', request_id()), 'admin/movies.php');
}
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';
$params = $q !== '' ? ['%' . $q . '%'] : [];
$where = 'active=1' . ($q !== '' ? ' AND title LIKE ?' : '');
$count = (int)D::one("SELECT COUNT(*) AS n FROM cinema_movies WHERE $where", $params)['n'];
[$page,$offset] = admin_page($count);
$movies = D::all("SELECT * FROM cinema_movies WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset", $params);
admin_header('Quản lý phim');
?>
<div class="section-heading page-heading"><div><span class="eyebrow">DANH MỤC</span><h1>Quản lý phim</h1></div><a class="button" href="<?= e(url('admin/movie-edit.php')) ?>">+ Thêm phim</a></div>
<form class="filter-bar" method="get"><label class="grow"><span class="sr-only">Tìm phim</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Tìm tên phim…"></label><button class="button button-outline" type="submit">Tìm kiếm</button></form>
<div class="panel table-scroll"><table><thead><tr><th>Phim</th><th>Thời lượng</th><th>Phát hành</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($movies as $movie): $state = $movie['release_date'] > date('Y-m-d') ? 'Sắp chiếu' : ($movie['end_date'] < date('Y-m-d') ? 'Đã kết thúc' : 'Đang chiếu'); ?><tr><td><div class="inline-title"><img class="admin-thumb" src="<?= e(image_url($movie['poster'])) ?>" alt=""><div><strong><?= e($movie['title']) ?></strong><small><?= e($movie['genre']) ?> · <?= $movie['age_rating'] ? 'T' . (int)$movie['age_rating'] : 'P' ?></small></div></div></td><td><?= (int)$movie['duration'] ?> phút</td><td><?= e($movie['release_date']) ?><br><?= e($movie['end_date']) ?></td><td><span class="badge"><?= $state ?></span></td><td><a class="button button-outline button-small" href="<?= e(url('admin/movie-edit.php', ['id' => $movie['id']])) ?>">Sửa</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$movie['id'] ?>"><button class="button button-danger button-small" type="submit" data-confirm="Ẩn phim khỏi danh mục? Lịch sử vé được giữ lại.">Ẩn phim</button></form></td></tr><?php endforeach; ?></tbody></table><?php if (!$movies): ?><div class="empty-state">Chưa có phim phù hợp.</div><?php endif; ?></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/movies.php', 'query' => ['q' => $q]]);
view('layouts/footer'); ?>
