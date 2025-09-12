<?php 
include("init.php");
include("template.php");

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

if ($user) {
    header('Location: account.php');
    exit;
}

$message = '';
$error = '';

if (isset($_POST['field_command']) && $_POST['field_command'] == 'forgot_submit') {
    $email = trim($_POST['field_login_email'] ?? '');
    
    if (empty($email)) {
        $error = 'Пожалуйста, введите ваш email адрес.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Пожалуйста, введите корректный email адрес.';
    } else {
        $stmt = $db->prepare("SELECT login FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $username = $stmt->fetchColumn();
        
        if ($username) {
            $message = 'Ваше имя пользователя: ' . htmlspecialchars($username) . '.';
        } else {
            $error = 'Аккаунт с таким E-Mail адресом не найден.';
        }
    }
}

showHeader("Забыли имя пользователя");
?>
<div class="tableSubTitle">Забыли имя пользователя</div>

<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
    <?php if ($error): ?>
<div class="errorBox"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<?php if ($message): ?>
<div class="confirmBox"><?=htmlspecialchars($message)?></div>
<?php endif; ?>
		<td style="padding-right: 15px;">
		<span class="highlight">Забыли ваше имя пользователя? Не проблема!</span>
		
		<br><br>

	

		Просто введите E-Mail адрес, с которым вы регистрировались, и мы покажем вам ваше имя пользователя. 

		

		</td>
		<td width="280">
		
		<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#E5ECF9">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td align="center">
				
		<table width="100%" cellpadding="5" cellspacing="0" border="0">
			<form method="post" action="forgot_username.php">
			<input type="hidden" name="field_command" value="forgot_submit">
				<tbody><tr>
					<td align="right"><span class="label">E-Mail адрес:</span></td>
					<td><input type="text" size="20" name="field_login_email" value="<?=htmlspecialchars($_POST['field_login_email'] ?? '')?>"></td>
				</tr>
				<tr>
					<td align="right"><span class="label">&nbsp;</span></td>
					<td><input type="submit" value="Получить имя!"><br><br></td>
				</tr>
			
			</tbody></table>
			</form>
			
				</td>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
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

</div>
<?php showFooter(); ?>