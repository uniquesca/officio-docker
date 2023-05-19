Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();

    $('#vac_list_container').css('min-height', getSuperadminPanelHeight() + 'px');

    // Create a grid
    new ManageVACsGrid();

    updateSuperadminIFrameHeight('#vac_list_container');
});