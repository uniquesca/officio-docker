var AutomaticRemindersActionsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisWindow = this;

    this.actionTypeCombo = new Ext.form.ComboBox({
        fieldLabel: _('Action Type'),
        emptyText: _('Please select action type...'),
        store: new Ext.data.Store({
            reader: new Ext.data.JsonReader({
                id: 'automatic_reminder_action_type_id'
            }, [
                {name: 'automatic_reminder_action_type_id'},
                {name: 'automatic_reminder_action_type_internal_name'},
                {name: 'automatic_reminder_action_type_name'}
            ])
        }),
        mode: 'local',
        displayField: 'automatic_reminder_action_type_name',
        valueField: 'automatic_reminder_action_type_id',
        triggerAction: 'all',
        selectOnFocus: true,
        forceSelection: true,
        allowBlank: false,
        width: 360
    });
    this.actionTypeCombo.on('beforeselect', this.switchPanelTypeContainers.createDelegate(this));

    /***** "Change field value" FIELDS *****/
    this.applicantTypeCombo = new Ext.form.ComboBox({
        fieldLabel: _('Applicant Type'),
        emptyText: _('Please select applicant type...'),
        width: 360,
        store: new Ext.data.Store({
            reader: new Ext.data.JsonReader({
                id: 'search_for_id'
            }, [
                {name: 'search_for_id'},
                {name: 'search_for_name'}
            ])
        }),
        mode: 'local',
        displayField: 'search_for_name',
        valueField: 'search_for_id',
        triggerAction: 'all',
        selectOnFocus: true,
        name: 'member_type',
        forceSelection: true,
        allowBlank: false,
        editable: false,
        typeAhead: false
    });
    this.applicantTypeCombo.on('beforeselect', this.showFields.createDelegate(this));

    this.fieldsCombo = new Ext.form.ComboBox({
        fieldLabel: _('Field'),
        emptyText: _('Please select a field...'),
        name: 'field_id',
        width: 360,
        listWidth: 360,
        allowBlank: false,
        store: new Ext.data.Store({
            reader: new Ext.data.JsonReader({
                fields: [
                    {name: 'field_id'},
                    {name: 'field_unique_id'},
                    {name: 'field_name'},
                    {name: 'field_type'},
                    {name: 'field_group_id'},
                    {name: 'field_group_name'},
                    {name: 'field_template_id'},
                    {name: 'field_template_name'}
                ]
            })
        }),
        displayField: 'field_name',
        valueField: 'field_id',
        typeAhead: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        searchContains: true,
        selectOnFocus: true,
        tpl: new Ext.XTemplate(
            '<tpl for=".">',
            '<tpl if="(this.field_template_id != values.field_template_id && !empty(values.field_template_id))">',
            '<tpl exec="this.field_template_id = values.field_template_id"></tpl>',
            '<h1 style="padding: 2px; background-color: #96BCEB;">{field_template_name}</h1>',
            '</tpl>',
            '<tpl if="this.field_group_name != values.field_group_name">',
            '<tpl exec="this.field_group_name = values.field_group_name"></tpl>',
            '<h1 style="padding: 2px 5px;">{field_group_name}</h1>',
            '</tpl>',
            '<tpl>',
            '<div class="x-combo-list-item" style="padding-left: 20px;">{field_name}</div>',
            '</tpl>',
            '</tpl>'
        )
    });
    this.fieldsCombo.on('beforeselect', this.showFieldOptions.createDelegate(this));
    this.fieldsCombo.on('afterrender', function () {
        this.reset();
    });

    this.changeFieldValueTextField = new Ext.form.TextField({
        fieldLabel: _('Field value'),
        name: 'text',
        width: 360,
        allowBlank: false,
        labelWidth: 100,
        hidden: true,
        disabled: true
    });

    this.changeFieldValueComboField = new Ext.form.ComboBox({
        fieldLabel: _('Field options'),
        name: 'option',
        allowBlank: false,
        hidden: true,
        labelWidth: 100,
        disabled: true,
        width: 360,
        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'option_id'
            }, [
                {name: 'option_id'},
                {name: 'option_name'}
            ])
        },
        displayField: 'option_name',
        valueField: 'option_id',
        typeAhead: false,
        mode: 'local',
        triggerAction: 'all',
        editable: false
    });

    this.changeFieldValueDateField = new Ext.form.DateField({
        fieldLabel: _('Field value'),
        name: 'date',
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort,
        preventMark: true,
        disabled: true,
        labelWidth: 100,
        width: 135
    });

    this.changeFieldValueDateNote = new Ext.form.DisplayField({
        value: _('You can use <a href="http://php.net/manual/en/datetime.formats.php" target="_blank">PHP date formats</a> here'),
        style: 'background-color: white; margin: 5px 0 0 5px; color: #677788; font-size: 12px; font-weight: bold;',
        width: 220
    });

    this.changeFieldValueDateContainer = new Ext.Panel({
        width: 465,
        xtype: 'panel',
        layout: 'column',
        bodyStyle: 'background-color:white;',
        hidden: true,
        name: 'container_datefield',
        items: [
            {
                columnWidth: 0.52,
                layout: 'form',
                items: this.changeFieldValueDateField
            }, {
                columnWidth: 0.48,
                layout: 'fit',
                items: this.changeFieldValueDateNote
            }
        ]
    });

    this.changeFieldValueContainer = new Ext.Container({
        hidden: true,
        layout: 'form',
        items: [
            thisWindow.applicantTypeCombo,
            thisWindow.fieldsCombo,
            {
                xtype: 'container',
                ref: '../../changeFieldValueFieldsContainer',
                layout: 'form',
                items: [
                    this.changeFieldValueTextField,
                    this.changeFieldValueComboField,
                    this.changeFieldValueDateContainer
                ]
            }
        ]
    });
    /***** "Change field value" FIELDS *****/


    /***** "Create task" FIELDS *****/
    this.taskSubject = new Ext.form.ComboBox({
        fieldLabel: _('Subject'),
        emptyText: _('Please enter a subject...'),
        name: 'task_subject',
        store: new Ext.data.ArrayStore({
            idIndex: 0,
            remoteSort: false,
            sortInfo: {
                field: 'task_subject',
                direction: 'ASC' // or 'DESC' (case-sensitive for local sorting)
            },
            fields: ['task_subject'],
            data: []
        }),
        tpl: '<tpl for="."><div class="x-combo-list-item">{task_subject}</div></tpl>',
        mode: 'local',
        allowBlank: false,
        displayField: 'task_subject',
        valueField: 'task_subject',
        triggerAction: 'all',
        selectOnFocus: true,
        width: 360
    });

    this.taskAssignTo = new Ext.form.ComboBox({
        fieldLabel: _('Assigned to'),
        emptyText: _('Please select...'),
        name: 'task_assign_to',
        store: new Ext.data.Store({
            data: [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'assign_to_id'},
                {name: 'assign_to_name'}
            ]))
        }),
        mode: 'local',
        allowBlank: false,
        displayField: 'assign_to_name',
        valueField: 'assign_to_id',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        width: 360
    });

    this.taskMessage = new Ext.form.TextArea({
        fieldLabel: _('Message'),
        emptyText: _('Please enter a message...'),
        name: 'task_message',
        style: 'box-sizing: content-box',
        anchor: '100%',
        height: 60
    });

    this.createTaskContainer = new Ext.Container({
        hidden: true,
        layout: 'form',
        items: [
            this.taskSubject,
            this.taskAssignTo,
            this.taskMessage
        ]
    });
    /***** "Create task" FIELDS *****/


    /***** "Send email" FIELDS *****/
    this.sendEmailTemplate = new Ext.form.ComboBox({
        name: 'template_id',
        fieldLabel: _('Template'),
        store: new Ext.data.Store({
            data: [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'templateId'},
                {name: 'templateName'}
            ]))
        }),
        mode: 'local',
        displayField: 'templateName',
        valueField: 'templateId',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        emptyText: _('Please select template...'),
        width: 360,
        allowBlank: false
    });

    this.toCombo = new Ext.form.ComboBox({
        name: 'to',
        fieldLabel: _('To'),
        store: new Ext.data.Store({
            data: [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'option_id'},
                {name: 'option_name'}
            ]))
        }),
        mode: 'local',
        displayField: 'option_name',
        valueField: 'option_id',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        emptyText: _('Please select addressee...'),
        width: 360,
        allowBlank: false
    });

    this.sendEmailContainer = new Ext.Container({
        hidden: true,
        layout: 'form',
        items: [
            this.toCombo,
            this.sendEmailTemplate
        ]
    });
    /***** "Send email" FIELDS *****/


    this.items = new Ext.FormPanel({
        frame: false,
        bodyStyle: 'padding:5px',
        labelWidth: 100,
        items: [
            {
                xtype: 'box',
                autoEl: {
                    tag: 'div',
                    style: 'margin-bottom: 10px',
                    html: _('Actions are about what needs to be done.<br>When the Auto task starts according to your schedule and<br>the Conditions you defined are met, what action should Auto task take?')
                }
            },

            thisWindow.actionTypeCombo,
            thisWindow.changeFieldValueContainer,
            thisWindow.createTaskContainer,
            thisWindow.sendEmailContainer
        ]
    });

    AutomaticRemindersActionsDialog.superclass.constructor.call(this, {
        title: config.params.action == 'add' ? '<i class="las la-plus"></i>' + _('Add new action') : '<i class="las la-pen"></i>' + _('Edit action'),
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        layout: 'form',
        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: config.params.action == 'add' ? _('Add action') : _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(AutomaticRemindersActionsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    switchPanelTypeContainers: function (combo, rec) {
        var thisDialog = this;
        switch (rec.data.automatic_reminder_action_type_internal_name) {
            case 'change_field_value':
                thisDialog.changeFieldValueContainer.setVisible(true);
                thisDialog.createTaskContainer.setVisible(false);
                thisDialog.sendEmailContainer.setVisible(false);
                break;

            case 'create_task':
                thisDialog.changeFieldValueContainer.setVisible(false);
                thisDialog.createTaskContainer.setVisible(true);
                thisDialog.sendEmailContainer.setVisible(false);
                break;

            case 'send_email':
                thisDialog.changeFieldValueContainer.setVisible(false);
                thisDialog.createTaskContainer.setVisible(false);
                thisDialog.sendEmailContainer.setVisible(true);
                break;

            default:
                break;
        }

        thisDialog.syncShadow();
    },

    sortFieldsByName: function (a, b) {
        var aName = a.field_name.toLowerCase();
        var bName = b.field_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    getGroupedFields: function (currentType) {
        var arrGroupedFields = [];
        var thisDialog = this;
        var notAllowedFieldTypes = ['case_internal_id', 'applicant_internal_id', 'multiple_combo'];

        if (is_superadmin) {
            notAllowedFieldTypes.push('office', 'office_multi');
        }

        if (!booAutomaticTurnedOn) {
            notAllowedFieldTypes.push('bcpnp_nomination_certificate_number');
        }

        if (currentType == 'case') {
            var arrFieldIds = [];
            for (var templateId in arrApplicantsSettings.case_group_templates) {
                if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                    Ext.each(arrApplicantsSettings.case_group_templates[templateId], function (group) {
                        Ext.each(group.fields, function (field) {
                            if (field.field_encrypted == 'N' && !arrFieldIds.has(field.field_unique_id) && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                                arrFieldIds.push(field.field_unique_id);
                                arrGroupedFields.push(field);
                            }
                        });
                    });
                }
            }
            arrGroupedFields.sort(this.sortFieldsByName);

        } else {
            Ext.each(arrApplicantsSettings.groups_and_fields[currentType][0]['fields'], function (group) {
                Ext.each(group.fields, function (field) {
                    if (field.field_encrypted == 'N' && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                        field.field_template_id = 0;
                        field.field_template_name = '';
                        field.field_group_id = group.group_id;
                        field.field_group_name = group.group_title;
                        arrGroupedFields.push(field);
                    }
                });
            });
        }

        return arrGroupedFields;
    },

    setComboValueAndFireBeforeSelectEvent: function (combo, value) {
        var index = combo.store.find(combo.valueField, value);
        var record = combo.store.getAt(index);
        if (record) {
            combo.fireEvent('beforeselect', combo, record, value);
            combo.setValue(value);
        }
    },

    loadSettings: function () {
        var thisWindow = this;

        thisWindow.getEl().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/automatic-reminder-actions/get',
            params: {
                reminder_id: thisWindow.params.reminder_id,
                action_id: thisWindow.params.action_id,
                act: Ext.encode(thisWindow.params.action)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    var action = resultData.action_info;

                    if (thisWindow.params.action != 'edit' && resultData.action_types.length > 0) {
                        // Automatically select the first option in the combo
                        action.action_type = resultData.action_types[0][thisWindow.actionTypeCombo.valueField];
                    }

                    // Load "action types" combo + set value for it
                    if (action.action_type !== undefined) {
                        thisWindow.actionTypeCombo.getStore().on('load', function () {
                            thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.actionTypeCombo, action.action_type);
                        }, this, {single: true});
                    }
                    thisWindow.actionTypeCombo.getStore().loadData(resultData.action_types);

                    // Set values for all fields related to "Change field value" action
                    var action_settings = {};
                    if (thisWindow.params.action == 'edit') {
                        action_settings = action.action_settings;
                        if (action_settings.member_type !== undefined) {
                            thisWindow.applicantTypeCombo.getStore().on('load', function () {
                                thisWindow.applicantTypeCombo.setValue(action_settings.member_type);

                                var rec = thisWindow.applicantTypeCombo.getStore().getById(action_settings.member_type);
                                thisWindow.showFields(thisWindow.applicantTypeCombo, rec);

                                thisWindow.fieldsCombo.setValue(action_settings.field_id);
                                var fieldsComboRec = thisWindow.fieldsCombo.getStore().getById(action_settings.field_id);
                                thisWindow.showFieldOptions(thisWindow.fieldsCombo, fieldsComboRec);

                                if (action_settings.option !== undefined) {
                                    thisWindow.changeFieldValueComboField.setValue(action_settings.option);
                                }

                                if (action_settings.text !== undefined) {
                                    thisWindow.changeFieldValueTextField.setValue(action_settings.text);
                                }

                                if (action_settings.date !== undefined) {
                                    $('#' + thisWindow.changeFieldValueDateField.getId()).val(action_settings.date);
                                }
                            }, this, {single: true});
                        }
                    }
                    thisWindow.applicantTypeCombo.getStore().loadData(resultData.member_types);

                    // Load templates combo + set value for it
                    if (action_settings.template_id !== undefined) {
                        thisWindow.sendEmailTemplate.getStore().on('load', function () {
                            thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.sendEmailTemplate, action_settings.template_id);
                        }, this, {single: true});
                    }
                    thisWindow.sendEmailTemplate.getStore().loadData(resultData.email_templates);

                    // Load "send email to" combo + set value for it
                    var toComboData = [
                        {
                            option_id: 'client',
                            option_name: _('Client')
                        }, {
                            option_id: 'employer',
                            option_name: _('Associated Employer')
                        }, {
                            option_id: 'responsible_staff',
                            option_name: officeLabel + _(' Responsible Staff')
                        }
                    ];

                    if (action_settings.to !== undefined) {
                        thisWindow.toCombo.getStore().on('load', function () {
                            thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.toCombo, action_settings.to);
                        }, this, {single: true});

                        Ext.each(resultData.assign_to_list, function (oData) {
                            // Add only active users/options or that were already saved
                            if (parseInt(oData['status']) == 1 || action_settings.to == oData['assign_to_id']) {
                                toComboData.push({
                                    option_id: oData['assign_to_id'],
                                    option_name: oData['assign_to_name']
                                });
                            }
                        });
                    } else {
                        Ext.each(resultData.assign_to_list, function (oData) {
                            // Add only active users/options
                            if (parseInt(oData['status']) == 1) {
                                toComboData.push({
                                    option_id: oData['assign_to_id'],
                                    option_name: oData['assign_to_name']
                                });
                            }
                        });
                    }

                    thisWindow.toCombo.getStore().loadData(toComboData);


                    // Load "task name" combo + set value for it
                    if (action_settings.task_subject !== undefined) {
                        thisWindow.taskSubject.getStore().on('load', function () {
                            thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.taskSubject, action_settings.task_subject);
                        }, this, {single: true});
                    }
                    thisWindow.taskSubject.getStore().loadData(resultData.task_subjects);

                    // Load "task assign to" combo + set value for it
                    var arrUpdatedUsersList = [];
                    if (action_settings.task_assign_to !== undefined) {
                        thisWindow.taskAssignTo.getStore().on('load', function () {
                            thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.taskAssignTo, action_settings.task_assign_to);
                        }, this, {single: true});


                        Ext.each(resultData.assign_to_list, function (oData) {
                            if (parseInt(oData['status']) == 1 || action_settings.task_assign_to == oData['assign_to_id']) {
                                arrUpdatedUsersList.push({
                                    assign_to_id: oData['assign_to_id'],
                                    assign_to_name: oData['assign_to_name']
                                });
                            }
                        });
                    } else {
                        Ext.each(resultData.assign_to_list, function (oData) {
                            if (parseInt(oData['status']) == 1) {
                                arrUpdatedUsersList.push({
                                    assign_to_id: oData['assign_to_id'],
                                    assign_to_name: oData['assign_to_name']
                                });
                            }
                        });
                    }

                    thisWindow.taskAssignTo.getStore().loadData(arrUpdatedUsersList);

                    // Set "task message" value
                    if (action_settings.task_message !== undefined) {
                        thisWindow.taskMessage.setValue(action_settings.task_message);
                    }

                    thisWindow.getEl().unmask();
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                    thisWindow.close();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                thisWindow.close();
            }
        });
    },

    showFields: function (combo, rec) {
        var booValueChanged = rec ? rec.data.search_for_id != combo.getValue() : false;

        // If data wasn't loaded for the combo - load it in the store
        if (empty(this.fieldsCombo.getStore().getCount()) || booValueChanged) {
            this.fieldsCombo.getStore().loadData(this.getGroupedFields([rec.data.search_for_id]));
        }

        if (booValueChanged) {
            // Reset fields combo value
            this.fieldsCombo.reset();

            // Hide all value fields
            var valueFields = this.changeFieldValueFieldsContainer.items.items;
            Ext.each(valueFields, function (f) {
                f.setVisible(false);
            });
        }

        this.syncShadow();
    },

    showFieldOptions: function (combo, fieldsComboRec) {
        var thisDialog = this;

        if (!fieldsComboRec) {
            var fieldsComboVal = thisDialog.fieldsCombo.getValue();

            var idx = thisDialog.fieldsCombo.store.find(thisDialog.fieldsCombo.valueField, fieldsComboVal);
            if (idx !== -1) {
                fieldsComboRec = thisDialog.fieldsCombo.store.getAt(idx);
            }
        }

        var booShowDate = false;
        var booShowText = false;
        var booShowOptions = false;
        var arrOptionsData = [];

        if (fieldsComboRec) {

            switch (fieldsComboRec.data.field_type) {
                case 'short_date':
                case 'date':
                case 'date_repeatable':
                    booShowDate = true;
                    break;

                case 'checkbox':
                    arrOptionsData = [
                        {
                            option_id: '1',
                            option_name: _('Checked')
                        }, {
                            option_id: '0',
                            option_name: _('Not Checked')
                        }
                    ];
                    booShowOptions = true;
                    break;

                case 'kskeydid':
                case 'bcpnp_nomination_certificate_number':
                    arrOptionsData = [
                        {
                            option_id: '0',
                            option_name: _('Generate')
                        }
                    ];
                    booShowOptions = true;
                    break;

                case 'combo':
                    arrOptionsData = arrApplicantsSettings.options[thisDialog.applicantTypeCombo.getValue()][fieldsComboRec.data.field_id];
                    booShowOptions = true;
                    break;

                case 'office_multi':
                    arrOptionsData = arrApplicantsSettings.options['general']['office'];
                    booShowOptions = true;
                    break;

                case 'agents':
                case 'office':
                case 'assigned_to':
                case 'staff_responsible_rma':
                case 'categories':
                case 'contact_sales_agent':
                case 'country':
                case 'employer_contacts':
                    arrOptionsData = arrApplicantsSettings.options['general'][fieldsComboRec.data.field_type];
                    booShowOptions = true;
                    break;

                case 'case_status':
                    arrOptionsData = arrApplicantsSettings.options.general.case_statuses['all'];
                    booShowOptions = true;
                    break;

                case 'email':
                    booShowText = true;
                    Ext.apply(thisDialog.changeFieldValueTextField, {vtype: 'email'});
                    break;

                default:
                    Ext.apply(thisDialog.changeFieldValueTextField, {vtype: ''});
                    booShowText = true;
                    break;
            }
        }

        // Show only required fields, hide others
        thisDialog.changeFieldValueTextField.setVisible(booShowText);
        thisDialog.changeFieldValueComboField.setVisible(booShowOptions);
        thisDialog.changeFieldValueDateContainer.setVisible(booShowDate);

        // Disable other fields - for correct form validation
        thisDialog.changeFieldValueTextField.setDisabled(!booShowText);
        thisDialog.changeFieldValueComboField.setDisabled(!booShowOptions);
        thisDialog.changeFieldValueDateField.setDisabled(!booShowDate);

        if (booShowOptions) {
            thisDialog.changeFieldValueComboField.store.loadData(arrOptionsData);
            thisDialog.changeFieldValueComboField.reset();
        }

        thisDialog.syncShadow();
    },

    collectFieldsValues: function (field, settings, booError) {
        var thisWindow = this;
        if (typeof field.items !== 'undefined') {
            field.items.each(function (innerField) {
                booError = thisWindow.collectFieldsValues(innerField, settings, booError) || booError;
            });
        } else {
            if (typeof field.disabled !== 'undefined' && typeof field.getName() !== 'undefined' && !field.disabled) {
                var val = '';
                if (field.getXType() == 'datefield') {
                    val = field.getRawValue();
                    if (empty(val)) {
                        booError = true;
                        Ext.simpleConfirmation.error(_('Date cannot be empty'));
                    }
                } else {
                    if (!field.isValid()) {
                        booError = true;
                    } else {
                        val = field.getValue();
                    }
                }
                settings[field.getName()] = val;
            }
        }

        return booError;
    },

    saveChanges: function () {
        var thisWindow = this;
        var booError = false;
        var settings = {};

        var actionTypeId = thisWindow.actionTypeCombo.getValue();
        if (!thisWindow.actionTypeCombo.isValid()) {
            booError = true;
        }

        if (!booError) {
            switch (thisWindow.actionTypeCombo.getRawValue()) {
                case 'Change field value':
                    booError = thisWindow.collectFieldsValues(thisWindow.changeFieldValueContainer, settings, booError);
                    break;

                case 'Create task':
                    booError = thisWindow.collectFieldsValues(thisWindow.createTaskContainer, settings, booError);
                    break;

                case 'Send email':
                    booError = thisWindow.collectFieldsValues(thisWindow.sendEmailContainer, settings, booError);
                    break;

                default:
                    Ext.simpleConfirmation.warning(_('Unsupported action type'));
                    booError = true;
                    break;
            }
        }

        if (booError) {
            thisWindow.getEl().unmask();
            return false;
        }

        // save
        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/automatic-reminder-actions/save',
            params: {
                reminder_id: thisWindow.params.reminder_id,
                action_id: thisWindow.params.action_id,
                act: thisWindow.params.action,
                action_type_id: actionTypeId,
                settings: Ext.encode(settings)
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisWindow.getEl().mask(_('Done!'));

                    if (empty(thisWindow.params.reminder_id)) {
                        thisWindow.owner.newActionIds.push(resultData.action_id);
                        thisWindow.owner.actionsGrid.getStore().baseParams.action_ids = Ext.encode(thisWindow.owner.newActionIds);
                    }

                    thisWindow.owner.actionsGrid.getStore().reload();

                    if (!empty(thisWindow.params.reminder_id)) {
                        thisWindow.owner.owner.getStore().reload();
                    }

                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    }, 750);
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                    thisWindow.getEl().unmask();
                }
            },
            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Saving Error'));
            }
        });
    }
});