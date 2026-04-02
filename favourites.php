<?php
include 'init.php';
include 'template.php';
require_once __DIR__ . '/duration_helper.php';

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

function get_video_duration($file, $id, $public_id = '') {
    return get_video_duration_fast($file, $id, $public_id);
}
$user = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['user']) ? $_SESSION['user'] : null);

if (isset($_SESSION['user']) && $user === $_SESSION['user'] && isset($_POST['remove_fav']) && isset($_POST['video_id'])) {
    $db->prepare("DELETE FROM user_favourites WHERE user = ? AND video_id = ?")
       ->execute([$user, intval($_POST['video_id'])]);
    header('Location: favourites.php?user=' . urlencode($user));
    exit;
}

$fav_list = [];
if ($user) {
    $stmtFav = $db->prepare("SELECT video_id FROM user_favourites WHERE user = ? ORDER BY created_at DESC");
    $stmtFav->execute([$user]);
    $fav_list = $stmtFav->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
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
$fav_total = count($fav_list);
$total_pages = ceil($fav_total / $per_page);
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
.channelPagingDiv { background: #CCC; margin: 0; padding: 5px 0; font-size: 13px; color: #333; font-weight: bold; text-align: right; }
.channelPagingDiv .pagerCurrent { color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent { color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent a { color: #03C; text-decoration: underline; }
</style>

<?php
$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$user]);
$total = $stmt_total->fetchColumn();

$comments_count = 0;
$comments_count = 0;
try {
    $stmtPc = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
    $stmtPc->execute([$user]);
    $comments_count = (int)$stmtPc->fetchColumn();
} catch (Exception $e) {}

echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
echo '<a href="channel.php?user='.urlencode($user).'">Профиль</a> | ';
echo '<a href="channel.php?user='.urlencode($user).'&tab=videos">Видео ('.$total.')</a> | ';
echo '<b>Избранное ('.count($fav_list).')</b> | ';
$fr_count = 0;
try {
    $stmtFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
    $stmtFr->execute([$user]);
    $fr_count = (int)$stmtFr->fetchColumn();
} catch (Exception $e) {}
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
          <td width="585">
            <div class="moduleTitleBar">
              <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="font-size:14px; font-weight:bold; color:#444; text-align:left; padding-left: 5px;  padding-bottom: 5px;">
                    <?=($is_own ? 'Мои избранные видео' : 'Избранные // '.$user_disp)?>
                  </td>
                  <td style="font-size:12px; font-weight:bold; color:#444; text-align:right; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
                    Видео <?= $fav_total ? ($offset + 1) . '-' . min($offset + $per_page, $fav_total) . ' из ' . $fav_total : '0 из 0' ?>
                  </td>
                </tr>
              </table>
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
                        <div style="font-size:12px; color:#222; font-weight:bold; margin:2px 0 2px 0;"><?=get_video_duration($video['file'], $video['id'], $video['public_id'] ?? '')?></div>
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
                <div class="channelPagingDiv pagingDiv">
                  Стр.
                  <?php
                  $start_page = max(1, $page - 2);
                  $end_page = min($total_pages, $page + 2);
                  $user_param = $user ? ('user='.urlencode($user).'&') : '';
                  if ($start_page > 1) {
                      echo '<span class="pagerNotCurrent"><a href="?'.$user_param.'page=1">1</a></span>';
                      if ($start_page > 2) echo ' ... ';
                  }
                  for ($i = $start_page; $i <= $end_page; $i++) {
                      if ($i == $page) {
                          echo '<span class="pagerCurrent">'.$i.'</span>';
                      } else {
                          echo '<span class="pagerNotCurrent"><a href="?'.$user_param.'page='.$i.'">'.$i.'</a></span>';
                      }
                  }
                  if ($end_page < $total_pages) {
                      if ($end_page < $total_pages - 1) echo ' ... ';
                      echo '<span class="pagerNotCurrent"><a href="?'.$user_param.'page='.$total_pages.'">'.$total_pages.'</a></span>';
                  }
                  if ($page < $total_pages) {
                      echo '<span class="pagerNotCurrent"><a href="?'.$user_param.'page='.($page + 1).'">Далее</a></span>';
                  }
                  ?>
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