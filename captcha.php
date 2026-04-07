<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$code = '';
$len = strlen($charset);
for ($i = 0; $i < 5; $i++) {
    $code .= $charset[random_int(0, $len - 1)];
}
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!function_exists('imagecreatetruecolor')) {
    unset($_SESSION['reg_captcha']);
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

$_SESSION['reg_captcha'] = $code;

header('Content-Type: image/png');

$w = 120;
$h = 40;
$im = imagecreatetruecolor($w, $h);
$bg = imagecolorallocate($im, 245, 245, 245);
$fg = imagecolorallocate($im, 40, 40, 40);
$noise = imagecolorallocate($im, 200, 200, 200);
imagefill($im, 0, 0, $bg);
for ($n = 0; $n < 6; $n++) {
    imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $noise);
}
$text = $code;
$font = 5;
$tw = imagefontwidth($font) * strlen($text);
$th = imagefontheight($font);
$x = (int) (($w - $tw) / 2) + random_int(-3, 3);
$y = (int) (($h - $th) / 2) + random_int(-2, 2);
imagestring($im, $font, max(2, $x), max(2, $y), $text, $fg);
imagepng($im);
imagedestroy($im);
