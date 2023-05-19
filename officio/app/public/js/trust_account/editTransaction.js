function editNote(transactionId, companyTAId) {
    var selRecord = Ext.getCmp('editor-grid' + companyTAId).store.getById(transactionId);
    var selNotes = empty(selRecord) ? '' : selRecord.data.notes;


    var notes = new Ext.form.TextField({
        fieldLabel: 'Notes',
        width: 265,
        value: selNotes
    });

    var win = new Ext.Window({
        title: 'Edit Notes',
        modal: true,
        width: 350,
        autoHeight: true,
        resizable: false,
        items: new Ext.FormPanel({
            bodyStyle: 'padding:5px;',
            labelWidth: 50,
            layout: 'form',
            items: notes
        }),
        buttons: [
            {
                text: 'Close',
                handler: function () {
                    win.close();
                }
            },
            {
                text: 'Save',
                cls: 'orange-btn',
                handler: function () {
                    win.getEl().mask('Saving...');

                    Ext.Ajax.request({
                        url: baseUrl + "/trust-account/edit/edit",
                        params: {
                            trust_account_id: transactionId,
                            notes: Ext.encode(notes.getValue())
                        },
                        success: function (result, request) {
                            win.getEl().mask('Done!');

                            Ext.getCmp('editor-grid' + companyTAId).store.reload();

                            setTimeout(function () {
                                win.getEl().unmask();
                                win.close();
                            }, 750);
                        },

                        failure: function (form, action) {
                            Ext.simpleConfirmation.error('Can\'t edit transaction');
                        }
                    });
                }
            }
        ]
    });

    win.show();
}

var showEditTransaction = function (tid, booCanUnassign, ta_id) {
    var destinationType;

    var destination_account_store = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/default/destination-account/get-destination-account-list',
            method: 'post'
        }),
        autoLoad: true,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            id: 'transactionId'
        }, [{name: 'transactionId'}, {name: 'transactionName'}])
    });

    var editTransactionTable = new Ext.FormPanel({
        id: 'edit-transaction-form',
        layout: 'table',
        defaults: {
            // applied to each contained panel
            bodyStyle: 'padding: 5px;'
        },
        layoutConfig: {columns: 2},
        items: [
            {
                html: '<p>Date from Bank:</p>'
            },
            {
                id: 'etf-date-from-bank',
                xtype: 'label',
                text: ''
            },

            {
                html: '<p>Description:</p>'
            },
            {
                id: 'etf-description',
                xtype: 'label',
                text: ''
            },

            {
                html: '<p>Notes:</p>'
            },
            new Ext.form.TextField({
                id: 'etf-notes',
                width: 230,
                maxLength: 1024
            }),

            {
                id: 'etf-deposit-title',
                html: '<p>Deposit:</p>',
                hidden: true
            },
            {
                id: 'etf-deposit',
                xtype: 'label',
                hidden: true,
                text: ''
            },

            {
                id: 'etf-withdrawal-title',
                html: '<p>Withdrawal:</p>',
                hidden: true
            },
            {
                id: 'etf-withdrawal',
                xtype: 'label',
                hidden: true,
                text: ''
            },

            {
                html: '<p>Balance:</p>'
            },
            {
                id: 'etf-balance',
                xtype: 'label',
                text: ''
            },

            {
                id: 'etf-assigned-to-title',
                html: '<p>Assigned To:</p>'
            },
            {
                id: 'etf-assigned-to',
                html: '<div id="etf-assigned-to"></div>'
            }
            ,

            {
                id: 'etf-destination-title',
                hidden: true,
                html: '<p>Destination account:</p>'
            },
            {
                id: 'etf-destination',
                xtype: 'combo',
                hidden: true,
                fieldLabel: '',
                hideLabel: true,
                displayField: 'transactionName',
                valueField: 'transactionId',
                typeAhead: true,
                forceSelection: true,
                triggerAction: 'all',
                selectOnFocus: true,
                mode: 'local',
                store: destination_account_store,
                listeners: {
                    beforeselect: function (n, selRecord) {
                        if (empty(selRecord.data.transactionId)) {
                            Ext.getCmp('etf-destination-custom').show();
                        } else {
                            Ext.getCmp('etf-destination-custom').hide();
                        }
                    }
                }
            },

            {
                id: 'etf-destination-custom-title',
                hidden: true,
                html: '<p>Custom destination:</p>'
            },
            {
                id: 'etf-destination-custom',
                xtype: 'textfield',
                width: 160,
                hidden: true
            },

            {
                id: 'etf-assigned-by-title',
                html: '<p>Assigned By:</p>'
            },
            {
                id: 'etf-assigned-by',
                xtype: 'label',
                text: ''
            },

            {
                id: 'etf-assigned-on-title',
                html: '<p>Assigned On:</p>'
            },
            {
                id: 'etf-assigned-on',
                xtype: 'label',
                text: ''
            }
        ]
    });

    var win = new Ext.Window({
        id: 'edit-transaction-window',
        title: arrApplicantsSettings.ta_label + ': Edit Transaction',
        modal: true,
        width: 500,
        autoHeight: true,
        resizable: false,
        items: editTransactionTable,
        buttons: [
            {
                text: _('Unassign this transaction'),
                style: 'margin-right: 40px',
                hidden: !booCanUnassign,
                handler: function () {
                    Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to unassign this transaction?'), function (btn, text) {
                        if (btn == 'yes') {
                            win.getEl().mask(_('Please wait...'));

                            Ext.Ajax.request({
                                url: baseUrl + "/trust-account/assign/unassign",
                                params: {
                                    trust_account_id: tid
                                },

                                success: function (result, request) {
                                    var resultDecoded = Ext.decode(result.responseText);
                                    if (resultDecoded.success) {
                                        Ext.getCmp('editor-grid' + ta_id).store.reload();
                                        var resultData = Ext.decode(result.responseText);
                                        var unassign_members = resultData.unassign_members;
                                        for (var $ii = 0; $ii < unassign_members.length; $ii++) {
                                            refreshAccountingTabByTA(unassign_members[$ii], ta_id);
                                        }

                                        win.getEl().mask(_('Transaction unassigned!'));
                                        setTimeout(function () {
                                            win.close();
                                        }, 750);
                                    } else {
                                        win.getEl().unmask();
                                        Ext.simpleConfirmation.error(resultDecoded.message);
                                    }
                                },

                                failure: function (form, action) {
                                    win.getEl().unmask();
                                    Ext.simpleConfirmation.error('Can\'t unassign transaction');
                                }
                            });
                        }
                    });
                }
            }, {
                text: 'Close',
                handler: function () {
                    win.close();
                }
            }, {
                text: 'Save',
                cls: 'orange-btn',
                handler: function () {
                    win.getEl().mask('Saving...');

                    var destination_account;
                    var destination;
                    var notes = Ext.getCmp('etf-notes').getValue();
                    if (destinationType == 'withdrawal') {
                        destination_account = Ext.getCmp('etf-destination').getValue();
                        if (destination_account == 'Other') {
                            destination = Ext.getCmp('etf-destination-custom').getValue();
                            if (empty(destination)) {
                                editTransactionTable.getForm().isValid();
                                Ext.getCmp('etf-destination-custom').markInvalid('This field is required');
                                Ext.simpleConfirmation.warning('Please type destination other name');
                                win.getEl().unmask();
                                return false;
                            }
                        }
                    }

                    Ext.Ajax.request({
                        url: baseUrl + "/trust-account/edit/edit",
                        params: {
                            trust_account_id: tid,
                            notes: Ext.encode(notes),
                            destination_account_id: destination_account,
                            destination_account_custom: Ext.encode(destination)
                        },
                        success: function (result, request) {
                            win.getEl().mask('Done!');

                            Ext.getCmp('editor-grid' + ta_id).store.reload();

                            setTimeout(function () {
                                win.getEl().unmask();
                            }, 750);
                        },

                        failure: function (form, action) {
                            Ext.simpleConfirmation.error('Can\'t saved transaction! Please try again later.');
                        }
                    });
                    return true;
                }
            }
        ],

        listeners: {
            show: function () {
                // Load transaction info when window will be showed
                setEditTransactionDefaultValues(this, tid);
            }
        }

    });

    function setEditTransactionDefaultValues(win, trust_account_id) {
        win.getEl().mask('Loading...');

        Ext.Ajax.request({
            url: baseUrl + "/trust-account/edit/get",
            params:
                {
                    trust_account_id: trust_account_id
                },
            success: function (result, request) {
                try {
                    var resultData = Ext.decode(result.responseText);
                    var ta = resultData.transaction;

                    Ext.getCmp('etf-date-from-bank').setText(ta.date_from_bank);
                    Ext.getCmp('etf-description').setText(ta.description);
                    Ext.getCmp('etf-notes').setValue(ta.notes);
                    if (!empty(ta.deposit)) {
                        Ext.getCmp('etf-deposit').setText(Ext.util.Format.usMoney(ta.deposit));
                        Ext.getCmp('etf-deposit').setVisible(true);
                        Ext.getCmp('etf-deposit-title').setVisible(true);
                    }
                    if (!empty(ta.withdrawal)) {
                        Ext.getCmp('etf-withdrawal').setText(Ext.util.Format.usMoney(ta.withdrawal));
                        Ext.getCmp('etf-withdrawal').setVisible(true);
                        Ext.getCmp('etf-withdrawal-title').setVisible(true);
                    }
                    //balance after transaction
                    Ext.getCmp('etf-balance').setText(Ext.util.Format.usMoney(Number(ta.balance) - Number(ta.withdrawal) + Number(ta.deposit)));

                    $('#etf-assigned-to').html(ta.assigned.to);
                    Ext.getCmp('etf-assigned-by').setText(ta.assigned.by);
                    Ext.getCmp('etf-assigned-on').setText(ta.assigned.on);

                    destinationType = ta.assigned.type;

                    if (ta.assigned.type == 'withdrawal') {
                        Ext.getCmp('etf-destination-title').setVisible(true);
                        Ext.getCmp('etf-destination').setVisible(true);

                        if (empty(ta.assigned.destination_account_id)) //Other
                        {
                            Ext.getCmp('etf-destination-custom-title').setVisible(true);
                            Ext.getCmp('etf-destination-custom').setVisible(true);
                            Ext.getCmp('etf-destination').setValue('Other');
                            Ext.getCmp('etf-destination-custom').setValue(ta.assigned.destination);
                        } else //From withdrawal types
                        if (ta.assigned.destination_account_id > 0) {
                            Ext.getCmp('etf-destination').setValue(ta.assigned.destination_account_id);
                            Ext.getCmp('etf-destination').setRawValue('');
                            Ext.getCmp('etf-destination').store.on("load", function () {
                                Ext.getCmp('etf-destination').setRawValue(Ext.getCmp('etf-destination').store.getById(ta.assigned.destination_account_id).data.transactionName);
                            });
                        } else //---
                        {
                            Ext.getCmp('etf-destination').setValue('---');
                        }
                    }

                    win.getEl().unmask();
                } catch (e) {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error('Can\'t load transaction\'s info');
                }
            },

            failure: function (form, action) {
                Ext.simpleConfirmation.error('Can\'t load transaction\'s info. Please try again later.');
                win.getEl().unmask();
            }
        });
    }

    win.show();
};