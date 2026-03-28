<?php
include 'init.php';
include 'template.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$me = $_SESSION['user'];
showHeader('Сообщения');

$months_ru = [
    1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
    5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
    9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
];
$days_ru = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

$total = 0;
try {
    $st = $db->prepare('SELECT COUNT(*) FROM mail_inbox WHERE to_user = ?');
    $st->execute([$me]);
    $total = (int) $st->fetchColumn();
} catch (Exception $e) {
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 5;
$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$rows = [];
try {
    $lim = (int) $per_page;
    $off = (int) $offset;
    $st = $db->prepare("SELECT id, from_user, topic, content, sent_at, seen_at FROM mail_inbox WHERE to_user = ? ORDER BY sent_at DESC, id DESC LIMIT {$lim} OFFSET {$off}");
    $st->execute([$me]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
}
?>
<style>
.channelPagingDiv { background: #CCC; margin: 0; padding: 5px 0; font-size: 13px; color: #333; font-weight: bold; text-align: right; }
.channelPagingDiv .pagerCurrent { color: #333; background-color: #FFF; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent { color: #03C; background-color: #CCC; padding: 1px 4px; border: 1px solid #999; margin-right: 5px; text-decoration: underline; cursor: pointer; }
.channelPagingDiv .pagerNotCurrent a { color: #03C; text-decoration: underline; }
</style>
<table width="600px" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#CCCCCC">
<tbody>
<tr>
	<td><img src="img/box_login_tl.gif" width="5" height="5" alt=""></td>
	<td width="100%"><img src="img/pixel.gif" width="1" height="5" alt=""></td>
	<td><img src="img/box_login_tr.gif" width="5" height="5" alt=""></td>
</tr>
<tr>
	<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
	<td>
	<div class="moduleTitleBar">
	<div class="moduleTitle"><div style="float: right; padding: 1px 5px 0px 0px; font-size: 12px;">Сообщения <?= $total ? ($offset + 1) . '-' . min($offset + $per_page, $total) . ' из ' . $total : '0 из 0' ?></div>
		Сообщения // Входящие
	</div>
	</div>

	<table width="100%" cellpadding="3" cellspacing="0" align="center" border="0">
	<tbody>
	<tr><td colspan="5" height="10"></td></tr>
	<tr>
		<td width="20">&nbsp;</td>
		<td><b>Сообщение</b></td>
		<td width="20">&nbsp;</td>
		<td width="70"><b>От</b></td>
		<td width="160"><b>Дата</b></td>
	</tr>
<?php foreach ($rows as $row):
    $mid = (int) $row['id'];
    $is_read = isset($row['seen_at']) && $row['seen_at'] !== null && $row['seen_at'] !== '';
    $bg = $is_read ? '#eeeeee' : '#FFCC66';
    $icon = $is_read ? 'mail.gif' : 'mail_unread.gif';
    $from = (string) $row['from_user'];
    $from_h = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
    $topic_raw = trim((string) ($row['topic'] ?? ''));
    $preview = $topic_raw !== '' ? mail_list_preview($topic_raw) : mail_list_preview((string) $row['content']);
    $preview_h = htmlspecialchars($preview, ENT_QUOTES, 'UTF-8');
    $ts = (int) $row['sent_at'];
    $date_str = $days_ru[(int) date('w', $ts)] . ', ' . (int) date('j', $ts) . ' ' . $months_ru[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    $link_class = $is_read ? '' : ' class="bold"';
    $href = 'read_msg.php?id=' . $mid . '&amp;s=';
?>
	<tr bgcolor="<?= htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') ?>">
		<td width="5"><img src="img/<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" alt=""></td>
		<td><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"<?= $link_class ?>><?= $preview_h ?></a></td>
		<td width="20">&nbsp;</td>
		<td><a href="channel.php?user=<?= rawurlencode($from) ?>"><?= $from_h ?></a></td>
		<td><?= htmlspecialchars($date_str, ENT_QUOTES, 'UTF-8') ?></td>
	</tr>
<?php endforeach; ?>
	</tbody>
	</table>

<?php if ($total_pages > 1): ?>
	<div class="channelPagingDiv pagingDiv">
		Стр.
		<?php
		$start_page = max(1, $page - 2);
		$end_page = min($total_pages, $page + 2);
		if ($start_page > 1) {
			echo '<span class="pagerNotCurrent"><a href="?page=1">1</a></span>';
			if ($start_page > 2) {
				echo ' ... ';
			}
		}
		for ($i = $start_page; $i <= $end_page; $i++) {
			if ($i == $page) {
				echo '<span class="pagerCurrent">' . $i . '</span>';
			} else {
				echo '<span class="pagerNotCurrent"><a href="?page=' . $i . '">' . $i . '</a></span>';
			}
		}
		if ($end_page < $total_pages) {
			if ($end_page < $total_pages - 1) {
				echo ' ... ';
			}
			echo '<span class="pagerNotCurrent"><a href="?page=' . $total_pages . '">' . $total_pages . '</a></span>';
		}
		if ($page < $total_pages) {
			echo '<span class="pagerNotCurrent"><a href="?page=' . ($page + 1) . '">Далее</a></span>';
		}
		?>
	</div>
<?php endif; ?>

	</td>
	<td><img src="img/pixel.gif" width="5" height="1" alt=""></td>
</tr>
<tr>
	<td><img src="img/box_login_bl.gif" width="5" height="5" alt=""></td>
	<td><img src="img/pixel.gif" width="1" height="5" alt=""></td>
	<td><img src="img/box_login_br.gif" width="5" height="5" alt=""></td>
</tr>
</tbody>
</table>
<?php
showFooter();
