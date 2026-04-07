<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
$rows = public_timeline(100);
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
			

	
<b><font size="3" class="h2_heading">Last 100 Public Updates</font></b>

<table id="timeline" class="doing" cellspacing="0">
	<tbody>
<?php
if ($rows === []) {
    ?>
<tr class="odd">
	<td class="user_actions"></td>
	<td class="thumb"></td>
	<td><span class="meta">Nothing to see yet.</span></td>
</tr>
<?php
} else {
    $i = 0;
    foreach ($rows as $s) {
        $sid = (int) $s['id'];
        $rowClass = ($i % 2 === 0) ? 'odd' : 'even';
        $i++;
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
<div class="notify">
	Want an account?<br>
	<a href="account_create.php" class="join">Join for Free!</a><br>
	Have an account? <a href="login.php">Sign in!</a>
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
