<?php
include("init.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$rawId = '';
if (isset($_GET['public_id'])) {
    $rawId = (string)$_GET['public_id'];
} elseif (isset($_GET['video_id'])) {
    $rawId = (string)$_GET['video_id'];
}
if ($rawId === '' || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $rawId)) {
    die();
}

try {
    $stmt = $db->prepare("SELECT id, file, private FROM videos WHERE public_id = ? LIMIT 1");
    $stmt->execute([$rawId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$video || !isset($video['file'])) {
        die();
    }
    if (!empty($video['private'])) {
        die();
    }

    $filePath = $video['file'];

    if (isset($_GET['format']) && $_GET['format'] === 'webm') {
        $webmPath = preg_replace('/\.[^.]+$/', '.webm', $filePath);
        if ($webmPath && file_exists(__DIR__ . '/' . $webmPath)) {
            $filePath = $webmPath;
        }
    }

    if (preg_match('~^https?://~i', $filePath)) {
        header('Location: ' . $filePath, true, 302);
        exit;
    }

    $relative = '/' . ltrim($filePath, '/');
    
    header('Location: ' . $relative . '?v=' . urlencode($rawId), true, 302);
    exit;
} catch (Exception $e) {
    die();
}
?>


