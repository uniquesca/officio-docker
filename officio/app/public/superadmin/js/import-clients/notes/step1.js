Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();

    $('#import-client-notes-container').css('min-height', getSuperadminPanelHeight() + 'px');

    // Create a grid
    var grid = new ImportClientNotesGrid();
    grid.render('import-client-notes-container');
    updateSuperadminIFrameHeight('#import-client-notes-container');

    if (errorMessage) {
        Ext.simpleConfirmation.error(errorMessage);
    }
});