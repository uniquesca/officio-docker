var AssignTAWithdrawalDialog = function (config) {
    var thisWindow = this;

    config = config || {};
    Ext.apply(this, config);

    // Prepare payment records for the combo and grid
    var arrSingleInvoicePayments = [];
    var arrMultipleInvoicePayments = [];
    Ext.each(config.invoices_payments, function (invoicePayment) {
        var rec = {
            invoicePaymentId: invoicePayment.invoicePaymentId,
            clientId: invoicePayment.clientId,
            clientName: invoicePayment.clientName,
            invoicePaymentName: 'Invoice #' + invoicePayment.invoiceNum + ' - ' + invoicePayment.clientName,
            invoicePaymentGridName: 'Invoice #' + invoicePayment.invoiceNum,
            amount: invoicePayment.amount
        };

        arrMultipleInvoicePayments.push(rec);
        if (invoicePayment.amount == config.withdrawalVal) {
            arrSingleInvoicePayments.push(rec);
        }
    });

    var oInvoicePaymentRecord = Ext.data.Record.create([
        {name: 'invoicePaymentId'},
        {name: 'isSpecialWithdrawal', type: 'bool'},
        {name: 'specialWithdrawalTransaction', type: 'int'},
        {name: 'specialWithdrawalTransactionCustom'},
        {name: 'clientId'},
        {name: 'clientName'},
        {name: 'invoicePaymentName'},
        {name: 'invoicePaymentGridName'},
        {name: 'amount'}
    ]);

    var multiple_invoices_store = new Ext.data.Store({
        data: arrMultipleInvoicePayments,
        reader: new Ext.data.JsonReader({id: 0}, oInvoicePaymentRecord)
    });

    thisWindow.specWithdrawalBtn = new Ext.Button({
        text: '<i class="las la-plus"></i>' + _('Add Special Withdrawal'),
        handler: this.editSpecialWithdrawal.createDelegate(this, [true])
    });

    thisWindow.status_bar = new Ext.ux.StatusBar({
        defaultText: '',
        statusAlign: 'right',
        height: 30,
        items: [thisWindow.specWithdrawalBtn]
    });

    var sm = new Ext.grid.CheckboxSelectionModel();
    sm.on('selectionchange', this.updateUnallocatedAmount, this);

    var expandCol = Ext.id();
    thisWindow.multiple_clients_grid = new Ext.grid.GridPanel({
        store: multiple_invoices_store,
        cm: new Ext.grid.ColumnModel({
            columns: [
                sm,
                {
                    id: expandCol,
                    header: _('Invoice Number'),
                    dataIndex: 'invoicePaymentGridName',
                    renderer: function (val, p, rec) {
                        if (rec.data.isSpecialWithdrawal) {
                            val = String.format(
                                '<a href="#" class="blklink edit_special_withdrawal" onclick="return false;" title="{0}">{1}</a>',
                                _('Click to edit or delete this Special Withdrawal'),
                                val
                            );
                        }

                        return val;
                    }
                }, {
                    header: _('Payment Amount'),
                    dataIndex: 'amount',
                    align: 'right',
                    width: 160,
                    renderer: function (value) {
                        return formatMoney(thisWindow.ta_currency, value, true);
                    }
                }, {
                    header: _('Client'),
                    dataIndex: 'clientName',
                    width: 220
                }
            ],
            defaultSortable: true
        }),
        sm: sm,
        stripeRows: true,
        mode: 'local',
        width: 640,
        height: 200,
        viewConfig: {
            emptyText: _('No Invoices found.'),
            deferEmptyText: false
        },
        loadMask: true,
        autoScroll: true,
        hidden: true,
        colspan: 3,
        autoExpandColumn: expandCol,
        bbar: thisWindow.status_bar,
        cls: 'extjs-grid',
        listeners:
            {
                render: function () {
                    // Update status on first loading
                    thisWindow.updateUnallocatedAmount();
                },

                cellclick: function (grid, rowIndex, columnIndex, e) {
                    var thisGrid = this;
                    var record = thisGrid.getStore().getAt(rowIndex);

                    if ($(e.getTarget()).hasClass('edit_special_withdrawal')) {
                        thisWindow.editSpecialWithdrawal(false);
                    }
                }
            }
    });

    // Single payment combo
    thisWindow.clients_combo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: arrSingleInvoicePayments,
            reader: new Ext.data.JsonReader({id: 'invoicePaymentId'}, oInvoicePaymentRecord)
        }),
        mode: 'local',
        searchContains: true,
        ctCls: 'deposit-select-first',
        width: 400,
        listWidth: 400,
        hideLabel: true,
        displayField: 'invoicePaymentName',
        valueField: 'invoicePaymentId',
        typeAhead: false,
        forceSelection: true,
        triggerAction: 'all',
        emptyText: _('Select a payment...'),
        selectOnFocus: true
    });

    thisWindow.assign_client_radio = new Ext.form.Radio({
        boxLabel: _('Assign to an Invoice of ') + formatMoney(thisWindow.ta_currency, config.withdrawalVal, true),
        name: 'rb-col',
        colspan: 3,
        ctCls: 'box-padding',
        fieldLabel: '',
        labelSeparator: '',
        checked: true,
        inputValue: 1
    });

    thisWindow.assign_client_radio.on('check', function (r, booChecked) {
        if (booChecked) {
            thisWindow.showContent('client');
        }
    });

    thisWindow.mc_radio = new Ext.form.Radio({
        boxLabel: _('Assign to Multiple Invoices'),
        name: 'rb-col',
        xtype: 'radio',
        ctCls: 'box-padding',
        fieldLabel: '',
        labelSeparator: '',
        inputValue: 2,
        colspan: 3
    });

    thisWindow.mc_radio.on('check', function (r, booChecked) {
        if (booChecked) {
            thisWindow.showContent('multiple-clients');
        }
    });

    thisWindow.transactions_radio = new Ext.form.Radio({
        boxLabel: _('Assign as a Special Withdrawal'),
        ctCls: 'box-padding',
        name: 'rb-col',
        inputValue: 2
    });

    thisWindow.transactions_radio.on('check', function (r, booChecked) {
        if (booChecked) {
            thisWindow.showContent('custom');
        }
    });

    thisWindow.transactions_combo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: config.withdrawal_types,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'transactionId'}, {name: 'transactionName'}]))
        }),
        mode: 'local',
        width: 250,
        hideLabel: true,
        displayField: 'transactionName',
        valueField: 'transactionId',
        typeAhead: true,
        forceSelection: true,
        triggerAction: 'all',
        emptyText: _('Select a transaction...'),
        selectOnFocus: true
    });

    thisWindow.transactions_combo.on('beforeselect', function (n, selRecord) {
        thisWindow.transactions_custom.setVisible(empty(selRecord.data.transactionId));
    });

    thisWindow.transactions_custom = new Ext.form.TextField({
        hidden: true,
        fieldLabel: _('Custom Transaction Name'),
        labelWidth: 150,
        width: 197,
        value: _('Miscellaneous'),
        allowBlank: false
    });

    // Returned payment
    thisWindow.payment_radio = new Ext.form.Radio({
        boxLabel: _('Assign as Returned Payment'),
        ctCls: 'box-padding',
        name: 'rb-col',
        inputValue: 3
    });

    thisWindow.payment_radio.on('check', function (r, booChecked) {
        if (booChecked) {
            thisWindow.showContent('returned_payment');
        }
    });

    thisWindow.payment_combo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: config.clients,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'clientId'}, {name: 'clientName'}]))
        }),
        mode: 'local',
        hideLabel: true,
        hidden: true,
        displayField: 'clientName',
        valueField: 'clientId',
        typeAhead: true,
        forceSelection: true,
        triggerAction: 'all',
        emptyText: _('Select a case...'),
        selectOnFocus: true,
        colspan: 3,
        width: 400
    });

    thisWindow.destination = new Ext.form.ComboBox({
        fieldLabel: _('Destination Account'),
        store: new Ext.data.Store({
            data: config.destinations,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'transactionId'}, {name: 'transactionName'}]))
        }),
        mode: 'local',
        width: 300,
        hideMode: 'visibility',
        displayField: 'transactionName',
        valueField: 'transactionId',
        typeAhead: true,
        forceSelection: true,
        triggerAction: 'all',
        value: '---',
        selectOnFocus: true
    });

    thisWindow.destination.on('beforeselect', function (n, selRecord) {
        thisWindow.destination_custom.setVisible(empty(selRecord.data.transactionId));
    });

    thisWindow.destination_custom = new Ext.form.TextField({
        hideMode: 'visibility',
        hidden: true,
        allowBlank: false,
        width: 222,
        style: 'margin-bottom:4px'
    });

    thisWindow.notes = new Ext.form.TextField({
        fieldLabel: _('Notes'),
        hideMode: 'visibility',
        labelWidth: 150,
        width: 595,
        value: '',
        colspan: 3
    });

    thisWindow.transactions_fieldset = new Ext.form.FieldSet({
        hidden: true,
        layout: 'table',
        style: 'border:none; margin:0; padding:0;',
        layoutConfig: {columns: 3},
        items: [thisWindow.transactions_combo, {html: '&nbsp;'}, thisWindow.transactions_custom]
    });

    var pan = new Ext.FormPanel({
        labelWidth: 140,
        autoHeight: true,
        bodyStyle: 'padding:5px',
        layout: 'table',
        layoutConfig: {columns: 1},
        items: [
            thisWindow.assign_client_radio, thisWindow.clients_combo,
            thisWindow.mc_radio, thisWindow.multiple_clients_grid,
            thisWindow.transactions_radio, thisWindow.transactions_fieldset,
            thisWindow.payment_radio, thisWindow.payment_combo
        ]
    });

    var destination_pan = new Ext.FormPanel({
        labelWidth: 140,
        autoHeight: true,
        bodyStyle: 'padding:5px',
        layout: 'table',
        cls: 'cell-align-top',
        layoutConfig: {columns: 3},
        items: [{layout: 'form', items: thisWindow.destination}, {html: '&nbsp;'}, thisWindow.destination_custom]
    });

    var notes_pan = new Ext.FormPanel({
        bodyStyle: 'padding:5px',
        labelWidth: 50,
        autoHeight: true,
        items: thisWindow.notes
    });

    AssignTAWithdrawalDialog.superclass.constructor.call(this, {
        title: _('Assign Withdrawal to Invoice(s) or a Special Transaction'),
        buttonAlign: 'right',
        modal: true,
        autoWidth: true,
        autoHeight: true,

        items: [pan, destination_pan, notes_pan],

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: _('Submit'),
                cls: 'orange-btn',
                handler: this.assignWithdrawalRequest.createDelegate(this)
            }
        ]
    });
};

Ext.extend(AssignTAWithdrawalDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    assignWithdrawalRequest: function () {
        var thisWindow = this;
        var params = {};
        var arrClientsRefresh = [];

        if (thisWindow.assign_client_radio.getValue()) //assign to a single payment
        {
            // Is client selected?
            if (!thisWindow.clients_combo.isValid() || getComboBoxIndex(thisWindow.clients_combo) == -1) {
                thisWindow.clients_combo.markInvalid(_('This field is required'));
                return;
            }

            var selectedValue = thisWindow.clients_combo.getValue();
            var selectedRec = thisWindow.clients_combo.getStore().getById(selectedValue);

            arrClientsRefresh = [selectedRec.data.clientId];

            params = {
                assign_to: 'single-invoice-payment',
                invoice_payment_id: selectedRec.data.invoicePaymentId
            };
        } else if (thisWindow.mc_radio.getValue()) // Assign to multiple payments
        {
            var amountSum = 0;
            var arrSelectedInvoicePayments = [];
            var arrSelectedSpecialWithdrawals = [];

            var arrSelectedItems = thisWindow.multiple_clients_grid.getSelectionModel().getSelections();
            Ext.each(arrSelectedItems, function (r) {
                if (empty(r.data.invoicePaymentId) && r.data.isSpecialWithdrawal) {
                    arrSelectedSpecialWithdrawals.push(r.data);
                } else {
                    arrSelectedInvoicePayments.push(r.data);
                    arrClientsRefresh.push(r.data.clientId);
                }

                amountSum += parseFloat(r.data.amount);
            });
            amountSum = toFixed(amountSum, 2);

            // Check if amounts sum is equal to withdrawal
            var strError = '';
            if (empty(strError) && amountSum != thisWindow.withdrawalVal) {
                strError = _('Amounts sum is not equal to withdrawal');
            }

            if (!empty(strError)) {
                Ext.simpleConfirmation.error(strError);
                return;
            }

            params = {
                'assign_to': 'multiple-invoice-payments',
                'arr_invoice_payment_ids': Ext.encode(arrSelectedInvoicePayments),
                'arr_special_withdrawals': Ext.encode(arrSelectedSpecialWithdrawals),
            };

        } else if (thisWindow.transactions_radio.getValue()) // Assign to Special Transaction
        {
            if (!thisWindow.transactions_combo.isValid() || getComboBoxIndex(thisWindow.transactions_combo) == -1) {
                thisWindow.transactions_combo.markInvalid('This field is required');
                return;
            } else if (!thisWindow.transactions_custom.isValid()) {
                thisWindow.transactions_custom.markInvalid('This field is required');
                // If other option is selected - check if something is entered
                return;
            }

            // Okay
            params = {
                assign_to: 'special-transaction',
                special_transaction_id: thisWindow.transactions_combo.getValue(),
                custom_transaction: Ext.encode(thisWindow.transactions_custom.getValue())
            };

        } else if (thisWindow.payment_radio.getValue()) // Assign to Returned Payment
        {
            // Is Returned Payment (client) selected?
            if (!thisWindow.payment_combo.isValid() || getComboBoxIndex(thisWindow.payment_combo) == -1) {
                thisWindow.payment_combo.markInvalid('This field is required');
                return;
            }

            var returned_payment_member_id = thisWindow.payment_combo.getValue();
            arrClientsRefresh[0] = returned_payment_member_id;

            // Okay
            params = {
                assign_to: 'returned-payment',
                returned_payment_member_id: returned_payment_member_id
            };
        }

        // Check if Destination Account is selected
        if (!thisWindow.destination.isValid()) {
            thisWindow.destination.markInvalid('This field is required');
            return;
        } else if (!thisWindow.destination_custom.hidden && !thisWindow.destination_custom.isValid()) {
            // If other option is selected - check if something is entered
            thisWindow.destination_custom.markInvalid('This field is required');
            return;
        }

        var oDestinationAccountValue = thisWindow.destination.getValue();
        if (getComboBoxIndex(thisWindow.destination) == -1) {
            oDestinationAccountValue = -1;
        }

        var generalParams = {
            act: Ext.encode('assign-withdrawal'),
            transaction_id: Ext.encode(thisWindow.ta_id),
            destination_account_id: Ext.encode(oDestinationAccountValue),
            destination_account_other: Ext.encode(thisWindow.destination_custom.getValue()),
            notes: Ext.encode(thisWindow.notes.getValue())
        };

        Ext.apply(params, generalParams);
        sendRequest(thisWindow, params, thisWindow.company_ta_id, arrClientsRefresh);
    },

    updateUnallocatedAmount: function () {
        var thisWindow = this;

        var sum = 0;
        var selected_items = thisWindow.multiple_clients_grid.getSelectionModel().getSelections();
        var free_amount = parseFloat(thisWindow.withdrawalVal);
        for (var i = 0; i < selected_items.length; i++) {
            free_amount -= parseFloat(selected_items[i].data.amount);
        }

        var txt = String.format(
            'Unallocated amount: <span style="color: {0};">{1}</span>',
            free_amount < 0 ? 'red' : (free_amount == 0 ? 'green' : '#FF7400'),
            formatMoney(thisWindow.ta_currency, free_amount, true)
        );

        thisWindow.status_bar.setStatus({
            text: txt,
            iconCls: '',
            clear: false
        });
    },

    showContent: function (section) {
        var thisWindow = this;

        thisWindow.clients_combo.hide();
        thisWindow.transactions_fieldset.hide();
        thisWindow.transactions_custom.hide();
        thisWindow.multiple_clients_grid.hide();
        thisWindow.payment_combo.hide();

        switch (section) {
            case 'client' :
                thisWindow.clients_combo.show();
                break;

            case 'multiple-clients' :
                thisWindow.multiple_clients_grid.show();
                break;

            case 'custom' :
                thisWindow.transactions_fieldset.show();
                if (getComboBoxIndex(thisWindow.transactions_combo) != -1) {
                    thisWindow.transactions_custom.show();
                }
                break;

            case 'returned_payment' :
                thisWindow.payment_combo.show();
                break;
            default:
                break;
        }

        thisWindow.syncShadow(); // Shadow bug
        thisWindow.center();
    },

    editSpecialWithdrawal: function (booAdd) {
        //get grid
        var thisWindow = this;
        var multiple_invoice_grid = thisWindow.multiple_clients_grid;

        var transactions_amount = new Ext.form.NumberField({
            fieldLabel: _('Amount'),
            allowBlank: false,
            width: 250,
        });

        var transactions_combo = new Ext.form.ComboBox({
            fieldLabel: _('Special Withdrawal'),
            emptyText: _('Please select...'),
            width: 250,

            store: new Ext.data.Store({
                data: thisWindow.withdrawal_types,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'transactionId'}, {name: 'transactionName'}]))
            }),
            mode: 'local',
            hideMode: 'visibility',
            displayField: 'transactionName',
            valueField: 'transactionId',
            typeAhead: true,
            forceSelection: true,
            triggerAction: 'all',
            editable: false,
            selectOnFocus: true
        });

        transactions_combo.on('beforeselect', function (n, selRecord) {
            transactions_custom.setVisible(empty(selRecord.data.transactionId));
        });

        var transactions_custom = new Ext.form.TextField({
            fieldLabel: '',
            emptyText: _('Please enter...'),
            value: 'Miscellaneous',
            hideMode: 'visibility',
            hidden: true,
            allowBlank: false,
            width: 250
        });

        //edit mode
        if (!booAdd) {
            var sel = multiple_invoice_grid.getSelectionModel().getSelected();

            transactions_amount.setValue(sel.data.amount);
            transactions_combo.setValue(sel.data.specialWithdrawalTransaction);

            if (sel.data.specialWithdrawalTransaction === 0) {
                transactions_combo.setValue(0);
                transactions_custom.setValue(sel.data.specialWithdrawalTransactionCustom);
                transactions_custom.show();
            }
        }

        var pan = new Ext.Panel({
            layout: 'table',
            layoutConfig: {columns: 2},
            items: [{
                layout: 'form',
                labelAlign: 'top',
                items: [transactions_combo]
            }, {
                layout: 'form',
                labelAlign: 'top',
                style: 'padding-left: 10px;',
                items: [transactions_custom]
            }, {
                layout: 'form',
                labelAlign: 'top',
                items: [transactions_amount]
            }]
        });

        var saveBtn = new Ext.Button({
            text: booAdd ? _('Add') : _('Update'),
            cls: 'orange-btn',
            handler: function () {
                //validate
                if (!transactions_amount.isValid() || !transactions_combo.isValid() || !transactions_custom.isValid()) {
                    return false;
                }

                //validate
                if (getComboBoxIndex(transactions_combo) == -1) {
                    transactions_combo.markInvalid();
                    return false;
                }

                //get transaction name
                var sw_name = transactions_combo.getValue() === 0 ? transactions_custom.getValue() : transactions_combo.getRawValue();
                var sw_store = multiple_invoice_grid.getStore();

                var recData = {
                    invoicePaymentId: 0,
                    isSpecialWithdrawal: true,
                    clientId: 0,
                    clientName: '',
                    invoicePaymentName: '',
                    invoicePaymentGridName: sw_name,
                    amount: transactions_amount.getValue(),
                    specialWithdrawalTransaction: transactions_combo.getValue(),
                    specialWithdrawalTransactionCustom: transactions_custom.getValue()
                };

                if (booAdd) {
                    //create new record
                    var record = new sw_store.recordType(recData);

                    //insert new record
                    multiple_invoice_grid.stopEditing();
                    sw_store.insert(0, record);

                    //select first row
                    multiple_invoice_grid.getSelectionModel().selectFirstRow();
                } else {
                    //update record
                    sel.data = recData;
                    sel.commit();
                }

                thisWindow.updateUnallocatedAmount();

                win.close();
            }
        });

        var deleteBtn = new Ext.Button({
            text: _('Delete'),
            hidden: booAdd,
            handler: function () {
                var selected = multiple_invoice_grid.getSelectionModel().getSelected();
                var sw_store = multiple_invoice_grid.getStore();
                sw_store.remove(selected);

                thisWindow.updateUnallocatedAmount();

                win.close();
            }
        });

        var cancelBtn = new Ext.Button({
            text: _('Cancel'),
            handler: function () {
                win.close();
            }
        });

        var win = new Ext.Window({
            title: booAdd ? '<i class="las la-plus"></i>' + _('Add Special Withdrawal') : '<i class="las la-pen"></i>' + _('Edit Special Withdrawal'),
            modal: true,
            autoWidth: true,
            autoHeight: true,
            items: pan,
            resizable: false,
            buttons: [deleteBtn, cancelBtn, saveBtn]
        });

        win.show();
        win.center();
    }
});


function assignWithdrawal(currentSelectedTransactionId, withdrawalVal, company_ta_id) {
    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + "/trust-account/assign/get-assign-withdrawal-data",
        params: {
            company_ta_id: Ext.encode(company_ta_id),
            ta_id: Ext.encode(currentSelectedTransactionId)
        },

        success: function (result) {
            Ext.getBody().unmask();

            var resultDecoded = Ext.decode(result.responseText);
            if (resultDecoded.success) {
                var win = new AssignTAWithdrawalDialog({
                    ta_id: currentSelectedTransactionId,
                    ta_currency: resultDecoded.ta_currency,
                    company_ta_id: company_ta_id,
                    withdrawalVal: toFixed(resultDecoded.withdrawalVal, 2),
                    invoices_payments: resultDecoded.invoices_payments,
                    withdrawal_types: resultDecoded.withdrawal_types,
                    destinations: resultDecoded.destinations,
                    clients: resultDecoded.clients
                });

                win.show();
                win.center();
            } else {
                // Show error message
                Ext.simpleConfirmation.error(resultDecoded.message);
            }
        },

        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error(_('Data cannot be loaded. Please try again later.'));
        }
    });
}