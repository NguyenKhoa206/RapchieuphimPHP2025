<?php

declare(strict_types=1);
// Convenience entry for XAMPP. Production DocumentRoot must be public/.
$prefix = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/.');
header('Location: ' . $prefix . '/public/', true, 302);
exit;
