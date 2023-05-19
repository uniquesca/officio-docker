Ext.onReady(function() {
    Ext.QuickTips.init();

    $('#case-templates-container').css('min-height', getSuperadminPanelHeight() + 'px');

    new CasesTemplatesTabPanel({
        renderTo: 'case-templates-container'
    });

    updateSuperadminIFrameHeight('#case-templates-container');
});