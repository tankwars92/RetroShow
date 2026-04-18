<?php
include 'init.php';
include 'template.php';

function register_generate_captcha(): void
{
	$a = rand(1, 9);
	$b = rand(1, 9);
	$_SESSION['register_captcha_a'] = $a;
	$_SESSION['register_captcha_b'] = $b;
	$_SESSION['register_captcha_answer'] = $a + $b;
}

$error = '';
$email_prefill = '';
$user_prefill = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['field_command'] ?? '') === 'signup_submit') {
	$email = trim((string)($_POST['field_signup_email'] ?? ''));
	$username = trim((string)($_POST['field_signup_username'] ?? ''));
	$password1 = (string)($_POST['field_signup_password_1'] ?? '');
	$password2 = (string)($_POST['field_signup_password_2'] ?? '');
	$captcha_answer = trim((string)($_POST['captcha'] ?? ''));
	$expected = isset($_SESSION['register_captcha_answer']) ? (string)$_SESSION['register_captcha_answer'] : '';

	$email_prefill = $email;
	$user_prefill = $username;

	if ($captcha_answer === '' || $expected === '' || (string)intval($captcha_answer) !== $expected) {
		$error = 'Неверный ответ на проверочный вопрос.';
	} elseif ($email === '' || $username === '' || $password1 === '' || $password2 === '') {
		$error = 'Пожалуйста, заполните все обязательные поля.';
	} elseif ($password1 !== $password2) {
		$error = 'Пароли не совпадают.';
	} elseif (strlen($password1) < 6) {
		$error = 'Пароль должен содержать минимум 6 символов.';
	} else {
		try {
			$stmt = $db->prepare('SELECT 1 FROM users WHERE login = ? LIMIT 1');
			$stmt->execute([$username]);
			if ($stmt->fetchColumn()) {
				$error = 'Пользователь с таким именем уже существует.';
			}
		} catch (Exception $e) {
			$error = 'Ошибка базы данных.';
		}

		if ($error === '') {
			try {
				$stmt = $db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
				$stmt->execute([$email]);
				if ($stmt->fetchColumn()) {
					$error = 'Пользователь с таким email уже существует.';
				}
			} catch (Exception $e) {
				$error = 'Ошибка базы данных.';
			}
		}

		if ($error === '') {
			try {
				$now = time();
				$stmt = $db->prepare("
					INSERT INTO users
					(login, pass, email, country, gender, birthday_mon, birthday_day, birthday_yr, signup_time, last_login)
					VALUES (?, ?, ?, '', '', '', '', '', ?, ?)
				");
				$stmt->execute([$username, $password1, $email, $now, $now]);
				$_SESSION['user'] = $username;
				header('Location: index.php');
				exit;
			} catch (Exception $e) {
				$error = 'Не удалось создать аккаунт.';
			}
		}
	}
}

register_generate_captcha();
$captcha_a = (int)($_SESSION['register_captcha_a'] ?? 0);
$captcha_b = (int)($_SESSION['register_captcha_b'] ?? 0);

showHeader('Регистрация');
?>

<?php if ($error !== ''): ?>
	<div class="errorBox">
		<?= htmlspecialchars($error) ?>
	</div>
<?php endif; ?>

<div style="padding: 0px 5px 0px 5px;">




<script>
function formValidator()
{
	/*
	var field_signup_email = document.theForm.field_signup_email;
	var field_signup_username = document.theForm.field_signup_username;
	var field_signup_password_1 = document.theForm.field_signup_password_1;
	var field_signup_password_2 = document.theForm.field_signup_password_2;
	*/

	var signup_button = document.theForm.signup_button;

	signup_button.disabled='true';
	signup_button.value='Пожалуйста, подождите...';
}
</script>

<div class="tableSubTitle">Регистрация</div>

Пожалуйста, введите данные вашего аккаунта ниже. Все поля обязательны.<br><br>
<table width="100%" cellpadding="5" cellspacing="0" border="0">
<form method="post" name="theForm" id="theForm" onsubmit="return formValidator();" action="register.php">


<input type="hidden" name="field_command" value="signup_submit">

	<tbody><tr>
		<td width="200" align="right"><span class="label">Email адрес:</span></td>
		<td><input type="text" size="30" maxlength="60" name="field_signup_email" value="<?=htmlspecialchars($email_prefill, ENT_QUOTES, 'UTF-8')?>"></td>
	</tr>
	<tr>
		<td align="right"><span class="label">Имя пользователя:</span></td>
		<td><input type="text" size="20" maxlength="20" name="field_signup_username" value="<?=htmlspecialchars($user_prefill, ENT_QUOTES, 'UTF-8')?>"></td>
	</tr>
	<tr>
		<td align="right"><span class="label">Пароль:</span></td>
		<td><input type="password" size="20" maxlength="20" name="field_signup_password_1" value=""></td>
	</tr>
	<tr>
		<td align="right"><span class="label">Повторите пароль:</span></td>
		<td><input type="password" size="20" maxlength="20" name="field_signup_password_2" value=""></td>
	</tr>
	<tr>
		<td align="right"><span class="label">Проверка:</span></td>
		<td>
			<span style="font-size:12px;"><?= (int)$captcha_a ?> + <?= (int)$captcha_b ?> = </span>
			<input type="text" size="4" maxlength="2" name="captcha" value="<?= htmlspecialchars((string)($_POST['captcha'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
			<br><i>(защита от спам-ботов)</i>
		</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><br>- Я подтверждаю, что мне больше 13 лет.
		<br>- Я согласен с <a href="help.php?p=terms" target="_blank">условиями использования</a> и <a href="help.php?p=privacy" target="_blank">политикой конфиденциальности</a>.</td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input name="signup_button" type="submit" value="Зарегистрироваться"></td>
	</tr>
	
	<tr>
		<td>&nbsp;</td>
		<td><br>Или <a href="index.php">вернуться на главную</a>.</td>
	</tr>
</tbody></table>
</form>

		</div>

<?php showFooter(); ?>
