var AccountingFeesDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    // Is used to check if this is the first row which cannot be deleted
    this.currentRowsCount = 0;

    // Filter an "empty" record from the list
    var arrSavedTemplates = [];
    Ext.each(arrApplicantsSettings.accounting.arrSavedPayments, function (oSavedTemplate) {
        if (!empty(oSavedTemplate.saved_payment_template_id)) {
            arrSavedTemplates.push(oSavedTemplate)
        }
    });

    this.advancedOptionsPanel = new Ext.Container({
        style: 'margin-bottom: 20px;',
        hidden: true,
        items: [
            {
                xtype: 'container',
                layout: 'column',
                height: 37, // need this to make sure that next radio will not jump up when we'll hide the combo
                items: [
                    {
                        xtype: 'container',
                        style: 'margin: 7px 10px 0 0;',
                        items: {
                            boxLabel: _('Select a Payment Schedule Template'),
                            name: 'schedule_type_radio',
                            xtype: 'radio',
                            value: 'template',
                            checked: true,
                            listeners: {
                                check: function (radio, booChecked) {
                                    if (booChecked) {
                                        thisWindow.switchPaymentPanels(true);
                                    }
                                }
                            }
                        }
                    }, {
                        xtype: 'combo',
                        name: 'schedule_template_combo',
                        store: new Ext.data.Store({
                            data: arrSavedTemplates,
                            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                                {name: 'saved_payment_template_id'},
                                {name: 'name'}
                            ]))
                        }),
                        mode: 'local',
                        valueField: 'saved_payment_template_id',
                        displayField: 'name',
                        triggerAction: 'all',
                        lazyRender: true,
                        typeAhead: true,
                        forceSelection: true,
                        width: 350,

                        listeners: {
                            select: this.applyRecordsFromTemplate.createDelegate(this)
                        }
                    }
                ]
            }, {
                name: 'schedule_type_radio',
                xtype: 'radio',
                value: 'recurring_plan',
                boxLabel: _('Recurring Payment Plan'),
                listeners: {
                    check: function (radio, booChecked) {
                        if (booChecked) {
                            thisWindow.switchPaymentPanels(false);
                        }
                    }
                }
            }
        ]
    });

    this.recurringPaymentsFormPanel = new Ext.FormPanel({
        bodyStyle: 'padding: 5px;',
        layout: 'column',
        width: 1080,
        hidden: true,
        defaults: {
            xtype: 'container',
            layout: 'form',
            labelAlign: 'top',
            style: {
                'padding-right': '10px'
            }
        },

        items: [
            {
                items: {
                    // Amount
                    name: 'amount',
                    fieldLabel: _('Amount') + ' (' + thisWindow.caseTACurrencySymbol + ')',
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'normalnumber',
                    style: 'text-align: right',
                    forceDecimalPrecision: true,
                    decimalPrecision: 2,
                    width: 120,
                    maxLength: 16,
                    minValue: 0.01,
                    allowBlank: false,
                    emptyText: '0.00'
                }
            }, {
                items: {
                    // Tax
                    name: 'tax',
                    fieldLabel: _('Tax'),
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'combo',
                    store: new Ext.data.SimpleStore({
                        fields: ['province_id', 'province'],
                        data: empty(arrApplicantsSettings.accounting.arrProvinces) ? [
                            [0, 'Exempt']
                        ] : arrApplicantsSettings.accounting.arrProvinces
                    }),
                    displayField: 'province',
                    valueField: 'province_id',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    selectOnFocus: false,
                    editable: false,
                    grow: true,
                    value: 0,
                    width: 220,
                    allowBlank: false
                }
            }, {
                items: {
                    // Description
                    name: 'description',
                    fieldLabel: _('Description'),
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'textfield',
                    allowBlank: false,
                    width: 290
                }
            }, {
                items: {
                    // Starting On Date
                    name: 'start',
                    fieldLabel: _('Starting On'),
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'datefield',
                    width: 140,
                    allowBlank: false,
                    format: dateFormatFull,
                    emptyText: 'mm/dd/yyyy',
                    maxLength: 12, // Fix issue with date entering in 'full' format
                    altFormats: dateFormatFull + '|' + dateFormatShort
                }
            }, {
                items: {
                    // Number of payments
                    name: 'number_of_payments',
                    fieldLabel: _('Number of payments'),
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'spinnerfield',
                    incrementValue: 1,
                    width: 150,
                    maxValue: 99,
                    minValue: 1,
                    allowDecimals: false,
                    allowBlank: false
                }
            }, {
                items: {
                    // Frequency
                    name: 'frequency',
                    fieldLabel: _('Every'),
                    labelSeparator: '',
                    labelStyle: 'font-weight: 500;',
                    xtype: 'combo',
                    store: new Ext.data.SimpleStore({
                        fields: ['value', 'display'],
                        data: [
                            [1, 'Month'],
                            [2, 'Two months'],
                            [3, 'Three months'],
                            [4, 'Four months'],
                            [6, 'Six months'],
                            [12, 'Year']
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
                    width: 150,
                    allowBlank: false
                }
            }, {
                xtype: 'box',
                'autoEl': {
                    'tag': 'a',
                    'href': '#',
                    'style': 'margin: 0 0 20px 5px',
                    'class': 'blulinkun12',
                    'html': _('Preview')
                }, // Thanks to IE - we need to use quotes...

                listeners: {
                    scope: this,
                    render: function (c) {
                        c.getEl().on('click', function () {
                            thisWindow.previewRecurringPaymentPlan(c);
                        }, this, {stopEvent: true});
                    }
                }
            }
        ]
    });

    this.simplePaymentsFormPanel = new Ext.FormPanel({
        bodyStyle: 'padding: 5px;',
        items: []
    });

    this.addRowButton = new Ext.Button({
        text: '<i class="las la-plus"></i>' + _('Add row'),
        style: 'margin-bottom: 20px',
        handler: this.addRow.createDelegate(thisWindow, [false])
    });

    this.advancedOptionsButton = new Ext.Button({
        text: _('Advanced options'),
        hidden: !thisWindow.booPrimaryTA,
        booChecked: false,
        handler: this.showAdvancedOptions.createDelegate(thisWindow)
    });

    this.cancelButton = new Ext.Button({
        text: _('Cancel'),
        handler: function () {
            thisWindow.close();
        }
    });

    this.assignAccountButton = new Ext.Button({
        text: _('Add to the Case'),
        cls: 'orange-btn',
        handler: this.saveFees.createDelegate(thisWindow)
    });

    AccountingFeesDialog.superclass.constructor.call(this, {
        title: '<i class="las la-plus"></i>' + _('Fees or Disbursements'),
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: {
            xtype: 'container',
            style: 'min-height: 250px',
            items: [
                this.advancedOptionsPanel,
                this.recurringPaymentsFormPanel,
                this.simplePaymentsFormPanel,
                this.addRowButton
            ]
        },

        bbar: new Ext.Toolbar({
            cls: 'no-bbar-borders',
            items: [this.advancedOptionsButton, '->', this.cancelButton, this.assignAccountButton]
        })
    });

    this.on('render', this.addRow.createDelegate(this, [true]), this);
};

Ext.extend(AccountingFeesDialog, Ext.Window, {
    switchPaymentPanels: function (booShowSimplePanel) {
        this.simplePaymentsFormPanel.setVisible(booShowSimplePanel);
        this.advancedOptionsPanel.find('name', 'schedule_template_combo')[0].setVisible(booShowSimplePanel);
        this.addRowButton.setVisible(booShowSimplePanel);

        this.recurringPaymentsFormPanel.setVisible(!booShowSimplePanel);
        this.recurringPaymentsFormPanel.getForm().clearInvalid();
    },

    applyRecordsFromTemplate: function (combo, r, index) {
        var thisWindow = this;
        thisWindow.currentRowsCount = 0;
        thisWindow.simplePaymentsFormPanel.removeAll();

        Ext.each(combo.getStore().reader.jsonData[index].payments, function (oPaymentRow, index) {
            thisWindow.addRow(empty(index), oPaymentRow);
        });
    },

    showAdvancedOptions: function (btn) {
        btn.booChecked = !btn.booChecked;
        if (!btn.booChecked) {
            var arrRadios = this.advancedOptionsPanel.find('name', 'schedule_type_radio');
            var templatesRadio = arrRadios[0];
            var recurringRadio = arrRadios[1];

            if (!templatesRadio.getValue()) {
                templatesRadio.setValue(true);
                recurringRadio.setValue(false);
            }
        }

        this.advancedOptionsPanel.setVisible(btn.booChecked);
        btn.setText(btn.booChecked ? _('Basic options') : _('Advanced options'));
    },

    addRow: function (booCreateHeaders, oPaymentRow) {
        var thisWindow = this;

        if (booCreateHeaders) {
            var helpIcon = empty(arrApplicantsSettings.accounting.scheduleHelpMessage) ? '' : String.format(
                _("<i class='las la-question-circle help-icon' ext:qtip='{0}' ext:qwidth='400' style='cursor: help; margin-left: 10px'></i>"),
                arrApplicantsSettings.accounting.scheduleHelpMessage.replaceAll("'", "\'")
            );

            thisWindow.simplePaymentsFormPanel.add({
                xtype: 'container',
                layout: 'column',

                defaults: {
                    // implicitly create Container by specifying xtype
                    xtype: 'label',
                    autoEl: 'div',
                    style: {
                        'font-size': '14px',
                        'font-weight': '500',
                        'margin-bottom': '4px'
                    }
                },

                items: [
                    {
                        html: '&nbsp;',
                        width: 45
                    }, {
                        text: _('Amount') + ' (' + thisWindow.caseTACurrencySymbol + ')',
                        width: 115
                    }, {
                        text: _('Tax'),
                        width: 220
                    }, {
                        text: _('Description'),
                        width: 285
                    }, {
                        html: _('Due On') + helpIcon
                    }
                ]
            });
        }


        thisWindow.currentRowsCount += 1;

        var basedOnContainerId = Ext.id();
        var basedOnDefaultValue = empty(oPaymentRow) || empty(oPaymentRow.type) ? 'date' : oPaymentRow.type;

        thisWindow.simplePaymentsFormPanel.add({
            xtype: 'container',
            layout: 'column',
            cls: 'fees_rows_container',
            width: thisWindow.booPrimaryTA ? 1020 : 800,
            defaults: {
                xtype: 'container',
                layout: 'form',
                style: {
                    'padding-right': '10px'
                }
            },

            items: [
                {
                    xtype: 'button',
                    cls: 'applicant-advanced-search-hide-row',
                    text: '<i class="las la-times"></i>',
                    handler: this.removeRow.createDelegate(this)
                }, {
                    items: {
                        // Amount
                        name: 'amount',
                        hideLabel: true,
                        xtype: 'normalnumber',
                        style: 'text-align: right',
                        forceDecimalPrecision: true,
                        decimalPrecision: 2,
                        width: 125,
                        maxLength: 16,
                        minValue: 0.01,
                        allowBlank: false,
                        emptyText: '0.00',
                        listeners: {
                            render: function (oField) {
                                if (!empty(oPaymentRow)) {
                                    oField.setValue(oPaymentRow.amount);
                                }
                            }
                        }
                    }
                }, {
                    items: {
                        // Tax
                        name: 'tax',
                        hideLabel: true,
                        xtype: 'combo',
                        store: new Ext.data.SimpleStore({
                            fields: ['province_id', 'province'],
                            data: empty(arrApplicantsSettings.accounting.arrProvinces) ? [
                                [0, 'Exempt']
                            ] : arrApplicantsSettings.accounting.arrProvinces
                        }),
                        displayField: 'province',
                        valueField: 'province_id',
                        typeAhead: false,
                        mode: 'local',
                        triggerAction: 'all',
                        selectOnFocus: false,
                        editable: false,
                        grow: true,
                        value: empty(oPaymentRow) ? 0 : oPaymentRow.tax_id,
                        width: 220,
                        allowBlank: false
                    }
                }, {
                    items: {
                        // Description
                        name: 'description',
                        hideLabel: true,
                        xtype: 'textfield',
                        value: empty(oPaymentRow) ? '' : oPaymentRow.description,
                        allowBlank: false,
                        width: 290
                    }
                }, {
                    items: {
                        // Due On / Type
                        name: 'type',
                        hideLabel: true,
                        xtype: 'combo',
                        store: new Ext.data.SimpleStore({
                            fields: ['due_on', 'label'],
                            data: [
                                ['date', _('Date')],
                                ['profile_date', _('Case Date Field')],
                                ['file_status', arrApplicantsSettings.file_status_label]
                            ]
                        }),
                        displayField: 'label',
                        valueField: 'due_on',
                        typeAhead: false,
                        mode: 'local',
                        triggerAction: 'all',
                        selectOnFocus: false,
                        editable: false,
                        grow: true,
                        value: basedOnDefaultValue,
                        width: 155,
                        allowBlank: false,
                        hidden: !thisWindow.booPrimaryTA,

                        listeners: {
                            afterrender: function (combo) {
                                var index = combo.store.find(combo.valueField, basedOnDefaultValue);
                                var record = combo.store.getAt(index);
                                if (record) {
                                    combo.fireEvent('beforeselect', combo, record, basedOnDefaultValue);
                                    combo.setValue(basedOnDefaultValue);
                                }
                            },
                            beforeselect: thisWindow.switchBasedOnFields.createDelegate(this, [basedOnContainerId], true)
                        }
                    }
                }, {
                    id: basedOnContainerId,
                    style: 'padding-right: 0',
                    items: [
                        {
                            // Date
                            name: 'date',
                            hideLabel: true,
                            xtype: 'datefield',
                            width: 140,
                            allowBlank: false,
                            format: dateFormatFull,
                            maxLength: 12, // Fix issue with date entering in 'full' format
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            emptyText: 'mm/dd/yyyy',
                            value: empty(oPaymentRow) || empty(oPaymentRow.due_date) || basedOnDefaultValue !== 'date' ? null : new Date(oPaymentRow.due_date)
                        }, {
                            // Case Date Field
                            name: 'profile_date',
                            hideLabel: true,
                            xtype: 'combo',
                            store: new Ext.data.Store({
                                data: thisWindow.arrCaseDateFields,
                                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                                    {name: 'cId'},
                                    {name: 'cName'}
                                ]))
                            }),
                            displayField: 'cName',
                            valueField: 'cId',
                            emptyText: _('Please select...'),
                            mode: 'local',
                            triggerAction: 'all',
                            lazyRender: true,
                            forceSelection: true,
                            allowBlank: false,
                            editable: false,
                            width: 200,
                            listWidth: 200,
                            typeAhead: true,
                            value: empty(oPaymentRow) || empty(oPaymentRow.due_on_id) || basedOnDefaultValue !== 'profile_date' ? null : oPaymentRow.due_on_id
                        }, {
                            // Case Status Field
                            name: 'file_status',
                            hideLabel: true,
                            xtype: 'combo',
                            store: new Ext.data.Store({
                                data: thisWindow.arrCaseStatusFields,
                                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                                    {name: 'cId'},
                                    {name: 'cName'}
                                ]))
                            }),
                            displayField: 'cName',
                            valueField: 'cId',
                            emptyText: _('Please select...'),
                            mode: 'local',
                            triggerAction: 'all',
                            lazyRender: true,
                            forceSelection: true,
                            allowBlank: false,
                            editable: false,
                            width: 200,
                            listWidth: 200,
                            typeAhead: true,
                            value: empty(oPaymentRow) || empty(oPaymentRow.due_on_id) || basedOnDefaultValue !== 'file_status' ? null : oPaymentRow.due_on_id
                        }
                    ]
                }
            ]
        });

        thisWindow.simplePaymentsFormPanel.doLayout();

        setTimeout(function () {
            thisWindow.simplePaymentsFormPanel.getForm().clearInvalid();
        }, 50);
    },

    switchBasedOnFields: function (combo, record, index, containerId) {
        var booShowDateField = false;
        var booShowProfileDateField = false;
        var booShowCaseStatusField = false;
        switch (record.get(combo.valueField)) {
            case 'date':
                booShowDateField = true;
                break;

            case 'profile_date':
                booShowProfileDateField = true;
                break;

            case 'file_status':
                booShowCaseStatusField = true;
                break;

            default:
                break;
        }

        var container = Ext.getCmp(containerId);
        var dateField = container.items.get(0);
        var profileDateField = container.items.get(1);
        var fileStatusField = container.items.get(2);

        dateField.setVisible(booShowDateField);
        dateField.setDisabled(!booShowDateField);
        profileDateField.setVisible(booShowProfileDateField);
        profileDateField.setDisabled(!booShowProfileDateField);
        fileStatusField.setVisible(booShowCaseStatusField);
        fileStatusField.setDisabled(!booShowCaseStatusField);
    },

    removeRow: function (btn) {
        var thisWindow = this;
        if (thisWindow.currentRowsCount > 1) {
            thisWindow.currentRowsCount -= 1;

            btn.ownerCt.hide();
            btn.ownerCt.removeAll();
        }
    },

    saveFees: function () {
        var booRecurringPaymentChecked = this.advancedOptionsPanel.find('name', 'schedule_type_radio')[1].getValue();
        if (this.advancedOptionsButton.booChecked && booRecurringPaymentChecked) {
            // Save recurring payments if Recurring Payment Plan radio is selected and Advanced Options checkbox is checked
            this.saveRecurringFees();
        } else {
            // Otherwise save simple payments
            this.saveSimpleFees();
        }
    },

    saveSimpleFees: function () {
        var thisWindow = this;
        if (!thisWindow.simplePaymentsFormPanel.getForm().isValid()) {
            return;
        }

        // Prepare data
        var arrPaymentsInfo = [];
        thisWindow.simplePaymentsFormPanel.items.each(function (oContainer) {
            if (oContainer.initialConfig.cls == 'fees_rows_container' && oContainer.items.length) {
                var type = oContainer.find('name', 'type')[0].getValue();
                var dueOnId = 0;
                var dueDate = oContainer.find('name', 'date')[0].getValue();
                if (type != 'date') {
                    dueDate = '';
                    dueOnId = type == 'profile_date' ? oContainer.find('name', 'profile_date')[0].getValue() : oContainer.find('name', 'file_status')[0].getValue();
                } else {
                    if (empty(dueDate)) {
                        dueDate = '';
                    } else {
                        dueDate = Ext.util.Format.date(dueDate, Date.patterns.ISO8601Short);
                    }
                }

                arrPaymentsInfo.push({
                    'payment_id': 0,
                    'type': type,
                    'amount': oContainer.find('name', 'amount')[0].getValue(),
                    'tax_id': oContainer.find('name', 'tax')[0].getValue(),
                    'description': oContainer.find('name', 'description')[0].getValue(),
                    'due_on_id': dueOnId,
                    'due_date': dueDate
                });
            }
        });

        var errorMessage = '';
        if (empty(arrPaymentsInfo.length)) {
            errorMessage = _('Nothing to save. Please add at least one payment.');
        }

        if (!empty(errorMessage)) {
            Ext.simpleConfirmation.error(errorMessage);
            return;
        }

        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/save-payments',
            params: {
                arrPayments: Ext.encode(arrPaymentsInfo),
                booSaveTemplate: Ext.encode(false),
                saved_payment_template_id: 0,
                name: Ext.encode(''),
                member_id: thisWindow.caseId,
                ta_id: thisWindow.caseTAId,
                template_id: 0
            },

            success: function (result) {
                thisWindow.getEl().unmask();
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.caseId).reloadClientAccounting();

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        thisWindow.close();
                    }, 750);
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
                thisWindow.getEl().unmask();
            }
        });
    },

    saveRecurringFees: function () {
        var thisWindow = this;
        if (!thisWindow.recurringPaymentsFormPanel.getForm().isValid()) {
            return;
        }

        thisWindow.getEl().mask(_('Sending Information...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/add-wizard',
            params: {
                payments: thisWindow.recurringPaymentsFormPanel.find('name', 'number_of_payments')[0].getValue(),
                period: thisWindow.recurringPaymentsFormPanel.find('name', 'frequency')[0].getValue(),
                start: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'start')[0].getValue()),
                amount: thisWindow.recurringPaymentsFormPanel.find('name', 'amount')[0].getValue(),
                description: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'description')[0].getValue()),
                gst_province_id: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'tax')[0].getValue()),
                member_id: thisWindow.caseId,
                ta_id: thisWindow.caseTAId
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.caseId).reloadClientAccounting();

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    }, 750);
                } else {
                    // Show error message
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
            }
        });
    },

    previewRecurringPaymentPlan: function (link) {
        var thisWindow = this;
        if (!thisWindow.recurringPaymentsFormPanel.getForm().isValid()) {
            return;
        }

        if (!empty(thisWindow.previewTooltip) && thisWindow.previewTooltip.isVisible()) {
            thisWindow.previewTooltip.destroy();
            thisWindow.previewTooltip = null;
            return;
        }

        var currentlySelectedTax = ' (' + thisWindow.recurringPaymentsFormPanel.find('name', 'tax')[0].getRawValue() + ')';

        thisWindow.getEl().mask(_('Calculating...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/preview-recurring-payment-plan',
            params: {
                payments: thisWindow.recurringPaymentsFormPanel.find('name', 'number_of_payments')[0].getValue(),
                period: thisWindow.recurringPaymentsFormPanel.find('name', 'frequency')[0].getValue(),
                start: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'start')[0].getValue()),
                amount: thisWindow.recurringPaymentsFormPanel.find('name', 'amount')[0].getValue(),
                description: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'description')[0].getValue()),
                gst_province_id: Ext.encode(thisWindow.recurringPaymentsFormPanel.find('name', 'tax')[0].getValue())
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    thisWindow.getEl().unmask();

                    var strRows = '';
                    Ext.each(resultData.arrPayments, function (oPayment) {
                        strRows += String.format(
                            '<tr>' +
                            '<td style="padding-right: 10px">{0}</td>' +
                            '<td style="padding-right: 10px">{1}</td>' +
                            '<td style="padding-right: 10px">{2}</td>' +
                            '<td style="">{3}</td>' +
                            '</tr>',
                            oPayment.due_date,
                            oPayment.description,
                            formatMoney(thisWindow.caseTACurrency, oPayment.subtotal, true, false),
                            formatMoney(thisWindow.caseTACurrency, oPayment.gst, true, false) + currentlySelectedTax
                        );
                    });

                    var strTable = '<table>' +
                        '<tr>' +
                        '<th style="width: 115px; font-weight: 500">' + _('Due Date') + '</th>' +
                        '<th style="padding-right: 10px; font-weight: 500">' + _('Description') + '</th>' +
                        '<th style="padding-right: 10px; font-weight: 500">' + _('Amount') + ' (' + thisWindow.caseTACurrencySymbol + ')' + '</th>' +
                        '<th style="font-weight: 500">' + _('Tax') + ' (' + thisWindow.caseTACurrencySymbol + ')' + '</th>' +
                        '</tr>' +
                        strRows +
                        '</table>';

                    thisWindow.previewTooltip = new Ext.ToolTip({
                        autoWidth: true,
                        html: strTable,
                        cls: 'max_height_limited',
                        dismissDelay: 0,
                        anchor: 'top'
                    });

                    var arrCoords = link.getEl().getXY();
                    arrCoords[1] += 23;
                    thisWindow.previewTooltip.showAt(arrCoords);
                } else {
                    // Show error message
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
            }
        });
    }
});