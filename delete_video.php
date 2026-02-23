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

$video_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($video_id <= 0) {
    showHeader('Удаление видео');
    echo '<div class="errorBox">Видео не найдено.</div>';
    echo '<div><a href="channel.php?user='.urlencode($current_user).'&tab=videos">Вернуться к моим видео</a></div>';
    showFooter();
    exit;
}

try {
    $stmt = $db->prepare('SELECT id, title, file, preview, user FROM videos WHERE id = ?');
    $stmt->execute([$video_id]);
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

        $file = $video['file'];
        $preview = $video['preview'];

        $stmt = $db->prepare('DELETE FROM comments WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM ratings WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM video_views WHERE video_id = ?');
        $stmt->execute([$video_id]);

        $stmt = $db->prepare('DELETE FROM videos WHERE id = ? AND user = ?');
        $stmt->execute([$video_id, $current_user]);

        $db->commit();

        $comments_file = __DIR__ . '/comments/' . $video_id . '.txt';
        if (file_exists($comments_file)) {
            @unlink($comments_file);
        }

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

        $fav_dir = __DIR__ . '/favourites';
        if (is_dir($fav_dir)) {
            foreach (glob($fav_dir . '/*.txt') as $fav_file) {
                $lines = file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $new_lines = array_filter($lines, function($line) use ($video_id) {
                    return trim($line) !== (string)$video_id;
                });
                if (count($new_lines) !== count($lines)) {
                    file_put_contents($fav_file, implode("\n", $new_lines));
                }
            }
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
              <a href="my_videos_edit.php?id=<?=intval($video_id)?>" style="color:#0033cc; text-decoration:underline;">Назад к редактированию</a>
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

  <form method="post" action="delete_video.php?id=<?=intval($video_id)?>">
    <input type="hidden" name="confirm" value="yes">
    <input type="submit" value="Удалить видео" style="font-size:13px; width:130px;">
  </form>
</div>
</center>

<?php
showFooter();

