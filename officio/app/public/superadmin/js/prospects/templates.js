var ProspectTemplatesGrid = function(config) {
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-company-prospects/get-templates-list-for-tree',
        autoLoad: true,
        remoteSort: false,
        reader: new Ext.data.JsonReader(
            {
                root: 'rows',
                totalProperty: 'totalCount'
            }, Ext.data.Record.create([
                {name: 'template_id'},
                {name: 'filename'},
                {name: 'default_template', type: 'boolean'},
                {name: 'author'},
                {name: 'create_date'}
            ])
        )
    });

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header:    'Template name',
                dataIndex: 'filename',
                width:     Ext.get('manage_company_prospects_container').getWidth() - 390
            }, {
                header:    'Default',
                dataIndex: 'default_template',
                renderer: this.formatDefaultColumn,
                width:     50
            }, {
                header:    'Author',
                dataIndex: 'author',
                width:     200
            }, {
                header:    'Date',
                dataIndex: 'create_date',
                width:     100,
                align:     'center'
            }
        ],
        defaultSortable: true
    });

    this.tbar = [
        {
            text: 'New Template',
            id: 'prospect-template-toolbar-add',
            iconCls: 'prospect-template-toolbar-add',
            disabled: false,
            handler: this.templateOpenDialog.createDelegate(this, [false])
        },
        {
            text: 'Edit Template',
            id: 'prospect-template-toolbar-edit',
            iconCls: 'prospect-template-toolbar-edit',
            disabled: true,
            handler: this.templateOpenDialog.createDelegate(this, [true])
        },
        {
            text: 'Delete Template',
            id: 'prospect-template-toolbar-delete',
            iconCls: 'prospect-template-toolbar-delete',
            disabled: true,
            handler: this.templateDelete.createDelegate(this)
        },
        {
            text: 'Set as Default',
            id: 'prospect-template-toolbar-mark-default',
            iconCls: 'prospect-template-toolbar-mark-default',
            disabled: true,
            handler: this.templateMarkDefault.createDelegate(this)
        },
        '->',
        {
            icon: topBaseUrl + '/images/refresh.png',
            cls: 'x-btn-icon',
            handler: this.refreshGrid.createDelegate(this)
        }
    ];

    ProspectTemplatesGrid.superclass.constructor.call(this, {
        id: 'prospects-templates-tree',
        renderTo: 'prospects-templates',
        sm: new Ext.grid.CheckboxSelectionModel(),
        height: getSuperadminPanelHeight() - $('#manage_company_prospects_container').outerHeight() - $('#prospects-templates').outerHeight() + $('#prospects-templates').height() - 26,
        split: true,
        autoWidth: true,
        stripeRows: true,
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',

        viewConfig: {
            deferEmptyText: 'No templates found.',
            emptyText: 'No templates found.',
            getRowClass: this.applyRowClass
        }
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons);
    this.on('rowdblclick', this.templateOpenDialog.createDelegate(this, [true]));
};


// Methods
Ext.extend(ProspectTemplatesGrid, Ext.grid.GridPanel, {
    refreshGrid: function() {
        this.getStore().reload();
    },

    updateToolbarButtons: function(sm) {
        var booOneSelected = (sm.getSelections().length == 1);
        Ext.getCmp('prospect-template-toolbar-edit').setDisabled(!booOneSelected);
        Ext.getCmp('prospect-template-toolbar-delete').setDisabled(!booOneSelected);

        var booIsDefault = booOneSelected ? sm.getSelections()[0].data.default_template : false;
        Ext.getCmp('prospect-template-toolbar-mark-default').setDisabled(!booOneSelected || booIsDefault);
    },

    formatDefaultColumn: function(booDefault) {
        return booDefault ? 'Yes' : '';
    },

    // Apply custom class for row in relation to several criteria
    applyRowClass: function(record) {
        return record.data.default_template ? 'prospect-template-row-default ' : '';
    },


    templateOpenDialog: function(booEdit) {
        var grid = this;
        var template_id = 0;

        if (booEdit) {
            var template = grid.getSelectionModel().getSelected();
            template_id = template.data.template_id;
        }


        Ext.getBody().mask('Loading...');
        Ext.Ajax.request({
            url: baseUrl + "/manage-company-prospects/get-template-info",
            params: {
                template_id: template_id
            },
            success: function (f, o) {

                function insertField() {
                    var row = fields.getSelectionModel().getSelected().data;
                    var text = '{' + row.name + '}';
                    text = text.trim();
                    Ext.getCmp('templates-editor')._froalaEditor.html.insert(text);
                }

                //get values
                var result = Ext.decode(f.responseText);

                var name = new Ext.form.TextField({
                    fieldLabel: 'Name',
                    allowBlank: false,
                    value: result.name,
                    anchor: '95%'
                });

                var from = new Ext.form.TextField({
                    fieldLabel: 'From',
                    width: 332,
                    vtype: 'email',
                    value: result.from,
                    anchor: '95%'
                });

                var to = new Ext.form.TextField({
                    fieldLabel: 'To',
                    width: 332,
                    value: result.to,
                    anchor: '95%'
                });

                var cc = new Ext.form.TextField({
                    fieldLabel: 'CC',
                    value: result.cc,
                    anchor: '95%'
                });

                var bcc = new Ext.form.TextField({
                    fieldLabel: 'BCC',
                    value: result.bcc,
                    anchor: '95%'
                });

                var subject = new Ext.form.TextField({
                    fieldLabel: 'Subject',
                    value: result.subject,
                    anchor: '100%'
                });

                var store = new Ext.data.GroupingStore({
                    url: baseUrl + '/manage-company-prospects/get-fields',
                    reader: new Ext.data.JsonReader({
                            root: 'rows',
                            totalProperty: 'totalCount'
                        },
                        Ext.data.Record.create([
                            {name: 'n'},
                            {name: 'group'},
                            {name: 'name'},
                            {name: 'label'}
                        ])),
                    autoLoad: true,
                    sortInfo: false,
                    groupField: 'n'
                });

                // create the grid
                var fields = new Ext.grid.GridPanel({
                    collapsible: true,
                    title: 'Fields',
                    split: true,
                    width: 450,
                    store: store,
                    cm: new Ext.grid.ColumnModel([
                        {
                            dataIndex: 'n',
                            hidden: true
                        },
                        {
                            header: "Group",
                            dataIndex: 'group',
                            hidden: true
                        },
                        {
                            id: 'name',
                            header: "Field",
                            dataIndex: 'name',
                            width: 200,
                            renderer: function (val) {
                                return '{' + val + '}';
                            }
                        },
                        {
                            header: "Label",
                            dataIndex: 'label',
                            width: 200
                        }
                    ]),

                    view: new Ext.grid.GroupingView({
                        deferEmptyText: 'No fields found.',
                        emptyText: 'No fields found.',
                        forceFit: true,
                        groupTextTpl: '{[values.rs[0].data["group"]]} ({[values.rs.length]} {[values.rs.length > 1 ? "Fields" : "Field"]})'
                    }),

                    sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
                    height: 300,
                    stripeRows: true,
                    cls: 'extjs-grid',
                    loadMask: true,
                    bbar: [new Ext.Toolbar.Button({
                        text: '&nbsp;&nbsp;&nbsp;&nbsp;<<&nbsp;&nbsp;&nbsp;&nbsp;',
                        pressed: true,
                        style: 'padding-bottom:4px;',
                        handler: function () {
                            insertField(id);
                        }
                    }), 'Double click on a field name to insert']
                });

                fields.on('rowdblclick', function () {
                    insertField();
                });

                var editor = new Ext.ux.form.FroalaEditor({
                    fieldLabel: '',
                    hideLabel: true,
                    id: 'templates-editor',
                    height: 162,
                    resizeEnabled: false,
                    value: result.message,
                    booAllowImagesUploading: true
                });

                var fields_fs = new Ext.form.FieldSet({
                    layout: 'column',
                    id: 'template-body-fieldset',
                    title: 'Body of template',
                    autoHeight: true,
                    cls: 'templates-fieldset',
                    items: [
                        {
                            id: 'template-body-editor',
                            layout: 'form',
                            items: editor,
                            width: 660
                        },{
                            id: 'template-body-fields',
                            width: 450,
                            layout: 'form',
                            items: fields
                        }
                    ]
                });

                var pan = new Ext.FormPanel({
                    itemCls: 'templates-sub-tab-items',
                    cls: 'templates-sub-tab',
                    bodyStyle: 'padding:0px',
                    labelWidth: 45,
                    items: [
                        {
                            layout: 'column',
                            items: [
                                {
                                    columnWidth: 0.5,
                                    layout: 'form',
                                    items: [from, cc, name]
                                },
                                {
                                    columnWidth: 0.5,
                                    layout: 'form',
                                    items: [to, bcc]
                                }
                            ]
                        },
                        subject,
                        fields_fs
                    ]
                });

                var saveBtn = new Ext.Button({
                    text: 'Save Template',
                    handler: function () {

                        var name_value = name.getValue();
                        var body = editor.getValue();
                        if (body === '') {
                            Ext.simpleConfirmation.msg('Info', 'Template body cannot be empty');
                            return false;
                        }

                        if (pan.getForm().isValid()) {
                            win.getEl().mask('Loading...');
                            Ext.Ajax.request({
                                url: baseUrl + "/manage-company-prospects/save-template",
                                params: {
                                    template_id: template_id,
                                    name: Ext.encode(name_value),
                                    subject: Ext.encode(subject.getValue()),
                                    from: Ext.encode(from.getValue()),
                                    to: Ext.encode(to.getValue()),
                                    cc: Ext.encode(cc.getValue()),
                                    bcc: Ext.encode(bcc.getValue()),
                                    body: Ext.encode(body)
                                },
                                success: function (f) {
                                    var result = Ext.decode(f.responseText);
                                    if (result.success) {
                                        grid.store.reload();

                                        win.getEl().mask('Done');
                                        setTimeout(function () {
                                            win.close();
                                        }, 750);
                                    } else {
                                        Ext.simpleConfirmation.error(result.message);
                                        win.getEl().unmask();
                                    }
                                },
                                failure: function () {
                                    Ext.simpleConfirmation.error('An error occurred when saving template');
                                    win.getEl().unmask();
                                }
                            });
                        }
                    }
                });

                var closeBtn = new Ext.Button({
                    text: 'Cancel',
                    handler: function () {
                        win.close();
                    }
                });

                var win = new Ext.Window({
                    initHidden: false,
                    title: 'Manage Template',
                    modal: true,
                    autoHeight: true,
                    resizable: false,
                    width: 1150,
                    layout: 'form',
                    items: pan,
                    buttons: [saveBtn, closeBtn]
                });

                win.show();
                win.center();

                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.Msg.alert('Status', 'Can\'t load Template Content');
                Ext.getBody().unmask();
            }
        });
    },

    templateDelete: function() {
        var grid = this;
        var template = grid.getSelectionModel().getSelected();

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete <i>' + template.data.filename + '</i>?', function(btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/template-delete',
                    params: {
                        template_id: template.data.template_id
                    },
                    success: function(result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.simpleConfirmation.info('Template <i>' + template.data.filename + '</i> was successfully deleted.');
                            grid.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function()  {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Template <i>' + template.data.filename + '</i> cannot be deleted. Please try again later.');
                    }
                });
            }
        });
    },

    templateMarkDefault: function() {
        var grid = this;
        var template = grid.getSelectionModel().getSelected();

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to mark template <i>' + template.data.filename + '</i> as default?', function(btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Processing...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/template-mark-as-default',
                    params: {
                        template_id: template.data.template_id
                    },
                    success: function(result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.simpleConfirmation.info('Template <i>' + template.data.filename + '</i> was successfully marked as default.');
                            grid.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function()  {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Template <i>' + template.data.filename + '</i> cannot be marked as default. Please try again later.');
                    }
                });
            }
        });
    }
});