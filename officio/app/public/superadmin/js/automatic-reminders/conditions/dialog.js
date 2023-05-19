var AutomaticRemindersConditionsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisWindow = this;

    this.basedOnFieldFieldsCombo = new Ext.form.ComboBox({
        id: 'based_on_field_fields_combo',
        width: 350,
        listWidth: 350,
        minWidth: 350,
        allowBlank: false,
        store: new Ext.data.Store({
            reader: new Ext.data.JsonReader({
                fields: [
                    {name: 'field_id'},
                    {name: 'field_is_special', type: 'boolean'},
                    {name: 'field_unique_id'},
                    {name: 'field_name'},
                    {name: 'field_type'},
                    {name: 'field_group_id'},
                    {name: 'field_group_name'},
                    {name: 'field_client_type'},
                    {name: 'field_template_id'},
                    {name: 'field_template_name'}
                ]
            }),
            data: this.getAllGroupedFields()
        }),
        displayField: 'field_name',
        valueField: 'field_id',
        typeAhead: false,
        hideLabel: true,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        emptyText: _('Please select a Field Name...'),
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
            '<tpl if="field_is_special">',
            '<div class="x-combo-list-item search-list-odd-row"><i class="lar la-star"></i> {field_name}</div>',
            '</tpl>',
            '<tpl if="!field_is_special">',
            '<div class="x-combo-list-item" style="padding-left: 20px;">{field_name}</div>',
            '</tpl>',
            '</tpl>'
        )
    });

    this.basedOnFieldFieldsCombo.on('beforeselect', this.loadConditionsBasedOnFieldType.createDelegate(this));

    this.basedOnFieldConditionsCombo = new Ext.form.ComboBox({
        id: 'based_on_field_conditions_combo',
        hidden: true,
        allowBlank: false,
        store: {
            xtype: 'arraystore',
            fields: ['filter_id', 'filter_name'],
            data: []
        },
        emptyText: _('Please select...'),
        mode: 'local',
        displayField: 'filter_name',
        valueField: 'filter_id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        editable: false,
        width: 150,
        minWidth: 0
    });
    this.basedOnFieldConditionsCombo.on('beforeselect', this.loadOptionsBasedOnFieldType.createDelegate(this));

    this.fileStatusNumber = new Ext.form.NumberField({
        width: 60,
        hideLabel: true,
        value: 5,
        allowNegative: false,
        allowDecimals: false
    });

    this.fileStatusDaysCombo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: arrConditionSettings.calendar_combo_days,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'field_id'},
                {name: 'label'}
            ]))
        }),
        mode: 'local',
        displayField: 'label',
        valueField: 'field_id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        editable: false,
        value: 'CALENDAR',
        width: 145
    });

    this.fileStatusCombo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: arrConditionSettings.file_status,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'id'},
                {name: 'label'}
            ]))
        }),
        mode: 'local',
        displayField: 'label',
        valueField: 'id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        editable: false,
        width: 295,
        emptyText: _('Please select...'),

        listeners: {
            'render': function (combo) {
                thisWindow.updateComboWidthBasedOnOptions(combo, false);
            }
        }
    });

    this.basedOnFieldOptionsContainer = new Ext.Container({
        items: [
            {
                width: 210,
                xtype: 'panel',
                hidden: true,
                name: 'container_textfield',
                items: {
                    id: 'based_on_field_textfield',
                    xtype: 'textfield',
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 210
                }
            }, {
                width: 210,
                xtype: 'panel',
                hidden: true,
                name: 'container_textarea',
                items: {
                    id: 'based_on_field_textarea',
                    xtype: 'textarea',
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 210
                }
            }, {
                width: 210,
                xtype: 'panel',
                hidden: true,
                name: 'container_combo',
                items: {
                    id: 'based_on_field_combo',
                    xtype: 'combo',
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 190,
                    minWidth: 0,
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
                }
            }, {
                width: 310,
                xtype: 'panel',
                hidden: true,
                name: 'container_multiple_combo',
                items: {
                    id: 'based_on_field_multiple_combo',
                    xtype: 'lovcombo',
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 290,
                    minWidth: 0,
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
                    separator: ';',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    editable: true
                }
            }, {
                xtype: 'panel',
                layout: 'column',
                hidden: true,
                name: 'container_datefield',
                items: [
                    {
                        id: 'based_on_field_date',
                        xtype: 'datefield',
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        preventMark: true,
                        hidden: true,
                        disabled: true,
                        width: 150
                    }, {
                        xtype: 'box',
                        hidden: true,
                        autoEl: {
                            tag: 'div',
                            html: '<i class="las la-question-circle" style="font-size: 24px; padding: 5px; cursor: help"></i>'
                        },
                        listeners: {
                            scope: this,
                            render: function(c){
                                new Ext.ToolTip({
                                    target: c.getEl(),
                                    anchor: 'right',
                                    html: _('For these dates, you can replace day with DD, month with MM, and the year with YY. These values will be replaced with the today\'s date.')
                                });
                            }
                        }
                    }
                ]
            }, {
                xtype: 'panel',
                layout: 'column',
                hidden: true,
                name: 'container_daterange',
                items: [
                    {
                        id: 'based_on_field_date_range_from',
                        xtype: 'datefield',
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        preventMark: true,
                        hidden: true,
                        disabled: true,
                        width: 150
                    }, {
                        xtype: 'displayfield',
                        value: '&amp;',
                        style: 'background-color: white; margin: 12px 0 0 5px; color: #677788; font-size: 14px; font-weight: bold;',
                        width: 15,
                        hidden: true
                    }, {
                        id: 'based_on_field_date_range_to',
                        xtype: 'datefield',
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        preventMark: true,
                        hidden: true,
                        disabled: true,
                        width: 150
                    }, {
                        xtype: 'box',
                        hidden: true,
                        autoEl: {
                            tag: 'div',
                            html: '<i class="las la-question-circle" style="font-size: 24px; padding: 5px; cursor: help"></i>'
                        },
                        listeners: {
                            scope: this,
                            render: function(c){
                                new Ext.ToolTip({
                                    target: c.getEl(),
                                    anchor: 'right',
                                    html: _('For these dates, you can replace day with DD, month with MM, and the year with YY. These values will be replaced with the today\'s date.')
                                });
                            }
                        }
                    }
                ]
            }, {
                xtype: 'panel',
                layout: 'column',
                hidden: true,
                name: 'container_numberfield',
                items: [
                    {
                        id: 'based_on_field_date_number',
                        xtype: 'numberfield',
                        style: 'margin-right: 10px',
                        allowBlank: false,
                        allowDecimals: false,
                        allowNegative: false,
                        minValue: 0,
                        value: 0,
                        hidden: true,
                        disabled: true,
                        width: 70
                    }, {
                        id: 'based_on_field_date_period',
                        xtype: 'combo',
                        allowBlank: false,
                        hidden: true,
                        disabled: true,
                        width: 100,
                        minWidth: 0,
                        store: new Ext.data.Store({
                            data: arrConditionSettings.condition_date_next_filter,
                            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                                {name: 'field_id'},
                                {name: 'label'}
                            ]))
                        }),
                        value: 'D',
                        displayField: 'label',
                        valueField: 'field_id',
                        typeAhead: false,
                        mode: 'local',
                        triggerAction: 'all',
                        editable: false,

                        listeners: {
                            'render': function (combo) {
                                thisWindow.updateComboWidthBasedOnOptions(combo, false);
                            }
                        }
                    }
                ]
            }, {
                xtype: 'panel',
                hidden: true,
                name: 'container_case_status',
                layout: 'column',
                items: [
                    thisWindow.fileStatusCombo,
                    {html: '&nbsp;'},
                    thisWindow.fileStatusNumber,
                    {html: '&nbsp;'},
                    thisWindow.fileStatusDaysCombo,
                    {html: '<div style="color:#000; padding: 12px 6px 0;">' + _('Ago') + '</div>'},

                ]
            }
        ]
    });

    AutomaticRemindersConditionsDialog.superclass.constructor.call(this, {
        title: config.params.action == 'add' ? '<i class="las la-plus"></i>' + _('Add new condition') : '<i class="las la-pen"></i>' + _('Edit condition'),
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        layout: 'form',
        bodyStyle: 'padding: 15px',
        items: [
            {
                xtype: 'box',
                autoEl: {
                    tag: 'div',
                    style: 'margin-bottom: 10px',
                    html: _('Conditions are checked when the scheduled trigger happens. All the conditions for this task must be true to initiate the task.<br><br>Please select or edit the condition:')
                }
            }, {
                layout: 'table',
                cls: 'x-table-layout-cell-top-align',
                layoutConfig: {columns: 4},
                items: [
                    {
                        html: '<div style="color:#000; padding: 12px 6px 0 0;">' + _('If') + '</div>'
                    },
                    {
                        xtype: 'container',
                        style: 'margin-right: 10px',
                        items: thisWindow.basedOnFieldFieldsCombo,
                    },
                    thisWindow.basedOnFieldConditionsCombo,
                    thisWindow.basedOnFieldOptionsContainer
                ]
            }
        ],

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: config.params.action == 'add' ? _('Add condition') : _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(AutomaticRemindersConditionsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    getAllGroupedFields: function () {
        var arrGroupedFields = [];
        var notAllowedFieldTypes = [];
        var booSkipRepeatableGroups = true;

        var arrSearchTypes = [];
        arrSearchTypes = ['individual'];
        if (arrApplicantsSettings.access.employers_module_enabled) {
            arrSearchTypes.unshift('employer');
        }

        arrSearchTypes.forEach(function (currentType) {
            Ext.each(arrApplicantsSettings.groups_and_fields[currentType][0]['fields'], function (group) {
                if (booSkipRepeatableGroups && group.group_repeatable === 'Y') {
                    return;
                }

                Ext.each(group.fields, function (field) {
                    if (field.field_encrypted == 'N' && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                        field.field_template_id = 0;
                        field.field_template_name = '';
                        field.field_group_id = group.group_id;
                        field.field_group_name = group.group_title;
                        field.field_client_type = currentType;
                        field.field_is_special = false;
                        arrGroupedFields.push(field);
                    }
                });
            });
        });

        var arrFieldIds = [];
        var arrSpecialFields = [];
        var arrGroupedCaseFields = [];
        for (var templateId in arrApplicantsSettings.case_group_templates) {
            if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                Ext.each(arrApplicantsSettings.case_group_templates[templateId], function (group) {
                    Ext.each(group.fields, function (oField) {
                        // Create a copy -> don't change the parent object
                        var field = {...oField};

                        if (field.field_encrypted == 'N' && !arrFieldIds.has(field.field_unique_id) && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                            arrFieldIds.push(field.field_unique_id);

                            field.field_client_type = 'case';
                            if (field.field_type == 'case_type' || field.field_type == 'case_status') {
                                field.field_group_name = '';
                                field.field_is_special = true;
                                arrSpecialFields.push(field);
                            } else {
                                field.field_is_special = false;
                                field.field_group_name = empty(field.field_group_name) ? _('Case Details') : field.field_group_name;
                                arrGroupedCaseFields.push(field);
                            }
                        }
                    });
                });
            }
        }
        arrGroupedCaseFields.sort(this.sortFieldsByName);

        arrGroupedFields.push.apply(arrGroupedFields, arrGroupedCaseFields);

        arrSpecialFields.push({
            field_id: 'case_form_assigned',
            field_unique_id: 'case_form_assigned',
            field_name: _('Form assigned to a case'),
            field_type: 'case_form_assigned',
            field_is_special: true,
            field_client_type: 'case'
        });

        // Add special fields to the top of the list
        for (var i = 0; i < arrSpecialFields.length; i++) {
            arrGroupedFields.unshift(arrSpecialFields[i]);
        }

        return arrGroupedFields;
    },

    sortFieldsByName: function (a, b) {
        var aName = a.field_name.toLowerCase();
        var bName = b.field_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    getGroupedFields: function (currentType) {
        var arrGroupedFields = [];
        var notAllowedFieldTypes = ['case_internal_id', 'applicant_internal_id', 'multiple_combo'];

        if (is_superadmin) {
            notAllowedFieldTypes.push('office', 'office_multi');
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

    toggleConditionsGroup: function (groupName, arrOptionsData) {
        var thisWindow = this;
        this.basedOnFieldOptionsContainer.items.each(function (f) {
            var booShowGroup = f.name == groupName;
            f.items.each(function (innerField) {
                innerField.setVisible(booShowGroup);
                innerField.setDisabled(!booShowGroup);
            });

            f.setVisible(booShowGroup);
            f.setDisabled(!booShowGroup);

            if (booShowGroup && (groupName == 'container_combo' || groupName == 'container_multiple_combo')) {
                var combo = f.items.get(0);
                arrOptionsData = arrOptionsData || [];
                combo.getStore().loadData(arrOptionsData);
                thisWindow.updateComboWidthBasedOnOptions(combo, true);
            }
        });

        this.syncSize();
        this.syncShadow();
    },

    updateComboWidthBasedOnOptions: function (combo, booResizeParent) {
        // Calculate the width of each option, take the max
        var metrics = Ext.util.TextMetrics.createInstance(combo.getEl());

        var newComboWidth = combo.minWidth;
        if (!empty(combo.emptyText)) {
            newComboWidth = Ext.max([newComboWidth, metrics.getWidth(combo.emptyText) + 60]);
        }

        combo.getStore().each(function (oRecord) {
            newComboWidth = Ext.max([newComboWidth, metrics.getWidth(oRecord.data[combo.displayField]) + 60]);
        });
        newComboWidth = Ext.min([450, newComboWidth]);

        combo.setWidth(newComboWidth);
        combo.getResizeEl().setWidth(newComboWidth);

        if (booResizeParent) {
            combo.ownerCt.setWidth(newComboWidth);
        }
    },

    loadConditionsBasedOnFieldType: function (combo, rec) {
        var thisWindow = this;
        var booDisableFilterCombo = false;
        var conditionsCombo = this.basedOnFieldConditionsCombo;
        var booValueChanged = rec ? rec.data.field_id != combo.getValue() : false;

        // If data wasn't loaded for the combo - load it in the store
        if (empty(conditionsCombo.getStore().getCount()) || booValueChanged) {
            var arrConditions = [];

            switch (rec.data.field_type) {
                case 'float':
                case 'number':
                case 'auto_calculated':
                    arrConditions = arrConditionSettings.filters['number'];
                    break;

                case 'date':
                    arrConditions = arrConditionSettings.filters['date'];
                    break;

                case 'date_repeatable':
                    arrConditions = arrConditionSettings.filters['date_repeatable'];
                    break;

                case 'combo':
                case 'agents':
                case 'office':
                case 'office_multi':
                case 'assigned_to':
                case 'staff_responsible_rma':
                case 'contact_sales_agent':
                case 'employer_contacts':
                case 'categories':
                case 'country':
                    arrConditions = arrConditionSettings.filters['combo'];
                    break;

                case 'case_status':
                    // Create a copy -> don't change the parent object
                    arrConditions = [...arrConditionSettings.filters['combo']];

                    arrConditions.unshift(['changed_to', _('changed to')]);
                    break;

                case 'case_type':
                case 'case_form_assigned':
                    arrConditions = arrConditionSettings.filters['is'];
                    booDisableFilterCombo = true;
                    break;

                case 'checkbox':
                    arrConditions = arrConditionSettings.filters['checkbox'];
                    break;

                case 'multiple_text_fields':
                    arrConditions = arrConditionSettings.filters['multiple_text_fields'];
                    break;

                default:
                    arrConditions = arrConditionSettings.filters['text'];
                    break;
            }

            conditionsCombo.setVisible(true);
            conditionsCombo.getStore().loadData(arrConditions);
            thisWindow.updateComboWidthBasedOnOptions(conditionsCombo, false);
        }

        if (booValueChanged) {
            // Reset conditions combo value
            conditionsCombo.setDisabled(booDisableFilterCombo);
            conditionsCombo.setValue();
            conditionsCombo.clearInvalid();

            Ext.getCmp('based_on_field_combo').reset();
            $('#based_on_field_multiple_combo').val('');

            this.toggleConditionsGroup('');

            // Automatically preselect the first option if it is only one in the combo
            setTimeout(function () {
                if (conditionsCombo.getStore().getCount() == 1) {
                    var rec = conditionsCombo.getStore().getAt(0);
                    var realValue = rec.data[conditionsCombo.valueField];
                    conditionsCombo.setValue(realValue);
                    conditionsCombo.fireEvent('beforeselect', conditionsCombo, rec, realValue);
                }
            }, 100);
        }
    },

    getSelectedFieldRecord: function () {
        var fieldsComboRec;
        var fieldsComboVal = this.basedOnFieldFieldsCombo.getValue();
        var idx = this.basedOnFieldFieldsCombo.store.find(this.basedOnFieldFieldsCombo.valueField, fieldsComboVal);
        if (idx !== -1) {
            fieldsComboRec = this.basedOnFieldFieldsCombo.store.getAt(idx);
        }

        return fieldsComboRec;
    },

    loadOptionsBasedOnFieldType: function (combo, filterComboRec) {
        var fieldsComboRec = this.getSelectedFieldRecord();
        var filterVal = filterComboRec.data.filter_id;

        var strContainerToShow = '';
        var arrOptionsData = [];
        switch (fieldsComboRec.data.field_type) {
            case 'short_date':
            case 'date':
            case 'date_repeatable':
                if (['is', 'is_not', 'is_before', 'is_after', 'is_between_today_and_date', 'is_between_date_and_today'].has(filterVal)) {
                    strContainerToShow = 'container_datefield';
                }

                if (['is_between_2_dates'].has(filterVal)) {
                    strContainerToShow = 'container_daterange';
                }

                if (['is_in_the_next', 'is_in_the_previous'].has(filterVal)) {
                    strContainerToShow = 'container_numberfield';
                }
                break;

            case 'combo':
                if (['is_one_of', 'is_none_of'].has(filterVal)) {
                    strContainerToShow = 'container_multiple_combo';
                } else {
                    strContainerToShow = 'container_combo';
                }
                arrOptionsData = arrApplicantsSettings.options[fieldsComboRec.data.field_client_type][fieldsComboRec.data.field_id];
                break;

            case 'office':
            case 'office_multi':
                if (['is_one_of', 'is_none_of'].has(filterVal)) {
                    strContainerToShow = 'container_multiple_combo';
                } else {
                    strContainerToShow = 'container_combo';
                }
                arrOptionsData = arrApplicantsSettings.options['general']['office'];
                break;

            case 'agents':
            case 'assigned_to':
            case 'staff_responsible_rma':
            case 'categories':
            case 'contact_sales_agent':
            case 'country':
            case 'employer_contacts':
                if (['is_one_of', 'is_none_of'].has(filterVal)) {
                    strContainerToShow = 'container_multiple_combo';
                } else {
                    strContainerToShow = 'container_combo';
                }
                arrOptionsData = arrApplicantsSettings.options['general'][fieldsComboRec.data.field_type];
                break;


            case 'case_form_assigned':
                strContainerToShow = 'container_combo';
                Ext.each(arrConditionSettings.forms, function (oForm) {
                    arrOptionsData.push({
                        option_id: oForm.form_id,
                        option_name: oForm.form_name
                    });
                });
                break;

            case 'case_status':
                switch (filterVal) {
                    case 'is_one_of':
                    case 'is_none_of':
                        strContainerToShow = 'container_multiple_combo';
                        break;

                    case 'changed_to':
                        strContainerToShow = 'container_case_status';
                        break;

                    default:
                        strContainerToShow = 'container_combo';
                        break;
                }

                Ext.each(arrConditionSettings.file_status, function (caseStatus) {
                    arrOptionsData.push({
                        option_id: caseStatus.id,
                        option_name: caseStatus.label
                    });
                });
                break;

            case 'case_type':
                if (['is_one_of', 'is_none_of'].has(filterVal)) {
                    strContainerToShow = 'container_multiple_combo';
                } else {
                    strContainerToShow = 'container_combo';
                }
                Ext.each(arrApplicantsSettings.case_templates, function (caseTemplate) {
                    arrOptionsData.push({
                        option_id: caseTemplate.case_template_id,
                        option_name: caseTemplate.case_template_name
                    });
                });
                break;

            case 'float':
            case 'number':
            case 'auto_calculated':
                strContainerToShow = 'container_textfield';
                break;

            // case 'text':
            // case 'password':
            // case 'email':
            // case 'phone':
            // case 'memo':
            default:
                if (['contains', 'does_not_contain', 'is', 'is_not', 'starts_with', 'ends_with'].has(filterVal)) {
                    strContainerToShow = 'container_textfield';
                } else if (['is_one_of', 'is_none_of'].has(filterVal)) {
                    strContainerToShow = 'container_textarea';
                }
                break;
        }

        this.toggleConditionsGroup(strContainerToShow, arrOptionsData);
    },

    getConditionTypeCheckedRadio: function () {
        var selectedConditionType = null;

        var arrRadios = this.find('name', 'automatic_reminder_condition_type');
        Ext.each(arrRadios, function (oRadio) {
            if (oRadio.getValue()) {
                selectedConditionType = oRadio.inputValue;
            }
        });

        return selectedConditionType;
    },

    loadSettings: function () {
        var thisWindow = this;

        if (thisWindow.params.action == 'edit') {
            thisWindow.getEl().mask(_('Loading...'));
            Ext.Ajax.request({
                url: baseUrl + "/automatic-reminder-conditions/get",
                params: {
                    reminder_id: thisWindow.params.reminder_id,
                    condition_id: thisWindow.params.condition_id,
                    act: Ext.encode(thisWindow.params.action)
                },
                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        // Set values
                        var oSettings = resultData.automatic_reminder_condition_settings;
                        switch (resultData.automatic_reminder_condition_type_internal_id) {
                            case 'CLIENT_PROFILE':
                            case 'PROFILE':
                                thisWindow.setComboByFieldId(thisWindow.basedOnFieldFieldsCombo, oSettings.prof, resultData.automatic_reminder_condition_type_internal_id === 'PROFILE');

                                var condition = oSettings.ba === 'AFTER' ? 'is_in_the_next' : 'is_in_the_previous';
                                thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.basedOnFieldConditionsCombo, condition);

                                var datePeriodField = Ext.getCmp('based_on_field_date_period');
                                var period = oSettings.days === 'CALENDAR' ? 'D' : 'BD';
                                thisWindow.setComboValueAndFireBeforeSelectEvent(datePeriodField, period);

                                Ext.getCmp('based_on_field_date_number').setValue(oSettings.number);
                                break;

                            case 'BASED_ON_FIELD':
                                thisWindow.setComboByFieldId(thisWindow.basedOnFieldFieldsCombo, oSettings.based_on_field_field_id, oSettings.based_on_field_member_type === 'case');
                                thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.basedOnFieldConditionsCombo, oSettings.based_on_field_condition);

                                if (oSettings.based_on_field_date_period !== undefined) {
                                    Ext.getCmp('based_on_field_date_period').setValue(oSettings.based_on_field_date_period);
                                }

                                if (oSettings.based_on_field_date_number !== undefined) {
                                    Ext.getCmp('based_on_field_date_number').setValue(oSettings.based_on_field_date_number);
                                }

                                if (oSettings.based_on_field_date_range_to !== undefined) {
                                    Ext.getCmp('based_on_field_date_range_to').setRawValue(oSettings.based_on_field_date_range_to);
                                 }

                                if (oSettings.based_on_field_date_range_from !== undefined) {
                                    Ext.getCmp('based_on_field_date_range_from').setRawValue(oSettings.based_on_field_date_range_from);
                                }

                                if (oSettings.based_on_field_date !== undefined) {
                                    // Set via setRawValue - so text values will be set correctly
                                    Ext.getCmp('based_on_field_date').setRawValue(oSettings.based_on_field_date);
                                }

                                if (oSettings.based_on_field_combo !== undefined) {
                                    Ext.getCmp('based_on_field_combo').setValue(oSettings.based_on_field_combo);
                                }

                                if (oSettings.based_on_field_multiple_combo !== undefined) {
                                    Ext.getCmp('based_on_field_multiple_combo').setValue(oSettings.based_on_field_multiple_combo);
                                }

                                if (oSettings.based_on_field_textfield !== undefined) {
                                    Ext.getCmp('based_on_field_textfield').setValue(oSettings.based_on_field_textfield);
                                }

                                if (oSettings.based_on_field_textarea !== undefined) {
                                    Ext.getCmp('based_on_field_textarea').setValue(oSettings.based_on_field_textarea);
                                }
                                break;

                            case 'FILESTATUS':
                                thisWindow.setComboByFieldType(thisWindow.basedOnFieldFieldsCombo, 'case_status');
                                thisWindow.setComboValueAndFireBeforeSelectEvent(thisWindow.basedOnFieldConditionsCombo, 'changed_to');
                                thisWindow.fileStatusNumber.setValue(oSettings.number);
                                thisWindow.fileStatusDaysCombo.setValue(oSettings.days);
                                thisWindow.fileStatusCombo.setValue(oSettings.file_status);
                                break;

                            case 'CASE_TYPE':
                                thisWindow.setComboByFieldType(thisWindow.basedOnFieldFieldsCombo, 'case_type');
                                var optionsCombo = Ext.getCmp('based_on_field_combo');
                                optionsCombo.getStore().on('load', function () {
                                    optionsCombo.setValue(oSettings.case_type);
                                }, this, {single: true});
                                break;

                            case 'CASE_HAS_FORM':
                                thisWindow.setComboByFieldType(thisWindow.basedOnFieldFieldsCombo, 'case_form_assigned');
                                var optionsCombo = Ext.getCmp('based_on_field_combo');
                                optionsCombo.getStore().on('load', function () {
                                    optionsCombo.setValue(oSettings.form_id);
                                }, this, {single: true});
                                break;

                            default:
                                break;
                        }

                        thisWindow.center();
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
        }
    },

    setComboByFieldId: function (combo, fieldId, booFieldOfCase) {
        var index = combo.store.findBy(function (record) {
            var booOk = false;
            if (record['data']['field_id'] == fieldId) {
                if ((booFieldOfCase && record['data']['field_client_type'] === 'case') || (!booFieldOfCase && record['data']['field_client_type'] !== 'case')) {
                    booOk = true;
                }
            }

            return booOk;
        });

        if (index !== -1) {
            var record = combo.store.getAt(index);
            var realValue = record.data[combo.valueField];

            combo.fireEvent('beforeselect', combo, record, realValue);
            combo.setValue(realValue);
        }
    },

    setComboByFieldType: function (combo, fieldType) {
        var index = combo.store.findBy(function (record) {
            var booOk = false;
            if (record['data']['field_type'] == fieldType) {
                booOk = true;
            }

            return booOk;
        });

        if (index !== -1) {
            var record = combo.store.getAt(index);
            var realValue = record.data[combo.valueField];

            combo.fireEvent('beforeselect', combo, record, realValue);
            combo.setValue(realValue);
        }
    },

    setComboValueAndFireBeforeSelectEvent: function (combo, value) {
        var index = combo.store.find(combo.valueField, value);
        var record = combo.store.getAt(index);
        if (record) {
            combo.fireEvent('beforeselect', combo, record, value);
            combo.setValue(value);
        }
    },

    saveChanges: function () {
        var thisWindow = this;
        var fieldsComboRec = this.getSelectedFieldRecord();

        var allParams = {
            'reminder_id': empty(thisWindow.params.reminder_id) ? null : thisWindow.params.reminder_id,
            'reminder_condition_id': empty(thisWindow.params.condition_id) ? null : thisWindow.params.condition_id,
            'type': ''
        };

        var booError = false;
        if (empty(fieldsComboRec)) {
            this.basedOnFieldFieldsCombo.markInvalid(_('This field is required'));
            booError = true;
        }

        if (!booError) {
            var conditionsCombo = this.basedOnFieldConditionsCombo;
            var optionsCombo = Ext.getCmp('based_on_field_combo');
            if (fieldsComboRec.data.field_type == 'case_form_assigned') {
                allParams['type'] = 'CASE_HAS_FORM';
                allParams['form_id'] = optionsCombo.getValue();

                if (empty(allParams['form_id'])) {
                    optionsCombo.markInvalid(_('This field is required'));
                    booError = true;
                }
            } else if (fieldsComboRec.data.field_type == 'case_type') {
                allParams['type'] = 'CASE_TYPE';
                allParams['case_type'] = optionsCombo.getValue();

                if (empty(allParams['case_type'])) {
                    optionsCombo.markInvalid(_('This field is required'));
                    booError = true;
                }
            } else if (fieldsComboRec.data.field_type == 'case_status') {
                if (empty(conditionsCombo.getValue())) {
                    conditionsCombo.markInvalid(_('This field is required'));
                    booError = true;
                } else {
                    allParams['type'] = 'FILESTATUS';
                    if (conditionsCombo.getValue() == 'changed_to') {

                        allParams['number'] = thisWindow.fileStatusNumber.getValue();
                        if (allParams['number'] === '') {
                            thisWindow.fileStatusNumber.markInvalid(_('This field is required'));
                            booError = true;
                        }

                        allParams['days'] = thisWindow.fileStatusDaysCombo.getValue();
                        allParams['ba'] = 'AFTER';

                        allParams['file_status'] = thisWindow.fileStatusCombo.getValue();
                        if (allParams['file_status'] === '') {
                            thisWindow.fileStatusCombo.markInvalid(_('This field is required'));
                            booError = true;
                        }
                    } else {

                    }
                }
            } else {
                var datePeriodField = Ext.getCmp('based_on_field_date_period');
                if (['is_in_the_next', 'is_in_the_previous'].has(conditionsCombo.getValue()) && ['D', 'BD'].has(datePeriodField.getValue())) {
                    allParams['type'] = fieldsComboRec.data.field_client_type == 'case' ? 'PROFILE' : 'CLIENT_PROFILE';
                    allParams['ba'] = conditionsCombo.getValue() == 'is_in_the_next' ? 'AFTER' : 'BEFORE';
                    allParams['days'] = datePeriodField.getValue() == 'BD' ? 'BUSINESS' : 'CALENDAR';
                    allParams['prof'] = fieldsComboRec.data.field_id;

                    var numberField = Ext.getCmp('based_on_field_date_number');
                    allParams['number'] = numberField.getValue();
                    if (allParams['number'] === '') {
                        numberField.markInvalid(_('This field is required'));
                        booError = true;
                    }
                }
            }

            if (!booError && empty(allParams['type'])) {
                allParams['type'] = 'BASED_ON_FIELD';
                allParams['based_on_field_member_types_combo'] = fieldsComboRec.data.field_client_type;

                var arrFieldsToCheck = [
                    'based_on_field_fields_combo',
                    'based_on_field_conditions_combo',
                    'based_on_field_textfield',
                    'based_on_field_textarea',
                    'based_on_field_combo',
                    'based_on_field_multiple_combo',
                    'based_on_field_date',
                    'based_on_field_date_range_from',
                    'based_on_field_date_range_to',
                    'based_on_field_date_number',
                    'based_on_field_date_period'
                ];

                Ext.each(arrFieldsToCheck, function (fieldId) {
                    allParams[fieldId] = null;

                    var field = Ext.getCmp(fieldId);
                    if (field.isVisible() && !field.disabled) {
                        var fieldVal;

                        if (['based_on_field_date', 'based_on_field_date_range_from', 'based_on_field_date_range_to'].has(fieldId)) {
                            fieldVal = field.getRawValue();
                            if (empty(fieldVal)) {
                                Ext.simpleConfirmation.error(_('Date cannot be empty'));
                                booError = true;
                            }
                            allParams[fieldId] = fieldVal;

                        } else {
                            booError = booError || !field.isValid();

                            if (field.isValid()) {
                                fieldVal = field.getValue();
                                allParams[fieldId] = fieldVal;
                            }
                        }
                    }
                });
            }
        }

        if (booError) {
            return false;
        }

        //save
        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/automatic-reminder-conditions/save',
            params: allParams,

            success: function (f) {
                try {
                    var resultData = Ext.decode(f.responseText);
                    if (resultData.success) {
                        thisWindow.getEl().mask('Done!');

                        if (empty(thisWindow.params.reminder_id)) {
                            thisWindow.owner.newConditionIds.push(resultData.condition_id);
                            thisWindow.owner.conditionsGrid.getStore().baseParams.condition_ids = Ext.encode(thisWindow.owner.newConditionIds);
                        }

                        thisWindow.owner.conditionsGrid.getStore().reload();

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
                } catch (e) {
                    Ext.simpleConfirmation.error(_('Error happened during data saving'));
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