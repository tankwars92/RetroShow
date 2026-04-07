<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

logout_user();
header('Location: index.php', true, 302);
exit;
