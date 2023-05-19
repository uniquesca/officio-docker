Ext.onReady(function() {
    Ext.QuickTips.init();
    $('#url-checker-content').css('min-height', getSuperadminPanelHeight() + 'px');
    new UrlGrid();
    updateSuperadminIFrameHeight('#url-checker-content');
});