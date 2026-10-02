<?php

declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$name = is_string($_GET['file'] ?? null) ? $_GET['file'] : '';
if (!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp|mp4|webm)$/', $name, $match)) {
    fail_not_found();
}
$path = APP_ROOT . '/storage/uploads/' . $name;
if (!is_file($path)) {
    fail_not_found();
}
$size = filesize($path);
$start = 0;
$end = $size - 1;
$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', $range, $parts) || ($parts[1] === '' && $parts[2] === '') || $size === 0) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    if ($parts[1] === '') {
        $start = max(0, $size - (int) $parts[2]);
    } else {
        $start = (int) $parts[1];
        if ($parts[2] !== '') {
            $end = min($end, (int) $parts[2]);
        }
    }
    if ($start > $end || $start >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    http_response_code(206);
    header("Content-Range: bytes $start-$end/$size");
}
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'mp4' => 'video/mp4', 'webm' => 'video/webm'][$match[1]];
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $name . '"');
header('Cache-Control: public, max-age=86400');
header('Accept-Ranges: bytes');
header('Content-Length: ' . max(0, $end - $start + 1));
session_write_close();
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}
$stream = fopen($path, 'rb');
fseek($stream, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($stream) && !connection_aborted()) {
    $chunk = fread($stream, min(65536, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
}
fclose($stream);
