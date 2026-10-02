<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static fn () => Auth::changePassword('admin', (int)$admin['id'], password_input('current_password'), password_input('new_password'), password_input('confirmation')), 'admin/password.php');
}
admin_header('Đổi mật khẩu quản trị');
?>
<div class="page-heading"><h1>Đổi mật khẩu quản trị</h1></div><section class="panel admin-form"><form method="post" class="form-stack"><?= csrf_field() ?><label>Mật khẩu hiện tại<input name="current_password" type="password" required autocomplete="current-password"></label><label>Mật khẩu mới<input name="new_password" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></label><label>Nhập lại mật khẩu mới<input name="confirmation" type="password" minlength="8" maxlength="72" required autocomplete="new-password"></label><button class="button" type="submit">Đổi mật khẩu</button></form></section>
<?php view('layouts/footer'); ?>
