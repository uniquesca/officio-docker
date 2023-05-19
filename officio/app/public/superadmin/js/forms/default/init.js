Ext.onReady(function() {
    Ext.QuickTips.init();
    $('#forms_default_container').css('min-height', getSuperadminPanelHeight() + 'px');
    new DefaultFormsGrid();
    updateSuperadminIFrameHeight('#forms_default_container');
});