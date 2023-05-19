function initApplicants(panelType, applicantId) {
    var panelId = panelType + '-tab-panel';
    var panel = Ext.getCmp(panelId);

    if (!panel) {
        panel = new ApplicantsTabPanel({
            id:          panelId,
            cls:         'clients-tab-panel tab-panel-with-combo' + (arrApplicantsSettings.access.queue.view ? '' : ' no-queue-tab-opened'),
            plain:       true,
            region:      'center',
            panelType:   panelType,
            applicantId: applicantId
        });

        panel.render(panelType + '-tab');
    } else {
        if(!empty(applicantId)) {
            if (panelType == 'contacts') {
                // We want open a specific contact's tab
                panel.openApplicantTab({
                    applicantId:   applicantId,
                    applicantName: '',
                    memberType:    'contact'
                });
            } else {
                // We don't know want we want to open :)
                // So we'll load info from the url
                panel.onAfterRender();
            }
        } else {
            // Switch to the Queue/Contacts tab
            if (panel.items.getCount() > 1) {
                var oTab = panel.items.get(1);
                panel.setActiveTab(oTab);
            }
        }
    }
}
