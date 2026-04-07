<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
$errors = [];
$form = [
    'name' => '',
    'screen_name' => '',
    'email' => '',
    'time_zone' => 'Pacific Time (US & Canada)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['user'] ?? [];
    $pwd = (string) ($u['password'] ?? '');
    $pwd2 = (string) ($_POST['password']['password_confirmation'] ?? '');

    $fileProfile = null;
    if (isset($_FILES['user']['tmp_name']['profile_image'])) {
        $fileProfile = [
            'name' => $_FILES['user']['name']['profile_image'] ?? '',
            'type' => $_FILES['user']['type']['profile_image'] ?? '',
            'tmp_name' => $_FILES['user']['tmp_name']['profile_image'] ?? '',
            'error' => (int) ($_FILES['user']['error']['profile_image'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($_FILES['user']['size']['profile_image'] ?? 0),
        ];
    }

    $form['name'] = trim((string) ($u['name'] ?? ''));
    $form['screen_name'] = trim((string) ($u['screen_name'] ?? ''));
    $form['email'] = trim((string) ($u['email'] ?? ''));
    $form['time_zone'] = trim((string) ($u['time_zone'] ?? ''));

    if ($form['name'] === '') {
        $errors[] = 'Please enter your name.';
    }
    if (!preg_match('/^[a-zA-Z0-9_]{1,32}$/', $form['screen_name'])) {
        $errors[] = 'Username: letters, numbers, and underscore only, up to 32 characters.';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if (strlen($pwd) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($pwd !== $pwd2) {
        $errors[] = 'Passwords do not match.';
    }

    if (function_exists('imagecreatetruecolor')) {
        $expect = (string) ($_SESSION['reg_captcha'] ?? '');
        $got = strtoupper(preg_replace('/\s+/', '', (string) ($u['captcha'] ?? '')));
        if ($expect === '' || $got === '' || !hash_equals($expect, $got)) {
            $errors[] = 'Wrong verification code.';
        }
    }

    if ($fileProfile !== null && $fileProfile['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($fileProfile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Could not upload picture.';
        } else {
            $info = @getimagesize($fileProfile['tmp_name']);
            $mime = is_array($info) ? ($info['mime'] ?? '') : '';
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if ($info === false || !in_array($mime, $allowed, true)) {
                $errors[] = 'Picture must be JPG, PNG, GIF, or WebP.';
            }
        }
    }

    if ($errors === []) {
        $pdo = app_pdo();
        $st = $pdo->prepare('SELECT id FROM users WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE');
        $st->execute([$form['screen_name'], $form['email']]);
        if ($st->fetch()) {
            $errors[] = 'That username or email is already taken.';
        } else {
            $excludePublic = isset($u['exclude_public']) && (string) $u['exclude_public'] === '1' ? 1 : 0;
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO users (username, display_name, email, password_hash, time_zone, exclude_from_public_timeline) VALUES (?,?,?,?,?,?)');
            $ins->execute([
                $form['screen_name'],
                $form['name'],
                $form['email'],
                $hash,
                $form['time_zone'] !== '' ? $form['time_zone'] : null,
                $excludePublic,
            ]);
            $newId = (int) $pdo->lastInsertId();
            $avatarRel = save_avatar_after_register($newId, $fileProfile);
            if ($avatarRel !== null) {
                $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$avatarRel, $newId]);
            }
            unset($_SESSION['reg_captcha']);
            login_user($newId);
            header('Location: user.php?id=' . rawurlencode($form['screen_name']), true, 302);
            exit;
        }
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>

	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="en-us">
	<title>Twitbirth: What are you doing?</title>
	
	<link href="stylesheets/screen.css" media="screen, projection" rel="Stylesheet" type="text/css">
	<link href="stylesheets/handheld.css" media="handheld" rel="Stylesheet" type="text/css">

	
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
	
</head>
<body class="account" id="front">
 
		
	<ul id="accessibility">
		<li>
			<a href="#navigation" accesskey="2">Skip to navigation</a>
		</li>
		<li>
			<a href="#side">Skip to sidebar</a>
		</li>
	</ul>
	
	<div id="container" class="subpage">
		<?php render_site_header_top_row($me); ?>

		<div id="content"><div class="wrapper">	
			<b><font size="3" class="h2_heading">Create a Free Twitbirth Account</font></b>

<?php if ($errors !== []) { ?>
			<div class="notify" style="margin-bottom:1em;border-color:#c00;">
				<?php foreach ($errors as $msg) { ?>
					<p><?= esc_html($msg) ?></p>
				<?php } ?>
			</div>
<?php } ?>

<form action="account_create.php" enctype="multipart/form-data" method="post" name="f">
<fieldset>
	<table cellspacing="0">
		<tbody><tr>
			<th><label for="user_name">Name</label></th>
			<td><input id="user_name" name="user[name]" size="30" type="text" value="<?= esc_html($form['name']) ?>"> <small>Real name or nick name</small></td>
		</tr>
		<tr>
			<th><label for="user_username">Create Username</label></th>
			<td><input id="user_screen_name" name="user[screen_name]" size="30" type="text" value="<?= esc_html($form['screen_name']) ?>"> <small>For signing in to Twitbirth (no spaces allowed!)</small></td>
		</tr>
		<tr>
			<th><label for="user_password">Create Password</label></th>
			<td><input id="user_password" name="user[password]" type="password" autocomplete="new-password"> <small>Six characters or more (be tricky!)</small></td>
		</tr>
		<tr>
			<th><label for="password_password_confirmation">Retype Password</label></th>
			<td><input id="password_password_confirmation" name="password[password_confirmation]" size="30" type="password"></td>
		</tr>
		<tr>
			<th><label for="user_email">Email Address</label></th>
			<td><input id="user_email" name="user[email]" size="30" type="text" value="<?= esc_html($form['email']) ?>"> <small>In case you forget your password!</small></td>
		</tr>
		<tr>
			<th><label for="user_time_zone">Time Zone</label></th>
			<td><select id="user_time_zone" name="user[time_zone]"><?php readfile(__DIR__ . '/includes/timezone_select_options.inc.html'); ?></select></td>
		</tr>
		<tr>
			<th>
				<label for="user_profile_image">
					Picture<br>
				</label>
			</th>
			<td>
				<input id="user_profile_image_temp" name="user[profile_image_temp]" type="hidden"><input id="user_profile_image" name="user[profile_image]" size="30" type="file">
				<p><small>Minimum size 48x48 pixels (jpg, gif, png)</small></p>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<input id="user_exclude_public" name="user[exclude_public]" type="checkbox" value="1"> <label for="user_exclude_public">Don't show my updates on the public timeline</label>
				<p><small>Your profile and RSS stay available; only the site-wide public timeline and "Featured" list will hide your posts.</small></p>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<p>By default, we’ll send you occasional Twitbirth news by email. It’s extremely
					easy to unsubscribe at any time (one click in the email).</p>
				<p>By joining Twitbirth, you confirm that you are over 13 years of age and accept the <a href="/web/20070104121950/http://Twitbirth.com/tos" target="_blank">Terms of Service</a>.</p>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<p><img src="captcha.php?t=<?= (int) time() ?>" alt="" width="120" height="40" id="captcha_img" border="2"/></p>
				<b>Enter the text displayed in the image above:</b>
				<input id="user_captcha" name="user[captcha]" type="text" size="12" maxlength="12" autocomplete="off" value="">
			</td>
		</tr>
		<tr>
			<th></th>
			<td><input name="commit" type="submit" value="Continue"></td>
		</tr>
	</tbody></table>
</fieldset>
</form>
<script type="text/javascript">
//<![CDATA[
$('user_name').focus()
var _tz = document.getElementById('user_time_zone');
if (_tz) { _tz.value = <?= json_encode($form['time_zone'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; }
//]]>
</script>
		</div></div><hr>

	<div id="side">
<?php render_sidebar_friend_requests_notice($me); ?>
<?php if ($me === null) { ?>
		<div class="notify">
	Want an account?<br>
	<a href="account_create.php" class="join">Join for Free!</a><br>
	Have an account? <a href="login.php">Sign in!</a>
</div>
<?php } ?>
	</div>
		<?php render_site_footer(); ?>
			</div>

</body></html>
