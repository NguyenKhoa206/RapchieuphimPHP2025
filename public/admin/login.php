<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Support\Auth;

if (Auth::admin()) {
    redirect('admin/index.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    try {
        if (Auth::login('admin', text_input('identifier', 100), password_input('password'))) {
            redirect('admin/index.php');
        }$error = 'Sai tài khoản hoặc mật khẩu.';
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
view('auth/form', ['register' => false, 'adminLogin' => true, 'error' => $error]);
