<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$me = current_user();

$rawId = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($rawId === '' || !ctype_digit($rawId)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head><body><p>Status not found. <a href="index.php">Home</a></p></body></html>';
    exit;
}

$row = status_by_id((int) $rawId);
if ($row === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head><body><p>Status not found. <a href="index.php">Home</a></p></body></html>';
    exit;
}

$avatarSrc = avatar_public_url($row['avatar_path'] ?? null);
$uid = rawurlencode($row['username']);
$body = $row['body'];
if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    $snippet = mb_strlen($body) > 50 ? mb_substr($body, 0, 50) . '…' : $body;
} else {
    $snippet = strlen($body) > 50 ? substr($body, 0, 50) . '...' : $body;
}
$pageTitle = 'Twitbirth / ' . $row['display_name'] . ': ' . $snippet;
$permalinkTime = format_status_permalink_time($row['created_at']);
$sid = (int) $row['id'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<meta http-equiv="Content-Language" content="en-us">
	<title><?= esc_html($pageTitle) ?></title>
	
	<link href="stylesheets/screen2.css" media="screen, projection" rel="Stylesheet" type="text/css">

	
	<link rel="shortcut icon" href="images/favicon.ico" type="image/x-icon">
	
	
</head>
<body class="status" id="show">
 
		<style type="text/css">
		a {color: #0000ff;}
		body {
			color: #000000;
			background-color: #9ae4e8;
						background: #9ae4e8 url(images/bg.gif) fixed repeat top left;
					}
		#side {
			background-color: #e0ff92;
			border: 1px solid #87bc44;
		}
		#side .notify {border: 1px solid #87bc44;}
		#side .actions {border: 1px solid #87bc44;}
		h2.thumb, h2.thumb a {color: #000000;}
		#permalink h2.thumb { font-weight: normal; margin: 0; }
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
		
		
				
		<div id="content"><div class="wrapper">	
			

<div id="permalink">
	
	
    	<div class="desc">
    		<p>
    		
    		  <?= format_status_body_html((string) $row['body']) ?>
    		
    		</p>
    		<p class="meta">
    			<span class="meta">
    				
    				    					 <?= esc_html($permalinkTime) ?>
    				    				from web
    				<span id="status_actions_<?= $sid ?>">
</span>

    			</span>
    		</p>
    	</div>
	
	<h2 class="thumb">
		<a href="user.php?id=<?= esc_html($uid) ?>"><img alt="Avatar" border="0" src="<?= esc_html($avatarSrc) ?>" width="48" height="48" style="vertical-align:middle" /></a>
		<a href="user.php?id=<?= esc_html($uid) ?>"><?= esc_html($row['display_name']) ?></a>
	</h2>

	<div id="ad"></div>

</div>
		</div></div><hr>


</body></html>