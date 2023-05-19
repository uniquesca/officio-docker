var DefaultAnalyticsPanel = function (config) {
    Ext.apply(this, config);

    var analyticsTypeCombo = new Ext.form.ComboBox({
        fieldLabel: 'Analytics Type',
        value:      'applicants',

        store: new Ext.data.SimpleStore({
            fields: ['analyticsTypeId', 'analyticsTypeName'],
            data:   [
                ['applicants', 'Clients'], ['contacts', 'Contacts']
            ]
        }),

        mode:          'local',
        width:         100,
        valueField:    'analyticsTypeId',
        displayField:  'analyticsTypeName',
        triggerAction: 'all',
        lazyRender:    true,
        editable:      false,

        listeners: {
            'select': function (combo) {
                analyticsGrid.analytics_type = combo.getValue();
                analyticsGrid.store.load();
            }
        }
    });

    var analyticsGrid = new DefaultAnalyticsGrid({
        analytics_type: 'applicants',
        height:         getSuperadminPanelHeight()
    });

    DefaultAnalyticsPanel.superclass.constructor.call(this, {
        // frame:     true,
        autoWidth: true,
        layout:    'form',
        height:    getSuperadminPanelHeight(),

        items: [
            analyticsTypeCombo,
            analyticsGrid
        ]
    });
};

Ext.extend(DefaultAnalyticsPanel, Ext.Panel, {});