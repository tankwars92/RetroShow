<?php 
include("init.php");
include("template.php");

$p = isset($_GET['p']) ? $_GET['p'] : '';
if ($p == 'whats_new') {
	$title = 'Что нового';
} else {
	$title = 'О нас';
}

function about_blog_body_html(string $text): string {
    return nl2br(trim($text));
}

showHeader($title);
?>

<table width="775" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;">
		
		<table width="775"  cellpadding="0" cellspacing="0" border="0">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td width="100%"><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td style="padding: 5px 0px 5px 0px;">
				

<?php if ($p == 'whats_new') { ?>

<?php
$posts = [];
try {
    $st = $db->query('SELECT title, body, title_en, body_en FROM blog_posts ORDER BY created_at DESC, id DESC');
    $posts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $posts = [];
}
if ($posts !== []):
    foreach ($posts as $post):
        $title_ru = trim((string)($post['title'] ?? ''));
        $body_ru = trim((string)($post['body'] ?? ''));
        $title_en = trim((string)($post['title_en'] ?? ''));
        $body_en = trim((string)($post['body_en'] ?? ''));
?>
<?php if ($title_ru !== '' || $body_ru !== ''): ?>
<div class="tableSubTitle"><?= $title_ru !== '' ? htmlspecialchars($title_ru, ENT_QUOTES, 'UTF-8') : 'RU' ?></div>
<?= about_blog_body_html($body_ru) ?>
<br>
<?php endif; ?>
<?php if ($title_en !== '' || $body_en !== ''): ?>
<div class="tableSubTitle"><?= $title_en !== '' ? htmlspecialchars($title_en, ENT_QUOTES, 'UTF-8') : 'EN' ?></div>
<?= about_blog_body_html($body_en) ?>
<br>
<?php endif; ?>
<br>
<?php
    endforeach;
else:
?>
<?= t('Пока нет новостей. Загляните позже!') ?>
<?php endif; ?>

<?php } else { ?>

<div class="tableSubTitle"><?= t('О нас') ?></div>

<span class="highlight"><?= t('Про RetroShow') ?></span>
<br><br>
<?= t('RetroShow - это небольшой сайт, который стилизован под YouTube образца августа 2005 года, хотя и не повторяет его полностью (поскольку наша конечная цель - не воспроизведение YouTube один в один).') ?>
<br><br>
<span class="highlight"><?= t('Что такое RetroShow?') ?></span>

<br><br>
<?= t('RetroShow - это способ поделиться своими видео с теми, кто вам дорог. С RetroShow вы можете:') ?>

<ul>
<li> <?= t('Демонстрировать свои любимые видео всему миру') ?>
</li><li> <?= t('Снимать на видео своих собак, кошек и других домашних животных') ?>
</li><li> <?= t('Публиковать в блоге видео, снятые на цифровую камеру или мобильный телефон') ?>
</li><li> <?= t('Безопасно и конфиденциально показывать видео своим друзьям и близким по всему миру') ?>
</li><li> <?= t('... и многое, многое другое!') ?>
</li></ul>
<br><span class="highlight"><a href="register.php"><?= t('Зарегистрируйтесь сейчас') ?></a> <?= t('и создайте бесплатный аккаунт.') ?></span>
<br><br> <br>

<?= t('Чтобы узнать больше о нашем сервисе, посетите раздел') ?> <a href="help.php"><?= t('Помощь') ?></a>.<br>

<br><span class="highlight"><?= t('Спасибо!') ?></span>
<ul>
<li><strong><a href="channel.php?user=BitByByte">BitByByte</a></strong> - <?= t('разработчик движка сайта.') ?></li>
<li><strong><a href="channel.php?user=dsalin">dsalin</a></strong> - <?= t('первый пользователь сайта, который занимался тестированием.') ?></li>
</ul>

<?php } ?>

				</td>
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

<?php showFooter(); ?>