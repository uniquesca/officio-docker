AutomatedLogPanel = function() {
    this.navigationPanel = new AutomatedLogNavigation();
    this.tabPanel = new AutomatedLogView();
    AutomatedLogPanel.superclass.constructor.call(this, {
        bodyStyle: 'background-color:#fff',
        frame: true,
        autoWidth: true,
        height: 600,
        layout: 'border',
        cls: 'automated-log-panel',
        items: [
            this.navigationPanel,
            this.tabPanel
        ]
    });
};

Ext.extend(AutomatedLogPanel, Ext.Panel, {
});
