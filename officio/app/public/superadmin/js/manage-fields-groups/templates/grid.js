var CasesTemplatesGrid = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.caseTemplateRecord = Ext.data.Record.create([
        {name: 'case_template_id', type: 'int'},
        {name: 'case_template_can_all_fields_edit', type: 'boolean'},
        {name: 'case_template_name'},
        {name: 'case_template_type'},
        {name: 'case_template_default'},
        {name: 'case_template_default_tooltip'},
        {name: 'case_template_needs_ia'},
        {name: 'case_template_employer_sponsorship'},
        {name: 'case_template_form_version_id'},
        {name: 'case_template_email_template_id'},
        {name: 'case_template_case_reference_as'},
        {name: 'case_template_hidden'},
        {name: 'case_template_hidden_for_company'},
        {name: 'case_template_categories'},
        {name: 'case_template_client_status_list_id'}
    ]);

    this.store = new Ext.data.Store({
        remoteSort: true,
        autoLoad: true,

        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/manage-fields-groups/get-cases-templates'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'case_template_id'
        }, this.caseTemplateRecord)
    });
    this.store.setDefaultSort('case_template_id', 'DESC');

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            header: _('Template Name'),
            dataIndex: 'case_template_name',
            sortable: true,
            width: 400
        }, {
            id: expandCol,
            header: _('Template is for'),
            dataIndex: 'case_template_type',
            sortable: false,
            width: 200,
            renderer: function (val) {
                var strResult = '';
                for (var i = 0; i < val.length; i++) {
                    Ext.each(arrCaseTemplateTypes, function (oTypeInfo) {
                        if (val[i] == oTypeInfo.case_template_type_id) {
                            strResult += empty(strResult) ? '' : ', ';
                            strResult += oTypeInfo.case_template_type_name;
                        }
                    });
                }

                return strResult;
            }
        }, {
            header: _('Layout'),
            dataIndex: 'case_template_default',
            sortable: false,
            width: 100,
            align: 'center',
            renderer: function (val, p, record) {
                var label = val == 'Y' ? _('Default') : _('Custom');

                if (!empty(record['data']['case_template_default_tooltip'])) {
                    label = String.format(
                        '<span ext:qtip="{0}" ext:qwidth="450" style="cursor: help;">{1}</span>',
                        record['data']['case_template_default_tooltip'].replaceAll("'", "\'"),
                        label
                    );
                }

                return label;
            }
        }, {
            header: _('Individual'),
            dataIndex: 'case_template_needs_ia',
            sortable: false,
            width: 120,
            align: 'center',
            renderer: function (val) {
                return val == 'Y' ? _('Yes') : _('No');
            }
        }, {
            header: _('Employer'),
            dataIndex: 'case_template_employer_sponsorship',
            sortable: false,
            width: 120,
            align: 'center',
            renderer: function (val) {
                return val == 'Y' ? _('Yes') : _('No');
            }
        }, {
            header: _('Hidden'),
            dataIndex: 'case_template_hidden',
            sortable: false,
            width: 120,
            align: 'center',
            renderer: function (val) {
                return val == 'Y' ? _('Yes') : _('No');
            }
        }, {
            header: _('Hidden For Company'),
            dataIndex: 'case_template_hidden_for_company',
            hidden: !booIsNotDefaultCompany,
            sortable: false,
            width: 175,
            align: 'center',
            renderer: function (val) {
                return val == 'Y' ? _('Yes') : _('No');
            }
        }
    ];

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New'),
            handler: this.changeCaseTemplateProperties.createDelegate(this, [true]),
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Layout'),
            ref: '../editTemplateButton',
            handler: this.editCaseTemplate.createDelegate(this),
            disabled: true
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete'),
            ref: '../deleteTemplateButton',
            hidden: true, // Temporary hidden
            handler: this.deleteCaseTemplate.createDelegate(this),
            disabled: true
        }, {
            text: '<i class="lab la-stack-exchange"></i>' + _('Change Properties'),
            ref: '../changeTemplatePropertiesButton',
            handler: this.changeCaseTemplateProperties.createDelegate(this, [false]),
            disabled: true
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            handler: this.refreshTemplatesList.createDelegate(this)
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 100500,
        store: this.store
    });

    CasesTemplatesGrid.superclass.constructor.call(this, {
        loadMask: {msg: _('Loading...')},
        sm: sm,
        cls: 'extjs-grid',
        stateful: true,
        height: getSuperadminPanelHeight() - 30,
        stripeRows: true,
        autoExpandColumn: expandCol,
        viewConfig: {
            emptyText: _('There are no records to show.')
        }
    });

    this.getSelectionModel().on('selectionchange', this.onSelectionChange.createDelegate(this));
    this.on('dblclick', this.editCaseTemplate.createDelegate(this));
};

Ext.extend(CasesTemplatesGrid, Ext.grid.GridPanel, {
    onSelectionChange: function () {
        var sel = this.view.grid.getSelectionModel().getSelections();
        var booIsSelectedOneTemplate = sel.length == 1;
        var booIsSelectedAtLeastOneTemplate = sel.length >= 1;

        // Note buttons
        this.editTemplateButton.setDisabled(!booIsSelectedOneTemplate);
        this.changeTemplatePropertiesButton.setDisabled(!booIsSelectedOneTemplate);
        this.deleteTemplateButton.setDisabled(!booIsSelectedAtLeastOneTemplate);
    },

    refreshTemplatesList: function () {
        this.getStore().reload();
    },

    changeCaseTemplateProperties: function (booNewCaseTemplate) {
        var record;
        if (booNewCaseTemplate) {
            record = new this.caseTemplateRecord({
                case_template_id: 0,
                case_template_can_all_fields_edit: true,
                case_template_categories: []
            });
        } else {
            record = this.getSelectionModel().getSelected();
        }

        var window = new ManageCaseTemplateDialog({}, this, record);
        window.showDialog();
    },


    editCaseTemplate: function () {
        var sel = this.getSelectionModel().getSelected();
        this.owner.caseTemplateEdit(sel.data.case_template_id, sel.data.case_template_name);
    },


    deleteCaseTemplate: function () {
        var thisGrid = this;
        var sel = this.getSelectionModel().getSelected();
        var templateName = sel.data.case_template_name;
        Ext.Msg.confirm(_('Please confirm'), 'Are you sure you want to delete <span style="font-style: italic;">' + templateName + '</span>?', function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: baseUrl + '/manage-fields-groups/delete-cases-template',
                    params: {
                        case_template_id: Ext.encode(sel.data.case_template_id)
                    },

                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            var msg = String.format(
                                _('Template <span style="font-style: italic;">{0}</span> was successfully deleted.'),
                                templateName
                            );

                            Ext.simpleConfirmation.success(msg);
                            thisGrid.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.msg);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();

                        var msg = String.format(
                            _('Template <span style="font-style: italic;">{0}</span> cannot be deleted. Please try again later.'),
                            templateName
                        );
                        Ext.simpleConfirmation.error(msg);
                    }
                });
            }
        });

    }
});
