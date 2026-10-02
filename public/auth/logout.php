<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_post();
$role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
unset($_SESSION[$role]);
session_regenerate_id(true);
$_SESSION['csrf'] = bin2hex(random_bytes(32));
redirect($role === 'admin' ? 'admin/login.php' : 'index.php');
