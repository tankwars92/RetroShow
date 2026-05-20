<?php
include("init.php");
include_once 'template.php';
require_once __DIR__ . '/recs.php';

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

function rus_date($time) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    $d = date('j', $time);
    $m = $months[intval(date('n', $time))];
    $y = date('Y', $time);
    return "$d $m $y";
}

function channel_video_rus_date_from_db($timeVal): string {
    if ($timeVal === null || $timeVal === '') {
        return rus_date(time());
    }
    if (is_numeric($timeVal)) {
        $ts = (int)$timeVal;
        if ($ts > 100000) {
            return rus_date($ts);
        }
    }
    $s = trim((string)$timeVal);
    foreach (['d.m.Y, H:i', 'd.m.Y H:i'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $s);
        if ($dt instanceof DateTime) {
            return rus_date((int)$dt->format('U'));
        }
    }
    $ts = strtotime(str_replace(',', '', $s));
    if ($ts !== false && $ts > 86400) {
        return rus_date($ts);
    }
    return rus_date(time());
}

function channel_comment_body_html($text) {
    return nl2br(htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'), false);
}

require_once 'duration_helper.php';

function get_video_duration($file, $id, $public_id = '') {
    return get_video_duration_fast($file, $id, $public_id);
}

function channel_get_rating_stats($db, $video_id) {
    $stmt = $db->query("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM ratings WHERE video_id = ".intval($video_id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = intval($row['cnt'] ?? 0);
    $avg = $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : 0.0;
    return [$count, $avg];
}
function channel_render_avg_stars_html($avg, $count, $show_count_rating = true) {
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
      <?php if ($show_count_rating): ?>
        <span style="color:#666666; font-size:smaller;">(<?=intval($count)?> оценок)</span>
      <?php endif; ?>
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

$fav_count = 0;
$comments_count = 0;
try {
    $stmtFav = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
    $stmtFav->execute([$user]);
    $fav_count = (int)$stmtFav->fetchColumn();
} catch (Exception $e) {}
try {
    $stmtPc = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
    $stmtPc->execute([$user]);
    $comments_count = (int)$stmtPc->fetchColumn();
} catch (Exception $e) {}

$profile_comments_preview = [];
try {
    $stmtPcPrev = $db->prepare('SELECT time, user, text FROM profile_comments WHERE profile_user = ? ORDER BY time DESC, id DESC LIMIT 3');
    $stmtPcPrev->execute([$user]);
    $profile_comments_preview = $stmtPcPrev->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
}

$subscribers_count = 0;
$my_friends = [];
try {
    $stmtMy = $db->prepare("SELECT friend FROM user_friends WHERE user = ?");
    $stmtMy->execute([$user]);
    $my_friends = $stmtMy->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
} catch (Exception $e) {}
try {
    $stmtSub = $db->prepare("
        SELECT COUNT(*) 
        FROM user_friends uf
        WHERE uf.friend = ?
          AND uf.user NOT IN (SELECT friend FROM user_friends WHERE user = ?)
    ");
    $stmtSub->execute([$user, $user]);
    $subscribers_count = (int)$stmtSub->fetchColumn();
} catch (Exception $e) {}

$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
$stmt_total->execute([$user]);
$total = $stmt_total->fetchColumn();

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;
if ($user && (!isset($_GET['tab']) || $_GET['tab'] === '')) {
    $is_owner_profile = isset($_SESSION['user']) && $_SESSION['user'] === $user;
    $are_friends = false;

    if (!$is_owner_profile && isset($_SESSION['user']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_friend'])) {
        try {
            $db->prepare("INSERT OR IGNORE INTO user_friends (user, friend, created_at) VALUES (?, ?, ?)")
                ->execute([$_SESSION['user'], $user, time()]);
        } catch (Exception $e) {
        }
        header('Location: channel.php?user=' . urlencode($user));
        exit;
    }
    if (!$is_owner_profile && isset($_SESSION['user'])) {
        try {
            $stAreFriends = $db->prepare("SELECT 1 FROM user_friends WHERE user = ? AND friend = ? LIMIT 1");
            $stAreFriends->execute([$_SESSION['user'], $user]);
            $are_friends = (bool)$stAreFriends->fetchColumn();
        } catch (Exception $e) {
            $are_friends = false;
        }
    }

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
    $videos_watched = (int)$stmt_vw->fetchColumn();

    if (!function_exists('ago_ru')) {
        function ago_ru($ts) {
            $diff = time() - (int)$ts;
            if ($diff < 60) return $diff.' секунд назад';
            if ($diff < 3600) return floor($diff / 60).' минут назад';
            if ($diff < 86400) return floor($diff / 3600).' часов назад';
            if ($diff < 2592000) return floor($diff / 86400).' дней назад';
            if ($diff < 31536000) return floor($diff / 2592000).' месяцев назад';
            return floor($diff / 31536000).' лет назад';
        }
    }

    $public_count = 0;
    $private_count = 0;
    $friends_count = 0;
    try {
        $stPub = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
        $stPub->execute([$user]);
        $public_count = (int)$stPub->fetchColumn();
        if ($is_owner_profile) {
            $stPriv = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
            $stPriv->execute([$user]);
            $private_count = (int)$stPriv->fetchColumn();
        }
        $stFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
        $stFr->execute([$user]);
        $friends_count = (int)$stFr->fetchColumn();
    } catch (Exception $e) {
    }

    $latest_public = null;
    try {
        $stLatest = $db->prepare("SELECT id, public_id, title, preview, time, views FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT 1");
        $stLatest->execute([$user]);
        $latest_public = $stLatest->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $latest_public = null;
    }

    $age_text = '';
    $birth_year = isset($user_data['birthday_yr']) ? trim((string)$user_data['birthday_yr']) : '';
    if ($birth_year !== '' && $birth_year !== '---') {
        $age_val = (int)date('Y') - (int)$birth_year;
        if ($age_val > 0 && $age_val < 120) {
            $n10 = $age_val % 10;
            $n100 = $age_val % 100;
            if ($n10 === 1 && $n100 !== 11) {
                $age_word = 'год';
            } elseif ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) {
                $age_word = 'года';
            } else {
                $age_word = 'лет';
            }
            $age_text = $age_val . ' ' . $age_word;
        }
    }

    $name_text = trim((string)($user_data['name'] ?? '') . ' ' . (string)($user_data['last_n'] ?? ''));
    $about_text = trim((string)($user_data['about_me'] ?? ''));

    $website_raw = trim((string)($user_data['website'] ?? ''));
    $website_href = $website_raw;
    if ($website_href !== '' && !preg_match('/^https?:\/\//i', $website_href)) {
        $website_href = 'http://' . $website_href;
    }

    showHeader('Профиль ' . htmlspecialchars($user));
    ?>
    <table width="790" align="center" cellpadding="0" cellspacing="0" border="0">
        <tr valign="top">
            <td width="180">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#E5ECF9">
                    <tr>
                        <td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
                        <td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                        <td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
                    </tr>
                    <tr>
                        <td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
                        <td align="center" style="padding:5px;">
                            <div style="font-size:14px;font-weight:bold;color:#003366;margin-bottom:5px;">
                                Вы знаете <?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>?
                            </div>
                            <?php if ($is_owner_profile): ?>
                                <div style="font-size:12px;color:#666;">
                                    Это ваш профиль
                                </div>
                            <?php elseif (isset($_SESSION['user']) && $are_friends): ?>
                                <div style="font-size:12px;color:#666;">
                                    Вы уже друзья.
                                </div>
                            <?php elseif (isset($_SESSION['user'])): ?>
                                <form method="post" action="channel.php?user=<?= urlencode($user) ?>" style="margin:0;">
                                    <input type="hidden" name="add_friend" value="1">
                                    <input type="submit" value="Добавить друга">
                                </form>
                            <?php else: ?>
                              <a href="register.php">Зарегистрируйтесь</a> или <a href="login.php">войдите</a>, чтобы добавить друга
                            <?php endif; ?>
                            <br><br>
                            <div style="font-size:14px;font-weight:bold;color:#003366;margin-bottom:5px;">
                                Хотите связаться?
                            </div>
                            <form method="get" action="outbox.php" style="margin:0;">
                                <input type="hidden" name="user" value="<?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="submit" value="Пишите мне!">
                            </form>
                        </td>
                        <td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
                    </tr>
                    <tr>
                        <td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
                        <td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                        <td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
                    </tr>
                </table>
            </td>

            <td style="padding:0 10px;">
                <table width="100%" cellpadding="5" cellspacing="0" border="0">
                    <tbody>
                    <tr>
                        <td width="120" align="right"><span class="label">Имя пользователя:</span></td>
                        <td><?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    
                    <?php if ($name_text !== ''): ?>
                    <tr>
                        <td align="right"><span class="label">Имя:</span></td>
                        <td><?= htmlspecialchars($name_text, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($age_text !== ''): ?>
                    <tr>
                        <td align="right"><span class="label">Возраст:</span></td>
                        <td><?= htmlspecialchars($age_text, ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($about_text !== ''): ?>
                    <tr>
                        <td align="right" valign="top"><span class="label">Обо мне:</span></td>
                        <td><?= nl2br(htmlspecialchars($about_text, ENT_QUOTES, 'UTF-8')) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($website_raw !== ''): ?>
                    <tr>
                        <td align="right"><span class="label">Веб-сайт:</span></td>
                        <td><a href="<?= htmlspecialchars($website_href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($website_raw, ENT_QUOTES, 'UTF-8') ?></a></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>

                    <?php if (!empty($user_data['hometown'])): ?>
                    <tr>
                        <td align="right"><span class="label">Родной город:</span></td>
                        <td><?= htmlspecialchars((string)$user_data['hometown'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($user_data['city'])): ?>
                    <tr>
                        <td align="right"><span class="label">Текущий город:</span></td>
                        <td><?= htmlspecialchars((string)$user_data['city'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($user_data['country'])): ?>
                    <tr>
                        <td align="right"><span class="label">Текущая страна:</span></td>
                        <td><?= htmlspecialchars((string)$user_data['country'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td colspan="2">&nbsp;</td>
                    </tr>

                    <tr>
                        <td align="right"><span class="label">Последний вход:</span></td>
                        <td><?= htmlspecialchars(ago_ru($last_login_time), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                    </tbody>
                </table>
            </td>

            <td width="180">
                <?= channel_sidebar_nav_html($user, 'profile', [
                    'public' => $public_count,
                    'private' => $private_count,
                    'fav' => $fav_count,
                    'friends' => $friends_count,
                ]) ?>

                <?php if ($latest_public): ?>
                <table width="100%" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC" style="margin-bottom:10px;">
                    <tr>
                        <td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
                        <td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                        <td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
                    </tr>
                    <tr>
                        <td width="5" style="background-color:#CCCCCC;"><img src="img/pixel.gif" width="5" height="1" alt=""></td>
                        <td>
                            <div class="moduleTitleBar">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr><td><div class="moduleTitle">Последнее видео</div></td></tr>
                                </table>
                            </div>
                            <div class="moduleFeatured">
                                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td align="center">
                                            <a href="video.php?id=<?= urlencode((string)($latest_public['public_id'] ?? $latest_public['id'])) ?>">
                                                <img src="<?= htmlspecialchars((string)($latest_public['preview'] ?? 'img/no_videos_140.jpg'), ENT_QUOTES, 'UTF-8') ?>" class="moduleFeaturedThumb" width="120" height="90" alt="">
                                            </a>
                                            <div class="moduleFeaturedTitle">
                                                <a href="video.php?id=<?= urlencode((string)($latest_public['public_id'] ?? $latest_public['id'])) ?>"><?= htmlspecialchars((string)($latest_public['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                            </div>
                                            <div class="moduleFeaturedDetails">
                                                Добавлено: <?= htmlspecialchars(channel_video_rus_date_from_db($latest_public['time'] ?? null), ENT_QUOTES, 'UTF-8') ?><br>
                                                от <a href="channel.php?user=<?= urlencode($user) ?>"><?= htmlspecialchars($user, ENT_QUOTES, 'UTF-8') ?></a>
                                            </div>
                                            <div class="moduleFeaturedDetails">Просмотров: <?= (int)($latest_public['views'] ?? 0) ?></div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                        <td width="5" style="background-color:#CCCCCC;"><img src="img/pixel.gif" width="5" height="1" alt=""></td>
                    </tr>
                    <tr>
                        <td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
                        <td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
                        <td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
                    </tr>
                </table>
                <?php endif; ?>

                <div style="font-size:12px;color:#444;margin-top:10px;text-align:center;">
                    <strong>Нравятся мои видео?</strong><br>
                    <a href="rss.php?user=<?= urlencode($user) ?>">Подпишитесь на RSS.</a>
                </div>
            </td>
        </tr>
    </table>
    <?php
    showFooter();
    exit;
}

if ($user && isset($_GET['tab']) && $_GET['tab'] === 'videos') {
  $public_only_view = isset($_GET['view']) && (string)$_GET['view'] === 'public';
  $fr_count = 0;
  try {
    $stmtFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
    $stmtFr->execute([$user]);
    $fr_count = (int)$stmtFr->fetchColumn();
  } catch (Exception $e) {
    $fr_count = 0;
  }
  $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
  $per_page = 5;
  $offset = ($page - 1) * $per_page;
  $is_owner = isset($_SESSION['user']) && $_SESSION['user'] === $user;
  $show_owner_tools = $is_owner && !$public_only_view;

  $profile_icon_mode = '0';
  if ($is_owner) {
    $profile_icon_mode = get_user_profile_icon_setting($user);
  }
  $can_choose_avatar = $is_owner && $profile_icon_mode === '1';

  if ($can_choose_avatar && !$public_only_view && isset($_GET['set_avatar'])) {
    $set_id = intval($_GET['set_avatar']);
    if ($set_id > 0) {
      try {
        $stmtSet = $db->prepare("SELECT preview FROM videos WHERE id = ? AND user = ?");
        $stmtSet->execute([$set_id, $user]);
        $vrow = $stmtSet->fetch(PDO::FETCH_ASSOC);
        if ($vrow && !empty($vrow['preview'])) {
          $stmtUpd = $db->prepare("UPDATE users SET profile_icon_custom = ? WHERE login = ?");
          $stmtUpd->execute([$vrow['preview'], $user]);
        }
      } catch (Exception $e) {
      }
    }
    $redir = 'channel.php?user=' . urlencode($user) . '&tab=videos';
    if ($page > 1) {
      $redir .= '&page=' . $page;
    }
    header('Location: ' . $redir);
    exit;
  }

  if ($show_owner_tools) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ?");
  } else {
    $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
  }
  $stmt->execute([$user]);
  $total = $stmt->fetchColumn();
  $total_pages = ceil($total / $per_page);

  if ($show_owner_tools) {
    $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file, tags, private, original_filename FROM videos WHERE user = ? ORDER BY id DESC LIMIT $offset, $per_page");
  } else {
    $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file, tags, private, original_filename FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC LIMIT $offset, $per_page");
  }
  $stmt->execute([$user]);
  $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $my_tags = [];
  $private_count = 0;
  try {
    $stmtTags = $db->prepare("SELECT tags FROM videos WHERE user = ? AND private = 0 ORDER BY id DESC");
    $stmtTags->execute([$user]);
    $tag_rows = $stmtTags->fetchAll(PDO::FETCH_ASSOC);
    $tag_stats = [];
    foreach ($tag_rows as $tr) {
      $raw_tags = trim((string)($tr['tags'] ?? ''));
      if ($raw_tags === '') continue;
      $parts = preg_split('/\s+/', $raw_tags, -1, PREG_SPLIT_NO_EMPTY);
      if (!is_array($parts)) continue;
      foreach ($parts as $tag) {
        $tag = trim((string)$tag);
        if ($tag === '') continue;
        $k = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        if (!isset($tag_stats[$k])) {
          $tag_stats[$k] = ['tag' => $tag, 'count' => 0];
        }
        $tag_stats[$k]['count']++;
      }
    }
    if (!empty($tag_stats)) {
      usort($tag_stats, function ($a, $b) {
        if ((int)$a['count'] === (int)$b['count']) {
          return strcmp((string)$a['tag'], (string)$b['tag']);
        }
        return ((int)$b['count'] - (int)$a['count']);
      });
      $my_tags = array_slice($tag_stats, 0, 20);
    }
  } catch (Exception $e) {
    $my_tags = [];
  }
  if ($is_owner) {
    try {
      $stmtPrivCount = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 1");
      $stmtPrivCount->execute([$user]);
      $private_count = (int)$stmtPrivCount->fetchColumn();
    } catch (Exception $e) {
      $private_count = 0;
    }
  }

  $public_count_videos = 0;
  try {
    $stmtPubCnt = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
    $stmtPubCnt->execute([$user]);
    $public_count_videos = (int)$stmtPubCnt->fetchColumn();
  } catch (Exception $e) {
    $public_count_videos = 0;
  }

  if ($public_only_view) {
    $sidebar_active = 'public';
  } elseif ($show_owner_tools) {
    $sidebar_active = 'private';
  } else {
    $sidebar_active = 'public';
  }

  showHeader(($public_only_view ? 'Публичные видео // ' . htmlspecialchars($user) : 'Мои видео '));
    ?>
  <link rel="stylesheet" href="img/styles.css" type="text/css">
  <style>
  .vfacets { margin: 5px 0 !important; }
  .vtagLabel { font-size: 11px !important; color: #888 !important; display: inline !important; }
  .vtagValue { display: inline !important; margin-left: 5px !important; }
  .vtagValue .dg { color: #333 !important; text-decoration: underline !important; }
  .vtagValue .dg:hover { color: #333 !important; text-decoration: underline !important; }
  </style>
<link rel="stylesheet" href="img/base.css" type="text/css">
<link rel="stylesheet" href="img/watch.css" type="text/css">
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
                      <?= $show_owner_tools ? 'Мои видео' : 'Публичные видео // '  . htmlspecialchars($user) ?>
                    </td>
                    <?php if (!$show_owner_tools || (int)$total > 0): ?>
                    <td style="font-size:12px; font-weight:bold; color:#444; text-align:right; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
                      Видео <?= ($offset + 1) ?>-<?= min($offset + $per_page, $total) ?> из <?= $total ?>
                    </td>
                    <?php endif; ?>
                  </tr>
                </table>
              </div>
              <?php if (count($videos) == 0): ?>
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
                  $vid_link = htmlspecialchars($row['public_id'] ?? $row['id']);
                  $desc = htmlspecialchars($row['description']);
                  $desc_short = mb_strlen($desc) > 30 ? mb_substr($desc, 0, 30) . '...' : $desc;
                  $desc_id = 'desc_chan_' . $row['id'];
                  $desc_full = nl2br($desc);
                  $comments_count = 0;
                  try {
                      $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                      $stmtCc->execute([$row['id']]);
                      $comments_count = (int)$stmtCc->fetchColumn();
                  } catch (Exception $e) {
                      $comments_count = 0;
                  }
                  list($rc, $ra) = channel_get_rating_stats($db, $row['id']);
                  ?>
                  <div style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x; border-bottom:1px dashed #999999; padding:10px;">
                    <table width="565" cellpadding="0" cellspacing="0" border="0">
                      <tr valign="top">
                        <td width="120" valign="top"><a href="video.php?id=<?=$vid_link?>"><img src="<?=htmlspecialchars($row['preview'])?>" class="moduleFeaturedThumb" width="120" height="90" style="margin: 0px 2px 0px 0px; display:block;"></a>
<center>
<br>

<?php if (is_valid_video_public_id($row['public_id'] ?? '')): ?>
    <button type="button" style="margin-bottom:15px;width:110px;" onclick="window.location.href='my_videos_edit.php?id=<?=urlencode((string)$row['public_id'])?>';">
      Редактировать</button>
    <?php endif; ?>

<br>

<button type="button" onclick="window.location.href='delete_video.php?id=<?=urlencode((string)$row['public_id'])?>';">
      Удалить</button>
</center>
                        </td>
                        <td width="445" valign="top" style="padding-left:8px;">
                          <?php if ($show_owner_tools): ?>
                          <table width="100%" cellpadding="0" cellspacing="0" border="0" style="table-layout:fixed;"><tr valign="top">
                          <td valign="top">
                          <?php endif; ?>
                          <div class="moduleEntryTitle">
                            <a href="video.php?id=<?=$vid_link?>"><?=htmlspecialchars($row['title'])?></a>
                          </div>
                          <div class="moduleEntryDescription">
                          <span id="<?= $desc_id ?>-short">
                            <?= $desc_short ?><?php if (mb_strlen($desc) > 30): ?> <a href="#" onclick="return showDescMore('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(ещё)</a><?php endif; ?>
                          </span>
                          <span id="<?= $desc_id ?>-full" style="display:none;">
                            <?= $desc_full ?> <a href="#" onclick="return showDescless('<?= $desc_id ?>');" style="color:#0033cc; font-size:11px;">(меньше)</a>
                          </span>
                          <?php if (!empty($row['tags'])): ?>
                          <div class="vfacets">
                              <div class="moduleEntryTags">Теги //
                                <span class="vidTagsBegin-<?=$row['id']?>">
                                      <?php
                                      $tags = preg_split('/\s+/', trim($row['tags'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
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
                                      <span id="vidTagsRemain-<?=$row['id']?>" style="display: none;">
                                        <?php foreach ($hidden_tags as $tag):
                                          $tag = trim($tag);
                                          if (!empty($tag)):
                                        ?><a href="results.php?search_type=tag&search_query=<?=urlencode($tag)?>"><?=htmlspecialchars($tag)?></a> : <?php
                                          endif;
                                        endforeach;
                                        ?></span>&nbsp;<span id="vidTagsMore-<?=$row['id']?>" class="smallText">(<a href="#" class="eLink" onclick="showInline('vidTagsRemain-<?=$row['id']?>'); hideInline('vidTagsMore-<?=$row['id']?>'); showInline('vidTagsLess-<?=$row['id']?>'); return false;">ещё</a>)</span><span id="vidTagsLess-<?=$row['id']?>" class="smallText" style="display: none;">(<a href="#" class="eLink" onclick="hideInline('vidTagsRemain-<?=$row['id']?>'); hideInline('vidTagsLess-<?=$row['id']?>'); showInline('vidTagsMore-<?=$row['id']?>'); return false;">меньше</a>)</span>
                                      <?php endif; ?>
                                  </span>
                              </div>
                          </div>
                          <?php endif; ?>
                          <div class="moduleEntryDetails">
                            Добавлено: <?= time_ago(strtotime($row['time'])) ?> от <a href="channel.php?user=<?= htmlspecialchars($row['user']) ?>" style="color:#0033cc; text-decoration:underline;"><?= htmlspecialchars($row['user']) ?></a>
                          </div>
                          <div class="moduleEntryDetails">
                            Время: <?=get_video_duration_fast($row['file'], $row['id'], $row['public_id'] ?? '')?> | Просмотров: <?= intval($row['views']) ?> | Комментариев: <?= intval($comments_count) ?>
                          </div>
                          <?= channel_render_avg_stars_html($ra, $rc) ?>
                          </div>

                          <?php if ($show_owner_tools): ?>
                          </td>
                          <td width="1">
                          </td>
                          </tr>
                          <?php endif; ?>
                          <?php if ($show_owner_tools): ?>
                          <tr>
                          <td colspan="2">
                          

                          <div style="margin:5px 0;padding:0;height:0;font-size:1px;line-height:1px;border:0;border-bottom:1px dashed #999999;overflow:hidden;"></div>
                          <?php
                          $fn_disp = trim((string)($row['original_filename'] ?? ''));
                          if ($fn_disp === '') {
                              $fn_disp = (string)($row['file'] ?? '');
                              $fn_disp = $fn_disp !== '' ? basename($fn_disp) : 'video.mp4';
                          }
                          $pid_share = (string)($row['public_id'] ?? '');
                          $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                          $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
                          $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
                          $dir = str_replace('\\', '/', dirname($script));
                          if ($dir === '.' || $dir === '/') {
                              $indexPath = '/';
                              $videoPath = '/video.php';
                          } else {
                              $indexPath = rtrim($dir, '/') . '/';
                              $videoPath = rtrim($dir, '/') . '/video.php';
                          }
                          if ($pid_share !== '' && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $pid_share)) {
                              $share_video_url = $scheme . '://' . $host . $indexPath . '?v=' . rawurlencode($pid_share);
                          } else {
                              $share_video_url = $scheme . '://' . $host . $videoPath . '?id=' . rawurlencode((string)($row['public_id'] ?? $row['id']));
                          }
                          ?>
                          <div class="moduleEntryDetails">Файл: <?=htmlspecialchars($fn_disp)?>
                            <div class="moduleEntryDetails">Статус: <?php if ($row['private'] == 0): ?> <span style="color:#24692A;font-weight:bold">Публичное видео</span> <?php else: ?> <span style="color:#8C172A;font-weight:bold">Приватное видео</span> <?php endif; ?></div>
                            <input name="video_link" type="text" onclick="javascript:document.linkForm.video_link.focus();document.linkForm.video_link.select();" value="<?= htmlspecialchars($share_video_url, ENT_QUOTES, 'UTF-8') ?>" size="50" readonly="true" style="font-size: 10px; text-align: center;">
                            <div class="formFieldInfo">Поделитесь этим видео с друзьями! Скопируйте и вставьте ссылку выше в Email или на сайт.</div>
                          </div>
                            
                          
                          </td>
                          </tr>
                          </table>
                          <?php endif; ?>
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
                  
                  $vf = $public_only_view ? '&view=public' : '';
                  if ($start_page > 1) {
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos'.$vf.'&page=1">1</a></span>';
                      if ($start_page > 2) echo ' ... ';
                  }
                  
                  for ($i = $start_page; $i <= $end_page; $i++) {
                      if ($i == $page) {
                          echo '<span class="pagerCurrent">'.$i.'</span>';
                      } else {
                          echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos'.$vf.'&page='.$i.'">'.$i.'</a></span>';
                      }
                  }
                  
                  if ($end_page < $total_pages) {
                      if ($end_page < $total_pages - 1) echo ' ... ';
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos'.$vf.'&page='.$total_pages.'">'.$total_pages.'</a></span>';
                  }
                  
                  if ($page < $total_pages) {
                      echo '<span class="pagerNotCurrent"><a href="?user='.urlencode($user).'&tab=videos'.$vf.'&page='.($page + 1).'">Далее</a></span>';
                  }
                  ?>
                </div>
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
        <?php if ($show_owner_tools): ?>
          <div style="font-weight: bold; color: #333; margin: 0px 0px 5px 0px;">Мои теги:</div>
          <?php if (!empty($my_tags)): ?>
            <?php foreach ($my_tags as $rt): ?>
              <div style="padding: 0px 0px 4px 0px; color: #999;">&raquo; <a href="results.php?search_type=tag&amp;search_query=<?=urlencode((string)$rt['tag'])?>"><?=htmlspecialchars((string)$rt['tag'])?></a></div>
            <?php endforeach; ?>
          <?php else: ?>
            <div style="font-size:12px;color:#666;">Тегов пока нет.</div>
          <?php endif; ?>
        <?php else: ?>
          <?= channel_sidebar_nav_html($user, $sidebar_active, [
              'public' => $public_count_videos,
              'private' => $is_owner ? $private_count : 0,
              'fav' => $fav_count,
              'friends' => $fr_count,
          ]) ?>
        <?php endif; ?>
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
    $show_recs_block = isset($_SESSION['user'])
        ? user_recs_enabled($db, (string)$_SESSION['user'])
        : recs_default_enabled();
    
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'recent';
    if ($filter === 'recs' && !(isset($_SESSION['user']) && $show_recs_block)) {
        $filter = 'recent';
    }
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
        case 'random':
            $filter_name = 'Случайные';
            break;
        case 'linked':
            $filter_name = 'Ссылки';
            break;
        case 'featured':
            $filter_name = 'Рекомендуемые';
            break;
        case 'recs':
            $filter_name = 'Рекомендованные';
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
            $stmt = $db->prepare("SELECT v.id, v.public_id, v.title, v.preview, v.description, v.time, v.views, v.user, v.file, 
                COALESCE(AVG(r.rating),0) AS avg_rating, 
                COUNT(r.id) AS votes_count,
                (COALESCE(AVG(r.rating),0) * COUNT(r.id)) / (1 + COUNT(r.id)) AS weighted_rating
                FROM videos v
                LEFT JOIN ratings r ON r.video_id = v.id
                WHERE v.private = 0 AND NOT EXISTS (SELECT 1 FROM channel_moderation cm WHERE cm.user = v.user AND cm.shadow_banned = 1)
                GROUP BY v.id
                ORDER BY weighted_rating DESC, v.views DESC, v.id DESC");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;

        case 'discussed':
            $stmt = $db->prepare("
                SELECT v.id, v.public_id, v.title, v.preview, v.description, v.time, v.views, v.user, v.file,
                       COALESCE(cc.cnt, 0) AS comments_count
                FROM videos v
                LEFT JOIN (
                    SELECT video_id, COUNT(*) AS cnt
                    FROM comments
                    GROUP BY video_id
                ) cc ON cc.video_id = v.id
                WHERE v.private = 0 AND NOT EXISTS (SELECT 1 FROM channel_moderation cm WHERE cm.user = v.user AND cm.shadow_banned = 1)
                ORDER BY comments_count DESC, v.views DESC, v.id DESC
            ");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;
        case 'favorites':
            $stmt = $db->prepare("
                SELECT v.id, v.public_id, v.title, v.preview, v.description, v.time, v.views, v.user, v.file,
                       COALESCE(fv.cnt, 0) AS favorites_count
                FROM videos v
                LEFT JOIN (
                    SELECT video_id, COUNT(*) AS cnt
                    FROM user_favourites
                    GROUP BY video_id
                ) fv ON fv.video_id = v.id
                WHERE v.private = 0 AND NOT EXISTS (SELECT 1 FROM channel_moderation cm WHERE cm.user = v.user AND cm.shadow_banned = 1)
                ORDER BY favorites_count DESC, v.views DESC, v.id DESC
            ");
            $stmt->execute();
            $all_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total = count($all_videos);
            $total_pages = ceil($total / $per_page);
            $videos = array_slice($all_videos, $offset, $per_page);
            break;

        case 'random':
            $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE private = 0 AND " . visible_video_sql_condition('videos', 'user'));
            $stmt->execute();
            $total = $stmt->fetchColumn();
            $total_pages = ceil($total / $per_page);
            $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE private = 0 AND " . visible_video_sql_condition('videos', 'user') . " ORDER BY RANDOM() LIMIT $offset, $per_page");
            $stmt->execute();
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'recs':
            $videos = [];
            try {
                $viewerUser = isset($_SESSION['user']) ? (string)$_SESSION['user'] : null;
                $viewerIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
                $all_recs = recs_get_home_recommendations($db, $viewerUser, $viewerIp, 100);
                $total = count($all_recs);
                $total_pages = 1;
                $videos = $all_recs;
            } catch (Exception $e) {
                $videos = [];
                $total = 0;
                $total_pages = 0;
            }
            break;
    }
    
    if ($filter !== 'discussed' && $filter !== 'favorites' && $filter !== 'rated' && $filter !== 'random' && $filter !== 'recs') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM videos WHERE private = 0 AND " . visible_video_sql_condition('videos', 'user'));
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $total_pages = ceil($total / $per_page);
        
        $stmt = $db->prepare("SELECT id, public_id, title, preview, description, time, views, user, file FROM videos WHERE private = 0 AND " . visible_video_sql_condition('videos', 'user') . " ORDER BY $order_by LIMIT $offset, $per_page");
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/1999/REC-html401-19991224/loose.dtd">
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $filter_name ?> видео - RetroShow</title>
<link rel="stylesheet" href="img/styles.css" type="text/css">
<link rel="stylesheet" href="img/base.css" type="text/css">
<link rel="stylesheet" href="img/watch.css" type="text/css">
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
<meta name="description" content="Share your videos with friends and family">
<meta name="keywords" content="video,sharing,camera phone,video phone">
<script type="text/javascript" src="img/ui_ets.js"></script>
<script language="javascript" type="text/javascript">
onLoadFunctionList = new Array();
function performOnLoadFunctions() {
    for (var i in onLoadFunctionList) {
        onLoadFunctionList[i]();
    }
}
</script>
<!--[if lt IE 6]>
<style type="text/css">
#ratingMessage { display:none; }
</style>
<![endif]-->
<!--[if lte IE 6]>
<style type="text/css">
html, body {
	margin: 10px !important;
	padding: 0 !important;
}
.showingTable {
	padding: 8px 6px !important;
	margin: 0 !important;
}
.showingTable td {
	padding-top: 4px !important;
	padding-bottom: 4px !important;
    padding-left: 6px !important;
	line-height: 16px !important;
	vertical-align: middle !important;
}
</style>
<![endif]-->
</head>
<body onload="performOnLoadFunctions();">
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:0; border-collapse:collapse;">
<tr><td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<?php
$__ch_logo = (!empty($_SESSION['user']) && isset($db) && $db instanceof PDO) ? user_header_logo_src($db, (string)$_SESSION['user']) : 'img/logo_sm.gif';
$__ch_alt = ($__ch_logo === 'img/logo_sm_YT.gif') ? 'YouTube' : 'RetroShow';
?>
<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="<?= htmlspecialchars($__ch_logo, ENT_QUOTES, 'UTF-8') ?>" width="120" height="48" alt="<?= htmlspecialchars($__ch_alt, ENT_QUOTES, 'UTF-8') ?>" border="0" style="vertical-align: middle; "></a></td>
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
<?php $mail_unread = count_unread_mail($db, $_SESSION['user']); $mail_icon = $mail_unread > 0 ? 'img/mail_unread.gif' : 'img/mail.gif'; ?>
<td>Привет, <a href="channel.php?user=<?=urlencode($_SESSION['user'])?>"><?=htmlspecialchars($_SESSION['user'])?></a>!&nbsp;&nbsp;&nbsp;<a href="my_messages.php"><img src="<?= htmlspecialchars($mail_icon, ENT_QUOTES, 'UTF-8') ?>" id="mailico" border="0" alt=""></a>&nbsp;(<a href="my_messages.php"><?= (int) $mail_unread ?></a>)</td>					
							<td class="myAccountContainer" style="padding: 0px 0px 0px 5px;">|&nbsp;
							<?php $admins = @unserialize(RETROSHOW_ADMINS); if (in_array($_SESSION['user'], $admins, true)) {?>
								<td><a href="admin.php" style="font-weight: bold;color: #24692A">Админ-панель</a></td>
								<td style="padding: 0px 5px 0px 5px;">|</td>
							<?php } ?>
							<td><a href="logout.php">Выйти</a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
							
    <?php endif; ?>
</tr></table>
</td></tr></table>
</td></tr>
<tr valign="bottom">
		<td>
		
		<table cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<?php
				$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
				$tabs = [
					['scripts' => ['index.php'], 'label' => 'Главная', 'href' => 'index.php'],
					['scripts' => ['channel.php', 'favourites.php', 'friends.php', 'results.php', 'video.php'], 'label' => 'Смотреть&nbsp;видео', 'href' => 'channel.php'],
					['scripts' => ['upload.php'], 'label' => 'Загрузить&nbsp;видео', 'href' => 'upload.php'],
					['scripts' => ['my_friends_invite.php'], 'label' => 'Пригласить&nbsp;друзей', 'href' => 'my_friends_invite.php'],
				];
				$found = false;
				foreach ($tabs as $t) {
					if (in_array($current_script, $t['scripts'], true)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$current_script = 'index.php';
				}
				foreach ($tabs as $idx => $t):
					$is_active = in_array($current_script, $t['scripts'], true);
					$ml = ($idx === 0) ? 5 : 0;
					$tab_style = $is_active
						? 'background-color: #DDDDDD; margin: 5px 2px 0px ' . $ml . 'px; border-bottom: 1px solid #DDDDDD;'
						: 'background-color: #BECEEE; margin: 5px 2px 1px ' . $ml . 'px; border-bottom: none;';
				?>
				<td>
					<table style="<?= $tab_style ?>" cellpadding="0" cellspacing="0" border="0">
						<tbody><tr>
							<td><img src="/img/box_login_tl.gif" width="5" height="5"></td>
							<td><img src="/img/pixel.gif" width="1" height="5"></td>
							<td><img src="/img/box_login_tr.gif" width="5" height="5"></td>
						</tr>
						<tr>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
							<td style="padding: 0px 20px 5px 20px; font-size: 13px; font-weight: bold;">
								<a href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>"><?= $t['label'] ?></a>
							</td>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
						</tr>
					</tbody></table>
				</td>
				<?php endforeach; ?>
			</tr></tbody>
		</table>
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
    'favorites' => 'Избранные',
    'random' => 'Случайные',
];

if (isset($_SESSION['user']) && $show_recs_block):
    $filters['recs'] = 'Для вас';
endif;

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

<form name="searchForm" id="searchForm" method="GET" action="results.php" style="margin: 0; padding: 0;">
<table align="center" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 10px;">
	<tbody><tr>
		<td style="padding-right: 5px;"><input tabindex="1" type="text" value="<?=htmlspecialchars($_GET['search_query'] ?? '')?>" name="search_query" maxlength="128" style="color:#ff3333; font-size: 12px; width: 293px;"></td>
		<td><input type="submit" value="Искать видео"></td>
	</tr></tbody></table>
</form>

<script language="javascript">
	onLoadFunctionList.push(function () { document.searchForm.search_query.focus(); });
</script>

	<table width="770" align="center" cellpadding="0" cellspacing="0" border="0">
	<tr valign="top">
	<td style="padding-right: 15px;">
		<table width="770" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
		<tr>
			<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
			<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
			<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
		</tr>
		<tr>
			<td><img src="img/pixel.gif" width="5" height="1"></td>
			<td width="760" style="background-color:#DDD; background-image:url('img/table_results_bg.gif'); background-position:left top; background-repeat:repeat-x;">
			
			<div class="moduleTitleBar">
				<table width="100%" cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td style="font-size:14px; font-weight:bold; color:#444; text-align:left; padding-left: 5px;  padding-bottom: 5px;"><?= $filter_name ?> видео</td>
						<td style="font-size:12px; font-weight:bold; color:#444; text-align:right; padding-right:5px; padding-bottom: 7px; white-space:nowrap;">
							Видео <?= ($offset + 1) ?>-<?= min($offset + $per_page, $total) ?> из <?= $total ?>
						</td>
					</tr>
				</table>
			</div>
      <div style="padding: 0 5px 0 5px;">

			<?php if (empty($videos)): ?>
			<?php else: ?>
				<table width="100%" cellpadding="0" cellspacing="0" border="0" style="padding: 0 0 10px 0;">
				<?php
				$i = 0;
				foreach ($videos as $video):
					if ($i % 5 == 0) echo '<tr valign="top">';
					list($rc, $ra) = channel_get_rating_stats($db, $video['id']);
					$card_comments = isset($video['comments_count']) ? (int)$video['comments_count'] : 0;
					if ($card_comments <= 0) {
						try {
							$stCardCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
							$stCardCc->execute([(int)$video['id']]);
							$card_comments = (int)$stCardCc->fetchColumn();
						} catch (Exception $e) {
							$card_comments = 0;
						}
					}
					$title_display = mb_strlen($video['title']) > 20 ? mb_substr($video['title'], 0, 22) . '...' : $video['title'];
				?>
					<td width="20%" style="padding: 2px; vertical-align: top;">
						<div style="padding-left: 4px; padding-right: 4px; padding-bottom: 0px;">
							<div class="moduleFeaturedThumb" style="float: left; margin: 0px;">
								<a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $video['id'])?>"><img src="<?=htmlspecialchars($video['preview'])?>" width="120" height="90" border="0" style="display: block;"></a>
							</div>
							<div class="moduleFeaturedTitle" style="text-align:center; padding-top: 6px; clear: left;">
								<a href="video.php?id=<?=htmlspecialchars($video['public_id'] ?? $video['id'])?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($title_display)?></a>
							</div>
							<div class="moduleFeaturedDetails" style="text-align:center; clear: left;">
								Добавлено: <?=time_ago(strtotime($video['time']))?><br>
								от <a href="channel.php?user=<?=urlencode($video['user'])?>" style="color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($video['user'])?></a><br>
								Просмотров: <?=intval($video['views'])?> | Комм. <?=intval($card_comments)?>
                </div>
                <?php if ($ra > 0): ?>
                  <center><?= channel_render_avg_stars_html($ra, $rc, false) ?></center>
                <?php endif; ?>
							
						</div>
					</td>
				<?php
					$i++;
					if ($i % 5 == 0) echo '</tr>';
				endforeach;
				if ($i % 5 != 0) {
					for ($j = $i % 5; $j < 5; $j++) echo '<td width="20%"></td>';
					echo '</tr>';
				}
				?>
				</table>
        </div>

				<?php if ($total_pages > 1): ?>
				<div class="pagingDiv" style="background: #CCC; margin: 0px 0 0px 0; padding: 5px 0px; font-size: 13px; color: #333; font-weight: bold; text-align: right;">
					Стр.
					<?php
					$start_page = max(1, $page - 2);
					$end_page = min($total_pages, $page + 2);
					$filter_param = 'filter=' . urlencode($filter) . '&';
					if ($start_page > 1) {
						echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?' . $filter_param . 'page=1" style="color: #03C; text-decoration: underline;">1</a></span>';
						if ($start_page > 2) echo ' ... ';
					}
					for ($pi = $start_page; $pi <= $end_page; $pi++) {
						if ($pi == $page) {
							echo '<span class="pagerCurrent" style="color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer;">' . $pi . '</span>';
						} else {
							echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?' . $filter_param . 'page=' . $pi . '" style="color: #03C; text-decoration: underline;">' . $pi . '</a></span>';
						}
					}
					if ($end_page < $total_pages) {
						if ($end_page < $total_pages - 1) echo ' ... ';
						echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?' . $filter_param . 'page=' . $total_pages . '" style="color: #03C; text-decoration: underline;">' . $total_pages . '</a></span>';
					}
					if ($page < $total_pages) {
						echo '<span class="pagerNotCurrent" style="color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer;"><a href="?' . $filter_param . 'page=' . ($page + 1) . '" style="color: #03C; text-decoration: underline;">Далее</a></span>';
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
	</tr>
	</table>
	</td></tr></table>

        <table cellpadding="0" cellspacing="0" border="0" width="100%">
        <tbody>
        <tr>
            <td align="center" valign="middle" style="font-family: Arial, sans-serif; font-size: 12px; line-height: 16px; text-align:center;">
                
                <table cellpadding="0" cellspacing="0" border="0" width="400" align="center">
                <tr>
                    <td align="center">
                        <a href="about.php?p=whats_new">Что нового?</a> |
                        <a href="about.php">О сайте</a> | 
                        <a href="http://github.com/tankwars92/RetroShow">Исходный код</a> | 
                        <a href="http://downgrade-net.ru/">Downgrade Net</a>
                    </td>
                </tr>
                <tr>
                    <td style="height:2px; font-size:1px; line-height:1px;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center">
						<br>
                        Copyright © 2026 RetroShow | 
                        <a href="rss.php"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align:text-top;"></a>
                    </td>
                </tr>
                <tr>
                    <td style="height:2px; font-size:1px; line-height:1px;">&nbsp;</td>
                </tr>
                <tr>
                    <td align="center">
						<br>
                        <script src="//downgrade-net.ru/services/ring/ring.php"></script> 
                        <img src="//downgrade-net.ru/services/counter/index.php?id=21" alt="Downgrade Counter" border="0">
                    </td>
                </tr>
                </table>

            </td>
        </tr>
        </tbody>
        </table>
</body>
</html>

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
            $comment_clean = str_replace(["|", "\n", "\r"], [' ', ' ', ' '], $comment);
            $db->prepare("INSERT INTO profile_comments (profile_user, user, text, time) VALUES (?, ?, ?, ?)")
               ->execute([$user, $_SESSION['user'], $comment_clean, time()]);
            $new_pc_id = (int) $db->lastInsertId();
            log_event('comment_profile', [
                'comment_id' => (int)$new_pc_id,
                'profile_user' => (string)$user,
                'author' => (string)$_SESSION['user'],
            ]);
            if ($user !== $_SESSION['user']) {
                $snippet = function_exists('mb_strlen') && function_exists('mb_substr')
                    ? (mb_strlen($comment_clean, 'UTF-8') > 120 ? mb_substr($comment_clean, 0, 120, 'UTF-8') . '...' : $comment_clean)
                    : (strlen($comment_clean) > 120 ? substr($comment_clean, 0, 120) . '...' : $comment_clean);
                $topic = 'Пользователь «' . $_SESSION['user'] . '» оставил комментарий на вашем канале.';
                $body = $topic . "\n\n" . 'Текст:' . "\n" . $snippet;
                add_mail($db, $user, 'system', $topic, $body, 'profile_comment', $new_pc_id, null, null, $user);
            }
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
    <td colspan="2" style="background:#999999; color:#fff; font-weight:bold; padding:3px;">Оставить новый комментарий</td>
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
      <?=rus_date($now)?>, <?=date('H:i', $now)?>
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
    $comments = [];
    try {
        $stmtPc = $db->prepare("SELECT time, user, text FROM profile_comments WHERE profile_user = ? ORDER BY time ASC, id ASC");
        $stmtPc->execute([$user]);
        $comments = $stmtPc->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $comments = [];
    }

	$fav_count = 0;
	$comments_count = 0;
	try {
		$stmtFav = $db->prepare("SELECT COUNT(*) FROM user_favourites WHERE user = ?");
		$stmtFav->execute([$user]);
		$fav_count = (int)$stmtFav->fetchColumn();
	} catch (Exception $e) {}
	try {
		$stmtPc2 = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user = ?");
		$stmtPc2->execute([$user]);
		$comments_count = (int)$stmtPc2->fetchColumn();
	} catch (Exception $e) {}
	
	$stmt_total = $db->prepare("SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0");
	$stmt_total->execute([$user]);
	$total = $stmt_total->fetchColumn();

	echo '<div style="padding:8px 0 12px 0; text-align:center; font-size:13px;">';
	echo (!isset($_GET['tab']) || $_GET['tab'] == '') 
		? '<b>Профиль</b>' : '<a href="channel.php?user='.urlencode($user).'">Профиль</a>';
	echo ' | ';
	echo (isset($_GET['tab']) && $_GET['tab'] === 'videos')
		? '<b>Видео ('.$total.')</b>' : '<a href="channel.php?user='.urlencode($user).'&tab=videos&amp;view=public">Видео ('.$total.')</a>';
	echo ' | ';
	echo '<a href="favourites.php?user='.urlencode($user).'&from=channel">Избранное ('.$fav_count.')</a> | ';
	$fr_count = 0;
	try {
		$stmtFr = $db->prepare("SELECT COUNT(*) FROM user_friends WHERE user = ?");
		$stmtFr->execute([$user]);
		$fr_count = (int)$stmtFr->fetchColumn();
	} catch (Exception $e) {}
	echo '<a href="friends.php?user='.urlencode($user).'">Друзья ('.$fr_count.')</a> | ';
	echo (isset($_GET['tab']) && $_GET['tab'] === 'comments')
		? '<b>Комментарии ('.$comments_count.')</b>' : '<a href="channel.php?user='.urlencode($user).'&tab=comments">Комментарии ('.$comments_count.')</a>';
	echo '</div>';
    ?>
    <?php if (isset($_SESSION['user']) && $_SESSION['user'] === $user): ?>
    <div style="width:550px; margin:0 auto 8px auto; text-align:right; font-size:12px;">
      <a href="dl_comments.php?user=<?=urlencode($user)?>" style="color:#0033cc; text-decoration:underline;">Скачать комментарии (.txt)</a>
    </div>
    <?php endif; ?>
    <table width="550" align="center" cellpadding="0" cellspacing="0" border="1" bordercolor="#666666" style="border-collapse:collapse; border:1px solid #666666; border-color:#666666;">
      <tr>
        <td colspan="2" style="background:#999999; color:#fff; font-weight:bold; padding:3px; border-right:1px solid #666666;">
          Комментарии <?=htmlspecialchars($user)?>
        </td>
      </tr>
      <?php if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2'): ?>
      <tr>
        <td colspan="2" style="padding:5px; text-align:center; background:#F4F4F4; border-right:1px solid #666666;">Этот пользователь отключил возможность комментирования своего профиля.</td>
      </tr>
      <?php elseif (count($comments) == 0): ?>
      <tr>
        <td colspan="2" style="padding:20px; text-align:center; color:#888; border-right:1px solid #666666;">Нет комментариев.</td>
      </tr>
      <?php else: foreach (array_reverse($comments) as $c): ?>
      <tr>
        <td width="110" style="background:#f8f8f8; text-align:center; padding:8px; border-right:1px solid #666666;">
          <a href="channel.php?user=<?=htmlspecialchars($c['user'])?>" style="font-weight:bold; color:#0033cc; text-decoration:underline;"><?=htmlspecialchars($c['user'])?></a><br>
          <?php $pi = get_user_profile_icon_setting($c['user']); $avatar = get_profile_icon($c['user'], $pi); ?>
          <br><img src="<?= $avatar ?>" width="64" height="50" style="border:1px solid #666666; background:#eee;">
        </td>
        <td style="padding:8px; vertical-align:top; border-right:1px solid #666666;">
          <div style="color:#888; font-size:13px;"><b><?=rus_date($c['time'])?></b></div>
		  <br>
          <div style="font-size:13px;color:#222;word-break:break-all;width:320px;"><?=channel_comment_body_html($c['text'] ?? '')?></div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      
      <?php if ($user_data && isset($user_data['profile_comm']) && $user_data['profile_comm'] === '2'): ?>

          <?php else: ?>
            <tr>
        <td colspan="2" style="padding:10px; background:#f8f8f8; text-align:center; border-right:1px solid #666666;">
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

<link rel="stylesheet" href="img/retroshow" type="text/css">
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
</style>
</head>
<body onload="performOnLoadFunctions();" style="margin:0; padding:0;">
<table width="800" cellpadding="0" cellspacing="0" border="0" align="center" style="margin-top:0; border-collapse:collapse;">
<tr><td bgcolor="#FFFFFF" style="padding-bottom: 25px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<?php
$__ch_logo = (!empty($_SESSION['user']) && isset($db) && $db instanceof PDO) ? user_header_logo_src($db, (string)$_SESSION['user']) : 'img/logo_sm.gif';
$__ch_alt = ($__ch_logo === 'img/logo_sm_YT.gif') ? 'YouTube' : 'RetroShow';
?>
<td width="130" rowspan="2" style="padding: 0px 5px 5px 5px;"><a href="index.php"><img src="<?= htmlspecialchars($__ch_logo, ENT_QUOTES, 'UTF-8') ?>" width="120" height="48" alt="<?= htmlspecialchars($__ch_alt, ENT_QUOTES, 'UTF-8') ?>" border="0" style="vertical-align: middle; "></a></td>
<td valign="top">
<table width="670" cellpadding="0" cellspacing="0" border="0">
<tr valign="top">
<td style="padding: 0px 5px 0px 5px; font-style: italic;">Загружайте и делитесь видео по всему миру!</td>
<td align="right">
<table cellpadding="0" cellspacing="0" border="0"><tr>
    <?php if (!isset($_SESSION['user'])): ?>
<td><a href="register.php"><strong>Регистрация</strong></a></td>
<td style="padding: 0px 5px 0px 5px;">|</td>
<td><a href="login.php">Вход</a></td>x`
<td style="padding: 0px 5px 0px 5px;">|</td>
<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
    <?php else: ?>
<?php $mail_unread = count_unread_mail($db, $_SESSION['user']); $mail_icon = $mail_unread > 0 ? 'img/mail_unread.gif' : 'img/mail.gif'; ?>
<td>Привет, <a href="channel.php?user=<?=urlencode($_SESSION['user'])?>"><?=htmlspecialchars($_SESSION['user'])?></a>!&nbsp;&nbsp;&nbsp;<a href="my_messages.php"><img src="<?= htmlspecialchars($mail_icon, ENT_QUOTES, 'UTF-8') ?>" id="mailico" border="0" alt=""></a>&nbsp;(<a href="my_messages.php"><?= (int) $mail_unread ?></a>)</td>					
							<td class="myAccountContainer" style="padding: 0px 0px 0px 5px;">|&nbsp;
							<?php $admins = @unserialize(RETROSHOW_ADMINS); if (in_array($_SESSION['user'], $admins, true)) {?>
								<td><a href="admin.php" style="font-weight: bold;color: #24692A">Админ-панель</a></td>
								<td style="padding: 0px 5px 0px 5px;">|</td>
							<?php } ?>
							<td><a href="logout.php">Выйти</a></td>
							<td style="padding: 0px 5px 0px 5px;">|</td>
							<td style="padding-right: 5px;"><a href="help.php">Помощь</a></td>
    <?php endif; ?>
</tr></table>
</td></tr></table>
</td></tr>
<tr valign="bottom">
		<td>
		
		<table cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<?php
				$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
				$tabs = [
					['scripts' => ['index.php'], 'label' => 'Главная', 'href' => 'index.php'],
					['scripts' => ['channel.php', 'favourites.php', 'friends.php', 'results.php', 'video.php'], 'label' => 'Смотреть&nbsp;видео', 'href' => 'channel.php'],
					['scripts' => ['upload.php'], 'label' => 'Загрузить&nbsp;видео', 'href' => 'upload.php'],
					['scripts' => ['my_friends_invite.php'], 'label' => 'Пригласить&nbsp;друзей', 'href' => 'my_friends_invite.php'],
				];
				$found = false;
				foreach ($tabs as $t) {
					if (in_array($current_script, $t['scripts'], true)) {
						$found = true;
						break;
					}
				}
				if (!$found) {
					$current_script = 'index.php';
				}
				foreach ($tabs as $idx => $t):
					$is_active = in_array($current_script, $t['scripts'], true);
					$ml = ($idx === 0) ? 5 : 0;
					$tab_style = $is_active
						? 'background-color: #DDDDDD; margin: 5px 2px 0px ' . $ml . 'px; border-bottom: 1px solid #DDDDDD;'
						: 'background-color: #BECEEE; margin: 5px 2px 1px ' . $ml . 'px; border-bottom: none;';
				?>
				<td>
					<table style="<?= $tab_style ?>" cellpadding="0" cellspacing="0" border="0">
						<tbody><tr>
							<td><img src="/img/box_login_tl.gif" width="5" height="5"></td>
							<td><img src="/img/pixel.gif" width="1" height="5"></td>
							<td><img src="/img/box_login_tr.gif" width="5" height="5"></td>
						</tr>
						<tr>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
							<td style="padding: 0px 20px 5px 20px; font-size: 13px; font-weight: bold;">
								<a href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>"><?= $t['label'] ?></a>
							</td>
							<td><img src="/img/pixel.gif" width="5" height="1"></td>
						</tr>
					</tbody></table>
				</td>
				<?php endforeach; ?>
			</tr></tbody>
		</table>
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
    <?php else: ?>
      <?php foreach ($videos as $row): ?>
        <div class="moduleEntry">
          <table width="565" cellpadding="0" cellspacing="0" border="0">
            <tr valign="top">
              <td><a href="video.php?id=<?=htmlspecialchars($row['public_id'] ?? $row['id'])?>"><img src="<?=htmlspecialchars($row['preview'])?>" class="moduleEntryThumb" width="120" height="90"></a></td>
              <td width="100%">
                <div class="moduleEntryTitle"><a href="video.php?id=<?=htmlspecialchars($row['public_id'] ?? $row['id'])?>" style="color:#0033cc; text-decoration:none; font-size:15px; font-weight:bold;"><?=htmlspecialchars($row['title'])?></a></div>
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
                <div class="moduleEntryDetails">Просмотров: <?=intval($row['views'])?> | Комментариев: <?php
                  try {
                      $stmtCc = $db->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
                      $stmtCc->execute([intval($row['id'])]);
                      echo intval($stmtCc->fetchColumn());
                  } catch (Exception $e) {
                      echo 0;
                  }
                ?></div>
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
<td align="center" valign="center"><span class="footer"><a href="about.php?p=whats_new">Что нового?</a> | <a href="about.php">О сайте</a> | <a href="http://github.com/tankwars92/retroshow">Исходный код</a> | <a href="http://downgrade-net.ru/">Downgrade Net</a>
<br><br>Copyright © 2026 RetroShow | <a href="rss.php"><img src="img/rss.gif" width="36" height="14" border="0" style="vertical-align: text-top;"></a></span></td>
</tr>
</table>

</body></html>

