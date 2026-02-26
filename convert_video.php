<?php

if (php_sapi_name() !== 'cli') {
    exit;
}

if ($argc < 9) {
    exit;
}

$id         = intval($argv[1]);
$temp_video = $argv[2];
$video_ext  = strtolower($argv[3]);
$title      = $argv[4];
$description= $argv[5];
$tags       = $argv[6];
$broadcast  = $argv[7];
$user       = $argv[8];

chdir(__DIR__);

require_once __DIR__ . '/init.php';

$preview_ext = 'jpg';
$final_video = 'uploads/' . $id . '.mp4';
$preview_file= 'uploads/' . $id . '_preview.' . $preview_ext;

if (!file_exists($temp_video)) {
    exit;
}

function get_video_dimensions_cli($file) {
    $ffprobe = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 " . escapeshellarg($file);
    $output = trim(shell_exec($ffprobe));
    if (preg_match('/(\d+),(\d+)/', $output, $matches)) {
        return [
            'width' => intval($matches[1]),
            'height'=> intval($matches[2])
        ];
    }
    return null;
}

function is_4_3_aspect_ratio_cli($width, $height) {
    $ratio = $width / $height;
    return abs($ratio - 4/3) < 0.1;
}

$output = [];
$return_var = 0;

if ($video_ext != 'mp4') {
    $ffprobe = "ffprobe -v error -select_streams v:0 -show_entries stream=codec_type -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($temp_video);
    $has_video = trim(shell_exec($ffprobe)) === 'video';

    $log_file = 'uploads/ffmpeg_' . $id . '.log';

    $dimensions = get_video_dimensions_cli($temp_video);
    $vf_filter = "format=yuv420p";

    if ($dimensions && is_4_3_aspect_ratio_cli($dimensions['width'], $dimensions['height'])) {
        $vf_filter .= ",scale=640:480";
    }

    if (!$has_video) {
        $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
                  " -f lavfi -i color=c=black:s=640x360 -shortest " .
                  " -c:v libx264 -profile:v baseline -level 3.0 -crf 35 -preset slow " .
                  " -c:a aac -b:a 64k -ar 44100 -ac 1 " .
                  " -movflags +faststart " .
                  " -brand mp42 " .
                  " -y " .
                  " -loglevel debug " .
                  escapeshellarg($final_video) .
                  " 2>" . escapeshellarg($log_file);
    } else {
        $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
                  " -c:v libx264 -profile:v baseline -level 3.0 -crf 35 -preset slow " .
                  " -c:a aac -b:a 64k -ar 44100 -ac 1 " .
                  " -vf \"" . $vf_filter . "\" " .
                  " -movflags +faststart " .
                  " -brand mp42 " .
                  " -y " .
                  " -loglevel debug " .
                  escapeshellarg($final_video) .
                  " 2>" . escapeshellarg($log_file);
    }

    exec($ffmpeg, $output, $return_var);

    if ($return_var !== 0) {
        if (file_exists($temp_video)) {
            @unlink($temp_video);
        }
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
        exit;
    } else {
        if (file_exists($temp_video)) {
            @unlink($temp_video);
        }
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
    }
} else {
    $log_file = 'uploads/ffmpeg_' . $id . '.log';

    $dimensions = get_video_dimensions_cli($temp_video);
    $vf_filter = "format=yuv420p";

    if ($dimensions && is_4_3_aspect_ratio_cli($dimensions['width'], $dimensions['height'])) {
        $vf_filter .= ",scale=640:480";
    }

    $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
              " -c:v libx264 -profile:v baseline -level 3.0 -crf 35 -preset slow " .
              " -c:a aac -b:a 64k -ar 44100 -ac 1 " .
              " -vf \"" . $vf_filter . "\" " .
              " -movflags +faststart " .
              " -brand mp42 " .
              " -y " .
              " -loglevel debug " .
              escapeshellarg($final_video) .
              " 2>" . escapeshellarg($log_file);

    exec($ffmpeg, $output, $return_var);

    if ($return_var !== 0) {
        if (file_exists($temp_video)) {
            @unlink($temp_video);
        }
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
        exit;
    } else {
        if (file_exists($temp_video)) {
            @unlink($temp_video);
        }
        if (file_exists($log_file)) {
            @unlink($log_file);
        }
    }
}

if (file_exists($final_video)) {
    $output = [];
    $return_var = 0;
    $ffmpeg = "ffmpeg -i " . escapeshellarg($final_video) . " -ss 00:00:01 -vframes 1 -y " . escapeshellarg($preview_file);
    exec($ffmpeg, $output, $return_var);

    if ($return_var !== 0) {
        $im = imagecreatetruecolor(120, 90);
        $bg = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $bg);
        imagejpeg($im, $preview_file);
        imagedestroy($im);
    }
}

if (file_exists($final_video) && file_exists($preview_file) && $user !== '') {
    $time = date("d.m.Y, H:i");
    $is_private = ($broadcast === 'private') ? 1 : 0;
    $tags = $tags ?? '';
    $stmt = $db->prepare("INSERT INTO videos (id, title, description, file, preview, user, time, private, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id, $title, $description, $final_video, $preview_file, $user, $time, $is_private, $tags]);
}

