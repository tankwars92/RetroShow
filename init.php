<?php
require_once __DIR__ . '/config.php';

function processing_settings_read(): array {
    global $db;
    $defaults = [
        'enabled' => true,
        'url' => rtrim((string)RETROSHOW_PROCESSING_SERVER, '/'),
    ];
    if (!isset($db) || !($db instanceof PDO)) {
        return $defaults;
    }
    try {
        $st = $db->prepare('SELECT value FROM meta WHERE key = ? LIMIT 1');
        $st->execute(['processing_enabled']);
        $en = $st->fetchColumn();
        $st->execute(['processing_server_url']);
        $url = $st->fetchColumn();
        $out = $defaults;
        if ($en !== false && $en !== null && $en !== '') {
            $out['enabled'] = ($en === '1' || $en === 1);
        }
        if (is_string($url) && trim($url) !== '') {
            $out['url'] = rtrim(trim($url), '/');
        }
        return $out;
    } catch (Exception $e) {
        return $defaults;
    }
}

function processing_enabled(): bool {
    return processing_settings_read()['enabled'];
}

function processing_base_url(): string {
    $u = processing_settings_read()['url'];
    return $u !== '' ? $u : rtrim((string)RETROSHOW_PROCESSING_SERVER, '/');
}

function processing_queue_url(): string {
    return processing_base_url() . '/queue';
}

function processing_health_url(): string {
    return processing_base_url() . '/health';
}

function processing_health_probe(): array {
    $url = processing_health_url();
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 1.5,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'detail' => ''];
    }
    return ['ok' => true, 'detail' => trim((string)$body)];
}

if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string)$lifetime);
    ini_set('session.cookie_lifetime', (string)$lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function get_client_ip_info() {
    $checks = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($checks as $key) {
        if (empty($_SERVER[$key])) continue;
        $raw = trim((string)$_SERVER[$key]);
        if ($raw === '') continue;
        if ($key === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $raw);
            foreach ($parts as $p) {
                $ip = trim($p);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return ['ip' => $ip, 'source' => $key, 'raw' => $raw];
                }
            }
            continue;
        }
        if (filter_var($raw, FILTER_VALIDATE_IP)) {
            return ['ip' => $raw, 'source' => $key, 'raw' => $raw];
        }
    }
    return ['ip' => '0.0.0.0', 'source' => 'unknown', 'raw' => ''];
}
function get_client_ip_candidates() {
    $vals = [];
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (empty($_SERVER[$k])) continue;
        $raw = trim((string)$_SERVER[$k]);
        if ($raw === '') continue;
        if ($k === 'HTTP_X_FORWARDED_FOR') {
            $parts = explode(',', $raw);
            foreach ($parts as $p) {
                $ip = trim($p);
                if (filter_var($ip, FILTER_VALIDATE_IP)) $vals[$ip] = true;
            }
        } else {
            if (filter_var($raw, FILTER_VALIDATE_IP)) $vals[$raw] = true;
        }
    }
    return array_keys($vals);
}

function logs_dir() {
    return __DIR__ . '/logs';
}
function get_modlog_path() {
    return __DIR__ . '/log.txt';
}
function log_event($event, array $extra = []) {
    $ip = get_client_ip_info();
    $payload = array_merge([
        'time' => time(),
        'event' => (string)$event,
        'user' => isset($_SESSION['user']) ? (string)$_SESSION['user'] : '',
        'ip' => (string)$ip['ip'],
        'ip_source' => (string)$ip['source'],
        'ip_raw' => (string)$ip['raw'],
        'ua' => (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
        'referer' => (string)($_SERVER['HTTP_REFERER'] ?? ''),
    ], $extra);
    $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line)) return;
    @file_put_contents(get_modlog_path(), $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function is_valid_video_public_id($s) {
    $s = (string)$s;
    if ($s === '' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $s)) {
        return false;
    }
    if (ctype_digit($s)) {
        return false;
    }
    return true;
}

function video_uploads_file_base($video_id, $public_id = '') {
    $id = (int)$video_id;
    if ($id <= 0) {
        return '0';
    }
    $pid = trim((string)$public_id);
    if ($pid !== '' && is_valid_video_public_id($pid)) {
        return $id . '_' . $pid;
    }
    return (string)$id;
}

function video_public_id_from_get() {
    foreach (['id', 'public_id', 'video_id'] as $key) {
        if (!isset($_GET[$key])) {
            continue;
        }
        $v = trim((string)$_GET[$key]);
        if (is_valid_video_public_id($v)) {
            return $v;
        }
    }
    return '';
}

function is_ip_banned($ip) {
    global $db;
    if (!$ip || !isset($db)) return false;
    try {
        $st = $db->prepare('SELECT 1 FROM ip_bans WHERE ip = ? LIMIT 1');
        $st->execute([$ip]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) {
        return false;
    }
}
try {
    $db = new PDO(RETROSHOW_DB_DSN);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

function sqlite_configure(PDO $db): void {
    try {
        $db->exec('PRAGMA busy_timeout = 10000');
        $db->exec('PRAGMA foreign_keys = ON');
        $db->exec('PRAGMA temp_store = MEMORY');
        $db->exec('PRAGMA cache_size = -64000');
        try {
            $db->exec('PRAGMA journal_mode = WAL');
        } catch (Exception $e) {
        }
        $db->exec('PRAGMA synchronous = NORMAL');
        try {
            $db->exec('PRAGMA mmap_size = 67108864');
        } catch (Exception $e) {
        }
    } catch (Exception $e) {
    }
}

sqlite_configure($db);

$db->exec("CREATE TABLE IF NOT EXISTS ip_bans (
    ip TEXT PRIMARY KEY,
    created_at INTEGER NOT NULL,
    created_by TEXT
)");

$banned_ip = '';
$candidates = get_client_ip_candidates();
foreach ($candidates as $cand) {
    if (is_ip_banned($cand)) {
        $banned_ip = $cand;
        break;
    }
}
if ($banned_ip !== '') {
    log_event('blocked_ip', ['matched_banned_ip' => $banned_ip]);
    header('HTTP/1.1 403 Forbidden');
    echo '
    <html>
    <head>
    <meta charset="UTF-8">
    <title>Сайт для вас недоступен.</title>
    </head>
    <body>
    <center>
    <b><font size="6">Сайт для вас недоступен.</font></b>
    <br>
    <p>Ваш IP-адрес находится в черном списке. Пожалуйста, свяжитесь с администрацией сайта.</p>
    </center>
    </body>
    </html>
    ';
    exit;
}

$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT UNIQUE,
    pass TEXT,
    email TEXT,
    country TEXT,
    gender TEXT,
    birthday_mon TEXT,
    birthday_day TEXT,
    birthday_yr TEXT,
    name TEXT,
    last_n TEXT,
    relationship TEXT,
    about_me TEXT,
    website TEXT,
    profile_icon TEXT DEFAULT '0',
    profile_icon_custom TEXT,
    profile_comm TEXT DEFAULT '1',
    profile_bull TEXT DEFAULT '1',
    player_type TEXT DEFAULT 'auto',
    hometown TEXT,
    city TEXT,
    signup_time INTEGER,
    last_login INTEGER
)");

$db->exec("CREATE TABLE IF NOT EXISTS channel_moderation (
    user TEXT PRIMARY KEY,
    shadow_banned INTEGER NOT NULL DEFAULT 0,
    shadow_banned_at INTEGER,
    shadow_banned_by TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS video_promotions (
    video_id INTEGER PRIMARY KEY,
    promoted_at INTEGER NOT NULL,
    promoted_by TEXT
)");

function is_user_shadow_banned($username) {
    global $db;
    $u = trim((string)$username);
    if ($u === '') return false;
    try {
        $st = $db->prepare('SELECT shadow_banned FROM channel_moderation WHERE user = ? LIMIT 1');
        $st->execute([$u]);
        return (int)$st->fetchColumn() === 1;
    } catch (Exception $e) {
        return false;
    }
}

function channel_moderation_remove_user($login) {
    global $db;
    $login = trim((string)$login);
    if ($login === '') {
        return;
    }
    try {
        $db->prepare('DELETE FROM channel_moderation WHERE user = ?')->execute([$login]);
    } catch (Exception $e) {
    }
}

function visible_video_sql_condition($videos_alias = 'videos', $user_col = 'user') {
    $va = trim((string)$videos_alias);
    $uc = trim((string)$user_col);
    if ($va === '') $va = 'videos';
    if ($uc === '') $uc = 'user';
    return "NOT EXISTS (SELECT 1 FROM channel_moderation cm WHERE cm.user = {$va}.{$uc} AND cm.shadow_banned = 1)";
}

function user_header_logo_src(PDO $db, $username) {
    $u = trim((string)$username);
    if ($u === '') {
        return 'img/logo_sm.gif';
    }
    try {
        $st = $db->prepare('SELECT header_logo FROM users WHERE login = ? LIMIT 1');
        $st->execute([$u]);
        $v = trim((string)$st->fetchColumn());
        if ($v === 'youtube') {
            return 'img/logo_sm_YT.gif';
        }
    } catch (Exception $e) {
    }
    return 'img/logo_sm.gif';
}

function force_logout_if_user_missing(PDO $db): void {
    if (empty($_SESSION['user'])) {
        return;
    }
    $login = (string)$_SESSION['user'];
    if ($login === '') {
        return;
    }
    $exists = false;
    try {
        $st = $db->prepare('SELECT 1 FROM users WHERE login = ? LIMIT 1');
        $st->execute([$login]);
        $exists = (bool)$st->fetchColumn();
    } catch (Exception $e) {
        return;
    }
    if ($exists) {
        return;
    }

    $_SESSION = [];
    if (session_id() !== '') {
        @session_destroy();
    }
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
    }
}

force_logout_if_user_missing($db);


$db->exec("CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT UNIQUE,
    title TEXT,
    description TEXT,
    file TEXT,
    preview TEXT,
    user TEXT,
    private INTEGER DEFAULT 0,
    tags TEXT,
    time TEXT,
    views INTEGER DEFAULT 0,
    FOREIGN KEY (user) REFERENCES users(login)
)");


$db->exec("CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    video_id INTEGER,
    parent_id INTEGER,
    user TEXT,
    text TEXT,
    time INTEGER,
    FOREIGN KEY (video_id) REFERENCES videos(id),
    FOREIGN KEY (user) REFERENCES users(login)
)");

try {
    $colsC = $db->query("PRAGMA table_info(comments)")->fetchAll(PDO::FETCH_ASSOC);
    $existingC = array_column($colsC, 'name');
    if (!in_array('parent_id', $existingC, true)) {
        $db->exec("ALTER TABLE comments ADD COLUMN parent_id INTEGER");
    }
    if (!in_array('reference_video_id', $existingC, true)) {
        $db->exec("ALTER TABLE comments ADD COLUMN reference_video_id INTEGER");
    }
} catch (Exception $e) {
}

$db->exec("CREATE TABLE IF NOT EXISTS profile_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    profile_user TEXT NOT NULL,
    user TEXT NOT NULL,
    text TEXT NOT NULL,
    time INTEGER NOT NULL
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_favourites (
    user TEXT NOT NULL,
    video_id INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (user, video_id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_friends (
    user TEXT NOT NULL,
    friend TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    PRIMARY KEY (user, friend)
)");

$db->exec("CREATE TABLE IF NOT EXISTS meta (
    key TEXT PRIMARY KEY,
    value TEXT
)");

try {
    $has_en = $db->query("SELECT 1 FROM meta WHERE key = 'processing_enabled' LIMIT 1")->fetchColumn();
    $has_url = $db->query("SELECT 1 FROM meta WHERE key = 'processing_server_url' LIMIT 1")->fetchColumn();
    if ($has_en === false || $has_url === false) {
        $en = '1';
        $url = rtrim((string)RETROSHOW_PROCESSING_SERVER, '/');
        $jsonPath = __DIR__ . DIRECTORY_SEPARATOR . 'processing_settings.json';
        if (is_file($jsonPath)) {
            $raw = @file_get_contents($jsonPath);
            $j = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($j)) {
                $en = !empty($j['enabled']) ? '1' : '0';
                if (!empty($j['url']) && is_string($j['url'])) {
                    $url = rtrim(trim($j['url']), '/');
                }
            }
        }
        $ins = $db->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
        $ins->execute(['processing_enabled', $en]);
        $ins->execute(['processing_server_url', $url]);
    }
} catch (Exception $e) {
}

$db->exec("CREATE TABLE IF NOT EXISTS video_processing_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL,
    user TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    tags TEXT NOT NULL,
    broadcast TEXT NOT NULL DEFAULT 'public',
    source_file TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    started_at INTEGER,
    finished_at INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',
    attempts INTEGER NOT NULL DEFAULT 0,
    last_error TEXT
)");
try { $db->exec("CREATE INDEX IF NOT EXISTS idx_video_processing_queue_status ON video_processing_queue (status, created_at)"); } catch (Exception $e) {}
try { $db->exec("CREATE INDEX IF NOT EXISTS idx_video_processing_queue_public_id ON video_processing_queue (public_id)"); } catch (Exception $e) {}

$db->exec("CREATE TABLE IF NOT EXISTS ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    video_id INTEGER NOT NULL,
    user TEXT,
    ip TEXT,
    rating INTEGER NOT NULL,
    rated_at INTEGER NOT NULL,
    UNIQUE(video_id, user, ip),
    FOREIGN KEY (video_id) REFERENCES videos(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS user_stats (
    user TEXT PRIMARY KEY,
    profile_viewed INTEGER DEFAULT 0,
    videos_watched INTEGER DEFAULT 0
)");

$db->exec("CREATE TABLE IF NOT EXISTS video_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    video_id INTEGER NOT NULL,
    user TEXT,
    ip TEXT,
    viewed_at INTEGER NOT NULL,
    FOREIGN KEY (video_id) REFERENCES videos(id)
)");
try { $db->exec("CREATE INDEX IF NOT EXISTS idx_video_views_viewed_at ON video_views (viewed_at DESC)"); } catch (Exception $e) {}
try { $db->exec("CREATE INDEX IF NOT EXISTS idx_video_views_video_id ON video_views (video_id)"); } catch (Exception $e) {}

function prune_views(PDO $db): void {
    try {
        $row = $db->query("SELECT value FROM meta WHERE key = 'prune_views_at'")->fetchColumn();
        $last = $row !== false && $row !== null ? (int)$row : 0;
        if ($last > 0 && (time() - $last) < 3600) {
            return;
        }
    } catch (Exception $e) {
        return;
    }

    try {
        $cutoff = time() - (90 * 86400);
        $stOld = $db->prepare('DELETE FROM video_views WHERE viewed_at < ?');
        $stOld->execute([$cutoff]);
    } catch (Exception $e) {
    }

    try {
        $max_rows = 300000;
        $batch = 20000;
        $cnt = (int)$db->query('SELECT COUNT(*) FROM video_views')->fetchColumn();
        if ($cnt > $max_rows) {
            $n = min($batch, $cnt - $max_rows);
            if ($n > 0) {
                $db->exec('DELETE FROM video_views WHERE id IN (
                    SELECT id FROM video_views
                    ORDER BY viewed_at ASC, id ASC
                    LIMIT ' . intval($n) . '
                )');
            }
        }
    } catch (Exception $e) {
    }

    try {
        $db->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)')
            ->execute(['prune_views_at', (string)time()]);
    } catch (Exception $e) {
    }
}

prune_views($db);

$cols = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
$existing_cols = array_column($cols, 'name');

$missing_cols = [
    'signup_time' => 'INTEGER',
    'last_login' => 'INTEGER',
    'hometown' => 'TEXT',
    'city' => 'TEXT',
    'relationship' => 'TEXT',
    'about_me' => 'TEXT',
    'website' => 'TEXT',
    'profile_icon' => 'TEXT DEFAULT "0"',
    'profile_icon_custom' => 'TEXT',
    'profile_comm' => 'TEXT DEFAULT "1"',
    'profile_bull' => 'TEXT DEFAULT "1"',
    'player_type' => 'TEXT DEFAULT "auto"',
    'home_block_type' => 'TEXT DEFAULT "recent_added"',
    'header_logo' => 'TEXT DEFAULT "retroshow"',
    'reset_token' => 'TEXT',
    'reset_token_expires' => 'INTEGER'
];

foreach ($missing_cols as $col_name => $col_def) {
    if (!in_array($col_name, $existing_cols)) {
        try {
            $db->exec("ALTER TABLE users ADD COLUMN $col_name $col_def");
        } catch (PDOException $e) {
        }
    }
}

$cols = $db->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_ASSOC);
$existing_cols = array_column($cols, 'name');

$missing_video_cols = [
    'public_id' => 'TEXT',
    'tags' => 'TEXT',
    'private' => 'INTEGER DEFAULT 0',
    'views' => 'INTEGER DEFAULT 0'
];

foreach ($missing_video_cols as $col_name => $col_def) {
    if (!in_array($col_name, $existing_cols)) {
        try {
            $db->exec("ALTER TABLE videos ADD COLUMN $col_name $col_def");
        } catch (PDOException $e) {
        }
    }
}

// -------------------------------------------------------------------------------------------------
// Если у вас есть база данных в старом формате (где комментарии хранятся в файлах), НЕ удаляйте эту функцию! 
// Она перенесёт комментарии и прочее из файлов в базу данных.

function deleteDir($dir) {
    if (!is_dir($dir)) return;

    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            deleteDir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

try {
    $now = time();
    $migrated = $db->query("SELECT value FROM meta WHERE key='migrated_file_storage_to_db'")->fetchColumn();

    if ($migrated !== '1') {

        $friends_dir = __DIR__ . '/friends';
        if (is_dir($friends_dir)) {
            foreach (glob($friends_dir . '/*.txt') as $file) {
                $user = urldecode(basename($file, '.txt'));
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $friend) {
                    $friend = trim($friend);
                    if ($friend === '' || $friend === $user) continue;
                    $db->prepare("INSERT OR IGNORE INTO user_friends (user, friend, created_at) VALUES (?, ?, ?)")
                       ->execute([$user, $friend, $now]);
                }
            }
        }

        $favourites_dir = __DIR__ . '/favourites';
        if (is_dir($favourites_dir)) {
            foreach (glob($favourites_dir . '/*.txt') as $file) {
                $user = urldecode(basename($file, '.txt'));
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ($lines as $vid) {
                    $vid = intval(trim($vid));
                    if ($vid <= 0) continue;
                    $db->prepare("INSERT OR IGNORE INTO user_favourites (user, video_id, created_at) VALUES (?, ?, ?)")
                       ->execute([$user, $vid, $now]);
                }
            }
        }

        $comments_dir = __DIR__ . '/comments';
        if (is_dir($comments_dir)) {

            foreach (glob($comments_dir . '/*.txt') as $file) {
                $base = basename($file, '.txt');
                if (strpos($base, 'profile_') === 0) continue;

                $video_id = intval($base);
                if ($video_id <= 0) continue;

                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                if (!$lines) continue;

                $stmtHas = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                $stmtHas->execute([$video_id]);
                if ((int)$stmtHas->fetchColumn() > 0) continue;

                $db->prepare("DELETE FROM comments WHERE video_id = ?")->execute([$video_id]);

                $map = [];
                $pending = [];

                foreach ($lines as $idx => $line) {
                    $parts = explode('|', $line, 4);
                    if (count($parts) < 3) continue;

                    $t = intval($parts[0]);
                    $u = trim($parts[1]);
                    $text = trim($parts[2]);
                    $parent_idx = $parts[3] ?? '';

                    $db->prepare("INSERT INTO comments (video_id, parent_id, user, text, time) VALUES (?, NULL, ?, ?, ?)")
                       ->execute([$video_id, $u, $text, $t ?: $now]);

                    $cid = (int)$db->lastInsertId();
                    $map[(string)$idx] = $cid;

                    if ($parent_idx !== '') {
                        $pending[(string)$idx] = (string)$parent_idx;
                    }
                }

                foreach ($pending as $child_idx => $parent_idx) {
                    if (!isset($map[$child_idx])) continue;
                    $child_id = $map[$child_idx];
                    $parent_id = $map[$parent_idx] ?? null;

                    if ($parent_id) {
                        $db->prepare("UPDATE comments SET parent_id = ? WHERE id = ?")
                           ->execute([$parent_id, $child_id]);
                    }
                }
            }

            foreach (glob($comments_dir . '/profile_*.txt') as $file) {
                $base = basename($file, '.txt');
                $profile_user = urldecode(substr($base, strlen('profile_')));

                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

                $stmtHas = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
                $stmtHas->execute([$profile_user]);
                if ((int)$stmtHas->fetchColumn() > 0) continue;

                $db->prepare("DELETE FROM profile_comments WHERE profile_user = ?")->execute([$profile_user]);

                foreach ($lines as $line) {
                    $parts = explode('|', $line, 3);
                    if (count($parts) < 3) continue;

                    $t = intval($parts[0]);
                    $u = trim($parts[1]);
                    $text = trim($parts[2]);

                    if ($u === '' || $text === '') continue;

                    $db->prepare("INSERT INTO profile_comments (profile_user, user, text, time) VALUES (?, ?, ?, ?)")
                       ->execute([$profile_user, $u, $text, $t ?: $now]);
                }
            }
        }

        $db->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES ('migrated_file_storage_to_db', '1')")
           ->execute();

        deleteDir(__DIR__ . '/friends');
        deleteDir(__DIR__ . '/favourites');
        deleteDir(__DIR__ . '/comments');
    }

} catch (Exception $e) {
    
}

// -------------------------------------------------------------------------------------------------
// Если у вас есть база данных в старом формате (без поддержки public_id), НЕ удаляйте эту функцию! 
// Она присвоит public_id всем существующим видео.

function init_generate_public_video_id(PDO $db) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
    while (true) {
        $id = '';
        for ($i = 0; $i < 11; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM videos WHERE public_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            return $id;
        }
    }
}

try {
    $stmt = $db->query("SELECT id FROM videos WHERE public_id IS NULL OR public_id = ''");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $pid = init_generate_public_video_id($db);
        $up = $db->prepare("UPDATE videos SET public_id = ? WHERE id = ?");
        $up->execute([$pid, $row['id']]);
    }
} catch (Exception $e) {
}

// -------------------------------------------------------------------------------------------------
// Почта.

$db->exec("CREATE TABLE IF NOT EXISTS mail_inbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    to_user TEXT NOT NULL,
    from_user TEXT NOT NULL,
    topic TEXT NOT NULL DEFAULT '',
    content TEXT NOT NULL,
    sent_at INTEGER NOT NULL,
    seen_at INTEGER,
    kind TEXT NOT NULL,
    comment_id INTEGER,
    video_id INTEGER,
    video_public_id TEXT,
    channel_login TEXT
)");
try {
    $cols = $db->query('PRAGMA table_info(mail_inbox)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($cols, 'name');
    if (!in_array('topic', $names, true)) {
        $db->exec("ALTER TABLE mail_inbox ADD COLUMN topic TEXT NOT NULL DEFAULT ''");
    }
} catch (Exception $e) {
}
$db->exec("CREATE INDEX IF NOT EXISTS idx_mail_inbox_user ON mail_inbox (to_user)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_mail_inbox_unread ON mail_inbox (to_user, seen_at)");

function mail_list_preview(string $s): string {
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($s, 'UTF-8') <= 25) {
            return $s;
        }
        return mb_substr($s, 0, 25, 'UTF-8') . '...';
    }
    if (strlen($s) <= 25) {
        return $s;
    }
    return substr($s, 0, 25) . '...';
}

function count_unread_mail(PDO $db, string $user): int {
    if ($user === '') {
        return 0;
    }
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM mail_inbox WHERE to_user = ? AND seen_at IS NULL');
        $st->execute([$user]);
        return (int) $st->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function mark_mail_seen(PDO $db, int $id, string $user): void {
    try {
        $st = $db->prepare('UPDATE mail_inbox SET seen_at = ? WHERE id = ? AND to_user = ? AND seen_at IS NULL');
        $st->execute([time(), $id, $user]);
    } catch (Exception $e) {
    }
}

function add_mail(
    PDO $db,
    string $to_user,
    string $from_user,
    string $topic,
    string $content,
    string $kind,
    ?int $comment_id = null,
    ?int $video_id = null,
    ?string $video_public_id = null,
    ?string $channel_login = null
): void {
    if ($to_user === '' || $from_user === '' || $to_user === $from_user) {
        return;
    }
    try {
        $st = $db->prepare('INSERT INTO mail_inbox (to_user, from_user, topic, content, sent_at, seen_at, kind, comment_id, video_id, video_public_id, channel_login) VALUES (?,?,?,?,?,NULL,?,?,?,?,?)');
        $st->execute([$to_user, $from_user, $topic, $content, time(), $kind, $comment_id, $video_id, $video_public_id, $channel_login]);
    } catch (Exception $e) {
    }
}

function mail_prev_next(PDO $db, string $user, int $id): array {
    try {
        $st = $db->prepare('SELECT id FROM mail_inbox WHERE to_user = ? ORDER BY sent_at DESC, id DESC');
        $st->execute([$user]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN, 0));
    } catch (Exception $e) {
        return ['prev' => null, 'next' => null];
    }
    $idx = array_search($id, $ids, true);
    if ($idx === false) {
        return ['prev' => null, 'next' => null];
    }
    $prev = $idx > 0 ? $ids[$idx - 1] : null;
    $next = $idx < count($ids) - 1 ? $ids[$idx + 1] : null;
    return ['prev' => $prev, 'next' => $next];
}

// -------------------------------------------------------------------------------------------------