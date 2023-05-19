var ClientDocumentsTree = function (config) {
    Ext.apply(this, config);
    var thisTree = this;

    var tooltip = {
        title: _('Client Default Folders'),
        text: _('Newly created <b>Top Level Folders</b> and/or <b>Sub Folders</b> will be automatically added to new client files only and not to existing client files.<br><br>' +
            'To apply the new default folder to a specific existing client:<br>' +
            '1. Go to <b>Clients</b>.<br>' +
            '2. Select the specific client.<br>' +
            '3. Click on <b>Documents</b>.<br>' +
            '4. Click on <b>New Folder</b> and select <b>Default Folders</b>.' +
            '<br><br>' +
            'Furthermore, for security purposes, the new folders will not automatically be available to all <b>Roles</b>.<br><br>' +
            'To give <b>Roles</b> access to these folders:<br>' +
            '1. Go to <b>Admin</b> and click on <b>Roles</b>.<br>' +
            '2. According to your company policy, provide access to the default folder for each role.'),
        width: 500,
        dismissDelay: 0
    };

    ClientDocumentsTree.superclass.constructor.call(this, {
        autoWidth: true,
        height: getSuperadminPanelHeight(),
        rootVisible: false,
        autoScroll: true,
        bodyBorder: false,
        border: false,
        lines: true,
        style: 'padding-bottom:5px;',
        destinationUrl: baseUrl + '/client-documents/upload',
        columns: [
            {
                id: 'filename',
                header: 'Name',
                dataIndex: 'filename',
                width: 500
            },
            {
                header: "Author",
                dataIndex: 'author',
                width: 150
            }
        ],
        loader: new Ext.tree.TreeLoader({
            dataUrl: baseUrl + '/client-documents/get-folders-tree',
            uiProviders: {
                'col': Ext.tree.ColumnNodeUI
            },
            listeners: {
                beforeload: function () {
                    Ext.getBody().mask('Loading...');
                },
                load: function () {
                    Ext.getBody().unmask();
                }
            }
        }),

        root: new Ext.tree.AsyncTreeNode({
            expanded: true,
            listeners: {
                load: function (node) {
                    //select first child
                    if (node.firstChild) {
                        setTimeout(function () {
                            node.firstChild.select();
                            node.firstChild.fireEvent('click', node.firstChild);
                        }, 100);
                    }
                }
            }
        }),

        listeners: {
            click: function (node) {
                node.expand();
                thisTree.setAccessRights(node);
            }
        },

        tbar: [
            {
                xtype: 'toolbar',
                items: [
                    {
                        text: '<i class="las la-folder-plus"></i>' + _('New Top Level Folder'),
                        id: 'tmenu-add-root',
                        cls: 'main-btn',
                        width: 180,
                        tooltip: tooltip,
                        handler: this.folderAdd.createDelegate(this, [0])
                    }, {
                        text: '<i class="las la-folder-plus"></i>' + _('New Sub Folder'),
                        id: 'tmenu-add',
                        width: 140,
                        tooltip: tooltip,
                        handler: function () {
                            var node = thisTree.getSelectionModel().getSelectedNode();
                            if (node) {
                                thisTree.folderAdd(node.attributes.el_id);
                            }
                        }
                    },
                    {
                        xtype: 'tbseparator',
                    },
                    {
                        text: '<i class="las la-file-download"></i>' + _('Download'),
                        id: 'tmenu-download',
                        disabled: true,
                        width: 100,
                        handler: this.downloadFile.createDelegate(this)
                    }, {
                        text: '<i class="las la-file-upload"></i>' + _('Upload'),
                        id: 'tmenu-upload',
                        disabled: true,
                        width: 80,
                        handler: this.uploadFile.createDelegate(this)
                    },
                    {
                        xtype: 'tbseparator',
                    },
                    {
                        text: '<i class="las la-edit"></i>' + _('Rename'),
                        id: 'tmenu-rename',
                        disabled: true,
                        width: 90,
                        handler: function () {
                            var node = thisTree.getSelectionModel().getSelectedNode();
                            if (node) {
                                thisTree.renameObject(node.attributes.el_id, node.attributes.filename);
                            }
                        }
                    }, {
                        text: '<i class="las la-trash"></i>' + _('Delete'),
                        id: 'tmenu-delete',
                        width: 80,
                        disabled: true,
                        handler: function () {
                            var node = thisTree.getSelectionModel().getSelectedNode();
                            if (node) {
                                thisTree.deleteObject(node);
                            }
                        }
                    }
                ]
            }
        ],

        bbar: {
            height: 25
        }
    });

    initTreeDragAndDrop(thisTree, 0);
};

Ext.extend(ClientDocumentsTree, Ext.tree.ColumnTree, {
    setAccessRights: function (node) {
        var booIsFile = node.attributes.type == 'file';
        Ext.getCmp('tmenu-add').setDisabled(node.attributes.locked || booIsFile);
        Ext.getCmp('tmenu-rename').setDisabled(node.attributes.locked);
        Ext.getCmp('tmenu-delete').setDisabled(node.attributes.locked);
        Ext.getCmp('tmenu-download').setDisabled(!booIsFile);
        Ext.getCmp('tmenu-upload').setDisabled(booIsFile);
    },

    isValidFileName: function (name) {
        return name !== null && name.length > 0 && name.search(/(\\|\/|\*|\?|"|<|>|\|)/) == -1;
    },

    downloadFile: function() {
        var thisTree = this;
        var node = thisTree.getSelectionModel().getSelectedNode();
        window.open(baseUrl + '/client-documents/download?id=' + node.attributes.el_id);
    },

    uploadFile: function() {
        var thisTree = this;
        var node = thisTree.getSelectionModel().getSelectedNode();

        var dialog = new MultiUploadDialog({
            settings: {
                tree: this,
                member_id: 0,
                node: node,
                folder_name: node.attributes.filename
            }
        });

        dialog.show();
    },

    folderAdd: function (folder_id) {
        var thisTree = this;
        Ext.Msg.prompt('Add folder', 'Please enter folder name:', function (btn, folder_name) {
            if (btn == 'ok' && folder_name !== '') {
                //validate folder name
                if (!thisTree.isValidFileName(folder_name)) {
                    Ext.simpleConfirmation.warning('Incorrect symbols in folder name');
                    return;
                }

                Ext.Ajax.request({
                    url: baseUrl + "/client-documents/add-folder",
                    params: {
                        name: Ext.encode(folder_name),
                        parent: folder_id
                    },
                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            var msg = String.format('<i>{0}</i> was created successfully.', folder_name);
                            Ext.simpleConfirmation.success(msg);

                            thisTree.root.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.msg);
                        }
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error('Cannot add new folder');
                    }
                });
            }
        });
    },

    renameObject: function (object_id, old_name) {
        var thisTree = this;
        Ext.Msg.prompt('Rename', 'Please enter new name:', function (btn, new_name) {
            if (btn == 'ok') {
                // Validate file/folder name
                if (!thisTree.isValidFileName(new_name)) {
                    Ext.simpleConfirmation.warning('Incorrect symbols in the name.');
                    return;
                }

                Ext.Ajax.request({
                    url: baseUrl + "/client-documents/rename",
                    params: {
                        object_id: object_id,
                        object_name: Ext.encode(new_name)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            var msg = String.format('<i>{0}</i> was renamed successfully.', old_name);
                            Ext.simpleConfirmation.success(msg);
                            thisTree.root.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.msg);
                        }
                    },

                    failure: function () {
                        var msg = String.format('<i>{0}</i> cannot be renamed. Please try again later.', old_name);
                        Ext.simpleConfirmation.error(msg);
                    }
                });
            }
        }, null, false, old_name);
    },

    deleteObject: function (object) {
        var thisTree = this;
        if (object.attributes.locked) {
            Ext.simpleConfirmation.info('This ' + object.attributes.type + ' is locked.');
            return;
        }

        if (object.attributes.files > 0) {
            Ext.simpleConfirmation.error(object.attributes.type + ' is not empty.');
            return;
        }

        var msg = String.format('Are you sure you want to delete <i>{0}</i>?', object.attributes.filename);
        Ext.Msg.confirm('Please confirm', msg, function (btn) {
            if (btn == 'yes') {
                Ext.Ajax.request({
                    url: baseUrl + '/client-documents/delete',
                    params: {
                        object_id: object.attributes.el_id
                    },
                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            var msg = String.format('<i>{0}</i> was deleted successfully.', object.attributes.filename);
                            Ext.simpleConfirmation.success(msg);
                            thisTree.root.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.msg);
                        }
                    },
                    failure: function () {
                        var msg = String.format('<i>{0}</i> cannot be deleted. Please try again later.', object.attributes.filename);
                        Ext.simpleConfirmation.error(msg);
                    }
                });
            }
        });
    }
});