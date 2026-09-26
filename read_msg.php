<?php
include 'init.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$me = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_mail'])) {
    $del_id = (int) ($_POST['mail_id'] ?? 0);
    $is_sent_delete = isset($_POST['delete_sent']) && (string) $_POST['delete_sent'] === '1';
    if ($del_id > 0) {
        try {
            if ($is_sent_delete) {
                $st = $db->prepare('DELETE FROM mail_inbox WHERE id = ? AND from_user = ?');
                $st->execute([$del_id, $me]);
            } else {
                $st = $db->prepare('DELETE FROM mail_inbox WHERE id = ? AND to_user = ?');
                $st->execute([$del_id, $me]);
            }
        } catch (Exception $e) {
        }
    }
    header('Location: ' . ($is_sent_delete ? 'outbox.php' : 'my_messages.php'));
    exit;
}

include 'template.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$s = isset($_GET['s']) ? (string) $_GET['s'] : '';
$s_q = rawurlencode($s);
$is_sent_view = isset($_GET['sent']) && (string) $_GET['sent'] !== '' && (string) $_GET['sent'] !== '0';

$msg = null;
if ($id > 0) {
    try {
        if ($is_sent_view) {
            $st = $db->prepare('SELECT id, to_user, from_user, topic, content, sent_at, kind, comment_id, video_id, video_public_id, channel_login FROM mail_inbox WHERE id = ? AND from_user = ?');
            $st->execute([$id, $me]);
        } else {
            $st = $db->prepare('SELECT id, to_user, from_user, topic, content, sent_at, kind, comment_id, video_id, video_public_id, channel_login FROM mail_inbox WHERE id = ? AND to_user = ?');
            $st->execute([$id, $me]);
        }
        $msg = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

if (!$msg) {
    header('Location: ' . ($is_sent_view ? 'outbox.php' : 'my_messages.php'));
    exit;
}

if (!$is_sent_view) {
    mark_mail_seen($db, $id, $me);
}

$from = (string) $msg['from_user'];
$from_h = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
$to_user = (string) ($msg['to_user'] ?? '');
$to_h = htmlspecialchars($to_user, ENT_QUOTES, 'UTF-8');
$body_h = htmlspecialchars((string) $msg['content'], ENT_QUOTES, 'UTF-8');
$topic_raw = trim((string) ($msg['topic'] ?? ''));
$topic_h = htmlspecialchars($topic_raw !== '' ? $topic_raw : mail_list_preview((string) $msg['content']), ENT_QUOTES, 'UTF-8');
$kind = (string) ($msg['kind'] ?? '');

$months_ru = [
    1 => t('января'), 2 => t('февраля'), 3 => t('марта'), 4 => t('апреля'),
    5 => t('мая'), 6 => t('июня'), 7 => t('июля'), 8 => t('августа'),
    9 => t('сентября'), 10 => t('октября'), 11 => t('ноября'), 12 => t('декабря'),
];
$ts = (int) $msg['sent_at'];
$sent_str = (int) date('j', $ts) . ' ' . $months_ru[(int) date('n', $ts)] . ' ' . date('Y', $ts)
    . ', ' . date('H:i', $ts);

$vid_count = 0;
$fav_count = 0;
if (!$is_sent_view) {
    try {
        $st = $db->prepare('SELECT COUNT(*) FROM videos WHERE user = ? AND private = 0');
        $st->execute([$from]);
        $vid_count = (int) $st->fetchColumn();
        $st2 = $db->prepare('SELECT COUNT(*) FROM user_favourites WHERE user = ?');
        $st2->execute([$from]);
        $fav_count = (int) $st2->fetchColumn();
    } catch (Exception $e) {
    }
}

if ($is_sent_view) {
    $n = mail_sent_prev_next($db, $me, $id);
} else {
    $n = mail_prev_next($db, $me, $id);
}
$prev_href = '';
if ($n['prev'] !== null) {
    $prev_href = 'read_msg.php?id=' . (int) $n['prev'] . ($is_sent_view ? '&sent=1' : '') . '&s=' . $s_q;
}
$next_href = '';
if ($n['next'] !== null) {
    $next_href = 'read_msg.php?id=' . (int) $n['next'] . ($is_sent_view ? '&sent=1' : '') . '&s=' . $s_q;
}

$ctx_link = '';
$ctx_label = '';
$ch = $msg['channel_login'] ?? null;
$pub = $msg['video_public_id'] ?? null;
if ($kind === 'profile_comment' && $ch) {
    $ctx_link = 'channel.php?user=' . rawurlencode((string) $ch);
    $ctx_label = t('Перейти на канал.');
} elseif ($kind === 'video_reply' && $pub) {
    $ctx_link = 'video.php?id=' . rawurlencode((string) $pub) . '#comments';
    $ctx_label = t('Перейти к ветке комментариев под видео.');
} elseif ($kind === 'video_comment' && $pub) {
    $ctx_link = 'video.php?id=' . rawurlencode((string) $pub) . '#comments';
    $ctx_label = t('Перейти к комментариям к видео.');
}

showHeader('Сообщение');
?>
<?php if ($is_sent_view): ?>
<div class="tableSubTitle"><?= t('Исходящие сообщения') ?></div>
<table width="45%" align="center" cellpadding="5" cellspacing="0" border="0">
    <tr align="center">
        <td align="center" colspan="3">
            <a href="my_messages.php"><?= t('Входящие сообщения') ?></a> | <a href="outbox.php" class="bold"><?= t('Исходящие сообщения') ?></a>
        </td>
    </tr>
</table>
<?php endif; ?>
<table width="100%" align="center" cellpadding="1" cellspacing="1" border="0" bgcolor="#EEEEEE">
<tbody>
<tr>
	<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""> <?php if ($prev_href !== ''): ?><a href="<?= htmlspecialchars($prev_href, ENT_QUOTES, 'UTF-8') ?>">&lt;&lt; <?= t('Назад') ?></a><?php endif; ?><?php if ($prev_href !== '' && $next_href !== ''): ?> | <?php endif; ?><?php if ($next_href !== ''): ?><a href="<?= htmlspecialchars($next_href, ENT_QUOTES, 'UTF-8') ?>"><?= t('Далее') ?> &gt;&gt;</a><?php endif; ?></td>
</tr>
<tr>
	<td>

	<table width="75%" cellpadding="4" cellspacing="9" align="center" border="0">
	<tbody>
	<tr><td colspan="2" height="10" width="35" align="right"></td></tr>
	<?php if ($is_sent_view): ?>
	<tr valign="top">
		<td align="right" valign="top"><span class="label"><?= t('Кому:') ?></span></td>
		<td><a href="channel.php?user=<?= rawurlencode($to_user) ?>"><?= $to_h ?></a></td>
	</tr>
	<?php else: ?>
	<tr valign="top">
		<td align="right" valign="top"><span class="label"><?= t('От:') ?></span></td>
		<td><a href="channel.php?user=<?= rawurlencode($from) ?>"><?= $from_h ?></a></td>
	</tr>
	<tr>
		<td align="right">&nbsp;</td>
		<td><a href="channel.php?user=<?= rawurlencode($from) ?>&amp;tab=videos"><?= t('Видео') ?></a> (<?= (int) $vid_count ?>) | <a href="favourites.php?user=<?= rawurlencode($from) ?>"><?= t('Избранное') ?></a> (<?= (int) $fav_count ?>)</td>
	</tr>
	<?php endif; ?>
	<tr valign="top">
		<td align="right" valign="top"><span class="label"><?= t('Отправлено:') ?></span></td>
		<td><?= htmlspecialchars($sent_str, ENT_QUOTES, 'UTF-8') ?></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label"><?= t('Тема:') ?></span></td>
		<td><?= $topic_h ?></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"><span class="label"><?= t('Сообщение:') ?></span></td>
		<td><div class="mailMessageArea"><?= nl2br($body_h) ?><?php if ($ctx_link !== '' && $ctx_label !== ''): ?><div style="margin-top:12px; padding-top:10px; border-top:1px dashed #999999;"><strong><a href="<?= htmlspecialchars($ctx_link, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctx_label, ENT_QUOTES, 'UTF-8') ?></a></div><?php endif; ?></div></td>
	</tr>
	<tr valign="top">
		<td align="right" valign="top"></td>
		<td><form method="post" action="read_msg.php" onsubmit="return confirm(<?= t_js_attr('Подтвердите: сообщение будет удалено без возможности восстановления.') ?>);">
			<?php if ($is_sent_view): ?><input type="hidden" name="delete_sent" value="1"><?php endif; ?>
			<input type="hidden" name="delete_mail" value="1">
			<input type="hidden" name="mail_id" value="<?= (int) $id ?>">
			<input type="submit" value="<?= htmlspecialchars(t('Удалить сообщение'), ENT_QUOTES, 'UTF-8') ?>" style="width:135px;">
		</form></td>
	</tr>
	</tbody>
	</table>

	</td>
</tr>
<tr>
	<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
</tr>
</tbody>
</table>
<?php
showFooter();
