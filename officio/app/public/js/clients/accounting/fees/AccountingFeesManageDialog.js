var AccountingFeesManageDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);
    var thisWindow = this;
    var booAddNew = empty(config.oRecord.id);

    this.paymentRecordId = new Ext.form.Hidden({
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

    this.dueOnDateField = new Ext.form.DateField({
        fieldLabel: _('Due On'),
        labelStyle: 'font-weight: 500',
        name: 'due_date',
        width: 150,
        allowBlank: false,
        style: 'margin-bottom: 5px;',
        value: !booAddNew ? new Date(config.oRecord.data['due_date']) : null,
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort,
        maxLength: 12 // Fix issue with date entering in 'full' format
    });

    this.formPanel = new Ext.form.FormPanel({
        bodyStyle: 'padding: 5px',
        labelAlign: 'top',
        layout: 'column',
        // width: 970,

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
                items: this.paymentRecordId
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
                style: 'padding-right: 0',
                items: this.dueOnDateField
            }
        ]
    });

    AccountingFeesManageDialog.superclass.constructor.call(this, {
        title: booAddNew ? '<i class="las la-plus"></i>' + _('Add Fee or Disbursement') : '<i class="las la-edit"></i>' + _('Edit Fee or Disbursement'),
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        modal: true,

        items: [this.formPanel],

        buttons: [
            {
                text: thisWindow.booShowUpdateButton ? _('Cancel') : _('Close'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Delete'),
                hidden: !thisWindow.booShowDeleteButton,
                handler: this.deleteFeePayment.createDelegate(this)
            }, {
                text: _('Update'),
                cls: 'orange-btn',
                hidden: !thisWindow.booShowUpdateButton,
                handler: this.updateFeePayment.createDelegate(this)
            }
        ]
    });

    this.on('show', function () {
        thisWindow.formPanel.getForm().clearInvalid();
    });
};

Ext.extend(AccountingFeesManageDialog, Ext.Window, {
    updateFeePayment: function () {
        var thisWindow = this;

        if (!thisWindow.formPanel.getForm().isValid()) {
            return;
        }

        thisWindow.getEl().mask(_('Saving...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/update-fee',
            loadMask: true,
            params: {
                payment_id: Ext.encode(thisWindow.paymentRecordId.getValue()),
                member_id: Ext.encode(thisWindow.caseId),
                ta_id: Ext.encode(thisWindow.caseTAId),
                amount: Ext.encode(thisWindow.amountField.getValue()),
                description: Ext.encode(thisWindow.descriptionField.getValue()),
                date: Ext.encode(thisWindow.dueOnDateField.getValue()),
                gst_province_id: Ext.encode(thisWindow.taxCombo.getValue())
            },

            success: function (result) {
                thisWindow.getEl().unmask();

                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisWindow.owner.owner.reloadClientAccounting();

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

    deleteFeePayment: function () {
        var thisWindow = this;
        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete this fee?'), function (btn) {
            if (btn == 'yes') {

                thisWindow.getEl().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/delete-fee',
                    params: {
                        payment_id: Ext.encode(thisWindow.paymentRecordId.getValue()),
                        member_id: Ext.encode(thisWindow.caseId),
                        ta_id: Ext.encode(thisWindow.caseTAId)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisWindow.getEl().mask(_('Done!'));

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
                        Ext.simpleConfirmation.error(_('Can\'t delete this payment'));
                    }
                });
            }
        });
    }
});