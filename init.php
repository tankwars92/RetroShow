<?php
session_start();
try {
    $db = new PDO('sqlite:retroshow.sqlite');
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
