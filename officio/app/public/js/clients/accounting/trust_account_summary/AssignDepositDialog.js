var AssignDepositDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    this.AssignDepositForm = new Ext.FormPanel({
        bodyStyle: 'padding:5px',
        labelWidth: 120,

        items: [{
            xtype: 'combo',
            fieldLabel: _('Transaction'),
            emptyText: _('Please select...'),
            ref: 'transactionIdCombo',

            store: {
                xtype: 'store',
                data: [],
                reader: new Ext.data.JsonReader(
                    {id: 0},
                    Ext.data.Record.create([
                        {
                            name: 'transaction_id'
                        }, {
                            name: 'transaction_description'
                        }, {
                            name: 'transaction_can_be_selected',
                            type: 'boolean'
                        }
                    ])
                )
            },

            mode: 'local',
            valueField: 'transaction_id',
            displayField: 'transaction_description',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            allowBlank: false,
            typeAhead: true,
            selectOnFocus: true,
            searchContains: true,
            width: 540,

            listeners: {
                render: {
                    buffer: 50, // We need this delay - to remove "marked as invalid"
                    fn: function (combo) {
                        combo.clearInvalid();
                    }
                },

                beforeselect: function (combo, rec) {
                    thisWindow.verifyButton.setDisabled(!rec.data['transaction_can_be_selected']);
                    thisWindow.AssignDepositForm.noEntryMessage.setVisible(!rec.data['transaction_can_be_selected']);
                    if (!rec.data['transaction_can_be_selected']) {
                        thisWindow.AssignDepositForm.noEntryMessage.setValue(
                            _("The selected deposit amount doesn't match the Deposit Reminder of ") + thisWindow.AssignDepositForm.depositAmountValue.getValue()
                        );
                    }
                }
            }
        }, {
            ref: 'noEntryMessage',
            xtype: 'displayfield',
            style: 'color: red; padding: 0 0 15px;',
            value: _('No available bank deposits to assign.')
        }, {
            ref: 'depositAmountValue',
            xtype: 'hidden'
        }, {
            xtype: 'container',
            layout: 'column',
            width: 670,
            items: [{
                xtype: 'container',
                layout: 'form',
                items: {
                    xtype: 'combo',
                    fieldLabel: _('Template'),
                    emptyText: _('Please select...'),
                    ref: '../../templatesCombo',

                    store: {
                        xtype: 'store',
                        data: [],
                        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'templateId'}, {name: 'templateName'}]))
                    },

                    width: 370,
                    mode: 'local',
                    displayField: 'templateName',
                    valueField: 'templateId',
                    typeAhead: true,
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    editable: false,
                    allowBlank: false,
                    autoSelect: true,

                    listeners: {
                        'beforeselect': function () {
                            thisWindow.AssignDepositForm.find('name', 'template_send_as')[0].setVisible(true);
                        },

                        render: {
                            buffer: 50, // We need this delay - to remove "marked as invalid"
                            fn: function (combo) {
                                combo.clearInvalid();
                            }
                        }
                    }
                }
            }, {html: '&nbsp;'}, {
                xtype: 'combo',
                name: 'template_send_as',
                hiddenName: 'template_send_as',
                emptyText: _('Please select...'),
                ref: '../templateSendAsCombo',

                store: {
                    xtype: 'store',
                    data: [],
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'optionId'}, {name: 'optionName'}]))
                },

                mode: 'local',
                width: 180,
                hideLabel: true,
                hidden: true,
                displayField: 'optionName',
                valueField: 'optionId',
                allowBlank: true,
                typeAhead: true,
                forceSelection: true,
                triggerAction: 'all',
                selectOnFocus: true,
                editable: false
            }]
        }, {
            xtype: 'combo',
            ref: 'paymentMadeBy',
            fieldLabel: _('Payment method'),
            emptyText: _('Optional...'),

            store: {
                xtype: 'store',
                reader: new Ext.data.JsonReader({
                    id: 'option_id'
                }, Ext.data.Record.create([
                    {name: 'option_id'},
                    {name: 'option_name'}
                ])),
                data: paymentMadeByOptions['arrOptions']
            },

            width: 540,
            displayField: 'option_name',
            valueField: 'option_id',
            mode: 'local',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            allowBlank: true,
            editable: true,
            typeAhead: true
        }, {
            xtype: 'textfield',
            ref: 'notesField',
            fieldLabel: _('Notes'),
            emptyText: _('Optional'),
            width: 540,
            value: ''
        }]
    });

    this.verifyButton = new Ext.Button({
        text: _('Verify'),
        cls: 'orange-btn',
        handler: thisWindow.assignDepositRequest.createDelegate(thisWindow)
    });

    AssignDepositDialog.superclass.constructor.call(this, {
        title: _('Verify Deposit'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        items: thisWindow.AssignDepositForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            },
            this.verifyButton
        ]
    });
};

Ext.extend(AssignDepositDialog, Ext.Window, {
    loadData: function () {
        var thisWindow = this;
        thisWindow.owner.getEl().mask(_('Loading...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/get-info-to-assign-deposit',
            params: {
                deposit_id: thisWindow.deposit_id,
                member_id: thisWindow.member_id,
                ta_id: thisWindow.ta_id
            },

            success: function (result) {
                thisWindow.owner.getEl().unmask();

                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisWindow.show();
                    thisWindow.center();

                    thisWindow.AssignDepositForm.noEntryMessage.setVisible(!resultData.can_be_assigned);
                    thisWindow.verifyButton.setDisabled(!resultData.can_be_assigned);
                    thisWindow.AssignDepositForm.templateSendAsCombo.getStore().loadData(resultData.send_as_options);
                    thisWindow.AssignDepositForm.templateSendAsCombo.setValue(1);
                    thisWindow.AssignDepositForm.templatesCombo.getStore().loadData(resultData.templates);
                    thisWindow.AssignDepositForm.transactionIdCombo.getStore().loadData(resultData.transactions);
                    thisWindow.AssignDepositForm.depositAmountValue.setValue(resultData.transaction_amount);

                    if (!empty(resultData.transactions.length)) {
                        thisWindow.AssignDepositForm.noEntryMessage.setValue(
                            _('None of the unassigned transaction(s) match the Deposit Reminder of ') + thisWindow.AssignDepositForm.depositAmountValue.getValue()
                        );
                    }

                    // Don't preselect (temporary?)
                    // if (!empty(resultData.transaction_id_select)) {
                    //     thisWindow.AssignDepositForm.transactionIdCombo.setValue(resultData.transaction_id_select);
                    // }
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisWindow.owner.getEl().unmask();
                Ext.simpleConfirmation.error('Cannot load information. Please try again later.');
            }
        });
    },

    assignDepositRequest: function () {
        var thisWindow = this;
        if (thisWindow.AssignDepositForm.getForm().isValid()) {
            thisWindow.getEl().mask(_('Saving...'));

            Ext.Ajax.request({
                url: baseUrl + '/clients/accounting/assign-deposit',

                params: {
                    deposit_id: Ext.encode(thisWindow.deposit_id),
                    member_id: Ext.encode(thisWindow.member_id),
                    transaction_id: Ext.encode(thisWindow.AssignDepositForm.transactionIdCombo.getValue()),
                    template_id: Ext.encode(thisWindow.AssignDepositForm.templatesCombo.getValue()),
                    payment_made_by: Ext.encode(thisWindow.AssignDepositForm.paymentMadeBy.getValue() === thisWindow.AssignDepositForm.paymentMadeBy.emptyText ? '' : thisWindow.AssignDepositForm.paymentMadeBy.getValue()),
                    notes: Ext.encode(thisWindow.AssignDepositForm.notesField.getValue())
                },

                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        refreshAccountingTabByTA(thisWindow.member_id, thisWindow.ta_id);

                        Ext.simpleConfirmation.success(resultData.message);
                        thisWindow.close();
                    } else {
                        thisWindow.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error(_('Deposit cannot be assigned. Please try again later.'));
                    thisWindow.getEl().unmask();
                }
            });
        }
    }
});