<?php $this->headMeta()->appendName('viewport', 'width=device-width; initial-scale=1.0'); ?>

<?php $this->headLink()->appendStylesheet($this->topBaseUrl . '/assets/plugins/bootstrap/dist/css/bootstrap.min.css'); ?>
<?php $this->headLink()->appendStylesheet($this->templateUrl . '/css/style.css'); ?>

<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/jquery/dist/jquery.slim.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/jquery/dist/jquery.min.js'); ?>
<?php $this->headScript()->appendFile($this->topBaseUrl . '/assets/plugins/bootstrap/dist/js/bootstrap.min.js'); ?>
<?php $this->headScript()->appendFile($this->templateUrl . '/js/script.js'); ?>

    <input type="hidden" id="entr_name" value="<?php echo $this->entranceName ?>"/>
    <input type="hidden" id="company_name_h" value="<?= $this->pageData['company_name'] ?>"/>
    <input type="hidden" id="company_address_h" value="<?= $this->pageData['address'] ?>"/>
    <input type="hidden" id="company_phone_h" value="<?php echo $this->pageData['phone'] ?>"/>
    <input type="hidden" id="title_h" value="<?php echo $this->pageData['title'] ?>"/>
    <input type="hidden" id="assessment_url_h" value="<?php echo $this->pageData['assessment_url'] ?>"/>

    <style>
        <?php echo $this->pageData['css'];
        echo $this->pageData['contentCss']; ?>
    </style>

<?php
echo $this->pageData['header'];
echo $this->pageData['content'];
echo $this->pageData['footer'];
?>