<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

header('Content-Type: text/plain; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

$me = current_user();
if ($me === null) {
    echo 'You must be signed in to update your status.';
    exit;
}

$status = isset($_POST['status']) ? trim((string) $_POST['status']) : '';
if ($status === '' && isset($_POST['text_content'])) {
    $status = trim((string) $_POST['text_content']);
}
if ($status === '') {
    echo 'Please enter a status.';
    exit;
}
if (strlen($status) > 500) {
    echo 'Status is too long (500 characters maximum).';
    exit;
}

$pdo = app_pdo();

$pdo->prepare('INSERT INTO statuses (user_id, body, created_at) VALUES (?,?,datetime(\'now\'))')->execute([$me['id'], $status]);
$pdo->prepare('UPDATE users SET current_status = ?, status_updated_at = datetime(\'now\') WHERE id = ?')->execute([$status, $me['id']]);

echo 'Your status has been saved.';
