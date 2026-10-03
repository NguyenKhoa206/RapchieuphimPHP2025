<?php view('layouts/header', ['title' => $register ? 'Tạo tài khoản' : 'Đăng nhập', 'adminPage' => $adminLogin]); ?>
<section class="auth-layout">
    <div class="auth-intro"><span class="eyebrow">HẸN BẠN TẠI RẠP</span><h2>Một tài khoản.<br>Nhiều trải nghiệm.</h2><p>Đặt ghế, theo dõi vé và quản lý thông tin của bạn tại một nơi.</p><div class="cinema-art" aria-hidden="true"><span>G</span><p>LIGHTS. CAMERA. CINEMA.</p></div></div>
    <div class="panel auth-panel"><h1><?= $register ? 'Tạo tài khoản' : ($adminLogin ? 'Đăng nhập quản trị' : 'Chào mừng trở lại') ?></h1>
    <p class="muted"><?= $register ? 'Điền thông tin để bắt đầu đặt vé.' : 'Nhập thông tin tài khoản để tiếp tục.' ?></p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack"><?= csrf_field() ?>
    <?php if ($register): ?>
        <label>Họ và tên<input name="name" maxlength="120" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label>
        <label>Email<input type="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"></label>
        <label>Số điện thoại<input type="tel" name="phone" pattern="0[0-9]{9,10}" maxlength="11" required autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>"></label>
        <div class="form-grid"><label>Giới tính<select name="gender"><option value="2">Khác / chưa cung cấp</option><option value="1">Nam</option><option value="0">Nữ</option></select></label><label>Ngày sinh<input type="date" name="birthday" max="<?= date('Y-m-d') ?>" value="<?= e($_POST['birthday'] ?? '') ?>"></label></div>
    <?php else: ?>
        <label><?= $adminLogin ? 'Tên đăng nhập' : 'Email hoặc số điện thoại' ?><input name="identifier" maxlength="190" required autocomplete="username" value="<?= e($_POST['identifier'] ?? '') ?>"></label>
    <?php endif; ?>
        <label>Mật khẩu<input type="password" name="password" <?= $register ? 'minlength="8"' : '' ?> maxlength="72" required autocomplete="<?= $register ? 'new-password' : 'current-password' ?>"></label>
    <?php if ($register): ?><label>Nhập lại mật khẩu<input type="password" name="confirmation" minlength="8" maxlength="72" required autocomplete="new-password"></label><?php endif; ?>
        <button class="button" type="submit"><?= $register ? 'Tạo tài khoản' : 'Đăng nhập' ?></button>
    </form>
    <?php if (!$adminLogin): ?><p class="auth-switch"><?= $register ? 'Đã có tài khoản?' : 'Chưa có tài khoản?' ?> <a href="<?= e(url($register ? 'auth/login.php' : 'auth/register.php')) ?>"><?= $register ? 'Đăng nhập' : 'Đăng ký' ?></a></p><?php endif; ?>
    </div>
</section>
<?php view('layouts/footer'); ?>
