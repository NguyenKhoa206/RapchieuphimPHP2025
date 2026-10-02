<?php

declare(strict_types=1);
require __DIR__ . '/fixture.php';

use App\Services\HistoryImportService as I;
use App\Services\HistoryService as H;
use App\Services\ReportService as R;
use App\Support\Database as D;

$f = reset_fixture();
foreach (['HistoryTicketFixture', 'HistoryInvoiceFixture'] as $table) {
    D::execute('DROP TABLE IF EXISTS ' . $table);
}
D::execute('CREATE TABLE HistoryInvoiceFixture (MaHD VARCHAR(40) PRIMARY KEY,MaKH VARCHAR(40),NgayLap DATETIME,TongTien DECIMAL(15,2),TrangThai VARCHAR(60))');
D::execute('CREATE TABLE HistoryTicketFixture (MaVe VARCHAR(40) PRIMARY KEY,MaHD VARCHAR(40),MaKH VARCHAR(40),TenPhim VARCHAR(190),Ghe VARCHAR(20),ThoiGian DATETIME)');
D::execute('UPDATE cinema_users SET legacy_key=? WHERE id=?', ['KH001', $f['users'][0]['id']]);
D::execute('UPDATE cinema_users SET legacy_key=? WHERE id=?', ['KH002', $f['users'][1]['id']]);
foreach ([['HD001', 'KH001', 220000], ['HD002', 'KH001', 110000], ['HD003', 'KH002', 90000], ['HD004', 'UNKNOWN', 50000]] as [$id,$customer,$amount]) {
    D::execute('INSERT INTO HistoryInvoiceFixture VALUES (?,?,?,?,?)', [$id, $customer, '2025-01-01 18:00:00', $amount, '<script>old</script>']);
}
foreach ([['V001', 'HD001', 'KH001'], ['V002', 'HD001', 'KH001'], ['V003', 'HD002', 'KH001'], ['V004', 'HD003', 'KH002']] as [$id,$invoice,$customer]) {
    D::execute('INSERT INTO HistoryTicketFixture VALUES (?,?,?,?,?,?)', [$id, $invoice, $customer, 'Phim cũ <b>1</b>', 'A1', '2025-01-01 19:00:00']);
}
$map = ['source_name' => 'fixture-2025', 'invoices' => ['table' => 'HistoryInvoiceFixture', 'columns' => ['id' => null, 'customer_id' => 'MaKH', 'issued_at' => 'NgayLap', 'amount' => 'TongTien', 'status' => 'TrangThai']], 'tickets' => ['table' => 'HistoryTicketFixture', 'columns' => ['id' => null, 'customer_id' => 'MaKH', 'invoice_id' => 'MaHD', 'movie_title' => 'TenPhim', 'seat_label' => 'Ghe', 'starts_at' => 'ThoiGian']]];
$checks = 0;
function history_check(bool $condition, string $message): void
{
    check($condition, $message);
    $GLOBALS['checks']++;
}
$dry = (new I($map))->import();
history_check($dry['invoices'] === 4 && $dry['tickets'] === 4 && $dry['linked_tickets'] === 4 && $dry['unresolved_customers'] === 1, 'Báo cáo dry-run sai.');
history_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_history_batches')['n'] === 0 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_legacy_invoices')['n'] === 0 && !D::one("SELECT setting_key FROM cinema_meta WHERE setting_key='history_import_lock'"), 'Dry-run ghi dữ liệu.');
$applied = (new I($map))->import(true);
history_check($applied['new_invoices'] === 4 && $applied['new_tickets'] === 4, 'Nhập lịch sử thiếu dữ liệu.');
$summary = H::summary();
history_check((int) $summary['invoices']['amount'] === 470000 && (int) $summary['invoices']['n'] === 4, 'Tổng hóa đơn bị nhân theo vé.');
history_check(H::listing('invoices', 1, (int) $f['users'][0]['id'])['count'] === 2 && H::listing('tickets', 1, (int) $f['users'][0]['id'])['count'] === 3, 'Lịch sử không thuộc đúng khách.');
$invoice = D::one("SELECT id FROM cinema_legacy_invoices WHERE source_key='HD001'");
history_check(count(H::detail('invoices', (int) $invoice['id'], (int) $f['users'][0]['id'])['tickets']) === 2 && H::detail('invoices', (int) $invoice['id'], (int) $f['users'][1]['id']) === null, 'Quan hệ vé sai hoặc IDOR.');
history_check(str_contains(D::one('SELECT raw_data FROM cinema_legacy_invoices WHERE id=?', [$invoice['id']])['raw_data'], '<script>old</script>'), 'Không giữ nguyên dữ liệu nguồn.');
history_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_orders')['n'] === 0 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_reservations')['n'] === 0 && R::revenue('month', 2025, null, 'real') === [], 'Lịch sử ảnh hưởng giữ ghế/doanh thu mới.');
$repeat = (new I($map))->import(true);
history_check($repeat['unchanged'] === 8 && $repeat['new_invoices'] === 0 && (int) D::one('SELECT COUNT(*) AS n FROM cinema_history_batches')['n'] === 1, 'Nhập lại bị trùng.');
$unlinkedMap = $map;
$unlinkedMap['source_name'] = 'unlinked-fixture';
$unlinkedMap['tickets']['columns']['invoice_id'] = null;
$unlinked = (new I($unlinkedMap))->import();
history_check($unlinked['unlinked_tickets'] === 4 && $unlinked['linked_tickets'] === 0, 'Tự suy đoán quan hệ hóa đơn qua MaKH.');
D::execute("INSERT INTO HistoryInvoiceFixture VALUES ('HD005','KH001','2025-01-01',10000,'old')");
D::execute("UPDATE HistoryInvoiceFixture SET TongTien=1 WHERE MaHD='HD001'");
rejects(fn () => (new I($map))->import(true), 'Nguồn đã đổi vẫn ghi đè lịch sử.');
$checks++;
history_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_legacy_invoices')['n'] === 4, 'Lỗi không rollback.');
D::execute("UPDATE HistoryInvoiceFixture SET TongTien=220000 WHERE MaHD='HD001'");
D::execute("UPDATE HistoryTicketFixture SET MaKH='KH002' WHERE MaVe='V001'");
$fresh = $map;
$fresh['source_name'] = 'mismatch-fixture';
rejects(fn () => (new I($fresh))->import(true), 'Vé khác khách của hóa đơn được ghép.');
$checks++;
history_check((int) D::one('SELECT COUNT(*) AS n FROM cinema_legacy_invoices')['n'] === 4, 'Ghép sai không rollback hóa đơn mới.');
D::execute("UPDATE HistoryTicketFixture SET MaKH='KH001',MaHD='MISSING' WHERE MaVe='V001'");
rejects(fn () => (new I($fresh))->import(), 'Khóa hóa đơn thiếu bị bỏ qua.');
$checks++;
D::execute("UPDATE HistoryTicketFixture SET MaHD='HD001' WHERE MaVe='V001'");
$bad = $map;
$bad['invoices']['columns']['id'] = 'MaKH';
rejects(fn () => (new I($bad))->import(), 'Khóa nguồn trùng được chấp nhận.');
$checks++;
$bad['invoices']['table'] = 'HistoryInvoiceFixture;DROP TABLE cinema_users';
rejects(fn () => (new I($bad))->import(), 'Tên bảng SQL injection được chấp nhận.');
$checks++;
$bad = $map;
$bad['tickets']['columns']['invoice_id'] = null;
rejects(fn () => (new I($bad))->import(true), 'Mapping đã nhập bị đổi lặng lẽ.');
$checks++;
D::execute("UPDATE HistoryInvoiceFixture SET TongTien=12.34 WHERE MaHD='HD005'");
rejects(fn () => (new I($fresh))->import(), 'Số tiền lẻ bị tự làm tròn.');
$checks++;
echo "$checks kiểm thử lịch sử, 0 thất bại.\n";
