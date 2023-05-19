var DefaultAnalyticsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisWindow = this;

    var arrDateByCombo = [
        ['years', _('Year')],
        ['quarters', _('Quarter')],
        ['same_quarter_last_years', _('Same quarter prev years')],
        ['months', _('Month')],
        ['same_month_last_years', _('Same month prev years')],
        ['month_days', _('Day')],
        ['week_days', _('Weekday')]
    ];

    // Generate the list of allowed options for the "Year" combo
    // from 1900 till current year + 100
    var currentYear = new Date().getFullYear(), years = [];
    var startYear   = 1900;
    while (startYear <= currentYear + 100) {
        years.push([startYear, startYear]);
        startYear++;
    }

    // These are custom options that will be shown at the top of the list
    this.arrStartingYearOptions = years.slice();
    this.arrStartingYearOptions.unshift(['YTD', _('YTD')]);

    this.arrStartingQuarterOptions = years.slice();
    this.arrStartingQuarterOptions.unshift(['QTD', _('QTD')]);

    this.arrStartingMonthOptions = years.slice();
    this.arrStartingMonthOptions.unshift(['MTD', _('MTD')]);

    this.arrStartingAllOptions = years.slice();
    this.arrStartingAllOptions.unshift(['YTD', _('YTD')]);
    this.arrStartingAllOptions.unshift(['QTD', _('QTD')]);
    this.arrStartingAllOptions.unshift(['MTD', _('MTD')]);

    var arrQuartersNames = [
        ['1', _('Q1')],
        ['2', _('Q2')],
        ['3', _('Q3')],
        ['4', _('Q4')]
    ];

    var arrMonthNames = [
        ['1', _('01 - January')],
        ['2', _('02 - February')],
        ['3', _('03 - March')],
        ['4', _('04 - April')],
        ['5', _('05 - May')],
        ['6', _('06 - June')],
        ['7', _('07 - July')],
        ['8', _('08 - August')],
        ['9', _('09 - September')],
        ['10', _('10 - October')],
        ['11', _('11 - November')],
        ['12', _('12 - December')]
    ];

    var arrGoBackNumbers = [];
    for (var i = 0; i <= 100; i++) {
        arrGoBackNumbers.push([i, i]);
    }

    var arrGoBackCombo = [
        ['years', _('Years')],
        ['quarters', _('Quarters')],
        ['months', _('Months')]
    ];

    var arrBreakdownFields = arrFields[this.analytics_type];

    var arrFormItems = [
        {
            name:  'analytics_id',
            xtype: 'hidden'
        }, {
            name:  'analytics_type',
            xtype: 'hidden',
            value: this.analytics_type
        }, {
            fieldLabel: 'Name',
            xtype:      'textfield',
            name:       'analytics_name',
            allowBlank: false,
            width:      225
        }, {
            fieldLabel: 'Show chart',
            xtype:      'checkbox',
            name:       'show_chart',
            checked:    empty(this.analytics_record['analytics_id']) ? 1 : 0
        }, {
            fieldLabel: 'Chart type',
            xtype:      'combo',
            name:       'chart_type',
            allowBlank: false,

            store: {
                xtype:  'arraystore',
                fields: ['chart_type_id', 'chart_type_name'],
                data:   [
                    ['bar', 'Bar Chart'],
                    ['doughnut_full', 'Doughnut Chart (full circle)'],
                    ['doughnut_half', 'Doughnut Chart (semi circle)'],
                    ['pie_full', 'Pie Chart (full circle)'],
                    ['pie_half', 'Pie Chart (semi circle)']
                ]
            },

            tpl: new Ext.XTemplate(
                '<tpl for=".">' +
                '<div class="x-combo-list-item analytics_chart_{chart_type_id}">{chart_type_name}</div>' +
                '</tpl>'
            ),

            displayField:   'chart_type_name',
            valueField:     'chart_type_id',
            mode:           'local',
            value:          'bar',
            width:          225,
            forceSelection: true,
            editable:       false,
            triggerAction:  'all',
            selectOnFocus:  true,
            typeAhead:      false
        }
    ];

    // 2 rows only
    for (i = 1; i <= 2; i++) {
        arrFormItems.push({
            xtype:  'container',
            layout: 'column',
            items:  [
                {
                    layout:     'form',
                    bodyStyle:  'background-color:white;',
                    labelWidth: 100,
                    width:      335,

                    items: {
                        xtype:           'combo',
                        fieldLabel:      i === 1 ? _('Group by (Y axis)') : _('Group by (X axis)'),
                        emptyText:       i === 1 ? _('Please select the field...') : _('Optional...'),
                        allowBlank:      i !== 1,
                        name:            'breakdown_field_' + i,
                        linkedFieldName: 'breakdown_field_date_operator_container_' + i,
                        width:           225,

                        store: new Ext.data.Store({
                            reader: new Ext.data.JsonReader({
                                fields: [
                                    {name: 'field_generated_full_id'},
                                    {name: 'field_unique_id'},
                                    {name: 'field_client_type'},
                                    {name: 'field_name'},
                                    {name: 'field_type'},
                                    {name: 'field_group_name'},
                                    {name: 'field_template_id'},
                                    {name: 'field_template_name'}
                                ]
                            }),

                            data: arrBreakdownFields
                        }),

                        displayField:   'field_name',
                        valueField:     'field_generated_full_id',
                        typeAhead:      false,
                        mode:           'local',
                        forceSelection: true,
                        triggerAction:  'all',
                        searchContains: true,
                        selectOnFocus:  true,

                        tpl: new Ext.XTemplate(
                            '<tpl for=".">' +
                            '<tpl if="(this.field_template_id != values.field_template_id && !empty(values.field_template_id))">' +
                            '<tpl exec="this.field_template_id = values.field_template_id"></tpl>' +
                            '<h1 style="padding: 2px; background-color: #96BCEB;">{field_template_name}</h1>' +
                            '</tpl>' +
                            '<tpl if="this.field_group_name != values.field_group_name">' +
                            '<tpl exec="this.field_group_name = values.field_group_name"></tpl>' +
                            '<h1 style="padding: 2px 5px;">{field_group_name}</h1>' +
                            '</tpl>' +
                            '<tpl if="field_type != \'special\'">' +
                            '<div class="x-combo-list-item" style="padding-left: 20px;">{field_name}</div>' +
                            '</tpl>' +
                            '</tpl>'
                        ),

                        listeners: {
                            'keyup': function (combo) {
                                combo.fireEvent('change', combo, combo.getValue());
                            },

                            'select': function (combo, record) {
                                combo.fireEvent('change', combo, record.data[combo.valueField]);
                            },

                            'change': function (combo) {
                                thisWindow.toggleDateFilters(combo.name);
                            },

                            'render': function (combo) {
                                thisWindow.toggleDateFilters(combo.name);
                            }
                        }
                    }
                }, {
                    xtype:    'container',
                    name:     'breakdown_field_date_operator_container_' + i,
                    layout:   'column',
                    disabled: true,
                    width:    665,


                    items: [
                        {
                            layout:     'form',
                            bodyStyle:  'background-color:white;',
                            labelWidth: 20,
                            width:      190,

                            items: {
                                xtype:             'combo',
                                fieldLabel:        _('By'),
                                name:              'breakdown_field_operator_' + i,
                                breakdownFieldRow: i,
                                width:             140,
                                listWidth:         165,

                                store: {
                                    xtype:  'arraystore',
                                    fields: ['option_id', 'option_name'],
                                    data:   arrDateByCombo
                                },

                                displayField:  'option_name',
                                valueField:    'option_id',
                                typeAhead:     false,
                                mode:          'local',
                                triggerAction: 'all',
                                editable:      false,

                                listeners: {
                                    'select': thisWindow.toggleDateFilterFields.createDelegate(thisWindow),
                                    'render': function (combo) {
                                        // Set the default value
                                        if (empty(combo.getValue())) {
                                            combo.setValue('years');
                                            combo.fireEvent('select', combo);
                                        }
                                    }
                                }
                            }
                        }, {
                            xtype:  'container',
                            layout: 'column',
                            style:  'padding-left: 10px',
                            name:   'breakdown_field_date_container_' + i,
                            width:  470,

                            items: [
                                {
                                    xtype:      'label',
                                    style:      'font-size: 12px; padding-top: 3px;',
                                    width:      50,
                                    labelWidth: 50,
                                    text:       _('Starting:')
                                }, {
                                    layout:    'form',
                                    width:     70,
                                    style:     'padding-right: 5px',
                                    bodyStyle: 'background-color:white;',

                                    items: {
                                        xtype:             'combo',
                                        hideLabel:         true,
                                        name:              'breakdown_field_date_starting_year_' + i,
                                        emptyText:         _('YYYY'),
                                        hideTrigger:       true,
                                        currentYearType:   '', // Will be used to identify if we need to reload list of options or not
                                        breakdownFieldRow: i,
                                        width:             50,
                                        listWidth:         30,

                                        store: {
                                            xtype:  'arraystore',
                                            fields: ['option_id', 'option_name'],
                                            data:   this.arrStartingAllOptions
                                        },

                                        displayField:    'option_name',
                                        valueField:      'option_id',
                                        typeAhead:       false,
                                        mode:            'local',
                                        triggerAction:   'all',
                                        editable:        true,
                                        forceSelection:  true,
                                        selectOnFocus:   true,
                                        enableKeyEvents: true,
                                        queryMode:       'local',
                                        maskRe:          /[YQMTDyqmtd0-9]/,


                                        listeners: {
                                            'blur': function (combo) {
                                                if (!empty(combo.forceFieldTo)) {
                                                    combo.setValue(combo.forceFieldTo.data.option_id);
                                                    combo.fireEvent('select', combo, combo.forceFieldTo);
                                                }
                                            },

                                            'keyup': function (combo) {
                                                // Load/show only the records that we want to show:
                                                // 'YTD' or 'QTD' or 'MTD' at the top
                                                // and what is filtered (like %18% => 1918, 2018)
                                                var enteredValue = combo.getRawValue();
                                                combo.store.filterBy(
                                                    function (record) {
                                                        var val = record.get('option_name');
                                                        return ['YTD', 'QTD', 'MTD'].has(val) || (new RegExp(enteredValue)).test(val);

                                                    }
                                                );

                                                // Remember what was entered, so on blur we can switch to this value...
                                                var idx            = combo.store.find(combo.valueField, enteredValue);
                                                combo.forceFieldTo = idx !== -1 ? combo.store.getAt(idx) : null;
                                            },

                                            'beforequery': function (queryEvent) {
                                                queryEvent.combo.onLoad();
                                                // prevent doQuery from firing and clearing out my filter.
                                                return false;
                                            },

                                            'select': function (combo, record) {
                                                if (!empty(record)) {
                                                    thisWindow.onStartingYearChange(combo, record.data.option_id)
                                                }
                                            }
                                        }
                                    }
                                }, {
                                    layout:    'form',
                                    bodyStyle: 'background-color:white;',
                                    width:     60,

                                    items: {
                                        xtype:     'combo',
                                        hideLabel: true,
                                        name:      'breakdown_field_date_starting_quarter_' + i,
                                        width:     50,

                                        store: {
                                            xtype:  'arraystore',
                                            fields: ['option_id', 'option_name'],
                                            data:   arrQuartersNames
                                        },

                                        value:         '1',
                                        displayField:  'option_name',
                                        valueField:    'option_id',
                                        typeAhead:     false,
                                        mode:          'local',
                                        triggerAction: 'all',
                                        editable:      false
                                    }
                                }, {
                                    layout:    'form',
                                    width:     120,
                                    style:     'padding-right: 5px',
                                    bodyStyle: 'background-color:white;',

                                    items: {
                                        xtype:     'combo',
                                        hideLabel: true,
                                        name:      'breakdown_field_date_starting_month_' + i,
                                        width:     115,

                                        store: {
                                            xtype:  'arraystore',
                                            fields: ['option_id', 'option_name'],
                                            data:   arrMonthNames
                                        },

                                        value:         '1',
                                        displayField:  'option_name',
                                        valueField:    'option_id',
                                        typeAhead:     false,
                                        mode:          'local',
                                        triggerAction: 'all',
                                        editable:      false
                                    }
                                }, {
                                    layout:     'form',
                                    labelWidth: 70,
                                    width:      140,
                                    style:      'padding-left: 5px',
                                    bodyStyle:  'background-color:white;',

                                    items: {
                                        xtype:      'combo',
                                        fieldLabel: _('Go back for'),
                                        name:       'breakdown_field_go_back_number_' + i,
                                        width:      30,

                                        store: {
                                            xtype:  'arraystore',
                                            fields: ['option_id', 'option_name'],
                                            data:   arrGoBackNumbers
                                        },

                                        value:           '0',
                                        displayField:    'option_name',
                                        valueField:      'option_id',
                                        typeAhead:       true,
                                        mode:            'local',
                                        triggerAction:   'all',
                                        editable:        true,
                                        forceSelection:  true,
                                        selectOnFocus:   true,
                                        enableKeyEvents: true,

                                        listeners: {
                                            'blur': function (combo) {
                                                if (!empty(combo.forceFieldTo)) {
                                                    combo.setValue(combo.forceFieldTo.data.option_id);
                                                    combo.fireEvent('select', combo, combo.forceFieldTo);
                                                }
                                            },

                                            'keyup': function (combo) {
                                                // Remember what was entered, so on blur we can switch to this value...
                                                var idx            = combo.store.find(combo.valueField, combo.getRawValue());
                                                combo.forceFieldTo = idx !== -1 ? combo.store.getAt(idx) : null;
                                            }
                                        }
                                    }
                                }, {
                                    layout:    'form',
                                    width:     80,
                                    style:     'padding-left: 10px',
                                    bodyStyle: 'background-color:white;',
                                    cls:       'disabled-combo-field',

                                    items: {
                                        xtype:       'combo',
                                        hideLabel:   true,
                                        name:        'breakdown_field_go_back_period_' + i,
                                        allowBlank:  true,
                                        disabled:    true,
                                        hideTrigger: true,
                                        width:       70,

                                        store: {
                                            xtype:  'arraystore',
                                            fields: ['option_id', 'option_name'],
                                            data:   arrGoBackCombo
                                        },

                                        value:         'years',
                                        displayField:  'option_name',
                                        valueField:    'option_id',
                                        typeAhead:     false,
                                        mode:          'local',
                                        triggerAction: 'all',
                                        editable:      false
                                    }
                                }
                            ]
                        }
                    ]
                }
            ]
        })
    }

    this.analyticsFormPanel = new Ext.form.FormPanel({
        bodyStyle: 'padding: 5px;',

        items: arrFormItems
    });

    DefaultAnalyticsDialog.superclass.constructor.call(this, {
        title:      empty(this.analytics_record['analytics_id']) ? '<i class="las la-plus"></i>'+_('Add Analytics') : '<i class="las la-edit"></i>'+_('Edit Analytics'),
        modal:      true,
        width:      1030,
        autoHeight: true,
        resizable:  false,
        items:      this.analyticsFormPanel,
        buttons:    [
            {
                text:    'Cancel',
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text:    '<i class="las la-save"></i>' + _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    thisWindow.on('show', thisWindow.onDialogRender.createDelegate(thisWindow));
};

Ext.extend(DefaultAnalyticsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    onDialogRender: function () {
        var thisDialog = this;

        if (!empty(thisDialog.analytics_record['analytics_params'])) {
            // Set main fields
            var arrMainFieldIds = ['analytics_id', 'analytics_name', 'show_chart', 'chart_type'];
            var oField;

            for (var i = 0; i < arrMainFieldIds.length; i++) {
                oField = thisDialog.analyticsFormPanel.find('name', arrMainFieldIds[i])[0];
                if (!empty(thisDialog.analytics_record['analytics_params'][arrMainFieldIds[i]])) {
                    oField.setValue(thisDialog.analytics_record['analytics_params'][arrMainFieldIds[i]]);
                } else if (!empty(thisDialog.analytics_record[arrMainFieldIds[i]])) {
                    oField.setValue(thisDialog.analytics_record[arrMainFieldIds[i]]);
                }
            }

            // Set filter fields
            var arrParams = thisDialog.analytics_record['analytics_params'];
            for (i = 1; i <= 2; i++) {
                var arrDateFilterFields = [
                    'breakdown_field_' + i,
                    'breakdown_field_operator_' + i,
                    'breakdown_field_date_starting_quarter_' + i,
                    'breakdown_field_date_starting_month_' + i,
                    'breakdown_field_date_starting_year_' + i,
                    'breakdown_field_go_back_number_' + i,
                    'breakdown_field_go_back_period_' + i
                ];

                for (var j = 0; j < arrDateFilterFields.length; j++) {
                    if (typeof arrParams[arrDateFilterFields[j]] !== 'undefined') {
                        oField = thisDialog.analyticsFormPanel.find('name', arrDateFilterFields[j])[0];
                        if (oField.getXType() === 'combo') {
                            if (oField.getStore().find(oField.valueField, arrParams[arrDateFilterFields[j]]) !== -1) {
                                oField.setValue(arrParams[arrDateFilterFields[j]]);
                            }
                        } else {
                            oField.setValue(arrParams[arrDateFilterFields[j]]);
                        }
                    }
                }

                thisDialog.toggleDateFilters('breakdown_field_' + i);

                var combo = thisDialog.analyticsFormPanel.find('name', 'breakdown_field_operator_' + i)[0];
                if (combo.isVisible()) {
                    combo.fireEvent('select', combo);
                }
            }
        }
    },

    isDateFieldSelected: function (comboboxName) {
        var oCombo = this.analyticsFormPanel.find('name', comboboxName)[0];
        var idx    = oCombo.store.find(oCombo.valueField, oCombo.getValue());
        var rec    = oCombo.store.getAt(idx);

        return [
            'date',
            'date_repeatable'
        ].has(empty(rec) ? '' : rec.data.field_type);
    },

    onStartingYearChange: function (combo, value) {
        var thisPanel = this;

        var oOperatorField = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_operator_' + combo.breakdownFieldRow)[0];
        var oQuarterField  = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_starting_quarter_' + combo.breakdownFieldRow)[0];
        var oMonthField    = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_starting_month_' + combo.breakdownFieldRow)[0];
        var oGoBackField   = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_go_back_number_' + combo.breakdownFieldRow)[0];

        oGoBackField.setWidth(60);
        oOperatorField.setWidth(160);

        if (['YTD', 'QTD', 'MTD'].has(value)) {
            oMonthField.ownerCt.setVisible(false);
            oQuarterField.ownerCt.setVisible(false);
        } else {
            switch (oOperatorField.getValue()) {
                case 'years':
                    oMonthField.ownerCt.setVisible(false);
                    oQuarterField.ownerCt.setVisible(false);
                    break;

                case 'quarters':
                case 'same_quarter_last_years':
                    oMonthField.ownerCt.setVisible(false);

                    oQuarterField.ownerCt.setVisible(true);
                    oQuarterField.setWidth(45);
                    oQuarterField.ownerCt.setWidth(50);
                    break;

                case 'months':
                case 'same_month_last_years':
                    oMonthField.ownerCt.setVisible(true);
                    oMonthField.setWidth(114);
                    oMonthField.ownerCt.setWidth(120);

                    oQuarterField.ownerCt.setVisible(false);
                    break;

                default:
                    break;
            }
        }
    },

    resetStartingYearField: function (oYearField, arrNewOptions) {
        var oldValue = oYearField.getValue();
        oYearField.getStore().loadData(arrNewOptions);

        var idx = oYearField.store.find(oYearField.valueField, oldValue);
        oYearField.setValue(idx === -1 ? '' : oldValue);
    },


    toggleDateFilters: function (comboboxName) {
        var oCombo       = this.analyticsFormPanel.find('name', comboboxName)[0];
        var oContainer   = this.analyticsFormPanel.find('name', oCombo.linkedFieldName)[0];
        var booShowField = this.isDateFieldSelected(comboboxName);

        oContainer.setVisible(booShowField);
        oContainer.setDisabled(!booShowField);
    },

    toggleDateFilterFields: function (combo) {
        var thisPanel     = this;
        var mainContainer = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_container_' + combo.breakdownFieldRow)[0];
        var oYearField    = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_starting_year_' + combo.breakdownFieldRow)[0];
        var goBackCombo   = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_go_back_period_' + combo.breakdownFieldRow)[0];

        switch (combo.getValue()) {
            case 'years':
                if (oYearField.currentYearType !== combo.getValue()) {
                    oYearField.currentYearType = combo.getValue();
                    thisPanel.resetStartingYearField(oYearField, thisPanel.arrStartingYearOptions);
                }
                thisPanel.onStartingYearChange(oYearField, oYearField.getValue());

                mainContainer.setVisible(true);

                goBackCombo.setValue('years');
                break;

            case 'quarters':
            case 'same_quarter_last_years':
                if (!['quarters', 'same_quarter_last_years'].has(oYearField.currentYearType)) {
                    oYearField.currentYearType = combo.getValue();
                    thisPanel.resetStartingYearField(oYearField, thisPanel.arrStartingQuarterOptions);
                }
                thisPanel.onStartingYearChange(oYearField, oYearField.getValue());

                mainContainer.setVisible(true);

                goBackCombo.setValue(combo.getValue() === 'quarters' ? 'quarters' : 'years');
                break;

            case 'months':
            case 'same_month_last_years':
                if (!['months', 'same_month_last_years'].has(oYearField.currentYearType)) {
                    oYearField.currentYearType = combo.getValue();
                    thisPanel.resetStartingYearField(oYearField, thisPanel.arrStartingMonthOptions);
                }
                thisPanel.onStartingYearChange(oYearField, oYearField.getValue());

                mainContainer.setVisible(true);

                goBackCombo.setValue(combo.getValue() === 'months' ? 'months' : 'years');
                break;

            case 'month_days':
            case 'week_days':
                mainContainer.setVisible(false);
                break;

            default:
                break;
        }
    },

    getFilterFields: function () {
        var thisPanel = this;

        var oParams = {
            show_chart: thisPanel.analyticsFormPanel.find('name', 'show_chart')[0].getValue(),
            chart_type: thisPanel.analyticsFormPanel.find('name', 'chart_type')[0].getValue()
        };

        for (var i = 1; i <= 2; i++) {
            var fieldName       = 'breakdown_field_' + i;
            var oFieldBreakdown = thisPanel.analyticsFormPanel.find('name', fieldName)[0];
            var idx             = oFieldBreakdown.store.find(oFieldBreakdown.valueField, oFieldBreakdown.getValue());
            var rec             = oFieldBreakdown.store.getAt(idx);
            oParams[fieldName]  = rec && rec.data ? Ext.encode(rec.data) : null;

            var booIsDateField      = thisPanel.isDateFieldSelected(fieldName);
            var arrDateFilterFields = [
                'breakdown_field_operator_' + i,
                'breakdown_field_date_starting_quarter_' + i,
                'breakdown_field_date_starting_month_' + i,
                'breakdown_field_date_starting_year_' + i,
                'breakdown_field_go_back_number_' + i,
                'breakdown_field_go_back_period_' + i
            ];

            for (var j = 0; j < arrDateFilterFields.length; j++) {
                oParams[arrDateFilterFields[j]] = booIsDateField ? Ext.encode(thisPanel.analyticsFormPanel.find('name', arrDateFilterFields[j])[0].getValue()) : null;
            }
        }

        return oParams;
    },

    saveChanges: function () {
        var thisPanel = this;

        if (!thisPanel.analyticsFormPanel.getForm().isValid()) {
            return false;
        }

        var oParams = {
            analytics_params: Ext.encode(thisPanel.getFilterFields())
        };


        var arrAllMainFields = ['analytics_id', 'analytics_name', 'analytics_type'];
        for (var i = 0; i < arrAllMainFields.length; i++) {
            oParams[arrAllMainFields[i]] = Ext.encode(thisPanel.analyticsFormPanel.find('name', arrAllMainFields[i])[0].getValue());
        }

        thisPanel.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url:    baseUrl + '/manage-default-analytics/update',
            params: oParams,

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    thisPanel.owner.store.load();
                }

                var msg = String.format(
                    '<span style="color: {0}">{1}</span>',
                    resultData.success ? 'black' : 'red',
                    resultData.success ? _('Done!') : resultData.message
                );

                thisPanel.getEl().mask(msg);
                setTimeout(function () {
                    if (resultData.success) {
                        thisPanel.close();
                    } else {
                        thisPanel.getEl().unmask();
                    }
                }, resultData.success ? 750 : 2000);
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Record cannot be saved. Please try again later.'));
                thisPanel.getEl().unmask();
            }
        });
    }
});