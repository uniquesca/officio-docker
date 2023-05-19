var ManageCaseTemplateDialog = function (config, parentGrid, oCaseTemplateRecord) {
    var thisDialog = this;
    Ext.apply(thisDialog, config);

    thisDialog.parentGrid = parentGrid;
    thisDialog.oCaseTemplateRecord = oCaseTemplateRecord;

    this.caseTypeCategories = new Ext.form.Hidden({
        name: 'case_template_categories'
    });

    this.caseTypeCategoriesGrid = new CaseCategoriesGrid({
        booReadOnly: !this.oCaseTemplateRecord.data['case_template_can_all_fields_edit'],
        height: 310
    }, this);

    var templateTypes = thisDialog.oCaseTemplateRecord.data['case_template_type'] || [];
    var arrCheckboxes = [];
    Ext.each(arrCaseTemplateTypes, function (oCaseType) {
        arrCheckboxes.push({
            name: 'case_template_type[]',
            boxLabel: oCaseType.case_template_type_name,
            checked: templateTypes.has(oCaseType.case_template_type_id),
            inputValue: oCaseType.case_template_type_id,
            isIndividual: oCaseType.case_template_type_name == 'Individual Clients',
            listeners: {
                'check': function () {
                    thisDialog.toggleIACheckbox();
                }
            }
        });
    });

    this.visibilityCheckboxesGroup = new Ext.form.CheckboxGroup({
        fieldLabel: _('Visible when creating'),
        cls: 'case_template_checkbox_group',
        itemCls: 'no-margin-bottom',
        msgTarget: 'qtip',
        allowBlank: false,
        width: 300,
        columns: 2,
        items: arrCheckboxes
    });

    this.caseTemplateNeedsIACheckbox = new Ext.form.Checkbox({
        style: 'padding-top: 15px',
        name: 'case_template_needs_ia',
        hiddenName: 'case_template_needs_ia',
        hidden: true,
        checked: thisDialog.oCaseTemplateRecord.data['case_template_needs_ia'] == 'Y',
        hideLabel: true,
        boxLabel: String.format(_('This {0} needs an Individual Client Contact'), case_type_field_label_singular)
    });

    this.caseTemplateEmployerSponsorshipCheckbox = new Ext.form.Checkbox({
        style: 'padding-top: 15px',
        name: 'case_template_employer_sponsorship',
        hiddenName: 'case_template_employer_sponsorship',
        checked: thisDialog.oCaseTemplateRecord.data['case_template_employer_sponsorship'] == 'Y',
        hideLabel: true,
        boxLabel: _('Employer Sponsorship') + ' ' + case_type_field_label_singular
    });

    this.templateNameField = new Ext.form.TextField({
        fieldLabel: case_type_field_label_singular + ' ' + _('Name'),
        name: 'case_template_name',
        allowBlank: false,
        width: 420
    });

    this.caseTemplateIsHiddenCheckbox = new Ext.form.Checkbox({
        boxLabel: _('Hidden'),
        hideLabel: true,
        height: 30,
        name: 'case_template_hidden',
        checked: thisDialog.oCaseTemplateRecord.data['case_template_hidden'] == 'Y'
    });

    if (!empty(thisDialog.oCaseTemplateRecord.data['case_template_id'])) {
        thisDialog.templateNameField.setValue(thisDialog.oCaseTemplateRecord.data['case_template_name']);
    }

    var caseStatusListId;
    if (empty(thisDialog.oCaseTemplateRecord.data['case_template_id']) && arrCaseStatusLists.length === 1) {
        caseStatusListId = arrCaseStatusLists[0]['client_status_list_id'];
    } else {
        caseStatusListId = thisDialog.oCaseTemplateRecord.data['case_template_client_status_list_id'];
    }

    var formVersionStore = new Ext.data.ArrayStore({
        fields: ['form_version_id', 'form_version_name'],
        data: arrCaseFormVersions
    });

    this.templateFormVersionCombo = new Ext.ux.form.LovCombo({
        name: 'case_form_version_id',
        hiddenName: 'case_form_version_id',
        fieldLabel: _('Form version <i>(assign on case creation)</i>'),
        width: 490,
        store: formVersionStore,
        displayField: 'form_version_name',
        valueField: 'form_version_id',
        separator: ';',
        typeAhead: false,
        mode: 'local',
        triggerAction: 'all',
        editable: true,
        value: thisDialog.oCaseTemplateRecord.data['case_template_form_version_id'],
        emptyText: _('Select a form...')
    });

    var emailTemplatesStore = new Ext.data.Store({
        data: arrCaseEmailTemplates,
        reader: new Ext.data.JsonReader({
            id: 'templateId'
        }, [{name: 'templateId'}, {name: 'templateName'}])
    });

    var caseStatusListsStore = new Ext.data.Store({
        data: arrCaseStatusLists,
        reader: new Ext.data.JsonReader({
            id: 'client_status_list_id'
        }, [
            {name: 'client_status_list_id'},
            {name: 'client_status_list_name'}
        ])
    });

    this.templateDefaultCaseStatusListCombo = new Ext.form.ComboBox({
        name: 'case_template_client_status_list_id',
        hiddenName: 'case_template_client_status_list_id',
        fieldLabel: String.format(_('{0} Workflow when no {1} is selected'), statuses_field_label_singular, categories_field_label_singular),
        width: 420,
        store: caseStatusListsStore,
        displayField: 'client_status_list_name',
        valueField: 'client_status_list_id',
        mode: 'local',
        typeAhead: false,
        allowBlank: false,
        editable: true,
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        value: caseStatusListId,
        emptyText: _('Select a workflow...')
    });

    this.templateCaseReferenceAsField = new Ext.form.TextField({
        fieldLabel: _('Case Referenced as'),
        name: 'case_template_case_reference_as',
        value: thisDialog.oCaseTemplateRecord.data['case_template_case_reference_as'],
        allowBlank: false,
        width: 420
    });

    this.caseTemplateForm = new Ext.form.FormPanel({
        baseCls: 'x-plain',
        defaultType: 'textfield',
        labelWidth: 180,
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name: 'case_template_id',
                value: thisDialog.oCaseTemplateRecord.data['case_template_id']
            },

            {
                xtype: 'box',
                hidden: this.oCaseTemplateRecord.data['case_template_can_all_fields_edit'],
                autoEl: {
                    tag: 'div',
                    'style': 'color: red; margin: 10px 0 20px 0; text-align: center',
                    html: _('This is a default case type and editing is disabled')
                }
            },
            thisDialog.caseTypeCategories,
            {
                xtype: 'container',
                layout: 'column',
                width: 1000,
                items: [
                    {
                        xtype: 'container',
                        layout: 'form',
                        width: 500,
                        items: [
                            this.templateNameField,
                            {
                                xtype: 'combo',
                                name: 'case_template_copy_from',
                                hiddenName: 'case_template_copy_from',
                                fieldLabel: _('Create a copy from'),
                                width: 420,
                                store: thisDialog.parentGrid.getStore(),
                                displayField: 'case_template_name',
                                valueField: 'case_template_id',
                                mode: 'local',
                                typeAhead: false,
                                editable: true,
                                forceSelection: true,
                                triggerAction: 'all',
                                selectOnFocus: true,
                                hidden: !empty(thisDialog.oCaseTemplateRecord.data['case_template_id']),
                                emptyText: _('Select a template...'),

                                listeners: {
                                    beforeselect: this.copyDefaultsFromCaseType.createDelegate(this),
                                }
                            },
                            thisDialog.visibilityCheckboxesGroup,
                            thisDialog.caseTemplateNeedsIACheckbox,
                            thisDialog.templateCaseReferenceAsField,
                            thisDialog.caseTemplateEmployerSponsorshipCheckbox,
                            thisDialog.templateDefaultCaseStatusListCombo
                        ]
                    }, {
                        xtype: 'container',
                        items: [
                            {
                                xtype: 'fieldset',
                                title: _('Optional Settings'),
                                cls: 'fieldset-with-normal-legend',
                                width: 500,
                                items: [
                                    thisDialog.templateFormVersionCombo, {
                                        xtype: 'combo',
                                        ref: '../emailTemplateCombo',
                                        name: 'case_email_template_id',
                                        hiddenName: 'case_email_template_id',
                                        fieldLabel: _('Send Email <i>(on case creation)</i>'),
                                        width: 490,
                                        store: emailTemplatesStore,
                                        displayField: 'templateName',
                                        valueField: 'templateId',
                                        mode: 'local',
                                        typeAhead: false,
                                        editable: true,
                                        forceSelection: true,
                                        triggerAction: 'all',
                                        selectOnFocus: true,
                                        value: thisDialog.oCaseTemplateRecord.data['case_template_email_template_id'],
                                        emptyText: _('Select a template...')
                                    }
                                ]
                            },

                            {
                                xtype: 'container',
                                layout: 'column',
                                height: 30,
                                items: [
                                    this.caseTemplateIsHiddenCheckbox, {
                                        width: 50,
                                        html: '&nbsp;'
                                    }, {
                                        name: 'case_template_hidden_for_company',
                                        xtype: 'checkbox',
                                        boxLabel: _('Hide for this company'),
                                        hideLabel: true,
                                        height: 30,
                                        hidden: !booIsNotDefaultCompany,
                                        checked: thisDialog.oCaseTemplateRecord.data['case_template_hidden_for_company'] == 'Y'
                                    }
                                ]
                            }

                        ]
                    }

                ]
            },

            {
                xtype: 'container',
                layout: 'column',
                width: 1000,
                items: this.caseTypeCategoriesGrid
            }
        ]
    });

    var title = '';
    if (empty(thisDialog.oCaseTemplateRecord.data['case_template_id'])) {
        title = '<i class="las la-plus"></i>' + _('New') + ' ' + case_type_field_label_singular;
    } else {
        title = '<i class="lab la-stack-exchange"></i>' + String.format(_('Edit {0} properties'), case_type_field_label_singular);
    }

    ManageCaseTemplateDialog.superclass.constructor.call(this, {
        title: title,
        plain: true,
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        border: false,
        items: thisDialog.caseTemplateForm,

        buttons: [
            {
                text: _('Cancel'),
                scope: this,
                handler: this.closeDialog
            },
            {
                text: empty(thisDialog.oCaseTemplateRecord.data['case_template_id']) ? _('Create') : _('Save'),
                cls: 'orange-btn',
                scope: this,
                handler: this.saveChanges
            }
        ]
    });

    this.on('show', this.thisDialogOnShow.createDelegate(this));
};

Ext.extend(ManageCaseTemplateDialog, Ext.Window, {
    showDialog: function () {
        this.show();
        this.center();
        this.setPosition(null, 20);
    },

    closeDialog: function () {
        this.close();
    },

    thisDialogOnShow: function () {
        this.toggleIACheckbox();

        if (!empty(this.oCaseTemplateRecord.data['case_template_id']) && !this.oCaseTemplateRecord.data['case_template_can_all_fields_edit']) {
            // Disable fields that we cannot change
            this.templateNameField.setDisabled(true);
            this.visibilityCheckboxesGroup.setDisabled(true);
            this.caseTemplateEmployerSponsorshipCheckbox.setDisabled(true);
            this.templateDefaultCaseStatusListCombo.setDisabled(true);
            this.templateCaseReferenceAsField.setDisabled(true);

            this.templateFormVersionCombo.setDisabled(true);
            this.caseTemplateIsHiddenCheckbox.setDisabled(true);
        }

        this.caseTypeCategoriesGrid.loadCaseTypeCategories(this.oCaseTemplateRecord.data['case_template_categories']);
    },

    toggleIACheckbox: function () {
        var thisDialog = this;
        var booIsEmployerChecked = false;
        var booIsIndividualChecked = false;
        thisDialog.visibilityCheckboxesGroup.items.each(function (item) {
            if (item.checked) {
                if (item.isIndividual) {
                    booIsIndividualChecked = true;
                } else {
                    booIsEmployerChecked = true;
                }
            }
        });

        if (booIsIndividualChecked) {
            thisDialog.caseTemplateNeedsIACheckbox.setValue(true);
            thisDialog.caseTemplateNeedsIACheckbox.setDisabled(true);
        } else {
            thisDialog.caseTemplateNeedsIACheckbox.setValue(false);
            thisDialog.caseTemplateNeedsIACheckbox.setDisabled(false);
        }

        if (booIsEmployerChecked && !booIsIndividualChecked) {
            thisDialog.caseTemplateEmployerSponsorshipCheckbox.setDisabled(false);
            thisDialog.templateCaseReferenceAsField.setVisible(true);
            thisDialog.templateCaseReferenceAsField.setDisabled(false);
        } else {
            thisDialog.caseTemplateEmployerSponsorshipCheckbox.setValue(false);
            thisDialog.caseTemplateEmployerSponsorshipCheckbox.setDisabled(true);
            thisDialog.templateCaseReferenceAsField.setVisible(false);
            thisDialog.templateCaseReferenceAsField.setDisabled(true);
        }
    },

    saveChanges: function () {
        var thisDialog = this;

        // Make sure that option is selected
        if (!thisDialog.caseTemplateForm.getForm().isValid()) {
            return;
        }

        thisDialog.getEl().mask(empty(thisDialog.oCaseTemplateRecord.data['case_template_id']) ? _('Saving...') : _('Processing...'));

        var checkbox = thisDialog.caseTemplateNeedsIACheckbox;
        var booIsDisabled = checkbox.disabled;
        if (booIsDisabled) {
            checkbox.setDisabled(false);
        }

        var arrCategories = [];
        var categoriesStore = thisDialog.caseTypeCategoriesGrid.getStore();
        for (var i = 0; i < categoriesStore.getCount(); i++) {
            var rec = categoriesStore.getAt(i);
            arrCategories.push(rec.data);
        }
        thisDialog.caseTypeCategories.setValue(Ext.encode(arrCategories));

        var url = empty(thisDialog.oCaseTemplateRecord.data['case_template_id']) ? baseUrl + '/manage-fields-groups/add-cases-template' : baseUrl + '/manage-fields-groups/update-cases-template';
        thisDialog.caseTemplateForm.getForm().submit({
            url: url,
            timeout: 10 * 60, // 10 minutes (in seconds, not milliseconds)

            success: function (form, action) {
                thisDialog.parentGrid.refreshTemplatesList();

                if (empty(thisDialog.oCaseTemplateRecord.data['case_template_id'])) {
                    thisDialog.parentGrid.owner.caseTemplateEdit(action.result.case_template_id, thisDialog.templateNameField.getValue());
                }

                if (typeof window.top.refreshSettings == 'function') {
                    window.top.refreshSettings('categories');
                }

                thisDialog.close();
            },

            failure: function (form, action) {
                thisDialog.getEl().unmask();
                var defaultMsg = empty(thisDialog.oCaseTemplateRecord.data['case_template_id']) ? _('Template cannot be created. Please try again later.') : _('Template cannot be updated. Please try again later.');
                var msg = action.result && !empty(action.result.msg) ? action.result.msg : defaultMsg;
                Ext.simpleConfirmation.error(msg);

                if (booIsDisabled) {
                    checkbox.setDisabled(true);
                }
            }
        });
    },

    copyDefaultsFromCaseType: function (combo, comboRec) {
        var templateTypes = comboRec.data['case_template_type'] || [];
        this.visibilityCheckboxesGroup.items.each(function (oCheckbox) {
            oCheckbox.setValue(templateTypes.has(oCheckbox.inputValue));
        });

        this.caseTemplateNeedsIACheckbox.setValue(comboRec.data['case_template_needs_ia'] == 'Y');
        this.caseTemplateEmployerSponsorshipCheckbox.setValue(comboRec.data['case_template_employer_sponsorship'] == 'Y');
        this.templateDefaultCaseStatusListCombo.setValue(comboRec.data['case_template_client_status_list_id']);

        // Reset ids
        var arrCategories = [...comboRec.data['case_template_categories']];
        Ext.each(arrCategories, function (oCategory) {
            oCategory['client_category_id'] = null;
            oCategory['client_category_parent_id'] = 0;
        });
        this.caseTypeCategoriesGrid.loadCaseTypeCategories(arrCategories);
    }
});
