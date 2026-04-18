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

function generate_public_video_id(PDO $db) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
    while (true) {
        $id = '';
        for ($i = 0; $i < 11; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $db->prepare('SELECT COUNT(*) FROM videos WHERE public_id = ?');
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            return $id;
        }
    }
}

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

function notify_processing_worker(int $queue_id): bool {
    $endpoint = processing_queue_url();
    $payload = json_encode(['queue_id' => $queue_id], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return false;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nConnection: close\r\n",
            'content' => $payload,
            'timeout' => 1.5,
        ],
    ]);
    $res = @file_get_contents($endpoint, false, $ctx);
    return $res !== false;
}

if (!isset($_SESSION['user'])) {
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

function normalize_tags($tags) {
    $tags = trim($tags ?? '');
    $parts = preg_split('/\s+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 1) {
    if ($ip_blocked) {
        $error = 'Загрузка видео для вашего IP адреса запрещена.';
    } else {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = normalize_tags($_POST['tags'] ?? '');
    if ($title === '') {
        $error = 'Введите название видео.';
    } elseif ($tags === '') {
        $error = 'Введите хотя бы один тег!';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p === 2) {
    if ($ip_blocked) {
        $error = 'Загрузка видео для вашего IP адреса запрещена.';
    } else {
    $title = $_SESSION['upload_title'] ?? '';
    $description = $_SESSION['upload_description'] ?? '';
    $tags = $_SESSION['upload_tags'] ?? '';
    $broadcast = $_POST['broadcast'] ?? 'public';
  
    if (empty($title)) {
        $error = "Введите название видео.";
    } elseif (strlen($description) > 5000) {
        $error = "Описание не должно превышать 5000 символов.";
    } elseif (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $error = "Ошибка при загрузке видео. Возможно, файл превышает лимит?";
    } elseif ($_FILES['video']['size'] > 1048576000) { 
        $error = "Файл слишком большой! Максимальный размер: 1000 МБ.";
    } else {
        $stmt = $db->query("SELECT MAX(id) + 1 as next_id FROM videos");
        $next_id = $stmt->fetch()['next_id'] ?? 1;
        $public_id = generate_public_video_id($db);
        $file_base = video_uploads_file_base((int)$next_id, $public_id);

        $video_ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $preview_ext = 'jpg';

        $temp_video = 'uploads/temp_' . $file_base . '.' . $video_ext;
        $final_video = 'uploads/' . $file_base . '.mp4';
        $preview_file = 'uploads/' . $file_base . '_preview.' . $preview_ext;
        
        if (!move_uploaded_file($_FILES['video']['tmp_name'], $temp_video)) {
            $error = "Ошибка при сохранении видео. Существует ли папка uploads и есть ли права на её запись?";
        } else {
            if ($use_external_processing) {
                $queue_video = 'uploads/queue_' . $file_base . '.' . $video_ext;
                if (!@rename($temp_video, $queue_video)) {
                    if (!@copy($temp_video, $queue_video)) {
                        $error = 'Ошибка постановки видео в очередь обработки.';
                    } else {
                        @unlink($temp_video);
                    }
                }

                if (empty($error)) {
                    try {
                        $stQ = $db->prepare("
                            INSERT INTO video_processing_queue
                            (public_id, user, title, description, tags, broadcast, source_file, created_at, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                        ");
                        $stQ->execute([
                            $public_id,
                            (string)$_SESSION['user'],
                            (string)$title,
                            (string)$description,
                            (string)$tags,
                            ($broadcast === 'private' ? 'private' : 'public'),
                            (string)$queue_video,
                            time()
                        ]);
                        $queue_id = (int)$db->lastInsertId();
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
                        $success = "Видео добавлено в очередь обработки. Оно появится после завершения конвертации. <a href=\"index.php\">На главную</a>";
                    } catch (Exception $e) {
                        $error = 'Ошибка при постановке видео в очередь.';
                    }
                }
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
                             " -vsync cfr -r 30 " .
                             " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast -threads 0 " .
                             " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                             " -movflags +faststart " .
                             " -brand mp42 " .
                             " -y " .
                             " -loglevel error " .
                             escapeshellarg($final_video) .
                             " 2>" . escapeshellarg($log_file);
                } else {
                    $ffmpeg = "ffmpeg -i " . escapeshellarg($temp_video) .
                             " -vf \"" . $vf_filter . "\" " .
                             " -vsync cfr -r 30 " .
                             " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast -threads 0 " .
                             " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                             " -movflags +faststart " .
                             " -brand mp42 " .
                             " -y " .
                             " -loglevel error " .
                             escapeshellarg($final_video) .
                             " 2>" . escapeshellarg($log_file);
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
                         " -vsync cfr -r 30 " .
                         " -c:v libx264 -profile:v baseline -level 3.0 -crf 25 -preset veryfast " .
                         " -c:a aac -b:a 96k -ar 44100 -ac 2 " .
                         " -vf \"" . $vf_filter . "\" " .
                         " -movflags +faststart " .
                         " -brand mp42 " .
                         " -y " .
                         " -loglevel debug " .
                         escapeshellarg($final_video) . 
                         " 2>" . escapeshellarg($log_file);
                
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
                $stmt = $db->prepare("INSERT INTO videos (public_id, title, description, file, preview, user, time, private, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$public_id, $title, $description, $final_video, $preview_file, $_SESSION['user'], $time, $is_private, $tags]);
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
                $success = "Видео успешно загружено! <a href=\"index.php\">На главную</a>";
            }
            }
        }
    }
    }
}

showHeader("Загрузка видео");
?>
<style type="text/css">
.upload-step-table { border-collapse: separate; border-spacing: 0; margin-top: 10px; }
.upload-step-title { font-size: 15px; color: #B94A00; font-weight: bold; border-bottom: 1px dashed #B94A00; padding-bottom: 4px; margin-bottom: 10px; }
.upload-label { text-align: right; padding-right: 10px; font-size: 13px; color: #333; vertical-align: top; }
.upload-input { text-align: left; }
.upload-btn { margin-top: 10px; }
.upload-bluebox { background: #E6F0FF; border: 1px dashed #000000; padding: 10px 15px; margin-bottom: 15px; font-size: 13px; color: #222; }
.upload-bluebox b { color: #222; }
.upload-bluebox .rules { color: #003399; font-size: 12px; }
.upload-radio { margin-right: 8px; }
.upload-note { color: #333; font-size: 12px; margin-top: 30px; text-align: center; }
</style>


<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<?php if ($p === 1): ?>
  <div class="upload-step-title">Загрузка видео (Шаг 1 из 2)</div>
  <?php if ($error): ?><div class="errorBox"><?=$error?></div><?php endif; ?>
  <?php if ($success): ?><div class="confirmBox"><?=$success?></div><?php endif; ?>
  <form method="post" action="upload.php?p=1">
    <table class="upload-step-table" width="500">
      <tr>
        <td class="upload-label" width="120"><b>Название:</b></td>
        <td class="upload-input"><input type="text" name="title" value="<?=htmlspecialchars($title)?>" style="width: 250px; font-size: 13px;"></td>
      </tr>
      <tr>
        <td class="upload-label" valign="top"><b>Описание:</b></td>
        <td class="upload-input"><textarea name="description" rows="4" style="width: 250px; font-size: 13px;"><?=htmlspecialchars($description)?></textarea></td>
      </tr>
      <tr>
        <td class="upload-label" valign="top"><b>Теги:</b></td>
        <td class="upload-input">
          <input type="text" name="tags" value="<?=htmlspecialchars($tags)?>" style="width: 250px; font-size: 13px;">
          <br>
          <span class="smallText"><b>Введите один или несколько тегов, описывающих ваше видео, через пробел.</b>
          <br>
          Лучше использовать релевантные ключевые слова, чтобы другие могли найти ваше видео!</span><br><br>
        </td>
      </tr>
      <tr>
        <td></td>
        <td class="upload-btn"><input type="submit" value="Далее ->" style="font-size: 13px;"></td>
      </tr>
  </table>
  </form>
<?php elseif ($p === 2): ?>
  <div class="upload-step-title">Загрузка видео (Шаг 2 из 2)</div>
  <?php if ($error): ?><div class="errorBox"><?=$error?></div><?php endif; ?>
  <?php if ($success): ?><div class="confirmBox"><?=$success?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" action="upload.php?p=2" onsubmit="var b=document.getElementById('uploadBtn'); if(b){b.disabled=true; b.value='Загрузка...';}">
    <input type="hidden" name="title" value="<?=htmlspecialchars($title)?>">
    <input type="hidden" name="description" value="<?=htmlspecialchars($description)?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="1048576000">
    <table class="upload-step-table" width="600">
      <tr>
        <td class="upload-label" width="120"><b>Файл:</b></td>
        <td class="upload-input">
          <div class="upload-bluebox">
            <input type="file" name="video" accept="video/*,audio/*" style="font-size: 13px;"><br>
			<br>
            <b>Максимальный размер файла: 1000 МБ, максимальная длина: неограничена.</b><br>
			<br>
            Не загружайте материалы, нарушающие авторские права, непристойные видео и т. д. Загружая видео, вы подтверждаете, что обладаете всеми необходимыми правами на этот контент.<br>
          </div>
        </td>
      </tr>
      <tr>
        <td class="upload-label"><b>Показ:</b></td>
        <td class="upload-input">
          <label><input type="radio" name="broadcast" value="public" class="upload-radio" checked><b>Публично</b>: видео будет доступно всем.</label><br>
          <label><input type="radio" name="broadcast" value="private" class="upload-radio"><b>Приватно</b>: видео будет доступно только по ссылке.</label>
        </td>
      </tr>
      <tr>
        <td></td>
        <td class="upload-btn"><input type="submit" value="Загрузить видео" id="uploadBtn"></td>
      </tr>
    </table>
  </form>
  
	<br>
    <center><b>ПОЖАЛУЙСТА, ПОДОЖДИТЕ, ЭТО МОЖЕТ ЗАНЯТЬ НЕСКОЛЬКО МИНУТ.<br>
    ПОСЛЕ ЗАВЕРШЕНИЯ ВЫ УВИДИТЕ ПОДТВЕРЖДЕНИЕ.</b></center>

<?php endif; ?>
</tr>
</table>

<div style="padding: 0px 5px 0px 5px;">

</div>
		</td></tr></table>
<?php showFooter(); ?>