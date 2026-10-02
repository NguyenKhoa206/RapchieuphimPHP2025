<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Support\Auth;

if (Auth::user()) {
    redirect('index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        if (Auth::login('user', text_input('identifier', 190), password_input('password'))) {
            $next = $_SESSION['next_booking'] ?? null;
            unset($_SESSION['next_booking']);
            if (is_int($next) && $next > 0) {
                redirect('booking.php', ['id' => $next]);
            }redirect('index.php');
        }
        $error = 'Sai tài khoản hoặc mật khẩu.';
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
view('auth/form', ['register' => false, 'adminLogin' => false, 'error' => $error]);
