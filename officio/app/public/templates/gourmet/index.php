<?php use Officio\Common\Service\Settings;

$siteBg = isset($this->companyWebsite['options']['background']) && !empty($this->companyWebsite['options']['background']) ? $this->companyWebsite['options']['background'] : $this->templateUrl . '/images/body-tail.jpg'; ?>
<?php $assessmentBackground = empty($this->companyWebsite['assessment_background']) ? '' : 'background-color: ' . $this->companyWebsite['assessment_background'] . ';'; ?>

<?php $this->headStyle()->captureStart() ?>
body { background: url(<?= $siteBg ?>) top center repeat; }
.slider-nav, #search-form a, .sf-menu ul {behavior: url(<?= $this->templateUrl ?>/js/PIE.htc);}
.content-box,
.sf-menu > li > a,
.button,
.sidebar,
#subs-form input, #subs-form a,
#contact-form input, #contact-form textarea {behavior: url(<?= $this->templateUrl ?>/js/PIE.htc); position: relative;}
li.assessment > a:link, li.assessment > a:visited { <?= $assessmentBackground ?> color: <?= $this->companyWebsite['assessment_foreground'] ?>; }
<?php $this->headStyle()->captureEnd(); ?>

<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/reset.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/superfish.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/slider.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/forms.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/ie.css', 'screen', 'lt IE 9'); ?>

<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery-1.7.1.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/superfish.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/FF-cash.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.hoverIntent.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.responsivemenu.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/cufon-yui.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/cufon-replace.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/Avanti_400.font.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/Walkway_Bold_400.font.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/slides.min.jquery.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/jquery.easing.1.3.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/forms.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/html5.js', 'text/javascript', array('conditional' => 'lt IE 9')); ?>

<div id="page2">
    <div class="bg">
        <!--======================== header ============================-->
        <header>
            <div class="main">
                <div class="indents">

                    <!--======================== logo ============================-->
                    <?php if (!empty($this->companyWebsite['company_logo'])) { ?>
                        <h1 class="company-title"><a href="<?= $this->baseUrl . '/webs/' . $this->entranceName ?>"><img src="<?= $this->uploadsUrl . $this->companyWebsite['company_logo'] ?>" hspace="2" vspace="2" border="0" align="bottom"
                                                                                                                        alt=""/></a></h1>
                    <?php } else {
                        if (!empty($this->companyWebsite['company_name'])) { ?>
                            <div class="company-title"><?= $this->companyWebsite['company_name'] ?></div>
                        <?php }
                    } ?>

                    <?php if ($this->companyWebsite['login_block_on'] == 'Y') { ?>
                        <div class="login-block">
                            <?php if (!empty($this->userId)) { ?>
                                <div class="login-txt right">
                                    <div class="left" style="padding: 4px 20px 0 0;"><?= $this->userName ?></div>
                                    <div class="left"><a href="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/logout" class="button">Logout</a></div>
                                    <div class="clear"></div>
                                </div>
                            <?php } else { ?>
                                <form method="post" class="form right" id="login" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/login">
                                    <fieldset>
                                        <label for="username"><?= $this->translate('Username') ?>:</label>
                                        <input type="text" id="username" name="username" value=""/>
                                        <label for="password"><?= $this->translate('Password') ?>:</label>
                                        <input type="password" id="password" name="password" value=""/>
                                        <a class="button" id="login-button">Login</a>
                                        <div class="clear"></div>
                                    </fieldset>
                                    <div class="error"><?= $this->escapeHtml($this->loginError) ?></div>
                                </form>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="clear"></div>

                </div>
                <div class="content-box">
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
                    <?php if ($this->booUseSlider && $this->currentPage == 'homepage') { ?>
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
        </header>
        <!--======================== content ===========================-->
        <?php $booExternalLinks = $this->companyWebsite['external_links_on'] == 'Y' && !empty($this->companyWebsite['external_links']) && $this->currentPage != 'assessment'; ?>
        <section id="content">
            <div class="wrapper">
                <div class="main">
                    <div class="content-box">
                        <div class="wrapper">
                            <div<?= ($booExternalLinks ? ' class="col-8"' : '') ?>>
                                <div class="content-indents1">

                                    <?php if ($this->currentPage != 'assessment' && $this->currentPage != 'homepage') { ?>
                                        <h3 class="p5"><?= $this->pageTitle ?></h3>
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

                                    <?php if ($this->currentPage == 'contact') { ?>
                                        <form id="contact-form" method="post" autocomplete="off" action="<?= $this->baseUrl ?>/webs/<?= $this->entranceName ?>/send-message">
                                            <div class="success">
                                                Contact form submitted!<br>
                                                <strong>We will be in touch soon.</strong>
                                            </div>
                                            <fieldset>
                                                <label class="name">
                                                    <input type="text" value="your name">
                                                    <span class="error">*This is not a valid name.</span> <span class="empty">*This field is required.</span>
                                                </label>
                                                <label class="phone">
                                                    <input type="tel" value="telephone">
                                                    <span class="error">*This is not a valid phone number.</span> <span class="empty">*This field is required.</span>
                                                </label>
                                                <label class="email">
                                                    <input type="text" value="e-mail">
                                                    <span class="error">*This is not a valid email address.</span> <span class="empty">*This field is required.</span>
                                                </label>
                                                <label class="message">
                                                    <textarea>message</textarea>
                                                    <span class="error">*The message is too short.</span> <span class="empty">*This field is required.</span>
                                                </label>
                                                <div class="buttons-wrapper">
                                                    <a class="button" data-type="reset"><span>Clear</span></a>
                                                    <a class="button" data-type="submit"><span>Send</span></a>
                                                </div>
                                            </fieldset>
                                        </form>
                                        <dl class="p6-1">
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
                                                <dd><?= $this->translate('E-mail') ?>: <a href="mailto:<?= $this->escapeUrl($this->companyWebsite['company_email']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_email']) ?></a>
                                                </dd>
                                            <?php } ?>
                                            <?php if (!empty($this->companyWebsite['company_skype'])) { ?>
                                                <dd><?= $this->translate('Skype') ?>: <a href="skype:<?= $this->escapeHtmlAttr($this->companyWebsite['company_skype']) ?>"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></a>
                                                </dd>
                                            <?php } ?>
                                        </dl>
                                        <div class="clear"></div>
                                    <?php } ?>

                                </div>
                            </div>

                            <?php if ($booExternalLinks) { ?>
                                <div class="col-4x last-item">
                                    <div class="sidebar">
                                        <?php if (!empty($this->companyWebsite['external_links_title'])) { ?>
                                            <h5 class="p5-1"><?= $this->escapeHtml($this->companyWebsite['company_skype']) ?></h5>
                                        <?php } ?>
                                        <ul class="list-1">
                                            <?php $linksNum = 0; ?>
                                            <?php foreach ($this->companyWebsite['external_links'] as $name => $link) { ?>
                                                <li<?= (++$linksNum == count($this->companyWebsite['external_links']) ? ' class="last-item"' : '') ?>><a href="<?= Settings::formatUrl($link) ?>" target="_blank"><?= $this->escapeHtml(
                                                            $name
                                                        ) ?></a></li>
                                            <?php } ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!--======================== footer ============================-->
        <footer>
            <div class="main">
                <div class="indents border-top">
                    <div class="wrapper">
                        <div class="nav-links">
                            <?php foreach ($this->menu as $page => $link) { ?>
                                <a href="<?= $link ?>"><?= trim($this->escapeHtml($this->companyWebsite[$page . '_name'])) ?></a>
                            <?php } ?>
                        </div>
                        <?php if (!empty($this->companyWebsite['company_skype']) ||
                            !empty($this->companyWebsite['company_linkedin']) ||
                            !empty($this->companyWebsite['company_facebook']) ||
                            !empty($this->companyWebsite['company_twitter'])) { ?>

                            <ul class="social-buttons">
                                <?php foreach (array('company_skype', 'company_facebook', 'company_twitter', 'company_linkedin') as $num => $service) { ?>
                                    <?php if (!empty($this->companyWebsite[$service])) { ?>
                                        <?php $name = $service == 'company_skype' ? 'skype:' . $this->companyWebsite[$service] : $this->companyWebsite[$service]; ?>
                                        <li><a href="<?= $name ?>" class="item-<?= ($num + 1) ?>"><strong><span></span></strong></a></li>
                                    <?php } ?>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                        <?php if (!empty($this->companyWebsite['footer_text'])) { ?>
                            <div class="footer-text"><?= nl2br($this->escapeHtml($this->companyWebsite['footer_text'])) ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <script type="text/javascript">Cufon.now();</script>
</div>