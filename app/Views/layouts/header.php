<?php
$adminPage = $adminPage ?? false;
$identity = $adminPage ? App\Support\Auth::admin() : App\Support\Auth::user();
$pageTitle = ($title ?? 'Phim & lịch chiếu') . ' · ' . config('name');
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/galaxy-logo-mobile-074abeac.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
    <script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Đến nội dung</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="<?= e(url($adminPage ? 'admin/index.php' : 'index.php')) ?>"><span class="brand-mark">G</span><span>GALAXY<span class="brand-small">CINEMA<?= $adminPage ? ' · QUẢN TRỊ' : '' ?></span></span></a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">Menu</button>
        <nav id="primary-nav" class="primary-nav" aria-label="Điều hướng chính">
        <?php if ($adminPage && $identity): ?>
            <?php foreach (['admin/index.php' => 'Tổng quan', 'admin/movies.php' => 'Phim', 'admin/cinemas.php' => 'Rạp & phòng', 'admin/showtimes.php' => 'Lịch chiếu', 'admin/orders.php' => 'Đơn vé', 'admin/revenue.php' => 'Doanh thu', 'admin/users.php' => 'Khách hàng', 'admin/combos.php' => 'Combo'] as $href => $label): ?>
            <a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        <?php else: ?>
            <a href="<?= e(url('index.php')) ?>">Phim</a>
            <a href="<?= e(url('showtimes.php')) ?>">Lịch chiếu</a>
            <?php if ($identity): ?><a href="<?= e(url('account/tickets.php')) ?>">Vé của tôi</a><?php endif; ?>
        <?php endif; ?>
        </nav>
        <div class="header-account">
        <?php if ($identity): ?>
            <a href="<?= e(url($adminPage ? 'admin/password.php' : 'account/profile.php')) ?>" class="account-name"><?= e($identity['name']) ?></a>
            <form method="post" action="<?= e(url('auth/logout.php')) ?>"><?= csrf_field() ?><input type="hidden" name="role" value="<?= $adminPage ? 'admin' : 'user' ?>"><button class="button button-small button-outline-light" type="submit">Đăng xuất</button></form>
        <?php else: ?>
            <a class="button button-small" href="<?= e(url($adminPage ? 'admin/login.php' : 'auth/login.php')) ?>">Đăng nhập</a>
        <?php endif; ?>
        </div>
    </div>
</header>
<main id="main" class="container main-content">
<?php foreach ($_SESSION['flash'] ?? [] as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
<?php endforeach;
unset($_SESSION['flash']); ?>
