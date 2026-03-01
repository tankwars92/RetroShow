<?php
include 'init.php';
include 'template.php';

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
$user = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['user']) ? $_SESSION['user'] : null);
$fav_file = $user ? __DIR__ . '/favourites/' . urlencode($user) . '.txt' : null;
$fav_list = ($fav_file && file_exists($fav_file)) ? file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();

if (isset($_SESSION['user']) && $user === $_SESSION['user'] && isset($_POST['remove_fav']) && isset($_POST['video_id'])) {
    $fav_list = array_diff($fav_list, [$_POST['video_id']]);
    file_put_contents($fav_file, implode("\n", $fav_list));
    header('Location: favourites.php');
    exit;
}

$videos = array();
if ($fav_list) {
    $db = new PDO('sqlite:retroshow.sqlite');
    $in = str_repeat('?,', count($fav_list)-1) . '?';
    $stmt = $db->prepare("SELECT id, public_id, user, title, description, file, tags, time, views, private, preview 
                      FROM videos 
                      WHERE id IN ($in)");
    $stmt->execute($fav_list);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      if (empty($row['private'])) {
          $row['public_id'] = !empty($row['public_id']) ? $row['public_id'] : $row['id'];
          $videos[$row['id']] = $row;
      }
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total = count($fav_list);
$total_pages = ceil($total / $per_page);
$offset = ($page - 1) * $per_page;
$paged_fav_list = array_slice($fav_list, $offset, $per_page);

$is_own = isset($_SESSION['user']) && $user === $_SESSION['user'];
$user_disp = htmlspecialchars($user);

showHeader('Избранное');
?>

<style>
.vfacets { margin: 5px 0; }
.vtagLabel { font-size: 11px; color: #888; display: inline; }
.vtagValue { display: inline; margin-left: 5px; }
.vtagValue .dg { color: #333; text-decoration: underline; }
.vtagValue .dg:hover { color: #333; text-decoration: underline; }
</style>

<?php
$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$user]);
$total = $stmt_total->fetchColumn();

$comments_count = 0;
$profile_comments_file = __DIR__ . '/comments/profile_' . urlencode($user) . '.txt';

if (file_exists($profile_comments_file)) {
    $comments_count = count(file($profile_comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}

echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
echo '<a href="channel.php?user='.urlencode($user).'">Профиль</a> | ';
echo '<a href="channel.php?user='.urlencode($user).'&tab=videos">Видео ('.$total.')</a> | ';
echo '<b>Избранное ('.count($fav_list).')</b> | ';
$fr_file = __DIR__ . '/friends/' . urlencode($user) . '.txt';
$fr_count = (file_exists($fr_file)) ? count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
echo '<a href="friends.php?user='.urlencode($user).'">Друзья ('.$fr_count.')</a> | ';
echo '<a href="channel.php?user='.urlencode($user).'&tab=comments">Комментарии ('.$comments_count.')</a>';
echo '</div>';
?>
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
              <div class="moduleTitle"><?=($is_own ? 'Мои избранные видео' : 'Избранные // '.$user_disp)?></div>
            </div>
            <?php if (!$fav_list): ?>
              <div style="padding:20px; background:#f8f8f8; border:1px solid #ccc; color:#888;">
                <?=($is_own ? 'У вас нет избранных видео.' : 'У пользователя '.$user_disp.' нет избранных видео.')?>
              </div>
            <?php else: ?>
              <?php foreach ($paged_fav_list as $vid): if (empty($videos[$vid])) continue; $video = $videos[$vid]; 
                    $desc = htmlspecialchars($video['description']);
                    $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                    $desc_id = 'desc_' . $video['id'];
                    $desc_full = nl2br($desc);
              ?>
                <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                  <table width="565" cellpadding="0" cellspacing="0" border="0">
                    <tr valign="top">
                      <td width="120"><a href="video.php?id=<?=htmlspecialchars($video['public_id'])?>"><img src="<?=htmlspecialchars($video['preview'])?>" class="moduleEntryThumb" width="120" height="90" style="border:1px solid #888;"></a></td>
                      <td width="100%" style="padding-left:8px;">
                        <div style="font-size:15px; font-weight:bold;"><a href="video.php?id=<?= htmlspecialchars($video['public_id']) ?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($video['title'])?></a></div>
                        <div style="font-size:12px; color:#222; font-weight:bold; margin:2px 0 2px 0;"><?=get_video_duration($video['file'], $video['id'])?></div>
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
                        <?php if ($is_own): ?>
                        <form method="post" style="margin:0; display:inline;"><input type="hidden" name="remove_fav" value="1"><input type="hidden" name="video_id" value="<?=intval($video['id'])?>"><a href="#" onclick="this.parentNode.submit();return false;" style="color:#0033cc; text-decoration:underline; font-size:11px; margin:0; padding:0; cursor:pointer;">Удалить из избранного</a></form>
                        <?php endif; ?>
                      </td>
                    </tr>
                  </table>
                </div>
              <?php endforeach; ?>
              <?php if ($total_pages > 1): ?>
              <div class="pagingDiv" style="margin-top:10px; margin-bottom:4px;">
                <table cellpadding="0" cellspacing="0" border="0" style="margin:0 auto;"><tr>
                <td style="padding:0 5px 0 0;">Страницы:</td>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                $user_param = $user ? ('user='.urlencode($user).'&') : '';
                if ($start_page > 1) {
                    echo '<td style="border:1px solid #999; background:#fff; padding:2px 8px;"><a href="?'.$user_param.'page=1" style="color:#0033CC; text-decoration:none;">1</a></td>';
                    if ($start_page > 2) {
                        echo '<td style="width:8px; text-align:center;">&nbsp;...&nbsp;</td>';
                    } else {
                        echo '<td style="width:5px;"></td>';
                    }
                }
                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<td style="border:1px solid #999; background:#ccc; font-weight:bold; padding:2px 8px;">'.$i.'</td>';
                    } else {
                        echo '<td style="border:1px solid #ccc; background:#fff; padding:2px 8px;"><a href="?'.$user_param.'page='.$i.'" style="color:#0033CC; text-decoration:none;">'.$i.'</a></td>';
                    }
                    if ($i < $end_page) echo '<td style="width:5px;"></td>';
                }
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<td style="width:8px; text-align:center;">&nbsp;...&nbsp;</td>';
                    } else {
                        echo '<td style="width:5px;"></td>';
                    }
                    echo '<td style="border:1px solid #999; background:#fff; padding:2px 8px;"><a href="?'.$user_param.'page='.$total_pages.'" style="color:#0033CC; text-decoration:none;">'.$total_pages.'</a></td>';
                }
                ?>
                </tr></table>
              </div>
              <?php endif; ?>
            <?php endif; ?>
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