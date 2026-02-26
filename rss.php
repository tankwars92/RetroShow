<?php
header('Content-Type: application/rss+xml; charset=utf-8');

require_once 'init.php';

$stmt = $db->prepare("SELECT * FROM videos WHERE private = 0 ORDER BY id DESC LIMIT 15");
$stmt->execute();
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>

<rss version="2.0">
<channel>
<title>RetroShow - Последние 10 видео.</title>
<description>Последние 10 загруженных видеороликов на RetroShow.</description>
<link><?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . '/') ?></link>
<language>ru-RU</language>
<generator>PHP</generator>
<lastBuildDate><?= date(DATE_RSS) ?></lastBuildDate>

<?php foreach ($videos as $video): ?>
<item>
<title><?= htmlspecialchars($video['title']) ?></title>
<description><?= htmlspecialchars($video['description']) ?></description>
<author><?= htmlspecialchars($video['user']) ?></author>
<pubDate><?= date(DATE_RSS, strtotime($video['time'])) ?></pubDate>
<link><?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . '/video.php?id=' . $video['id']) ?></link>
<guid><?= htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . '/video.php?id=' . $video['id']) ?></guid>
</item>
<?php endforeach; ?>

</channel>
</rss>