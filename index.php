<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
include("init.php"); 
include("template.php");

$contest = false;

if (isset($_SESSION['user'])) {
    $stmt = $db->prepare("SELECT SUM(views) FROM videos WHERE user = ?");
    $stmt->execute([$_SESSION['user']]);
    $video_views = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare("SELECT profile_viewed FROM user_stats WHERE user = ?");
    $stmt->execute([$_SESSION['user']]);
    $channel_views = $stmt->fetchColumn() ?: 0;

    $subscribers_count = 0;
    $friends_file = __DIR__ . '/friends/' . urlencode($_SESSION['user']) . '.txt';
    $friends_count = file_exists($friends_file) ? count(file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
    if (file_exists($friends_file)) {
        $friends = file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($friends as $friend) {
            $friend_friends_file = __DIR__ . '/friends/' . urlencode($friend) . '.txt';
            if (!file_exists($friend_friends_file) || !in_array($_SESSION['user'], file($friend_friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))) {
                $subscribers_count++;
            }
        }
    }

    $userStats = [
        'video_views' => $video_views,
        'channel_views' => $channel_views,
        'friends' => $friends_count
    ];
}

function time_ago($time) {
    $diff = time() - $time;
    if ($diff < 60) return $diff.' секунд назад';
    $mins = floor($diff/60);
    if ($mins < 60) {
        $n = $mins;
        $f = ($n%10==1 && $n%100!=11) ? 'минуту' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'минуты' : 'минут');
        return "$n $f назад";
    }
    $hours = floor($mins/60);
    if ($hours < 24) {
        $n = $hours;
        $f = ($n%10==1 && $n%100!=11) ? 'час' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'часа' : 'часов');
        return "$n $f назад";
    }
    $days = floor($hours/24);
    if ($days < 7) {
        $n = $days;
        $f = ($n%10==1 && $n%100!=11) ? 'день' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'дня' : 'дней');
        return "$n $f назад";
    }
    $weeks = floor($days/7);
    if ($weeks < 5) {
        $n = $weeks;
        $f = ($n%10==1 && $n%100!=11) ? 'неделю' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'недели' : 'недель');
        return "$n $f назад";
    }
    $months = floor($days/30);
    if ($months < 12) {
        $n = $months;
        $f = ($n%10==1 && $n%100!=11) ? 'месяц' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'месяца' : 'месяцев');
        return "$n $f назад";
    }
    $years = floor($days/365);
    $n = $years;
    $f = ($n%10==1 && $n%100!=11) ? 'год' : (($n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20)) ? 'года' : 'лет');
    return "$n $f назад";
}

function get_home_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}
function render_avg_stars_html($avg, $count) {
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

$stmt = $db->query("SELECT * FROM videos ORDER BY id DESC LIMIT 10");
$recent_videos = array_filter($stmt->fetchAll(), function($v) { return empty($v['private']); });

  $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
  $per_page = 5;
  $offset = ($page - 1) * $per_page;
  
  $stmt = $db->query("SELECT COUNT(*) FROM videos");
  $total = $stmt->fetchColumn();
  $total_pages = ceil($total / $per_page);
  
$stmt = $db->query("SELECT * FROM videos WHERE private = 0 ORDER BY id DESC");
$all_videos = $stmt->fetchAll();

$featured_videos = [];
$three_days_ago = time() - (3 * 24 * 60 * 60);

foreach ($all_videos as $video) {
    $video_time = strtotime($video['time']);
    
    if ($video_time < $three_days_ago) {
        continue;
    }
    
    $age_in_hours = max(1, (time() - $video_time) / 3600);
    $views_per_hour = $video['views'] / $age_in_hours;
    
    $featured_videos[] = [
        'video' => $video,
        'views_per_hour' => $views_per_hour
    ];
}

usort($featured_videos, function($a, $b) {
    return $b['views_per_hour'] <=> $a['views_per_hour'];
});

$featured_videos = array_slice($featured_videos, 0, 5);

showHeader("Главная");
?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'video_not_found'): ?>
  <div class="errorBox">Видео не найдено.</div>
<?php endif; ?>

<style>
.vfacets { margin: 5px 0; }
.vtagLabel { font-size: 11px; color: #888; display: inline; }
.vtagValue { display: inline; margin-left: 5px; }
.vtagValue .dg { color: #333; text-decoration: underline; }
.vtagValue .dg:hover { color: #333; text-decoration: underline; }
</style>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;">
		
		<table width="595" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#E5ECF9">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td style="padding: 5px 0px 5px 0px;">
				
								
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tbody><tr valign="top">
					<td width="33%" style="border-right: 1px dashed #369; padding: 0px 10px 10px 10px; color: #444;">
					<div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;"><a href="channel.php">Смотрите</a></div>
					Мгновенно находите и смотрите тысячи быстрых потоковых видео.
					</td>
					<td width="33%" style="border-right: 1px dashed #369; padding: 0px 10px 10px 10px; color: #444;">
					<div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;"><a href="upload.php">Загружайте</a></div>
					Быстро загружайте видео практически в любом формате.
					</td>
					<td width="33%" style="padding: 0px 10px 10px 10px; color: #444;">
					<div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;"><a href="my_friends_invite.php">Делитесь</a></div>
					Легко делитесь своими видео с семьей, друзьями или коллегами.
					</td>
					</tr>
				</tbody></table>

									
				</td>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>

		<?php if (!empty($recent_videos)): ?>
		<div style="padding: 10px 0px 10px 0px;">
		<table width="595" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#EEEEDD">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td width="585">
				<div style="padding: 2px 5px 8px 5px;">
				<div style="font-size: 14px; font-weight: bold; color: #666633;">Недавно добавленные...</div>
				
				<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
				<tbody><tr>
							
						<?php 
						$count = 0;
						foreach ($recent_videos as $video): 
							if ($count >= 5) break;
						?>
						<td width="20%" align="center">
		
						<a href="video.php?id=<?= $video['id'] ?>"><img src="<?= $video['preview'] ?>" width="80" height="60" style="border: 5px solid #FFFFFF; margin-top: 10px;"></a>
						<div class="moduleFeaturedDetails" style="padding-top: 2px;">
<?= time_ago(strtotime($video['time'])) ?>
</div>
		
						</td>
						<?php 
							$count++;
						endforeach; 
						?>
										</tr>
				</tbody></table>
				
				</div>
				</td>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>
		</div>
		<?php endif; ?>
		
		<table width="595" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td width="585">
				<div class="moduleTitleBar">
  <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td style="font-size:14px; font-weight:bold; color:#444; text-align:left;">Популярные видео сегодня</td>
      <td style="text-align:right; font-size:12px; padding-right:5px; white-space:nowrap;">
		<nobr><a href="channel.php">Больше видео</a></nobr>
		</td>
    </tr>
  </table>
</div>
		
				<?php
                function get_video_duration($file, $id) {
                    $cache_file = __DIR__ . '/uploads/' . intval($id) . '_duration.txt';
                    if (file_exists($cache_file)) {
                        $duration = trim(file_get_contents($cache_file));
                        if (preg_match('/^\d{1,5}(:[0-5]\d){1,2}$/', $duration)) return $duration;
                    }
                    $ffprobe = 'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 ' . escapeshellarg($file);
                    $out = shell_exec($ffprobe);
                    $seconds = intval(round(floatval($out)));
                    $h = floor($seconds / 3600);
                    $m = floor(($seconds % 3600) / 60);
                    $s = $seconds % 60;
                    if ($h > 0) {
                        $duration = sprintf('%d:%02d:%02d', $h, $m, $s);
    } else {
                        $duration = sprintf('%d:%02d', $m, $s);
                    }
                    if ($seconds < 360000) file_put_contents($cache_file, $duration);
                    return $duration;
                }
                foreach ($featured_videos as $item):
                    $video = $item['video'];
                    $desc = htmlspecialchars($video['description']);
                    $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                    $comments_file = __DIR__ . '/comments/' . $video['id'] . '.txt';
                    $comments_count = (file_exists($comments_file)) ? count(file($comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
					list($rc, $ra) = get_home_rating_stats($db, $video['id']);
                ?>
                <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                  <table width="565" cellpadding="0" cellspacing="0" border="0">
                    <tr valign="top">
                      <td width="120"><a href="video.php?id=<?= $video['id'] ?>"><img src="<?= $video['preview'] ?>" class="moduleEntryThumb" width="120" height="90" style="border:1px solid #888;"></a></td>
                      <td width="100%" style="padding-left:8px;">
						<div class="vtitle">
							<a href="video.php?id=<?= $video['id'] ?>"><?= htmlspecialchars($video['title']) ?></a><br>
							<span class="runtime"><?=get_video_duration($video['file'], $video['id'])?></span>
						</div>
                        <?php
                        $desc_id = 'desc_' . $video['id'];
                        $desc_full = nl2br($desc);
                        ?>
                        <span id="<?= $desc_id ?>-short" style="font-size:12px; color:#222; margin:2px 0 2px 0;">
                          <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                        </span>
                        <span id="<?= $desc_id ?>-full" style="display:none; font-size:12px; color:#222; margin:2px 0 2px 0;">
                          <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                        </span>
                        <?php if (!empty($video['tags'])): ?>
                        <div class="vfacets">
                            <div class="vtagLabel">Теги:</div>
                            <div class="vtagValue">
                                <span id="vidTagsBegin-<?=$video['id']?>">
                                    <?php 
                                    $tags = explode(' ', trim($video['tags']));
                                    $visible_tags = array_slice($tags, 0, 5);
                                    $hidden_tags = array_slice($tags, 5);
                                    
                                    foreach ($visible_tags as $tag): 
                                      $tag = trim($tag);
                                      if (!empty($tag)):
                                    ?>
                                      <a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>" class="dg"><?=htmlspecialchars($tag)?></a>&nbsp;
                                    <?php 
                                      endif;
                                    endforeach; 
                                    
                                    if (!empty($hidden_tags)):
                                    ?>
                                    <span id="vidTagsRemain-<?=$video['id']?>" style="display: none;">
                                      <?php foreach ($hidden_tags as $tag): 
                                        $tag = trim($tag);
                                        if (!empty($tag)):
                                      ?><a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>" class="dg"><?=htmlspecialchars($tag)?></a>&nbsp;<?php 
                                        endif;
                                      endforeach; 
                                      ?></span>&nbsp;<span id="vidTagsMore-<?=$video['id']?>" class="smallText">(<a href="#" class="eLink" onclick="showInline('vidTagsRemain-<?=$video['id']?>'); hideInline('vidTagsMore-<?=$video['id']?>'); showInline('vidTagsLess-<?=$video['id']?>'); return false;">ещё</a>)</span><span id="vidTagsLess-<?=$video['id']?>" class="smallText" style="display: none;">(<a href="#" class="eLink" onclick="hideInline('vidTagsRemain-<?=$video['id']?>'); hideInline('vidTagsLess-<?=$video['id']?>'); showInline('vidTagsMore-<?=$video['id']?>'); return false;">меньше</a>)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Добавлено:</span> <?= time_ago(strtotime($video['time'])) ?></div>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Автор:</span> <a href="channel.php?user=<?= htmlspecialchars($video['user']) ?>" style="color:#0033cc; text-decoration:underline;"><b><?= htmlspecialchars($video['user']) ?></b></a></div>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Просмотров:</span> <?= intval($video['views']) ?></div>
						<?= render_avg_stars_html($ra, $rc) ?>
                      </td>
                    </tr>
                  </table>
                </div>
                <?php endforeach; ?>					
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

    <?php if ($contest): ?>
    <div style="margin-top: 10px;">
		<table width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFCC99">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td width="170" style="padding: 5px; text-align: center;">
				<div style="font-weight: bold; font-size: 13px;">Сентябрьский конкурс!</div>
				
				<a href="#"><img src="" width="80" height="60" style="border: 5px solid #FFFFFF; margin-top: 10px;"></a>
				
				<div style="font-size: 16px; font-weight: bold; padding-top: 5px;"><a href="monthly_contest.php">ИМЯ!</a></div>
				<div style="font-size: 11px; padding: 10px 0px 5px 0px;">RetroShow представляет наш первый ежемесячный конкурс видео!</div>
				
								
				<div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;"><a href="<?= isset($_SESSION['user']) ? 'monthly_contest.php' : 'signup.php' ?>">Присоединяйтесь к конкурсу сейчас!</a></div>
				
								
				</td>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
			</tr>
			<tr>
				<td><img src="img/box_login_bl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_br.gif" width="5" height="5"></td>
			</tr>
		</tbody></table>
		</div>
    <?php endif; ?>

        <div class="hpContentBlock" style="margin-top:20px;">
          <div class="headerRCBox">
            <b class="rch">
              <b class="rch1"><b></b></b>
              <b class="rch2"><b></b></b>
              <b class="rch3"></b>
              <b class="rch4"></b>
              <b class="rch5"></b>
            </b> 
            <div class="content">
              <span class="headerTitle">
                <?php if (isset($_SESSION['user'])): ?>
                  Привет, <?=htmlspecialchars($_SESSION['user'])?>
                <?php else: ?>
                  Что тут делать?
                <?php endif; ?>
              </span>
            </div>
          </div>
          
          <table width="180" cellpadding="6" cellspacing="0" border="0" style="background:#fff; border:1px solid #ccc;">
            <tr>
              <td style="font-size:12px; color:#222;">
                <?php if (isset($_SESSION['user'])): ?>
                  <table width="90%" cellpadding="2" cellspacing="0" border="0" style="font-size:11px;">
                    <tr>
                      <td style="vertical-align:top;">
                        <span class="hpStatsHeading">Статистика</span><br>
                        <span class="smallLabel">Просмотров:</span> <?=$userStats['video_views']?><br>
                        <span class="smallLabel">Просмотров канала:</span> <?=$userStats['channel_views']?><br>
                        <span class="smallLabel">Подписчиков:</span> <?=$subscribers_count?><br>
                        <span class="smallLabel"><a href="channel.php?user=<?=urlencode($_SESSION['user'])?>">Мой канал</a></span>
                      </td>
                    </tr>
                  </table>
                  <div style="font-size:11px; padding-top:6px;">
                    <span class="hpStatsHeading">Ссылки</span><br>
                    <a href="channel.php?user=<?=urlencode($_SESSION['user'])?>&tab=videos">Мои видео</a> |
                    <a href="favourites.php">Избранное</a> |
                    <a href="friends.php">Мои друзья</a>
                  </div>
                <?php else: ?>
                <table class="hpAboutTable" width="90%">
                  <tbody><tr>
                    <td class="label"><font size="2"><a href="channel.php">Смотрите</a></font></td>
                    <td class="desc">Находите и смотрите тысячи потоковых видео.</td>
                  </tr>
                  <tr>
                    <td class="label"><font size="2"><a href="upload.php">Загружайте</a></font></td>
                    <td class="desc">Быстро загружайте видео практически в любом формате.</td>
                  </tr>
                  <tr>
                    <td class="label"><font size="2"><a href="my_friends_invite.php">Делитесь</a></font></td>
                    <td>Легко делитесь своими видео с друзьями, семьёй или коллегами.</td>
                  </tr>
                  </tbody></table>
                        
				<div style="border-top: 1px solid #CCC; margin-top: 6px; padding-top: 6px;">
				<b><font size="2" color="#000000">Вход для участников</font></b>
				</div>

                <form method="post" action="login.php" style="margin:0;">
                  <table width="90%" cellpadding="2" cellspacing="0" border="0" style="font-size:12px;">
                    <tr>
                      <td style="padding:2px 0 2px 0;"><b>Имя:</b></td>
                      <td style="padding:2px 0 2px 0;"><input type="text" name="login" style="width:100px; font-size:12px;"></td>
                    </tr>
                    <tr>
                      <td style="padding:2px 0 2px 0;"><b>Пароль:</b></td>
                      <td style="padding:2px 0 2px 0;"><input type="password" name="pass" style="width:100px; font-size:12px;"></td>
                    </tr>
                                      <tr>
                    <td style="padding:0px 0 2px 0; width:50%;">
                      <input type="submit" value="Войти" style="font-size:12px;">
                    </td>
                    <td style="padding:0px 0 2px 0; width:50%; text-align:right;">
                      <a href="register.php" style="font-size:12px;"><b>Регистрация</b></a>
                    </td>
                  </tr>
                  </table>
                </form>
                <div class="hpLoginForgot smallText">
						<b>Забыли:</b> <a href="forgot_username.php">Имя</a> | <a href="forgot.php">Пароль</a>
						</div>
                <?php endif; ?>
              </td>
            </tr>
          </table>
        </div>
        
        <?php
        $stmt = $db->prepare("SELECT login, COALESCE(last_login, 0) as last_login FROM users ORDER BY last_login DESC, id DESC LIMIT 8");
        $stmt->execute();
        $online_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count_user_videos = function($u) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
            $q->execute([$u]);
            return (int)$q->fetchColumn();
        };
        $count_user_favorites = function($u) {
            $fav_file = __DIR__ . '/favourites/' . urlencode($u) . '.txt';
            if (!file_exists($fav_file)) return 0;
            $lines = file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return $lines ? count($lines) : 0;
        };
        $count_user_friends = function($u) {
            $friends_file = __DIR__ . '/friends/' . urlencode($u) . '.txt';
            if (!file_exists($friends_file)) return 0;
            $lines = file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return $lines ? count($lines) : 0;
        };
        ?>
        <div style="margin-top:20px;">
          <table class="roundedTable" width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#EEEEDD">
            <tbody>
            <tr>
              <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
              <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
              <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
            </tr>
            <tr>
              <td><img src="img/pixel.gif" width="5" height="1"></td>
              <td width="170">
                <div style="font-size: 14px; font-weight: bold; margin-bottom: 8px; color:#666633;">Последние 8 каналов...</div>
                <?php foreach ($online_users as $iuser): $u = $iuser['login']; $vnum = $count_user_videos($u); $fnum = $count_user_favorites($u); $frnum = $count_user_friends($u); ?>
                  <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;"><a href="channel.php?user=<?=urlencode($u)?>"><?=htmlspecialchars($u)?></a></div>
                  <div style="font-size: 12px; margin-bottom: 8px; padding-bottom: 10px; border-bottom: 1px dashed #CCCC66;">
                    <a href="channel.php?user=<?=urlencode($u)?>"><img src="img/icon_vid.gif" alt="Videos" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"></a> (<a href="channel.php?user=<?=urlencode($u)?>&tab=videos"><?=$vnum?></a>)
                     | <a href="favourites.php?user=<?=urlencode($u)?>"><img src="img/icon_fav.gif" alt="Favorites" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"></a> (<a href="favourites.php?user=<?=urlencode($u)?>"><?=$fnum?></a>)
                     | <a href="friends.php?user=<?=urlencode($u)?>"><img src="img/icon_friends.gif" alt="Friends" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"></a> (<a href="friends.php?user=<?=urlencode($u)?>"><?=$frnum?></a>)
                  </div>
                <?php endforeach; ?>
                <div style="font-weight: bold; margin-bottom: 5px;">Иконки означают:</div>
                <div style="margin-bottom: 4px;"><img src="img/icon_vid.gif" alt="Videos" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"> - Видео</div>
                <div style="margin-bottom: 4px;"><img src="img/icon_fav.gif" alt="Favorites" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"> - Избранное</div>
                <img src="img/icon_friends.gif" alt="Friends" width="14" height="14" border="0" style="vertical-align: text-bottom; padding-left: 2px; padding-right: 1px;"> - Друзья
              </td>
              <td><img src="img/pixel.gif" width="5" height="1"></td>
            </tr>
            <tr>
              <td><img src="img/box_login_bl.gif" width="5" height="5"></td>
              <td><img src="img/pixel.gif" width="1" height="5"></td>
              <td><img src="img/box_login_br.gif" width="5" height="5"></td>
            </tr>
            </tbody></table>
        </div>
        
		</td>
	</tr>
</tbody></table>
<?php showFooter(); ?>

<script type="text/javascript">
function showDescMore(id) {
  var s = document.getElementById(id+'-short');
  var f = document.getElementById(id+'-full');
  if (s && f) { s.style.display = 'none'; f.style.display = 'inline'; }
  return false;
}
function showDescless(id) {
  var s = document.getElementById(id+'-short');
  var f = document.getElementById(id+'-full');
  if (s && f) { f.style.display = 'none'; s.style.display = 'inline'; }
  return false;
}
</script>
