<?php
define('RETROSHOW_DB_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'retroshow.sqlite');
define('RETROSHOW_DB_DSN', 'sqlite:' . RETROSHOW_DB_PATH);
define('RETROSHOW_ADMINS', serialize([
    'ADMIN'
]));

// -------------------------------------------------------------------------------------------------
// Настройка Mailer.
// -------------------------------------------------------------------------------------------------

define('SMTP_HOST', 'server.name');
define('SMTP_PORT', 25);
define('SMTP_SECURE', 'none'); // 'ssl', 'tls', 'none'
define('SMTP_USERNAME', 'username@server.name');
define('SMTP_PASSWORD', 'password');
define('SMTP_FROM_EMAIL', 'username@server.name');
define('SMTP_FROM_NAME', 'RetroShow');

