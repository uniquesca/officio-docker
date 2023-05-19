<?php if (isset($this->options['background'])) { ?>
    <?php foreach ($this->options['background'] as $key => $bg) { ?>
        <input type="hidden" name="background[]" value="<?= $bg ?>"/>
    <?php } ?>
<?php } ?>

<table cellpadding="0" cellspacing="0" border="0" class="settings">
    <tr>
        <td colspan="2"><h3>Template settings:</h3></td>
    </tr>
    <tr>
        <?php $headerBg = $this->options['header-bg'] ?? '#191919'; ?>
        <td width="170"><label>Header background color:</label></td>
        <td>
            <?php foreach (array('#191919', '#42392A', '#1F87C4') as $key => $color) { ?>
                <div class="cws-color-item" style="background-color: <?= $color ?>;">
                    <input type="radio" name="header-bg" value="<?= $color ?>" <?= ($headerBg == $color ? ' checked="checked"' : '') ?> />
                </div>
            <?php } ?>

            <div style="float:left; padding-top:10px;">
                <input type="radio" id="header-bg-color-picker" name="header-bg" class="color_picker_radio">
                <label for="header-bg-color-picker">
                    or choose from the color picker
                    <input type="text" name="header-bg" value="<?= $headerBg ?>" class="colorPickerSettings"/>
                </label>
            </div>
        </td>
    </tr>
    <tr>
        <?php $siteBg = $this->options['site-bg'] ?? '#FFFFFF'; ?>
        <td width="170"><label>Website background color:</label></td>
        <td>
            <?php foreach (array('#FFFFFF', '#F9FFDB') as $key => $color) { ?>
                <div class="cws-color-item" style="background-color: <?= $color ?>;">
                    <input type="radio" name="site-bg" value="<?= $color ?>" <?= ($siteBg == $color ? ' checked="checked"' : '') ?> />
                </div>
            <?php } ?>

            <div style="float:left; padding-top:10px;">
                <input type="radio" id="site-bg-color-picker" name="site-bg" class="color_picker_radio">
                <label for="site-bg-color-picker">
                    or choose from the color picker
                    <input type="text" name="site-bg" value="<?= $siteBg ?>" class="colorPickerSettings"/>
                </label>
            </div>
        </td>
    </tr>
    <tr>
        <?php $menu_color_on = $this->options['menu-text-color-on'] ?? '#83C00B'; ?>
        <td width="170"><label>Menu text color on:</label></td>
        <td>
            <div style="float:left; padding-left:7px;">
                <input type="text" name="menu-text-color-on" value="<?= $menu_color_on ?>" class="colorPickerSettings"/>
            </div>
        </td>
    </tr>
    <tr>
        <?php $menu_color_off = $this->options['menu-text-color-off'] ?? '#FFFFFF'; ?>
        <td width="170"><label>Menu text color off:</label></td>
        <td>
            <div style="float:left; padding-left:7px;">
                <input type="text" name="menu-text-color-off" value="<?= $menu_color_off ?>" class="colorPickerSettings"/>
            </div>
        </td>
    </tr>
    <tr>
        <?php $menu_highlight_bar_color = $this->options['menu-highlight-bar-color'] ?? 'transparent'; ?>
        <td width="170"><label>Menu highlight bar:</label></td>
        <td>
            <div style="float:left; padding-left:7px;">
                <input type="text" name="menu-highlight-bar-color" value="<?= $menu_highlight_bar_color ?>" class="colorPickerSettings"/>
            </div>
        </td>
    </tr>
    <tr>
        <?php $footer_bg_color = $this->options['footer-bg-color'] ?? '#72685D'; ?>
        <td width="170"><label>Footer background color:</label></td>
        <td>
            <div style="float:left; padding-left:7px;">
                <input type="text" name="footer-bg-color" value="<?= $footer_bg_color ?>" class="colorPickerSettings"/>
            </div>
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

<script type="text/javascript">
    $(document).ready(function () {
        $('.colorPickerSettings').colorPicker();
        $('.colorPicker-palette').css('width', '181px');

        // if no radios were selected, select color picker radio
        $('.color_picker_radio').each(function () {
            var name = $(this).attr('name');

            if ($(':input[type=radio][name=' + name + ']:checked').length == 0) {
                $(this).prop('checked', true);
                $(this).trigger('change');
            }
        });

        // if not color picker radio is checked, remove name from hidden color picker input
        $(':radio[name=header-bg], :radio[name=site-bg]').change(function () {
            var name = $(this).attr('name');

            $(':input[type=text][name=' + name + ']').attr('name', $(this).hasClass('color_picker_radio') ? name : '');
        });
    });
</script>