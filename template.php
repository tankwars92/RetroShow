<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


function showHeader($title = "RetroShow") {
    global $show_menu, $db;
    if (!isset($show_menu)) $show_menu = true;
    $current = strtolower(basename($_SERVER['SCRIPT_NAME']));
    function nav_link($href, $text) {
        global $current;
        $is_active = ($current === strtolower($href));
		
        return $is_active
            ? '<span style="font-weight:bold; color:#0033cc;">'.$text.'</span>'
            : '<a href="'.$href.'">'.$text.'</a>';
    }
	function nav_link_ex($href, $text, $is_active) {
    return $is_active
        ? '<a href="'.$href.'"><b style="color:#0033cc;text-decoration:underline">'.$text.'</b></a>'
        : '<a href="'.$href.'">'.$text.'</a>';
}
	
?>
<html><head><style class="vjs-styles-defaults">
	.vjs-fluid:not(.vjs-audio-only-mode) {
		padding-top: 56.25%
	}
</style>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php if ($title === "Главная"): ?>
<title><?= (user_header_logo_src($db, isset($_SESSION['user']) ? $_SESSION['user'] : '') === 'img/logo_sm_YT.gif') ? 'YouTube' : 'RetroShow'; ?> - Загружайте и делитесь видео по всему миру!</title>
<?php else: ?>
<title><?= htmlspecialchars($title) ?> - <?= (user_header_logo_src($db, isset($_SESSION['user']) ? $_SESSION['user'] : '') === 'img/logo_sm_YT.gif') ? 'YouTube' : 'RetroShow'; ?></title>
<?php endif; ?>
		
		<script language="javascript" type="text/javascript">
		onLoadFunctionList = new Array();
		function performOnLoadFunctions()
		{
			for (var i in onLoadFunctionList)
			{
				onLoadFunctionList[i]();
			}
		}
		</script>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<meta name="description" content="Share your videos with friends and family">
<link rel="stylesheet" href="img/styles.css" type="text/css">
<link rel="stylesheet" href="img/base.css" type="text/css">
<link rel="stylesheet" href="img/watch.css" type="text/css">
<script type="text/javascript" src="img/ui_ets.js"></script>
<link href="img/styles.css" rel="stylesheet" type="text/css">
<link rel="alternate" type="application/rss+xml" title="Recently Added Videos" href="rss.php">
<style type="text/css">
.formTitle { font-size: 16px; font-weight: bold; margin-bottom: 15px; color: #333; }
.error { background-color: #FFE6E6; border: 1px solid #FF9999; padding: 10px; margin: 10px 0px; color: #CC0000; font-size: 12px; }
.success { background-color: #E6FFE6; border: 1px solid #99FF99; padding: 10px; margin: 10px 0px; color: #006600; font-size: 12px; }
.formTable { margin: 0px auto; }
.label { font-weight: bold; color: #333; font-size: 12px; }
.pageTitle { font-size: 18px; font-weight: bold; margin-bottom: 15px; color: #333; }
.pageIntro { font-size: 14px; margin-bottom: 15px; color: #333; line-height: 1.4; }
.pageText { font-size: 12px; margin-bottom: 15px; color: #333; line-height: 1.4; }
.codeArea { background-color: #F5F5F5; border: 1px solid #CCCCCC; padding: 10px; margin: 10px 0px; font-family: monospace; font-size: 11px; color: #333; }
.pagingDiv { text-align: center; margin: 15px 0px; font-size: 12px; }
.pagerCurrent { background-color: #CCCCCC; border: 1px solid #999999; padding: 3px 8px; margin: 0px 2px; font-weight: bold; }
.pagerNotCurrent { background-color: #FFFFFF; border: 1px solid #CCCCCC; padding: 3px 8px; margin: 0px 2px; cursor: pointer; text-decoration: none; color: #000000; }
#footerDiv {
	clear: both;
	margin: 12px auto 24px auto;
	padding-bottom: 12px;
	font-size: 11px;
}
#footerCopyright { padding-top: 12px; text-align: center; }
#footerSearch { padding-top: 8px; text-align: center; }
#footerLinks { height: 66px; line-height: 15px; }
#footerContent {
	background: #EEE;
	border-top: 1px solid #CCC;
	border-bottom: 1px solid #CCC;
	padding: 8px 0px;
}
.footColumn { }
.footColumnBar {
	height: 60px;
	width: 170px;
	margin-right: 20px;
}
.footLabel {
	font-weight: bold;
	font-size: 11px;
	color: #333;
}
.footValues {
	margin-left: 0px;
	padding-bottom: 6px;
	font-size: 11px;
}
.footValues .column { float: left; padding-right: 26px; }

.hpStatsHeading {
	font-weight: bold;
	font-size: 13px;
	margin-bottom: 2px;
}

.smallLabel {
	font-weight: bold;
	font-size: 11px;
	color: #333;
}
</style>
<!--[if lt IE 6]>
<style type="text/css">
#ratingMessage { display:none; }
</style>
<![endif]-->
<!--[if lte IE 6]>
<style type="text/css">
html, body {
	margin: 10px !important;
	padding: 0 !important;
}
.showingTable {
	padding: 8px 6px !important;
	margin: 0 !important;
}
.showingTable td {
	padding-top: 4px !important;
	padding-bottom: 4px !important;
    padding-left: 6px !important;
	line-height: 16px !important;
	vertical-align: middle !important;
}
</style>
<![endif]-->
</head>


<body onload="performOnLoadFunctions();">
 
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center">
	<tbody><tr>
		<td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
		

<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<?php
		$__logo_src = 'img/logo_sm.gif';
		if (!empty($_SESSION['user']) && is_string($_SESSION['user']) && isset($db) && $db instanceof PDO) {
			$__logo_src = user_header_logo_src($db, $_SESSION['user']);
		}
		$__logo_alt = ($__logo_src === 'img/logo_sm_YT.gif') ? 'YouTube' : 'RetroShow';
		?>
		<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="<?= htmlspecialchars($__logo_src, ENT_QUOTES, 'UTF-8') ?>" width="120" height="48" alt="<?= htmlspecialchars($__logo_alt, ENT_QUOTES, 'UTF-8') ?>" border="0" style="vertical-align: middle; "></a></td>
		<td valign="top">
		
		<table width="670" cellpadding="0" cellspacing="0" border="0">
			<tbody><tr valign="top">
				<td style="padding: 0px 5px 0px 5px; font-style: italic;">Загружайте и делитесь видео по всему миру!</td>
				<td align="right">
				
				<table cellpadding="0" cellspacing="0" border="0">
					<tbody><tr>
		
						<?php if (!isset($_SESSION['user'])): ?>
							<td><a href="register.php"><strong>Регистрация</strong></a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td><a href="login.php">Вход</a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
						<?php else: ?>
							<?php
							global $db;
							$mail_unread = (isset($db) && $db instanceof PDO) ? count_unread_mail($db, $_SESSION['user']) : 0;
							$mail_icon = $mail_unread > 0 ? 'img/mail_unread.gif' : 'img/mail.gif';
							?>
							<td>Привет, <a href="channel.php?user=<?=urlencode($_SESSION['user'])?>"><?=htmlspecialchars($_SESSION['user'])?></a>!&nbsp;&nbsp;&nbsp;<a href="my_messages.php"><img src="<?= htmlspecialchars($mail_icon, ENT_QUOTES, 'UTF-8') ?>" id="mailico" border="0" alt=""></a>&nbsp;(<a href="my_messages.php"><?= (int) $mail_unread ?></a>)</td>					
							<td class="myAccountContainer" style="padding: 0px 0px 0px 5px;">|&nbsp;
							<?php $admins = @unserialize(RETROSHOW_ADMINS); if (in_array($_SESSION['user'], $admins, true)) {?>
								<td><a href="admin.php" style="font-weight: bold;color: #24692A">Админ-панель</a></td>
								<td style="padding: 0px 5px 0px 5px;">|</td>
							<?php } ?>
							<td><a href="logout.php">Выйти</a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
							
						<?php endif; ?>
	
		
										
					</tr>
				</tbody></table>
				
				</td>
			</tr>
		</tbody></table>
		</td>
	</tr>
	<tr valign="bottom">
		<td>
		
		<table cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<?php
				$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
				$tabs = [
					['scripts' => ['index.php'], 'label' => 'Главная', 'href' => 'index.php'],
					['scripts' => ['channel.php', 'favourites.php', 'friends.php', 'results.php', 'video.php'], 'label' => 'Смотреть&nbsp;видео', 'href' => 'channel.php'],
					['scripts' => ['upload.php'], 'label' => 'Загрузить&nbsp;видео', 'href' => 'upload.php'],
					['scripts' => ['my_friends_invite.php'], 'label' => 'Пригласить&nbsp;друзей', 'href' => 'my_friends_invite.php'],
				];
				$found = false;
				foreach ($tabs as $t) {
					if (in_array($current_script, $t['scripts'], true)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$current_script = 'index.php';
				}
				foreach ($tabs as $idx => $t):
					$is_active = in_array($current_script, $t['scripts'], true);
					$ml = ($idx === 0) ? 5 : 0;
					$tab_style = $is_active
						? 'background-color: #DDDDDD; margin: 5px 2px 0px ' . $ml . 'px; border-bottom: 1px solid #DDDDDD;'
						: 'background-color: #BECEEE; margin: 5px 2px 1px ' . $ml . 'px; border-bottom: none;';
				?>
				<td>
					<table style="<?= $tab_style ?>" cellpadding="0" cellspacing="0" border="0">
						<tbody><tr>
							<td><img src="/img/box_login_tl.gif" width="5" height="5"></td>
							<td><img src="/img/pixel.gif" width="1" height="5"></td>
							<td><img src="/img/box_login_tr.gif" width="5" height="5"></td>
						</tr>
						<tr>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
							<td style="padding: 0px 20px 5px 20px; font-size: 13px; font-weight: bold;">
								<a href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>"><?= $t['label'] ?></a>
							</td>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
						</tr>
					</tbody></table>
				</td>
				<?php endforeach; ?>
			</tr></tbody>
		</table>
		</td>
	</tr>
	
</tbody></table>

<table align="center" width="800" bgcolor="#DDDDDD" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 10px;">
	<tbody><tr>
		<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
		<td><img src="img/pixel.gif" width="1" height="5"></td>
		<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
	</tr>
	<tr>
		<td><img src="img/pixel.gif" width="5" height="1"></td>
		<td width="790" align="center" style="padding: 2px;">

		<table cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<td style="font-size: 10px;">&nbsp;</td>
				
				<?php
$menu_user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
$cur_user = isset($_GET['user']) ? $_GET['user'] : '';
$cur_tab = $_GET['tab'] ?? '';
$cur_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
$is_my_videos = $cur_script === 'channel.php' && $cur_tab === 'videos' && $menu_user && $cur_user === $menu_user;
$is_my_channel = $cur_script === 'channel.php' && ($cur_tab === '' || !isset($_GET['tab'])) && $menu_user && $cur_user === $menu_user;
$is_fav = $cur_script === 'favourites.php' && $menu_user && $cur_user === $menu_user;
$is_friends = $cur_script === 'friends.php' && $menu_user && $cur_user === $menu_user;
$is_account = $cur_script === 'account.php';

$link_my_videos = isset($_SESSION['user']) ? 'channel.php?user=' . urlencode($_SESSION['user']) . '&tab=videos' : 'login.php';
$link_my_channel = isset($_SESSION['user']) ? 'channel.php?user=' . urlencode($_SESSION['user']) : 'login.php';
$link_fav = isset($_SESSION['user']) ? 'favourites.php?user=' . urlencode($_SESSION['user']) : 'login.php';
$link_friends = isset($_SESSION['user']) ? 'friends.php?user=' . urlencode($_SESSION['user']) : 'login.php';
$link_account = isset($_SESSION['user']) ? 'account.php' : 'login.php';
?>
<td style="  "><?=nav_link_ex($link_my_videos, 'Мои видео', $is_my_videos)?></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><?=nav_link_ex($link_my_channel, 'Мой канал', $is_my_channel)?></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><?=nav_link_ex($link_fav, 'Избранное', $is_fav)?></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><?=nav_link_ex($link_friends, 'Мои друзья', $is_friends)?></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><?=nav_link_ex($link_account, 'Настройки', $is_account)?></td>
<td style="font-size: 10px;">&nbsp;</td>
</tr></table>
			
		</td>
		<td><img src="img/pixel.gif" width="5" height="1"></td>
	</tr>
	<tr>
		<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_bl.gif" width="5" height="5"></td>
		<td style="border-bottom: 1px solid #BBBBBB"><img src="img/pixel.gif" width="1" height="5"></td>
		<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_br.gif" width="5" height="5"></td>
	</tr>
</tbody></table>

<form name="searchForm" id="searchForm" method="GET" action="results.php" style="margin: 0; padding: 0;">
<table align="center" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 10px;">
	<tbody><tr>
		<td style="padding-right: 5px;"><input tabindex="1" type="text" value="<?=htmlspecialchars($_GET['search_query'] ?? '')?>" name="search_query" maxlength="128" style="color:#ff3333; font-size: 12px; width: 300px;"></td>
		<td><input type="submit" value="Искать видео"></td>
	</tr></tbody></table>
</form>

<script language="javascript">
	onLoadFunctionList.push(function () { document.searchForm.search_query.focus(); });
</script>

<?php
$news_file = __DIR__ . '/news.txt';
if (file_exists($news_file)) {
    $news_text = trim(file_get_contents($news_file));
    if (!empty($news_text)) {
        echo '<div class="confirmBox">' . nl2br($news_text) . '</div>';
    }
}
?>

<div style="padding: 0px 5px 0px 5px;">


<?php }
function showFooter() {
?>
</div>
        </td></tr></table>

        <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tbody>
        <tr>
            <td align="center" valign="middle" style="font-family: Arial, sans-serif; font-size: 12px; line-height: 16px; text-align:center;">
                
                <table cellpadding="0" cellspacing="0" border="0" width="400" align="center">
                <tr>
                    <td align="center">
                    	<a href="about.php?p=whats_new">Что нового?</a> |
                        <a href="about.php">О сайте</a> | 
                        <a href="http://github.com/tankwars92/RetroShow">Исходный код</a> | 
                        <a href="http://downgrade-net.ru/">Downgrade Net</a>
                    </td>
                </tr>
                <tr>
                    <td style="height:2px; font-size:1px; line-height:1px;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center">
						<br>
                        Copyright © 2026 RetroShow | 
                        <a href="rss.php"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align:text-top;"></a>
                    </td>
                </tr>
                <tr>
                    <td style="height:2px; font-size:1px; line-height:1px;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center">
						<br>
                        <script src="//downgrade-net.ru/services/ring/ring.php"></script> 
                        <img src="//downgrade-net.ru/services/counter/index.php?id=21" alt="Downgrade Counter" border="0">
                    </td>
                </tr>
                </table>

            </td>
        </tr>
        </tbody>
        </table>
</body>
</html>
<?php } ?> 