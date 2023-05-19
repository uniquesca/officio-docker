<?php use Officio\Common\Service\Settings;

$assessmentBackground = empty($this->companyWebsite['assessment_background']) ? '' : 'background-color: ' . $this->companyWebsite['assessment_background'] . ';'; ?>

<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/reset.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/skeleton.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/superfish.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/forms.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/slider.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie.css', 'screen', 'lt IE 9'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie9.css', 'screen', 'IE 9'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie7.css', 'screen', 'lt IE 8'); ?>
<?php $this->headLink()->headLink(array('rel' => 'favicon', 'href' => $this->templateUrl . '/images/favicon.ico'), 'PREPEND'); ?>

<?php $this->headScript()->appendFile('//maps.google.com/maps/api/js?sensor=true', 'text/javascript', array('minify_disabled' => true, 'weight' => 50)); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery-1.7.1.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/superfish.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/FF-cash.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.responsivemenu.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/cufon-yui.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/cufon-replace.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/Bebas_400.font.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/slides.min.jquery.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.easing.1.3.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/forms.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/html5.js', 'text/javascript', array('conditional' => 'lt IE 9')); ?>

<?php $this->headStyle()->captureStart() ?>
.sf-menu ul {behavior: url(<?= $this->templateUrl ?>/js/PIE.htc);}
.block1, .block2, #contact-form input, #contact-form textarea{behavior: url(<?= $this->templateUrl ?>/js/PIE.htc); position:relative;}
li.assessment > a:link, li.assessment a:visited { <?= $assessmentBackground ?> color: <?= $this->companyWebsite['assessment_foreground'] ?>; }
<?php $this->headStyle()->captureEnd(); ?>

<div class="container_24">
    <div class="grid_24">

        <?php if ($this->companyWebsite['login_block_on'] == 'Y') { ?>
            <div style="padding: 20px 0;">
                <?php if (!empty($this->userId)) { ?>
                    <div style="padding-right: 25px;">
                        <div class="login-txt right"><?= $this->userName ?> <a href="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/logout" class="link">Logout</a></div>
                        <div class="clear"></div>
                    </div>
                <?php } else { ?>
                    <div id="login-container">
                        <form method="post" class="form" id="login" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/login">
                            <fieldset>
                                <label class="keywords" style="margin-right: 8px;"><?= $this->translate('Username') ?>:</label>
                                <input type="text" name="username" value=""/>
                                <label class="keywords"><?= $this->translate('Password') ?>:</label>
                                <input type="password" name="password" value=""/>
                                <a class="button" id="login-button">Login</a>
                            </fieldset>
                            <div class="error"><?= $this->escapeHtml($this->loginError) ?></div>
                        </form>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <!--======================== header ============================-->
        <header>

            <!--======================== logo ============================-->
            <?php if (!empty($this->companyWebsite['company_logo'])) { ?>
                <h1><a href="<?= $this->baseUrl . '/webs/' . $this->entranceName ?>"><img src="<?= $this->uploadsUrl . $this->companyWebsite['company_logo'] ?>" hspace="2" vspace="2" border="0" align="bottom" alt=""/></a></h1>
            <?php } else {
                if (!empty($this->companyWebsite['company_name'])) { ?>
                    <?= $this->companyWebsite['company_name'] ?>
                <?php }
            } ?>

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
            <?php if ($this->currentPage == 'homepage' && $this->booUseSlider) { ?>
                <!--=================== slider ==================-->
                <div id="slides">
                    <div class="slides_container">
                        <?php foreach ($this->companyWebsite['options']['selected-slide'] as $slide) { ?>
                            <div class="slide">
                                <img src="<?= $this->baseUrl . $slide ?>" width="950" height="440" alt="">
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </header>
        <!--======================== content ===========================-->
        <section id="content" class="border-bottom">
            <div class="wrapper">
                <div class="extra-marg-bot<?= ($this->currentPage == 'contact' ? ' grid_15 alpha' : 'col-6') ?>">
                    <div class="indent-right6">

                        <?php if ($this->currentPage != 'homepage') { ?>
                            <h3 class="icon-<?= $this->currentPage ?> p2-1"><?= $this->pageTitle ?></h3>
                        <?php } ?>

                        <?php if ($this->currentPage != 'contact' && $this->currentPage != 'assessment') { ?>
                            <p class="p5"><?= nl2br($this->companyWebsite[$this->currentPage . '_text']) ?></p>
                        <?php } ?>

                        <?php if ($this->currentPage == 'assessment') { ?>
                            <?php if (!empty($this->companyWebsite['assessment_banner'])) { ?>
                                <div style="margin-bottom: 10px; text-align: center;"><img src="<?= $this->uploadsUrl . $this->companyWebsite['assessment_banner'] ?>" alt=""/></div>
                            <?php } ?>
                        <?php } ?>

                        <?php if ($this->currentPage == 'assessment' && !empty($this->companyWebsite['assessment_url'])) { ?>
                            <iframe src="<?= $this->companyWebsite['assessment_url'] ?>" height="800" style="background-color: #fff; width: 100%;"></iframe>
                        <?php } ?>

                        <?php if ($this->currentPage == 'contact' && $this->companyWebsite['contact_on'] == 'Y' && !empty($this->companyWebsite['company_email'])) { ?>
                            <form id="contact-form" method="post" autocomplete="off" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/send-message">
                                <div class="success">
                                    <?= $this->translate('Contact form submitted!') ?><br>
                                    <strong><?= $this->translate('We will be in touch soon.') ?></strong>
                                </div>
                                <fieldset>
                                    <label class="name">
                                        <input type="text" value="<?= $this->translate('Name:') ?>">
                                        <span class="error"><?= $this->translate('*This is not a valid name.') ?></span> <span class="empty"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <label class="email">
                                        <input type="text" value="<?= $this->translate('E-mail:') ?>">
                                        <span class="error"><?= $this->translate('*This is not a valid email address.') ?></span> <span class="empty"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <label class="phone">
                                        <input type="tel" value="<?= $this->translate('Phone:') ?>">
                                        <span class="error"><?= $this->translate('*This is not a valid phone number.') ?></span> <span class="empty"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <label class="message">
                                        <textarea><?= $this->translate('Message') ?>:</textarea>
                                        <span class="error"><?= $this->translate('*The message is too short.') ?></span> <span class="empty"><?= $this->translate('*This field is required.') ?></span>
                                    </label>
                                    <div class="buttons-wrapper">
                                        <a class="button" data-type="reset"><?= $this->translate('reset') ?></a>
                                        <a class="button" data-type="submit"><?= $this->translate('submit') ?></a>
                                    </div>
                                </fieldset>
                            </form>
                        <?php } ?>

                    </div>
                </div>

                <?php if ($this->currentPage == 'contact') { ?>
                    <div class="grid_9 last-item">
                        <h3 class="icon-4 p1-1"><?= $this->translate('Our Address') ?></h3>
                        <div id="map">
                            <?php if ($this->companyWebsite['contact_map'] == 'Y' && !empty($this->companyWebsite['contact_map_coords'])) { ?>
                                <figure class="p3-1">
                                    <textarea style="display: none;" id="map-address"><?= $this->companyWebsite['contact_text'] ?></textarea>
                                    <script type="text/javascript">
                                        $(function () {
                                            var myLatlng = new google.maps.LatLng(<?=implode(',', $this->companyWebsite['contact_map_coords'])?>);
                                            var myOptions = {zoom: 14, center: myLatlng, mapTypeId: google.maps.MapTypeId.ROADMAP};
                                            var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
                                            var marker = new google.maps.Marker({position: myLatlng, map: map, title: $('#map-address').val()});
                                        });
                                    </script>
                                    <div id="map_canvas" class="borders" style="width: 320px; height: 190px; display: block;"></div>
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
                    </div>
                <?php } ?>

            </div>
        </section>
        <!--======================== footer ============================-->
        <aside>
            <div class="aside-bg">
                <div class="container_24">
                    <div class="wrapper indent-bottom1 indent-top">
                        <div class="grid_15 alpha">
                            <?php if ($this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links'])) { ?>
                                <?php if (!empty($this->companyWebsite['external_links_title'])) { ?>
                                    <h5 class="p3"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></h5>
                                <?php } ?>
                                <ul class="list-1">
                                    <?php $linksNum = 0; ?>
                                    <?php foreach ($this->companyWebsite['external_links'] as $name => $link) { ?>
                                        <li<?= (++$linksNum == count($this->companyWebsite['external_links']) ? ' class="last-item"' : '') ?>><a href="<?= Settings::formatUrl($link) ?>" target="_blank"><?= $this->escapeHtml($name) ?></a>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </div>
                        <div class="grid_9 last-item">
                            <div class="indent-right5-1">
                                <?php if (!empty($this->companyWebsite['company_skype']) ||
                                    !empty($this->companyWebsite['company_linkedin']) ||
                                    !empty($this->companyWebsite['company_facebook']) ||
                                    !empty($this->companyWebsite['company_twitter'])) { ?>
                                    <h5 class="p4"><?= $this->translate('Connect with us') ?></h5>
                                    <!--===========================social icons==============================-->
                                    <ul class="social-icons">
                                        <?php foreach (array('company_facebook', 'company_linkedin', 'company_twitter', 'company_skype') as $num => $service) { ?>
                                            <?php if (!empty($this->companyWebsite[$service])) { ?>
                                                <?php $name = $service == 'company_skype' ? 'skype:' . $this->companyWebsite[$service] : $this->companyWebsite[$service]; ?>
                                                <li class="icon-<?= ($num + 1) ?>"><a href="<?= $name ?>"></a></li>
                                            <?php } ?>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <footer>
            <div class="wrapper">
                <div class="footer-nav" style="float: left;">
                    <?php foreach ($this->menu as $page => $link) { ?>
                        <a href="<?= $link ?>"><?= trim($this->escapeHtml($this->companyWebsite[$page . '_name'])) ?></a>
                    <?php } ?>
                </div>
                <?php if (!empty($this->companyWebsite['footer_text'])) { ?>
                    <div class="footer-text"><?= nl2br($this->escapeHtml($this->companyWebsite['footer_text'])) ?></div>
                <?php } ?>
            </div>

        </footer>
    </div>
    <div class="clear"></div>
</div>
<script type="text/javascript">Cufon.now();</script>