var TimeLogSummaryFilter = function (config, viewer) {
    this.viewer = viewer;
    Ext.apply(this, config);

    var startDateId = Ext.id();
    var endDateId = Ext.id();
    this.ownerFilterId = Ext.id();
    TimeLogSummaryFilter.superclass.constructor.call(this, {
        buttonAlign: 'center',
        style: 'margin: 0 10px',
        items: [
            {
                xtype: 'fieldset',
                title: _('Show'),
                cls: 'time-log-summary-fieldset',
                autoHeight: true,
                defaultType: 'radio', // each item will be a radio button
                items: [
                    {
                        checked: true,
                        hideLabel: true,
                        boxLabel: _('All'),
                        name: 'tracker-filter-billed',
                        inputValue: ''
                    },
                    {
                        hideLabel: true,
                        boxLabel: _('Billed Times'),
                        name: 'tracker-filter-billed',
                        inputValue: 'Y'
                    },
                    {
                        hideLabel: true,
                        boxLabel: _('Unbilled Times'),
                        name: 'tracker-filter-billed',
                        inputValue: 'N'
                    }
                ]
            },
            {
                xtype: 'fieldset',
                title: _('Posted by'),
                cls: 'time-log-summary-fieldset',
                autoHeight: true,
                items: {
                    id: this.ownerFilterId,
                    xtype: 'combo',
                    hideLabel: true,
                    store: new Ext.data.ArrayStore({
                        fields: ['owner_id', 'owner_name'],
                        data: typeof (arrTimeLogSummarySettings) !== 'undefined' ? arrTimeLogSummarySettings.users : []
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
                title: _('Date Range'),
                cls: 'time-log-summary-fieldset',
                labelWidth: 45,
                autoHeight: true,
                items: [
                    {
                        id: startDateId,
                        name: 'tracker-filter-date-from',
                        xtype: 'datefield',
                        fieldLabel: _('From'),
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
                        fieldLabel: _('To'),
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
                cls: 'orange-btn',
                scope: this,
                handler: this.applyFilter
            }
        ]
    });
};

Ext.extend(TimeLogSummaryFilter, Ext.form.FormPanel, {
    applyFilter: function () {
        var store = this.viewer.TimeLogSummaryGrid.store;
        var params = this.getForm().getValues();
        Ext.apply(store.baseParams, params);

        store.load();
    },

    resetFilter: function () {
        this.getForm().reset();
        Ext.getCmp(this.ownerFilterId).setValue(0);
        this.applyFilter();
    }
});