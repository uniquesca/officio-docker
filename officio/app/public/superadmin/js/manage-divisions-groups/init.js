Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();

    $('#divisions_groups_container').css('min-height', getSuperadminPanelHeight() + 'px');

    // Create a grid
    new DivisionsGroupsGrid();
    updateSuperadminIFrameHeight('#divisions_groups_container');
});