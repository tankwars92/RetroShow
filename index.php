<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
include("init.php"); 
include("template.php");

$contest = false;

if (isset($_SESSION['user'])) {
    $current_user = $_SESSION['user'];
    $home_block_type = 'recent_added';

    $stmt = $db->prepare("SELECT SUM(views) FROM videos WHERE user = ?");
    $stmt->execute([$current_user]);
    $video_views = $stmt->fetchColumn() ?: 0;

    $stmt = $db->prepare("SELECT profile_viewed FROM user_stats WHERE user = ?");
    $stmt->execute([$current_user]);
    $channel_views = $stmt->fetchColumn() ?: 0;

    $friends_count = 0;
    $subscribers_count = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
        $stmt->execute([$current_user]);
        $friends_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $friends_count = 0;
    }
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM user_friends uf
            WHERE uf.friend = ?
              AND uf.user NOT IN (SELECT friend FROM user_friends WHERE user = ?)
        ");
        $stmt->execute([$current_user, $current_user]);
        $subscribers_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $subscribers_count = 0;
    }
    try {
        $stmt = $db->prepare("SELECT home_block_type FROM users WHERE login = ? LIMIT 1");
        $stmt->execute([$current_user]);
        $hbt = (string)$stmt->fetchColumn();
        if ($hbt === 'recent_viewed') {
            $home_block_type = 'recent_viewed';
        }
    } catch (Exception $e) {
        $home_block_type = 'recent_added';
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
        <img src="img/star_smn<?=($parts[0]==='full'?'':($parts[0]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[1]==='full'?'':($parts[1]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[2]==='full'?'':($parts[2]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[3]==='full'?'':($parts[3]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[4]==='full'?'':($parts[4]==='half'?'_half':'_bg'))?>.gif" style="border:0; vertical-align:middle;">
      </nobr>
      <div class="rating"><?=intval($count)?> оценок</div>
    </div>
    <?php
    return ob_get_clean();
}

function calculate_trending_score($video, $comments, $rating_avg, $rating_count) {
  $age_hours = max(1, (time() - strtotime($video['time'])) / 3600);

  $views_rate = $video['views'] / pow($age_hours, 1.2);

  $comments_score = $comments * 2;

  $rating_score = $rating_avg * log(1 + $rating_count) * 10;

  $fresh_bonus = ($age_hours < 24) ? 50 : 0;

  return $views_rate + $comments_score + $rating_score + $fresh_bonus;
}  

$recent_videos = [];
$recent_block_mode = 'recent_added';
if (isset($_SESSION['user']) && isset($home_block_type) && $home_block_type === 'recent_viewed') {
    try {
        $scan_limit = 300;
        $stmt = $db->prepare("SELECT video_id, viewed_at FROM video_views ORDER BY viewed_at DESC LIMIT ?");
        $stmt->bindValue(1, (int)$scan_limit, PDO::PARAM_INT);
        $stmt->execute();
        $view_rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $picked = [];
        $ordered_ids = [];
        foreach ($view_rows as $vr) {
            $vid = (int)($vr['video_id'] ?? 0);
            $vts = (int)($vr['viewed_at'] ?? 0);
            if ($vid <= 0 || $vts <= 0) continue;
            if (isset($picked[$vid])) continue;
            $picked[$vid] = $vts;
            $ordered_ids[] = $vid;
            if (count($ordered_ids) >= 30) break;
        }

        if (!empty($ordered_ids)) {
            $in = implode(',', array_fill(0, count($ordered_ids), '?'));
            $stmtV = $db->prepare("SELECT * FROM videos WHERE id IN ($in) AND (private = 0 OR private IS NULL)");
            $stmtV->execute($ordered_ids);
            $videos_rows = $stmtV->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $by_id = [];
            foreach ($videos_rows as $vr) {
                $by_id[(int)$vr['id']] = $vr;
            }

            $recent_videos = [];
            foreach ($ordered_ids as $vid) {
                if (!isset($by_id[$vid])) continue;
                $row = $by_id[$vid];
                $row['last_viewed_at'] = (int)$picked[$vid];
                $recent_videos[] = $row;
                if (count($recent_videos) >= 10) break;
            }
        }
        if (!empty($recent_videos)) {
            $recent_block_mode = 'recent_viewed';
        }
    } catch (Exception $e) {
        $recent_videos = [];
    }
}
if (empty($recent_videos)) {
    $stmt = $db->query("SELECT * FROM videos ORDER BY id DESC LIMIT 10");
    $recent_videos = array_filter($stmt->fetchAll(), function($v) { return empty($v['private']); });
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

$stmt = $db->query("SELECT COUNT(*) FROM videos");
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

$stmt = $db->query("SELECT * FROM videos WHERE private = 0 ORDER BY id DESC LIMIT 200");
$all_videos = $stmt->fetchAll();

$featured_videos = [];

foreach ($all_videos as $video) {
    $video_time = strtotime($video['time']);
    $age_hours = max(1, (time() - $video_time) / 3600);

    if ($age_hours > 168) continue;

    $comments_count = 0;
    try {
        $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
        $stmtCc->execute([$video['id']]);
        $comments_count = (int)$stmtCc->fetchColumn();
    } catch (Exception $e) {
        $comments_count = 0;
    }

    list($rc, $ra) = get_home_rating_stats($db, $video['id']);

    $score = calculate_trending_score($video, $comments_count, $ra, $rc);

    $featured_videos[] = [
        'video' => $video,
        'score' => $score
    ];
}

usort($featured_videos, function($a, $b) {
    return $b['score'] <=> $a['score'];
});

$featured_videos = array_slice($featured_videos, 0, 5);

showHeader("Главная");
?>

<?php
$tags_mode = isset($_GET['p']) ? (string)$_GET['p'] : '';
if ($tags_mode === 'tags') {
    $latestLimit = 200;
    $stmtLatest = $db->query("SELECT tags FROM videos WHERE private = 0 AND tags IS NOT NULL AND tags != '' ORDER BY id DESC LIMIT " . intval($latestLimit));
    $latest_counts = [];
    while ($row = $stmtLatest->fetch(PDO::FETCH_ASSOC)) {
        $tags = preg_split('/\s+/', trim((string)($row['tags'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') continue;
            if (!isset($latest_counts[$tag])) $latest_counts[$tag] = 0;
            $latest_counts[$tag]++;
        }
    }
    arsort($latest_counts);
    $latest_top = array_slice($latest_counts, 0, 50, true);
    $latest_min_count = !empty($latest_top) ? min($latest_top) : 1;
    $latest_max_count = !empty($latest_top) ? max($latest_top) : 1;
    $latest_base_font_size = 12;
    $latest_max_font_size = 17;

    $stmt = $db->query("SELECT tags FROM videos WHERE private = 0 AND tags IS NOT NULL AND tags != ''");
    $popular_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tags = preg_split('/\s+/', trim((string)($row['tags'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($tags as $tag) {
            $tag = trim((string)$tag);
            if ($tag === '') continue;
            if (!isset($popular_counts[$tag])) $popular_counts[$tag] = 0;
            $popular_counts[$tag]++;
        }
    }
    arsort($popular_counts);
    $popular_top = array_slice($popular_counts, 0, 50, true);
    $popular_min_count = !empty($popular_top) ? min($popular_top) : 1;
    $popular_max_count = !empty($popular_top) ? max($popular_top) : 1;

    ?>
    <div style="padding: 10px 0 0 0;">
        <div class="tableSubTitle">Теги</div>
        <div style="font-size: 14px; font-weight: bold; color: #666666; margin-bottom: 10px;">Последние теги //</div>
        <div style="margin-bottom: 20px; font-size: 13px; color: #333333;">
            <?php if (!empty($latest_top)): ?>
                <?php $i = 0; foreach ($latest_top as $tag => $count): ?>
                    <?php
                    if ($latest_max_count > $latest_min_count) {
                        $ratio = ($count - $latest_min_count) / ($latest_max_count - $latest_min_count);
                        $font_size = round($latest_base_font_size + ($latest_max_font_size - $latest_base_font_size) * $ratio);
                    } else {
                        $font_size = $latest_base_font_size;
                    }
                    ?>
                    <?php if ($i > 0) echo ' : '; $i++; ?>
                    <a style="font-size: <?=$font_size?>px;" href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a>
                <?php endforeach; ?>
                :
            <?php else: ?>
                <div style="font-size: 12px; color: #000;"><i>Тегов пока нет!</i></div>
            <?php endif; ?>
        </div>

        <div style="font-size: 16px; font-weight: bold; color: #666666; margin-bottom: 10px;">Популярные теги //</div>
        <div style="font-size: 13px; color: #333333;">
            <?php if (!empty($popular_top)): ?>
                <?php $i = 0; $popular_base_font_size = 12; $popular_max_font_size = 28; ?>
                <?php foreach ($popular_top as $tag => $count): ?>
                    <?php
                    if ($popular_max_count > $popular_min_count) {
                        $ratio = ($count - $popular_min_count) / ($popular_max_count - $popular_min_count);
                        $font_size = round($popular_base_font_size + ($popular_max_font_size - $popular_base_font_size) * $ratio);
                    } else {
                        $font_size = $popular_base_font_size;
                    }
                    ?>
                    <?php if ($i > 0) echo ' : '; $i++; ?>
                    <a style="font-size: <?=$font_size?>px;" href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a>
                <?php endforeach; ?>
                    :
            <?php else: ?>
                <div style="font-size: 12px; color: #000;"><i>Тегов пока нет!</i></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    showFooter();
    exit;
}
?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'video_not_found'): ?>
  <div class="errorBox">Видео не найдено.</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'video_not_allowed'): ?>
  <div class="errorBox">Видео не найдено или у вас нет прав для его редактирования.</div>
<?php endif; ?>

<?php if (isset($_GET['info']) && $_GET['info'] === 'video_converting'): ?>
  <div class="confirmBox">Ваше видео конвертируется! Скоро он будет доступно к просмотру.</div>
<?php endif; ?>

<style>
.vfacets { margin: 5px 0; }
.vtagLabel { font-size: 11px; color: #888; display: inline; }
.vtagValue { display: inline; margin-left: 5px; }

.vtagValue .dg,
.vtagValue .dg:visited {
  color: #333;
  text-decoration: underline;
}

.vtagValue .dg:hover {
  color: #333;
  text-decoration: underline;
}
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
					Мгновенно находите и смотрите тысячи видео.
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
				<div style="font-size: 14px; font-weight: bold; color: #666633;"><?= ($recent_block_mode === 'recent_viewed') ? 'Недавно просмотренные...' : 'Недавно добавленные...' ?></div>
				
				<table width="100%" align="center" cellpadding="0" cellspacing="0" border="0">
				<tbody><tr>
							
						<?php 
						$count = 0;
						foreach ($recent_videos as $video): 
							if ($count >= 5) break;
						?>
						<td width="20%" align="center">
		
						<a href="video.php?id=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>"><img src="<?= $video['preview'] ?>" width="80" height="60" style="border: 5px solid #FFFFFF; margin-top: 10px;"></a>
						<div class="moduleFeaturedDetails" style="padding-top: 2px;">
<?php
$ago_ts = ($recent_block_mode === 'recent_viewed' && !empty($video['last_viewed_at']))
    ? (int)$video['last_viewed_at']
    : strtotime($video['time']);
echo time_ago($ago_ts);
?>
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
      <td style="font-size:14px; font-weight:bold; color:#444; text-align:left; padding-left: 5px;  padding-bottom: 5px;">Популярные видео сегодня</td>
      <td style="text-align:right; font-size:12px; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
		<nobr><a href="channel.php"><b>Больше видео</b></a></nobr>
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
                    $comments_count = 0;
                    try {
                        $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                        $stmtCc->execute([$video['id']]);
                        $comments_count = (int)$stmtCc->fetchColumn();
                    } catch (Exception $e) {
                        $comments_count = 0;
                    }
					list($rc, $ra) = get_home_rating_stats($db, $video['id']);
                ?>
                <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                  <table width="565" cellpadding="0" cellspacing="0" border="0">
                    <tr valign="top">
                      <td width="120" valign="top"><a href="video.php?id=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>"><img src="<?= $video['preview'] ?>" class="moduleFeaturedThumb" width="120" height="90" style="margin: 0px 2px 0px 0px; display:block;"></a></td>
                      <td width="100%" style="padding-left:8px;">
						<div class="moduleEntryTitle">
							<a href="video.php?id=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>"><?= htmlspecialchars($video['title']) ?></a><br>
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
                                    $tags = preg_split('/\s+/', trim($video['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
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

        <div style="margin-top:20px;">
          <table class="roundedTable" width="180" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
            <tbody>
              <tr>
                <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
                <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
                <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
              </tr>
              <tr>
                <td><img src="img/pixel.gif" width="5" height="1"></td>
                <td width="170">
          <div class="moduleTitleBar">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="font-size: 13px; font-weight: bold; color: #444444; padding-bottom: 5px;">
                  <?php if (isset($_SESSION['user'])): ?>
                    Привет, <?=htmlspecialchars($_SESSION['user'])?>
                  <?php else: ?>
                    Что тут делать?
                  <?php endif; ?>
                </td>
                <td style="text-align:right; font-size:12px; padding-right:5px; padding-bottom: 7px; white-space:nowrap;"></td>
              </tr>
            </table>
          </div>
          
          <table width="100%" cellpadding="6" cellspacing="0" border="0" style="background:#fff; border:0;">
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
                    <td class="label"><font size="2"><a href="channel.php">Смотреть</a></font></td>
                    <td class="desc">Находите и смотрите тысячи видео.</td>
                  </tr>
                  <tr>
                    <td class="label"><font size="2"><a href="upload.php">Загружать</a></font></td>
                    <td class="desc">Быстро загружайте видео практически в любом формате.</td>
                  </tr>
                  <tr>
                    <td class="label"><font size="2"><a href="my_friends_invite.php">Делиться</a></font></td>
                    <td>Легко делитесь своими видео с друзьями, семьёй или коллегами.</td>
                  </tr>
                  </tbody></table>
                        
				<div style="border-top: 1px solid #CCC; margin-top: 6px; padding-top: 6px;">
				<b><font size="2" color="#000000">Войти в аккаунт:</font></b>
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
                </td>
                <td><img src="img/pixel.gif" width="5" height="1"></td>
              </tr>
              <tr>
                <td><img src="img/box_login_bl.gif" width="5" height="5"></td>
                <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
                <td><img src="img/box_login_br.gif" width="5" height="5"></td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <?php
        $tags_mode = isset($_GET['p']) ? (string)$_GET['p'] : '';

        $latestLimit = 200;
        $stmtLatest = $db->query("SELECT tags FROM videos WHERE private = 0 AND tags IS NOT NULL AND tags != '' ORDER BY id DESC LIMIT " . intval($latestLimit));
        $latest_counts = [];
        while ($row = $stmtLatest->fetch(PDO::FETCH_ASSOC)) {
            $tags = preg_split('/\s+/', trim((string)($row['tags'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($tags as $tag) {
                $tag = trim((string)$tag);
                if ($tag === '') continue;
                if (!isset($latest_counts[$tag])) $latest_counts[$tag] = 0;
                $latest_counts[$tag]++;
            }
        }
        arsort($latest_counts);
        $latest_top = array_slice($latest_counts, 0, 50, true);

        $latest_min_count = !empty($latest_top) ? min($latest_top) : 1;
        $latest_max_count = !empty($latest_top) ? max($latest_top) : 1;
        $latest_base_font_size = 12;
        $latest_max_font_size = 17;

        $popular_top = [];
        $popular_min_count = 1;
        $popular_max_count = 1;
        if ($tags_mode === 'tags') {
            $stmt = $db->query("SELECT tags FROM videos WHERE private = 0 AND tags IS NOT NULL AND tags != ''");
            $all_tags = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tags = preg_split('/\s+/', trim((string)($row['tags'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
                foreach ($tags as $tag) {
                    $tag = trim((string)$tag);
                    if ($tag === '') continue;
                    if (!isset($all_tags[$tag])) $all_tags[$tag] = 0;
                    $all_tags[$tag]++;
                }
            }
            arsort($all_tags);
            $popular_top = array_slice($all_tags, 0, 50, true);
            $popular_min_count = !empty($popular_top) ? min($popular_top) : 1;
            $popular_max_count = !empty($popular_top) ? max($popular_top) : 1;
        }
        ?>

        <?php if ($tags_mode === 'tags'): ?>
          <div class="tableSubTitle">Tags</div>

          <div style="font-size: 14px; font-weight: bold; color: #666666; margin-bottom: 10px;">Latest Tags //</div>
          <div style="margin-bottom: 20px; font-size: 13px; color: #333333;">
            <?php if (!empty($latest_top)): ?>
              <?php $i = 0; foreach ($latest_top as $tag => $count): ?>
                <?php
                if ($latest_max_count > $latest_min_count) {
                    $ratio = ($count - $latest_min_count) / ($latest_max_count - $latest_min_count);
                    $font_size = round($latest_base_font_size + ($latest_max_font_size - $latest_base_font_size) * $ratio);
                } else {
                    $font_size = $latest_base_font_size;
                }
                ?>
                <?php if ($i > 0) echo ' : '; $i++; ?>
                <a style="font-size: <?=$font_size?>px;" href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="font-size: 12px; color: #000;"><i>Тегов пока нет!</i></div>
            <?php endif; ?>
          </div>

          <div style="font-size: 16px; font-weight: bold; color: #666666; margin-bottom: 10px;">Most Popular Tags //</div>
          <div style="font-size: 13px; color: #333333;">
            <?php if (!empty($popular_top)): ?>
              <?php $i = 0; $popular_base_font_size = 12; $popular_max_font_size = 28; foreach ($popular_top as $tag => $count): ?>
                <?php
                if ($popular_max_count > $popular_min_count) {
                    $ratio = ($count - $popular_min_count) / ($popular_max_count - $popular_min_count);
                    $font_size = round($popular_base_font_size + ($popular_max_font_size - $popular_base_font_size) * $ratio);
                } else {
                    $font_size = $popular_base_font_size;
                }
                ?>
                <?php if ($i > 0) echo ' : '; $i++; ?>
                <a style="font-size: <?=$font_size?>px;" href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="font-size: 12px; color: #000;"><i>Тегов пока нет!</i></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="margin: 10px 0px 5px 0px; font-size: 12px; font-weight: bold; color: #333;">Недавние теги:</div>
          <div style="font-size: 13px; color: #333333;">
            <?php if (!empty($latest_top)): ?>
              <?php $i = 0; foreach ($latest_top as $tag => $count): ?>
                <?php
                if ($latest_max_count > $latest_min_count) {
                    $ratio = ($count - $latest_min_count) / ($latest_max_count - $latest_min_count);
                    $font_size = round($latest_base_font_size + ($latest_max_font_size - $latest_base_font_size) * $ratio);
                } else {
                    $font_size = $latest_base_font_size;
                }
                ?>
                <?php if ($i > 0) echo ' : '; $i++; ?>
                <a style="font-size: <?=$font_size?>px;" href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a>
              <?php endforeach; ?>
              :
            <?php else: ?>
              <div style="font-size: 12px; color: #000;"><i>Тегов пока нет!</i></div>
            <?php endif; ?>
          </div>
          <div style="font-size: 14px; font-weight: bold; margin-top: 10px;">
            <a href="index.php?p=tags">Больше тегов</a>
          </div>
        <?php endif; ?>
        
        <?php
        $stmt = $db->prepare("SELECT login, COALESCE(last_login, 0) as last_login FROM users ORDER BY last_login DESC, id DESC LIMIT 8");
        $stmt->execute();
        $online_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count_user_videos = function($u) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
            $q->execute([$u]);
            return (int)$q->fetchColumn();
        };
        $count_user_favorites = function($u) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
            $q->execute([$u]);
            return (int)$q->fetchColumn();
        };
        $count_user_friends = function($u) use ($db) {
            $q = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
            $q->execute([$u]);
            return (int)$q->fetchColumn();
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
