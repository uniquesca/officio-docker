<?php use Officio\Common\Service\Settings;

$background = isset($this->companyWebsite['options']['selected-bg']) && !empty($this->companyWebsite['options']['selected-bg']) ? $this->baseUrl . $this->companyWebsite['options']['selected-bg'] : $this->templateUrl . '/images/bg.gif'; ?>
<?php $footerBg = isset($this->companyWebsite['options']['footer-bg']) && !empty($this->companyWebsite['options']['footer-bg']) ? $this->companyWebsite['options']['footer-bg'] : '#61584F'; ?>
<?php $assessmentBackground = empty($this->companyWebsite['assessment_background']) ? '' : 'background-color: ' . $this->companyWebsite['assessment_background'] . ';'; ?>

<?php $this->headStyle()->captureStart() ?>
.main-part { background: url(<?= $background ?>) repeat left top; }
#body,footer { background-color: <?= $footerBg ?>; }
li.assessment a:link, li.assessment a:visited { <?= $assessmentBackground ?> color: <?= $this->companyWebsite['assessment_foreground'] ?>; padding: 0 10px 3px; }
<?php $this->headStyle()->captureEnd(); ?>
<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet('//fonts.googleapis.com/css?family=Anton'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/styles.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/skeleton.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/slider.css'); ?>

<?php $this->headScript()->appendFile('//maps.google.com/maps/api/js?sensor=true', 'text/javascript', array('minify_disabled' => true, 'weight' => 50)); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/slides.min.jquery.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.backgroundPosition.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/html5.js', 'text/javascript', array('conditional' => 'lt IE 9')); ?>

<?php $this->headStyle()->captureStart() ?>
#contact-form input, #contact-form textarea{behavior: url(<?= $this->templateUrl ?>/js/PIE.htc); position:relative;}
<?php $this->headStyle()->captureEnd(); ?>

<div id="body" class="<?= $this->currentPage ?>">
    <div class="main-part">
        <header>
            <div class="header-border">
                <div class="container_12">
                    <div class="row-1">
                        <div class="grid_9 indent-sw4">
                            <div class="wrapper">
                                <div class="col-1 logo-col">
                                    <?php if (!empty($this->companyWebsite['company_logo'])) { ?>
                                        <h1><a href="<?= $this->baseUrl . '/webs/' . $this->entranceName ?>"><img src="<?= $this->uploadsUrl . $this->companyWebsite['company_logo'] ?>" hspace="2" vspace="2" border="0" align="bottom"
                                                                                                                  alt=""/></a></h1>
                                    <?php } else {
                                        if (!empty($this->companyWebsite['company_name'])) { ?>
                                            <h1><?= $this->companyWebsite['company_name'] ?></h1>
                                        <?php }
                                    } ?>
                                </div>
                                <div class="col-2">
                                    <nav id="main-menu">
                                        <ul class="menu mobile-menu sf-js-enabled" id="mm1">
                                            <?php foreach ($this->menu as $page => $link) { ?>
                                                <li<?= ($page == 'assessment' ? ' class="assessment"' : '') ?>><a href="<?= $link ?>"<?= ($this->currentPage == $page ? ' class="active"' : '') ?>><?= trim(
                                                            $this->escapeHtml($this->companyWebsite[$page . '_name'])
                                                        ) ?></a></li>
                                            <?php } ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <div class="grid_3">
                            <?php if (!empty($this->companyWebsite['company_skype']) ||
                                !empty($this->companyWebsite['company_linkedin']) ||
                                !empty($this->companyWebsite['company_facebook']) ||
                                !empty($this->companyWebsite['company_twitter'])) { ?>

                                <div class="p3">
                                    <ul class="social-buttons">
                                        <?php foreach (array('company_skype', 'company_facebook', 'company_twitter', 'company_linkedin') as $num => $service) { ?>
                                            <?php if (!empty($this->companyWebsite[$service])) { ?>
                                                <?php $name = $service == 'company_skype' ? 'skype:' . $this->companyWebsite[$service] : $this->companyWebsite[$service]; ?>
                                                <li><a href="<?= $name ?>" class="item-<?= ($num + 1) ?>"><strong><span></span></strong></a></li>
                                            <?php } ?>
                                        <?php } ?>
                                    </ul>
                                    <div class="clear"></div>
                                </div>
                            <?php } ?>

                            <?php if ($this->companyWebsite['login_block_on'] == 'Y') { ?>
                                <?php if (!empty($this->userId)) { ?>
                                    <p class="login-txt"><?= $this->userName ?> <a href="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/logout" class="link">Logout</a></p>
                                <?php } else { ?>
                                    <form method="post" id="login" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/login">
                                        <div class="login-field"><input type="text" name="username" value="" maxlength="50"/></div>
                                        <div class="login-txt"><?= $this->translate('Username') ?></div>
                                        <div class="clear"></div>
                                        <div class="login-field"><input type="password" name="password" value="" maxlength="50"/></div>
                                        <div class="login-txt"><?= $this->translate('Password') ?></div>
                                        <div class="clear"></div>
                                        <div class="error"><?= $this->escapeHtml($this->loginError) ?></div>
                                        <div><a class="button" style="float: right;" id="login-button">Login</a></div>
                                    </form>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div class="clear"></div>
                    </div>
                    <?php if ($this->currentPage == 'homepage' && $this->booUseSlider) { ?>
                        <div class="grid_12">
                            <div id="slides">
                                <div class="slider-border-top">
                                    <div class="slider-border-bottom">
                                        <div class="slider-border-left">
                                            <div class="slider-border-right">
                                                <div class="slides_container">
                                                    <?php foreach ($this->companyWebsite['options']['selected-slide'] as $slide) { ?>
                                                        <div class="slide"><img alt="" src="<?= $this->baseUrl . $slide ?>"></div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="clear"></div>
                </div>
            </div>
        </header>

        <section class="content">
            <div class="container_12">
                <div class="wrapper bot-pad indent-sw2">

                    <h3 class="title">
                        <?php if ($this->currentPage != 'homepage') { ?>
                            <?= $this->pageTitle ?>
                        <?php } ?>
                    </h3>

                    <div class="grid_9">

                        <?php if ($this->currentPage != 'contact' && $this->currentPage != 'assessment') { ?>
                            <p class="indent-bot2"><?= nl2br($this->companyWebsite[$this->currentPage . '_text']) ?></p>
                        <?php } ?>

                        <?php if ($this->currentPage == 'contact') { ?>

                            <?php if ($this->companyWebsite['contact_map'] == 'Y' && !empty($this->companyWebsite['contact_map_coords'])) { ?>
                                <figure class="map-style indent-bot2">
                                    <textarea style="display: none;" id="map-address"><?= $this->companyWebsite['contact_text'] ?></textarea>
                                    <script type="text/javascript">
                                        $(function () {
                                            var myLatlng = new google.maps.LatLng(<?=implode(',', $this->companyWebsite['contact_map_coords'])?>);
                                            var myOptions = {zoom: 16, center: myLatlng, mapTypeId: google.maps.MapTypeId.ROADMAP};
                                            var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
                                            var marker = new google.maps.Marker({position: myLatlng, map: map, title: $('#map-address').val()});
                                        });
                                    </script>
                                    <div id="map_canvas" style="width: 700px; height: 300px; display: block;"></div>
                                </figure>
                            <?php } ?>

                            <div style="width: 620px;">
                                <div class="alpha grid_4 width-2 indent-sw5">
                                    <dl>
                                        <?php if (!empty($this->companyWebsite['contact_text'])) { ?>
                                            <dt class="ico1"><?= nl2br($this->escapeHtml($this->companyWebsite['contact_text'])) ?></dt>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_email'])) { ?>
                                            <dt class="ico4"><?= $this->translate('E-mail') ?>: <a class="link bo" href="mailto:<?= $this->escapeUrl($this->companyWebsite['company_email']) ?>"><?= $this->escapeHtml(
                                                        $this->companyWebsite['company_email']
                                                    ) ?></a></dt>
                                        <?php } ?>
                                    </dl>
                                </div>
                                <div class="grid_5 omega width-2">
                                    <dl>
                                        <?php if (!empty($this->companyWebsite['company_phone'])) { ?>
                                            <dt class="ico2"><?= $this->translate('Phone') ?>: <?= $this->escapeHtml($this->companyWebsite['company_phone']) ?></dt>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_fax'])) { ?>
                                            <dt class="ico3 last"><?= $this->translate('FAX') ?>: <?= $this->escapeHtml($this->companyWebsite['company_fax']) ?></dt>
                                        <?php } ?>
                                        <?php if (!empty($this->companyWebsite['company_skype'])) { ?>
                                            <dt class="ico5"><?= $this->translate('Skype') ?>: <a class="link bo" href="skype:<?= $this->escapeHtmlAttr($this->companyWebsite['company_skype']) ?>"><?= $this->escapeHtml(
                                                        $this->companyWebsite['company_skype']
                                                    ) ?></a></dt>
                                        <?php } ?>
                                    </dl>
                                </div>
                            </div>

                        <?php } ?>

                        <?php if ($this->currentPage == 'assessment') { ?>
                            <?php if (!empty($this->companyWebsite['assessment_banner'])) { ?>
                                <div style="margin-bottom: 10px;"><img src="<?= $this->uploadsUrl . $this->companyWebsite['assessment_banner'] ?>" alt=""/></div>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($this->currentPage == 'assessment' && !empty($this->companyWebsite['assessment_url'])) { ?>
                    <iframe src="<?= $this->companyWebsite['assessment_url'] ?>" height="800" style="background-color: #fff; width: 100%;"></iframe>
                <?php } ?>

            </div>
        </section>
    </div>

    <aside>
        <div class="aside-bg">
            <div class="container_12">
                <div class="wrapper">
                    <div class="grid_4 indent-sw2" style="padding-right: 300px;">
                        <?php if ($this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links'])) { ?>
                            <?php if (!empty($this->companyWebsite['external_links_title'])) { ?>
                                <h4 class="indent-bot2"><?= $this->escapeHtml($this->companyWebsite['external_links_title']) ?></h4>
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