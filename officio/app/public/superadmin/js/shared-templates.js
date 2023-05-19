function template(grid, options)
{
    Ext.QuickTips.init();
    
    function insertField()
    {
        var row = fields.getSelectionModel().getSelected().data;
        editor.activated = true;
        editor.focus.defer(2, editor);
        
        var lt = Ext.isIE ? '&lt;%' : '<%';
        var gt = Ext.isIE ? '%&gt;' : '%>';
        
        editor.insertAtCursor(lt + row.name + gt);
    }

    var name = new Ext.form.TextField({
        name: 'templates_name',
        fieldLabel: 'Template Name',
        allowBlank: false,
        anchor: '95%'
    });

    var templates_for = new Ext.form.ComboBox({
        name: 'templates_for',
        hiddenName: 'templates_for',
        fieldLabel: 'Templates is for',
        store: new Ext.data.SimpleStore({
            fields: ['type_id', 'type_name'],
            data: [
                ['General', 'General'],
                ['Invoice', 'Invoice'],
                ['Payment', 'Receipt of Payment'],
                ['Request', 'Request for Payment'],
                ['Password', 'User Id & Password update'],
                ['Prospect', 'Company Prospect'],
                ['Welcome', 'Welcome Message']
            ]
        }),
        mode: 'local',
        displayField: 'type_name',
        valueField: 'type_id',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        allowBlank: false,
        anchor: '100%'
    });
    
    var from = new Ext.form.TextField({
        name: 'templates_from',
        fieldLabel: 'From Email Address',
        width: 332,
        listeners: {
            'valid': validateEmailField
        }
    });
    
    var cc = new Ext.form.TextField({
        name: 'templates_cc',
        fieldLabel: 'CC',
        vtype: 'multiemail',
        anchor: '95%'
    });
    
    var bcc = new Ext.form.TextField({
        name: 'templates_bcc',
        fieldLabel: 'BCC',
        vtype: 'multiemail',
        anchor: '100%'
    });
    
    var subject = new Ext.form.TextField({
        name: 'templates_subject',
        fieldLabel: 'Subject',
        anchor: '100%'
    });

    var strDefaultFilterValue = 'individual_0';
    var fieldsStore = new Ext.data.GroupingStore({
        url: baseUrl + '/shared-templates/get-fields',
        baseParams: {
            filter_by: strDefaultFilterValue
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        },
        Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
        autoLoad: false,
        sortInfo: {field: 'label', direction: "ASC"}, //order in-groups fields
        groupField: 'n'
    });

    var filterFieldsStore = new Ext.data.Store({
        url: baseUrl + '/shared-templates/get-fields-filter',
        autoLoad: true,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            id: 'filter_id'
        }, [
            {name: 'filter_id'},         // e.g. 'case_123'
            {name: 'filter_group_id'},   // e.g. 'case'
            {name: 'filter_group_name'}, // e.g. 'Case Profile'
            {name: 'filter_type_name'},  // e.g. 'Advice'
            {name: 'filter_type_id'}     // e.g. 123
        ])
    });

    // Set default value for the 'filter' combo
    filterFieldsStore.on('load', function(store) {
        var index = store.find(filterFieldsCombo.valueField, strDefaultFilterValue),
            record = store.getAt(index);

        // Apply combo value with delay - cause all things must be rendered
        setTimeout(function () {
            filterFieldsCombo.setValue(strDefaultFilterValue);
            filterFieldsCombo.fireEvent('beforeselect', filterFieldsCombo, record, strDefaultFilterValue);
        }, 100);
    }, this, {single: true});


    var filterFieldsCombo = new Ext.form.ComboBox({
        store: filterFieldsStore,
        mode: 'local',
        valueField: 'filter_id',
        displayField: 'filter_type_name',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Please select...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 350,
        listWidth: 350,

        tpl: new Ext.XTemplate(
            '<tpl for=".">'+
                '<tpl if="(this.filter_group_name != values.filter_group_name && !empty(values.filter_group_name))">'+
                    '<tpl exec="this.filter_group_name = values.filter_group_name"></tpl>'+
                    '<h1 style="padding: 2px;">{filter_group_name}</h1>'+
                '</tpl>'+

                '<tpl if="(empty(values.filter_group_name))">'+
                    '<div class="x-combo-list-item">{filter_type_name}</div>'+
                '</tpl>'+

                '<tpl if="(!empty(values.filter_group_name))">'+
                    '<div class="x-combo-list-item" style="padding-left: 20px;">{filter_type_name}</div>'+
                '</tpl>'+
            '</tpl>'
        )
    });

    // Apply selected 'filter' and reload fields list
    filterFieldsCombo.on('beforeselect', function(combo, rec) {
        fieldsStore.load({
            params: {
                filter_by: Ext.encode(rec.data)
            }
        });
    });


    // create the grid
    var fields = new Ext.grid.GridPanel({
        collapsible: true,
        title: 'Fields',
        region: 'east',
        split: true,
        width: 440,
        store: fieldsStore,
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
            renderer: function(val){return '&lt;%' + val + '%&gt;';}
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
        tbar: [
            {
                xtype: 'label',
                style: 'display: block; padding-top: 5px; height: 20px;',
                forId: filterFieldsCombo.getId(),
                html: 'Filter fields by:&nbsp;'
            }, filterFieldsCombo
        ],

        bbar: [new Ext.Toolbar.Button({
                text: '&nbsp;&nbsp;&nbsp;&nbsp;<<&nbsp;&nbsp;&nbsp;&nbsp;',
                pressed: true,
                style: 'padding-bottom:4px;',
                handler: function()
                {
                    insertField();
                }
             }), 'Double click on a field name to insert']
    });
    
    fields.on('rowdblclick', function() {
        insertField();
    });
    
    var editor = new Ext.ux.form.FroalaEditor({
        name: 'templates_message',
        region: 'center',
        hideLabel: true,
        height: 350,
        booAllowImagesUploading: true,
        allowBlank: false
    });
    
    var template_fs = new Ext.form.FieldSet({
        title: 'Template Info (required)',
        layout: 'fit',
        height: 80,
        cls: 'templates-fieldset',
        labelWidth: 120,
        items:
        {
            layout: 'column',
            items:
            [{
                columnWidth: 0.5,
                layout: 'form',
                items: name
            },
            {
                columnWidth: 0.5,
                layout: 'form',
                items: templates_for
            }]
        }
    });

    var field_fs = new Ext.form.FieldSet({
        title: 'Template Fields',
        height: 125,
        cls: 'templates-fieldset',
        labelWidth: 120,
        items: [
            {
                layout: 'form',
                style: 'padding-bottom: 5px;',
                items: from
            },
            {
                layout: 'column',
                style: 'padding-bottom: 5px;',
                items: [
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: cc
                    },
                    {
                        columnWidth: 0.5,
                        layout: 'form',
                        items: bcc
                    }
                ]
            },
            {
                layout: 'form',
                items: subject
            }
        ]
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
        bodyStyle: 'padding:0px',
        items: [template_fs, field_fs, fields_fs]
    });
    
    var saveBtn = new Ext.Button({
        text: 'Save Template',
        cls:  'orange-btn',
        handler: function()
        {
            var form = pan.getForm();
            if(form.isValid() && !from.getEl().hasClass(from.invalidClass))
            {
                form.submit({
                    url: baseUrl + '/shared-templates/save',
                    waitMsg: 'Saving...',
                    params:
                    {
                        act: options.action,
                        template_id: options.template_id
                    },
                    success: function()
                    {
                        grid.store.reload();

                        if(options.action == 'add')    {
                            Ext.Msg.alert('Status', 'New Template added!');
                        }
                        
                        win.close();
                    },
                    failure: function()
                    {
                      Ext.Msg.alert('Status', 'Can\'t save Template');
                      Ext.getBody().unmask();
                    }
                });
            }
        }
    });
    
    var closeBtn = new Ext.Button({
        text: 'Cancel',
        handler: function(){ win.close(); }
    });

    var win = new Ext.Window({
        title:      options.action === 'add' ? '<i class="las la-plus"></i>' + _('Add New Template') : '<i class="las la-edit"></i>' + _('Edit Template'),
        modal:      true,
        autoHeight: true,
        resizable:  false,
        width:      1000,
        layout:     'form',
        items:      pan,
        buttons:    [closeBtn, saveBtn]
    });

    win.show();
    win.center();

    //edit action
    if(options.action == 'edit')
    {
        //set default values
        win.getEl().mask('Loading...');
        Ext.Ajax.request({
            url:     baseUrl + "/shared-templates/get-template?id=" + options.template_id,
            success: function (f) {
                //get values
                var resultData = Ext.decode(f.responseText);

                templates_for.setValue(resultData.templates_for);
                name.setValue(resultData.name);
                editor.setValue(resultData.message);
                subject.setValue(resultData.subject);
                from.setValue(resultData.from);
                cc.setValue(resultData.cc);
                bcc.setValue(resultData.bcc);

                win.getEl().unmask();
            },
            failure: function () {
                Ext.Msg.alert('Status', 'Can\'t load Template Content');
                win.getEl().unmask();
            }
        });
    }
}

function deleteTemplates(grid, template_id)
{
    Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete Template?', function(btn) {
        if (btn == 'yes') {
            Ext.getBody().mask('Deleting...');
            Ext.Ajax.request({
                url: baseUrl + '/shared-templates/delete',
                params: {
                    templates: Ext.encode([template_id])
                },
                success: function() {
                    grid.store.reload();
                    Ext.getBody().unmask();
                },
                failure: function() {
                    Ext.Msg.alert('Status', 'Selected template cannot be deleted. Please try again later.');
                    Ext.getBody().unmask();
                }
            });
        }
    });
}

    var editTemplate = function() {
        var grid = Ext.getCmp('admin-shared-templates');
        if(grid) {
            var node = grid.getSelectionModel().getSelected();
            if(node) {
                template(grid, {action: 'edit', template_id: node.data.template_id});
            }
        }
    };

function setDefaultTemplate(grid) {
    if (!grid.getSelectionModel().hasSelection()) {
        return;
    }

    var default_template_id = null;
    grid.store.each(function () {
        if (this.data.is_default) {
            default_template_id = this.data.template_id;
        }
    });

    var template_id = grid.getSelectionModel().getSelected().data.template_id;

    //same template
    if (template_id == default_template_id) {
        return;
    }

    //set template as default
    Ext.getBody().mask('Please wait...');
    Ext.Ajax.request({
        url: baseUrl + '/shared-templates/set-default',
        params: {
            old_template_id: default_template_id,
            new_template_id: template_id
        },
        success: function () {
            grid.store.reload();
            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.getBody().unmask();
            Ext.Msg.alert('Status', 'Can\'t set template as default');
        }
    });
}

function showTemplates()
{
    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                id: 'name',
                header: "Template name",
                dataIndex: 'name',
                sortable: true,
                width: 300,
                renderer: function (v) {
                    return "<a href='#' class='blulinkun' onClick='editTemplate(); return false;'>" + v + "</a>";
                }
            },
            {
                header: "Default",
                dataIndex: 'is_default',
                align: 'center',
                width: 100,
                renderer: function (v) {
                    return v ? 'YES' : '';
                }
            },
            {
                header: "Templates for",
                dataIndex: 'templates_for',
                width: 160
            },
            {
                header: "Size",
                dataIndex: 'size',
                align: 'center',
                width: 80
            },
            {
                header: "Date",
                dataIndex: 'create_date',
                width: 140
            }
        ],
        defaultSortable: true
    });
    
    var store = new Ext.data.Store({
        url: baseUrl + '/shared-templates/get-templates',
        autoLoad: true,
        remoteSort: false,
        reader: new Ext.data.JsonReader(
        {
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
                {name: 'template_id'},
                {name: 'name'},
                {name: 'is_default'},
                {name: 'templates_for'},
                {name: 'size'},
                {name: 'create_date'}
            ])),
        listeners: {
            load: function () {
                var iframe = parent.document.getElementById('admin_section_frame');
                if (iframe) {
                    var iframeBodyHeight = iframe.contentWindow.document.body.offsetHeight;
                    iframe.style.height = iframeBodyHeight + 'px';
                }
            }
        }
    });
    
    var grid = new Ext.grid.GridPanel({
        id: 'admin-shared-templates',
        renderTo: 'admin-shared-templates-div',
        store: store,
        cm: cm,
        sm: new Ext.grid.CheckboxSelectionModel(),
        autoHeight: true,
        split: true,
        stripeRows: true,
        autoExpandColumn: 'name',
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        viewConfig: { emptyText: 'No templates found.' },
        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('New Template'),
                id: 't-fmenu-add',
                handler: function () {
                    template(grid, {action: 'add'});
                }
            },
            {
                text: '<i class="las la-edit"></i>' + _('Edit Template'),
                disabled: true,
                ref: '../editTemplateBtn',
                handler: function () {
                    editTemplate();
                }
            },
            {
                text: '<i class="las la-trash"></i>' + _('Delete Template'),
                disabled: true,
                ref: '../deleteTemplateBtn',
                handler: function () {
                    var node = grid.getSelectionModel().getSelected();
                    if (node) {
                        deleteTemplates(grid, node.data.template_id);
                    }
                }
            },
            {
                text: '<i class="las la-check"></i>' + _('Set as Default'),
                disabled: true,
                ref: '../setTemplateAsDefaultBtn',
                handler: function () {
                    setDefaultTemplate(grid);
                }
            }
        ]
    });

    grid.getSelectionModel().on('selectionchange', function(selModel) {
        var sel = selModel.getSelections();
        var booIsSelectedOnlyOne = sel.length == 1;

        grid.editTemplateBtn.setDisabled(!booIsSelectedOnlyOne);
        grid.deleteTemplateBtn.setDisabled(!booIsSelectedOnlyOne);
        grid.setTemplateAsDefaultBtn.setDisabled(!booIsSelectedOnlyOne);
    });

    grid.on('dblcick', function(){
        editTemplate();
    });
}

Ext.onReady(function(){
    $('#admin-shared-templates-div').css('min-height', getSuperadminPanelHeight() + 'px');
    showTemplates();
});