<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Support\Database as D;

clear_fixture();
$legacyTables = ['Ve', 'HoaDon', 'LichChieu', 'PhongChieu', 'RapChieu', 'BoPhim', 'Administrator', 'KhachHang'];
foreach ($legacyTables as $table) {
    if (D::one('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [config('database.name'), $table])) {
        throw new RuntimeException('Dùng CSDL kiểm thử migration riêng, chưa có các bảng cũ.');
    }
}
$definitions = [
    'KhachHang' => 'MaKH VARCHAR(40) PRIMARY KEY,HoTen VARCHAR(120),Email VARCHAR(190),SoDienThoai VARCHAR(20),GioiTinh INT,MatKhau VARCHAR(255)',
    'Administrator' => 'TenDangNhap VARCHAR(100) PRIMARY KEY,MatKhau VARCHAR(255)',
    'BoPhim' => 'MaPhim INT PRIMARY KEY,TenPhim VARCHAR(190),TheLoai VARCHAR(120),GioiHanTuoi INT,ThoiLuong INT,NgayKhoiChieu DATE,NgayKetThuc DATE,MoTa TEXT,AnhBia VARCHAR(255),Trailer VARCHAR(255)',
    'RapChieu' => 'MaRap INT PRIMARY KEY,TenRap VARCHAR(190),DiaChi VARCHAR(255),Sdt VARCHAR(20)',
    'PhongChieu' => 'MaPhong INT PRIMARY KEY,MaRap INT,TenPhong VARCHAR(120),SoGhe INT',
    'LichChieu' => 'MaLich INT PRIMARY KEY,MaPhim INT,MaPhong INT,NgayChieu DATE,GioChieu TIME,GiaVe INT',
    'HoaDon' => 'MaHD INT PRIMARY KEY,TongTien INT',
    'Ve' => 'MaVe INT PRIMARY KEY,MaKH VARCHAR(40)',
];
foreach ($definitions as $table => $columns) {
    db()->exec('CREATE TABLE `' . $table . '` (' . $columns . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
$command = [PHP_BINARY];
if (php_ini_loaded_file()) {
    $command[] = '-c';
    $command[] = php_ini_loaded_file();
}$command[] = APP_ROOT . '/scripts/migrate-legacy.php';
$run = static function () use ($command): string {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0, 'Migration thất bại: ' . $errors);
    return $output;
};
try {
    D::execute('INSERT INTO KhachHang VALUES (?,?,?,?,?,?)', ['KH001', 'Khách cũ', 'legacy@example.test', '0900000090', 1, md5('Legacy123!')]);
    D::execute('INSERT INTO Administrator VALUES (?,?)', ['legacyadmin', md5('Legacy123!')]);
    D::execute('INSERT INTO BoPhim VALUES (?,?,?,?,?,?,?,?,?,?)', [7, 'Phim cũ', 'Hoạt hình', 0, 95, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'Mô tả', 'hinh/jujutsu-kaisen-500_1759216418459.jpg', null]);
    D::execute('INSERT INTO RapChieu VALUES (?,?,?,?)', [3, 'Rạp cũ', 'Địa chỉ cũ', '0900000091']);
    D::execute('INSERT INTO PhongChieu VALUES (?,?,?,?)', [5, 3, 'Phòng cũ', 780]);
    D::execute('INSERT INTO LichChieu VALUES (?,?,?,?,?,?)', [9, 7, 5, date('Y-m-d', strtotime('+1 day')), '19:00:00', 80000]);
    D::execute('INSERT INTO HoaDon VALUES (?,?)', [1, 456000]);
    D::execute('INSERT INTO Ve VALUES (?,?)', [1, 'KH001']);
    $run();
    $user = D::one('SELECT * FROM cinema_users WHERE legacy_key=?', ['KH001']);
    check($user !== null && $user['password_scheme'] === 'legacy_md5', 'Không giữ khóa chuỗi/MD5');
    check((int)D::one('SELECT COUNT(*) AS n FROM cinema_seats WHERE room_id=5')['n'] === 780, 'Thiếu ghế');
    check(D::one('SELECT MAX(row_label) AS last_row FROM cinema_seats WHERE room_id=5')['last_row'] === 'Z', 'Sai giới hạn hàng ghế');
    check(D::one('SELECT poster FROM cinema_movies WHERE id=7')['poster'] === 'jujutsu-kaisen-500-1759216418459.jpg', 'Sai ánh xạ ảnh');
    check(substr(D::one('SELECT ends_at FROM cinema_showtimes WHERE id=9')['ends_at'], 11) === '20:35:00', 'Sai giờ kết thúc');
    check((int)D::one('SELECT TongTien FROM HoaDon WHERE MaHD=1')['TongTien'] === 456000, 'Đã thay đổi hóa đơn cũ');
    check((int)D::one('SELECT COUNT(*) AS n FROM cinema_orders')['n'] === 0, 'Tự đoán lịch sử đơn');
    $run();
    check((int)D::one('SELECT COUNT(*) AS n FROM cinema_users')['n'] === 1, 'Import không idempotent');
    echo "PASS Migration: khóa KH001, MD5, ID danh mục, ảnh, 780 ghế A–Z, giờ kết thúc, giữ hóa đơn cũ, không nhập trùng.\n";
} finally {
    foreach ($legacyTables as $table) {
        db()->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }
}
