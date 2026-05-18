<?php
define('RETROSHOW_DB_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'retroshow.sqlite');
define('RETROSHOW_DB_DSN', 'sqlite:' . RETROSHOW_DB_PATH);
define('RETROSHOW_ADMINS', serialize([
    'ADMIN'
]));

define('RETROSHOW_PROCESSING_SERVER', 'http://127.0.0.1:8090');

// -------------------------------------------------------------------------------------------------
// Настройка Mailer.
// -------------------------------------------------------------------------------------------------

define('SMTP_HOST', 'example.com');
define('SMTP_PORT', 25);
define('SMTP_SECURE', 'none'); // 'ssl', 'tls', 'none'
define('SMTP_USERNAME', 'domain@example.com');
define('SMTP_PASSWORD', 'password');
define('SMTP_FROM_EMAIL', 'domain@example.com');
define('SMTP_FROM_NAME', 'RetroShow');

