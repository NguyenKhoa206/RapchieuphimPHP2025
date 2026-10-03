<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;
use App\Support\Upload;

final class CinemaService
{
    public static function movies(string $search = '', string $status = 'showing', int $page = 1): array
    {
        $where = 'active=1';
        $params = [];
        if ($status === 'upcoming') {
            $where .= ' AND release_date>CURDATE()';
        } elseif ($status === 'showing') {
            $where .= ' AND release_date<=CURDATE() AND end_date>=CURDATE()';
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE ? OR genre LIKE ?)';
            $params = ['%' . $search . '%', '%' . $search . '%'];
        }
        $total = (int) D::one("SELECT COUNT(*) AS n FROM cinema_movies WHERE $where", $params)['n'];
        $page = max(1, min($page, max(1, (int) ceil($total / 12))));
        $offset = ($page - 1) * 12;
        return ['items' => D::all("SELECT * FROM cinema_movies WHERE $where ORDER BY release_date DESC,id DESC LIMIT 12 OFFSET $offset", $params), 'total' => $total, 'page' => $page];
    }

    public static function showtimes(?int $movie = null, ?string $date = null, ?int $cinema = null): array
    {
        $sql = 'SELECT s.*,m.title,m.poster,m.age_rating,c.name AS cinema_name,r.name AS room_name,r.room_type FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE s.active=1 AND m.active=1 AND r.active=1 AND c.active=1 AND s.starts_at>NOW()';
        $params = [];
        if ($movie) {
            $sql .= ' AND s.movie_id=?';
            $params[] = $movie;
        }
        if ($date) {
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$start || $start->format('Y-m-d') !== $date) {
                throw new \DomainException('Ngày chiếu không hợp lệ.');
            }
            $sql .= ' AND s.starts_at>=? AND s.starts_at<?';
            $params[] = $date . ' 00:00:00';
            $params[] = $start->modify('+1 day')->format('Y-m-d H:i:s');
        }
        if ($cinema) {
            $sql .= ' AND r.cinema_id=?';
            $params[] = $cinema;
        }
        return D::all($sql . ' ORDER BY s.starts_at,c.name,r.name LIMIT 200', $params);
    }

    public static function saveMovie(?int $id, array $input): void
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $input['release_date']);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $input['end_date']);
        if (!$start || !$end || $start->format('Y-m-d') !== $input['release_date'] || $end->format('Y-m-d') !== $input['end_date'] || $start > $end
            || $input['duration'] < 1 || $input['duration'] > 600 || !in_array($input['age_rating'], [0, 13, 16, 18], true)) {
            throw new \DomainException('Ngày chiếu, thời lượng hoặc giới hạn tuổi không hợp lệ.');
        }
        $url = is_string($input['trailer_url'] ?? null) ? trim($input['trailer_url']) : '';
        $externalTrailer = youtube_trailer_url($url);
        if ($url !== '' && !$externalTrailer) {
            throw new \DomainException('Trailer phải là đường dẫn HTTPS của YouTube hoặc youtu.be.');
        }
        $db = db();
        $poster = null;
        $trailer = null;
        try {
            $poster = Upload::save('poster', 'image');
            $trailer = Upload::save('trailer', 'video');
            $db->beginTransaction();
            $old = $id ? D::one('SELECT * FROM cinema_movies WHERE id=? AND active=1 FOR UPDATE', [$id]) : null;
            if ($id && !$old) {
                throw new \DomainException('Không tìm thấy phim.');
            }
            if ($old && ($old['duration'] != $input['duration'] || $old['release_date'] !== $input['release_date'] || $old['end_date'] !== $input['end_date'])
                && D::one('SELECT id FROM cinema_showtimes WHERE movie_id=? AND active=1 AND starts_at>NOW() LIMIT 1', [$id])) {
                throw new \DomainException('Phim đã có lịch chiếu tương lai. Hãy hủy các lịch chưa bán vé trước khi sửa thời lượng hoặc ngày phát hành.');
            }
            $values = [$input['title'], $input['genre'], $input['age_rating'], $input['duration'], $input['release_date'], $input['end_date'], $input['description'], $poster ?? ($old['poster'] ?? null), $trailer ?? $externalTrailer ?? ($old['trailer'] ?? null)];
            if ($id) {
                D::execute('UPDATE cinema_movies SET title=?,genre=?,age_rating=?,duration=?,release_date=?,end_date=?,description=?,poster=?,trailer=? WHERE id=?', [...$values, $id]);
            } else {
                D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description,poster,trailer) VALUES (?,?,?,?,?,?,?,?,?)', $values);
            }
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Upload::remove($poster);
            Upload::remove($trailer);
            throw $error;
        }
        if ($poster) {
            Upload::remove($old['poster'] ?? null);
        }
        if ($trailer || $externalTrailer) {
            Upload::remove($old['trailer'] ?? null);
        }
    }

    public static function createRoom(int $cinema, string $name, string $type, int $rows, int $columns, ?int $capacity = null): void
    {
        if ($rows < 1 || $rows > 26 || $columns < 1 || $columns > 30 || !in_array($type, ['standard', 'vip', 'couple'], true)) {
            throw new \DomainException('Phòng phải có 1–26 hàng và 1–30 ghế mỗi hàng.');
        }
        $capacity ??= $rows * $columns;
        $db = db();
        $db->beginTransaction();
        try {
            if (!D::one('SELECT id FROM cinema_cinemas WHERE id=? AND active=1 FOR UPDATE', [$cinema])) {
                throw new \DomainException('Rạp không tồn tại.');
            }
            D::execute('INSERT INTO cinema_rooms (cinema_id,name,room_type) VALUES (?,?,?)', [$cinema, $name, $type]);
            self::generateSeats((int) $db->lastInsertId(), $rows, $columns, $capacity, $type);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function generateSeats(int $room, int $rows, int $columns, int $capacity, string $type = 'standard'): void
    {
        if ($rows < 1 || $rows > 26 || $columns < 1 || $columns > 30 || $capacity < 1 || $capacity > $rows * $columns) {
            throw new \DomainException('Sơ đồ ghế không hợp lệ.');
        }
        $extra = ['standard' => 0, 'vip' => 20000, 'couple' => 40000][$type] ?? 0;
        for ($index = 0; $index < $capacity; $index++) {
            D::execute('INSERT INTO cinema_seats (room_id,row_label,seat_number,price_extra) VALUES (?,?,?,?)', [$room, chr(65 + (int) floor($index / $columns)), ($index % $columns) + 1, $extra]);
        }
    }

    public static function saveShowtime(?int $id, int $movie, int $room, string $startValue, int $price, string $language): void
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startValue);
        if (!$start || $start->format('Y-m-d\TH:i') !== $startValue || $start <= new \DateTimeImmutable() || $price < 1000 || $price > 5000000) {
            throw new \DomainException('Giờ chiếu phải ở tương lai và giá vé phải hợp lệ.');
        }
        $db = db();
        $db->beginTransaction();
        try {
            $roomData = D::one('SELECT r.id FROM cinema_rooms r JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE r.id=? AND r.active=1 AND c.active=1 FOR UPDATE', [$room]);
            $film = D::one('SELECT * FROM cinema_movies WHERE id=? AND active=1 FOR UPDATE', [$movie]);
            if (!$roomData || !$film) {
                throw new \DomainException('Phim hoặc phòng chiếu không tồn tại.');
            }
            if ($start->format('Y-m-d') < $film['release_date'] || $start->format('Y-m-d') > $film['end_date']) {
                throw new \DomainException('Lịch chiếu nằm ngoài thời gian phát hành của phim.');
            }
            if ($id && (!D::one('SELECT id FROM cinema_showtimes WHERE id=? FOR UPDATE', [$id]) || D::one('SELECT id FROM cinema_orders WHERE showtime_id=? LIMIT 1', [$id]))) {
                throw new \DomainException('Không thể sửa lịch đã có đơn đặt vé.');
            }
            $end = $start->modify('+' . (int) $film['duration'] . ' minutes');
            $conflict = D::one('SELECT id FROM cinema_showtimes WHERE room_id=? AND active=1 AND id<>? AND starts_at<? AND DATE_ADD(ends_at,INTERVAL 15 MINUTE)>? LIMIT 1', [$room, $id ?? 0, $end->modify('+15 minutes')->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
            if ($conflict) {
                throw new \DomainException('Lịch bị trùng. Cần ít nhất 15 phút dọn phòng giữa hai suất, kể cả qua nửa đêm.');
            }
            $values = [$movie, $room, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $price, $language];
            if ($id) {
                D::execute('UPDATE cinema_showtimes SET movie_id=?,room_id=?,starts_at=?,ends_at=?,base_price=?,language=? WHERE id=?', [...$values, $id]);
            } else {
                D::execute('INSERT INTO cinema_showtimes (movie_id,room_id,starts_at,ends_at,base_price,language) VALUES (?,?,?,?,?,?)', $values);
            }
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }

    public static function archive(string $kind, int $id): void
    {
        $db = db();
        $db->beginTransaction();
        try {
            $tables = ['movie' => 'cinema_movies', 'cinema' => 'cinema_cinemas', 'room' => 'cinema_rooms', 'showtime' => 'cinema_showtimes', 'combo' => 'cinema_combos'];
            if (!isset($tables[$kind])) {
                throw new \DomainException('Thao tác không hợp lệ.');
            }
            if (!D::one('SELECT id FROM ' . $tables[$kind] . ' WHERE id=? FOR UPDATE', [$id])) {
                throw new \DomainException('Không tìm thấy dữ liệu.');
            }
            $conditions = ['movie' => 's.movie_id=?', 'cinema' => 'r.cinema_id=?', 'room' => 's.room_id=?', 'showtime' => 's.id=?'];
            if ($kind === 'showtime') {
                if (D::one("SELECT id FROM cinema_orders WHERE showtime_id=? AND (status='paid' OR (status='pending' AND expires_at>NOW())) LIMIT 1", [$id])) {
                    throw new \DomainException('Lịch đã có vé hoặc đơn đang giữ ghế. Không thể hủy.');
                }
            } elseif (isset($conditions[$kind]) && D::one('SELECT s.id FROM cinema_showtimes s JOIN cinema_rooms r ON r.id=s.room_id WHERE s.active=1 AND s.starts_at>NOW() AND ' . $conditions[$kind] . ' LIMIT 1', [$id])) {
                throw new \DomainException('Còn lịch chiếu tương lai. Hãy xử lý lịch chiếu trước.');
            }
            D::execute('UPDATE ' . $tables[$kind] . ' SET active=0 WHERE id=?', [$id]);
            $db->commit();
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
