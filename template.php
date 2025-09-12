<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function showHeader($title = "RetroShow") {
    global $show_menu;
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
<title><?= htmlspecialchars($title) ?> - RetroShow</title>
		
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
<link rel="stylesheet" href="img_/styles_ets11562102812.css" type="text/css">
<link rel="stylesheet" href="img_/base_ets1156367996.css" type="text/css">
<link rel="stylesheet" href="img_/watch_ets1156799200.css" type="text/css">
<script type="text/javascript" src="img/ui_ets.js"></script>
<link href="img/styles.css" rel="stylesheet" type="text/css">
<link rel="alternate" type="application/rss+xml" title="YouTube " "="" recently="" added="" videos="" [rss]"="" href="rss/global/recently_added.rss">
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
/* Footer Elements */
#footerDiv {
	clear: both;
	/* width: 875px; */
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
	/* border-right: 1px solid #CCC; */
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
</head>


<body onload="performOnLoadFunctions();">
 
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center">
	<tbody><tr>
		<td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
		

<table width="100%" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="img/logo_sm.gif" width="120" height="48" alt="RetroShow" border="0" style="vertical-align: middle; "></a></td>
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
							<td>Привет, <strong><?=htmlspecialchars($_SESSION['user'])?></strong></td>
							<td class="myAccountContainer" style="padding: 0px 0px 0px 5px;">|<span style="white-space: nowrap;">
<a href="account.php" onmouseover="showDropdownShow();">Мой аккаунт</a><a href="#" onclick="arrowClicked();return false;" onmouseover="document.arrowImg.src='/img/icon_menarrwdrpdwn_mouseover3_14x14.gif'" onmouseout="document.arrowImg.src='/img/icon_menarrwdrpdwn_regular_14x14.gif'"><img name="arrowImg" src="img_/icon_menarrwdrpdwn_regular_14x14.gif" align="texttop" border="0" style="margin-left: 2px;"></a>

<div id="myAccountDropdown" class="myAccountMenu" onmouseover="showDropdown();" onmouseout="hideDropwdown();" style="display: none; position: absolute;">
	<div id="menuContainer" class="menuBox">
		<div class="menuBoxItem" id="MyAccountMyVideo" onmouseover="showDropdown();changeBGcolor(this,1);" onmouseout="changeBGcolor(this,0);">
			<a href="<?php echo isset($_SESSION['user']) ? 'channel.php?user=' . urlencode($_SESSION['user']) . '&tab=videos' : 'login.php'; ?>" class="dropdownLinks"><span class="smallText">Мои видео</span></a>
		</div>
		<div class="menuBoxItem <?php echo ($currentPage == 'favourites.php') ? 'active' : ''; ?>" id="MyAccountMyFavorites" onmouseover="showDropdown();changeBGcolor(this,1);" onmouseout="changeBGcolor(this,0);">
				<a href="<?php echo (isset($_SESSION['user'])) ? 'favourites.php?user=' . urlencode($_SESSION['user']) : 'login.php'; ?>" class="dropdownLinks"><span class="smallText">Избранное</span></a>
			</div>
			<div class="menuBoxItem <?php echo ($currentPage == 'friends.php') ? 'active' : ''; ?>" id="MyAccountSubscription" onmouseover="showDropdown();changeBGcolor(this,1);" onmouseout="changeBGcolor(this,0);">
				<a href="<?php echo (isset($_SESSION['user'])) ? 'friends.php?user=' . urlencode($_SESSION['user']) : 'login.php'; ?>" class="dropdownLinks"><span class="smallText">Мои друзья</span></a>
			</div>
	</div>
</div>
<script>
toggleVisibility('myAccountDropdown',0);
</script></span></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td><a href="help.php">Помощь</a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td style="padding-right: 5px;"><a href="logout.php">Выйти</a></td>
							
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
		
		<div id="gNavDiv">
		<div id="gNavDiv">
			<?php
			$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));

			$tabs = [
				['index.php', 'Главная', 'index.php'],
				['channel.php,favourites.php,friends.php', 'Смотреть&nbsp;видео', 'channel.php'],
				['upload.php', 'Загрузить&nbsp;видео', 'upload.php'],
				['my_friends_invite.php', 'Пригласить&nbsp;друзей', 'my_friends_invite.php']
			];

			$found = false;
			foreach ($tabs as $tab) {
				if (in_array($current_script, explode(',', $tab[0]))) {
					$found = true;
					break;
				}
			}

			if (!$found) {
				$current_script = 'index.php';
			}

			foreach ($tabs as $tab) {
				$is_active = in_array($current_script, explode(',', $tab[0]));
				$class = $is_active ? 'ltab' : 'tab';
				$rc_class = $is_active ? 'rcs' : 'rc';
				$selected = $is_active ? ' selected' : '';
				echo "<div class=\"$class\"><b class=\"$rc_class\"><b class=\"{$rc_class}1\"><b></b></b><b class=\"{$rc_class}2\"><b></b></b><b class=\"{$rc_class}3\"></b><b class=\"{$rc_class}4\"></b><b class=\"{$rc_class}5\"></b></b><div class=\"tabContent$selected\"><a href=\"{$tab[2]}\">{$tab[1]}</a></div></div>";
			}
			?>
</div>

		</div>
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
// Отображение новости
$news_file = __DIR__ . '/news.txt';
if (file_exists($news_file)) {
    $news_text = trim(file_get_contents($news_file));
    if (!empty($news_text)) {
        echo '<div class="confirmBox">' . htmlspecialchars($news_text) . '</div>';
    }
}
?>

<div style="padding: 0px 5px 0px 5px;">


<?php }
function showFooter() {
?>
</div>
		</td></tr></table>
<center>
<div style="width:790px; margin:0;">
  <div id="footerDiv">
    <div id="footerContent">
      <div id="footerLinks">
        <table border="0" cellpadding="0" cellspacing="0" width="90%" align="center"><tbody><tr valign="top">
          <td>
            <div>
              <div class="footLabel">Ваш&nbsp;&nbsp;Аккаунт</div>
              <div class="footValues">
                <div class="column">
                  <a href="<?php if (isset($_SESSION['user'])) { echo 'channel.php?user=' . urlencode($_SESSION['user']) . '&tab=videos'; } else { echo 'login.php'; } ?>">Мои видео</a><br>
                  <a href="favourites.php">Избранное</a><br>
                  <a href="account.php">Настройки</a>
                </div>
              </div>
            </div>
          </td>
		  <td style="padding-left: 90px;"></td>
          <td bgcolor="#CCCCCC" width="1"></td>
          <td style="padding-left:60px; padding-right:60px;">
            <div>
              <div class="footLabel">RetroShow</div>
              <div class="footValues">
                <div class="column">
                  <a href="index.php">Главная</a><br>
                  <a href="upload.php">Загрузить видео</a><br>
                  <a href="channel.php">Все видео</a>
                </div>
              </div>
            </div>
          </td>
          <td bgcolor="#CCCCCC" width="1"></td>
          <td style="padding-left:70px; padding-right:61px;">
            <div>
              <div class="footLabel">Помощь</div>
              <div class="footValues">
                <div class="column">
                  <a href="#">Помощь</a><br>
                  <a href="#">Правила</a><br>
                  <a href="about.php">О проекте</a><br>
                  <a href="#">Контакты</a><br>
                </div>
              </div>
            </div>
          </td>
        </tr></tbody></table>
      </div> 
    </div>
    <div id="footerCopyright">
	  <br>
      Copyright © 2025 RetroShow, Inc.
    </div>
  </div>
</div>

</center>

<div style="all: unset;"><div style="all: unset;"></div></div></body></html>
<?php } ?> 