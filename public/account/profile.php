<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Support\Auth;
use App\Support\Database as D;

$user = Auth::requireUser();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        if (($_POST['action'] ?? '') === 'password') {
            Auth::changePassword('user', (int)$user['id'], password_input('current_password'), password_input('new_password'), password_input('confirmation'));
            flash('Đổi mật khẩu thành công. Các phiên đăng nhập khác đã hết hiệu lực.');
        } else {
            $row = D::one('SELECT password_hash FROM cinema_users WHERE id=?', [$user['id']]);
            if (!password_verify(password_input('current_password'), $row['password_hash'])) {
                throw new DomainException('Mật khẩu xác nhận không đúng.');
            }
            $input = ['name' => text_input('name', 120), 'email' => mb_strtolower(text_input('email', 190)), 'phone' => text_input('phone', 20), 'gender' => (int)text_input('gender', 1), 'birthday' => text_input('birthday', 10, false)];
            Auth::validateContact($input);
            D::execute('UPDATE cinema_users SET name=?,email=?,phone=?,gender=?,birthday=? WHERE id=?', [$input['name'], $input['email'], $input['phone'], $input['gender'], $input['birthday'] ?: null, $user['id']]);
            flash('Đã cập nhật thông tin cá nhân.');
        }
    } catch (DomainException $exception) {
        flash($exception->getMessage(), 'error');
    } catch (PDOException $exception) {
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }flash('Email hoặc số điện thoại đã được sử dụng.', 'error');
    }
    redirect('account/profile.php');
}
view('layouts/header', ['title' => 'Thông tin cá nhân']);
?>
<div class="page-heading"><span class="eyebrow">TÀI KHOẢN</span><h1>Thông tin cá nhân</h1><p class="muted">Ngày sinh dùng để kiểm tra giới hạn tuổi của phim.</p></div>
<div class="two-columns"><section class="panel"><h2>Hồ sơ của bạn</h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><label>Họ tên<input name="name" value="<?= e($user['name']) ?>" maxlength="120" required></label><label>Email<input type="email" name="email" value="<?= e($user['email']) ?>" maxlength="190" required></label><label>Số điện thoại<input type="tel" name="phone" value="<?= e($user['phone']) ?>" pattern="0[0-9]{9,10}" required></label><div class="form-grid"><label>Giới tính<select name="gender"><?php foreach ([2 => 'Khác', 1 => 'Nam', 0 => 'Nữ'] as $value => $label): ?><option value="<?= $value ?>" <?= (int)$user['gender'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label><label>Ngày sinh<input type="date" name="birthday" value="<?= e($user['birthday']) ?>" max="<?= date('Y-m-d') ?>"></label></div><label>Mật khẩu hiện tại để xác nhận<input type="password" name="current_password" required autocomplete="current-password"></label><button class="button" type="submit">Lưu thông tin</button></form></section><section class="panel"><h2>Đổi mật khẩu</h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="password"><label>Mật khẩu hiện tại<input type="password" name="current_password" required autocomplete="current-password"></label><label>Mật khẩu mới<input type="password" name="new_password" minlength="8" maxlength="72" required autocomplete="new-password"></label><label>Nhập lại mật khẩu mới<input type="password" name="confirmation" minlength="8" maxlength="72" required autocomplete="new-password"></label><button class="button button-outline" type="submit">Đổi mật khẩu</button></form></section></div>
<?php view('layouts/footer'); ?>
