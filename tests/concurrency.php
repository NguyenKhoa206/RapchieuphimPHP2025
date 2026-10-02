<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';
use App\Support\Database as D;

if (!function_exists('proc_open')) {
    echo "SKIP Cần proc_open để kiểm tra hai kết nối đồng thời.\n";
    exit;
}
$f = reset_fixture();
// Fresh PHP processes have independent PDO connections and share a start deadline.
$workers = [];
$deadline = microtime(true) + 1;
for ($i = 0;$i < 2;$i++) {
    $command = [PHP_BINARY];
    if (php_ini_loaded_file()) {
        $command[] = '-c';
        $command[] = php_ini_loaded_file();
    }
    $command[] = __DIR__ . '/worker.php';
    $command[] = (string)$f['show'];
    $command[] = (string)$f['users'][$i]['id'];
    $command[] = (string)$f['seats'][0];
    $command[] = (string)$deadline;
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Không thể khởi động worker.');
    }
    $workers[] = [$process, $pipes];
}
foreach ($workers as [$process,$pipes]) {
    fwrite($pipes[0], "go\n");
    fclose($pipes[0]);
}
$codes = [];
foreach ($workers as [$process,$pipes]) {
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $codes[] = proc_close($process);
    echo $output;
    if ($errors) {
        fwrite(STDERR, $errors);
    }
}
sort($codes);
check($codes === [0, 2], 'Cần đúng một thành công và một từ chối ghế.');
check((int)D::one('SELECT COUNT(*) AS n FROM cinema_orders')['n'] === 1, 'Tạo nhiều hơn một đơn.');
check((int)D::one('SELECT COUNT(*) AS n FROM cinema_reservations')['n'] === 1, 'Giữ trùng ghế.');
echo "PASS Hai kết nối đặt cùng ghế: chỉ một đơn thành công.\n";
