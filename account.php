<?php
include "init.php";
include_once "template.php";

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = false;
$pw_error = '';
$pw_success = false;

try {
    $stmt = $db->prepare('SELECT email, about_me, gender, birthday_mon, birthday_day, birthday_yr, country, name, last_n, relationship, website, profile_icon, profile_comm, profile_bull, player_type, hometown, city FROM users WHERE login = ?');
    $stmt->execute([$user]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user_data = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $about_me = trim($_POST['about'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $birthday_mon = $_POST['birthday_mon'] ?? '';
    $birthday_day = $_POST['birthday_day'] ?? '';
    $birthday_yr = $_POST['birthday_yr'] ?? '';
    $country = $_POST['country'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $last_n = trim($_POST['last_n'] ?? '');
    $relationship = $_POST['relationship'] ?? '';
    $website = trim($_POST['website'] ?? '');
    
    if (!empty($website) && !preg_match('/^https?:\/\//', $website)) {
        $website = 'http://' . $website;
    }
    $profile_icon = $_POST['profile_icon'] ?? '0';
    $profile_comm = $_POST['profile_comm'] ?? '1';
    $profile_bull = $_POST['profile_bull'] ?? '1';
    $player_type = $_POST['player_type'] ?? 'auto';
    $hometown = trim($_POST['hometown'] ?? '');
    $city = trim($_POST['city'] ?? '');
    
    if (mb_strlen($about_me) > 500) $about_me = mb_substr($about_me, 0, 500);
    
    $stmt = $db->prepare('UPDATE users SET email = ?, about_me = ?, gender = ?, birthday_mon = ?, birthday_day = ?, birthday_yr = ?, country = ?, name = ?, last_n = ?, relationship = ?, website = ?, profile_icon = ?, profile_comm = ?, profile_bull = ?, player_type = ?, hometown = ?, city = ? WHERE login = ?');
    if ($stmt->execute([$email, $about_me, $gender, $birthday_mon, $birthday_day, $birthday_yr, $country, $name, $last_n, $relationship, $website, $profile_icon, $profile_comm, $profile_bull, $player_type, $hometown, $city, $user])) {
        $success = true;
		
        $user_data['email'] = $email;
        $user_data['about_me'] = $about_me;
        $user_data['gender'] = $gender;
        $user_data['birthday_mon'] = $birthday_mon;
        $user_data['birthday_day'] = $birthday_day;
        $user_data['birthday_yr'] = $birthday_yr;
        $user_data['country'] = $country;
        $user_data['name'] = $name;
        $user_data['last_n'] = $last_n;
        $user_data['relationship'] = $relationship;
        $user_data['website'] = $website;
        $user_data['profile_icon'] = $profile_icon;
        $user_data['profile_comm'] = $profile_comm;
        $user_data['profile_bull'] = $profile_bull;
        $user_data['player_type'] = $player_type;
        $user_data['hometown'] = $hometown;
        $user_data['city'] = $city;
    } else {
        $error = 'Ошибка при обновлении данных.';
    }
	
    $old_pw = trim($_POST['old_password'] ?? '');
    $new_pw = trim($_POST['new_password'] ?? '');
    $new_pw2 = trim($_POST['new_password2'] ?? '');
    if ($old_pw !== '' || $new_pw !== '' || $new_pw2 !== '') {
        if ($old_pw === '' || $new_pw === '' || $new_pw2 === '') {
            $pw_error = 'Пожалуйста, заполните все поля для смены пароля.';
            $success = false;
        } else {
            $stmt = $db->prepare('SELECT pass FROM users WHERE login = ?');
            $stmt->execute([$user]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || $old_pw !== $row['pass']) {
                $pw_error = 'Старый пароль неверен.';
                $success = false;
            } elseif ($new_pw !== $new_pw2) {
                $pw_error = 'Новые пароли не совпадают.';
                $success = false;
            } elseif (mb_strlen($new_pw) < 4) {
                $pw_error = 'Новый пароль слишком короткий (минимум 4 символа).';
                $success = false;
            } elseif ($new_pw === $old_pw) {
                $pw_error = 'Новый пароль совпадает со старым.';
                $success = false;
            } else {
                $stmt = $db->prepare('UPDATE users SET pass = ? WHERE login = ?');
                if ($stmt->execute([$new_pw, $user])) {
                    $pw_success = true;
                } else {
                    $pw_error = 'Ошибка при смене пароля.';
                    $success = false;
                }
            }
        }
    }
}

showHeader('Настройки аккаунта');
?>
<style type="text/css">
.error { background-color: #FFE6E6; border: 1px solid #FF9999; padding: 10px; margin: 10px 0px; color: #CC0000; font-size: 12px; }
.success { background-color: #E6FFE6; border: 1px solid #99FF99; padding: 10px; margin: 10px 0px; color: #006600; font-size: 12px; }
</style>
<center>
<div style="width:600px; text-align:left;">
  <form method="post" action="account.php" style="margin:0;">
    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
    <tr>
      <td colspan="5">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">Настройки аккаунта</td>
            <td align="right" style="font-size:12px; color:#0033cc; font-weight:normal; padding-bottom:2px;" valign="middle"><a href="channel.php?user=<?=urlencode($user)?>" style="color:#0033cc; text-decoration:underline;">Перейти к каналу</a></td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="5"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;"><tr><td height="1" bgcolor="#CCCCCC"></td></tr></table></td>
    </tr>
<tr>
  <td colspan="3" style="font-size:11px; color:#444; padding-top:6px;">* Показывает обязательное поле.</td>
  <td colspan="2" align="right" style="padding-top:0; margin-top:0;">
    <a href="delete_account.php" style="color:#c00; font-size:12px; text-decoration:underline; font-weight:bold; margin-top:0; padding-top:0;">Удалить мой аккаунт</a>
  </td>
</tr>

<tr>
  <td colspan="5">
    <?php if ($error): ?>
      <div class="errorBox" style="margin-bottom:8px;"> <?=htmlspecialchars($error)?> </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="confirmBox" style="margin-bottom:8px;">Данные успешно сохранены!</div>
    <?php endif; ?>
  </td>
</tr>
<tr><td colspan="5" height="10"></td></tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Имя пользователя:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4"> <?=htmlspecialchars($user)?></td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Email:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="text" size="20" maxlength="500" name="email" value="<?=htmlspecialchars($user_data['email'] ?? '')?>">*
  </td>
</tr>

<tr>
      <td colspan="5">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">Личные данные</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="5"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;"><tr><td height="1" bgcolor="#CCCCCC"></td></tr></table></td>
    </tr>
<tr>
  <td style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>О себе:</b><br><font size="1px" color="#555555">(Расскажите о себе)</font></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <textarea maxlength="500" name="about" rows="5" cols="45"><?=htmlspecialchars($user_data['about_me'] ?? '')?></textarea>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Имя:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="text" size="20" maxlength="500" name="name" value="<?=htmlspecialchars($user_data['name'] ?? '')?>">
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Фамилия:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="text" size="20" maxlength="500" name="last_n" value="<?=htmlspecialchars($user_data['last_n'] ?? '')?>">
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Дата рождения:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <select name="birthday_mon">
      <option value="---">---</option>
      <option value="1" <?= ($user_data['birthday_mon'] ?? '') == '1' ? 'selected' : '' ?>>Янв</option>
      <option value="2" <?= ($user_data['birthday_mon'] ?? '') == '2' ? 'selected' : '' ?>>Фев</option>
      <option value="3" <?= ($user_data['birthday_mon'] ?? '') == '3' ? 'selected' : '' ?>>Мар</option>
      <option value="4" <?= ($user_data['birthday_mon'] ?? '') == '4' ? 'selected' : '' ?>>Апр</option>
      <option value="5" <?= ($user_data['birthday_mon'] ?? '') == '5' ? 'selected' : '' ?>>Май</option>
      <option value="6" <?= ($user_data['birthday_mon'] ?? '') == '6' ? 'selected' : '' ?>>Июн</option>
      <option value="7" <?= ($user_data['birthday_mon'] ?? '') == '7' ? 'selected' : '' ?>>Июл</option>
      <option value="8" <?= ($user_data['birthday_mon'] ?? '') == '8' ? 'selected' : '' ?>>Авг</option>
      <option value="9" <?= ($user_data['birthday_mon'] ?? '') == '9' ? 'selected' : '' ?>>Сен</option>
      <option value="10" <?= ($user_data['birthday_mon'] ?? '') == '10' ? 'selected' : '' ?>>Окт</option>
      <option value="11" <?= ($user_data['birthday_mon'] ?? '') == '11' ? 'selected' : '' ?>>Ноя</option>
      <option value="12" <?= ($user_data['birthday_mon'] ?? '') == '12' ? 'selected' : '' ?>>Дек</option>
    </select>
    <select name="birthday_day">
      <option value="---">---</option>
      <?php for ($i = 1; $i <= 31; $i++): ?>
        <option value="<?=$i?>" <?= ($user_data['birthday_day'] ?? '') == $i ? 'selected' : '' ?>><?=$i?></option>
      <?php endfor; ?>
    </select>
    <select name="birthday_yr">
      <option value="---">---</option>
      <?php for ($i = 2024; $i >= 1900; $i--): ?>
        <option value="<?=$i?>" <?= ($user_data['birthday_yr'] ?? '') == $i ? 'selected' : '' ?>><?=$i?></option>
      <?php endfor; ?>
    </select>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Пол:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <select name="gender">
      <option value="0" <?= ($user_data['gender'] ?? '') == '0' ? 'selected' : '' ?>>Не указан</option>
      <option value="m" <?= ($user_data['gender'] ?? '') == 'm' ? 'selected' : '' ?>>Мужской</option>
      <option value="f" <?= ($user_data['gender'] ?? '') == 'f' ? 'selected' : '' ?>>Женский</option>
    </select>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Семейное положение:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <select name="relationship">
      <option value="0" <?= ($user_data['relationship'] ?? '') == '0' ? 'selected' : '' ?>>Не указано</option>
      <option value="1" <?= ($user_data['relationship'] ?? '') == '1' ? 'selected' : '' ?>>Холост/Не замужем</option>
      <option value="2" <?= ($user_data['relationship'] ?? '') == '2' ? 'selected' : '' ?>>В отношениях</option>
    </select>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Личный сайт:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="text" size="20" maxlength="500" name="website" value="<?=htmlspecialchars($user_data['website'] ?? '')?>">
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Иконка профиля:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="radio" name="profile_icon" value="0" <?= ($user_data['profile_icon'] ?? '0') == '0' ? 'checked' : '' ?>> 
    <label for="1">Использовать последнее загруженное видео как фото профиля.</label><br>
    <input type="radio" name="profile_icon" value="1" <?= ($user_data['profile_icon'] ?? '0') == '1' ? 'checked' : '' ?>>
    <label for="2">Я выберу фото профиля из "Мои видео".</label><br>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Комментарии профиля:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="radio" name="profile_comm" value="1" <?= ($user_data['profile_comm'] ?? '1') == '1' ? 'checked' : '' ?>> 
    <label for="1">Разрешить комментарии к профилю.</label><br>
    <input type="radio" name="profile_comm" value="2" <?= ($user_data['profile_comm'] ?? '1') == '2' ? 'checked' : '' ?>>
    <label for="2">Не разрешать комментарии к профилю.</label><br>
  </td>
</tr>
<tr>
  <td width="120" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Тип плеера:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="radio" name="player_type" value="auto" <?= ($user_data['player_type'] ?? 'auto') == 'auto' ? 'checked' : '' ?>> 
    <label for="1">Автоматический выбор (рекомендуется)</label><br>
    <input type="radio" name="player_type" value="flash" <?= ($user_data['player_type'] ?? 'auto') == 'flash' ? 'checked' : '' ?>>
    <label for="2">Всегда Flash плеер</label><br>
    <input type="radio" name="player_type" value="html5" <?= ($user_data['player_type'] ?? 'auto') == 'html5' ? 'checked' : '' ?>>
    <label for="3">Всегда HTML5 плеер</label><br>
  </td>
</tr>
</table>
</div>
</div>
</center>
<center>
<div style="width:600px; text-align:left;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
  <tr>
      <td colspan="5">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">Информация о местоположении</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="5"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;"><tr><td height="1" bgcolor="#CCCCCC"></td></tr></table></td>
    </tr>
    <tr>
      <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Родной город:</b></td>
      <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
        <input type="text" size="20" maxlength="500" name="hometown" value="<?=htmlspecialchars($user_data['hometown'] ?? '')?>">
      </td>
    </tr>
    <tr>
      <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Текущий город:</b></td>
      <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
        <input type="text" size="20" maxlength="500" name="city" value="<?=htmlspecialchars($user_data['city'] ?? '')?>">
      </td>
    </tr>
    <tr>
      <td width="120" style="font-size:13px; color:#333; padding-bottom:8px;"><b>Текущая страна:</b></td>
      <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
             <select name="country" tabindex="5">
               <option value="" <?= ($user_data['country'] ?? '') == '' ? 'selected' : '' ?>>---</option>
 			        <option value="US" <?= ($user_data['country'] ?? '') == 'US' ? 'selected' : '' ?>>United States</option>
 			        <option value="AF" <?= ($user_data['country'] ?? '') == 'AF' ? 'selected' : '' ?>>Afghanistan</option>
 			        <option value="AL" <?= ($user_data['country'] ?? '') == 'AL' ? 'selected' : '' ?>>Albania</option>
 			        <option value="DZ" <?= ($user_data['country'] ?? '') == 'DZ' ? 'selected' : '' ?>>Algeria</option>
 			        <option value="AS" <?= ($user_data['country'] ?? '') == 'AS' ? 'selected' : '' ?>>American Samoa</option>
 			        <option value="AD" <?= ($user_data['country'] ?? '') == 'AD' ? 'selected' : '' ?>>Andorra</option>
 			        <option value="AO" <?= ($user_data['country'] ?? '') == 'AO' ? 'selected' : '' ?>>Angola</option>
 			        <option value="AI" <?= ($user_data['country'] ?? '') == 'AI' ? 'selected' : '' ?>>Anguilla</option>
 			        <option value="AG" <?= ($user_data['country'] ?? '') == 'AG' ? 'selected' : '' ?>>Antigua and Barbuda</option>
 			        <option value="AR" <?= ($user_data['country'] ?? '') == 'AR' ? 'selected' : '' ?>>Argentina</option>
			         			        <option value="AM" <?= ($user_data['country'] ?? '') == 'AM' ? 'selected' : '' ?>>Armenia</option>
 			        <option value="AW" <?= ($user_data['country'] ?? '') == 'AW' ? 'selected' : '' ?>>Aruba</option>
 			        <option value="AU" <?= ($user_data['country'] ?? '') == 'AU' ? 'selected' : '' ?>>Australia</option>
 			        <option value="AT" <?= ($user_data['country'] ?? '') == 'AT' ? 'selected' : '' ?>>Austria</option>
 			        <option value="AZ" <?= ($user_data['country'] ?? '') == 'AZ' ? 'selected' : '' ?>>Azerbaijan</option>
 			        <option value="BS" <?= ($user_data['country'] ?? '') == 'BS' ? 'selected' : '' ?>>Bahamas</option>
 			        <option value="BH" <?= ($user_data['country'] ?? '') == 'BH' ? 'selected' : '' ?>>Bahrain</option>
 			        <option value="BD" <?= ($user_data['country'] ?? '') == 'BD' ? 'selected' : '' ?>>Bangladesh</option>
 			        <option value="BB" <?= ($user_data['country'] ?? '') == 'BB' ? 'selected' : '' ?>>Barbados</option>
 			        <option value="BY" <?= ($user_data['country'] ?? '') == 'BY' ? 'selected' : '' ?>>Belarus</option>
			        <option value="BE" <?= ($user_data['country'] ?? '') == 'BE' ? 'selected' : '' ?>>Belgium</option>
			        <option value="BZ" <?= ($user_data['country'] ?? '') == 'BZ' ? 'selected' : '' ?>>Belize</option>
			        <option value="BJ" <?= ($user_data['country'] ?? '') == 'BJ' ? 'selected' : '' ?>>Benin</option>
			        <option value="BM" <?= ($user_data['country'] ?? '') == 'BM' ? 'selected' : '' ?>>Bermuda</option>
			        <option value="BT" <?= ($user_data['country'] ?? '') == 'BT' ? 'selected' : '' ?>>Bhutan</option>
			        <option value="BO" <?= ($user_data['country'] ?? '') == 'BO' ? 'selected' : '' ?>>Bolivia</option>
			        <option value="BA" <?= ($user_data['country'] ?? '') == 'BA' ? 'selected' : '' ?>>Bosnia and Herzegovina</option>
			        <option value="BW" <?= ($user_data['country'] ?? '') == 'BW' ? 'selected' : '' ?>>Botswana</option>
			        <option value="BV" <?= ($user_data['country'] ?? '') == 'BV' ? 'selected' : '' ?>>Bouvet Island</option>
			        <option value="BR" <?= ($user_data['country'] ?? '') == 'BR' ? 'selected' : '' ?>>Brazil</option>
			        <option value="IO" <?= ($user_data['country'] ?? '') == 'IO' ? 'selected' : '' ?>>British Indian Ocean</option>
			        <option value="VG" <?= ($user_data['country'] ?? '') == 'VG' ? 'selected' : '' ?>>British Virgin Islands</option>
			        <option value="BN" <?= ($user_data['country'] ?? '') == 'BN' ? 'selected' : '' ?>>Brunei</option>
			        <option value="BG" <?= ($user_data['country'] ?? '') == 'BG' ? 'selected' : '' ?>>Bulgaria</option>
			        <option value="BF" <?= ($user_data['country'] ?? '') == 'BF' ? 'selected' : '' ?>>Burkina Faso</option>
			        <option value="BI" <?= ($user_data['country'] ?? '') == 'BI' ? 'selected' : '' ?>>Burundi</option>
			        <option value="KH" <?= ($user_data['country'] ?? '') == 'KH' ? 'selected' : '' ?>>Cambodia</option>
			        <option value="CM" <?= ($user_data['country'] ?? '') == 'CM' ? 'selected' : '' ?>>Cameroon</option>
			        <option value="CA" <?= ($user_data['country'] ?? '') == 'CA' ? 'selected' : '' ?>>Canada</option>
			        <option value="CV" <?= ($user_data['country'] ?? '') == 'CV' ? 'selected' : '' ?>>Cape Verde</option>
			        <option value="KY" <?= ($user_data['country'] ?? '') == 'KY' ? 'selected' : '' ?>>Cayman Islands</option>
			        <option value="CF" <?= ($user_data['country'] ?? '') == 'CF' ? 'selected' : '' ?>>Central African Republic</option>
			        <option value="TD" <?= ($user_data['country'] ?? '') == 'TD' ? 'selected' : '' ?>>Chad</option>
			        <option value="CL" <?= ($user_data['country'] ?? '') == 'CL' ? 'selected' : '' ?>>Chile</option>
			        <option value="CN" <?= ($user_data['country'] ?? '') == 'CN' ? 'selected' : '' ?>>China</option>
			        <option value="CX" <?= ($user_data['country'] ?? '') == 'CX' ? 'selected' : '' ?>>Christmas Island</option>
			        <option value="CC" <?= ($user_data['country'] ?? '') == 'CC' ? 'selected' : '' ?>>Cocos (Keeling) Islands</option>
			        <option value="CO" <?= ($user_data['country'] ?? '') == 'CO' ? 'selected' : '' ?>>Colombia</option>
			        <option value="KM" <?= ($user_data['country'] ?? '') == 'KM' ? 'selected' : '' ?>>Comoros</option>
			        <option value="CG" <?= ($user_data['country'] ?? '') == 'CG' ? 'selected' : '' ?>>Congo</option>
			        <option value="CD" <?= ($user_data['country'] ?? '') == 'CD' ? 'selected' : '' ?>>Congo (DRC)</option>
			        <option value="CK" <?= ($user_data['country'] ?? '') == 'CK' ? 'selected' : '' ?>>Cook Islands</option>
			        <option value="CR" <?= ($user_data['country'] ?? '') == 'CR' ? 'selected' : '' ?>>Costa Rica</option>
			        <option value="CI" <?= ($user_data['country'] ?? '') == 'CI' ? 'selected' : '' ?>>Cote d'Ivoire</option>
			        <option value="HR" <?= ($user_data['country'] ?? '') == 'HR' ? 'selected' : '' ?>>Croatia</option>
			        <option value="CU" <?= ($user_data['country'] ?? '') == 'CU' ? 'selected' : '' ?>>Cuba</option>
			        <option value="CY" <?= ($user_data['country'] ?? '') == 'CY' ? 'selected' : '' ?>>Cyprus</option>
			        <option value="CZ" <?= ($user_data['country'] ?? '') == 'CZ' ? 'selected' : '' ?>>Czech Republic</option>
			        <option value="DK" <?= ($user_data['country'] ?? '') == 'DK' ? 'selected' : '' ?>>Denmark</option>
			        <option value="DJ" <?= ($user_data['country'] ?? '') == 'DJ' ? 'selected' : '' ?>>Djibouti</option>
			        <option value="DM" <?= ($user_data['country'] ?? '') == 'DM' ? 'selected' : '' ?>>Dominica</option>
			        <option value="DO" <?= ($user_data['country'] ?? '') == 'DO' ? 'selected' : '' ?>>Dominican Republic</option>
			        <option value="TP" <?= ($user_data['country'] ?? '') == 'TP' ? 'selected' : '' ?>>East Timor</option>
			        <option value="EC" <?= ($user_data['country'] ?? '') == 'EC' ? 'selected' : '' ?>>Ecuador</option>
			        <option value="EG" <?= ($user_data['country'] ?? '') == 'EG' ? 'selected' : '' ?>>Egypt</option>
			        <option value="SV" <?= ($user_data['country'] ?? '') == 'SV' ? 'selected' : '' ?>>El Salvador</option>
			        <option value="GQ" <?= ($user_data['country'] ?? '') == 'GQ' ? 'selected' : '' ?>>Equatorial Guinea</option>
			        <option value="ER" <?= ($user_data['country'] ?? '') == 'ER' ? 'selected' : '' ?>>Eritrea</option>
			        <option value="EE" <?= ($user_data['country'] ?? '') == 'EE' ? 'selected' : '' ?>>Estonia</option>
			        <option value="ET" <?= ($user_data['country'] ?? '') == 'ET' ? 'selected' : '' ?>>Ethiopia</option>
			        <option value="FK" <?= ($user_data['country'] ?? '') == 'FK' ? 'selected' : '' ?>>Falkland Islands</option>
			        <option value="FO" <?= ($user_data['country'] ?? '') == 'FO' ? 'selected' : '' ?>>Faroe Islands</option>
			        <option value="FJ" <?= ($user_data['country'] ?? '') == 'FJ' ? 'selected' : '' ?>>Fiji</option>
			        <option value="FI" <?= ($user_data['country'] ?? '') == 'FI' ? 'selected' : '' ?>>Finland</option>
			        <option value="FR" <?= ($user_data['country'] ?? '') == 'FR' ? 'selected' : '' ?>>France</option>
			        <option value="GF" <?= ($user_data['country'] ?? '') == 'GF' ? 'selected' : '' ?>>French Guyana</option>
			        <option value="PF" <?= ($user_data['country'] ?? '') == 'PF' ? 'selected' : '' ?>>French Polynesia</option>
			        <option value="TF" <?= ($user_data['country'] ?? '') == 'TF' ? 'selected' : '' ?>>French Southern Lands</option>
			        <option value="GA" <?= ($user_data['country'] ?? '') == 'GA' ? 'selected' : '' ?>>Gabon</option>
			        <option value="GM" <?= ($user_data['country'] ?? '') == 'GM' ? 'selected' : '' ?>>Gambia</option>
			        <option value="GZ" <?= ($user_data['country'] ?? '') == 'GZ' ? 'selected' : '' ?>>Gaza Strip</option>
			        <option value="GE" <?= ($user_data['country'] ?? '') == 'GE' ? 'selected' : '' ?>>Georgia</option>
			        <option value="DE" <?= ($user_data['country'] ?? '') == 'DE' ? 'selected' : '' ?>>Germany</option>
			        <option value="GH" <?= ($user_data['country'] ?? '') == 'GH' ? 'selected' : '' ?>>Ghana</option>
			        <option value="GI" <?= ($user_data['country'] ?? '') == 'GI' ? 'selected' : '' ?>>Gibraltar</option>
			        <option value="GR" <?= ($user_data['country'] ?? '') == 'GR' ? 'selected' : '' ?>>Greece</option>
			        <option value="GL" <?= ($user_data['country'] ?? '') == 'GL' ? 'selected' : '' ?>>Greenland</option>
			        <option value="GD" <?= ($user_data['country'] ?? '') == 'GD' ? 'selected' : '' ?>>Grenada</option>
			        <option value="GP" <?= ($user_data['country'] ?? '') == 'GP' ? 'selected' : '' ?>>Guadeloupe</option>
			        <option value="GU" <?= ($user_data['country'] ?? '') == 'GU' ? 'selected' : '' ?>>Guam</option>
			        <option value="GT" <?= ($user_data['country'] ?? '') == 'GT' ? 'selected' : '' ?>>Guatemala</option>
			        <option value="GN" <?= ($user_data['country'] ?? '') == 'GN' ? 'selected' : '' ?>>Guinea</option>
			        <option value="GW" <?= ($user_data['country'] ?? '') == 'GW' ? 'selected' : '' ?>>Guinea-Bissau</option>
			        <option value="GY" <?= ($user_data['country'] ?? '') == 'GY' ? 'selected' : '' ?>>Guyana</option>
			        <option value="HT" <?= ($user_data['country'] ?? '') == 'HT' ? 'selected' : '' ?>>Haiti</option>
			        <option value="HM" <?= ($user_data['country'] ?? '') == 'HM' ? 'selected' : '' ?>>Heard & McDonald Islands</option>
			        <option value="VA" <?= ($user_data['country'] ?? '') == 'VA' ? 'selected' : '' ?>>Holy See (Vatican City)</option>
			        <option value="HN" <?= ($user_data['country'] ?? '') == 'HN' ? 'selected' : '' ?>>Honduras</option>
			        <option value="HK" <?= ($user_data['country'] ?? '') == 'HK' ? 'selected' : '' ?>>Hong Kong</option>
			        <option value="HU" <?= ($user_data['country'] ?? '') == 'HU' ? 'selected' : '' ?>>Hungary</option>
			        <option value="IS" <?= ($user_data['country'] ?? '') == 'IS' ? 'selected' : '' ?>>Iceland</option>
			        <option value="IN" <?= ($user_data['country'] ?? '') == 'IN' ? 'selected' : '' ?>>India</option>
			        <option value="ID" <?= ($user_data['country'] ?? '') == 'ID' ? 'selected' : '' ?>>Indonesia</option>
			        <option value="IR" <?= ($user_data['country'] ?? '') == 'IR' ? 'selected' : '' ?>>Iran</option>
			        <option value="IQ" <?= ($user_data['country'] ?? '') == 'IQ' ? 'selected' : '' ?>>Iraq</option>
			        <option value="IE" <?= ($user_data['country'] ?? '') == 'IE' ? 'selected' : '' ?>>Ireland</option>
			        <option value="IL" <?= ($user_data['country'] ?? '') == 'IL' ? 'selected' : '' ?>>Israel</option>
			        <option value="IT" <?= ($user_data['country'] ?? '') == 'IT' ? 'selected' : '' ?>>Italy</option>
			        <option value="JM" <?= ($user_data['country'] ?? '') == 'JM' ? 'selected' : '' ?>>Jamaica</option>
			        <option value="JP" <?= ($user_data['country'] ?? '') == 'JP' ? 'selected' : '' ?>>Japan</option>
			        <option value="JO" <?= ($user_data['country'] ?? '') == 'JO' ? 'selected' : '' ?>>Jordan</option>
			        <option value="KZ" <?= ($user_data['country'] ?? '') == 'KZ' ? 'selected' : '' ?>>Kazakhstan</option>
			        <option value="KE" <?= ($user_data['country'] ?? '') == 'KE' ? 'selected' : '' ?>>Kenya</option>
			        <option value="KI" <?= ($user_data['country'] ?? '') == 'KI' ? 'selected' : '' ?>>Kiribati</option>
			        <option value="KW" <?= ($user_data['country'] ?? '') == 'KW' ? 'selected' : '' ?>>Kuwait</option>
			        <option value="KG" <?= ($user_data['country'] ?? '') == 'KG' ? 'selected' : '' ?>>Kyrgyzstan</option>
			        <option value="LA" <?= ($user_data['country'] ?? '') == 'LA' ? 'selected' : '' ?>>Laos</option>
			        <option value="LV" <?= ($user_data['country'] ?? '') == 'LV' ? 'selected' : '' ?>>Latvia</option>
			        <option value="LB" <?= ($user_data['country'] ?? '') == 'LB' ? 'selected' : '' ?>>Lebanon</option>
			        <option value="LS" <?= ($user_data['country'] ?? '') == 'LS' ? 'selected' : '' ?>>Lesotho</option>
			        <option value="LR" <?= ($user_data['country'] ?? '') == 'LR' ? 'selected' : '' ?>>Liberia</option>
			        <option value="LY" <?= ($user_data['country'] ?? '') == 'LY' ? 'selected' : '' ?>>Libya</option>
			        <option value="LI" <?= ($user_data['country'] ?? '') == 'LI' ? 'selected' : '' ?>>Liechtenstein</option>
			        <option value="LT" <?= ($user_data['country'] ?? '') == 'LT' ? 'selected' : '' ?>>Lithuania</option>
			        <option value="LU" <?= ($user_data['country'] ?? '') == 'LU' ? 'selected' : '' ?>>Luxembourg</option>
			        <option value="MO" <?= ($user_data['country'] ?? '') == 'MO' ? 'selected' : '' ?>>Macau</option>
			        <option value="MK" <?= ($user_data['country'] ?? '') == 'MK' ? 'selected' : '' ?>>Macedonia</option>
			        <option value="MG" <?= ($user_data['country'] ?? '') == 'MG' ? 'selected' : '' ?>>Madagascar</option>
			        <option value="MW" <?= ($user_data['country'] ?? '') == 'MW' ? 'selected' : '' ?>>Malawi</option>
			        <option value="MY" <?= ($user_data['country'] ?? '') == 'MY' ? 'selected' : '' ?>>Malaysia</option>
			        <option value="MV" <?= ($user_data['country'] ?? '') == 'MV' ? 'selected' : '' ?>>Maldives</option>
			        <option value="ML" <?= ($user_data['country'] ?? '') == 'ML' ? 'selected' : '' ?>>Mali</option>
			        <option value="MT" <?= ($user_data['country'] ?? '') == 'MT' ? 'selected' : '' ?>>Malta</option>
			        <option value="MH" <?= ($user_data['country'] ?? '') == 'MH' ? 'selected' : '' ?>>Marshall Islands</option>
			        <option value="MQ" <?= ($user_data['country'] ?? '') == 'MQ' ? 'selected' : '' ?>>Martinique</option>
			        <option value="MR" <?= ($user_data['country'] ?? '') == 'MR' ? 'selected' : '' ?>>Mauritania</option>
			        <option value="MU" <?= ($user_data['country'] ?? '') == 'MU' ? 'selected' : '' ?>>Mauritius</option>
			        <option value="YT" <?= ($user_data['country'] ?? '') == 'YT' ? 'selected' : '' ?>>Mayotte</option>
			        <option value="MX" <?= ($user_data['country'] ?? '') == 'MX' ? 'selected' : '' ?>>Mexico</option>
			        <option value="FM" <?= ($user_data['country'] ?? '') == 'FM' ? 'selected' : '' ?>>Micronesia</option>
			        <option value="MD" <?= ($user_data['country'] ?? '') == 'MD' ? 'selected' : '' ?>>Moldova</option>
			        <option value="MC" <?= ($user_data['country'] ?? '') == 'MC' ? 'selected' : '' ?>>Monaco</option>
			        <option value="MN" <?= ($user_data['country'] ?? '') == 'MN' ? 'selected' : '' ?>>Mongolia</option>
			        <option value="MS" <?= ($user_data['country'] ?? '') == 'MS' ? 'selected' : '' ?>>Montserrat</option>
			        <option value="MA" <?= ($user_data['country'] ?? '') == 'MA' ? 'selected' : '' ?>>Morocco</option>
			        <option value="MZ" <?= ($user_data['country'] ?? '') == 'MZ' ? 'selected' : '' ?>>Mozambique</option>
			        <option value="MM" <?= ($user_data['country'] ?? '') == 'MM' ? 'selected' : '' ?>>Myanmar</option>
			        <option value="NA" <?= ($user_data['country'] ?? '') == 'NA' ? 'selected' : '' ?>>Namibia</option>
			        <option value="NR" <?= ($user_data['country'] ?? '') == 'NR' ? 'selected' : '' ?>>Naura</option>
			        <option value="NP" <?= ($user_data['country'] ?? '') == 'NP' ? 'selected' : '' ?>>Nepal</option>
			        <option value="NL" <?= ($user_data['country'] ?? '') == 'NL' ? 'selected' : '' ?>>Netherlands</option>
			        <option value="AN" <?= ($user_data['country'] ?? '') == 'AN' ? 'selected' : '' ?>>Netherlands Antilles</option>
			        <option value="NC" <?= ($user_data['country'] ?? '') == 'NC' ? 'selected' : '' ?>>New Caledonia</option>
			        <option value="NZ" <?= ($user_data['country'] ?? '') == 'NZ' ? 'selected' : '' ?>>New Zealand</option>
			        <option value="NI" <?= ($user_data['country'] ?? '') == 'NI' ? 'selected' : '' ?>>Nicaragua</option>
			        <option value="NE" <?= ($user_data['country'] ?? '') == 'NE' ? 'selected' : '' ?>>Niger</option>
			        <option value="NG" <?= ($user_data['country'] ?? '') == 'NG' ? 'selected' : '' ?>>Nigeria</option>
			        <option value="NU" <?= ($user_data['country'] ?? '') == 'NU' ? 'selected' : '' ?>>Niue</option>
			        <option value="NF" <?= ($user_data['country'] ?? '') == 'NF' ? 'selected' : '' ?>>Norfolk Island</option>
			        <option value="KP" <?= ($user_data['country'] ?? '') == 'KP' ? 'selected' : '' ?>>North Korea</option>
			        <option value="MP" <?= ($user_data['country'] ?? '') == 'MP' ? 'selected' : '' ?>>Northern Marianas</option>
			        <option value="NO" <?= ($user_data['country'] ?? '') == 'NO' ? 'selected' : '' ?>>Norway</option>
			        <option value="OM" <?= ($user_data['country'] ?? '') == 'OM' ? 'selected' : '' ?>>Oman</option>
			        <option value="PK" <?= ($user_data['country'] ?? '') == 'PK' ? 'selected' : '' ?>>Pakistan</option>
			        <option value="PW" <?= ($user_data['country'] ?? '') == 'PW' ? 'selected' : '' ?>>Palau</option>
			        <option value="PA" <?= ($user_data['country'] ?? '') == 'PA' ? 'selected' : '' ?>>Panama</option>
			        <option value="PG" <?= ($user_data['country'] ?? '') == 'PG' ? 'selected' : '' ?>>Papua New Guinea</option>
			        <option value="PY" <?= ($user_data['country'] ?? '') == 'PY' ? 'selected' : '' ?>>Paraguay</option>
			        <option value="PE" <?= ($user_data['country'] ?? '') == 'PE' ? 'selected' : '' ?>>Peru</option>
			        <option value="PH" <?= ($user_data['country'] ?? '') == 'PH' ? 'selected' : '' ?>>Philippines</option>
			        <option value="PN" <?= ($user_data['country'] ?? '') == 'PN' ? 'selected' : '' ?>>Pitcairn</option>
			        <option value="PL" <?= ($user_data['country'] ?? '') == 'PL' ? 'selected' : '' ?>>Poland</option>
			        <option value="PT" <?= ($user_data['country'] ?? '') == 'PT' ? 'selected' : '' ?>>Portugal</option>
			        <option value="PR" <?= ($user_data['country'] ?? '') == 'PR' ? 'selected' : '' ?>>Puerto Rico</option>
			        <option value="QA" <?= ($user_data['country'] ?? '') == 'QA' ? 'selected' : '' ?>>Qatar</option>
			        <option value="RE" <?= ($user_data['country'] ?? '') == 'RE' ? 'selected' : '' ?>>Reunion</option>
			        <option value="RO" <?= ($user_data['country'] ?? '') == 'RO' ? 'selected' : '' ?>>Romania</option>
			        <option value="RU" <?= ($user_data['country'] ?? '') == 'RU' ? 'selected' : '' ?>>Russia</option>
			        <option value="RW" <?= ($user_data['country'] ?? '') == 'RW' ? 'selected' : '' ?>>wanda</option>
			        <option value="KN" <?= ($user_data['country'] ?? '') == 'KN' ? 'selected' : '' ?>>Saint Kitts and Nevis</option>
			        <option value="LC" <?= ($user_data['country'] ?? '') == 'LC' ? 'selected' : '' ?>>Saint Lucia</option>
			        <option value="VC" <?= ($user_data['country'] ?? '') == 'VC' ? 'selected' : '' ?>>St. Vincent & Grenadines</option>
			        <option value="WS" <?= ($user_data['country'] ?? '') == 'WS' ? 'selected' : '' ?>>Samoa</option>
			        <option value="SM" <?= ($user_data['country'] ?? '') == 'SM' ? 'selected' : '' ?>>San Marino</option>
			        <option value="ST" <?= ($user_data['country'] ?? '') == 'ST' ? 'selected' : '' ?>>Sao Tome and Principe</option>
			        <option value="SA" <?= ($user_data['country'] ?? '') == 'SA' ? 'selected' : '' ?>>Saudi Arabia</option>
			        <option value="SN" <?= ($user_data['country'] ?? '') == 'SN' ? 'selected' : '' ?>>Senegal</option>
			        <option value="CS" <?= ($user_data['country'] ?? '') == 'CS' ? 'selected' : '' ?>>Serbia & Montenegro</option>
			        <option value="SC" <?= ($user_data['country'] ?? '') == 'SC' ? 'selected' : '' ?>>Seychelles</option>
			        <option value="SL" <?= ($user_data['country'] ?? '') == 'SL' ? 'selected' : '' ?>>Sierra Leone</option>
			        <option value="SG" <?= ($user_data['country'] ?? '') == 'SG' ? 'selected' : '' ?>>Singapore</option>
			        <option value="SK" <?= ($user_data['country'] ?? '') == 'SK' ? 'selected' : '' ?>>Slovakia</option>
			        <option value="SI" <?= ($user_data['country'] ?? '') == 'SI' ? 'selected' : '' ?>>Slovenia</option>
			        <option value="SB" <?= ($user_data['country'] ?? '') == 'SB' ? 'selected' : '' ?>>Solomon Islands</option>
			        <option value="SO" <?= ($user_data['country'] ?? '') == 'SO' ? 'selected' : '' ?>>Somalia</option>
			        <option value="ZA" <?= ($user_data['country'] ?? '') == 'ZA' ? 'selected' : '' ?>>South Africa</option>
			        <option value="GS" <?= ($user_data['country'] ?? '') == 'GS' ? 'selected' : '' ?>>South Georgia</option>
			        <option value="KR" <?= ($user_data['country'] ?? '') == 'KR' ? 'selected' : '' ?>>South Korea</option>
			        <option value="ES" <?= ($user_data['country'] ?? '') == 'ES' ? 'selected' : '' ?>>Spain</option>
			        <option value="LK" <?= ($user_data['country'] ?? '') == 'LK' ? 'selected' : '' ?>>Sri Lanka</option>
			        <option value="SH" <?= ($user_data['country'] ?? '') == 'SH' ? 'selected' : '' ?>>St. Helena</option>
			        <option value="PM" <?= ($user_data['country'] ?? '') == 'PM' ? 'selected' : '' ?>>St. Pierre and Miquelon</option>
			        <option value="SD" <?= ($user_data['country'] ?? '') == 'SD' ? 'selected' : '' ?>>Sudan</option>
			        <option value="SR" <?= ($user_data['country'] ?? '') == 'SR' ? 'selected' : '' ?>>Suriname</option>
			        <option value="SJ" <?= ($user_data['country'] ?? '') == 'SJ' ? 'selected' : '' ?>>Svalbard</option>
			        <option value="SZ" <?= ($user_data['country'] ?? '') == 'SZ' ? 'selected' : '' ?>>Swaziland</option>
			        <option value="SE" <?= ($user_data['country'] ?? '') == 'SE' ? 'selected' : '' ?>>Sweden</option>
			        <option value="CH" <?= ($user_data['country'] ?? '') == 'CH' ? 'selected' : '' ?>>Switzerland</option>
			        <option value="SY" <?= ($user_data['country'] ?? '') == 'SY' ? 'selected' : '' ?>>Syria</option>
			        <option value="TW" <?= ($user_data['country'] ?? '') == 'TW' ? 'selected' : '' ?>>Taiwan</option>
			        <option value="TJ" <?= ($user_data['country'] ?? '') == 'TJ' ? 'selected' : '' ?>>Tajikistan</option>
			        <option value="TZ" <?= ($user_data['country'] ?? '') == 'TZ' ? 'selected' : '' ?>>Tanzania</option>
			        <option value="TH" <?= ($user_data['country'] ?? '') == 'TH' ? 'selected' : '' ?>>Thailand</option>
			        <option value="TG" <?= ($user_data['country'] ?? '') == 'TG' ? 'selected' : '' ?>>Togo</option>
			        <option value="TK" <?= ($user_data['country'] ?? '') == 'TK' ? 'selected' : '' ?>>Tokelau</option>
			        <option value="TO" <?= ($user_data['country'] ?? '') == 'TO' ? 'selected' : '' ?>>Tonga</option>
			        <option value="TT" <?= ($user_data['country'] ?? '') == 'TT' ? 'selected' : '' ?>>Trinidad and Tobago</option>
			        <option value="TN" <?= ($user_data['country'] ?? '') == 'TN' ? 'selected' : '' ?>>Tunisia</option>
			        <option value="TR" <?= ($user_data['country'] ?? '') == 'TR' ? 'selected' : '' ?>>Turkey</option>
			        <option value="TM" <?= ($user_data['country'] ?? '') == 'TM' ? 'selected' : '' ?>>Turkmenistan</option>
			        <option value="TC" <?= ($user_data['country'] ?? '') == 'TC' ? 'selected' : '' ?>>Turks and Caicos Islands</option>
			        <option value="TV" <?= ($user_data['country'] ?? '') == 'TV' ? 'selected' : '' ?>>Tuvalu</option>
			        <option value="UG" <?= ($user_data['country'] ?? '') == 'UG' ? 'selected' : '' ?>>Uganda</option>
			        <option value="UA" <?= ($user_data['country'] ?? '') == 'UA' ? 'selected' : '' ?>>Ukraine</option>
			        <option value="AE" <?= ($user_data['country'] ?? '') == 'AE' ? 'selected' : '' ?>>United Arab Emirates</option>
			        <option value="GB" <?= ($user_data['country'] ?? '') == 'GB' ? 'selected' : '' ?>>United Kingdom</option>
			        <option value="VI" <?= ($user_data['country'] ?? '') == 'VI' ? 'selected' : '' ?>>US Virgin Islands</option>
			        <option value="UY" <?= ($user_data['country'] ?? '') == 'UY' ? 'selected' : '' ?>>Uruguay</option>
			        <option value="UZ" <?= ($user_data['country'] ?? '') == 'UZ' ? 'selected' : '' ?>>Uzbekistan</option>
			        <option value="VU" <?= ($user_data['country'] ?? '') == 'VU' ? 'selected' : '' ?>>Vanuatu</option>
			        <option value="VE" <?= ($user_data['country'] ?? '') == 'VE' ? 'selected' : '' ?>>Venezuela</option>
			        <option value="VN" <?= ($user_data['country'] ?? '') == 'VN' ? 'selected' : '' ?>>Vietnam</option>
			        <option value="WF" <?= ($user_data['country'] ?? '') == 'WF' ? 'selected' : '' ?>>Wallis and Futuna</option>
			        <option value="PS" <?= ($user_data['country'] ?? '') == 'PS' ? 'selected' : '' ?>>West Bank</option>
			        <option value="EH" <?= ($user_data['country'] ?? '') == 'EH' ? 'selected' : '' ?>>Western Sahara</option>
			        <option value="YE" <?= ($user_data['country'] ?? '') == 'YE' ? 'selected' : '' ?>>Yemen</option>
			        <option value="ZM" <?= ($user_data['country'] ?? '') == 'ZM' ? 'selected' : '' ?>>Zambia</option>
			        <option value="ZW" <?= ($user_data['country'] ?? '') == 'ZW' ? 'selected' : '' ?>>Zimbabwe</option>
			</select>
      </td>
    </tr>
  </table>
</div>

<div style="width:600px; text-align:left;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-family:Tahoma,Arial,sans-serif; font-size:13px; border-collapse:collapse;">
  <tr>
      <td colspan="5">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="color:#CC6633; font-weight:bold; font-size:15px; padding-bottom:2px;" valign="middle">Смена пароля</td>
          </tr>
        </table>
      </td>
    </tr>
    <tr>
      <td colspan="5"><table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:10px;"><tr><td height="1" bgcolor="#CCCCCC"></td></tr></table></td>
    </tr>
    <tr>
      <td colspan="5">
        <?php if ($pw_error): ?>
          <div class="errorBox" style="margin-bottom:8px;"> <?=htmlspecialchars($pw_error)?> </div>
        <?php endif; ?>
        <?php if ($pw_success): ?>
          <div class="confirmBox" style="margin-bottom:8px;">Пароль успешно изменён!</div>
        <?php endif; ?>
      </td>
    </tr>
<tr>
  <td width="180" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Старый пароль:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="password" name="old_password" maxlength="64" style="width:200px;">
  </td>
</tr>
<tr>
  <td width="180" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Новый пароль:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="password" name="new_password" maxlength="64" style="width:200px;">
  </td>
</tr>
<tr>
  <td width="180" style="font-size:13px; color:#333; padding-bottom:8px; vertical-align:top;"><b>Повторите новый пароль:</b></td>
  <td style="font-size:13px; color:#222; padding-bottom:8px;" colspan="4">
    <input type="password" name="new_password2" maxlength="64" style="width:200px;">
  </td>
</tr>
<tr>
  <td></td>
  <td style="padding-bottom:8px;" colspan="4">
    <input type="submit" value="Обновить профиль">
  </td>
</tr>
</table>
  </form>
</div>
</center>
<?php
showFooter(); 