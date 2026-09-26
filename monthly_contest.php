<?php
include("init.php"); 
include("template.php");

showHeader("Ежемесячный конкурс");
?>

<div style="padding: 0px 5px 0px 5px;">

<div class="tableSubTitle"><?= t('Ежемесячный конкурс') ?></div>

<table width="775" align="center" cellpadding="0" cellspacing="0" border="0">
	<tbody><tr valign="top">
		<td style="padding-right: 15px;" width="510">
		
		<span class="highlight"><?= t('Что это?') ?></span>
		<br><br><?= t('Ля-ля-ля.') ?>
		
		<br><br><span class="highlight"><?= t('Этот месяц (Сентябрь 2005)') ?></span>
		<br><br><?= t('Ага-ага-ага.') ?>
		
		<br><br><span class="highlight"><?= t('Как принять участие?') ?></span>
		<br><br><?= t('Угу-угу-угу.') ?>
		
		<br><br><span class="highlight"><?= t('Кто побеждает?') ?></span>
		<br><br><?= t('Я.') ?>

		<br><br><span class="highlight"><?= t('Приз?') ?></span>
		<br><br><?= t('Не придумал.') ?>
		
		</td>
		<td width="280">
		
		<table width="200" align="center" cellpadding="0" cellspacing="0" border="0" bgcolor="#FFCC99">
			<tbody><tr>
				<td><img src="img/box_login_tl.gif" width="5" height="5"></td>
				<td><img src="img/pixel.gif" width="1" height="5"></td>
				<td><img src="img/box_login_tr.gif" width="5" height="5"></td>
			</tr>
			<tr>
				<td><img src="img/pixel.gif" width="5" height="1"></td>
				<td width="200" style="padding: 5px; text-align: center;">
				<div style="margin-bottom: 10px; font-weight: bold; font-size: 13px;"><?= t('Расписание конкурсов видео') ?></div>
				
				<?= t('Сентябрь 2005:') ?> <a href="results.php?search_query=ИМЯ">ИМЯ</a>
				<br><?= t('Октябрь 2005:') ?> <?= t('Будет объявлено') ?>
				<br><?= t('Ноябрь 2005:') ?> <?= t('Будет объявлено') ?>
				<br><?= t('Декабрь 2005:') ?> <?= t('Будет объявлено') ?>
				
				<br><br><div style="font-size: 11px; padding: 5px;"><?= t('Есть предложение для ежемесячного конкурса видео? Пожалуйста,') ?> <a href="mailto:bitbybyte@w10.site"><?= t('расскажите нам') ?></a> <?= t('об этом.') ?></div>
				
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
</div>

<?php showFooter(); ?>
