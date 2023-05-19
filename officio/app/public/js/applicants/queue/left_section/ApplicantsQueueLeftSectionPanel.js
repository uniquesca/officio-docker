ApplicantsQueueLeftSectionPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.ApplicantsQueueLeftSectionGrid = new ApplicantsQueueLeftSectionGrid({
        region: 'center',
        arrQueuesToShow: config.arrQueuesToShow,
        panelType: config.panelType,
        height: config.height
    }, this);

    ApplicantsQueueLeftSectionPanel.superclass.constructor.call(this, {
        layout: 'border',
        items: [
            this.ApplicantsQueueLeftSectionGrid
        ]
    });
};

Ext.extend(ApplicantsQueueLeftSectionPanel, Ext.Panel, {
    refreshList: function() {
        this.ApplicantsQueueLeftSectionGrid.store.reload();
    }
});