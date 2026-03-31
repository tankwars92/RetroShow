<?php
include("init.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$rawId = '';
if (isset($_GET['video_id'])) {
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

    $fullPath = __DIR__ . '/' . ltrim($filePath, '/');
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        die();
    }

    $size = filesize($fullPath);
    $start = 0;
    $end = $size - 1;
    $status = 200;

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') $start = (int)$m[1];
        if ($m[2] !== '') $end = (int)$m[2];
        if ($start > $end || $start >= $size) {
            header('HTTP/1.1 416 Range Not Satisfiable');
            header('Content-Range: bytes */' . $size);
            exit;
        }
        if ($end >= $size) $end = $size - 1;
        $status = 206;
    }

    $length = $end - $start + 1;
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . $length);
    if ($status === 206) {
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    } else {
        header('HTTP/1.1 200 OK');
    }

    $fp = fopen($fullPath, 'rb');
    if ($fp === false) {
        die();
    }
    fseek($fp, $start);
    $remaining = $length;
    while (!feof($fp) && $remaining > 0) {
        $read = ($remaining > 8192) ? 8192 : $remaining;
        $buf = fread($fp, $read);
        if ($buf === false) break;
        echo $buf;
        $remaining -= strlen($buf);
        if (function_exists('ob_flush')) @ob_flush();
        flush();
    }
    fclose($fp);
    exit;
} catch (Exception $e) {
    die();
}
?>


