<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;

final class PaymentService
{
    private const ENDPOINTS = [
        'sandbox' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'production' => 'https://pay.vnpay.vn/vpcpay.html',
    ];

    public static function available(): bool
    {
        $c = config('payments.vnpay', []);
        $url = parse_url((string) ($c['return_url'] ?? ''));
        return ($c['enabled'] ?? false) === true
            && isset(self::ENDPOINTS[$c['environment'] ?? ''])
            && preg_match('/^[a-zA-Z0-9]{8}$/', (string) ($c['tmn_code'] ?? '')) === 1
            && strlen((string) ($c['hash_secret'] ?? '')) >= 16
            && strlen((string) ($c['return_url'] ?? '')) <= 255
            && ($url['scheme'] ?? '') === 'https' && !empty($url['host'])
            && !isset($url['user']) && !isset($url['pass']) && !isset($url['fragment']);
    }

    public static function sandbox(): bool
    {
        return config('payments.vnpay.environment') === 'sandbox';
    }

    // VNPay 2.1.0 signs sorted, RFC1738 encoded fields (spaces become +).
    public static function signature(array $params, string $secret): string
    {
        unset($params['vnp_SecureHash'], $params['vnp_SecureHashType']);
        ksort($params, SORT_STRING);
        return hash_hmac('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC1738), $secret);
    }

    public static function verified(array $input): ?array
    {
        $secret = (string) config('payments.vnpay.hash_secret', '');
        if (strlen($secret) < 16 || !is_string($input['vnp_SecureHash'] ?? null)
            || !preg_match('/^[a-f0-9]{128}$/i', $input['vnp_SecureHash'])) {
            return null;
        }
        $params = [];
        foreach ($input as $key => $value) {
            if (!str_starts_with((string) $key, 'vnp_') || in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true)) {
                continue;
            }
            if (!is_string($value) || strlen($value) > 1024) {
                return null;
            }
            $params[$key] = $value;
        }
        if (!hash_equals(self::signature($params, $secret), strtolower($input['vnp_SecureHash']))) {
            return null;
        }
        if (($params['vnp_TmnCode'] ?? '') !== config('payments.vnpay.tmn_code')) {
            return null;
        }
        return $params;
    }

    public static function start(int $orderId, int $userId, string $ip): string
    {
        if (!self::available()) {
            throw new \DomainException('Thanh toán online chưa được cấu hình. Vui lòng liên hệ rạp.');
        }
        $hint = D::one('SELECT showtime_id FROM cinema_orders WHERE id=? AND user_id=?', [$orderId, $userId]);
        if (!$hint) {
            throw new \DomainException('Không tìm thấy đơn vé của bạn.');
        }
        $db = db();
        $db->beginTransaction();
        try {
            D::one('SELECT id FROM cinema_showtimes WHERE id=? FOR UPDATE', [$hint['showtime_id']]);
            $order = D::one('SELECT * FROM cinema_orders WHERE id=? FOR UPDATE', [$orderId]);
            if ($order['status'] !== 'pending' || $order['payment_method'] !== 'vnpay' || strtotime($order['expires_at']) <= time() + 30) {
                throw new \DomainException('Đơn không thể thanh toán online hoặc thời hạn giữ ghế còn dưới 30 giây.');
            }
            $attempt = D::one('SELECT * FROM cinema_payments WHERE order_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$orderId]);
            if ($attempt && in_array($attempt['status'], ['paid', 'review'], true)) {
                throw new \DomainException('Giao dịch đã được ghi nhận. Vui lòng kiểm tra trạng thái đơn.');
            }
            if ($attempt && $attempt['status'] === 'pending') {
                if ($attempt['environment'] !== config('payments.vnpay.environment') || $attempt['tmn_code'] !== config('payments.vnpay.tmn_code')) {
                    throw new \DomainException('Cấu hình cổng đã thay đổi. Liên hệ rạp để kiểm tra giao dịch đang chờ.');
                }
                $params = json_decode($attempt['request_params'], true, 32, JSON_THROW_ON_ERROR);
            } else {
                $ref = strtoupper(bin2hex(random_bytes(16)));
                $params = [
                    'vnp_Version' => '2.1.0', 'vnp_Command' => 'pay',
                    'vnp_TmnCode' => (string) config('payments.vnpay.tmn_code'),
                    'vnp_Amount' => (string) ((int) $order['total'] * 100),
                    'vnp_CreateDate' => date('YmdHis'),
                    'vnp_ExpireDate' => date('YmdHis', strtotime($order['expires_at'])),
                    'vnp_CurrCode' => 'VND',
                    'vnp_IpAddr' => filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1',
                    'vnp_Locale' => 'vn',
                    'vnp_OrderInfo' => 'Thanh toan ve ' . $order['reference'],
                    'vnp_OrderType' => 'other',
                    'vnp_ReturnUrl' => (string) config('payments.vnpay.return_url'),
                    'vnp_TxnRef' => $ref,
                ];
                D::execute('INSERT INTO cinema_payments (order_id,txn_ref,environment,tmn_code,amount,request_params,expires_at) VALUES (?,?,?,?,?,?,?)', [
                    $orderId, $ref, config('payments.vnpay.environment'), config('payments.vnpay.tmn_code'),
                    $order['total'], json_encode($params, JSON_THROW_ON_ERROR), $order['expires_at'],
                ]);
            }
            $params['vnp_SecureHash'] = self::signature($params, (string) config('payments.vnpay.hash_secret'));
            ksort($params);
            $url = self::ENDPOINTS[(string) config('payments.vnpay.environment')] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC1738);
            $db->commit();
            return $url;
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    /** IPN is the only route that confirms online payments. ReturnURL stays read-only. */
    public static function notify(array $input): array
    {
        $params = self::verified($input);
        if (!$params) {
            return ['RspCode' => '97', 'Message' => 'Invalid signature'];
        }
        if (!preg_match('/^[A-F0-9]{32}$/', $params['vnp_TxnRef'] ?? '')
            || !preg_match('/^[0-9]{1,12}$/', $params['vnp_Amount'] ?? '')
            || !preg_match('/^[0-9]{2}$/', $params['vnp_ResponseCode'] ?? '')
            || !preg_match('/^[0-9]{2}$/', $params['vnp_TransactionStatus'] ?? '')
            || !preg_match('/^[0-9]{1,15}$/', $params['vnp_TransactionNo'] ?? '')
            || !preg_match('/^[a-zA-Z0-9]{3,20}$/', $params['vnp_BankCode'] ?? '')
            || (isset($params['vnp_CurrCode']) && $params['vnp_CurrCode'] !== 'VND')) {
            return ['RspCode' => '99', 'Message' => 'Invalid data'];
        }
        $success = $params['vnp_ResponseCode'] === '00' && $params['vnp_TransactionStatus'] === '00';
        $paidDate = \DateTimeImmutable::createFromFormat('!YmdHis', $params['vnp_PayDate'] ?? '');
        if ($success && (!$paidDate || $paidDate->format('YmdHis') !== ($params['vnp_PayDate'] ?? '') || (int) $params['vnp_TransactionNo'] === 0)) {
            return ['RspCode' => '99', 'Message' => 'Invalid payment date or transaction'];
        }
        $hint = D::one('SELECT p.id,p.order_id,o.showtime_id FROM cinema_payments p JOIN cinema_orders o ON o.id=p.order_id WHERE p.txn_ref=?', [$params['vnp_TxnRef']]);
        if (!$hint) {
            return ['RspCode' => '01', 'Message' => 'Order not found'];
        }
        $db = db();
        $db->beginTransaction();
        try {
            // Same lock order as booking/cancel: no callback can sell an expired seat twice.
            $show = D::one('SELECT * FROM cinema_showtimes WHERE id=? FOR UPDATE', [$hint['showtime_id']]);
            $order = D::one('SELECT * FROM cinema_orders WHERE id=? FOR UPDATE', [$hint['order_id']]);
            $attempt = D::one('SELECT * FROM cinema_payments WHERE id=? FOR UPDATE', [$hint['id']]);
            if ($attempt['tmn_code'] !== $params['vnp_TmnCode'] || $attempt['environment'] !== config('payments.vnpay.environment')) {
                $result = ['RspCode' => '97', 'Message' => 'Merchant mismatch'];
            } elseif ((int) $params['vnp_Amount'] !== (int) $attempt['amount'] * 100 || (int) $attempt['amount'] !== (int) $order['total']) {
                $result = ['RspCode' => '04', 'Message' => 'Invalid amount'];
            } elseif (in_array($attempt['status'], ['paid', 'review'], true) || ($attempt['status'] === 'failed' && !$success)) {
                $result = ['RspCode' => '02', 'Message' => 'Order already confirmed'];
            } else {
                $state = $success ? 'paid' : 'failed';
                $reason = null;
                if ($success) {
                    $reservationCount = (int) D::one('SELECT COUNT(*) AS n FROM cinema_reservations WHERE order_id=?', [$order['id']])['n'];
                    $seatCount = (int) D::one("SELECT COALESCE(SUM(quantity),0) AS n FROM cinema_order_items WHERE order_id=? AND kind='seat'", [$order['id']])['n'];
                    if ($attempt['status'] !== 'pending' || $order['status'] !== 'pending'
                        || strtotime($order['expires_at']) <= time() || strtotime($show['starts_at']) <= time()
                        || $seatCount < 1 || $reservationCount !== $seatCount) {
                        $state = 'review';
                        $reason = 'Tiền đã ghi nhận sau khi đơn hết hạn, đã xử lý hoặc ghế không còn được giữ. Cần đối soát/hoàn tiền.';
                    } else {
                        D::execute("UPDATE cinema_orders SET status='paid',paid_at=?,paid_by=NULL WHERE id=?", [$paidDate->format('Y-m-d H:i:s'), $order['id']]);
                    }
                }
                D::execute('UPDATE cinema_payments SET status=?,response_payload=?,response_code=?,bank_code=?,transaction_no=?,review_reason=?,confirmed_at=NOW(),paid_at=? WHERE id=?', [
                    $state, json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $params['vnp_ResponseCode'],
                    $params['vnp_BankCode'], $success ? $params['vnp_TransactionNo'] : null, $reason,
                    $success ? $paidDate->format('Y-m-d H:i:s') : null, $attempt['id'],
                ]);
                $result = ['RspCode' => '00', 'Message' => 'Confirm success'];
            }
            $db->commit();
            return $result;
        } catch (\Throwable $error) {
            $db->rollBack();
            // Never log callback query strings: they may include banking identifiers.
            error_log('VNPay IPN failed for payment ' . $hint['id'] . ': ' . get_class($error));
            return ['RspCode' => '99', 'Message' => 'Temporary processing error'];
        }
    }

    public static function returnOrder(array $input, int $userId): ?int
    {
        $params = self::verified($input);
        if (!$params) {
            return null;
        }
        $row = D::one('SELECT o.id,p.amount FROM cinema_payments p JOIN cinema_orders o ON o.id=p.order_id WHERE p.txn_ref=? AND o.user_id=? AND p.tmn_code=? AND p.environment=?', [$params['vnp_TxnRef'] ?? '', $userId, config('payments.vnpay.tmn_code'), config('payments.vnpay.environment')]);
        if (!$row || ($params['vnp_Amount'] ?? '') !== (string) ((int) $row['amount'] * 100)) {
            return null;
        }
        return (int) $row['id'];
    }
}
