<?php
include "init.php";
include_once "template.php";

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $stmt = $db->prepare('SELECT pass FROM users WHERE login = ?');
    $stmt->execute([$user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $password !== $row['pass']) {
        $error = 'Неверный пароль!';
    } else {
        $stmt_videos = $db->prepare('SELECT id FROM videos WHERE user = ?');
        $stmt_videos->execute([$user]);
        $video_ids = [];
        while ($v = $stmt_videos->fetch(PDO::FETCH_ASSOC)) {
            $video_ids[] = intval($v['id']);
        }
		
        $db->prepare('DELETE FROM users WHERE login = ?')->execute([$user]);
        $db->prepare('DELETE FROM video_views WHERE user = ?')->execute([$user]);
        $db->prepare('DELETE FROM videos WHERE user = ?')->execute([$user]);
		
        $fav_file = __DIR__ . '/favourites/' . urlencode($user) . '.txt';
        if (file_exists($fav_file)) unlink($fav_file);
		
        $friends_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
        if (file_exists($friends_file)) unlink($friends_file);
		
        $messages_file = __DIR__ . '/messages/' . urlencode($user) . '.txt';
        if (file_exists($messages_file)) unlink($messages_file);
		
        $playlists_file = __DIR__ . '/playlists/' . urlencode($user) . '.txt';
        if (file_exists($playlists_file)) unlink($playlists_file);
		
        $profile_comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';
        if (file_exists($profile_comments_file)) unlink($profile_comments_file);
		
        $comments_dir = __DIR__ . '/comments/';
        foreach (glob($comments_dir . '*.txt') as $cfile) {
            $lines = file($cfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_filter($lines, function($l) use ($user) {
                $parts = explode('|', $l, 3);
                return !(isset($parts[1]) && $parts[1] === $user);
            });
            file_put_contents($cfile, implode("\n", $lines));
        }
		
        foreach ($video_ids as $vid) {
            $mp4 = __DIR__ . "/uploads/{$vid}.mp4";
            $jpg = __DIR__ . "/uploads/{$vid}_preview.jpg";
            if (file_exists($mp4)) unlink($mp4);
            if (file_exists($jpg)) unlink($jpg);
        }
        session_destroy();
        $success = true;
        header('Location: index.php');
        exit;
    }
}

showHeader('Удаление аккаунта');
?>
<center>
<div style="width:600px; text-align:left;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
    <tr>
      <td colspan="2">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">Удаление аккаунта</td>
            <td align="right" style="font-size:12px; color:#0033cc; font-weight:normal; padding-bottom:2px;" valign="middle"><a href="account.php" style="color:#0033cc; text-decoration:underline;">Назад к настройкам</a></td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <?php if ($error): ?>
          <div class="errorBox" style="margin-bottom:8px;"> <?=htmlspecialchars($error)?> </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="confirmBox" style="margin-bottom:8px;">Аккаунт успешно удалён!</div>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td colspan="2"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">
  <tr>
    <td height="1" bgcolor="#CCCCCC"></td>
  </tr>
</table></td>
    </tr>
  </table>
  <div style="color:#c00; font-size:13px; margin-bottom:10px; margin-top:6px;">
    Удаление аккаунта приведёт к безвозвратному удалению всех ваших данных (видео, комментарии, избранное и т.д.) с RetroShow. Это действие необратимо.
  </div>
  <form method="post" action="delete_account.php">
    <label for="password" style="font-size:13px;">Введите ваш пароль:</label>
    <input type="password" name="password" id="password" style="font-size:13px; border:1px solid #ccc; padding:2px 6px; margin-left:8px;" maxlength="32">
    <br><br>
    <input type="submit" value="Удалить аккаунт" style="font-size:13px; width:130px;">
  </form>
</div>
</center>
<?php
showFooter(); 