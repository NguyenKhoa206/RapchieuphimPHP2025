<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => D::execute('UPDATE cinema_users SET active=1-active,auth_version=auth_version+1 WHERE id=?', [request_id()]), 'admin/users.php');
}
$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';
$where = $q !== '' ? ' WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?' : '';
$params = $q !== '' ? ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'] : [];
$count = (int)D::one('SELECT COUNT(*) AS n FROM cinema_users' . $where, $params)['n'];
[$page,$offset] = admin_page($count);
$users = D::all('SELECT id,legacy_key,name,email,phone,gender,active,created_at FROM cinema_users' . $where . " ORDER BY id DESC LIMIT 20 OFFSET $offset", $params);
admin_header('Khách hàng');
?>
<div class="page-heading"><span class="eyebrow">TÀI KHOẢN KHÁCH HÀNG</span><h1>Quản lý khách hàng</h1><p class="muted">Khóa tài khoản sẽ kết thúc quyền truy cập; lịch sử đơn vé được giữ lại.</p></div><form method="get" class="filter-bar"><label class="grow"><span class="sr-only">Tìm khách hàng</span><input name="q" placeholder="Tên, email hoặc số điện thoại…" value="<?= e($q) ?>"></label><button class="button button-outline" type="submit">Tìm kiếm</button></form><div class="panel table-scroll"><table><thead><tr><th>Mã</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><?= e($user['legacy_key'] ?? $user['id']) ?></td><td><?= e($user['name']) ?></td><td><?= e($user['email']) ?></td><td><?= e($user['phone']) ?></td><td><span class="badge"><?= $user['active'] ? 'Hoạt động' : 'Đã khóa' ?></span></td><td><a class="button button-small button-outline" href="<?= e(url('admin/user-edit.php', ['id' => $user['id']])) ?>">Sửa</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><button class="button button-small button-danger" type="submit" data-confirm="Đổi trạng thái truy cập của tài khoản này?"><?= $user['active'] ? 'Khóa' : 'Mở khóa' ?></button></form></td></tr><?php endforeach; ?></tbody></table><?php if (!$users): ?><div class="empty-state">Chưa có khách hàng phù hợp.</div><?php endif; ?></div>
<?php view('admin/pagination', ['page' => $page, 'count' => $count, 'path' => 'admin/users.php', 'query' => ['q' => $q]]);
view('layouts/footer'); ?>
