Ext.onReady(function () {
    Ext.QuickTips.init();

    $('#automatic-reminders-content').css('min-height', getSuperadminPanelHeight() + 'px');

    new AutomaticRemindersGrid({
        renderTo: 'automatic-reminders-content',
        height: getSuperadminPanelHeight(),
        width: '100%'
    });
    updateSuperadminIFrameHeight('#automatic-reminders-content');
});