<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Services\BookingService as B;
use App\Services\PaymentService as P;
use App\Support\Database as D;

$job = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$wait = $job['deadline'] - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1000000));
}
if ($job['action'] === 'notify') {
    echo json_encode(P::notify($job['callback']), JSON_THROW_ON_ERROR);
} else {
    $user = D::one('SELECT * FROM cinema_users WHERE id=?', [$job['user']]);
    $id = B::placeOrder($job['show'], $user, [$job['seat']], [], 'counter', bin2hex(random_bytes(32)));
    echo json_encode(['order' => $id], JSON_THROW_ON_ERROR);
}
