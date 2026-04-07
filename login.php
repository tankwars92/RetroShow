<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim((string) ($_POST['username_or_email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $error = 'Enter your username or email and password.';
    } else {
        $pdo = app_pdo();
        $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? COLLATE NOCASE OR email = ? COLLATE NOCASE');
        $st->execute([$login, $login]);
        $row = $st->fetch();
        if ($row && password_verify($password, $row['password_hash'])) {
            login_user((int) $row['id']);
            header('Location: index.php', true, 302);
            exit;
        }
        $error = 'Wrong username or password.';
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
			<b><font size="3" class="h2_heading">Sign in to Twitbirth</font></b>

<?php if ($error !== '') { ?>
			<p style="color:#c00;"><strong><?= esc_html($error) ?></strong></p>
<?php } ?>

<p>If you’ve been using Twitbirth from your phone,
	<a href="/web/20070307121657/http://Twitbirth.com/account/complete">click here</a>
	and we’ll get you signed up on the web.</p>
	

<form action="login.php" method="post" name="f">
    <fieldset>
    	<table cellspacing="0">
    		<tbody><tr>
    			<th><label for="username_or_email">Username or Email</label></th>
    			<td><input id="username_or_email" name="username_or_email" type="text"></td>
    		</tr>
    		<tr>
    			<th><label for="password">Password</label></th>
    			<td><input id="password" name="password" type="password"> <small><a href="/web/20070307121657/http://Twitbirth.com/account/resend_password">Forgot?</a></small></td>
    		</tr>
    		<tr>
    			<th></th>
    			<td><input id="remember_me" name="remember_me" type="checkbox" value="1"> <label for="remember_me" class="inline">Remember me</label></td>
    		</tr>
    		<tr>
    			<th></th>
    			<td><input name="commit" type="submit" value="Sign In"></td>
    		</tr>
    	</tbody></table>
    </fieldset>
</form>

<script type="text/javascript">
//<![CDATA[
$('username_or_email').focus()
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