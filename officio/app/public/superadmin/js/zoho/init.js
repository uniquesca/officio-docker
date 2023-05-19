Ext.onReady(function () {
    Ext.QuickTips.init();
    $('#zoho_settings_panel').css('min-height', getSuperadminPanelHeight() + 'px');

    new ZohoTabPanel({
        renderTo: 'zoho_settings_panel'
    });
});