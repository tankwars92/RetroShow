<?php
include "init.php";
include "template.php";
include __DIR__ . "/lib/mailer.php";

if (!empty($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

function forgot_generateStrongPassword($length = 12, $add_dashes = false, $available_sets = 'luds')
{
    $sets = array();
    if (strpos($available_sets, 'l') !== false)
        $sets[] = 'abcdefghjkmnpqrstuvwxyz';
    if (strpos($available_sets, 'u') !== false)
        $sets[] = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    if (strpos($available_sets, 'd') !== false)
        $sets[] = '23456789';
    if (strpos($available_sets, 's') !== false)
        $sets[] = '!@#$%&*?';

    $all = '';
    $password = '';
    foreach ($sets as $set) {
        $chars = str_split($set);
        $password .= $chars[array_rand($chars)];
        $all .= $set;
    }

    $allChars = str_split($all);
    for ($i = 0; $i < $length - count($sets); $i++) {
        $password .= $allChars[array_rand($allChars)];
    }

    $password = str_shuffle($password);

    if (!$add_dashes) {
        return $password;
    }

    $dash_len = floor(sqrt($length));
    $dash_str = '';
    while (strlen($password) > $dash_len) {
        $dash_str .= substr($password, 0, $dash_len) . '-';
        $password = substr($password, $dash_len);
    }
    $dash_str .= $password;
    return $dash_str;
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
            $new_password = forgot_generateStrongPassword(rand(12, 28));

            $upd = $db->prepare("UPDATE users SET pass = ? WHERE login = ?");
            $upd->execute([$new_password, $user['login']]);

            $subject = "Ваш аккаунт RetroShow";
            $body = '<img src="http://retroshow.hoho.ws/img/logo_sm.png" width="147" height="50" hspace="12" vspace="12" alt="RetroShow"><br>' .
                'Здравствуйте, ' . htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') . ',<p>' .
                'Вот ваши данные для входа на RetroShow:<br>' .
                'Логин: ' . htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') . '<br>' .
                'Новый пароль: ' . htmlspecialchars($new_password, ENT_QUOTES, 'UTF-8') . '<p>' .
                'Вы можете войти в свой аккаунт, используя эти данные.<br>' .
                'В настройках аккаунта вы можете изменить свой пароль.<p>' .
                'Спасибо, что пользуетесь RetroShow!<br>' .
                '<i>RetroShow - Broadcast Yourself.</i><br><br><br><br>' .
                '<center><div style="padding: 2px; padding-left: 7px; padding-top: 0px; margin-top: 10px; background-color: #E5ECF9; border-top: 1px dashed #3366CC; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: bold;">&nbsp;</div><br>' .
                'Copyright © 2026 RetroShow, LLC';

            if (send_smtp_email_advanced($user['email'], $user['login'], $subject, $body, true)) {
                $success = "Новый пароль отправлен на E-mail, указанный при регистрации.";
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
            Просто введите ваше имя пользователя, и мы отправим новый пароль на <b>E-mail</b>, указанный при регистрации.
        </td>
        <td width="280">
            <table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#E5ECF9">
                <tr>
                    <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
                    <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
                    <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
                </tr>
                <tr>
                    <td><img src="img/pixel.gif" width="5" height="1"></td>
                    <td align="center">
                        <table width="100%" cellpadding="5" cellspacing="0" border="0">
                            <form method="post" action="forgot.php">
                                <input type="hidden" name="field_command" value="forgot_submit">
                                <tr>
                                    <td align="center" colspan="2">
                                        <div style="font-size: 14px; font-weight: bold; color:#003366; margin-bottom: 5px;">
                                            Восстановление пароля
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right"><span class="label">Имя пользователя:</span></td>
                                    <td><input type="text" size="20" name="field_login_username" value=""></td>
                                </tr>
                                <tr>
                                    <td align="right"><span class="label">&nbsp;</span></td>
                                    <td>
                                        <input type="submit" style="width:150px;" value="Выслать новый пароль"><br><br>
                                    </td>
                                </tr>
                            </form>
                        </table>
                    </td>
                    <td><img src="img/pixel.gif" width="5" height="1"></td>
                </tr>
                <tr>
                    <td><img src="img/box_login_bl.gif" width="5" height="5"></td>
                    <td><img src="img/pixel.gif" width="1" height="5"></td>
                    <td><img src="img/box_login_br.gif" width="5" height="5"></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php showFooter(); ?>

