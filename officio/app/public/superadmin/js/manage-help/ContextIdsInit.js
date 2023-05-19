Ext.onReady(function() {
    Ext.QuickTips.init();

    new ContextIdsTabPanel({
        renderTo: 'help_context_ids_container'
    });

    if (!$('#help_context_ids_container').length) {
        updateSuperadminIFrameHeight('#help_context_ids_container');
    }
});