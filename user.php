<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$username = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($username === '') {
    http_response_code(404);
    header('Location: 404.php');
    exit;
}

$profile = user_by_username($username);
if ($profile === null) {
    http_response_code(404);
    header('Location: 404.php');
    exit;
}

$me = current_user();
$isOwn = $me !== null && (int) $me['id'] === (int) $profile['id'];
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'friends') ? 'friends' : 'prev';
$uid = rawurlencode($profile['username']);
$avatarSrc = avatar_public_url($profile['avatar_path'] ?? null);
$pageTitle = 'Twitbirth / ' . $profile['display_name'];

$rawStatus = isset($profile['current_status']) ? trim((string) $profile['current_status']) : '';
$hasStatus = $rawStatus !== '';

$stAt = $profile['status_updated_at'] ?? null;
$statusMetaPhrase = ($hasStatus && $stAt !== null && $stAt !== '') ? format_status_time($stAt) : '';
$latestSid = latest_status_id((int) $profile['id']);

$profileId = (int) $profile['id'];
$viewAll = isset($_GET['view_all']) && (string) $_GET['view_all'] !== '' && (string) $_GET['view_all'] !== '0';
$statusLimit = $viewAll ? 5000 : 10;
$statusRows = statuses_for_user($profileId, $statusLimit);
$friendFeedRows = $tab === 'friends' ? statuses_friends_last_hours($profileId, 24, 500) : [];
$updatesCount = status_count_for_user($profileId);
$profileFriendsList = friends_accepted_list($profileId);
$friendsCount = friend_accepted_count($profileId);
$followersCount = follower_pending_count($profileId);
$friendRel = 'guest';
if ($me !== null) {
    $friendRel = friend_relation((int) $me['id'], $profileId);
}

$statusMaxChars = 500;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="en-us">
	<title><?= esc_html($pageTitle) ?></title>
	
	<link href="stylesheets/screen.css" media="screen, projection" rel="Stylesheet" type="text/css">

	
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
	
</head>
<body class="account" id="profile">
 
	<style type="text/css">
		font.thumb, font.thumb a { color: #000000; }
		#content font.thumb {
			text-align: left;
			font-weight: normal;
			line-height: 1.2;
		}
		#content font.thumb .profile_heading_main {
			display: inline;
			margin-left: 0.15em;
			vertical-align: middle;
		}
		#content font.thumb .profile_username {
			font-weight: bold;
			vertical-align: middle;
		}
		#content font.thumb .friend_next_name {
			display: inline;
			margin-left: 0.45em;
			font-size: 0.32em;
			font-weight: normal;
			white-space: nowrap;
			vertical-align: middle;
		}
		#content font.thumb .friend_next_name .add_friend_btn {
			padding: 2px 8px;
			font-weight: bold;
			font-size: 1em;
			vertical-align: middle;
			margin: 0;
			cursor: default;
		}
		#content font.thumb .friend_next_name .add_friend_btn[type="submit"] { cursor: pointer; }
		#content font.thumb .friend_next_name a.add_friend_btn {
			text-decoration: none;
			color: #0000ff;
			border: 1px solid #999;
			background: #eee;
			padding: 2px 8px;
		}
		#content font.thumb form.add_friend_form {
			display: inline;
			margin: 0;
			padding: 0;
			vertical-align: middle;
		}
		.tabMenu li a {text-decoration: underline;}
    </style>
	
	<ul id="accessibility">
		<li>
			<a href="#navigation" accesskey="2">Skip to navigation</a>
		</li>
		<li>
			<a href="#side">Skip to sidebar</a>
		</li>
	</ul>
	
	<div id="container">
		<?php render_site_header_top_row($me); ?>

		<div id="content"><div class="wrapper">	
			


	




<b><font size="3" class="thumb">
			<a href="user.php?id=<?= esc_html($uid) ?>"><img alt="" border="0" src="<?= esc_html($avatarSrc) ?>" width="48" height="48" style="vertical-align:middle;border:1px solid #999;" /></a>
			<span class="profile_heading_main">
				<?= esc_html($profile['username']) ?>
<?php if (!$isOwn) { ?>
				<span class="friend_next_name">
	<?php if ($me === null) { ?>
					
	<?php } elseif ($friendRel === 'friends') { ?>
					
	<?php } elseif ($friendRel === 'pending_out') { ?>
					<span class="add_friend_btn add_friend_state"><input type="button" class="add_friend_btn" value="Request sent!" disabled/></span>
	<?php } elseif ($friendRel === 'pending_in') { ?>
					<a href="friends.php?view=requests" class="add_friend_btn">Respond to request</a>
	<?php } else { ?>
					<form class="add_friend_form" action="friend_action.php" method="post" onsubmit="var inp=this.getElementsByTagName('input');for(var i=0;i&lt;inp.length;i++){if(inp[i].type=='submit'){inp[i].disabled=true;inp[i].value='Request sent';break;}}return true;">
						<input type="hidden" name="action" value="request" />
						<input type="hidden" name="to_user" value="<?= esc_html($profile['username']) ?>" />
						<input type="hidden" name="return_to" value="user.php?id=<?= esc_html($uid) ?><?= $tab === 'friends' ? '&amp;tab=friends' : '' ?><?= $viewAll ? '&amp;view_all=1' : '' ?>" />
						<input type="submit" class="add_friend_btn" value="Add as friend" />
					</form>
	<?php } ?>
				</span>
<?php } ?>
			</span>
</font></b>

<?php if ($hasStatus) { ?>
<div class="desc">
		<p><?= format_status_body_html($rawStatus) ?></p>
	<p class="meta">
		<?php if ($latestSid !== null && $statusMetaPhrase !== '') { ?>
		<a href="status.php?id=<?= (int) $latestSid ?>"><?= esc_html($statusMetaPhrase) ?></a>
		<?php } elseif ($statusMetaPhrase !== '') { ?>
		<span><?= esc_html($statusMetaPhrase) ?></span>
		<?php } ?>
		<?php if ($statusMetaPhrase !== '') { ?> from web<?php } ?>

		<span id="status_actions_wrap"></span>
	</p>
	</div>
<?php } ?>
<br>
<?php if ($isOwn) { ?>
    <div>
        <form id="doing-form" action="" method="post" onsubmit="return post_status_update();">
            <input id="csrf_token" name="csrf_token" type="hidden" value="">
            <input id="reply_id" name="reply_id" type="hidden" value="">
            <div class="bar">
                <table>
                    <thead>
                    <tr>
                        <th>
                            <h3 id="rt"
                                style="display:none;">Replying to {}</h3>
                            <h3 id="wryd">What are you doing?</h3>
                        </th>
                        <th>
                            <span id="characters-available"><span
                                    class="chars-available-txt">Characters available: </span><b
                                    id="characters"><?= (int) $statusMaxChars ?></b></span>
                            <span id="submit-loading"></span>
                        </th>
                    </tr>
                    </thead>
                </table>

            </div>
            <div class="info">
                <textarea id="text_content" maxlength="" name="text_content" oninput="doingForm()">
</textarea>

            </div>
            <ul class="suggestions alt"></ul>
            <div class="center">
                <div class="submit">
                    <input class="button" id="submit" name="submit" type="submit" value="Update">
                </div>
            </div>

        </form>
    </div>
<script type="text/javascript">
/* <![CDATA[ */
var STATUS_MAX_LEN = <?= (int) $statusMaxChars ?>;

function set_elem_text(el, s) {
	if (el == null) {
		return;
	}
	if (typeof el.textContent !== "undefined") {
		el.textContent = s;
	} else {
		el.innerText = s;
	}
}

function doingForm() {
	var ta = document.getElementById("text_content");
	var el = document.getElementById("characters");
	if (ta == null || el == null) {
		return;
	}
	var n = ta.value.length;
	var left = STATUS_MAX_LEN - n;
	if (left < 0) {
		left = 0;
	}
	set_elem_text(el, String(left));
}

function encode_uri_component(s) {
	if (typeof encodeURIComponent != "undefined") {
		return encodeURIComponent(s);
	}
	return escape(s).replace(/\+/g, "%2B");
}

function create_http_request() {
	var x = null;
	if (typeof XMLHttpRequest != "undefined") {
		x = new XMLHttpRequest();
	} else if (typeof ActiveXObject != "undefined") {
		try { x = new ActiveXObject("Msxml2.XMLHTTP"); } catch (e1) {}
		if (x == null) {
			try { x = new ActiveXObject("Microsoft.XMLHTTP"); } catch (e2) {}
		}
	}
	return x;
}

function post_status_update() {
	var ta = document.getElementById("text_content");
	if (ta == null) {
		return false;
	}
	var xhr = create_http_request();
	if (xhr == null) {
		alert("Your browser cannot send updates.");
		return false;
	}
	var v = ta.value;
	var trimmed = v.replace(/^\s+/, "").replace(/\s+$/, "");
	if (trimmed === "") {
		alert("Please enter a status.");
		return false;
	}
	xhr.open("POST", "status_post.php", true);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xhr.onreadystatechange = function () {
		if (xhr.readyState != 4) {
			return;
		}
		var msg = xhr.responseText;
		if (xhr.status == 200) {
			alert(msg);
			window.location.reload();
		} else {
			alert(msg != "" ? msg : "Could not save your status.");
		}
	};
	xhr.send("status=" + encode_uri_component(v));
	return false;
}

(function () {
	var ta = document.getElementById("text_content");
	if (ta == null) {
		return;
	}
	function bind() {
		doingForm();
	}
	function delayed_bind() {
		setTimeout(bind, 0);
	}
	if (ta.addEventListener) {
		ta.addEventListener("input", bind, false);
		ta.addEventListener("keyup", bind, false);
		ta.addEventListener("keydown", bind, false);
		ta.addEventListener("paste", delayed_bind, false);
		ta.addEventListener("cut", delayed_bind, false);
	} else if (ta.attachEvent) {
		ta.attachEvent("onkeyup", bind);
		ta.attachEvent("onkeydown", bind);
		ta.attachEvent("onpaste", delayed_bind);
		ta.attachEvent("oncut", delayed_bind);
		ta.attachEvent("onpropertychange", function () {
			if (window.event && window.event.propertyName === "value") {
				bind();
			}
		});
	}
	bind();
})();
/* ]]> */
</script>
<?php } ?>

  <ul class="tabMenu">
  	<li<?= $tab === 'friends' ? ' class="active"' : '' ?>>
  	  <a href="user.php?id=<?= esc_html($uid) ?>&amp;tab=friends">With Friends (24h)</a>
  	</li>
  	<li<?= $tab === 'prev' ? ' class="active"' : '' ?>>
  	  <a href="user.php?id=<?= esc_html($uid) ?>">Previous</a>
  	</li>
  </ul>

  <div class="tab">
		<table class="doing<?= $tab === 'friends' ? ' doing_with_friends' : '' ?>" id="timeline" cellspacing="0">
			<tbody>
<?php
$optColspan = $tab === 'friends' ? 3 : 2;
if ($tab === 'friends') {
    if ($friendFeedRows === []) {
        ?>
				<tr class="odd">
	<td class="user_actions"></td>
	<td class="thumb"></td>
	<td><span class="meta">Nothing to see there.</span></td>
</tr>
<?php
    } else {
        $fi = 0;
        foreach ($friendFeedRows as $s) {
            $sid = (int) $s['id'];
            $authorId = (int) $s['user_id'];
            $rowClass = ($fi % 2 === 0) ? 'odd' : 'even';
            $fi++;
            $timePhrase = format_status_time($s['created_at']);
            $uenc = rawurlencode($s['username']);
            $thumb = avatar_public_url($s['avatar_path'] ?? null);
            ?>
				<tr class="<?= $rowClass ?>" id="status_<?= $sid ?>">
	<td class="user_actions" id="user_action_<?= $authorId ?>">
</td>
		<td class="thumb">
		<a href="user.php?id=<?= esc_html($uenc) ?>"><img alt="" src="<?= esc_html($thumb) ?>" width="48" height="48" style="vertical-align:middle" /></a>
	</td>
	<td>
					<strong><a href="user.php?id=<?= esc_html($uenc) ?>" title="<?= esc_html($s['display_name']) ?>"><?= esc_html($s['username']) ?></a></strong>

					<?= format_status_body_html((string) $s['body']) ?>

		<span class="meta">
						<a href="status.php?id=<?= $sid ?>"><?= esc_html($timePhrase) ?></a>
						from web

			<span id="status_actions_<?= $sid ?>">
</span>

		</span>
	</td>
</tr>

<?php
        }
    }
} elseif ($statusRows === []) {
    ?>
				<tr class="odd">
	<td class="user_actions">
</td>
	<td>
		<span class="meta">Nothing to see there.</span>
	</td>
</tr>
<?php
} else {
    $i = 0;
    foreach ($statusRows as $s) {
        $sid = (int) $s['id'];
        $rowClass = ($i % 2 === 0) ? 'odd' : 'even';
        $i++;
        $timePhrase = format_status_time($s['created_at']);
        ?>
				<tr class="<?= $rowClass ?>" id="status_<?= $sid ?>">
	<td class="user_actions">
</td>
	<td>
					<?= format_status_body_html((string) $s['body']) ?>

		<span class="meta">
						<a href="status.php?id=<?= $sid ?>"><?= esc_html($timePhrase) ?></a>
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
				<tr class="statuses_options_row"><td colspan="<?= (int) $optColspan ?>" class="statuses_options"><div class="statuses_options_inner">
<?php if ($tab !== 'friends') { ?>
<?php if ($viewAll) { ?>
					<a href="user.php?id=<?= esc_html($uid) ?>">Recent updates</a> |
<?php } else { ?>
					<a href="user.php?id=<?= esc_html($uid) ?>&amp;view_all=1">View all…</a> |
<?php } ?>
<?php } ?>
					<a href="rss.php?id=<?= esc_html($uid) ?>">RSS Feed</a>
				</div></td></tr>
			</tbody></table>
	</div>


		</div></div><hr>

	<div id="side">
<div class="msg">
	About <strong><?= esc_html($profile['username']) ?></strong>  
</div>
<?php render_sidebar_friend_requests_notice($me); ?>

<ul class="about">
	<li>Name: <?= esc_html($profile['display_name']) ?></li>
<?php if ($profile['time_zone']) { ?>
				<li>Time zone: <?= esc_html($profile['time_zone']) ?></li>
<?php } ?>
<?php
$bioShow = trim((string) ($profile['bio'] ?? ''));
if ($bioShow !== '') {
    ?>
				<li>Bio: <?= nl2br(esc_html($bioShow), true) ?></li>
<?php
}
$locShow = trim((string) ($profile['location'] ?? ''));
if ($locShow !== '') {
    ?>
				<li>Location: <?= esc_html($locShow) ?></li>
<?php
}
$webShow = trim((string) ($profile['web'] ?? ''));
if ($webShow !== '') {
    $webHref = $webShow;
    if (!preg_match('#^https?://#i', $webHref)) {
        $webHref = 'http://' . $webHref;
    }
    ?>
				<li>Web: <a href="<?= esc_html($webHref) ?>" rel="nofollow me"><?= esc_html($webShow) ?></a></li>
<?php
}
?>
<?php if ($isOwn) { ?>
				<li>Email: <?= esc_html($profile['email']) ?></li>
<?php } ?>
	</ul>

<?php
$anyProfileStat = $friendsCount > 0 || $followersCount > 0 || $updatesCount > 0;
if ($anyProfileStat) {
    ?>
<ul class="about profile_stats">
<?php if ($friendsCount > 0) { ?>
	<li><?= (int) $friendsCount ?> Friends</li>
<?php } ?>
<?php if ($followersCount > 0) { ?>
	<li><?= (int) $followersCount ?> Followers</li>
<?php } ?>
<?php if ($updatesCount > 0) { ?>
	<li><?= (int) $updatesCount ?> Updates</li>
<?php } ?>
</ul>
<?php } ?>

<?php if ($profileFriendsList !== []) { ?>
<div id="friends" class="profile_friends">
<?php foreach ($profileFriendsList as $f) {
    $fu = rawurlencode($f['username']);
    $fa = avatar_public_url($f['avatar_path'] ?? null);
    ?>
	<a href="user.php?id=<?= esc_html($fu) ?>" title="<?= esc_html($f['username']) ?>"><img src="<?= esc_html($fa) ?>" alt="" width="32" height="32" /></a>
<?php } ?>
</div>
<?php } ?>

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
