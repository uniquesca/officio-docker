Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();

    $('#offices_list_container').css('min-height', getSuperadminPanelHeight() + 'px');

    // Create a grid
    new ManageOfficesGrid();

    updateSuperadminIFrameHeight('#offices_list_container');
});