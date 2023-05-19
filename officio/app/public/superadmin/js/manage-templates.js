function template(grid, template_id)
{
    var template_type = Ext.getCmp('system-templates-type').getValue();

    Ext.getBody().mask('Loading...');
    Ext.Ajax.request({
        url: baseUrl + "/manage-templates/get-template-info",
        params: {
            template_id: template_id,
            template_type: template_type
        },
        
        success: function(f)
        {
            function insertField() {
                var sel = fields.getSelectionModel().getSelected();
                if(sel) {
                    var row = sel.data;
                    editor.activated = true;
                    editor.focus.defer(2, editor);

                    editor.insertAtCursor('{' + row.name + '}');
                }
            }

            //get values
            var result = Ext.decode(f.responseText);

            var system_type = new Ext.form.DisplayField({
                fieldLabel: 'Type',
                value: result.system_label,
                style: 'padding-top:3px'
            });

            var name;
            if (result.system == 'Y') {
                name = new Ext.form.DisplayField({
                    fieldLabel: 'Name',
                    value: result.title,
                    style: 'padding-top:3px; margin: 2px 0;'
                });
            } else {
                name = new Ext.form.TextField({
                    fieldLabel: 'Name',
                    allowBlank: false,
                    disabled: template_type == 'system',
                    value: result.title,
                    style: 'margin: 2px 0;',
                    anchor: '95%'
                });
            }

            var from = new Ext.form.TextField({
                fieldLabel: 'From',
                width: 332,
                value: result.from,
                style: 'margin: 2px 0;',
                anchor: '95%'
            });

            var to = new Ext.form.TextField({
                fieldLabel: 'To',
                width: 332,
                value: result.to,
                style: 'margin: 2px 0;',
                anchor: '100%'
            });

            var cc = new Ext.form.TextField({
                fieldLabel: 'CC',
                value: result.cc,
                style: 'margin: 2px 0;',
                anchor: '95%'
            });

            var bcc = new Ext.form.TextField({
                fieldLabel: 'BCC',
                value: result.bcc,
                style: 'margin: 2px 0;',
                anchor: '100%'
            });

            var subject = new Ext.form.TextField({
                fieldLabel: 'Subject',
                value: result.subject,
                style: 'margin: 2px 0;',
                anchor: '100%'
            });

            var store = new Ext.data.GroupingStore({
                url: baseUrl + '/manage-templates/get-fields',
                reader: new Ext.data.JsonReader({
                        root: 'rows',
                        totalProperty: 'totalCount'
                    },
                    Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
                autoLoad: true,
                sortInfo: false,
                groupField: 'n',

                listeners: {
                    'beforeload': function (store, options) {
                        options.params = options.params || {};

                        // Load fields list only of specific type (for mass email load only company fields)
                        var params = {
                            template_type: template_type
                        };
                        Ext.apply(options.params, params);
                    },

                    'load': function () {
                        fields_fs.syncSize();
                    }
                }
            });

            // create the grid
            var fields = new Ext.grid.GridPanel({
                collapsible: true,
                title: 'Fields',
                region: 'east',
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
                    }]),

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
                bbar: [
                    {
                        xtype: 'button',
                        text: '&nbsp;&nbsp;&nbsp;&nbsp;<<&nbsp;&nbsp;&nbsp;&nbsp;',
                        pressed: true,
                        style: 'padding-bottom:4px;',
                        handler: function () {
                            insertField();
                        }
                    },
                    'Double click on a field name to insert'
                ]
            });

            fields.on('rowdblclick', function () {
                insertField();
            });

            var editor = new Ext.ux.form.FroalaEditor({
                region: 'center',
                hideLabel: true,
                booAllowImagesUploading: true,
                height: 450,
                width: 550,
                heightDifference: 175,
                initialWidthDifference: 40,
                widthDifference: 10,
                allowBlank: false,
                value: result.template
            });

            var fields_fs = new Ext.form.FieldSet({
                layout: 'border',
                title: 'Body of template',
                autoHeight: true,
                cls: 'templates-fieldset',
                items: [editor, fields]
            });

            var pan = new Ext.FormPanel({
                itemCls: 'templates-sub-tab-items',
                cls: 'templates-sub-tab',
                labelWidth: 45,
                items: [{
                    layout: 'column',
                    items:
                        [{
                            columnWidth: 0.5,
                            layout: 'form',
                            items: [from, cc, name]
                        },
                            {
                                columnWidth: 0.5,
                                layout: 'form',
                                items: [to, bcc, system_type]
                            }]
                }, subject, fields_fs]
            });

            var saveBtn = new Ext.Button({
                text: 'Save Template',
                cls: 'orange-btn',
                handler: function () {
                    var name_value = name.getValue();
                    var body = editor.getValue();
                    if (body === '') {
                        Ext.simpleConfirmation.warning('Template body cannot be empty');
                        return false;
                    }

                    if (name_value === '') {
                        Ext.simpleConfirmation.warning('Name cannot be empty');
                        return false;
                    }

                    win.getEl().mask('Loading...');
                    Ext.Ajax.request({
                        url: baseUrl + "/manage-templates/save",
                        params: {
                            template_id: template_id,
                            template_type: template_type,
                            name: Ext.encode(name_value),
                            subject: Ext.encode(subject.getValue()),
                            from: Ext.encode(from.getValue()),
                            to: Ext.encode(to.getValue()),
                            cc: Ext.encode(cc.getValue()),
                            bcc: Ext.encode(bcc.getValue()),
                            body: Ext.encode(body)
                        },

                        success: function (result) {
                            var resultData = Ext.decode(result.responseText);
                            if (resultData.success) {
                                grid.store.reload();

                                win.getEl().mask(_('Done'));
                                setTimeout(function () {
                                    win.close();
                                }, 750);
                            } else {
                                Ext.simpleConfirmation.error(resultData.message);
                                win.getEl().unmask();
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error('An error occurred during template saving');
                            win.getEl().unmask();
                        }
                    });
                }
            });

            var closeBtn = new Ext.Button({
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            });

            var win = new Ext.Window({
                title: _('Manage Template'),
                modal: true,
                autoHeight: true,
                resizable: false,
                width: 1000,
                layout: 'form',
                items: pan,
                buttons: [closeBtn, saveBtn]
            });

            win.show();
            win.center();

            Ext.getBody().unmask();
        },

        failure: function () {
            Ext.simpleConfirmation.error('Cannot load template content');
            Ext.getBody().unmask();
        }
    });
}

function deleteTemplates(grid, template_id) {
    var node = grid.getSelectionModel().getSelected();
    var question = String.format(
        'Are you sure you want to delete <i>{0}</i> template?',
        node.data.title
    );

    Ext.Msg.confirm('Please confirm', question, function (btn) {
        if (btn == 'yes') {
            Ext.Ajax.request({
                url: baseUrl + '/manage-templates/delete',
                params: {
                    template_id: template_id
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        grid.store.reload();
                    } else {
                        Ext.simpleConfirmation.error(resultData.message);
                        win.getEl().unmask();
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error('Selected template cannot be deleted. Please try again later.');
                }
            });
        }
    });
}

function editTemplate(grid) {
    if(grid) {
        var node = grid.getSelectionModel().getSelected();
        if(node) {
            template(grid, node.data.template_id);
        }
    }
}

function addTemplate(grid) {
    template(grid);
}

function showTemplates()
{
    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                id:        'title',
                header:    'Template name',
                dataIndex: 'title'
            }, {
                header:    'Date',
                dataIndex: 'create_date',
                width:     150
            }
        ],
        defaultSortable: true
    });

    var templateRecord = Ext.data.Record.create([
        {name: 'template_id'},
        {name: 'type'},
        {name: 'title'},
        {name: 'create_date'}
    ]);
    
    var store = new Ext.data.Store({
        url: baseUrl + '/manage-templates/get-templates',
        autoLoad: true,
        remoteSort: false,
        
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, templateRecord),

        listeners: {
            'beforeload' : function(store, options) {
                options.params = options.params || {};

                // Load templates only of specific type
                var params = {
                    template_type: Ext.getCmp('system-templates-type').getValue()
                };
                Ext.apply(options.params, params);
            }
        }
    });
    
    var grid = new Ext.grid.GridPanel({
        renderTo: 'admin-system-templates-div',
        store: store,
        cm: cm,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autoHeight: true,
        split: true,
        stripeRows: true,
        autoExpandColumn: 'title',
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        viewConfig: { emptyText: 'No templates found.' },
        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('New Template'),
                ref: '../newTemplateBtn',
                disabled: true,
                handler: function()    {
                    addTemplate(grid);
                }
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit Template'),
                ref: '../editTemplateBtn',
                disabled: true,
                handler: function()    {
                    editTemplate(grid);
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Template'),
                ref: '../deleteTemplateBtn',
                disabled: true,
                handler: function() {
                    var node = grid.getSelectionModel().getSelected();
                    if(node) {
                        deleteTemplates(grid, node.data.template_id);
                    }
                }
            }, '->', {
                xtype: 'label',
                text: 'Template Type:',
                style: 'padding-right: 5px;'
            }, {
                id: 'system-templates-type',
                xtype: 'combo',
                store: new Ext.data.Store({
                    data: [
                        {'typeId': 'system',     'typeName': 'System'},
                        {'typeId': 'mass_email', 'typeName': 'Mass Email'},
                        {'typeId': 'other',      'typeName': 'Other'}
                    ],
                    reader: new Ext.data.JsonReader(
                        {id: 0},
                        Ext.data.Record.create([
                            {name: 'typeId'},
                            {name: 'typeName'}
                        ])
                    )
                }),
                mode: 'local',
                valueField: 'typeId',
                displayField: 'typeName',
                triggerAction: 'all',
                forceSelection: true,
                value: 'system',
                readOnly: true,
                typeAhead: false,
                selectOnFocus: true,
                editable: false,
                width: 130,
                listWidth: 130,
                listeners: {
                    'select' : function() {
                        grid.store.load();
                        toggleToolbarButtons();
                    }
                }
            }
        ]
    });

    var toggleToolbarButtons = function() {
        var sm = grid.getSelectionModel();
        var selTemplatesCount = sm.getCount();
        var template_type = Ext.getCmp('system-templates-type').getValue();
        var booSystem = template_type === 'system';

        grid.deleteTemplateBtn.setDisabled(selTemplatesCount === 0 || booSystem);
        grid.editTemplateBtn.setDisabled(selTemplatesCount != 1);

        grid.newTemplateBtn.setDisabled(booSystem);
    };

    grid.getSelectionModel().on({
        'selectionchange': function () {
            toggleToolbarButtons();
        }
    });

    grid.on('rowdblclick', function(){
        editTemplate(grid);
    });
    
    grid.getView().getRowClass = function(record){
        return (record.data.type == 'system' ? 'green-row' : '');
    };
}

Ext.onReady(function(){
    //init tooltips
    Ext.QuickTips.init();
    $('#admin-system-templates-div').css('min-height', getSuperadminPanelHeight() + 'px');

    //show templates grid
    showTemplates();
    updateSuperadminIFrameHeight('#admin-system-templates-div');
});