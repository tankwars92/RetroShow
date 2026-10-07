<?php 
@ini_set('upload_max_filesize', '1000M');
@ini_set('post_max_size', '1000M');
@ini_set('memory_limit', '1000M');
@ini_set('max_execution_time', '0');
@ini_set('max_input_time', '0');

@ini_set('display_errors', 'Off');
@ini_set('display_startup_errors', 'Off');
@ini_set('log_errors', 'On');
@ini_set('error_reporting', 'E_ALL & ~E_NOTICE & ~E_WARNING');

include("init.php");
include("template.php");

function get_video_dimensions($file) {
    $ffprobe = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=p=0 " . escapeshellarg($file);
    $output = trim(shell_exec($ffprobe));
    
    if (preg_match('/(\d+),(\d+)/', $output, $matches)) {
        return [
            'width' => intval($matches[1]),
            'height' => intval($matches[2])
        ];
    }
    return null;
}

function is_4_3_aspect_ratio($width, $height) {
    $ratio = $width / $height;
    return abs($ratio - 4/3) < 0.1;
}

// Продолжать обработку, даже если клиент отвалился/отменил (сервер "подвис" - видео всё равно дойдёт до конца)
@ignore_user_abort(true);

// Режим ответа для JS-загрузки (XHR / скрытый iframe): отдаём минимальную страницу с результатом
$is_ajax = isset($_GET['ajax']);

function ajax_respond($ok, $msg) {
    $cls = is_string($ok) ? $ok : ($ok ? 'ok' : 'err');
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
       . '<div id="r" class="' . $cls . '">' . $msg . '</div></body></html>';
    exit;
}

if (!isset($_SESSION['user'])) {
    if ($is_ajax) {
        ajax_respond(false, t('Сессия истекла. Войдите заново и повторите загрузку.'));
    }
    header("Location: login.php");
    exit;
}

$ip_info = get_client_ip_info();
$client_ip = $ip_info['ip'];
$client_ip_source = $ip_info['source'];
$ip_blocked = is_ip_banned($client_ip);

$error = '';
$success = '';
$use_external_processing = processing_enabled();

$p = isset($_GET['p']) ? intval($_GET['p']) : 1;

// ---------------------------------------------------------------------------
// Загрузка по кускам с докачкой: каждый запрос маленький, поэтому 502/таймаут
// прокси на одном куске просто повторяется, а не ломает всю загрузку.
//   ?p=2&ajax=1&chunk=1   - принять один кусок (uid, offset, chunk)
//   ?p=2&ajax=1&finish=1  - собрать файл и обработать (безопасно повторять)
// ---------------------------------------------------------------------------
$chunk_dir = 'uploads/.chunks';
$chunk_mode = $is_ajax && isset($_GET['chunk']) && $_SERVER['REQUEST_METHOD'] === 'POST';
$finish_mode = $is_ajax && isset($_GET['finish']) && $_SERVER['REQUEST_METHOD'] === 'POST';
$chunk_base = '';
$chunk_lock = null;

function plain_respond($line) {
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo $line;
    exit;
}

function save_uploaded_file($tmp, $dest) {
    global $finish_mode;
    if ($finish_mode) {
        if (@rename($tmp, $dest)) { return true; }
        if (@copy($tmp, $dest)) { @unlink($tmp); return true; }
        return false;
    }
    return move_uploaded_file($tmp, $dest);
}

if ($chunk_mode || $finish_mode) {
    $uid = isset($_POST['uid']) ? (string)$_POST['uid'] : '';
    if (!preg_match('/^[a-f0-9]{16,32}$/', $uid)) {
        if ($chunk_mode) { plain_respond('ERR bad uid'); }
        ajax_respond(false, t('Некорректный идентификатор загрузки.'));
    }
    if (!is_dir($chunk_dir)) {
        @mkdir($chunk_dir, 0755, true);
        @file_put_contents($chunk_dir . '/.htaccess', "Order allow,deny\nDeny from all\n");
        @file_put_contents($chunk_dir . '/index.html', '');
    }
    $chunk_base = $chunk_dir . '/' . md5((string)$_SESSION['user']) . '_' . $uid;
}

if ($chunk_mode) {
    session_write_close(); // не блокируем параллельные запросы сессией
    if ($ip_blocked) { plain_respond('ERR ip'); }
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : -1;
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK || $offset < 0) {
        plain_respond('ERR upload');
    }
    $len = (int)$_FILES['chunk']['size'];
    if ($len <= 0 || $len > 8388608 || $offset + $len > 1048576000) { plain_respond('ERR size'); }

    if ($offset === 0) {
        // чистим брошенные куски старше суток
        foreach ((array)@glob($chunk_dir . '/*') as $old) {
            if (is_file($old) && @filemtime($old) < time() - 86400) { @unlink($old); }
        }
    }

    $part = $chunk_base . '.part';
    $fp = @fopen($part, 'c+b');
    if (!$fp) { plain_respond('ERR fopen'); }
    flock($fp, LOCK_EX);
    $st = fstat($fp);
    $cur = (int)$st['size'];
    if ($offset > $cur) {
        // пропуск: говорим клиенту, с какого места продолжать
        flock($fp, LOCK_UN); fclose($fp);
        plain_respond('OK ' . $cur);
    }
    $in = @fopen($_FILES['chunk']['tmp_name'], 'rb');
    if (!$in) { flock($fp, LOCK_UN); fclose($fp); plain_respond('ERR read'); }
    fseek($fp, $offset);
    $copied = stream_copy_to_stream($in, $fp);
    fflush($fp);
    $st = fstat($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    fclose($in);
    if ($copied !== $len) { plain_respond('ERR write'); }
    plain_respond('OK ' . (int)$st['size']);
}

if ($finish_mode) {
    $done_file = $chunk_base . '.done';
    $chunk_lock = @fopen($chunk_base . '.lock', 'c');
    if ($chunk_lock && !flock($chunk_lock, LOCK_EX | LOCK_NB)) {
        // этот же файл уже обрабатывается другим запросом (повтор после 502)
        ajax_respond('busy', t('Сервер ещё обрабатывает видео...'));
    }
    clearstatcache();
    if (file_exists($done_file)) {
        $d = (string)file_get_contents($done_file);
        ajax_respond(substr($d, 0, 1) === '1', (string)substr($d, 1));
    }
    session_write_close();
}

function normalize_tags($tags) {
    $tags = trim($tags ?? '');
    $parts = preg_split('/\s+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 1) {
    if ($ip_blocked) {
        $error = t('Загрузка видео для вашего IP адреса запрещена.');
    } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = normalize_tags($_POST['tags'] ?? '');
    if ($title === '') {
        $error = t('Введите название видео.');
    } elseif ($tags === '') {
        $error = t('Введите хотя бы один тег!');
    } else {
        $_SESSION['upload_title'] = $title;
        $_SESSION['upload_description'] = $description;
        $_SESSION['upload_tags'] = $tags;
        header('Location: upload.php?p=2');
        exit;
    }
    }
} else {
    $title = $_SESSION['upload_title'] ?? '';
    $description = $_SESSION['upload_description'] ?? '';
    $tags = $_SESSION['upload_tags'] ?? '';
}

$up_name = $_FILES['video']['name'] ?? '';
$up_tmp = $_FILES['video']['tmp_name'] ?? '';
$up_size = (int)($_FILES['video']['size'] ?? 0);
$up_err = isset($_FILES['video']) ? $_FILES['video']['error'] : UPLOAD_ERR_NO_FILE;
if ($finish_mode) {
    $up_name = isset($_POST['name']) ? (string)$_POST['name'] : '';
    $up_tmp = $chunk_base . '.part';
    clearstatcache();
    $up_size = file_exists($up_tmp) ? (int)filesize($up_tmp) : 0;
    $up_err = ($up_size > 0 && $up_size === (int)($_POST['size'] ?? -1)) ? UPLOAD_ERR_OK : UPLOAD_ERR_NO_FILE;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 2) {
    if ($ip_blocked) {
        $error = t('Загрузка видео для вашего IP адреса запрещена.');
    } else {
    $title = $_SESSION['upload_title'] ?? '';
    $description = $_SESSION['upload_description'] ?? '';
    $tags = $_SESSION['upload_tags'] ?? '';
    $broadcast = $_POST['broadcast'] ?? 'public';
  
    if (empty($title)) {
        $error = t('Введите название видео.');
    } elseif (strlen($description) > 5000) {
        $error = t('Описание не должно превышать 5000 символов.');
    } elseif ($up_err !== UPLOAD_ERR_OK) {
        $error = t('Ошибка при загрузке видео. Возможно, файл превышает лимит?');
    } elseif ($up_size > 1048576000) { 
        $error = t('Файл слишком большой! Максимальный размер: 1000 МБ.');
    } else {
        $public_id = generate_public_video_id_for_upload($db);
        $video_ext = strtolower(pathinfo($up_name, PATHINFO_EXTENSION));
        if ($video_ext === '') {
            $video_ext = 'bin';
        }
        $preview_ext = 'jpg';
        $orig_upload_name = video_original_upload_name($up_name);

        if ($use_external_processing) {
            $queue_stem = video_queue_source_stem($public_id);
            $temp_video = 'uploads/.tmp_' . bin2hex(random_bytes(8)) . '.' . $video_ext;
            $queue_video = 'uploads/' . $queue_stem . '.' . $video_ext;

            if (!save_uploaded_file($up_tmp, $temp_video)) {
                $error = t('Ошибка при сохранении видео. Существует ли папка uploads и есть ли права на её запись?');
            } else {
                if (!@rename($temp_video, $queue_video)) {
                    if (!@copy($temp_video, $queue_video)) {
                        $error = t('Ошибка постановки видео в очередь обработки.');
                        @unlink($temp_video);
                    } else {
                        @unlink($temp_video);
                    }
                }

                if (empty($error)) {
                    try {
                        $queue_id = enqueue_video_processing($db, [
                            'public_id' => $public_id,
                            'user' => (string)$_SESSION['user'],
                            'title' => (string)$title,
                            'description' => (string)$description,
                            'tags' => (string)$tags,
                            'broadcast' => ($broadcast === 'private' ? 'private' : 'public'),
                            'source_file' => (string)$queue_video,
                            'created_at' => time(),
                            'original_filename' => $orig_upload_name !== '' ? $orig_upload_name : null,
                        ]);
                        notify_processing_worker($queue_id);

                        log_event('upload_video', [
                            'upload_user' => (string)$_SESSION['user'],
                            'video_public_id' => (string)$public_id,
                            'queue_id' => $queue_id,
                            'title' => (string)$title,
                            'tags' => (string)$tags,
                            'ip_detected' => (string)$client_ip,
                            'ip_detected_source' => (string)$client_ip_source,
                            'queued' => 1,
                        ]);

                        unset($_SESSION['upload_title'], $_SESSION['upload_description'], $_SESSION['upload_tags']);
                        $success = t('Видео добавлено в очередь обработки. Оно появится после завершения конвертации. <a href="index.php">На главную</a>');
                    } catch (Exception $e) {
                        $error = t('Ошибка при добавлении видео в очередь. Попробуйте ещё раз.');
                        @unlink($queue_video);
                    }
                }
            }
        } else {
        $stmt = pdo_retry(function () use ($db) {
            return $db->query("SELECT MAX(id) + 1 as next_id FROM videos");
        });
        $next_id = $stmt->fetch()['next_id'] ?? 1;
        $file_base = video_uploads_file_base((int)$next_id, $public_id);

        $temp_video = 'uploads/temp_' . $file_base . '.' . $video_ext;
        $final_video = 'uploads/' . $file_base . '.mp4';
        $preview_file = 'uploads/' . $file_base . '_preview.' . $preview_ext;
        
        if (!save_uploaded_file($up_tmp, $temp_video)) {
            $error = t('Ошибка при сохранении видео. Существует ли папка uploads и есть ли права на её запись?');
        } else {
            $output = [];
            $return_var = 0;

            $dimensions = get_video_dimensions($temp_video);
            $vf_filter = "format=yuv420p";
            if ($dimensions) {
                $w = (int)($dimensions['width'] ?? 0);
                $h = (int)($dimensions['height'] ?? 0);
                if ($w > 0 && $h > 0) {
                    if (is_4_3_aspect_ratio($w, $h)) {
                        $vf_filter .= ",scale=640:480";
                    } else {
                        if ($w > 640 || $h > 480) {
                            $vf_filter .= ",scale=640:-2";
                        }
                    }
                }
            }

            if ($video_ext != 'mp4') {
                $ffprobe = "ffprobe -v error -select_streams v:0 -show_entries stream=codec_type -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($temp_video);
                $has_video = trim(shell_exec($ffprobe)) === 'video';
                
                $log_file = 'uploads/ffmpeg_' . $file_base . '.log';

                if (!$has_video) {
                    $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
                             " -f lavfi -i color=c=black:s=640x360 -shortest " .
                             " -fps_mode cfr -r 30 " .
                             " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast -threads 0 " .
                             " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                             " -movflags +faststart " .
                             " -brand mp42 " .
                             " -y " .
                             " -loglevel error " .
                             escapeshellarg($final_video) .
                             " 2>&1";
                } else {
                    $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
                             " -vf \"" . $vf_filter . "\" " .
                             " -fps_mode cfr -r 30 " .
                             " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast -threads 0 " .
                             " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                             " -movflags +faststart " .
                             " -brand mp42 " .
                             " -y " .
                             " -loglevel error " .
                             escapeshellarg($final_video) .
                             " 2>&1";
                }
                
                exec($ffmpeg, $output, $return_var);
                
                if ($return_var !== 0) {
                    $error_log = file_exists($log_file) ? file_get_contents($log_file) : 'Лог недоступен';
                    if (file_exists($temp_video)) {
                        @unlink($temp_video);
                    }
                    if (file_exists($log_file)) {
                        @unlink($log_file);
                    }
                    $error = "Ошибка при конвертации в MP4. Код ошибки: " . $return_var . 
                            "<br>Детали ошибки: <pre>" . htmlspecialchars($error_log) . "</pre>";
                } else {
                    if (file_exists($temp_video)) {
                        @unlink($temp_video);
                    }
                    if (file_exists($log_file)) {
                        @unlink($log_file);
                    }
                }
            } else {
                $log_file = 'uploads/ffmpeg_' . $file_base . '.log';

                $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) . 
                         " -fps_mode cfr -r 30 " .
                         " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast " .
                         " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                         " -vf \"" . $vf_filter . "\" " .
                         " -movflags +faststart " .
                         " -brand mp42 " .
                         " -y " .
                         " -loglevel debug " .
                         escapeshellarg($final_video) . 
                         " 2>&1";
                
                exec($ffmpeg, $output, $return_var);
                
                if ($return_var !== 0) {
                    $error_log = file_exists($log_file) ? file_get_contents($log_file) : 'Лог недоступен';
                    if (file_exists($temp_video)) {
                        @unlink($temp_video);
                    }
                    if (file_exists($log_file)) {
                        @unlink($log_file);
                    }
                    $error = "Ошибка при конвертации в MP4. Код ошибки: " . $return_var . 
                            "<br>Детали ошибки: <pre>" . htmlspecialchars($error_log) . "</pre>";
                } else {
                    if (file_exists($temp_video)) {
                        @unlink($temp_video);
                    }
                    if (file_exists($log_file)) {
                        @unlink($log_file);
                    }
                }
            }

            if (empty($error)) {
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

            if (empty($error)) {
                $time = date("d.m.Y, H:i");
                $is_private = ($broadcast === 'private') ? 1 : 0;
                $tags = normalize_tags($tags ?? '');
                $stmt = $db->prepare("INSERT INTO videos (public_id, title, description, file, preview, user, time, private, tags, original_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$public_id, $title, $description, $final_video, $preview_file, $_SESSION['user'], $time, $is_private, $tags, $orig_upload_name !== '' ? $orig_upload_name : null]);
                $inserted_id = (int)$db->lastInsertId();
                log_event('upload_video', [
                    'upload_user' => (string)$_SESSION['user'],
                    'video_public_id' => (string)$public_id,
                    'video_id' => (int)$inserted_id,
                    'title' => (string)$title,
                    'tags' => (string)$tags,
                    'ip_detected' => (string)$client_ip,
                    'ip_detected_source' => (string)$client_ip_source,
                ]);
                $success = t('Видео успешно загружено!') . ' <a href="index.php">' . t('На главную') . '</a>';
            }
            }
        }
    }
    }
}

if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST' && $p === 2) {
    $ajax_ok = ($success !== '');
    $ajax_msg = $ajax_ok ? $success : ($error !== '' ? $error : t('Неизвестная ошибка при загрузке.'));
    if ($finish_mode) {
        // запоминаем итог: повторный finish (после 502) получит тот же ответ и не создаст дубль
        @file_put_contents($chunk_base . '.done', ($ajax_ok ? '1' : '0') . $ajax_msg);
        @unlink($chunk_base . '.part');
        if ($ajax_ok) {
            if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
            unset($_SESSION['upload_title'], $_SESSION['upload_description'], $_SESSION['upload_tags']);
        }
    }
    ajax_respond($ajax_ok, $ajax_msg);
}

showHeader("Загрузка видео");
?>
<style type="text/css">
.upload-step-table { border-collapse: separate; border-spacing: 0; margin-top: 10px; }
.upload-label { text-align: right; padding-right: 10px; font-size: 13px; color: #333; vertical-align: top; }
.upload-input { text-align: left; }
.upload-btn { margin-top: 10px; }
.upload-bluebox { background: #E6F0FF; border: 1px dashed #000000; padding: 10px 15px; margin-bottom: 15px; font-size: 13px; color: #222; }
.upload-bluebox b { color: #222; }
.upload-bluebox .rules { color: #003399; font-size: 12px; }
.upload-radio { margin-right: 8px; }
.upload-note { color: #333; font-size: 12px; margin-top: 30px; text-align: center; }
.formHighlight {
    background-image: url(img/table_results_selected_bg.gif);
    background-repeat: repeat-x;
    background-color: #FFFFCC;
    background-position: left top;
    border: 1px dashed #CCCC66;
    padding: 7px;
    padding-bottom: 10px;
    margin-bottom: 5px;
}

.formHighlightText {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    font-weight: normal;
    color: #666633;
    margin-top: 5px;
    margin-left: 6px;
}
</style>


<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<?php if ($p === 1): ?>
  <div class="tableSubTitle"><?= t('Загрузка видео (Шаг 1 из 2)') ?></div>
  <?php if ($error): ?><div class="errorBox"><?=$error?></div><?php endif; ?>
  <?php if ($success): ?><div class="confirmBox"><?=$success?></div><?php endif; ?>
  <form method="post" action="upload.php?p=1">
    <table class="upload-step-table" width="100%">
      <tr>
        <td class="upload-label" width="200"><b><?= t('Название:') ?></b></td>
        <td class="upload-input"><input type="text" name="title" value="<?=htmlspecialchars($title)?>" style="width: 250px; font-size: 13px;"></td>
      </tr>
      <tr>
        <td class="upload-label" valign="top"><b><?= t('Описание:') ?></b></td>
        <td class="upload-input"><textarea name="description" rows="4" style="width: 250px; font-size: 13px;"><?=htmlspecialchars($description)?></textarea></td>
      </tr>
      <tr>
        <td class="upload-label" valign="top"><b><?= t('Теги:') ?></b></td>
        <td class="upload-input">
          <input type="text" name="tags" value="<?=htmlspecialchars($tags)?>" style="width: 250px; font-size: 13px;">
        </td>
      </tr>
      <tr align="left">
        <td></td>
        <td><div class="formFieldInfo"><strong><?= t('Введите один или несколько тегов, описывающих ваше видео, через пробел.') ?></strong><br><?= t('Лучше использовать релевантные ключевые слова, чтобы другие пользователи могли найти ваше видео!') ?></div></td>
      </tr>
      <tr>
        <td></td>
        <td class="upload-btn"><input type="submit" value="<?= htmlspecialchars(t('Далее ->'), ENT_QUOTES, 'UTF-8') ?>" style="font-size: 13px;"></td>
      </tr>
  </table>
  </form>
<?php elseif ($p === 2): ?>
  <div class="tableSubTitle"><?= t('Загрузка видео (Шаг 2 из 2)') ?></div>
  <?php if ($error): ?><div class="errorBox"><?=$error?></div><?php endif; ?>
  <?php if ($success): ?><div class="confirmBox"><?=$success?></div><?php endif; ?>
  <div id="uploadResult" style="display:none"></div>
  <form id="uploadForm" method="post" enctype="multipart/form-data" action="upload.php?p=2" onsubmit="return up_submit()">
    <input type="hidden" name="title" value="<?=htmlspecialchars($title)?>">
    <input type="hidden" name="description" value="<?=htmlspecialchars($description)?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="1048576000">
    <table class="upload-step-table" width="100%">
      <tr>
        <td class="upload-label" width="200"><b><?= t('Файл:') ?></b></td>
        <td class="upload-input">
        <div width="595" height="20" cellpadding="0" border="0" bgcolor="#E5ECF9" class="formHighlight">
            <input type="file" style="margin-bottom: 3px" id="fileToUpload" name="video" accept="video/*,audio/*"><br>
            <span class="formHighlightText"><b><?= t('Макс. размер файла: 1000 МБ. Не загружайте материалы, нарущающие авторские права.') ?></b></span><br>
            <span class="formHighlightText"><?= t('После загрузки, вы можете редактировать или удалить это видео в любое время в разделе "Мои видео".') ?></span>
        </div>
        </td>
      </tr>
      <tr>
        <td class="upload-label"><b><?= t('Показ:') ?></b></td>
        <td class="upload-input">
          <label><input type="radio" name="broadcast" value="public" class="upload-radio" checked><b><?= t('Публично') ?></b>: <?= t('видео будет доступно всем.') ?></label><br>
          <label><input type="radio" name="broadcast" value="private" class="upload-radio"><b><?= t('Приватно') ?></b>: <?= t('видео будет доступно только по ссылке.') ?></label>
        </td>
      </tr>
      <tr>
        <td></td>
        <td>
        <br>
        <b><?= t('ПОЖАЛУЙСТА, ПОДОЖДИТЕ, ЭТО МОЖЕТ ЗАНЯТЬ НЕСКОЛЬКО МИНУТ.') ?><br>
        <?= t('ДАЖЕ ЕСЛИ СТРАНИЦА ОБНОВИЛАСЬ БЕЗ ПОДТВЕРЖДЕНИЯ, ВАШЕ') ?><br> <?= t('ВИДЕО БУДЕТ ОТПРАВЛЕНО НА ОБРАБОТКУ.') ?></b></td>
      </tr>
      <tr>
        <td></td>
        <td class="upload-btn"><br><input type="submit" value="<?= htmlspecialchars(t('Загрузить видео'), ENT_QUOTES, 'UTF-8') ?>" id="uploadBtn">
            <span id="uploadCancelWrap" style="display: none;"><input type="button" id="uploadCancelBtn" value="<?= htmlspecialchars(t('Отмена'), ENT_QUOTES, 'UTF-8') ?>" onclick="up_cancel()"></span>
          <div id="uploadStatus" style="display: none; margin-top: 8px;">
            <div style="position: relative; width: 400px; height: 18px; border: 1px solid #666666; background: #FFFFFF; overflow: hidden;">
              <div id="uploadBar" style="position: absolute; left: 0px; top: 0px; z-index: 1; width: 0px; height: 18px; background: #FF0000; font-size: 1px; line-height: 1px;">&nbsp;</div>
              <div id="uploadText" style="position: absolute; left: 0px; top: 0px; z-index: 2; width: 398px; height: 18px; text-align: center; font-family: Arial, Helvetica, sans-serif; font-size: 11px; line-height: 18px; color: #000000; white-space: nowrap; overflow: hidden;">&nbsp;</div>
            </div>
          </div>
        </td>
      </tr>
    </table>
  </form>
    
<script type="text/javascript">
<!--
var UP_L = {
  uploading: <?= json_encode(t('Загрузка файла...')) ?>,
  processing: <?= json_encode(t('Обработка на сервере...')) ?>,
  waiting: <?= json_encode(t('Загрузка и обработка...')) ?>,
  noFile: <?= json_encode(t('Выберите файл для загрузки.')) ?>,
  tooBig: <?= json_encode(t('Файл слишком большой! Максимальный размер: 1000 МБ.')) ?>,
  stalled: <?= json_encode(t('Загрузка остановилась: данные не передаются уже больше минуты. Проверьте соединение и попробуйте ещё раз.')) ?>,
  netError: <?= json_encode(t('Ошибка сети во время загрузки. Попробуйте ещё раз.')) ?>,
  httpError: <?= json_encode(t('Сервер вернул ошибку:')) ?>,
  badResponse: <?= json_encode(t('Сервер ответил неожиданно (возможно, он перегружен). Проверьте раздел «Мои видео», прежде чем загружать повторно.')) ?>,
  cancelled: <?= json_encode(t('Загрузка отменена. Если файл уже успел дойти до сервера, видео всё равно может появиться в «Мои видео», проверьте это перед повторной загрузкой.')) ?>,
  cancelledSimple: <?= json_encode(t('Загрузка отменена.')) ?>,
  plainBtn: <?= json_encode(t('Отправить обычным способом')) ?>,
  resumeBtn: <?= json_encode(t('Продолжить загрузку')) ?>,
  btnBusy: <?= json_encode(t('Загрузка...')) ?>,
  retrying: <?= json_encode(t('Загрузка...')) ?>,
  retryIn: <?= json_encode(t('через')) ?>,
  sec: <?= json_encode(t('с')) ?>,
  chunkFailed: <?= json_encode(t('Не удалось передать файл: сервер слишком долго не отвечает. Уже загруженная часть сохранена, нажмите «Продолжить загрузку».')) ?>,
  finishLong: <?= json_encode(t('Обработка на сервере...')) ?>,
  finishFailed: <?= json_encode(t('Сервер слишком долго не отвечает после загрузки. Видео могло быть обработано: проверьте раздел «Мои видео», прежде чем загружать повторно.')) ?>
};

var UP_URL = 'upload.php?p=2&ajax=1';
var UP_CHUNK_URL = 'upload.php?p=2&ajax=1&chunk=1';
var UP_FINISH_URL = 'upload.php?p=2&ajax=1&finish=1';
var UP_PLAIN_URL = 'upload.php?p=2';
var UP_BAR_W = 398;
var UP_STALL_MS = 60000;
var UP_CHUNK = 524288;        
var UP_CHUNK_TIMEOUT = 45000;
var UP_CHUNK_RETRIES = 12;
var UP_FINISH_POLL = 5000;
var UP_FINISH_TRIES = 180;

var up_busy = false;
var up_waiting = false;
var up_aborted = false;
var up_done = false;
var up_timer = null;
var up_stall = null;
var up_retryTimer = null;
var up_started = 0;
var up_xhr = null;
var up_mode = '';
var up_btnValue = '';

var up_uid = '';
var up_file = null;
var up_pos = 0;
var up_total = 0;
var up_chunkRetry = 0;
var up_finishTry = 0;
var up_stage = '';

function up_el(id) { return document.getElementById(id); }

function up_show(id, on) {
  var e = up_el(id);
  if (e) { e.style.display = on ? '' : 'none'; }
}

function up_status(html) {
  var e = up_el('uploadText');
  if (e) { e.innerHTML = html; }
}

function up_bar(pct) {
  var e = up_el('uploadBar');
  if (!e) { return; }
  if (pct < 0) { pct = 0; }
  if (pct > 100) { pct = 100; }
  e.style.width = Math.round(UP_BAR_W * pct / 100) + 'px';
}

function up_mb(n) {
  return (Math.round(n / 104857.6) / 10) + ' MB';
}

function up_makeUid() {
  var hex = '0123456789abcdef';
  var s = '';
  var i;
  for (i = 0; i < 32; i++) { s += hex.charAt(Math.floor(Math.random() * 16)); }
  return s;
}

function up_begin() {
  var b = up_el('uploadBtn');
  up_busy = true;
  up_aborted = false;
  up_done = false;
  up_show('uploadResult', false);
  up_bar(0);
  up_status('&nbsp;');
  up_show('uploadStatus', true);
  up_show('uploadCancelWrap', true);
  if (b) {
    if (up_btnValue === '') { up_btnValue = b.value; }
    b.disabled = true;
    b.value = UP_L.btnBusy;
  }
}

function up_end() {
  var b = up_el('uploadBtn');
  up_busy = false;
  up_waiting = false;
  if (up_stall) { clearTimeout(up_stall); up_stall = null; }
  if (up_retryTimer) { clearTimeout(up_retryTimer); up_retryTimer = null; }
  up_xhr = null;
  up_show('uploadStatus', false);
  up_show('uploadCancelWrap', false);
  if (b) {
    b.disabled = false;
    if (up_btnValue !== '') { b.value = up_btnValue; }
  }
  var f = up_el('uploadForm');
  if (f) {
    try { f.target = ''; } catch (e1) {}
    f.action = UP_PLAIN_URL;
  }
}

function up_btnHtml(label, fn) {
  return '<input type="button" value="' + label + '" onclick="' + fn + '">';
}

function up_result(ok, html, btns) {
  var r = up_el('uploadResult');
  up_end();
  if (!r) { return; }
  r.className = ok ? 'confirmBox' : 'errorBox';
  var extra = '';
  if (!ok && btns === 'plain') { extra = '<br><br>' + up_btnHtml(UP_L.plainBtn, 'up_plain()'); }
  if (!ok && btns === 'resume') { extra = '<br><br>' + up_btnHtml(UP_L.resumeBtn, 'up_resume()'); }
  r.innerHTML = html + extra;
  r.style.display = '';
  if (ok) { up_show('uploadForm', false); }
}

function up_parse(text) {
  var m = /<div id="r" class="(ok|err|busy)">([\s\S]*)<\/div>/.exec(text || '');
  if (!m) { return null; }
  return { kind: m[1], html: m[2] };
}

function up_validate() {
  var f = up_el('fileToUpload');
  if (!f || f.value === '') {
    alert(UP_L.noFile);
    return false;
  }
  try {
    if (f.files && f.files[0] && f.files[0].size > 1048576000) {
      alert(UP_L.tooBig);
      return false;
    }
  } catch (e) {}
  return true;
}

function up_hasXhr2() {
  try {
    if (window.FormData && window.XMLHttpRequest) {
      var x = new XMLHttpRequest();
      return !!(x && x.upload);
    }
  } catch (e) {}
  return false;
}

function up_pickFile() {
  try {
    var f = up_el('fileToUpload');
    if (f && f.files && f.files[0] && f.files[0].slice && f.files[0].size > 0) { return f.files[0]; }
  } catch (e) {}
  return null;
}

function up_broadcast() {
  var form = up_el('uploadForm');
  var els = form.elements['broadcast'];
  var i;
  if (els && els.length) {
    for (i = 0; i < els.length; i++) { if (els[i].checked) { return els[i].value; } }
  }
  return 'public';
}

/* ================= режим 1: куски + докачка (современные браузеры) ================= */

function up_sendChunked(file) {
  up_mode = 'chunk';
  up_stage = 'upload';
  up_file = file;
  up_total = file.size;
  up_pos = 0;
  up_uid = up_makeUid();
  up_chunkRetry = 0;
  up_finishTry = 0;
  up_progress();
  up_nextChunk();
}

function up_progress() {
  var pct = up_total > 0 ? (up_pos * 100 / up_total) : 0;
  up_bar(pct);
  up_status(UP_L.uploading + ' ' + Math.round(pct) + '% (' + up_mb(up_pos) + ' / ' + up_mb(up_total) + ')');
}

function up_nextChunk() {
  if (up_aborted) { return; }
  if (up_pos >= up_total) { up_startFinish(); return; }
  var end = up_pos + UP_CHUNK;
  if (end > up_total) { end = up_total; }
  var fd = new FormData();
  fd.append('uid', up_uid);
  fd.append('offset', String(up_pos));
  fd.append('chunk', up_file.slice(up_pos, end), 'chunk.bin');
  var x = new XMLHttpRequest();
  up_xhr = x;
  x.open('POST', UP_CHUNK_URL, true);
  try { x.timeout = UP_CHUNK_TIMEOUT; } catch (e) {}
  x.onreadystatechange = function () {
    if (x.readyState !== 4 || up_aborted || x !== up_xhr) { return; }
    var m = null;
    if (x.status === 200) { m = /^OK (\d+)/.exec(x.responseText || ''); }
    if (m) {
      var size = parseInt(m[1], 10);
      up_pos = (size > 0 && size <= up_total) ? size : end;
      up_chunkRetry = 0;
      up_progress();
      setTimeout(up_nextChunk, 0);
    } else {
      up_chunkFail();
    }
  };
  try {
    x.send(fd);
  } catch (e2) {
    up_chunkFail();
  }
}

function up_chunkFail() {
  if (up_aborted) { return; }
  up_chunkRetry++;
  if (up_chunkRetry > UP_CHUNK_RETRIES) {
    up_result(false, UP_L.chunkFailed, 'resume');
    return;
  }
  var wait = Math.round(Math.min(1000 * Math.pow(2, up_chunkRetry - 1), 15000) / 1000);
  up_status(UP_L.retrying + ' (' + up_chunkRetry + '/' + UP_CHUNK_RETRIES + ') ' + UP_L.retryIn + ' ' + wait + ' ' + UP_L.sec + '... ' + Math.round(up_pos * 100 / up_total) + '%');
  if (up_retryTimer) { clearTimeout(up_retryTimer); }
  up_retryTimer = setTimeout(function () { up_retryTimer = null; up_nextChunk(); }, wait * 1000);
}

function up_startFinish() {
  up_stage = 'finish';
  up_finishTry = 0;
  up_bar(100);
  up_status(UP_L.processing);
  up_doFinish();
}

function up_doFinish() {
  if (up_aborted) { return; }
  var fd = new FormData();
  fd.append('uid', up_uid);
  fd.append('name', up_file.name || 'video.bin');
  fd.append('size', String(up_total));
  fd.append('broadcast', up_broadcast());
  var x = new XMLHttpRequest();
  up_xhr = x;
  x.open('POST', UP_FINISH_URL, true);
  x.onreadystatechange = function () {
    if (x.readyState !== 4 || up_aborted || x !== up_xhr) { return; }
    var r = (x.status === 200) ? up_parse(x.responseText) : null;
    if (r && r.kind === 'ok') {
      up_result(true, r.html, '');
    } else if (r && r.kind === 'err') {
      up_result(false, r.html, '');
    } else {
      up_finishLater();
    }
  };
  try {
    x.send(fd);
  } catch (e) {
    up_finishLater();
  }
}

function up_finishLater() {
  if (up_aborted) { return; }
  up_finishTry++;
  if (up_finishTry > UP_FINISH_TRIES) {
    up_result(false, UP_L.finishFailed, 'resume');
    return;
  }
  up_status(UP_L.finishLong + ' (' + up_finishTry + ')...');
  if (up_retryTimer) { clearTimeout(up_retryTimer); }
  up_retryTimer = setTimeout(function () { up_retryTimer = null; up_doFinish(); }, UP_FINISH_POLL);
}

function up_resume() {
  if (up_busy) { return; }
  up_begin();
  up_chunkRetry = 0;
  if (up_stage === 'finish') {
    up_finishTry = 0;
    up_status(UP_L.processing);
    up_doFinish();
  } else {
    up_progress();
    up_nextChunk();
  }
}

/* ================= режим 2: весь файл одним XHR (XHR2 без File.slice) ================= */

function up_armStall() {
  if (up_stall) { clearTimeout(up_stall); }
  up_stall = setTimeout(up_onStall, UP_STALL_MS);
}

function up_onStall() {
  if (up_done || up_aborted) { return; }
  up_aborted = true;
  try { if (up_xhr) { up_xhr.abort(); } } catch (e) {}
  up_result(false, UP_L.stalled, 'plain');
}

function up_sendXhr() {
  var form = up_el('uploadForm');
  var fd = new FormData(form);
  var x = new XMLHttpRequest();
  up_xhr = x;
  up_mode = 'xhr';
  up_status(UP_L.uploading);
  up_armStall();

  x.open('POST', UP_URL, true);

  x.upload.onprogress = function (e) {
    if (e && e.lengthComputable && e.total > 0) {
      var pct = e.loaded * 100 / e.total;
      up_bar(pct);
      up_status(UP_L.uploading + ' ' + Math.round(pct) + '% (' + up_mb(e.loaded) + ' / ' + up_mb(e.total) + ')');
    }
    up_armStall();
  };
  x.upload.onload = function () {
    up_done = true;
    if (up_stall) { clearTimeout(up_stall); up_stall = null; }
    up_bar(100);
    up_status(UP_L.processing);
  };
  x.onreadystatechange = function () {
    if (x.readyState !== 4 || up_aborted) { return; }
    if (x.status === 200) {
      var r = up_parse(x.responseText);
      if (r) { up_result(r.kind === 'ok', r.html, r.kind === 'ok' ? '' : 'plain'); }
      else { up_result(false, UP_L.badResponse, 'plain'); }
    } else if (x.status === 0) {
      up_result(false, UP_L.netError, 'plain');
    } else {
      up_result(false, UP_L.httpError + ' ' + x.status, 'plain');
    }
  };
  x.send(fd);
}

/* ================= общее ================= */

function up_submit() {
  if (up_busy) { return false; }
  if (!up_validate()) { return false; }
  if (up_hasXhr2()) {
    up_begin();
    try {
      var file = up_pickFile();
      if (file) {
        up_sendChunked(file);
      } else {
        up_sendXhr();
      }
      return false;
    } catch (e) {
      up_xhr = null;
      up_end();
    }
  }
  
  var b = up_el('uploadBtn');
  if (b) {
    try { b.value = UP_L.btnBusy; b.disabled = true; } catch (e2) {}
  }
  return true;
}

function up_cancel() {
  up_aborted = true;
  try { if (up_xhr) { up_xhr.abort(); } } catch (e) {}
  if (up_mode === 'chunk' && up_stage === 'upload') {
    up_result(false, UP_L.cancelledSimple, '');
  } else {
    up_result(false, UP_L.cancelled, '');
  }
}

function up_plain() {
  var form = up_el('uploadForm');
  if (!form) { return; }
  if (!up_validate()) { return; }
  try { form.target = ''; } catch (e) {}
  form.action = UP_PLAIN_URL;
  form.submit();
}
// -->
</script>
<?php endif; ?>
</tr>
</table>

<div style="padding: 0px 5px 0px 5px;">

</div>
        </td></tr></table>
<?php showFooter(); ?>