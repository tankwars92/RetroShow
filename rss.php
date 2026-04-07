<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

function rss_xml_text(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function rss_pub_date(string $createdAt): string
{
    $dt = parse_status_utc($createdAt);
    if ($dt === null) {
        return gmdate('r');
    }

    return $dt->setTimezone(new DateTimeZone('UTC'))->format('r');
}

$username = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
if ($username === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

$profile = user_by_username($username);
if ($profile === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

$userId = (int) $profile['id'];
$displayName = (string) $profile['display_name'];
$uname = (string) $profile['username'];
$rows = statuses_for_user($userId, 100);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = str_replace('\\', '/', $scriptDir);
$basePath = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
$base = $scheme . '://' . $host . $basePath;

$profileUrl = $base . '/user.php?id=' . rawurlencode($uname);

header('Content-Type: application/rss+xml; charset=UTF-8');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
<title><?= rss_xml_text('Twitbirth / ' . $displayName) ?></title>
<link><?= rss_xml_text($profileUrl) ?></link>
<description><?= rss_xml_text('Twitbirth updates from ' . $displayName) ?></description>
<language>en-us</language>
<ttl>40</ttl>
<?php
foreach ($rows as $s) {
    $sid = (int) $s['id'];
    $body = (string) $s['body'];
    $itemTitle = $displayName . ': ' . $body;
    $itemDesc = $itemTitle;
    $statusUrl = $base . '/status.php?id=' . $sid;
    $pub = rss_pub_date((string) $s['created_at']);
    ?>
<item>
<title><?= rss_xml_text($itemTitle) ?></title>
<description><?= rss_xml_text($itemDesc) ?></description>
<pubDate><?= rss_xml_text($pub) ?></pubDate>
<guid><?= rss_xml_text($statusUrl) ?></guid>
<link><?= rss_xml_text($statusUrl) ?></link>
</item>
<?php
}
?>
</channel>
</rss>
