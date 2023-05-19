Ext.onReady(function() {
    Ext.QuickTips.init();

    $('#applicant-types-container').css('min-height', getSuperadminPanelHeight() + 'px');

    new ApplicantTypesTabPanel({
        renderTo: 'applicant-types-container'
    });

    updateSuperadminIFrameHeight('#applicant-types-container');
});