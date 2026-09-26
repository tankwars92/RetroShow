<?php
include "init.php";
include "template.php";

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
if (session_status() == PHP_SESSION_NONE) session_start();

$view_user = isset($_GET['user']) ? $_GET['user'] : (isset($_SESSION['user']) ? $_SESSION['user'] : null);
$friends = array();
if ($view_user) {
    $stmtFr = $db->prepare("SELECT friend FROM user_friends WHERE user = ? ORDER BY created_at DESC");
    $stmtFr->execute([$view_user]);
    $friends = $stmtFr->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
    if (isset($_SESSION['user']) && $_SESSION['user'] === $view_user) {
        $del_friend = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_friend'])) {
            $del_friend = trim((string)($_POST['friend'] ?? ''));
        } elseif (isset($_GET['del'])) {
            $del_friend = trim((string)$_GET['del']);
        }
        if ($del_friend !== '' && in_array($del_friend, $friends, true)) {
            $db->prepare("DELETE FROM user_friends WHERE user = ? AND friend = ?")->execute([$view_user, $del_friend]);
            header("Location: friends.php?user=" . urlencode($view_user));
            exit;
        }
    }
}
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total_friends = count($friends);
$total_pages = $total_friends ? ceil($total_friends / $per_page) : 1;
$offset = ($page - 1) * $per_page;
$paged_friends = array_slice($friends, $offset, $per_page);
showHeader('Друзья');
$user_disp = htmlspecialchars($view_user);
$is_own = isset($_SESSION['user']) && $_SESSION['user'] === $view_user;
$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$view_user]);
$total_videos = $stmt_total->fetchColumn();
$comments_count = 0;
$comments_count = 0;
try {
    $stmtPc = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
    $stmtPc->execute([$view_user]);
    $comments_count = (int)$stmtPc->fetchColumn();
} catch (Exception $e) {}
$fav_count = 0;
$fr_count = 0;
$private_count = 0;
try {
    $stmtFav = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
    $stmtFav->execute([$view_user]);
    $fav_count = (int)$stmtFav->fetchColumn();
} catch (Exception $e) {}
try {
    $stmtFr2 = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
    $stmtFr2->execute([$view_user]);
    $fr_count = (int)$stmtFr2->fetchColumn();
} catch (Exception $e) {}
try {
    $stmtPriv = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
    $stmtPriv->execute([$view_user]);
    $private_count = (int)$stmtPriv->fetchColumn();
} catch (Exception $e) {}
?>
<style>
.channelPagingDiv { background: #CCC; margin: 0; padding: 5px 0; font-size: 13px; color: #333; font-weight: bold; text-align: right; }
.channelPagingDiv .pagerCurrent { color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent { color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent a { color: #03C; text-decoration: underline; }
</style>
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
                  <?= t('Друзья // ') ?><?=htmlspecialchars($view_user)?>
                </td>
                <?php if (!$is_own || (int)$total_friends > 0): ?>
                <td style="font-size:12px; font-weight:bold; color:#444; text-align:right; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
                  <?= t('Друзья') ?> <?= $total_friends ? ($offset + 1) . '-' . min($offset + $per_page, $total_friends) . ' ' . t('из') . ' ' . $total_friends : '0 ' . t('из') . ' 0' ?>
                </td>
                <?php endif; ?>
              </tr>
            </table>
          </div>
<?php
if (!$view_user) {
    echo '<div style="padding:20px; text-align:center; color:#888; font-size:14px; background:#fff;">'.t('Войдите или выберите пользователя для просмотра друзей.').'</div>';

} else {
    if (!$is_own && (int)$total_friends === 0) {
        echo '<div style="padding:10px;font-size:13px;color:#666;">'.t('Этот пользователь не добавил ни одного друга.').'</div>';
    }
    foreach ($paged_friends as $friend) {
        $videos_count = 0;
        $favs_count = 0;
        $fr_count = 0;
        $friend_has_avatar = false;
        $friend_avatar_thumb = '';
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stmt->execute([$friend]);
        $videos_count = (int)$stmt->fetchColumn();
        $friend_avatar_thumb = get_profile_icon($friend, '0');
        if ($friend_avatar_thumb !== '' && $friend_avatar_thumb !== 'img/no_videos_140.jpg') {
            $friend_has_avatar = true;
        }
        try {
            $stmtFav2 = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
            $stmtFav2->execute([$friend]);
            $favs_count = (int)$stmtFav2->fetchColumn();
        } catch (Exception $e) { $favs_count = 0; }
        try {
            $stmtFr3 = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
            $stmtFr3->execute([$friend]);
            $fr_count = (int)$stmtFr3->fetchColumn();
        } catch (Exception $e) { $fr_count = 0; }
        echo '<div class="moduleEntry">';
        echo '<table width="100%" cellpadding="0" cellspacing="0" border="0">';
        echo '<tr valign="top">';

        if ($is_own && $friend_has_avatar) {
            echo '<td align="center" width="150">';
            echo '<a href="channel.php?user='.urlencode($friend).'">';
            echo '<img src="'.htmlspecialchars($friend_avatar_thumb, ENT_QUOTES, 'UTF-8').'" class="moduleEntryThumb" width="120" height="90" border="0" alt="">';
            echo '</a>';
            echo '<form method="post" action="friends.php?user='.urlencode($view_user).'" style="margin:0;">';
            echo '<input type="hidden" name="remove_friend" value="1">';
            echo '<input type="hidden" name="friend" value="'.htmlspecialchars($friend, ENT_QUOTES, 'UTF-8').'">';
            echo '<input type="submit" value="'.htmlspecialchars(t('Удалить из друзей'), ENT_QUOTES, 'UTF-8').'" style="margin-top:5px;">';
            echo '</form>';
            echo '</td>';
        } elseif ($is_own) {
            echo '<td align="center" width="150">';
            echo '<form method="post" action="friends.php?user='.urlencode($view_user).'" style="margin:0;">';
            echo '<input type="hidden" name="remove_friend" value="1">';
            echo '<input type="hidden" name="friend" value="'.htmlspecialchars($friend, ENT_QUOTES, 'UTF-8').'">';
            echo '<input type="submit" value="'.htmlspecialchars(t('Удалить из друзей'), ENT_QUOTES, 'UTF-8').'" style="margin-top:5px;">';
            echo '</form>';
            echo '</td>';
        } else {
            $friend_thumb = get_profile_icon($friend, '0');
            echo '<td width="130" align="center">';
            echo '<a href="channel.php?user='.urlencode($friend).'">';
            echo '<img src="'.htmlspecialchars($friend_thumb, ENT_QUOTES, 'UTF-8').'" class="moduleEntryThumb" width="120" height="90" border="0" alt="">';
            echo '</a>';
            echo '</td>';
        }

        echo '<td width="100%">';
        echo '<div class="moduleEntryTitle">';
        echo '<a href="channel.php?user='.urlencode($friend).'">'.htmlspecialchars($friend, ENT_QUOTES, 'UTF-8').'</a>';
        if ($is_own) {
            echo '<span style="color:#777;font-size:11px;"> ('.t('Друзья').')</span>';
        }
        echo '</div>';

        echo '<div class="moduleEntryDescription">';
        if ($is_own) {
            echo '<a href="channel.php?user='.urlencode($friend).'&tab=videos">'.t('Видео').'</a> ('.$videos_count.') | ';
            echo '<a href="favourites.php?user='.urlencode($friend).'">'.t('Избранное').'</a> ('.$favs_count.') | ';
            echo '<a href="friends.php?user='.urlencode($friend).'">'.t('Друзья').'</a> ('.$fr_count.')';
        } else {
            echo '<a href="channel.php?user='.urlencode($friend).'&tab=videos&view=public">'.t('Видео').'</a> ('.$videos_count.') | ';
            echo '<a href="favourites.php?user='.urlencode($friend).'">'.t('Избранное').'</a> ('.$favs_count.') | ';
            echo '<a href="friends.php?user='.urlencode($friend).'">'.t('Друзья').'</a> ('.$fr_count.')';
        }
        echo '</div>';
        echo '</td>';

        echo '</tr>';
        echo '</table>';
        echo '</div>';
    }
    if ($total_pages > 1): ?>
    <div class="channelPagingDiv pagingDiv">
      Стр.
      <?php
      $start_page = max(1, $page - 2);
      $end_page = min($total_pages, $page + 2);
      $user_param = $view_user ? ('user='.urlencode($view_user).'&') : '';
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
          echo '<span class="pagerNotCurrent"><a href="?'.$user_param.'page='.($page + 1).'">'.t('Далее').'</a></span>';
      }
      ?>
    </div>
    <?php endif;
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
    <?= channel_sidebar_nav_html($view_user, 'friends', [
        'public' => (int)$total_videos,
        'private' => $is_own ? (int)$private_count : 0,
        'fav' => (int)$fav_count,
        'friends' => (int)$fr_count,
    ]) ?>
  </td>
</tr>
</table>
<?php showFooter(); ?> 