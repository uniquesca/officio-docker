ApplicantsAnalyticsPanel = function (config, owner) {
    var thisPanel = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.analyticsChart         = null;
    this.analyticsChartType     = 'bar'; // Default
    this.arrAnalyticsMembersIds = null;
    this.arrAnalyticsLoadedData = null;
    this.analyticsChartId       = Ext.id();
    this.analyticsChartLegendId = Ext.id();

    var booCanSaveAnalytics   = false;
    var booCanExportAnalytics = false;
    var booCanPrintAnalytics  = false;
    var booViewAdvancedSearch = false;
    if (thisPanel.booStandaloneAnalytics) {
        var booExistingRecord = !empty(thisPanel.oAnalyticsSettings) && !empty(thisPanel.oAnalyticsSettings['analytics_id']);

        booCanSaveAnalytics   = hasAccessToRules(config.panelType, 'analytics', booExistingRecord ? 'edit' : 'add');
        booCanExportAnalytics = hasAccessToRules(config.panelType, 'analytics', 'export');
        booCanPrintAnalytics  = hasAccessToRules(config.panelType, 'analytics', 'print');
        booViewAdvancedSearch = hasAccessToRules(config.panelType, 'search', 'view_advanced_search');
    }

    // How we need to show the chart (and legends) by default
    // Can be saved to the advanced search and loaded/applied later
    this.showChartByDefault = true;

    this.alertContainer = new Ext.Panel({
        html:  _('No records were found...'),
        style: 'font-size: 12px; padding: 10px; font-style: italic;'
    });

    // Default sizes for each block
    // Automatically increase the grid's size if chart + legend are hidden
    var booFieldAnalyticsNameVisible = this.booStandaloneAnalytics && !empty(thisPanel.oAnalyticsSettings) && !empty(thisPanel.oAnalyticsSettings['analytics_id']);

    this.analyticsFormContainerHeight           = booFieldAnalyticsNameVisible ? 145 : 110;
    this.analyticsChartAndLegendContainerHeight = 150;
    this.analyticsGridHeight                    = 200;
    this.analyticsButtonsContainerHeight        = 40;

    this.analyticsChartAndLegendContainer = new Ext.Container({
        height: this.analyticsChartAndLegendContainerHeight,

        items: [
            {
                xtype:  'container',
                height: 50,
                items:  {
                    id:     this.analyticsChartLegendId,
                    xtype:  'box',
                    autoEl: {
                        tag:     'div',
                        'class': 'analytics-chart-legend'
                    },
                    html:   '&nbsp;'
                }
            },
            {
                xtype:  'container',
                height: 100,

                items: [
                    {
                        id:    this.analyticsChartId,
                        xtype: 'box',

                        autoEl: {
                            tag: 'canvas'
                        }
                    }
                ]
            }
        ]
    });

    this.analyticsGridContainer = new Ext.Container({
        xtype:  'container',
        height: this.analyticsGridHeight,
        items:  []
    });

    this.analyticsButtonsContainer = new Ext.Container({
        layout: 'column',
        height: this.analyticsButtonsContainerHeight,
        hidden: true,

        items: [
            {
                xtype:   'button',
                text:    '<i class="las la-print"></i>' + _('Print'),
                width:   100,
                hidden:  !booCanPrintAnalytics,
                style:   'padding: 10px 5px 10px 10px',
                handler: thisPanel.printData.createDelegate(this)
            }, {
                xtype:   'button',
                text:    '<i class="las la-file-export"></i>' + _('Export'),
                width:   100,
                hidden:  !booCanExportAnalytics,
                style:   'padding: 10px 10px 10px 5px',
                handler: thisPanel.exportData.createDelegate(this)
            }
        ]
    });

    this.chartTypesMenu = [
        new Ext.menu.CheckItem({
            text:         _('Bar Chart'),
            cls:          'analytics_chart_bar',
            group:        'chart_type',
            value:        'bar',
            checked:      thisPanel.analyticsChartType === 'bar',
            checkHandler: thisPanel.changeChartType.createDelegate(thisPanel)
        }), new Ext.menu.CheckItem({
            text:         _('Doughnut Chart (full circle)'),
            cls:          'analytics_chart_doughnut_full',
            group:        'chart_type',
            value:        'doughnut_full',
            checked:      thisPanel.analyticsChartType === 'doughnut_full',
            checkHandler: thisPanel.changeChartType.createDelegate(thisPanel)
        }), new Ext.menu.CheckItem({
            text:         _('Doughnut Chart (semi circle)'),
            cls:          'analytics_chart_doughnut_half',
            group:        'chart_type',
            value:        'doughnut_half',
            checked:      thisPanel.analyticsChartType === 'doughnut_half',
            checkHandler: thisPanel.changeChartType.createDelegate(thisPanel)
        }), new Ext.menu.CheckItem({
            text:         _('Pie Chart (full circle)'),
            cls:          'analytics_chart_pie_full',
            group:        'chart_type',
            value:        'pie_full',
            checked:      thisPanel.analyticsChartType === 'pie_full',
            checkHandler: thisPanel.changeChartType.createDelegate(thisPanel)
        }), new Ext.menu.CheckItem({
            text:         _('Pie Chart (semi circle)'),
            cls:          'analytics_chart_pie_half',
            group:        'chart_type',
            value:        'pie_half',
            checked:      thisPanel.analyticsChartType === 'pie_half',
            checkHandler: thisPanel.changeChartType.createDelegate(thisPanel)
        })
    ];

    this.chartTypeButton = new Ext.Button({
        text:    '<i class="las la-chart-bar"></i>' + _('Chart Type'),
        style:   'margin-right: 10px',
        menu:    this.chartTypesMenu
    });


    this.toggleChartContainer = new Ext.Container({
        layout: 'column',

        items: [
            {
                xtype:    'box',
                'autoEl': {
                    'tag':   'a',
                    'href':  '#',
                    'class': 'bluelink',
                    style:   'padding: 5px 20px',
                    'html':  _('Show Chart or Hide Chart')
                }, // Thanks to IE - we need to use quotes...

                listeners: {
                    scope:  this,
                    render: function (c) {
                        c.getEl().on('click', function () {
                            thisPanel.toggleChartAndLegend(!thisPanel.analyticsChartAndLegendContainer.isVisible());
                        }, this, {stopEvent: true});
                    }
                }
            },
            this.chartTypeButton
        ]
    });

    this.applyButtonContainer = new Ext.Container({
        layout: 'column',

        items: [
            {
                xtype:   'button',
                text:    '<i class="lar la-save"></i>' + _('Save'),
                style:   'margin-right: 10px',
                hidden:  !booCanSaveAnalytics,
                width:   110,

                handler: thisPanel.saveAnalytics.createDelegate(this)
            }, {
                xtype:   'button',
                text:    '<i class="las la-binoculars"></i>' + _('Get Analytics'),
                cls:     'orange-btn',
                width:   110,

                handler: thisPanel.loadData.createDelegate(this)
            }
        ]
    });

    var arrBreakdownFields = owner.getGroupedFields('all', ['special']);

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

    this.fieldAnalyticsId = new Ext.form.Hidden({
        value: !empty(thisPanel.oAnalyticsSettings) && !empty(thisPanel.oAnalyticsSettings['analytics_id']) ? thisPanel.oAnalyticsSettings['analytics_id'] : 0
    });

    this.fieldAnalyticsName = new Ext.form.TextField({
        fieldLabel: _('Analytics Name'),
        style:      'margin-bottom: 10px',
        hidden:     !booFieldAnalyticsNameVisible,
        disabled:   !booFieldAnalyticsNameVisible,
        allowBlank: false,
        value:      !empty(thisPanel.oAnalyticsSettings) && !empty(thisPanel.oAnalyticsSettings['analytics_name']) ? thisPanel.oAnalyticsSettings['analytics_name'] : '',
        width:      225
    });

    var arrFormItems = [
        {
            layout:     'form',
            labelWidth: 100,
            bodyStyle:  'background-color: white;',

            items: [
                this.fieldAnalyticsId,
                this.fieldAnalyticsName
            ]
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
                    labelWidth: this.booStandaloneAnalytics ? 100 : 85,
                    width:      335,

                    items: {
                        xtype:           'combo',
                        fieldLabel:      this.booStandaloneAnalytics ? (i === 1 ? _('Group by (Y axis)') : _('Group by (X axis)')) : (i === 1 ? _('Breakdown for') : _('And group by')),
                        emptyText:       i === 1 ? _('Please select the field...') : _('Optional...'),
                        allowBlank:      i !== 1,
                        name:            'breakdown_field_' + i,
                        linkedFieldName: 'breakdown_field_date_operator_container_' + i,
                        width:           225,

                        store: new Ext.data.Store({
                            reader: new Ext.data.JsonReader({
                                fields: [
                                    {
                                        name:    'field_generated_full_id',
                                        convert: thisPanel.generateFieldFullId.createDelegate(thisPanel)
                                    },
                                    {name: 'field_id'},
                                    {name: 'field_unique_id'},
                                    {name: 'field_name'},
                                    {name: 'field_type'},
                                    {name: 'field_group_id'},
                                    {name: 'field_group_name'},
                                    {name: 'field_client_type'},
                                    {name: 'field_template_id'},
                                    {name: 'field_template_name'}
                                ]
                            }),
                            data:   arrBreakdownFields
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
                                thisPanel.toggleDateFilters(combo.name);
                            },

                            'render': function (combo) {
                                thisPanel.toggleDateFilters(combo.name);
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
                                    'select': thisPanel.toggleDateFilterFields.createDelegate(thisPanel),
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
                                                    thisPanel.onStartingYearChange(combo, record.data.option_id)
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

    arrFormItems.push({
        xtype:  'container',
        layout: 'column',
        style:  'padding: 10px 0',
        items:  [
            this.applyButtonContainer,
            this.toggleChartContainer,
            {
                xtype:   'button',
                text:    '<i class="las la-filter"></i>' + _('Advanced...'),
                style:   'margin-left: 10px',
                hidden:  !booViewAdvancedSearch,
                width:   110,

                handler: thisPanel.openAdvancedTab.createDelegate(this)
            }
        ]
    });

    this.analyticsFormPanel = new Ext.FormPanel({
        height:    this.analyticsFormContainerHeight,
        style:     'padding: 10px; border-bottom: 1px dotted #ccc;',
        bodyStyle: 'background-color: white',

        items: arrFormItems
    });

    ApplicantsAnalyticsPanel.superclass.constructor.call(this, {
        cls:        'extjs-panel-with-border',
        bodyStyle:  'background-color: white;',
        style:      'background-color: white;',
        autoHeight: true,

        items: [
            {
                xtype: 'container',
                items: [
                    this.alertContainer,
                    {
                        xtype: 'container',
                        items: [
                            this.analyticsFormPanel,
                            this.analyticsChartAndLegendContainer,
                            this.analyticsGridContainer,
                            this.analyticsButtonsContainer
                        ]
                    }
                ]
            }
        ]
    });

    thisPanel.on('activate', thisPanel.onPanelActivate.createDelegate(thisPanel));
    thisPanel.on('render', thisPanel.onPanelRender.createDelegate(thisPanel));
};

Ext.extend(ApplicantsAnalyticsPanel, Ext.Panel, {
    generateFieldFullId: function (v, record) {
        return record.field_client_type + '_' + record.field_unique_id;
    },

    onPanelRender: function () {
        var thisPanel = this;

        if (thisPanel.booStandaloneAnalytics) {
            if (!empty(thisPanel.oAnalyticsSettings) && !empty(thisPanel.oAnalyticsSettings['analytics_id'])) {
                thisPanel.applySettings(thisPanel.oAnalyticsSettings['analytics_params']);
                thisPanel.loadData();
            } else {
                this.toggleChartContainer.setVisible(false);
            }
        }
    },

    onPanelActivate: function () {
        var thisPanel = this;

        // Clear invalid fields, if any
        thisPanel.analyticsFormPanel.getForm().clearInvalid();

        var arrMemberIds = [];
        try {
            if (!thisPanel.booStandaloneAnalytics) {
                arrMemberIds = thisPanel.owner.advancedSearchGrid.getStore().reader.jsonData.all_ids;
            }
        } catch (e) {
        }

        // Toggle grid / chart / buttons if needed
        if (arrMemberIds.length || thisPanel.booForceShowAnalytics || thisPanel.booStandaloneAnalytics) {
            thisPanel.alertContainer.setVisible(false);
            thisPanel.analyticsFormPanel.setVisible(true);

            for (var i = 1; i <= 2; i++) {
                var combo = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_operator_' + i)[0];
                if (combo.isVisible()) {
                    combo.fireEvent('select', combo);
                }
            }

            if (thisPanel.arrAnalyticsMembersIds !== arrMemberIds && !thisPanel.booStandaloneAnalytics) {
                thisPanel.toggleChartAndGridContainers(false);
            }

            if (!arrMemberIds.length && !thisPanel.booStandaloneAnalytics) {
                thisPanel.applyButtonContainer.setVisible(false);
            }
        } else {
            thisPanel.alertContainer.setVisible(true);
            thisPanel.analyticsFormPanel.setVisible(false);
            thisPanel.toggleChartAndGridContainers(false);
        }

        this.owner.fixParentPanelHeight();
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

    toggleDateFilters: function (comboboxName) {
        var oCombo       = this.analyticsFormPanel.find('name', comboboxName)[0];
        var oContainer   = this.analyticsFormPanel.find('name', oCombo.linkedFieldName)[0];
        var booShowField = this.isDateFieldSelected(comboboxName);

        oContainer.setVisible(booShowField);
        oContainer.setDisabled(!booShowField);
    },

    onStartingYearChange: function (combo, value) {
        var thisPanel = this;

        var oOperatorField = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_operator_' + combo.breakdownFieldRow)[0];
        var oQuarterField  = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_starting_quarter_' + combo.breakdownFieldRow)[0];
        var oMonthField    = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_date_starting_month_' + combo.breakdownFieldRow)[0];
        var oGoBackField   = thisPanel.analyticsFormPanel.find('name', 'breakdown_field_go_back_number_' + combo.breakdownFieldRow)[0];

        if (thisPanel.booStandaloneAnalytics) {
            oGoBackField.setWidth(60);
        }

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

    toggleChartAndGridContainers: function (booShow) {
        this.toggleChartContainer.setVisible(booShow);
        this.analyticsGridContainer.setVisible(booShow);
        this.analyticsButtonsContainer.setVisible(booShow);

        this.toggleChartAndLegend(booShow && this.showChartByDefault);
    },

    changeChartType: function (checkbox, booChecked) {
        if (booChecked) {
            this.analyticsChartType = checkbox.value;
            this.initChart();
        }
    },

    toggleChartAndLegend: function (booShow) {
        var thisPanel = this;

        thisPanel.analyticsChartAndLegendContainer.setVisible(booShow);
        thisPanel.chartTypeButton.setVisible(booShow);
        thisPanel.analyticsGridContainer.setHeight(booShow ? thisPanel.analyticsGridHeight : thisPanel.analyticsGridHeight + thisPanel.analyticsChartAndLegendContainerHeight);
        thisPanel.analyticsGridContainer.items.each(function (item) {
            item.setHeight(thisPanel.analyticsGridContainer.getHeight());
        });
    },

    initChart: function () {
        var thisPanel = this;

        if (!empty(this.analyticsChart)) {
            this.analyticsChart.destroy();
        } else {
            // Listen for click on the legend's li and toggle the item from the chart
            $(document).on('click', "#" + thisPanel.analyticsChartLegendId + " > ul > li", function () {
                if (thisPanel.analyticsChartType === 'bar') {
                    var index = $(this).index();
                    $(this).toggleClass("strike");
                    var curr    = thisPanel.analyticsChart.data.datasets[index];
                    curr.hidden = !curr.hidden;
                    thisPanel.analyticsChart.update();
                }
            });
        }

        var oConfig;
        switch (thisPanel.analyticsChartType) {
            case 'doughnut_full':
            case 'doughnut_half':
            case 'pie_full':
            case 'pie_half':
                oConfig = {
                    type: 'doughnut',

                    data: {
                        labels:   [],
                        datasets: []
                    },

                    options: {
                        responsive:          true,
                        maintainAspectRatio: false,
                        cutoutPercentage:    ['doughnut_full', 'doughnut_half'].has(thisPanel.analyticsChartType) ? 30 : 0,
                        circumference:       ['doughnut_full', 'pie_full'].has(thisPanel.analyticsChartType) ? 2 * Math.PI : Math.PI,
                        rotation:            ['doughnut_full', 'pie_full'].has(thisPanel.analyticsChartType) ? -Math.PI / 2 : -Math.PI,

                        animation: {
                            duration: 1500
                        },

                        legend: {
                            display: true
                        }
                    }
                };
                break;

            case 'bar':
            default:
                oConfig = {
                    type: 'bar',

                    data: {
                        labels:   [],
                        datasets: []
                    },

                    options: {
                        responsive:          true,
                        maintainAspectRatio: false,

                        animation: {
                            duration: 1500
                        },

                        scales: {
                            y: [
                                {
                                    ticks: {
                                        beginAtZero: true
                                    }
                                }
                            ]
                        }
                    }
                };
                break;
        }

        var ctx = document.getElementById(this.analyticsChartId).getContext('2d');

        this.analyticsChart = new Chart(ctx, oConfig);


        // Apply the data
        thisPanel.analyticsChart.clear();
        thisPanel.analyticsChart.data.labels   = thisPanel.arrAnalyticsLoadedData.labels;
        thisPanel.analyticsChart.data.datasets = [];

        var oSettings = thisPanel.getFilterFields();
        if (thisPanel.arrAnalyticsLoadedData.datasets.length) {
            var dataset;
            var dsColor;
            var backgroundColor;

            if (thisPanel.analyticsChartType === 'bar' || !empty(oSettings['breakdown_field_2'])) {
                for (var i = 0; i < thisPanel.arrAnalyticsLoadedData.datasets.length; i++) {
                    dataset = thisPanel.arrAnalyticsLoadedData.datasets[i];

                    if (thisPanel.analyticsChartType !== 'bar') {
                        dsColor         = [];
                        backgroundColor = [];
                        for (var d = 0; d < dataset.data.length; d++) {
                            var thisColor = getChartDefinedRandomColor(d);
                            dsColor.push(thisColor);
                            backgroundColor.push(Chart.helpers.color(thisColor).alpha(0.5).rgbString())
                        }
                    } else {
                        dsColor         = getChartDefinedRandomColor(i);
                        backgroundColor = Chart.helpers.color(dsColor).alpha(0.5).rgbString();
                    }

                    thisPanel.analyticsChart.data.datasets.push({
                        label:           dataset.label,
                        backgroundColor: backgroundColor,
                        borderColor:     dsColor,
                        borderWidth:     1,
                        data:            dataset.data
                    });
                }
            } else {
                // For the doughnut chart we need to pass data in slightly different format
                var arrLabels = [];
                for (var l = 0; l < thisPanel.arrAnalyticsLoadedData.labels.length; l++) {
                    var arrData     = [];
                    backgroundColor = [];

                    for (var j = 0; j < thisPanel.arrAnalyticsLoadedData.datasets.length; j++) {
                        dataset = thisPanel.arrAnalyticsLoadedData.datasets[j];
                        arrLabels.push(dataset.label);
                        arrData.push(dataset.data[l]);

                        backgroundColor.push(Chart.helpers.color(getChartDefinedRandomColor(j)).alpha(0.5).rgbString())
                    }

                    thisPanel.analyticsChart.data.datasets.push({
                        label:           thisPanel.arrAnalyticsLoadedData.labels[l],
                        backgroundColor: backgroundColor,
                        data:            arrData
                    });
                }

                thisPanel.analyticsChart.data.labels = arrLabels;
            }

            // Generate the legend from the received data
            // $('#' + thisPanel.analyticsChartLegendId).html(this.analyticsChart.generateLegend());
            thisPanel.analyticsChart.update();
        }
    },

    printData: function () {
        print($('#' + this.analyticsGridContainer.getId() + ' .x-grid3').html(), _('Analytics'));
    },

    exportData: function () {
        var grid = this.analyticsGridContainer.items.get(0);

        // Get visible columns
        var cm      = [];
        var cmModel = grid.getColumnModel().config;
        for (var i = 0; i < cmModel.length; i++) {
            if (!cmModel[i].hidden) {
                cm.push({
                    id:    cmModel[i].dataIndex,
                    name:  cmModel[i].header,
                    width: cmModel[i].width
                });
            }
        }

        // Export loaded data
        var arrRecords = [];
        grid.getStore().each(function (rec) {
            arrRecords.push(rec.data);
        });

        submit_hidden_form(topBaseUrl + '/applicants/analytics/export', {
            arrColumns: Ext.encode(cm),
            arrRecords: Ext.encode(arrRecords)
        });
    },

    getFilterFields: function () {
        var thisPanel = this;

        var oParams = {
            show_chart: thisPanel.analyticsChartAndLegendContainer.isVisible(),
            chart_type: thisPanel.analyticsChartType
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

    applySettings: function (arrParams) {
        var thisPanel = this;

        for (var i = 1; i <= 2; i++) {
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
                    var oField = thisPanel.analyticsFormPanel.find('name', arrDateFilterFields[j])[0];
                    if (oField.getXType() === 'combo') {
                        if (oField.getStore().find(oField.valueField, arrParams[arrDateFilterFields[j]]) !== -1) {
                            oField.setValue(arrParams[arrDateFilterFields[j]]);
                        }
                    } else {
                        oField.setValue(arrParams[arrDateFilterFields[j]]);
                    }
                }
            }

            thisPanel.toggleDateFilters('breakdown_field_' + i);
        }

        if (typeof arrParams.show_chart !== 'undefined') {
            this.showChartByDefault = arrParams.show_chart;
        }

        if (typeof arrParams.chart_type !== 'undefined') {
            this.analyticsChartType = arrParams.chart_type;
        }
    },

    loadData: function () {
        var thisPanel = this;

        if (thisPanel.analyticsFormPanel.getForm().isValid()) {
            thisPanel.getEl().mask(_('Loading...'));

            this.arrAnalyticsMembersIds = [];

            // Prepare/collect grouping data
            var oParams = {};
            try {
                try {
                    this.arrAnalyticsMembersIds = thisPanel.owner.advancedSearchGrid.getStore().reader.jsonData.all_ids;
                } catch (e) {
                }

                oParams = {
                    panel_type: Ext.encode(thisPanel.panelType),
                    ids:        Ext.encode(thisPanel.arrAnalyticsMembersIds),
                    standalone: Ext.encode(thisPanel.booStandaloneAnalytics)
                };

                Ext.apply(oParams, this.getFilterFields());
            } catch (e) {
            }

            Ext.Ajax.request({
                url: topBaseUrl + '/applicants/analytics/get-analytics-data',

                params: oParams,

                success: function (f) {
                    thisPanel.getEl().unmask();

                    var resultData = Ext.decode(f.responseText);
                    if (resultData.success) {
                        thisPanel.analyticsGridContainer.setVisible(true);
                        thisPanel.analyticsButtonsContainer.setVisible(resultData.chartData.datasets.length);
                        thisPanel.toggleChartContainer.setVisible(resultData.chartData.datasets.length);
                        thisPanel.toggleChartAndLegend(thisPanel.showChartByDefault && resultData.chartData.datasets.length);

                        // Check "chart type" checkbox
                        for (var i = 0; i < thisPanel.chartTypesMenu.length; i++) {
                            var checkbox = thisPanel.chartTypesMenu[i];
                            checkbox.setChecked(checkbox.value === thisPanel.analyticsChartType, true);
                        }

                        thisPanel.arrAnalyticsLoadedData = resultData.chartData;
                        thisPanel.initChart();

                        // Generate the grid
                        thisPanel.initGrid(resultData.chartData);
                    } else {
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function (form, action) {
                    if (!empty(action.result.message)) {
                        Ext.simpleConfirmation.error(action.result.message);
                    } else {
                        Ext.simpleConfirmation.error(_('Cannot load info...'));
                    }

                    thisPanel.getEl().unmask();
                }
            });
        }
    },

    initGrid: function (oData) {
        // Generate grid each time we load data to be showed
        var container              = this.analyticsGridContainer;
        var breakdownFieldLabel    = this.analyticsFormPanel.find('name', 'breakdown_field_1')[0].getRawValue();
        var breakdownFieldOperator = this.analyticsFormPanel.find('name', 'breakdown_field_operator_1')[0].getValue();
        var booIsFirstDateField    = this.isDateFieldSelected('breakdown_field_1');
        var booIsSecondDateField   = this.isDateFieldSelected('breakdown_field_2');
        var arrColumns             = [];
        var arrFields              = [];
        var mainColumnId           = Ext.id();

        // Identify if "changes" column must be visible + how data for it must be calculated
        var booShowChangesColumn = false;
        var booCalculateByYAxis  = true;
        var booUseMonthsSorting  = false;
        var booUseYearsSorting   = false;
        if (booIsFirstDateField && booIsSecondDateField) {
            // Don't show the column if both date fields were selected
            booShowChangesColumn = false;
        } else if (booIsFirstDateField || booIsSecondDateField) {
            // Show the column if one date field was selected

            // How data for it must be calculated - by X or Y axis
            booCalculateByYAxis = booIsFirstDateField;

            // Don't show the column if only one "results row" will be shown for Y axis
            booShowChangesColumn = !(booCalculateByYAxis && oData.datasets.length <= 1);

            if (booIsFirstDateField) {
                booUseMonthsSorting = ['months', 'same_month_last_years'].has(breakdownFieldOperator);
                booUseYearsSorting  = ['years'].has(breakdownFieldOperator);
            }
        }


        // The first main column
        arrColumns.push({
            id:        mainColumnId,
            header:    breakdownFieldLabel,
            width:     250,
            sortable:  true,
            dataIndex: 'label'
        });
        arrFields.push({
            name:     'label',
            sortType: function (s) {
                if (booUseMonthsSorting) {
                    // Sort Months in other way
                    var date = empty(s) || s === '[Not set]' ? new Date('1900-01-01') : new Date(s + ' 01');
                    return date.getTime();
                } else if (booUseYearsSorting) {
                    // Sort Years in other way
                    var date = empty(s) || s === '[Not set]' ? new Date('1900-01-01') : new Date(s + '-01-01');
                    return date.getTime();
                } else {
                    // Custom "natural" sorting - so the same as it is on the php side
                    return String(s).toLowerCase();
                }
            }
        });

        // All other columns
        for (var i = 0; i < oData.labels.length; i++) {
            arrColumns.push({
                header:    oData.labels[i],
                width:     65,
                sortable:  true,
                dataIndex: 'column_' + i,
                fixed:     true
            });
            arrFields.push({name: 'column_' + i});

            if (booShowChangesColumn) {
                // Changes column
                arrColumns.push({
                    header:    _('% Change'),
                    width:     65,
                    sortable:  true,
                    dataIndex: 'changes_percent_column_' + i,
                    fixed:     true,
                    hidden:    !booCalculateByYAxis && i === oData.labels.length - 1,

                    renderer: function (val) {
                        var result = '<div style="text-align: center">-</div>';
                        if (val !== null && val !== undefined) {
                            result = String.format(
                                '<img src="{0}/images/icons/{1}" alt="% Change" width="16" height="16" style="vertical-align: middle" /><span style="color: {2};">{3}%</span>',
                                topBaseUrl,
                                empty(val) ? 'bullet_arrow_right_left.png' : (val > 0 ? 'bullet_arrow_up_green.png' : 'bullet_arrow_down_red.png'),
                                empty(val) ? 'black' : (val > 0 ? 'green' : 'red'),
                                empty(val) ? 0 : val
                            );
                        }

                        return result;
                    }
                });
                arrFields.push({name: 'changes_percent_column_' + i});
            }
        }


        // Prepare data
        var arrPreparedData = [];
        for (i = 0; i < oData.datasets.length; i++) {
            var dataset = oData.datasets[i];

            var oRowData = {
                'label': dataset.label
            };
            for (var j = 0; j < dataset.data.length; j++) {
                oRowData['column_' + j] = dataset.data[j];
            }

            arrPreparedData.push(oRowData);
        }

        // Calculate % of changes
        var change;
        if (booShowChangesColumn) {
            if (booCalculateByYAxis) {
                for (i = arrPreparedData.length - 1; i >= 0; i--) {
                    dataset = oData.datasets[i];
                    for (j = 0; j < dataset.data.length; j++) {
                        if (i === arrPreparedData.length - 1 || empty(arrPreparedData[i + 1]['column_' + j])) {
                            arrPreparedData[i]['changes_percent_column_' + j] = null;
                        } else {
                            change = arrPreparedData[i]['column_' + j] - arrPreparedData[i + 1]['column_' + j];

                            arrPreparedData[i]['changes_percent_column_' + j] = Math.round((change / arrPreparedData[i + 1]['column_' + j]) * 100);
                        }
                    }
                }
            } else {
                for (i = 0; i < arrPreparedData.length; i++) {
                    dataset = oData.datasets[i];
                    for (j = dataset.data.length - 1; j >= 0; j--) {
                        if (j === dataset.data.length - 1 || empty(arrPreparedData[i]['column_' + (j + 1)])) {
                            arrPreparedData[i]['changes_percent_column_' + j] = null;
                        } else {
                            change = arrPreparedData[i]['column_' + j] - arrPreparedData[i]['column_' + (j + 1)];

                            arrPreparedData[i]['changes_percent_column_' + j] = Math.round((change / arrPreparedData[i]['column_' + (j + 1)]) * 100);
                        }
                    }
                }
            }
        }

        var grid = new Ext.grid.GridPanel({
            store: {
                xtype:  'jsonstore',
                fields: arrFields,
                data:   arrPreparedData
            },

            columns:          arrColumns,
            height:           container.getHeight(),
            split:            true,
            stripeRows:       true,
            loadMask:         true,
            autoScroll:       true,
            cls:              'extjs-grid',
            autoExpandColumn: mainColumnId,
            autoExpandMin:    250,

            viewConfig: {
                deferEmptyText: false,
                emptyText:      _('No data to display.')
            }
        });

        container.removeAll();
        container.add(grid);
        container.doLayout();

        this.owner.fixParentPanelHeight();
    },

    sendRequestSaveAnalytics: function () {
        var thisPanel = this;

        thisPanel.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: empty(thisPanel.fieldAnalyticsId.getValue()) ? topBaseUrl + '/applicants/analytics/add' : topBaseUrl + '/applicants/analytics/edit',

            params: {
                analytics_id:     Ext.encode(thisPanel.fieldAnalyticsId.getValue()),
                analytics_name:   Ext.encode(thisPanel.fieldAnalyticsName.getValue()),
                analytics_type:   Ext.encode(thisPanel.panelType),
                analytics_params: Ext.encode(thisPanel.getFilterFields())
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    // Refresh list of Saved Searches
                    var wnd = Ext.getCmp(thisPanel.panelType + '-saved-analytics-window');
                    if (wnd) {
                        wnd.SavedAnalyticsGrid.store.reload();
                    }

                    // Show Name field
                    thisPanel.fieldAnalyticsName.setDisabled(false);
                    thisPanel.fieldAnalyticsName.setVisible(true);
                    thisPanel.analyticsFormPanel.setHeight(145);

                    // Update new id
                    thisPanel.fieldAnalyticsId.setValue(resultData.savedAnalyticsId);
                }

                var msg = String.format(
                    '<span style="color: {0}">{1}</span>',
                    resultData.success ? 'black' : 'red',
                    resultData.success ? _('Done!') : resultData.message
                );

                thisPanel.getEl().mask(msg);
                setTimeout(function () {
                    thisPanel.getEl().unmask();
                }, resultData.success ? 1000 : 2000);
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Analytics cannot be saved. Please try again later.'));
                thisPanel.getEl().unmask();
            }
        });
    },

    saveAnalytics: function () {
        var thisPanel = this;
        if (thisPanel.analyticsFormPanel.getForm().isValid()) {
            if (empty(thisPanel.fieldAnalyticsId.getValue())) {
                Ext.Msg.prompt(_('Save analytics'), _('Name:'), function (btn, text) {
                    if (btn === 'ok') {
                        thisPanel.fieldAnalyticsName.setValue(text);
                        thisPanel.sendRequestSaveAnalytics();
                    }
                });
            } else {
                this.sendRequestSaveAnalytics();
            }
        }
    },

    openAdvancedTab: function () {
        // Open Advanced Search with visible "Analytics" sub tab
        this.owner.openAdvancedSearchTab(0, '', true);
    }
});