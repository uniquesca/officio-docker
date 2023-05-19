var ApplicantTypesGrid = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        remoteSort: true,
        autoLoad: true,

        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/manage-applicant-fields-groups/get-applicant-types'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'applicant_type_id',
            fields: [
                'applicant_type_id',
                'applicant_type_name',
                'is_system'
            ]
        })
    });
    this.store.setDefaultSort('applicant_type_id', 'DESC');

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            id: expandCol,
            header: 'Template Name',
            dataIndex: 'applicant_type_name',
            sortable: true,
            renderer: function (val, a, row) {
                var d = row.data;

                return String.format(
                    '<span style="font-weight: {0}">{1}</span>',
                    d.is_system == 'Y' ? 'bold' : 'normal', val
                );
            }
        }
    ];

    this.tbar = [
        {
            text:    '<i class="las la-plus"></i>' + _('Add'),
            handler: this.addApplicantType.createDelegate(this, [])
        }, {
            text:     '<i class="las la-edit"></i>' + _('Edit'),
            ref:      '../editTemplateButton',
            handler: this.editApplicantType.createDelegate(this),
            disabled: true
        }, {
            text:     '<i class="las la-trash"></i>' + _('Delete'),
            ref:      '../deleteTemplateButton',
            handler: this.deleteApplicantType.createDelegate(this),
            disabled: true
        }, {
            text:     '<i class="lab la-stack-exchange"></i>' + _('Change Properties'),
            ref:      '../changeTemplatePropertiesButton',
            handler: this.changeApplicantTypeProperties.createDelegate(this),
            disabled: true
        }, '->', {
            text: '<i class="las la-redo-alt"></i>',
            handler: this.refreshTemplatesList.createDelegate(this)
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 100500,
        store: this.store
    });

    ApplicantTypesGrid.superclass.constructor.call(this, {
        loadMask:         {msg: 'Loading...'},
        sm:               sm,
        stateful:         true,
        height:           getSuperadminPanelHeight() - 30,
        stripeRows:       true,
        autoExpandColumn: expandCol,
        viewConfig: {
            emptyText: 'There are no records to show.'
        }
    });

    this.getSelectionModel().on('selectionchange', this.onSelectionChange.createDelegate(this));
    this.on('dblclick', this.editApplicantType.createDelegate(this));
};

Ext.extend(ApplicantTypesGrid, Ext.grid.GridPanel, {
    onSelectionChange: function() {
        var sel = this.view.grid.getSelectionModel().getSelections();
        var booIsSelectedOneTemplate = sel.length == 1;
        var booIsSelectedAtLeastOneTemplate = sel.length >= 1;

        var booIsSystemChecked = false;
        for (var i = 0; i < sel.length; i++) {
            if (sel[i]['data']['is_system'] == 'Y') {
                booIsSystemChecked = true;
            }
        }

        // Note buttons
        this.editTemplateButton.setDisabled(!booIsSelectedOneTemplate);
        this.changeTemplatePropertiesButton.setDisabled(!booIsSelectedOneTemplate || booIsSystemChecked);
        this.deleteTemplateButton.setDisabled(!booIsSelectedAtLeastOneTemplate || booIsSystemChecked);
    },

    refreshTemplatesList: function() {
        this.getStore().reload();
    },

    addApplicantType: function(templateId, templateName) {
        var thisGrid = this;

        var newTemplateName = new Ext.form.TextField({
            fieldLabel: 'Template Name',
            name: 'applicant_type_name',
            msgTarget:  'side',
            allowBlank: false,
            width: 290
        });

        if (!empty(templateId)) {
            newTemplateName.setValue(templateName);
        }

        var newTemplateForm = new Ext.form.FormPanel({
            baseCls: 'x-plain',
            defaultType: 'textfield',
            labelWidth: 120,
            items: [
                {
                    xtype: 'hidden',
                    name: 'applicant_type_id',
                    value: templateId
                },
                newTemplateName, {
                    xtype: 'combo',
                    name: 'applicant_type_copy_from',
                    hiddenName: 'applicant_type_copy_from',
                    fieldLabel: 'Create a copy from',
                    width: 290,
                    store: thisGrid.getStore(),
                    displayField: 'applicant_type_name',
                    valueField: 'applicant_type_id',
                    mode: 'local',
                    typeAhead: false,
                    editable: true,
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    hidden: !empty(templateId),
                    emptyText:'Select a template...'
                }
            ]
        });

        var newTemplateWindow = new Ext.Window({
            title: empty(templateId) ? '<i class="las la-plus"></i>' + _('New template') : '<i class="lab la-stack-exchange"></i>' + _('Change template properties'),
            width: 460, // IE fix :(
            autoHeight: true,
            layout: 'fit',
            plain:true,
            modal: true,
            bodyStyle:'padding:5px;',
            buttonAlign:'center',
            items: newTemplateForm,

            buttons: [{
                text: 'Cancel',
                handler: function() {
                    newTemplateWindow.close();
                }
            },
                {
                text: empty(templateId) ? 'Create' : 'Save',
                cls:  'orange-btn',
                handler: function() {
                    if(newTemplateForm.getForm().isValid()) {
                        newTemplateWindow.getEl().mask(empty(templateId) ? 'Saving...' : 'Processing...');

                        var url = empty(templateId) ? baseUrl + '/manage-applicant-fields-groups/add-applicant-type' : baseUrl + '/manage-applicant-fields-groups/update-applicant-type';
                        newTemplateForm.getForm().submit({
                            url: url,

                            success: function(form, action) {
                                thisGrid.refreshTemplatesList();

                                if (empty(templateId)) {
                                    thisGrid.owner.applicantTypeEdit(action.result.applicant_type_id, newTemplateName.getValue());
                                }
                                newTemplateWindow.close();
                            },

                            failure: function(form, action) {
                                newTemplateWindow.getEl().unmask();
                                var defaultMsg = empty(templateId) ? 'Template cannot be created. Please try again later.' : 'Template cannot be updated. Please try again later.';
                                var msg = action.result && !empty(action.result.msg) ? action.result.msg : defaultMsg;
                                Ext.simpleConfirmation.error(msg);
                            }
                        });
                    }
                }
            }]
        });

        newTemplateWindow.show();
        newTemplateWindow.center();
    },

    changeApplicantTypeProperties: function() {
        var sel = this.getSelectionModel().getSelected();
        this.addApplicantType(sel.data.applicant_type_id, sel.data.applicant_type_name);
    },


    editApplicantType: function() {
        var sel = this.getSelectionModel().getSelected();
        this.owner.applicantTypeEdit(sel.data.applicant_type_id, sel.data.applicant_type_name);
    },


    deleteApplicantType: function() {
        var thisGrid = this;
        var sel = this.getSelectionModel().getSelected();
        var templateName = sel.data.applicant_type_name;
        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete <span style="font-style: italic;">' + templateName + '</span>?', function(btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-applicant-fields-groups/delete-applicant-type',
                    params: {
                        applicant_type_id: Ext.encode(sel.data.applicant_type_id)
                    },
                    success: function(result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.simpleConfirmation.success('Template <span style="font-style: italic;">' + templateName + '</span> was successfully deleted.');
                            thisGrid.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.msg);
                        }
                    },
                    failure: function()  {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Template <span style="font-style: italic;">' + templateName + '</span> cannot be deleted. Please try again later.');
                    }
                });
            }
        });

    }
});