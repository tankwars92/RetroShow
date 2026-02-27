<?php
include "init.php";
include "template.php";

$error = '';
$success = '';
$can_change = false;

$login = isset($_GET['login']) ? trim($_GET['login']) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $token = trim($_POST['token'] ?? '');
    $pass1 = $_POST['password1'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($login === '' || $token === '') {
        $error = "Неверная ссылка для смены пароля.";
    } elseif ($pass1 === '' || $pass2 === '') {
        $error = "Пожалуйста, введите новый пароль два раза.";
    } elseif ($pass1 !== $pass2) {
        $error = "Пароли не совпадают.";
    } elseif (strlen($pass1) < 6) {
        $error = "Пароль должен содержать минимум 6 символов.";
    } else {
        $stmt = $db->prepare("SELECT login, reset_token, reset_token_expires FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();
        if (!$user || empty($user['reset_token']) || $user['reset_token'] !== $token || empty($user['reset_token_expires']) || (int)$user['reset_token_expires'] < $now) {
            $error = "Ссылка для смены пароля недействительна или устарела.";
        } else {
            $can_change = true;
            $upd = $db->prepare("UPDATE users SET pass = ?, reset_token = NULL, reset_token_expires = NULL WHERE login = ?");
            $upd->execute([$pass1, $login]);
            $success = "Пароль успешно изменён. Теперь вы можете войти, используя новый пароль.";
        }
    }
} else {
    if ($login === '' || $token === '') {
        $error = "Неверная ссылка для смены пароля.";
    } else {
        $stmt = $db->prepare("SELECT login, reset_token, reset_token_expires FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = time();
        if (!$user || empty($user['reset_token']) || $user['reset_token'] !== $token || empty($user['reset_token_expires']) || (int)$user['reset_token_expires'] < $now) {
            $error = "Ссылка для смены пароля недействительна или устарела.";
        } else {
            $can_change = true;
        }
    }
}

showHeader("Смена пароля");
?>

<div class="tableSubTitle">Смена пароля</div>

<?php if ($error): ?>
    <div class="errorBox"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="confirmBox"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($can_change && !$success): ?>
<form method="post" action="reset_password.php" style="margin:0;">
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
    <tr>
    <td width="180" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Новый пароль:</b></td>
    <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
        <input type="hidden" name="login" value="<?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <input type="password" name="password1" maxlength="64" style="width:200px;">
    </td>
    </tr>
    <tr>
    <td width="180" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Повторите новый пароль:</b></td>
    <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
        <input type="password" name="password2" maxlength="64" style="width:200px;">
    </td>
    </tr>
    <tr>
    <td></td>
    <td style="padding-bottom:8px;" colspan="4">
        <input type="submit" value="Сохранить новый пароль">
    </td>
    </tr>
</table>
</form>
<?php endif; ?>

<?php showFooter(); ?>

