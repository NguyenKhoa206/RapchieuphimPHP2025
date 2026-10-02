<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Support\Database as D;
use App\Support\Auth;
use App\Services\CinemaService as C;

if (PHP_SAPI !== 'cli' || !str_ends_with((string) config('database.name'), '_test')) {
    throw new RuntimeException('Kiểm thử chỉ chạy từ CLI với CSDL riêng có tên kết thúc bằng _test.');
}

function clear_fixture(): void
{
    $tables = ['cinema_legacy_tickets', 'cinema_legacy_invoices', 'cinema_history_batches', 'cinema_payments', 'cinema_reservations', 'cinema_order_items', 'cinema_orders', 'cinema_showtimes', 'cinema_seats', 'cinema_rooms', 'cinema_cinemas', 'cinema_movies', 'cinema_combos', 'cinema_login_attempts', 'cinema_users', 'cinema_admins', 'cinema_meta'];
    foreach ($tables as $table) {
        D::execute('DELETE FROM ' . $table);
    }
}

function reset_fixture(): array
{
    clear_fixture();
    foreach ([['Khách Một', 'one@example.test', '0900000001'], ['Khách Hai', 'two@example.test', '0900000002']] as [$name,$email,$phone]) {
        Auth::register(['name' => $name, 'email' => $email, 'phone' => $phone, 'gender' => 2, 'birthday' => '1990-01-01', 'password' => 'Password123!', 'confirmation' => 'Password123!']);
    }
    D::execute('INSERT INTO cinema_admins (username,name,password_hash) VALUES (?,?,?)', ['testadmin', 'Admin Test', password_hash('Password123!', PASSWORD_DEFAULT)]);
    $admin = (int) db()->lastInsertId();
    D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description) VALUES (?,?,?,?,?,?,?)', ['Phim kiểm thử', 'Hành động', 0, 100, date('Y-m-d'), date('Y-m-d', strtotime('+30 days')), 'Fixture']);
    $movie = (int) db()->lastInsertId();
    D::execute('INSERT INTO cinema_cinemas (name,address,phone) VALUES (?,?,?)', ['Rạp Test', 'Địa chỉ Test', '0900000010']);
    $cinema = (int) db()->lastInsertId();
    C::createRoom($cinema, 'Phòng VIP', 'vip', 2, 6);
    $room = (int) D::one('SELECT id FROM cinema_rooms WHERE cinema_id=? AND name=?', [$cinema, 'Phòng VIP'])['id'];
    C::createRoom($cinema, 'Phòng thường', 'standard', 2, 6);
    $otherRoom = (int) D::one('SELECT id FROM cinema_rooms WHERE cinema_id=? AND name=?', [$cinema, 'Phòng thường'])['id'];
    C::saveShowtime(null, $movie, $room, date('Y-m-d', strtotime('+1 day')) . 'T19:00', 90000, 'Phụ đề tiếng Việt');
    $show = (int) D::one('SELECT id FROM cinema_showtimes WHERE room_id=?', [$room])['id'];
    D::execute('INSERT INTO cinema_combos (name,price) VALUES (?,?)', ['Combo Test', 50000]);
    $combo = (int) db()->lastInsertId();
    $seats = array_column(D::all('SELECT id FROM cinema_seats WHERE room_id=? ORDER BY id', [$room]), 'id');
    $users = D::all('SELECT * FROM cinema_users ORDER BY id');
    return compact('admin', 'movie', 'cinema', 'room', 'otherRoom', 'show', 'combo', 'seats', 'users');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rejects(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException($message);
}
