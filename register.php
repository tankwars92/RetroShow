<?php 
include("init.php"); 
include("template.php");

$error = '';
$success = '';

$captcha_error = '';

function register_generate_captcha() {
    $a = rand(1, 9);
    $b = rand(1, 9);
    $_SESSION['register_captcha_a'] = $a;
    $_SESSION['register_captcha_b'] = $b;
    $_SESSION['register_captcha_answer'] = $a + $b;
}

if ($_POST) {
    if (isset($_POST['action_signup'])) {
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password1 = $_POST['password1'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $country = $_POST['country'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $birthday_mon = $_POST['birthday_mon'] ?? '';
        $birthday_day = $_POST['birthday_day'] ?? '';
        $birthday_yr = $_POST['birthday_yr'] ?? '';
        $captcha_answer = trim($_POST['captcha'] ?? '');
        $expected = isset($_SESSION['register_captcha_answer']) ? (string)$_SESSION['register_captcha_answer'] : '';

        if ($captcha_answer === '' || $expected === '' || (string)intval($captcha_answer) !== $expected) {
            $error = "Неверный ответ на проверочный вопрос.";
        } elseif ($email == '' || $username == '' || $password1 == '' || $password2 == '') {
            $error = "Пожалуйста, заполните все обязательные поля.";
        } elseif ($password1 !== $password2) {
            $error = "Пароли не совпадают.";
        } elseif (strlen($password1) < 6) {
            $error = "Пароль должен содержать минимум 6 символов.";
    } else {
        $stmt = $db->prepare("SELECT login FROM users WHERE login = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Пользователь с таким именем уже существует.";
            } else {
                $stmt = $db->prepare("SELECT login FROM users WHERE email = ?");
                $stmt->execute([$email]);
        if ($stmt->fetch()) {
                    $error = "Пользователь с таким email уже существует.";
        } else {
            $now = time();
                    $stmt = $db->prepare("INSERT INTO users (login, pass, email, country, gender, birthday_mon, birthday_day, birthday_yr, signup_time, last_login) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $password1, $email, $country, $gender, $birthday_mon, $birthday_day, $birthday_yr, $now, $now]); 
                    $_SESSION['user'] = $username;
            header("Location: index.php");
            exit;
                }
            }
        }
    } elseif (isset($_POST['action_login'])) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username == '' || $password == '') {
            $error = "Пожалуйста, введите имя пользователя и пароль.";
        } else {
            $stmt = $db->prepare("SELECT login FROM users WHERE login = ? AND pass = ?");
            $stmt->execute([$username, $password]);
            if ($stmt->fetch()) {
                $_SESSION['user'] = $username;
                $stmt = $db->prepare("UPDATE users SET last_login = ? WHERE login = ?");
                $stmt->execute([time(), $username]);
                header("Location: index.php");
                exit;
            } else {
                $error = "Неверное имя пользователя или пароль.";
            }
        }
    }
}

register_generate_captcha();

$captcha_a = $_SESSION['register_captcha_a'] ?? 0;
$captcha_b = $_SESSION['register_captcha_b'] ?? 0;

showHeader("Регистрация");
?>

<?php if ($error): ?>
	<div class="errorBox">
		<?= htmlspecialchars($error) ?>
	</div>
<?php endif; ?>
<table width="650" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td width="350" style="padding-right: 15px;">
		
<div id="suSignupDiv" class="contentBox" style="width: 300px !important; max-width: 300px;">
<h2>Присоединиться к RetroShow</h2>
        Это бесплатно и просто. <span class="smallText"><b>(Все поля обязательны)</b></span><br>
		
	
	<br>
	
	<form name="signupForm" id="signupForm" method="post">
		<input type="hidden" name="current_form" value="signupForm">
		<input type="hidden" name="signup_type" value="">
			
		
	
		
	
		
	
		
	
		
	

	
		<table class="dataEntryTableSmall" border="0" style="width: 280px !important; max-width: 280px;">
			<tbody><tr>
				<td class="formLabel">	<nobr>Email:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="1" type="text" size="20" maxlength="60" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></td>
				
			</tr>
			<tr>
				<td class="formLabel">	<nobr>Логин:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="2" type="text" size="20" maxlength="20" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></td>
			</tr>
			<tr>
				<td class="formLabel">	<nobr>Пароль:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="3" type="password" size="20" maxlength="20" name="password1" value=""></td>
			</tr>
			<tr>
				<td class="formLabel">	<nobr>Повторите пароль:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="4" type="password" size="20" maxlength="20" name="password2" value=""></td>
			</tr>
			<tr>
				<td class="formLabel">	<nobr>Страна:</nobr>
</td>
				<td class="formFieldSmall">
			<select name="country" tabindex="5">
			        <option value="" selected="selected">---</option>
			        <option value="US">United States</option>
			        <option value="AF">Afghanistan</option>
			        <option value="AL">Albania</option>
			        <option value="DZ">Algeria</option>
			        <option value="AS">American Samoa</option>
			        <option value="AD">Andorra</option>
			        <option value="AO">Angola</option>
			        <option value="AI">Anguilla</option>
			        <option value="AG">Antigua and Barbuda</option>
			        <option value="AR">Argentina</option>
			        <option value="AM">Armenia</option>
			        <option value="AW">Aruba</option>
			        <option value="AU">Australia</option>
			        <option value="AT">Austria</option>
			        <option value="AZ">Azerbaijan</option>
			        <option value="BS">Bahamas</option>
			        <option value="BH">Bahrain</option>
			        <option value="BD">Bangladesh</option>
			        <option value="BB">Barbados</option>
			        <option value="BY">Belarus</option>
			        <option value="BE">Belgium</option>
			        <option value="BZ">Belize</option>
			        <option value="BJ">Benin</option>
			        <option value="BM">Bermuda</option>
			        <option value="BT">Bhutan</option>
			        <option value="BO">Bolivia</option>
			        <option value="BA">Bosnia and Herzegovina</option>
			        <option value="BW">Botswana</option>
			        <option value="BV">Bouvet Island</option>
			        <option value="BR">Brazil</option>
			        <option value="IO">British Indian Ocean</option>
			        <option value="VG">British Virgin Islands</option>
			        <option value="BN">Brunei</option>
			        <option value="BG">Bulgaria</option>
			        <option value="BF">Burkina Faso</option>
			        <option value="BI">Burundi</option>
			        <option value="KH">Cambodia</option>
			        <option value="CM">Cameroon</option>
			        <option value="CA">Canada</option>
			        <option value="CV">Cape Verde</option>
			        <option value="KY">Cayman Islands</option>
			        <option value="CF">Central African Republic</option>
			        <option value="TD">Chad</option>
			        <option value="CL">Chile</option>
			        <option value="CN">China</option>
			        <option value="CX">Christmas Island</option>
			        <option value="CC">Cocos (Keeling) Islands</option>
			        <option value="CO">Colombia</option>
			        <option value="KM">Comoros</option>
			        <option value="CG">Congo</option>
			        <option value="CD">Congo (DRC)</option>
			        <option value="CK">Cook Islands</option>
			        <option value="CR">Costa Rica</option>
			        <option value="CI">Cote d'Ivoire</option>
			        <option value="HR">Croatia</option>
			        <option value="CU">Cuba</option>
			        <option value="CY">Cyprus</option>
			        <option value="CZ">Czech Republic</option>
			        <option value="DK">Denmark</option>
			        <option value="DJ">Djibouti</option>
			        <option value="DM">Dominica</option>
			        <option value="DO">Dominican Republic</option>
			        <option value="TP">East Timor</option>
			        <option value="EC">Ecuador</option>
			        <option value="EG">Egypt</option>
			        <option value="SV">El Salvador</option>
			        <option value="GQ">Equatorial Guinea</option>
			        <option value="ER">Eritrea</option>
			        <option value="EE">Estonia</option>
			        <option value="ET">Ethiopia</option>
			        <option value="FK">Falkland Islands</option>
			        <option value="FO">Faroe Islands</option>
			        <option value="FJ">Fiji</option>
			        <option value="FI">Finland</option>
			        <option value="FR">France</option>
			        <option value="GF">French Guyana</option>
			        <option value="PF">French Polynesia</option>
			        <option value="TF">French Southern Lands</option>
			        <option value="GA">Gabon</option>
			        <option value="GM">Gambia</option>
			        <option value="GZ">Gaza Strip</option>
			        <option value="GE">Georgia</option>
			        <option value="DE">Germany</option>
			        <option value="GH">Ghana</option>
			        <option value="GI">Gibraltar</option>
			        <option value="GR">Greece</option>
			        <option value="GL">Greenland</option>
			        <option value="GD">Grenada</option>
			        <option value="GP">Guadeloupe</option>
			        <option value="GU">Guam</option>
			        <option value="GT">Guatemala</option>
			        <option value="GN">Guinea</option>
			        <option value="GW">Guinea-Bissau</option>
			        <option value="GY">Guyana</option>
			        <option value="HT">Haiti</option>
			        <option value="HM">Heard & McDonald Islands</option>
			        <option value="VA">Holy See (Vatican City)</option>
			        <option value="HN">Honduras</option>
			        <option value="HK">Hong Kong</option>
			        <option value="HU">Hungary</option>
			        <option value="IS">Iceland</option>
			        <option value="IN">India</option>
			        <option value="ID">Indonesia</option>
			        <option value="IR">Iran</option>
			        <option value="IQ">Iraq</option>
			        <option value="IE">Ireland</option>
			        <option value="IL">Israel</option>
			        <option value="IT">Italy</option>
			        <option value="JM">Jamaica</option>
			        <option value="JP">Japan</option>
			        <option value="JO">Jordan</option>
			        <option value="KZ">Kazakhstan</option>
			        <option value="KE">Kenya</option>
			        <option value="KI">Kiribati</option>
			        <option value="KW">Kuwait</option>
			        <option value="KG">Kyrgyzstan</option>
			        <option value="LA">Laos</option>
			        <option value="LV">Latvia</option>
			        <option value="LB">Lebanon</option>
			        <option value="LS">Lesotho</option>
			        <option value="LR">Liberia</option>
			        <option value="LY">Libya</option>
			        <option value="LI">Liechtenstein</option>
			        <option value="LT">Lithuania</option>
			        <option value="LU">Luxembourg</option>
			        <option value="MO">Macau</option>
			        <option value="MK">Macedonia</option>
			        <option value="MG">Madagascar</option>
			        <option value="MW">Malawi</option>
			        <option value="MY">Malaysia</option>
			        <option value="MV">Maldives</option>
			        <option value="ML">Mali</option>
			        <option value="MT">Malta</option>
			        <option value="MH">Marshall Islands</option>
			        <option value="MQ">Martinique</option>
			        <option value="MR">Mauritania</option>
			        <option value="MU">Mauritius</option>
			        <option value="YT">Mayotte</option>
			        <option value="MX">Mexico</option>
			        <option value="FM">Micronesia</option>
			        <option value="MD">Moldova</option>
			        <option value="MC">Monaco</option>
			        <option value="MN">Mongolia</option>
			        <option value="MS">Montserrat</option>
			        <option value="MA">Morocco</option>
			        <option value="MZ">Mozambique</option>
			        <option value="MM">Myanmar</option>
			        <option value="NA">Namibia</option>
			        <option value="NR">Naura</option>
			        <option value="NP">Nepal</option>
			        <option value="NL">Netherlands</option>
			        <option value="AN">Netherlands Antilles</option>
			        <option value="NC">New Caledonia</option>
			        <option value="NZ">New Zealand</option>
			        <option value="NI">Nicaragua</option>
			        <option value="NE">Niger</option>
			        <option value="NG">Nigeria</option>
			        <option value="NU">Niue</option>
			        <option value="NF">Norfolk Island</option>
			        <option value="KP">North Korea</option>
			        <option value="MP">Northern Marianas</option>
			        <option value="NO">Norway</option>
			        <option value="OM">Oman</option>
			        <option value="PK">Pakistan</option>
			        <option value="PW">Palau</option>
			        <option value="PA">Panama</option>
			        <option value="PG">Papua New Guinea</option>
			        <option value="PY">Paraguay</option>
			        <option value="PE">Peru</option>
			        <option value="PH">Philippines</option>
			        <option value="PN">Pitcairn</option>
			        <option value="PL">Poland</option>
			        <option value="PT">Portugal</option>
			        <option value="PR">Puerto Rico</option>
			        <option value="QA">Qatar</option>
			        <option value="RE">Reunion</option>
			        <option value="RO">Romania</option>
			        <option value="RU">Russia</option>
			        <option value="RW">wanda</option>
			        <option value="KN">Saint Kitts and Nevis</option>
			        <option value="LC">Saint Lucia</option>
			        <option value="VC">St. Vincent & Grenadines</option>
			        <option value="WS">Samoa</option>
			        <option value="SM">San Marino</option>
			        <option value="ST">Sao Tome and Principe</option>
			        <option value="SA">Saudi Arabia</option>
			        <option value="SN">Senegal</option>
			        <option value="CS">Serbia & Montenegro</option>
			        <option value="SC">Seychelles</option>
			        <option value="SL">Sierra Leone</option>
			        <option value="SG">Singapore</option>
			        <option value="SK">Slovakia</option>
			        <option value="SI">Slovenia</option>
			        <option value="SB">Solomon Islands</option>
			        <option value="SO">Somalia</option>
			        <option value="ZA">South Africa</option>
			        <option value="GS">South Georgia</option>
			        <option value="KR">South Korea</option>
			        <option value="ES">Spain</option>
			        <option value="LK">Sri Lanka</option>
			        <option value="SH">St. Helena</option>
			        <option value="PM">St. Pierre and Miquelon</option>
			        <option value="SD">Sudan</option>
			        <option value="SR">Suriname</option>
			        <option value="SJ">Svalbard</option>
			        <option value="SZ">Swaziland</option>
			        <option value="SE">Sweden</option>
			        <option value="CH">Switzerland</option>
			        <option value="SY">Syria</option>
			        <option value="TW">Taiwan</option>
			        <option value="TJ">Tajikistan</option>
			        <option value="TZ">Tanzania</option>
			        <option value="TH">Thailand</option>
			        <option value="TG">Togo</option>
			        <option value="TK">Tokelau</option>
			        <option value="TO">Tonga</option>
			        <option value="TT">Trinidad and Tobago</option>
			        <option value="TN">Tunisia</option>
			        <option value="TR">Turkey</option>
			        <option value="TM">Turkmenistan</option>
			        <option value="TC">Turks and Caicos Islands</option>
			        <option value="TV">Tuvalu</option>
			        <option value="UG">Uganda</option>
			        <option value="UA">Ukraine</option>
			        <option value="AE">United Arab Emirates</option>
			        <option value="GB">United Kingdom</option>
			        <option value="VI">US Virgin Islands</option>
			        <option value="UY">Uruguay</option>
			        <option value="UZ">Uzbekistan</option>
			        <option value="VU">Vanuatu</option>
			        <option value="VE">Venezuela</option>
			        <option value="VN">Vietnam</option>
			        <option value="WF">Wallis and Futuna</option>
			        <option value="PS">West Bank</option>
			        <option value="EH">Western Sahara</option>
			        <option value="YE">Yemen</option>
			        <option value="ZM">Zambia</option>
			        <option value="ZW">Zimbabwe</option>
			</select>
				</td>
				<td>&nbsp;</td>
			</tr>

		
			<tr>
				<td class="formLabel">	<nobr>Пол:</nobr>
</td>
				<td class="formFieldSmall">
					<input tabindex="11" name="gender" type="radio" value="m"> Мужской
					&nbsp;
					<input tabindex="12" name="gender" type="radio" value="f"> Женский
				</td>
			</tr>

			<tr>
				<td class="formLabel">	<nobr>Родился:</nobr>
</td>
				<td class="formFieldSmall">
	<select name="birthday_mon" tabindex="13">
			<option value="---" selected="selected">---</option>
				<option value="1"> Янв </option>
				<option value="2"> Фев </option>
				<option value="3"> Мар </option>
				<option value="4"> Апр </option>
				<option value="5"> Май </option>
				<option value="6"> Июн </option>
				<option value="7"> Июл </option>
				<option value="8"> Авг </option>
				<option value="9"> Сен </option>
				<option value="10"> Окт </option>
				<option value="11"> Ноя </option>
				<option value="12"> Дек </option>
		</select>

		<select name="birthday_day" tabindex="14">
			<option value="---" selected="selected">---</option>
			        <option>1</option>
			        <option>2</option>
			        <option>3</option>
			        <option>4</option>
			        <option>5</option>
			        <option>6</option>
			        <option>7</option>
			        <option>8</option>
			        <option>9</option>
			        <option>10</option>
			        <option>11</option>
			        <option>12</option>
			        <option>13</option>
			        <option>14</option>
			        <option>15</option>
			        <option>16</option>
			        <option>17</option>
			        <option>18</option>
			        <option>19</option>
			        <option>20</option>
			        <option>21</option>
			        <option>22</option>
			        <option>23</option>
			        <option>24</option>
			        <option>25</option>
			        <option>26</option>
			        <option>27</option>
			        <option>28</option>
			        <option>29</option>
			        <option>30</option>
			        <option>31</option>
		</select>

	
	<select name="birthday_yr" tabindex="15">
	<option value="---" selected="selected">---</option>
    <option value="2024">2024</option>
    <option value="2023">2023</option>
    <option value="2022">2022</option>
    <option value="2021">2021</option>
    <option value="2020">2020</option>
    <option value="2019">2019</option>
    <option value="2018">2018</option>
    <option value="2017">2017</option>
    <option value="2016">2016</option>
    <option value="2015">2015</option>
    <option value="2014">2014</option>
    <option value="2013">2013</option>
    <option value="2012">2012</option>
    <option value="2011">2011</option>
    <option value="2010">2010</option>
    <option value="2009">2009</option>
    <option value="2008">2008</option>
    <option value="2007">2007</option>
    <option value="2006">2006</option>
    <option value="2005">2005</option>
    <option value="2004">2004</option>
    <option value="2003">2003</option>
    <option value="2002">2002</option>
    <option value="2001">2001</option>
    <option value="2000">2000</option>
    <option value="1999">1999</option>
    <option value="1998">1998</option>
    <option value="1997">1997</option>
    <option value="1996">1996</option>
    <option value="1995">1995</option>
    <option value="1994">1994</option>
    <option value="1993">1993</option>
    <option value="1992">1992</option>
    <option value="1991">1991</option>
    <option value="1990">1990</option>
    <option value="1989">1989</option>
    <option value="1988">1988</option>
    <option value="1987">1987</option>
    <option value="1986">1986</option>
    <option value="1985">1985</option>
    <option value="1984">1984</option><option value="1983">1983</option><option value="1982">1982</option><option value="1981">1981</option><option value="1980">1980</option>
    <option value="1979">1979</option><option value="1978">1978</option><option value="1977">1977</option>
    <option value="1976">1976</option><option value="1975">1975</option><option value="1974">1974</option>
    <option value="1973">1973</option><option value="1972">1972</option><option value="1971">1971</option><option value="1970">1970</option><option value="1969">1969</option><option value="1968">1968</option><option value="1967">1967</option><option value="1966">1966</option><option value="1965">1965</option><option value="1964">1964</option><option value="1963">1963</option><option value="1962">1962</option><option value="1961">1961</option><option value="1960">1960</option><option value="1959">1959</option><option value="1958">1958</option><option value="1957">1957</option><option value="1956">1956</option><option value="1955">1955</option><option value="1954">1954</option><option value="1953">1953</option><option value="1952">1952</option><option value="1951">1951</option><option value="1950">1950</option><option value="1949">1949</option><option value="1948">1948</option><option value="1947">1947</option><option value="1946">1946</option><option value="1945">1945</option><option value="1944">1944</option><option value="1943">1943</option><option value="1942">1942</option><option value="1941">1941</option><option value="1940">1940</option><option value="1939">1939</option><option value="1938">1938</option><option value="1937">1937</option><option value="1936">1936</option><option value="1935">1935</option><option value="1934">1934</option><option value="1933">1933</option><option value="1932">1932</option><option value="1931">1931</option><option value="1930">1930</option><option value="1929">1929</option><option value="1928">1928</option><option value="1927">1927</option><option value="1926">1926</option><option value="1925">1925</option><option value="1924">1924</option><option value="1923">1923</option><option value="1922">1922</option><option value="1921">1921</option><option value="1920">1920</option>		</select>
				</td>
			</tr>
			<tr>
                <td class="formLabel"><nobr>Проверка:</nobr></td>
                <td class="formFieldSmall">
                    <span style="font-size:12px;"><?= htmlspecialchars($captcha_a) ?> + <?= htmlspecialchars($captcha_b) ?> = </span>
                    <input tabindex="5" type="text" size="4" maxlength="2" name="captcha" value="<?= htmlspecialchars($_POST['captcha'] ?? '') ?>">
					<br>
					<i>(защита от спам-ботов)</i>
                </td>
            </tr>
			<tr>
				
				<td class="formFieldSmall"> &nbsp;</td>
				<td class="formFieldSmall"><br>- Я подтверждаю, что мне больше 13 лет.
					<br>- Я согласен с <a href="/t/terms" target="_blank">условиями использования</a> и <a href="/t/privacy" target="_blank">политикой конфиденциальности</a>.
					<p><input tabindex="18" name="action_signup" type="submit" value="Зарегистрироваться"></p>	
				</td>
			</tr>
		</tbody></table>
	</form>
  </div>
		</td>
		<td width="250">

<div id="suSigninDiv">
	<h2>Войти</h2>
	<p>Уже зарегистрированы? Войдите здесь.</p>
	
	<form method="post" name="loginForm" id="loginForm">
		<input type="hidden" name="current_form" value="loginForm">
			
		
	
		
	
		
	
		
	
		
	

		<table class="dataEntryTableSmall">
				<tbody><tr>
				<td class="formLabel">	<nobr>Логин:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="101" type="text" size="20" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"></td>
				</tr>
				<tr>
				<td class="formLabel">	<nobr>Пароль:</nobr>
</td>
				<td class="formFieldSmall"><input tabindex="102" type="password" size="20" name="password"></td>
				</tr>
				<tr>
					<td class="formLabel">&nbsp;</td>
				<td class="formFieldSmall"><input tabindex="103" type="submit" name="action_login" value="Войти">
				<p class="smallText"><b>Забыли:</b>&nbsp;<a href="forgot_username.php">Имя</a> | <a href="forgot.php">Пароль</a></p>
					</td>
				</tr>
		</tbody></table>
  </form>
	<br>
	<h2>Что такое RetroShow?</h2>
	<p>RetroShow - это способ донести ваши видео до людей, которые важны для вас.<br>
	С RetroShow вы можете:</p>
	<ul>			
		<li>Загружать, тегировать и делиться своими видео по всему миру</li>
		<li>Просматривать тысячи оригинальных видео, загруженных участниками сообщества</li>
		<li>Находить, присоединяться и создавать видео-группы для связи с людьми со схожими интересами</li>
		<li>Настраивать свой опыт с помощью плейлистов и подписок</li>
		<li>Интегрировать RetroShow с вашим сайтом, используя встраивание видео или API</li>
	</ul>
	<p>Чтобы узнать больше о нашем сервисе, посетите <a href="help.php">Центр помощи</a>.</p>
		</div>
		
		</td>
	</tr>
</tbody></table>

<?php showFooter(); ?>
