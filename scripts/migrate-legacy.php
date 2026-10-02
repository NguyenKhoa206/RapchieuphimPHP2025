<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;
use App\Support\Upload;

// Import into new prefixed tables. Original tables and invoices remain untouched.
$tableNames = [];
foreach (D::all('SHOW TABLES') as $row) {
    $name = (string)array_values($row)[0];
    $tableNames[strtolower($name)] = $name;
}
function legacy_rows(string $name): array
{
    $actual = $GLOBALS['tableNames'][strtolower($name)] ?? null;
    if (!$actual) {
        return [];
    }
    return array_map(static fn ($row) => array_change_key_case($row, CASE_LOWER), D::all('SELECT * FROM `' . str_replace('`', '``', $actual) . '`'));
}
if (D::one("SELECT setting_value FROM cinema_meta WHERE setting_key='legacy_imported_at'")) {
    echo "Dữ liệu cũ đã được nhập trước đó; không nhập lại.\n";
    exit;
}
if (!isset($tableNames['khachhang']) && !isset($tableNames['bophim'])) {
    throw new RuntimeException('Không tìm thấy các bảng cũ KhachHang/BoPhim trong CSDL đã cấu hình.');
}
foreach (['cinema_users', 'cinema_admins', 'cinema_movies', 'cinema_cinemas', 'cinema_rooms', 'cinema_showtimes'] as $table) {
    if (D::one("SELECT id FROM $table LIMIT 1")) {
        throw new RuntimeException('Các bảng cinema_ phải trống trước khi nhập. Chưa chạy seed hoặc tạo admin trước bước này.');
    }
}
$legacyUploads = $argv[1] ?? APP_ROOT . '/uploads';
$assets = json_decode(file_get_contents(APP_ROOT . '/database/legacy-assets.json'), true, 512, JSON_THROW_ON_ERROR);
$copied = [];
$warnings = [];
function import_media(?string $value, string $kind): ?string
{
    if (!$value) {
        return null;
    }
    $path = preg_replace('~^(?:\./|\.\./|/)+~', '', str_replace('\\', '/', rawurldecode($value)));
    if (str_starts_with($path, 'hinh/')) {
        $key = substr($path, 5);
        if (isset($GLOBALS['assets'][$key])) {
            return $GLOBALS['assets'][$key];
        }
    }
    $file = $GLOBALS['legacyUploads'] . '/' . basename($path);
    if (!is_file($file)) {
        $GLOBALS['warnings'][] = 'Không tìm thấy media: ' . basename($path);
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
    $types = $kind === 'image' ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'] : ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    if (!isset($types[$mime]) || filesize($file) > config('upload_limit')) {
        $GLOBALS['warnings'][] = 'Bỏ qua media sai loại/quá lớn: ' . basename($path);
        return null;
    }
    $name = bin2hex(random_bytes(20)) . '.' . $types[$mime];
    $directory = APP_ROOT . '/storage/uploads';
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    if (!copy($file, $directory . '/' . $name)) {
        throw new RuntimeException('Không thể sao chép media.');
    }
    $GLOBALS['copied'][] = 'upload:' . $name;
    return 'upload:' . $name;
}
function password_scheme(string $hash): string
{
    if (preg_match('/^[a-f0-9]{32}$/i', $hash)) {
        return 'legacy_md5';
    }
    if ((password_get_info($hash)['algo'] ?? null) !== null) {
        return 'modern';
    }
    throw new RuntimeException('Mật khẩu cũ có định dạng chưa được hỗ trợ. Không nhập mật khẩu dạng văn bản.');
}
$counts = [];
$db = db();
$db->beginTransaction();
try {
    foreach (legacy_rows('KhachHang') as $row) {
        $hash = (string)$row['matkhau'];
        D::execute('INSERT INTO cinema_users (legacy_key,name,email,phone,gender,password_hash,password_scheme) VALUES (?,?,?,?,?,?,?)', [(string)$row['makh'], (string)$row['hoten'], mb_strtolower((string)$row['email']), (string)$row['sodienthoai'], (int)($row['gioitinh'] ?? 2), $hash, password_scheme($hash)]);
        $counts['Khách hàng'] = ($counts['Khách hàng'] ?? 0) + 1;
    }
    foreach (legacy_rows('Administrator') as $row) {
        $hash = (string)$row['matkhau'];
        D::execute('INSERT INTO cinema_admins (username,name,password_hash,password_scheme) VALUES (?,?,?,?)', [(string)$row['tendangnhap'], (string)($row['hoten'] ?? $row['tendangnhap']), $hash, password_scheme($hash)]);
        $counts['Admin'] = ($counts['Admin'] ?? 0) + 1;
    }
    foreach (legacy_rows('BoPhim') as $row) {
        D::execute('INSERT INTO cinema_movies (id,title,genre,age_rating,duration,release_date,end_date,description,poster,trailer) VALUES (?,?,?,?,?,?,?,?,?,?)', [(int)$row['maphim'], (string)$row['tenphim'], (string)$row['theloai'], (int)$row['gioihantuoi'], (int)$row['thoiluong'], (string)$row['ngaykhoichieu'], (string)$row['ngayketthuc'], (string)$row['mota'], import_media($row['anhbia'] ?? null, 'image'), import_media($row['trailer'] ?? null, 'video')]);
        $counts['Phim'] = ($counts['Phim'] ?? 0) + 1;
    }
    foreach (legacy_rows('RapChieu') as $row) {
        D::execute('INSERT INTO cinema_cinemas (id,name,address,phone) VALUES (?,?,?,?)', [(int)$row['marap'], (string)$row['tenrap'], (string)$row['diachi'], (string)$row['sdt']]);
        $counts['Rạp'] = ($counts['Rạp'] ?? 0) + 1;
    }
    foreach (legacy_rows('PhongChieu') as $row) {
        $capacity = (int)$row['soghe'];
        if ($capacity < 1 || $capacity > 780) {
            throw new RuntimeException('Số ghế cũ phải từ 1 đến 780. Kiểm tra phòng ' . $row['maphong']);
        }$columns = min(30, max(min(20, $capacity), (int)ceil($capacity / 26)));
        D::execute('INSERT INTO cinema_rooms (id,cinema_id,name) VALUES (?,?,?)', [(int)$row['maphong'], (int)$row['marap'], (string)$row['tenphong']]);
        C::generateSeats((int)$row['maphong'], (int)ceil($capacity / $columns), $columns, $capacity);
        $counts['Phòng'] = ($counts['Phòng'] ?? 0) + 1;
    }
    foreach (legacy_rows('LichChieu') as $row) {
        $movie = D::one('SELECT duration FROM cinema_movies WHERE id=?', [(int)$row['maphim']]);
        if (!$movie) {
            throw new RuntimeException('Lịch cũ tham chiếu phim không tồn tại.');
        }$start = new DateTimeImmutable($row['ngaychieu'] . ' ' . $row['giochieu']);
        D::execute('INSERT INTO cinema_showtimes (id,movie_id,room_id,starts_at,ends_at,base_price) VALUES (?,?,?,?,?,?)', [(int)$row['malich'], (int)$row['maphim'], (int)$row['maphong'], $start->format('Y-m-d H:i:s'), $start->modify('+' . (int)$movie['duration'] . ' minutes')->format('Y-m-d H:i:s'), (int)$row['giave']]);
        $counts['Lịch chiếu'] = ($counts['Lịch chiếu'] ?? 0) + 1;
    }
    D::execute("INSERT INTO cinema_meta (setting_key,setting_value) VALUES ('legacy_imported_at',?)", [date('c')]);
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    foreach ($copied as $path) {
        Upload::remove($path);
    }throw $error;
}
foreach ($counts as $label => $count) {
    echo "$label: $count\n";
}foreach (array_unique($warnings) as $warning) {
    echo "Lưu ý: $warning\n";
}
echo "Các bảng cũ giữ nguyên. HoaDon/Ve cũ chưa chuyển vì repo không có schema chứng minh quan hệ hóa đơn–vé; cần đối chiếu trước khi nhập lịch sử tài chính. MD5 cũ được nâng cấp khi đăng nhập lần đầu.\n";
