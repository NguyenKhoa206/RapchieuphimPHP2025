<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;

final class BookingService
{
    public static function showtime(int $id): ?array
    {
        return D::one('SELECT s.*,m.title,m.poster,m.age_rating,c.name AS cinema_name,c.address,r.name AS room_name,r.room_type FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE s.id=? AND s.active=1 AND m.active=1 AND r.active=1 AND c.active=1 AND s.starts_at>NOW()', [$id]);
    }

    public static function seats(array $showtime): array
    {
        return D::all("SELECT s.*, CONCAT(s.row_label,s.seat_number) AS label, CASE WHEN o.status='paid' OR (o.status='pending' AND o.expires_at>NOW()) THEN 1 ELSE 0 END AS reserved FROM cinema_seats s LEFT JOIN cinema_reservations r ON r.seat_id=s.id AND r.showtime_id=? LEFT JOIN cinema_orders o ON o.id=r.order_id WHERE s.room_id=? ORDER BY s.row_label,s.seat_number", [$showtime['id'], $showtime['room_id']]);
    }

    private static function releaseExpired(int $showtime): void
    {
        D::execute("DELETE r FROM cinema_reservations r JOIN cinema_orders o ON o.id=r.order_id WHERE o.showtime_id=? AND o.status='pending' AND o.expires_at<=NOW()", [$showtime]);
        D::execute("UPDATE cinema_orders SET status='expired' WHERE showtime_id=? AND status='pending' AND expires_at<=NOW()", [$showtime]);
    }

    public static function placeOrder(int $showtimeId, array $user, array $seatIds, array $combos, string $method, string $nonce): int
    {
        if (!in_array($method, ['counter', 'demo', 'vnpay'], true) || ($method === 'demo' && !config('allow_demo_payment')) || ($method === 'vnpay' && !PaymentService::available())) {
            throw new \DomainException('Phương thức thanh toán không hợp lệ.');
        }
        if (count($seatIds) < 1 || count($seatIds) > 8 || !preg_match('/^[a-f0-9]{64}$/', $nonce)) {
            throw new \DomainException('Chọn từ 1 đến 8 ghế.');
        }
        foreach ($seatIds as $value) {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) {
                throw new \DomainException('Ghế không hợp lệ.');
            }
        }
        $seatIds = array_map('intval', $seatIds);
        sort($seatIds);
        if (count(array_unique($seatIds)) !== count($seatIds)) {
            throw new \DomainException('Danh sách ghế bị trùng.');
        }
        $db = db();
        $db->beginTransaction();
        try {
            // Lock one showtime, so bookings for other rooms/cinemas can proceed independently.
            D::one('SELECT id FROM cinema_showtimes WHERE id=? FOR UPDATE', [$showtimeId]);
            $show = self::showtime($showtimeId);
            if (!$show || strtotime($show['starts_at']) <= time()) {
                throw new \DomainException('Suất chiếu không còn mở bán.');
            }
            $key = hash('sha256', $user['id'] . '|' . $showtimeId . '|' . $nonce);
            if ($existing = D::one('SELECT id FROM cinema_orders WHERE request_key=? AND user_id=?', [$key, $user['id']])) {
                $db->commit();
                return (int)$existing['id'];
            }
            if ($show['age_rating'] > 0) {
                if (!$user['birthday'] || (new \DateTimeImmutable($user['birthday']))->diff(new \DateTimeImmutable($show['starts_at']))->y < $show['age_rating']) {
                    throw new \DomainException('Vui lòng cập nhật ngày sinh. Phim yêu cầu từ ' . $show['age_rating'] . ' tuổi.');
                }
            }
            self::releaseExpired($showtimeId);
            $slots = implode(',', array_fill(0, count($seatIds), '?'));
            $seats = D::all("SELECT *,CONCAT(row_label,seat_number) AS label FROM cinema_seats WHERE room_id=? AND id IN ($slots)", [$show['room_id'], ...$seatIds]);
            if (count($seats) !== count($seatIds)) {
                throw new \DomainException('Ghế không thuộc phòng chiếu này.');
            }
            if (D::one("SELECT seat_id FROM cinema_reservations WHERE showtime_id=? AND seat_id IN ($slots) LIMIT 1", [$showtimeId, ...$seatIds])) {
                throw new \DomainException('Một ghế vừa được người khác đặt. Vui lòng chọn lại.');
            }
            $items = [];
            $total = 0;
            foreach ($seats as $seat) {
                $price = (int)$show['base_price'] + (int)$seat['price_extra'];
                $total += $price;
                $items[] = ['seat', $seat['label'], 1, $price];
            }
            if (count($combos) > 30) {
                throw new \DomainException('Danh sách combo không hợp lệ.');
            }
            foreach ($combos as $id => $quantity) {
                if (!ctype_digit((string)$id) || !is_scalar($quantity) || !ctype_digit((string)$quantity) || (int)$quantity > 10) {
                    throw new \DomainException('Mỗi combo được chọn tối đa 10 phần.');
                }
                if ((int)$quantity === 0) {
                    continue;
                }
                $combo = D::one('SELECT * FROM cinema_combos WHERE id=? AND active=1', [(int)$id]);
                if (!$combo) {
                    throw new \DomainException('Combo không còn bán.');
                }
                $total += (int)$quantity * (int)$combo['price'];
                $items[] = ['combo', $combo['name'], (int)$quantity, (int)$combo['price']];
            }
            $expires = date('Y-m-d H:i:s', min(time() + (int)config('booking_minutes') * 60, strtotime($show['starts_at'])));
            D::execute('INSERT INTO cinema_orders (reference,request_key,user_id,showtime_id,total,payment_method,expires_at) VALUES (?,?,?,?,?,?,?)', [strtoupper(bin2hex(random_bytes(8))), $key, $user['id'], $showtimeId, $total, $method, $expires]);
            $orderId = (int)$db->lastInsertId();
            foreach ($items as $item) {
                D::execute('INSERT INTO cinema_order_items (order_id,kind,item_name,quantity,unit_price) VALUES (?,?,?,?,?)', [$orderId, ...$item]);
            }
            foreach ($seatIds as $seatId) {
                D::execute('INSERT INTO cinema_reservations (showtime_id,seat_id,order_id) VALUES (?,?,?)', [$showtimeId, $seatId, $orderId]);
            }
            $db->commit();
            return $orderId;
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function order(int $id, ?int $userId = null): ?array
    {
        $sql = 'SELECT o.*,u.name AS customer_name,u.email,u.phone,m.title,m.poster,s.starts_at,s.language,c.name AS cinema_name,c.address,r.name AS room_name FROM cinema_orders o JOIN cinema_users u ON u.id=o.user_id JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE o.id=?';
        $params = [$id];
        if ($userId !== null) {
            $sql .= ' AND o.user_id=?';
            $params[] = $userId;
        }
        $order = D::one($sql, $params);
        if ($order) {
            if ($order['status'] === 'pending' && strtotime($order['expires_at']) <= time()) {
                $order['status'] = 'expired';
            }
            $order['items'] = D::all('SELECT * FROM cinema_order_items WHERE order_id=? ORDER BY kind DESC,id', [$id]);
            $order['payment'] = D::one('SELECT id,environment,txn_ref,status,bank_code,transaction_no,response_code,paid_at,review_reason FROM cinema_payments WHERE order_id=? ORDER BY id DESC LIMIT 1', [$id]);
        }
        return $order;
    }

    public static function transition(int $orderId, string $action, ?int $userId = null, ?int $adminId = null): void
    {
        if (!in_array($action, ['pay', 'cancel'], true) || ($userId === null && $adminId === null)) {
            throw new \DomainException('Thao tác không hợp lệ.');
        }
        $hint = D::one('SELECT showtime_id FROM cinema_orders WHERE id=?', [$orderId]);
        if (!$hint) {
            throw new \DomainException('Không tìm thấy đơn vé.');
        }
        $db = db();
        $db->beginTransaction();
        try {
            D::one('SELECT id FROM cinema_showtimes WHERE id=? FOR UPDATE', [$hint['showtime_id']]);
            $order = D::one('SELECT * FROM cinema_orders WHERE id=? FOR UPDATE', [$orderId]);
            if ($userId !== null && (int)$order['user_id'] !== $userId) {
                throw new \DomainException('Bạn không có quyền xử lý đơn này.');
            }
            self::releaseExpired((int)$order['showtime_id']);
            if ($order['status'] !== 'pending' || strtotime($order['expires_at']) <= time()) {
                throw new \DomainException('Đơn đã được xử lý hoặc hết hạn giữ ghế.');
            }
            if ($action === 'pay') {
                if ($order['payment_method'] === 'vnpay') {
                    throw new \DomainException('VNPay chỉ được xác nhận qua thông báo của cổng thanh toán.');
                }
                if ($adminId === null && ($order['payment_method'] !== 'demo' || !config('allow_demo_payment'))) {
                    throw new \DomainException('Chỉ nhân viên mới có thể xác nhận thanh toán tại quầy.');
                }
                D::execute("UPDATE cinema_orders SET status='paid',paid_at=NOW(),paid_by=? WHERE id=?", [$adminId, $orderId]);
            } else {
                D::execute('DELETE FROM cinema_reservations WHERE order_id=?', [$orderId]);
                D::execute("UPDATE cinema_orders SET status='cancelled' WHERE id=?", [$orderId]);
            }
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
