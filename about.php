<?php 
include("init.php");
include("template.php");

$p = isset($_GET['p']) ? $_GET['p'] : '';
if ($p == 'whats_new') {
	$title = 'Что нового';
} else {
	$title = 'О нас';
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

<div class="tableSubTitle">30 марта, 2026 г.</div>

Здравствуйте, уважаемые пользователи! Вот и заработал раздел <b>«Что нового»</b>. Что касается недавних обновлений, то появилась система личных сообщений, но на данный момент она работает лишь в одну сторону — входящие. Туда вам будут приходить системные оповещения, такие как кто-то прокомментировал ваше видео, ваш канал или ответил в вашей ветке комментариев.

<br><br>
Также появилась возможность прикреплять к вашим комментариям видео, выбирая как из ваших собственных загруженных видео, так и из тех видео, которые вы добавили в избранное.

<br><br>
Надеюсь, вам, уважаемые пользователи, будет полезно это обновление! Спасибо за внимание.

<br><br>
Помните, что скачать исходный код движка вы всегда сможете в <a href="https://github.com/tankwars92/RetroShow">соответствующем GitHub-репозитории</a>.

<?php } else { ?>

<div class="tableSubTitle">О нас</div>

<span class="highlight">Про RetroShow</span>
<br><br>
RetroShow - это небольшой сайт, который стилизован под YouTube образца августа 2005 года, хотя и не повторяет его полностью (поскольку наша конечная цель - не воспроизведение YouTube один в один).
<br><br>
<span class="highlight">Что такое RetroShow?</span>

<br><br>
RetroShow - это способ поделиться своими видео с теми, кто вам дорог. С RetroShow вы можете:

<ul>
<li> Демонстрировать свои любимые видео всему миру
</li><li> Снимать на видео своих собак, кошек и других домашних животных
</li><li> Публиковать в блоге видео, снятые на цифровую камеру или мобильный телефон
</li><li> Безопасно и конфиденциально показывать видео своим друзьям и близким по всему миру
</li><li> ... и многое, многое другое!
</li></ul>
<br><span class="highlight"><a href="register.php">Зарегистрируйтесь сейчас</a> и создайте бесплатный аккаунт.</span>
<br><br> <br>

Чтобы узнать больше о нашем сервисе, посетите раздел <a href="help.php">Помощь</a>.<br>

<br><span class="highlight">Спасибо!</span>
<ul>
<li><strong><a href="channel.php?user=BitByByte">BitByByte</a></strong> - разработчик движка сайта.</li>
<li><strong><a href="channel.php?user=dsalin">dsalin</a></strong> - первый пользователь сайта, который занимался тестированием.</li>
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