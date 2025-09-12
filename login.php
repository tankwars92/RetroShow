<?php 
include("init.php"); 

$error = '';
if ($_POST) {
    $login = trim($_POST['login'] ?? '');
    $pass = trim($_POST['pass'] ?? '');

    $stmt = $db->prepare("SELECT id FROM users WHERE login = ? AND pass = ?");
    $stmt->execute([$login, $pass]);
    if ($stmt->fetch()) {
        $_SESSION['user'] = $login;
        $now = time();
        $upd = $db->prepare("UPDATE users SET last_login = ? WHERE login = ?");
        $upd->execute([$now, $login]);
        
        if (ob_get_level()) ob_end_clean();
        
        header("Location: index.php");
        exit;
    } else {
        $error = "Неверный логин или пароль.";
    }
}

include("template.php");
showHeader("Вход");
?>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;">
		
		<div id="siSignupDiv">
			<font size="3"><b>Новый пользователь RetroShow?</b></font>
			
			<p>RetroShow - это способ поделиться вашими видео с людьми, которые важны для вас. С RetroShow вы можете:</p>

			<ul>			
				<li>Загружать, тегировать и делиться видео по всему миру</li>
				<li>Просматривать тысячи оригинальных видео, загруженных участниками сообщества</li>
				<li>Находить, присоединяться и создавать видео-группы для связи с людьми со схожими интересами</li>
				<li>Настраивать свой опыт с плейлистами и подписками</li>
				<li>Интегрировать RetroShow с вашим сайтом используя встраивание видео</li>
			</ul>
				
			<font size="3"><b><a href="register.php">Зарегистрируйтесь сейчас</a> и откройте бесплатный аккаунт.</b></font>
				
			<p>Чтобы узнать больше о нашем сервисе, посетите <a href="about.php">О сайте</a>.</p>
  </div>
		
		</td>
		<td width="300">

		<div class="contentBox" style="float: right; background-color: #EEE; border: 1px solid #CCC; padding: 15px;">
			<b><font size="4" style="margin-top: 0px;">Войти</font></b>
			<br>
			<br>Войдите для доступа к вашему аккаунту.
			<br>
			<br>
			
  <?php if ($error): ?>
			<div style="background-color: #FFE6E6; border: 1px solid #FF9999; padding: 10px; margin: 10px 0px; color: #CC0000; font-size: 12px;"><?=htmlspecialchars($error)?></div>
  <?php endif; ?>
			
			<table class="dataEntryTableSmall" cellpadding="5" cellspacing="0" border="0" style="width: 100%;">
				<form name="loginForm" id="loginForm" method="post">
				<input type="hidden" name="current_form" value="loginForm">
					
				<tbody><tr>
					<td class="formLabel" style="font-weight: bold; color: #333; font-size: 12px;">Имя пользователя:</td>
					<td class="formFieldSmall"><input tabindex="1" type="text" size="20" name="login" style="width: 200px; font-size: 12px;"></td>
				</tr>
				<tr>
					<td class="formLabel" style="font-weight: bold; color: #333; font-size: 12px;">Пароль:</td>
					<td class="formFieldSmall"><input tabindex="2" type="password" size="20" name="pass" style="width: 200px; font-size: 12px;"></td>
				</tr>
				<tr>
					<td class="formLabel">&nbsp;</td>
					<td class="formFieldSmall">
						<input type="submit" name="action_login" value="Войти">
						<p class="smallText" style="font-size: 11px; margin-top: 10px;">
							<b>Забыли:</b>&nbsp;<a href="forgot_username.php">Имя</a> | <a href="forgot.php">Пароль</a>
						</p>
					</td>
				</tr>
  </form>
				</tbody>
</table>
		</div>
		
		</td>
	</tr>
</tbody></table>

<script type="text/javascript">
if (document.loginForm && document.loginForm.login) {
    document.loginForm.login.focus();
}
</script>

<?php showFooter(); ?>
