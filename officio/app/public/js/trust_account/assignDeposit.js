// ***********************
// Assign Deposit to client or something else
// ***********************/

// This function is used in assign Withdrawal window too
function sendRequest(win, options, ta_id, arrClientsRefresh, sendReceiptInfo) {
    win.getEl().mask(_('Sending Information...'));

    Ext.Ajax.request({
        url: baseUrl + "/trust-account/assign",
        params: options,

        success: function(result) {
            var resultData = Ext.decode(result.responseText);

            if (resultData.success) {
                // Refresh the list
                Ext.getCmp('editor-grid' + ta_id).store.reload();

                if (arrClientsRefresh && arrClientsRefresh.length > 0) {
                    for (var i = 0; i < arrClientsRefresh.length; i++) {
                        refreshAccountingTabByTA(arrClientsRefresh[i], ta_id);
                    }
                }

                if (resultData.arr_refresh_members && resultData.arr_refresh_members.length > 0) {
                    for (i = 0; i < resultData.arr_refresh_members.length; i++) {
                        refreshAccountingTabByTA(resultData.arr_refresh_members[i], ta_id);
                    }
                }

                // Show confirmation message
                Ext.simpleConfirmation.msg(_('Info'), resultData.message, 3000);
                win.close();

                // Send/Save As
                if (sendReceiptInfo) {
                    var documentTitle = _('Assign Deposit');
                    var send_as_email_options = false;
                    for (i = 0; i < sendReceiptInfo.length; i++) {
                        if (sendReceiptInfo[i].template_id) {
                            switch (sendReceiptInfo[i].send_as) {
                                case 1 : // send as email
                                    send_as_email_options = {
                                        member_id: sendReceiptInfo[i].member_id,
                                        template_id: sendReceiptInfo[i].template_id,
                                        next_member: send_as_email_options
                                    };
                                    break;

                                /*
                                 case 2 : // show as pdf
                                 window.open(baseUrl + '/trust-account/assign/save-deposit-as-pdf?member_id=' +
                                 sendReceiptInfo[i].member_id + '&template_id=' + sendReceiptInfo[i].template_id +
                                 '&tid=' + options.transaction_id + '&file=' + escape(documentTitle));
                                 break;

                                 //check if client has no email address, and we try to create mail without 'From' options
                                 case 3 : // save as letter
                                 save_email({member_id: options.member_id, template_id: options.template_id});
                                 break;
                                 */

                                case 4 : // Save to Documents
                                    Ext.getBody().mask(_('Saving Document...'));
                                    Ext.Ajax.request({
                                        url: baseUrl + '/documents/index/save-doc-file',
                                        params: {
                                            member_id:   Ext.encode(sendReceiptInfo[i].member_id),
                                            template_id: Ext.encode(sendReceiptInfo[i].template_id),
                                            title:       Ext.encode(documentTitle)
                                        },
                                        success: function(result) {
                                            Ext.getBody().unmask();

                                            var resultDecoded = Ext.decode(result.responseText);
                                            if (resultDecoded.success) {
                                                Ext.simpleConfirmation.info(_('Document saved in the case Correspondence folder'));
                                            }
                                        },
                                        failure: function() {
                                            Ext.getBody().unmask();
                                            Ext.simpleConfirmation.error(_('Can\'t create Document'));
                                        }
                                    });
                                    break;

                                case 6 : // download as doc
                                    window.open(baseUrl + '/documents/index/download-doc-file?member_id=' + sendReceiptInfo[i].member_id +
                                        '&template_id=' + sendReceiptInfo[i].template_id + '&title=' + escape(documentTitle));
                                    break;

                                default:
                                    break;
                            }
                        }
                    }

                    //open email dialog wizard
                    if (send_as_email_options) {
                        show_email_dialog(send_as_email_options);
                    }
                }
            } else {
                Ext.simpleConfirmation.error(resultData.message);
                win.getEl().unmask();
            }
        },
        failure: function() {
            Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
            win.getEl().unmask();
        }
    });
}

var assignDeposit = function(currentSelectedTransactionId, depositVal, ta_id) {
    depositVal = toFixed(depositVal, 2);
    
    Ext.getBody().mask('Loading...');

    Ext.Ajax.request({
        url: baseUrl + "/trust-account/assign/get-assign-deposit-data",
        params: {
            ta_id: ta_id,
            depositVal: Ext.encode(depositVal)
        },
        success: function(f, o) {
            var result = Ext.decode(f.responseText);

            var updateUnallocatedAmount = function() {
                var sum = 0;
                multiple_clients_grid.store.each(function(rec) {
                    sum += parseFloat(rec.data.clientAmount);
                });
                var amount = depositVal - sum;

                var txt = String.format(
                    'Unallocated amount: <span style="color: {0};">{1}</span>',
                    amount < 0 ? 'red' : (amount == 0 ? 'green' : '#FF7400'),
                    Ext.util.Format.usMoney(amount)
                );

                status_bar.setStatus({
                    text: txt,
                    iconCls: '',
                    clear: false
                });
            };

            // Check if selected client has a deposit
            var isDepositsForSelectedClient = function(memberId) {
                var isDeposits = false;
                for (var i = 0; i < result.deposits.length; i++) {
                    if (result.deposits[i].memberId == memberId) {
                        isDeposits = true;
                    }
                }

                return isDeposits;
            };

            var showSelectDepositWindow = function(x, y, memberId, defaultDepositId, callback) {

                //is only text label
                if (!isDepositsForSelectedClient(memberId)) {
                    return this;
                }

                var deposit_label = new Ext.form.Label({
                    cls: 'x-form-item label',
                    style: 'padding-bottom:6px;',
                    text: _('Please specify if the amount you just received corresponds to any of the following amounts you were expecting to receive from this case:')
                });

                var deposit_radio = new Ext.form.RadioGroup({
                    hideLabel: true,
                    columns: 1,
                    itemCls: 'x-check-group-alt',
                    cls: 'assign-deposit-radiogroup',
                    items: [
                        {
                            boxLabel: _('No. It does not correspond.'),
                            name: 'deposits-radio',
                            inputValue: 0,
                            checked: empty(defaultDepositId)
                        }
                    ]
                });

                //add new items (radio elements)
                var deposits = result.deposits;
                for (var i = 0; i < deposits.length; i++) {
                    //show unassigned deposits only for selected client without already selected deposits
                    if (deposits[i].memberId == memberId && (!deposits[i].is_assigned || deposits[i].depositId == defaultDepositId)) {
                        deposit_radio.items.push({
                            boxLabel: deposits[i].depositValue + ' ' + deposits[i].depositDate,
                            name: 'deposits-radio',
                            inputValue: deposits[i].depositId,
                            depositValue: deposits[i].depositValue,
                            depositDate: deposits[i].depositDate,
                            checked: deposits[i].depositId == defaultDepositId
                        });
                    }
                }

                var pan = new Ext.FormPanel({
                    bodyStyle: 'padding:5px',
                    items: [
                        {
                            layout: 'column',
                            items: [
                                {
                                    columnWidth: 0.9,
                                    layout: 'form',
                                    items: [deposit_label]
                                }
                            ]
                        },
                        deposit_radio
                    ],

                    buttons: [
                        {
                            text: _('Close'),
                            minWidth: 100,
                            handler: function () {
                                depositWindow.close();
                            }
                        }, {
                            text: _('OK'),
                            minWidth: 100,
                            cls: 'orange-btn',
                            style: 'float:right',
                            handler: function () {

                                //get new values
                                var deposit = deposit_radio.getValue();
                                var depositValue = empty(deposit.inputValue) ? result.depositVal : deposit.depositValue;

                                for (var i = 0; i < deposits.length; i++) {
                                    //uncheck previously selected deposit
                                    if (deposits[i].depositId == defaultDepositId) {
                                        deposits[i].is_assigned = false;
                                    }

                                    //check current deposit as selected
                                    if (deposits[i].depositId == deposit.inputValue) {
                                        deposits[i].is_assigned = true;
                                    }
                                }

                                //save changes
                                callback({
                                    depositId: deposit.inputValue,
                                    depositValue: depositValue,
                                    depositDate: deposit.depositDate
                                });

                                //close window
                                depositWindow.close();
                            }
                        }
                    ]
                });

                var depositWindow = new Ext.Window({
                    title: _('Please confirm'),
                    modal: true,
                    width: 400,
                    autoHeight: true,
                    plain: true,
                    resizable: false,
                    items: [pan],
                    border: false,
                    bodyBorder: false
                });

                //set position
                if (x && y) {
                    depositWindow.setPosition(x, y);
                }

                depositWindow.show();
                return true;
            };

            var clients_store = new Ext.data.Store({
                data: result.clients,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                    {name: 'clientId'},
                    {name: 'clientName'}
                ]))
            });

            var templates_store = new Ext.data.Store({
                data: result.templates,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                    {name: 'templateId'},
                    {name: 'templateName'}
                ]))
            });

            var transaction_store = new Ext.data.Store({
                data: result.deposit_types,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                    {name: 'transactionId'},
                    {name: 'transactionName'}
                ]))
            });

            var send_as_store = new Ext.data.Store({
                data: result.sendAsOptions,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                    {name: 'optionId'},
                    {name: 'optionName'}
                ]))
            });

            //multiple clients combo
            var clientsCombo = new Ext.form.ComboBox({
                store: clients_store,
                mode: 'local',
                searchContains: true,
                valueField: 'clientId',
                displayField: 'clientName',
                triggerAction: 'all',
                lazyRender: true,
                forceSelection: true
            });

            clientsCombo.on('select', function(combo, record) {
                var selected_record = multiple_clients_grid.getSelectionModel().getSelected();
                var dep_label = '';
                if (!isDepositsForSelectedClient(record.data.clientId)) {
                    dep_label = _('new');
                    selected_record.data.deposit_id = 0;
                } else {
                    selected_record.data.deposit_id = null;

                    //select deposit amount
                    showSelectDepositWindow(false, false, record.data.clientId, null, function(deposit) {
                        selected_record.data.deposit_id = deposit.depositId;
                        selected_record.set('clientDeposit', '<a href="#" class="bluelink select-deposit-link" onclick="return false">' + (empty(deposit.depositId) ? 'new' : deposit.depositDate) + '</a>');
                    });
                }

                selected_record.set('clientDeposit', dep_label);
                selected_record.commit();
            });

            var templatesCombo = new Ext.form.ComboBox({
                store: templates_store,
                mode: 'local',
                valueField: 'templateId',
                displayField: 'templateName',
                triggerAction: 'all',
                lazyRender: true,
                forceSelection: true,
                typeAhead: true,
                value: 0
            });

            var templatesSendAsCombo = new Ext.form.ComboBox({
                store: send_as_store,
                mode: 'local',
                valueField: 'optionId',
                displayField: 'optionName',
                value: 1,
                triggerAction: 'all',
                lazyRender: true,
                forceSelection: true,
                editable: false
            });

            var deposit_link = new Ext.form.DisplayField({
                value: '',
                deposit_id: false,
                style: 'padding:4px 0 0 6px; font-size: 14px;',
                listeners: {
                    'render': function (obj) {
                        Ext.fly(obj.el).on('click', function () {
                            showSelectDepositWindow(obj.el.getX() - 250, obj.el.getY() + 23, clients_combo.getValue(), deposit_link.deposit_id, function (deposit) {
                                deposit_link.deposit_id = deposit.depositId;
                                deposit_link.setValue('<a href="#" class="bluelink" onclick="return false;">' + deposit.depositValue + '</a>');
                            });
                        });
                    },
                    scope: this
                }
            });

            var cm = new Ext.grid.ColumnModel([
                {
                    header: _('Case'),
                    dataIndex: 'clientId',
                    width: 180,
                    editor: clientsCombo,
                    renderer: function(value, p, record) {
                        return record.data['clientName'];
                    }
                },
                {
                    header: _('Type'),
                    dataIndex: 'clientDeposit',
                    sortable: true,
                    width: 100
                },
                {
                    header: _('Amount'),
                    dataIndex: 'clientAmount',
                    sortable: true,
                    width: 80,
                    renderer: 'usMoney',
                    editor: new Ext.form.NumberField({
                        allowBlank: false,
                        allowNegative: true
                    })
                },
                {
                    header: _('Template'),
                    dataIndex: 'templateId',
                    width: 200,
                    editor: templatesCombo,
                    renderer: function(value, p, record) {
                        return record.data['clientTemplate'];
                    }
                },
                {
                    header: _('Send/Save as'),
                    dataIndex: 'sendAsId',
                    width: 100,
                    editor: templatesSendAsCombo,
                    renderer: function(value, p, record) {
                        return record.data['clientTemplateSendAs'];
                    }
                }
            ]);

            var data_record = [
                {name: 'clientId',             type: 'int'},
                {name: 'clientName',           type: 'string'},
                {name: 'clientAmount',         type: 'float'},
                {name: 'templateId',           type: 'int'},
                {name: 'clientTemplate',       type: 'string'},
                {name: 'clientTemplateSendAs', type: 'string'}
            ];

            var AssignClientInfo = Ext.data.Record.create(data_record);

            var multiple_clients_store = new Ext.data.SimpleStore({
                fields: data_record
            });

            // Load sample clients to the grid
            multiple_clients_store.loadData([
                [, _('Please select'), 0, 0, "Don't Send Receipt",'-'],
                [, _('Please select'), 0, 0, "Don't Send Receipt",'-']
            ]);

            var status_bar = new Ext.ux.StatusBar({
                defaultText: '',
                statusAlign: 'right',
                items: [
                    {
                        text: '<i class="las la-plus"></i>' + _('Add Case'),
                        tooltip: _('Add a new case'),
                        handler: function() {
                            var p = new AssignClientInfo({
                                clientName: _('Please select'),
                                clientAmount: 0,
                                clientTemplate: "Don't Send Receipt",
                                clientTemplateSendAs: '-',
                                templateId: 0
                            });

                            multiple_clients_grid.stopEditing();
                            multiple_clients_store.insert(multiple_clients_store.getCount(), p);
                        }
                    },
                    '-',
                    {
                        text: '<i class="las la-trash"></i>' + _('Remove Case'),
                        tooltip: _('Remove the selected case'),
                        handler: function() {
                            var selected = multiple_clients_grid.getSelectionModel().getSelected();
                            if (selected) {
                                Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete the selected case(s)?'),
                                    function(btn) {
                                        if (btn == 'yes') {
                                            multiple_clients_grid.store.remove(selected);
                                            updateUnallocatedAmount();
                                        }
                                    });
                            }
                        }
                    }
                ]
            });

            // Create the "clients editor grid"
            var multiple_clients_grid = new Ext.grid.EditorGridPanel({
                store: multiple_clients_store,
                cm: cm,
                sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
                stripeRows: true,
                mode: 'local',
                width: 665,
                height: 200,
                viewConfig:
                {
                    emptyText: _('Please add a case.'),
                    deferEmptyText: false
                },
                clicksToEdit: 1,
                loadMask: true,
                autoScroll: true,
                hidden: true,
                colspan: 3,
                bbar: status_bar,
                cls: 'extjs-grid',
                listeners:
                {
                    render: function() {
                        // Update status on first loading
                        updateUnallocatedAmount();
                    },

                    'validateedit': function(e) {
                        // Show combo name instead of value (id)
                        switch (e.field) {
                            case 'clientId' :
                                e.record.data['clientName'] = clientsCombo.getRawValue();
                                break;

                            case 'templateId' :
                                e.record.data['clientTemplate'] = templatesCombo.getRawValue();
                                break;

                            case 'sendAsId' :
                                e.record.data['clientTemplateSendAs'] = templatesSendAsCombo.getRawValue();
                                break;
                            default:
                                break;
                        }
                    },

                    afteredit: function(e) {
                        // Update status again (recalculate)
                        e.record.commit();
                        updateUnallocatedAmount();
                    },

                    click: function(e) {
                        var btn = e.getTarget('.select-deposit-link');
                        if (btn) {

                            //get cell
                            var t = e.getTarget();
                            var v = this.getView();
                            var rowIdx = v.findRowIndex(t);
                            var record = this.getStore().getAt(rowIdx);
                            var position = $(btn).offset();

                            //show window
                            showSelectDepositWindow(position.left, position.top + 15, record.data.clientId, record.data.deposit_id, function(deposit) {
                                record.data.deposit_id = deposit.depositId;
                                record.set('clientDeposit', '<a href="#" class="bluelink select-deposit-link" onclick="return false">' + (empty(deposit.depositId) ? _('new') : deposit.depositDate) + '</a>');
                            });
                        }
                    }
                }
            });

            var showContent = function(section) {
                one_client_fieldset.hide();
                templates_combo.hide();
                send_as_combo.hide();

                special_deposit_fieldset.hide();
                assign_transaction_custom.hide();

                multiple_clients_grid.hide();

                switch (section) {
                    case 'client' :
                        one_client_fieldset.show();

                        if (getComboBoxIndex(clients_combo) != -1) {
                            templates_combo.show();
                        }

                        if (getComboBoxIndex(templates_combo) > 0) {
                            send_as_combo.show();
                        }
                        break;

                    case 'multiple-clients' :
                        multiple_clients_grid.show();
                        break;

                    case 'custom' :
                        special_deposit_fieldset.show();

                        //is last item
                        if ((assign_transaction_combo.getStore().getCount() - 1) == getComboBoxIndex(assign_transaction_combo)) {
                            assign_transaction_custom.show();
                        }
                        break;
                    default:
                        break;
                }

                // Shadow bug
                win.syncShadow();
            };

            var clients_combo = new Ext.form.ComboBox({
                store: clients_store,
                mode: 'local',
                searchContains: true,
                width: 400,
                listWidth: 400,
                ctCls: 'deposit-select-first',
                hideLabel: true,
                displayField: 'clientName',
                valueField: 'clientId',
                typeAhead: false,
                forceSelection: true,
                triggerAction: 'all',
                emptyText: _('Select a case...'),
                selectOnFocus: true
            });

            //one client combo
            clients_combo.on('select', function() {

                //show templates combo
                templates_combo.show();

                //show amount or open window to select deposit amount
                if (isDepositsForSelectedClient(clients_combo.getValue())) {
                    showSelectDepositWindow(deposit_link.el.getX() - 250, deposit_link.el.getY() + 23, clients_combo.getValue(), deposit_link.deposit_id, function(deposit) {
                        deposit_link.deposit_id = deposit.depositId;
                        deposit_link.setValue('<a href="#" class="bluelink" onclick="return false;">' + deposit.depositValue + '</a>');
                    });

                } else {
                    deposit_link.deposit_id = 0;
                    deposit_link.setValue(result.depositVal);
                }

                //show deposit link
                deposit_link.show();
            });

            var templates_combo = new Ext.form.ComboBox({
                width: 400,
                store: templates_store,
                mode: 'local',
                ctCls: 'deposit-select',
                hideLabel: true,
                displayField: 'templateName',
                valueField: 'templateId',
                emptyText: _('Please select a template...'),
                typeAhead: true,
                forceSelection: true,
                triggerAction: 'all',
                selectOnFocus: true,
                hidden: true,
                editable: false,
                autoSelect: true,
                value: 0
            });

            templates_combo.on('select', function(c, r) {
                send_as_combo.setVisible(r.data.templateId > 0);
            });

            var send_as_combo = new Ext.form.ComboBox({
                store: send_as_store,
                mode: 'local',
                width: 200,
                ctCls: 'deposit-select',
                hideLabel: true,
                hidden: true,
                displayField: 'optionName',
                valueField: 'optionId',
                allowBlank: true,
                typeAhead: true,
                forceSelection: true,
                triggerAction: 'all',
                value: 1,
                selectOnFocus: true,
                editable: false
            });

            var assign_client_radio = new Ext.form.Radio({
                name: 'deposit-assign-radio',
                colspan: 3,
                fieldLabel: '',
                labelSeparator: '',
                ctCls: 'box-padding',
                boxLabel: _('Assign to a case'),
                checked: true,
                inputValue: 1
            });

            assign_client_radio.on('check', function(r, booChecked) {
                if (booChecked) {
                    showContent('client');
                }
            });

            var mc_radio = new Ext.form.Radio({
                name: 'deposit-assign-radio',
                xtype: 'radio',
                fieldLabel: '',
                labelSeparator: '',
                ctCls: 'box-padding',
                boxLabel: _('Assign to Multiple Cases'),
                inputValue: 2,
                colspan: 3
            });

            mc_radio.on('check', function(r, booChecked) {
                if (booChecked) {
                    showContent('multiple-clients');
                }
            });

            var st_radio = new Ext.form.Radio({
                name: 'deposit-assign-radio',
                xtype: 'radio',
                fieldLabel: '',
                labelSeparator: '',
                ctCls: 'box-padding',
                boxLabel: _('Assign as a Special Deposit'),
                inputValue: 3,
                colspan: 3
            });

            st_radio.on('check', function(r, booChecked) {
                if (booChecked) {
                    showContent('custom');
                }
            });

            var assign_transaction_combo = new Ext.form.ComboBox({
                store: transaction_store,
                mode: 'local',
                width: 250,
                readOnly: true,
                hideLabel: true,
                displayField: 'transactionName',
                valueField: 'transactionId',
                typeAhead: true,
                forceSelection: true,
                triggerAction: 'all',
                emptyText: _('Select a transaction...'),
                selectOnFocus: true
            });

            assign_transaction_combo.on('beforeselect', function(n, selRecord) {
                if (empty(selRecord.data.transactionId)) {
                    assign_transaction_custom.show();
                } else {
                    assign_transaction_custom.hide();
                }
            });

            var assign_transaction_custom = new Ext.form.TextField({
                xtype: 'textfield',
                width: 200,
                hidden: true,
                value: _('Miscellaneous'),
                labelWidth: 200,
                allowBlank: false
            });

            var one_client_fieldset = new Ext.form.FieldSet({
                layout: 'table',
                style: 'border:none; margin:0; padding:0 0 5px;',
                layoutConfig: {columns: 3},
                items: [
                    clients_combo, {html: '&nbsp;'}, deposit_link,
                    {html: '', style: 'padding:0;', colspan: 3},
                    templates_combo, {html: '&nbsp;'}, send_as_combo
                ]
            });

            var special_deposit_fieldset = new Ext.form.FieldSet({
                hidden: true,
                layout: 'table',
                style: 'border:none; margin:0; padding:0;',
                layoutConfig: {columns: 3},
                items: [
                    assign_transaction_combo,
                    {html: '&nbsp;'},
                    assign_transaction_custom
                ]
            });

            var assign_pan = new Ext.FormPanel({
                autoHeight: true,
                layout: 'table',
                layoutConfig: {columns: 1},
                items: [
                    assign_client_radio,
                    one_client_fieldset,
                    mc_radio,
                    multiple_clients_grid,
                    st_radio,
                    special_deposit_fieldset
                ]
            });

            var payment_made = new Ext.form.ComboBox({
                fieldLabel: _('Payment method'),
                width: 540,
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
                displayField:   'option_name',
                valueField:     'option_id',
                mode:           'local',
                triggerAction:  'all',
                lazyRender:     true,
                forceSelection: true,
                allowBlank:     true,
                editable:       false,
                typeAhead:      false
            });

            var notes = new Ext.form.TextField({
                fieldLabel: _('Notes'),
                width: 540,
                value: ''
            });

            var other_pan = new Ext.FormPanel({
                bodyStyle: 'padding:5px',
                labelWidth: 120,
                autoHeight: true,
                items: [payment_made, notes]
            });

            var submitBtn = new Ext.Button({
                text: _('Submit'),
                cls: 'orange-btn',
                disabled: false,
                handler: function() {
                    var params = {};
                    var arrClientsRefresh = [];
                    var sendReceiptInfo = [];
                    var member_id = clients_combo.getValue();

                    if (assign_client_radio.getValue()) //assign to one client
                    {
                        // Is client selected?
                        if (!clients_combo.isValid() || getComboBoxIndex(clients_combo) == -1) {
                            clients_combo.markInvalid(_('This field is required'));
                            return;
                        }

                        // Is template selected?
                        if (!templates_combo.isValid() || getComboBoxIndex(templates_combo) == -1) {
                            templates_combo.markInvalid(_('This field is required'));
                            return;
                        }

                        // deposit not selected
                        if (deposit_link.deposit_id === false) {
                            Ext.simpleConfirmation.error(_('Please select a deposit for case'));
                            return;
                        }

                        // Okay
                        arrClientsRefresh = [member_id];

                        params = {
                            assign_to: 'one-client',
                            member_id: member_id,
                            deposit_id: deposit_link.deposit_id
                        };

                        //get receipt info
                        sendReceiptInfo.push({
                            member_id: member_id,
                            template_id: templates_combo.getValue(),
                            send_as: send_as_combo.getValue()
                        });
                    }
                    else if (mc_radio.getValue()) // Assign to multiple clients
                    {
                        var amountSum = 0;
                        var arrClientInfo = new Array();
                        var strError = '';

                        multiple_clients_grid.getStore().each(function(r) {
                            if(empty(strError)) {
                                if (empty(r.data.clientId)) {
                                    // Client is not selected
                                    strError = _('Please select a case(s)');
                                }

                                if (!empty(r.data.templateId) && r.data.clientTemplateSendAs == '-' && empty(strError)) {
                                    // Send/Save as is not selected
                                    strError = _('Please select a case(s) "Send/Save as" property');
                                }

                                if (r.data.deposit_id === null && empty(strError)) { //0 - valid value
                                    // Client Deposit is not selected
                                    strError = _('Please enter a case(s) deposit');
                                }

                                if (r.data.clientAmount == 0 && empty(strError)) {
                                    // clientAmount is not entered
                                    strError = _('Please enter correct amount');
                                }

                                if (r.data.clientTemplate == 'Send Receipt?' && empty(strError)) {
                                    // template is not selected
                                    strError = _('Please select a template');
                                }

                                if (!empty(r.data.templateId) && r.data.clientTemplateSendAs == '-' && empty(strError)) {
                                    // template send method is not selected
                                    strError = _('Please select email send method');
                                }

                                if (empty(strError)) {
                                    arrClientInfo[arrClientInfo.length] = r.data;
                                    amountSum += parseFloat(r.data.clientAmount);

                                    //get receipt info
                                    sendReceiptInfo.push({
                                        member_id:   r.data['clientId'],
                                        template_id: r.data['templateId'],
                                        send_as:     r.data['sendAsId']
                                    });
                                }
                            }
                        });
                        amountSum = toFixed(amountSum, 2);

                        // Check if amounts sum is equal to deposit
                        if (empty(strError) && amountSum != depositVal) {
                            strError = _('Amounts sum is not equal to deposit');
                        }

                        if (!empty(strError)) {
                            Ext.simpleConfirmation.error(strError);
                            return;
                        }

                        // Okay. It seems that all is correct
                        for (var $ii = 0; $ii < arrClientInfo.length; $ii++) {
                            arrClientsRefresh[arrClientsRefresh.length] = arrClientInfo[$ii].clientId;
                        }

                        params = {
                            'assign_to': 'multiple-clients',
                            'arrClients': Ext.encode(arrClientInfo)
                        };

                    }
                    else if (st_radio.getValue()) // Assign to special transaction
                    {
                        if (!assign_transaction_combo.isValid() || getComboBoxIndex(assign_transaction_combo) == -1) {
                            assign_transaction_combo.markInvalid(_('This field is required'));
                            return;
                        }
                        else if (!assign_transaction_custom.isValid()) {
                            // If other option is selected - check if something is entered
                            assign_transaction_custom.markInvalid(_('This field is required'));
                            return;
                        }

                        // Okay
                        params = {
                            assign_to: 'custom-transaction',
                            special_transaction_id: assign_transaction_combo.getValue(),
                            custom_transaction: Ext.encode(assign_transaction_custom.getValue())
                        };
                    }

                    var generalParams = {
                        act: Ext.encode('assign-deposit'),
                        transaction_id: Ext.encode(currentSelectedTransactionId),
                        payment_made_by: Ext.encode(payment_made.getValue()),
                        notes: Ext.encode(notes.getValue()),
                        template_id: Ext.encode(templates_combo.getValue())
                    };

                    // Send info to server
                    Ext.apply(params, generalParams);
                    sendRequest(win, params, ta_id, arrClientsRefresh, sendReceiptInfo);
                }
            });

            var closeBtn = new Ext.Button({
                text: 'Cancel',
                handler: function() {
                    win.close();
                }
            });

            var win = new Ext.Window({
                title: _('Assign Deposit to Case(s) or a Special Transaction'),
                layout: 'fit',
                modal: true,
                width: 700,
                autoHeight: true,
                plain: true,
                border: false,

                resizable: false,
                items: [assign_pan, other_pan],
                buttons: [closeBtn, submitBtn]
            });

            // Automatically preselect the client + a deposit with the same amount
            var deposits = result.deposits;
            for (var i = 0; i < deposits.length; i++) {
                if (deposits[i].depositValue === result.depositVal) {
                    win.on('show', function () {
                        clients_combo.setValue(deposits[i].memberId);

                        //show templates combo
                        templates_combo.show();

                        deposit_link.deposit_id = deposits[i].depositId;
                        deposit_link.setValue('<a href="#" class="bluelink" onclick="return false;">' + deposits[i].depositValue + '</a>');

                        //show deposit link
                        deposit_link.show();
                    }, win, {single: true});
                    break;
                }
            }

            win.show();
            win.center();

            Ext.getBody().unmask();
        },
        failure: function() {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error('Can\'t load data');
        }
    });
};

function downloadAssignedDepositReceipt(memberId, templateId) {
   var documentTitle = 'Assign Deposit';
   if (!empty(memberId) && !empty(templateId) ) {
       var url = baseUrl + '/documents/index/download-doc-file?member_id=' + memberId +
           '&template_id=' + templateId + '&title=' + escape(documentTitle);

       window.open(url);
   }
}