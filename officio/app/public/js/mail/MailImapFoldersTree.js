var MailImapFoldersTree = function(config, parentDialog) {
    Ext.apply(this, config);
    var thisTree = this;

    MailImapFoldersTree.superclass.constructor.call(this, {
        title: 'Subscribe Folders',
        autoWidth: true,
        rootVisible: false,
        lines: true,
        animate: true,
        autoScroll: true,
        root: initAsyncTree(false),

        columns: [
            {
                id: 'filename',
                header: 'Folders',
                dataIndex: 'filename',
                autoWidth: true
            }
        ],


        loader: new Ext.tree.TreeLoader({
            clearOnLoad: false,
            uiProviders: {'col': Ext.tree.ColumnNodeUI},

            // Used to prevent autoload
            // Real load is in runReload method
            directFn: function(poNode,pfCallback) {
               if (pfCallback) {
                  pfCallback([],{status: true, scope: this, argument: { callback: pfCallback, node: poNode }});
               }
            },

            listeners: {
                beforeload: function() {
                    thisTree.getEl().mask('Loading...');

                    // Check which account is selected
                    var loader = thisTree.getLoader();
                    loader.baseParams = loader.baseParams || {};

                    var params = {
                        account_id: parentDialog.accountId,
                        imap_folders_subscribe: 1
                    };
                    Ext.apply(loader.baseParams, params);
                },

                load: function(loader, nodes) {
                    // Automatically select first folder
                    if (nodes.childNodes.length > 0) {
                        thisTree.expandAll();
                    }

                    thisTree.getEl().unmask();
                }
            }
        }),

        listeners: {
            checkchange: function (node, checked) {
                node.expand();


                if (!checked) {
                    //uncheck all folders under the folder
                    node.eachChild(function (subnode) {
                        subnode.getUI().toggleCheck(checked);
                    });
                } else {
                    thisTree.checkParent(node);
                }

                node.select();

                var ImapFoldersSubscribe = [];
                if (thisTree) {
                    ImapFoldersSubscribe.push({
                        folder_id: 0,
                        level: 0,
                        name: 'No mapping'
                    });
                    thisTree.getRootNode().cascade(function(folder) {
                        if (!folder.isRoot) {
                            if (parseInt(folder.attributes.level) < 2 && folder.attributes.checked) {
                                ImapFoldersSubscribe.push({
                                    folder_id: folder.attributes.real_folder_id,
                                    level: folder.attributes.level,
                                    name: folder.attributes.folder_label
                                });
                            }
                        }
                    });
                }

                var mappingValuesArray = [
                    parentDialog.InboxImapFoldersCombo,
                    parentDialog.SentImapFoldersCombo,
                    parentDialog.DraftsImapFoldersCombo,
                    parentDialog.TrashImapFoldersCombo
                ];
                var arrNewComboValues = [];

                for (var i = 0; i < mappingValuesArray.length; i++) {
                    var booUnsubscribedFolder = true;
                    for (var j = 0; j < ImapFoldersSubscribe.length; j++) {
                        if (mappingValuesArray[i].getValue() == ImapFoldersSubscribe[j]['folder_id']) {
                            booUnsubscribedFolder = false;
                        }
                    }

                    if (booUnsubscribedFolder) {
                        arrNewComboValues.push(0);
                    } else {
                        arrNewComboValues.push(mappingValuesArray[i].getValue());
                    }
                }

                parentDialog.combosStore.loadData(ImapFoldersSubscribe);

                for (i = 0; i < mappingValuesArray.length; i++) {
                    mappingValuesArray[i].setValue(arrNewComboValues[i]);
                }
            },

            collapsenode: function() {
                parentDialog.syncShadow();
            }
        },

        collapseFirst: false
    });
};

Ext.extend(MailImapFoldersTree, Ext.tree.ColumnTree, {
    runReload: function() {
        this.getLoader().directFn = null;
        this.getLoader().dataUrl = topBaseUrl + '/mailer/index/folders-list';
        this.root.reload();
    },

    checkParent: function(node) {
        if (node.parentNode.getUI().checkbox) {
            node.parentNode.getUI().checkbox.checked = true;
            node.parentNode.getUI().node.attributes.checked = true;
            this.checkParent(node.parentNode);
        }
    },

    // Check if node is checked
    isChecked: function(node) {
        return node && node.getUI().isChecked();
    },

    // prevent the default context menu when you miss the node
    afterRender: function() {
        MailImapFoldersTree.superclass.afterRender.call(this);
        this.el.on('contextmenu', function(e) {
            e.preventDefault();
        });
    }
});