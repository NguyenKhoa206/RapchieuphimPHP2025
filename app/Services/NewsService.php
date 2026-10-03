<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;
use App\Support\Upload;

final class NewsService
{
    public const CATEGORIES = ['news' => 'Tin mới', 'promotion' => 'Ưu đãi'];
    public const STATUSES = ['draft' => 'Bản nháp', 'published' => 'Đăng công khai', 'hidden' => 'Đã ẩn'];
    private const PUBLIC_WHERE = "status='published' AND published_at<=NOW() AND (expires_at IS NULL OR expires_at>NOW())";

    public static function stockImages(): array
    {
        return [
            'com.jpg' => 'Bắp ngọt vị cốm', 'happyday.jpg' => 'Happy Day',
            'snack.jpg' => 'Snack đủ vị', 'thanhvien.jpg' => 'Thành viên Galaxy',
            'imax-treasure-hunt.jpg' => 'IMAX', 'momo-galaxy.jpg' => 'MoMo',
            'zalopay.jpg' => 'ZaloPay', 'taiapp.jpg' => 'Ứng dụng Galaxy',
        ];
    }

    public static function latest(): array
    {
        return D::all('SELECT * FROM cinema_news WHERE ' . self::PUBLIC_WHERE . ' ORDER BY published_at DESC,id DESC LIMIT 12');
    }

    public static function listing(string $category, int $page): array
    {
        $where = self::PUBLIC_WHERE;
        $params = [];
        if (isset(self::CATEGORIES[$category])) {
            $where .= ' AND category=?';
            $params[] = $category;
        }
        $count = (int) D::one("SELECT COUNT(*) AS n FROM cinema_news WHERE $where", $params)['n'];
        $page = max(1, min($page, max(1, (int) ceil($count / 12))));
        $offset = ($page - 1) * 12;
        return ['rows' => D::all("SELECT * FROM cinema_news WHERE $where ORDER BY published_at DESC,id DESC LIMIT 12 OFFSET $offset", $params), 'count' => $count, 'page' => $page];
    }

    public static function published(int $id): ?array
    {
        return D::one('SELECT * FROM cinema_news WHERE id=? AND ' . self::PUBLIC_WHERE, [$id]);
    }

    public static function state(array $news): string
    {
        if ($news['status'] !== 'published') {
            return self::STATUSES[$news['status']] ?? 'Đã ẩn';
        }
        if (strtotime($news['published_at']) > time()) {
            return 'Hẹn giờ đăng';
        }
        return $news['expires_at'] && strtotime($news['expires_at']) <= time() ? 'Hết hạn' : 'Đang hiển thị';
    }

    public static function save(?int $id, array $input): int
    {
        $text = static function (string $key, int $max, bool $required = true) use ($input): string {
            $value = trim(is_string($input[$key] ?? null) ? $input[$key] : '');
            if (($required && $value === '') || mb_strlen($value) > $max) {
                $labels = ['title' => 'tiêu đề', 'summary' => 'mô tả ngắn', 'body' => 'nội dung bài', 'category' => 'loại bài', 'status' => 'trạng thái', 'published_at' => 'thời gian đăng', 'expires_at' => 'thời gian hết hạn', 'stock_image' => 'ảnh bìa'];
                throw new \DomainException('Vui lòng kiểm tra ' . $labels[$key] . '.');
            }
            return $value;
        };
        $title = $text('title', 190);
        $summary = $text('summary', 300, false);
        $body = $text('body', 20000);
        $category = $text('category', 16);
        $status = $text('status', 16);
        if (!isset(self::CATEGORIES[$category], self::STATUSES[$status])) {
            throw new \DomainException('Loại bài hoặc trạng thái không hợp lệ.');
        }
        $parseDate = static function (string $value): string {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
            if (!$date || (int) $date->format('Y') < 1000 || $date->format('Y-m-d\TH:i') !== $value) {
                throw new \DomainException('Ngày giờ đăng hoặc hết hạn không hợp lệ.');
            }
            return $date->format('Y-m-d H:i:s');
        };
        $start = $parseDate($text('published_at', 16));
        $endValue = $text('expires_at', 16, false);
        $end = $endValue !== '' ? $parseDate($endValue) : null;
        if ($end && $end <= $start) {
            throw new \DomainException('Thời gian hết hạn phải sau thời gian đăng.');
        }
        $stock = $text('stock_image', 80, false);
        if ($stock !== '' && !isset(self::stockImages()[$stock])) {
            throw new \DomainException('Ảnh có sẵn không hợp lệ.');
        }
        $image = null;
        $db = db();
        try {
            $image = Upload::save('image', 'image');
            $db->beginTransaction();
            $old = $id ? D::one('SELECT * FROM cinema_news WHERE id=? FOR UPDATE', [$id]) : null;
            if ($id && !$old) {
                throw new \DomainException('Không tìm thấy bài viết.');
            }
            $cover = $image ?? ($stock !== '' ? 'tinmoikhuyenmai/' . $stock : ($old['image'] ?? null));
            if (!$cover) {
                throw new \DomainException('Vui lòng tải ảnh bìa hoặc chọn ảnh có sẵn.');
            }
            $summary = $summary !== '' ? $summary : mb_strimwidth($body, 0, 240, '…');
            $values = [$title, $summary, $body, $cover, $category, $status, ($input['is_demo'] ?? '') === '1' ? 1 : 0, $start, $end];
            if ($id) {
                D::execute('UPDATE cinema_news SET title=?,summary=?,body=?,image=?,category=?,status=?,is_demo=?,published_at=?,expires_at=? WHERE id=?', [...$values, $id]);
            } else {
                D::execute('INSERT INTO cinema_news (title,summary,body,image,category,status,is_demo,published_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?)', $values);
                $id = (int) $db->lastInsertId();
            }
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Upload::remove($image);
            throw $error;
        }
        if ($old && $old['image'] !== $cover) {
            Upload::remove($old['image']);
        }
        return $id;
    }

    public static function hide(int $id): void
    {
        if (!D::one('SELECT id FROM cinema_news WHERE id=?', [$id])) {
            throw new \DomainException('Không tìm thấy bài viết.');
        }
        D::execute("UPDATE cinema_news SET status='hidden' WHERE id=?", [$id]);
    }
}
