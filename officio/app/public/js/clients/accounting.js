var refreshAccountingTabByTA = function (member_id, ta_id) {
    var panel = Ext.getCmp('accounting_invoices_panel_' + member_id);
    if (panel) {
        panel.reloadClientAccounting();
    }
};

// Refresh FT grid and its Balance
function refreshFT(member_id, ta_id) {
    var aTab = Ext.getCmp('financial-transactions-grid-' + member_id + '-' + ta_id);
    if (aTab) {
        // Refresh the grid
        aTab.store.reload();
    }
}

var getCurrencyByTAId = function (ta_id) {
    for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
        if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] == ta_id) {
            return arrApplicantsSettings.accounting.arrCompanyTA[i][2];
        }
    }

    // Can't be here, but...
    return '';
};


var getCurrencySymbolByTAId = function (ta_id, booReverse) {
    for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
        if (booReverse) {
            if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] != ta_id && !empty(arrApplicantsSettings.accounting.arrCompanyTA[i][0])) {
                return arrApplicantsSettings.accounting.arrCompanyTA[i][3];
            }
        } else {
            if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] == ta_id) {
                return arrApplicantsSettings.accounting.arrCompanyTA[i][3];
            }
        }
    }

    // Can't be here, but...
    return '';
};

function fixParentTabHeight(caseId) {
    var accountingTab = Ext.getCmp('ctab-client-' + caseId + '-sub-tab-accounting');
    if (accountingTab) {
        var applicantTab = Ext.getCmp(accountingTab.panelType + '-tab-' + accountingTab.applicantId + '-' + caseId);
        if (applicantTab) {
            applicantTab.items.get(0).owner.fixParentPanelHeight();
        }
    }
}

function show_hide_gridpanel(member_id, grid_id, show) {
    bottom_bar = '#' + grid_id + ' .x-panel-bbar';
    grid_id = '#' + grid_id + ' .x-grid3-viewport';

    if (show) {
        $(grid_id).show();
        $(bottom_bar).show();
    } else {
        if ($(grid_id).is(':visible')) {
            $(grid_id).hide();
            $(bottom_bar).hide();
        } else {
            $(grid_id).show();
            $(bottom_bar).show();
        }
    }
    fixParentTabHeight(member_id);

    return true;
}

function showFTDetails(payment_id, ta_id, member_id) {
    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + '/clients/accounting/get-ft-details',
        params: {
            payment_id: payment_id
        },
        success: function (f) {
            var result = Ext.decode(f.responseText);

            var invoice_number = new Ext.form.DisplayField({
                fieldLabel: 'Invoice #',
                value: result.invoice_number,
                hidden: result.isFee
            });

            var date = new Ext.form.DisplayField({
                fieldLabel: 'Date',
                value: result.date_formatted
            });

            var payment_made_by = new Ext.form.DisplayField({
                fieldLabel: 'Payment&nbsp;made&nbsp;by',
                value: result.payment_made_by,
                hidden: result.isFee
            });

            var amount = new Ext.form.DisplayField({
                fieldLabel: 'Amount',
                value: result.amount
            });

            var gst = new Ext.form.DisplayField({
                fieldLabel: empty(result.gst_label) ? 'GST' : result.gst_label,
                value: result.gst_formatted,
                hidden: !result.isFee || empty(result.gst_formatted)
            });

            var description = new Ext.form.DisplayField({
                fieldLabel: 'Description',
                value: result.description
            });

            var notesField;
            if (is_client) {
                notesField = new Ext.form.DisplayField({
                    fieldLabel: 'Notes',
                    value: result.notes,
                    width: 320
                });
            } else {
                notesField = new Ext.form.TextField({
                    fieldLabel: 'Notes',
                    value: result.notes,
                    width: 320
                });
            }

            var pan = new Ext.FormPanel({
                autoHeight: true,
                bodyStyle: 'padding:5px',
                labelWidth: 100,
                items: [invoice_number, date, payment_made_by, amount, gst, description, notesField]
            });

            var win = new Ext.Window({
                title: 'Edit Fees Due notes',
                layout: 'fit',
                modal: true,
                width: 450,
                autoHeight: true,
                items: [pan],
                buttons: [
                    {
                        text: 'Cancel',
                        handler: function () {
                            win.close();
                        }
                    }, {
                        text: 'Save',
                        cls: 'orange-btn',
                        hidden: is_client,
                        handler: function () {
                            win.getEl().mask('Sending Information');
                            Ext.Ajax.request({
                                url: baseUrl + '/clients/accounting/update-notes',
                                params: {
                                    update_id: payment_id,
                                    update_notes: Ext.encode(notesField.getValue()),
                                    update_type: 'payment',
                                    member_id: member_id
                                },

                                success: function (result) {
                                    var resultData = Ext.decode(result.responseText);

                                    if (resultData.success) {
                                        // Show confirmation message
                                        win.getEl().mask('Done!');

                                        refreshFT(member_id, ta_id);

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
                                    Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                                    win.getEl().unmask();
                                }
                            });

                            return true;
                        }
                    }
                ]
            });

            Ext.getBody().unmask();
            win.show();
            win.center();
        },
        failure: function () {
            Ext.simpleConfirmation.error('Can\'t load templates');
            Ext.getBody().unmask();
        }
    });
}

var updateNotes = function (win, member_id, ta_id, update_type, update_id, update_notes) {
    win.getEl().mask('Saving...');

    Ext.Ajax.request({
        url: baseUrl + '/clients/accounting/update-notes',
        params: {
            update_type: update_type,
            update_id: update_id,
            update_notes: Ext.encode(update_notes),
            member_id: member_id
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
};

var showReturnedPaymentDetails = function (withdrawal_id, member_id, ta_id) {

    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + '/clients/accounting/get-assigned-withdrawal-details',
        params: {
            withdrawal_id: withdrawal_id,
            member_id: member_id,
            ta_id: ta_id
        },
        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            var withdrawalInfo = resultData.arrWithdrawalInfo;

            if (resultData.success) {

                var description = new Ext.form.DisplayField({
                    fieldLabel: 'Notes',
                    value: withdrawalInfo.description
                });

                var amount = new Ext.form.DisplayField({
                    fieldLabel: 'Amount',
                    value: formatMoney(withdrawalInfo.currency, withdrawalInfo.amount, true)
                });

                var date_from_bank = new Ext.form.DisplayField({
                    fieldLabel: 'Date from Bank',
                    labelStyle: 'width: 110px; padding: 0',
                    style: 'padding: 0',
                    value: withdrawalInfo.date_from_bank
                });

                var ta_description = new Ext.form.DisplayField({
                    fieldLabel: 'Description',
                    value: withdrawalInfo.ta_description
                });

                var assigned_by = new Ext.form.DisplayField({
                    fieldLabel: 'Assigned by',
                    value: withdrawalInfo.ta_assigned_by
                });

                var assigned_on = new Ext.form.DisplayField({
                    fieldLabel: 'Assigned on',
                    value: withdrawalInfo.ta_assigned_on
                });

                var notes;
                if (is_client) {
                    notes = new Ext.form.DisplayField({
                        fieldLabel: 'Notes',
                        value: withdrawalInfo.notes
                    });
                } else {
                    notes = new Ext.form.TextField({
                        fieldLabel: 'Notes',
                        width: 230,
                        maxLength: 1024,
                        value: withdrawalInfo.notes
                    });
                }

                var fieldset = new Ext.form.FieldSet({
                    width: 370,
                    style: 'padding:5px; margin-top: 40px;',
                    cls: 'no-borders-fieldset',
                    labelWidth: 110,
                    title: 'Details from ' + arrApplicantsSettings.ta_label,
                    collapsible: false,
                    autoHeight: true,
                    items: [date_from_bank, ta_description, assigned_by, assigned_on]
                });

                var pan = new Ext.FormPanel({
                    bodyStyle: 'padding:5px;',
                    labelWidth: 110,
                    items: [description, amount, fieldset, notes]
                });

                var win = new Ext.Window({
                    title: 'Detail Comments',
                    modal: true,
                    width: 395,
                    autoHeight: true,
                    resizable: false,
                    items: pan,
                    buttons: [
                        {
                            text: 'Close',
                            handler: function () {
                                win.close();
                            }
                        }, {
                            text: 'Save',
                            cls: 'orange-btn',
                            hidden: is_client,
                            handler: function () {
                                updateNotes(win, member_id, ta_id, 'withdrawal', withdrawal_id, notes.getValue());
                            }
                        }
                    ]
                });

                win.show();
                win.center();

            } else {
                // Show error message
                Ext.simpleConfirmation.error(resultData.message);
            }

            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error('Can\'t load withdrawal\'s info. Please try again later.');
        }
    });
}; // showReturnedPaymentDetails

var showWithdrawalDetails = function (withdrawal_id, member_id, ta_id) {

    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + '/clients/accounting/get-assigned-withdrawal-details',
        params: {
            withdrawal_id: withdrawal_id,
            member_id: member_id,
            ta_id: ta_id
        },
        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            var withdrawalInfo = resultData.arrWithdrawalInfo;

            if (resultData.success) {

                var description = new Ext.form.DisplayField({
                    fieldLabel: 'Notes',
                    value: withdrawalInfo.description
                });

                var amount = new Ext.form.DisplayField({
                    fieldLabel: 'Amount',
                    value: formatMoney(withdrawalInfo.currency, withdrawalInfo.amount, true)
                });

                var date_from_bank = new Ext.form.DisplayField({
                    fieldLabel: 'Date from Bank',
                    labelStyle: 'width: 110px; padding: 0',
                    style: 'padding: 0',
                    value: withdrawalInfo.date_from_bank
                });

                var ta_description = new Ext.form.DisplayField({
                    fieldLabel: 'Description',
                    value: withdrawalInfo.ta_description
                });

                var assigned_by = new Ext.form.DisplayField({
                    fieldLabel: 'Assigned by',
                    value: withdrawalInfo.ta_assigned_by
                });

                var assigned_on = new Ext.form.DisplayField({
                    fieldLabel: 'Assigned on',
                    value: withdrawalInfo.ta_assigned_on
                });

                var notes;
                if (is_client) {
                    notes = new Ext.form.DisplayField({
                        fieldLabel: 'Notes',
                        value: withdrawalInfo.notes
                    });
                } else {
                    notes = new Ext.form.TextField({
                        fieldLabel: 'Notes',
                        width: 230,
                        maxLength: 1024,
                        value: withdrawalInfo.notes
                    });
                }

                var fieldset = new Ext.form.FieldSet({
                    style: 'padding:5px; margin-top: 40px;',
                    cls: 'no-borders-fieldset',
                    width: 370,
                    labelWidth: 110,
                    title: 'Details from ' + arrApplicantsSettings.ta_label,
                    collapsible: false,
                    autoHeight: true,
                    items: [date_from_bank, ta_description, assigned_by, assigned_on]
                });

                var pan = new Ext.FormPanel({
                    labelWidth: 110,
                    bodyStyle: 'padding:5px;',
                    items: [
                        description,
                        amount,
                        fieldset,
                        {html: '<p>&nbsp;</p>'}, //Spacer
                        notes
                    ]
                });

                var win = new Ext.Window({
                    title: 'Withdrawal Details',
                    modal: true,
                    autoWidth: true,
                    autoHeight: true,
                    resizable: false,
                    items: pan,
                    buttons: [
                        {
                            text: 'Close',
                            handler: function () {
                                win.close();
                            }
                        }, {
                            text: 'Save',
                            cls: 'orange-btn',
                            hidden: is_client,
                            handler: function () {
                                updateNotes(win, member_id, ta_id, 'withdrawal', withdrawal_id, notes.getValue());
                            }
                        }
                    ]
                });

                win.show();
                win.center();

            } else {
                // Show error message
                Ext.simpleConfirmation.error(resultData.message);
            }

            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error('Can\'t load withdrawal\'s info. Please try again later.');
        }
    });
}; // showWithdrawalDetails