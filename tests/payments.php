<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Services\BookingService as B;
use App\Services\PaymentService as P;
use App\Services\ReportService as R;
use App\Support\Database as D;

$GLOBALS['app_config']['payments']['vnpay'] = ['enabled' => true, 'environment' => 'sandbox', 'tmn_code' => 'TEST1234', 'hash_secret' => 'test-only-vnpay-signing-secret', 'return_url' => 'https://cinema.example/payment/return.php'];
$checks = 0;
function payment_check(bool $condition, string $message): void
{
    check($condition, $message);
    $GLOBALS['checks']++;
}
function callback(int $order, array $override = []): array
{
    $attempt = D::one('SELECT * FROM cinema_payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$order]);
    $p = array_replace(['vnp_TmnCode' => 'TEST1234', 'vnp_Amount' => (string) ((int) $attempt['amount'] * 100), 'vnp_BankCode' => 'NCB', 'vnp_TransactionNo' => (string) (100000 + (int) $attempt['id']), 'vnp_TxnRef' => $attempt['txn_ref'], 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_PayDate' => date('YmdHis')], $override);
    $p['vnp_SecureHash'] = P::signature($p, 'test-only-vnpay-signing-secret');
    return $p;
}
function online_order(array $f, int $seat = 0): int
{
    return B::placeOrder($f['show'], $f['users'][0], [$f['seats'][$seat]], [], 'vnpay', bin2hex(random_bytes(32)));
}

$vector = ['vnp_TxnRef' => 'ABC0123', 'vnp_OrderInfo' => 'Thanh toan ve A + B', 'vnp_Amount' => '11000000', 'vnp_TmnCode' => 'TEST1234'];
payment_check(P::signature($vector, 'test-only-vnpay-signing-secret') === 'a5ee688bea354e8615a2d428f513567657f2b1e7c279c10267db46da3dc411e532652609cb0eb38830b7c8863e4ba19c7a8d9ddba00caa00eba3c87127126343', 'Sai HMAC/RFC1738 so với vector độc lập Python.');
$f = reset_fixture();
payment_check(P::available(), 'Cấu hình sandbox hợp lệ không được nhận.');
$GLOBALS['app_config']['payments']['vnpay']['return_url'] = 'http://cinema.example/payment/return.php';
payment_check(!P::available(), 'Callback thiếu HTTPS được cho phép.');
$GLOBALS['app_config']['payments']['vnpay']['return_url'] = 'https://cinema.example/payment/return.php';
$GLOBALS['app_config']['payments']['vnpay']['enabled'] = false;
rejects(fn () => online_order($f), 'Cổng chưa bật vẫn tạo được đơn online.');
$checks++;
$GLOBALS['app_config']['payments']['vnpay']['enabled'] = true;
$order = online_order($f);
$url = P::start($order, (int) $f['users'][0]['id'], '127.0.0.1');
parse_str((string) parse_url($url, PHP_URL_QUERY), $request);
payment_check(str_starts_with($url, 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?') && $request['vnp_Amount'] === '11000000' && P::verified($request) !== null, 'URL/giá/chữ ký yêu cầu sai.');
payment_check(P::start($order, (int) $f['users'][0]['id'], '1.2.3.4') === $url && (int) D::one('SELECT COUNT(*) AS n FROM cinema_payments')['n'] === 1, 'Nhấn thanh toán lặp tạo nhiều yêu cầu.');
rejects(fn () => P::start($order, (int) $f['users'][1]['id'], '127.0.0.1'), 'Khách khác mở được cổng cho đơn.');
rejects(fn () => B::transition($order, 'pay', null, $f['admin']), 'Admin tự xác nhận đơn online.');
$checks += 2;
$good = callback($order);
$tampered = $good;
$tampered['vnp_Amount'] = '100';
payment_check(P::notify($tampered)['RspCode'] === '97' && B::order($order)['status'] === 'pending', 'Chữ ký giả làm đơn paid.');
payment_check(P::notify(callback($order, ['vnp_Amount' => '100']))['RspCode'] === '04', 'Số tiền sai được chấp nhận.');
payment_check(P::notify(callback($order, ['vnp_TmnCode' => 'OTHER123']))['RspCode'] === '97', 'Merchant khác được chấp nhận.');
payment_check(P::notify(callback($order, ['vnp_TxnRef' => str_repeat('F', 32)]))['RspCode'] === '01', 'TxnRef không có được chấp nhận.');
payment_check(P::notify(callback($order, ['vnp_PayDate' => '20260230000000']))['RspCode'] === '99', 'Ngày lỗi được chấp nhận.');
payment_check(P::verified([...$good, 'vnp_Amount' => ['11000000']]) === null, 'Tham số dạng mảng được chấp nhận.');
payment_check(P::returnOrder($good, (int) $f['users'][0]['id']) === $order && B::order($order)['status'] === 'pending', 'ReturnURL cấp vé trước IPN.');
payment_check(P::returnOrder($good, (int) $f['users'][1]['id']) === null, 'ReturnURL lộ đơn khác.');
payment_check(P::notify($good)['RspCode'] === '00' && B::order($order)['status'] === 'paid', 'IPN hợp lệ không xác nhận vé.');
payment_check(P::notify($good)['RspCode'] === '02' && (int) D::one('SELECT COUNT(*) AS n FROM cinema_payments')['n'] === 1, 'IPN lặp không idempotent.');
payment_check(array_sum(array_column(R::revenue('day', (int) date('Y'), null, 'real'), 'total')) === 0 && (int) array_sum(array_column(R::revenue('day', (int) date('Y'), null, 'sandbox'), 'total')) === 110000, 'Sandbox lẫn vào tiền thực.');

$retry = online_order($f, 1);
P::start($retry, (int) $f['users'][0]['id'], '127.0.0.1');
payment_check(P::notify(callback($retry, ['vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02', 'vnp_TransactionNo' => '0']))['RspCode'] === '00' && B::order($retry)['status'] === 'pending', 'Thanh toán thất bại vẫn cấp vé.');
$firstAttempt = callback($retry);
P::start($retry, (int) $f['users'][0]['id'], '127.0.0.1');
payment_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_payments WHERE order_id=?', [$retry])['n'] === 2, 'Không tạo lại giao dịch sau thất bại.');
payment_check(P::notify(callback($retry))['RspCode'] === '00' && B::order($retry)['status'] === 'paid', 'Thanh toán lại thất bại.');
payment_check(P::notify($firstAttempt)['RspCode'] === '00' && (int) D::one("SELECT COUNT(*) AS n FROM cinema_payments WHERE order_id=? AND status='review'", [$retry])['n'] === 1, 'Khoản tiền thứ hai bị bỏ qua.');

$late = online_order($f, 2);
P::start($late, (int) $f['users'][0]['id'], '127.0.0.1');
$lateCallback = callback($late);
D::execute('UPDATE cinema_orders SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?', [$late]);
$replacement = B::placeOrder($f['show'], $f['users'][1], [$f['seats'][2]], [], 'counter', bin2hex(random_bytes(32)));
payment_check(P::notify($lateCallback)['RspCode'] === '00' && B::order($late)['status'] === 'expired' && B::order($late)['payment']['status'] === 'review', 'Thanh toán muộn bán trùng ghế.');
payment_check((int) D::one('SELECT order_id FROM cinema_reservations WHERE showtime_id=? AND seat_id=?', [$f['show'], $f['seats'][2]])['order_id'] === $replacement, 'Callback cướp lại ghế.');
payment_check(P::notify($lateCallback)['RspCode'] === '02', 'Khoản cần đối soát bị ghi nhận lại.');
$cancelled = online_order($f, 3);
P::start($cancelled, (int) $f['users'][0]['id'], '127.0.0.1');
B::transition($cancelled, 'cancel', (int) $f['users'][0]['id']);
payment_check(P::notify(callback($cancelled))['RspCode'] === '00' && B::order($cancelled)['status'] === 'cancelled' && B::order($cancelled)['payment']['status'] === 'review', 'Đơn đã hủy được cấp vé khi IPN muộn.');

$GLOBALS['app_config']['payments']['vnpay']['environment'] = 'production';
$real = online_order($f, 4);
payment_check(str_starts_with(P::start($real, (int) $f['users'][0]['id'], '127.0.0.1'), 'https://pay.vnpay.vn/vpcpay.html?'), 'Endpoint production sai.');
P::notify(callback($real));
payment_check((int) array_sum(array_column(R::revenue('day', (int) date('Y'), null, 'real'), 'total')) === 110000, 'Doanh thu thực không cộng đúng một đơn VNPay.');
B::transition($replacement, 'pay', null, $f['admin']);
payment_check((int) array_sum(array_column(R::revenue('month', (int) date('Y'), $f['cinema'], 'real'), 'total')) === 220000, 'Doanh thu quầy và online cộng trùng hoặc thiếu.');
echo "$checks kiểm thử thanh toán, 0 thất bại.\n";
