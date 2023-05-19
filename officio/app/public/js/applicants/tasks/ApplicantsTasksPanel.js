ApplicantsTasksPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.ApplicantsTasksGrid = new ApplicantsTasksGrid({
        region: 'center',
        panelType: config.panelType
    }, this);
    ApplicantsTasksPanel.superclass.constructor.call(this, {
        layout: 'border',
        items: [
            this.ApplicantsTasksGrid
        ]
    });
};

Ext.extend(ApplicantsTasksPanel, Ext.Panel, {
    refreshList: function() {
        this.ApplicantsTasksGrid.store.reload();
    }
});