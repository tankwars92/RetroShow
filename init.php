<?php
require_once __DIR__ . '/config.php';

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

try {
    $db = new PDO(RETROSHOW_DB_DSN);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
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
            $countExisting = (int)$stmtHas->fetchColumn();
            if ($countExisting > 0) continue;

            $db->prepare("DELETE FROM comments WHERE video_id = ?")->execute([$video_id]);

            $map = [];
            $pending = [];

            foreach ($lines as $idx => $line) {
                $parts = explode('|', $line, 4);
                if (count($parts) < 3) continue;
                $t = intval($parts[0]);
                $u = trim($parts[1]);
                $text = trim($parts[2]);
                $parent_idx = isset($parts[3]) ? trim($parts[3]) : '';

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
                    $db->prepare("UPDATE comments SET parent_id = ? WHERE id = ?")->execute([$parent_id, $child_id]);
                }
            }
        }

        foreach (glob($comments_dir . '/profile_*.txt') as $file) {
            $base = basename($file, '.txt');
            $profile_user = urldecode(substr($base, strlen('profile_')));

            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            $stmtHas = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
            $stmtHas->execute([$profile_user]);
            $countExisting = (int)$stmtHas->fetchColumn();
            if ($countExisting > 0) continue;

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

    $db->prepare("INSERT OR REPLACE INTO meta (key, value) VALUES ('migrated_file_storage_to_db', '1')")->execute();
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