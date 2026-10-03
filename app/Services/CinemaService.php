<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;
use App\Support\Upload;

final class CinemaService
{
    private const DISPLAY_JOIN = ' LEFT JOIN cinema_movie_display d ON d.movie_id=m.id';
    private const MOVIE_COLUMNS = 'm.*,d.banner,COALESCE(d.is_hot,0) AS is_hot,COALESCE(d.is_featured,0) AS is_featured';

    public static function movies(string $search = '', string $status = 'showing', int $page = 1, string $tag = ''): array
    {
        $where = 'm.active=1';
        $params = [];
        if ($status === 'upcoming') {
            $where .= ' AND m.release_date>CURDATE()';
        } elseif ($status === 'past') {
            $where .= ' AND m.end_date<CURDATE()';
        } elseif ($status === 'showing') {
            $where .= ' AND m.release_date<=CURDATE() AND m.end_date>=CURDATE()';
        }
        if ($search !== '') {
            $where .= ' AND (m.title LIKE ? OR m.genre LIKE ?)';
            $params = ['%' . $search . '%', '%' . $search . '%'];
        }
        if ($tag !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM cinema_movie_tags t WHERE t.movie_id=m.id AND t.tag=?)';
            $params[] = $tag;
        }
        $total = (int) D::one("SELECT COUNT(*) AS n FROM cinema_movies m WHERE $where", $params)['n'];
        $page = max(1, min($page, max(1, (int) ceil($total / 12))));
        $offset = ($page - 1) * 12;
        $order = $status === 'upcoming' ? 'm.release_date ASC,m.id ASC' : 'm.release_date DESC,m.id DESC';
        $rows = D::all('SELECT ' . self::MOVIE_COLUMNS . ' FROM cinema_movies m' . self::DISPLAY_JOIN . " WHERE $where ORDER BY $order LIMIT 12 OFFSET $offset", $params);
        return ['items' => self::withTags($rows), 'total' => $total, 'page' => $page];
    }

    private static function withTags(array $movies): array
    {
        if (!$movies) {
            return [];
        }
        $ids = array_column($movies, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $tags = [];
        foreach (D::all("SELECT movie_id,tag FROM cinema_movie_tags WHERE movie_id IN ($placeholders) ORDER BY tag", $ids) as $row) {
            $tags[$row['movie_id']][] = $row['tag'];
        }
        foreach ($movies as &$movie) {
            $movie['tags'] = $tags[$movie['id']] ?? [];
        }
        unset($movie);
        return $movies;
    }

    public static function movie(int $id): ?array
    {
        $rows = D::all('SELECT ' . self::MOVIE_COLUMNS . ' FROM cinema_movies m' . self::DISPLAY_JOIN . ' WHERE m.id=? AND m.active=1', [$id]);
        return self::withTags($rows)[0] ?? null;
    }

    public static function tagOptions(): array
    {
        return array_column(D::all('SELECT t.tag,COUNT(*) AS n FROM cinema_movie_tags t JOIN cinema_movies m ON m.id=t.movie_id WHERE m.active=1 GROUP BY t.tag ORDER BY n DESC,t.tag LIMIT 16'), 'tag');
    }

    public static function parseTags(string $value): array
    {
        if (mb_strlen($value) > 300) {
            throw new \DomainException('Tối đa 8 tag, mỗi tag dài tối đa 32 ký tự.');
        }
        $tags = [];
        foreach (explode(',', $value) as $tag) {
            $tag = preg_replace('/\s+/u', ' ', trim($tag));
            if ($tag === '') {
                continue;
            }
            if (!$tag || mb_strlen($tag) > 32 || !preg_match('/^[\p{L}\p{N} &+\-]+$/u', $tag)) {
                throw new \DomainException('Tag chỉ gồm chữ, số, khoảng trắng hoặc dấu &, +, -.');
            }
            $tags[mb_strtolower($tag)] ??= $tag;
        }
        if (count($tags) > 8) {
            throw new \DomainException('Mỗi phim có tối đa 8 tag.');
        }
        return array_values($tags);
    }

    public static function featured(): array
    {
        $rows = D::all('SELECT ' . self::MOVIE_COLUMNS . ' FROM cinema_movies m' . self::DISPLAY_JOIN . ' WHERE m.active=1 ORDER BY is_featured DESC,(m.release_date<=CURDATE() AND m.end_date>=CURDATE()) DESC,m.release_date DESC,m.id ASC LIMIT 3');
        return self::withTags($rows);
    }

    public static function hot(): array
    {
        return self::withTags(D::all('SELECT ' . self::MOVIE_COLUMNS . ' FROM cinema_movies m' . self::DISPLAY_JOIN . ' WHERE m.active=1 AND d.is_hot=1 ORDER BY m.release_date DESC,m.id DESC LIMIT 4'));
    }

    public static function monthlyTop(): array
    {
        $start = date('Y-m-01') . ' 00:00:00';
        $end = date('Y-m-d H:i:s', strtotime('first day of next month 00:00:00'));
        // Aggregate seat quantities first: combos and payment attempts must not
        // multiply ticket counts. Demo and sandbox payments are excluded.
        $ranked = "SELECT s.movie_id,SUM(i.quantity) AS tickets FROM cinema_orders o
            JOIN cinema_showtimes s ON s.id=o.showtime_id
            JOIN cinema_order_items i ON i.order_id=o.id AND i.kind='seat'
            WHERE o.status='paid' AND o.paid_at>=? AND o.paid_at<? AND o.paid_at<=NOW()
            AND (o.payment_method='counter' OR (o.payment_method='vnpay' AND EXISTS
                (SELECT 1 FROM cinema_payments p WHERE p.order_id=o.id AND p.status='paid' AND p.environment='production')))
            GROUP BY s.movie_id HAVING tickets>0";
        return self::withTags(D::all('SELECT ' . self::MOVIE_COLUMNS . ',ranking.tickets FROM cinema_movies m' . self::DISPLAY_JOIN . " JOIN ($ranked) ranking ON ranking.movie_id=m.id WHERE m.active=1 ORDER BY ranking.tickets DESC,m.id ASC LIMIT 4", [$start, $end]));
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
        $tags = array_key_exists('tags', $input) ? self::parseTags(is_string($input['tags']) ? $input['tags'] : '') : null;
        $db = db();
        $poster = null;
        $trailer = null;
        $banner = null;
        try {
            $poster = Upload::save('poster', 'image');
            $trailer = Upload::save('trailer', 'video');
            $banner = Upload::save('banner', 'image');
            $db->beginTransaction();
            $old = $id ? D::one('SELECT * FROM cinema_movies WHERE id=? AND active=1 FOR UPDATE', [$id]) : null;
            if ($id && !$old) {
                throw new \DomainException('Không tìm thấy phim.');
            }
            $display = $id ? D::one('SELECT * FROM cinema_movie_display WHERE movie_id=?', [$id]) : null;
            if ($old && ($old['duration'] != $input['duration'] || $old['release_date'] !== $input['release_date'] || $old['end_date'] !== $input['end_date'])
                && D::one('SELECT id FROM cinema_showtimes WHERE movie_id=? AND active=1 AND starts_at>NOW() LIMIT 1', [$id])) {
                throw new \DomainException('Phim đã có lịch chiếu tương lai. Hãy hủy các lịch chưa bán vé trước khi sửa thời lượng hoặc ngày phát hành.');
            }
            $values = [$input['title'], $input['genre'], $input['age_rating'], $input['duration'], $input['release_date'], $input['end_date'], $input['description'], $poster ?? ($old['poster'] ?? null), $trailer ?? $externalTrailer ?? ($old['trailer'] ?? null)];
            if ($id) {
                D::execute('UPDATE cinema_movies SET title=?,genre=?,age_rating=?,duration=?,release_date=?,end_date=?,description=?,poster=?,trailer=? WHERE id=?', [...$values, $id]);
            } else {
                D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description,poster,trailer) VALUES (?,?,?,?,?,?,?,?,?)', $values);
                $id = (int) $db->lastInsertId();
            }
            $cover = $banner ?? (($input['remove_banner'] ?? '') === '1' ? null : ($display['banner'] ?? null));
            if ($tags !== null || $banner || array_key_exists('is_hot', $input) || array_key_exists('is_featured', $input) || array_key_exists('remove_banner', $input)) {
                $hot = array_key_exists('is_hot', $input) ? (($input['is_hot'] ?? '') === '1' ? 1 : 0) : ($display['is_hot'] ?? 0);
                $featured = array_key_exists('is_featured', $input) ? (($input['is_featured'] ?? '') === '1' ? 1 : 0) : ($display['is_featured'] ?? 0);
                D::execute('INSERT INTO cinema_movie_display (movie_id,banner,is_hot,is_featured) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE banner=VALUES(banner),is_hot=VALUES(is_hot),is_featured=VALUES(is_featured)', [$id, $cover, $hot, $featured]);
            }
            if ($tags !== null) {
                D::execute('DELETE FROM cinema_movie_tags WHERE movie_id=?', [$id]);
                foreach ($tags as $tag) {
                    D::execute('INSERT IGNORE INTO cinema_movie_tags (movie_id,tag) VALUES (?,?)', [$id, $tag]);
                }
            }
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Upload::remove($poster);
            Upload::remove($trailer);
            Upload::remove($banner);
            throw $error;
        }
        if ($poster) {
            Upload::remove($old['poster'] ?? null);
        }
        if ($trailer || $externalTrailer) {
            Upload::remove($old['trailer'] ?? null);
        }
        if ($display && $display['banner'] !== $cover) {
            Upload::remove($display['banner']);
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
