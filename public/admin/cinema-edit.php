<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;

$id = request_id();
$cinema = D::one('SELECT * FROM cinema_cinemas WHERE id=? AND active=1', [$id]);
if (!$cinema) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static function () use ($id): void {
        $phone = text_input('phone', 20);
        if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
            throw new DomainException('Số điện thoại không hợp lệ.');
        }D::execute('UPDATE cinema_cinemas SET name=?,address=?,phone=? WHERE id=?', [text_input('name', 190), text_input('address', 255), $phone, $id]);
    }, 'admin/cinema-edit.php', ['id' => $id]);
}
admin_header('Sửa rạp');
?>
<a class="back-link" href="<?= e(url('admin/cinemas.php')) ?>">← Rạp & phòng</a><div class="page-heading"><h1>Sửa thông tin rạp</h1></div><section class="panel admin-form"><form method="post" class="form-stack"><?= csrf_field() ?><label>Tên rạp<input name="name" maxlength="190" required value="<?= e($cinema['name']) ?>"></label><label>Địa chỉ<input name="address" maxlength="255" required value="<?= e($cinema['address']) ?>"></label><label>Số điện thoại<input name="phone" pattern="0[0-9]{9,10}" required value="<?= e($cinema['phone']) ?>"></label><button class="button" type="submit">Lưu thay đổi</button></form></section>
<?php view('layouts/footer'); ?>
