Ext.onReady(function() {
    Ext.QuickTips.init();
    $('#logs_container').css('min-height', getSuperadminPanelHeight() + 'px');
    new AccessLogsPanel({
        renderTo: 'logs_container'
    });
    updateSuperadminIFrameHeight('#logs_container');
});