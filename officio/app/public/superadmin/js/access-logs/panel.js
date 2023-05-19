var AccessLogsPanel = function (config) {
    Ext.apply(this, config);

    var logsGrid = new AccessLogsGrid({
        region: 'center'
    });

    var filterForm = new AccessLogsFilterPanel({
        title: _('Filter Events Log by:'),
        region: 'east'
    });

    AccessLogsPanel.superclass.constructor.call(this, {
        frame: false,
        autoWidth: true,
        height: getSuperadminPanelHeight(),
        layout: 'border',
        items: [
            logsGrid, filterForm
        ]
    });
};

Ext.extend(AccessLogsPanel, Ext.Panel, {
});