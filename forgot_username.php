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

<?php if ($error): ?>
    <div class="errorBox"><?=htmlspecialchars($error)?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="confirmBox"><?=htmlspecialchars($message)?></div>
<?php endif; ?>

<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr valign="top">
        <td style="padding-right: 15px;">
            <span class="highlight">Забыли ваше имя пользователя? Не проблема!</span>
            <br><br>
            Просто введите E-Mail адрес, с которым вы регистрировались, и мы покажем вам ваше имя пользователя. 
        </td>
        <td width="300">
            <table width="100%" cellpadding="5" cellspacing="0" bgcolor="#E5ECF9">
                <form method="post" action="forgot_username.php">
                    <input type="hidden" name="field_command" value="forgot_submit">
                    <tr>
                        <td align="right"><span class="label">E-Mail адрес:</span></td>
                        <td><input type="text" size="20" name="field_login_email" value="<?=htmlspecialchars($_POST['field_login_email'] ?? '')?>"></td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td><input type="submit" value="Получить имя!"></td>
                    </tr>
                </form>
            </table>
        </td>
    </tr>
</table>

<?php showFooter(); ?>