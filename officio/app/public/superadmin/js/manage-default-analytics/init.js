Ext.onReady(function () {
    Ext.QuickTips.init();

    $('#admin-default-analytics').css('min-height', getSuperadminPanelHeight() + 'px');
    new DefaultAnalyticsPanel({
        renderTo: 'admin-default-analytics'
    });
    updateSuperadminIFrameHeight('#admin-default-analytics');
});