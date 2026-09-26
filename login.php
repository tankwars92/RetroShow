<?php
include 'init.php';
include 'template.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$cmd = (string)($_POST['field_command'] ?? '');
	if ($cmd === 'login_submit') {
		$login = trim((string)($_POST['field_login_username'] ?? ''));
		$pass = (string)($_POST['field_login_password'] ?? '');

		if ($login === '' || $pass === '') {
			$error = t('Введите имя пользователя и пароль.');
		} else {
			$stmt = $db->prepare("SELECT id FROM users WHERE login = ? AND pass = ?");
			$stmt->execute([$login, $pass]);
			if ($stmt->fetch()) {
				$_SESSION['user'] = $login;
				$now = time();
				$upd = $db->prepare("UPDATE users SET last_login = ? WHERE login = ?");
				$upd->execute([$now, $login]);

				if (ob_get_level()) {
					ob_end_clean();
				}
				header("Location: index.php");
				exit;
			}
			$error = t('Неверный логин или пароль.');
		}
	}
}

showHeader("Вход");
?>

<?php if ($error): ?>
	<div class="errorBox"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<div style="padding: 0px 5px 0px 5px;">


<div class="tableSubTitle"><?= t('Вход') ?></div>

<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
	<tr valign="top">
		<td style="padding-right: 15px;">
		
		
		<span class="highlight"><?= t('Что такое RetroShow?') ?></span>

		<?= t('RetroShow — это способ донести ваши видео до людей, которые важны для вас. С RetroShow вы можете:') ?>
		
		<ul>
		<li><?= t('Показывать любимые видео всему миру') ?></li>
		<li><?= t('Делиться видео, снятыми на камеру или телефон') ?></li>
		<li><?= t('Приватно показывать видео друзьям и семье по всему миру') ?></li>
		<li><?= t('... и многое другое!') ?></li></ul>

		<br><span class="highlight"><a href="register.php"><?= t('Зарегистрируйтесь сейчас') ?></a> <?= t('и откройте бесплатный аккаунт.') ?></span>
		<br><br><br>
		
		<?= t('Чтобы узнать больше о нашем сервисе, посетите раздел') ?> <a href="help.php"><?= t('Помощь') ?></a>.<br><br><br>
		</td>
		<td width="300">
		
		<table width="100%" cellpadding="5" cellspacing="0" bgcolor="#E5ECF9">
			<form method="post" name="loginForm" id="loginForm" action="login.php">
			<input type="hidden" name="field_command" value="login_submit">
				<tr>
					<td align="center" colspan="2"><div style="font-size: 14px; font-weight: bold; color:#003366; margin-bottom: 5px; padding-top: 5px;"><?= t('Вход в RetroShow') ?></div></td>
				</tr>
				<tr>
					<td align="right"><span class="label"><?= t('Имя пользователя:') ?></span></td>
					<td><input tabindex="1" type="text" name="field_login_username" value="" style="width: 135px;"></td>
				</tr>
				<tr>
					<td align="right"><span class="label"><?= t('Пароль:') ?></span></td>
					<td><input tabindex="2" type="password" name="field_login_password" style="width: 135px;"></td>
				</tr>
				<tr>
					<td align="right"><span class="label">&nbsp;</span></td>
					<td><input type="submit" value="<?= htmlspecialchars(t('Войти'), ENT_QUOTES, 'UTF-8') ?>"></td>
				</tr>
				<tr>
					<td align="center" colspan="2"><a href="forgot.php"><?= t('Забыли пароль?') ?></a><br><br></td>
				</tr>
			</form>
		</table>

		<script language="javascript">
			onLoadFunctionList.push(function(){ document.loginForm.field_login_username.focus(); });
		</script>
			
		</td>
	</tr>
</table>
		</div>

<?php showFooter(); ?>
