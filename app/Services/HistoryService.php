<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;

final class HistoryService
{
    public static function table(string $kind): string
    {
        return match ($kind) {
            'invoices' => 'cinema_legacy_invoices',
            'tickets' => 'cinema_legacy_tickets',
            default => throw new \DomainException('Loại lịch sử không hợp lệ.'),
        };
    }

    public static function listing(string $kind, int $page = 1, ?int $userId = null, bool $unresolved = false): array
    {
        $table = self::table($kind);
        $where = $userId !== null ? 'h.user_id=?' : ($unresolved ? 'h.user_id IS NULL' : '1=1');
        $params = $userId !== null ? [$userId] : [];
        $count = (int) D::one("SELECT COUNT(*) AS n FROM $table h WHERE $where", $params)['n'];
        $page = max(1, min($page, max(1, (int) ceil($count / 20))));
        $offset = ($page - 1) * 20;
        $rows = D::all("SELECT h.*,u.name AS customer_name FROM $table h LEFT JOIN cinema_users u ON u.id=h.user_id WHERE $where ORDER BY h.id DESC LIMIT 20 OFFSET $offset", $params);
        return compact('count', 'page', 'rows');
    }

    public static function detail(string $kind, int $id, ?int $userId = null): ?array
    {
        $table = self::table($kind);
        $where = 'h.id=?' . ($userId !== null ? ' AND h.user_id=?' : '');
        $row = D::one("SELECT h.*,u.name AS customer_name FROM $table h LEFT JOIN cinema_users u ON u.id=h.user_id WHERE $where", $userId !== null ? [$id, $userId] : [$id]);
        if ($row && $kind === 'invoices') {
            $row['tickets'] = D::all('SELECT * FROM cinema_legacy_tickets WHERE invoice_id=?' . ($userId !== null ? ' AND user_id=?' : '') . ' ORDER BY id', $userId !== null ? [$id, $userId] : [$id]);
        }
        return $row;
    }

    public static function summary(): array
    {
        $invoices = D::one('SELECT COUNT(*) AS n,COALESCE(SUM(amount),0) AS amount,SUM(user_id IS NULL) AS unresolved,SUM(amount IS NULL) AS missing_amount FROM cinema_legacy_invoices');
        $tickets = D::one('SELECT COUNT(*) AS n,SUM(invoice_id IS NULL) AS unlinked,SUM(user_id IS NULL) AS unresolved FROM cinema_legacy_tickets');
        return compact('invoices', 'tickets');
    }
}
