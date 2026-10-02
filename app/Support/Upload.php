<?php

declare(strict_types=1);

namespace App\Support;

final class Upload
{
    public static function save(string $field, string $kind): ?string
    {
        $file = $_FILES[$field] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > config('upload_limit')) {
            throw new \DomainException('File tải lên quá lớn hoặc chưa tải lên thành công.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $types = $kind === 'image'
            ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
            : ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!isset($types[$mime]) || ($kind === 'image' && !getimagesize($file['tmp_name']))) {
            throw new \DomainException('Ảnh phải là JPG, PNG, WebP; trailer phải là MP4 hoặc WebM.');
        }
        $directory = APP_ROOT . '/storage/uploads';
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new \RuntimeException('Không thể tạo thư mục upload.');
        }
        $name = bin2hex(random_bytes(20)) . '.' . $types[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
            throw new \RuntimeException('Không thể lưu file tải lên.');
        }
        return 'upload:' . $name;
    }

    public static function remove(?string $path): void
    {
        if ($path && preg_match('/^upload:([a-f0-9]{40}\.(jpg|png|webp|mp4|webm))$/', $path, $m)) {
            $file = APP_ROOT . '/storage/uploads/' . $m[1];
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
