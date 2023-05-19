var ManageScheduleRecordDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);
    var thisWindow = this;
    var booAddNew = empty(config.oRecord.id);

    this.arrDataRecordFields = [
        {name: 'payment_id', type: 'int'},
        {name: 'type', type: 'string'},
        {name: 'amount', type: 'float'},
        {name: 'tax_id', type: 'int'},
        {name: 'description', type: 'string'},
        {name: 'due_on_id', type: 'int'},
        {name: 'due_date', type: 'date'}
    ];

    this.AssignPaymentInfo = Ext.data.Record.create(thisWindow.arrDataRecordFields);

    this.scheduleExjsRecordId = new Ext.form.Hidden({
        value: booAddNew ? 0 : config.oRecord.id
    });

    this.scheduleRecordPaymentId = new Ext.form.Hidden({
        value: booAddNew ? 0 : config.oRecord.data['payment_id']
    });

    this.amountField = new Ext.ux.form.NormalNumberField({
        fieldLabel: _('Amount'),
        labelStyle: 'font-weight: 500',
        labelSeparator: '',
        name: 'amount',
        width: 120,
        style: 'text-align: right',
        forceDecimalPrecision: true,
        decimalPrecision: 2,
        emptyText: '0.00',
        allowBlank: false,
        allowNegative: true,
        listeners: {
            render: function (oField) {
                if (!booAddNew) {
                    oField.setValue(parseFloat(config.oRecord.data['amount']));
                }
            }
        }
    });

    this.taxCombo = new Ext.form.ComboBox({
        fieldLabel: _('Tax'),
        labelStyle: 'font-weight: 500',
        labelSeparator: '',
        name: 'tax_id',
        allowBlank: false,
        store: new Ext.data.SimpleStore({
            fields: ['province_id', 'province'],
            data: empty(thisWindow.arrProvinces) || empty(thisWindow.arrProvinces.length) ? [
                [0, 'Exempt']
            ] : thisWindow.arrProvinces
        }),
        displayField: 'province',
        valueField: 'province_id',
        mode: 'local',
        triggerAction: 'all',
        width: 220,
        lazyRender: true,
        forceSelection: true,
        editable: false,
        typeAhead: true,
        value: booAddNew ? 0 : config.oRecord.data['tax_id']
    });

    this.descriptionField = new Ext.form.TextField({
        fieldLabel: _('Description'),
        labelStyle: 'font-weight: 500',
        labelSeparator: '',
        name: 'description',
        allowBlank: false,
        width: 290,
        value: booAddNew ? null : config.oRecord.data['description']
    });

    this.dueOnCombo = new Ext.form.ComboBox({
        fieldLabel: _('Due On'),
        labelStyle: 'font-weight: 500',
        labelSeparator: '',
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
        value: config.oRecord.data['type'],
        width: 155,
        allowBlank: false,

        listeners: {
            afterrender: function (combo) {
                var index = combo.store.find(combo.valueField, config.oRecord.data['type']);
                var record = combo.store.getAt(index);
                if (record) {
                    combo.fireEvent('beforeselect', combo, record, config.oRecord.data['type']);
                    combo.setValue(config.oRecord.data['type']);
                }
            },
            beforeselect: thisWindow.toggleDueOnFields.createDelegate(this)
        }
    });

    this.dueOnDateField = new Ext.form.DateField({
        name: 'due_date',
        width: 150,
        allowBlank: false,
        style: 'margin-bottom: 5px;',
        value: !booAddNew && config.oRecord.data['type'] == 'date' ? new Date(config.oRecord.data['due_date']) : null,
        disabled: !booAddNew && config.oRecord.data['type'] != 'date',
        hidden: !booAddNew && config.oRecord.data['type'] != 'date',
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort,
        maxLength: 12 // Fix issue with date entering in 'full' format
    });

    this.dueOnCaseDateField = new Ext.form.ComboBox({
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
        disabled: config.oRecord.data['type'] != 'profile_date',
        hidden: config.oRecord.data['type'] != 'profile_date',
        value: config.oRecord.data['type'] == 'profile_date' ? config.oRecord.data['due_on_id'] : null,
        forceSelection: true,
        allowBlank: false,
        editable: false,
        width: 200,
        style: 'margin-bottom: 5px;',
        typeAhead: true
    });

    this.dueOnCaseStatusField = new Ext.form.ComboBox({
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
        disabled: config.oRecord.data['type'] != 'file_status',
        hidden: config.oRecord.data['type'] != 'file_status',
        value: config.oRecord.data['type'] == 'file_status' ? config.oRecord.data['due_on_id'] : null,
        forceSelection: true,
        allowBlank: false,
        editable: false,
        width: 200,
        style: 'margin-bottom: 5px;',
        typeAhead: true
    });

    this.formPanel = new Ext.form.FormPanel({
        bodyStyle: 'padding: 5px',
        labelAlign: 'top',
        layout: 'column',
        width: 970,

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
                layout: 'form',
                items: this.scheduleExjsRecordId
            }, {
                layout: 'form',
                items: this.scheduleRecordPaymentId
            }, {
                layout: 'form',
                items: this.amountField
            }, {
                layout: 'form',
                items: this.taxCombo
            }, {
                layout: 'form',
                items: this.descriptionField
            }, {
                layout: 'form',
                items: this.dueOnCombo
            }, {
                style: 'padding-right: 0',
                items: [
                    this.dueOnDateField,
                    this.dueOnCaseDateField,
                    this.dueOnCaseStatusField
                ]
            }
        ]
    });

    ManageScheduleRecordDialog.superclass.constructor.call(this, {
        title: '<i class="las la-plus"></i>' + (booAddNew ? _('Add Fee or Disbursement') : _('Edit Fee or Disbursement')),
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        modal: true,

        items: [this.formPanel],

        buttons: [
            {
                text:  thisWindow.booShowUpdateButton ? _('Cancel') : _('Close'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Delete'),
                hidden: !thisWindow.booShowDeleteButton,
                handler: this.deleteSchedulePayment.createDelegate(this)
            }, {
                text: booAddNew ? _('Add') : _('Update'),
                hidden: !thisWindow.booShowUpdateButton,
                cls: 'orange-btn',
                handler: this.createUpdateSchedulePayment.createDelegate(this)
            }
        ]
    });

    this.on('show', function () {
        thisWindow.formPanel.getForm().clearInvalid();
    });
};

Ext.extend(ManageScheduleRecordDialog, Ext.Window, {
    toggleDueOnFields: function (combo, record) {
        var thisWindow = this;
        var booShowDateField = false;
        var booShowProfileDateField = false;
        var booShowCaseStatusField = false;

        switch (record.data[combo.valueField]) {
            case 'file_status':
                booShowCaseStatusField = true;
                break;

            case 'profile_date':
                booShowProfileDateField = true;
                break;

            default:
                booShowDateField = true;
                break;
        }

        thisWindow.dueOnDateField.setVisible(booShowDateField);
        thisWindow.dueOnDateField.setDisabled(!booShowDateField);
        thisWindow.dueOnCaseDateField.setVisible(booShowProfileDateField);
        thisWindow.dueOnCaseDateField.setDisabled(!booShowProfileDateField);
        thisWindow.dueOnCaseStatusField.setVisible(booShowCaseStatusField);
        thisWindow.dueOnCaseStatusField.setDisabled(!booShowCaseStatusField);
    },

    createUpdateSchedulePayment: function () {
        var thisWindow = this;

        if (!thisWindow.formPanel.getForm().isValid()) {
            return;
        }

        var dueOnType = thisWindow.dueOnCombo.getValue();
        var dueOnId = 0;
        if (dueOnType == 'profile_date') {
            dueOnId = thisWindow.dueOnCaseDateField.getValue();
        } else if (dueOnType == 'file_status') {
            dueOnId = thisWindow.dueOnCaseStatusField.getValue();
        }

        var p = new thisWindow.AssignPaymentInfo({
            payment_id: thisWindow.scheduleRecordPaymentId.getValue(),
            type: dueOnType,
            amount: thisWindow.amountField.getValue(),
            tax_id: thisWindow.taxCombo.getValue(),
            description: thisWindow.descriptionField.getValue(),
            due_on_id: dueOnId,
            due_date: thisWindow.dueOnDateField.getValue()
        });

        if (thisWindow.booUseDefaultSaveHandler) {
            thisWindow.savePSRecord(p);
        } else {
            var store = thisWindow.owner.templates_grid.getStore();
            var extjsRecordId = thisWindow.scheduleExjsRecordId.getValue();
            if (empty(extjsRecordId)) {
                store.insert(store.getCount(), p);
            } else {
                var rec = store.getById(extjsRecordId);

                if (!empty(rec)) {
                    rec.data = p.data;
                    rec.commit();
                }
            }

            thisWindow.close();
        }
    },

    savePSRecord: function (rec) {
        var thisWindow = this;

        thisWindow.getEl().mask(_('Saving...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/save-payment',
            loadMask: true,
            params: {
                mode: 'edit',
                member_id: thisWindow.caseId,
                ta_id: thisWindow.caseTAId,
                payment_schedule_id: rec.data.payment_id,
                amount: rec.data.amount,
                description: Ext.encode(rec.data.description),
                based_on: rec.data.due_on_id,
                type: Ext.encode(rec.data.type),
                based_date: Ext.encode(rec.data.due_date),
                gst_province_id: Ext.encode(rec.data.tax_id)
            },

            success: function (result) {
                thisWindow.getEl().unmask();

                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisWindow.owner.reloadFeesList();

                    thisWindow.close();
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
                thisWindow.getEl().unmask();
            }
        });
    },

    deleteSchedulePayment: function () {
        var thisWindow = this;
        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete this payment?'), function (btn) {
            if (btn == 'yes') {

                thisWindow.getEl().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/delete-payment',
                    params: {
                        payment_id: thisWindow.scheduleRecordPaymentId.getValue(),
                        member_id: thisWindow.caseId,
                        ta_id: thisWindow.caseTAId
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisWindow.getEl().mask('Done!');

                            thisWindow.owner.reloadFeesList();

                            setTimeout(function () {
                                thisWindow.getEl().unmask();
                                thisWindow.close();
                            }, 750);
                        } else {
                            thisWindow.getEl().unmask();
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function () {
                        thisWindow.getEl().unmask();
                        Ext.simpleConfirmation.error('Can\'t delete this payment');
                    }
                });
            }
        });
    }
});