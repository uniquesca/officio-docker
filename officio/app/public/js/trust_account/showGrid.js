var showUnassignedTransactions = function (ta_id) {
    FtaSetFilterAndApply({filter: 'process'}, ta_id);
};

function showTransactionsGrid(ta_id, currency) {
    // Don't show $0.00
    var customMoney = function (val, booShowEmpty) {
        return formatMoney(currency, val, booShowEmpty);
    };

    // the column model has information about grid columns
    // dataIndex maps the column to the specific data field in
    // the data store (created below)
    var sm = new Ext.grid.CheckboxSelectionModel();
    var autoExpandColumnId = Ext.id();
    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm,
            {
                header: _('Date from Bank'),
                dataIndex: 'date_from_bank',
                resizable: false,
                menuDisabled: true,
                width: 155,
                fixed: true,
                align: 'center'
            }, {
                id: autoExpandColumnId,
                header: _('Description/Notes'),
                dataIndex: 'description',
                align: 'left',
                menuDisabled: true,
                width: 200
            }, {
                header: _('Payment method'),
                dataIndex: 'payment_method',
                align: 'left',
                menuDisabled: true,
                width: 110
            }, {
                header: _('Deposit'),
                dataIndex: 'deposit',
                align: 'right',
                menuDisabled: true,
                width: 80,
                renderer: function (value, p, record) {
                    var booShowZero = false;
                    if (record.data.purpose == 'OFFICIO_START_BALANCE' ||
                        record.data.purpose == 'OFFICIO_BANK_TRANSFER_DIFFERENTIAL' ||
                        record.data.withdrawal === '') {
                        booShowZero = true;
                    }
                    return customMoney(value, booShowZero);
                }
            }, {
                header: _('Withdrawal'),
                dataIndex: 'withdrawal',
                align: 'right',
                width: 100,
                menuDisabled: true,
                renderer: function (value, p, record) {
                    booShowZero = (record.data.deposit === '') ? true : false;
                    return customMoney(value, booShowZero);
                }
            }, {
                header: _('Balance'),
                dataIndex: 'balance',
                hidden: true,
                align: 'right',
                width: 80,
                menuDisabled: true,
                renderer: function (value, p, record) {
                    var booShowZero = true;
                    if (record.data.purpose == 'OFFICIO_START_BALANCE' ||
                        record.data.purpose == 'OFFICIO_BANK_TRANSFER_DIFFERENTIAL') {
                        booShowZero = false;
                    }

                    return customMoney(value, booShowZero);
                }
            }, {
                header: _('Allocation Amount'),
                dataIndex: 'allocation_amount',
                align: 'right',
                width: 130,
                menuDisabled: true,
                renderer: function (value, p, record) {
                    var result = '';
                    if (!empty(value)) {
                        var arr = value.split(',');
                        arr.forEach(function (val, index) {
                            result += '<div' + ((index === 0) ? ' style="padding-bottom:4px;">' : ' class="trustac-devider">') + customMoney(val, false) + '</div>';
                        });
                    }
                    result = '<div style="width:100%;">' + result + '</div>';
                    return result;

                }
            }, {
                header: _('Assigned to'),
                dataIndex: 'client_name',
                width: 130,
                menuDisabled: true,
                align: 'left',
                renderer: function (value, p, record) {
                    var tooltip = record.data['client_name_text'].replaceAll("'", "&#39;").replaceAll('"', "&#39;");
                    return String.format(
                        "<div ext:qtip='{0}'>{1}</div>",
                        tooltip,
                        value.replace(/(.*)title="(.*)"(.*)/g, '$1 ext:qtip="' + tooltip + '"$3')
                    );
                }
            }, {
                header: _('Receipt Number'),
                dataIndex: 'receipt_number',
                align: 'right',
                menuDisabled: true,
                width: 150
            }, {
                header: _('Destination Account'),
                dataIndex: 'destination_account',
                hidden: true,
                align: 'left',
                menuDisabled: true,
                width: 150
            }
        ],
        defaultSortable: true
    });

    // this could be inline, but we want to define the Transaction record type, so we can add records dynamically
    var TransactionRecord = Ext.data.Record.create([
        {name: 'id', type: 'int'},
        {name: 'date_from_bank', type: 'string'},
        {name: 'description', type: 'string'},
        {name: 'deposit', type: 'float'},
        {name: 'withdrawal', type: 'float'},
        {name: 'balance', type: 'float'},
        {name: 'notes', type: 'string'},
        {name: 'assigned', type: 'bool'},
        {name: 'purpose', type: 'string'},
        {name: 'client_name', type: 'string'},
        {name: 'client_name_text', type: 'string'},
        {name: 'destination_account', type: 'string'},
        {name: 'allocation_amount', type: 'string'},
        {name: 'receipt_number', type: 'string'},
        {name: 'payment_method', type: 'string'}
    ]);

    var store = new Ext.data.Store({
        url: baseUrl + '/trust-account/index/get-transactions-grid',
        remoteSort: true,
        sortInfo: {field: 'date_from_bank', direction: 'ASC'},
        reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, TransactionRecord
        ),

        listeners: {
            beforeload: function (store, options) {
                Ext.getBody().mask('Loading...');

                options.params = options.params || {};

                //variables from Grid
                var params =
                    {
                        ta_id: ta_id,
                        start: empty(options.params.start) ? 0 : options.params.start,
                        limit: empty(options.params.limit) ? displayRecordsOnTrustAccountPage : options.params.limit
                    };

                Ext.apply(options.params, params, FtaGetFilter(ta_id));
            },

            load: function (store, records, opt) {
                var data = store.reader.jsonData;

                if (parseInt(data.totalTransactionsInTA, 10) < 0) {
                    return;
                }

                if (parseInt(data.totalTransactionsInTA, 10) !== 0) {
                    // there are transactions
                    var unassigned = 0;
                    var unassigned_count = 0;

                    for (var i = 0, len = records.length; i < len; i++) {
                        if (!records[i].data.assigned && records[i].data.withdrawal > 0) {
                            unassigned += records[i].data.withdrawal;
                            unassigned_count++;
                        }
                    }

                    showSummary(data.balance, unassigned, unassigned_count);

                    //update grid height
                    var new_grid_height = calculateGridHeight(store);
                    if (grid.getHeight() < new_grid_height) {
                        grid.setHeight(new_grid_height);
                    }

                    $('#ta-sample-' + ta_id).hide();
                    grid.show();
                    grid.setHeight(initPanelSize() - 135);
                    grid.bbar.show();

                    toggleAccountingButtons(false);
                } else {
                    // no transactions in the Client A/C
                    grid.show();
                    grid.setHeight(initPanelSize() - 135);
                    grid.bbar.hide();

                    // Generate fake data
                    const getDaysInMonth = (year, month) => new Date(year, month, 0).getDate()
                    const addMonths = (input, months) => {
                        const date = new Date(input)
                        date.setDate(1)
                        date.setMonth(date.getMonth() + months)
                        date.setDate(Math.min(input.getDate(), getDaysInMonth(date.getFullYear(), date.getMonth() + 1)))
                        return date
                    }

                    var fakeRows = [];
                    // 3 month from today as a start date
                    var dateFromBank = addMonths(new Date(), -3);
                    for (var i = 0; i < 10; i++) {
                        var booDeposit = empty(i) || Math.random() < 0.5;
                        var amount = Math.floor(Math.random() * 5000) + 1000;

                        var description = '';
                        if (booDeposit) {
                            if (empty(i)) {
                                description = empty(i) ? _('Starting balance') : '';
                            } else {
                                var randomDepositMsg = Math.random() * 3;
                                if (randomDepositMsg <= 1) {
                                    description = _('Wire transfer');
                                } else if (randomDepositMsg <= 2) {
                                    description = _('Deposit');
                                } else {
                                    description = _('Cheque #') + Math.floor(Math.random() * 5000);
                                }
                            }
                        } else {
                            description = _('Transfer to operating account');
                        }

                        fakeRows.push({
                            id: i,
                            date_from_bank: String.format(
                                '<a href="#" onclick="return false">{0}</a>',
                                Ext.util.Format.date(dateFromBank, dateFormatFull)
                            ),
                            description: String.format(
                                '<a href="#" onclick="return false">{0}</a>',
                                description
                            ),
                            deposit: booDeposit ? amount : 0,
                            withdrawal: !booDeposit ? amount : 0,
                        });

                        // Add 1-5 days to the previous date
                        dateFromBank = new Date(+dateFromBank + 86400000 * (Math.random() * 5));
                    }

                    var arrFakeData = {
                        rows: fakeRows,
                        totalCount: fakeRows.length,
                        totalTransactionsInTA: -1
                    };
                    store.loadData(arrFakeData);

                    $('#ta-sample-' + ta_id).show();

                    toggleAccountingButtons(true);
                }

                Ext.getBody().unmask();
            }
        }
    });

    var toggleAccountingButtons = function (booDisable) {
        btnTASelectColumns.setDisabled(booDisable);
        btnTARecReport.setDisabled(booDisable);
        if (btnTARecordsDelete) {
            btnTARecordsDelete.setDisabled(booDisable);
        }
        btnTARecordsExport.setDisabled(booDisable);
        if (btnTAImportHistory) {
            btnTAImportHistory.setDisabled(booDisable);
        }
        btnTAPrint.setDisabled(booDisable);
    };

    var showSummary = function (balance, unassigned_transactions, unassigned_transactions_count) {
        var transactionsText = '';
        if (unassigned_transactions > 0) {
            transactionsText = ' in <a href="#" onclick="showUnassignedTransactions(' + ta_id + '); return false;">' + unassigned_transactions_count + ' unassigned</a> Transaction';
        }

        statusBar.setStatus({
            text: _('Current ') + arrApplicantsSettings.ta_label + _(' Balance: ') + customMoney(balance, true),
            iconCls: 'ok-icon'
        });
    };

    var statusBar = new Ext.ux.StatusBar({
        text: '',
        cls: 'trustac-status-grid'
    });

    var pagingBar = new Ext.PagingToolbar({
        pageSize: displayRecordsOnTrustAccountPage,
        store: store,
        emptyMsg: _("No transactions to display")
    });

    var ShowGrid = function (config) {
        var thisGrid = this;
        Ext.apply(this, config);

        Ext.grid.CheckboxSelectionModel.override({
            initEvents: function () {
                Ext.grid.CheckboxSelectionModel.superclass.initEvents.call(this);
                if (this.grid.enableDragDrop || this.grid.enableDrag)
                    this.grid.events['rowclick'].clearListeners();
                this.grid.on('render', function () {
                    var view = this.grid.getView();

                    view.mainBody.on('mousedown', function (e, t) {
                        this.onMouseDown(e, t);
                        var mainChecker = $('#editor-grid' + ta_id + ' .x-grid3-header .x-grid3-hd-checker')[0];
                        var selfFormsGrid = Ext.getCmp('editor-grid' + ta_id);
                        if (selfFormsGrid.getSelectionModel().getCount() == selfFormsGrid.getStore().getCount()) {
                            $(mainChecker).addClass('x-grid3-hd-checker-on');
                        } else {
                            $(mainChecker).removeClass('x-grid3-hd-checker-on');
                        }

                        var mappingChecker = $('#mapping-main-grid .x-grid3-header .x-grid3-hd-checker')[0];
                        var selfGrid = Ext.getCmp('mapping-main-grid');
                        if (!selfGrid)
                            return;
                        if (selfGrid.getSelectionModel().getCount() == selfGrid.getStore().getCount()) {
                            $(mappingChecker).addClass('x-grid3-hd-checker-on');
                        } else {
                            $(mappingChecker).removeClass('x-grid3-hd-checker-on');
                        }
                    }, this);
                    Ext.fly(view.innerHd).on('mousedown', this.onHdMouseDown, this);
                }, this);
            }
        });

        FormsGrid.superclass.constructor.call(this, {
            id: 'editor-grid' + ta_id,
            stateId: 'ta_grid',
            hidden: true,
            store: store,
            cm: cm,
            sm: sm,
            bbar: [statusBar, '-', pagingBar],
            renderTo: 'div-ta-grid' + ta_id,
            layout: 'fit',
            height: 500,
            stripeRows: true,
            autoExpandColumn: autoExpandColumnId,
            viewConfig: {
                emptyText: _('No transactions found.'),
                forceFit: true
            },
            autoScroll: true,
            cls: 'extjs-grid'
        });
    };

    Ext.extend(ShowGrid, Ext.grid.GridPanel, {});
    var grid = new ShowGrid();

    grid.getView().getRowClass = function (record, index) {
        return (record.data.assigned ? 'green-row' : '');
    };


    new Ext.form.ComboBox({
        id: 'filter_type_t' + ta_id,
        typeAhead: false,
        triggerAction: 'all',
        transform: 'filter_type' + ta_id,
        width: 250,
        editable: false,
        forceSelection: true,
        thisCookieId: 'ta_filter_type_default',

        listeners: {
            beforeselect: function (combo, selRecord) {
                FtaSetFilter({filter: selRecord.data.value}, ta_id);

                // Save selected value to cookies and use it next time on page refresh
                Ext.state.Manager.set(combo.thisCookieId, selRecord.data.value);
            }
        }
    });

    var ds = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/trust-account/index/get-cases-list',
            method: 'post',
        }),

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'clientId'},
            {name: 'clientFullName'}
        ])
    });

    var resultTpl = new Ext.XTemplate(
        '<tpl for="."><div class="x-combo-list-item" style="padding: 2px;">{[this.formatName(values)]}</div></tpl>',
        {
            formatName: function (record_data) {
                return this.highlight(record_data.clientFullName, ds.reader.jsonData.query);
            },

            highlight: function (str, query) {
                var highlightedRow = str.replace(
                    new RegExp('(' + preg_quote(query) + ')', 'gi'),
                    "<b style='background-color: #FFFF99;'>$1</b>"
                );

                return highlightedRow;
            }
        }
    );

    new Ext.form.ComboBox({
        id: 'filter-client-name' + ta_id,
        triggerAction: 'all',
        hidden: true,
        transform: 'client_name' + ta_id,
        width: 400,
        listWidth: 389,
        doNotAutoResizeList: true,
        emptyText: _('Type and select a Case...'),
        store: ds,
        tpl: resultTpl,
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
        queryDelay: 750
    });

    var btnTARecordsDelete;
    if (!is_client && arrApplicantsSettings['access']['accounting']['general']['can_edit']) {
        btnTARecordsDelete = new Ext.Button({
            text: '<i class="las la-trash"></i>' + _('Delete'),
            tooltip: _('Delete a particular time period of a selected transaction(s).'),
            renderTo: 'delete_transactions_menu_' + ta_id,
            menu: new Ext.menu.Menu({
                cls: 'no-icon-menu',
                items: [
                    {
                        text: '<i class="las la-window-close"></i>' + _('Time period'),
                        handler: function () {
                            deleteTATransactionsByTime(ta_id);
                        }
                    }, {
                        text: '<i class="las la-window-close"></i>' + _('Selected transactions'),
                        handler: function () {
                            deleteTATransactionsBySelection(ta_id);
                        }
                    }
                ]
            })
        });
    }

    var btnTARecordsExport = new Ext.Button({
        text: '<i class="las la-file-export"></i>' + _('Export'),
        tooltip: _('Create an Excel spreadsheet of all visible transactions.'),
        renderTo: 'export_ta_btn_' + ta_id,
        handler: function () {
            exportImportedTransactions(
                ta_id,
                $('#filter_type' + ta_id).val(),
                $('#client_name' + ta_id).val(),
                $('#client_code' + ta_id).val(),
                $('#start-date' + ta_id).val(),
                $('#end-date' + ta_id).val(),
                $('#unassigned-end-date' + ta_id).val()
            );
        }
    });

    var btnTAImportHistory;
    if (arrApplicantsSettings['access']['accounting']['general']['history']) {
        btnTAImportHistory = new Ext.Button({
            text: '<i class="las la-history"></i>' + _('Import & Change History'),
            tooltip: String.format(
                _('Review a log of all previous bank record imports and changes to the {0} module.'),
                arrApplicantsSettings.ta_label
            ),
            renderTo: 'ta_import_history_' + ta_id,
            handler: function () {
                showHistory(ta_id);
            }
        });
    }

    var btnTAPrint = new Ext.Button({
        text: '<i class="las la-print"></i>' + _('Print'),
        tooltip: _('Print all visible transactions.'),
        renderTo: 'ta_print_' + ta_id,
        handler: function () {
            iprint(ta_id);
        }
    });

    if (arrApplicantsSettings['access']['accounting']['general']['import']) {
        new Ext.Button({
            text: '<i class="las la-file-import"></i>' + _('Import'),
            tooltip: _('Import a file of the transactions in your bank account.'),
            cls: 'main-btn',
            renderTo: 'ta_import_' + ta_id,
            handler: function () {
                showImportDialog1(ta_id, currency);
            }
        });
    }

    // set default filter
    if (typeof window['FtaSetFilter'] !== 'undefined') {
        var defaultFilter = '';
        var combo = Ext.getCmp('filter_type_t' + ta_id);
        if (combo) {
            defaultFilter = Ext.state.Manager.get(combo.thisCookieId);

            // Make sure that a saved value is correct
            if (empty(defaultFilter) || combo.getStore().find(combo.valueField, defaultFilter) === -1) {
                defaultFilter = '';
            }
        }

        if (empty(defaultFilter)) {
            // Use All as a default option
            defaultFilter = 'all';
        }

        FtaSetFilterAndApply({filter: defaultFilter}, ta_id);
    }

    var arrColumnsMenu = [];
    Ext.each(cm.columns, function (oColumn, index) {
        if (empty(index)) {
            return;
        }

        arrColumnsMenu.push({
            xtype: 'menucheckitem',
            text: oColumn.header,
            checked: !oColumn.hidden,
            hideOnClick: false,
            checkHandler: function (item, e) {
                cm.setHidden(cm.findColumnIndex(oColumn.dataIndex), !e);
            }
        });
    });

    var btnTASelectColumns = new Ext.Button({
        text: '<i class="las la-columns"></i>' + _('Select Columns'),
        tooltip: _('Select the information columns you want to display.'),
        renderTo: 'select-columns-' + ta_id,
        menu: new Ext.menu.Menu({
            items: arrColumnsMenu
        })
    });

    var btnTARecReport = new Ext.Button({
        text: '<i class="las la-book"></i>' + _('Reconciliation Report'),
        tooltip: _('Create or view a reconciliation report.'),
        cls: 'secondary-btn-border-only',
        renderTo: 'reconcile-menu-' + ta_id,
        menu: new Ext.menu.Menu({
            cls: 'no-icon-menu',
            width: 190,
            items: [
                {
                    text: '<i class="las la-sticky-note"></i>' + _('Law Society'),
                    handler: function () {
                        showReconcile(ta_id, site_version == 'australia' ? 'iccrc' : 'general');
                    }
                }, {
                    text: '<i class="las la-file-alt"></i>' + _('CICC'),
                    hidden: site_version == 'australia',
                    handler: function () {
                        showReconcile(ta_id, 'iccrc');
                    }
                }, {
                    text: '<i class="las la-search-location"></i>' + _('View past reports'),
                    hidden: empty(arrApplicantsSettings['access']['accounting']['general']['history']),
                    handler: function () {
                        showHistory(ta_id, 6);
                    }
                }
            ]
        })
    });

    if (!is_client && arrApplicantsSettings['access']['accounting']['general']['reports']) {
        new Ext.Button({
            text: '<i class="las la-book"></i>' + _('Reports'),
            tooltip: _('Generate reports on all of your clients.'),
            cls: 'secondary-btn-border-only',
            renderTo: 'view-reports-' + ta_id,
            menu: new Ext.menu.Menu({
                cls: 'no-icon-menu',
                items: [
                    {
                        text: _('All Clients - Case Balances Report'),
                        handler: function () {
                            showClientAccountingReport(false);
                        }
                    }, {
                        text: _('All Clients - Case Transactions Report'),
                        handler: function () {
                            showClientAccountingReport(true);
                        }
                    }
                ]
            })
        });
    }
}

function getSelectedTransactionsIds(ta_id) {
    var grid = Ext.getCmp('editor-grid' + ta_id);
    var arrSelectedTransactionsIds = [];
    var s = grid.getSelectionModel().getSelections();
    if (s.length > 0) {
        for (var i = 0; i < s.length; i++) {
            arrSelectedTransactionsIds.push(s[i].data.id);
        }
    }

    return arrSelectedTransactionsIds;
}

var showClientAccountingReport = function (booTransactions) {
    // Disable Date Period fields if they will be not used
    var checkPeriod = function () {
        var booDisable = Ext.getCmp('client-report').getValue().getGroupValue() === 'balances-all';
        Ext.getCmp('client-report-startdt').setDisabled(booDisable);
        Ext.getCmp('client-report-enddt').setDisabled(booDisable);
    };

    if (booTransactions) {
        wndTitle = 'All Clients - Case Transactions Report';
        wndHeight = 430;
        arrRadios = [
            {boxLabel: 'Show All Transactions', name: 'client-reports', inputValue: 'transaction-all', checked: true},
            {boxLabel: 'Show Only Fees Due', name: 'client-reports', inputValue: 'transaction-fees-due'},
            {boxLabel: 'Show Only Fees Received', name: 'client-reports', inputValue: 'transaction-fees-received'}
        ];
    } else {
        wndTitle = 'All Clients - Case Balances Report';
        wndHeight = 305;
        arrRadios = [
            {boxLabel: 'All Balances', name: 'client-reports', inputValue: 'balances-all', checked: true},
            {boxLabel: 'Balances between', name: 'client-reports', inputValue: 'balances-period'}
        ];
    }

    var reportsForm = new Ext.form.FormPanel({
        labelWidth: 85,
        bodyStyle: 'padding:5px;',
        defaults: {
            msgTarget: 'side'
        },

        items: [
            {
                id: 'client-report',
                xtype: 'radiogroup',
                hideLabel: true,
                columns: 1,
                items: arrRadios,
                listeners: {
                    change: function () {
                        checkPeriod();
                    }
                }
            },

            {
                layout: 'column',
                width: 390,
                items: [
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        labelWidth: 40,
                        items: {
                            id: 'client-report-startdt',
                            xtype: 'datefield',
                            fieldLabel: 'From',
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: 140,
                            allowBlank: false,
                            vtype: 'daterange',
                            endDateField: 'client-report-enddt' // id of the end date field
                        }
                    },
                    {
                        columnWidth: 0.5,
                        labelWidth: 20,
                        layout: 'form',
                        items: {
                            id: 'client-report-enddt',
                            xtype: 'datefield',
                            fieldLabel: 'To',
                            format: dateFormatFull,
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            width: 140,
                            allowBlank: false,
                            vtype: 'daterange',
                            value: new Date(),
                            startDateField: 'client-report-startdt' // id of the start date field
                        }
                    }
                ]
            },

            {html: '<p>&nbsp;</p>'},
            //Spacer

            {
                id: 'client-report-type',
                xtype: 'combo',
                fieldLabel: 'Report type',
                allowBlank: false,
                width: 90,
                store: new Ext.data.SimpleStore({
                    fields: ['type_id', 'type_name'],
                    data: [
                        ['pdf', 'PDF'],
                        ['xls', 'Excel']
                    ]
                }),
                mode: 'local',
                displayField: 'type_name',
                valueField: 'type_id',
                value: 'pdf',
                triggerAction: 'all',
                selectOnFocus: true,
                editable: false
            },

            {html: '<p>&nbsp;</p>', hidden: !booTransactions},
            //Spacer

            {
                id: 'client-report-currency',
                xtype: 'combo',
                hidden: !booTransactions,
                disabled: !booTransactions,
                fieldLabel: 'Currency',
                allowBlank: false,
                width: 90,
                store: new Ext.data.SimpleStore({
                    fields: ['currency_id', 'currency_label'],
                    data: arrApplicantsSettings.accounting.arrCurrencies
                }),
                mode: 'local',
                displayField: 'currency_label',
                valueField: 'currency_id',
                lazyRender: true,
                selectOnFocus: true,
                forceSelection: true,
                triggerAction: 'all',
                typeAhead: true,
                editable: false,

                // Automatically preselect the value if it is only 1 in the list
                value: arrApplicantsSettings.accounting.arrCurrencies.length === 1 ? arrApplicantsSettings.accounting.arrCurrencies[0][0] : undefined
            }
        ]
    });

    var win = new Ext.Window({
        title: '<i class="las la-book"></i>' + wndTitle,
        layout: 'fit',
        modal: true,
        resizable: false,
        width: 400,
        height: wndHeight,

        items: reportsForm,

        listeners: {
            show: function () {
                checkPeriod();

                // Clear all invlaid fields (if any)
                reportsForm.getForm().clearInvalid();
            }
        },

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            }, {
                text: '<i class="las la-file-alt"></i>' + _('Generate'),
                cls: 'orange-btn',
                handler: function () {
                    if (reportsForm.getForm().isValid()) {
                        var report = Ext.getCmp('client-report').getValue().getGroupValue();
                        var type = Ext.getCmp('client-report-type').getValue();

                        var file_name = (report === 'balances-all' || report === 'balances-period') ? 'All_Clients_Case_Balances_Report' : 'All_Clients_Case_Transactions_Report';

                        // Create a form and submit
                        var $f = $('<form></form>').attr({
                            method: 'post',
                            target: '_blank',
                            action: baseUrl + '/clients/accounting/create-report?file=' + file_name + '.' + type
                        }).appendTo(document.body);

                        var arrHiddenVariables = {
                            report: Ext.encode(report),
                            from: Ext.encode(Ext.getCmp('client-report-startdt').getValue()),
                            to: Ext.encode(Ext.getCmp('client-report-enddt').getValue()),
                            type: Ext.encode(type),
                            currency: Ext.encode(Ext.getCmp('client-report-currency').getValue())
                        };

                        for (var i in arrHiddenVariables) {
                            if (arrHiddenVariables.hasOwnProperty(i)) {
                                $('<input type="hidden" />').attr({
                                    name: i,
                                    value: arrHiddenVariables[i]
                                }).appendTo($f);
                            }
                        }

                        $f[0].submit();
                    }
                }
            }
        ]
    });

    win.show();
    win.center();
};