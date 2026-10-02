<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Support\Database as D;

// Start a PHP server with the same test DB, and seed it before this script.
$base = rtrim(getenv('TEST_BASE_URL') ?: 'http://127.0.0.1:8000', '/');
$checks = 0;
function http_check(bool $condition, string $message): void
{
    check($condition, $message);
    $GLOBALS['checks']++;
}
function request(array &$client, string $path, string $method = 'GET', array|string|null $data = null, array $extraHeaders = []): array
{
    $headers = $extraHeaders;
    if (!empty($client['cookie'])) {
        $headers[] = 'Cookie: ' . $client['cookie'];
    }
    if (is_array($data)) {
        $data = http_build_query($data);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $data ?? '', 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 15]]);
    $body = file_get_contents($GLOBALS['base'] . $path, false, $context);
    if ($body === false) {
        throw new RuntimeException('Không gọi được server kiểm thử tại ' . $GLOBALS['base']);
    }
    $status = 0;
    $responseHeaders = [];
    foreach ($http_response_header as $header) {
        if (preg_match('~^HTTP/\S+ (\d+)~', $header, $match)) {
            $status = (int)$match[1];
            continue;
        }
        if (!str_contains($header, ':')) {
            continue;
        }
        [$key,$value] = explode(':', $header, 2);
        $responseHeaders[strtolower($key)] = trim($value);
        if (strtolower($key) === 'set-cookie') {
            $client['cookie'] = explode(';', trim($value))[0];
        }
    }
    return ['status' => $status, 'body' => $body, 'headers' => $responseHeaders];
}
function token(array $response): string
{
    if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $response['body'], $m)) {
        throw new RuntimeException('Không có CSRF token.');
    }return $m[1];
}
function login(array &$client, string $path, string $identifier, string $password): void
{
    $page = request($client, $path);
    $response = request($client, $path, 'POST', ['csrf' => token($page), 'identifier' => $identifier, 'password' => $password]);
    http_check($response['status'] === 303, 'Đăng nhập HTTP thất bại.');
}

$show = D::one('SELECT s.* FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id WHERE s.active=1 AND m.active=1 AND s.starts_at>NOW() ORDER BY s.id LIMIT 1');
check($show !== null, 'Chạy seed.php trước kiểm thử HTTP.');
$sid = (int)$show['id'];
$phone = '09' . random_int(10000000, 99999999);
$email = 'http-' . $phone . '@example.test';
$password = ' SpacePassword123! ';
$a = [];
$b = [];
$admin = [];
$home = request($a, '/');
http_check($home['status'] === 200, 'Trang chủ lỗi.');
http_check(isset($home['headers']['content-security-policy'],$home['headers']['x-content-type-options']), 'Thiếu header bảo vệ.');
http_check(str_contains($home['headers']['set-cookie'] ?? '', 'HttpOnly') && str_contains($home['headers']['set-cookie'] ?? '', 'SameSite=Lax'), 'Cookie thiếu thuộc tính.');
http_check(request($a, '/admin/index.php')['status'] === 303, 'Khách truy cập được admin.');
http_check(request($a, '/auth/logout.php')['status'] === 405, 'GET đăng xuất được.');
http_check(request($a, '/auth/login.php', 'POST', ['identifier' => 'x', 'password' => 'x'])['status'] === 419, 'CSRF thiếu được nhận.');
$register = request($a, '/auth/register.php');
$response = request($a, '/auth/register.php', 'POST', ['csrf' => token($register), 'name' => 'Khách HTTP <script>alert(1)</script>', 'email' => $email, 'phone' => $phone, 'gender' => 2, 'birthday' => '1990-01-01', 'password' => $password, 'confirmation' => $password]);
http_check($response['status'] === 303, 'Đăng ký HTTP thất bại.');
http_check(request($a, '/booking.php?id=' . $sid)['status'] === 303, 'Đặt vé chưa đăng nhập không bị chặn.');
$loginPage = request($a, '/auth/login.php');
$response = request($a, '/auth/login.php', 'POST', ['csrf' => token($loginPage), 'identifier' => $email, 'password' => $password]);
http_check($response['status'] === 303 && str_contains($response['headers']['location'] ?? '', 'booking.php'), 'Không quay lại suất sau đăng nhập.');
$booking = request($a, '/booking.php?id=' . $sid);
http_check($booking['status'] === 200 && str_contains($booking['body'], '&lt;script&gt;'), 'Tên khách chưa escape.');
http_check(!str_contains($booking['body'], 'value="demo"'), 'Demo chưa tắt mặc định.');
preg_match('/name="nonce" value="([a-f0-9]{64})"/', $booking['body'], $m);
$nonce = $m[1] ?? '';
$seatRows = D::all("SELECT s.* FROM cinema_seats s WHERE s.room_id=? AND NOT EXISTS (SELECT 1 FROM cinema_reservations r JOIN cinema_orders o ON o.id=r.order_id WHERE r.seat_id=s.id AND r.showtime_id=? AND (o.status='paid' OR (o.status='pending' AND o.expires_at>NOW()))) ORDER BY s.id LIMIT 2", [$show['room_id'], $sid]);
check(count($seatRows) === 2, 'Cần hai ghế trống trong fixture HTTP.');
$combo = D::one('SELECT * FROM cinema_combos WHERE active=1 LIMIT 1');
$payload = ['csrf' => token($booking), 'nonce' => $nonce, 'seats' => array_column($seatRows, 'id'), 'combos' => [$combo['id'] => 1], 'method' => 'counter', 'total' => 1];
$response = request($a, '/booking.php?id=' . $sid, 'POST', $payload);
http_check($response['status'] === 303 && str_contains($response['headers']['location'] ?? '', 'order.php'), 'Đặt ghế HTTP thất bại.');
parse_str(parse_url($response['headers']['location'], PHP_URL_QUERY), $query);
$oid = (int)$query['id'];
$order = D::one('SELECT * FROM cinema_orders WHERE id=?', [$oid]);
$expected = (int)$show['base_price'] * 2 + array_sum(array_column($seatRows, 'price_extra')) + (int)$combo['price'];
http_check((int)$order['total'] === $expected, 'Tin giá do browser gửi lên.');
$repeat = request($a, '/booking.php?id=' . $sid, 'POST', $payload);
http_check($repeat['headers']['location'] === $response['headers']['location'], 'Gửi lại tạo trùng đơn HTTP.');
$orderPage = request($a, '/order.php?id=' . $oid);
$unauthorizedPay = request($a, '/order.php?id=' . $oid, 'POST', ['csrf' => token($orderPage), 'action' => 'pay']);
http_check($unauthorizedPay['status'] === 303 && D::one('SELECT status FROM cinema_orders WHERE id=?', [$oid])['status'] === 'pending', 'Khách tự xác nhận tiền quầy.');
$otherPhone = '09' . random_int(10000000, 99999999);
$otherEmail = 'http-' . $otherPhone . '@example.test';
$page = request($b, '/auth/register.php');
request($b, '/auth/register.php', 'POST', ['csrf' => token($page), 'name' => 'Khách khác', 'email' => $otherEmail, 'phone' => $otherPhone, 'gender' => 2, 'birthday' => '1990-01-01', 'password' => 'Password123!', 'confirmation' => 'Password123!']);
login($b, '/auth/login.php', $otherEmail, 'Password123!');
http_check(request($b, '/order.php?id=' . $oid)['status'] === 404, 'IDOR: đọc đơn khác.');
http_check(request($b, '/api/seats.php?id=' . $sid)['status'] === 200, 'API ghế lỗi.');
$user = D::one('SELECT id FROM cinema_users WHERE email=?', [$email]);
$page = request($a, '/account/profile.php');
$profile = ['csrf' => token($page), 'action' => 'profile', 'name' => 'Khách đã sửa', 'email' => $email, 'phone' => $phone, 'gender' => 1, 'birthday' => '1991-01-01', 'current_password' => $password];
http_check(request($a, '/account/profile.php', 'POST', $profile)['status'] === 303 && D::one('SELECT name FROM cinema_users WHERE id=?', [$user['id']])['name'] === 'Khách đã sửa', 'Cập nhật hồ sơ lỗi.');
$page = request($a, '/account/profile.php');
$response = request($a, '/account/profile.php', 'POST', ['csrf' => token($page), 'action' => 'password', 'current_password' => $password, 'new_password' => 'UpdatedPassword123!', 'confirmation' => 'UpdatedPassword123!']);
http_check($response['status'] === 303 && request($a, '/account/profile.php')['status'] === 200, 'Đổi mật khẩu mất phiên hiện tại.');

$adminUser = 'httpcheck';
D::execute('INSERT INTO cinema_admins (username,name,password_hash) VALUES (?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),active=1', [$adminUser, 'HTTP Test', password_hash('Password123!', PASSWORD_DEFAULT)]);
login($admin, '/admin/login.php', $adminUser, 'Password123!');
foreach (['/admin/index.php', '/admin/movies.php', '/admin/cinemas.php', '/admin/showtimes.php', '/admin/orders.php', '/admin/revenue.php', '/admin/users.php', '/admin/combos.php', '/admin/password.php', '/account/tickets.php'] as $path) {
    $client = $path === '/account/tickets.php' ? $a : $admin;
    $r = request($client, $path);
    http_check($r['status'] === 200, 'Route lỗi: ' . $path);
}
$page = request($admin, '/admin/order.php?id=' . $oid);
$response = request($admin, '/admin/order.php?id=' . $oid, 'POST', ['csrf' => token($page), 'action' => 'pay']);
http_check($response['status'] === 303 && D::one('SELECT status FROM cinema_orders WHERE id=?', [$oid])['status'] === 'paid', 'Admin không xác nhận được.');
http_check(str_contains(request($a, '/order.php?id=' . $oid)['body'], 'In thông tin vé'), 'Không xem/in vé paid được.');
$page = request($admin, '/admin/movie-edit.php');
$fields = ['csrf' => token($page), 'title' => 'Phim HTTP ' . $phone, 'genre' => 'Kiểm thử', 'age_rating' => 0, 'duration' => 95, 'release_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'description' => 'Mô tả HTTP'];
function multipart(array $fields, string $filename, string $content, string $mime): array
{
    $boundary = 'cinema' . bin2hex(random_bytes(16));
    $body = '';
    foreach ($fields as $key => $value) {
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
    }
    $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"poster\"; filename=\"$filename\"\r\nContent-Type: $mime\r\n\r\n" . $content . "\r\n--$boundary--\r\n";
    return [$body, ['Content-Type: multipart/form-data; boundary=' . $boundary]];
}
[$body,$headers] = multipart($fields, 'poster.jpg', '<?php echo "bad"; ?>', 'image/jpeg');
request($admin, '/admin/movie-edit.php', 'POST', $body, $headers);
http_check(D::one('SELECT id FROM cinema_movies WHERE title=?', [$fields['title']]) === null, 'Upload PHP giả JPEG được nhận.');
$page = request($admin, '/admin/movie-edit.php');
$fields['csrf'] = token($page);
[$body,$headers] = multipart($fields, 'poster.php', file_get_contents(APP_ROOT . '/public/assets/images/jujutsu-kaisen-500-1759216418459.jpg'), 'image/jpeg');
request($admin, '/admin/movie-edit.php', 'POST', $body, $headers);
$movie = D::one('SELECT * FROM cinema_movies WHERE title=?', [$fields['title']]);
http_check($movie !== null && preg_match('/^upload:[a-f0-9]{40}\.jpg$/', $movie['poster']) === 1, 'Không chuẩn hóa MIME/tên file.');
$media = '/media.php?file=' . substr($movie['poster'], 7);
$full = request($a, $media);
http_check($full['status'] === 200 && $full['headers']['content-type'] === 'image/jpeg', 'Media không phục vụ ảnh.');
$range = request($a, $media, 'GET', null, ['Range: bytes=0-9']);
http_check($range['status'] === 206 && strlen($range['body']) === 10 && $range['body'] === substr($full['body'], 0, 10), 'Range media sai.');
http_check(request($a, $media, 'GET', null, ['Range: bytes=999999999-'])['status'] === 416, 'Nhận range ngoài file.');
http_check(request($a, '/media.php?file=../../config/local.php')['status'] === 404, 'Đọc file ngoài upload.');
$page = request($a, '/account/profile.php');
$response = request($a, '/auth/logout.php', 'POST', ['csrf' => token($page), 'role' => 'user']);
http_check($response['status'] === 303 && request($a, '/account/profile.php')['status'] === 303, 'Đăng xuất chưa xóa phiên.');
echo "PASS HTTP: $checks kiểm tra đăng ký, phiên, CSRF, hồ sơ, đặt vé, IDOR, admin, upload và media range.\n";
