<?php
include_once "init.php";
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include_once "config.php";
include_once __DIR__ . "/lib/mailer.php";
include_once "template.php";

showHeader("Пригласить друзей");

$sent = false;
$sent_count = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emails = array();
    if (!empty($_POST['email_friends'])) {
        foreach ($_POST['email_friends'] as $idx => $email) {
            $email = trim($email);
            if ($email != '') {
                $name = isset($_POST['fname_friends'][$idx]) ? trim($_POST['fname_friends'][$idx]) : '';
                $emails[] = array('email' => $email, 'name' => $name);
            }
        }
    }
    
    $user = isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : "ваше_имя";
    $personal = isset($_POST['personal_message']) ? trim($_POST['personal_message']) : '';

    $subject = "RetroShow";
    $htmlTemplate = '
<img src="http://retroshow.hoho.ws/img/logo_sm.png" vspace="12" alt="RetroShow"><br>
RetroShow - это отличный сайт для обмена и хранения личных видео. Я использую
<br>RetroShow, чтобы делиться видео с друзьями и семьёй. Я бы хотел 
<br>добавить вас в список людей, с которыми могу делиться своими 
<br>видео.
<br><a href="http://retroshow.hoho.ws/">{link_text}</a>
<p><i>RetroShow - Broadcast Yourself.</i><br><br>
<center>
<div style="padding: 2px; padding-left: 7px; padding-top: 0px; margin-top: 10px; background-color: #E5ECF9; border-top: 1px dashed #3366CC; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: bold;">
&nbsp;</div><br>
Copyright © 2026 RetroShow, LLC
';

    foreach ($emails as $e) {
        $to_email = $e['email'];
        $to_name  = $e['name'] !== '' ? $e['name'] : $to_email;

        $link_text = $personal !== '' ? $personal : 'Перейти на RetroShow';
        $safe_link_text = htmlspecialchars($link_text, ENT_QUOTES, 'UTF-8');
        $body = str_replace('{link_text}', $safe_link_text, $htmlTemplate);

        if (send_smtp_email_advanced($to_email, $to_name, $subject, $body, true)) {
            $sent_count++;
        }
    }
    $sent = true;
}
?>
<style type="text/css">
.invite-section { margin-bottom: 18px; }
.invite-label { font-size: 13px; color: #333; width: 110px; display: inline-block; }
.invite-input { width: 220px; font-size: 13px; }
.invite-name { width: 180px; font-size: 13px; }
.invite-message-box { background: #BBCCEE; border: 1px dashed #000066; padding: 10px; margin-top: 10px; }
.invite-message-label { font-size: 13px; color: #333; }
.invite-textarea { width: 330px; height: 70px; font-size: 13px; }
.invite-btn { font-size: 13px; }
.formHighlight {
	background-image: url(/img/table_results_selected_bg.gif);
	background-repeat: repeat-x;
	background-color: #FFFFCC;
	background-position: left top;
	border: 1px dashed #CCCC66;
	padding: 7px;
	padding-bottom: 10px;
	margin-bottom: 5px;
}
</style>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px;">
<tr><td>
<div class="tableSubTitle"><?= t('Пригласить друзей') ?></div>
<?php if ($sent && $sent_count > 0) { ?>
  <div class="confirmBox"><?= t('Приглашения отправлены!') ?> (<?= $sent_count ?>)</div>
<?php } elseif (!empty($_POST['email_family']) || !empty($_POST['email_friends'])) { ?>
  <div class="errorBox"><?= t('Приглашения не отправлены! Попробуйте еще раз.') ?></div>
<?php } ?>
<div style="font-size:12px; color:#444; margin-bottom:8px;"><?= t('RetroShow становится интереснее с друзьями!') ?><br><br>
<?= t('Хотите поделиться интересными или забавными видео с коллегами и друзьями? Пригласите их присоединиться!') ?></div>

<form method="post" action="my_friends_invite.php">
<table cellspacing="5" cellpadding="0" border="0" id="table5">
<tbody>

<tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr><tr>
  <td align="right"><span class="label"><nobr>Email:</nobr></span></td>
  <td>
    <input maxlength="60" size="30" name="email_friends[]">
    <span class="label" style="margin-left:3em"><nobr><?= t('Имя:') ?></nobr></span>
    <input type="text" name="fname_friends[]" class="invite-input">
  </td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
</table>

<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;"><tr>
  <td style="vertical-align:top; font-weight:bold; font-size:13px;"><?= t('Сообщение:') ?></td>
  <td style="vertical-align:top; padding-left:8px;">
    <div class="formHighlight" style="width:500px; margin-top:0; padding-top:0;">
      <br>
      <?= t('Здравствуйте,') ?><br>
      <br>
      <?= t('RetroShow - это отличный сайт для обмена и хранения личных видео. Я использую RetroShow, чтобы делиться видео с друзьями и семьёй. Я бы хотел добавить вас в список людей, с которыми могу делиться своими видео.') ?><br>
      <br>
      <?= t('Ваше личное сообщение:') ?><br>
      <textarea name="personal_message" class="invite-textarea"><?= t('Вы слышали про RetroShow? Мне очень нравится этот сайт.') ?></textarea><br>
      <br>
      <?= t('Спасибо,') ?><br>
      <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : t('ваше_имя'); ?>
    </div>
  </td>
</tr></table>

<div style="margin-top:10px; margin-left:83px;">
  <input type="submit" value="<?= htmlspecialchars(t('Отправить приглашения'), ENT_QUOTES, 'UTF-8') ?>" class="invite-btn">
</div>
</form>
</td></tr>
</table><?php showFooter(); ?> 