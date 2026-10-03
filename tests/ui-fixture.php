<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Support\Auth;
use App\Support\Database as D;

// Run only after seed.php in a dedicated cinema_ui_test database.
if (D::one("SELECT id FROM cinema_users WHERE email='ui@example.test'")) {
    throw new RuntimeException('UI fixture cần CSDL mới.');
}
Auth::register(['name' => 'Người xem thử', 'email' => 'ui@example.test', 'phone' => '0900999001', 'gender' => 2, 'birthday' => '1990-01-01', 'password' => 'UiPassword123!', 'confirmation' => 'UiPassword123!']);
$user = (int) D::one("SELECT id FROM cinema_users WHERE email='ui@example.test'")['id'];
D::execute('INSERT INTO cinema_admins (username,name,password_hash) VALUES (?,?,?)', ['uiadmin', 'Quản trị thử', password_hash('UiPassword123!', PASSWORD_DEFAULT)]);
D::execute('INSERT INTO cinema_history_batches (source_name,mapping_hash,invoice_count,ticket_count) VALUES (?,?,1,1)', ['ui-fixture', hash('sha256', 'ui')]);
$batch = (int) db()->lastInsertId();
D::execute('INSERT INTO cinema_legacy_invoices (source_name,source_key,source_hash,batch_id,user_id,issued_at,amount,original_status,raw_data) VALUES (?,?,?,?,?,?,?,?,?)', ['ui-fixture', 'HD2025-001', hash('sha256', 'ui-invoice'), $batch, $user, '2025-10-01 18:00:00', 180000, 'Trạng thái từ hệ thống cũ', '{}']);
$invoice = (int) db()->lastInsertId();
D::execute('INSERT INTO cinema_legacy_tickets (source_name,source_key,source_hash,batch_id,user_id,invoice_id,movie_title,cinema_name,room_name,seat_label,starts_at,amount,raw_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', ['ui-fixture', 'V2025-001', hash('sha256', 'ui-ticket'), $batch, $user, $invoice, 'Bộ phim từ năm trước', 'Galaxy Nguyễn Du', 'Phòng 1', 'A1', '2025-10-01 19:00:00', 90000, '{}']);
echo json_encode(['invoice' => $invoice], JSON_THROW_ON_ERROR) . PHP_EOL;
// Only this private UI fixture populates a ranking; normal seeds create no sales.
Auth::register(['name' => 'Khách xếp hạng thử', 'email' => 'ranking-ui@example.test', 'phone' => '0900999002', 'gender' => 2, 'birthday' => '1990-01-01', 'password' => 'UiPassword123!', 'confirmation' => 'UiPassword123!']);
$rankingUser = (int) D::one("SELECT id FROM cinema_users WHERE email='ranking-ui@example.test'")['id'];
foreach ([['jujutsu-kaisen-500-1759216418459.jpg', 8], ['teeyod-3-500-1759482319151.jpg', 4]] as [$poster, $tickets]) {
    $show = D::one('SELECT s.id FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id WHERE m.poster=? ORDER BY s.id LIMIT 1', [$poster]);
    D::execute("INSERT INTO cinema_orders (reference,request_key,user_id,showtime_id,total,status,payment_method,expires_at,paid_at) VALUES (?,?,?,?,?,'paid','counter',?,NOW())", [strtoupper(bin2hex(random_bytes(8))), bin2hex(random_bytes(32)), $rankingUser, $show['id'], 90000 * $tickets, date('Y-m-d H:i:s', strtotime('+1 hour'))]);
    $order = (int) db()->lastInsertId();
    for ($seat = 1; $seat <= $tickets; $seat++) {
        D::execute("INSERT INTO cinema_order_items (order_id,kind,item_name,quantity,unit_price) VALUES (?,'seat',?,1,90000)", [$order, 'A' . $seat]);
    }
}
