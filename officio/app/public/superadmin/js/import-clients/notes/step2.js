Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();
    $('#import-client-notes-container').css('min-height', getSuperadminPanelHeight() + 'px');
    updateSuperadminIFrameHeight('#import-client-notes-container');

    var els = Ext.select('input.datepicker', true);
    els.each(function(el) {
        var val = $('#' + el.id).val();
        var newId = 'datepicker_' + el.id;

            var df = new Ext.form.DateField({
                id: newId,
                format: dateFormatFull,
                maxLength: 12, // Fix issue with date entering in 'full' format
                altFormats: dateFormatFull + '|' + dateFormatShort
            });
            df.applyToMarkup(el);

            // Chrome fix - invalid date on applying to entered date
            if (!empty(val) && Ext.isChrome) {
                try {
                    var dt = new Date(val);
                    df.setValue(dt);
                } catch(err) {
                }
            }

        $('#' + el.id).removeClass('datepicker');
    });

    if (errorMessage) {
        Ext.simpleConfirmation.error(errorMessage);
    }
});