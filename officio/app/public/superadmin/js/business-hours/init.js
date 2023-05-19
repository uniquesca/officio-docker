Ext.onReady(function () {
    Ext.QuickTips.init();

    if ($('#schedule-container').length) {
        $('#schedule-container').empty().css('min-height', getSuperadminPanelHeight() + 'px');

        new BusinessSchedulePanel({
            renderTo:   'schedule-container',
            member_id:  typeof edit_member_id == 'number' || typeof edit_member_id == 'string' ? edit_member_id : null,
            company_id: typeof company_id == 'number' || typeof company_id == 'string' ? company_id : null
        });

        if (!$('#schedule-container').parents('#manage-company-content').length) {
            updateSuperadminIFrameHeight('#schedule-container');
        }
    }
});