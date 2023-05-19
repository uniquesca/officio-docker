var ProspectsTasksPanel = function(config, owner) {
    this.owner = owner;
    var thisPanel = this;
    Ext.apply(this, config);

    this.ProspectsTasksGrid = new ProspectsTasksGrid({
        region: 'center',
        height: 250,
        panelType: config.panelType
    }, this);

    ProspectsTasksPanel.superclass.constructor.call(this, {
        id: config.panelType + '-left-section-tasks',
        layout: 'border',
        style: 'padding: 5px;',
        items: [
            {
                region: 'north',
                xtype: 'panel',
                layout: 'table',
                cls: 'garytxt',
                height: 15,
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%'
                        }
                    },
                    columns: 2
                },

                items: [
                    {
                        html: '<img src="' + topBaseUrl + '/images/orange-arrow.gif" width="7" height="8" /> ' +
                            '<span style="font-weight: bold;">' + _('Tasks') + '</span>'
                    }, {
                        xtype: 'box',
                        style: 'float: right; cursor: pointer;',
                        autoEl: {tag: 'img', src: topBaseUrl + '/images/refresh12.png', width: 12, height: 12},
                        listeners: {
                            scope: this,
                            render: function(c){
                                c.getEl().on('click', thisPanel.refreshProspectsTasksList.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }
                ]
            },

            this.ProspectsTasksGrid
        ]
    });
};

Ext.extend(ProspectsTasksPanel, Ext.Panel, {
    refreshProspectsTasksList: function() {
        this.ProspectsTasksGrid.store.reload();
    }
});