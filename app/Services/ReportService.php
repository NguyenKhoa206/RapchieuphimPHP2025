<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;

final class ReportService
{
    public static function revenue(string $period, int $year, ?int $cinema, bool|string $mode = false): array
    {
        if (!in_array($period, ['day', 'month', 'year'], true) || $year < 2000 || $year > 2100) {
            throw new \DomainException('Bộ lọc doanh thu không hợp lệ.');
        }
        $group = ['day' => "DATE_FORMAT(o.paid_at,'%Y-%m-%d')", 'month' => "DATE_FORMAT(o.paid_at,'%Y-%m')", 'year' => "DATE_FORMAT(o.paid_at,'%Y')"][$period];
        $mode = is_bool($mode) ? ($mode ? 'demo' : 'counter') : $mode;
        $paidOnline = "EXISTS (SELECT 1 FROM cinema_payments p WHERE p.order_id=o.id AND p.status='paid' AND p.environment='production')";
        $source = match ($mode) {
            'real' => "(o.payment_method='counter' OR (o.payment_method='vnpay' AND $paidOnline))",
            'counter' => "o.payment_method='counter'",
            'vnpay' => "o.payment_method='vnpay' AND $paidOnline",
            'sandbox' => "o.payment_method='vnpay' AND EXISTS (SELECT 1 FROM cinema_payments p WHERE p.order_id=o.id AND p.status='paid' AND p.environment='sandbox')",
            'demo' => "o.payment_method='demo'",
            default => throw new \DomainException('Nguồn doanh thu không hợp lệ.'),
        };
        $params = [$year . '-01-01 00:00:00', ($year + 1) . '-01-01 00:00:00'];
        $where = "o.status='paid' AND o.paid_at>=? AND o.paid_at<? AND $source";
        if ($cinema) {
            $where .= ' AND r.cinema_id=?';
            $params[] = $cinema;
        }
        // One row per order; never join seat/item rows when summing order totals.
        return D::all("SELECT $group AS label,SUM(o.total) AS total,COUNT(*) AS orders FROM cinema_orders o JOIN cinema_showtimes s ON s.id=o.showtime_id JOIN cinema_rooms r ON r.id=s.room_id WHERE $where GROUP BY $group ORDER BY label", $params);
    }
}
