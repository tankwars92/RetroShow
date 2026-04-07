<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
$homeRows = public_timeline(3);
$featuredPosters = users_top_by_status_count(5);
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
			

<b><font size="3" class="h2_heading">A global community of friends and strangers answering one simple question: <em>What are you doing?</em></font></b>

<div id="tour" style="margin:5; padding:0; line-height:0;"></div>

<style>
#tour {
    margin: 0;
    padding: 0;
    line-height: 0;
    font-size: 0;
    height: 200px;
    width: 500px;
}
#tour object, #tour embed {
    display: block;
    margin: 0;
    padding: 0;
    border: 0;
}
</style>

<script>
function hasNativeFlash() {
    try { return !!new ActiveXObject("ShockwaveFlash.ShockwaveFlash"); } catch(e) {}
    if (navigator.mimeTypes && navigator.mimeTypes["application/x-shockwave-flash"]) return true;
    return false;
}

window.onload = function() {
    var container = document.getElementById("tour");

    if (hasNativeFlash()) {
        container.innerHTML =
            '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" ' +
            'width="500" height="200">' +
            '<param name="movie" value="flash/slide_show.swf">' +
            '<param name="quality" value="high">' +
            '<param name="wmode" value="transparent">' +
            '</object>';
    } else {
        var ruffleScript = document.createElement("script");
        ruffleScript.src = "https://unpkg.com/@ruffle-rs/ruffle";
        ruffleScript.onload = function() {
            if (window.RufflePlayer) {
                window.RufflePlayer.config = {
                    autoplay: "on",
                    unmuteOverlay: "hidden",
                    splashScreen: false
                };
                var ruffle = window.RufflePlayer.newest();
                var player = ruffle.createPlayer();
                player.style.width = "500px";
                player.style.height = "200px";
                container.appendChild(player);
                player.load("flash/slide_show.swf");
            }
        };
        document.head.appendChild(ruffleScript);
    }
};
</script>

<b><font size="3" class="h3_heading"><a href="public_timeline.php">Recent Public Updates</a></font></b>
<table class="doing" id="timeline" cellspacing="0">
	<tbody>
<?php
if ($homeRows === []) {
    ?>
<tr class="odd">
	<td class="user_actions"></td>
	<td class="thumb"></td>
	<td>
		<span class="meta">Nothing to see yet.</span>
	</td>
</tr>
<?php
} else {
    $hi = 0;
    foreach ($homeRows as $s) {
        $sid = (int) $s['id'];
        $rowClass = ($hi % 2 === 0) ? 'odd' : 'even';
        $hi++;
        $uenc = rawurlencode($s['username']);
        $thumb = avatar_public_url($s['avatar_path'] ?? null);
        $tp = format_status_time($s['created_at']);
        ?>
<tr class="<?= $rowClass ?>" id="status_<?= $sid ?>">
	<td class="user_actions" id="user_action_<?= (int) $s['user_id'] ?>">
</td>
	
		<td class="thumb">
		<a href="user.php?id=<?= esc_html($uenc) ?>"><img alt="" src="<?= esc_html($thumb) ?>" width="48" height="48" style="vertical-align:middle" /></a>
	</td>
		
	<td>	
					<strong><a href="user.php?id=<?= esc_html($uenc) ?>"><?= esc_html($s['display_name']) ?></a></strong>
		
					<?= format_status_body_html((string) $s['body']) ?>

				
		<span class="meta">
			
						<a href="status.php?id=<?= $sid ?>"><?= esc_html($tp) ?></a>
						from web

			<span id="status_actions_<?= $sid ?>">
</span>

		</span>
	</td>
</tr>
<?php
    }
}
?>

</tbody></table>



		</div></div><hr>

	<div id="side">
<?php if ($me === null) { ?>
		<div class="msg">
	<b><font size="3" class="msg_heading">Please Sign In!</font></b>
</div>

<form action="login.php" class="signin" method="post" name="f"><fieldset>
	<div>
		<label for="username_or_email"><strong>Username</strong> or Email</label>
		<input id="email" name="username_or_email" type="text">
		<small><strong>*</strong> Note: You can no longer sign-in with your phone number!</small>
	</div>
	<div>
		<label for="password">Password</label>
		<input id="pass" name="password" type="password">
	</div>
	<input id="remember_me" name="remember_me" type="checkbox" value="1"> <label for="remember_me">Remember me</label>
	<input id="submit" name="commit" type="submit" value="Sign In!">
</fieldset>
</form>

<div class="notify">
	Want an account?<br>
	<a href="account_create.php" class="join">Join for Free!</a><br>
	It’s fast and easy!
</div>
<?php } ?>
<?php render_sidebar_friend_requests_notice($me); ?>

<ul class="featured">
	<li><strong>Featured!</strong></li>
<?php
if ($featuredPosters === []) {
    ?>
	<li><span class="meta">No updates yet.</span></li>
<?php
} else {
    foreach ($featuredPosters as $fp) {
        $fu = rawurlencode($fp['username']);
        $fa = avatar_public_url($fp['avatar_path'] ?? null);
        ?>
	<li>
	<a href="user.php?id=<?= esc_html($fu) ?>"><img alt="" src="<?= esc_html($fa) ?>" width="24" height="24" /></a>
	<a href="user.php?id=<?= esc_html($fu) ?>"><?= esc_html($fp['display_name']) ?></a>
	</li>
<?php
    }
}
?>
</ul>


	</div>
		<?php render_site_footer(); ?>
			</div>

</body></html>
