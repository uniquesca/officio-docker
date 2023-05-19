var ClientTrackerFilter = function (viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);

    var startDateId = Ext.id();
    var endDateId = Ext.id();
    this.ownerFilterId = Ext.id();
    ClientTrackerFilter.superclass.constructor.call(this, {
        buttonAlign: 'center',
        items: [
            {
                xtype: 'fieldset',
                title: 'Show',
                hidden: this.viewer.booCompanies,
                autoHeight: true,
                defaultType: 'radio', // each item will be a radio button
                items: [
                    {
                        checked: true,
                        hideLabel: true,
                        boxLabel: 'All',
                        name: 'tracker-filter-billed',
                        inputValue: ''
                    },
                    {
                        hideLabel: true,
                        boxLabel: 'Billed Times',
                        name: 'tracker-filter-billed',
                        inputValue: 'Y'
                    },
                    {
                        hideLabel: true,
                        boxLabel: 'Unbilled Times',
                        name: 'tracker-filter-billed',
                        inputValue: 'N'
                    }
                ]
            },
            {
                xtype: 'fieldset',
                title: 'Posted by',
                autoHeight: true,
                items: {
                    id: this.ownerFilterId,
                    xtype: 'combo',
                    hideLabel: true,
                    store: new Ext.data.ArrayStore({
                        fields: ['owner_id', 'owner_name'],
                        data: typeof(arrTimeTrackerSettings)!=='undefined' ? arrTimeTrackerSettings.users : []
                    }),
                    hiddenName: 'tracker-filter-owner',
                    displayField: 'owner_name',
                    valueField: 'owner_id',
                    typeAhead: true,
                    editable: false,
                    mode: 'local',
                    triggerAction: 'all',
                    selectOnFocus: true,
                    width: 220,
                    value: 0
                }
            },
            {
                xtype: 'fieldset',
                title: 'Date Range',
                labelWidth: 45,
                autoHeight: true,
                items: [
                    {
                        id: startDateId,
                        name: 'tracker-filter-date-from',
                        xtype: 'datefield',
                        fieldLabel: 'From',
                        width: 170,
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        vtype: 'daterange',
                        endDateField: endDateId
                    },
                    {
                        id: endDateId,
                        name: 'tracker-filter-date-to',
                        xtype: 'datefield',
                        fieldLabel: 'To',
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        width: 170,
                        vtype: 'daterange',
                        startDateField: startDateId // id of the start date field
                    }
                ]
            }
        ],

        buttons: [
            {
                text: '<i class="las la-undo-alt"></i>' + _('Reset filter'),
                scope: this,
                handler: this.resetFilter
            },
            {
                text: '<i class="las la-filter"></i>' + _('Apply filter'),
                cls:  'orange-btn',
                scope: this,
                handler: this.applyFilter
            }
        ]
    });
};

Ext.extend(ClientTrackerFilter, Ext.form.FormPanel, {
    applyFilter: function() {
        var store = this.viewer.ClientTrackerGrid.store;
        var params = this.getForm().getValues();
        Ext.apply(store.baseParams, params);

        store.load();
    },

    resetFilter: function() {
        this.getForm().reset();
        Ext.getCmp(this.ownerFilterId).setValue(0);
        this.applyFilter();
    }
});

Ext.reg('appClientTrackerFilter', ClientTrackerFilter);