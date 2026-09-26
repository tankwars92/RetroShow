<?php 
include("init.php");
include("template.php");

$p = isset($_GET['p']) ? $_GET['p'] : '';
if ($p === 'terms') {
    $title = 'Условия использования';
} elseif ($p === 'privacy') {
    $title = 'Политика конфиденциальности';
} else {
    $title = 'Помощь';
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
				
				<div class="tableSubTitle">
<?php
if ($p === 'terms') {
    echo t('Условия использования');
} elseif ($p === 'privacy') {
    echo t('Политика конфиденциальности');
} else {
    echo t('Помощь');
}
?>
				</div>

<div class="pageTable">
<?php if ($p === 'terms'): ?>
<?php if (site_lang_is_en()): ?>
<p><b>1. General terms and consent</b><br>
By using and/or visiting the <b>RetroShow (<?php echo $_SERVER['HTTP_HOST']; ?>)</b> website, you confirm that you have read and agree to these Terms of Use and the Privacy Policy at <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>/help.php?p=privacy"><?php echo $_SERVER['HTTP_HOST']; ?>/help.php?p=privacy</a>. If you do not agree, you must stop using the site.</p>
<p><b>2. Governing law</b><br>
These Terms are governed by the laws of the Russian Federation. Disputes arising from use of the site are resolved under applicable Russian law.</p>
<p><b>3. Use of the site</b><br>
The site is provided for personal, non-commercial use. You may not:<br>
(i) copy or distribute site materials without the rights holder's permission;<br>
(ii) interfere with the site or attempt unauthorized access to the server or data;<br>
(iii) use the site to post illegal information;<br>
(iv) post ads without the administration's consent;<br>
(v) impersonate another person.<br>
The administration may limit or terminate access without prior notice if these terms are violated.</p>
<p><b>4. Intellectual property</b><br>
All site materials (including design, code, text, images, and video) are intellectual property protected under Russian law. Use without the rights holder's consent is prohibited except as allowed by law.</p>
<p><b>5. User content</b><br>
You are fully responsible for materials you post. By posting, you confirm you have all rights needed to publish them.<br><br>
By posting, you grant RetroShow a non-exclusive, royalty-free, worldwide license to store, reproduce, publicly display, and distribute that content for operating the site.<br><br>
You may not post materials that:<br>
(i) violate Russian law;<br>
(ii) infringe copyright or other third-party rights;<br>
(iii) contain defamation, threats, extremist material, pornography, or other prohibited information;<br>
(iv) violate privacy rights.<br><br>
The administration may remove any content without explanation.</p>
<p><b>6. Limitation of liability</b><br>
The site is provided "as is". The administration does not guarantee uninterrupted or error-free operation.<br><br>
The administration is not liable for:<br>
(i) temporary unavailability;<br>
(ii) errors or inaccuracies in materials;<br>
(iii) users' actions;<br>
(iv) damage from using or being unable to use the site.<br><br>
Liability is limited to actual proven damages unless otherwise required by Russian law.</p>
<p><b>7. Changes</b><br>
The administration may change these Terms at any time. The new version takes effect when published. Continued use means you accept the changed terms.</p>
<p><b>8. Age</b><br>
By using the site, you confirm you are 18 or older, or that you use it with the consent of a legal guardian.</p>
<?php else: ?>

<p><b>1. Общие положения и согласие</b><br>
Используя и/или посещая веб-сайт <b>RetroShow (<?php echo $_SERVER['HTTP_HOST']; ?>)</b>, вы подтверждаете, что ознакомились и соглашаетесь с настоящими Условиями использования, а также с Политикой конфиденциальности, размещённой по адресу <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>/help.php?p=privacy"><?php echo $_SERVER['HTTP_HOST']; ?>/help.php?p=privacy</a>. Если вы не согласны с настоящими условиями, вы обязаны прекратить использование сайта.</p>

<p><b>2. Применимое право</b><br>
Настоящие Условия регулируются законодательством Российской Федерации. Все споры, возникающие в связи с использованием сайта, подлежат разрешению в соответствии с действующим законодательством Российской Федерации.</p>

<p><b>3. Использование сайта</b><br>
Сайт предоставляется для личного некоммерческого использования. Пользователю запрещается:<br>
(i) копировать или распространять материалы сайта без разрешения правообладателя;<br>
(ii) вмешиваться в работу сайта, пытаться получить несанкционированный доступ к серверу или данным;<br>
(iii) использовать сайт для размещения незаконной информации;<br>
(iv) размещать рекламу без согласия администрации;<br>
(v) выдавать себя за другое лицо.<br>
Администрация вправе ограничить или полностью прекратить доступ пользователя к сайту без предварительного уведомления при нарушении настоящих условий.</p>

<p><b>4. Интеллектуальная собственность</b><br>
Все материалы сайта (включая дизайн, программный код, тексты, изображения и видео) являются объектами интеллектуальной собственности и охраняются в соответствии с законодательством Российской Федерации (включая часть IV Гражданского кодекса РФ). Любое использование материалов без согласия правообладателя запрещено, за исключением случаев, прямо предусмотренных законом.</p>

<p><b>5. Пользовательский контент</b><br>
Пользователь несёт полную ответственность за размещаемые им материалы. Размещая контент на сайте, пользователь подтверждает, что обладает всеми необходимыми правами на его публикацию.<br><br>

Размещая материалы, пользователь предоставляет RetroShow неисключительную, безвозмездную, действующую на территории всего мира лицензию на хранение, воспроизведение, публичный показ и распространение такого контента в целях функционирования сайта.<br><br>

Запрещается размещать материалы, которые:<br>
(i) нарушают законодательство Российской Федерации;<br>
(ii) нарушают авторские или иные права третьих лиц;<br>
(iii) содержат клевету, угрозы, экстремистские материалы, порнографию либо иную запрещённую информацию;<br>
(iv) нарушают права на частную жизнь.<br><br>

Администрация вправе удалить любой контент без объяснения причин.</p>

<p><b>6. Ограничение ответственности</b><br>
Сайт предоставляется по принципу «как есть». Администрация не гарантирует бесперебойную и безошибочную работу сайта.<br><br>

Администрация не несёт ответственности за:<br>
(i) временную недоступность сайта;<br>
(ii) возможные ошибки или неточности в материалах;<br>
(iii) действия пользователей сайта;<br>
(iv) возможный ущерб, возникший в результате использования или невозможности использования сайта.<br><br>

Ответственность администрации в любом случае ограничивается суммой фактически причинённого реального ущерба, если иное не предусмотрено законодательством Российской Федерации.</p>

<p><b>7. Изменение условий</b><br>
Администрация вправе в любое время изменять настоящие Условия. Новая редакция вступает в силу с момента её публикации на сайте. Продолжение использования сайта означает согласие с изменёнными условиями.</p>

<p><b>8. Возрастные ограничения</b><br>
Используя сайт, вы подтверждаете, что достигли возраста 18 лет либо используете сайт с согласия законных представителей.</p>
<?php endif; ?>

<?php elseif ($p === 'privacy'): ?>
<?php if (site_lang_is_en()): ?>
<p><b>RetroShow Privacy Policy</b><br>
We respect your privacy and work to protect your personal data when you use <b>RetroShow (<?php echo $_SERVER['HTTP_HOST']; ?>)</b>. This policy explains what we collect, how we use it, and how we protect it.</p>
<p><b>1. General</b><br>
This Privacy Policy is part of the site Terms of Use. By using the site, you confirm you have read this Policy and agree to it. If you do not agree, please stop using the site.</p>
<p><b>2. Data we collect</b><br>
We may collect:<br>
- name and email if you register or comment;<br>
- IP address when you visit;<br>
- data you volunteer when uploading videos or other materials.<br>
We do not collect extra data such as a phone number or home address unless you provide it.</p>
<p><b>3. How we use data</b><br>
Your data is used only to run the site and provide services. We do not share personal data with third parties without your consent, except as required by Russian law (for example, at the request of law enforcement).<br>
Email given with comments or uploads is not shown to other users without your consent.</p>
<p><b>4. Security</b><br>
We take reasonable steps to protect personal data from unauthorized access, change, disclosure, or destruction, including physical, technical, and organizational measures.</p>
<p><b>5. Children</b><br>
The site is not intended for children under 13. We do not knowingly collect data from anyone under 13. Users under 18 may use the site only with a parent or guardian's consent.</p>
<p><b>6. Changes</b><br>
We may update this Privacy Policy. The new version takes effect when published. Continued use after a change means you accept the updated Policy.</p>
<p><b>7. Your rights</b><br>
You may request information about your personal data, correction of inaccurate information, and deletion of data where that does not conflict with the law.</p>
<?php else: ?>

<p><b>Политика конфиденциальности RetroShow</b><br>
Мы уважаем вашу конфиденциальность и стремимся защищать ваши персональные данные при использовании сайта <b>RetroShow (<?php echo $_SERVER['HTTP_HOST']; ?>)</b>. Настоящая политика конфиденциальности объясняет, какие данные мы собираем, как используем их и какие меры безопасности применяем.</p>

<p><b>1. Общие положения</b><br>
Политика конфиденциальности является частью Условий использования сайта. Используя сайт, вы подтверждаете, что ознакомились с настоящей Политикой и согласны с её положениями. Если вы не согласны с ними, пожалуйста, прекратите использование сайта.</p>

<p><b>2. Какие данные мы собираем</b><br>
Мы можем собирать следующие данные:<br>
- имя и адрес электронной почты, если вы регистрируетесь или оставляете комментарий;<br>
- IP-адрес при посещении сайта;<br>
- данные, которые вы предоставляете добровольно при загрузке видео или материалов.<br>
Мы не собираем лишние данные, такие как номер телефона или домашний адрес, если вы их не указали добровольно.</p>

<p><b>3. Использование данных</b><br>
Ваши данные используются только для работы сайта и предоставления сервисов. Мы не передаем персональные данные третьим лицам без вашего согласия, за исключением случаев, предусмотренных законодательством РФ (например, по запросу правоохранительных органов).<br>
Электронная почта, указанная при комментариях или загрузке видео, не отображается другим пользователям без вашего согласия.</p>

<p><b>4. Безопасность данных</b><br>
Мы предпринимаем разумные меры для защиты персональных данных от несанкционированного доступа, изменения, раскрытия или уничтожения. Это включает физические, технические и организационные меры безопасности.</p>

<p><b>5. Дети и несовершеннолетние</b><br>
Сайт не предназначен для детей младше 13 лет. Мы сознательно не собираем данные о лицах младше 13 лет. Пользователи младше 18 лет могут использовать сайт только с согласия родителей или законных представителей.</p>

<p><b>6. Изменения в политике</b><br>
Мы можем обновлять эту Политику конфиденциальности. Новая редакция вступает в силу с момента публикации на сайте. Продолжение использования сайта после внесения изменений означает согласие с обновлённой Политикой.</p>

<p><b>7. Ваши права</b><br>
Вы имеете право запрашивать информацию о своих персональных данных, требовать исправления неточной информации, а также требовать удаления данных, если это не противоречит закону.</p>
<?php endif; ?>

<?php else: ?>
<span class="highlight"><?= t('В: Какие видео я могу загружать?') ?></span>
<br><br><?= t('О: Вы можете загружать любые личные видео, которыми хотите поделиться со всем миром. Мы не допускаем обнажённую натуру, и ваше видео должно быть приемлемым для любой аудитории.') ?>
<br><br><?= t('Однако это оставляет большой простор для творчества! У вас есть') ?> <a href="results.php?search_query=собака"><?= t('собака') ?></a> <?= t('или') ?> <a href="results.php?search_query=кошка"><?= t('кошка') ?></a>? <?= t('Вы отдыхали в') ?> <a href="results.php?search_query=Мексика">Mexico</a>? <?= t('Вы живёте в') ?> <a href="results.php?search_query=Нидерланды">the Netherlands</a>?
<br><br><?= t('Это лишь некоторые примеры видео, которые загружают наши пользователи. В конце концов, вы знаете себя лучше всех. Что бы вы хотели заснять на видео?') ?>

<br><br><span class="highlight"><?= t('В: Какова продолжительность моего видео?') ?></span>
<br><br><?= t('О: Ограничений по времени для вашего видео нет, но размер загружаемого видеофайла не должен превышать 1 ГБ.') ?>

<br><br><span class="highlight"><?= t('В: Какие форматы видеофайлов я могу загружать?') ?></span>
<br><br><?= t('О: RetroShow принимает видеофайлы с большинства цифровых камер и мобильных телефонов в форматах .MP4, .WEBM, .AVI, .MOV, .FLV, .MKV и другие.') ?>

<br><br><span class="highlight"><?= t('В: Как я могу улучшить свои видео?') ?></span>
<br><br><?= t('О: Мы рекомендуем вам редактировать видео с помощью таких программ, как Windows MovieMaker (входит в комплект каждой установки Windows) или Apple iMovie. С помощью этих программ вы можете легко редактировать видео, добавлять звуковые дорожки и т. д.') ?>

<br><br><span class="highlight"><?= t('В: Сохраняю ли я авторские права и другие законные права на свои видео?') ?></span>
<br><br><?= t('О: Да. Вы сохраняете все права на свой контент. RetroShow не берет на себя никаких авторских прав на ваши материалы.') ?>

<br><br><span class="highlight"><?= t('В: Какова ваша политика в отношении нарушения авторских прав?') ?></span>
<br><br><?= t('О: RetroShow уважает права правообладателей и издателей и принимает видео только от лиц, обладающих всеми необходимыми правами на загруженный материал. Наша политика заключается в том, чтобы реагировать на любые уведомления о предполагаемых нарушениях в соответствии с Законом об авторском праве в цифровую эпоху (DMCA). Если мы получим уведомление или у нас появятся основания полагать, что предоставленный вами контент нарушает авторские права третьих лиц, ваш аккаунт может быть удален, а видео удалено с RetroShow.') ?>

<br><br><span class="highlight"><?= t('В: Как сообщить о нарушении авторских прав?') ?></span>
<br><br><?= t('О: Если вы считаете, что кто-то другой загрузил ваш контент, защищенный авторским правом, без вашего разрешения, мы рекомендуем вам связаться с этим лицом для разрешения любых разногласий напрямую. Вы также можете обратиться в нашу службу поддержки, используя') ?> <a href="contact.php"><?= t('эту форму') ?></a>, <?= t('чтобы получить инструкции о том, как отправить уведомление о нарушении авторских прав в RetroShow.') ?>

<br><br><span class="highlight"><?= t('В: Что делать, если меня ложно обвинили в нарушении авторских прав?') ?></span>
<br><br><?= t('О: Мы сообщим вам, если получим жалобу на нарушение авторских прав в отношении любого вашего видеоконтента, размещенного на RetroShow. Мы предоставим вам возможность отреагировать соответствующим образом.') ?>

<br><br><span class="highlight"><?= t('В: Что вы делаете, чтобы предотвратить появление на RetroShow контента, нарушающего ваши правила?') ?></span>
<br><br><?= t('О: Мы проводим предварительную проверку загруженных видео как вручную, так и автоматически. Хотя мы делаем все возможное для выявления и удаления видео, нарушающих наши правила, наш процесс проверки в первую очередь ориентирован на удаление контента для взрослых или очевидных нарушений авторских прав и не является абсолютно безупречным. Тем не менее, мы призываем наших зрителей сообщать нам, если они обнаружат нарушения политики или проблемы с авторскими правами. У нас есть процесс рассмотрения сообщений о нарушениях политики и реагирования на сообщения о нарушениях авторских прав в соответствии с Законом об авторском праве в цифровую эпоху.') ?>
<br><br><span class="highlight"><?= t('Связь с администрацией') ?></span>
<br><br><?= t('Если у вас есть какие-либо вопросы или предложения по сайту, заполните форму обратной связи') ?> <a href="contact.php"><?= t('здесь') ?></a>.
<?php endif; ?>


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
