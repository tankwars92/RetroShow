<?php
include "init.php";
include "template.php";
include __DIR__ . "/lib/mailer.php";

if (!empty($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

function forgot_generate_token()
{
    return md5(uniqid(mt_rand(), true));
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['field_login_username'])) {
    $login = trim($_POST['field_login_username']);

    if ($login === '') {
        $error = "Пожалуйста, введите имя пользователя.";
    } else {
        $stmt = $db->prepare("SELECT login, email FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Такой пользователь не найден.";
        } elseif (empty($user['email'])) {
            $error = "У этого пользователя не указан email, восстановить пароль невозможно.";
        } else {
            $token = forgot_generate_token();
            $expires = time() + 3600;

            $upd = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE login = ?");
            $upd->execute([$token, $expires, $user['login']]);

            $reset_link = "http://retroshow.hoho.ws/reset_password.php?login=" . urlencode($user['login']) . "&token=" . urlencode($token);

            $subject = "Смена пароля RetroShow";
            $body = '<img src="http://retroshow.hoho.ws/img/logo_sm.png" width="147" height="50" hspace="12" vspace="12" alt="RetroShow"><br>' .
                'Здравствуйте, ' . htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') . ',<p>' .
                'Вы запросили смену пароля на RetroShow.<br>' .
                'Чтобы задать новый пароль, перейдите по ссылке ниже (она действительна в течение 1 часа):<br>' .
                '<a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '</a><p>' .
                'Если вы не запрашивали смену пароля, просто проигнорируйте это письмо.<p>' .
                'Спасибо, что пользуетесь RetroShow!<br>' .
                '<i>RetroShow - Broadcast Yourself.</i><br><br><br><br>' .
                '<center><div style="padding: 2px; padding-left: 7px; padding-top: 0px; margin-top: 10px; background-color: #E5ECF9; border-top: 1px dashed #3366CC; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: bold;">&nbsp;</div><br>' .
                'Copyright © 2026 RetroShow, LLC';

            if (send_smtp_email_advanced($user['email'], $user['login'], $subject, $body, true)) {
                $success = "На E-mail, указанный при регистрации, отправлена ссылка для смены пароля.";
            } else {
                $error = "Не удалось отправить письмо. Попробуйте позже.";
            }
        }
    }
}

showHeader("Забыли пароль");
?>

<div class="tableSubTitle">Забыли пароль</div>

<?php if ($error): ?>
    <div class="errorBox"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="confirmBox"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr valign="top">
        <td style="padding-right: 15px;">
            <span class="highlight">Забыли пароль? Не проблема!</span>
            <br><br>
            Просто введите ваше имя пользователя, и мы отправим на <b>E-mail</b> специальную ссылку для смены пароля.
        </td>
        <td width="300">
            <table width="100%" cellpadding="5" cellspacing="0" bgcolor="#E5ECF9">
                <form method="post" action="forgot.php">
                    <input type="hidden" name="field_command" value="forgot_submit">
                    <tr>
                        <td align="right"><span class="label">Имя пользователя:</span></td>
                        <td><input type="text" size="20" name="field_login_username" value=""></td>
                    </tr>
                    <tr>
                        <td>&nbsp;</td>
                        <td>
                            <input type="submit" style="width:110px;" value="Выслать ссылку">
                        </td>
                    </tr>
                </form>
            </table>
        </td>
    </tr>
</table>

<?php showFooter(); ?>

