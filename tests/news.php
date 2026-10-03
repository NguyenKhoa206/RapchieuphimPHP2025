<?php
declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Support\Database as D;
use App\Services\NewsService as N;
use App\Services\CinemaService as C;

$fixture = reset_fixture();
$checks = 0;
function news_check(bool $condition, string $message): void
{
    check($condition, $message);
    $GLOBALS['checks']++;
}
$input = ['title' => 'Tin thử <script>', 'summary' => '', 'body' => "Dòng một\nDòng hai <img onerror=alert(1)>", 'category' => 'news', 'status' => 'draft', 'published_at' => date('Y-m-d\TH:i', strtotime('-2 days')), 'expires_at' => '', 'stock_image' => 'com.jpg'];
$id = N::save(null, $input);
news_check(N::published($id) === null && N::latest() === [], 'Bản nháp bị công khai.');
news_check(D::one('SELECT summary FROM cinema_news WHERE id=?', [$id])['summary'] !== '', 'Không tạo mô tả ngắn.');
$input['status'] = 'published';
$input['stock_image'] = '';
N::save($id, $input);
news_check(N::published($id)['image'] === 'tinmoikhuyenmai/com.jpg', 'Sửa bài làm mất ảnh bìa.');
news_check(N::listing('news', 1)['count'] === 1 && N::listing('promotion', 1)['count'] === 0, 'Lọc loại bài sai.');
$input['published_at'] = date('Y-m-d\TH:i', strtotime('+1 day'));
N::save($id, $input);
news_check(N::published($id) === null && N::latest() === [], 'Hẹn giờ bị hiển thị sớm.');
$input['published_at'] = date('Y-m-d\TH:i', strtotime('-2 days'));
$input['expires_at'] = date('Y-m-d\TH:i', strtotime('-1 day'));
N::save($id, $input);
news_check(N::published($id) === null && N::listing('all', 1)['count'] === 0, 'Bài hết hạn vẫn xuất hiện.');
$input['expires_at'] = '';
N::save($id, $input);
N::hide($id);
news_check(N::published($id) === null && D::one('SELECT body FROM cinema_news WHERE id=?', [$id])['body'] === $input['body'], 'Ẩn bài làm mất nội dung.');
N::save($id, $input);
news_check(N::published($id) !== null, 'Không đăng lại bài đã ẩn.');
foreach ([['category' => 'bad'], ['status' => 'bad'], ['title' => ''], ['title' => str_repeat('x', 191)], ['body' => ''], ['body' => str_repeat('x', 20001)], ['published_at' => '2026-02-31T10:00'], ['published_at' => '0001-01-01T10:00'], ['expires_at' => $input['published_at']], ['stock_image' => '../../config/local.php']] as $invalid) {
    rejects(static fn () => N::save($id, array_replace($input, $invalid)), 'Nội dung sai được lưu.');
    $checks++;
}
rejects(static fn () => N::save(null, $input), 'Bài mới không có ảnh được lưu.');
$checks++;
rejects(static fn () => N::save(99999999, $input), 'Sửa ID không tồn tại.');
$checks++;
rejects(static fn () => N::hide(99999999), 'Ẩn ID không tồn tại.');
$checks++;
$long = array_replace($input, ['body' => str_repeat('🎬', 20000)]);
N::save($id, $long);
news_check(mb_strlen(N::published($id)['body']) === 20000, 'Nội dung UTF-8 dài bị cắt.');
for ($i = 0; $i < 13; $i++) {
    N::save(null, array_replace($input, ['title' => 'Bài ' . $i, 'stock_image' => 'snack.jpg']));
}
news_check(count(N::latest()) === 12, 'Trang chủ lấy bài không giới hạn.');
news_check(count(N::listing('all', 1)['rows']) === 12 && count(N::listing('all', 2)['rows']) === 2, 'Phân trang sai.');
news_check(N::listing('all', 999)['page'] === 2, 'Không giới hạn số trang.');

foreach (['https://youtu.be/cPs3j9LNCjY?si=test', 'https://www.youtube.com/watch?v=cPs3j9LNCjY&feature=share', 'https://youtube.com/shorts/cPs3j9LNCjY', 'https://m.youtube.com/embed/cPs3j9LNCjY'] as $url) {
    news_check(youtube_trailer_url($url) === 'https://www.youtube.com/watch?v=cPs3j9LNCjY', 'Đường dẫn YouTube hợp lệ không được chuẩn hóa.');
}
foreach (['javascript:alert(1)', 'https://youtube.com.evil.test/watch?v=cPs3j9LNCjY', 'https://youtube.com@evil.test/watch?v=cPs3j9LNCjY', 'http://youtube.com/watch?v=cPs3j9LNCjY', 'https://youtube.com/watch?v[]=cPs3j9LNCjY', 'https://youtube.com/watch?v=bad'] as $url) {
    news_check(youtube_trailer_url($url) === null, 'Nhận URL trailer không an toàn.');
}
$film = D::one('SELECT * FROM cinema_movies WHERE id=?', [$fixture['movie']]);
rejects(static fn () => C::saveMovie($fixture['movie'], array_replace($film, ['trailer_url' => 'https://evil.test'])), 'Lưu URL trailer ngoài YouTube.');
$checks++;
C::saveMovie($fixture['movie'], array_replace($film, ['trailer_url' => 'https://youtu.be/cPs3j9LNCjY']));
news_check(D::one('SELECT trailer FROM cinema_movies WHERE id=?', [$fixture['movie']])['trailer'] === 'https://www.youtube.com/watch?v=cPs3j9LNCjY', 'Không lưu được trailer YouTube.');

clear_fixture();
$demo = require APP_ROOT . '/database/demo-content.php';
// Simulate the four films from the previous seed; the update must preserve them.
foreach ([0, 2, 4, 7] as $index) {
    [$title, $genre, $age, $duration, $poster] = $demo['movies'][$index];
    D::execute('INSERT INTO cinema_movies (title,genre,age_rating,duration,release_date,end_date,description,poster) VALUES (?,?,?,?,?,?,?,?)', ['Đã sửa ' . $title, $genre, $age, $duration, '2025-01-01', '2027-01-01', 'Nội dung riêng', $poster]);
}
$before = D::all('SELECT * FROM cinema_movies ORDER BY id');
$command = escapeshellarg(PHP_BINARY) . ' -c ' . escapeshellarg(php_ini_loaded_file()) . ' ' . escapeshellarg(APP_ROOT . '/scripts/seed-content.php') . ' --include-movies';
exec($command, $output, $exitCode);
news_check($exitCode === 0 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_movies')['n'] === 8, 'Không bổ sung đủ phim main cũ.');
foreach ($before as $film) {
    news_check(D::one('SELECT * FROM cinema_movies WHERE id=?', [$film['id']]) === $film, 'Ghi đè phim đã có.');
}
news_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_news WHERE is_demo=1')['n'] === 8, 'Thiếu tin minh họa main cũ.');
$sample = D::one('SELECT * FROM cinema_news ORDER BY id LIMIT 1');
D::execute("UPDATE cinema_news SET title='Đã sửa riêng',status='hidden' WHERE id=?", [$sample['id']]);
exec($command, $output, $exitCode);
news_check($exitCode === 0 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_movies')['n'] === 8 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_news')['n'] === 8, 'Import mẫu lặp tạo bản ghi trùng.');
news_check(D::one('SELECT title,status FROM cinema_news WHERE id=?', [$sample['id']]) === ['title' => 'Đã sửa riêng', 'status' => 'hidden'], 'Import mẫu lặp đăng lại hoặc ghi đè bài đã sửa.');
echo "PASS NEWS: $checks kiểm tra bản nháp, đăng/ẩn/hẹn giờ/hết hạn, nội dung UTF-8, phân trang và mẫu main cũ không ghi đè.\n";
