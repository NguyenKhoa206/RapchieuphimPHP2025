<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Services\BookingService as B;
use App\Services\PaymentService as P;
use App\Support\Database as D;

if (!function_exists('proc_open')) {
    throw new RuntimeException('Cần proc_open để kiểm tra IPN đồng thời.');
}
foreach (['VNPAY_ENABLED' => '1', 'VNPAY_ENVIRONMENT' => 'sandbox', 'VNPAY_TMN_CODE' => 'TEST1234', 'VNPAY_HASH_SECRET' => 'test-only-vnpay-signing-secret', 'VNPAY_RETURN_URL' => 'https://cinema.example/payment/return.php'] as $key => $value) {
    putenv($key . '=' . $value);
}
$GLOBALS['app_config']['payments'] = (require APP_ROOT . '/config/app.php')['payments'];
function race(array $jobs): array
{
    $workers = [];
    $deadline = microtime(true) + 1;
    foreach ($jobs as $job) {
        $command = [PHP_BINARY];
        if (php_ini_loaded_file()) {
            array_push($command, '-c', php_ini_loaded_file());
        }
        $command[] = __DIR__ . '/payment-worker.php';
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        check(is_resource($process), 'Không khởi động được worker.');
        fwrite($pipes[0], json_encode([...$job, 'deadline' => $deadline], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process,$pipes]) {
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(proc_close($process) === 0, 'Worker thất bại: ' . $error);
        $results[] = json_decode($out, true, 32, JSON_THROW_ON_ERROR);
    }
    return $results;
}
function concurrent_callback(int $order): array
{
    $p = D::one('SELECT * FROM cinema_payments WHERE order_id=?', [$order]);
    $callback = ['vnp_TmnCode' => 'TEST1234', 'vnp_Amount' => (string) ((int) $p['amount'] * 100), 'vnp_BankCode' => 'NCB', 'vnp_TransactionNo' => (string) (900000 + $p['id']), 'vnp_TxnRef' => $p['txn_ref'], 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_PayDate' => date('YmdHis')];
    $callback['vnp_SecureHash'] = P::signature($callback, 'test-only-vnpay-signing-secret');
    return $callback;
}
$f = reset_fixture();
$order = B::placeOrder($f['show'], $f['users'][0], [$f['seats'][0]], [], 'vnpay', bin2hex(random_bytes(32)));
P::start($order, (int) $f['users'][0]['id'], '127.0.0.1');
$job = ['action' => 'notify', 'callback' => concurrent_callback($order)];
$responses = race([$job, $job]);
$codes = array_column($responses, 'RspCode');
sort($codes);
check($codes === ['00', '02'] && B::order($order)['status'] === 'paid', 'IPN đồng thời ghi nhận hai lần hoặc mất thanh toán.');
$late = B::placeOrder($f['show'], $f['users'][0], [$f['seats'][1]], [], 'vnpay', bin2hex(random_bytes(32)));
P::start($late, (int) $f['users'][0]['id'], '127.0.0.1');
$callback = concurrent_callback($late);
D::execute('UPDATE cinema_orders SET expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?', [$late]);
$responses = race([['action' => 'notify', 'callback' => $callback], ['action' => 'book', 'show' => $f['show'], 'user' => (int) $f['users'][1]['id'], 'seat' => (int) $f['seats'][1]]]);
check($responses[0]['RspCode'] === '00' && isset($responses[1]['order']), 'Callback/đặt lại đồng thời thất bại.');
check(B::order($late)['status'] === 'expired' && B::order($late)['payment']['status'] === 'review', 'Callback muộn cấp vé trùng khi race.');
check((int) D::one('SELECT order_id FROM cinema_reservations WHERE showtime_id=? AND seat_id=?', [$f['show'], $f['seats'][1]])['order_id'] === $responses[1]['order'], 'Ghế không thuộc đơn mới.');
echo "PASS IPN đồng thời và IPN muộn chạy cùng đặt lại: không ghi nhận lặp hoặc bán trùng ghế.\n";
