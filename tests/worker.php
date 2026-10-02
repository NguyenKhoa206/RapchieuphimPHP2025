<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Services\BookingService as B;
use App\Support\Database as D;

fgets(STDIN);
$user = D::one('SELECT * FROM cinema_users WHERE id=?', [(int)$argv[2]]);
$wait = (float)$argv[4] - microtime(true);
if ($wait > 0) {
    usleep((int)($wait * 1000000));
}
try {
    $id = B::placeOrder((int)$argv[1], $user, [(int)$argv[3]], [], 'counter', bin2hex(random_bytes(32)));
    echo "Đã tạo đơn $id\n";
    exit(0);
} catch (DomainException $error) {
    echo 'Ghế bị từ chối: ' . $error->getMessage() . PHP_EOL;
    exit(2);
}
