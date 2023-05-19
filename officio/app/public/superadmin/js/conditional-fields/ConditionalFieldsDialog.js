var ConditionalFieldsDialog = function (config) {
    Ext.apply(this, config);
    var thisDialog = this;

    var booEditMode = thisDialog.mode === 'edit';

    var arrComboOptions = [];
    var fieldConditionXType = 'combo';
    var tpl;
    if (this.fieldType === 'checkbox') {
        arrComboOptions.push([0, _('Unchecked')]);
        arrComboOptions.push(['checked', _('Checked')]);


        tpl = new Ext.XTemplate(
            '<tpl for="."><tpl if="this.isOptionUsed(option_id)"><div class="x-combo-list-item x-item-disabled">{option_label}</div></tpl>',
            '<tpl if="this.isOptionUsed(option_id) == false"><div class="x-combo-list-item">{option_label}</div></tpl></tpl>',
            {
                isOptionUsed: function (option_id) {
                    return booEditMode;
                }
            }
        );
    } else {
        arrComboOptions.push([0, _('-- NOT SELECTED --')]);

        for (var i = 0; i < this.fieldOptions.length; i++) {
            var fieldOption = this.fieldOptions[i];
            arrComboOptions.push([fieldOption[0], fieldOption[1]]);
        }

        fieldConditionXType = 'lovcombo';

        tpl = new Ext.XTemplate(
            '<tpl for=".">'
            + '<div class="x-combo-list-item">'
            + '<img src="' + Ext.BLANK_IMAGE_URL + '" '
            + 'class="ux-lovcombo-icon ux-lovcombo-icon-'
            + '{[values.checked?"checked":"unchecked"' + ']}">'
            + '<tpl for="."><tpl if="this.isOptionUsed(option_id)"><div class="ux-lovcombo-item-text x-item-disabled">{' + ('option_label') + ':htmlEncode}</div></tpl>'
            + '<tpl if="this.isOptionUsed(option_id) == false"><div class="ux-lovcombo-item-text">{' + ('option_label') + ':htmlEncode}</div></tpl></tpl>'
            + '</div>'
            + '</tpl>',
            {
                isOptionUsed: function (option_id) {
                    return booEditMode;
                }
            }
        );
    }

    var checkedOptions = undefined;
    Ext.each(thisDialog.arrSelectedOptions, function (option) {
        if (checkedOptions !== undefined) {
            checkedOptions += ';';
        } else {
            checkedOptions = '';
        }
        checkedOptions += option['data']['field_option_id'] + '';
    });

    var arrGroupsAndFields = [
        {
            xtype: 'container',
            layout: 'form',
            colspan: 2,
            items: [
                {
                    style: 'padding: 10px 0;',
                    html: _('Select the option and then select the fields to hide.<br>Grayed out options, already have a profile; use edit for those.')
                }, {
                    xtype: fieldConditionXType,
                    fieldLabel: _('Field Option'),
                    name: 'condition_option_id',
                    hiddenName: 'condition_option_id',
                    forceSelection: true,
                    searchContains: true,
                    allowBlank: false,
                    mode: 'local',
                    separator: ';',
                    useSelectAll: false,

                    store: new Ext.data.ArrayStore({
                        fields: ['option_id', 'option_label'],
                        data: arrComboOptions
                    }),

                    triggerAction: 'all',
                    emptyText: _('Please select...'),
                    value: checkedOptions,
                    displayField: 'option_label',
                    valueField: 'option_id',
                    width: 250,
                    tpl: tpl,

                    listeners: {
                        'beforeselect': function (combo, record) {
                            // Don't allow selecting disabled options (except for the current one)
                            if (booEditMode) {
                                return false;
                            }
                        }
                    }
                }, {
                    html: '&nbsp;'
                }
            ]
        }
    ];

    var arrFieldsToIgnore = [];
    var arrGroupsToIgnore = [];
    for (i = 0; i < this.arrGroupedFields.length; i++) {
        var oGroup = this.arrGroupedFields[i];

        if (empty(i)) {
            arrGroupsAndFields.push({
                cellCls: 'td-header-cell',
                html: _('Group / Field Name')
            });

            arrGroupsAndFields.push({
                cellCls: 'td-header-cell',
                html: _('Is Hidden')
            });
        }

        arrGroupsAndFields.push({
            colspan: 2,
            html: '&nbsp;'
        });

        arrGroupsAndFields.push({
            xtype: 'label',
            style: 'font-weight: bold',
            html: oGroup['group_title']
        });

        var booGroupDisabled = false;
        if (oGroup['fields'] && oGroup['fields'].length) {
            for (var j = 0; j < oGroup['fields'].length; j++) {
                if ((oGroup['fields'][j]['field_required'] === 'Y' && !oGroup['fields'][j]['field_skip_access_requirements']) || thisDialog.conditionFieldId === 'field_' + oGroup['fields'][j]['field_id']) {
                    booGroupDisabled = true;
                    break;
                }
            }
        }

        var msgHelp = booGroupDisabled ? String.format(
            '<i class="las la-info-circle help-icon" ext:qtip="{0}"></i>',
            _('There are required (and `Skip Access Requirements` is not checked) fields in the group or current field is in this group. This group cannot be hidden.')
        ) : '';

        var booGroupChecked = false;
        if (!booGroupDisabled && booEditMode) {
            var arrGroupCheckedForOptions = [];
            Ext.each(thisDialog.arrSelectedOptions, function (option) {
                if (option['data']['field_condition_hidden_groups'].has(oGroup['group_id'])) {
                    arrGroupCheckedForOptions.push(option['data']['field_option_label']);
                }
            });

            if (arrGroupCheckedForOptions.length === thisDialog.arrSelectedOptions.length) {
                booGroupChecked = true;
            } else {
                if (arrGroupCheckedForOptions.length > 0) {
                    arrGroupsToIgnore.push(oGroup['group_id']);

                    booGroupDisabled = true;

                    msgHelp = String.format(
                        '<i class="las la-info-circle help-icon help-icon-error" ext:qtip="{0}"></i>',
                        _('This Group is hidden for:') + '<br />' + arrGroupCheckedForOptions.join('<br />')
                    );
                }
            }
        }

        arrGroupsAndFields.push({
            xtype: 'checkbox',
            boxLabel: _('Group Hidden') + msgHelp,
            thisGroupId: oGroup['group_id'],
            name: 'condition_groups_hidden[]',
            inputValue: oGroup['group_id'],
            disabled: booGroupDisabled,
            checked: booGroupChecked,

            listeners: {
                'check': this.onGroupCheckUncheck.createDelegate(this)
            }
        });

        if (oGroup['fields'] && oGroup['fields'].length) {
            for (j = 0; j < oGroup['fields'].length; j++) {
                var oField = oGroup['fields'][j];

                arrGroupsAndFields.push({
                    xtype: 'label',
                    style: 'margin-left: 15px',
                    html: oField['field_name']
                });

                var booFieldChecked = false;
                var booFieldDisabled = false;
                var booFieldCanBeEnabled = true;
                var msgFieldHelp = '';
                if (booGroupChecked) {
                    booFieldDisabled = true;
                    booFieldChecked = true;
                } else {
                    booFieldDisabled = (oField['field_required'] === 'Y' && !oField['field_skip_access_requirements']) || thisDialog.conditionFieldId === 'field_' + oField['field_id'];
                    if (booFieldDisabled) {
                        msgFieldHelp = String.format(
                            '<i class="las la-info-circle help-icon" ext:qtip="{0}"></i>',
                            _('This field cannot be hidden because it is required (and "Skip Access Requirements" is not checked) OR this is currently edited field.')
                        );
                    } else if (booEditMode) {
                        var arrFieldCheckedForOptions = [];
                        Ext.each(thisDialog.arrSelectedOptions, function (option) {
                            if (option['data']['field_condition_hidden_fields'].has(oField['field_id'])) {
                                arrFieldCheckedForOptions.push(option['data']['field_option_label']);
                            }
                        });

                        if (arrFieldCheckedForOptions.length === thisDialog.arrSelectedOptions.length) {
                            booFieldChecked = true;
                        } else {
                            if (arrFieldCheckedForOptions.length > 0) {
                                arrFieldsToIgnore.push(oField['field_id']);

                                booFieldDisabled = true;
                                booFieldCanBeEnabled = false;
                                msgFieldHelp = String.format(
                                    '<i class="las la-info-circle help-icon help-icon-error" ext:qtip="{0}"></i>',
                                    _('This Field is hidden for:') + '<br />' + arrFieldCheckedForOptions.join('<br />')
                                );
                            }
                        }
                    }
                }

                arrGroupsAndFields.push({
                    xtype: 'checkbox',
                    style: 'margin-left: 15px',
                    boxLabel: _('Hidden') + msgFieldHelp,
                    parentGroupId: oGroup['group_id'],
                    disabled: booFieldDisabled,
                    name: 'condition_fields_hidden[]',
                    inputValue: oField['field_id'],
                    checked: booFieldChecked,
                    booCanBeEnabled: booFieldCanBeEnabled
                });
            }
        }
    }

    arrGroupsAndFields.push({
        xtype: 'hidden',
        name: 'case_template_id',
        value: this.caseTemplateId
    });

    arrGroupsAndFields.push({
        xtype: 'hidden',
        name: 'field_id',
        value: this.conditionFieldId
    });

    arrGroupsAndFields.push({
        xtype: 'hidden',
        name: 'mode',
        value: thisDialog.mode
    });

    arrGroupsAndFields.push({
        xtype: 'hidden',
        name: 'hidden_fields_ignore',
        value: arrFieldsToIgnore.join(';')
    });

    arrGroupsAndFields.push({
        xtype: 'hidden',
        name: 'hidden_groups_ignore',
        value: arrGroupsToIgnore.join(';')
    });

    this.conditionDetailsForm = new Ext.form.FormPanel({
        layout: 'table',
        style: 'background-color: #fff; padding: 5px;',
        cls: 'x-table-layout-cell-top-align x-table-layout-hover',

        layoutConfig: {
            columns: 2,
            tableAttrs: {
                style: {
                    width: '100%'
                }
            }
        },

        items: arrGroupsAndFields
    });

    ConditionalFieldsDialog.superclass.constructor.call(this, {
        id: 'conditional_fields_dialog',
        title: _('Manage Conditional Fields'),
        closeAction: 'close',
        modal: true,
        autoScroll: true,
        resizable: false,
        width: 600,
        height: 500,
        layout: 'form',
        items: this.conditionDetailsForm,

        buttons: [
            {
                text: _('Cancel'),
                scope: this,
                handler: this.closeDialog
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                scope: this,
                handler: this.saveChanges
            }
        ]
    });
};

Ext.extend(ConditionalFieldsDialog, Ext.Window, {
    showDialog: function () {
        this.show();
        this.center();
    },

    closeDialog: function () {
        this.close();
    },

    onGroupCheckUncheck: function (thisCheckbox, booChecked) {
        var arrFields = this.conditionDetailsForm.find('parentGroupId', thisCheckbox.thisGroupId);
        for (var i = 0; i < arrFields.length; i++) {
            arrFields[i].setValue(booChecked);
            if (!booChecked) {
                if (arrFields[i].booCanBeEnabled) {
                    arrFields[i].setDisabled(booChecked);
                }
            } else {
                arrFields[i].setDisabled(booChecked);
            }
        }
    },

    saveChanges: function () {
        var thisDialog = this;

        // Make sure that option is selected
        if (!thisDialog.conditionDetailsForm.getForm().isValid()) {
            return;
        }

        // Make sure that at least one field or group is checked
        var params = thisDialog.conditionDetailsForm.getForm().getValues();
        if (typeof params['condition_groups_hidden[]'] == 'undefined' && typeof params['condition_fields_hidden[]'] == 'undefined' && params['hidden_fields_ignore'] === '' && params['hidden_groups_ignore'] === '') {
            Ext.simpleConfirmation.error(_('Please check at least one field or group.'));
            return;
        }

        thisDialog.conditionDetailsForm.getForm().submit({
            url: baseUrl + '/conditional-fields/save',
            waitMsg: _('Saving...'),
            clientValidation: true,
            timeout: 10 * 60, // 10 minutes (in seconds, not milliseconds)

            success: function (form, action) {
                var res = action.result;

                thisDialog.parentGrid.store.reload();


                thisDialog.getEl().mask(_('Done!'));
                setTimeout(function () {
                    arrReadableConditions = res.conditions;
                    showGroupsAndFieldsReadableConditions();

                    thisDialog.closeDialog();
                }, 750);
            },

            failure: function (form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});