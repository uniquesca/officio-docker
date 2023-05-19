var ClientTrackerPanel = function(config) {
    Ext.apply(this, config);

    this.booCompanies=config.booCompanies;

    this.ClientTrackerToolbar = new ClientTrackerToolbar(this, {
        region: 'north',
        height: 50,
        panelType: config.panelType
    });

    this.ClientTrackerFilter = new ClientTrackerFilter(this, {
        forceLayout: true
    });

    this.ClientTrackerFilterPanel = new Ext.Panel({
        title: 'Filter',
        region: 'east',
        cls: 'time-tracker-filter',
        style: 'border: 1px solid #CEDDEF;',
        collapsible: true,
        split: true,
        collapsed: true,
        collapseMode: 'mini',
        width: 250,
        minSize: 250,
        maxSize: 250,
        forceLayout: true,
        items: this.ClientTrackerFilter
    });

    this.ClientTrackerGrid = new ClientTrackerGrid(this, {
        region: 'center',
        split: true
    });

    ClientTrackerPanel.superclass.constructor.call(this, {
        layout: 'border',
        items: [
            this.ClientTrackerToolbar,
            this.ClientTrackerFilterPanel,
            this.ClientTrackerGrid
        ]
    });
};

Ext.extend(ClientTrackerPanel, Ext.Panel, {
});

Ext.reg('appClientTrackerPanel', ClientTrackerPanel);