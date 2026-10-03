<?php
$adminPage = $adminPage ?? false;
$identity = $adminPage ? App\Support\Auth::admin() : App\Support\Auth::user();
$adminShell = $adminPage && $identity;
$homePage = $homePage ?? false;
$GLOBALS['layout_admin'] = $adminShell;
$pageTitle = ($title ?? 'Phim & lịch chiếu') . ' · ' . config('name');
$current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$links = $adminShell ? [
    'admin/index.php' => ['Tổng quan', 'grid', ['index.php']],
    'admin/movies.php' => ['Phim', 'film', ['movies.php', 'movie-edit.php']],
    'admin/news.php' => ['Tin & ưu đãi', 'news', ['news.php', 'news-edit.php']],
    'admin/cinemas.php' => ['Rạp & phòng', 'building', ['cinemas.php', 'cinema-edit.php', 'room-edit.php']],
    'admin/showtimes.php' => ['Lịch chiếu', 'calendar', ['showtimes.php', 'showtime-edit.php']],
    'admin/orders.php' => ['Đơn vé', 'ticket', ['orders.php', 'order.php']],
    'admin/payments.php' => ['Thanh toán online', 'wallet', ['payments.php']],
    'admin/history.php' => ['Lịch sử cũ', 'archive', ['history.php']],
    'admin/revenue.php' => ['Doanh thu', 'chart', ['revenue.php']],
    'admin/users.php' => ['Khách hàng', 'users', ['users.php', 'user-edit.php']],
    'admin/combos.php' => ['Bắp & nước', 'cup', ['combos.php']],
] : [
    'index.php' => ['Home', 'film', ['index.php']],
    'showtimes.php' => ['Đặt vé', 'ticket', ['showtimes.php', 'booking.php']],
    'index.php#movies' => ['Phim', 'film', ['movie.php']],
    ($homePage ? 'index.php#tinmoi' : 'news.php') => ['Tin mới & ưu đãi', 'news', ['news.php', 'news-detail.php']],
];
$headerSearch = $current === 'index.php' && is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 120) : '';
$headerStatus = in_array($_GET['status'] ?? 'showing', ['showing', 'upcoming', 'past', 'all'], true) ? ($_GET['status'] ?? 'showing') : 'showing';
$headerTag = $homePage && is_string($_GET['tag'] ?? null) ? mb_substr(trim($_GET['tag']), 0, 32) : '';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/images/galaxy-logo-mobile-074abeac.png')) ?>">
    <link rel="preload" href="<?= e(url('assets/fonts/be-vietnam-pro/be-vietnam-pro-400.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <?php if (!$adminShell): ?><link rel="preload" href="<?= e(url('assets/fonts/oswald/oswald-variable.woff2')) ?>" as="font" type="font/woff2" crossorigin><?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/classic.css')) ?>">
    <script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
</head>
<body class="<?= $adminShell ? 'admin-shell' : 'customer-shell' ?><?= $homePage && !$adminShell ? ' home-shell' : '' ?>">
<a class="skip-link" href="#main">Đến nội dung</a>
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="<?= e(url($adminPage ? 'admin/index.php' : 'index.php')) ?>">
            <img class="brand-logo" src="<?= e(url('assets/images/galaxy-logo-mobile-074abeac.png')) ?>" width="150" height="78" alt="Galaxy Cinema<?= $adminShell ? ' · Quản trị' : '' ?>">
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Mở điều hướng"><?= icon('menu') ?></button>
        <?php if ($adminShell): ?><span class="admin-top-title"><?= e($title ?? 'Quản trị') ?></span><?php else: ?>
        <nav id="primary-nav" class="primary-nav" aria-label="Điều hướng chính">
            <?php foreach ($links as $href => [$label, $glyph, $pages]): ?>
                <a href="<?= e(url($href)) ?>" <?= in_array($current, $pages, true) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a class="mobile-tickets-link" href="<?= e(url('account/tickets.php')) ?>">Vé của tôi</a>
            <form method="get" action="<?= e(url('index.php')) ?>" class="header-search">
                <input type="hidden" name="status" value="<?= e($headerStatus) ?>">
                <?php if ($headerTag !== ''): ?><input type="hidden" name="tag" value="<?= e($headerTag) ?>"><?php endif; ?>
                <label for="header-search" class="sr-only">Tìm phim hoặc thể loại</label>
                <input id="header-search" type="search" name="q" maxlength="120" placeholder="Tìm phim rạp…" value="<?= e($headerSearch) ?>">
                <button type="submit" aria-label="Tìm phim"><?= icon('search') ?></button>
            </form>
        </nav>
        <?php endif; ?>
        <div class="header-account">
        <?php if ($identity): ?>
            <details class="account-menu">
                <summary><?= icon('user') ?><span><?= e($identity['name']) ?></span><?= icon('chevron-down') ?></summary>
                <div class="account-dropdown">
                    <a href="<?= e(url($adminPage ? 'admin/password.php' : 'account/profile.php')) ?>"><?= $adminPage ? 'Đổi mật khẩu' : 'Thông tin cá nhân' ?></a>
                    <?php if (!$adminPage): ?><a href="<?= e(url('account/tickets.php')) ?>">Vé & hóa đơn</a><a href="<?= e(url('account/tickets.php', ['tab' => 'invoices'])) ?>">Hóa đơn cũ</a><?php endif; ?>
                    <form method="post" action="<?= e(url('auth/logout.php')) ?>">
                        <?= csrf_field() ?><input type="hidden" name="role" value="<?= $adminPage ? 'admin' : 'user' ?>">
                        <button type="submit">Đăng xuất</button>
                    </form>
                </div>
            </details>
        <?php else: ?>
            <a class="account-login" href="<?= e(url($adminPage ? 'admin/login.php' : 'auth/login.php')) ?>"><?= icon('user') ?>Đăng nhập</a>
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
<main id="main" class="<?= $homePage ? 'home-content' : 'container' ?> main-content">
<?php foreach ($_SESSION['flash'] ?? [] as $message): ?>
    <div class="alert alert-<?= e($message['type']) ?>" role="status"><?= e($message['message']) ?></div>
<?php endforeach;
unset($_SESSION['flash']); ?>
