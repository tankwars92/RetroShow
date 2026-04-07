<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();
if ($me === null) {
    header('Location: login.php', true, 302);
    exit;
}

$view = (isset($_GET['view']) && $_GET['view'] === 'friends') ? 'friends' : 'requests';
$myId = (int) $me['id'];
$pendingCount = friend_incoming_pending_count($myId);
$incoming = friend_requests_incoming($myId);
$friendsList = friends_accepted_list($myId);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="en-us">
	<title>Twitbirth / Friends</title>
	<link href="stylesheets/screen.css" media="screen, projection" rel="Stylesheet" type="text/css">
	<link href="stylesheets/handheld.css" media="handheld" rel="Stylesheet" type="text/css">
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
</head>
<body class="account" id="front">
	<ul id="accessibility">
		<li><a href="#navigation" accesskey="2">Skip to navigation</a></li>
		<li><a href="#side">Skip to sidebar</a></li>
	</ul>
	<div id="container" class="subpage">
		<?php render_site_header_top_row($me); ?>
		<div id="content"><div class="wrapper">
			<b><font size="3" class="h2_heading">Friends</font></b>
			<ul class="friends_view_tabs">
				<li<?= $view === 'requests' ? ' class="active"' : '' ?>><a href="friends.php?view=requests">Requests<?php
if ($pendingCount > 0) {
    echo ' (' . $pendingCount . ')';
}
?></a></li>
				<li<?= $view === 'friends' ? ' class="active"' : '' ?>><a href="friends.php?view=friends">Friends</a></li>
			</ul>

<?php if ($view === 'requests') { ?>
			<?php if ($incoming === []) { ?>
			<p>No pending friend requests.</p>
			<?php } else { ?>
			<table class="friends_list_table" cellspacing="0">
				<tbody>
				<?php foreach ($incoming as $row) {
				    $fu = rawurlencode($row['username']);
				    $av = avatar_public_url($row['avatar_path'] ?? null);
				    $fid = (int) $row['friendship_id'];
				    ?>
				<tr>
					<td class="friends_thumb"><a href="user.php?id=<?= esc_html($fu) ?>"><img src="<?= esc_html($av) ?>" alt="" width="48" height="48" /></a></td>
					<td class="friends_cell">
						<a href="user.php?id=<?= esc_html($fu) ?>"><?= esc_html($row['display_name']) ?></a>
						<span class="friends_meta">(@<?= esc_html($row['username']) ?>)</span>
						<div class="friends_row_actions">
							<form action="friend_action.php" method="post" class="device_control">
								<input type="hidden" name="action" value="accept" />
								<input type="hidden" name="friendship_id" value="<?= $fid ?>" />
								<input type="hidden" name="return_to" value="friends.php?view=requests" />
								<input type="submit" value="Accept" />
							</form>
							<form action="friend_action.php" method="post" class="device_control">
								<input type="hidden" name="action" value="decline" />
								<input type="hidden" name="friendship_id" value="<?= $fid ?>" />
								<input type="hidden" name="return_to" value="friends.php?view=requests" />
								<input type="submit" value="Decline" />
							</form>
						</div>
					</td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
			<?php } ?>
<?php } else { ?>
			<?php if ($friendsList === []) { ?>
			<p>You have no friends yet. Accept requests or add people from their profiles.</p>
			<?php } else { ?>
			<table class="friends_list_table" cellspacing="0">
				<tbody>
				<?php foreach ($friendsList as $row) {
				    $fu = rawurlencode($row['username']);
				    $av = avatar_public_url($row['avatar_path'] ?? null);
				    ?>
				<tr>
					<td class="friends_thumb"><a href="user.php?id=<?= esc_html($fu) ?>"><img src="<?= esc_html($av) ?>" alt="" width="48" height="48" /></a></td>
					<td class="friends_cell">
						<a href="user.php?id=<?= esc_html($fu) ?>"><?= esc_html($row['display_name']) ?></a>
						<span class="friends_meta">(@<?= esc_html($row['username']) ?>)</span>
						<div class="friends_row_actions">
							<form action="friend_action.php" method="post" class="device_control" onsubmit="var b=this.querySelector('input[type=submit]');if(b){b.disabled=true;}return true;">
								<input type="hidden" name="action" value="unfriend" />
								<input type="hidden" name="other_user" value="<?= esc_html($row['username']) ?>" />
								<input type="hidden" name="return_to" value="friends.php?view=friends" />
								<input type="submit" value="Remove" />
							</form>
						</div>
					</td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
			<?php } ?>
<?php } ?>
		</div></div>
		<?php render_site_footer(); ?>
	</div>
</body>
</html>
