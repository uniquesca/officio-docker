var AccountingFeesManageTemplatesDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    // Is used to check if this is the first row which cannot be deleted
    this.currentRowsCount = 0;

    this.advancedOptionsPanel = new Ext.FormPanel({
        style: 'margin-bottom: 20px',
        labelWidth: 395,
        items: [
            {
                xtype: 'combo',
                fieldLabel: _('Create a New or Edit an existing Payment Schedule Template'),
                labelStyle: 'padding-top: 10px; width: 395px;',
                ref: '../scheduleTemplateCombo',
                store: new Ext.data.Store({
                    data: arrApplicantsSettings.accounting.arrSavedPayments,
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
                editable: false,
                forceSelection: true,
                width: 350,

                listeners: {
                    select: this.applyRecordsFromTemplate.createDelegate(this),
                    render: function (combo) {
                        combo.setValue('');
                        combo.fireEvent('select', combo);
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
        style: 'margin-bottom: 25px',
        handler: this.addRow.createDelegate(thisWindow, [false])
    });

    this.deleteTemplateButton = new Ext.Button({
        text: _('Delete'),
        handler: this.deleteTemplate.createDelegate(thisWindow)
    });

    this.renameTemplateButton = new Ext.Button({
        text: _('Rename'),
        handler: this.renameTemplate.createDelegate(thisWindow)
    });

    this.saveTemplateAsButton = new Ext.Button({
        text: _('Save As'),
        handler: this.saveTemplateAs.createDelegate(thisWindow, [0])
    });

    this.cancelButton = new Ext.Button({
        text: _('Cancel'),
        handler: function () {
            thisWindow.close();
        }
    });

    this.saveTemplateButton = new Ext.Button({
        text: _('Save'),
        cls: 'orange-btn',
        ctCls: 'x-toolbar-cell-no-right-padding',
        handler: function () {
            var selectedTemplateId = thisWindow.scheduleTemplateCombo.getValue();
            if (empty(selectedTemplateId)) {
                thisWindow.saveTemplateAs(0, null, true);
            } else {
                thisWindow.saveTemplateChanges(selectedTemplateId, thisWindow.scheduleTemplateCombo.getRawValue(), null, true);
            }
        }
    });

    AccountingFeesManageTemplatesDialog.superclass.constructor.call(this, {
        title: '<i class="las la-list"></i>' + _('Edit Payment Schedule Template'),
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [
            this.advancedOptionsPanel,
            {
                xtype: 'container',
                style: 'min-height: 250px',
                items: [
                    this.simplePaymentsFormPanel,
                    this.addRowButton
                ]
            }
        ],

        bbar: new Ext.Toolbar({
            cls: 'no-bbar-borders',
            items: [this.deleteTemplateButton, this.renameTemplateButton, this.saveTemplateAsButton, '->', this.cancelButton, this.saveTemplateButton]
        })
    });

    this.on('render', this.addRow.createDelegate(this, [true]), this);
};

Ext.extend(AccountingFeesManageTemplatesDialog, Ext.Window, {
    toggleBottomButtons: function (booShow) {
        var thisWindow = this;
        thisWindow.deleteTemplateButton.setVisible(booShow);
        thisWindow.renameTemplateButton.setVisible(booShow);
        thisWindow.saveTemplateAsButton.setVisible(booShow);
    },

    applyRecordsFromTemplate: function (combo, r, index) {
        var thisWindow = this;
        var booIsNewTemplate = empty(index);
        thisWindow.toggleBottomButtons(!booIsNewTemplate);

        thisWindow.currentRowsCount = 0;
        thisWindow.simplePaymentsFormPanel.removeAll();

        if (booIsNewTemplate) {
            thisWindow.addRow(true);
        } else {
            Ext.each(combo.getStore().reader.jsonData[index].payments, function (oPaymentRow, index) {
                thisWindow.addRow(empty(index), oPaymentRow);
            });
        }
    },

    addRow: function (booCreateHeaders, oPaymentRow) {
        var thisWindow = this;

        if (booCreateHeaders) {
            var helpIcon = empty(arrApplicantsSettings.accounting.scheduleHelpMessage) ? '' : String.format(
                _("<i class='las la-question-circle help-icon' ext:qtip='{0}' ext:qwidth='400' style='cursor: help; margin-left: 10px; vertical-align: middle'></i>"),
                arrApplicantsSettings.accounting.scheduleHelpMessage.replaceAll("'", "\'")
            );

            thisWindow.simplePaymentsFormPanel.add({
                xtype: 'container',
                layout: 'column',

                defaults: {
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
                        // "Due On" / Type
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
                            allowBlank: true,
                            format: dateFormatFull,
                            maxLength: 12, // Fix issue with date entering in 'full' format
                            altFormats: dateFormatFull + '|' + dateFormatShort,
                            emptyText: 'mm/dd/yyyy',
                            value: empty(oPaymentRow) || empty(oPaymentRow.due_date) || basedOnDefaultValue !== 'date' ? null : Date.parseDate(oPaymentRow.due_date, Date.patterns.ISO8601Short)
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

    saveTemplateChanges: function (templateId, templateName, arrPaymentsInfo, booCloseDialog) {
        var thisWindow = this;

        if (empty(arrPaymentsInfo)) {
            if (!thisWindow.simplePaymentsFormPanel.getForm().isValid()) {
                return;
            }

            // Prepare the data
            arrPaymentsInfo = [];
            thisWindow.simplePaymentsFormPanel.items.each(function (oContainer) {
                if (oContainer.initialConfig.cls == 'fees_rows_container') {
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
        }

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
                booSaveTemplate: Ext.encode(true),
                saved_payment_template_id: templateId,
                name: Ext.encode(templateName),
                member_id: thisWindow.caseId,
                ta_id: thisWindow.caseTAId,
                template_id: 0
            },

            success: function (result) {
                thisWindow.getEl().unmask();
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    var combo = thisWindow.scheduleTemplateCombo;
                    combo.getStore().loadData(resultData.saved_payments);
                    combo.setValue(resultData.saved_payment_template_id);

                    refreshSettings('accounting');

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        if (booCloseDialog) {
                            thisWindow.close();
                        } else {
                            thisWindow.getEl().unmask();
                        }
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

    deleteTemplate: function () {
        var thisWindow = this;
        var templatesCombo = thisWindow.scheduleTemplateCombo;

        var question = String.format(
            _('Are you sure you want to delete the template "{0}"?'),
            templatesCombo.getRawValue()
        );

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn == 'yes') {
                //send request
                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/remove-payment-template',
                    params: {
                        saved_payment_template_id: templatesCombo.getValue()
                    },
                    success: function (result, request) {
                        var resultData = Ext.decode(result.responseText);
                        if (!resultData.success) {
                            Ext.simpleConfirmation.error(resultData.message);
                        } else {
                            //clear info about item
                            templatesCombo.getStore().removeAt(getComboBoxIndex(templatesCombo));
                            templatesCombo.clearValue();
                            templatesCombo.setValue(0);
                            templatesCombo.fireEvent('select', templatesCombo);

                            refreshSettings('accounting');

                            thisWindow.getEl().mask(_('Done!'));
                            setTimeout(function () {
                                thisWindow.getEl().unmask();
                            }, 750);
                        }
                    }, failure: function (form, action) {
                        Ext.simpleConfirmation.error(_('Cannot remove the template. Please try again later.'));
                    }
                });
            }
        });
    },

    saveTemplateAs: function (templateId, arrPaymentsInfo, booCloseDialog) {
        var thisWindow = this;

        if (empty(arrPaymentsInfo)) {
            if (!thisWindow.simplePaymentsFormPanel.getForm().isValid()) {
                return;
            }
        }

        var win = new Ext.Window({
            title: _('Payment Schedule Template'),
            layout: 'form',
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,
            labelAlign: 'top',
            bodyStyle: 'padding: 10px 10px 0;',

            items: {
                fieldLabel: _('Please enter a template name'),
                xtype: 'textfield',
                ref: '../templateNameField',
                width: 360,
                enableKeyEvents: true,
                listeners: {
                    keyup: function (field, e) {
                        if (e.getKey() == Ext.EventObject.ENTER) {
                            win.saveTemplateNameButton.handler.call(win.saveTemplateNameButton.scope);
                        }
                    }
                }
            },

            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    cls: 'orange-btn',
                    text: _('OK'),
                    ref: '../saveTemplateNameButton',

                    handler: function () {
                        var templateName = trim(win.templateNameField.getValue());

                        if (empty(templateName)) {
                            Ext.simpleConfirmation.error('Template name cannot be empty.');
                            return false;
                        }

                        thisWindow.saveTemplateChanges(templateId, templateName, arrPaymentsInfo, booCloseDialog);
                        win.close();
                    }
                }
            ]
        });

        win.show();
        win.center();
    },

    renameTemplate: function () {
        var store = this.scheduleTemplateCombo.getStore();
        var value = this.scheduleTemplateCombo.getValue();
        var index = store.find(this.scheduleTemplateCombo.valueField, value);

        this.saveTemplateAs(value, store.reader.jsonData[index].payments);
    }
});