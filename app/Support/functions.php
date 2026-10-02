<?php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['app_config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function public_base(): string
{
    $configured = (string) config('base_path', '');
    if ($configured !== '') {
        return rtrim('/' . trim($configured, '/'), '/');
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $physical = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? APP_ROOT . '/public/index.php');
    $relative = substr($physical, strlen(str_replace('\\', '/', APP_ROOT)));
    if (str_starts_with($relative, '/public/')) {
        $suffix = substr($relative, strlen('/public'));
        return rtrim(substr($script, 0, -strlen($suffix)), '/');
    }
    return rtrim(substr($script, 0, -strlen($relative)), '/') . '/public';
}

function url(string $path = 'index.php', array $query = []): string
{
    return public_base() . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query), true, 303);
    exit;
}

function asset_url(string $path): string
{
    return url($path, ['v' => (string) filemtime(APP_ROOT . '/public/' . $path)]);
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Phương thức không được hỗ trợ.');
    }
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.');
    }
}

function money(mixed $amount): string
{
    return number_format((float) $amount, 0, ',', '.') . ' ₫';
}

function db(): PDO
{
    return App\Support\Database::connection();
}

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_ROOT . '/app/Views/' . $template . '.php';
}

function request_id(string $key = 'id'): int
{
    $value = filter_var($_GET[$key] ?? $_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if (!$value || $value < 1) {
        http_response_code(404);
        exit('Không tìm thấy dữ liệu.');
    }
    return $value;
}

function image_url(?string $path): string
{
    if (!$path) {
        return url('assets/images/poster-placeholder.svg');
    }
    if (str_starts_with($path, 'upload:')) {
        return url('media.php', ['file' => substr($path, 7)]);
    }
    return url('assets/images/' . ltrim($path, '/'));
}

function text_input(string $key, int $max = 255, bool $required = true): string
{
    $value = trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : '');
    if (($required && $value === '') || mb_strlen($value) > $max) {
        throw new DomainException('Vui lòng kiểm tra trường thông tin: ' . $key);
    }
    return $value;
}

function fail_not_found(): never
{
    http_response_code(404);
    exit('Không tìm thấy dữ liệu.');
}

function password_input(string $key): string
{
    $value = $_POST[$key] ?? null;
    if (!is_string($value) || strlen($value) > 72 || $value === '') {
        throw new DomainException('Vui lòng kiểm tra mật khẩu.');
    }
    return $value;
}
