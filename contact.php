<?php 
include("init.php");
include("template.php");

$error = '';
$success = '';

$subjectMap = [
	'1' => 'Вопрос о сайте',
	'2' => 'Ошибка или баг',
	'3' => 'Предложение по улучшению',
	'4' => 'Вопрос по контенту',
	'5' => 'Другое',
];

function contact_admin_logins(): array {
	$admins = @unserialize(RETROSHOW_ADMINS);
	if (!is_array($admins)) $admins = [];
	$admins = array_values(array_unique(array_filter(array_map('strval', $admins))));
	return $admins;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['field_command'] ?? '') === 'contact_submit') {
	$fromEmail = trim((string)($_POST['field_contact_email'] ?? ''));
	$subjectKey = (string)($_POST['field_contact_subject'] ?? '0');
	$message = trim((string)($_POST['field_contact_message'] ?? ''));

	if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
		$error = 'Введите корректный email.';
	} elseif (!isset($subjectMap[$subjectKey])) {
		$error = 'Выберите тему.';
	} elseif ($message === '') {
		$error = 'Введите сообщение.';
	} else {
		$ip = get_client_ip_info();
		$subjectText = $subjectMap[$subjectKey];

		$adminLogins = contact_admin_logins();
		$realFromUser = isset($_SESSION['user']) ? trim((string)$_SESSION['user']) : '';
		$fromUser = $realFromUser !== '' ? $realFromUser : 'Guest';
		
		if (in_array($fromUser, $adminLogins, true)) {
			$fromUser = 'SYSTEM';
		}

		$topic = 'Обратная связь: ' . $subjectText;
		$body =
			($realFromUser !== '' ? ("Аккаунт: " . $realFromUser . " (" . $fromEmail . ")\n") : "Почта: " . $fromEmail . "\n") .
			"IP-адрес: " . $ip['ip'] . "\n\n" .
			"Оставленное сообщение:\n " . $message;

		$sent = 0;
		foreach ($adminLogins as $toLogin) {
			$toLogin = trim((string)$toLogin);
			if ($toLogin === '') continue;
			if ($toLogin === $fromUser) continue;
			add_mail($db, $toLogin, $fromUser, $topic, $body, 'contact');
			$sent++;
		}

		log_event('contact_submit', [
			'from_email' => $fromEmail,
			'subject' => $subjectText,
			'message' => (function_exists('mb_substr') ? mb_substr($message, 0, 2000, 'UTF-8') : substr($message, 0, 2000)),
			'from_user' => $fromUser,
			'from_user_real' => $realFromUser,
			'sent_to' => $adminLogins,
			'sent_count' => $sent,
		]);

		if ($sent > 0) {
			$success = 'Сообщение отправлено администраторам в ЛС.';
			$_POST['field_contact_message'] = '';
		} else {
			$error = 'Не удалось отправить сообщение!';
		}
	}
}

showHeader("Связаться с нами");
?>

<table width="775" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;">
		
		<table width="775"  cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td style="padding: 5px 0px 5px 0px;">
				

<div class="tableSubTitle">Связаться с нами</div>

Если у вас есть какие-либо вопросы или предложения по сайту, заполните и отправьте форму ниже. Учтите, что ваше сообщение будет доставлено всем администраторам веб-ресурса. Ваш IP-адрес будет сохранён и виден администраторам, чтобы предотвратить спам-атаки, а ваша почта также будет видна только администраторам, чтобы они могли отправить ответное письмо.

<br><br>
<?php if (!empty($error)): ?><div class="errorBox"><?=$error?></div><?php endif; ?>
<?php if (!empty($success)): ?><div class="confirmBox"><?=$success?></div><?php endif; ?>
<table width="100%" cellpadding="5" cellspacing="0" border="0">
	<form method="post" action="contact.php">
	<input type="hidden" name="field_command" value="contact_submit">
	<tbody><tr>
		<td width="200" align="right"><span class="label">Ваша почта:</span></td>
		<td><input type="text" size="30" maxlength="60" name="field_contact_email" value="<?=htmlspecialchars((string)($_POST['field_contact_email'] ?? ''), ENT_QUOTES, 'UTF-8')?>"></td>
	</tr>
	<tr>
		<td align="right"><span class="label">Тема:</span></td>

		
		<td><select name="field_contact_subject">
			    <option value="0">---</option>
			    <option value="1"<?=((string)($_POST['field_contact_subject'] ?? '')==='1')?' selected="selected"':''?>>Вопрос о сайте</option>
			    <option value="2"<?=((string)($_POST['field_contact_subject'] ?? '')==='2')?' selected="selected"':''?>>Ошибка или баг</option>
			    <option value="3"<?=((string)($_POST['field_contact_subject'] ?? '')==='3')?' selected="selected"':''?>>Предложение по улучшению</option>
			    <option value="4"<?=((string)($_POST['field_contact_subject'] ?? '')==='4')?' selected="selected"':''?>>Вопрос по контенту</option>
			    <option value="5"<?=((string)($_POST['field_contact_subject'] ?? '')==='5')?' selected="selected"':''?>>Другое</option>
			</select>
		</td>
	</tr>
	<tr>
		<td align="right" valign="top"><span class="label">Сообщение:</span></td>
		<td><textarea name="field_contact_message" cols="40" rows="4"><?=htmlspecialchars((string)($_POST['field_contact_message'] ?? ''), ENT_QUOTES, 'UTF-8')?></textarea></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input type="submit" value="Отправить"></td>
	</tr>
</tbody></form></table>


				</td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>
		
		</td>
	</tr>
</tbody></table>

<?php showFooter(); ?>