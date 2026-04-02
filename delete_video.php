<?php
include "init.php";
include_once "template.php";

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$current_user = $_SESSION['user'];
$error = '';
$success = false;

$public_id = video_public_id_from_get();
if ($public_id === '') {
    showHeader('Удаление видео');
    echo '<div class="errorBox">Видео не найдено.</div>';
    echo '<div><a href="channel.php?user='.urlencode($current_user).'&tab=videos">Вернуться к моим видео</a></div>';
    showFooter();
    exit;
}

try {
    $stmt = $db->prepare('SELECT id, public_id, title, file, preview, user FROM videos WHERE public_id = ?');
    $stmt->execute([$public_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $video = false;
}

if (!$video || $video['user'] !== $current_user) {
    showHeader('Удаление видео');
    echo '<div class="errorBox">Видео не найдено или у вас нет прав для его удаления.</div>';
    echo '<div><a href="channel.php?user='.urlencode($current_user).'&tab=videos">Вернуться к моим видео</a></div>';
    showFooter();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    try {
        $db->beginTransaction();

        $video_id = (int)$video['id'];
        $file = $video['file'];
        $preview = $video['preview'];

        $stmt = $db->prepare('DELETE FROM comments WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM ratings WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM video_views WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM videos WHERE public_id = ? AND user = ?');
        $stmt->execute([$public_id, $current_user]);

        $db->commit();

        $duration_cache = __DIR__ . '/uploads/' . $video_id . '_duration.txt';
        if (file_exists($duration_cache)) {
            @unlink($duration_cache);
        }

        if (!empty($file) && file_exists($file)) {
            @unlink($file);
        } else {
            $mp4 = __DIR__ . '/uploads/' . $video_id . '.mp4';
            if (file_exists($mp4)) {
                @unlink($mp4);
            }
        }

        if (!empty($preview) && file_exists($preview)) {
            @unlink($preview);
        } else {
            $jpg = __DIR__ . '/uploads/' . $video_id . '_preview.jpg';
            if (file_exists($jpg)) {
                @unlink($jpg);
            }
        }

        try {
            $db->prepare('DELETE FROM user_favourites WHERE video_id = ?')->execute([$video_id]);
        } catch (Exception $e) {
        }

        header('Location: channel.php?user='.urlencode($current_user).'&tab=videos');
        exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'Ошибка при удалении видео.';
    }
}

showHeader('Удаление видео');
?>

<center>
<div style="width:600px; text-align:left;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
    <tr>
      <td colspan="2">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">
              Удаление видео
            </td>
            <td align="right" style="font-size:12px; color:#0033cc; font-weight:normal; padding-bottom:2px;" valign="middle">
              <a href="my_videos_edit.php?id=<?=urlencode($public_id)?>" style="color:#0033cc; text-decoration:underline;">Назад к редактированию</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;">
          <tr><td height="1" bgcolor="#CCCCCC"></td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <?php if ($error): ?>
          <div class="errorBox" style="margin-bottom:8px;"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <div style="color:#c00; font-size:13px; margin-bottom:10px; margin-top:6px;">
    Удаление этого видео приведёт к безвозвратному удалению файла видео, его обложки, комментариев, оценок, просмотров и ссылок в избранном.
    Это действие необратимо.
  </div>

  <div style="font-size:13px; margin-bottom:10px;">
    Вы действительно хотите удалить видео "<b><?=htmlspecialchars($video['title'])?></b>"?
  </div>

  <form method="post" action="delete_video.php?id=<?=urlencode($public_id)?>">
    <input type="hidden" name="confirm" value="yes">
    <input type="submit" value="Удалить видео" style="font-size:13px; width:130px;">
  </form>
</div>
</center>

<?php
showFooter();

