<?php
include "init.php";
include "template.php";

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
if (session_status() == PHP_SESSION_NONE) session_start();

$view_user = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['user']) ? $_SESSION['user'] : null);
$friends = array();
if ($view_user) {
    $friends_file = __DIR__ . '/friends/' . urlencode($view_user) . '.txt';
    if (file_exists($friends_file)) {
        $friends = file($friends_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    if (isset($_SESSION['user']) && $_SESSION['user'] === $view_user && isset($_GET['del']) && in_array($_GET['del'], $friends)) {
        $friends = array_diff($friends, [$_GET['del']]);
        file_put_contents($friends_file, implode("\n", $friends));
        header("Location: friends.php");
        exit;
    }
}
showHeader('Друзья');
$user_disp = htmlspecialchars($view_user);
$is_own = isset($_SESSION['user']) && $_SESSION['user'] === $view_user;
$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$view_user]);
$total_videos = $stmt_total->fetchColumn();
$comments_count = 0;
$profile_comments_file = __DIR__ . '/comments/profile_' . urlencode($view_user) . '.txt';
if (file_exists($profile_comments_file)) {
    $comments_count = count(file($profile_comments_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
}
$fav_file = __DIR__ . '/favourites/' . urlencode($view_user) . '.txt';
$fav_count = (file_exists($fav_file)) ? count(file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
$fr_file = __DIR__ . '/friends/' . urlencode($view_user) . '.txt';
$fr_count = (file_exists($fr_file)) ? count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
echo '<a href="channel.php?user='.urlencode($view_user).'">Профиль</a> | ';
echo '<a href="channel.php?user='.urlencode($view_user).'&tab=videos">Видео ('.$total_videos.')</a> | ';
echo '<a href="favourites.php?user='.urlencode($view_user).'">Избранное ('.$fav_count.')</a> | ';
echo '<b>Друзья ('.$fr_count.')</b> | ';
echo '<a href="channel.php?user='.urlencode($view_user).'&tab=comments">Комментарии ('.$comments_count.')</a>';
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
            <div class="moduleTitle">Друзья // <?=htmlspecialchars($view_user)?></div>
          </div>
<?php
if (!$view_user) {
    echo '<div style="padding:20px; text-align:center; color:#888; font-size:14px; background:#fff;">Войдите или выберите пользователя для просмотра друзей.</div>';
} elseif (count($friends) == 0) {
    echo "<div style='padding:20px; background:#f8f8f8; border:1px solid #ccc; color:#888;'>
    " . ($is_own ? 'У вас нет друзей.' : 'У пользователя '.$user_disp.' нет друзей.') . "
  </div>";

} else {
    foreach ($friends as $friend) {
        $videos_count = 0;
        $favs_count = 0;
        $fr_count = 0;
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stmt->execute([$friend]);
        $videos_count = (int)$stmt->fetchColumn();
        $fav_file = __DIR__ . '/favourites/' . urlencode($friend) . '.txt';
        if (file_exists($fav_file)) {
            $favs_count = count(file($fav_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }
        $fr_file = __DIR__ . '/friends/' . urlencode($friend) . '.txt';
        if (file_exists($fr_file)) {
            $fr_count = count(file($fr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }
        echo '<div style="background-color:#DDD; background-image:url(\'img/table_results_bg.gif\'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">';
        echo '<table width="565" cellpadding="0" cellspacing="0" border="0">';
        echo '<tr valign="top">';
		
        $friend_user_data = null;
        try {
            $stmt_friend = $db->prepare('SELECT profile_icon FROM users WHERE login = ?');
            $stmt_friend->execute([$friend]);
            $friend_user_data = $stmt_friend->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $friend_user_data = null;
        }
        echo '<td width="90"><a href="channel.php?user='.urlencode($friend).'"><img src="'.get_profile_icon($friend, $friend_user_data['profile_icon'] ?? '0').'" class="moduleEntryThumb" width="90" height="70" style="border:1px solid #888;"></a></td>';
        echo '<td width="100%" style="padding-left:8px;">';
        echo '<div style="font-size:13px; font-weight:bold;"><a href="channel.php?user='.urlencode($friend).'" style="color:#0033cc; text-decoration:underline;">'.htmlspecialchars($friend).'</a></div>';
        echo '<div style="font-size:11px; margin:2px 0 2px 0;">';
        echo '<a href="channel.php?user='.urlencode($friend).'&tab=videos" style="color:#0033cc; font-size:12px; text-decoration:underline;">Видео</a> ('.$videos_count.') | ';
        echo '<a href="favourites.php?user='.urlencode($friend).'" style="color:#0033cc; font-size:12px; text-decoration:underline;">Избранное</a> ('.$favs_count.') | ';
        echo '<a href="friends.php?user='.urlencode($friend).'" style="color:#0033cc; font-size:12px; text-decoration:underline;">Друзья</a> ('.$fr_count.')';
        echo '</div>';
        if (isset($_SESSION['user']) && $_SESSION['user'] === $view_user) {
            echo '<div style="font-size:11px; margin:2px 0 2px 0;">';
            echo '<form method="get" style="margin:0; display:inline;">';
            echo '<input type="hidden" name="user" value="'.htmlspecialchars($view_user).'">';
            echo '<input type="hidden" name="del" value="'.htmlspecialchars($friend).'">';
            echo '<a href="#" onclick="this.parentNode.submit();return false;" style="color:#0033cc; text-decoration:underline; font-size:12px; margin:0; padding:0; cursor:pointer;">Удалить из друзей</a>';
            echo '</form>';
            echo '</div>';
        }
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
    }
}
?>
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