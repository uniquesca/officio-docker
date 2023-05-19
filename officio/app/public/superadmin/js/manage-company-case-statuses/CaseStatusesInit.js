Ext.onReady(function() {
    Ext.QuickTips.init();

    new CaseStatusesListsGrid({
        renderTo: 'company-case-statuses-container'
    });

    if (!$('#company-case-statuses-container').length) {
        updateSuperadminIFrameHeight('#company-case-statuses-container');
    }
});