<?php
include_once "init.php";
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
include_once "template.php";

showHeader("Пригласить друзей");

$sent = false;
$sent_count = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emails = array();
    if (!empty($_POST['email_family'])) {
        foreach ($_POST['email_family'] as $idx => $email) {
            $email = trim($email);
            if ($email != '') {
                $name = isset($_POST['fname_family'][$idx]) ? trim($_POST['fname_family'][$idx]) : '';
                $emails[] = array('email' => $email, 'name' => $name);
            }
        }
    }
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
    $content = $personal;
	
    foreach ($emails as $e) {
        $receiver = urlencode($e['email']);
        $msg = urlencode($content);

        @file_get_contents("http://localhost:1305/?receiver=$receiver&content=$msg");
        $sent_count++;
    }
    $sent = true;
}
?>
<style type="text/css">
.invite-title { color: #cc6633; font-weight: bold; font-size: 15px; margin-bottom: 6px; }
.invite-section { margin-bottom: 18px; }
.invite-label { font-size: 13px; color: #333; width: 110px; display: inline-block; }
.invite-input { width: 220px; font-size: 13px; }
.invite-name { width: 180px; font-size: 13px; }
.invite-message-box { background: #BBCCEE; border: 1px dashed #000066; padding: 10px; margin-top: 10px; }
.invite-message-label { font-size: 13px; color: #333; }
.invite-textarea { width: 330px; height: 70px; font-size: 13px; }
.invite-btn { font-size: 13px; }
</style>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px;">
<tr><td>
<div class="invite-title">Пригласить друзей</div>
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;"><tr><td height="1" bgcolor="#CCCCCC"></td></tr></table>
<?php if ($sent): ?>
  <div class="confirmBox">Приглашения отправлены! (<?= $sent_count ?>)</div>
<?php endif; ?>
<div style="font-size:12px; color:#444; margin-bottom:8px;">RetroShow становится интереснее с друзьями!<br>
Хотите поделиться семейными или праздничными видео? Пригласите родственников присоединиться!</div>

<form method="post" action="my_friends_invite.php">
<div class="invite-section">
<?php for ($i=0; $i<4; $i++): ?>
  <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:4px;"><tr>
    <td style="padding-right:8px;"><b>E-mail:</b></td>
    <td><input type="text" name="email_family[]" class="invite-input"></td>
    <td style="padding-right:8px; padding-left:8px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Имя:</b></td>
    <td><input type="text" name="fname_family[]" class="invite-name"></td>
  </tr></table>
<?php endfor; ?>
</div>

Хотите поделиться забавными видео с коллегами и друзьями? Пригласите их присоединиться!<br><br>
<div class="invite-section">
<?php for ($i=0; $i<4; $i++): ?>
  <table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:4px;"><tr>
    <td style="padding-right:8px;"><b>E-mail:</b></td>
    <td><input type="text" name="email_friends[]" class="invite-input"></td>
    <td style="padding-right:8px; padding-left:8px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>Имя:</b></td>
    <td><input type="text" name="fname_friends[]" class="invite-name"></td>
  </tr></table>
<?php endfor; ?>
</div>

<table cellpadding="0" cellspacing="0" border="0" style="margin-bottom:12px;"><tr>
  <td style="vertical-align:top; font-weight:bold; font-size:13px;">Сообщение:</td>
  <td style="vertical-align:top; padding-left:8px;">
    <div class="invite-message-box" style="width:500px; margin-top:0; padding-top:0;">
      <br>
      Здравствуйте,<br>
      <br>
      RetroShow — отличный сайт для обмена и хранения личных видео. Я использую RetroShow, чтобы делиться видео с друзьями и семьёй. Я бы хотел добавить вас в список людей, с которыми могу делиться своими видео.<br>
      <br>
      Ваше личное сообщение:<br>
      <textarea name="personal_message" class="invite-textarea">Вы слышали о RetroShow? Мне очень нравится этот сайт.</textarea><br>
      <br>
      Спасибо,<br>
      <?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']) : "ваше_имя"; ?>
    </div>
  </td>
</tr></table>

<div style="margin-top:10px; margin-left:83px;">
  <input type="submit" value="Отправить приглашения" class="invite-btn">
</div>
</form>
</td></tr>
</table><?php showFooter(); ?> 
