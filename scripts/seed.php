<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib/demo.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

if (D::one('SELECT id FROM cinema_movies LIMIT 1') || D::one('SELECT id FROM cinema_cinemas LIMIT 1')) {
    throw new RuntimeException('Chỉ tạo dữ liệu mẫu khi danh mục phim và rạp đang trống.');
}
$films = (require APP_ROOT . '/database/demo-content.php')['movies'];
$db = db();
$db->beginTransaction();
try {
    foreach ($films as $film) {
        [$title,$genre,$age,$duration,$poster,$days] = $film;
        D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description,poster,trailer) VALUES (?,?,?,?,?,?,?,?,?)', [$title, $genre, $age, $duration, date('Y-m-d', strtotime($days . ' days')), date('Y-m-d', strtotime(($days + 60) . ' days')), 'Dữ liệu minh họa cho đồ án; lịch phát hành, giới hạn tuổi và thời lượng trong bản mẫu không phải lịch chiếu thực tế.', $poster, $film['trailer'] ?? null]);
        seed_movie_display((int) $db->lastInsertId(), $film);
    }
    foreach ([['Galaxy Quang Trung', 'Quang Trung, TP. Hồ Chí Minh'], ['Galaxy Nguyễn Du', 'Nguyễn Du, TP. Hồ Chí Minh']] as [$name,$address]) {
        D::execute('INSERT INTO cinema_cinemas (name,address,phone) VALUES (?,?,?)', [$name, $address, '0900000000']);
        $cinema = (int)$db->lastInsertId();
        D::execute('INSERT INTO cinema_rooms (cinema_id,name) VALUES (?,?)', [$cinema, 'Phòng 01']);
        C::generateSeats((int)$db->lastInsertId(), 9, 16, 144);
    }
    foreach ([['Combo 1 · Bắp lớn & 2 nước', 75000], ['Combo 2 · Bắp nhỏ & 1 nước', 50000], ['Nước ngọt lớn', 25000]] as $combo) {
        D::execute('INSERT INTO cinema_combos (name,price) VALUES (?,?)', $combo);
    }
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
$movies = D::all('SELECT id FROM cinema_movies ORDER BY id');
$rooms = D::all('SELECT id FROM cinema_rooms ORDER BY id');
for ($day = 1;$day <= 3;$day++) {
    foreach ($rooms as $index => $room) {
        foreach (['10:00', '15:00', '20:00'] as $time) {
            C::saveShowtime(null, (int)$movies[$index % count($movies)]['id'], (int)$room['id'], date('Y-m-d', strtotime('+' . $day . ' days')) . 'T' . $time, 90000, 'Phụ đề tiếng Việt');
        }
    }
}
echo "Đã tạo phim, rạp, ghế, combo và lịch chiếu mẫu trong 3 ngày tới.\n";
