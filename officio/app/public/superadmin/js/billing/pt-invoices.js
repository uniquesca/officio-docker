var PtInvoicesPanel = function(config) {
    Ext.apply(this, config);

// We need render some items only once
    var booRendered = false;

    // Use global config
    var invoicesOnPage = arrInvoicesConfig.invoicesOnPage;
    var currency = arrInvoicesConfig.currency;

    var customMoney = function(val) {
        return formatMoney(currency, val, true);
    };

    // Mark gross value in red if:
    // 1. sum of net and tax isn't equal to gross
    // 2. if net is less than zero
    var grossCheck = function(val, row, rec) {
        var booMarkRed = false;

        var totalCalculated = parseFloat(rec.data.invoice_tax) + parseFloat(rec.data.invoice_net);
        if(toFixed(parseFloat(val), 2) != toFixed(totalCalculated, 2) ||
           parseFloat(rec.data.invoice_net) < 0) {
            booMarkRed = true;
        }

        return String.format(
            '<span {0}>{1}</span>',
            booMarkRed ? 'style="color: red;"' : '',
            formatMoney(currency, val, true)
        );
    };

    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header:    'Date Posted',
                dataIndex: 'invoice_posted_date',
                width:     75,
                renderer:  Ext.util.Format.dateRenderer('Y-m-d'),
                fixed:     true
            }, {
                header: "Invoice Date",
                dataIndex: 'invoice_date',
                width: 75,
                renderer: Ext.util.Format.dateRenderer('Y-m-d'),
                fixed: true
            }, {
                id: 'invoice_subject',
                header: "Subject",
                dataIndex: 'invoice_subject'
            }, {
                header: "Invoice Number",
                dataIndex: 'invoice_number',
                hidden: true
            },{
                header: '',
                width: 23,
                align: 'center',
                sortable: false,
                renderer: function(value, p, record){
                    var imgIcon = 'company.png';
                    var imgAlt  = 'Company';
                    if(empty(record.data.invoice_company)) {
                        imgIcon = 'error.png';
                        imgAlt  = 'Unknown';
                    } else if(empty(record.data.invoice_company_id)) {
                        imgIcon = 'user_green.png';
                        imgAlt  = 'Prospect';
                    }

                    return String.format(
                        '<img src="{0}/images/icons/{1}" alt="{2}" title="{2}" width="16" height="16" />',
                        topBaseUrl, imgIcon, imgAlt
                    );
                }
            }, {
                header: "Company/Prospect",
                dataIndex: 'invoice_company',
                width: 200
            },{
                header: "Product",
                dataIndex: 'invoice_product',
                hidden: true
            }, {
                header: "Mode of Payment",
                dataIndex: 'invoice_mode_of_payment',
                hidden: true
            }, {
                header: "Gross",
                dataIndex: 'invoice_gross',
                renderer: grossCheck,
                width: 60
            }, {
                header: "Net",
                dataIndex: 'invoice_net',
                renderer: customMoney,
                width: 60
            }, {
                header: "Tax",
                dataIndex: 'invoice_tax',
                renderer: customMoney,
                width: 60
            }, {
                header: "Status",
                dataIndex: 'invoice_status',
                width: 60
            }

        ],
        defaultSortable: true
    });

    var TransactionRecord = Ext.data.Record.create([
        {name: 'invoice_id', type: 'int'},
        {name: 'invoice_date', type: 'date', dateFormat: 'Y-m-d'},
        {name: 'invoice_posted_date', type: 'date', dateFormat: 'Y-m-d'},
        {name: 'invoice_number', type: 'string'},
        {name: 'invoice_subject', type: 'string'},
        {name: 'invoice_company', type: 'string'},
        {name: 'invoice_company_id', type: 'int'},
        {name: 'invoice_prospect_id', type: 'int'},
        {name: 'invoice_product', type: 'string'},
        {name: 'invoice_mode_of_payment', type: 'string'},
        {name: 'invoice_gross', type: 'float'},
        {name: 'invoice_net', type: 'float'},
        {name: 'invoice_tax', type: 'float'},
        {name: 'invoice_status', type: 'string'}
    ]);

    var getFilterParams = function() {
        var date_from_val = Ext.get('invoice_filter_date_from').getValue();
        var date_from = new Date();
        if(!empty(date_from_val)) {
            date_from = Date.parseDate(date_from_val, dateFormatFull);
            date_from_val = date_from.format(dateFormatShort);
        }

        var date_to_val = Ext.get('invoice_filter_date_to').getValue();
        var date_to = new Date();
        if(!empty(date_to_val)) {
            date_to = Date.parseDate(date_to_val, dateFormatFull);
            date_to_val = date_to.format(dateFormatShort);
        }

        // Apply filter variables
        return {
            dir                    : invoicesStore.sortInfo.direction,
            sort                   : invoicesStore.sortInfo.field,
            filter_date_by         : Ext.encode(Ext.getCmp('invoice_filter_radio').getGroupValue()),
            filter_date_from       : Ext.encode(date_from_val),
            filter_date_to         : Ext.encode(date_to_val),
            filter_company         : Ext.encode(Ext.getCmp('invoice_filter_company').getValue()),
            filter_mode_of_payment : Ext.encode(Ext.getCmp('invoice_filter_mode_of_payment').getValue()),
            filter_product         : Ext.encode(Ext.getCmp('invoice_filter_product').getValue())
        };
    };

    // create the data store
    var invoicesStore = new Ext.data.Store({
        url: baseUrl + '/manage-invoices/get-invoices',
        method: 'POST',
        autoLoad: true,
        remoteSort: true,
        sortInfo:{field: 'invoice_posted_date', direction:'DESC'},

        baseParams: {
            start: 0,
            limit: invoicesOnPage
        },

        reader: new Ext.data.JsonReader({
            root:'rows',
            totalProperty:'totalCount'
        }, TransactionRecord),

        listeners: {
            beforeload: function(store, options) {
                options.params = options.params || {};
                params = getFilterParams();
                Ext.apply(options.params, params);
            },

            load: function() {
            }
        }
    });

    var tbar = new Ext.Toolbar({
        items: [
            {
                text: '<i class="las la-file-excel"></i>' + _('Export to Excel'),
                tooltip: 'Export all filtered invoices to excel file',
                handler: function() {
                    var $f = $('<form></form>').attr({
                        method: 'post',
                        target: '_blank',
                        action: baseUrl + '/manage-invoices/export'
                    }).appendTo(document.body);

                    var arrHiddenVariables = getFilterParams();

                    for(var i in arrHiddenVariables) {
                        $('<input type="hidden" />').attr({
                            name: i,
                            value: arrHiddenVariables[i]
                        }).appendTo($f);
                    }

                    $f[0].submit();
                }
            }
        ]
    });

    // create the Grid
    var invoicesGrid = new Ext.grid.GridPanel({
        store: invoicesStore,
        region: 'center',
        cm: cm,
        bodyStyle: 'background-color:#fff',
        stripeRows: true,
        autoExpandColumn: 'invoice_subject',
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        viewConfig: { deferEmptyText: 'No invoices found.', emptyText: 'No invoices found.' },
        loadMask: true,
        height: 450,
        autoWidth: true,

        tbar: tbar,

        bbar: new Ext.PagingToolbar({
            pageSize: invoicesOnPage,
            store: invoicesStore,
            displayInfo: true
        })
    });


    var ds = new Ext.data.Store({
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
    });

    // Custom rendering Template
    var resultTpl =  new Ext.XTemplate(
        '<tpl for="."><div class="x-combo-list-item" style="padding: 7px;">',
            '<h3>{companyName}</h3>',
            '<p style="padding-top: 3px;">Email: {companyEmail}</p>',
        '</div></tpl>'
    );

    var companySearch = new Ext.form.ComboBox({
        id: 'invoice_filter_company',
        fieldLabel: 'Company/Prospect',
        store: ds,
        displayField: 'companyName',
        typeAhead: false,
        emptyText: 'Type to search for company or prospect...',
        loadingText: 'Searching...',
        width: 240,
        listWidth: 240,
        listClass: 'no-pointer',
        cls: 'with-right-border',
        pageSize: 10,
        minChars: 1,
        hideTrigger: true,
        tpl: resultTpl,
        itemSelector: 'div.x-combo-list-item'
    });


    var filterForm = new Ext.FormPanel({
        region: 'east',

        titlebar: true,
        title: 'Extended Filter',
        collapsible: true,
        collapsed: false,
        initialSize: 280,
        width: 280,
        split: true,

        labelAlign: 'top',
        buttonAlign: 'center',

        bodyStyle: {
            background: '#ffffff',
            padding: '7px'
        },

        items: [
            {
                id: 'invoice_filter_radio',
                xtype: 'radio',
                hideLabel: true,
                itemCls: 'no-padding-top no-padding-bottom',
                boxLabel: 'Today',
                name: 'invoice-date-period',
                inputValue: 'today'
            }, {
                xtype: 'radio',
                hideLabel: true,
                itemCls: 'no-padding-top no-padding-bottom',
                boxLabel: 'This month',
                name: 'invoice-date-period',
                inputValue: 'month',
                checked: true
            }, {
                xtype: 'radio',
                hideLabel: true,
                itemCls: 'no-padding-top no-padding-bottom',
                boxLabel: 'This year',
                name: 'invoice-date-period',
                inputValue: 'year'
            },
            {
                xtype: 'radio',
                hideLabel: true,
                itemCls: 'no-padding-top no-padding-bottom',
                boxLabel: 'From:'+new Array(24).join('&nbsp;')+'To:',
                name: 'invoice-date-period',
                inputValue: 'from_to'
            },

            {
                layout:'column',
                style: 'padding-bottom: 10px;',
                bodyStyle: {
                    background: '#ffffff'
                },
                items:[
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff'
                        },
                        items: [{
                            id: 'invoice_filter_date_from',
                            xtype: 'datefield',
                            hideLabel: true,
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: 120,
                            vtype: 'daterange',
                            endDateField: 'invoice_filter_date_to' // id of the end date field
                        }]
                    }, {
                        columnWidth: 0.5,
                        layout: 'form',
                        bodyStyle: {
                            background: '#ffffff'
                        },
                        items: [{
                            id: 'invoice_filter_date_to',
                            xtype: 'datefield',
                            hideLabel: true,
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: 120,
                            vtype: 'daterange',
                            startDateField: 'invoice_filter_date_from' // id of the start date field
                        }]
                    }
                ]
            }, companySearch, {
                id: 'invoice_filter_mode_of_payment',
                xtype: 'combo',
                fieldLabel: 'Mode of Payment',
                store: new Ext.data.Store({
                    data: arrModes,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'modeId'}, {name: 'modeName'}]))
                }),
                mode: 'local',
                width: 240,
                displayField: 'modeName',
                valueField: 'modeId',
                allowBlank: true,
                typeAhead: false,
                forceSelection: true,
                triggerAction: 'all',
                emptyText: 'Select mode of payment...',
                selectOnFocus: true,
                editable: false,
                value: ''
            }, {
                id: 'invoice_filter_product',
                xtype: 'combo',
                fieldLabel: 'Product',
                store: new Ext.data.Store({
                    data: arrProducts,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'productId'}, {name: 'productName'}]))
                }),
                mode: 'local',
                width: 240,
                displayField: 'productName',
                valueField: 'productId',
                allowBlank: true,
                typeAhead: false,
                forceSelection: true,
                triggerAction: 'all',
                emptyText: 'Select a product...',
                selectOnFocus: true,
                editable: false,
                value: ''
            }
        ],

        buttons: [
            {
                text: 'Reset',
                handler: function () {
                    filterForm.getForm().reset();
                }
            }, {
                text: 'Apply Filter',
                cls: 'orange-btn',
                handler: function () {
                    if (filterForm.getForm().isValid()) {
                        invoicesStore.reload();
                    }
                }
            }
        ],

        listeners: {
            'afterlayout': function() {
                if(!booRendered && arrInvoicesConfig.booCollapsedFilter) {
                    filterForm.collapse(false);
                }
                booRendered = true;
            }
        }
    });

    PtInvoicesPanel.superclass.constructor.call(this, {
        frame: true,
        autoWidth: true,
        bodyStyle: 'background-color:#fff',
        height: 450,
        layout: 'border',
        items: [
            invoicesGrid,
            filterForm
        ]
    });
};

Ext.extend(PtInvoicesPanel, Ext.Panel, {
});