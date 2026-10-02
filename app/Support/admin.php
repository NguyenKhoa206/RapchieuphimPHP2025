<?php

declare(strict_types=1);
$admin = App\Support\Auth::requireAdmin();

function admin_action(callable $action, string $path, array $query = []): never
{
    require_post();
    try {
        $action();
        flash('Đã lưu thay đổi.');
    } catch (DomainException $error) {
        flash($error->getMessage(), 'error');
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') {
            throw $error;
        } flash('Dữ liệu bị trùng hoặc đang được sử dụng.', 'error');
    }
    redirect($path, $query);
}

function admin_header(string $title): void
{
    view('layouts/header', ['title' => $title, 'adminPage' => true]);
}
function admin_page(int $count): array
{
    $page = max(1, min((int)($_GET['page'] ?? 1), max(1, (int)ceil($count / 20))));
    return [$page, ($page - 1) * 20];
}
