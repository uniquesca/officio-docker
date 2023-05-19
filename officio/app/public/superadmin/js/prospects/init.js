Ext.onReady(function(){
    Ext.QuickTips.init();
    Ext.form.Field.prototype.msgTarget = 'side';

    $("#manage_company_prospects_container").tabs();
    $('.main-prospects-tabs a').click(function(){
        var containerId = $(this).attr('href');

        $(containerId).css('min-height', getSuperadminPanelHeight() - $('#manage_company_prospects_container').outerHeight() - $(containerId).outerHeight() + $(containerId).height() + 'px');
        setTimeout(function(){
            updateSuperadminIFrameHeight(containerId, true);
        }, 200);
    });

    initProspectsSettings();
    showQuestionnairesSection();
});