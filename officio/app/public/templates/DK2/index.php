<?php use Officio\Common\Service\Settings;

$headerBg = isset($this->companyWebsite['options']['header-bg']) && !empty($this->companyWebsite['options']['header-bg']) ? $this->companyWebsite['options']['header-bg'] : '#191919'; ?>
<?php $siteBg = isset($this->companyWebsite['options']['site-bg']) && !empty($this->companyWebsite['options']['site-bg']) ? $this->companyWebsite['options']['site-bg'] : '#fff'; ?>
<?php $assessmentBackground = empty($this->companyWebsite['assessment_background']) ? '' : 'background-color: ' . $this->companyWebsite['assessment_background'] . ';'; ?>

<?php $this->headStyle()->captureStart() ?>
body { background-color: <?= $siteBg ?>; }
.indent-bottom { background-color: <?= $headerBg ?>; }
.slider-nav{behavior: url(<?= $this->templateUrl ?>/js/PIE.htc);}
.button{behavior: url(<?= $this->templateUrl ?>/js/PIE.htc); position: relative;}
li.assessment a:link, li.assessment a:visited { <?=$assessmentBackground?> color: <?=$this->companyWebsite['assessment_foreground']?>; }

.sf-menu > li > a {
color: <?= !empty($this->companyWebsite['options']['menu-text-color-off']) ? $this->companyWebsite['options']['menu-text-color-off'] : '#FFFFFF' ?>;
}
.sf-menu > li > a:hover,
.sf-menu > li.sfHover > a, .sf-menu > li > a:hover, .sf-menu > li.current > a {
color: <?= !empty($this->companyWebsite['options']['menu-text-color-on']) ? $this->companyWebsite['options']['menu-text-color-on'] . ' !important' : '#83C00B' ?>;
}

.sf-menu > li > a:hover {
background-color: <?= !empty($this->companyWebsite['options']['menu-highlight-bar-color']) ? $this->companyWebsite['options']['menu-highlight-bar-color'] . ' !important' : 'transparent' ?>;
}

.aside-bg
{
background-color:<?= !empty($this->companyWebsite['options']['footer-bg-color']) ? $this->companyWebsite['options']['footer-bg-color'] . ' !important' : '#72685D' ?>;
}

<?php $this->headStyle()->captureEnd(); ?>

<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/reset.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/skeleton.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/superfish.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/forms.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/slider.css'); ?>
<?php $this->headLink()->appendStylesheet('//fonts.googleapis.com/css?family=Open+Sans+Condensed:300'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie.css', 'screen', 'lt IE 9'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie7.css', 'screen', 'lt IE 8'); ?>

<?php $this->headScript()->appendFile('//maps.google.com/maps/api/js?sensor=true', 'text/javascript', array('minify_disabled' => true, 'weight' => 50)); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery-1.7.1.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/superfish.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.responsivemenu.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/slides.min.jquery.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.easing.1.3.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/forms.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/html5.js', 'text/javascript', array('conditional' => 'lt IE 9')); ?>

<div id="page2">
    <div class="container_12">
        <div class="wrapper">
            <div class="grid_12">
                <?php if ($this->companyWebsite['login_block_on'] == 'Y') { ?>
                    <?php if (!empty($this->userId)) { ?>
                        <div class="right">
                            <div class="login-txt right">
                                <div class="left" style="padding: 25px 20px 0 0;"><?= $this->userName ?></div>
                                <div class="left"><a href="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/logout" class="button"><strong>Logout</strong></a></div>
                                <div class="clear"></div>
                            </div>
                            <div class="clear"></div>
                        </div>
                    <?php } else { ?>
                        <div id="login-container">
                            <form method="post" class="form right" id="login" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/login">
                                <fieldset>
                                    <div class="left" style="padding-right: 12px;">
                                        <label class="keywords" style="margin-right: 8px;"><?= $this->translate('Username') ?>:</label>
                                        <input type="text" name="username" value=""/>
                                    </div>
                                    <div class="left" style="padding-right: 12px;">
                                        <label class="keywords"><?= $this->translate('Password') ?>:</label>
                                        <input type="password" name="password" value=""/>
                                    </div>
                                    <a class="button" id="login-button">Login</a>
                                    <div class="clear"></div>
                                </fieldset>
                                <div class="error"><?= $this->escapeHtml($this->loginError) ?></div>
                            </form>
                        </div>
                    <?php } ?>
                <?php } ?>

                <!--======================== header ============================-->
                <header>
                    <div class="indent-bottom">
                        <!--======================== logo ============================-->
                        <div>

                            <?php if (!empty($this->companyWebsite['company_logo'])) { ?>
                                <h1 class="company-logo"><a href="<?= $this->baseUrl . '/webs/' . $this->entranceName ?>"><img src="<?= $this->uploadsUrl . $this->companyWebsite['company_logo'] ?>" hspace="2" vspace="2" border="0"
                                                                                                                               align="bottom" alt=""/></a></h1>
                            <?php } else {
                                if (!empty($this->companyWebsite['company_name'])) { ?>
                                    <div class="company-logo"><?= $this->companyWebsite['company_name'] ?></div>
                                <?php }
                            } ?>

                            <div class="clear"></div>
                        </div>
                        <!--======================== menu ============================-->
                        <nav>
                            <ul class="sf-menu responsive-menu">
                                <?php foreach ($this->menu as $page => $link) { ?>
                                    <?php $cls = array(); ?>
                                    <?php if ($this->currentPage == $page) {
                                        $cls[] = 'current';
                                    } ?>
                                    <?php if ($page == 'contact') {
                                        $cls[] = 'last-item';
                                    } ?>
                                    <?php if ($page == 'assessment') {
                                        $cls[] = 'assessment';
                                    } ?>
                                    <li<?= (empty($cls) ? '' : ' class="' . implode(' ', $cls) . '"') ?>><a href="<?= $link ?>"><?= trim($this->escapeHtml($this->companyWebsite[$page . '_name'])) ?></a></li>
                                <?php } ?>
                            </ul>
                        </nav>
                        <div class="clear"></div>
                    </div>
                    <?php if ($this->currentPage == 'homepage' && $this->booUseSlider) { ?>
                        <!--=================== slider ==================-->
                        <div id="slides">
                            <div class="slides_container">
                                <?php foreach ($this->companyWebsite['options']['selected-slide'] as $slide) { ?>
                                    <div class="slide"><img src="<?= $this->baseUrl . $slide ?>" alt=""></div>
                                <?php } ?>
                            </div>
                            <div class="slider-nav"></div>
                        </div>
                    <?php } ?>
                </header>
                <!--======================== content ===========================-->
                <section id="content">
                    <div class="wrapper">
                        <div class="alpha">

                            <?php $booExternalLinks = $this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links']) && $this->currentPage !== 'contact'; ?>

                            <?php if ($this->currentPage != 'homepage') { ?>
                                <h3 class="icon-<?= $this->currentPage ?> p5"><?= $this->pageTitle ?></h3>
                            <?php } ?>

                            <?php if ($this->currentPage != 'contact' && $this->currentPage != 'assessment') { ?>
                                <p class="p9"><?= nl2br($this->companyWebsite[$this->currentPage . '_text']) ?></p>
                            <?php } ?>

                            <?php if ($this->currentPage == 'assessment') { ?>
                                <?php if (!empty($this->companyWebsite['assessment_banner'])) { ?>
                                    <div style="margin-bottom: 10px; text-align: center;"><img src="<?= $this->uploadsUrl . $this->companyWebsite['assessment_banner'] ?>" alt=""/></div>
                                <?php } ?>
                            <?php } ?>

                            <?php if ($this->currentPage == 'assessment' && !empty($this->companyWebsite['assessment_url'])) { ?>
                                <iframe src="<?= $this->companyWebsite['assessment_url'] ?>" height="800" style="background-color: #fff; width: 100%;"></iframe>
                            <?php } ?>

                            <?php if ($this->currentPage == 'contact') { ?>
                                <div id="map">
                                    <?php if ($this->companyWebsite['contact_map'] == 'Y' && !empty($this->companyWebsite['contact_map_coords'])) { ?>
                                        <figure class="p5">
                                            <textarea style="display: none;" id="map-address"><?= $this->companyWebsite['contact_text'] ?></textarea>
                                            <script type="text/javascript">
                                                $(function () {
                                                    var myLatlng = new google.maps.LatLng(<?=implode(',', $this->companyWebsite['contact_map_coords'])?>);
                                                    var myOptions = {zoom: 14, center: myLatlng, mapTypeId: google.maps.MapTypeId.ROADMAP};
                                                    var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
                                                    var marker = new google.maps.Marker({position: myLatlng, map: map, title: $('#map-address').val()});
                                                });
                                            </script>
                                            <div id="map_canvas" style="width:700px; height:300px; display:block; margin-left:120px;"></div>
                                        </figure>
                                    <?php } ?>
                                    <dl>
                                        <?php if (!empty($this->companyWebsite['contact_text'])) { ?>
                                            <dt><?= nl2br($this->escapeHtml($this->companyWebsite['contact_text'])) ?></dt>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_phone'])) { ?>
                                            <dd><span><?= $this->translate('Phone') ?>:</span> <?= $this->escapeHtml($this->companyWebsite['company_phone']) ?></dd>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_fax'])) { ?>
                                            <dd><span><?= $this->translate('FAX') ?>:</span> <?= $this->escapeHtml($this->companyWebsite['company_fax']) ?></dd>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_email'])) { ?>
                                            <dd><?= $this->translate('E-mail') ?>: <a href="mailto:<?= $this->escapeUrl($this->companyWebsite['company_email']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_email']) ?></a></dd>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_skype'])) { ?>
                                            <dd><?= $this->translate('Skype') ?>: <a href="skype:<?= $this->escapeHtmlAttr($this->companyWebsite['company_skype']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></a></dd>
                                        <?php } ?>
                                    </dl>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div style="text-align:center; margin-bottom:15px;">
        <?php if (!empty($this->companyWebsite['company_skype']) ||
            !empty($this->companyWebsite['company_linkedin']) ||
            !empty($this->companyWebsite['company_facebook']) ||
            !empty($this->companyWebsite['company_twitter'])) { ?>
            <!--====================social icons====================-->
            <ul class="social-icons">
                <?php foreach (array('company_twitter', 'company_linkedin', 'company_facebook', 'company_skype') as $num => $service) { ?>
                    <?php if (!empty($this->companyWebsite[$service])) { ?>
                        <?php $name = $service == 'company_skype' ? 'skype:' . $this->companyWebsite[$service] : $this->companyWebsite[$service]; ?>
                        <li class="icon-<?= ($num + 1) ?>"><a href="<?= $name ?>"></a></li>
                    <?php } ?>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>

    <!--======================== footer ============================-->
    <aside>
        <div class="aside-bg">
            <div class="container_12">
                <div class="wrapper">
                    <div class="grid_4 indent-sw2" style="padding-right: 300px;">
                        <?php if ($this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links'])) { ?>
                            <?php if (!empty($this->companyWebsite['external_links_title'])) { ?>
                                <h4 class="indent-bot2"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></h4>
                            <?php } ?>
                            <ul class="list-1">
                                <?php $linksNum = 0; ?>
                                <?php foreach ($this->companyWebsite['external_links'] as $name => $link) { ?>
                                    <li<?= (++$linksNum == count($this->companyWebsite['external_links']) ? ' class="last"' : '') ?>><a href="<?= Settings::formatUrl($link) ?>" target="_blank"><?= $this->escapeHtml($name) ?></a></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                    <?php if ($this->companyWebsite['contact_on'] == 'Y' && !empty($this->companyWebsite['company_email'])) { ?>
                        <div class="grid_4">
                            <h4 class="bot-indent2"><?= $this->translate('contact us') ?></h4>
                            <form id="contact-form" method="post" autocomplete="off" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/send-message">
                                <div class="status" style="display: none;"></div>
                                <fieldset>
                                    <label class="name">
                                        <input type="text" name="name" value="Name" maxlength="128"/>
                                        <span id="name-empty" class="empty" style="display: none;"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <label class="email">
                                        <input type="text" name="email" value="E-mail" maxlength="256"/>
                                        <span id="email-empty" class="empty" style="display: none;"><?= $this->translate('*This field is required.') ?></span>
                                        <span id="email-incorrect" class="error" style="display: none;"><?= $this->translate('*Email address is incorrect.') ?></span>
                                    </label>
                                    <label class="message">
                                        <textarea name="message">Message</textarea>
                                        <span id="message-empty" class="empty" style="display: none;"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <div class="buttons-wrapper">
                                        <a data-type="reset" href="#" class="button"><strong><?= $this->translate('reset') ?></strong></a>
                                        <a data-type="submit" href="#" class="button"><strong><?= $this->translate('send') ?></strong></a>
                                    </div>
                                </fieldset>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </aside>

    <footer>
        <div class="container_12">
            <div class="wrapper">
                <div class="grid_12">
                    <div class="footer-nav" style="float: left;">
                        <?php foreach ($this->menu as $page => $link) { ?>
                            <a href="<?= $link ?>"><?= trim($this->escapeHtml($this->companyWebsite[$page . '_name'])) ?></a>
                        <?php } ?>
                    </div>
                    <?php if (!empty($this->companyWebsite['footer_text'])) { ?>
                        <div class="footer-text" style="float: right;"><?= nl2br($this->escapeHtml($this->companyWebsite['footer_text'])) ?></div>
                    <?php } ?>
                    <br style="clear: both;"/>
                </div>
            </div>
        </div>
    </footer>
</div>