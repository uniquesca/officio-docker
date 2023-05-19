var ApplicantsQueueApplyChangesWindow = function (config, owner) {

    var booShowFieldCombo = false;
    switch (config.strFieldType) {
        case 'push_to_queue':
            config.comboLabel = 'to';
            config.title = '<i class="las la-users"></i>' + _('Push to ') + arrApplicantsSettings.office_label;
            config.arrComboData = arrApplicantsSettings.queue_settings['fields_options']['office_push_to_queue'];
            config.submitToUrl = topBaseUrl + '/applicants/queue/push-to-queue';
            break;

        case 'file_status':
            config.comboLabel = 'Change to';
            config.title = 'Change ' + arrApplicantsSettings.file_status_label;
            config.iconCls = 'icon-applicant-queue-change-file-status';
            config.arrComboData = arrApplicantsSettings.options.general.case_statuses['all'];
            config.submitToUrl = topBaseUrl + '/applicants/queue/change-file-status';
            break;

        case 'visa_subclass':
            config.comboLabel = 'Change to';
            config.title = 'Change ' + arrApplicantsSettings.visa_subclass_label;
            config.iconCls = 'icon-applicant-queue-change-visa-subclass';
            config.arrComboData = arrApplicantsSettings.queue_settings['fields_options']['visa_subclass'];
            config.submitToUrl = topBaseUrl + '/applicants/queue/change-visa-subclass';
            break;

        case 'assigned_staff':
            config.fieldComboLabel = 'Field';
            config.comboLabel = 'Change to';
            config.title = 'Change Assigned Staff';
            config.iconCls = 'icon-applicant-queue-change-assigned-staff';
            config.arrFieldComboData = arrApplicantsSettings.queue_settings['fields_options']['assigned_staff_fields'];
            config.arrComboData = arrApplicantsSettings.options['general']['staff_responsible_rma'];
            config.submitToUrl = topBaseUrl + '/applicants/queue/change-assigned-staff';

            booShowFieldCombo = true;
            break;

        default:
    }

    this.owner = owner;
    Ext.apply(this, config);

    var thisSearchWindow = this;

    var arrItems = [];
    switch (config.strFieldType) {
        case 'assigned_staff':
            arrItems = [
                {
                    xtype: 'displayfield',
                    hideLabel: true,

                    value: String.format(
                        'You have selected {0} {1}.',
                        this.arrSelectedClientIds.length,
                        this.arrSelectedClientIds.length == 1 ? 'client' : 'clients'
                    )
                }, {
                    id: 'fieldCombo',
                    xtype: 'combo',
                    width: 250,
                    fieldLabel: config.fieldComboLabel,

                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'field_id'
                        }, [
                            {name: 'field_id'},
                            {name: 'field_name'}
                        ]),
                        data: this.arrFieldComboData
                    },

                    displayField: 'field_name',
                    valueField: 'field_id',
                    mode: 'local',
                    editable: false,
                    forceSelection: true,
                    triggerAction: 'all',
                    allowBlank: false,

                    listeners: {
                        select: function () {
                            var newStore;
                            var arrData;
                            Ext.getCmp('changeToCombo').reset();

                            if (this.getValue() == 'registered_migrant_agent') {
                                arrData = arrApplicantsSettings.options['general']['staff_responsible_rma'];
                            } else {
                                arrData = arrApplicantsSettings.options['general']['assigned_to'];
                            }

                            newStore = new Ext.data.Store({
                                reader: new Ext.data.JsonReader({
                                    id: 'option_id'
                                }, [
                                    {name: 'option_id'},
                                    {name: 'option_name'}
                                ]),
                                data: arrData
                            });

                            Ext.getCmp('changeToCombo').bindStore(newStore);
                        }
                    }
                }, {
                    id: 'changeToCombo',
                    xtype: 'combo',
                    width: 250,
                    fieldLabel: config.comboLabel,

                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'}
                        ]),
                        data: this.arrComboData
                    },

                    displayField: 'option_name',
                    valueField: 'option_id',
                    mode: 'local',
                    lazyInit: false,
                    typeAhead: false,
                    editable: false,
                    forceSelection: true,
                    triggerAction: 'all',
                    allowBlank: false,
                    selectOnFocus: true
                }, {
                    xtype: 'displayfield',
                    hideLabel: true,
                    style: 'padding-top: 15px;',
                    value: 'Are you sure you would like to proceed?'
                }, {
                    xtype: 'displayfield',
                    hideLabel: true,
                    value: 'Once completed, this change cannot be undone.'
                }
            ];
            break;

        case 'push_to_queue':
            var strSelectedOffices = 'Multiple ' + arrApplicantsSettings.office_label + 's';
            if (!empty(config.strSelectedOffices)) {
                var arrSelectedOffices = [];
                Ext.each(config.strSelectedOffices.split(','), function (officeId) {
                    Ext.each(thisSearchWindow.arrComboData, function (oOfficeInfo) {
                        if (parseInt(oOfficeInfo.option_id, 10) === parseInt(officeId, 10)) {
                            arrSelectedOffices.push('<span style="white-space: nowrap; font-weight: bold">' + oOfficeInfo.option_name + '</span>');
                        }
                    });
                });
                strSelectedOffices = arrSelectedOffices.join(', ');

                if (arrSelectedOffices.length) {
                    strSelectedOffices += ' ' + arrApplicantsSettings.office_label;
                    if (arrSelectedOffices.length > 1) {
                        strSelectedOffices += 's';
                    }
                }
            }
            strSelectedOffices += ' to';

            var arrCheckboxes = [];
            Ext.each(this.arrComboData, function (oOption) {
                arrCheckboxes.push({
                    xtype: 'checkbox',
                    name: 'office_push_to[]',
                    hideLabel: true,
                    boxLabel: oOption.option_name,
                    inputValue: oOption.option_id,
                    checked: false
                });
            });

            arrItems = [
                {
                    xtype: 'displayfield',
                    hideLabel: true,
                    value: String.format(
                        'You have selected to push {0} {1} from:',
                        this.arrSelectedClientIds.length,
                        this.arrSelectedClientIds.length == 1 ? 'client' : 'clients'
                    )
                }, {
                    xtype: 'displayfield',
                    hideLabel: true,
                    width: 450,
                    value: strSelectedOffices
                }, {
                    id: 'officeChangeToGroup',
                    xtype: 'checkboxgroup',
                    hideLabel: true,
                    cls: 'office_push_to_checkbox_group',
                    allowBlank: false,
                    columns: 1,
                    style: 'max-height: ' + (Ext.getBody().getViewSize().height - 300) + 'px;',
                    items: arrCheckboxes
                }, {
                    xtype: 'displayfield',
                    hideLabel: true,
                    style: 'padding-top: 15px;',
                    value: 'Are you sure you would like to proceed?'
                }
            ];
            break;

        default:
            // After manual debug we got these values :)
            var booAllScreen = (screen.height / config.arrComboData.length) < 600 / 22;
            var panelWithLinks = new Ext.Panel({
                xtype: 'panel',
                fieldLabel: 'Click to Select',
                autoScroll: booAllScreen,
                autoHeight: !booAllScreen,
                autoWidth: true,
                layout: 'table',
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%'
                        }
                    },
                    columns: 1
                }
            });

            if (booAllScreen) {
                panelWithLinks.height = Ext.getBody().getHeight() - 120;
            }

            config.arrComboData.forEach(function (item) {
                panelWithLinks.add({
                    xtype: 'box',
                    style: 'padding-top: 8px; float: left; font-size: 12px;',
                    autoEl: {tag: 'a', href: '#', 'class': 'blulinkun bulkchangesdialoghref', html: _(item.option_name)},
                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', thisSearchWindow.linkOnClick.createDelegate(thisSearchWindow, [item.option_id, item.option_name]), thisSearchWindow, {stopEvent: true});
                        }
                    }
                });
            });

            arrItems.push(panelWithLinks);
            break;
    }

    ApplicantsQueueApplyChangesWindow.superclass.constructor.call(this, {
        layout: 'fit',
        resizable: false,
        bodyStyle: 'padding: 5px; background-color:white;',
        buttonAlign: 'right',
        modal: true,
        autoWidth: true,
        items: [
            {
                xtype: 'form',
                labelAlign: 'top',
                labelWidth: 60,
                items: arrItems
            }
        ],

        buttons: [
            {
                text: thisSearchWindow.strFieldType != 'assigned_staff' ? _('Cancel') : _('No'),
                handler: function () {
                    thisSearchWindow.close();
                }
            },
            {
                text: thisSearchWindow.strFieldType == 'assigned_staff' ? _('Yes') : _('Submit'),
                cls: 'orange-btn',
                iconCls: this.iconCls,
                handler: this.applyChanges.createDelegate(this)
            }
        ]
    });

    this.on('beforeshow', function () {
        if (config.strFieldType === 'push_to_queue' && !config.arrComboData.length) {
            Ext.simpleConfirmation.warning(String.format(_('There are no {0}s to push to.'), arrApplicantsSettings.office_label));
            return false;
        }
    });
};

Ext.extend(ApplicantsQueueApplyChangesWindow, Ext.Window, {
    applyChanges: function (option) {
        var win = this;

        var companyFieldId = '';

        if (win.strFieldType == 'assigned_staff') {
            companyFieldId = Ext.getCmp('fieldCombo').getValue();
            if (empty(companyFieldId)) {
                Ext.simpleConfirmation.warning('Please select a field.');
                return false;
            }
        }

        var selectedOption;
        var arrCheckedCheckboxesLabels = [];
        if (win.strFieldType == 'assigned_staff') {
            selectedOption = Ext.getCmp('changeToCombo').getValue();
        } else if (win.strFieldType == 'push_to_queue') {
            var arrCheckedCheckboxesValues = [];
            var group = Ext.getCmp('officeChangeToGroup');
            group.items.each(function (item) {
                if (item.checked) {
                    arrCheckedCheckboxesValues.push(item.inputValue);
                    arrCheckedCheckboxesLabels.push(item.boxLabel);
                }
            });

            selectedOption = arrCheckedCheckboxesValues.join(',');
        } else {
            selectedOption = option;
        }

        if (empty(selectedOption) && (win.strFieldType == 'assigned_staff' || win.strFieldType == 'push_to_queue')) {
            Ext.simpleConfirmation.warning('Please select an option.');
            return false;
        }

        if (win.strFieldType == 'push_to_queue' && selectedOption == win.strSelectedOffices) {
            Ext.simpleConfirmation.warning('Nothing to change.');
            return false;
        }

        win.getEl().mask('Updating...');

        Ext.Ajax.request({
            url: this.submitToUrl,
            timeout: 300000, // 5 minutes
            params: {
                arrClientIds: Ext.encode(this.arrSelectedClientIds),
                companyFieldId: Ext.encode(companyFieldId),
                selectedOption: Ext.encode(selectedOption)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    if (typeof win.onSuccessUpdate === 'function') {
                        win.onSuccessUpdate(selectedOption, arrCheckedCheckboxesLabels);
                    }

                    win.close();
                } else {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                win.getEl().unmask();
            }
        });
    },

    linkOnClick: function (option, optionName) {
        var msg = 'You have chosen ';
        var str = '';
        var currentWindow = this;
        switch (this.strFieldType) {
            case 'push_to_queue':
                str = String.format(
                    'to Push {0} {1} to <i>{2}</i>.',
                    this.arrSelectedClientIds.length,
                    this.arrSelectedClientIds.length == 1 ? 'client' : 'clients',
                    optionName
                );
                break;

            case 'file_status':
                str = String.format(
                    'to Change Case Status of {0} {1} to <i>{2}</i>.',
                    this.arrSelectedClientIds.length,
                    this.arrSelectedClientIds.length == 1 ? 'client' : 'clients',
                    optionName
                );
                break;

            case 'visa_subclass':
                str = String.format(
                    'to Change Visa Subclass of {0} {1} to <i>{2}</i>.',
                    this.arrSelectedClientIds.length,
                    this.arrSelectedClientIds.length == 1 ? 'client' : 'clients',
                    optionName
                );
                break;
        }

        msg += str + '<br /><br />Are you sure you would like to proceed?';
        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                currentWindow.applyChanges(option);
            }
        });
    }

});