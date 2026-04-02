<?php
include("init.php");

@ini_set('display_errors', 'Off');
@ini_set('display_startup_errors', 'Off');

function get_requested_public_id(): string {
    $raw = '';
    if (isset($_GET['public_id'])) $raw = (string)$_GET['public_id'];
    elseif (isset($_GET['id'])) $raw = (string)$_GET['id'];
    elseif (isset($_GET['video_id'])) $raw = (string)$_GET['video_id'];
    if ($raw === '' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $raw)) return '';
    return $raw;
}

function forbid_403(): void {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

try {
    $publicId = get_requested_public_id();
    if ($publicId === '') {
        forbid_403();
    }

    $st = $db->prepare('SELECT file FROM videos WHERE public_id = ? LIMIT 1');
    $st->execute([$publicId]);
    $video = $st->fetch(PDO::FETCH_ASSOC);
    if (!$video) forbid_403();

    $filePath = (string)($video['file'] ?? '');
    if ($filePath === '') forbid_403();

    if (isset($_GET['format']) && $_GET['format'] === 'webm') {
        $webmPath = preg_replace('/\.[^.]+$/', '.webm', $filePath);
        if ($webmPath && file_exists(__DIR__ . '/' . ltrim($webmPath, '/'))) {
            $filePath = $webmPath;
        }
    }

    if (preg_match('~^https?://~i', $filePath)) {
        header('Location: ' . $filePath, true, 302);
        exit;
    }

    $relative = '/' . ltrim($filePath, '/');
    header('Location: ' . $relative, true, 302);
    exit;
} catch (Exception $e) {
    forbid_403();
}
