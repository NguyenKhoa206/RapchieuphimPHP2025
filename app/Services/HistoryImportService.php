<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Database as D;

final class HistoryImportService
{
    private array $mapping;
    private array $report = [
        'invoices' => 0, 'tickets' => 0, 'new_invoices' => 0, 'new_tickets' => 0,
        'unchanged' => 0, 'linked_tickets' => 0, 'unlinked_tickets' => 0,
        'unresolved_customers' => 0, 'unresolved_showtimes' => 0,
        'invoice_amount_total' => 0, 'missing_invoice_amounts' => 0,
    ];

    public function __construct(array $mapping)
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,80}$/', $mapping['source_name'] ?? '')) {
            throw new \DomainException('source_name phải có 1–80 chữ, số, dấu chấm/gạch.');
        }
        foreach (['invoices', 'tickets'] as $kind) {
            if (!isset($mapping[$kind]['table'], $mapping[$kind]['columns']) || !is_array($mapping[$kind]['columns'])) {
                throw new \DomainException('Mapping phải khai báo bảng và columns cho invoices/tickets.');
            }
        }
        $this->mapping = $mapping;
    }

    private static function identifier(string $value): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $value) || str_starts_with(strtolower($value), 'cinema_')) {
            throw new \DomainException('Tên bảng/cột nguồn không hợp lệ: ' . $value);
        }
        return '`' . $value . '`';
    }

    private function source(string $kind): array
    {
        $definition = $this->mapping[$kind];
        $table = self::identifier((string) $definition['table']);
        $columns = array_column(D::all('SHOW COLUMNS FROM ' . $table), 'Field');
        $map = $definition['columns'];
        if (empty($map['id'])) {
            $primary = D::all('SHOW INDEX FROM ' . $table . " WHERE Key_name='PRIMARY'");
            if (count($primary) !== 1) {
                throw new \DomainException('Bảng ' . $definition['table'] . ' cần khóa chính đơn hoặc mapping id duy nhất. Không tự suy đoán khóa bằng MaKH.');
            }
            $map['id'] = $primary[0]['Column_name'];
        }
        foreach ($map as $field => $column) {
            if ($column !== null && (!is_string($column) || !in_array($column, $columns, true))) {
                throw new \DomainException('Cột mapping không tồn tại: ' . $kind . '.' . $field);
            }
        }
        if (empty($map['customer_id'])) {
            throw new \DomainException('Cần chỉ rõ cột customer_id; không ghép khách hàng bằng tên/email.');
        }
        $rows = D::all('SELECT * FROM ' . $table . ' ORDER BY ' . self::identifier($map['id']));
        $seen = [];
        foreach ($rows as $row) {
            $key = self::key($row[$map['id']]);
            if (isset($seen['key:' . $key])) {
                throw new \DomainException('Khóa nguồn bị trùng trong ' . $definition['table'] . '. Cần mapping khóa duy nhất.');
            }
            $seen['key:' . $key] = true;
        }
        return [$rows, $map];
    }

    private static function key(mixed $value): string
    {
        if (!is_scalar($value) || (string) $value === '' || mb_strlen((string) $value) > 190) {
            throw new \DomainException('Khóa lịch sử trống hoặc dài quá 190 ký tự.');
        }
        return (string) $value;
    }

    private static function value(array $row, array $map, string $field): ?string
    {
        $column = $map[$field] ?? null;
        if ($column === null || $row[$column] === null || (string) $row[$column] === '') {
            return null;
        }
        return (string) $row[$column];
    }

    private static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        throw new \DomainException('Ngày nguồn không hợp lệ. Cần Y-m-d hoặc Y-m-d H:i:s.');
    }

    private static function amount(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!preg_match('/^[0-9]{1,12}(?:\.0+)?$/', $value)) {
            throw new \DomainException('Số tiền nguồn phải là số nguyên VND không âm, không có dấu phân cách.');
        }
        return (int) $value;
    }

    private function user(?string $key): ?int
    {
        if ($key === null) {
            $this->report['unresolved_customers']++;
            return null;
        }
        $user = D::one('SELECT id FROM cinema_users WHERE BINARY legacy_key=?', [$key]);
        if (!$user) {
            $this->report['unresolved_customers']++;
        }
        return $user ? (int) $user['id'] : null;
    }

    private static function raw(array $row): array
    {
        ksort($row);
        $raw = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return [$raw, hash('sha256', $raw)];
    }

    private function existing(string $table, string $key, string $hash): ?array
    {
        $existing = D::one('SELECT * FROM ' . $table . ' WHERE source_name=? AND source_key=?', [$this->mapping['source_name'], $key]);
        if ($existing && !hash_equals($existing['source_hash'], $hash)) {
            throw new \DomainException('Dữ liệu nguồn đã đổi cho khóa ' . $key . '. Dừng nhập để đối chiếu; không ghi đè lịch sử.');
        }
        if ($existing) {
            $this->report['unchanged']++;
        }
        return $existing;
    }

    /** Dry-run and apply use the same validation. A failed apply rolls back every row. */
    public function import(bool $apply = false): array
    {
        $db = db();
        $db->beginTransaction();
        try {
            // Serializes imports, including dry-runs, without locking original tables for updates.
            D::execute("INSERT IGNORE INTO cinema_meta (setting_key,setting_value) VALUES ('history_import_lock','1')");
            D::one("SELECT setting_key FROM cinema_meta WHERE setting_key='history_import_lock' FOR UPDATE");
            [$invoices, $invoiceMap] = $this->source('invoices');
            [$tickets, $ticketMap] = $this->source('tickets');
            $mappingHash = hash('sha256', json_encode($this->mapping, JSON_THROW_ON_ERROR));
            $oldMapping = D::one('SELECT mapping_hash FROM cinema_history_batches WHERE source_name=? ORDER BY id LIMIT 1', [$this->mapping['source_name']]);
            if ($oldMapping && !hash_equals($oldMapping['mapping_hash'], $mappingHash)) {
                throw new \DomainException('Mapping của nguồn đã thay đổi. Đối chiếu bản nhập trước khi sửa quan hệ.');
            }
            $batch = null;
            if ($apply) {
                D::execute('INSERT INTO cinema_history_batches (source_name,mapping_hash,invoice_count,ticket_count) VALUES (?,?,0,0)', [$this->mapping['source_name'], $mappingHash]);
                $batch = (int) $db->lastInsertId();
            }
            $invoiceIndex = [];
            foreach ($invoices as $row) {
                $this->report['invoices']++;
                $key = self::key($row[$invoiceMap['id']]);
                [$raw, $hash] = self::raw($row);
                $existing = $this->existing('cinema_legacy_invoices', $key, $hash);
                $customer = self::value($row, $invoiceMap, 'customer_id');
                $user = $this->user($customer);
                $amount = self::amount(self::value($row, $invoiceMap, 'amount'));
                $issued = self::date(self::value($row, $invoiceMap, 'issued_at'));
                $status = self::value($row, $invoiceMap, 'status');
                $this->report['invoice_amount_total'] += $amount ?? 0;
                $this->report['missing_invoice_amounts'] += (int) ($amount === null);
                if (!$existing) {
                    $this->report['new_invoices']++;
                    if ($apply) {
                        D::execute('INSERT INTO cinema_legacy_invoices (source_name,source_key,source_hash,batch_id,user_id,legacy_customer_key,issued_at,amount,original_status,raw_data) VALUES (?,?,?,?,?,?,?,?,?,?)', [$this->mapping['source_name'], $key, $hash, $batch, $user, $customer, $issued, $amount, $status, $raw]);
                        $existing = ['id' => (int) $db->lastInsertId()];
                    }
                }
                $invoiceIndex['key:' . $key] = ['id' => $existing['id'] ?? null, 'customer' => $customer, 'user' => $user];
            }
            foreach ($tickets as $row) {
                $this->report['tickets']++;
                $key = self::key($row[$ticketMap['id']]);
                [$raw, $hash] = self::raw($row);
                $existing = $this->existing('cinema_legacy_tickets', $key, $hash);
                $customer = self::value($row, $ticketMap, 'customer_id');
                $user = $this->user($customer);
                $invoiceKey = self::value($row, $ticketMap, 'invoice_id');
                $invoice = $invoiceKey === null ? null : ($invoiceIndex['key:' . $invoiceKey] ?? null);
                if ($invoiceKey !== null && !$invoice) {
                    throw new \DomainException('Vé ' . $key . ' tham chiếu hóa đơn không tồn tại.');
                }
                if ($invoice && ($customer === null || $invoice['customer'] !== $customer)) {
                    throw new \DomainException('Khách hàng trên vé ' . $key . ' khác khách hàng trên hóa đơn.');
                }
                $this->report[$invoice ? 'linked_tickets' : 'unlinked_tickets']++;
                $showKey = self::value($row, $ticketMap, 'showtime_id');
                $show = null;
                if ($showKey !== null && ctype_digit($showKey) && D::one("SELECT setting_key FROM cinema_meta WHERE setting_key='legacy_imported_at'")) {
                    $show = D::one('SELECT m.title,c.name AS cinema_name,r.name AS room_name,s.starts_at FROM cinema_showtimes s JOIN cinema_movies m ON m.id=s.movie_id JOIN cinema_rooms r ON r.id=s.room_id JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE s.id=?', [$showKey]);
                }
                if ($showKey !== null && !$show) {
                    $this->report['unresolved_showtimes']++;
                }
                $fields = [
                    self::value($row, $ticketMap, 'movie_title') ?? ($show['title'] ?? null),
                    self::value($row, $ticketMap, 'cinema_name') ?? ($show['cinema_name'] ?? null),
                    self::value($row, $ticketMap, 'room_name') ?? ($show['room_name'] ?? null),
                    self::value($row, $ticketMap, 'seat_label'),
                    self::date(self::value($row, $ticketMap, 'starts_at') ?? ($show['starts_at'] ?? null)),
                    self::amount(self::value($row, $ticketMap, 'amount')),
                    self::value($row, $ticketMap, 'status'),
                ];
                if (!$existing) {
                    $this->report['new_tickets']++;
                    if ($apply) {
                        D::execute('INSERT INTO cinema_legacy_tickets (source_name,source_key,source_hash,batch_id,user_id,invoice_id,legacy_customer_key,legacy_invoice_key,movie_title,cinema_name,room_name,seat_label,starts_at,amount,original_status,raw_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$this->mapping['source_name'], $key, $hash, $batch, $user, $invoice['id'] ?? null, $customer, $invoiceKey, ...$fields, $raw]);
                    }
                }
            }
            if ($apply && ($this->report['new_invoices'] + $this->report['new_tickets']) > 0) {
                D::execute('UPDATE cinema_history_batches SET invoice_count=?,ticket_count=? WHERE id=?', [$this->report['new_invoices'], $this->report['new_tickets'], $batch]);
                $db->commit();
            } else {
                $db->rollBack();
            }
            return ['mode' => $apply ? 'apply' : 'dry-run', 'source_name' => $this->mapping['source_name'], ...$this->report];
        } catch (\Throwable $error) {
            $db->rollBack();
            throw $error;
        }
    }
}
