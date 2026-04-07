<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const APP_ROOT = __DIR__ . '/..';
const APP_DATA_DIR = APP_ROOT . '/data';
const APP_DB_PATH = APP_DATA_DIR . '/twitbirth.sqlite';

function esc_html(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_autolinkable_http_url(string $url): bool
{
    if ($url === '' || strlen($url) > 2048) {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower((string) $parts['scheme']);

    return ($scheme === 'http' || $scheme === 'https') && trim((string) $parts['host']) !== '';
}

function format_status_body_html(string $body): string
{
    $pattern = '#https?://[^\s<>"\'\]\)]+#iu';
    $offset = 0;
    $out = '';
    $lenBody = strlen($body);
    while ($offset < $lenBody && preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE, $offset)) {
        $pos = (int) $m[0][1];
        $match = (string) $m[0][0];
        $out .= esc_html(substr($body, $offset, $pos - $offset));
        $url = rtrim($match, '.,;:!?）」\'"');
        if (is_autolinkable_http_url($url)) {
            $maxDisplay = 42;
            if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                $display = $url;
                if (mb_strlen($display, 'UTF-8') > $maxDisplay) {
                    $display = mb_substr($url, 0, $maxDisplay - 3, 'UTF-8') . '...';
                }
            } else {
                $display = strlen($url) > $maxDisplay ? (substr($url, 0, $maxDisplay - 3) . '...') : $url;
            }
            $out .= '<a href="' . esc_html($url) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html($display) . '</a>';
        } else {
            $out .= esc_html($match);
        }
        $offset = $pos + strlen($match);
    }
    $out .= esc_html(substr($body, $offset));

    return $out;
}

function app_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_dir(APP_DATA_DIR)) {
        if (!mkdir(APP_DATA_DIR, 0755, true) && !is_dir(APP_DATA_DIR)) {
            throw new RuntimeException('Cannot create data directory');
        }
    }
    $pdo = new PDO('sqlite:' . APP_DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_schema($pdo);
    return $pdo;
}

function init_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE COLLATE NOCASE,
    display_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    time_zone TEXT,
    bio TEXT,
    location TEXT,
    web TEXT,
    avatar_path TEXT,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    exclude_from_public_timeline INTEGER NOT NULL DEFAULT 0,
    current_status TEXT,
    status_updated_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL);
    ensure_column($pdo, 'users', 'bio', 'TEXT');
    ensure_column($pdo, 'users', 'location', 'TEXT');
    ensure_column($pdo, 'users', 'web', 'TEXT');
    ensure_column($pdo, 'users', 'avatar_path', 'TEXT');
    ensure_column($pdo, 'users', 'is_deleted', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'exclude_from_public_timeline', 'INTEGER DEFAULT 0');
    ensure_column($pdo, 'users', 'current_status', 'TEXT');
    ensure_column($pdo, 'users', 'status_updated_at', 'TEXT');
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS statuses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_statuses_user_created ON statuses (user_id, created_at)');
    migrate_legacy_statuses($pdo);
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS friendships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requester_id INTEGER NOT NULL,
    addressee_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(requester_id, addressee_id)
);
SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_friendships_addressee_status ON friendships (addressee_id, status)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_friendships_requester_status ON friendships (requester_id, status)');
}


function parse_status_utc(string $createdAt): ?DateTimeImmutable
{
    $createdAt = trim($createdAt);
    if ($createdAt === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($createdAt, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return null;
    }
}

function format_status_time(string $createdAt): string
{
    $thenUtc = parse_status_utc($createdAt);
    if ($thenUtc === null) {
        return '';
    }
    $tzLocal = new DateTimeZone(date_default_timezone_get());
    $thenLocal = $thenUtc->setTimezone($tzLocal);
    $nowLocal = new DateTimeImmutable('now', $tzLocal);

    $thenDay = $thenLocal->format('Y-m-d');
    $todayDay = $nowLocal->format('Y-m-d');
    $yesterdayDay = $nowLocal->modify('-1 day')->format('Y-m-d');

    $diff = $nowLocal->getTimestamp() - $thenLocal->getTimestamp();
    if ($diff < 0) {
        return $thenLocal->format('h:i A F j, Y');
    }

    if ($thenDay === $todayDay) {
        if ($diff < 45) {
            return 'less than a minute ago';
        }
        if ($diff < 3600) {
            $m = max(1, (int) floor($diff / 60));

            return 'about ' . $m . ($m === 1 ? ' minute' : ' minutes') . ' ago';
        }
        $h = max(1, (int) floor($diff / 3600));

        return 'about ' . $h . ($h === 1 ? ' hour' : ' hours') . ' ago';
    }
    if ($thenDay === $yesterdayDay) {
        return 'yesterday';
    }
    if ($thenDay < $yesterdayDay) {
        return $thenLocal->format('h:i A F j, Y');
    }

    return $thenLocal->format('h:i A F j, Y');
}

function format_status_permalink_time(string $createdAt): string
{
    $thenUtc = parse_status_utc($createdAt);
    if ($thenUtc === null) {
        return '';
    }
    $tzLocal = new DateTimeZone(date_default_timezone_get());

    return $thenUtc->setTimezone($tzLocal)->format('h:i A F j, Y');
}

function migrate_legacy_statuses(PDO $pdo): void
{
    try {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM statuses')->fetchColumn();
    } catch (PDOException $e) {
        return;
    }
    if ($count > 0) {
        return;
    }
    $q = $pdo->query('SELECT id, current_status, status_updated_at FROM users WHERE current_status IS NOT NULL AND TRIM(current_status) != \'\'');
    if ($q === false) {
        return;
    }
    $ins = $pdo->prepare('INSERT INTO statuses (user_id, body, created_at) VALUES (?,?,?)');
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $when = $r['status_updated_at'] ?? null;
        if ($when === null || $when === '') {
            $when = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        }
        $ins->execute([(int) $r['id'], (string) $r['current_status'], $when]);
    }
}

function statuses_for_user(int $userId, int $limit = 100): array
{
    $limit = max(1, min(5000, $limit));
    $st = app_pdo()->prepare('SELECT id, user_id, body, created_at FROM statuses WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit);
    $st->execute([$userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}

function status_count_for_user(int $userId): int
{
    $st = app_pdo()->prepare('SELECT COUNT(*) FROM statuses WHERE user_id = ?');
    $st->execute([$userId]);

    return (int) $st->fetchColumn();
}

function latest_status_id(int $userId): ?int
{
    $st = app_pdo()->prepare('SELECT id FROM statuses WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return null;
    }

    return (int) $row['id'];
}

function status_by_id(int $statusId): ?array
{
    $st = app_pdo()->prepare(
        'SELECT s.id, s.user_id, s.body, s.created_at, u.username, u.display_name, u.avatar_path
         FROM statuses s INNER JOIN users u ON u.id = s.user_id WHERE s.id = ?'
    );
    $st->execute([$statusId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function public_timeline(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $st = app_pdo()->prepare(
        'SELECT s.id, s.user_id, s.body, s.created_at, u.username, u.display_name, u.avatar_path
         FROM statuses s
         INNER JOIN users u ON u.id = s.user_id
         WHERE COALESCE(u.exclude_from_public_timeline, 0) = 0
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT ' . $limit
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}


function users_top_by_status_count(int $limit = 5): array
{
    $limit = max(1, min(50, $limit));
    $st = app_pdo()->prepare(
        'SELECT u.id, u.username, u.display_name, u.avatar_path, COUNT(s.id) AS status_count
         FROM users u
         INNER JOIN statuses s ON s.user_id = u.id
         WHERE COALESCE(u.exclude_from_public_timeline, 0) = 0
         GROUP BY u.id
         ORDER BY COUNT(s.id) DESC, u.username COLLATE NOCASE
         LIMIT ' . $limit
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}

function ensure_column(PDO $pdo, string $table, string $column, string $sqlType): void
{
    if ($table !== 'users') {
        return;
    }
    $st = $pdo->query('PRAGMA table_info(users)');
    if ($st === false) {
        return;
    }
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE users ADD COLUMN ' . $column . ' ' . $sqlType);
}

const AVATAR_DIR = APP_ROOT . '/uploads/avatars';

function avatar_public_url(?string $avatarPath): string
{
    if ($avatarPath !== null && $avatarPath !== '' && is_file(APP_ROOT . '/' . str_replace('\\', '/', $avatarPath))) {
        return $avatarPath;
    }
    return 'images/no_avatar.gif';
}

function save_avatar_after_register(int $userId, ?array $fileUser): ?string
{
    if ($fileUser === null || empty($fileUser['tmp_name'])) {
        return null;
    }
    $err = (int) ($fileUser['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($fileUser['tmp_name'])) {
        return null;
    }
    $maxBytes = 3 * 1024 * 1024;
    if (($fileUser['size'] ?? 0) > $maxBytes) {
        return null;
    }
    $info = @getimagesize($fileUser['tmp_name']);
    if ($info === false) {
        return null;
    }
    $mime = $info['mime'] ?? '';
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        return null;
    }
    $ext = $extMap[$mime];
    if (!is_dir(AVATAR_DIR)) {
        if (!mkdir(AVATAR_DIR, 0755, true) && !is_dir(AVATAR_DIR)) {
            return null;
        }
    }
    $base = 'uploads/avatars/' . $userId . '.' . $ext;
    $full = APP_ROOT . '/' . $base;
    if (!move_uploaded_file($fileUser['tmp_name'], $full)) {
        return null;
    }
    return $base;
}

function user_clear_avatar_files(int $userId): void
{
    $pattern = AVATAR_DIR . DIRECTORY_SEPARATOR . $userId . '.*';
    foreach (glob($pattern) ?: [] as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function save_avatar_for_existing_user(int $userId, ?array $fileUser): array
{
    if ($fileUser === null || empty($fileUser['tmp_name'])) {
        return ['ok' => true, 'path' => null, 'error' => null];
    }
    $err = (int) ($fileUser['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null, 'error' => null];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($fileUser['tmp_name'])) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not upload picture.'];
    }
    $maxBytes = 3 * 1024 * 1024;
    if (($fileUser['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'path' => null, 'error' => 'File is too large.'];
    }
    $info = @getimagesize($fileUser['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'path' => null, 'error' => 'Picture must be JPG, PNG, GIF, or WebP.'];
    }
    $mime = $info['mime'] ?? '';
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($extMap[$mime])) {
        return ['ok' => false, 'path' => null, 'error' => 'Picture must be JPG, PNG, GIF, or WebP.'];
    }
    $ext = $extMap[$mime];
    if (!is_dir(AVATAR_DIR)) {
        if (!mkdir(AVATAR_DIR, 0755, true) && !is_dir(AVATAR_DIR)) {
            return ['ok' => false, 'path' => null, 'error' => 'Could not save picture.'];
        }
    }
    user_clear_avatar_files($userId);
    $base = 'uploads/avatars/' . $userId . '.' . $ext;
    $full = APP_ROOT . '/' . $base;
    if (!move_uploaded_file($fileUser['tmp_name'], $full)) {
        return ['ok' => false, 'path' => null, 'error' => 'Could not save picture.'];
    }

    return ['ok' => true, 'path' => $base, 'error' => null];
}

function friend_incoming_pending_count(int $userId): int
{
    $st = app_pdo()->prepare('SELECT COUNT(*) FROM friendships WHERE addressee_id = ? AND status = ?');
    $st->execute([$userId, 'pending']);

    return (int) $st->fetchColumn();
}

function friend_accepted_count(int $userId): int
{
    $st = app_pdo()->prepare('SELECT COUNT(*) FROM friendships WHERE status = ? AND (requester_id = ? OR addressee_id = ?)');
    $st->execute(['accepted', $userId, $userId]);

    return (int) $st->fetchColumn();
}

function follower_pending_count(int $profileUserId): int
{
    return friend_incoming_pending_count($profileUserId);
}

function friendship_accepted_between(PDO $pdo, int $a, int $b): bool
{
    if ($a === $b) {
        return false;
    }
    $st = $pdo->prepare(
        'SELECT 1 FROM friendships WHERE status = ? AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))'
    );
    $st->execute(['accepted', $a, $b, $b, $a]);

    return (bool) $st->fetchColumn();
}

function friend_relation(int $viewerId, int $profileUserId): string
{
    if ($viewerId === $profileUserId) {
        return 'self';
    }
    $pdo = app_pdo();
    if (friendship_accepted_between($pdo, $viewerId, $profileUserId)) {
        return 'friends';
    }
    $st = $pdo->prepare('SELECT 1 FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = ?');
    $st->execute([$viewerId, $profileUserId, 'pending']);
    if ($st->fetchColumn()) {
        return 'pending_out';
    }
    $st->execute([$profileUserId, $viewerId, 'pending']);
    if ($st->fetchColumn()) {
        return 'pending_in';
    }

    return 'none';
}

function friend_request_send(int $fromId, int $toId): string
{
    if ($fromId === $toId) {
        return 'self';
    }
    $pdo = app_pdo();
    if (friendship_accepted_between($pdo, $fromId, $toId)) {
        return 'friends';
    }
    $stOut = $pdo->prepare('SELECT id FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = ?');
    $stOut->execute([$fromId, $toId, 'pending']);
    if ($stOut->fetch(PDO::FETCH_ASSOC)) {
        return 'pending_out';
    }
    $stIn = $pdo->prepare('SELECT id FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = ?');
    $stIn->execute([$toId, $fromId, 'pending']);
    $row = $stIn->fetch(PDO::FETCH_ASSOC);
    if ($row !== false) {
        $pdo->prepare('UPDATE friendships SET status = ? WHERE id = ?')->execute(['accepted', (int) $row['id']]);

        return 'accepted_reciprocal';
    }
    $pdo->prepare('INSERT INTO friendships (requester_id, addressee_id, status) VALUES (?,?,?)')->execute([$fromId, $toId, 'pending']);

    return 'sent';
}

function friend_accept(int $friendshipId, int $addresseeId): bool
{
    $pdo = app_pdo();
    $st = $pdo->prepare('UPDATE friendships SET status = ? WHERE id = ? AND addressee_id = ? AND status = ?');
    $st->execute(['accepted', $friendshipId, $addresseeId, 'pending']);

    return $st->rowCount() > 0;
}

function friend_decline(int $friendshipId, int $addresseeId): bool
{
    $pdo = app_pdo();
    $st = $pdo->prepare('DELETE FROM friendships WHERE id = ? AND addressee_id = ? AND status = ?');
    $st->execute([$friendshipId, $addresseeId, 'pending']);

    return $st->rowCount() > 0;
}

function friend_unfriend(int $userId, int $otherUserId): bool
{
    if ($userId === $otherUserId) {
        return false;
    }
    $pdo = app_pdo();
    $st = $pdo->prepare(
        'DELETE FROM friendships WHERE status = ? AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))'
    );
    $st->execute(['accepted', $userId, $otherUserId, $otherUserId, $userId]);

    return $st->rowCount() > 0;
}

function friend_requests_incoming(int $userId): array
{
    $st = app_pdo()->prepare(
        'SELECT f.id AS friendship_id, u.id, u.username, u.display_name, u.avatar_path
         FROM friendships f
         INNER JOIN users u ON u.id = f.requester_id
         WHERE f.addressee_id = ? AND f.status = ?
         ORDER BY f.created_at DESC'
    );
    $st->execute([$userId, 'pending']);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}

function friends_accepted_list(int $userId): array
{
    $sql = 'SELECT id, username, display_name, avatar_path FROM (
                SELECT u.id, u.username, u.display_name, u.avatar_path FROM friendships f
                INNER JOIN users u ON u.id = f.addressee_id
                WHERE f.requester_id = ? AND f.status = ?
                UNION
                SELECT u.id, u.username, u.display_name, u.avatar_path FROM friendships f
                INNER JOIN users u ON u.id = f.requester_id
                WHERE f.addressee_id = ? AND f.status = ?
            ) ORDER BY display_name COLLATE NOCASE';
    $st = app_pdo()->prepare($sql);
    $st->execute([$userId, 'accepted', $userId, 'accepted']);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}

function statuses_friends_last_hours(int $profileUserId, int $hours, int $limit = 500): array
{
    $hours = max(1, min(168, $hours));
    $limit = max(1, min(2000, $limit));
    $friends = friends_accepted_list($profileUserId);
    $ids = [];
    foreach ($friends as $f) {
        $ids[] = (int) $f['id'];
    }
    if ($ids === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $st = app_pdo()->prepare(
        'SELECT s.id, s.user_id, s.body, s.created_at, u.username, u.display_name, u.avatar_path
         FROM statuses s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.user_id IN (' . $placeholders . ')
         AND datetime(s.created_at) >= datetime(\'now\', \'-' . $hours . ' hours\')
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT ' . $limit
    );
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    return $rows ?: [];
}

function user_by_id(int $id): ?array
{
    $st = app_pdo()->prepare('SELECT id, username, display_name, email, time_zone, bio, location, web, avatar_path, is_deleted, exclude_from_public_timeline, current_status, status_updated_at, created_at FROM users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

function user_by_username(string $username): ?array
{
    $st = app_pdo()->prepare('SELECT id, username, display_name, email, time_zone, bio, location, web, avatar_path, is_deleted, exclude_from_public_timeline, current_status, status_updated_at, created_at FROM users WHERE username = ? COLLATE NOCASE AND is_deleted = 0');
    $st->execute([trim($username)]);
    $row = $st->fetch();
    return $row ?: null;
}

function user_by_username_including_deleted(string $username): ?array
{
    $st = app_pdo()->prepare('SELECT id, username, display_name, email, time_zone, bio, location, web, avatar_path, is_deleted, exclude_from_public_timeline, current_status, status_updated_at, created_at FROM users WHERE username = ? COLLATE NOCASE');
    $st->execute([trim($username)]);
    $row = $st->fetch();
    return $row ?: null;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $row = user_by_id((int) $_SESSION['user_id']);
    if (!$row) {
        return null;
    }
    if (((int) ($row['is_deleted'] ?? 0)) === 1) {
        logout_user();
        return null;
    }
    return $row;
}

function login_user(int $userId): void
{
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function render_sidebar_logged_in(?array $me): void
{
    if ($me === null) {
        return;
    }
    $u = rawurlencode($me['username']);
    $av = avatar_public_url($me['avatar_path'] ?? null);
    echo '<div class="user_sidebar_chrome">';
    echo '<a href="user.php?id=' . esc_html($u) . '">Home</a> | <a href="user.php?id=' . esc_html($u) . '">Your Profile</a> | <a href="public_timeline.php">Public Timeline</a> | <a href="settings.php">Settings</a> | <a href="friends.php">Friends</a> | <a href="logout.php">Sign out</a>';
    echo '</div>';
}

function render_default_site_logo_html(): string
{
    return '<b><font size="3" id="header" class="site_heading"><a href="index.php" title="Twitbirth: home" accesskey="1"><img alt="Twitbirth.com" height="49" src="images/Twitbirth.png" width="210" /></a></font></b>';
}

function render_user_top_menu_bar(?array $me): void
{
    if ($me === null) {
        return;
    }
    echo '<div id="user_menu_top" class="user_top_menu_bar">';
    render_sidebar_logged_in($me);
    echo '</div>';
}

function render_site_header_top_row(?array $me, string $logoHtml = ''): void
{
    if ($logoHtml === '') {
        $logoHtml = render_default_site_logo_html();
    }
    echo '<table class="site_top_row" cellspacing="0" cellpadding="0" border="0" width="100%"><tr>';
    echo '<td class="site_top_logo_cell" valign="middle" align="left">' . $logoHtml . '</td>';
    if ($me !== null) {
        echo '<td class="site_top_user_cell" valign="middle" align="right">';
        render_user_top_menu_bar($me);
        echo '</td>';
    }
    echo '</tr></table>';
}

function render_sidebar_friend_requests_notice(?array $me): void
{
    if ($me === null) {
        return;
    }
    $n = friend_incoming_pending_count((int) $me['id']);
    if ($n <= 0) {
        return;
    }
    $label = $n === 1 ? '1 new friend request!' : ($n . ' new friend requests!');
    echo '<ul class="featured"><li><b><a href="friends.php?view=requests">' . esc_html($label) . '</a></b></li></ul>';
}

function render_site_footer(): void
{
    $current = $_GET['p'] ?? '';

    function menu_item($page, $label, $current)
    {
        if ($current === $page) {
            return "<li><span>$label</span></li> ";
        } else {
            return "<li><a href=\"plain.php?p=$page\">$label</a></li> ";
        }
    }

    echo '<div id="footer">
    <b><font size="3" class="footer_heading">Footer</font></b>
    <ul>
        <li class="first">© 2026 BitByByte</li> '
        . menu_item('about', 'About Us', $current) .
        menu_item('contact', 'Contact', $current) .
    '</ul>

<br>
<center>
<script src="//downgrade-net.ru/services/ring/ring.php"></script>&nbsp;
<img src="//downgrade-net.ru/services/counter/index.php?id=28" alt="Downgrade Counter" border="0"> 
</center>
</div>';
}