<?php
include("init.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$rawId = isset($_GET['video_id']) ? $_GET['video_id'] : '';
if ($rawId === '' || !ctype_digit((string)$rawId)) {
    die();
}

$videoId = (int)$rawId;

try {
    $stmt = $db->prepare("SELECT id, file, private FROM videos WHERE id = ? LIMIT 1");
    $stmt->execute([$videoId]);
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
        $redirectUrl = $filePath;
    } else {
        $redirectUrl = '/' . ltrim($filePath, '/');
    }

    header('Location: ' . $redirectUrl);
    header('HTTP/1.1 303 See Other');
    exit;
} catch (Exception $e) {
    die();
}
?>


