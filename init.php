<?php
require_once __DIR__ . '/config.php';

session_start();
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
    user TEXT,
    text TEXT,
    time INTEGER,
    FOREIGN KEY (video_id) REFERENCES videos(id),
    FOREIGN KEY (user) REFERENCES users(login)
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
    'player_type' => 'TEXT DEFAULT "auto"'
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