<?php 
include("init.php");

require_once 'duration_helper.php';

function get_video_duration($file, $id) {
    return get_video_duration_fast($file, $id);
}

function time_ago($time) {
    $diff = time() - $time;
    if ($diff < 60) return 'только что';
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

function rus_date($format, $time) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $d = date('j', $time);
    $m = $months[intval(date('n', $time))];
    $y = date('Y', $time);
    return "$d $m $y";
}

if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_GET['id'])) {
    header('Location: index.php?error=video_not_found');
    exit;
}

$id_param = $_GET['id'];
$video = null;
$id = null;

if (preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id_param)) {
    $stmt = $db->prepare("SELECT * FROM videos WHERE public_id = ?");
    $stmt->execute([$id_param]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($video) {
        $id = intval($video['id']);
    }
}

if (!$video) {
    $id = intval($id_param);
    $stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$video || !$id) {
    header('Location: index.php?error=video_not_found');
    exit;
}

@include_once __DIR__ . '/duration_helper.php';
$flash_len = 0;
if (function_exists('get_video_duration_fast')) {
    $dur_str = get_video_duration_fast($video['file'], $id);
    if ($dur_str && $dur_str !== '--:--') {
        $parts = explode(':', $dur_str);
        if (count($parts) === 3) {
            $flash_len = intval($parts[0]) * 3600 + intval($parts[1]) * 60 + intval($parts[2]);
        } elseif (count($parts) === 2) {
            $flash_len = intval($parts[0]) * 60 + intval($parts[1]);
        }
    }
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

$user_player_type = 'auto';
if ($user) {
    $stmt_user = $db->prepare('SELECT player_type FROM users WHERE login = ?');
    $stmt_user->execute([$user]);
    $user_player_type = $stmt_user->fetchColumn() ?: 'auto';
}

if (isset($_GET['download']) && $_GET['download'] == 'avi') {
    $temp_avi = 'uploads/temp_' . $id . '.avi';
    
    $ffmpeg = "ffmpeg -i " . escapeshellarg($video['file']) . 
             " -c:v msmpeg4v2 " . 
             " -c:a libmp3lame -b:a 192k " .
             " -vf \"scale=320:240\" " .
             " -r 15 " .
             " -b:v 800k " .
             escapeshellarg($temp_avi);
    
    exec($ffmpeg, $output, $return_var);
    
    if ($return_var !== 0) {
        die("Ошибка при конвертации в AVI. Убедитесь, что FFmpeg установлен.");
    }
    
    if (!file_exists($temp_avi) || filesize($temp_avi) == 0) {
        die("Ошибка: файл AVI не создан или пуст.");
    }
    
    if (ob_get_level()) ob_end_clean();
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="video_' . $id . '.avi"');
    header('Content-Length: ' . filesize($temp_avi));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    $handle = fopen($temp_avi, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
    
    unlink($temp_avi);
    exit;
}

function get_video_rating_stats($db, $video_id) {
    $row = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id))->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0;
    return [$count, $avg];
}
function get_user_current_rating($db, $video_id, $user, $ip) {
    if ($user) {
        $st = $db->prepare("SELECT rating FROM ratings WHERE video_id = ? AND user = ? ORDER BY rated_at DESC LIMIT 1");
        $st->execute([$video_id, $user]);
        $r = $st->fetchColumn();
        if ($r !== false) return intval($r);
    }
    $st = $db->prepare("SELECT rating FROM ratings WHERE video_id = ? AND ip = ? ORDER BY rated_at DESC LIMIT 1");
    $st->execute([$video_id, $ip]);
    $r = $st->fetchColumn();
    return $r !== false ? intval($r) : 0;
}
function render_rating_inner_html($video_id, $ratings_count, $avg_rating, $initial_rating = 0) {
    ob_start();
    ?>
						<div id="ratingMessage" class="label" style="white-space:nowrap;">Оцените&nbsp;видео</div>
		          		<form style="display:none;" name="ratingForm" action="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $video_id)?>&ajax=rating" method="POST">
	<input type="hidden" name="action_add_rating" value="1">
	<input type="hidden" name="video_id" value="<?=intval($video_id)?>">
	<input type="hidden" name="rating" id="rating" value="">
</form>

	<div>
		<nobr>
			<a href="#" onclick="ratingComponent.setStars(1); return false;" onmouseover="ratingComponent.showStars(1);" onmouseout="ratingComponent.clearStars();"><img src="img/star_smn_bg.gif" id="star_1" class="rating" style="border: 0px"></a>
			<a href="#" onclick="ratingComponent.setStars(2); return false;" onmouseover="ratingComponent.showStars(2);" onmouseout="ratingComponent.clearStars();"><img src="img/star_smn_bg.gif" id="star_2" class="rating" style="border: 0px"></a>
			<a href="#" onclick="ratingComponent.setStars(3); return false;" onmouseover="ratingComponent.showStars(3);" onmouseout="ratingComponent.clearStars();"><img src="img/star_smn_bg.gif" id="star_3" class="rating" style="border: 0px"></a>
			<a href="#" onclick="ratingComponent.setStars(4); return false;" onmouseover="ratingComponent.showStars(4);" onmouseout="ratingComponent.clearStars();"><img src="img/star_smn_bg.gif" id="star_4" class="rating" style="border: 0px"></a>
			<a href="#" onclick="ratingComponent.setStars(5); return false;" onmouseover="ratingComponent.showStars(5);" onmouseout="ratingComponent.clearStars();"><img src="img/star_smn_bg.gif" id="star_5" class="rating" style="border: 0px"></a>
		</nobr>
		<div class="rating" style="white-space:nowrap;"><?=intval($ratings_count)?> оценок</div>
	</div>
	<script type="text/javascript">
		if (typeof UTRating !== 'undefined') {
			var ratingComponent = new UTRating('ratingDiv', 5, 'ratingComponent', 'ratingForm');
			ratingComponent.starCount = <?=intval($initial_rating)?>;
			ratingComponent.drawStars(<?=intval($initial_rating)?>);
		}
	</script>
	<?php
    return ob_get_clean();
}

 function render_rating_inner_html_disabled($db, $video_id, $ratings_count, $avg_rating, $user, $ip) {
     ob_start();
     $current = get_user_current_rating($db, $video_id, $user, $ip);
     if ($current < 1 || $current > 5) { $current = 0; }
     ?>
 						<div id="ratingMessage" class="label" style="white-space:nowrap;">Спасибо за оценку!</div>
 	<div>
 		<nobr>
 			<img src="img/star_smn<?=($current>=1?'':'_bg')?>.gif" id="star_1" class="rating" style="border:0px" alt="1">
 			<img src="img/star_smn<?=($current>=2?'':'_bg')?>.gif" id="star_2" class="rating" style="border:0px" alt="2">
 			<img src="img/star_smn<?=($current>=3?'':'_bg')?>.gif" id="star_3" class="rating" style="border:0px" alt="3">
 			<img src="img/star_smn<?=($current>=4?'':'_bg')?>.gif" id="star_4" class="rating" style="border:0px" alt="4">
 			<img src="img/star_smn<?=($current>=5?'':'_bg')?>.gif" id="star_5" class="rating" style="border:0px" alt="5">
 		</nobr>
		<div class="rating"><?=intval($ratings_count)?> оценок</div>
	</div>
	<?php
    return ob_get_clean();
}

 function render_rating_inner_html_guest($ratings_count, $avg_rating) {
     ob_start();
     $avg = floatval($avg_rating);
     $remaining = $avg;
     $stars = [];
     for ($i = 0; $i < 5; $i++) {
         if ($remaining >= 0.75) {
             $stars[] = 'full';
         } elseif ($remaining >= 0.25) {
             $stars[] = 'half';
         } else {
             $stars[] = 'empty';
         }
         $remaining = max(0.0, $remaining - 1.0);
     }
     ?>
 						<div id="ratingMessage" class="label">Оцените видео</div>
 	<div>
 		<nobr>
 			<img src="img/star_smn<?=($stars[0]==='full'?'':($stars[0]==='half'?'_half':'_bg'))?>.gif" class="rating" style="border:0px" alt="1">
 			<img src="img/star_smn<?=($stars[1]==='full'?'':($stars[1]==='half'?'_half':'_bg'))?>.gif" class="rating" style="border:0px" alt="2">
 			<img src="img/star_smn<?=($stars[2]==='full'?'':($stars[2]==='half'?'_half':'_bg'))?>.gif" class="rating" style="border:0px" alt="3">
 			<img src="img/star_smn<?=($stars[3]==='full'?'':($stars[3]==='half'?'_half':'_bg'))?>.gif" class="rating" style="border:0px" alt="4">
 			<img src="img/star_smn<?=($stars[4]==='full'?'':($stars[4]==='half'?'_half':'_bg'))?>.gif" class="rating" style="border:0px" alt="5">
 		</nobr>
 		<div class="rating"><?=intval($ratings_count)?> оценок</div>
 	</div>
 	<?php
     return ob_get_clean();
 }

if (isset($_GET['ajax']) && $_GET['ajax'] === 'rating' && isset($_POST['action_add_rating'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $r = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    if ($r < 1 || $r > 5) $r = 0;
    if ($r > 0) {
        if ($user) {
            $upd = $db->prepare("UPDATE ratings SET rating = ?, rated_at = ? WHERE video_id = ? AND user = ?");
            $upd->execute([$r, time(), $id, $user]);
            if ($upd->rowCount() == 0) {
                $db->prepare("INSERT INTO ratings (video_id, user, ip, rating, rated_at) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$id, $user, $ip, $r, time()]);
            }
        } else {
            $upd = $db->prepare("UPDATE ratings SET rating = ?, rated_at = ? WHERE video_id = ? AND ip = ?");
            $upd->execute([$r, time(), $id, $ip]);
            if ($upd->rowCount() == 0) {
                $db->prepare("INSERT INTO ratings (video_id, user, ip, rating, rated_at) VALUES (?, ?, ?, ?, ?)")
                   ->execute([$id, null, $ip, $r, time()]);
            }
        }
    }
    list($ratings_count, $avg_rating) = get_video_rating_stats($db, $id);
    if (!$user) {
        echo render_rating_inner_html_guest($ratings_count, $avg_rating);
        exit;
    }
    echo render_rating_inner_html_disabled($db, $id, $ratings_count, $avg_rating, $user, $_SERVER['REMOTE_ADDR']);
    exit;
}

$friends_dir = __DIR__ . '/friends';
if (!is_dir($friends_dir)) mkdir($friends_dir);
if ($user && $video['user'] && $user !== $video['user']) {
    $friends_file = $friends_dir . '/' . urlencode($user) . '.txt';
    $friends_list = file_exists($friends_file) ? file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
    $is_friend = in_array($video['user'], $friends_list);
    if (isset($_GET['friend_add']) && $_GET['friend_add'] === $video['user']) {
        if (!$is_friend) {
            $friends_list[] = $video['user'];
            file_put_contents($friends_file, implode("\n", $friends_list));
        }
        header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id));
        exit;
    }
    if (isset($_GET['friend_del']) && $_GET['friend_del'] === $video['user']) {
        if ($is_friend) {
            $friends_list = array_diff($friends_list, [$video['user']]);
            file_put_contents($friends_file, implode("\n", $friends_list));
        }
        header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id));
        exit;
    }
}

$is_private = !empty($video['private']);
if ($is_private) {
    $rec_stmt = $db->prepare("SELECT * FROM videos WHERE id != ? AND private = 0 ORDER BY id DESC LIMIT 5");
    $rec_stmt->execute([$id]);
    $recommended = $rec_stmt->fetchAll();
} else {
    $rec_stmt = $db->prepare("SELECT * FROM videos WHERE id != ? AND private = 0 ORDER BY id DESC LIMIT 5");
    $rec_stmt->execute([$id]);
    $recommended = $rec_stmt->fetchAll();

    $user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = time();
    $timeout = 6 * 3600;

    if ($user) {
        $check_stmt = $db->prepare("SELECT viewed_at FROM video_views WHERE video_id = ? AND user = ? ORDER BY viewed_at DESC LIMIT 1");
        $check_stmt->execute([$id, $user]);
        $last = $check_stmt->fetchColumn();
    } else {
        $check_stmt = $db->prepare("SELECT viewed_at FROM video_views WHERE video_id = ? AND ip = ? ORDER BY viewed_at DESC LIMIT 1");
        $check_stmt->execute([$id, $ip]);
        $last = $check_stmt->fetchColumn();
    }
    if (!$last || $now - $last > $timeout) {
        $db->prepare("UPDATE videos SET views = views + 1 WHERE id = ?")->execute([$id]);
        $db->prepare("INSERT INTO video_views (video_id, user, ip, viewed_at) VALUES (?, ?, ?, ?)")
            ->execute([$id, $user, $ip, $now]);
        $video['views'] = ($video['views'] ?? 0) + 1;
    }
}

try {
    $tags_str = isset($video['tags']) ? trim($video['tags']) : '';
    if ($tags_str !== '') {
        $tags_arr = preg_split('/\s+/', $tags_str);
        $tags_arr = array_filter(array_map('trim', $tags_arr));
        $tags_arr = array_values(array_unique($tags_arr));
        if (count($tags_arr) > 0) {
            $tags_arr = array_slice($tags_arr, 0, 5);
            $clauses = [];
            $params = [$id];
            foreach ($tags_arr as $t) {
                $clauses[] = 'tags LIKE ?';
                $params[] = '%' . $t . '%';
            }
            $sql = 'SELECT * FROM videos WHERE id != ? AND private = 0 AND (' . implode(' OR ', $clauses) . ') ORDER BY id DESC LIMIT 5';
            $stmtSim = $db->prepare($sql);
            $stmtSim->execute($params);
            $byTags = $stmtSim->fetchAll();
            if (!empty($byTags)) {
                $chosen = $byTags;
                $need = 5 - count($chosen);
                if ($need > 0) {
                    $excludeIds = array_map(function($v){ return intval($v['id']); }, $chosen);
                    $excludeIds[] = intval($id);
                    $ph = implode(',', array_fill(0, count($excludeIds), '?'));
                    $sqlMore = 'SELECT * FROM videos WHERE private = 0 AND id NOT IN (' . $ph . ') ORDER BY id DESC LIMIT ' . intval($need);
                    $stmtMore = $db->prepare($sqlMore);
                    $stmtMore->execute($excludeIds);
                    $more = $stmtMore->fetchAll();
                    if (!empty($more)) {
                        $chosen = array_merge($chosen, $more);
                    }
                }
                $recommended = $chosen;
            }
        }
    }
} catch (Exception $e) {
}

$comment_error = '';
$comments_file = __DIR__ . '/comments/' . $id . '.txt';
$comments_count = (file_exists($comments_file)) ? count(file($comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
if (isset($_GET['del_comment']) && $user) {
    $del_id = $_GET['del_comment'];
    if (file_exists($comments_file)) {
        $lines = file($comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $comments_check = [];
        foreach ($lines as $k => $l) {
            $parts = explode('|', $l, 4);
            if (count($parts) >= 3 && ($parts[0] ?? '') !== 'REMOVED') {
                $comments_check[$k] = ['id' => $k, 'user' => $parts[1], 'parent_id' => $parts[3] ?? ''];
            }
        }
        $get_descendants = function($pid) use ($comments_check, &$get_descendants) {
            $ids = [ $pid ];
            foreach ($comments_check as $c) {
                if ((string)$c['parent_id'] === (string)$pid) {
                    $ids = array_merge($ids, $get_descendants($c['id']));
                }
            }
            return $ids;
        };
        if (isset($comments_check[$del_id]) && $comments_check[$del_id]['user'] === $user) {
            $to_delete = $get_descendants($del_id);
            $new_lines = [];
            foreach ($lines as $idx => $l) {
                $parts = explode('|', $l, 4);
                if (count($parts) < 3 || ($parts[0] ?? '') === 'REMOVED') continue;
                if (in_array($idx, $to_delete)) continue;
                if (isset($parts[3]) && in_array($parts[3], $to_delete)) $parts[3] = '';
                $new_lines[] = implode('|', $parts);
            }
            file_put_contents($comments_file, implode("\n", $new_lines) . (count($new_lines) ? "\n" : ""), LOCK_EX);
        }
    }
    header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id) . "#comments");
    exit;
}
if (isset($_POST['add_comment'])) {
    if (!$user) {
        $comment_error = 'Только для зарегистрированных пользователей!';
    } else {
        $comment_text = trim($_POST['comment_text'] ?? '');
        $parent_id = isset($_POST['reply_parent_id']) ? $_POST['reply_parent_id'] : '';
        if ($comment_text == '') {
            $comment_error = 'Комментарий не может быть пустым!';
        } elseif (mb_strlen($comment_text) > 500) {
            $comment_error = 'Комментарий слишком длинный (макс. 500 символов)!';
        } else {
            $comments_dir = __DIR__ . '/comments';
            if (!is_dir($comments_dir)) {
                mkdir($comments_dir, 0755, true);
            }
            $line = time() . '|' . str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $user) . '|' . str_replace(['|', "\n", "\r"], [' ', ' ', ' '], $comment_text) . '|' . $parent_id . "\n";
            file_put_contents($comments_file, $line, FILE_APPEND | LOCK_EX);
            header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id));
            exit;
        }
    }
}
$comments = [];
if (file_exists($comments_file)) {
    $lines = file($comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $k => $l) {
        $parts = explode('|', $l, 4);
        if (count($parts) >= 3) {
            $comments[$k] = [
                'id' => $k,
                'time' => intval($parts[0]),
                'user' => $parts[1],
                'text' => $parts[2],
                'parent_id' => isset($parts[3]) ? $parts[3] : ''
            ];
        }
    }
}
$comment_tree = build_comment_tree($comments);
function build_comment_tree($comments, $parent_id = '', &$max_child_time = null) {
    $tree = [];
    foreach ($comments as $c) {
        if ($c['parent_id'] === $parent_id) {
            $child_max_time = $c['time'];
            $children = build_comment_tree($comments, (string)$c['id'], $child_max_time);
            $c['children'] = $children;
			
            $c['max_time'] = $child_max_time;
            if ($child_max_time > $c['time']) $c['max_time'] = $child_max_time;
            else $c['max_time'] = $c['time'];
            $tree[] = $c;
        }
    }
	
    if ($parent_id === '') {
        usort($tree, function($a, $b) { return $b['max_time'] - $a['max_time']; });
    } else {
        usort($tree, function($a, $b) { return $a['time'] - $b['time']; });
    }
	
    if ($max_child_time !== null && count($tree)) {
        foreach ($tree as $c) {
            if ($c['max_time'] > $max_child_time) $max_child_time = $c['max_time'];
        }
    }
    return $tree;
}
function render_comments($tree, $level = 0) {
    global $user, $video, $id;
    $max_level = 5;
    foreach ($tree as $c) {
        $ml = ($level > 0 ? 'margin-left:'.(min($level, $max_level)*30).'px;' : '');
        echo '<div>';
        echo '<div style="background:#EEEEEE; padding:2px 6px;'.$ml.'">';
        echo '<a href="channel.php?user='.urlencode($c['user']).'" style="color:#0033cc;text-decoration:underline;font-size:13px;"><b>'.htmlspecialchars($c['user']).'</b></a> ';
        echo '<span style="color:#888;font-size:11px;">('.time_ago($c['time']).')</span>';
        echo '</div>';
        echo '<div style="font-size:13px;color:#222;padding:4px 6px 0 6px;'.$ml.' word-break:break-all;">'.nl2br(htmlspecialchars($c['text'])).'</div>';
        echo '<div style="text-align:right;font-size:11px;color:#0033cc;padding:0 6px 2px 0;'.$ml.'">';
        if ($user) {
            echo '<a href="#" class="reply-link" data-id="'.$c['id'].'" style="color:#0033cc;text-decoration:underline;font-size:11px;">(ответить)</a>';
            if ($user === $c['user']) {
                $vid = urlencode($video['public_id'] ?? $id);
                echo ' <a href="video.php?id='.$vid.'&del_comment='.$c['id'].'#comments" onclick="return confirm(\'Удалить комментарий?\');" style="color:#0033cc;text-decoration:underline;font-size:11px;">(удалить)</a>';
            }
        } else {
          echo '<a href="#" onclick="alert(\'Только для зарегистрированных пользователей!\'); return false;" data-id="'.$c['id'].'" style="color:#0033cc;text-decoration:underline;font-size:11px;">(ответить)</a>';
        }
        echo '</div>';
        echo '<div class="reply-form" id="replyform-'.$c['id'].'" style="display:none;margin-left:30px;"></div>';
        if (!empty($c['children'])) render_comments($c['children'], $level+1);
        echo '</div>';
    }
}

$favourites_dir = __DIR__ . '/favourites';
if (!is_dir($favourites_dir)) mkdir($favourites_dir);
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$fav_file = $user ? "$favourites_dir/" . urlencode($user) . ".txt" : null;
$fav_list = ($fav_file && file_exists($fav_file)) ? file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
$is_fav = $user && in_array($id, $fav_list);

if ($user && isset($_GET['fav_add']) && $_GET['fav_add'] == 1) {
    if (!$is_fav) {
        $fav_list[] = $id;
        file_put_contents($fav_file, implode("\n", $fav_list));
    }
    header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id));
    exit;
}
if ($user && isset($_GET['fav_del']) && $_GET['fav_del'] == 1) {
    if ($is_fav) {
        $fav_list = array_diff($fav_list, [$id]);
        file_put_contents($fav_file, implode("\n", $fav_list));
    }
    header("Location: video.php?id=" . urlencode($video['public_id'] ?? $id));
    exit;
}
$fav_count = 0;
foreach (glob("$favourites_dir/*.txt") as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (in_array($id, $lines)) $fav_count++;
}

list($ratings_count, $avg_rating) = get_video_rating_stats($db, $id);
$ip = $_SERVER['REMOTE_ADDR'];
$current_rating = get_user_current_rating($db, $id, $user, $ip);
?>

<html><head><title><?=htmlspecialchars($video['title'])?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="stylesheet" href="img/styles.css" type="text/css">
<link rel="stylesheet" href="img/base.css" type="text/css">
<link rel="stylesheet" href="img/watch.css" type="text/css">
<script type="text/javascript" src="img/ui_ets.js"></script>
<script type="text/javascript" src="img/AJAX.js"></script>
<script type="text/javascript" src="img/components.js"></script>
<link href="img/styles.css" rel="stylesheet" type="text/css">
<link rel="alternate" type="application/rss+xml" title="Recently Added Videos" href="rss.hp">
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
#vidFacetsDiv { margin-bottom: 3px; }
#vidFacetsTable { width: 100%; }
#vidFacetsTable .label { font-weight: bold; color: #333; font-size: 11px; text-align: left; padding-left: 8px; padding-right: 2px; width: 35px; }
#vidFacetsTable .smallLabel { font-weight: bold; color: #333; font-size: 11px; text-align: left; padding-left: 8px; padding-right: 2px; width: 35px; }
#vidFacetsTable .tags { font-size: 11px; word-wrap: break-word; }
#vidFacetsTable .dg { color: #333; text-decoration: underline; }
#vidFacetsTable .dg:hover { color: #333; text-decoration: underline; }
#vidFacetsTable .smallText { font-size: 10px; }
#vidFacetsTable .eLink { color: #0033cc; text-decoration: none; }
#vidFacetsTable .eLink:hover { text-decoration: none; }
</style>
<!--[if lt IE 6]>
<style type="text/css">
#ratingMessage { display:none; }
</style>
<![endif]-->
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
<a href="account.php" onmouseover="showDropdownShow();">Мой аккаунт</a><a href="#" onclick="arrowClicked();return false;" onmouseover="document.arrowImg.src='/img/icon_menarrwdrpdwn_mouseover3_14x14.gif'" onmouseout="document.arrowImg.src='/img/icon_menarrwdrpdwn_regular_14x14.gif'"><img name="arrowImg" src="img/icon_menarrwdrpdwn_regular_14x14.gif" align="texttop" border="0" style="margin-left: 2px;"></a>

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
			<?php
			$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
			$tabs = [
				['index.php', 'Главная', 'index.php'],
				['channel.php,favourites.php,friends.php,video.php', 'Смотреть&nbsp;видео', 'channel.php'],
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
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'channel.php?user=' . urlencode($_SESSION['user']) . '&tab=videos'; } else { echo 'login.php'; } ?>">Мои видео</a></td>
				<td style="padding: 0px 10px 0px 10px;">|</td>
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'channel.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Мой канал</a></td>
				<td style="padding: 0px 10px 0px 10px;">|</td>
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'favourites.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Избранное</a></td>
				<td style="padding: 0px 10px 0px 10px;">|</td>
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'friends.php?user=' . urlencode($_SESSION['user']); } else { echo 'login.php'; } ?>">Мои друзья</a></td>
				<td style="padding: 0px 10px 0px 10px;">|</td>
				<td style="  "><a href="<?php if (isset($_SESSION['user'])) { echo 'account.php'; } else { echo 'login.php'; } ?>">Настройки</a></td>
			</tr>
		</tbody></table>
			
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

<table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
  <td width="435">
    <div style="font-size: 20px; font-weight: bold; margin-bottom: 5px;"><?=htmlspecialchars($video['title'])?></div>
    
    <link rel="stylesheet" href="viewfinder/player.css">
    <div style="text-align: center; margin-bottom: 8px;">
        <div id="flashPlayerBox" style="display:none; font-size:14px; font-weight: bold;">
            <embed src="player.swf?video_id=<?=$id?>&l=<?=$flash_len?>&c=14&s=i5nkrobo60sub2rqflh31bapgg" width="425" height="350">
        </div>

        <div class="player" id="playerBox" style="margin: 0 0 0 0">
        <div class="mainContainer">
            <div class="playerScreen">
                <div class="playbackArea">
                    <div class="videoContainer">
                        <video class="videoObject" id="video" autoplay muted>
                            <source src="<?=htmlspecialchars($video['file'])?>">
                         </video>
                    </div>
                </div>
            </div>
            <div class="controlBackground">
                <div class="controlContainer">
                    <div class="lBtnContainer">
                        <div class="button" id="playButton">
                            <img src="viewfinder/resource/play.png" id="playIcon">
                            <img src="viewfinder/resource/pause.png" class="hidden" id="pauseIcon">
                        </div>
                    </div>
                    <div class="centerContainer">
                        <div class="seekbarElementContainer">
                            <progress class="seekProgress" id="seekProgress" value="0" min="0" max="10"></progress>
                        </div>
                        <div class="seekbarElementContainer">
                            <input class="seekHandle" id="seekHandle" value="0" min="0" step="1" type="range" max="10">
                        </div>
                    </div>
                    <div class="rBtnContainer">
                        <div class="button" id="muteButton">
                            <img src="viewfinder/resource/unmute.png" id="muteIcon">
                            <img src="viewfinder/resource/mute.png" class="hidden" id="unmuteIcon">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="aboutBox hidden" id="aboutBox">
            <div class="aboutBoxContent">
            <div class="aboutHeader">Viewfinder</div>
            <div class="aboutBody">
                <div>Version 1.0<br>
                <br>
                2005-Style HTML5 player<br>
                <br>
                Created by Purpleblaze
            </div>
            </div>
            <button id="aboutCloseBtn">Close</button>
            </div>
        </div>
        <div class="contextMenu hidden" id="playerContextMenu" style="display: none;">
            <div class="contextItem" id="contextMute">
                <span>Mute</span>
                <div id="muteTick" class="tick hidden">    
                </div>
            </div>
            <div class="contextItem" id="contextLoop">
                <span>Loop</span>
                <div id="loopTick" class="tick hidden">
                </div>
            </div>
            <div class="contextSeparator"></div>
            <div class="contextItem" id="contextAbout">About</div>
        </div>
        </div>
    </div>
    
    <script src="viewfinder/player.js"></script>
    <script>
    (function(){
        function hasFlash(){
            var has = false;
            try {
                has = Boolean(new ActiveXObject('ShockwaveFlash.ShockwaveFlash'));
            } catch(e) {
                has = navigator.plugins && navigator.plugins['Shockwave Flash'] ? true : false;
            }
            return has;
        }
        var userPlayerType = '<?=$user_player_type?>';
        var flashOk = hasFlash();
        var flashBox = document.getElementById('flashPlayerBox');
        var html5Box = document.getElementById('playerBox');
        
        if (userPlayerType === 'flash') {
            if (html5Box) html5Box.style.display = 'none';
            if (flashBox) flashBox.style.display = 'block';
        } else if (userPlayerType === 'html5') {
            if (html5Box) html5Box.style.display = '';
            if (flashBox) flashBox.style.display = 'none';
        } else {
            if (flashOk) {
                if (html5Box) html5Box.style.display = 'none';
                if (flashBox) flashBox.style.display = 'block';
            } else {
                if (html5Box) html5Box.style.display = '';
                if (flashBox) flashBox.style.display = 'none';
            }
        }
    })();
    </script>
    </div>

    <div id="actionsAndStatsDiv" class="contentBox" style="border:1px solid #ccc; background:#fff; margin-bottom:10px; overflow:hidden; height:1%;">
		<div id="ratingDivWrapper" style="float:left; width:32%; padding:4px;">
			<div id="ratingDiv">
<?php
echo $user ? render_rating_inner_html($id, $ratings_count, $avg_rating, $current_rating) : render_rating_inner_html_guest($ratings_count, $avg_rating);
?>
			</div>
		</div>
		<div id="actionsDiv" style="float:left; width:32%; padding:4px;">
			<div class="actionRow" style="font-size:12px;">
        <?php if ($user): ?>
<?php if ($is_fav): ?>
  <a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&fav_del=1" style="color:#0033cc; text-decoration:none;"><img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle" border="0"> Убрать из избранного</a>
<?php else: ?>
  <a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&fav_add=1" style="color:#0033cc; text-decoration:none;"><img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle" border="0"> Добавить в избранное</a>
<?php endif; ?>
<br>
<?php else: ?>
<img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle"> <a href="login.php" style="color:#0033cc; text-decoration:none;">Войти, чтобы добавить в избранное</a>
<br>
<?php endif; ?>
<a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&download=avi" style="color:#0033cc; text-decoration:none; font-size:12px;"><img src="img/web_w_icon.gif" border="0" width="19" height="17" align="absmiddle"> Скачать видео в AVI</a> (или <a href="get_video.php?video_id=<?=intval($id)?>" style="color:#0033cc; text-decoration:none; font-size:12px;">MP4</a>)<br>
			</div>
		</div>
		<div id="statsDiv" style="float:left; width:28%; padding:4px; font-size:12px; color:#333;">
			<div class="statRow">
      <b>Просмотров:</b> <?=intval($video['views'])?><br>
      <b>Комментариев:</b> <?=$comments_count?><br>
      <b>Понравилось:</b> <?=$fav_count?> раз<br>
			</div>
		</div>
		<div style="clear:both;"></div>
	</div>
    <!--[if lt IE 6]>
    <div style="border:1px solid #ccc; background:#fff; margin:8px 0;">
      <table cellpadding="4" cellspacing="0" border="0" width="100%">
        <tr valign="top">
          <td width="33%">
            <div style="font-weight:bold; font-size:12px; color:#333;">Оцените видео</div>
            <div>
              <?php echo $user ? render_rating_inner_html($id, $ratings_count, $avg_rating, $current_rating)
                                : render_rating_inner_html_guest($ratings_count, $avg_rating); ?>
            </div>
          </td>
          <td width="34%">
            <div style="font-size:12px;">
            <?php if ($user): ?>
              <?php if ($is_fav): ?>
                <a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&fav_del=1" style="color:#0033cc; text-decoration:none;"><img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle" border="0"> Убрать из избранного</a>
              <?php else: ?>
                <a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&fav_add=1" style="color:#0033cc; text-decoration:none;"><img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle" border="0"> Добавить в избранное</a>
              <?php endif; ?>
              <br>
            <?php else: ?>
              <img src="img/fav_w_icon.gif" width="19" height="17" align="absmiddle"> <a href="login.php" style="color:#0033cc;">Войти, чтобы добавить в избранное</a><br>
            <?php endif; ?>
              <a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>&download=avi" style="color:#0033cc; text-decoration:none; font-size:12px;"><img src="img/web_w_icon.gif" border="0" width="19" height="17" align="absmiddle"> Скачать видео в AVI</a> (или <a href="get_video.php?video_id=<?=intval($id)?>" style="color:#0033cc; text-decoration:none; font-size:12px;">MP4</a>)
            </div>
          </td>
          <td width="33%" style="font-size:12px; color:#333;">
            <b>Просмотров:</b> <?=intval($video['views'])?><br>
            <b>Комментариев:</b> <?=$comments_count?><br>
            <b>Понравилось:</b> <?=$fav_count?> раз
          </td>
        </tr>
      </table>
    </div>
    <![endif]-->
	
    <a name="comments"></a>
    <table cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 5px;"><tr>
  <td><b><font style="margin: 0px; font-size: 15px;">Комментарии и ответы</font></b></td>
  <td align="right">
    <div style="padding-bottom: 2px;">
      <b><a href="#">Добавить видео-ответ</a></b>
    </div>
    <div>
      <b><a href="#" id="showCommentForm" onclick="return false;">Оставить текстовый комментарий</a></b>
    </div>
  </td>
</tr></table>
<div style="margin-bottom: 10px;">
<?php if ($user): ?>
  <div id="commentFormBlock" style="display:none;">
    <form method="post" action="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $id)?>" name="comment_formmain_comment2" id="comment_formmain_comment2" style="margin:0;">
      <input type="hidden" name="add_comment" value="1">
      <input type="hidden" name="form_id" value="comment_formmain_comment2">
      <input type="hidden" name="reply_parent_id" value="">
      <input type="hidden" name="comment_type" value="V">
      <textarea tabindex="2" name="comment_text" cols="55" rows="3" style="font-size: 13px; width: 98%;"></textarea><br>
      <input type="submit" name="add_comment_button" value="Добавить">
      <input type="button" name="discard_comment_button" value="Отмена" onclick="this.parentNode.parentNode.style.display='none';">
      <?php if ($comment_error): ?>
      <div style="color: #c00; font-size: 12px; padding: 3px 0; margin-top: 5px;"><?=htmlspecialchars($comment_error)?></div>
      <?php endif; ?>
    </form>
  </div>
<?php else: ?>
  <br><b><font style="margin: 0px; font-size: 15px;">Хотите оставить комментарий?</font></b><br>
  <span style="font-size: 12px;">Зарегистрируйтесь на RetroShow или <a href="login.php">войдите</a>, если у вас уже есть аккаунт.</span>
<?php endif; ?>
</div>
<?php if (count($comments) == 0): ?>
  Комментариев пока нет.
<?php else: ?>
  <?php render_comments($comment_tree); ?>
<?php endif; ?>
</div>
<script type="text/javascript">
function showInline(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'inline';
}
function hideInline(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
}
function showReplyForm(id) {
    var forms = document.getElementsByClassName('reply-form');
    for (var i=0; i<forms.length; i++) forms[i].style.display = 'none';
    var f = document.getElementById('replyform-'+id);
    if (f) {
        var orig = document.getElementById('commentFormBlock');
        if (!orig) return false;
        var html = orig.innerHTML
            .replace(/name=\"comment_formmain_comment2\"/g, 'name="reply_form_'+id+'"')
            .replace(/id=\"comment_formmain_comment2\"/g, 'id="reply_form_'+id+'"')
            .replace(/name=\"reply_parent_id\" value=\"\"/g, 'name="reply_parent_id" value="'+id+'"');
        f.innerHTML = html;
        f.style.display = '';
    }
    return false;
}
window.onload = function() {
    var btn = document.getElementById ? document.getElementById('showCommentForm') : document.all['showCommentForm'];
    if (btn) {
        btn.onclick = function() {
            var f = document.getElementById ? document.getElementById('commentFormBlock') : document.all['commentFormBlock'];
            if (f) {
                if (f.style.display == '' || f.style.display == 'none') {
                    f.style.display = 'block';
                } else {
                    f.style.display = 'none';
                }
            }
            return false;
        };
    }
    var links = document.getElementsByTagName('a');
    for (var i=0; i<links.length; i++) {
        if (links[i].className && links[i].className.indexOf('reply-link') !== -1) {
            links[i].onclick = function() { return showReplyForm(this.getAttribute('data-id')); }
        }
    }
};
</script>
  </td>
  <td width="355" style="padding-left: 10px;">
    <br><br>
    <div id="exploreDiv">
		<div class="headerRCBox">
	<b class="rch">
	<b class="rch1"><b></b></b>
	<b class="rch2"><b></b></b>
	<b class="rch3"></b>
	<b class="rch4"></b>
	<b class="rch5"></b>
	</b> <div class="content"><span class="headerTitleLite">О видео</span></div>
	</div>
    <?php
$desc = trim($video['description']);
$desc_short = mb_strlen($desc) > 50 ? mb_substr($desc, 0, 50) . '...' : $desc;
?>
<table width="100%" cellpadding="2" cellspacing="0" border="0" style="background: #fff; border: 1px solid #ccc; margin-bottom: 10px;">
  <tr valign="top">
    <td style="width:100%; font-size:11px; color:#333;">
      <div style="padding-left: 8px;">
      <div id="uploaderInfo" style="overflow:hidden; zoom:1;">
      <?php
      if ($video['user']) {
        if (!isset($is_friend)) {
          $friends_dir = __DIR__ . '/friends';
          if (!is_dir($friends_dir)) mkdir($friends_dir);
          $is_friend = false;
          if ($user) {
            $friends_file = $friends_dir . '/' . urlencode($user) . '.txt';
            $friends_list = file_exists($friends_file) ? file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
            $is_friend = in_array($video['user'], $friends_list);
          }
        }
        echo '<div id="subscribeDiv" style="float:right; text-align:center; margin:2px 8px 4px 8px;">';
        if ($user && $user === $video['user']) {
          echo '<div><img src="img/btn_subscribe_sm_yellow_99x16.gif" class="alignMid" alt="subscribe" border="0" height="16" width="99"></div>';
        } else if ($user) {
          if ($is_friend) {
            echo '<div><a href="video.php?id='.htmlspecialchars($video['public_id'] ?? $id).'&friend_del='.urlencode($video['user']).'" title="subscribe" style="text-decoration:none;"><img src="img/btn_subscribe_sm_yellow_99x16.gif" class="alignMid" alt="subscribe" title="subscribe" border="0" height="16" width="99"></a></div>';
          } else {
            echo '<div><a href="video.php?id='.htmlspecialchars($video['public_id'] ?? $id).'&friend_add='.urlencode($video['user']).'" title="subscribe" style="text-decoration:none;"><img src="img/btn_subscribe_sm_yellow_99x16.gif" class="alignMid" alt="subscribe" title="subscribe" border="0" height="16" width="99"></a></div>';
          }
        } else {
          echo '<div><a href="login.php" title="subscribe" style="text-decoration:none;"><img src="img/btn_subscribe_sm_yellow_99x16.gif" class="alignMid" alt="subscribe" title="subscribe" border="0" height="16" width="99"></a></div>';
        }
        echo '<div id="subscribeCount" class="smallText">на '.htmlspecialchars($video['user']).'</div>';
        echo '</div>';
      }
      ?>
      <div id="userInfoDiv">
      <span style="color:#333333;"><b>Загружено</b></span>&nbsp;&nbsp;<b><?=rus_date('j F Y', strtotime($video['time']))?></b><br>
      <span style="color:#333333;"><b>От</b></span>&nbsp;&nbsp;<b><a href="channel.php?user=<?=urlencode($video['user'])?>" style="color:#0033cc;"><?=htmlspecialchars($video['user'])?></a></b><br>
      </div>
      </div>
      </div>
      <?php if (trim($desc) !== ''): ?>
        <div style="padding-left: 8px;">
        <span id="desc-short" style="font-size:13px;"><?=htmlspecialchars($desc_short)?><?php if (mb_strlen($desc) > 50): ?> <a href="#" id="desc-more" style="color:#0033cc;">(ещё)</a><?php endif; ?></span>
        <span id="desc-full" style="display:none; font-size:13px;"><?=nl2br(htmlspecialchars($desc))?> <a href="#" id="desc-less" style="color:#0033cc;">(меньше)</a></span>
        </div>
  <?php endif; ?>
      <div id="vidFacetsDiv">
        <form name="urlForm" id="urlForm">
        <table id="vidFacetsTable">
        
        <tbody>
        <?php if (!empty($video['tags'])): ?>
        <tr><td class="label">Теги</td>
        <td class="tags">		
          <span id="vidTagsBegin">
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
            <span id="vidTagsRemain" style="display: none;">
              <?php foreach ($hidden_tags as $tag): 
                $tag = trim($tag);
                if (!empty($tag)):
              ?><a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>" class="dg"><?=htmlspecialchars($tag)?></a>&nbsp;<?php 
                endif;
              endforeach; 
              ?></span>&nbsp;<span id="vidTagsMore" class="smallText">(<a href="#" class="eLink" onclick="showInline('vidTagsRemain'); hideInline('vidTagsMore'); showInline('vidTagsLess'); return false;">ещё</a>)</span><span id="vidTagsLess" class="smallText" style="display: none;">(<a href="#" class="eLink" onclick="hideInline('vidTagsRemain'); hideInline('vidTagsLess'); showInline('vidTagsMore'); return false;">меньше</a>)</span>
            <?php endif; ?>
          </span>
        </td>
        </tr>
        <?php endif; ?>
        <tr><td class="label">URL</td>
        <td>
        <input name="video_link" value="http://retroshow.hoho.ws/video.php?id=<?= htmlspecialchars($video['public_id'] ?? $video['id']) ?>" class="vidURLField" onclick="javascript:document.urlForm.video_link.focus();document.urlForm.video_link.select();" readonly="true" type="text">
        </td>
        </tr>
        <tr><td class="smallLabel">Вставка</td>
        <td>
        <input name="embed_code" value="&lt;script type=&quot;text/javascript&quot; src=&quot;http://retroshow.hoho.ws/jwplayer/jwplayer.js&quot;&gt;&lt;/script&gt;&lt;div id=&quot;mediaplayer&quot;&gt;&lt;/div&gt;&lt;script type=&quot;text/javascript&quot;&gt;jwplayer(&quot;mediaplayer&quot;).setup({&#39;controlbar.position&#39;:&#39;bottom&#39;,&#39;logo.hide&#39;:&#39;true&#39;,file:&quot;http://retroshow.hoho.ws/uploads/<?= $video['id'] ?>.mp4&quot;,image:&quot;http://retroshow.hoho.ws/uploads/<?= $video['id'] ?>_preview.jpg&quot;,height:344,width:425,modes:[{type:&quot;html5&quot;},{type:&quot;flash&quot;,src:&quot;http://retroshow.hoho.ws/jwplayer/player.swf&quot;},{type:&quot;download&quot;}]});&lt;/script&gt;" class="vidURLField" onclick="javascript:document.urlForm.embed_code.focus();document.urlForm.embed_code.select();" readonly="true" type="text">
        </td></tr>
        </tbody></table>
        </form>
      </div>
    </td>
  </tr>
</table>
<script type="text/javascript">
function retroShowDescMore() {
    var more = document.getElementById ? document.getElementById('desc-more') : document.all['desc-more'];
    var less = document.getElementById ? document.getElementById('desc-less') : document.all['desc-less'];
    var short = document.getElementById ? document.getElementById('desc-short') : document.all['desc-short'];
    var full = document.getElementById ? document.getElementById('desc-full') : document.all['desc-full'];
    if (more && short && full) {
        more.onclick = function() {
            short.style.display = 'none';
            full.style.display = 'inline';
            return false;
        };
    }
    if (less && short && full) {
        less.onclick = function() {
            full.style.display = 'none';
            short.style.display = 'inline';
            return false;
        };
    }
}
if (window.attachEvent) {
    window.attachEvent('onload', retroShowDescMore);
} else if (window.addEventListener) {
    window.addEventListener('load', retroShowDescMore, false);
} else {
    window.onload = retroShowDescMore;
}
</script>
    <div id="exploreDiv">
		<div class="headerRCBox">
	<b class="rch">
	<b class="rch1"><b></b></b>
	<b class="rch2"><b></b></b>
	<b class="rch3"></b>
	<b class="rch4"></b>
	<b class="rch5"></b>
	</b> <div class="content"><span class="headerTitleLite">Посмотрите больше видео</span></div>
	</div>  
    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse; background: #f5f5f5; border-top: none;">
      <tr><td>
        <table width="100%" cellpadding="2" cellspacing="0" border="0" style="background: #fff;">
          <tr style="background: #ffffcc;"><td width="60"><a href="#"><img src="<?=htmlspecialchars($video['preview'])?>" width="60" height="45" border="0"></a></td><td><a href="#"><b><?=htmlspecialchars($video['title'])?></b></a><br><span style="font-size: 11px;"><?=get_video_duration($video['file'], $video['id'])?><br>Автор: <a href="channel.php?user=<?=htmlspecialchars($video['user'])?>" style="color: #000; text-decoration: underline;"><?=htmlspecialchars($video['user'])?></a><br>Просмотров: <?=intval($video['views'] ?? 212)?><br><b>&lt;&lt; Сейчас смотрите</b></span></td></tr>
          <?php foreach ($recommended as $rec): ?>
          <tr style="background: #EEEEEE;"><td width="60"><a href="video.php?id=<?=htmlspecialchars($rec['public_id'] ?? $rec['id'])?>"><img src="<?=htmlspecialchars($rec['preview'])?>" width="60" height="45" border="0"></a></td><td><a href="video.php?id=<?=htmlspecialchars($rec['public_id'] ?? $rec['id'])?>"><b><?=htmlspecialchars($rec['title'])?></b></a><br><span style="font-size: 11px;"><?=get_video_duration($rec['file'], $rec['id'])?><br>Автор: <a href="channel.php?user=<?=htmlspecialchars($rec['user'])?>" style="color: #000; text-decoration: underline;"><?=htmlspecialchars($rec['user'])?></a><br>Просмотров: <?=intval($rec['views'] ?? 0)?></span></td></tr>
          <?php endforeach; ?>
          <tr>
            <td colspan="2" style="background:#cccccc; text-align:right; font-size:11px; padding:3px 8px;"> 
              <a href="channel.php" style="color:#0033cc;">Посмотрите все видео</a>
            </td>
          </tr>
  </table>
</td></tr>
    </table>
  </td>
</tr>
</table><div style="padding: 0px 5px 0px 5px;">

</div>
        </td></tr></table>
        <table cellpadding="10" cellspacing="0" border="0" align="center">
    <tbody><tr>
        <td align="center" valign="center"><span class="footer"><a href="about.php">О сайте</a> | <a href="http://github.com/tankwars92/RetroShow">Исходный код</a> | <a href="http://downgrade-net.ru/">Downgrade Net</a></span> 
        <br><br>Copyright © 2026 RetroShow | <a href="rss.php"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align: text-top;"></a></span>
        <br>
        <br>
        <script src="//downgrade-net.ru/services/ring/ring.php"></script> <img src="//downgrade-net.ru/services/counter/index.php?id=21" alt="Downgrade Counter" border="0"> 
    </td>
    </tr>
</tbody></table>



<div id="sheet" style="position:fixed; top:0px; visibility:hidden; width:100%; text-align:center;">
<table width="100%">
<tbody><tr>
<td align="center">
<div id="sheetContent" style="filter:alpha(opacity=50); -moz-opacity:0.5; opacity:0.5; border: 1px solid black; background-color:#cccccc; width:40%; text-align:left;"></div>
</td>
</tr>
</tbody></table>
</div>

<div id="tooltip"></div>


<script type="text/javascript">
function retroShowToggleCommentForm() {
    var btn = document.getElementById ? document.getElementById('showCommentForm') : document.all['showCommentForm'];
    if (btn) {
        btn.onclick = function() {
            var f = document.getElementById ? document.getElementById('commentFormBlock') : document.all['commentFormBlock'];
            if (!f) {
                alert('Только для зарегистрированных пользователей!');
                return false;
            }
            if (f.style.display == '' || f.style.display == 'none') {
                f.style.display = 'block';
            } else {
                f.style.display = 'none';
            }
            return false;
        };
    }
}
function retroShowReplyLinks() {
    var links = document.getElementsByTagName('a');
    for (var i=0; i<links.length; i++) {
        if (links[i].className && links[i].className.indexOf('reply-link') !== -1) {
            links[i].onclick = function() {
                var id = this.getAttribute ? this.getAttribute('data-id') : this['data-id'];
                for (var j=0; j<links.length; j++) {
                    if (links[j].className && links[j].className.indexOf('reply-link') !== -1) {
                        var rid = links[j].getAttribute ? links[j].getAttribute('data-id') : links[j]['data-id'];
                        var rf = document.getElementById ? document.getElementById('replyform-' + rid) : document.all['replyform-' + rid];
                        if (rf) rf.style.display = 'none';
                    }
                }
                var f = document.getElementById ? document.getElementById('replyform-' + id) : document.all['replyform-' + id];
                var orig = document.getElementById ? document.getElementById('commentFormBlock') : document.all['commentFormBlock'];
                if (f && orig) {
                    var html = orig.innerHTML
                        .replace(/name="comment_formmain_comment2"/g, 'name="reply_form_'+id+'"')
                        .replace(/id="comment_formmain_comment2"/g, 'id="reply_form_'+id+'"');
                    f.innerHTML = html;
                    f.style.display = 'block';
					
                    var replyField = f.getElementsByTagName('input');
                    for (var k=0; k<replyField.length; k++) {
                        if (replyField[k].name == 'reply_parent_id') {
                            replyField[k].value = id;
                        }
                    }
                }
                return false;
            };
        }
    }
}
if (window.attachEvent) {
    window.attachEvent('onload', retroShowToggleCommentForm);
    window.attachEvent('onload', retroShowReplyLinks);
} else if (window.addEventListener) {
    window.addEventListener('load', retroShowToggleCommentForm, false);
    window.addEventListener('load', retroShowReplyLinks, false);
} else {
    window.onload = function() {
        retroShowToggleCommentForm();
        retroShowReplyLinks();
    };
}
</script>
</body>
</html>
