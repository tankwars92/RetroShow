<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
if ($me === null) {
    header('Location: login.php', true, 302);
    exit;
}

function friend_redirect_target(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return 'friends.php';
    }
    if (preg_match('#^(?:user\.php|friends\.php)(?:\?[^\s#]*)?$#i', $raw)) {
        return $raw;
    }

    return 'friends.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit;
}

$action = (string) ($_POST['action'] ?? '');
$back = friend_redirect_target((string) ($_POST['return_to'] ?? ''));
$myId = (int) $me['id'];

switch ($action) {
    case 'request':
        $toName = trim((string) ($_POST['to_user'] ?? ''));
        $target = user_by_username($toName);
        if ($target === null) {
            break;
        }
        friend_request_send($myId, (int) $target['id']);
        
        $back = 'user.php?id=' . rawurlencode((string) $target['username']);
        $rt = (string) ($_POST['return_to'] ?? '');
        if (strpos($rt, 'tab=friends') !== false) {
            $back .= '&tab=friends';
        }
        if (strpos($rt, 'view_all=1') !== false) {
            $back .= '&view_all=1';
        }
        break;
    case 'accept':
        $fid = (int) ($_POST['friendship_id'] ?? 0);
        if ($fid > 0) {
            friend_accept($fid, $myId);
        }
        break;
    case 'decline':
        $fid = (int) ($_POST['friendship_id'] ?? 0);
        if ($fid > 0) {
            friend_decline($fid, $myId);
        }
        break;
    case 'unfriend':
        $otherName = trim((string) ($_POST['other_user'] ?? ''));
        $other = user_by_username_including_deleted($otherName);
        if ($other !== null) {
            friend_unfriend($myId, (int) $other['id']);
        }
        break;
    default:
        break;
}

header('Location: ' . $back, true, 302);
exit;
