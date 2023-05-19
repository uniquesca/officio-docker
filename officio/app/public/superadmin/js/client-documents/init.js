Ext.onReady(function () {
    // Init tooltips
    Ext.QuickTips.init();

    $('#admin-client-documents-content').css('min-height', getSuperadminPanelHeight() + 'px');

    new ClientDocumentsTree({
        renderTo: 'admin-client-documents-content'
    });
    updateSuperadminIFrameHeight('#admin-client-documents-content');
});