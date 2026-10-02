<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Auth;
use App\Support\Database as D;

$username = $argv[1] ?? '';
$reset = in_array('--reset', $argv, true);
if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $username)) {
    throw new RuntimeException('Cách dùng: php scripts/create-admin.php tenadmin [--reset]');
}
$old = D::one('SELECT id FROM cinema_admins WHERE username=?', [$username]);
if ($old && !$reset) {
    throw new RuntimeException('Tài khoản đã tồn tại. Dùng --reset nếu muốn đặt lại mật khẩu.');
}
fwrite(STDERR, "Nhập mật khẩu admin (ít nhất 8 ký tự; không hiển thị khi terminal hỗ trợ): ");
$tty = function_exists('stream_isatty') && stream_isatty(STDIN);
if ($tty && PHP_OS_FAMILY !== 'Windows') {
    shell_exec('stty -echo');
}
try {
    $password = rtrim((string)fgets(STDIN), "\r\n");
} finally {
    if ($tty && PHP_OS_FAMILY !== 'Windows') {
        shell_exec('stty echo');
    }fwrite(STDERR, "\n");
}
Auth::validatePassword($password);
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($old) {
    D::execute("UPDATE cinema_admins SET password_hash=?,password_scheme='modern',auth_version=auth_version+1,active=1 WHERE id=?", [$hash, $old['id']]);
} else {
    D::execute('INSERT INTO cinema_admins (username,name,password_hash) VALUES (?,?,?)', [$username, $username, $hash]);
}
echo "Đã lưu tài khoản admin. Không có mật khẩu mặc định trong mã nguồn.\n";
