var MarkAsPaidDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;
    var clientAccountingPanel = Ext.getCmp('accounting_invoices_panel_' + thisWindow.member_id);
    this.clientAccountingPanel = clientAccountingPanel;

    this.ta_currency_label = clientAccountingPanel.getCurrencySymbolByTAId(thisWindow.ta_id)
    this.ta_currency = clientAccountingPanel.getCurrencyByTAId(thisWindow.ta_id);
    this.amount_limit = [];

    var arrTAInfo = clientAccountingPanel.getTAInfo(clientAccountingPanel.primaryTAId);
    this.transferFromPrimaryTARadio = new Ext.form.Radio({
        boxLabel: _('Pay invoice from ') + arrApplicantsSettings.ta_label + ' ' + arrTAInfo[1],
        name: 'invoice_payment_transfer_from',
        itemCls: 'no-margin-bottom no-padding-top',
        inputValue: clientAccountingPanel.primaryTAId,
        maxTAAmount: Number.MAX_VALUE,
        checked: false
    });

    this.transferFromPrimaryTARadioError = new Ext.form.DisplayField({
        hidden: true,
        itemCls: 'no-margin-bottom',
        value: "<div style='color: red; margin-left: 25px'>" + _('No sufficient verified fund to transfer') + "</div>"
    });

    arrTAInfo = clientAccountingPanel.getTAInfo(clientAccountingPanel.secondaryTAId);
    this.transferFromSecondaryTARadio = new Ext.form.Radio({
        boxLabel:    empty(clientAccountingPanel.secondaryTAId) ? '' : _('Pay invoice from ') + arrApplicantsSettings.ta_label + ' ' + arrTAInfo[1],
        name:        'invoice_payment_transfer_from',
        inputValue:  clientAccountingPanel.secondaryTAId,
        maxTAAmount: Number.MAX_VALUE,
        hidden:      empty(clientAccountingPanel.secondaryTAId),
        checked:     false
    });

    this.transferFromSecondaryTARadioError = new Ext.form.DisplayField({
        hidden: true,
        value:  "<div style='color: red; margin-left: 25px'>" + _('No sufficient verified fund to transfer') + "</div>"
    });

    this.transferFromOtherRadio = new Ext.form.Radio({
        boxLabel: _('Pay invoice from ') + arrApplicantsSettings.accounting.invoicePaymentOperatingAccountLabel,
        name: 'invoice_payment_transfer_from',
        inputValue: 'other',
        checked: true,
        maxTAAmount: Number.MAX_VALUE
    });

    this.transferFromOtherField = new Ext.form.TextField({
        name: 'invoice_payment_transfer_from_other',
        style: 'margin-left: 20px',
        hidden: true,
        allowBlank: false,
        value: arrApplicantsSettings.accounting.invoicePaymentOperatingAccountLabel,
        width: 225
    });

    this.specialAdjustmentRadio = new Ext.form.Radio({
        boxLabel: _('Write off invoice with an adjustment'),
        name: 'invoice_payment_transfer_from',
        inputValue: 'special_adjustment',
        maxTAAmount: Number.MAX_VALUE
    });

    var arrSpecialAdjustmentLabels = [];
    Ext.each(arrApplicantsSettings.accounting.arrInvoicePaymentSpecialAdjustmentOptions, function (label) {
        arrSpecialAdjustmentLabels.push([label]);
    });

    this.specialAdjustmentCombo = new Ext.form.ComboBox({
        allowBlank: false,
        store: new Ext.data.SimpleStore({
            fields: ['payment_label'],
            data: arrSpecialAdjustmentLabels
        }),
        displayField: 'payment_label',
        valueField: 'payment_label',
        mode: 'local',
        triggerAction: 'all',
        width: 220,
        lazyRender: true,
        forceSelection: true,
        editable: false,
        value: arrSpecialAdjustmentLabels[0][0],

        listeners: {
            'beforeselect': function (combo, record) {
                thisWindow.onRadioChange(thisWindow.transferFromRadioGroup, thisWindow.transferFromRadioGroup.getValue(), record['data'][combo.valueField]);
            }
        }
    });

    var transferFromPrimaryContainer = new Ext.Container({
        xtype: 'container',
        layout: 'form',
        items: [
            this.transferFromPrimaryTARadio,
            this.transferFromPrimaryTARadioError
        ]
    });

    var transferFromSecondaryContainer = new Ext.Container({
        xtype: 'container',
        style: 'padding-top: 5px',
        width: 600,
        items: [
            this.transferFromSecondaryTARadio,
            this.transferFromSecondaryTARadioError
        ]
    });

    this.transferFromRadioGroup = new Ext.form.RadioGroup({
        hideLabel: true,
        columns: 1,
        allowBlank: false,
        preventMark: false,
        width: 600,

        items: [{
            xtype: 'container',
            layout: 'table',
            width: 600,
            layoutConfig: {
                tableAttrs: {
                    style: {
                        width: '100%'
                    }
                },

                columns: 1
            },

            items: [
                {
                    xtype:  'container',
                    layout: 'column',

                    items:   [{
                        xtype: 'label',
                        style: 'float: left; padding-right: 10px',
                        text:  _('Date:')
                    }, {
                        xtype:      'datefield',
                        value:      new Date(),
                        name:       'invoice_payment_date',
                        format:     dateFormatFull,
                        maxLength:  12, // Fix issue with date entering in 'full' format
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        allowBlank: false,
                        width:      160
                    }]
                }, {
                    xtype: 'container',
                    style: 'padding-top: 30px',
                    layout: 'form',
                    items: {
                        xtype: 'label',
                        text: _('Select an invoice payment option:')
                    }
                },

                {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        {
                            xtype: 'container',
                            style: 'padding-top: 8px',
                            items: this.transferFromOtherRadio
                        },

                        this.transferFromOtherField
                    ]
                },

                // Show in different order - depends on the invoice's T/A (show it first)
                this.transferFromPrimaryTARadio.checked ? transferFromPrimaryContainer : transferFromSecondaryContainer,
                this.transferFromPrimaryTARadio.checked ? transferFromSecondaryContainer : transferFromPrimaryContainer,

                {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        {
                            xtype: 'container',
                            style: 'padding-top: 8px; padding-right: 10px',
                            items: this.specialAdjustmentRadio
                        },

                        this.specialAdjustmentCombo
                    ]
                }
            ]
        }],

        listeners: {
            'change': function (group, radio) {
                thisWindow.onRadioChange(group, radio, thisWindow.specialAdjustmentCombo.getValue());
            }
        }
    });

    var anotherTAId = parseInt(thisWindow.ta_id, 10) === parseInt(clientAccountingPanel.primaryTAId, 10) ? clientAccountingPanel.secondaryTAId : clientAccountingPanel.primaryTAId;
    anotherTAId = empty(anotherTAId) ? clientAccountingPanel.primaryTAId : anotherTAId;

    // The label will be changed if "Special adjustment" is selected
    this.payAmountLabel = new Ext.form.Label({
        html: _('Pay Amount') + ' (' + thisWindow.ta_currency_label + ')',
        style: 'padding-right: 30px'
    });

    this.invoicesContainer = new Ext.Container({
        layout: 'table',

        layoutConfig: {
            tableAttrs: {
                style: {
                    'margin': '40px 0'
                }
            },

            columns: 6
        },

        items: [
            {
                xtype: 'label',
                style: 'padding-right: 30px',
                html: _('Invoice #')
            }, {
                xtype: 'label',
                html: _('Invoice Date'),
                style: 'padding-right: 30px'
            }, {
                xtype: 'label',
                html: _('Invoice Amount') + ' (' + thisWindow.ta_currency_label + ')',
                style: 'padding-right: 30px'
            }, {
                xtype: 'label',
                html: _('Amount Due') + ' (' + thisWindow.ta_currency_label + ')',
                style: 'padding-right: 30px'
            },
            this.payAmountLabel,
            {
                xtype: 'label',
                cellCls: 'equivalent_amount',
                html: _(' Equivalent of') + ' (' + thisWindow.clientAccountingPanel.getCurrencySymbolByTAId(anotherTAId) + ')'
            }, {
                colspan: 6,
                html: '<div style="height: 3px">&nbsp;</div>'
            }
        ]
    });

    // This is a Cheque for transfer from "operational account" or "from T/A", but is a Description for "Special adjustment" radio
    this.chequeLabel = new Ext.form.Label({
        xtype: 'label',
        style: 'padding: 10px 10px 0 0',
        html: _('Cheque or Transaction #:')
    });

    this.formPanel = new Ext.FormPanel({
        labelWidth: 155,
        labelAlign: 'top',
        bodyStyle:  'padding: 5px;',

        items: [

            {
                xtype: 'hidden',
                name:  'invoice_payment_member_id',
                value: thisWindow.member_id
            }, {
                xtype: 'hidden',
                name:  'invoice_payment_ta_id',
                value: thisWindow.ta_id
            },

            this.transferFromRadioGroup, this.invoicesContainer,

            {
                xtype: 'container',
                layout: 'column',
                width: 600,
                items: [
                    this.chequeLabel,
                    {
                        xtype: 'textfield',
                        hideLabel:  true,
                        name: 'invoice_payment_cheque_num',
                        maxLength: 255,
                        width: 433
                    }
                ]
            }
        ]
    });

    this.cancelButton = new Ext.Button({
        text:    _('Cancel'),
        handler: function () {
            thisWindow.close();
        }
    });

    this.payButton = new Ext.Button({
        text:    _('Pay'),
        cls:     'orange-btn',
        handler: this.savePayment.createDelegate(thisWindow)
    });

    MarkAsPaidDialog.superclass.constructor.call(this, {
        title:      _('Pay Now'), // Old name "Mark as paid"
        cls:        'mark-as-paid-dialog',
        resizable:  false,
        autoHeight: true,
        autoWidth:  true,
        modal:      true,

        items: [this.formPanel],

        buttons: [this.cancelButton, this.payButton]
    });
};

Ext.extend(MarkAsPaidDialog, Ext.Window, {
    showMarkAsPaidDialog: function () {
        this.loadSettings();
    },

    onRadioChange: function (group, radio, adjustmentComboValue) {
        var thisWindow = this;

        if (radio) {
            // @Note: temporary don't show it, pass 1 value always
            // thisWindow.transferFromOtherField.setVisible(radio.inputValue === 'other');

            var arrInvoiceAmountFields = thisWindow.invoicesContainer.find('class', 'invoice-amount');
            Ext.each(arrInvoiceAmountFields, function (oAmountField) {
                oAmountField.maxValue = Ext.min([oAmountField.invoice_amount_due, Ext.num(radio.maxTAAmount, Number.MAX_VALUE)]);
                oAmountField.clearInvalid();
            });

            if (radio.inputValue === 'special_adjustment') {
                thisWindow.specialAdjustmentCombo.setDisabled(false);
                thisWindow.transferAmountContainer.setVisible(false);
                thisWindow.transferFromOtherField.setValue(adjustmentComboValue);

                Ext.getDom(thisWindow.chequeLabel.id).innerHTML = _('Description/Reason:');
                Ext.getDom(thisWindow.payAmountLabel.id).innerHTML = adjustmentComboValue + ' (' + thisWindow.ta_currency_label + ')';
            } else {
                thisWindow.specialAdjustmentCombo.setDisabled(true);
                thisWindow.transferAmountContainer.setVisible(true);
                thisWindow.transferFromOtherField.setValue(arrApplicantsSettings.accounting.invoicePaymentOperatingAccountLabel);

                Ext.getDom(thisWindow.chequeLabel.id).innerHTML = _('Cheque or Transaction #:');
                Ext.getDom(thisWindow.payAmountLabel.id).innerHTML = _('Pay Amount') + ' (' + thisWindow.ta_currency_label + ')';
            }
        }

        thisWindow.recalculateTotal();
    },

    getTASubTotal: function (ta_id) {
        var thisWindow = this;
        ta_id = parseInt(ta_id, 10);
        for (var i = 0; i < thisWindow.amount_limit.length; i++) {
            if (ta_id === parseInt(thisWindow.amount_limit[i].ta_id, 10)) {
                return thisWindow.amount_limit[i].ta_balance;
            }
        }

        return 0;
    },

    applyLoadedInfo: function (resultData) {
        var thisWindow = this;

        // Will be used to set available balance for each shown T/A
        thisWindow.amount_limit = resultData.amount_limit;

        var availableTotalPrimary = thisWindow.getTASubTotal(thisWindow.transferFromPrimaryTARadio.inputValue);
        var newBoxLabel = thisWindow.transferFromPrimaryTARadio.boxLabel + ' ' + availableTotalPrimary;
        thisWindow.transferFromPrimaryTARadio.getEl().parent().down('label.x-form-cb-label').update(newBoxLabel);
        thisWindow.transferFromPrimaryTARadio.maxTAAmount = availableTotalPrimary;

        if (thisWindow.transferFromSecondaryTARadio.isVisible()) {
            var availableTotalSecondary = thisWindow.getTASubTotal(thisWindow.transferFromSecondaryTARadio.inputValue);
            newBoxLabel = thisWindow.transferFromSecondaryTARadio.boxLabel + ' ' + availableTotalSecondary;
            thisWindow.transferFromSecondaryTARadio.getEl().parent().down('label.x-form-cb-label').update(newBoxLabel);
            thisWindow.transferFromSecondaryTARadio.maxTAAmount = availableTotalSecondary;
        }

        // Generate invoices rows
        Ext.each(resultData.invoices, function (oInvoice) {
            thisWindow.invoicesContainer.add({
                xtype: 'label',
                html:  oInvoice['invoice_number']
            });

            thisWindow.invoicesContainer.add({
                xtype: 'label',
                html:  Ext.util.Format.date(oInvoice['invoice_date'], dateFormatFull)
            });

            thisWindow.invoicesContainer.add({
                xtype: 'label',
                html:  formatMoney(thisWindow.ta_currency, oInvoice['invoice_amount'], true)
            });

            thisWindow.invoicesContainer.add({
                xtype: 'label',
                html:  formatMoney(thisWindow.ta_currency, oInvoice['invoice_amount_due'], true)
            });

            thisWindow.invoicesContainer.add({
                xtype: 'normalnumber',
                name: 'invoice_payment_amount_' + oInvoice['invoice_id'],
                invoice_id: oInvoice['invoice_id'],
                invoice_amount_due: oInvoice['invoice_amount_due'],
                'class': 'invoice-amount',
                allowNegative: false,
                maxText: _('Payment amount cannot be greater than the invoice amount.'),
                style: 'text-align: right',
                forceDecimalPrecision: true,
                decimalPrecision: 2,
                width: 155,
                minValue: 0.01,
                enableKeyEvents: true,
                emptyText: '0.00',

                listeners: {
                    render: function (field) {
                        // Set the value here to make sure that a correct format will be applied
                        field.setValue(oInvoice['invoice_amount_due']);
                    },

                    keyup: thisWindow.recalculateTotal.createDelegate(thisWindow)
                }
            });

            thisWindow.invoicesContainer.add({
                xtype: 'normalnumber',
                name: 'invoice_payment_amount_equivalent_' + oInvoice['invoice_id'],
                cellCls: 'equivalent_amount',
                allowNegative: false,
                style: 'text-align: right;',
                forceDecimalPrecision: true,
                decimalPrecision: 2,
                width: 155,
                minValue: 0.01,
                enableKeyEvents: true,
                emptyText: '0.00',

                listeners: {
                    render: function (field) {
                        // Set the value here to make sure that a correct format will be applied
                        field.setValue(oInvoice['invoice_amount_due']);
                    }
                }
            });
        });

        thisWindow.invoicesContainer.add({
            colspan: 6,
            html: '&nbsp;'
        });

        thisWindow.transferAmountContainer = new Ext.Container({
            xtype: 'container',
            colspan: 5,
            layout: 'table',
            layoutConfig: {
                columns: 2,
                tableAttrs: {
                    style: {
                        'float': 'right',
                        'padding-right': '25px'
                    }
                }
            },

            items: [
                {
                    style: 'margin-right: 10px',
                    html: '<label style="font-weight: bold">' + _('Transfer Amount in ') + thisWindow.ta_currency_label + '</label>'
                }, {
                    xtype: 'displayfield',
                    'class': 'total-amount',
                    value: '<label style="font-weight: bold">' + formatMoney(thisWindow.ta_currency, 0, true) + '</label>'
                }
            ]
        });

        thisWindow.invoicesContainer.add(thisWindow.transferAmountContainer);

        thisWindow.invoicesContainer.doLayout();

        // Automatically toggle fields in relation to the checked radio
        thisWindow.transferFromRadioGroup.fireEvent('change', thisWindow.transferFromRadioGroup, thisWindow.transferFromRadioGroup.getValue());
    },

    loadSettings: function () {
        var thisWindow = this;

        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/get-mark-as-paid-details',

            params: {
                member_id:  Ext.encode(thisWindow.member_id),
                ta_id:      Ext.encode(thisWindow.ta_id),
                invoice_id: Ext.encode(thisWindow.invoice_id),
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                Ext.getBody().unmask();

                if (resultData.success) {
                    thisWindow.on('show', thisWindow.applyLoadedInfo.createDelegate(thisWindow, [resultData]), thisWindow, {single: true});
                    thisWindow.show();
                    thisWindow.center();
                } else {
                    // Show an error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot load data.<br/>Please try again later.'));
                Ext.getBody().unmask();
            }
        });
    },

    recalculateTotal: function () {
        var thisWindow = this;
        var enteredAmount = 0;
        var arrInvoiceAmountFields = thisWindow.invoicesContainer.find('class', 'invoice-amount');
        Ext.each(arrInvoiceAmountFields, function (oAmountField) {
            if (!empty(oAmountField.getValue())) {
                enteredAmount += parseFloat(oAmountField.getValue());
            }
        });

        var booHideEquivalent = true;
        if (thisWindow.transferFromPrimaryTARadio.getValue()) {
            booHideEquivalent = thisWindow.transferFromPrimaryTARadio.inputValue == thisWindow.ta_id
        } else if (thisWindow.transferFromSecondaryTARadio.getValue()) {
            booHideEquivalent = thisWindow.transferFromSecondaryTARadio.inputValue == thisWindow.ta_id
        }

        // If a primary T/A's currency is the same as a secondary T/A's currency - hide the "equivalent" field
        if (!empty(thisWindow.clientAccountingPanel.secondaryTAId)) {
            var primaryTACurrency = thisWindow.clientAccountingPanel.getCurrencyByTAId(thisWindow.clientAccountingPanel.primaryTAId);
            var secondaryTACurrency = thisWindow.clientAccountingPanel.getCurrencyByTAId(thisWindow.clientAccountingPanel.secondaryTAId);

            if (primaryTACurrency == secondaryTACurrency) {
                booHideEquivalent = true;

                // We should update the amount of the equivalent amount field because it will be saved
                Ext.each(arrInvoiceAmountFields, function (oAmountField) {
                    if (!empty(oAmountField.getValue())) {
                        Ext.each(thisWindow.invoicesContainer.find('name', 'invoice_payment_amount_equivalent_' + oAmountField.invoice_id), function (obj) {
                            obj.setValue(oAmountField.getValue());
                        });
                    }
                });
            }
        }

        Ext.each(thisWindow.invoicesContainer.find('cellCls', 'equivalent_amount'), function (obj) {
            obj.setVisible(!booHideEquivalent);
        });

        var oTotalAmountField = thisWindow.invoicesContainer.find('class', 'total-amount')[0];
        oTotalAmountField.setValue('<label style="font-weight: bold">' + formatMoney(thisWindow.ta_currency, enteredAmount, true) + '</label>');

        thisWindow.transferFromPrimaryTARadioError.setVisible(false);
        thisWindow.transferFromSecondaryTARadioError.setVisible(false);

        var oRadio = thisWindow.transferFromRadioGroup.getValue();
        if (oRadio) {
            // Show errors for each checked radio
            if (oRadio.maxTAAmount <= 0) {
                if (thisWindow.transferFromPrimaryTARadio.getValue()) {
                    thisWindow.transferFromPrimaryTARadioError.setVisible(true);
                } else if (thisWindow.transferFromSecondaryTARadio.getValue()) {
                    thisWindow.transferFromSecondaryTARadioError.setVisible(true);
                }
            }

            // Show total in red + disable the submit button if entered amount is more than allowed by T/A
            if (enteredAmount > oRadio.maxTAAmount) {
                if (thisWindow.transferFromPrimaryTARadio.getValue()) {
                    thisWindow.transferFromPrimaryTARadioError.setVisible(true);
                } else if (thisWindow.transferFromSecondaryTARadio.getValue()) {
                    thisWindow.transferFromSecondaryTARadioError.setVisible(true);
                }

                this.payButton.setDisabled(true);
                oTotalAmountField.addClass('total-amount-invalid');
            } else {
                this.payButton.setDisabled(false);
                oTotalAmountField.removeClass('total-amount-invalid');
            }
        }
    },

    savePayment: function () {
        var thisWindow = this;

        // Check the form
        // and if "Other" option is checked - make sure that something is entered in the text field
        var booSubmitForm = !thisWindow.transferFromOtherField.isVisible() || (thisWindow.transferFromOtherField.isVisible() && thisWindow.transferFromOtherField.isValid());
        if (!thisWindow.formPanel.getForm().isValid()) {
            booSubmitForm = false;
        }

        if (booSubmitForm) {
            thisWindow.formPanel.getForm().submit({
                url:              baseUrl + '/clients/accounting/mark-as-paid',
                waitMsg:          _('Saving...'),
                clientValidation: false,

                success: function () {
                    Ext.simpleConfirmation.success(_('Done!'));

                    thisWindow.clientAccountingPanel.reloadClientAccounting();
                    thisWindow.close();
                },

                failure: function (form, action) {
                    var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                    Ext.simpleConfirmation.error(msg);
                }
            });
        }
    }
});