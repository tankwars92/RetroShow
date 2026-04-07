<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
if ($me === null) {
    header('Location: login.php', true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php', true, 302);
    exit;
}

$pdo = app_pdo();
$id = (int) $me['id'];

user_clear_avatar_files($id);

$deletedUsername = 'deleted_' . $id;
$deletedDisplay = 'Deleted Account';
$deletedEmail = 'deleted_' . $id . '@invalid.local';
$newHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

$st = $pdo->prepare(
    'UPDATE users
     SET username = ?,
         display_name = ?,
         email = ?,
         password_hash = ?,
         time_zone = NULL,
         bio = NULL,
         location = NULL,
         web = NULL,
         avatar_path = NULL,
         current_status = NULL,
         status_updated_at = NULL,
         is_deleted = 1
     WHERE id = ?'
);
$st->execute([$deletedUsername, $deletedDisplay, $deletedEmail, $newHash, $id]);

logout_user();
header('Location: index.php', true, 302);
exit;

