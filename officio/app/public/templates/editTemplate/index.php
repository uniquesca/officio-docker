<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->topBaseUrl . '/assets/plugins/grapesjs/dist/css/grapes.min.css'); ?>
<?php $this->headLink()->appendStylesheet($this->topBaseUrl . '/assets/plugins/bootstrap/dist/css/bootstrap.min.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>

<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/grapesjs/dist/grapes.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/grapesjs-preset-webpage/dist/grapesjs-preset-webpage.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/jquery/dist/jquery.slim.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/jquery/dist/jquery.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/bootstrap/dist/js/bootstrap.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/grapesjs-plugin-iframe/dist/grapesjs-plugin-iframe.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>

<input type="hidden" id="company_name_h" value="<?= $this->pageData['company_name'] ?>"/>
<input type="hidden" id="company_address_h" value="<?= $this->pageData['address'] ?>"/>
<input type="hidden" id="company_phone_h" value="<?php echo $this->pageData['phone'] ?>"/>
<input type="hidden" id="title_h" value="<?php echo $this->pageData['title'] ?>"/>
<input type="hidden" id="assessment_url_h" value="<?php echo $this->pageData['assessment_url'] ?>"/>
<input type="hidden" id="entr_name" value="<?php echo $this->entranceName ?>"/>
<input type="hidden" id="page_name" value="<?php echo $this->currentPage ?>"/>
<input type="hidden" id="json_pages" value="<?= htmlspecialchars(json_encode($this->activePages)) ?>"/>
<script type="text/javascript">
    //<![CDATA[
    var topBaseUrl = '<?= $this->topBaseUrl ?>';
    //]]>
</script>


<nav class="navbar navbar-expand-sm navbar-light bg-light" id="builder-nav">
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav mr-auto">
            <?php foreach ($this->activePages as $link => $page) {
                if ($page['available']) { ?>
                    <li name="<?= $link ?>" class="nav-item">
                        <a class="nav-link" href="/builder/<?= "$this->entranceName/$link" ?>"><?= $page['name'] ?></a>
                    </li>
                <?php }
            } ?>

        </ul>
    </div>
</nav>
<div id="gjs">
    <style>
        #header,
        #content,
        #footer {

            padding: 10px 20px
        }

        #header,
        #footer {
            min-height: 100px;
        }

        #content {
            padding-top: 30px;
            padding-bottom: 30px;
            min-height: calc(100vh);
        }

        img {
            max-width: 100%;
            height: auto;
        }

        .container {
            min-height: 75px;
        }

        .col-sm {
            min-height: 35px;
        }

        .navbar-nav .nav-link {
            opacity: .5;
        }

        a,
        .navbar-nav .nav-link:hover {
            opacity: .9;
        }

        .navbar-nav .active > .nav-link {
            opacity: .9;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(0, 0, 0, 0.5)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 8h24M4 16h24M4 24h24'/%3E%3C/svg%3E");
        }


        <?php echo $this->pageData['css'];
        echo $this->pageData['contentCss']; ?>
    </style>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <div data-gjs-droppable="false" data-gjs-draggable="false">
        <header data-gjs-type="default" data-gjs-removable="false" data-gjs-draggable="false" data-gjs-droppable="true" id='header'>
            <?php echo $this->pageData['header']; ?>
        </header>
        <section data-gjs-type="default" data-gjs-removable="false" data-gjs-draggable="false" data-gjs-droppable="true" id='content'>
            <?php echo $this->pageData['content']; ?>
        </section>
        <footer data-gjs-type="default" data-gjs-removable="false" data-gjs-draggable="false" data-gjs-droppable="true" id='footer'>
            <?php echo $this->pageData['footer']; ?>
        </footer>
    </div>
</div>