<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Support\Database as D;
use App\Support\Auth;
use App\Services\BookingService as B;
use App\Services\CinemaService as C;
use App\Services\ReportService as R;

session_start();
$f = reset_fixture();
extract($f);
$results = [];
function test(string $name, callable $callback): void
{
    try {
        $callback();
        $GLOBALS['results'][] = 'PASS ' . $name;
    } catch (Throwable $error) {
        $GLOBALS['results'][] = 'FAIL ' . $name . ': ' . $error->getMessage();
    }
}
$nonce = static fn (): string => bin2hex(random_bytes(32));

test('SQL injection không đăng nhập được', static function (): void {
    check(!Auth::login('admin', "' OR 1=1 -- ", 'wrong'), 'SQL injection bypass');
    check(!Auth::login('user', "' OR 1=1 -- ", 'wrong'), 'SQL injection bypass');
});
test('Mật khẩu hiện đại và phiên có version', static function () use ($users): void {
    check(Auth::login('user', 'one@example.test', 'Password123!'), 'Không đăng nhập được');
    check((int) Auth::user()['id'] === (int) $users[0]['id'], 'Nhận sai tài khoản');
});
test('MD5 cũ nâng cấp sau đăng nhập', static function () use ($users): void {
    D::execute("UPDATE cinema_users SET password_hash=?,password_scheme='legacy_md5' WHERE id=?", [md5('Legacy123!'), $users[1]['id']]);
    check(Auth::login('user', 'two@example.test', 'Legacy123!'), 'Legacy login lỗi');
    $row = D::one('SELECT * FROM cinema_users WHERE id=?', [$users[1]['id']]);
    check($row['password_scheme'] === 'modern' && password_verify('Legacy123!', $row['password_hash']), 'MD5 chưa nâng cấp');
});
test('Đổi mật khẩu vô hiệu phiên cũ', static function () use ($users): void {
    $old = $_SESSION['user'];
    Auth::changePassword('user', (int)$users[1]['id'], 'Legacy123!', 'NewPassword123!', 'NewPassword123!');
    check(Auth::user() !== null, 'Phiên hiện tại bị mất');
    $_SESSION['user'] = $old;
    check(Auth::user() === null, 'Phiên cũ chưa bị thu hồi');
    check(Auth::login('user', 'two@example.test', 'NewPassword123!'), 'Mật khẩu mới không dùng được');
});
test('Khóa tạm sau 5 lần sai', static function (): void {
    for ($i = 0;$i < 5;$i++) {
        check(!Auth::login('admin', 'testadmin', 'wrong'), 'Sai mật khẩu vẫn vào được');
    }
    rejects(static fn () => Auth::login('admin', 'testadmin', 'Password123!'), 'Không giới hạn lần thử');
    D::execute('DELETE FROM cinema_login_attempts');
});
test('Liên hệ và mật khẩu sai bị từ chối', static function (): void {
    rejects(static fn () => Auth::validatePassword('short'), 'Mật khẩu ngắn được nhận');
    rejects(static fn () => Auth::validatePassword(str_repeat('á', 40)), 'Bỏ qua giới hạn byte');
    rejects(static fn () => Auth::validateContact(['name' => 'A', 'email' => 'bad', 'phone' => '0900000000', 'gender' => 2, 'birthday' => '']), 'Email sai được nhận');
    rejects(static fn () => Auth::validateContact(['name' => 'A', 'email' => 'ok@example.test', 'phone' => '0900000000', 'gender' => 2, 'birthday' => '2026-02-31']), 'Ngày tự chuẩn hóa được nhận');
});
test('Email trùng bị từ chối', static function (): void {
    rejects(static fn () => Auth::register(['name' => 'Duplicate', 'email' => 'one@example.test', 'phone' => '0900000099', 'gender' => 2, 'birthday' => '', 'password' => 'Password123!', 'confirmation' => 'Password123!']), 'Đăng ký email trùng');
});
test('Giá ghế VIP và combo lấy từ máy chủ', static function () use ($show, $users, $seats, $combo, $nonce): void {
    $id = B::placeOrder($show, $users[0], [$seats[0], $seats[1]], [$combo => 2], 'counter', $nonce());
    $GLOBALS['paidOrder'] = $id;
    check((int)B::order($id)['total'] === 320000, 'Sai giá 2 ghế VIP + 2 combo');
    check(count(B::order($id)['items']) === 3, 'Thiếu snapshot dòng hàng');
});
test('Gửi lại cùng request không tạo đơn mới', static function () use ($show, $users, $seats, $nonce): void {
    $n = $nonce();
    $first = B::placeOrder($show, $users[0], [$seats[2]], [], 'counter', $n);
    $again = B::placeOrder($show, $users[0], [$seats[2]], [], 'counter', $n);
    check($first === $again, 'Tạo trùng đơn');
    B::transition($first, 'cancel', (int)$users[0]['id']);
});
test('Ghế đã giữ không đặt được lần hai', static function () use ($show, $users, $seats, $nonce): void {
    rejects(static fn () => B::placeOrder($show, $users[1], [$seats[0]], [], 'counter', $nonce()), 'Đặt trùng ghế');
});
test('Ghế sai phòng/trùng/nhiều bị từ chối', static function () use ($show, $users, $seats, $otherRoom, $nonce): void {
    $wrong = D::one('SELECT id FROM cinema_seats WHERE room_id=? LIMIT 1', [$otherRoom])['id'];
    rejects(static fn () => B::placeOrder($show, $users[0], [$wrong], [], 'counter', $nonce()), 'Nhận ghế sai phòng');
    rejects(static fn () => B::placeOrder($show, $users[0], [$seats[3], $seats[3]], [], 'counter', $nonce()), 'Nhận ghế trùng');
    rejects(static fn () => B::placeOrder($show, $users[0], array_slice($seats, 0, 9), [], 'counter', $nonce()), 'Nhận hơn 8 ghế');
});
test('Combo sai/số lượng âm bị từ chối', static function () use ($show, $users, $seats, $combo, $nonce): void {
    rejects(static fn () => B::placeOrder($show, $users[0], [$seats[3]], [$combo => -1], 'counter', $nonce()), 'Số lượng âm được nhận');
    rejects(static fn () => B::placeOrder($show, $users[0], [$seats[3]], [999999 => 1], 'counter', $nonce()), 'Nhận combo không có');
});
test('Không đọc/xử lý đơn của khách khác', static function () use ($users): void {
    check(B::order($GLOBALS['paidOrder'], (int)$users[1]['id']) === null, 'Lộ đơn khác');
    rejects(static fn () => B::transition($GLOBALS['paidOrder'], 'cancel', (int)$users[1]['id']), 'Hủy đơn khác');
});
test('Khách không tự trả tiền tại quầy', static function () use ($users): void {
    rejects(static fn () => B::transition($GLOBALS['paidOrder'], 'pay', (int)$users[0]['id']), 'Khách tự xác nhận tiền');
});
test('Admin xác nhận và không cộng lặp', static function () use ($admin): void {
    B::transition($GLOBALS['paidOrder'], 'pay', null, $admin);
    check(B::order($GLOBALS['paidOrder'])['status'] === 'paid', 'Chưa chuyển paid');
    rejects(static fn () => B::transition($GLOBALS['paidOrder'], 'pay', null, $admin), 'Nhận xác nhận lần hai');
    rejects(static fn () => B::transition($GLOBALS['paidOrder'], 'cancel', null, $admin), 'Hủy vé paid');
});
test('Hủy đơn trả ghế', static function () use ($show, $users, $seats, $nonce): void {
    $id = B::placeOrder($show, $users[0], [$seats[3]], [], 'counter', $nonce());
    B::transition($id, 'cancel', (int)$users[0]['id']);
    $next = B::placeOrder($show, $users[1], [$seats[3]], [], 'counter', $nonce());
    check($next !== $id && B::order($id)['status'] === 'cancelled', 'Ghế chưa được trả');
    B::transition($next, 'cancel', (int)$users[1]['id']);
});
test('Đơn hết hạn hiển thị và giải phóng ghế', static function () use ($show, $users, $seats, $nonce): void {
    $id = B::placeOrder($show, $users[0], [$seats[4]], [], 'counter', $nonce());
    D::execute('UPDATE cinema_orders SET expires_at=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE id=?', [$id]);
    check(B::order($id)['status'] === 'expired', 'Chưa hiển thị expired');
    rejects(static fn () => B::transition($id, 'pay', null, $GLOBALS['admin']), 'Trả tiền đơn hết hạn');
    $next = B::placeOrder($show, $users[1], [$seats[4]], [], 'counter', $nonce());
    check(D::one('SELECT status FROM cinema_orders WHERE id=?', [$id])['status'] === 'expired', 'Chưa dọn đơn');
    B::transition($next, 'cancel', (int)$users[1]['id']);
});
test('Giới hạn tuổi có hiệu lực ở máy chủ', static function () use ($show, $movie, $users, $seats, $nonce): void {
    D::execute('UPDATE cinema_movies SET age_rating=18 WHERE id=?', [$movie]);
    $young = $users[0];
    $young['birthday'] = date('Y-m-d', strtotime('-10 years'));
    rejects(static fn () => B::placeOrder($show, $young, [$seats[5]], [], 'counter', $nonce()), 'Khách thiếu tuổi đặt được');
    $young['birthday'] = null;
    rejects(static fn () => B::placeOrder($show, $young, [$seats[5]], [], 'counter', $nonce()), 'Không ngày sinh đặt được');
    D::execute('UPDATE cinema_movies SET age_rating=0 WHERE id=?', [$movie]);
});
test('Demo mặc định tắt và báo cáo tách riêng', static function () use ($show, $users, $seats, $nonce): void {
    $GLOBALS['app_config']['allow_demo_payment'] = false;
    rejects(static fn () => B::placeOrder($show, $users[0], [$seats[5]], [], 'demo', $nonce()), 'Demo không tắt');
    $GLOBALS['app_config']['allow_demo_payment'] = true;
    $id = B::placeOrder($show, $users[0], [$seats[5]], [], 'demo', $nonce());
    B::transition($id, 'pay', (int)$users[0]['id']);
    $GLOBALS['app_config']['allow_demo_payment'] = false;
    $real = R::revenue('month', (int)date('Y'), null);
    $demo = R::revenue('month', (int)date('Y'), null, true);
    check((int)$real[0]['total'] === 320000 && (int)$demo[0]['total'] === 110000, 'Gộp tiền demo/nhân dòng hàng');
});
test('Doanh thu ngày/tháng/năm và theo rạp', static function () use ($cinema): void {
    foreach (['day' => date('Y-m-d'), 'month' => date('Y-m'), 'year' => date('Y')] as $period => $label) {
        $rows = R::revenue($period, (int)date('Y'), $cinema);
        check(count($rows) === 1 && $rows[0]['label'] === $label && (int)$rows[0]['orders'] === 1 && (int)$rows[0]['total'] === 320000, 'Nhóm doanh thu sai');
    }
    check(R::revenue('day', (int)date('Y'), 999999) === [], 'Sai bộ lọc rạp');
    rejects(static fn () => R::revenue('month', 0, null), 'Nhận năm sai');
});
test('Không sửa/xóa lịch có đơn và không xóa lịch sử', static function () use ($show, $movie, $room, $cinema): void {
    rejects(static fn () => C::saveShowtime($show, $movie, $room, date('Y-m-d', strtotime('+2 days')) . 'T19:00', 90000, 'Test'), 'Sửa lịch có đơn');
    foreach (['showtime' => $show, 'movie' => $movie, 'room' => $room, 'cinema' => $cinema] as $kind => $id) {
        rejects(static fn () => C::archive($kind, $id), 'Ngừng dữ liệu còn lịch/vé');
    }
    check(B::order($GLOBALS['paidOrder']) !== null, 'Mất lịch sử');
});
test('Trùng lịch qua nửa đêm bị từ chối', static function () use ($movie, $otherRoom): void {
    $day = date('Y-m-d', strtotime('+3 days'));
    $next = date('Y-m-d', strtotime('+4 days'));
    C::saveShowtime(null, $movie, $otherRoom, $day . 'T23:00', 90000, 'Test');
    rejects(static fn () => C::saveShowtime(null, $movie, $otherRoom, $next . 'T00:45', 90000, 'Test'), 'Bỏ qua dọn phòng qua nửa đêm');
    C::saveShowtime(null, $movie, $otherRoom, $next . 'T00:55', 90000, 'Test');
});
test('Sửa thời lượng phim không làm lệch lịch đã tạo', static function () use ($movie): void {
    $input = D::one('SELECT * FROM cinema_movies WHERE id=?', [$movie]);
    $input['duration'] = (int)$input['duration'] + 1;
    rejects(static fn () => C::saveMovie($movie, $input), 'Sửa thời lượng khi còn lịch tương lai');
    check((int)D::one('SELECT duration FROM cinema_movies WHERE id=?', [$movie])['duration'] === 100, 'Không rollback thay đổi phim');
});
test('Sơ đồ ghế ngoài giới hạn bị từ chối', static function () use ($room): void {
    rejects(static fn () => C::generateSeats($room, 27, 30, 780), 'Nhận quá 26 hàng');
    rejects(static fn () => C::generateSeats($room, 1, 1, 2), 'Nhận quá sức chứa sơ đồ');
});
test('Archive danh mục chưa dùng giữ bản ghi', static function (): void {
    D::execute('INSERT INTO cinema_combos (name,price) VALUES (?,?)', ['Ngừng bán', 25000]);
    $id = (int)db()->lastInsertId();
    C::archive('combo', $id);
    check((int)D::one('SELECT active FROM cinema_combos WHERE id=?', [$id])['active'] === 0, 'Xóa cứng combo');
});
test('Tài khoản bị khóa mất phiên', static function () use ($users): void {
    Auth::login('user', 'one@example.test', 'Password123!');
    D::execute('UPDATE cinema_users SET active=0,auth_version=auth_version+1 WHERE id=?', [$users[0]['id']]);
    check(Auth::user() === null, 'Tài khoản khóa vẫn đăng nhập');
});

session_write_close();
echo implode(PHP_EOL, $results) . PHP_EOL;
$failures = count(array_filter($results, static fn ($line) => str_starts_with($line, 'FAIL')));
echo count($results) . ' kiểm thử, ' . $failures . ' thất bại.' . PHP_EOL;
exit($failures ? 1 : 0);
