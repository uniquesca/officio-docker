Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();
    $('#import-client-notes-container').css('min-height', getSuperadminPanelHeight() + 'px');
    updateSuperadminIFrameHeight('#import-client-notes-container');

    $('#check_all_fields').click(function(){
        $('.columns-mapping td input[type=checkbox]').trigger('click');
    });

    $('.show_details').mouseover(function(){
        $('.import_errors_details').hide();
        $(this).parent().find('.import_errors_details').show();
    });

    $('.import_errors_details').mouseleave(function(){
        $(this).hide();
    });

    if (errorMessage) {
        Ext.simpleConfirmation.error(errorMessage);
    }
});