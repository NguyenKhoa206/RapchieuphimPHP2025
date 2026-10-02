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
        Auth::register(['name' => text_input('name', 120), 'email' => mb_strtolower(text_input('email', 190)), 'phone' => text_input('phone', 20), 'gender' => (int)text_input('gender', 1), 'birthday' => text_input('birthday', 10, false), 'password' => password_input('password'), 'confirmation' => password_input('confirmation')]);
        flash('Đăng ký thành công. Bạn có thể đăng nhập.');
        redirect('auth/login.php');
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}
view('auth/form', ['register' => true, 'adminLogin' => false, 'error' => $error]);
