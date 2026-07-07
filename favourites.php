<?php
include 'init.php';
include 'template.php';
require_once __DIR__ . '/duration_helper.php';

function get_profile_icon($username, $profile_icon_setting = '0') {
    static $icon_cache = [];
    
    $icon_mode = (string)$profile_icon_setting;
    $cache_key = $username . '_' . $icon_mode;
    if (isset($icon_cache[$cache_key])) {
        return $icon_cache[$cache_key];
    }

    if ($icon_mode === '1') {
        global $db;
        try {
            $stmt = $db->prepare('SELECT profile_icon_custom FROM users WHERE login = ?');
            $stmt->execute([$username]);
            $custom = $stmt->fetchColumn();
            if ($custom && is_string($custom) && $custom !== '') {
                $icon_cache[$cache_key] = $custom;
                return $custom;
            }
        } catch (Exception $e) {
        }
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

function get_video_duration($file, $id, $public_id = '') {
    return get_video_duration_fast($file, $id, $public_id);
}

function favourites_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}

function favourites_render_avg_stars_html($avg, $count) {
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
        <img src="img/star_smn<?=($parts[0]==='full'?'':($parts[0]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[1]==='full'?'':($parts[1]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[2]==='full'?'':($parts[2]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[3]==='full'?'':($parts[3]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
        <img src="img/star_smn<?=($parts[4]==='full'?'':($parts[4]==='half'?'_half':'_bg'))?>.gif" style="border:0; padding:0px; margin:0px; vertical-align:middle;">
      </nobr>
      <span style="color:#666666; font-size:smaller;">(<?=intval($count)?> оценок)</span>
    </div>
    <?php
    return ob_get_clean();
}

$user = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['user']) ? $_SESSION['user'] : null);
$from_channel = isset($_GET['from']) && (string)$_GET['from'] === 'channel';

if (isset($_SESSION['user']) && $user === $_SESSION['user'] && isset($_POST['remove_fav']) && isset($_POST['video_id'])) {
    $db->prepare("DELETE FROM user_favourites WHERE user = ? AND video_id = ?")
       ->execute([$user, intval($_POST['video_id'])]);
    $redir = 'favourites.php?user=' . urlencode($user);
    if ($from_channel) {
        $redir .= '&from=channel';
    }
    header('Location: ' . $redir);
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
    $in = str_repeat('?,', count($fav_list)-1) . '?';
    $stmt = $db->prepare("SELECT id, public_id, user, title, description, file, tags, time, views, private, preview 
                      FROM videos 
                      WHERE id IN ($in)");
    $stmt->execute($fav_list);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      if (empty($row['private']) && video_is_ready($row) && !is_user_shadow_banned($row['user'] ?? '')) {
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

$my_tags = [];
if ($is_own && !$from_channel && !empty($videos)) {
    $tag_stats = [];
    foreach ($videos as $row) {
        $tags_raw = trim((string)($row['tags'] ?? ''));
        if ($tags_raw === '') continue;
        $parts = preg_split('/\s+/', $tags_raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) continue;
        foreach ($parts as $t) {
            $tag = trim((string)$t);
            if ($tag === '') continue;
            $k = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
            if (!isset($tag_stats[$k])) {
                $tag_stats[$k] = ['tag' => $tag, 'count' => 0];
            }
            $tag_stats[$k]['count']++;
        }
    }
    if (!empty($tag_stats)) {
        $tag_stats = array_values($tag_stats);
        usort($tag_stats, function ($a, $b) {
            if ((int)$a['count'] === (int)$b['count']) {
                return strcmp((string)$a['tag'], (string)$b['tag']);
            }
            return ((int)$b['count'] - (int)$a['count']);
        });
        $my_tags = array_slice($tag_stats, 0, 20);
    }
}

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
 $private_count = 0;
try {
    $stmtPc = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
    $stmtPc->execute([$user]);
    $comments_count = (int)$stmtPc->fetchColumn();
} catch (Exception $e) {}
try {
    $stmtPriv = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
    $stmtPriv->execute([$user]);
    $private_count = (int)$stmtPriv->fetchColumn();
} catch (Exception $e) {}
$fr_count = 0;
try {
    $stmtFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
    $stmtFr->execute([$user]);
    $fr_count = (int)$stmtFr->fetchColumn();
} catch (Exception $e) {}
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
                  <?php if (!$is_own || (int)$fav_total > 0): ?>
                  <td style="font-size:12px; font-weight:bold; color:#444; text-align:right; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
                    Видео <?= $fav_total ? ($offset + 1) . '-' . min($offset + $per_page, $fav_total) . ' из ' . $fav_total : '0 из 0' ?>
                  </td>
                  <?php endif; ?>
                </tr>
              </table>
            </div>
            <?php if (!$fav_list): ?>
              <?php if (!$is_own): ?>
                <div style="padding:10px;font-size:13px;color:#666;">
                Этот пользователь не добавил ничего в избранное.
                </div>
              <?php endif; ?>
            <?php else: ?>
              <?php foreach ($paged_fav_list as $vid): if (empty($videos[$vid])) continue; $video = $videos[$vid];
                    $vid_link = htmlspecialchars($video['public_id'] ?? $video['id']);
                    $desc = htmlspecialchars($video['description']);
                    $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                    $desc_id = 'desc_' . $video['id'];
                    $desc_full = nl2br($desc);
                    $comments_count = 0;
                    try {
                        $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                        $stmtCc->execute([$video['id']]);
                        $comments_count = (int)$stmtCc->fetchColumn();
                    } catch (Exception $e) {
                        $comments_count = 0;
                    }
                    list($rc, $ra) = favourites_rating_stats($db, $video['id']);
              ?>
                <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                  <table width="565" cellpadding="0" cellspacing="0" border="0">
                    <tr valign="top">
                      <td width="120" valign="top"><a href="video.php?id=<?=$vid_link?>"><img src="<?=htmlspecialchars($video['preview'])?>" class="moduleFeaturedThumb" width="120" height="90" style="margin: 0px 2px 0px 0px; display:block;"></a>
                      <div style="margin-top: 5px; align: center">
                      <center>
                      <?php if ($is_own): ?>
                      <form method="post" action="favourites.php?user=<?=urlencode($user)?><?php if ($from_channel): ?>&amp;from=channel<?php endif; ?>" onsubmit="return confirm('Убрать это видео из избранного?');" style="margin:0;">
                        <input type="hidden" name="remove_fav" value="1">
                        <input type="hidden" name="video_id" value="<?=intval($video['id'])?>">
                        <input type="submit" value="Удалить видео">
                      </form>
                      <?php endif; ?>
                      </center>
                      </div>
                      </td>
                      <td width="100%" style="padding-left:8px;">
                        <div class="moduleEntryTitle">
                          <a href="video.php?id=<?=$vid_link?>"><?=htmlspecialchars($video['title'])?></a>
                        </div>
                        <div class="moduleEntryDescription">
                        <span id="<?= $desc_id ?>-short">
                          <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                        </span>
                        <span id="<?= $desc_id ?>-full" style="display:none;">
                          <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                        </span>
                        <?php if (!empty($video['tags'])): ?>
                        <div class="vfacets">
                            <div class="moduleEntryTags">Теги //
                              <span class="vidTagsBegin-<?=$video['id']?>">
                                    <?php
                                    $tags = preg_split('/\s+/', trim($video['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                                    $visible_tags = array_slice($tags, 0, 5);
                                    $hidden_tags = array_slice($tags, 5);

                                    foreach ($visible_tags as $tag):
                                      $tag = trim($tag);
                                      if (!empty($tag)):
                                    ?>
                                      <a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a> :
                                    <?php
                                      endif;
                                    endforeach;

                                    if (!empty($hidden_tags)):
                                    ?>
                                    <span id="vidTagsRemain-<?=$video['id']?>" style="display: none;">
                                      <?php foreach ($hidden_tags as $tag):
                                        $tag = trim($tag);
                                        if (!empty($tag)):
                                      ?><a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a> : <?php
                                        endif;
                                      endforeach;
                                      ?></span>&nbsp;<span id="vidTagsMore-<?=$video['id']?>" class="smallText">(<a href="#" class="eLink" onclick="showInline('vidTagsRemain-<?=$video['id']?>'); hideInline('vidTagsMore-<?=$video['id']?>'); showInline('vidTagsLess-<?=$video['id']?>'); return false;">ещё</a>)</span><span id="vidTagsLess-<?=$video['id']?>" class="smallText" style="display: none;">(<a href="#" class="eLink" onclick="hideInline('vidTagsRemain-<?=$video['id']?>'); hideInline('vidTagsLess-<?=$video['id']?>'); showInline('vidTagsMore-<?=$video['id']?>'); return false;">меньше</a>)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="moduleEntryDetails">
                          Добавлено: <?= time_ago(strtotime($video['time'])) ?> от <a href="channel.php?user=<?= htmlspecialchars($video['user']) ?>" style="color:#0033cc; text-decoration:underline;"><?= htmlspecialchars($video['user']) ?></a>
                        </div>
                        <div class="moduleEntryDetails">
                          Время: <?=get_video_duration_fast($video['file'], $video['id'], $video['public_id'] ?? '')?> | Просмотров: <?= intval($video['views']) ?> | Комментариев: <?= intval($comments_count) ?>
                        </div>
                        <?= favourites_render_avg_stars_html($ra, $rc) ?>
                        </div>
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
      <?= channel_sidebar_nav_html($user, 'favorites', [
          'public' => (int)$total,
          'private' => $is_own ? (int)$private_count : 0,
          'fav' => (int)$fav_total,
          'friends' => (int)$fr_count,
      ]) ?>
      <?php if ($is_own && !$from_channel): ?>
      <div style="font-weight: bold; color: #333; margin: 0px 0px 5px 0px;">Любимые теги:</div>
      <?php if (!empty($my_tags)): ?>
      <?php foreach ($my_tags as $rt): ?>
      <div style="padding: 0px 0px 4px 0px; color: #999;">&raquo; <a href="results.php?search_type=tag&amp;search_query=<?=urlencode((string)$rt['tag'])?>"><?=htmlspecialchars((string)$rt['tag'])?></a></div>
      <?php endforeach; ?>
      <?php endif; ?>
      <?php endif; ?>
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