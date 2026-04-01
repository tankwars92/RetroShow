<?php 
include("init.php");
include("template.php");

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$admins = @unserialize(RETROSHOW_ADMINS);
if (!is_array($admins)) $admins = [];

if (!$user) {
    header('Location: login.php');
    exit;
}

if (!in_array($user, $admins, true)) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

function admin_modlog_file() {
    return get_modlog_path();
}
function admin_read_bans() {
    global $db;
    $rows = [];
    try {
        $st = $db->query('SELECT ip, created_at, created_by FROM ip_bans ORDER BY created_at DESC');
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $rows = [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'ip' => trim((string)($r['ip'] ?? '')),
            'ts' => (int)($r['created_at'] ?? 0),
            'admin' => trim((string)($r['created_by'] ?? '')),
        ];
    }
    return $out;
}
function admin_append_ban($ip, $admin) {
    global $db;
    $st = $db->prepare('INSERT OR REPLACE INTO ip_bans (ip, created_at, created_by) VALUES (?, ?, ?)');
    $st->execute([$ip, time(), $admin]);
}
function admin_remove_ban_ip($ip) {
    global $db;
    try {
        $st = $db->prepare('DELETE FROM ip_bans WHERE ip = ?');
        $st->execute([$ip]);
    } catch (Exception $e) {}
}

function admin_delete_video_full(PDO $db, array $videoRow) {
    $video_id = (int)$videoRow['id'];
    $file = (string)($videoRow['file'] ?? '');
    $preview = (string)($videoRow['preview'] ?? '');

    $db->prepare('DELETE FROM comments WHERE video_id = ?')->execute([$video_id]);
    $db->prepare('DELETE FROM ratings WHERE video_id = ?')->execute([$video_id]);
    $db->prepare('DELETE FROM video_views WHERE video_id = ?')->execute([$video_id]);
    try { $db->prepare('DELETE FROM user_favourites WHERE video_id = ?')->execute([$video_id]); } catch (Exception $e) {}
    $db->prepare('DELETE FROM videos WHERE id = ?')->execute([$video_id]);

    $duration_cache = __DIR__ . '/uploads/' . $video_id . '_duration.txt';
    if (is_file($duration_cache)) @unlink($duration_cache);

    if ($file !== '' && is_file($file)) @unlink($file);
    $mp4 = __DIR__ . '/uploads/' . $video_id . '.mp4';
    if (is_file($mp4)) @unlink($mp4);

    if ($preview !== '' && is_file($preview)) @unlink($preview);
    $jpg = __DIR__ . '/uploads/' . $video_id . '_preview.jpg';
    if (is_file($jpg)) @unlink($jpg);
}
function admin_collect_video_ids_by_ip($ip) {
    $path = admin_modlog_file();
    if (!is_file($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $ids = [];
    foreach ($lines as $line) {
        $obj = json_decode($line, true);
        if (!is_array($obj)) continue;
        if (($obj['event'] ?? '') !== 'upload_video') continue;
        $line_ip = trim((string)($obj['ip'] ?? ''));
        $line_ip2 = trim((string)($obj['ip_detected'] ?? ''));
        if ($line_ip !== $ip && $line_ip2 !== $ip) continue;
        $vid = (int)($obj['video_id'] ?? 0);
        if ($vid > 0) $ids[$vid] = true;
    }
    return array_keys($ids);
}

function admin_collect_log_rows($limit = 200) {
    $path = admin_modlog_file();
    if (!is_file($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) return [];
    $lines = array_slice($lines, -1 * max(1, (int)$limit));
    $rows = [];
    foreach ($lines as $line) {
        $obj = json_decode($line, true);
        if (!is_array($obj)) continue;
        $rows[] = $obj;
    }
    return array_reverse($rows);
}

function admin_delete_channel_keep_videos(PDO $db, $login) {
    try { $db->prepare('DELETE FROM users WHERE login = ?')->execute([$login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM user_favourites WHERE user = ?')->execute([$login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM user_friends WHERE user = ? OR friend = ?')->execute([$login, $login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM profile_comments WHERE profile_user = ? OR user = ?')->execute([$login, $login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM comments WHERE user = ?')->execute([$login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM mail_inbox WHERE to_user = ? OR from_user = ?')->execute([$login, $login]); } catch (Exception $e) {}
    try { $db->prepare('DELETE FROM video_views WHERE user = ?')->execute([$login]); } catch (Exception $e) {}
}

function admin_human_log_line(array $row) {
    $event = (string)($row['event'] ?? '');
    $user = (string)($row['user'] ?? '');
    $ip = (string)($row['ip'] ?? '');
    $ua = (string)($row['ua'] ?? '');
    $admin_user = (string)($row['admin_user'] ?? '');
    if ($event === 'upload_video') {
        $u = (string)($row['upload_user'] ?? $user);
        $title = (string)($row['title'] ?? '');
        return 'Загрузка видео: аккаунт "' . $u . '", видео "' . $title . '", IP ' . $ip;
    }
    if ($event === 'comment_video') {
        $author = (string)($row['author'] ?? $user);
        $title = (string)($row['video_title'] ?? '');
        return 'Комментарий к видео: аккаунт "' . $author . '", видео "' . $title . '", IP ' . $ip;
    }
    if ($event === 'comment_profile') {
        $author = (string)($row['author'] ?? $user);
        $target = (string)($row['profile_user'] ?? '');
        return 'Комментарий на канал: аккаунт "' . $author . '" на канал "' . $target . '", IP ' . $ip;
    }
    if ($event === 'blocked_ip') {
        return 'Заблокированный вход по IP: ' . $ip . '; UA: ' . $ua;
    }
    if ($event === 'blocked_channel') {
        $blocked = (string)($row['blocked_user'] ?? $user);
        return 'Попытка входа заблокированного канала: "' . $blocked . '", IP ' . $ip;
    }

    if ($event === 'admin_ip_ban') {
        $t = (string)($row['target_ip'] ?? '');
        $by = ($admin_user !== '' ? $admin_user : $user);
        return 'Модерация: бан IP ' . $t . ' (админ "' . $by . '")';
    }
    if ($event === 'admin_ip_unban') {
        $t = (string)($row['target_ip'] ?? '');
        $by = ($admin_user !== '' ? $admin_user : $user);
        return 'Модерация: разбан IP ' . $t . ' (админ "' . $by . '")';
    }
    if ($event === 'admin_delete_videos_by_ip') {
        $t = (string)($row['target_ip'] ?? '');
        $deleted = (int)($row['deleted'] ?? 0);
        $by = ($admin_user !== '' ? $admin_user : $user);
        return 'Модерация: удаление видео по IP ' . $t . ' (удалено ' . $deleted . ', админ "' . $by . '")';
    }
    if ($event === 'admin_delete_channel') {
        $t = (string)($row['target_channel'] ?? '');
        $by = ($admin_user !== '' ? $admin_user : $user);
        return 'Модерация: удаление канала "' . $t . '" (админ "' . $by . '")';
    }
    if ($event === 'admin_delete_videos_by_channel') {
        $t = (string)($row['target_channel'] ?? '');
        $deleted = (int)($row['deleted'] ?? 0);
        $by = ($admin_user !== '' ? $admin_user : $user);
        return 'Модерация: удаление всех видео канала "' . $t . '" (удалено ' . $deleted . ', админ "' . $by . '")';
    }
    if ($event === 'contact_submit') {
        $from_email = (string)($row['from_email'] ?? '');
        $from_user_real = trim((string)($row['from_user_real'] ?? ''));
        $from_user = trim((string)($row['from_user'] ?? ''));
        $who = $from_user_real !== '' ? $from_user_real : ($from_user !== '' ? $from_user : 'Guest');
        $subject = trim((string)($row['subject'] ?? ''));
        $ip_address = (string)($row['ip'] ?? '');
        $parts = [];
        $parts[] = 'Обратная связь';
        if ($from_email !== '') $parts[] = 'почта "' . $from_email . '"';
        $parts[] = 'от "' . $who . '"';
        $parts[] = 'IP ' . $ip_address;
        return implode(': ', [array_shift($parts), implode(', ', $parts)]);
    }

    return $event . ' | user=' . $user . ' | ip=' . $ip;
}

$news_file = __DIR__ . '/news.txt';
$current_news = '';
if (file_exists($news_file)) {
    $current_news = trim(file_get_contents($news_file));
}

if (isset($_POST['field_command']) && $_POST['field_command'] == 'news_submit') {
    $news_text = trim($_POST['field_news_text'] ?? '');
    if (mb_strlen($news_text) > 500) {
        $error = 'Текст новости слишком длинный (макс. 500 символов).';
    } else {
        file_put_contents($news_file, $news_text, LOCK_EX);
        $current_news = $news_text;
        $message = 'Новость успешно добавлена!';
    }
}

if (isset($_POST['field_command']) && $_POST['field_command'] == 'ip_ban_submit') {
    $ip = trim((string)($_POST['field_ip'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'Неверный IP адрес.';
    } else {
        $exists = false;
        $bans = admin_read_bans();
        foreach ($bans as $b) {
            if ($b['ip'] === $ip) { $exists = true; break; }
        }
        if ($exists) {
            $error = 'Этот IP уже в бане.';
        } else {
            admin_append_ban($ip, $user);
            log_event('admin_ip_ban', ['target_ip' => $ip, 'admin_user' => $user]);
            $message = 'IP успешно добавлен в бан.';
        }
    }
}
if (isset($_POST['field_command']) && $_POST['field_command'] == 'ip_unban_submit') {
    $ip = trim((string)($_POST['field_ip'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'Неверный IP адрес.';
    } else {
        admin_remove_ban_ip($ip);
        log_event('admin_ip_unban', ['target_ip' => $ip, 'admin_user' => $user]);
        $message = 'IP удалён из бана.';
    }
}

if (isset($_POST['field_command']) && $_POST['field_command'] == 'delete_videos_by_ip') {
    $ip = trim((string)($_POST['field_ip'] ?? ''));
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $error = 'Неверный IP адрес.';
    } else {
        $ids = admin_collect_video_ids_by_ip($ip);
        if (empty($ids)) {
            $error = 'По этому IP не найдено загруженных видео в файловом логе.';
        } else {
            $deleted = 0;
            foreach ($ids as $vid) {
                try {
                    $st = $db->prepare('SELECT id, file, preview FROM videos WHERE id = ? LIMIT 1');
                    $st->execute([(int)$vid]);
                    $row = $st->fetch(PDO::FETCH_ASSOC);
                    if (!$row) continue;
                    admin_delete_video_full($db, $row);
                    $deleted++;
                } catch (Exception $e) {
                }
            }
            log_event('admin_delete_videos_by_ip', ['target_ip' => $ip, 'deleted' => (int)$deleted, 'admin_user' => $user]);
            $message = 'Удалено видео по IP: ' . (int)$deleted . '.';
        }
    }
}

if (isset($_POST['field_command']) && $_POST['field_command'] == 'delete_channel') {
    $login = trim((string)($_POST['field_channel'] ?? ''));
    if ($login === '') {
        $error = 'Укажите логин канала.';
    } else {
        admin_delete_channel_keep_videos($db, $login);
        log_event('admin_delete_channel', ['target_channel' => $login, 'admin_user' => $user]);
        $message = 'Канал удалён (видео сохранены).';
    }
}
if (isset($_POST['field_command']) && $_POST['field_command'] == 'delete_videos_by_channel') {
    $login = trim((string)($_POST['field_channel'] ?? ''));
    if ($login === '') {
        $error = 'Укажите логин канала.';
    } else {
        $deleted = 0;
        try {
            $st = $db->prepare('SELECT id, file, preview FROM videos WHERE user = ?');
            $st->execute([$login]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                admin_delete_video_full($db, $row);
                $deleted++;
            }
            $message = 'Удалено видео канала: ' . (int)$deleted . '.';
            log_event('admin_delete_videos_by_channel', ['target_channel' => $login, 'deleted' => (int)$deleted, 'admin_user' => $user]);
        } catch (Exception $e) {
            $error = 'Ошибка удаления видео канала.';
        }
    }
}
if (isset($_POST['field_command']) && $_POST['field_command'] == 'clear_logs') {
    $log_file = admin_modlog_file();
    @file_put_contents($log_file, '', LOCK_EX);
    $message = 'Логи очищены.';
}

$bans_list = admin_read_bans();
$log_rows = admin_collect_log_rows(200);
$log_query = trim((string)($_GET['log_q'] ?? ''));
if ($log_query !== '') {
    $filtered = [];
    $needle = function_exists('mb_strtolower') ? mb_strtolower($log_query, 'UTF-8') : strtolower($log_query);
    foreach ($log_rows as $row) {
        $human = admin_human_log_line($row);
        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $hay = $human . ' ' . (string)$json;
        $hay_l = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
        if (strpos($hay_l, $needle) !== false) {
            $filtered[] = $row;
        }
    }
    $log_rows = $filtered;
}

showHeader("Админ панель");
?>

<div style="padding: 0px 5px 0px 5px;">

<div class="tableSubTitle">Админ панель</div>

<?php if ($error): ?>
	<div class="errorBox" style="margin-bottom:8px;"> <?=htmlspecialchars($error)?> </div>
<?php endif; ?>
<?php if ($message): ?>
	<div class="confirmBox" style="margin-bottom:8px;"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<form method="post" action="admin.php">
<input type="hidden" name="field_command" value="news_submit">

<table width="500" align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 0; margin-top: 10px;">
<tr>
      <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Текст новости:</b></td>
      <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
        <textarea name="field_news_text" rows="4" cols="45" maxlength="500"><?=htmlspecialchars($_POST['field_news_text'] ?? $current_news)?></textarea>
      </td>
    </tr>
    <tr>
      <td></td>
      <td style="padding-bottom:8px;" colspan="4">
        <input type="hidden" name="field_command" value="news_submit">
        <input type="submit" value="Добавить новость">
      </td>
    </tr>
</table>
</form>

<br>
<div class="tableSubTitle">Функции модерации</div>
<div class="highlight">Бан IP.</div>
<form method="post" action="admin.php" style="margin-top:8px;">
<input type="hidden" name="field_command" value="ip_ban_submit">
<table width="500" align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 0;">
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>IP:</b></td>
  <td style="padding-bottom:8px;"><input type="text" name="field_ip" value="" style="width:220px;"></td>
</tr>
<tr>
  <td></td>
  <td style="padding-bottom:8px;">
    <input type="submit" value="Забанить IP">
    <button type="submit" name="field_command" value="ip_unban_submit">Разбанить IP</button>
  </td>
</tr>
</table>
</form>

<div style="width:500px; margin:0 auto; font-size:12px; color:#333;">
  <button type="button" onclick="return adminToggle('ipBansList', this);">Показать баны</button>
  <div id="ipBansList" style="display:none; margin-top:6px;">
  <?php if (!empty($bans_list)): ?>
    <b>Текущие баны:</b><br>
    <?php foreach ($bans_list as $b): ?>
      <?=htmlspecialchars($b['ip'])?><br>
    <?php endforeach; ?>
  <?php else: ?>
    Банов IP пока нет.
  <?php endif; ?>
  </div>
</div>

<hr>
<div class="highlight">Удалить все видео по IP.</div>
<form method="post" action="admin.php" style="margin-top:8px;" onsubmit="return confirm('Удалить все видео по этому IP?');">
<input type="hidden" name="field_command" value="delete_videos_by_ip">
<table width="500" align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 0;">
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>IP:</b></td>
  <td style="padding-bottom:8px;"><input type="text" name="field_ip" value="" style="width:220px;"></td>
</tr>
<tr>
  <td></td>
  <td style="padding-bottom:8px;"><input type="submit" value="Удалить видео по IP"></td>
</tr>
</table>
</form>

<hr>
<div class="highlight">Удалить канал.</div>
<form method="post" action="admin.php" style="margin-top:8px;" onsubmit="return confirm('Удалить канал?');">
<input type="hidden" name="field_command" value="delete_channel">
<table width="500" align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 0;">
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Канал:</b></td>
  <td style="padding-bottom:8px;"><input type="text" name="field_channel" value="" style="width:220px;"></td>
</tr>
<tr>
  <td></td>
  <td style="padding-bottom:8px;">
    <input type="submit" value="Удалить канал">
  </td>
</tr>
</table>
</form>

<hr>
<div class="highlight">Удалить все видео канала.</div>
<form method="post" action="admin.php" style="margin-top:8px;" onsubmit="return confirm('Удалить все видео указанного канала?');">
<input type="hidden" name="field_command" value="delete_videos_by_channel">
<table width="500" align="center" cellpadding="0" cellspacing="0" border="0" style="border-collapse: separate; border-spacing: 0;">
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Канал:</b></td>
  <td style="padding-bottom:8px;"><input type="text" name="field_channel" value="" style="width:220px;"></td>
</tr>
<tr>
  <td></td>
  <td style="padding-bottom:8px;"><input type="submit" value="Удалить видео канала"></td>
</tr>
</table>
</form>

<hr>
<div class="highlight">Логи (последние 200).</div>
<form method="get" action="admin.php" style="width:760px; margin:0 auto 6px auto; font-size:12px;">
  Поиск по логам:
  <input type="text" name="log_q" value="<?=htmlspecialchars($log_query)?>" style="width:320px;">
  <input type="submit" value="Искать">
  <button type="button" onclick="window.location.href='admin.php'">Сброс</button>
</form>
<form method="post" action="admin.php" style="width:760px; margin:0 auto 6px auto; font-size:12px;" onsubmit="return confirm('Очистить лог-файл полностью?');">
  <input type="hidden" name="field_command" value="clear_logs">
  <input type="submit" value="Очистить логи">
</form>
<div style="width:760px; margin:0 auto; max-height:320px; overflow:auto; border:1px solid #CCC; background:#FFF; padding:6px; font-size:12px;">
  <?php if (empty($log_rows)): ?>
    Логи пусты.
  <?php else: ?>
    <?php foreach ($log_rows as $row): ?>
      <div style="border-bottom:1px dashed #DDD; padding:4px 0;">
        <b><?=htmlspecialchars(date('Y-m-d H:i:s', (int)($row['time'] ?? time())))?></b>
        | <?=htmlspecialchars(admin_human_log_line($row))?><br>
        <span style="color:#666;">UA: <?=htmlspecialchars((string)($row['ua'] ?? ''))?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>
<script type="text/javascript">
function adminToggle(id, el) {
  var d = document.getElementById ? document.getElementById(id) : document.all[id];
  if (!d) return false;
  if (d.style.display == 'none' || d.style.display === '') {
    d.style.display = 'block';
    if (el) el.innerHTML = 'Скрыть баны';
  } else {
    d.style.display = 'none';
    if (el) el.innerHTML = 'Показать баны';
  }
  return false;
}
</script>
<?php showFooter(); ?>
