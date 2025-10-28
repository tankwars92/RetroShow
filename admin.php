<?php 
include("init.php");
include("template.php");

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$admin_username = "BitByByByte"; // !!! ЗАМЕНИТЕ НА СВОЁ ИМЯ ПОЛЬЗОВАТЕЛЯ !!!

if (!$user) {
    header('Location: login.php');
    exit;
}

if ($user !== 'BitByByte') {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

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
</div>
<?php showFooter(); ?>
