<?php
$adminPage = $adminPage ?? false;
$identity = $adminPage ? App\Support\Auth::admin() : App\Support\Auth::user();
$adminShell = $adminPage && $identity;
$GLOBALS['layout_admin'] = $adminShell;
$pageTitle = ($title ?? 'Phim & lịch chiếu') . ' · ' . config('name');
$current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$links = $adminShell ? [
    'admin/index.php' => ['Tổng quan', 'grid', ['index.php']],
    'admin/movies.php' => ['Phim', 'film', ['movies.php', 'movie-edit.php']],
    'admin/cinemas.php' => ['Rạp & phòng', 'building', ['cinemas.php', 'cinema-edit.php', 'room-edit.php']],
    'admin/showtimes.php' => ['Lịch chiếu', 'calendar', ['showtimes.php', 'showtime-edit.php']],
    'admin/orders.php' => ['Đơn vé', 'ticket', ['orders.php', 'order.php']],
    'admin/payments.php' => ['Thanh toán online', 'wallet', ['payments.php']],
    'admin/history.php' => ['Lịch sử cũ', 'archive', ['history.php']],
    'admin/revenue.php' => ['Doanh thu', 'chart', ['revenue.php']],
    'admin/users.php' => ['Khách hàng', 'users', ['users.php', 'user-edit.php']],
    'admin/combos.php' => ['Bắp & nước', 'cup', ['combos.php']],
] : [
    'index.php' => ['Phim', 'film', ['index.php', 'movie.php']],
    'showtimes.php' => ['Lịch chiếu', 'calendar', ['showtimes.php', 'booking.php']],
    'account/tickets.php' => ['Vé của tôi', 'ticket', ['tickets.php', 'order.php', 'history.php']],
];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#111e30">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/galaxy-logo-mobile-074abeac.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= $adminShell ? 'admin-shell' : 'customer-shell' ?>">
<a class="skip-link" href="#main">Đến nội dung</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="<?= e(url($adminPage ? 'admin/index.php' : 'index.php')) ?>">
            <span class="brand-mark"><?= icon('film') ?></span>
            <span>GALAXY<span class="brand-small">CINEMA<?= $adminShell ? ' · QUẢN TRỊ' : '' ?></span></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Mở điều hướng"><?= icon('menu') ?></button>
        <?php if ($adminShell): ?><span class="admin-top-title"><?= e($title ?? 'Quản trị') ?></span><?php else: ?>
        <nav id="primary-nav" class="primary-nav" aria-label="Điều hướng chính">
            <?php foreach ($links as $href => [$label, $glyph, $pages]): ?>
                <a href="<?= e(url($href)) ?>" <?= in_array($current, $pages, true) ? 'aria-current="page"' : '' ?>><?= icon($glyph) ?><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <div class="header-account">
        <?php if ($identity): ?>
            <a href="<?= e(url($adminPage ? 'admin/password.php' : 'account/profile.php')) ?>" class="account-name"><?= icon('user') ?><span><?= e($identity['name']) ?></span></a>
            <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                <?= csrf_field() ?><input type="hidden" name="role" value="<?= $adminPage ? 'admin' : 'user' ?>">
                <button class="button button-small button-outline-light" type="submit">Đăng xuất</button>
            </form>
        <?php else: ?>
            <a class="button button-small" href="<?= e(url($adminPage ? 'admin/login.php' : 'auth/login.php')) ?>"><?= icon('user') ?>Đăng nhập</a>
        <?php endif; ?>
        </div>
    </div>
</header>
<?php if ($adminShell): ?>
<aside class="admin-sidebar">
    <p class="sidebar-label">KHÔNG GIAN QUẢN TRỊ</p>
    <nav id="primary-nav" class="primary-nav" aria-label="Điều hướng quản trị">
        <?php foreach ($links as $href => [$label, $glyph, $pages]): ?>
            <a href="<?= e(url($href)) ?>" <?= in_array($current, $pages, true) ? 'aria-current="page"' : '' ?>><?= icon($glyph) ?><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <a class="sidebar-site-link" href="<?= e(url()) ?>"><?= icon('arrow') ?>Mở trang khách hàng</a>
</aside>
<?php endif; ?>
<button class="nav-backdrop" type="button" aria-label="Đóng điều hướng" hidden></button>
<main id="main" class="container main-content">
<?php foreach ($_SESSION['flash'] ?? [] as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
<?php endforeach;
unset($_SESSION['flash']); ?>
