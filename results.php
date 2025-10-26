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

$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$search_type = isset($_GET['search_type']) ? trim($_GET['search_type']) : '';

if (empty($search_query)) {
    header('Location: index.php');
    exit;
}

$videos = array();
if ($search_query) {
    try {
        $db = new PDO('sqlite:retroshow.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $search_term = '%' . $search_query . '%';
        
        $stmt = $db->prepare("SELECT * FROM videos WHERE (private = 0 OR private IS NULL) ORDER BY id DESC");
        $stmt->execute();
        $all_public = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $needle = mb_strtolower($search_query, 'UTF-8');
        $videos = [];
        foreach ($all_public as $row) {
            $title = isset($row['title']) ? $row['title'] : '';
            $desc  = isset($row['description']) ? $row['description'] : '';
            $userf = isset($row['user']) ? $row['user'] : '';
            $tags  = isset($row['tags']) ? $row['tags'] : '';

            if ($search_type === 'tag') {
                if ($tags !== '' && mb_stripos($tags, $needle, 0, 'UTF-8') !== false) {
                    $videos[] = $row;
                }
            } else {
                if (
                    ($title !== '' && mb_stripos($title, $needle, 0, 'UTF-8') !== false) ||
                    ($desc  !== '' && mb_stripos($desc,  $needle, 0, 'UTF-8') !== false) ||
                    ($userf !== '' && mb_stripos($userf, $needle, 0, 'UTF-8') !== false) ||
                    ($tags  !== '' && mb_stripos($tags,  $needle, 0, 'UTF-8') !== false)
                ) {
                    $videos[] = $row;
                }
            }
        }

        
    } catch (PDOException $e) {
        echo "<div class='errorBox'>Ошибка базы данных: " . htmlspecialchars($e->getMessage()) . ".</div>";
    }
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total = count($videos);
$total_pages = ceil($total / $per_page);
$offset = ($page - 1) * $per_page;
$paged_videos = array_slice($videos, $offset, $per_page);

showHeader('Результаты поиска: ' . htmlspecialchars($search_query));
?>

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
  <tr valign="top">
    <td style="padding-right: 15px;">
             <table width="595" align="center" cellpadding="0" cellspacing="0" border="0">
         <tr>
           <td><img src="img/box_login_tl.gif" width="5" height="5"></td>
           <td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
           <td><img src="img/box_login_tr.gif" width="5" height="5"></td>
         </tr>
         <tr>
           <td><img src="img/pixel.gif" width="5" height="1"></td>
           <td style="padding: 0px 0px 0px 0px;">
             <div class="headerRCBox">
                 <b class="rch">
                     <b class="rch1"><b></b></b>
                     <b class="rch2"><b></b></b>
                     <b class="rch3"></b>
                     <b class="rch4"></b>
                     <b class="rch5"></b>
                 </b>
                 <div class="content">
                     <div class="headerTitleRight">
                         Показано
                         <b><?=($page-1)*$per_page+1?></b>-<b><?=min($page*$per_page, $total)?></b> из <b><?=$total?></b>
                     </div>
                     <div class="headerTitle">
                         Результаты <span class="normalText">поиска по запросу</span>
                         '<?=htmlspecialchars($search_query)?>'
                     </div>
                 </div>
             </div>
            
            <?php if (empty($videos)): ?>
                <div style="background-color:#FFFFFF;padding: 6px; ">
			Не найдено видео по запросу '<?=htmlspecialchars($search_query)?>'.
		</div>
            <?php else: ?>
              
              <?php foreach ($paged_videos as $video): 
                    $desc = htmlspecialchars($video['description']);
                    $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                    $desc_id = 'desc_' . $video['id'];
                    $desc_full = nl2br($desc);
              ?>
                <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                  <table width="565" cellpadding="0" cellspacing="0" border="0">
                    <tr valign="top">
                      <td width="120"><a href="video.php?id=<?=intval($video['id'])?>"><img src="<?=htmlspecialchars($video['preview'])?>" class="moduleEntryThumb" width="120" height="90" style="border:1px solid #888;"></a></td>
                      <td width="100%" style="padding-left:8px;">
                        <div style="font-size:15px; font-weight:bold;"><a href="video.php?id=<?=intval($video['id'])?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($video['title'])?></a></div>
                        <div style="font-size:12px; color:#222; font-weight:bold; margin:2px 0 2px 0;"><?=get_video_duration($video['file'], $video['id'])?></div>
                        <span id="<?= $desc_id ?>-short" style="font-size:12px; color:#222; margin:2px 0 2px 0;">
                          <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                        </span>
                        <span id="<?= $desc_id ?>-full" style="display:none; font-size:12px; color:#222; margin:2px 0 2px 0;">
                          <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                        </span>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Добавлено:</span> <?= time_ago(strtotime($video['time'])) ?></div>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Автор:</span> <a href="channel.php?user=<?= htmlspecialchars($video['user']) ?>" style="color:#0033cc; text-decoration:underline;"><b><?= htmlspecialchars($video['user']) ?></b></a></div>
                        <div style="font-size:11px; margin:2px 0 2px 0;"><span style="color:#888;">Просмотров:</span> <?= intval($video['views']) ?></div>
                      </td>
                    </tr>
                  </table>
                </div>
              <?php endforeach; ?>
              
                                                           <?php if ($total_pages > 1): ?>
                <div class="pagingDiv" style="background: #CCC; margin: 0px 0 0px 0; padding: 5px 0px; font-size: 13px; color: #333; font-weight: bold; text-align: right;">
                    Стр.
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    $search_param = 'search_query='.urlencode($search_query).'&';
                    if ($search_type) {
                        $search_param .= 'search_type='.urlencode($search_type).'&';
                    }
                    
                    if ($start_page > 1) {
                        echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?'.$search_param.'page=1" style="color: #03C; text-decoration: underline;">1</a></span>';
                        if ($start_page > 2) echo ' ... ';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="pagerCurrent" style="color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer;">'.$i.'</span>';
                        } else {
                            echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?'.$search_param.'page='.$i.'" style="color: #03C; text-decoration: underline;">'.$i.'</a></span>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo ' ... ';
                        echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?'.$search_param.'page='.$total_pages.'" style="color: #03C; text-decoration: underline;">'.$total_pages.'</a></span>';
                    }
                    
                    if ($page < $total_pages) {
                        echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?'.$search_param.'page='.($page + 1).'" style="color: #03C; text-decoration: underline;">Далее</a></span>';
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
