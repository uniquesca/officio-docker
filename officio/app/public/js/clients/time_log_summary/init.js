function initTimeLogSummary() {
    var panelId = 'time-log-summary-tab-panel';
    var panel = Ext.getCmp(panelId);

    if (!panel) {
        var oTimeLogSummaryPanel = new TimeLogSummaryPanel({
            title: _('Time Log Summary'),
            cls: 'clients-tab-panel',
            plain: true
        });

        var tabPanel = new Ext.TabPanel({
            id: panelId,
            renderTo: 'time-log-summary-tab',
            autoWidth: true,
            autoHeight: true,
            plain: true,
            activeTab: 0,
            enableTabScroll: true,
            minTabWidth: 200,
            cls: 'clients-tab-panel',
            bodyStyle: 'padding: 10px',

            plugins: [
                new Ext.ux.TabUniquesNavigationMenu({})
            ],

            items: oTimeLogSummaryPanel
        });

        tabPanel.doLayout();
    }
}