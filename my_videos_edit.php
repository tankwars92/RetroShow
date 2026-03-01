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

$video_id = 0;
if (isset($_GET['id'])) {
    $video_id = intval($_GET['id']);
} elseif (isset($_GET['video_id'])) {
    $video_id = intval($_GET['video_id']);
}

if ($video_id <= 0) {
    header('Location: index.php?error=video_not_allowed');
    exit;
}

try {
    $stmt = $db->prepare('SELECT id, title, description, tags, private, user FROM videos WHERE id = ?');
    $stmt->execute([$video_id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $video = false;
}

if (!$video || $video['user'] !== $current_user) {
    header('Location: index.php?error=video_not_allowed');
    exit;
}

$title = $video['title'];
$description = $video['description'];
$tags = $video['tags'];
$broadcast = $video['private'] ? 'private' : 'public';

function normalize_tags($tags) {
    $tags = trim($tags ?? '');
    $parts = preg_split('/\s+/', $tags, -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = normalize_tags($_POST['tags'] ?? '');
    $broadcast = $_POST['broadcast'] ?? 'public';

    if ($title === '') {
        $error = 'Введите название видео.';
    } elseif ($tags === '') {
        $error = 'Введите хотя бы один тег!';
    } elseif (mb_strlen($description) > 5000) {
        $error = 'Описание не должно превышать 5000 символов.';
    } else {
        $is_private = ($broadcast === 'private') ? 1 : 0;
        try {
            $stmt = $db->prepare('UPDATE videos SET title = ?, description = ?, tags = ?, private = ? WHERE id = ? AND user = ?');
            if ($stmt->execute([$title, $description, $tags, $is_private, $video_id, $current_user])) {
                $success = true;
                $video['title'] = $title;
                $video['description'] = $description;
                $video['tags'] = $tags;
                $video['private'] = $is_private;
            } else {
                $error = 'Ошибка при сохранении изменений.';
            }
        } catch (Exception $e) {
            $error = 'Ошибка при сохранении изменений.';
        }
    }
}

showHeader('Редактирование видео');
?>

<center>
<div style="width:600px; text-align:left;">
  <form method="post" action="my_videos_edit.php?id=<?=intval($video_id)?>" style="margin:0;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
      <tr>
        <td colspan="2">
          <table width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
              <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">
                Редактирование видео
              </td>
              <td align="right" style="font-size:12px; font-weight:normal; padding-bottom:2px;" valign="middle">
                <a href="delete_video.php?id=<?=intval($video_id)?>" style="color:#c00; text-decoration:underline;"><b>Удалить видео</b></a>
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
          <?php if ($success): ?>
            <div class="confirmBox" style="margin-bottom:8px;">Изменения успешно сохранены.</div>
          <?php endif; ?>
        </td>
      </tr>

      <tr>
        <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Название:</b></td>
        <td style="font-size:13px; color:#222; padding-bottom:8px;">
          <input type="text" name="title" value="<?=htmlspecialchars($title)?>" style="width: 350px; font-size: 13px;">
        </td>
      </tr>

      <tr>
        <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Описание:</b></td>
        <td style="font-size:13px; color:#222; padding-bottom:8px;">
          <textarea name="description" rows="6" style="width: 350px; font-size: 13px;"><?=htmlspecialchars($description)?></textarea>
        </td>
      </tr>

      <tr>
        <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Теги:</b></td>
        <td style="font-size:13px; color:#222; padding-bottom:8px;">
          <input type="text" name="tags" value="<?=htmlspecialchars($tags)?>" style="width: 350px; font-size: 13px;">
          <br>
          <span class="smallText">
            Введите один или несколько тегов, разделённых пробелами. Например: <code>серфинг пляж волны</code>.
          </span>
        </td>
      </tr>

      <tr>
        <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Показ:</b></td>
        <td style="font-size:13px; color:#222; padding-bottom:8px;">
          <label>
            <input type="radio" name="broadcast" value="public" <?= $broadcast === 'public' ? 'checked' : '' ?>>
            <b>Публично</b>: видео будет доступно всем.
          </label>
          <br>
          <label>
            <input type="radio" name="broadcast" value="private" <?= $broadcast === 'private' ? 'checked' : '' ?>>
            <b>Приватно</b>: видео будет доступно только по ссылке.
          </label>
        </td>
      </tr>

      <tr>
        <td></td>
        <td style="padding-top:10px;">
          <input type="submit" value="Сохранить изменения">
        </td>
      </tr>
    </table>
  </form>
</div>
</center>

<?php
showFooter();
