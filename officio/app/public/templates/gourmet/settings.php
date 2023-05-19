<table cellpadding="0" cellspacing="0" border="0" class="settings">
    <tr>
        <td colspan="2"><h3>Template settings:</h3></td>
    </tr>
    <tr>
        <?php $selectedBg = isset($this->options['selected-bg']) && !empty($this->options['selected-bg']) ? $this->options['selected-bg'] : false; ?>
        <td valign="top" width="170"><label>Background:</label></td>
        <td valign="top">
            <div class="backgrounds cws-items-block">

                <h4>Default template background:</h4>

                <div class="cws-item<?= (empty($selectedBg) ? ' active' : '') ?>" style="float: left; padding: 5px;">
                    <input type="radio" id="selected-bg-default" name="selected-bg" value=""<?= (empty($selectedBg) ? ' checked="checked"' : '') ?> style="float: left;"/>
                    <label class="cws-template-bg" for="selected-bg-default"><span style="background: url('<?= $this->templateUrl . '/images/body-tail.jpg' ?>') repeat;" alt=""></span></label>
                </div>

                <div style="clear:both;"></div>

                <?php if (!empty($this->defaultBackgrounds)) { ?>

                    <h4>Predefined backgrounds:</h4>

                    <?php foreach ($this->defaultBackgrounds as $key => $bg) { ?>
                        <?php $bg = '/templates/lib/bg/' . $bg; ?>
                        <div class="cws-item<?= ($selectedBg == $bg ? ' active' : '') ?>" style="float: left; padding: 5px;">
                            <input type="radio" id="selected-bg-p<?= $key ?>" name="selected-bg" value="<?= $bg ?>"<?= ($selectedBg == $bg ? ' checked="checked"' : '') ?> style="float: left;"/>
                            <label class="cws-template-bg" for="selected-bg-p<?= $key ?>"><span style="background: url('<?= $this->topBaseUrl . $bg ?>') repeat;" alt=""></label>
                        </div>
                    <?php } ?>

                    <div style="clear:both;"></div>

                <?php } ?>

                <?php if (isset($this->options['background'])) { ?>

                    <h4>Uploaded images:</h4>

                    <?php foreach ($this->options['background'] as $key => $bg) { ?>
                        <div class="cws-item<?= ($selectedBg == $bg ? ' active' : '') ?>" style="float: left; padding: 5px;">
                            <input type="radio" name="selected-bg" id="selected-bg-<?= $key ?>" value="<?= $bg ?>"<?= ($selectedBg == $bg ? ' checked="checked"' : '') ?> style="float: left;"/>
                            <label class="cws-template-bg" for="selected-bg-p<?= $key ?>"><span style="background: url('<?= $this->topBaseUrl . $bg . '?' . microtime(true) ?>') repeat;" alt=""></label>
                            <div style="text-align: center;"><a href="#" data-option="<?= $bg ?>" class="bluelink remove-option">remove</a></div>
                            <input type="hidden" name="background[]" value="<?= $bg ?>"/>
                        </div>
                    <?php } ?>

                    <div style="clear:both;"></div>

                <?php } ?>
            </div>
        </td>
    </tr>
    <tr>
        <td width="170"><label for="background">Add new background:</label></td>
        <td>
            <input type="file" id="background" name="background"/>
        </td>
    </tr>
    <?php $booUseSlider = isset($this->options['slider']) && $this->options['slider'] == 'on'; ?>
    <tr>
        <td width="170"><label for="slider">Use slider:</label></td>
        <td><input type="checkbox" id="slider" name="slider"<?= ($booUseSlider ? ' checked="checked"' : '') ?>
                   onchange="$(this).is(':checked') ? $('#slider-items-block, #new-slide').show() : $('#slider-items-block, #new-slide').hide(); return false;"/></td>
    </tr>
    <?php $selectedSlides = $this->options['selected-slide'] ?? array(); ?>
    <tr id="slider-items-block"<?= ($booUseSlider ? '' : ' class="hidden"') ?>>
        <td valign="top" width="170"><label for="slider-items">Slider images:<br/>&nbsp;&nbsp;(950x440)</label></td>
        <td>
            <div id="slider-items" class="cws-items-block">

                <?php if (!empty($this->defaultSlides)) { ?>

                    <h4>Predefined slides:</h4>

                    <?php foreach ($this->defaultSlides as $key => $slide) { ?>
                        <?php $slide = '/templates/lib/sld/' . $slide; ?>
                        <div style="float: left; padding: 5px;">
                            <input type="checkbox" name="selected-slide[]" id="selected-slide-p<?= $key ?>" value="<?= $slide ?>"<?= (in_array($slide, $selectedSlides) ? ' checked="checked"' : '') ?> style="float:left;"/>
                            <label for="selected-slide-p<?= $key ?>"><img src="<?= $this->topBaseUrl . $slide . '?' . microtime(true) ?>" width="135" height="75" alt=""/></label>
                        </div>
                    <?php } ?>

                    <div style="clear: both;"></div>

                <?php } ?>

                <?php if (isset($this->options['slide'])) { ?>

                    <h4>Uploaded images:</h4>

                    <?php foreach ($this->options['slide'] as $key => $slide) { ?>
                        <div class="cws-item" style="float: left; padding: 5px;">
                            <div>
                                <input type="checkbox" name="selected-slide[]" id="selected-slide-<?= $key ?>" value="<?= $slide ?>"<?= (in_array($slide, $selectedSlides) ? ' checked="checked"' : '') ?> style="float: left;"/>
                                <label for="selected-slide-<?= $key ?>"><img src="<?= $this->topBaseUrl . $slide . '?' . microtime(true) ?>" width="135" height="75" alt=""/></label>
                                <div style="text-align: center;"><a href="#" data-option="<?= $slide ?>" class="bluelink remove-option">remove</a></div>
                                <input type="hidden" name="slide[]" value="<?= $slide ?>"/>
                            </div>
                        </div>
                    <?php } ?>

                    <div style="clear: both;"></div>

                <?php } ?>
            </div>
        </td>
    </tr>
    <tr id="new-slide"<?= ($booUseSlider ? '' : ' class="hidden"') ?>>
        <td width="170"><label for="slide">Add new slide:</label></td>
        <td>
            <input type="file" id="slide" name="slide"/>
        </td>
    </tr>
</table>