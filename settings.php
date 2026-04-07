<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
if ($me === null) {
    header('Location: login.php', true, 302);
    exit;
}

$errors = [];
$notice = '';

$form = [
    'username' => (string) ($me['username'] ?? ''),
    'display_name' => (string) ($me['display_name'] ?? ''),
    'email' => (string) ($me['email'] ?? ''),
    'time_zone' => (string) ($me['time_zone'] ?? ''),
    'bio' => (string) ($me['bio'] ?? ''),
    'location' => (string) ($me['location'] ?? ''),
    'web' => (string) ($me['web'] ?? ''),
    'exclude_public' => ((int) ($me['exclude_from_public_timeline'] ?? 0)) === 1,
];

$activeTab = isset($_GET['tab']) && in_array((string) $_GET['tab'], ['account', 'picture', 'password'], true)
    ? (string) $_GET['tab']
    : 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['settings_action']) ? (string) $_POST['settings_action'] : 'account';
    if (!in_array($action, ['account', 'picture', 'password'], true)) {
        $action = 'account';
    }
    $activeTab = $action;

    $u = $_POST['user'] ?? [];
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

    if ($action === 'picture') {
        $removeCurrent = isset($_POST['remove_current']) && (string) $_POST['remove_current'] === '1';
        $wantAvatar = $fileProfile !== null && $fileProfile['error'] !== UPLOAD_ERR_NO_FILE;
        if ($removeCurrent) {
            user_clear_avatar_files((int) $me['id']);
            app_pdo()->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')->execute([(int) $me['id']]);
            $me = user_by_id((int) $me['id']) ?: $me;
            $notice = 'Current picture was removed.';
        } elseif (!$wantAvatar) {
            $errors[] = 'Please choose a picture first.';
        } elseif ($fileProfile['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Could not upload picture.';
        } else {
            $info = @getimagesize($fileProfile['tmp_name']);
            $mime = is_array($info) ? ($info['mime'] ?? '') : '';
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if ($info === false || !in_array($mime, $allowed, true)) {
                $errors[] = 'Picture must be JPG, PNG, GIF, or WebP.';
            }
        }
        if ($errors === []) {
            $av = save_avatar_for_existing_user((int) $me['id'], $fileProfile);
            if (!$av['ok']) {
                $errors[] = (string) $av['error'];
            } else {
                if ($av['path'] !== null) {
                    app_pdo()->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([(string) $av['path'], (int) $me['id']]);
                }
                $me = user_by_id((int) $me['id']) ?: $me;
                $notice = 'Your picture was saved.';
            }
        }
    } elseif ($action === 'password') {
        $oldPwd = (string) ($_POST['old_password'] ?? '');
        $newPwd = (string) ($_POST['password'] ?? '');
        $newPwd2 = (string) ($_POST['password_confirmation'] ?? '');

        if ($oldPwd === '' || $newPwd === '' || $newPwd2 === '') {
            $errors[] = 'Please fill in all password fields.';
        } elseif (strlen($newPwd) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPwd !== $newPwd2) {
            $errors[] = 'New password and confirmation do not match.';
        }

        if ($errors === []) {
            $pdo = app_pdo();
            $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $st->execute([(int) $me['id']]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            $hash = $row['password_hash'] ?? null;
            if (!$hash || !password_verify($oldPwd, (string) $hash)) {
                $errors[] = 'Old password is incorrect.';
            } else {
                $newHash = password_hash($newPwd, PASSWORD_DEFAULT);
                $up = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $up->execute([$newHash, (int) $me['id']]);
                $notice = 'Your password was updated.';
            }
        }
    } else {
        $form['username'] = trim((string) ($u['username'] ?? ''));
        $form['display_name'] = trim((string) ($u['name'] ?? ''));
        $form['email'] = trim((string) ($u['email'] ?? ''));
        $form['time_zone'] = trim((string) ($u['time_zone'] ?? ''));
        $form['bio'] = trim((string) ($u['bio'] ?? ''));
        $form['location'] = trim((string) ($u['location'] ?? ''));
        $form['web'] = trim((string) ($u['web'] ?? ''));
        $form['exclude_public'] = isset($u['exclude_public']) && (string) $u['exclude_public'] === '1';

        if ($form['username'] === '') {
            $errors[] = 'Please enter a username.';
        } elseif (!preg_match('/^[A-Za-z0-9_]{1,20}$/', $form['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscore (max 20).';
        }
        if ($form['display_name'] === '') {
            $errors[] = 'Please enter your name.';
        } elseif (strlen($form['display_name']) > 200) {
            $errors[] = 'Name must be 200 characters or less.';
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        }
        if (strlen($form['bio']) > 500) {
            $errors[] = 'Bio must be 500 characters or less.';
        }
        if (strlen($form['location']) > 160) {
            $errors[] = 'Location must be 160 characters or less.';
        }
        if (strlen($form['web']) > 500) {
            $errors[] = 'Web must be 500 characters or less.';
        }

        if ($errors === []) {
            $pdo = app_pdo();
            $st = $pdo->prepare('SELECT id FROM users WHERE email = ? COLLATE NOCASE AND id != ?');
            $st->execute([$form['email'], $me['id']]);
            if ($st->fetch()) {
                $errors[] = 'That email address is already in use.';
            } else {
                $su = $pdo->prepare('SELECT id FROM users WHERE username = ? COLLATE NOCASE AND id != ?');
                $su->execute([$form['username'], $me['id']]);
                if ($su->fetch()) {
                    $errors[] = 'That username is already taken.';
                }
            }
            if ($errors === []) {
                $tz = $form['time_zone'] !== '' ? $form['time_zone'] : null;
                $bio = $form['bio'] !== '' ? $form['bio'] : null;
                $loc = $form['location'] !== '' ? $form['location'] : null;
                $web = $form['web'] !== '' ? $form['web'] : null;
                $excludePublic = $form['exclude_public'] ? 1 : 0;
                $up = $pdo->prepare('UPDATE users SET username = ?, display_name = ?, email = ?, time_zone = ?, bio = ?, location = ?, web = ?, exclude_from_public_timeline = ? WHERE id = ?');
                $up->execute([$form['username'], $form['display_name'], $form['email'], $tz, $bio, $loc, $web, $excludePublic, $me['id']]);
                $me = user_by_id((int) $me['id']);
                if ($me !== null) {
                    $form['username'] = (string) ($me['username'] ?? '');
                    $form['display_name'] = (string) ($me['display_name'] ?? '');
                    $form['email'] = (string) ($me['email'] ?? '');
                    $form['time_zone'] = (string) ($me['time_zone'] ?? '');
                    $form['bio'] = (string) ($me['bio'] ?? '');
                    $form['location'] = (string) ($me['location'] ?? '');
                    $form['web'] = (string) ($me['web'] ?? '');
                    $form['exclude_public'] = ((int) ($me['exclude_from_public_timeline'] ?? 0)) === 1;
                }
                $notice = 'Your settings were saved.';
            }
        }
    }
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="en-us">
	<title>Twitbirth / Settings</title>
	<link href="stylesheets/screen.css" media="screen, projection" rel="Stylesheet" type="text/css">
	<link href="stylesheets/handheld.css" media="handheld" rel="Stylesheet" type="text/css">
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">

	<style>
		.tabMenu li a {text-decoration: underline;}
	</style>
</head>
<body class="account" id="front">
	<ul id="accessibility">
		<li><a href="#navigation" accesskey="2">Skip to navigation</a></li>
		<li><a href="#side">Skip to sidebar</a></li>
	</ul>
	<div id="container" class="subpage">
		<?php render_site_header_top_row($me); ?>
		<div id="content" class="wrapper"	>
			<style type="text/css">
			#container.subpage #content {
				background: none !important;
				padding-top: 0 !important;
			}
			#content font.thumb.settings_profile_head {
				background: none;
				padding: 0;
				margin: 0;
				display: inline;
			}
			</style>
			<b><font size="3" class="thumb settings_profile_head">
				<a href="user.php?id=<?= esc_html(rawurlencode((string) $me['username'])) ?>">
					<img alt="" border="0" src="<?= esc_html(avatar_public_url($me['avatar_path'] ?? null)) ?>" width="48" height="48" style="vertical-align:middle;border:1px solid #999;" />
				</a>
				<?= esc_html((string) $me['username']) ?>
			</font></b>

			<ul class="tabMenu">
				<li id="main-settings-li" class="first<?= $activeTab === 'account' ? ' active' : '' ?>"><a id="main-settings-link" href="settings.php?tab=account">Account</a></li>
				<li id="password-settings-li"<?= $activeTab === 'password' ? ' class="active"' : '' ?>><a id="password-settings-link" href="settings.php?tab=password">Password</a></li>
				<li id="picture-settings-li"<?= $activeTab === 'picture' ? ' class="active"' : '' ?>><a id="picture-settings-link" href="settings.php?tab=picture">Picture</a></li>
			</ul>

			<div class="tab">

<?php if ($errors !== []) { ?>
			<div class="notify" style="margin-bottom:1em;border-color:#c00;">
				<?php foreach ($errors as $msg) { ?>
					<p><?= esc_html($msg) ?></p>
				<?php } ?>
			</div>
<?php } ?>

<form action="settings.php?tab=account" enctype="multipart/form-data" method="post" name="settings_form" id="main-settings"<?= $activeTab === 'account' ? '' : ' style="display:none;"' ?>>
<input type="hidden" name="settings_action" value="account" />
<fieldset>
	<table cellspacing="0">
		<tbody>
		<tr>
			<th><label for="user_name">Full Name</label></th>
			<td><input id="user_name" name="user[name]" size="30" type="text" value="<?= esc_html($form['display_name']) ?>"></td>
		</tr>
		<tr>
			<th><label for="user_username_ro">Username</label></th>
			<td>
				<input id="user_username_ro" name="user[username]" size="30" type="text" value="<?= esc_html($form['username']) ?>">
				Your URL: <?= esc_html('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/') ?><b><font color="#33653e"><?= esc_html($form['username']) ?></font></b><br>No spaces, please.
			</td>
		</tr>
		<tr>
			<th><label for="user_email">Email</label></th>
			<td><input id="user_email" name="user[email]" size="30" type="text" value="<?= esc_html($form['email']) ?>"></td>
		</tr>
		<tr>
			<th><label for="user_time_zone">Time Zone</label></th>
			<td><select id="user_time_zone" name="user[time_zone]"><?php readfile(__DIR__ . '/includes/timezone_select_options.inc.html'); ?></select></td>
		</tr>
		<tr>
			<th><label for="user_web">More Info URL</label></th>
			<td><input id="user_web" name="user[web]" size="50" type="text" value="<?= esc_html($form['web']) ?>"> <br>Have a website? Put the address here.</td>
		</tr>
		<tr>
			<th><label for="user_bio">Bio</label></th>
			<td><textarea id="user_bio" name="user[bio]" rows="4" cols="50"><?= esc_html($form['bio']) ?></textarea> <br>About yourself in fewer than 160 characters.</td>
		</tr>
		<tr>
			<th><label for="user_location">Location</label></th>
			<td><input id="user_location" name="user[location]" size="40" type="text" value="<?= esc_html($form['location']) ?>"> <br>Where in the world are you?</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<input id="user_exclude_public" name="user[exclude_public]" type="checkbox" value="1"<?= $form['exclude_public'] ? ' checked="checked"' : '' ?>> <label for="user_exclude_public">Protect my updates</label>
				<p>Your profile and RSS stay available; only the site-wide public timeline and "Featured" list will hide your posts.</p>
			</td>
		</tr>
		<tr>
			<th></th>
			<td><input class="button" id="button" name="commit" type="submit" value="Save"></td>
		</tr>
		</tbody>
	</table>
</fieldset>
</form>
<form action="settings.php?tab=password" enctype="multipart/form-data" method="post" name="password_form" id="password-settings"<?= $activeTab === 'password' ? '' : ' style="display:none;"' ?>>
<input type="hidden" name="settings_action" value="password" />
<fieldset>
	<table cellspacing="0">
		<tbody>
		<tr>
			<th><label for="old_password">Old Password</label></th>
			<td><input id="old_password" name="old_password" type="password" size="30"></td>
		</tr>
		<tr>
			<th><label for="password">New Password</label></th>
			<td><input id="password" name="password" type="password" size="30"></td>
		</tr>
		<tr>
			<th><label for="password_confirmation">Retype Password</label></th>
			<td><input id="password_confirmation" name="password_confirmation" type="password" size="30"></td>
		</tr>
		<tr>
			<th></th>
			<td><input class="button" id="password_save" name="commit_password" type="submit" value="Save"></td>
		</tr>
		</tbody>
	</table>
</fieldset>
</form>
<form action="settings.php?tab=picture" enctype="multipart/form-data" method="post" name="picture_form" id="picture-settings"<?= $activeTab === 'picture' ? '' : ' style="display:none;"' ?>>
<input type="hidden" name="settings_action" value="picture" />
<fieldset>
	<table cellspacing="0">
		<tbody>
		<tr>
			<th>
				<a href="user.php?id=<?= esc_html(rawurlencode((string) $me['username'])) ?>">
					<img alt="" border="0" src="<?= esc_html(avatar_public_url($me['avatar_path'] ?? null)) ?>" width="48" height="48" style="vertical-align:middle;border:1px solid #999;" />
				</a>
			</th>
			<td>
				<input id="user_profile_image" name="user[profile_image]" size="30" type="file">
				<br><small>Minimum size 48x48 pixels (jpg, gif, png, webp)</small>
			</td>
		</tr>
		<tr>
			<th></th>
			<td>
				<input class="button" id="picture_save" name="commit_picture" type="submit" value="Save">
				<button class="button" type="submit" name="remove_current" value="1">Remove current</button>
			</td>
		</tr>
		</tbody>
	</table>
</fieldset>
</form>
<?php if ($activeTab === 'account') { ?>
<fieldset>
	<table cellspacing="0">
		<tbody>
<tr>
    <th>
	<form action="delete_account.php" method="post" onsubmit="return confirm('Delete your account? This cannot be undone. Your updates will remain, but your profile will disappear.');" style="margin-left: 30px;">
		<input type="submit" value="Delete your account." style="color:#0000FF;background:transparent;border:0;padding:0;text-decoration:underline;cursor:pointer;" />
	</form>

				</th>
				<td></td>
</tr>
</tbody>
</table>
</fieldset>
<?php } ?>
<script type="text/javascript">
//<![CDATA[
var _tz = document.getElementById('user_time_zone');
if (_tz) { _tz.value = <?= json_encode($form['time_zone'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; }
//]]>
</script>
		</div></div>
		<div id="side">
			<div class="header" style="border-bottom: 1px solid #87bc44; padding-bottom: 0px;">
				<b><font style="font-size: 1.2em;">Account</font></b>
			</div>
			<div class="header" style="padding-bottom: 5px;"></div>
			<p>From here you can change your basic account info, fill in your profile data, and set whether you want to be private or public</p>
			<br>
			<b>Tips</b>
			<ol>
				<li>Filling in your profile information will help people find you on Twitbirth. For example, you'll be more likely to turn up in an Twitbirth search if you've added your location or your real name</li>
				<li>Change your Twitbirth username anytime without affecting your existing updates or other data. After changing it, make sure to let your followers know, so you'll continue receiving all of your messages with your new username.</li>
				<li>Protect your profile to keep your Twitbirth updates private, so no one will see them on the public timeline.</li>
			</ol>
		</div>
		<?php render_site_footer(); ?>
	</div>
</body>
</html>
