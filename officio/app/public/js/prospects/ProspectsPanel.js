var ProspectsPanel = function(config) {
    Ext.apply(this, config);

    this.ProspectsTabPanel = new ProspectsTabPanel({
        id: config.panelType + '-tab-panel',
        cls: 'clients-tab-panel tab-panel-with-combo',
        plain: true,
        region: 'center',
        panelType: config.panelType,
        width: Ext.getCmp(config.panelType + '-tab').getWidth(),
        height:   initPanelSize(),
        initSettings: config.initSettings
    });

    ProspectsPanel.superclass.constructor.call(this, {
        height: initPanelSize(true),
        stateful: false,
        items: [
            this.ProspectsTabPanel
        ]
    });
};

Ext.extend(ProspectsPanel, Ext.Panel, {
});
