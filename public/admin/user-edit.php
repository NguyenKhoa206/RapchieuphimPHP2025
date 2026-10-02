<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Support\Auth;

$id = request_id();
$user = D::one('SELECT id,name,email,phone,gender,birthday FROM cinema_users WHERE id=?', [$id]);
if (!$user) {
    fail_not_found();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static function () use ($id): void {
        $input = ['name' => text_input('name', 120), 'email' => mb_strtolower(text_input('email', 190)), 'phone' => text_input('phone', 20), 'gender' => (int)text_input('gender', 1), 'birthday' => text_input('birthday', 10, false)];
        Auth::validateContact($input);
        D::execute('UPDATE cinema_users SET name=?,email=?,phone=?,gender=?,birthday=?,auth_version=auth_version+1 WHERE id=?', [$input['name'], $input['email'], $input['phone'], $input['gender'], $input['birthday'] ?: null, $id]);
    }, 'admin/user-edit.php', ['id' => $id]);
}
admin_header('Sửa khách hàng');
?>
<a class="back-link" href="<?= e(url('admin/users.php')) ?>">← Khách hàng</a><div class="page-heading"><h1>Sửa thông tin khách hàng</h1></div><section class="panel admin-form"><form method="post" class="form-stack"><?= csrf_field() ?><label>Họ tên<input name="name" maxlength="120" required value="<?= e($user['name']) ?>"></label><label>Email<input name="email" type="email" maxlength="190" required value="<?= e($user['email']) ?>"></label><label>Số điện thoại<input name="phone" pattern="0[0-9]{9,10}" required value="<?= e($user['phone']) ?>"></label><div class="form-grid"><label>Giới tính<select name="gender"><?php foreach ([2 => 'Khác', 1 => 'Nam', 0 => 'Nữ'] as $value => $label): ?><option value="<?= $value ?>" <?= (int)$user['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Ngày sinh<input name="birthday" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($user['birthday']) ?>"></label></div><button class="button" type="submit">Lưu thay đổi</button></form></section>
<?php view('layouts/footer'); ?>
