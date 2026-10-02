<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
use App\Services\CinemaService;

$showtimes = CinemaService::showtimes();
view('layouts/header', ['title' => 'Lịch chiếu']);
?>
<div class="page-heading"><span class="eyebrow">LÊN KẾ HOẠCH CHO BUỔI XEM PHIM</span><h1>Lịch chiếu & đặt vé</h1><p class="muted">Chọn suất chiếu để xem ghế còn trống trong đúng phòng.</p></div>
<?php view('movies/showtimes', ['showtimes' => $showtimes]);
view('layouts/footer'); ?>
