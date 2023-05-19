var AccessLogsFilterPanel = function (config) {
    var filterForm = this;
    Ext.apply(this, config);

    var casesStore = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-cases-list',
            method: 'post',
        }),

        baseParams: {
            parentMemberId: 0,
            booLimitCases: 1,
            booCategoryMustBeLinked: 0,
            exceptCaseId: 0
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'clientId'},
            {name: 'clientFullName'}
        ])
    });

    var panelWidth = 310;

    AccessLogsFilterPanel.superclass.constructor.call(this, {
        collapsible: true,
        collapsed: false,
        initialSize: panelWidth,
        width: panelWidth,
        split: true,

        labelAlign: 'top',
        buttonAlign: 'center',
        cls: 'filter-panel',
        bodyStyle: {
            'padding-left': '7px'
        },

        items: [
            {
                layout: 'form',
                style: 'margin-top: 10px; margin-bottom: 10px;',
                items: {
                    id: 'log_filter_date',
                    fieldLabel: _('Date'),
                    labelSeparator: '',
                    xtype: 'combo',
                    store: new Ext.data.SimpleStore({
                        fields: ['value', 'display'],
                        data: [
                            ['today', _('Today')],
                            ['month', _('This month')],
                            ['year', _('This year')],
                            ['from_to', _('Custom Date Range')]
                        ]
                    }),
                    displayField: 'display',
                    valueField: 'value',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    selectOnFocus: false,
                    editable: false,
                    grow: true,
                    width: panelWidth,
                    value: 'today',
                    allowBlank: false,
                    listeners: {
                        beforeselect: function (combo, record) {
                            Ext.getCmp('log_filter_date_range_section').setVisible(record.data.value == 'from_to');
                        }
                    }
                }
            }, {
                id: 'log_filter_date_range_section',
                hidden: true,
                layout: 'column',
                style: 'padding-bottom: 10px;',
                items: [
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: [{
                            id: 'log_filter_date_from',
                            xtype: 'datefield',
                            fieldLabel: _('From'),
                            labelSeparator: '',
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: panelWidth / 2,
                            vtype: 'daterange',
                            endDateField: 'log_filter_date_to' // id of the end date field
                        }]
                    }, {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: [{
                            id: 'log_filter_date_to',
                            xtype: 'datefield',
                            fieldLabel: _('To'),
                            labelSeparator: '',
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: panelWidth / 2,
                            vtype: 'daterange',
                            startDateField: 'log_filter_date_from' // id of the start date field
                        }]
                    }
                ]
            }, {
                layout: 'form',
                style: 'margin-bottom: 10px;',
                items: {
                    id: 'log_filter_type',
                    xtype: 'combo',

                    fieldLabel: _('Event Type'),
                    labelSeparator: '',
                    emptyText: _('Any Event Type'),
                    store: new Ext.data.Store({
                        data: arrSettings.arrLogTypes,
                        reader: new Ext.data.JsonReader(
                            {id: 0},
                            Ext.data.Record.create([
                                {name: 'filter_type_id'},
                                {name: 'filter_type_name'},
                                {name: 'filter_type_section'}
                            ])
                        )
                    }),
                    mode: 'local',
                    tpl: new Ext.XTemplate(
                        '<tpl for=".">',
                        '<tpl if="filter_type_name==\'-\'">',
                        '<div class="x-combo-list-item x-item-disabled"><hr style="border-top: 0" /></div>',
                        '</tpl>',
                        '<tpl if="filter_type_name!=\'-\'">',
                        '<div class="x-combo-list-item" style="padding-left: 20px;">{filter_type_name}</div>',
                        '</tpl>',
                        '</tpl>'
                    ),
                    valueField: 'filter_type_id',
                    displayField: 'filter_type_name',
                    triggerAction: 'all',
                    editable: true,
                    searchContains: true,
                    forceSelection: true,
                    selectOnFocus: true,
                    width: panelWidth,
                    listWidth: panelWidth,
                    doNotAutoResizeList: true
                }
            }, {
                layout: 'form',
                style: 'margin-bottom: 10px;',
                items: {
                    id: 'log_filter_users',
                    xtype: 'combo',

                    fieldLabel: _('User'),
                    labelSeparator: '',
                    emptyText: _('Any user...'),
                    store: new Ext.data.Store({
                        data: arrSettings.arrLogUsers,
                        reader: new Ext.data.JsonReader(
                            {id: 0},
                            Ext.data.Record.create([
                                {name: 'filter_user_id'},
                                {name: 'filter_username'}
                            ])
                        )
                    }),
                    mode: 'local',
                    valueField: 'filter_user_id',
                    displayField: 'filter_username',
                    editable: true,
                    searchContains: true,
                    forceSelection: true,
                    selectOnFocus: true,
                    width: panelWidth,
                    listWidth: panelWidth
                }
            }, {
                layout: 'form',
                style: 'margin-bottom: 10px;',
                hidden: arrSettings.show_company_filter,
                items: {
                    id: 'log_filter_cases',
                    xtype: 'combo',

                    fieldLabel: _('Case'),
                    labelSeparator: '',
                    emptyText: _('Enter client name or case number...'),
                    store: casesStore,
                    valueField: 'clientId',
                    displayField: 'clientFullName',
                    forceSelection: true,
                    itemSelector: 'div.x-combo-list-item',
                    triggerClass: 'x-form-search-trigger',
                    listClass: 'no-pointer',
                    typeAhead: false,
                    selectOnFocus: true,
                    pageSize: 0,
                    minChars: 2,
                    queryDelay: 750,
                    width: panelWidth,
                    listWidth: panelWidth
                }
            }, {
                id: 'log_filter_company',
                xtype: 'combo',
                fieldLabel: _('Company'),
                labelSeparator: '',
                emptyText: _('Type to search for company...'),
                loadingText: _('Searching...'),
                hidden: !arrSettings.show_company_filter,
                store: new Ext.data.Store({
                    proxy: new Ext.data.HttpProxy({
                        url: baseUrl + '/manage-company/company-search',
                        method: 'post'
                    }),

                    reader: new Ext.data.JsonReader({
                        root: 'rows',
                        totalProperty: 'totalCount',
                        id: 'companyId'
                    }, [
                        {name: 'companyId', mapping: 'company_id'},
                        {name: 'companyName', mapping: 'companyName'},
                        {name: 'companyEmail', mapping: 'companyEmail'}
                    ])
                }),
                valueField: 'companyId',
                displayField: 'companyName',
                typeAhead: false,
                cls: 'with-right-border',
                width: 250,
                listWidth: 250,
                listClass: 'no-pointer',
                pageSize: 10,
                minChars: 1,
                hideTrigger: true,
                tpl: new Ext.XTemplate(
                    '<tpl for="."><div class="x-combo-list-item" style="padding: 7px;">',
                    '<h3>{companyName}</h3>',
                    '<p style="padding-top: 3px;">Email: {companyEmail}</p>',
                    '</div></tpl>'
                ),
                itemSelector: 'div.x-combo-list-item'
            }, {
                xtype: 'container',
                layout: 'column',
                style: 'margin: 0 auto',
                width: 160,
                items: [
                    {
                        xtype: 'button',
                        text: _('Reset'),
                        handler: function () {
                            filterForm.getForm().reset();
                        }
                    }, {
                        xtype: 'button',
                        text: _('Search'),
                        cls: 'orange-btn',
                        style: 'float: right',
                        handler: function () {
                            if (filterForm.getForm().isValid()) {
                                Ext.getCmp('log_grid').getStore().load();
                            }
                        }
                    }
                ]
            }
        ]
    });
};

Ext.extend(AccessLogsFilterPanel, Ext.form.FormPanel, {});