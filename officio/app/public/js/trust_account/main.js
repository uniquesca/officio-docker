var rr_months = [];

function createReconciliation(ta_id, end_date, reconcile_type, is_draft, balance_date, balance_notes) {
    var url = baseUrl + '/trust-account/index/create-reconcile';
    var oParams = {
        company_ta_id: ta_id,
        reconcile_type: reconcile_type,
        end_date: Ext.encode(end_date),
        is_draft: is_draft,
        balance_date: balance_date ? balance_date : '',
        balance_notes: balance_notes ? balance_notes : ''
    };

    if (is_draft) {
        submit_hidden_form(url, oParams);
    } else {
        Ext.getBody().mask('Creating report...');

        //create report
        Ext.Ajax.request({
            url: url,
            params: oParams,

            success: function (f) {
                var result = Ext.decode(f.responseText);

                if (result.success) {
                    //clear month array
                    rr_months = [];

                    //show report
                    window.open(baseUrl + '/trust-account/index/get-pdf?id=' + result.id + '&file=Reconciliation_report.pdf');
                } else if (result.msg) {
                    Ext.simpleConfirmation.error(result.msg);
                }

                Ext.getBody().unmask();
            },
            failure: function () {
                Ext.getBody().unmask();
                Ext.Msg.alert('Status', 'Can\'t create Reconciliation Report');
            }
        });
    }
}

function proceedReconcile(ta_id, reconcile_type) {
    //create reconcile
    Ext.getBody().mask('Processing...');
    Ext.Ajax.request({
        url: baseUrl + '/trust-account/index/check-reconcile',
        params: {
            company_ta_id: ta_id,
            end_date: Ext.encode(rr_months[ta_id]),
            reconcile_type: reconcile_type
        },
        success: function (result) {
            result = Ext.decode(result.responseText);
            if (!result.success) {
                Ext.getBody().unmask();
                if (result.msg) {
                    Ext.simpleConfirmation.error(result.msg);
                }
                return false;
            }

            if (result.unassignedWithdrawals > 0 || result.unassignedDeposits > 0) {
                Ext.simpleConfirmation.warning('You have selected to reconcile for a period which has unassigned transactions. Please ensure all the deposits and withdrawals for the selected period are assigned to Cases or Invoices before you proceed.');
                FtaSetFilterAndApply({filter: 'unassigned', end_date: result.end_date}, ta_id);
            } else if (result.balance != 0) {
                showCheckReconcileDialog(ta_id, reconcile_type, result.balance);
            } else {
                createReconciliation(ta_id, rr_months[ta_id], reconcile_type, 0);
            }

            Ext.getBody().unmask();
            return true;

        },
        failure: function () {
            Ext.Msg.alert('Status', 'Can\'t check reconcile');
            Ext.getBody().unmask();
        }
    });
}

function showCheckReconcileDialog(ta_id, reconcile_type, balance) {
    // Get the currency of the current T/A - use it when show amounts
    var currency;
    if (arrTATabs !== undefined && arrTATabs.length > 0) {
        for (var i = 0; i < arrTATabs.length; i++) {
            if (arrTATabs[i].company_ta_id == ta_id) {
                currency = arrTATabs[i].currency;
                break;
            }
        }
    }

    // Labels are different if balance is > 0 or < 0
    var label = '';
    var labelStyle = 'font-size: 12px;';
    if (balance > 0) {
        label = String.format(
            'The balance in your ' + arrApplicantsSettings.ta_label + ' is short for {0}.<br/>' +
            'This could be due to Bank service charges applied to your ' + arrApplicantsSettings.ta_label + '.<br/>' +
            'To reconcile, this amount needs to be deposited to your ' + arrApplicantsSettings.ta_label + '.',
            formatMoney(currency, balance, true)
        );
    } else {
        label = String.format(
            'The balance in your ' + arrApplicantsSettings.ta_label + ' is over by {0}.<br/>' +
            'This could be due to Bank interest charges applied to your ' + arrApplicantsSettings.ta_label + '.<br/>' +
            'To reconcile, this amount needs to be withdrawn from your ' + arrApplicantsSettings.ta_label + '.',
            formatMoney(currency, Math.abs(balance), true)
        );
    }

    var formPanel = new Ext.form.FormPanel({
        labelWidth: 220,
        labelAlign: 'top',
        layout: 'column',
        style: 'padding: 10px 0;',
        defaults: {
            msgTarget: 'side'
        },

        items: [
            {
                bodyStyle: 'padding:5px',
                layout: 'form',
                items: {
                    name: 'balance_date',
                    xtype: 'datefield',
                    fieldLabel: 'Date',
                    format: dateFormatFull,
                    width: 150,
                    allowBlank: false,
                    value: rr_months[ta_id]
                }
            }, {
                bodyStyle: 'padding:5px',
                layout: 'form',
                items: {
                    name: 'balance_notes',
                    xtype: 'textfield',
                    fieldLabel: 'Notes',
                    width: 250,
                    allowBlank: false,
                    value: balance > 0 ? 'Deposit amount to reconcile account' : 'Withdrawal amount to reconcile account'
                }
            }, {
                bodyStyle: 'padding:5px',
                layout: 'form',
                items: {
                    name: 'balance_amount',
                    xtype: 'textfield',
                    fieldLabel: 'Amount',
                    style: 'color: gray;',
                    readOnly: true,
                    width: 100,
                    value: formatMoney(currency, Math.abs(balance), true)
                }
            }
        ]
    });


    var wnd = new Ext.Window({
        title: 'Reconciliation Warning',
        plain: false,
        bodyStyle: 'padding:5px; background-color:#fff;',
        buttonAlign: 'center',
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [
            {
                xtype: 'label',
                style: labelStyle,
                html: label
            }, formPanel, {
                xtype: 'label',
                style: labelStyle,
                html: 'To review your accounts, click Download Draft.<br/>' +
                    'To exit Reconciliation, click Cancel.'
            }
        ],

        buttons: [
            {
                text: 'Proceed with Reconciliation',
                width: 160,
                handler: function () {
                    var form = formPanel.getForm();
                    var balance_date = Ext.util.Format.date(form.findField('balance_date').getValue(), 'Y-m-d');
                    var balance_notes = form.findField('balance_notes').getValue();
                    createReconciliation(ta_id, rr_months[ta_id], reconcile_type, 0, balance_date, balance_notes);

                    wnd.close();
                }
            }, {
                text: 'Download Draft',
                width: 100,
                handler: function () {
                    var form = formPanel.getForm();
                    if (form.isValid()) {
                        var balance_date = Ext.util.Format.date(form.findField('balance_date').getValue(), 'Y-m-d');
                        var balance_notes = form.findField('balance_notes').getValue();
                        createReconciliation(ta_id, rr_months[ta_id], reconcile_type, 1, balance_date, balance_notes);
                    }
                }
            }, {
                text: 'Cancel',
                handler: function () {
                    wnd.close();
                }
            }
        ]
    });
    wnd.show();
}

function showReconcile(ta_id, reconcile_type) {
    Ext.getBody().mask('Loading...');

    Ext.Ajax.request({
        url: baseUrl + '/trust-account/index/get-reconcile',
        params: {
            company_ta_id: ta_id,
            reconcile_type: reconcile_type
        },
        success: function (f) {
            var resultData = Ext.decode(f.responseText);

            if (!resultData.success) {
                Ext.Msg.alert('Status', resultData.msg);
                Ext.getBody().unmask();
                return;
            }

            if (resultData.month.length === 0) {
                Ext.Msg.alert('Status', 'Your ' + arrApplicantsSettings.ta_label + ' requires to have at least one month of history before you can reconcile');
                Ext.getBody().unmask();
                return;
            }

            var ta_reconcile_label = new Ext.form.Label({
                text: resultData.last_reconcile ? 'This ' + arrApplicantsSettings.ta_label + ' was reconciled for end of ' + resultData.last_reconcile + '.' : 'This ' + arrApplicantsSettings.ta_label + ' was not reconciled.',
                cls: 'x-form-item'
            });

            var months = new Ext.form.ComboBox({
                store: new Ext.data.Store({
                    data: resultData.month,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                        {name: 'id'},
                        {name: 'label'}
                    ]))
                }),
                mode: 'local',
                displayField: 'label',
                valueField: 'id',
                triggerAction: 'all',
                selectOnFocus: true,
                editable: false,
                value: (rr_months[ta_id] ? rr_months[ta_id] : 'Select month...'),
                fieldLabel: 'Reconcile until end of',
                labelStyle: 'padding-top: 10px; width: 150px',
                width: 180
            });

            months.on('select', function (obj, record) {
                rr_months[ta_id] = record.data.id;
            });

            var saveBtn = new Ext.Button({
                text: 'Reconcile',
                cls: 'orange-btn',
                handler: function () {
                    //validate
                    if (getComboBoxIndex(months) == -1) {
                        months.markInvalid('Please select month');
                        return;
                    }

                    rr_months[ta_id] = months.getValue();
                    proceedReconcile(ta_id, reconcile_type);
                    win.close();
                }
            });

            var closeBtn = new Ext.Button({
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            });

            var win = new Ext.Window({
                title: 'Reconciliation Report',
                modal: true,
                autoHeight: true,
                autoWidth: true,
                resizable: false,
                layout: 'form',
                tools: [
                    {
                        id: 'help',
                        qtip: _('View the related help topics.'),
                        hidden: !allowedPages.has('help'),
                        handler: function (event, toolEl) {
                            showHelpContextMenu(toolEl, 'trust-account-reconciliation-report');
                        }
                    }
                ],

                items: new Ext.FormPanel({
                    labelWidth: 150,
                    style: 'background-color:#fff; padding:5px;',
                    items: [ta_reconcile_label, months]
                }),
                buttons: [closeBtn, saveBtn]
            });

            //show window
            win.show();
            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.getBody().unmask();
            Ext.Msg.alert('Status', 'Can\'t load data');
        }
    });
}

function initTrustAC(taId) {
    var cpan = Ext.getCmp('ta-tab-panel');
    if (!empty(cpan)) {
        cpan.destroy();
    }

    //if no tab panel loaded
    if (arrTATabs !== undefined && arrTATabs.length > 0) {
        var mainAccountingGrid = new ClientsAccountingGrid({
            id: 'ta-tab-panel-grid',
            hash: '#trustac',
            style: 'padding: 9px 10px 0 10px',
            title: arrApplicantsSettings.ta_label,
            width: $('#divTrustAccountTab').width(), // This is needed because of the incorrect calculation in the browser
            height: initPanelSize() - 3,

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 0);

                    // Automatically open the first T/A if only 1 T/A is in the company (or the user has access to)
                    if (arrTATabs.length === 1) {
                        mainAccountingGrid.openAccountingTab(arrTATabs[0]['tabId']);
                    }
                }
            }
        });

        var menuTabId = Ext.id();

        cpan = new Ext.TabPanel({
            id: 'ta-tab-panel',
            renderTo: 'divTrustAccountTab',
            autoWidth: true,
            plain: true,
            activeTab: 1,
            enableTabScroll: true,
            minTabWidth: 200,
            cls: 'clients-tab-panel',
            plugins: [
                new Ext.ux.TabUniquesMenuSimple({
                    booAllowTabClosing: true,
                    defaultEmptyText: String.format(
                        _('No open {0}s'),
                        arrApplicantsSettings.ta_label
                    )
                })
            ],

            items: [
                {
                    id: menuTabId,
                    text: '&nbsp;',
                    iconCls: 'main-navigation-icon',
                    listeners: {
                        'render': function (oThisTab) {
                            var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                                navigationTab: Ext.get(oThisTab.tabEl)
                            });

                            oMenuTab.initNavigationTab(oMenuTab);
                        }
                    }
                },
                mainAccountingGrid
            ],

            listeners: {
                'afterrender': function () {
                    Ext.getCmp(menuTabId).fireEvent('render', Ext.getCmp(menuTabId));

                    if (!empty(taId)) {
                        mainAccountingGrid.openAccountingTab('ta_tab_' + taId);
                    }
                },

                'beforetabchange': function (oTabPanel, newTab) {
                    if (newTab.id === menuTabId) {
                        return false;
                    }
                }
            }
        });
    } else {
        // Show message that there is no created TA for this company

        cpan = new Ext.TabPanel({
            id: 'ta-tab-panel',
            autoWidth: true,
            plain: true,
            activeTab: 0,
            enableTabScroll: true,
            minTabWidth: 200,
            cls: 'clients-tab-panel',
            plugins: [
                new Ext.ux.TabUniquesNavigationMenu({})
            ],

            items: {
                bodyStyle: 'padding: 10px',
                title: arrApplicantsSettings.ta_label,
                height: initPanelSize() - 200,
                html: Ext.getDom('divNoTrustAccounts').innerHTML
            }
        });

        cpan.render('divTrustAccountTab');
        cpan.doLayout();
    }
}

function switchToTAHome() {
    var tabPanel = Ext.getCmp('ta-tab-panel');
    if (tabPanel) {
        tabPanel.setActiveTab(tabPanel.items.length === 1 ? 0 : 1);
    }
}
