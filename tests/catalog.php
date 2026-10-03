<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Services\CinemaService as C;
use App\Support\Database as D;

$f = reset_fixture();
$checks = 0;
function catalog_check(bool $condition, string $message): void
{
    check($condition, $message);
    $GLOBALS['checks']++;
}
function catalog_movie(string $name, string $start, string $end, string $tags): int
{
    D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description) VALUES (?,?,?,?,?,?,?)', [$name, 'Hành động', 0, 100, $start, $end, 'Catalog test']);
    $id = (int) db()->lastInsertId();
    $film = D::one('SELECT * FROM cinema_movies WHERE id=?', [$id]);
    C::saveMovie($id, [...$film, 'tags' => $tags]);
    return $id;
}
$film = D::one('SELECT * FROM cinema_movies WHERE id=?', [$f['movie']]);
C::saveMovie($f['movie'], [...$film, 'tags' => ' Hành động , Anime, HÀNH ĐỘNG ', 'is_hot' => '1', 'is_featured' => '1']);
$display = C::movie($f['movie']);
catalog_check($display['tags'] === ['Anime', 'Hành động'] && (int) $display['is_hot'] === 1 && (int) $display['is_featured'] === 1, 'Không lưu/chuẩn hóa tag và tùy chọn hiển thị.');
catalog_check(C::monthlyTop() === [], 'Tạo bảng xếp hạng giả khi chưa có vé.');
$past = catalog_movie('Phim đã chiếu', date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('-1 day')), 'Hành động, Phim Việt');
$upcoming = catalog_movie('Phim sắp chiếu', date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+60 days')), 'Hành động');
$inactive = catalog_movie('Phim ẩn', date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'Tag riêng');
D::execute('UPDATE cinema_movies SET active=0 WHERE id=?', [$inactive]);
catalog_check(C::movies('', 'showing')['total'] === 1 && C::movies('', 'upcoming')['total'] === 1 && C::movies('', 'past')['total'] === 1, 'Phân loại sai ở ranh giới ngày chiếu.');
catalog_check(C::movies('', 'all', 1, 'Hành động')['total'] === 3 && C::movies('', 'past', 1, 'Phim Việt')['total'] === 1, 'Không kết hợp tag với trạng thái.');
catalog_check(C::movies('sắp', 'all', 1, 'Hành động')['total'] === 1 && C::movies('sắp', 'past', 1, 'Hành động')['total'] === 0, 'Tìm kiếm/tag/trạng thái không kết hợp bằng AND.');
catalog_check(C::movies('', 'all', 1, "' OR 1=1 --")['total'] === 0, 'Tag cho phép SQL injection.');
catalog_check(!in_array('Tag riêng', C::tagOptions(), true) && C::movie($inactive) === null, 'Lộ phim hoặc tag của phim ẩn.');
catalog_check(count(C::hot()) === 1 && C::featured()[0]['id'] === $f['movie'], 'Phim hot và banner không theo tùy chọn admin.');
foreach ([str_repeat('x', 33), 'a,b,c,d,e,f,g,h,i', '<script>', 'Anime/../../', str_repeat('x', 301)] as $bad) {
    rejects(static fn () => C::saveMovie($f['movie'], [...$film, 'tags' => $bad]), 'Lưu tag sai định dạng.');
    $checks++;
}
catalog_check(C::movie($f['movie'])['tags'] === ['Anime', 'Hành động'], 'Lưu tag lỗi làm thay đổi dữ liệu cũ.');
C::saveMovie($f['movie'], $film);
catalog_check(C::movie($f['movie'])['tags'] === ['Anime', 'Hành động'] && (int) C::movie($f['movie'])['is_hot'] === 1, 'Luồng sửa cũ ghi đè các tùy chọn mới.');
C::saveMovie($f['movie'], [...$film, 'tags' => '', 'is_hot' => '0', 'is_featured' => '0']);
catalog_check(C::movie($f['movie'])['tags'] === [] && C::hot() === [], 'Không bỏ được tag hoặc tùy chọn hot.');
for ($n = 0; $n < 13; $n++) {
    catalog_movie('Phân trang ' . $n, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'Phân trang');
}
catalog_check(count(C::movies('', 'all', 1, 'Phân trang')['items']) === 12 && count(C::movies('', 'all', 2, 'Phân trang')['items']) === 1 && C::movies('', 'all', 999, 'Phân trang')['page'] === 2, 'Phân trang mất bộ lọc tag.');

function catalog_order(int $movie, string $paid, string $method, string $status, int $quantity, ?string $environment = null): int
{
    $f = $GLOBALS['f'];
    D::execute('INSERT INTO cinema_showtimes (movie_id,room_id,starts_at,ends_at,base_price,active) VALUES (?,?,?,?,?,0)', [$movie, $f['room'], date('Y-m-d H:i:s', strtotime('-2 hours')), date('Y-m-d H:i:s', strtotime('-1 hour')), 90000]);
    $show = (int) db()->lastInsertId();
    D::execute('INSERT INTO cinema_orders (reference,request_key,user_id,showtime_id,total,status,payment_method,expires_at,paid_at) VALUES (?,?,?,?,?,?,?,?,?)', [strtoupper(bin2hex(random_bytes(8))), bin2hex(random_bytes(32)), $f['users'][0]['id'], $show, 90000 * $quantity, $status, $method, date('Y-m-d H:i:s', strtotime('+1 hour')), $paid]);
    $order = (int) db()->lastInsertId();
    D::execute("INSERT INTO cinema_order_items (order_id,kind,item_name,quantity,unit_price) VALUES (?,'seat','A1',?,90000)", [$order, $quantity]);
    D::execute("INSERT INTO cinema_order_items (order_id,kind,item_name,quantity,unit_price) VALUES (?,'combo','Bắp nước',100,1000)", [$order]);
    if ($environment) {
        for ($n = 0; $n < 2; $n++) {
            D::execute("INSERT INTO cinema_payments (order_id,txn_ref,environment,tmn_code,amount,status,request_params,expires_at) VALUES (?,?,?,?,?,'paid','{}',?)", [$order, strtoupper(bin2hex(random_bytes(16))), $environment, 'TEST1234', $quantity * 90000, date('Y-m-d H:i:s', strtotime('+1 hour'))]);
        }
    }
    return $order;
}
$now = date('Y-m-d H:i:s', max(strtotime(date('Y-m-01') . ' 00:00:00'), time() - 60));
catalog_order($f['movie'], $now, 'counter', 'paid', 2);
catalog_order($f['movie'], $now, 'counter', 'paid', 3);
catalog_order($past, $now, 'vnpay', 'paid', 7, 'production');
catalog_order($f['movie'], $now, 'demo', 'paid', 30);
catalog_order($f['movie'], $now, 'vnpay', 'paid', 30, 'sandbox');
catalog_order($f['movie'], $now, 'vnpay', 'paid', 30);
foreach (['pending', 'cancelled', 'expired'] as $status) {
    catalog_order($f['movie'], $now, 'counter', $status, 30);
}
catalog_order($f['movie'], date('Y-m-d H:i:s', strtotime(date('Y-m-01') . ' -1 second')), 'counter', 'paid', 30);
catalog_order($f['movie'], date('Y-m-d H:i:s', strtotime('first day of next month 00:00:00')), 'counter', 'paid', 30);
catalog_order($f['movie'], date('Y-m-d H:i:s', strtotime('+1 hour')), 'counter', 'paid', 30);
catalog_order($inactive, $now, 'counter', 'paid', 100);
$ranked = C::monthlyTop();
catalog_check(count($ranked) === 2 && $ranked[0]['id'] === $past && (int) $ranked[0]['tickets'] === 7 && $ranked[1]['id'] === $f['movie'] && (int) $ranked[1]['tickets'] === 5, 'Top tháng tính sai nguồn tiền, trạng thái, ngày, combo hoặc nhân theo payment attempts.');
// Month start belongs to this month; tests do not place a paid_at in the future.
catalog_order($f['movie'], date('Y-m-01') . ' 00:00:00', 'counter', 'paid', 3);
$ranked = C::monthlyTop();
catalog_check((int) $ranked[0]['tickets'] === 8 && $ranked[0]['id'] === $f['movie'], 'Mốc đầu tháng bị loại khỏi xếp hạng.');
foreach (D::all("SELECT id FROM cinema_movies WHERE title LIKE 'Phân trang %' ORDER BY id LIMIT 4") as $row) {
    catalog_order((int) $row['id'], $now, 'counter', 'paid', 1);
}
$ranked = C::monthlyTop();
catalog_check(count($ranked) === 4 && (int) $ranked[2]['tickets'] === 1 && (int) $ranked[2]['id'] < (int) $ranked[3]['id'], 'Không giới hạn top hoặc thứ tự đồng hạng không ổn định.');
echo "PASS CATALOG: $checks kiểm tra tag, kết hợp bộ lọc, phân trang, banner/hot và top tháng không tính demo/sandbox hay nhân vé.\n";
