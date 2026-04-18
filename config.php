<?php
define('RETROSHOW_DB_PATH', __DIR__ . DIRECTORY_SEPARATOR . 'retroshow.sqlite');
define('RETROSHOW_DB_DSN', 'sqlite:' . RETROSHOW_DB_PATH);
define('RETROSHOW_ADMINS', serialize([
    'BitByByte'
]));

define('RETROSHOW_PROCESSING_SERVER', 'http://127.0.0.1:8090');

// -------------------------------------------------------------------------------------------------
// Настройка Mailer.
// -------------------------------------------------------------------------------------------------

define('SMTP_HOST', 'w10.host');
define('SMTP_PORT', 2525);
define('SMTP_SECURE', 'none'); // 'ssl', 'tls', 'none'
define('SMTP_USERNAME', 'bitbybyte@w10.site');
define('SMTP_PASSWORD', 'dfh3HKVdkr4ef3PEPf');
define('SMTP_FROM_EMAIL', 'bitbybyte@w10.site');
define('SMTP_FROM_NAME', 'RetroShow');

