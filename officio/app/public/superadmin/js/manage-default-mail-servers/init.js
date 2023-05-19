Ext.onReady(function () {
    Ext.QuickTips.init();
    $('#admin-manage-default-mail-servers').css('min-height', getSuperadminPanelHeight() + 'px');

    new DefaultMailServersGrid({
        renderTo: 'admin-manage-default-mail-servers'
    });

    updateSuperadminIFrameHeight('#admin-manage-default-mail-servers');
});