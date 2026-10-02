<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\HistoryService;
use App\Support\Auth;

$user = Auth::requireUser();
$kind = ($_GET['kind'] ?? '') === 'tickets' ? 'tickets' : 'invoices';
$record = HistoryService::detail($kind, request_id(), (int) $user['id']);
if (!$record) {
    fail_not_found();
}
view('layouts/header', ['title' => 'Lịch sử ' . $record['source_key']]);
view('account/history-detail', ['record' => $record, 'kind' => $kind, 'adminPage' => false]);
view('layouts/footer');
