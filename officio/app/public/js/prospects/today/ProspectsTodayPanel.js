var ProspectsTodayPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.ProspectsTodayGrid = new ProspectsTodayGrid({
        id: config.panelType + '-tgrid',
        region: 'center',
        panelType: config.panelType,
        initSettings: config.initSettings
    }, this);

    ProspectsTodayPanel.superclass.constructor.call(this, {
        layout: 'border',
        items: [
            // {
            //     region: 'north',
            //     xtype: 'panel',
            //     layout: 'table',
            //     cls: 'garytxt',
            //     height: 15,
            //     layoutConfig: {
            //         tableAttrs: {
            //             style: {
            //                 width: '100%'
            //             }
            //         },
            //         columns: 1
            //     },
            //
            //     items: [
            //         {
            //             html: '<img src="' + topBaseUrl + '/images/orange-arrow.gif" width="7" height="8" /> ' +
            //                 '<span style="font-weight: bold;">' + _("Today's Prospects") + '</span>'
            //         }
            //     ]
            // },

            this.ProspectsTodayGrid
        ]
    });
};

Ext.extend(ProspectsTodayPanel, Ext.Panel, {
});