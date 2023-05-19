var ApplicantsQueuePanel = function(config, owner) {
    var thisPanel = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.queueGrid = new ApplicantsQueueGrid({
        panelType:           config.panelType,
        runQueryUrl:         topBaseUrl + '/applicants/queue/run',
        columnsCookieId:     config.panelType === 'applicants' ? 'queue_search_columns_settings' : 'contacts_queue_search_columns_settings',
        hidden:              false,
        booHideMassEmailing: false,
        booHideGridToolbar:  false
    }, this);


    ApplicantsQueuePanel.superclass.constructor.call(this, {
        cls: 'extjs-panel-with-border',
        bodyStyle: 'background-color:white; padding: 20px',
        style: 'background-color:white;',
        buttonAlign: 'left',
        autoHeight: true,
        items: [
            this.queueGrid
        ]
    });
};

Ext.extend(ApplicantsQueuePanel, Ext.Panel, {
});