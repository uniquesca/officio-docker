var TrustAccountSummaryDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;
    var thisGridId = Ext.id();

    // this could be inline, but we want to define the Transaction record
    // type so we can add records dynamically
    var TransactionRecord = Ext.data.Record.create([
        {name: 'id', type: 'string'},
        {name: 'real_id', type: 'int'},
        {name: 'date', type: 'date', dateFormat: dateFormatFull},
        {name: 'description', type: 'string'},
        {name: 'receipt_number', type: 'string'},
        {name: 'deposit', type: 'float'},
        {name: 'withdrawal', type: 'float'},
        {name: 'trust_account_id', type: 'int'},
        {name: 'status', type: 'string'},
        {name: 'type', type: 'string'},
        {name: 'can_edit_client', type: 'boolean'}
    ]);

    var store = new Ext.data.Store({
        // load using HTTP
        url: baseUrl + '/clients/accounting/get-client-summary-list',
        remoteSort: false,
        autoLoad: true,
        baseParams: {
            member_id: thisWindow.caseId,
            ta_id: thisWindow.taId,
            start: 0,
            limit: arrApplicantsSettings.accounting.taRecordsOnPage
        },

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, TransactionRecord),

        listeners: {
            load: function (thisStore) {
                if (thisStore.getTotalCount() > arrApplicantsSettings.accounting.taRecordsOnPage) {
                    pagingBar.show();
                }

                $('#' + thisGridId + ' .x-grid3-header').toggle(!empty(thisStore.getTotalCount()));
            }
        }
    });
    store.on('load', this.updateTotals.createDelegate(this));

    var cm = new Ext.grid.ColumnModel({
        defaults: {
            menuDisabled: true
        },

        columns: [
            {
                header: 'Date',
                dataIndex: 'date',
                width: 120,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull)
            },
            {
                header: 'Description',
                dataIndex: 'description',
                width: 200,
                align: 'left'
            },
            {
                header: 'Receipt Number',
                dataIndex: 'receipt_number',
                align: 'right',
                width: 150
            },
            {
                header: 'Deposit',
                dataIndex: 'deposit',
                align: 'right',
                width: 115,
                renderer: function (val) {
                    return formatMoney(thisWindow.caseTACurrency, val, false);
                }
            },
            {
                header: 'Withdrawal',
                dataIndex: 'withdrawal',
                align: 'right',
                width: 130,
                renderer: function (val) {
                    return formatMoney(thisWindow.caseTACurrency, val, false);
                }
            },
            {
                dataIndex: 'status',
                align: 'left',
                width: 250,
                sortable: false,
                header: 'Comments',
                renderer: function (val, i, rec) {
                    if (rec.data['type'] === 'deposit') {
                        if (rec.data['can_edit_client']) {
                            val = String.format(
                                "<a href='#' class='show_deposit_details' onclick='return false' title='{0}'>{1}</a>",
                                val.replaceAll("'", "′"),
                                val
                            );
                        }

                        if (empty(rec.data['trust_account_id'])) {
                            // Not Cleared Deposit
                            val += String.format(
                                '<br><a href="#" class="assign_deposit" style="color: red" onclick="return false" >{0}</a>',
                                _('Verify')
                            );
                        }
                    } else {
                        val = String.format(
                            "<div ext:qtip='{0}'>{1}</div>",
                            Ext.util.Format.stripTagsLeaveBr(val).replaceAll("'", "&#39;"),
                            val
                        );
                    }

                    return val;
                }
            }
        ],
        defaultSortable: true
    });

    var trustSummaryTBar = new Ext.Toolbar({
        style: 'margin-bottom: 10px',
        items: [
            {
                xtype: 'label',
                cls: 'accounting_label',
                ctCls: 'x-toolbar-cell-no-right-padding',
                html: arrApplicantsSettings.ta_label + ' ' + _('Summary')
            }, {
                xtype: 'box',

                'autoEl': {
                    'tag': 'a',
                    'href': '#',
                    'class': 'blulinkunb', // Thanks to IE - we need to use quotes...
                    'html': '(' + thisWindow.taFullName + ')'
                },

                listeners: {
                    scope: this,
                    render: function (c) {
                        c.getEl().on('click', function (e) {
                            setUrlHash('#trustac/' + thisWindow.taId);
                            setActivePage();

                            thisWindow.close();
                        }, this, {stopEvent: true});
                    }
                }
            }, '->', {
                text: '<i class="las la-redo-alt"></i>',
                handler: function () {
                    thisWindow.reloadTASummary();
                }
            }
        ]
    });

    var pagingBar = new Ext.PagingToolbar({
        hidden: true,
        pageSize: arrApplicantsSettings.accounting.taRecordsOnPage,
        store: store
    });

    this.totalPanel = new Ext.Panel();

    this.summaryGrid = new Ext.grid.GridPanel({
        id: thisGridId,
        border: false,
        loadMask: {msg: 'Loading...'},
        cm: cm,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        store: store,
        height: 465,
        stripeRows: true,
        cls: 'extjs-grid',
        viewConfig: {
            deferEmptyText: 'No entries found.',
            emptyText: 'No entries found.'
        },
        tbar: (is_client ? false : trustSummaryTBar),
        bbar: [
            pagingBar,
            this.totalPanel
        ]
    });
    this.summaryGrid.on('cellclick', this.onCellClick, this);

    TrustAccountSummaryDialog.superclass.constructor.call(this, {
        id: 'accounting_ta_summary_dialog_' + thisWindow.caseId + '_' + thisWindow.taId,
        title: arrApplicantsSettings.ta_label + ' ' + _('Summary'),
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [this.summaryGrid],

        buttons: [
            {
                text: _('Add Deposit Reminder'),
                hidden: !thisWindow.booCanEditClient,
                cls: 'secondary-btn',
                handler: function () {
                    thisWindow.showDepositDetails(0, thisWindow.caseId, thisWindow.taId);
                }
            }, {
                text: _('Close'),
                cls: 'orange-btn',
                handler: function () {
                    thisWindow.close();
                }
            }
        ],
    });
};

Ext.extend(TrustAccountSummaryDialog, Ext.Window, {
    reloadTASummary: function () {
        this.summaryGrid.getStore().load();
    },

    updateTotals: function (store) {
        var thisWindow = this;
        thisWindow.totalPanel.removeAll();

        thisWindow.totalPanel.add({
            xtype: 'container',
            cls: 'summary-info',
            layout: 'table',
            layoutConfig: {
                tableAttrs: {
                    style: {
                        width: '100%'
                    }
                },
                columns: 4
            },

            items: [
                {
                    width: thisWindow.getWidth() / 2,
                    html: String.format(
                        _('Total Payments received in {0}: {1}'),
                        thisWindow.taFullName,
                        formatMoney(thisWindow.caseTACurrency, thisWindow.totalPaymentsReceived, true, false)
                    )
                }, {
                    html: _('Available Total: ') + formatMoney(thisWindow.caseTACurrency, store.reader.jsonData.available_total, true, false)
                }, {
                    style: 'margin: 0 10px',
                    html: '&nbsp;'
                }, {
                    html: _('Deposits Not Verified: ') + formatMoney(thisWindow.caseTACurrency, store.reader.jsonData.deposits_not_verified, true, false)
                }
            ]
        });

        thisWindow.totalPanel.doLayout();
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var thisWindow = this;

        var record = thisWindow.summaryGrid.getStore().getAt(rowIndex);
        if ($(e.getTarget()).hasClass('show_deposit_details')) {
            thisWindow.showDepositDetails(record.data['real_id'], thisWindow.caseId, thisWindow.taId);
        } else if ($(e.getTarget()).hasClass('delete_deposit')) {
            thisWindow.doDepositDelete(record.data['real_id'], thisWindow, false);
        } else if ($(e.getTarget()).hasClass('assign_deposit')) {
            thisWindow.showAssignDepositDialog(record.data['real_id'], thisWindow, false);
        }
    },

    showDepositDetails: function (deposit_id, member_id, ta_id) {
        var thisWindow = this;

        var showAddEditDepositWindow = function (member_id, ta_id, arrDepositInfo) {

            var wndTitle;
            if (empty(arrDepositInfo.deposit_id)) {
                wndTitle = _('Add Deposit');
            } else if (!arrDepositInfo.status_cleared) {
                wndTitle = _('Edit Deposit');
            } else {
                wndTitle = _('Deposit details');
            }

            var deposit_details;
            if (arrDepositInfo.status_cleared || is_client) {
                deposit_details = new Ext.form.DisplayField({
                    fieldLabel: 'Deposit Details',
                    hidden: true,
                    value: arrDepositInfo.deposit_details
                });
            } else {
                deposit_details = new Ext.form.TextField({
                    fieldLabel: 'Deposit Details',
                    hidden: true,
                    value: arrDepositInfo.deposit_details,
                    maxLength: 255,
                    width: 250
                });
            }

            var deposit_amount;
            if (arrDepositInfo.status_cleared || is_client) {
                deposit_amount = new Ext.form.DisplayField({
                    fieldLabel: 'Deposit Amount (' + getCurrencySymbolByTAId(ta_id, false) + ')',
                    labelStyle: 'width: 165px; padding: 0',
                    value: arrDepositInfo.deposit_amount
                });
            } else {
                deposit_amount = new Ext.form.NumberField({
                    fieldLabel: 'Deposit Amount (' + getCurrencySymbolByTAId(ta_id, false) + ')',
                    maxLength: 16,
                    minValue: 0.01,
                    width: 250,
                    allowBlank: false,
                    allowNegative: false
                });

                if (!empty(arrDepositInfo.deposit_id)) {
                    deposit_amount.setValue(arrDepositInfo.deposit_amount);
                }
            }

            var deposit_created_by = new Ext.form.DisplayField({
                fieldLabel: 'Created by',
                value: arrDepositInfo.deposit_created_by,
                hidden: empty(arrDepositInfo.deposit_created_by)
            });

            var ta_receipt_sent_by = new Ext.form.DisplayField({
                fieldLabel: 'Receipt of payment sent by',
                value: arrDepositInfo.ta_receipt_sent_by
            });

            var ta_date_from_bank = new Ext.form.DisplayField({
                fieldLabel: 'Date from Bank',
                labelStyle: 'width: 110px; padding: 0',
                style: 'padding: 0',
                value: arrDepositInfo.ta_date_from_bank
            });

            var ta_description = new Ext.form.DisplayField({
                fieldLabel: 'Description',
                value: arrDepositInfo.ta_description
            });

            var ta_assigned_by = new Ext.form.DisplayField({
                fieldLabel: 'Assigned by',
                value: arrDepositInfo.ta_assigned_by
            });

            var ta_assigned_on = new Ext.form.DisplayField({
                fieldLabel: 'Assigned on',
                value: arrDepositInfo.ta_assigned_on
            });

            var ta_fieldset = new Ext.form.FieldSet({
                style: 'padding:5px; margin-top: 40px;',
                cls: 'no-borders-fieldset',
                title: 'Details from ' + arrApplicantsSettings.ta_label,
                autoHeight: true,
                hidden: !arrDepositInfo.status_cleared,
                labelWidth: 110,
                items: [ta_date_from_bank, ta_description, ta_assigned_by, ta_assigned_on]
            });

            var deposit_notes;
            if (is_client) {
                deposit_notes = new Ext.form.DisplayField({
                    fieldLabel: 'Notes',
                    value: arrDepositInfo.deposit_notes
                });
            } else {
                deposit_notes = new Ext.form.TextField({
                    fieldLabel: 'Notes',
                    labelWidth: 110,
                    maxLength: 255,
                    width: 250,
                    value: arrDepositInfo.deposit_notes
                });
            }

            var depositDetailsForm = new Ext.FormPanel({
                bodyStyle: 'padding:5px',
                labelWidth: 170,

                items: [
                    deposit_details,
                    deposit_amount,
                    deposit_created_by,
                    ta_fieldset,
                    {html: '<p>&nbsp;</p>'}, //Spacer
                    deposit_notes
                ]
            });

            var deleteBtn = new Ext.Button({
                text: 'Delete',
                hidden: arrDepositInfo.status_cleared || empty(arrDepositInfo.deposit_id),
                handler: function () {
                    thisWindow.doDepositDelete(arrDepositInfo.deposit_id, win, true);
                }
            });

            var saveBtn = new Ext.Button({
                text: 'Save',
                cls: 'orange-btn',
                hidden: is_client,
                handler: function () {
                    if (arrDepositInfo.status_cleared) {
                        // Only Notes can be updated for cleared deposits
                        updateNotes(win, member_id, ta_id, 'deposit', deposit_id, deposit_notes.getValue());
                    } else {
                        if (depositDetailsForm.getForm().isValid()) {
                            // Update deposit details
                            win.getEl().mask('Saving...');

                            Ext.Ajax.request({
                                url: baseUrl + '/clients/accounting/update-deposit',
                                params: {
                                    deposit_id: arrDepositInfo.deposit_id,
                                    member_id: member_id,
                                    ta_id: ta_id,

                                    deposit_details: Ext.encode(deposit_details.getValue()),
                                    deposit_amount: Ext.encode(deposit_amount.getValue()),
                                    deposit_notes: Ext.encode(deposit_notes.getValue())
                                },
                                success: function (result) {
                                    var resultData = Ext.decode(result.responseText);
                                    if (resultData.success) {
                                        win.getEl().mask('Done!');

                                        refreshAccountingTabByTA(member_id, ta_id);

                                        setTimeout(function () {
                                            win.getEl().unmask();
                                            win.close();
                                        }, 750);
                                    } else {
                                        win.getEl().unmask();
                                        Ext.simpleConfirmation.error(resultData.message);
                                    }
                                },

                                failure: function () {
                                    win.getEl().unmask();
                                    Ext.simpleConfirmation.error('Can\'t save info');
                                }
                            });
                        }
                    }
                }
            });

            var cancelBtn = new Ext.Button({
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            });

            var win = new Ext.Window({
                title: wndTitle,
                modal: true,
                autoHeight: true,
                autoWidth: true,
                resizable: false,
                items: depositDetailsForm,
                buttons: [cancelBtn, deleteBtn, saveBtn]
            });

            win.show();
            win.center();
        };

        if (empty(deposit_id)) {
            // Set default values for Add Deposit dialog
            var arrDepositInfo = {
                deposit_id: 0,
                deposit_details: '',
                deposit_amount: 0,
                deposit_created_by: '',

                status_cleared: false,
                ta_receipt_sent_by: '',
                ta_date_from_bank: '',
                ta_description: '',
                ta_assigned_by: '',
                ta_assigned_on: '',

                deposit_notes: ''
            };

            showAddEditDepositWindow(member_id, ta_id, arrDepositInfo);
        } else {
            // Load deposit info
            thisWindow.getEl().mask('Loading...');
            Ext.Ajax.request({
                url: baseUrl + '/clients/accounting/get-deposit-details',
                params: {
                    deposit_id: deposit_id,
                    member_id: member_id,
                    ta_id: ta_id
                },
                success: function (result) {
                    try {
                        var resultData = Ext.decode(result.responseText);

                        if (resultData.success) {
                            thisWindow.getEl().unmask();

                            var arrReceivedInfo = resultData.arrDepositInfo;
                            var arrDepositInfo = {
                                deposit_id: arrReceivedInfo.deposit_id,
                                deposit_details: arrReceivedInfo.deposit_description,
                                deposit_amount: arrReceivedInfo.amount,
                                deposit_created_by: arrReceivedInfo.created_by,

                                status_cleared: arrReceivedInfo.status_cleared,
                                ta_receipt_sent_by: arrReceivedInfo.author,
                                ta_date_from_bank: arrReceivedInfo.date_from_bank,
                                ta_description: arrReceivedInfo.description,
                                ta_assigned_by: arrReceivedInfo.assigned_by,
                                ta_assigned_on: arrReceivedInfo.assigned_on,

                                deposit_notes: arrReceivedInfo.notes
                            };

                            showAddEditDepositWindow(member_id, ta_id, arrDepositInfo);
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    } catch (e) {
                        thisWindow.getEl().unmask();
                        Ext.simpleConfirmation.error('Can\'t load deposit\'s info');
                    }

                    thisWindow.getEl().unmask();
                },

                failure: function () {
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error('Can\'t load deposit\'s info. Please try again later.');
                }
            });
        }
    },

    doDepositDelete: function (depositId, win, booClose) {
        var self = this;

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this deposit?', function (btn, text) {
            if (btn == 'yes') {
                win.getEl().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/delete-deposit',
                    params: {
                        deposit_id: depositId,
                        member_id: self.caseId,
                        ta_id: self.taId
                    },
                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            win.getEl().mask('Done!');

                            refreshAccountingTabByTA(self.caseId, self.taId);

                            setTimeout(function () {
                                win.getEl().unmask();

                                if (booClose) {
                                    win.close();
                                }
                            }, 750);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function () {
                        win.getEl().unmask();
                        Ext.simpleConfirmation.error('Can\'t delete this deposit');
                    }
                });
            }
        });
    },

    showAssignDepositDialog: function (deposit_id) {
        var wnd = new AssignDepositDialog({
            deposit_id: deposit_id,
            member_id: this.caseId,
            ta_id: this.taId,
            taFullName: this.taFullName
        }, this);

        wnd.loadData();
    }
});