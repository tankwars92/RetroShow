<?php
include("init.php");
include_once 'template.php';

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'recent';

function time_ago($time) {
    $diff = time() - $time;
    if ($diff < 60) return $diff.' секунд назад';
    $mins = floor($diff/60);
    if ($mins < 60) return $mins.' минут назад';
    $hours = floor($mins/60);
    if ($hours < 24) return $hours.' часов назад';
    $days = floor($hours/24);
    if ($days < 7) return $days.' дней назад';
    $weeks = floor($days/7);
    if ($weeks < 5) return $weeks.' недель назад';
    $months = floor($days/30);
    if ($months < 12) return $months.' месяцев назад';
    $years = floor($days/365);
    return $years.' лет назад';
}

require_once 'duration_helper.php';

function get_video_duration($file, $id) {
    return get_video_duration_fast($file, $id);
}

function channel_get_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}
function channel_render_avg_stars_html($avg, $count) {
    $remaining = floatval($avg);
    $parts = [];
    for ($i=0; $i<5; $i++) {
        if ($remaining >= 0.75) $parts[] = 'full';
        elseif ($remaining >= 0.25) $parts[] = 'half';
        else $parts[] = 'empty';
        $remaining = max(0.0, $remaining - 1.0);
    }
    ob_start();
    ?>
    <div style="margin:2px 0 2px 0;">
      <nobr>
        <img src="img_/star_smn<?=($parts[0]==='full'?'':($parts[0]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img_/star_smn<?=($parts[1]==='full'?'':($parts[1]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img_/star_smn<?=($parts[2]==='full'?'':($parts[2]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img_/star_smn<?=($parts[3]==='full'?'':($parts[3]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img_/star_smn<?=($parts[4]==='full'?'':($parts[4]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
      </nobr>
      <div class="rating"><?=intval($count)?> оценок</div>
    </div>
    <?php
    return ob_get_clean();
}

function get_user_profile_icon_setting($username) {
    global $db;
    try {
        $stmt = $db->prepare('SELECT profile_icon FROM users WHERE login = ?');
        $stmt->execute([$username]);
        $val = $stmt->fetchColumn();
        return ($val === '1') ? '1' : '0';
    } catch (Exception $e) {
        return '0';
    }
}

$user = isset($_GET['user']) ? $_GET['user'] : null;

function get_profile_icon($username, $profile_icon_setting = '0') {
    static $icon_cache = [];
    
    $cache_key = $username . '_' . $profile_icon_setting;
    if (isset($icon_cache[$cache_key])) {
        return $icon_cache[$cache_key];
    }
    
    if ($profile_icon_setting === '1') {
        $icon_cache[$cache_key] = 'img/no_videos_140.jpg';
        return 'img/no_videos_140.jpg';
    }
    
    global $db;
    $stmt = $db->prepare("SELECT preview FROM videos WHERE user = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$username]);
    $last_video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last_video && $last_video['preview']) {
        $icon_cache[$cache_key] = $last_video['preview'];
        return $last_video['preview'];
    }
    
    $icon_cache[$cache_key] = 'img/no_videos_140.jpg';
    return 'img/no_videos_140.jpg';
}

$user_data = null;
if ($user) {
    try {
        $stmt_user = $db->prepare('SELECT about_me, gender, birthday_yr, birthday_mon, birthday_day, country, name, last_n, website, city, hometown, profile_comm, profile_icon FROM users WHERE login = ?');
        $stmt_user->execute([$user]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $user_data = null;
    }
}

if ($user && !$user_data) {
    showHeader('Канал не найден');
    echo '<div class="errorBox">Канал не найден!</div>';
    showFooter();
    exit;
}

$about_me = '';
if ($user_data && isset($user_data['about_me'])) {
  $about_me = trim($user_data['about_me']);
}

$fav_file = __DIR__ . '/favourites/' . urlencode($user) . '.txt';
$fav_count = (file_exists($fav_file)) ? count(file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;

$profile_comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';
$comments_count = (file_exists($profile_comments_file)) ? count(file($profile_comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;

$subscribers_count = 0;
$friends_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
if (file_exists($friends_file)) {
    $friends = file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($friends as $friend) {
        $friend_friends_file = __DIR__ . '/friends/' . urlencode($friend) . '.txt';
        if (!file_exists($friend_friends_file) || !in_array($user, file($friend_friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
            $subscribers_count++;
        }
    }
}

$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$user]);
$total = $stmt_total->fetchColumn();

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;
if ($user && (!isset($_GET['tab']) || $_GET['tab'] === '')) {
    $stmt = $db->prepare('SELECT last_login, signup_time FROM users WHERE login = ?');
    $stmt->execute([$user]);
    $user_row = $stmt->fetch(PDO::FETCH_ASSOC);
    $last_login_time = isset($user_row['last_login']) ? intval($user_row['last_login']) : time();
    $signup_time = isset($user_row['signup_time']) ? intval($user_row['signup_time']) : time();

    $db->exec("CREATE TABLE IF NOT EXISTS user_stats (user TEXT PRIMARY KEY, profile_viewed INTEGER DEFAULT 0, videos_watched INTEGER DEFAULT 0)");
	
    if (isset($_SESSION['user']) && $_SESSION['user'] !== $user) {
        $db->exec("INSERT OR IGNORE INTO user_stats (user) VALUES (".$db->quote($user).")");
        $db->exec("UPDATE user_stats SET profile_viewed = profile_viewed + 1 WHERE user = " . $db->quote($user));
    }
	
    $stat = $db->query("SELECT profile_viewed FROM user_stats WHERE user = " . $db->quote($user));
    $profile_viewed = ($stat && ($row = $stat->fetch())) ? intval($row['profile_viewed']) : 0;
	
    $stmt_vw = $db->prepare("SELECT COUNT(DISTINCT video_id) FROM video_views WHERE user = ?");
    $stmt_vw->execute([$user]);
    $videos_watched = $stmt_vw->fetchColumn();

    $profile = [
        'username' => htmlspecialchars($user),
        'url' => 'http://retroshow.hoho.ws/channel.php?user='.urlencode($user),
        'videos_watched' => $videos_watched,
        'profile_viewed' => $profile_viewed,
    ];
    $profile['last_login_time'] = $last_login_time;
    $profile['signup_time'] = $signup_time;
    function ago_ru($ts) {
        $diff = time() - $ts;
        if ($diff < 60) return $diff.' секунд назад';
        if ($diff < 3600) return floor($diff/60).' минут назад';
        if ($diff < 86400) return floor($diff/3600).' часов назад';
        if ($diff < 2592000) return floor($diff/86400).' дней назад';
        if ($diff < 31536000) return floor($diff/2592000).' месяцев назад';
        return floor($diff/31536000).' лет назад';
    }
    $profile['last_login'] = $profile['last_login_time'] ? ago_ru($profile['last_login_time']) : '–';
    $profile['member_since'] = $profile['signup_time'] ? ago_ru($profile['signup_time']) : '–';
    showHeader('Профиль ' . $profile['username']);
?>

<style type="text/css">
.profileBoxHead { background:#888; color:#fff; font-weight:bold; font-size:14px; padding:4px 8px; }
.profileBox { border:1px solid #bbb; margin-bottom:10px; }
.profileBoxContent { background:#fff; padding:8px; font-size:13px; }
.profileLabel { color:#888; font-size:11px; text-align:left; }
.profileValue { font-size:13px; }
.profileLink { color:#0033cc; text-decoration:underline; }
.profileBulletinTable { border-collapse:collapse; width:100%; }
.profileBulletinTable td, .profileBulletinTable th { border:1px solid #bbb; padding:4px; font-size:12px; }
.profileTitles { word-wrap: anywhere;
	font-family:  Arial, Helvetica, sans-serif;
	font-size: 12px;
	font-weight: bold;
	color: #999999;
	padding-top: 4px;
	padding-bottom: 4px;
	}
</style>
<?php

if (!isset($total)) {
    $stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
    $stmt_total->execute([$user]);
    $total = $stmt_total->fetchColumn();
}
echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
echo (!isset($_GET['tab']) || $_GET['tab'] == '') 
    ? '<b>Профиль</b>' : '<a href="channel.php?user='.urlencode($user).'">Профиль</a>';
echo ' | ';
echo (isset($_GET['tab']) && $_GET['tab'] === 'videos')
    ? '<b>Видео ('.$total.')</b>' : '<a href="channel.php?user='.urlencode($user).'&tab=videos">Видео ('.$total.')</a>';
echo ' | ';
echo '<a href="favourites.php?user='.urlencode($user).'">Избранное ('.$fav_count.')</a> | ';
$fr_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
$fr_count = (file_exists($fr_file)) ? count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
echo '<a href="friends.php?user='.urlencode($user).'">Друзья ('.$fr_count.')</a> | ';
echo '<a href="channel.php?user='.urlencode($user).'&tab=comments">Комментарии ('.$comments_count.')</a>';
echo '</div>';
?>


<style>
.profileBox {
  border:1px solid #ddd;
  margin-bottom:10px;
  zoom:1;
  overflow:hidden;
}

.profileBoxHead {
  display:block;
  width:100%;
  padding:3px;
  font-size:12px;
  background:#888;
  color:#000;
  margin:0;
  zoom:1;
  box-sizing:border-box;
}

.profileBoxContent{
  padding:6px;
  width:100%;
  box-sizing:border-box;
  overflow:auto;
  zoom:1;
}

.profileBoxContent table {
  width:100%;
  table-layout:fixed;
  border-collapse:collapse;
}

.profileBoxContent td,
.profileBoxContent a,
.profileBoxContent .profileLink {
  word-wrap:break-word;
  white-space:normal;
}
</style>
<!--[if lte IE 6]>
<style>
.profileBox, .profileBoxHead, .profileBoxContent { zoom:1; }
</style>
<![endif]-->

<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
  <td width="320">
    <div class="profileBox">
      <div class="profileBoxHead" style="background:#888; color:#000; padding:3px; font-size:12px; ">Привет. Я <?= $profile['username'] ?></div>
      <div class="profileBoxContent">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr valign="top">
            <td width="140">
              <img src="<?= get_profile_icon($user, $user_data['profile_icon'] ?? '0') ?>" width="140" height="108" style="border:1px solid #bbb; background:#eee;">
            </td>
            <td style="padding-left: 10px;">
              <?php if ($user_data): ?>
                <?php if ($user_data['birthday_yr'] && $user_data['birthday_yr'] !== '---'): ?>
                  <?php 
                    $birth_year = intval($user_data['birthday_yr']);
                    $current_year = date('Y');
                    $age = $current_year - $birth_year;
                  ?>
                  <span class="profileTitles">Возраст: </span><?= $age ?><br>
                <?php endif; ?>
                
                <?php if ($user_data['gender']): ?>
                  <span class="profileTitles">Пол: </span>
                  <?= ($user_data['gender'] === 'm') ? 'Мужской' : (($user_data['gender'] === 'f') ? 'Женский' : '') ?><br>
                <?php endif; ?>
                
                <?php if ($user_data['country']): ?>
                  <?= htmlspecialchars($user_data['country']) ?><br>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        </table>
        
        <br>
        <span class="profileTitles">Последний вход:</span> <?= $profile['last_login'] ?><br>
        <span class="profileTitles">Зарегистрирован:</span> <?= $profile['member_since'] ?><br>
        <span class="profileTitles">URL:</span> <a href="<?= $profile['url'] ?>" class="profileLink"><?= $profile['url'] ?></a>
      </div>
    </div>
  </td>
  <td style="padding-left:10px;" valign="top">
    <div class="profileBox">
      <div class="profileBoxHead" style="background:#888; color:#000; padding:3px; font-size:12px;">Подробнее обо мне</div>
      <div class="profileBoxContent">
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <?php if ($about_me): ?>
  <div style="margin-bottom:10px; background:#fff;">
    <div style="font-size:13px; color:#222; white-space:pre-line;"> <?=nl2br(htmlspecialchars($about_me))?> </div>
    <hr style="border:0; border-top:1px dashed #888; height:1px; margin:2px 0 2px 0; background:none;">
  </div>
<?php endif; ?>
          <?php if ($user_data && ($user_data['name'] || $user_data['last_n'])): ?>
            <span class="profileTitles">Имя:</span> <?= htmlspecialchars(trim($user_data['name'] . ' ' . $user_data['last_n'])) ?><br>
          <?php endif; ?>
          <span class="profileTitles">Подписчики:</span> <?= $subscribers_count ?><br>
          <span class="profileTitles">Видео просмотрено:</span> <?= $profile['videos_watched'] ?><br>
          <span class="profileTitles">Профиль просмотрен:</span> <?= $profile['profile_viewed'] ?> раз<br>
          <span class="profileTitles">Последний вход:</span> <?= $profile['last_login'] ?><br>
          <span class="profileTitles">Зарегистрирован:</span> <?= $profile['member_since'] ?><br>
          <?php if ($user_data && $user_data['website']): ?>
            <?php 
            $website_url = $user_data['website'];
            if (!preg_match('/^https?:\/\//', $website_url)) {
                $website_url = 'http://' . $website_url;
            }
            ?>
            <span class="profileTitles">Сайт:</span>&nbsp;<a href="<?= htmlspecialchars($website_url) ?>" class="profileLink" target="_blank"><?= htmlspecialchars($user_data['website']) ?></a><br>
          <?php endif; ?>
          <?php if ($user_data && $user_data['hometown']): ?>
            <span class="profileTitles">Родной город:</span> <?= htmlspecialchars($user_data['hometown']) ?><br>
          <?php endif; ?>
          <?php if ($user_data && $user_data['city']): ?>
            <span class="profileTitles">Текущий город:</span> <?= htmlspecialchars($user_data['city']) ?><br>
          <?php endif; ?>
        </table>
      </div>
    </div>
          <div class="profileBox">
      <div class="profileBoxHead" style="background:#888; color:#000; padding:3px; font-size:12px;">Мои комментарии</div>
      
        <?php if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2'): ?>
          <div class="profileBoxContent" style="background:#F4F4F4;">
          <center>Этот пользователь отключил возможность комментирования своего профиля.</center>
        <?php elseif (isset($_SESSION['user'])): ?>
          <div class="profileBoxContent">
  <a href="channel.php?user=<?=urlencode($profile['username'])?>&tab=comments&action=new" class="profileLink">Оставить комментарий</a> для <?= $profile['username'] ?>.
  <form id="profileCommentForm" action="comments.php?user=<?=urlencode($profile['username'])?>" method="post" style="display:none; margin-top:8px;">
    <textarea name="comment" rows="3" cols="40" style="font-size:13px;"></textarea><br>
    <input type="submit" value="Отправить" style="font-size:13px;">
  </form>
        <?php else: ?>
          <div class="profileBoxContent">
  <a href="#" class="profileLink" onclick="alert('Только для зарегистрированных пользователей!');return false;">Оставить комментарий</a> для <?= $profile['username'] ?>.

        <?php endif; ?>
        <?php if (!$user_data || !isset($user_data['profile_comm']) || $user_data['profile_comm'] !== '2'): ?>
Публикуемые вами комментарии будут видны всем, кто просматривает профиль пользователя <?= $profile['username'] ?>.
        <?php endif; ?>
      </div>
  </div>
  </td>
</tr>
</table>
  <?php
    showFooter();
    exit;
}

if ($user && isset($_GET['tab']) && $_GET['tab'] === 'videos') {
  $fr_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
  $fr_count = (file_exists($fr_file)) ? count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
  $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
  $per_page = 5;
  $offset = ($page - 1) * $per_page;
    $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
  $stmt->execute([$user]);
  $total = $stmt->fetchColumn();
  $total_pages = ceil($total / $per_page);
    $stmt = $db->prepare("SELECT id, title, preview, description, time, views, user, file, tags FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT $offset, $per_page");
  $stmt->execute([$user]);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    showHeader('Публичные видео // ' . htmlspecialchars($user));
    ?>
  <link rel="stylesheet" href="img_/styles_ets11562102812.css" type="text/css">
  <style>
  .vfacets { margin: 5px 0 !important; }
  .vtagLabel { font-size: 11px !important; color: #888 !important; display: inline !important; }
  .vtagValue { display: inline !important; margin-left: 5px !important; }
  .vtagValue .dg { color: #333 !important; text-decoration: underline !important; }
  .vtagValue .dg:hover { color: #333 !important; text-decoration: underline !important; }
  </style>
<link rel="stylesheet" href="img_/base_ets1156367996.css" type="text/css">
<link rel="stylesheet" href="img_/watch_ets1156799200.css" type="text/css">
	<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">
  <a href="channel.php?user=<?=urlencode($user)?>">Профиль</a> |
  <b>Видео</b> (<?= $total ?>)</a>
  <a href="favourites.php?user=<?=urlencode($user)?>">Избранное (<?=$fav_count?>)</a> |
  <a href="friends.php?user=<?=urlencode($user)?>">Друзья (<?=$fr_count?>)</a> |
  <a href="channel.php?user=<?=urlencode($user)?>&tab=comments">Комментарии (<?=$comments_count?>)</a>
</div>
    <table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr valign="top">
      <td style="padding-right: 15px;">
        <table width="595" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
          <tr>
            <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
            <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
            <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
          </tr>
          <tr>
            <td><img src="img/pixel.gif" width="5" height="1"></td>
            <td style="padding: 5px 0px 5px 0px;">
              <div class="moduleTitleBar">
                <div class="moduleTitle">Публичные видео // <?=htmlspecialchars($user)?></div>
              </div>
              <?php if (count($videos) == 0): ?>
                <div style="padding:20px; background:#f8f8f8; border:1px solid #ccc; color:#888;">Нет видео.</div>
              <?php else: ?>
				<script type="text/javascript">
					function showDescMore(id) {
						var s = document.getElementById ? document.getElementById(id+'-short') : document.all[id+'-short'];
						var f = document.getElementById ? document.getElementById(id+'-full') : document.all[id+'-full'];
						if (s && f) { s.style.display = 'none'; f.style.display = 'inline'; }
						return false;
					}
					function showDescless(id) {
						var s = document.getElementById ? document.getElementById(id+'-short') : document.all[id+'-short'];
						var f = document.getElementById ? document.getElementById(id+'-full') : document.all[id+'-full'];
						if (s && f) { f.style.display = 'none'; s.style.display = 'inline'; }
						return false;
					}
				</script>
                <?php foreach ($videos as $row): ?>
                  <?php
                  $desc = htmlspecialchars($row['description']);
                  $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                  $desc_id = 'desc_chan_' . $row['id'];
                  $desc_full = nl2br($desc);
                  ?>
                  <div class="moduleEntry">
                    <table width="565" cellpadding="0" cellspacing="0" border="0">
                      <tr valign="top">
                        <td width="120"><a href="video.php?id=<?=intval($row['id'])?>"><img src="<?=htmlspecialchars($row['preview'])?>" class="moduleEntryThumb" width="120" height="90" style="border:1px solid #888;"></a></td>
                        <td width="100%" style="padding-left:8px;">
                          <div style="font-size:15px; font-weight:bold;"><a href="video.php?id=<?=intval($row['id'])?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($row['title'])?></a></div>
                          <div style="font-size:12px; color:#222; font-weight:bold; margin:2px 0 2px 0;"><?=get_video_duration($row['file'], $row['id'])?></div>
                          <span id="<?= $desc_id ?>-short" style="font-size:12px; color:#222; margin:2px 0 2px 0;">
                            <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                          </span>
                          <span id="<?= $desc_id ?>-full" style="display:none; font-size:12px; color:#222; margin:2px 0 2px 0;">
                            <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                          </span>
                          <?php if (!empty($row['tags'])): ?>
                          <div class="vfacets">
                              <div class="vtagLabel">Теги:</div>
                              <div class="vtagValue">
                                  <?php 
                                  $tags = explode(' ', trim($row['tags']));
                                  $tag_links = [];
                                  foreach ($tags as $tag): 
                                      $tag = trim($tag);
                                      if (!empty($tag)):
                                          $tag_links[] = '<a href="results.php?search_type=tag&search_query='.urlencode($tag).'" class="dg">'.htmlspecialchars($tag).'</a>';
                                      endif;
                                  endforeach;
                                  echo implode(' &nbsp; ', $tag_links);
                                  ?>
                              </div>
                          </div>
                          <?php endif; ?>
                          <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Добавлено:</span> <?= time_ago(strtotime($row['time'])) ?></div>
                          <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Автор</span> <a href="channel.php?user=<?= htmlspecialchars($row['user']) ?>" style="color:#0033cc; text-decoration:underline;"><b><?= htmlspecialchars($row['user']) ?></b></a></div>
                          <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Просмотров:</span> <?= intval($row['views']) ?></div>
                          <?php list($rc,$ra)=channel_get_rating_stats($db,$row['id']); echo channel_render_avg_stars_html($ra,$rc); ?>
                        </td>
                      </tr>
                    </table>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
              <?php if ($total_pages > 1): ?>
                <div class="pagingDiv" style="margin: 0px 0 0px 0;">
                  Стр.
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);
                  
                  if ($start_page > 1) {
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos&page=1">1</a></span>';
                      if ($start_page > 2) echo ' ... ';
                  }
                  
                  for ($i = $start_page; $i <= $end_page; $i++) {
                      if ($i == $page) {
                          echo '<span class="pagerCurrent">'.$i.'</span>';
                      } else {
                          echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos&page='.$i.'">'.$i.'</a></span>';
                      }
                  }
                  
                  if ($end_page < $total_pages) {
                      if ($end_page < $total_pages - 1) echo ' ... ';
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos&page='.$total_pages.'">'.$total_pages.'</a></span>';
                  }
                  
                  if ($page < $total_pages) {
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos&page='.($page + 1).'">Далее</a></span>';
                  }
                  ?>
                </div>
              <?php endif; ?>
            </td>
        </table>
      </td>
      <td width="180">
        <table width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFEEBB">
          <tr>
            <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
            <td><img src="img/pixel.gif" width="1" height="5"></td>
            <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
          </tr>
          <tr>
            <td><img src="img/pixel.gif" width="5" height="1"></td>
            <td width="170">
              <div style="font-size: 16px; font-weight: bold; text-align: center; padding: 5px 5px 10px 5px;"><a href="register.php">Зарегистрируйтесь бесплатно!</a></div>
            </td>
            <td><img src="img/pixel.gif" width="5" height="1"></td>
          </tr>
          <tr>
            <td><img src="img/box_login_bl.gif" width="5" height="5"></td>
            <td><img src="img/pixel.gif" width="1" height="5"></td>
            <td><img src="img/box_login_br.gif" width="5" height="5"></td>
          </tr>
        </table>
      </td>
    </tr>
    </table>
    <?php
    showFooter();
    exit;
}

if (!$user && (!isset($_GET['tab']) || $_GET['tab'] === '')) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'recent';
    $filter_name = 'Последние';
    
    switch ($filter) {
        case 'recent':
            $filter_name = 'Последние';
            break;
        case 'viewed':
            $filter_name = 'Популярные';
            break;
        case 'rated':
            $filter_name = 'Высоко оцененные';
            break;
        case 'discussed':
            $filter_name = 'Обсуждаемые';
            break;
        case 'favorites':
            $filter_name = 'Избранные';
            break;
        case 'linked':
            $filter_name = 'Ссылки';
            break;
        case 'featured':
            $filter_name = 'Рекомендуемые';
            break;
    }
    
    $order_by = 'id DESC';
    
    switch ($filter) {
        case 'recent':
            $order_by = 'id DESC';
            break;
        case 'viewed':
            $order_by = 'views DESC, id DESC';
            break;

        case 'rated':
            $stmt = $db->prepare("SELECT v.id, v.title, v.preview, v.description, v.time, v.views, v.user, v.file, 
                COALESCE(AVG(r.rating),0) AS avg_rating, 
                COUNT(r.id) AS votes_count,
                (COALESCE(AVG(r.rating),0) * COUNT(r.id)) / (1 + COUNT(r.id)) AS weighted_rating
                FROM videos v
                LEFT JOIN ratings r ON r.video_id = v.id
                WHERE v.private = 0
                GROUP BY v.id
                ORDER BY weighted_rating DESC, v.views DESC, v.id DESC");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;

        case 'discussed':
            $stmt = $db->prepare("SELECT id, title, preview, description, time, views, user, file FROM videos WHERE private = 0");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $comments_cache = [];
            $comments_dir = __DIR__ . '/comments/';
            
            foreach ($all_videos as $video) {
                $comments_file = $comments_dir . $video['id'] . '.txt';
                if (file_exists($comments_file)) {
                    $line_count = 0;
                    $handle = fopen($comments_file, 'r');
                    while (!feof($handle)) {
                        $line = fgets($handle);
                        if (trim($line) !== '') {
                            $line_count++;
                        }
                    }
                    fclose($handle);
                    $comments_cache[$video['id']] = $line_count;
                } else {
                    $comments_cache[$video['id']] = 0;
                }
            }
            
            usort($all_videos, function($a, $b) use ($comments_cache) {
                $a_comments = $comments_cache[$a['id']] ?? 0;
                $b_comments = $comments_cache[$b['id']] ?? 0;
                if ($a_comments != $b_comments) {
                    return $b_comments - $a_comments;
                }
                return $b['views'] - $a['views'];
            });
            
            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;
        case 'favorites':
            $stmt = $db->prepare("SELECT id, title, preview, description, time, views, user, file FROM videos WHERE private = 0");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $favorites_cache = [];
            $favourites_dir = __DIR__ . '/favourites';
            
            foreach (glob("$favourites_dir/*.txt") as $file) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $video_id) {
                    if (!isset($favorites_cache[$video_id])) {
                        $favorites_cache[$video_id] = 0;
                    }
                    $favorites_cache[$video_id]++;
                }
            }
            
            usort($all_videos, function($a, $b) use ($favorites_cache) {
                $a_favorites = $favorites_cache[$a['id']] ?? 0;
                $b_favorites = $favorites_cache[$b['id']] ?? 0;
                if ($a_favorites != $b_favorites) {
                    return $b_favorites - $a_favorites;
                }
                return $b['views'] - $a['views'];
            });
            
            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;
    }
    
    if ($filter !== 'discussed' && $filter !== 'favorites' && $filter !== 'rated') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE private = 0");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
        
        $stmt = $db->prepare("SELECT id, title, preview, description, time, views, user, file FROM videos WHERE private = 0 ORDER BY $order_by LIMIT $offset, $per_page");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/loose.dtd">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title><?= $filter_name ?> видео - RetroShow</title>
<link rel="stylesheet" href="img_/styles_ets11562102812.css" type="text/css">
<link rel="stylesheet" href="img_/base_ets1156367996.css" type="text/css">
<link rel="stylesheet" href="img_/watch_ets1156799200.css" type="text/css">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<meta name="description" content="Share your videos with friends and family">
<meta name="keywords" content="video,sharing,camera phone,video phone">
<script type="text/javascript" src="img_/ui_ets11558746822.js"></script>
<script language="javascript" type="text/javascript">
onLoadFunctionList = new Array();
function performOnLoadFunctions() {
    for (var i in onLoadFunctionList) {
        onLoadFunctionList[i]();
    }
}
</script>
</head>
<body onload="performOnLoadFunctions();">
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center">
<tr><td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="img/logo_sm.gif" width="120" height="48" alt="RetroShow" border="0" style="vertical-align: middle; "></a></td>
<td valign="top">
<table width="670" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td style="padding: 0px 5px 0px 5px; font-style: italic;">Загружайте и делитесь видео по всему миру!</td>
<td align="right">
<table cellpadding="0" cellspacing="0" border="0"><tr>
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
				<a href="<?php echo isset($_SESSION['user']) ? 'favourites.php?user=' . urlencode($_SESSION['user']) : 'login.php'; ?>" class="dropdownLinks"><span class="smallText">Избранное</span></a>
			</div>
			<div class="menuBoxItem <?php echo ($currentPage == 'friends.php') ? 'active' : ''; ?>" id="MyAccountSubscription" onmouseover="showDropdown();changeBGcolor(this,1);" onmouseout="changeBGcolor(this,0);">
				<a href="<?php echo isset($_SESSION['user']) ? 'friends.php?user=' . urlencode($_SESSION['user']) : 'login.php'; ?>" class="dropdownLinks"><span class="smallText">Мои друзья</span></a>
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
</tr></table>
</td></tr></table>
</td></tr>
<tr valign="bottom">
		<td>
		
		<div id="gNavDiv">
			<?php
			$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
			$tabs = [
				['index.php', 'Главная', 'index.php'],
				['channel.php,favourites.php,friends.php', 'Смотреть&nbsp;видео', 'channel.php'],
				['upload.php', 'Загрузить&nbsp;видео', 'upload.php'],
				['my_friends_invite.php', 'Пригласить&nbsp;друзей', 'my_friends_invite.php']
			];
			foreach ($tabs as $tab) {
				$is_active = in_array($current_script, explode(',', $tab[0]));
				$class = $is_active ? 'ltab' : 'tab';
				$rc_class = $is_active ? 'rcs' : 'rc';
				$selected = $is_active ? ' selected' : '';
				echo "<div class=\"$class\"><b class=\"$rc_class\"><b class=\"{$rc_class}1\"><b></b></b><b class=\"{$rc_class}2\"><b></b></b><b class=\"{$rc_class}3\"></b><b class=\"{$rc_class}4\"></b><b class=\"{$rc_class}5\"></b></b><div class=\"tabContent$selected\"><a href=\"{$tab[2]}\">{$tab[1]}</a></div></div>";
			}
			?>
		</div>
</td>
	</tr>
</table>
<?php if (session_status() == PHP_SESSION_NONE) session_start(); ?>
<table align="center" width="800" bgcolor="#DDDDDD" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 10px;">
<tr>
<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
<td><img src="img/pixel.gif" width="1" height="5"></td>
<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
</tr>
<tr>
<td><img src="img/pixel.gif" width="5" height="1"></td>
<td width="790" align="center" style="padding: 2px;">
<table cellpadding="0" cellspacing="0" border="0">
<tr>
<?php
$filters = [
    'recent' => 'Последние',
    'viewed' => 'Популярные', 
    'rated' => 'Высоко оцененные',
    'discussed' => 'Обсуждаемые',
    'favorites' => 'Избранные'
];

$first = true;
foreach ($filters as $filter_key => $filter_label) {
    if (!$first) {
        echo '<td style="padding: 0px 10px 0px 10px;">|</td>';
    }
    $is_active = ($filter === $filter_key);
    echo '<td style="  ">';
    if ($is_active) {
        echo '<b><a href="channel.php?filter=' . $filter_key . '">' . $filter_label . '</a></b>';
    } else {
        echo '<a href="channel.php?filter=' . $filter_key . '">' . $filter_label . '</a>';
    }
    echo '</td>';
    $first = false;
}
?>


</tr>
</table>
</td>
<td><img src="img/pixel.gif" width="5" height="1"></td>
</tr>
<tr>
<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_bl.gif" width="5" height="5"></td>
<td style="border-bottom: 1px solid #BBBBBB"><img src="img/pixel.gif" width="1" height="5"></td>
<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_br.gif" width="5" height="5"></td>
</tr>
</table>
	<center>
		<table width="790" cellpadding="0" cellspacing="0" border="0">
	<tr valign="top">
	<td style="padding-right:15px;">
		<div class="headerRCBox">
		<b class="rch">
		<b class="rch1"><b></b></b>
		<b class="rch2"><b></b></b>
		<b class="rch3"></b>
		<b class="rch4"></b>
		<b class="rch5"></b>
		</b> <div class="content">    <div class="headerTitleRight">Видео <?= ($offset + 1) ?>-<?= min($offset + $per_page, $total) ?> из <?= $total ?></div>
		<div class="headerTitle" style="text-align:left;"><?= $filter_name ?> видео</div>
	</div>
		</div>

	<div class="contentBox">	
	<table cellpadding="0" cellspacing="0" border="0" width="100%">
		<?php
		$i = 0;
		foreach ($videos as $video) {
			if ($i % 4 == 0) echo '<tr valign="top">';
			?>
			<td width="20%">
				<div class="v120vEntry">
					<div class="img">
						<a href="video.php?id=<?=intval($video['id'])?>"><img src="<?=htmlspecialchars($video['preview'])?>" class="vimg" width="120" height="90"></a>
					</div>
					<div class="title" style="text-align:left;">
						<b><a href="video.php?id=<?=intval($video['id'])?>"><?=htmlspecialchars($video['title'])?></a></b><br>
						                <span class="runtime"><?=get_video_duration($video['file'], $video['id'])?></span>
					</div>
					<div class="facets" style="text-align:left;">
						<span class="grayText">Добавлено:</span> <?=time_ago(strtotime($video['time']))?><br>
						<span class="grayText">Автор:</span> <a href="channel.php?user=<?=urlencode($video['user'])?>"><?=htmlspecialchars($video['user'])?></a><br>
						<span class="grayText">Просмотров:</span> <?=intval($video['views'])?><br>
							<?php list($rc,$ra)=channel_get_rating_stats($db,$video['id']); echo channel_render_avg_stars_html($ra,$rc); ?>
					</div>
				</div>
			</td>
			<?php
			$i++;
			if ($i % 4 == 0) echo '</tr>';
		}
		if ($i % 4 != 0) {
			for ($j = $i % 4; $j < 4; $j++) echo '<td width="20%"></td>';
			echo '</tr>';
		}
		?>
	</table>
	</div>
			<div class="footerBox">
		
		<div class="pagingDiv">
				Стр.

			    <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<span class="pagerNotCurrent"><a href="?page=1&filter='.$filter.'">1</a></span>';
                    if ($start_page > 2) echo ' ... ';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span class="pagerCurrent">'.$i.'</span>';
                    } else {
                        echo '<span class="pagerNotCurrent"><a href="?page='.$i.'&filter='.$filter.'">'.$i.'</a></span>';
                    }
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo ' ... ';
                    echo '<span class="pagerNotCurrent"><a href="?page='.$total_pages.'&filter='.$filter.'">'.$total_pages.'</a></span>';
                }
                
                if ($page < $total_pages) {
                    echo '<span class="pagerNotCurrent"><a href="?page='.($page + 1).'&filter='.$filter.'">Далее</a></span>';
                }
                ?>
			</div> 
	</div>
</td>
<td width="180">
	<table width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFEEBB">
		<tr>
			<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
			<td><img src="img/pixel.gif" width="1" height="5"></td>
			<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
		</tr>
		<tr>
			<td><img src="img/pixel.gif" width="5" height="1"></td>
			<td width="170">
				<div style="font-size: 16px; font-weight: bold; text-align: center; padding: 5px 5px 10px 5px;"><a href="register.php">Зарегистрируйтесь бесплатно!</a></div>
			</td>
			<td><img src="img/pixel.gif" width="5" height="1"></td>
		</tr>
		<tr>
			<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
			<td><img src="img/pixel.gif" width="1" height="5"></td>
			<td><img src="img/box_login_br.gif" width="5" height="5"></td>
		</tr>
	</table>
</td>
</tr>
</table>
<div style="padding: 0px 5px 0px 5px;">

</div>
		</td></tr></table>
    <table cellpadding="10" cellspacing="0" border="0" align="center">
	<tbody><tr>
		<td align="center" valign="center"><span class="footer"><a href="about.php">О сайте</a> | <a href="http://github.com/tankwars92/RetroShow">Исходный код</a> | <a href="http://downgrade.hoho.ws/">Downgrade Net</a></span> 
		<br><br>Copyright © 2026 RetroShow | <a href="rss/global/recently_added.rss"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align: text-top;"></a></span>
		<br>
		<br>
		<!--<!--<script src="//downgrade.hoho.ws/services/ring/ring.php"></script> <img src="//downgrade.hoho.ws/services/counter/index.php?id=9" alt="Downgrade Counter">-->
	</td>
	</tr>
</tbody></table>

<?php
exit;
}

if ($user && isset($_GET['tab']) && $_GET['tab'] === 'comments' && isset($_GET['action']) && $_GET['action'] === 'new') {
    if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2') {
        header('Location: channel.php?user='.urlencode($user));
        exit;
    }
    if (!isset($_SESSION['user'])) {
        header('Location: channel.php?user='.urlencode($user));
        exit;
    }
    $comment_error = '';
    if (isset($_POST['submit_comment'])) {
        $comment = trim($_POST['comment'] ?? '');
        if ($comment === '') {
            $comment_error = 'Комментарий не может быть пустым!';
    } else {
            $comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';
            $line = time() . '|' . str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $_SESSION['user']) . '|' . str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $comment) . "\n";
            file_put_contents($comments_file, $line, FILE_APPEND | LOCK_EX);
            header('Location: channel.php?user='.urlencode($user).'&tab=comments');
            exit;
        }
    }
    showHeader('Оставить комментарий');
    $now = time();
?>
<form method="post" action="channel.php?user=<?=urlencode($user)?>&tab=comments&action=new">
<table width="550" align="center" cellpadding="0" cellspacing="0" border="1" style="border-collapse:collapse; margin-top:30px; border-color:#999999;">
  <tr>
    <td colspan="2" style="background:#888; color:#000; font-weight:bold; padding:3px;">Оставить новый комментарий</td>
  </tr>
  <tr>
    <td width="110" style="background:#f8f8f8; text-align:right; padding:8px; border-right:1px solid #bbb; font-weight:bold; color:#666;">От:</td>
    <td style="padding:8px;">
      <table cellpadding="0" cellspacing="0" border="0"><tr>
        <td><img src="<?= get_profile_icon($_SESSION['user'], get_user_profile_icon_setting($_SESSION['user'])) ?>" width="140" height="108" style="border:1px solid #bbb; background:#eee;">
		<br>
		<a href="channel.php?user=<?=htmlspecialchars($_SESSION['user'])?>" style="font-weight:bold; color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($_SESSION['user'])?></a>
		<br>
		<br>
</td>
      </tr><tr>

      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="background:#f8f8f8; text-align:right; padding:8px; border-right:1px solid #bbb; font-weight:bold; color:#666;">Дата:</td>
    <td style="padding:8px; font-weight:bold; color:#666;">
      <?=date('F d, Y, H:i A', $now)?>
    </td>
  </tr>
  <tr>
    <td style="background:#f8f8f8; text-align:right; padding:8px; border-right:1px solid #bbb; font-weight:bold; color:#666;">Текст:</td>
    <td style="padding:8px;">
      <textarea tabindex="2" maxlength="255" name="comment" cols="55" rows="30"></textarea>
      <?php if ($comment_error): ?><div style="color:red; font-size:12px; margin-top:4px;"><?=htmlspecialchars($comment_error)?></div><?php endif; ?>
    </td>
  </tr>
</form>
</table>
<center>
	<br>
	<input type="submit" name="submit_comment" value="Отправить комментарий" style="font-size:13px;">
</center>
<?php
showFooter();
exit;
}

if ($user && isset($_GET['tab']) && $_GET['tab'] === 'comments' && !isset($_GET['action'])) {
    showHeader('Комментарии о пользователе');
    $comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';
    $comments = [];
    if (file_exists($comments_file)) {
        $lines = file($comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $l) {
            $parts = explode('|', $l, 3);
            if (count($parts) >= 3) {
                $comments[] = [
                    'time' => intval($parts[0]),
                    'user' => $parts[1],
                    'text' => $parts[2]
                ];
            }
        }
    }

	$fav_file = __DIR__ . '/favourites/' . urlencode($user) . '.txt';
	$fav_count = (file_exists($fav_file)) ? count(file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;

	$profile_comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';
	$comments_count = (file_exists($profile_comments_file)) ? count(file($profile_comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
	
	$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
	$stmt_total->execute([$user]);
	$total = $stmt_total->fetchColumn();

	echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
	echo (!isset($_GET['tab']) || $_GET['tab'] == '') 
		? '<b>Профиль</b>' : '<a href="channel.php?user='.urlencode($user).'">Профиль</a>';
	echo ' | ';
	echo (isset($_GET['tab']) && $_GET['tab'] === 'videos')
		? '<b>Видео ('.$total.')</b>' : '<a href="channel.php?user='.urlencode($user).'&tab=videos">Видео ('.$total.')</a>';
	echo ' | ';
	echo '<a href="favourites.php?user='.urlencode($user).'">Избранное ('.$fav_count.')</a> | ';
	$fr_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
	$fr_count = (file_exists($fr_file)) ? count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
	echo '<a href="friends.php?user='.urlencode($user).'">Друзья ('.$fr_count.')</a> | ';
	echo (isset($_GET['tab']) && $_GET['tab'] === 'comments')
		? '<b>Комментарии ('.$comments_count.')</b>' : '<a href="channel.php?user='.urlencode($user).'&tab=comments">Комментарии ('.$comments_count.')</a>';
	echo '</div>';
    ?>
    <table width="550" align="center" cellpadding="0" cellspacing="0" border="1" style="border-collapse:collapse; border-color:#999999;">
      <tr>
        <td colspan="2" style="background:#888; color:#000; font-weight:bold; padding:3px;">
          Комментарии <?=htmlspecialchars($user)?>
        </td>
      </tr>
      <?php if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2'): ?>
      <tr>
        <td colspan="2" style="padding:5px; text-align:center; background:#F4F4F4;">Этот пользователь отключил возможность комментирования своего профиля.</td>
      </tr>
      <?php elseif (count($comments) == 0): ?>
      <tr>
        <td colspan="2" style="padding:20px; text-align:center; color:#888;">Нет комментариев.</td>
      </tr>
      <?php else: foreach (array_reverse($comments) as $c): ?>
      <tr>
        <td width="110" style="background:#f8f8f8; text-align:center; padding:8px; border-right:1px solid #bbb;">
          <a href="channel.php?user=<?=htmlspecialchars($c['user'])?>" style="font-weight:bold; color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($c['user'])?></a><br>
          <?php $pi = get_user_profile_icon_setting($c['user']); $avatar = get_profile_icon($c['user'], $pi); ?>
          <br><img src="<?= $avatar ?>" width="64" height="50" style="border:1px solid #bbb; background:#eee;">
        </td>
        <td style="padding:8px; vertical-align:top;">
          <div style="color:#888; font-size:13px;"><b><?=date('F d, Y', $c['time'])?></b></div>
		  <br>
          <div style="word-wrap: anywhere; width: 400px"><?=nl2br(htmlspecialchars($c['text']))?></div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      
      <?php if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2'): ?>

          <?php else: ?>
            <tr>
        <td colspan="2" style="padding:10px; background:#f8f8f8; text-align:center;">
            <a href="channel.php?user=<?=urlencode($user)?>&tab=comments&action=new" style="color:#0033cc; text-decoration:underline;">Оставить комментарий</a> для <?=htmlspecialchars($user)?>.
            <span style="color:#666; font-size:12px;">Публикуемые вами комментарии будут видны всем, кто просматривает профиль пользователя <?=htmlspecialchars($user)?>.</span>
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <?php
    showFooter();
    exit;
}
  



?>
<html><head><title><?=( $user ? 'Канал ' . htmlspecialchars($user) : $filter_name . ' видео' )?> - RetroShow</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link href="img/styles.css" rel="stylesheet" type="text/css">

<link rel="stylesheet" href="img/epiktube.css" type="text/css">
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
</style>
</head>
<body onload="performOnLoadFunctions();">
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center">
<tr><td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="img/logo_sm.gif" width="120" height="48" alt="RetroShow" border="0" style="vertical-align: middle; "></a></td>
<td valign="top">
<table width="670" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td style="padding: 0px 5px 0px 5px; font-style: italic;">Загружайте и делитесь видео по всему миру!</td>
<td align="right">
<table cellpadding="0" cellspacing="0" border="0"><tr>
    <?php if (!isset($_SESSION['user'])): ?>
<td><a href="register.php"><strong>Регистрация</strong></a></td>
<td style="padding: 0px 5px 0px 5px;">|</td>
<td><a href="login.php">Вход</a></td>
<td style="padding: 0px 5px 0px 5px;">|</td>
<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
    <?php else: ?>
<td>Привет, <strong><?=htmlspecialchars($_SESSION['user'])?></strong></td>
<td style="padding: 0px 5px 0px 5px;">|</td>
<td><a href="help.php">Помощь</a></td>
<td style="padding: 0px 5px 0px 5px;">|</td>
<td style="padding-right: 5px;"><a href="logout.php">Выйти</a></td>
    <?php endif; ?>
</tr></table>
</td></tr></table>
</td></tr>
<tr valign="bottom">
		<td>
		
		<div id="gNavDiv">
			<?php
			$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
			$tabs = [
				['index.php', 'Главная', 'index.php'],
				['channel.php,favourites.php,friends.php', 'Смотреть&nbsp;видео', 'channel.php'],
				['upload.php', 'Загрузить&nbsp;видео', 'upload.php'],
				['my_friends_invite.php', 'Пригласить&nbsp;друзей', 'my_friends_invite.php']
			];
			foreach ($tabs as $tab) {
				$is_active = in_array($current_script, explode(',', $tab[0]));
				$class = $is_active ? 'ltab' : 'tab';
				$rc_class = $is_active ? 'rcs' : 'rc';
				$selected = $is_active ? ' selected' : '';
				echo "<div class=\"$class\"><b class=\"$rc_class\"><b class=\"{$rc_class}1\"><b></b></b><b class=\"{$rc_class}2\"><b></b></b><b class=\"{$rc_class}3\"></b><b class=\"{$rc_class}4\"></b><b class=\"{$rc_class}5\"></b></b><div class=\"tabContent$selected\"><a href=\"{$tab[2]}\">{$tab[1]}</a></div></div>";
			}
			?>
		</div>
</td>
	</tr>
</table>
<?php if (session_status() == PHP_SESSION_NONE) session_start(); ?>
<table align="center" width="800" bgcolor="#DDDDDD" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 10px;">
<tr>
<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
<td><img src="img/pixel.gif" width="1" height="5"></td>
<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
</tr>
<tr>
<td><img src="img/pixel.gif" width="5" height="1"></td>
<td width="790" align="center" style="padding: 2px;">
<table cellpadding="0" cellspacing="0" border="0">
<tr>
<td style="font-size: 10px;">&nbsp;</td>
<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'channel.php?user=' . urlencode($_SESSION['user']) . '&tab=videos'; } else { echo 'login.php'; } ?>">Мои видео</a></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'channel.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Мой канал</a></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'favourites.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Избранное</a></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'friends.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Мои друзья</a></td>
<td style="padding: 0px 10px 0px 10px;">|</td>
<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'account.php'; } else { echo 'login.php'; } ?>">Настройки</a></td>
<td style="font-size: 10px;">&nbsp;</td>
</tr>
</table>
</td>
<td><img src="img/pixel.gif" width="5" height="1"></td>
</tr>
<tr>
<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_bl.gif" width="5" height="5"></td>
<td style="border-bottom: 1px solid #BBBBBB"><img src="img/pixel.gif" width="1" height="5"></td>
<td style="border-bottom: 1px solid #FFFFFF"><img src="img/box_login_br.gif" width="5" height="5"></td>
</tr>
</table>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;">
		
		<table width="595" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td style="padding: 5px 0px 5px 0px;">
    <div class="moduleTitleBar">
      <div class="moduleTitle"><?=( $user ? 'Видео от ' . htmlspecialchars($user) : $filter_name . ' видео' )?></div>
    </div>
    <?php if (count($videos) == 0): ?>
      <div style="padding:20px; background:#f8f8f8; border:1px solid #ccc; color:#888;">Нет видео.</div>
    <?php else: ?>
      <?php foreach ($videos as $row): ?>
        <div class="moduleEntry">
          <table width="565" cellpadding="0" cellspacing="0" border="0">
            <tr valign="top">
              <td><a href="video.php?id=<?=intval($row['id'])?>"><img src="<?=htmlspecialchars($row['preview'])?>" class="moduleEntryThumb" width="120" height="90"></a></td>
              <td width="100%">
                <div class="moduleEntryTitle"><a href="video.php?id=<?=intval($row['id'])?>" style="color:#0033cc; text-decoration:none; font-size:15px; font-weight:bold;"><?=htmlspecialchars($row['title'])?></a></div>
                <?php
                $desc = htmlspecialchars($row['description']);
                $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                $desc_id = 'desc_chan_' . $row['id'];
                $desc_full = nl2br($desc);
                ?>
                <span id="<?= $desc_id ?>-short" style="font-size:12px; color:#222; margin:2px 0 2px 0;">
                  <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                </span>
                <span id="<?= $desc_id ?>-full" style="display:none; font-size:12px; color:#222; margin:2px 0 2px 0;">
                  <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                </span>
                <div class="moduleEntryDetails">Добавлено: <?=time_ago(strtotime($row['time']))?> пользователем <a href="channel.php?user=<?=urlencode($row['user'])?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($row['user'])?></a></div>
                <div class="moduleEntryDetails">Просмотров: <?=intval($row['views'])?> | Комментариев: <?php $cf = __DIR__.'/comments/'.intval($row['id']).'.txt'; echo file_exists($cf)?count(file($cf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)):0; ?></div>
                <?php list($rc,$ra)=channel_get_rating_stats($db,$row['id']); echo channel_render_avg_stars_html($ra,$rc); ?>
              </td>
            </tr>
          </table>
        </div>
      <?php endforeach; ?>
	  <?php endif; ?>

<script type="text/javascript">
function showDescMore(id) {
  var s = document.getElementById ? document.getElementById(id+'-short') : document.all[id+'-short'];
  var f = document.getElementById ? document.getElementById(id+'-full') : document.all[id+'-full'];
  if (s && f) { s.style.display = 'none'; f.style.display = 'inline'; }
  return false;
}
function showDescless(id) {
  var s = document.getElementById ? document.getElementById(id+'-short') : document.all[id+'-short'];
  var f = document.getElementById ? document.getElementById(id+'-full') : document.all[id+'-full'];
  if (s && f) { f.style.display = 'none'; s.style.display = 'inline'; }
  return false;
}
</script>

</td>
<td><img src="img/pixel.gif" width="5" height="1"></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>
		
		</td>
		<td width="180">
		
		<table width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFEEBB">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td width="170">
		
								
				<div style="font-size: 16px; font-weight: bold; text-align: center; padding: 5px 5px 10px 5px;"><a href="register.php">Зарегистрируйтесь бесплатно!</a></div>
				
								
				</td>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>
		
		</td>
	</tr>
</tbody></table>

<table cellpadding="10" cellspacing="0" border="0" align="center">
<tr>
<td align="center" valign="center"><span class="footer"><a href="about.php">О сайте</a> | <a href="http://github.com/tankwars92/retroshow">Исходный код</a> | <a href="http://downgrade.hoho.ws/">Downgrade Net</a> 
<br><br>Copyright © 2026 RetroShow | <a href="rss/global/recently_added.rss"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align: text-top;"></a></span></td>
</tr>
</table>

</body></html>

