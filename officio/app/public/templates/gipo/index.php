<?php use Officio\Common\Service\Settings;

$background = isset($this->companyWebsite['options']['selected-bg']) && !empty($this->companyWebsite['options']['selected-bg']) ? $this->baseUrl . $this->companyWebsite['options']['selected-bg'] : $this->templateUrl . '/images/bg.gif'; ?>
<?php $assessmentBackground = empty($this->companyWebsite['assessment_background']) ? '' : 'background-color: ' . $this->companyWebsite['assessment_background'] . ';'; ?>

<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/maintenance-page.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/boilerplate.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/superfish.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/slider.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie.css', 'screen', 'lt IE 9'); ?>

<?php $this->headScript()->appendFile('//maps.google.com/maps/api/js?sensor=true', 'text/javascript', array('minify_disabled' => true, 'weight' => 50)); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery-1.7.1.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.colorbox-min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.hoverIntent.minified.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/superfish.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/supersubs.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/supposition.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/slides.min.jquery.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/sftouchscreen.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/html5.js', 'text/javascript', array('conditional' => 'lt IE 9')); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/forms.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/theme636.core.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.loader.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>

<?php $this->headStyle()->captureStart() ?>
body { background: url(<?= $background ?>) repeat left top; }
#superfish-1 li.assessment > a:link, #superfish-1 li.assessment > a:visited { <?= $assessmentBackground ?> color: <?= $this->companyWebsite['assessment_foreground'] ?>; background-image: none; border-radius: 0 0 5px 5px; }
<?php $this->headStyle()->captureEnd(); ?>

<div id="page-wrapper">
    <div id="page">

        <?php if ($this->companyWebsite['login_block_on'] == 'Y') { ?>
            <div class="login-block">
                <?php if (!empty($this->userId)) { ?>
                    <p class="login-txt"><?= $this->userName ?> <a href="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/logout" class="link">Logout</a></p>
                <?php } else { ?>
                    <form method="post" class="form" id="login" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/login">
                        <label class="keywords" style="margin-right: 8px;"><?= $this->translate('Username') ?>:</label>
                        <input type="text" name="username" value=""/>
                        <label class="keywords"><?= $this->translate('Password') ?>:</label>
                        <input type="password" name="password" value=""/>
                        <a class="button" id="login-button">Login</a>
                        <div class="error"><?= $this->escapeHtml($this->loginError) ?></div>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>

        <header class="clearfix" role="banner" id="header">
            <div class="section-1 clearfix">
                <div class="col1 col-title">
                    <?php if (!empty($this->companyWebsite['company_logo'])) { ?>
                        <a href="<?= $this->baseUrl . '/webs/' . $this->entranceName ?>"><img src="<?= $this->uploadsUrl . $this->companyWebsite['company_logo'] ?>" id="logo" hspace="2" vspace="2" border="0" align="bottom" alt=""/></a>
                    <?php } else {
                        if (!empty($this->companyWebsite['company_name'])) { ?>
                            <div class="company-title"><?= $this->companyWebsite['company_name'] ?></div>
                        <?php }
                    } ?>
                </div>
                <div class="col2">
                    <div class="region region-menu">
                        <div class="block block-superfish block-odd" id="block-superfish-1">
                            <div class="content">
                                <ul class="sf-menu main-menu sf-horizontal sf-style-default sf-total-items-6 sf-parent-items-1 sf-single-items-5 sf-js-enabled sf-shadow" id="superfish-1">
                                    <?php $i = 0; ?>
                                    <?php foreach ($this->menu as $page => $link) { ?>
                                        <?php $cls = array('sf-item-' . $i, 'sf-depth-1', 'sf-no-children', $i % 2 == 0 ? 'odd' : 'even'); ?>
                                        <?php if ($i == 0) {
                                            $cls[] = 'first';
                                        } ?>
                                        <?php if ($page == 'contact') {
                                            $cls[] = 'last';
                                        } ?>
                                        <?php if ($page == 'assessment') {
                                            $cls[] = 'assessment';
                                        } ?>
                                        <li<?= (empty($cls) ? '' : ' class="' . implode(' ', $cls) . '"') ?>><a class="sf-depth-1<?= ($this->currentPage == $page ? ' active' : '') ?>" href="<?= $link ?>"><?= trim(
                                                    $this->escapeHtml($this->companyWebsite[$page . '_name'])
                                                ) ?></a></li>
                                        <?php ++$i; ?>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-2 clearfix">
                <div class="region region-header">
                    <div class="block block-views block-even" id="block-views-slider-block">
                        <div class="content">
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
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <?php $booExternalLinks = $this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links']) && $this->currentPage == 'homepage'; ?>
        <?php $booContactForm = $this->currentPage == 'contact' && $this->companyWebsite['contact_on'] == 'Y' && !empty($this->companyWebsite['company_email']); ?>
        <div id="main-wrapper">
            <div class="clearfix" id="main">
                <div role="main" class="column clearfix" id="content">
                    <div class="section left<?= ($booExternalLinks ? ' content-section-small' : '') ?>">

                        <?php if ($this->currentPage != 'homepage') { ?>
                            <h1 id="page-title" class="title"><?= $this->pageTitle ?></h1>
                        <?php } ?>

                        <section class="block block-views block-even" id="block-views-smart-moves-block">
                            <div class="content">

                                <?php if ($this->currentPage != 'contact' && $this->currentPage != 'assessment') { ?>
                                    <?= nl2br($this->companyWebsite[$this->currentPage . '_text']) ?>
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

                                    <div id="map" class="left">
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
                                                <div id="map_canvas" class="borders" style="width: 300px; height: 170px; display: block;"></div>
                                            </figure>
                                        <?php } ?>
                                        <div class="contact-info">
                                            <?php if (!empty($this->companyWebsite['contact_text'])) { ?>
                                                <?= nl2br($this->escapeHtml($this->companyWebsite['contact_text'])) ?><br/>
                                            <?php } ?>
                                            <?php if (!empty($this->companyWebsite['company_phone'])) { ?>
                                                <span><?= $this->translate('Phone') ?>:</span> <?= $this->escapeHtml($this->companyWebsite['company_phone']) ?><br/>
                                            <?php } ?>
                                            <?php if (!empty($this->companyWebsite['company_fax'])) { ?>
                                                <span><?= $this->translate('FAX') ?>:</span> <?= $this->escapeHtml($this->companyWebsite['company_fax']) ?><br/>
                                            <?php } ?>
                                            <?php if (!empty($this->companyWebsite['company_email'])) { ?>
                                                <?= $this->translate('E-mail') ?>: <a href="mailto:<?= $this->escapeUrl($this->companyWebsite['company_email']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_email']) ?></a><br/>
                                            <?php } ?>
                                            <?php if (!empty($this->companyWebsite['company_skype'])) { ?>
                                                <?= $this->translate('Skype') ?>: <a href="skype:<?= $this->escapeHtmlAttr($this->companyWebsite['company_skype']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></a><br/>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <?php if ($booContactForm) { ?>
                                        <div class="contact-block left">
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
                                                        <input type="submit" class="form-submit" value="<?= $this->translate('Submit') ?>"/>
                                                        <input type="reset" class="form-submit" value="<?= $this->translate('Clear') ?>"/>
                                                    </div>
                                                </fieldset>
                                            </form>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </section>
                    </div>

                    <?php if ($booExternalLinks) { ?>
                        <div class="right" style="width: 240px;">
                            <?php if (!empty($this->companyWebsite['external_links_title'])) { ?>
                                <h2><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></h2>
                                <br/>
                            <?php } ?>
                            <?php $linksNum = 0; ?>
                            <ul class="external-links">
                                <?php foreach ($this->companyWebsite['external_links'] as $name => $link) { ?>
                                    <li<?= (++$linksNum == count($this->companyWebsite['external_links']) ? ' class="last-item"' : '') ?>><a href="<?= Settings::formatUrl($link) ?>" target="_blank"><?= $this->escapeHtml($name) ?></a></li>
                                <?php } ?>
                            </ul>
                        </div>
                    <?php } ?>

                </div>
            </div>
        </div>

        <footer role="contentinfo" id="footer">
            <div class="footer-wrapper clearfix">
                <div class="region region-footer clearfix">
                    <?php if (!empty($this->companyWebsite['footer_text'])) { ?>
                        <section class="block block-block block-odd" id="block-block-5">
                            <div class="content"><?= nl2br($this->escapeHtml($this->companyWebsite['footer_text'])) ?></div>
                        </section>
                    <?php } ?>
                    <section class="block block-block block-even" id="block-block-6">
                        <?php foreach ($this->menu as $page => $link) { ?>
                            <a href="<?= $link ?>"><?= trim($this->escapeHtml($this->companyWebsite[$page . '_name'])) ?></a>
                        <?php } ?>
                    </section>
                    <?php if (!empty($this->companyWebsite['company_skype']) ||
                        !empty($this->companyWebsite['company_linkedin']) ||
                        !empty($this->companyWebsite['company_facebook']) ||
                        !empty($this->companyWebsite['company_twitter'])) { ?>
                        <section class="block block-follow block-odd" id="block-follow-site">
                            <h2><?= $this->translate('follow us') ?></h2>
                            <div class="follow-links clearfix">
                                <?php foreach (array('company_twitter', 'company_linkedin', 'company_facebook', 'company_skype') as $num => $service) { ?>
                                    <?php if (!empty($this->companyWebsite[$service])) { ?>
                                        <?php $name = $service == 'company_skype' ? 'skype:' . $this->companyWebsite[$service] : $this->companyWebsite[$service]; ?>
                                        <li class="icon-<?= ($num + 1) ?>"><a href="<?= $name ?>"></a></li>
                                        <a class="follow-link follow-link-<?= ($num + 1) ?>" href="<?= $name ?>"></a>
                                    <?php } ?>
                                <?php } ?>
                            </div>
                        </section>
                    <?php } ?>
                </div>
            </div>
        </footer>

    </div>
</div>