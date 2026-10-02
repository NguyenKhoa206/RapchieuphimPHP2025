<a class="back-link" href="<?= e(url($adminPage ? 'admin/history.php' : 'account/tickets.php', [$adminPage ? 'kind' : 'tab' => $kind])) ?>">← Quay lại lịch sử</a>
<div class="page-heading"><span class="eyebrow">DỮ LIỆU LƯU TRỮ</span><h1><?= $kind === 'invoices' ? 'Hóa đơn' : 'Vé' ?> <?= e($record['source_key']) ?></h1><span class="badge badge-warning">Lịch sử từ hệ thống cũ</span></div>
<div class="alert alert-info">Bản ghi lưu trữ phản ánh dữ liệu cũ. Trạng thái thanh toán chưa được đối soát; bản ghi này không dùng để giữ ghế hoặc vào rạp.</div>
<section class="panel">
    <h2>Thông tin <?= $kind === 'invoices' ? 'hóa đơn' : 'vé' ?></h2>
    <dl class="details">
        <dt>Mã gốc</dt><dd><?= e($record['source_key']) ?></dd>
        <dt>Nguồn</dt><dd><?= e($record['source_name']) ?></dd>
        <dt>Khách hàng</dt><dd><?= e($record['customer_name'] ?? 'Chưa liên kết tài khoản') ?></dd>
        <dt>Số tiền ghi nhận</dt><dd><?= $record['amount'] !== null ? money($record['amount']) : 'Chưa có dữ liệu' ?></dd>
        <dt>Trạng thái gốc</dt><dd><?= e($record['original_status'] ?? 'Chưa có dữ liệu') ?></dd>
        <?php if ($kind === 'invoices'): ?>
            <dt>Ngày lập</dt><dd><?= $record['issued_at'] ? date('H:i · d/m/Y', strtotime($record['issued_at'])) : 'Chưa có dữ liệu' ?></dd>
        <?php else: ?>
            <dt>Hóa đơn gốc</dt><dd><?= e($record['legacy_invoice_key'] ?? 'Chưa xác định quan hệ') ?></dd>
            <dt>Phim</dt><dd><?= e($record['movie_title'] ?? 'Chưa có dữ liệu') ?></dd>
            <dt>Rạp / phòng</dt><dd><?= e($record['cinema_name'] ?? '—') ?> · <?= e($record['room_name'] ?? '—') ?></dd>
            <dt>Ghế</dt><dd><?= e($record['seat_label'] ?? 'Chưa có dữ liệu') ?></dd>
            <dt>Suất chiếu</dt><dd><?= $record['starts_at'] ? date('H:i · d/m/Y', strtotime($record['starts_at'])) : 'Chưa có dữ liệu' ?></dd>
        <?php endif; ?>
    </dl>
    <?php if ($kind === 'invoices'): ?>
    <h3>Vé liên kết trực tiếp</h3>
    <?php if (!$record['tickets']): ?><p class="muted">Chưa có vé được liên kết bằng mã hóa đơn.</p><?php endif; ?>
    <?php foreach ($record['tickets'] as $ticket): ?>
    <div class="history-ticket-row"><span><strong><?= e($ticket['movie_title'] ?? 'Vé ' . $ticket['source_key']) ?></strong><small>Ghế <?= e($ticket['seat_label'] ?? 'chưa xác định') ?> · Mã <?= e($ticket['source_key']) ?></small></span><a class="button button-outline button-small" href="<?= e(url($adminPage ? 'admin/history.php' : 'account/history.php', ['kind' => 'tickets', 'id' => $ticket['id']])) ?>">Chi tiết</a></div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($adminPage): ?><details class="raw-data"><summary>Dữ liệu nguồn để đối chiếu</summary><pre><?= e(json_encode(json_decode($record['raw_data'], true, 64, JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></details><?php endif; ?>
</section>
