var ProspectTemplatesGrid = function (config) {
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        url: topBaseUrl + '/superadmin/manage-company-prospects/get-templates-list-for-tree',
        autoLoad: true,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {
                name: 'template_id'
            }, {
                name: 'filename'
            }, {
                name: 'default_template',
                type: 'boolean'
            }, {
                name: 'author'
            }, {
                name: 'create_date'
            }, {
                name: 'update_date'
            }, {
                name: 'author_update'
            }, {
                name: 'template_tooltip'
            }
        ]))
    });

    this.cm = new Ext.grid.ColumnModel({
        defaults: {
            menuDisabled: true
        },

        columns: [{
            header: _('Template name'),
            dataIndex: 'filename',
            width: config.mainColumnWidth
        }, {
            header: '',
            dataIndex: 'default_template',
            align: 'center',
            renderer: this.formatDefaultColumn,
            width: 80
        }, {
            header: 'Updated by',
            dataIndex: 'author_update',
            width: 160,
            renderer: function (val, p, record) {
                if (val) {
                    return String.format(
                        '<span ext:qtip="{0}" ext:qwidth="450" style="cursor: help;">{1}</span>',
                        record['data']['template_tooltip'].replaceAll("'", "\'"),
                        val
                    );
                }
            }
        }, {
            header: _('Updated on'),
            dataIndex: 'update_date',
            width: 140,
            align: 'center',
            renderer: function (val, p, record) {
                if (val) {
                    return String.format(
                        '<span ext:qtip="{0}" ext:qwidth="450" style="cursor: help;">{1}</span>',
                        record['data']['template_tooltip'].replaceAll("'", "\'"),
                        val
                    );
                }
            }
        }],

        defaultSortable: true
    });

    this.tbar = [{
        text: '<i class="las la-plus"></i>' + _('New Template'),
        tooltip: _('Create a new template for a selected folder.'),
        cls: 'main-btn',
        disabled: false,
        handler: this.templateOpenDialog.createDelegate(this, [false])
    }, {
        text: '<i class="las la-pen"></i>' + _('Edit Template'),
        tooltip: _('Edit the selected template.'),
        cls: 'main-btn',
        ref: '../EditTemplateButton',
        disabled: true,
        handler: this.templateOpenDialog.createDelegate(this, [true])
    }, {
        text: '<i class="las la-trash"></i>' + _('Delete Template'),
        tooltip: _('Delete selected template.'),
        ref: '../DeleteTemplateButton',
        disabled: true,
        handler: this.templateDelete.createDelegate(this)
    }, {
        text: '<i class="las la-check"></i>' + _('Set as Default'),
        tooltip: _('Make selected template the default template.'),
        ref: '../MakeDefaultTemplateButton',
        disabled: true,
        handler: this.templateMarkDefault.createDelegate(this)
    }, '->', {
        text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
        ctCls: 'x-toolbar-cell-no-right-padding',
        handler: this.refreshGrid.createDelegate(this)
    }, {
        xtype: 'label',
        html: String.format(
            "<i class='las la-info-circle help-icon' ext:qtip='{0}' ext:qwidth='450' style='cursor: help; margin-left: 10px; vertical-align: text-bottom'></i>",
            _('<b>Important Note:</b><br>It is the responsibility of the user to ensure that he/she is fully compliant with all regulatory requirements as well as all laws in effect in his/her jurisdiction(s) with respect to the use of Officio’s "Template" feature.')
        )
    }, {
        xtype: 'button',
        text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
        hidden: !allowedPages.has('help'),
        handler: function () {
            showHelpContextMenu(this.getEl(), 'prospect-templates');
        }
    }];

    ProspectTemplatesGrid.superclass.constructor.call(this, {
        id: 'prospect-templates-grid',
        sm: new Ext.grid.CheckboxSelectionModel(),
        split: true,
        autoWidth: true,
        stripeRows: true,
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-panel-with-border',

        viewConfig: {
            deferEmptyText: _('No templates found.'),
            emptyText: _('No templates found.'),
            getRowClass: this.applyRowClass
        }
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
    this.on('rowdblclick', this.templateOpenDialog.createDelegate(this, [true]));
};


// Methods
Ext.extend(ProspectTemplatesGrid, Ext.grid.GridPanel, {
    refreshGrid: function () {
        this.getStore().reload();
    },

    updateToolbarButtons: function (sm) {
        var booOneSelected = (sm.getSelections().length === 1);
        this.EditTemplateButton.setDisabled(!booOneSelected);
        this.DeleteTemplateButton.setDisabled(!booOneSelected);

        var booIsDefault = booOneSelected ? sm.getSelections()[0].data.default_template : false;
        this.MakeDefaultTemplateButton.setDisabled(!booOneSelected || booIsDefault);
    },

    formatDefaultColumn: function (booDefault) {
        return booDefault ? '<span style="color: #46BA72; font-weight: 500">' + _('Default') + '</span>' : '';
    },

    // Apply custom class for row in relation to several criteria
    applyRowClass: function (record) {
        return record.data.default_template ? 'prospect-template-row-default ' : '';
    },


    templateOpenDialog: function (booEdit, template_id) {
        var thisGridPanel = this;
        template_id = template_id || 0;

        if (booEdit && empty(template_id)) {
            var template = thisGridPanel.getSelectionModel().getSelected();
            template_id = template.data.template_id;
        }

        var tabPanel = Ext.getCmp('prospects-templates-tab');
        var tabId = 'prospects-templates-tab-' + template_id;
        var contentDivId = 'prospects-templates-tab-content-' + template_id;

        var openedTab = Ext.getCmp(tabId);
        if (openedTab) {
            tabPanel.setActiveTab(openedTab);
            return;
        }

        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/superadmin/manage-company-prospects/get-template-info',
            params: {
                template_id: template_id
            },
            success: function (f) {
                var templateEditorId = Ext.id();

                function saveProspectTemplateChanges() {
                    if (!name.isValid() || !subject.isValid() || !from.isValid() || !to.isValid() || !cc.isValid() || !bcc.isValid()) {
                        pan.setActiveTab(0);
                        Ext.simpleConfirmation.msg(_('Warning'), _('Please fill required fields and try again.'));
                        return;
                    }

                    var body = editor.getValue();
                    if (body === '') {
                        pan.setActiveTab(1);
                        Ext.simpleConfirmation.msg(_('Warning'), _('Template content cannot be empty.'));
                        return;
                    }

                    tabPanel.getEl().mask(_('Saving...'));
                    Ext.Ajax.request({
                        url: topBaseUrl + '/superadmin/manage-company-prospects/save-template',

                        params: {
                            template_id: template_id,
                            name: Ext.encode(name.getValue()),
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
                                var thisTab = tabPanel.getActiveTab();
                                if (empty(template_id)) {
                                    tabPanel.getEl().unmask();

                                    tabPanel.remove(thisTab);
                                    thisGridPanel.templateOpenDialog(true, result.template_id);
                                } else {
                                    thisTab.setTitle(name.getValue());

                                    tabPanel.getEl().mask(_('Done'));
                                    thisGridPanel.store.reload();
                                    setTimeout(function () {
                                        tabPanel.getEl().unmask();
                                    }, 750);
                                }
                            } else {
                                Ext.simpleConfirmation.error(result.message);
                                tabPanel.getEl().unmask();
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error(_('An error occurred when saving template'));
                            tabPanel.getEl().unmask();
                        }
                    });
                }

                //get values
                var result = Ext.decode(f.responseText);

                var name = new Ext.form.TextField({
                    fieldLabel: _('Name'),
                    allowBlank: false,
                    value: result.name,
                    anchor: '100%'
                });

                var from = new Ext.form.TextField({
                    fieldLabel: _('From'),
                    vtype: 'email',
                    value: result.from,
                    anchor: '100%'
                });

                var to = new Ext.form.TextField({
                    fieldLabel: _('To'),
                    value: result.to,
                    anchor: '100%'
                });

                var cc = new Ext.form.TextField({
                    fieldLabel: _('CC'),
                    value: result.cc,
                    anchor: '100%'
                });

                var bcc = new Ext.form.TextField({
                    fieldLabel: _('BCC'),
                    value: result.bcc,
                    anchor: '100%'
                });

                var subject = new Ext.form.TextField({
                    fieldLabel: _('Subject'),
                    value: result.subject,
                    anchor: '100%'
                });

                var fieldsPanel1 = new Ext.grid.GridPanel({
                    title: _('Fields'),
                    split: true,
                    autoWidth: true,

                    store: new Ext.data.GroupingStore({
                        url: topBaseUrl + '/superadmin/manage-company-prospects/get-fields',
                        reader: new Ext.data.JsonReader({
                            root: 'rows',
                            totalProperty: 'totalCount'
                        }, Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
                        autoLoad: true,
                        sortInfo: false,
                        groupField: 'n'
                    }),

                    cm: new Ext.grid.ColumnModel([{
                        dataIndex: 'n',
                        hidden: true
                    }, {
                        header: 'Group',
                        dataIndex: 'group',
                        hidden: true
                    }, {
                        header: 'Field',
                        dataIndex: 'name',
                        width: 200,
                        renderer: function (val) {
                            return '{' + val + '}';
                        }
                    }, {
                        header: 'Label',
                        dataIndex: 'label',
                        width: 200
                    }]),

                    view: new Ext.grid.GroupingView({
                        deferEmptyText: _('No fields found.'),
                        emptyText: _('No fields found.'),
                        forceFit: true,
                        groupTextTpl: '{[values.rs[0].data["group"]]} ({[values.rs.length]} {[values.rs.length > 1 ? "Fields" : "Field"]})'
                    }),

                    sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
                    height: initPanelSize() - 130,
                    stripeRows: true,
                    hideHeaders: true,
                    cls: 'extjs-grid',
                    loadMask: true,

                    tbar: {
                        xtype: 'panel',
                        layout: 'form',
                        labelWidth: 50,
                        items: [{
                            xtype: 'box',
                            autoEl: {
                                tag: 'div',
                                style: 'font-size: smaller; padding: 5px 5px 0 5px',
                                html: _('To automatically populate the Template, select a field below and click the "Copy" button. Then, paste it into the desired box in the Template.')
                            },
                        }]
                    },

                    bbar: [{
                        xtype: 'button',
                        text: '<i class="las la-copy"></i>' + _('Copy Field ID'),
                        pressed: true,
                        cls: 'blue-btn',
                        style: 'margin-bottom:4px; margin-top:4px;',
                        handler: function () {
                            fieldsPanel1.copyFieldToClipboard();
                        }
                    }, _('Double click on a field name to copy to the clipboard')],

                    listeners: {
                        'rowdblclick': function () {
                            fieldsPanel1.copyFieldToClipboard();
                        }
                    },

                    copyFieldToClipboard: function () {
                        var selected = this.getSelectionModel().getSelected();
                        if (selected) {
                            var row = selected.data;

                            var text = '{' + row.name + '}';
                            text = text.trim();

                            var textarea = document.createElement('textarea');
                            textarea.id = 'temp-copy-to-clipboard-field';
                            textarea.style.height = 0;

                            document.body.appendChild(textarea);
                            textarea.value = text;

                            var selector = document.querySelector('#temp-copy-to-clipboard-field');
                            selector.select();

                            document.execCommand('copy');
                            document.body.removeChild(textarea);
                            Ext.simpleConfirmation.msg(_('Info'), _('Field ID copied to clipboard'));
                        } else {
                            Ext.simpleConfirmation.msg(_('Info'), _('Please select a field to copy.'));
                        }
                    }
                });

                var fieldsPanel2 = new Ext.grid.GridPanel({
                    title: _('Fields'),
                    split: true,
                    autoWidth: true,

                    store: new Ext.data.GroupingStore({
                        url: topBaseUrl + '/superadmin/manage-company-prospects/get-fields',
                        reader: new Ext.data.JsonReader({
                            root: 'rows',
                            totalProperty: 'totalCount'
                        }, Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
                        autoLoad: false,
                        sortInfo: false,
                        groupField: 'n'
                    }),

                    cm: new Ext.grid.ColumnModel([{
                        dataIndex: 'n',
                        hidden: true
                    }, {
                        header: 'Group',
                        dataIndex: 'group',
                        hidden: true
                    }, {
                        header: 'Field',
                        dataIndex: 'name',
                        width: 200,
                        renderer: function (val) {
                            return '{' + val + '}';
                        }
                    }, {
                        header: 'Label',
                        dataIndex: 'label',
                        width: 200
                    }]),

                    view: new Ext.grid.GroupingView({
                        deferEmptyText: _('No fields found.'),
                        emptyText: _('No fields found.'),
                        forceFit: true,
                        groupTextTpl: '{[values.rs[0].data["group"]]} ({[values.rs.length]} {[values.rs.length > 1 ? "Fields" : "Field"]})'
                    }),

                    sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
                    height: initPanelSize() - 90,
                    stripeRows: true,
                    hideHeaders: true,
                    cls: 'extjs-grid',
                    loadMask: true,

                    tbar: {
                        xtype: 'panel',
                        layout: 'form',
                        labelWidth: 50,
                        items: [{
                            xtype: 'box',
                            autoEl: {
                                tag: 'div',
                                style: 'font-size: smaller; padding: 5px 5px 0 5px',
                                html: _('Double-click on the field you want to insert into the CONTENT box.<div style="font-style: italic">NOTE: The field will be inserted where your cursor is placed.</div>')
                            },
                        }]
                    },

                    bbar: [{
                        xtype: 'button',
                        text: '&nbsp;&nbsp;&nbsp;&nbsp;' + '<i class="las la-angle-double-left" style="padding-right: 0"></i>' + '&nbsp;&nbsp;&nbsp;&nbsp;',
                        pressed: true,
                        cls: 'blue-btn',
                        style: 'margin-bottom:4px; margin-top:4px;',
                        handler: function () {
                            fieldsPanel2.insertField();
                        }
                    }, _('Select field above, then click button.')],

                    listeners: {
                        'rowdblclick': function () {
                            fieldsPanel2.insertField();
                        }
                    },

                    insertField: function () {
                        var row = fieldsPanel2.getSelectionModel().getSelected().data;
                        var text = '{' + row.name + '}';
                        text = text.trim();

                        Ext.getCmp(templateEditorId)._froalaEditor.html.insert(text);
                    }
                });

                var editor = new Ext.ux.form.FroalaEditor({
                    id: templateEditorId,
                    fieldLabel: '',
                    hideLabel: true,
                    height: initPanelSize() - 150,
                    resizeEnabled: false,
                    value: '',
                    booAllowImagesUploading: true
                });

                var fields_fs = new Ext.form.FieldSet({
                    layout: 'column',
                    title: '',
                    cls: 'applicants-profile-fieldset',
                    style: 'padding: 0; margin-bottom: 0',
                    autoHeight: true,

                    items: [{
                        layout: 'form',
                        cls: 'no-padding-body',
                        items: editor,
                        width: Ext.getCmp('prospects-templates-tab').getWidth() - 475 - 275 // 275 - tabWidth
                    }, {
                        xtype: 'container',
                        width: 440,
                        cls: 'right-panel',
                        style: 'margin-left: 10px; background-color: #FFF',
                        items: fieldsPanel2
                    }]
                });

                var saveBtn = new Ext.Button({
                    text: '<i class="lar la-save"></i>' + _('Save'),
                    cls: 'orange-btn',
                    style: 'margin-bottom: 10px;',
                    handler: saveProspectTemplateChanges
                });

                var saveBtn2 = new Ext.Button({
                    text: '<i class="lar la-save"></i>' + _('Save'),
                    cls: 'orange-btn',
                    style: 'margin-bottom: 10px;',
                    handler: saveProspectTemplateChanges
                });

                var pan = new Ext.ux.VerticalTabPanel({
                    hideMode: 'visibility',
                    autoWidth: true,
                    autoHeight: true,
                    activeTab: 1,
                    enableTabScroll: true,
                    plain: true,
                    deferredRender: false,
                    cls: 'clients-sub-tab-panel',

                    headerCfg: {
                        cls: 'clients-sub-tab-header x-tab-panel-header x-vr-tab-panel-header'
                    },

                    defaults: {
                        autoHeight: true,
                        autoScroll: true
                    },

                    items: [{
                        title: '<i class="las la-arrow-left"></i>' + _('Back to Templates'),
                        tabIdentity: 'back_to_templates'
                    }, {
                        title: _('1. Define Settings'),
                        itemCls: 'templates-sub-tab-items',
                        cls: 'templates-sub-tab',
                        style: 'padding: 10px',
                        xtype: 'container',
                        layout: 'column',


                        items: [{
                            xtype: 'form',
                            width: Ext.getCmp('prospects-templates-tab').getWidth() - 460 - 275, // 275 - tabWidth
                            labelAlign: 'top',
                            items: [saveBtn, {
                                xtype: 'fieldset',
                                title: _('Template Info (required)'),
                                cls: 'applicants-profile-fieldset',
                                layout: 'fit',
                                titleCollapse: false,
                                collapsible: true,
                                items: {
                                    layout: 'form',
                                    items: [name]
                                }
                            }, {
                                xtype: 'fieldset',
                                title: _('Template Fields'),
                                cls: 'applicants-profile-fieldset',
                                layout: 'fit',
                                titleCollapse: false,
                                collapsible: true,
                                items: {
                                    layout: 'form',
                                    items: [to, from, cc, bcc, subject]
                                }
                            }]
                        }, {
                            xtype: 'container',
                            width: 440,
                            items: [
                                {
                                    xtype: 'container',
                                    layout: 'column',
                                    items: [
                                        {
                                            xtype: 'button',
                                            cls: 'main-btn',
                                            style: 'margin-bottom: 10px; margin-right: 20px; float: right',
                                            text: '<i class="lar la-arrow-alt-circle-right"></i>' + _('Next'),
                                            width: 150,
                                            handler: function () {
                                                pan.setActiveTab(2);
                                            }
                                        }, {
                                            xtype: 'button',
                                            style: 'margin-right: 10px; margin-top: 5px; float: right',
                                            text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                                            hidden: !allowedPages.has('help'),
                                            handler: function () {
                                                showHelpContextMenu(this.getEl(), 'prospect-template-details');
                                            }
                                        }
                                    ]
                                }, {
                                    xtype: 'container',
                                    cls: 'right-panel',
                                    style: 'clear: both;',
                                    items: fieldsPanel1
                                }
                            ]
                        }]
                    }, {
                        xtype: 'form',
                        title: _('2. Define Content'),
                        style: 'padding: 4px',

                        items: [
                            {
                                xtype: 'container',
                                layout: 'column',
                                items: [
                                    saveBtn2,
                                    {
                                        xtype: 'button',
                                        cls: 'main-btn',
                                        style: 'margin-bottom: 10px; margin-right: 20px; float: right',
                                        text: '<i class="lar la-arrow-alt-circle-left"></i>' + _('Back'),
                                        width: 150,
                                        handler: function () {
                                            pan.setActiveTab(1);
                                        }
                                    }, {
                                        xtype: 'button',
                                        style: 'margin-right: 10px; margin-top: 5px; float: right',
                                        text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                                        hidden: !allowedPages.has('help'),
                                        handler: function () {
                                            showHelpContextMenu(this.getEl(), 'prospect-template-details');
                                        }
                                    }, {
                                        xtype: 'calendlybutton',
                                        text: '<i class="las la-calendar"></i>' + _('Insert Calendly Link'),
                                        style: 'margin-right: 10px; float: right',
                                        cls: 'secondary-column-btn',
                                        hidden: !(typeof calendly_enabled !== 'undefined' && calendly_enabled),
                                        editor: editor
                                    }
                                ]
                            },

                            fields_fs
                        ],

                        isStoreLoaded: false,
                        listeners: {
                            'activate': function () {
                                editor.syncIframeSize();

                                if (!this.isStoreLoaded) {
                                    fieldsPanel2.getStore().reload();
                                    this.isStoreLoaded = true;
                                }
                            }
                        }
                    }],

                    listeners: {
                        'beforetabchange': function (oTabPanel, newTab) {
                            if (newTab.tabIdentity === 'back_to_templates') {
                                var oTabPanel = Ext.getCmp('prospects-templates-tab');
                                var templatesTab = oTabPanel.getItem('prospects-templates-sub-tab');

                                if (oTabPanel && templatesTab) {
                                    // Switch to the queue tab
                                    oTabPanel.setActiveTab(templatesTab);
                                }

                                return false;
                            }
                        }
                    }
                });

                tabPanel.add({
                    id: tabId,
                    title: empty(result.name) ? _('New Template') : result.name,
                    html: '<div id="' + contentDivId + '"></div>',
                    autoHeight: true,
                    closable: true
                }).show();

                pan.render(contentDivId);

                Ext.getCmp(templateEditorId).setValue(result.message);

                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Can\'t load Template Content'));
                Ext.getBody().unmask();
            }
        });
    },

    templateDelete: function () {
        var thisGridPanel = this;
        var template = thisGridPanel.getSelectionModel().getSelected();

        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete <i>' + template.data.filename + '</i>?'), function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/superadmin/manage-company-prospects/template-delete',
                    params: {
                        template_id: template.data.template_id
                    },
                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.info(_('Template <i>' + template.data.filename + '</i> was successfully deleted.'));
                            thisGridPanel.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Template <i>' + template.data.filename + '</i> cannot be deleted. Please try again later.'));
                    }
                });
            }
        });
    },

    templateMarkDefault: function () {
        var thisGridPanel = this;
        var template = thisGridPanel.getSelectionModel().getSelected();

        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to mark template <i>' + template.data.filename + '</i> as default?'), function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Processing...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/superadmin/manage-company-prospects/template-mark-as-default',
                    params: {
                        template_id: template.data.template_id
                    },
                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.info(_('Template <i>' + template.data.filename + '</i> was successfully marked as default.'));
                            thisGridPanel.getStore().reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Template <i>' + template.data.filename + '</i> cannot be marked as default. Please try again later.'));
                    }
                });
            }
        });
    }
});
