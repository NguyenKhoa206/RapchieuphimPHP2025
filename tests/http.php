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
foreach (['/admin/index.php', '/admin/movies.php', '/admin/cinemas.php', '/admin/showtimes.php', '/admin/orders.php', '/admin/revenue.php', '/admin/users.php', '/admin/combos.php', '/admin/password.php', '/admin/payments.php', '/admin/history.php', '/account/tickets.php', '/account/tickets.php?tab=invoices', '/account/tickets.php?tab=tickets'] as $path) {
    $client = str_starts_with($path, '/account/') ? $a : $admin;
    $r = request($client, $path);
    http_check($r['status'] === 200, 'Route lỗi: ' . $path);
}
$page = request($admin, '/admin/order.php?id=' . $oid);
$response = request($admin, '/admin/order.php?id=' . $oid, 'POST', ['csrf' => token($page), 'action' => 'pay']);
http_check($response['status'] === 303 && D::one('SELECT status FROM cinema_orders WHERE id=?', [$oid])['status'] === 'paid', 'Admin không xác nhận được.');
http_check(str_contains(request($a, '/order.php?id=' . $oid)['body'], 'In thông tin vé'), 'Không xem/in vé paid được.');
$page = request($admin, '/admin/movie-edit.php');
$fields = ['csrf' => token($page), 'title' => 'Phim HTTP ' . $phone, 'genre' => 'Kiểm thử', 'age_rating' => 0, 'duration' => 95, 'release_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+20 days')), 'description' => 'Mô tả HTTP'];
function multipart(array $fields, string $filename, string $content, string $mime, string $field = 'poster'): array
{
    $boundary = 'cinema' . bin2hex(random_bytes(16));
    $body = '';
    foreach ($fields as $key => $value) {
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
    }
    $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$field\"; filename=\"$filename\"\r\nContent-Type: $mime\r\n\r\n" . $content . "\r\n--$boundary--\r\n";
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
// Configured test merchant only; no real provider request is made.
$booking = request($a, '/booking.php?id=' . $sid);
http_check(str_contains($booking['body'], 'value="vnpay"'), 'Thiếu lựa chọn VNPay đã cấu hình.');
preg_match('/name="nonce" value="([a-f0-9]{64})"/', $booking['body'], $nonceMatch);
$onlineSeat = D::one("SELECT s.id FROM cinema_seats s WHERE s.room_id=? AND NOT EXISTS (SELECT 1 FROM cinema_reservations r JOIN cinema_orders o ON o.id=r.order_id WHERE r.seat_id=s.id AND r.showtime_id=? AND (o.status='paid' OR (o.status='pending' AND o.expires_at>NOW()))) LIMIT 1", [$show['room_id'], $sid]);
$response = request($a, '/booking.php?id=' . $sid, 'POST', ['csrf' => token($booking), 'nonce' => $nonceMatch[1], 'seats' => [$onlineSeat['id']], 'combos' => [], 'method' => 'vnpay']);
parse_str((string) parse_url($response['headers']['location'], PHP_URL_QUERY), $onlineQuery);
$onlineId = (int) $onlineQuery['id'];
$onlinePage = request($a, '/order.php?id=' . $onlineId);
http_check(str_contains($onlinePage['body'], 'Thanh toán qua VNPay') && str_contains($onlinePage['headers']['content-security-policy'], 'https://sandbox.vnpayment.vn'), 'Thiếu nút/CSP thanh toán.');
http_check(request($a, '/payment/start.php?id=' . $onlineId)['status'] === 405, 'GET tạo thanh toán được.');
http_check(request($a, '/payment/start.php', 'POST', ['id' => $onlineId])['status'] === 419, 'Thanh toán không có CSRF được.');
$otherPage = request($b, '/account/profile.php');
request($b, '/payment/start.php', 'POST', ['id' => $onlineId, 'csrf' => token($otherPage)]);
http_check(D::one('SELECT id FROM cinema_payments WHERE order_id=?', [$onlineId]) === null, 'Khách khác tạo được giao dịch.');
$redirect = request($a, '/payment/start.php', 'POST', ['id' => $onlineId, 'csrf' => token($onlinePage)]);
http_check($redirect['status'] === 303 && str_starts_with($redirect['headers']['location'] ?? '', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?'), 'Không chuyển hướng đúng VNPay.');
$attempt = D::one('SELECT * FROM cinema_payments WHERE order_id=?', [$onlineId]);
$callback = ['vnp_Amount' => (string) ((int) $attempt['amount'] * 100), 'vnp_BankCode' => 'NCB', 'vnp_TransactionNo' => (string) (500000 + (int) $attempt['id']), 'vnp_TxnRef' => $attempt['txn_ref'], 'vnp_TmnCode' => 'TEST1234', 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_PayDate' => date('YmdHis')];
$callback['vnp_SecureHash'] = App\Services\PaymentService::signature($callback, 'test-only-vnpay-signing-secret');
$return = request($a, '/payment/return.php?' . http_build_query($callback));
http_check($return['status'] === 303 && str_contains($return['headers']['location'], 'order.php') && D::one('SELECT status FROM cinema_orders WHERE id=?', [$onlineId])['status'] === 'pending', 'ReturnURL đã cập nhật trả tiền.');
http_check(request($b, '/api/order-status.php?id=' . $onlineId)['status'] === 404, 'API trạng thái lộ đơn người khác.');
$adminOnline = request($admin, '/admin/order.php?id=' . $onlineId);
request($admin, '/admin/order.php?id=' . $onlineId, 'POST', ['csrf' => token($adminOnline), 'action' => 'pay']);
http_check(D::one('SELECT status FROM cinema_orders WHERE id=?', [$onlineId])['status'] === 'pending', 'Admin xác nhận online thủ công.');
$provider = [];
$badIpn = request($provider, '/payment/ipn.php?vnp_SecureHash=invalid');
http_check($badIpn['status'] === 200 && json_decode($badIpn['body'], true)['RspCode'] === '97', 'IPN giả không bị từ chối.');
$ipnPath = '/payment/ipn.php?' . http_build_query($callback);
$ipn = request($provider, $ipnPath);
http_check($ipn['status'] === 200 && json_decode($ipn['body'], true)['RspCode'] === '00', 'IPN HTTP hợp lệ lỗi.');
http_check(json_decode(request($provider, $ipnPath)['body'], true)['RspCode'] === '02', 'IPN HTTP lặp không idempotent.');
$onlinePaid = request($a, '/order.php?id=' . $onlineId);
http_check(str_contains($onlinePaid['body'], 'VNPay Sandbox') && !str_contains($onlinePaid['body'], 'Mã này tại quầy') && str_contains($onlinePaid['body'], 'không dùng để vào rạp'), 'Vé sandbox thiếu nhãn thử nghiệm.');
http_check(json_decode(request($a, '/api/order-status.php?id=' . $onlineId)['body'], true)['status'] === 'paid', 'API không cập nhật trạng thái.');
http_check(isset($onlinePaid['headers']['cache-control']) && str_contains($onlinePaid['headers']['cache-control'], 'no-store'), 'Đơn cá nhân bị cache.');

D::execute('INSERT INTO cinema_history_batches (source_name,mapping_hash,invoice_count,ticket_count) VALUES (?,?,1,0)', ['http-' . $phone, hash('sha256', 'http')]);
$batch = (int) db()->lastInsertId();
D::execute('INSERT INTO cinema_legacy_invoices (source_name,source_key,source_hash,batch_id,user_id,amount,original_status,raw_data) VALUES (?,?,?,?,?,?,?,?)', ['http-' . $phone, 'HD-XSS-' . $phone, hash('sha256', 'fixture'), $batch, $user['id'], 120000, '<script>history-xss</script>', '{"secret":"admin-only"}']);
$historyId = (int) db()->lastInsertId();
$historic = request($a, '/account/history.php?kind=invoices&id=' . $historyId);
http_check($historic['status'] === 200 && str_contains($historic['body'], '&lt;script&gt;history-xss&lt;/script&gt;') && !str_contains($historic['body'], 'admin-only'), 'Lịch sử chưa escape hoặc lộ raw data.');
http_check(request($b, '/account/history.php?kind=invoices&id=' . $historyId)['status'] === 404, 'IDOR lịch sử hóa đơn.');
http_check(str_contains(request($a, '/account/tickets.php?tab=invoices')['body'], 'HD-XSS-' . $phone) && !str_contains(request($b, '/account/tickets.php?tab=invoices')['body'], 'HD-XSS-' . $phone), 'Danh sách lịch sử lộ tài khoản.');
http_check(request($admin, '/admin/history.php?id=99999999')['status'] === 404, 'Admin history không trả 404.');
http_check(str_contains(request($admin, '/admin/history.php?kind=invoices&id=' . $historyId)['body'], 'admin-only'), 'Admin không đối chiếu được dữ liệu nguồn.');
http_check(request($a, '/admin/history.php')['status'] === 303, 'Khách vào được lịch sử quản trị.');

$newsPage = request($admin, '/admin/news-edit.php');
http_check($newsPage['status'] === 200, 'Không mở được editor tin.');
$newsFields = ['csrf' => token($newsPage), 'title' => 'Tin HTTP ' . $phone . ' <script>news-xss</script>', 'summary' => 'Mô tả <img src=x onerror=alert(1)>', 'body' => "Dòng đầu\nNội dung <script>alert(1)</script>", 'category' => 'promotion', 'status' => 'draft', 'published_at' => date('Y-m-d\TH:i', strtotime('-1 day')), 'expires_at' => '', 'stock_image' => 'com.jpg'];
http_check(request($a, '/admin/news-edit.php', 'POST', $newsFields)['status'] === 303 && D::one('SELECT id FROM cinema_news WHERE title=?', [$newsFields['title']]) === null, 'Khách đăng được tin.');
http_check(request($admin, '/admin/news-edit.php', 'POST', array_replace($newsFields, ['csrf' => 'bad']))['status'] === 419, 'Tin bỏ qua CSRF.');
$missingImage = request($admin, '/admin/news-edit.php', 'POST', array_replace($newsFields, ['stock_image' => '']));
http_check($missingImage['status'] === 200 && str_contains($missingImage['body'], e($newsFields['body'])) && D::one('SELECT id FROM cinema_news WHERE title=?', [$newsFields['title']]) === null, 'Tin thiếu ảnh không được kiểm tra hoặc mất nội dung nhập.');
$saved = request($admin, '/admin/news-edit.php', 'POST', $newsFields);
http_check($saved['status'] === 303, 'Không lưu được bản nháp.');
$newsId = (int) D::one('SELECT id FROM cinema_news WHERE title=?', [$newsFields['title']])['id'];
$editPath = '/admin/news-edit.php?id=' . $newsId;
$publicPath = '/news-detail.php?id=' . $newsId;
http_check(request($a, $publicPath)['status'] === 404 && !str_contains(request($a, '/news.php')['body'], e($newsFields['title'])), 'Bản nháp lộ công khai.');
$editPage = request($admin, $editPath);
http_check(str_contains($editPage['body'], e($newsFields['body'])) && !str_contains($editPage['body'], '<script>alert(1)</script>'), 'Editor tin không escape nội dung.');
$newsFields['csrf'] = token($editPage);
$newsFields['stock_image'] = '';
$invalidDate = request($admin, $editPath, 'POST', array_replace($newsFields, ['expires_at' => $newsFields['published_at']]));
http_check($invalidDate['status'] === 200 && str_contains($invalidDate['body'], 'alert-error') && D::one('SELECT expires_at FROM cinema_news WHERE id=?', [$newsId])['expires_at'] === null, 'Tin nhận thời gian hết hạn sai.');
$newsFields['status'] = 'published';
http_check(request($admin, $editPath, 'POST', $newsFields)['status'] === 303, 'Không đăng được tin.');
$public = request($a, $publicPath);
http_check($public['status'] === 200 && str_contains($public['body'], nl2br(e($newsFields['body']))) && !str_contains($public['body'], '<script>alert(1)</script>'), 'Trang tin không escape nội dung.');
http_check(str_contains(request($a, '/')['body'], e($newsFields['title'])) && str_contains(request($a, '/news.php?category=promotion')['body'], e($newsFields['title'])) && !str_contains(request($a, '/news.php?category=news')['body'], e($newsFields['title'])), 'Trang chủ/lọc tin chưa dùng dữ liệu mới.');
$newsFields['published_at'] = date('Y-m-d\TH:i', strtotime('+1 day'));
request($admin, $editPath, 'POST', $newsFields);
http_check(request($a, $publicPath)['status'] === 404 && !str_contains(request($a, '/')['body'], e($newsFields['title'])), 'Tin hẹn giờ bị hiển thị sớm.');
$newsFields['published_at'] = date('Y-m-d\TH:i', strtotime('-2 days'));
$newsFields['expires_at'] = date('Y-m-d\TH:i', strtotime('-1 day'));
request($admin, $editPath, 'POST', $newsFields);
http_check(request($a, $publicPath)['status'] === 404, 'Bài hết hạn vẫn đọc được.');
$newsFields['expires_at'] = '';
[$newsBody, $newsHeaders] = multipart($newsFields, 'cover.jpg', '<?php echo "bad"; ?>', 'image/jpeg', 'image');
http_check(request($admin, $editPath, 'POST', $newsBody, $newsHeaders)['status'] === 200 && D::one('SELECT image FROM cinema_news WHERE id=?', [$newsId])['image'] === 'tinmoikhuyenmai/com.jpg', 'Ảnh tin PHP giả JPEG được nhận.');
[$newsBody, $newsHeaders] = multipart($newsFields, 'cover.php', file_get_contents(APP_ROOT . '/public/assets/images/tinmoikhuyenmai/com.jpg'), 'image/jpeg', 'image');
http_check(request($admin, $editPath, 'POST', $newsBody, $newsHeaders)['status'] === 303, 'Ảnh tin hợp lệ không lưu được.');
$imageOne = D::one('SELECT image FROM cinema_news WHERE id=?', [$newsId])['image'];
http_check(preg_match('/^upload:[a-f0-9]{40}\.jpg$/', $imageOne) === 1 && request($a, '/media.php?file=' . substr($imageOne, 7))['headers']['content-type'] === 'image/jpeg', 'Ảnh tin không đổi tên an toàn hoặc không đọc được.');
request($admin, $editPath, 'POST', $newsFields);
http_check(D::one('SELECT image FROM cinema_news WHERE id=?', [$newsId])['image'] === $imageOne, 'Sửa tin không tải ảnh làm mất ảnh cũ.');
[$newsBody, $newsHeaders] = multipart($newsFields, 'new-cover.jpg', file_get_contents(APP_ROOT . '/public/assets/images/tinmoikhuyenmai/snack.jpg'), 'image/jpeg', 'image');
request($admin, $editPath, 'POST', $newsBody, $newsHeaders);
$imageTwo = D::one('SELECT image FROM cinema_news WHERE id=?', [$newsId])['image'];
http_check($imageTwo !== $imageOne && !is_file(APP_ROOT . '/storage/uploads/' . substr($imageOne, 7)), 'Thay ảnh tin không dọn file cũ.');
$adminNews = request($admin, '/admin/news.php');
http_check($adminNews['status'] === 200 && str_contains($adminNews['body'], e($newsFields['title'])), 'Danh sách admin không có bài mới.');
http_check(request($admin, '/admin/news.php', 'POST', ['csrf' => 'bad', 'action' => 'hide', 'id' => $newsId])['status'] === 419, 'Ẩn tin bỏ qua CSRF.');
request($admin, '/admin/news.php', 'POST', ['csrf' => token($adminNews), 'action' => 'hide', 'id' => $newsId]);
http_check(request($a, $publicPath)['status'] === 404 && !str_contains(request($a, '/news.php')['body'], e($newsFields['title'])), 'Tin đã ẩn vẫn công khai.');
http_check(request($a, '/news-detail.php?id=99999999')['status'] === 404 && request($admin, '/admin/news-edit.php?id=99999999')['status'] === 404, 'ID tin không tồn tại không trả 404.');
App\Support\Upload::remove($imageTwo);

$page = request($a, '/account/profile.php');
$response = request($a, '/auth/logout.php', 'POST', ['csrf' => token($page), 'role' => 'user']);
http_check($response['status'] === 303 && request($a, '/account/profile.php')['status'] === 303, 'Đăng xuất chưa xóa phiên.');
echo "PASS HTTP: $checks kiểm tra đăng ký, phiên, CSRF, hồ sơ, đặt vé, IDOR, VNPay/IPN, lịch sử, tin/ưu đãi, admin, upload và media range.\n";
