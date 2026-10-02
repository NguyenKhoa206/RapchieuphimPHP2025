<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;

$id = request_id();
$room = D::one('SELECT * FROM cinema_rooms WHERE id=? AND active=1', [$id]);
if (!$room) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => D::execute('UPDATE cinema_rooms SET name=? WHERE id=?', [text_input('name', 120), $id]), 'admin/room-edit.php', ['id' => $id]);
}
admin_header('Đổi tên phòng');
?>
<a class="back-link" href="<?= e(url('admin/cinemas.php')) ?>">← Rạp & phòng</a><div class="page-heading"><h1>Đổi tên phòng</h1></div><section class="panel admin-form"><form method="post" class="form-stack"><?= csrf_field() ?><label>Tên phòng<input name="name" maxlength="120" required value="<?= e($room['name']) ?>"></label><p class="muted">Loại phòng và sơ đồ ghế cố định sau khi tạo để giữ đúng các vé đã đặt. Tạo phòng mới nếu cần thay đổi sơ đồ.</p><button class="button" type="submit">Lưu tên phòng</button></form></section>
<?php view('layouts/footer'); ?>
