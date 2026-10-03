<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/lib/demo.php';
use App\Support\Database as D;
if (array_diff(array_slice($argv, 1), ['--include-movies'])) {
    throw new RuntimeException('Cách dùng: php scripts/seed-content.php [--include-movies]');
}
$content = require APP_ROOT . '/database/demo-content.php';
$movies = $news = 0;
$db = db();
$db->beginTransaction();
try {
    if (in_array('--include-movies', $argv, true)) {
        foreach ($content['movies'] as $film) {
            [$title, $genre, $age, $duration, $poster, $days] = $film;
            if ($existing = D::one('SELECT id FROM cinema_movies WHERE poster=? OR title=?', [$poster, $title])) {
                seed_movie_display((int) $existing['id'], $film);
                continue;
            }
            D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description,poster,trailer) VALUES (?,?,?,?,?,?,?,?,?)', [$title, $genre, $age, $duration, date('Y-m-d', strtotime($days . ' days')), date('Y-m-d', strtotime(($days + 60) . ' days')), 'Phim minh họa từ giao diện main cũ. Lịch phát hành, giới hạn tuổi và thời lượng trong bản mẫu không phải lịch chiếu thực tế.', $poster, $film['trailer'] ?? null]);
            seed_movie_display((int) $db->lastInsertId(), $film);
            $movies++;
        }
    }
    foreach ($content['news'] as [$image, $title, $category]) {
        $key = 'classic:' . $image;
        if (D::one('SELECT id FROM cinema_news WHERE sample_key=?', [$key])) {
            continue;
        }
        D::execute('INSERT INTO cinema_news (sample_key,title,summary,body,image,category,status,is_demo,published_at) VALUES (?,?,?,?,?,?,?,1,?)', [$key, $title, 'Nội dung minh họa từ giao diện Galaxy cũ.', 'Bài giới thiệu minh họa từ giao diện cũ của đồ án. Hình ảnh này không xác nhận chương trình còn hiệu lực. Vui lòng liên hệ rạp để biết thời gian, giá và điều kiện áp dụng thực tế.', 'tinmoikhuyenmai/' . $image, $category, 'published', date('Y-m-d H:i:s')]);
        $news++;
    }
    $db->commit();
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
echo "Đã thêm $movies phim và $news bài minh họa. Giữ nguyên phim, nội dung và trạng thái bài đã có.\n";
