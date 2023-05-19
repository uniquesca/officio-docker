var DocumentTemplatesTree = function (config) {
    var thisTemplatesTree = this;
    Ext.apply(this, config);

    this.contextTemplateMenu = null;
    this.contextFoldersMenu = null;

    DocumentTemplatesTree.superclass.constructor.call(this, {
        id:          'document-templates-tree',
        cls:         'tree',
        autoWidth:   true,
        rootVisible: false,
        autoScroll:  true,
        enableDD:    true,
        bodyBorder:  false,
        border:      false,
        lines:       true,

        root: new Ext.tree.AsyncTreeNode({
            allowDrop: false,
            allowDrag: false,
            expanded:  true,

            allowRW:           true,
            allowEdit:         true,
            allowDeleteFolder: false,

            listeners: {
                load: function (node) {
                    setTimeout(function () {
                        if (node.firstChild) {
                            node.firstChild.select();
                            node.firstChild.fireEvent('click', node.firstChild);
                        }
                    }, 500);
                }
            }
        }),

        destination: 'templates',

        columns: [{
            id: 'filename',
            header: 'Template name',
            dataIndex: 'filename',
            sortable: true,
            width: config.mainColumnWidth,
            renderer: function (v, meta, record) {
                var strDefault = '';
                if (record.is_default) {
                    strDefault = '<span style="margin-left: 50px; font-weight: 500">' + _('(Default)') + '</span>'
                    v = '<span style="font-weight: 900">' + v + '</span>';
                }
                return (v ? v : '') + strDefault;
            }
        },
            {
                header: 'Templates type',
                dataIndex: 'templates_type',
                width: 130,
                align: 'center',

                renderer: function (val) {
                    if (!empty(val)) {
                        var img = '';
                        var title = '';
                        switch (val) {
                        case 'Email':
                            img = 'las la-envelope';
                            title = 'Email template';
                            break;

                        case 'Letter':
                            img = 'las la-file';
                            title = 'Letter template';
                            break;

                        default:
                    }

                    return String.format('<i class="{0}" title="{1}" style="font-size: 18px"></i>', img, title);
                }
            }
        }, {
            header:    'Templates for',
            dataIndex: 'templates_for',
            width:     150
        }, {
            header:    'Updated by',
            dataIndex: 'author_update',
            width:     130,
            renderer: function (val, p, record) {
                if (val) {
                    return String.format(
                        '<span ext:qtip="{0}" ext:qwidth="450" style="cursor: help;">{1}</span>',
                        record['template_tooltip'].replaceAll("'", "\'"),
                        val
                    );
                }
            }
        }, {
            header:    'Updated on',
            dataIndex: 'update_date',
            width:     100,
            align:     'center',
            renderer: function (val, p, record) {
                if (val) {
                    return String.format(
                        '<span ext:qtip="{0}" ext:qwidth="450" style="cursor: help;">{1}</span>',
                        record['template_tooltip'].replaceAll("'", "\'"),
                        val
                    );
                }
            }
        }],

        loader: new Ext.tree.TreeLoader({
            dataUrl:     topBaseUrl + '/templates/index/get-templates',
            baseParams:  {
                subfolders: Ext.encode(true)
            },
            uiProviders: {'col': Ext.tree.ColumnNodeUI},
            listeners:   {
                beforeload: function () {
                    Ext.getBody().mask('Loading...');
                },

                load: function () {
                    Ext.getBody().unmask();
                }
            }
        }),

        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('New Template'),
                tooltip: _('Create a new template for a selected folder.'),
                cls: 'main-btn',
                id: 't-fmenu-add',
                disabled: true,
                menu: {
                    cls: 'no-icon-menu',
                    showSeparator: false,
                    items: [{
                        text: '<i class="las la-envelope"></i>' + _('New Email Template'),
                        id: 't-fmenu-add-email',

                        handler: function () {
                            if (!thisTemplatesTree.getFocusedFolderId(thisTemplatesTree)) {
                                Ext.simpleConfirmation.warning('Please select Template Folder!');
                            } else {
                                thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), true);
                            }
                        }
                    }, {
                        text: '<i class="las la-file"></i>' + _('New Letter Template'),
                        id: 't-fmenu-add-letter',

                        handler: function () {
                            if (!thisTemplatesTree.getFocusedFolderId(thisTemplatesTree)) {
                                Ext.simpleConfirmation.warning('Please select Template Folder!');
                            } else {
                                thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), false, true);
                            }
                        }
                    }]
                }
            }, {
                text: '<i class="las la-pen"></i>' + _('Edit Template'),
                tooltip: _('Edit the selected template.'),
                cls: 'main-btn',
                id: 't-fmenu-edit',
                disabled: true,
                handler: thisTemplatesTree.templateEdit.createDelegate(thisTemplatesTree)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Template'),
                tooltip: _('Delete selected template.'),
                id: 't-fmenu-delete',
                disabled: true,
                handler: thisTemplatesTree.deleteTemplates.createDelegate(thisTemplatesTree)
            }, {
                text: '<i class="las la-check"></i>' + _('Set as Default'),
                tooltip: _('Make selected template the default template.'),
                id: 't-fmenu-default',
                disabled: true,
                hidden: !is_administrator,
                handler: thisTemplatesTree.setDefaultTemplate.createDelegate(thisTemplatesTree)
            }, '->', {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                ctCls: 'x-toolbar-cell-no-right-padding',
                handler: function () {
                    thisTemplatesTree.getRootNode().reload();
                }
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
                    showHelpContextMenu(this.getEl(), 'client-templates');
                }
            }
        ]
    });

    this.on('movenode', this.onThisTreeDragAndDrop.createDelegate(this));
    this.on('contextmenu', this.onThisTreeContextMenu.createDelegate(this));
    this.on('click', this.onThisTreeClick.createDelegate(this));
    this.on('dblclick', this.onThisTreeDoubleClick.createDelegate(this));
    this.on('render', this.onThisTreeRender.createDelegate(this));
};

Ext.extend(DocumentTemplatesTree, Ext.tree.ColumnTree, {
    onThisTreeRender: function () {
        var thisTemplatesTree = this;

        this.contextFoldersMenu = new Ext.menu.Menu({
            cls:             'no-icon-menu',
            showSeparator:   false,
            enableScrolling: false,

            items: [{
                text:     '<i class="las la-folder-plus"></i>' + _('Add new Folder'),
                id:       't-tmenu-add-folder',
                disabled: true,
                handler:  function () {
                    addFolder(thisTemplatesTree);
                }
            }, {
                text:     '<i class="lar la-edit"></i>' + _('Rename'),
                id:       't-tmenu-rename-folder',
                disabled: true,
                handler:  function () {
                    renameDocument(thisTemplatesTree, 'templates');
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                id:       't-tmenu-delete-folder',
                disabled: true,
                handler:  thisTemplatesTree.deleteTemplateFolder.createDelegate(thisTemplatesTree)
            }, '-', {
                text:     '<i class="las la-envelope"></i>' + _('New Email Template'),
                id:       't-tmenu-add-email',
                disabled: true,
                handler:  function () {
                    thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), true);
                }
            }, {
                text:     '<i class="las la-file"></i>' + _('New Letter Template'),
                id:       't-tmenu-add-letter',
                disabled: true,
                handler:  function () {
                    thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), false);
                }
            }]
        });

        this.contextTemplateMenu = new Ext.menu.Menu({
            cls:             'no-icon-menu',
            showSeparator:   false,
            enableScrolling: false,

            items: [{
                text:     '<i class="las la-pen"></i>' + _('Edit Template'),
                id:       't-tmenu-edit',
                ref:      '../EditTemplateMenuItem',
                disabled: true,
                handler:  thisTemplatesTree.templateEdit.createDelegate(thisTemplatesTree)
            }, {
                text:     '<i class="las la-trash"></i>' + _('Delete Template'),
                id:       't-tmenu-delete',
                disabled: true,
                handler:  thisTemplatesTree.deleteTemplates.createDelegate(thisTemplatesTree)
            }, {
                text:     '<i class="las la-copy"></i>' + _('Duplicate Template'),
                id:       't-tmenu-duplicate',
                disabled: true,
                handler:  thisTemplatesTree.duplicateTemplates.createDelegate(thisTemplatesTree)
            }, {
                text:     '<i class="las la-check"></i>' + _('Set as Default'),
                id:       't-tmenu-default',
                disabled: true,
                hidden:   !is_administrator,
                handler:  thisTemplatesTree.setDefaultTemplate.createDelegate(thisTemplatesTree)
            }]
        });
    },

    getNodeId: function (node) {
        return (node && node.attributes) ? (node.isRoot ? 'root' : node.attributes.el_id) : false;
    },

    isFolder: function (node) {
        return (node && node.attributes) ? node.attributes.folder : false;
    },

    getNodeFileName: function (node) {
        return (node && node.attributes) ? node.attributes.filename : '';
    },

    getFocusedNode: function (tree) {
        return tree ? tree.getSelectionModel().getSelectedNode() : false;
    },

    getFocusedNodeId: function (tree) {
        return this.getNodeId(this.getFocusedNode(tree));
    },

    getFocusedFile: function (tree) {
        var node = this.getFocusedNode(tree);
        return (node && !this.isFolder(node)) ? node : false;
    },

    getFocusedFolderId: function (tree) {
        var node = this.getFocusedNode(tree);
        if (node) {
            if (this.isFolder(node)) {
                return this.getNodeId(node);
            } else {
                return this.getNodeId(node.parentNode);
            }
        }
        return false;
    },

    getFocusedFileId: function (tree) {
        var file = this.getFocusedFile(tree);
        return file ? this.getNodeId(file) : false;
    },

    getSelectedNodes: function (tree, booOnlyId) {
        var files = [];
        var checked = tree.getChecked();
        if (checked.length === 0) { //if no checked items get selected items
            var file = (booOnlyId ? this.getFocusedNodeId(tree) : this.getFocusedNode(tree));
            if (file) {
                files = [file];
            }
        } else {//else download all checked items
            for (var i = 0; i < checked.length; i++) {
                files.push(booOnlyId ? this.getNodeId(checked[i]) : checked[i]);
            }
        }

        return files;
    },

    getSelectedFiles: function (tree, booOnlyId) {
        var nodes = this.getSelectedNodes(tree);

        var arr = [];
        for (var i = 0; i < nodes.length; i++) {
            if (!this.isFolder(nodes[i])) {
                arr.push(booOnlyId ? this.getNodeId(nodes[i]) : nodes[i]);
            }
        }

        return arr;
    },

    onThisTreeDragAndDrop: function (tree, node, oldParent, newParent, index) {
        var thisTree = this;
        var index_set = 0;
        for (var i = 0; i < index; i++) {
            if (newParent.childNodes[i]['attributes']['order'] !== undefined) {
                index_set++;
            }
        }

        Ext.Ajax.request({
            url:     topBaseUrl + '/templates/index/drag-and-drop',
            params:  {
                file_id:   thisTree.getNodeId(node),
                folder_id: thisTree.getNodeId(newParent),
                order:     index_set
            },
            failure: function () {
                Ext.simpleConfirmation.error('Can\'t drop this Template');
            }
        });
    },

    onThisTreeContextMenu: function (node, e) {
        node.select();
        e.stopEvent();

        // show context menu
        if (this.isFolder(node)) {
            this.contextFoldersMenu.showAt(e.getXY());
        } else {
            this.contextTemplateMenu.showAt(e.getXY());
        }

        this.setAllowedItems(node, this.isFolder(node));
    },

    onThisTreeClick: function (node) {
        node.select();
        if (this.isFolder(node)) {
            node.expand();
        }

        this.setAllowedItems(node, this.isFolder(node));
    },

    onThisTreeDoubleClick: function (node) {
        if (this.isFolder(node)) {
            node.ui.toggleCheck(); //disable checking
        } else {
            this.templateEdit(this, true);
        }
    },

    setAllowedItems: function (node, is_folder) {
        var allowAdd = node.attributes.arrAccessRights.allowAdd;
        var allowAddFolder = node.attributes.arrAccessRights.allowAddFolder;
        var allowDeleteFolder = node.attributes.arrAccessRights.allowRenameDeleteFolder;
        var allowDelete = node.attributes.arrAccessRights.allowDelete;
        var allowDefault = node.attributes.arrAccessRights.allowDefault;

        //replace option label if user can only read template
        var text = (!allowAddFolder ? 'Preview Template' : 'Edit Template');
        Ext.getCmp('t-fmenu-edit').setText('<i class="las la-pen"></i>' + text);
        Ext.getCmp('t-tmenu-edit').setText('<i class="las la-pen"></i>' + text);

        Ext.getCmp('t-fmenu-add').setDisabled(!allowAdd);
        Ext.getCmp('t-fmenu-add-email').setDisabled(!allowAdd);
        Ext.getCmp('t-tmenu-add-email').setDisabled(!allowAdd);
        Ext.getCmp('t-fmenu-add-letter').setDisabled(!allowAdd);
        Ext.getCmp('t-tmenu-add-letter').setDisabled(!allowAdd);
        Ext.getCmp('t-tmenu-duplicate').setDisabled(!allowAdd);
        Ext.getCmp('t-fmenu-edit').setDisabled(is_folder);
        Ext.getCmp('t-tmenu-edit').setDisabled(is_folder);
        Ext.getCmp('t-fmenu-delete').setDisabled(is_folder || !allowDelete);
        Ext.getCmp('t-tmenu-delete').setDisabled(is_folder || !allowDelete);
        Ext.getCmp('t-fmenu-default').setDisabled(is_folder || !allowDefault);
        Ext.getCmp('t-tmenu-default').setDisabled(is_folder || !allowDefault);

        Ext.getCmp('t-tmenu-add-folder').setDisabled(!allowAddFolder);
        Ext.getCmp('t-tmenu-rename-folder').setDisabled(!allowDeleteFolder);
        Ext.getCmp('t-tmenu-delete-folder').setDisabled(!allowDeleteFolder);
    },

    letterTemplateAdd: function (options) {
        var thisTemplatesTree = this;

        var checkSelectedFile = function (field) {
            var value = field.getValue();
            var oFileDetails = extractFileName(value);
            // Check if this file is pdf
            var booDisabled = false;
            if (oFileDetails.ext.toLowerCase() !== 'docx') {
                booDisabled = true;
                field.reset();
                field.markInvalid('Only docx files are supported');
                Ext.simpleConfirmation.warning('Only docx files are supported');
            }

            // Enable upload button if pdf file was selected
            addButton.setDisabled(booDisabled);
        };

        var templateUpload = new Ext.form.FileUploadField({
            name:      'template-upload',
            hideLabel: true,
            width:     375,
            height:    50,
            scope:     this,
            listeners: {
                fileselected: function () {
                    checkSelectedFile(this);
                    radioUpload.setValue(true);
                }
            }
        });

        var radioUpload = new Ext.form.Radio({
            boxLabel:   'Upload your template (.docx)',
            width:      220,
            name:       'file-type',
            inputValue: 'upload',
            style:      'padding:5px;',
            checked:    !zoho_enabled && options.template_id
        });

        var radioCreate = new Ext.form.Radio({
            boxLabel:   'Create a blank template',
            width:      180,
            name:       'file-type',
            inputValue: 'blank',
            style:      'padding:5px;',
            hidden:     !zoho_enabled && options.template_id,
            checked:    !(!zoho_enabled && options.template_id)
        });

        var templateName = new Ext.form.TextField({
            fieldLabel: 'Template name',
            maxlength:  '32',
            width:      495,
            regex:      /^^[^ \\/:*?""<>|]+([ ]+[^ \\/:*?""<>|]+)*$$/i,
            hidden:     !zoho_enabled && options.template_id,
            regexText:  'Invalid template name'
        });

        var templatesFor = new Ext.form.ComboBox({
            fieldLabel:    'Template is for',
            store:         new Ext.data.SimpleStore({
                fields: ['type_id', 'type_name'],
                data:   [['General', 'General'], ['Invoice', 'Invoice'], ['Payment', 'Receipt of Payment'], ['Request', 'Request for Payment'], ['Password', 'User Id & Password update'], ['Prospect', 'Company Prospect'], ['Welcome', 'Welcome Message']]
            }),
            mode:          'local',
            displayField:  'type_name',
            valueField:    'type_id',
            triggerAction: 'all',
            selectOnFocus: true,
            editable:      false,
            width:         495,
            hidden:        !zoho_enabled && options.template_id,
            allowBlank:    !zoho_enabled && options.template_id
        });

        var addButton = new Ext.Button({
            text:    !zoho_enabled && options.template_id ? 'Upload' : 'Add',
            cls:     'orange-btn',
            handler: function () {
                var name = templateName.getValue();
                var templatesForVal = templatesFor.getValue();

                if (!zoho_enabled && options.template_id) {
                    var focusedNode = thisTemplatesTree.getFocusedNode(thisTemplatesTree);
                    name = thisTemplatesTree.getNodeFileName(focusedNode);
                    templatesForVal = focusedNode.attributes.templates_for;
                } else {
                    if (trim(name) === '') {
                        templateName.markInvalid('Template name cannot be empty');
                        return false;
                    }
                }

                var uploadFieldValue = templateUpload.getValue();
                if (radioUpload.getValue()) {
                    if (empty(uploadFieldValue)) {
                        templateUpload.markInvalid('Template upload field cannot be empty');
                        return false;
                    }
                }

                var obj = Ext.getCmp('add-template-form');
                if (obj.getForm().isValid()) {

                    Ext.MessageBox.show({
                        title:      radioUpload.getValue() ? 'Uploading...' : 'Creating...',
                        msg:        radioUpload.getValue() ? 'Uploading...' : 'Creating...',
                        width:      300,
                        wait:       true,
                        waitConfig: {interval: 200},
                        closable:   false,
                        icon:       'ext-mb-upload'
                    });

                    obj.getForm().submit({
                        url:     topBaseUrl + '/templates/index/save',
                        params:  {
                            act:                  !zoho_enabled && options.template_id ? 'edit' : options.action,
                            templates_name:       name,
                            templates_for:        templatesForVal,
                            templates_from:       '',
                            templates_cc:         '',
                            templates_bcc:        '',
                            templates_subject:    '',
                            templates_message:    '',
                            template_attachments: '',
                            template_id:          options.template_id,
                            folder_id:            options.folder_id,
                            templates_type:       'Letter'
                        },
                        success: function (f, o) {
                            Ext.MessageBox.hide();
                            var resultData = Ext.decode(o.response.responseText);
                            if (empty(resultData.error)) {
                                thisTemplatesTree.getRootNode().reload();

                                if (resultData.automatically_open) {
                                    win.close();

                                    var myDocsTabs = Ext.getCmp('clients-templates-tab');
                                    myDocsTabs.add({
                                        id:       'mydocs-templates-edit-sub-tab-' + resultData.template_id,
                                        title:    name,
                                        html:     '<div id="mydocs-templates-edit-' + resultData.template_id + '"></div>',
                                        closable: true
                                    }).show();

                                    //add form
                                    thisTemplatesTree.template({
                                        action:           'edit',
                                        folder_id:        options.folder_id,
                                        template_id:      resultData.template_id,
                                        booEmailTemplate: false
                                    });
                                } else {
                                    var confirmationTimeout = 750;

                                    win.getEl().mask('Done !');
                                    setTimeout(function () {
                                        win.close();
                                    }, confirmationTimeout);
                                }
                            } else {
                                Ext.simpleConfirmation.error(resultData.error);
                            }
                        },

                        failure: function (form, action) {
                            Ext.MessageBox.hide();

                            var msg = action && action.result && action.result.error ? action.result.error : 'Error happened during file(s) uploading. Please try again later.';

                            win.getEl().unmask();
                            Ext.simpleConfirmation.error(msg);
                        }
                    });
                }
            }
        });

        var win = new Ext.Window({
            title:      'New Letter Template',
            modal:      true,
            width:      620,
            autoHeight: true,
            resizable:  false,
            labelWidth: 220,
            items:      new Ext.FormPanel({
                id:         'add-template-form',
                layout:     'form',
                fileUpload: true,
                bodyStyle:  'padding:5px;',
                items:      [templateName, {
                    layout:       'table',
                    style:        'font-size: 12px; padding-bottom:8px;',
                    layoutConfig: {
                        columns: 2
                    },
                    items:        [{
                        xtype:   'container',
                        colspan: 2,
                        items:   [radioCreate]
                    }, radioUpload, templateUpload]
                }, templatesFor]
            }),

            buttons: [
                {
                    text:    'Cancel',
                    handler: function () {
                        win.close();
                    }
                },
                addButton
           ]
        });

        win.show();
        win.center();

        return true;
    },

    templateAdd: function (folder_id, booEmailTemplate, booToolbar) {
        //if no folder return false
        if (!folder_id) {
            return false;
        }

        var thisTemplatesTree = this;
        var mydocsTabs = Ext.getCmp('clients-templates-tab');
        var templatesAdd = booEmailTemplate ? Ext.getCmp('mydocs-templates-add-email-sub-tab') : Ext.getCmp('mydocs-templates-add-letter-sub-tab');
        if (!templatesAdd) {
            if (!booEmailTemplate) {
                var template_id = thisTemplatesTree.getFocusedFileId(thisTemplatesTree);
                thisTemplatesTree.letterTemplateAdd({
                    action:      'add',
                    folder_id:   folder_id,
                    template_id: booToolbar ? false : template_id
                });
            } else {
                //add templates sub tab
                mydocsTabs.add({
                    id:         'mydocs-templates-add-email-sub-tab',
                    title:      'New Email Template',
                    html:       '<div id="mydocs-templates-add-email"></div>',
                    autoHeight: true,
                    closable:   true
                }).show();

                //add form
                thisTemplatesTree.template({
                    action:           'add',
                    folder_id:        folder_id,
                    booEmailTemplate: true
                });
            }
        } else {
            templatesAdd.show();
        }

        return true;
    },

    templateEdit: function (tree, booDoubleClick, options) {
        var thisTemplatesTree = this;

        options = options ? options : {
            template_id: thisTemplatesTree.getFocusedFileId(thisTemplatesTree),
            folder_id:   thisTemplatesTree.getFocusedFolderId(thisTemplatesTree),
            folder_name: thisTemplatesTree.getNodeFileName(thisTemplatesTree.getFocusedNode(thisTemplatesTree))
        };

        if (!options.folder_name || !options.folder_id || !options.template_id) {
            return false;
        }

        var selectedNode = thisTemplatesTree.getFocusedNode(thisTemplatesTree);
        var booEmailTemplate = selectedNode.attributes.templates_type == 'Email';

        if (!booEmailTemplate && !zoho_enabled) {
            if (booDoubleClick) {
                Ext.getBody().mask(_('Loading...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/templates/index/get-letter-template-file',

                    params: {
                        template_id: options.template_id,
                        download: 1
                    },

                    success: function (form) {
                        var resultData = Ext.decode(form.responseText);

                        if (resultData.success) {
                            window.open(resultData.file_path);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                    }
                });
            } else {
                thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), false);
            }
        } else {
            var mydocsTabs = Ext.getCmp('clients-templates-tab');
            var templatesEdit = Ext.getCmp('mydocs-templates-edit-sub-tab-' + options.template_id);
            if (!templatesEdit) {
                //add templates sub tab
                mydocsTabs.add({
                    id:         'mydocs-templates-edit-sub-tab-' + options.template_id,
                    title:      options.folder_name,
                    html:       '<div id="mydocs-templates-edit-' + options.template_id + '"></div>',
                    closable:   true,
                    autoHeight: true,
                    listeners:  {
                        activate: function () {
                            thisTemplatesTree.fixParentTemplatesTabHeight(options.booEmailTemplate);
                        }
                    }
                }).show();

                //add form
                thisTemplatesTree.template({
                    action:           'edit',
                    folder_id:        options.folder_id,
                    template_id:      options.template_id,
                    booEmailTemplate: booEmailTemplate
                });
            } else {
                templatesEdit.show();
            }
        }

        return true;
    },

    insertField: function (id) {
        var selected = Ext.getCmp('templates-field' + id).getSelectionModel().getSelected();
        if (selected) {
            var row = Ext.getCmp('templates-field' + id).getSelectionModel().getSelected().data;

            var text = '<%' + row.name + '%>';
            text = text.trim();
            Ext.getCmp('templates-message' + id)._froalaEditor.html.insert(text);
        }
    },

    copyFieldToClipboard: function (selected) {
        if (selected) {
            var row = selected.data;

            var text = '<%' + row.name + '%>';
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
            Ext.simpleConfirmation.msg('Info', 'Field ID copied to clipboard');
        } else {
            Ext.simpleConfirmation.msg('Info', 'Please select a field to copy.');
        }
    },

    fixParentTemplatesTabHeight: function (booEmailTemplate) {
        // var myDocsHeight = 0;
        // if (initPanelSize() < 613) {
        //     myDocsHeight = 637;
        // } else {
        //     myDocsHeight = initPanelSize();
        // }
        // // if (booEmailTemplate) {
        // //     myDocsHeight += 75;
        // // } else {
        // //     myDocsHeight -= 15;
        // // }
        //
        // $('#clients-templates-tab').height(myDocsHeight);
        //
        // var subTab = Ext.getCmp('mydocs-templates-add-email-sub-tab');
        // if (subTab) {
        //     subTab.setHeight(myDocsHeight);
        // }
    },

    template: function (options) {
        var thisTemplatesTree = this;

        var template_file_attachments = [];

        var id = (options.booEmailTemplate ? '-email' : '-letter') + (options.action == 'add' ? '' : '-' + options.template_id);

        var name = new Ext.form.TextField({
            id:         'templates-name' + id,
            name:       'templates-name',
            fieldLabel: 'Template Name',
            allowBlank: false,
            anchor:     '100%'
        });

        var templates_for = new Ext.form.ComboBox({
            id:            'templates-for' + id,
            fieldLabel:    'Template is for',
            store:         new Ext.data.SimpleStore({
                fields: ['type_id', 'type_name'],
                data:   [['General', 'General'], ['Invoice', 'Invoice'], ['Payment', 'Receipt of Payment'], ['Request', 'Request for Payment'], ['Password', 'User Id & Password update'], ['Prospect', 'Company Prospect'], ['Welcome', 'Welcome Message']]
            }),
            mode:          'local',
            displayField:  'type_name',
            valueField:    'type_id',
            hiddenName:    'template_for',
            triggerAction: 'all',
            selectOnFocus: true,
            editable:      false,
            anchor:        '100%',
            allowBlank:    false
        });

        var from = new Ext.form.TextField({
            id:         'templates-from' + id,
            name:       'templates-from',
            fieldLabel: 'From Email Address',
            anchor:     '100%',
            listeners:  {
                'valid': validateEmailField
            }
        });

        var cc = new Ext.form.TextField({
            id:         'templates-cc' + id,
            name:       'templates-cc',
            fieldLabel: 'CC',
            vtype:      'multiemailSpecial',
            anchor:     '100%'
        });

        var bcc = new Ext.form.TextField({
            id:         'templates-bcc' + id,
            name:       'templates-bcc',
            fieldLabel: 'BCC',
            vtype:      'multiemailSpecial',
            anchor:     '100%'
        });

        var subject = new Ext.form.TextField({
            id:         'templates-subject' + id,
            name:       'templates-subject',
            fieldLabel: 'Subject',
            anchor:     '100%'
        });

        var attachmentsStore = new Ext.data.Store({
            url:        topBaseUrl + '/templates/index/get-templates-list',
            autoLoad:   false, // Note: we'll load it only when it is needed
            baseParams: {
                withoutOther:   true,
                template_for:   '',
                templates_type: Ext.encode('Letter'),
                template_id:    options.template_id,
                only_shared:    1
            },
            reader:     new Ext.data.JsonReader({
                root:          'rows',
                totalProperty: 'totalCount',
                id:            'templateId'
            }, [{name: 'templateId'}, {name: 'templateName'}])
        });

        var attachments = new Ext.ux.form.LovCombo({
            fieldLabel:     'Attachments',
            maxHeight:      200,
            anchor:         '95%',
            store:          attachmentsStore,
            triggerAction:  'all',
            valueField:     'templateId',
            displayField:   'templateName',
            mode:           'local',
            searchContains: true,
            useSelectAll:   false,
            allowBlank:     true
        });

        var sendAsPdf = new Ext.form.Checkbox({
            fieldLabel: 'Send as PDF'
        });

        var strDefaultFilterValue = 'individual_0';
        var fieldsStore = new Ext.data.GroupingStore({
            url:        topBaseUrl + '/templates/index/get-fields',
            baseParams: {
                filter_by: strDefaultFilterValue
            },
            reader:     new Ext.data.JsonReader({
                root:          'rows',
                totalProperty: 'totalCount'
            }, Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
            autoLoad:   false,
            sortInfo:   {
                field:     'label',
                direction: 'ASC'
            }, //order in-groups fields
            groupField: 'n'
        });

        var fieldsStore2 = new Ext.data.GroupingStore({
            url:        topBaseUrl + '/templates/index/get-fields',
            baseParams: {
                filter_by: strDefaultFilterValue
            },
            reader:     new Ext.data.JsonReader({
                root:          'rows',
                totalProperty: 'totalCount'
            }, Ext.data.Record.create([{name: 'n'}, {name: 'group'}, {name: 'name'}, {name: 'label'}])),
            autoLoad:   false,
            sortInfo:   {
                field:     'label',
                direction: 'ASC'
            }, //order in-groups fields
            groupField: 'n'
        });

        var filterFieldsStore = new Ext.data.Store({
            url:      topBaseUrl + '/templates/index/get-fields-filter',
            autoLoad: true,
            reader:   new Ext.data.JsonReader({
                root: 'rows',
                id:   'filter_id'
            }, [{name: 'filter_id'},         // e.g. 'case_123'
                {name: 'filter_group_id'},   // e.g. 'case'
                {name: 'filter_group_name'}, // e.g. 'Case Profile'
                {name: 'filter_type_name'},  // e.g. 'Advice'
                {name: 'filter_type_id'}     // e.g. 123
            ])
        });

        // Set default value for the 'filter' combo
        filterFieldsStore.on('load', function (store) {
            var index = store.find(filterFieldsCombo.valueField, strDefaultFilterValue), record = store.getAt(index);

            // Apply combo value with delay - cause all things must be rendered
            setTimeout(function () {
                filterFieldsCombo.setValue(strDefaultFilterValue);
                filterFieldsCombo.fireEvent('beforeselect', filterFieldsCombo, record, strDefaultFilterValue);
            }, 100);
        }, this, {single: true});


        var filterFieldsCombo = new Ext.form.ComboBox({
            store:          filterFieldsStore,
            mode:           'local',
            valueField:     'filter_id',
            displayField:   'filter_type_name',
            triggerAction:  'all',
            forceSelection: true,
            fieldLabel:     'Filter by',
            labelStyle:     'padding-top: 10px; padding-left: 5px; width: 50px; color: #2F6BBA',
            emptyText:      'Please select...',
            readOnly:       true,
            typeAhead:      true,
            selectOnFocus:  true,
            editable:       false,
            width:          345,
            listWidth:      345,

            tpl: new Ext.XTemplate('<tpl for=".">' +
                '<tpl if="(this.filter_group_name != values.filter_group_name && !empty(values.filter_group_name))">' +
                    '<tpl exec="this.filter_group_name = values.filter_group_name"></tpl>' +
                    '<h1 style="padding: 2px;">{filter_group_name}</h1>' +
                '</tpl>' +

                '<tpl if="(empty(values.filter_group_name))">' + '<div class="x-combo-list-item" style="font-weight: bold">{filter_type_name}</div>' + '</tpl>' +

                '<tpl if="(!empty(values.filter_group_name))">' + '<div class="x-combo-list-item" style="padding-left: 20px;">{filter_type_name}</div>' + '</tpl>' + '</tpl>')
        });

        // Apply selected 'filter' and reload fields list
        filterFieldsCombo.on('beforeselect', function (combo, rec) {
            fieldsStore.load({
                params: {
                    filter_by: Ext.encode(rec.data)
                }
            });
        });

        var filterFieldsStore2 = new Ext.data.Store({
            url:      topBaseUrl + '/templates/index/get-fields-filter',
            autoLoad: true,
            reader:   new Ext.data.JsonReader({
                root: 'rows',
                id:   'filter_id'
            }, [{name: 'filter_id'},         // e.g. 'case_123'
                {name: 'filter_group_id'},   // e.g. 'case'
                {name: 'filter_group_name'}, // e.g. 'Case Profile'
                {name: 'filter_type_name'},  // e.g. 'Advice'
                {name: 'filter_type_id'}     // e.g. 123
            ])
        });

        // Set default value for the 'filter' combo
        filterFieldsStore2.on('load', function (store) {
            var index = store.find(filterFieldsCombo2.valueField, strDefaultFilterValue), record = store.getAt(index);

            // Apply combo value with delay - cause all things must be rendered
            setTimeout(function () {
                filterFieldsCombo2.setValue(strDefaultFilterValue);
                filterFieldsCombo2.fireEvent('beforeselect', filterFieldsCombo2, record, strDefaultFilterValue);
            }, 100);
        }, this, {single: true});


        var filterFieldsCombo2 = new Ext.form.ComboBox({
            store:          filterFieldsStore2,
            mode:           'local',
            valueField:     'filter_id',
            displayField:   'filter_type_name',
            triggerAction:  'all',
            forceSelection: true,
            fieldLabel:     'Filter by',
            labelStyle:     'padding-top: 10px; padding-left: 5px; width: 50px; color: #2F6BBA',
            emptyText:      'Please select...',
            readOnly:       true,
            typeAhead:      true,
            selectOnFocus:  true,
            editable:       false,
            width:          375,
            listWidth:      375,

            tpl: new Ext.XTemplate('<tpl for=".">' +
                '<tpl if="(this.filter_group_name != values.filter_group_name && !empty(values.filter_group_name))">' +
                    '<tpl exec="this.filter_group_name = values.filter_group_name"></tpl>' +
                    '<h1 style="padding: 2px;">{filter_group_name}</h1>' +
                '</tpl>' +

                '<tpl if="(empty(values.filter_group_name))">' + '<div class="x-combo-list-item" style="font-weight: bold">{filter_type_name}</div>' + '</tpl>' +

                '<tpl if="(!empty(values.filter_group_name))">' + '<div class="x-combo-list-item" style="padding-left: 20px;">{filter_type_name}</div>' + '</tpl>' + '</tpl>')
        });

        // Apply selected 'filter' and reload fields list
        filterFieldsCombo2.on('beforeselect', function (combo, rec) {
            fieldsStore2.load({
                params: {
                    filter_by: Ext.encode(rec.data)
                }
            });
        });

        var bottomBar = [];
        var copyFieldIdButton;
        if (options.booEmailTemplate) {
            bottomBar = [{
                xtype:   'button',
                text: '&nbsp;&nbsp;&nbsp;&nbsp;' + '<i class="las la-angle-double-left" style="padding-right: 0"></i>' + '&nbsp;&nbsp;&nbsp;&nbsp;',
                cls:     'blue-btn',
                style:   'margin-bottom:4px; margin-top:4px;',
                handler: function () {
                    thisTemplatesTree.insertField(id);
                }
            }, 'Select field above, then click button.'];
        } else {
            copyFieldIdButton = new Ext.Button({
                text:     '<i class="las la-copy"></i>' + _('Copy Field ID'),
                disabled: true,
                cls:      'blue-btn',
                style:    'margin-bottom:4px; margin-top:4px;',
                handler:  function () {
                    thisTemplatesTree.copyFieldToClipboard(fieldsGridPanel.getSelectionModel().getSelected());
                }
            });

            bottomBar = [copyFieldIdButton];
        }

        var copyFieldIdButton2 = new Ext.Button({
            text:     '<i class="las la-copy"></i>' + _('Copy Field ID'),
            disabled: true,
            cls:      'blue-btn',
            style:    'margin-bottom:4px; margin-top:4px;',
            handler:  function () {
                thisTemplatesTree.copyFieldToClipboard(fieldsGridPanel2.getSelectionModel().getSelected());
            }
        });


        // create the grid
        var fieldsGridPanel = new Ext.grid.GridPanel({
            id:         'templates-field' + id,
            title:      'Fields',
            split:      true,
            width:      440,
            store:      fieldsStore,
            cm:         new Ext.grid.ColumnModel([{
                dataIndex: 'n',
                hidden:    true
            }, {
                dataIndex: 'group',
                hidden:    true
            }, {
                id:        'name',
                header:    'Field',
                dataIndex: 'name',
                width:     200,
                renderer:  function (val) {
                    return '&lt;%' + val + '%&gt;';
                }
            }, {
                header:    'Label',
                dataIndex: 'label',
                width:     200
            }]),

            view: new Ext.grid.GroupingView({
                deferEmptyText: _('No records found.'),
                emptyText: _('No records found.'),
                forceFit: true,
                groupTextTpl: '{[values.rs[0].data["group"]]} ({[values.rs.length]} {[values.rs.length > 1 ? "Fields" : "Field"]})'
            }),

            sm:         new Ext.grid.RowSelectionModel({singleSelect: true}),
            height:     300,
            stripeRows: true,
            hideHeaders: true,
            cls:        'extjs-grid right-panel',
            style:      'padding: 5px',
            loadMask:   true,

            tbar: {
                xtype: 'panel',
                layout: 'form',
                labelWidth: 50,
                items: [{
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        style: 'font-size: smaller; padding: 5px 5px 10px 5px',
                        html: options.booEmailTemplate ? _('Double click on the field you want to insert into the CONTENT box.<div style="font-style: italic">NOTE: The field will be inserted where your cursor is placed.</div>') : _('Copy and paste any of the following fields to the CONTENT section of your template to automatically populate your template with the client\'s information.')
                    },
                }, filterFieldsCombo]
            },

            bbar: bottomBar
        });

        var fieldsGridPanel2 = new Ext.grid.GridPanel({
            title:      'Fields',
            style:      'padding: 0 5px 5px 5px',
            split:      true,
            width:      440,
            store:      fieldsStore2,
            cm:         new Ext.grid.ColumnModel([{
                dataIndex: 'n',
                hidden:    true
            }, {
                dataIndex: 'group',
                hidden:    true
            }, {
                id:        'name',
                header:    'Field',
                dataIndex: 'name',
                width:     200,
                renderer:  function (val) {
                    return '&lt;%' + val + '%&gt;';
                }
            }, {
                header:    'Label',
                dataIndex: 'label',
                width:     200
            }]),

            view: new Ext.grid.GroupingView({
                deferEmptyText: _('No records found.'),
                emptyText: _('No records found.'),
                forceFit: true,
                groupTextTpl: '{[values.rs[0].data["group"]]} ({[values.rs.length]} {[values.rs.length > 1 ? "Fields" : "Field"]})'
            }),

            sm:         new Ext.grid.RowSelectionModel({singleSelect: true}),
            height:     initPanelSize() - 70,
            stripeRows: true,
            hideHeaders: true,
            cls:        'extjs-grid',
            loadMask:   true,

            tbar: {
                xtype:      'panel',
                layout:     'form',
                labelWidth: 50,
                items: [{
                    xtype: 'box',
                    autoEl: {
                        tag:     'div',
                        style: 'font-size: smaller; padding: 5px 5px 10px 5px',
                        html: _('To automatically populate the Template, select a field below and click the "Copy" button. Then, paste it into the desired box in the Template.')
                    },
                }, filterFieldsCombo2]
            },

            bbar: [copyFieldIdButton2]
        });

        if (options.booEmailTemplate) {
            fieldsGridPanel.on('rowdblclick', function () {
                thisTemplatesTree.insertField(id);
            });
        } else {
            fieldsGridPanel.getSelectionModel().on('selectionchange', function(sm){
                copyFieldIdButton.setDisabled(sm.getCount() < 1);
            });
        }

        fieldsGridPanel2.getSelectionModel().on('selectionchange', function(sm){
            copyFieldIdButton2.setDisabled(sm.getCount() < 1);
        });

        // more space because of the Tab header + Toolbar
        var availableHeight = initPanelSize() - 70;
        if (options.booEmailTemplate) {
            availableHeight -= 15;
        }

        var repairHeight = availableHeight;
        var editorHeight = availableHeight;

        var loadTabSettings = function () {
            var defaultSettings = {
                width:     440,
                collapsed: 0
            };

            var savedSettings = Ext.state.Manager.get('templates_tab_settings');

            return empty(savedSettings) ? defaultSettings : savedSettings;
        };

        var saveTabSettings = function (panelWidth, booCollapsed) {
            Ext.state.Manager.set('templates_tab_settings', {
                width:     panelWidth,
                collapsed: booCollapsed
            });
        };

        var oSavedSettings = loadTabSettings();
        var rightPanelWidth = oSavedSettings.width;
        var booIsCollapsed = oSavedSettings.collapsed;
        var leftPanelWidth = Ext.getBody().getViewSize().width - rightPanelWidth - 105;
        if (booIsCollapsed) {
            leftPanelWidth = Ext.getBody().getViewSize().width - 105;
        }

        var editor;
        var editorId = 'templates-message' + id;
        if (options.booEmailTemplate) {
            editor = new Ext.ux.form.FroalaEditor({
                id: editorId,
                width: leftPanelWidth,
                height: editorHeight,
                heightDifference: 61,
                hideLabel: true,
                allowBlank: false,
                value: '',
                booAllowImagesUploading: true
            });
        } else {
            editor = new Ext.Panel({
                cls: 'no-padding-body',
                html: '<iframe id="' + editorId + '" name="' + editorId + '" style="border:none;" width="' + leftPanelWidth + 'px" height="' + editorHeight + 'px" src="" scrolling="auto"></iframe>'
            });

            editor.on('resize', function (panel, width) {
                $('#' + editorId).width(width);
            });
        }

        var template_fs = new Ext.form.FieldSet({
            title:      'Template Info (required)',
            layout:     'fit',
            titleCollapse: false,
            collapsible:   true,
            cls:           'applicants-profile-fieldset',
            labelWidth: 120,
            items:      {
                layout: 'form',
                items:  [name, templates_for]
            }
        });

        var templates_fields_fs = new Ext.form.FieldSet({
            title: 'Template Fields',
            titleCollapse: false,
            collapsible: true,
            hidden: !options.booEmailTemplate,
            cls: 'applicants-profile-fieldset',
            labelWidth: 120,
            items: [{
                layout: 'form',
                items: [from, cc, bcc, subject]
            }]
        });

        var template_letter_attachments_fs = new Ext.form.FieldSet({
            title:         'Attachments from Letter Templates',
            layout:        'fit',
            autoHeight:    true,
            titleCollapse: false,
            collapsible:   true,
            hidden:        !options.booEmailTemplate,
            cls:           'applicants-profile-fieldset',
            labelWidth:    120,

            items: {
                layout: 'column',
                items:  [{
                    columnWidth: 0.5,
                    layout:      'form',
                    items:       attachments
                }, {
                    columnWidth: 0.5,
                    layout:      'form',
                    items:       sendAsPdf
                }]
            }
        });

        var addAttachmentToThePanel = function (oFileInfo) {
            template_file_attachments.push(oFileInfo);

            var newEl = new Ext.Container({
                style: 'display: inline; padding-left: 7px;',

                items: [{
                    xtype: 'box',
                    autoEl: {
                        tag: 'a',
                        href: '#',
                        'class': 'bluelink',
                        html: oFileInfo.name
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                submit_hidden_form(
                                    topBaseUrl + '/templates/index/download-attach',
                                    {
                                        template_id: options.template_id,
                                        type: oFileInfo.type,
                                        attach_id: oFileInfo.file_id,
                                        name: oFileInfo.name
                                    }
                                );
                            }, this, {stopEvent: true});
                        }
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag: 'span',
                        html: ' (' + oFileInfo.file_size + ') ',
                        style: 'font-size: 11px;'
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag: 'img',
                        src: topBaseUrl + '/images/deleteicon.gif',
                        'class': 'template-attachment-cancel'
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                for (var i = 0; i < template_file_attachments.length; i++) {
                                    if (template_file_attachments[i]['attach_id'] == oFileInfo.attach_id) {
                                        template_file_attachments.splice(i, 1);
                                        break;
                                    }
                                }

                                attachmentsPanel.remove(newEl);
                                attachmentsPanel.doLayout();
                            }, this, {stopEvent: true});
                        }
                    }
                }]
            });

            attachmentsPanel.add(newEl);
            attachmentsPanel.doLayout();
        };

        var attachFiles = new Ext.Button({
            text:    'Attach Files',
            handler: function () {
                var dialog = new UploadAttachmentsDialog({
                    settings: {
                        template_id: options.template_id,
                        act:         options.action,

                        onFileUpload: function (oFileInfo) {
                            addAttachmentToThePanel({
                                type: 'uploaded',
                                attach_id: oFileInfo.tmp_name,
                                file_id: oFileInfo.tmp_name,
                                tmp_name: oFileInfo.tmp_name,
                                name: oFileInfo.name,
                                size: oFileInfo.size,
                                file_size: oFileInfo.file_size,
                                extension: oFileInfo.extension
                            });
                        }
                    }
                });

                dialog.show();
            }
        });

        var attachmentsPanel = new Ext.Panel();

        var template_pc_attachments_fs = new Ext.form.FieldSet({
            title:         'Attach Files',
            layout:        'fit',
            autoHeight:    true,
            titleCollapse: false,
            collapsible:   true,
            hidden:        !options.booEmailTemplate,
            cls:           'applicants-profile-fieldset',
            labelWidth:    120,

            items: {
                layout: 'column',
                items:  [{
                    columnWidth: 0.2,
                    layout:      'form',
                    items:       attachFiles
                }, {
                    columnWidth: 0.8,
                    layout:      'form',
                    items:       attachmentsPanel
                }]
            }
        });

        var editorPanel = new Ext.Panel({
            xtype:    'panel',
            layout:   'fit',
            items:    editor,
            region:   'center',
            split:    true,
            stateful: false,
            style:    'padding: 0',
            cls:      'no-padding-body',
            width:    leftPanelWidth
        });

        var fieldsPanel = new Ext.Panel({
            width:             rightPanelWidth,
            height:            repairHeight,
            items:             fieldsGridPanel,
            cls:               'no-padding-body',
            style:             'padding-left: 10px; background-color: #FFF',
            layout:            'fit',
            region:            'east',
            split:             true,
            collapsed:         booIsCollapsed,
            collapseMode:      'mini',
            collapseDirection: 'right',
            minSize:           320,
            stateful:          false,

            listeners: {
                resize: function (panel, width) {
                    if (!empty(width)) {
                        saveTabSettings(width, panel.collapsed);
                        editor.setWidth(fields_fs.getWidth() - width - 15);
                    }
                },

                beforecollapse: function (panel) {
                    saveTabSettings(fieldsPanel.getWidth(), 1);
                },

                collapse: function () {
                    editor.setWidth(fields_fs.getWidth() - 15);

                    // There is a bug when collapse fields section
                    // We need to set the height again
                    editor.setHeight(fields_fs.getHeight());
                },

                expand: function (panel) {
                    saveTabSettings(fieldsPanel.getWidth(), 0);

                    // If we expand this section that was rendered as collapsed -
                    // We need to sync size, so the grid will be visible
                    fieldsGridPanel.syncSize();
                }
            }
        });

        // //Height amendments if screen resolution is low
        // var fieldsPanelHeight = repairHeight;
        // if (initPanelSize() < 613) {
        //     fieldsPanelHeight = 315;
        // }
        // if (initPanelSize() < 613 && !options.booEmailTemplate) {
        //     fieldsPanelHeight = 570;
        // }
        var fields_fs = new Ext.form.FieldSet({
            layout:        'border',
            title:         '',
            height:        repairHeight,
            titleCollapse: false,
            collapsible:   false,
            cls:           'applicants-profile-fieldset',
            autoWidth:     true,
            frame:         true,
            split:         true,
            stateful:      false,
            style:         'padding: 0; margin-bottom: 0',

            items:     [editorPanel, fieldsPanel]
        });

        var saveTemplateChanges = function () {
            var templateName = name.getValue();
            var templateMessage = '';
            var templateFor = templates_for.getValue();
            var templateFrom = options.booEmailTemplate ? from.getValue() : '';
            var templateCc = options.booEmailTemplate ? cc.getValue() : '';
            var templateBcc = options.booEmailTemplate ? bcc.getValue() : '';
            var templateSubject = options.booEmailTemplate ? subject.getValue() : '';
            var templateAttachments = options.booEmailTemplate ? attachments.getValue() : [];
            var templateAttachmentsSendAsPdf = options.booEmailTemplate ? sendAsPdf.getValue() : 0;

            // from, cc, bcc, subject

            var booError = false;
            var errMsg = '';
            if (trim(templateName) === '') {
                name.markInvalid('Template name cannot be empty');
                errMsg += 'Template name cannot be empty. ';
                booError = true;
            }

            if (options.booEmailTemplate && from.getEl().hasClass(from.invalidClass)) {
                booError = true;
                var invalidMsg = 'Please fill out the required fields in the Settings tab. ';
                errMsg += errMsg === '' ? invalidMsg : "<br>" + invalidMsg;
            }

            if (empty(templateFor)) {
                var templatesForMsg = '"Template is for" cannot be empty. ';
                templates_for.markInvalid(templatesForMsg);
                errMsg += errMsg === '' ? templatesForMsg : "<br>" + templatesForMsg;
                booError = true;
            }

            if (booError) {
                Ext.simpleConfirmation.warning(errMsg);
                return false;
            }

            if (options.booEmailTemplate) {
                templateMessage = editor.getValue();
            }

            if (!empty(options.folder_id)) {
                Ext.getBody().mask('Saving...');
                Ext.Ajax.request({
                    url:     topBaseUrl + '/templates/index/save',
                    params:  {
                        act:                        options.action,
                        template_id:                options.template_id,
                        folder_id:                  options.folder_id,
                        templates_name:             templateName,
                        templates_for:              templateFor,
                        templates_from:             templateFrom,
                        templates_cc:               templateCc,
                        templates_bcc:              templateBcc,
                        templates_subject:          templateSubject,
                        templates_message:          templateMessage,
                        templates_attachments:      templateAttachments,
                        templates_send_as_pdf:      templateAttachmentsSendAsPdf ? 1 : 0,
                        templates_file_attachments: Ext.encode(template_file_attachments),
                        templates_type:             options.booEmailTemplate ? 'Email' : 'Letter'
                    },

                    success: function (f) {
                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            thisTemplatesTree.root.reload();

                            if (options.action == 'add') {
                                Ext.simpleConfirmation.msg('Info', 'New Template was successfully added');
                                if (options.booEmailTemplate) {
                                    Ext.getCmp('clients-templates-tab').remove('mydocs-templates-add-email-sub-tab');
                                } else {
                                    Ext.getCmp('clients-templates-tab').remove('mydocs-templates-add-letter-sub-tab');
                                }

                                thisTemplatesTree.templateEdit(true, result.template_id);
                            } else {
                                template_file_attachments = [];
                                attachmentsPanel.removeAll();

                                Ext.each(result.file_attachments, function (v) {
                                    addAttachmentToThePanel({
                                        type: 'template_file_attachment',
                                        attach_id: v['id'],
                                        file_id: v['file_id'],
                                        name: v['name'],
                                        size: v['size'],
                                        file_size: v['size']
                                    });
                                });

                                Ext.simpleConfirmation.msg('Info', 'Template was successfully saved');
                            }
                        } else {
                            Ext.simpleConfirmation.error('Can\'t save Template');
                        }
                        Ext.getBody().unmask();
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error('Can\'t receive information');
                        Ext.getBody().unmask();
                    }
                });
            } else {
                Ext.simpleConfirmation.error('Please select Template Folder!');
            }
        };

        var saveTemplateBtn = new Ext.Button({
            text:    '<i class="lar la-save"></i>' + _('Save'),
            cls:     'orange-btn',
            style:   'margin-bottom: 10px;',

            handler: saveTemplateChanges
        });

        var saveTemplateBtn2 = new Ext.Button({
            text:    '<i class="lar la-save"></i>' + _('Save'),
            cls:     'orange-btn',
            style:   'margin-bottom: 10px;',
            hidden:  !options.booEmailTemplate,

            handler: saveTemplateChanges
        });

        var pan = new Ext.ux.VerticalTabPanel({
            hideMode:        'visibility',
            autoWidth:       true,
            autoHeight:      true,
            activeTab:       1,
            enableTabScroll: true,
            plain:           true,
            deferredRender:  false,
            cls:             'clients-sub-tab-panel',

            headerCfg: {
                cls: 'clients-sub-tab-header x-tab-panel-header x-vr-tab-panel-header'
            },

            defaults: {
                autoHeight: true,
                autoScroll: true
            },

            items: [
                {
                    title: '<i class="las la-arrow-left"></i>' + _('Back to Templates'),
                    tabIdentity: 'back_to_templates'
                }, {
                    title:   _('1. Define Settings'),
                    itemCls: 'templates-sub-tab-items',
                    cls:     'templates-sub-tab',
                    style:   'padding: 10px 10px 0 10px',
                    xtype: 'container',
                    layout: 'column',

                    items:   [
                        {
                            xtype:      'form',
                            width:      Ext.getCmp('clients-templates-tab').getWidth() - 460 - 275, // 275 - tabWidth
                            labelAlign: 'top',
                            items: [saveTemplateBtn, template_fs, templates_fields_fs, template_letter_attachments_fs, template_pc_attachments_fs]
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
                                            width: 160,
                                            handler: function () {
                                                pan.setActiveTab(2);
                                            }
                                        }, {
                                            xtype:   'button',
                                            style: 'margin-right: 10px; margin-top: 5px; float: right',
                                            text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                                            hidden:  !allowedPages.has('help'),
                                            handler: function () {
                                                showHelpContextMenu(this.getEl(), 'client-template-details');
                                            }
                                        }
                                    ]
                                }, {
                                    xtype: 'container',
                                    cls:   'right-panel',
                                    style: 'clear: both;',
                                    items: fieldsGridPanel2
                                }
                            ]
                        }
                    ]
                }, {
                    xtype:   'form',
                    title:   _('2. Define Content'),
                    style:   'padding: 4px',

                    items:   [
                        {
                            xtype: 'container',
                            layout: 'column',
                            items: [
                                saveTemplateBtn2,
                                {
                                    xtype:  'box',
                                    hidden:  options.booEmailTemplate,
                                    autoEl: {
                                        tag:  'div',
                                        style: 'padding-top: 10px; color: orange; font-size: 13px;',
                                        html: '<i class="las la-exclamation-triangle"></i> ' + _('Changes to letter template content must be saved before closing this tab.')

                                    }
                                }, {
                                    xtype: 'button',
                                    cls: 'main-btn',
                                    style: 'margin-bottom: 10px; margin-right: 20px; float: right',
                                    text: '<i class="lar la-arrow-alt-circle-left"></i>' + _('Back'),
                                    width: 160,
                                    handler: function () {
                                        pan.setActiveTab(1);
                                    }
                                }, {
                                    xtype:   'button',
                                    style: 'margin-right: 10px; margin-top: 5px; float: right',
                                    text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                                    hidden:  !allowedPages.has('help'),
                                    handler: function () {
                                        showHelpContextMenu(this.getEl(), 'client-template-details');
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
                            if (!this.isStoreLoaded) {
                                fieldsGridPanel.getStore().reload();
                                this.isStoreLoaded = true;
                            }
                        }
                    }
                }
            ],

            listeners: {
                'beforetabchange': function (oTabPanel, newTab) {
                    if (newTab.tabIdentity === 'back_to_templates') {
                        var oTabPanel = Ext.getCmp('clients-templates-tab');
                        var templatesTab = oTabPanel.getItem('mydocs-templates-sub-tab');

                        if (oTabPanel && templatesTab) {
                            // Switch to the queue tab
                            oTabPanel.setActiveTab(templatesTab);
                        }

                        return false;
                    }
                }
            }
        });


        if (options.action == 'add') {
            if (options.booEmailTemplate) {
                pan.render('mydocs-templates-add-email');
            } else {
                pan.render('mydocs-templates-add-letter');
            }
        } else {
            pan.render('mydocs-templates-edit-' + options.template_id);
        }

        // Resize html editor + fields section
        //editorPanel.setHeight(initPanelSize() - 265  - (Ext.isIE ? 20 : 0) + (!options.booEmailTemplate ? 240 : 0));
        // fieldsGridPanel.setHeight(initPanelSize() - 265 + (!options.booEmailTemplate ? 240 : 0) - (Ext.isIE ? 20 : 0));

        setTimeout(function () {
            thisTemplatesTree.fixParentTemplatesTabHeight(options.booEmailTemplate);
        }, 200)

        if (!options.booEmailTemplate) {
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url: topBaseUrl + '/templates/index/get-letter-template-file',

                params: {
                    template_id: options.template_id,
                    download: 0
                },

                success: function (form) {
                    var resultData = Ext.decode(form.responseText);

                    if (resultData.success) {
                        templates_for.setValue(resultData.template.templates_for);
                        name.setValue(resultData.template.name);
                        subject.setValue(resultData.template.subject);
                        from.setValue(resultData.template.from);
                        cc.setValue(resultData.template.cc);
                        bcc.setValue(resultData.template.bcc);

                        $('#templates-message' + id).attr('src', resultData.file_path);
                    } else {
                        Ext.simpleConfirmation.error(resultData.message);
                    }

                    Ext.getBody().unmask();
                },

                failure: function () {
                    Ext.getBody().unmask();
                }
            });
        } else {
            //edit action
            if (options.action == 'edit') {
                Ext.getBody().mask('Loading...');

                //set default values
                Ext.Ajax.request({
                    url: topBaseUrl + '/templates/index/get-template',
                    params: {
                        'id': options.template_id
                    },

                    success: function (f) {
                        //get values
                        var resultData = Ext.decode(f.responseText);

                        templates_for.setValue(resultData.template.templates_for);
                        name.setValue(resultData.template.name);
                        editor.setValue(resultData.template.message);
                        subject.setValue(resultData.template.subject);
                        from.setValue(resultData.template.from);
                        cc.setValue(resultData.template.cc);
                        bcc.setValue(resultData.template.bcc);

                        var attachmentsList = [];
                        Ext.each(resultData.template.attachments, function (v) {
                            attachmentsList.push(v['letter_template_id']);
                        });

                        Ext.each(resultData.template.file_attachments, function (v) {
                            addAttachmentToThePanel({
                                type: 'template_file_attachment',
                                attach_id: v['id'],
                                file_id: v['file_id'],
                                name: v['name'],
                                size: v['size'],
                                file_size: v['size']
                            });
                        });

                        attachmentsStore.on('load', function() {
                            attachments.setValue(attachmentsList.join(','));
                        }, this, {single: true});
                        attachmentsStore.load();

                        sendAsPdf.setValue(resultData.template.attachments_pdf);

                        if (resultData.access != 'edit') {
                            saveTemplateBtn.disable();
                        }

                        Ext.getBody().unmask();

                    },
                    failure: function () {
                        Ext.simpleConfirmation.error('Can\'t load Template Content');
                        Ext.getBody().unmask();
                    }
                });
            } else {
                attachmentsStore.load();
            }
        }

    },

    deleteTemplates: function () {
        var thisTemplatesTree = this;
        var templates = thisTemplatesTree.getSelectedFiles(thisTemplatesTree, true);

        if (!templates || templates.length === 0) {
            Ext.simpleConfirmation.warning('Please select template to delete');
            return false;
        }

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete Template' + (templates.length > 1 ? 's' : '') + '?', function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url:     topBaseUrl + '/templates/index/delete',
                    params:  {
                        templates: Ext.encode(templates)
                    },
                    success: function () {
                        thisTemplatesTree.getRootNode().reload();
                        Ext.getBody().unmask();
                    },
                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Selected template' + (templates.length > 1 ? 's' : '') + ' cannot be deleted. Please try again later.');
                    }
                });
            }
        });
        return true;
    },

    duplicateTemplates: function () {
        var thisTemplatesTree = this;
        var templates = thisTemplatesTree.getSelectedFiles(thisTemplatesTree, true);

        Ext.getBody().mask('Please wait...');
        Ext.Ajax.request({
            url: topBaseUrl + '/templates/index/duplicate',

            params: {
                templates: Ext.encode(templates)
            },

            success: function (result) {
                result = Ext.decode(result.responseText);
                Ext.getBody().unmask();

                if (result.success) {
                    thisTemplatesTree.getRootNode().reload();
                } else {
                    Ext.simpleConfirmation.error(result.message);
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Selected template' + (templates.length > 1 ? 's' : '') + ' cannot be duplicated. Please try again later.');
            }
        });

        return true;
    },

    deleteTemplateFolder: function () {
        var thisTemplatesTree = this;
        var folder_id = thisTemplatesTree.getFocusedFolderId(thisTemplatesTree);
        if (folder_id) {
            Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this folder?', function (btn) {
                if (btn == 'yes') {
                    Ext.getBody().mask('Deleting...');
                    Ext.Ajax.request({
                        url:     topBaseUrl + '/templates/index/delete-folder',
                        params:  {
                            folder_id: folder_id
                        },
                        success: function () {
                            thisTemplatesTree.root.reload();
                            Ext.getBody().unmask();
                        },
                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error('Selected Folder cannot be deleted. Please try again later.');
                        }
                    });
                }
            });
        }
    },

    setDefaultTemplate: function () {
        var thisTemplatesTree = this;
        var arrDefaultTemplateIds = [];
        var selectedTemplateId = thisTemplatesTree.getFocusedFileId(thisTemplatesTree);

        function findDefaultNode(node) {
            for (var i = 0; i < node.length; i++) {
                if (thisTemplatesTree.isFolder(node[i])) {
                    findDefaultNode(node[i].childNodes);
                } else if (node[i].attributes.is_default) {
                    arrDefaultTemplateIds.push(getNodeId(node[i]));
                }
            }
        }

        findDefaultNode(thisTemplatesTree.getRootNode().childNodes);

        // Skip if this is the same template
        if (arrDefaultTemplateIds.length == 1 && selectedTemplateId == arrDefaultTemplateIds[0]) {
            return true;
        }

        //set template as default
        Ext.getBody().mask('Please wait...');
        Ext.Ajax.request({
            url: topBaseUrl + '/templates/index/set-default',

            params: {
                arrOldTemplateIds: Ext.encode(arrDefaultTemplateIds),
                newTemplateId:     Ext.encode(selectedTemplateId)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    Ext.getBody().mask('Done!');

                    setTimeout(function () {
                        thisTemplatesTree.getRootNode().reload();
                        Ext.getBody().unmask();
                    }, 750);
                } else {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Can\'t set template as default');
            }
        });

        return true;
    }
});