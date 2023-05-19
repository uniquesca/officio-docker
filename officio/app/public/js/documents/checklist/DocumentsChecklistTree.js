var DocumentsChecklistTree = function (config) {
    Ext.apply(this, config);
    var thisTree = this;

    var applicantTab = Ext.getCmp(this.panelType + '-tab-' + this.applicantId + '-' + this.clientId);
    var maxWidth     = applicantTab.getWidth() - 225;

    DocumentsChecklistTree.superclass.constructor.call(this, {
        layout:      'anchor',
        autoWidth:   true,
        rootVisible: false,
        lines:       true,
        animate:     true,
        autoScroll:  true,

        root: new Ext.tree.AsyncTreeNode({
            expanded:  true,
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

        destinationUrl: baseUrl + '/documents/checklist/upload',

        columns: [
            {
                header:    _('Document'),
                dataIndex: 'document',
                width:     maxWidth * 2 / 3
            },
            {
                header:    _('Dependant'),
                dataIndex: 'dependent',
                width:     maxWidth / 6
            },
            {
                header:    _('Tags'),
                dataIndex: 'tag',
                width:     maxWidth / 6,
                renderer: function (val) {
                    if (Array.isArray(val)) {
                        val = implode(', ', val);
                    }
                    return val;
                }
            }
        ],

        tbar: [
            {
                text: '<i class="las la-file-upload"></i>' + _('Upload'),
                disabled: true,
                ref: '../toolbarButtonUpload',
                hidden: !arrDocumentsChecklistAccess.has('upload'),
                handler: thisTree.uploadDocument.createDelegate(this)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                disabled: true,
                ref: '../toolbarButtonDelete',
                hidden: !arrDocumentsChecklistAccess.has('delete'),
                handler: thisTree.deleteDocument.createDelegate(this)
            }, {
                text: '<i class="las la-file-download"></i>' + _('Download'),
                disabled: true,
                ref: '../toolbarButtonDownload',
                hidden: !arrDocumentsChecklistAccess.has('download'),
                handler: thisTree.downloadDocument.createDelegate(this, [false])
            }, {
                text: '<i class="las la-tags"></i>' + _('Set Tags'),
                disabled: true,
                ref: '../toolbarButtonSetTags',
                hidden: !arrDocumentsChecklistAccess.has('tags'),
                handler: thisTree.setDocumentTags.createDelegate(this)
            }, {
                text: '<i class="las la-percent"></i>' + _('Tag Percentage'),
                disabled: true,
                ref: '../toolbarButtonTagPercentage',
                hidden: !arrDocumentsChecklistAccess.has('tags'),
                handler: thisTree.showTagPercentage.createDelegate(this)
            }, {
                text: '<i class="las la-user-check"></i>' + _('Reassign File'),
                disabled: true,
                ref: '../toolbarButtonReassign',
                hidden: !arrDocumentsChecklistAccess.has('reassign'),
                handler: thisTree.reassignFile.createDelegate(this)
            }, '->', {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                handler: function () {
                    reload_keep_expanded(thisTree);
                }
            }, {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    showHelpContextMenu(this.getEl(), 'clients-checklist');
                }
            }
        ],


        loader: new Ext.tree.TreeLoader({
            dataUrl:    baseUrl + '/documents/checklist/get-list',
            baseParams: {
                clientId: config.clientId
            },

            clearOnLoad: false,
            uiProviders: {'col': Ext.tree.ColumnNodeUI},

            listeners: {
                beforeload: function () {
                    Ext.getBody().mask('Loading...');
                },

                load: function (loader, nodes) {
                    // Automatically expand all
                    if (nodes.childNodes.length > 0) {
                        thisTree.expandAll();
                    }

                    for (var i=0; i < nodes.childNodes.length; i++) {
                        if (nodes.childNodes[i].attributes.missed_dependents !== undefined && !empty(nodes.childNodes[i].attributes.missed_dependents)) {
                            nodes.childNodes[i].ui.elNode.setAttribute('ext:qtip', nodes.childNodes[i].attributes.missed_dependents);
                            nodes.childNodes[i].ui.textNode.setAttribute('ext:qtip', nodes.childNodes[i].attributes.missed_dependents);
                            nodes.childNodes[i].ui.iconNode.setAttribute('ext:qtip', nodes.childNodes[i].attributes.missed_dependents);
                            nodes.childNodes[i].ui.ecNode.setAttribute('ext:qtip', nodes.childNodes[i].attributes.missed_dependents);
                        }
                    }

                    thisTree.toggleTagPercentageBtn();

                    Ext.getBody().unmask();
                },

                loadexception: function (loader, node, response) {
                    var resultData   = Ext.decode(response.responseText);
                    var errorMessage = resultData && resultData.error ? resultData.error : 'Error happened. Please try again later.';

                    Ext.simpleConfirmation.warning(errorMessage);
                    Ext.getBody().unmask();
                }
            }
        }),

        collapseFirst: false,

        listeners: {
            click: function (node) {
                node.select();

                var booIsFile = thisTree.isFile(node);
                var booIsForm = thisTree.isForm(node);

                if (thisTree.toolbarButtonUpload) {
                    thisTree.toolbarButtonUpload.setDisabled(!booIsFile && !booIsForm);
                }

                if (thisTree.toolbarButtonDelete) {
                    thisTree.toolbarButtonDelete.setDisabled(!booIsFile);
                }

                if (thisTree.toolbarButtonDownload) {
                    thisTree.toolbarButtonDownload.setDisabled(!booIsFile);
                }

                if (thisTree.toolbarButtonSetTags) {
                    thisTree.toolbarButtonSetTags.setDisabled(!booIsFile);
                }

                if (thisTree.toolbarButtonReassign) {
                    thisTree.toolbarButtonReassign.setDisabled(!booIsFile);
                }
            },

            expandnode:   thisTree.fixDocumentsParentTabHeight.createDelegate(thisTree),
            collapsenode: thisTree.fixDocumentsParentTabHeight.createDelegate(thisTree),
            dblclick: function (node) {
                thisTree.downloadDocument(node);
            },
        }
    });
};

Ext.extend(DocumentsChecklistTree, Ext.tree.ColumnTree, {
    // prevent the default context menu when you miss the node
    afterRender: function () {
        DocumentsChecklistTree.superclass.afterRender.call(this);
        this.el.on('contextmenu', function (e) {
            e.preventDefault();
        });
    },

    fixDocumentsParentTabHeight: function () {
        var applicantTab = Ext.getCmp(this.panelType + '-tab-' + this.applicantId + '-' + this.clientId);
        if (applicantTab) {
            applicantTab.items.get(0).owner.fixParentPanelHeight();
        }
    },

    isForm: function (node) {
        return (node && node.attributes) ? node.attributes.is_form : false;
    },

    isFile: function (node) {
        return (node && node.attributes) ? node.attributes.is_file : false;
    },

    uploadDocument: function () {
        var thisTree = this;

        var node = thisTree.getSelectionModel().getSelectedNode();
        if (thisTree.isFile(node)) {
            node = node.parentNode;
        }

        var dialog = new DocumentsChecklistUploadDialog({
            floating: {shadow: false},
            settings: {
                tree:           thisTree,
                member_id:      thisTree.clientId,
                node:           node,
                folder_name:    node.attributes.text
            }
        });

        dialog.show();
        dialog.center();
    },

    downloadDocument: function (node) {
        var thisTree = this;

        if (!node) {
            node = thisTree.getSelectionModel().getSelectedNode();
        }
        if (!node || !thisTree.isFile(node)) {
            return;
        }
        var attributes = node['attributes'];

        window.open(baseUrl + '/documents/checklist/download?id=' + attributes['id']);
    },

    deleteDocument: function () {
        var thisTree = this;

        var selNode = thisTree.getSelectionModel().getSelectedNode();
        if (!selNode || !thisTree.isFile(selNode)) {
            return;
        }

        var node = selNode['attributes'];
        var question = String.format(
            _('Are you sure you want to delete {0}?'),
            '<span style="font-style: italic;">' + node['text'] + '</span>'
        );
        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url:    baseUrl + '/documents/checklist/delete',
                    params: {
                        clientId: Ext.encode(thisTree.clientId),
                        fileId:   Ext.encode(node['id'])
                    },

                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.success(resultDecoded.message);
                            reload_keep_expanded(thisTree);
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();

                        var error = String.format(
                            _('File {0} cannot be deleted. Please try again later.'),
                            '<span style="font-style: italic;">' + node['text'] + '</span>'
                        );

                        Ext.simpleConfirmation.error(error);
                    }
                });
            }
        });
    },

    setDocumentTags: function () {
        var thisTree = this;

        var selNode = thisTree.getSelectionModel().getSelectedNode();
        if (!selNode || !thisTree.isFile(selNode)) {
            return;
        }

        var dialog = new DocumentsChecklistTagsDialog({
            clientId:     thisTree.clientId,
            fileId:       selNode['attributes']['id'],
            arrSavedTags: selNode['attributes']['tag']
        }, thisTree);
        dialog.show();
        dialog.center();
    },

    reassignFile: function () {
        var thisTree = this;

        var selNode = thisTree.getSelectionModel().getSelectedNode();
        if (!selNode || !thisTree.isFile(selNode)) {
            return;
        }

        var wnd = new DocumentsChecklistReassignDialog({
            clientId:    thisTree.clientId,
            fileId:      selNode['attributes']['id'],
            dependentId: selNode['attributes']['dependent_id']
        }, thisTree);

        wnd.show();
        wnd.center();
    },

    getAllFileTags: function () {
        var folderNodes = this.root.childNodes;
        var tags = [];
        var filesCount = 0;

        for (var i = 0; i < folderNodes.length; i++) {
            var fileNodes = folderNodes[i].childNodes;
            filesCount += fileNodes.length;
            for (var j = 0; j < fileNodes.length; j++) {
                if (fileNodes[j].attributes.tag !== undefined && fileNodes[j].attributes.tag.length) {
                    fileNodes[j].attributes.tag.forEach(function(element) {
                        tags.push(element);
                    });
                }
            }
        }

        return {
            tags: tags,
            filesCount: filesCount
        }
    },

    toggleTagPercentageBtn: function () {
        var thisTree = this;
        var oResult = thisTree.getAllFileTags();

        thisTree.toolbarButtonTagPercentage.setDisabled(oResult.tags.length === 0);
    },

    showTagPercentage: function () {
        var thisTree = this;
        var oResult = this.getAllFileTags();
        var tags = oResult.tags;
        var filesCount = oResult.filesCount;

        if (!tags.length) {
            Ext.simpleConfirmation.info(_('There are no tags defined.'));
            return;
        }

        var arrTagPercentage = [];
        var arrUsedTags = [];

        tags.forEach(function (element) {
            if (arrUsedTags.indexOf(element) === -1) {
                var count = 0;
                for (var c = 0; c < tags.length; c++) {
                    if (tags[c] === element) {
                        count++;
                    }
                }
                var percentage = count / filesCount * 100;

                arrTagPercentage.push({
                    tag: element,
                    percentage: percentage.toFixed(2)
                });
                arrUsedTags.push(element);
            }
        });

        var wnd = new DocumentsChecklistTagPercentageDialog({
            tags: arrTagPercentage
        }, thisTree);

        wnd.show();
        wnd.center();
    },

    // Need this "hack", because there is no such method in ExtJs... :(
    refreshNodeColumns: function (n) {
        var t     = n.getOwnerTree();
        var a     = n.attributes;
        var cols  = t.columns;
        var el    = n.ui.getEl().firstChild; // <div class="x-tree-el">
        var cells = el.childNodes;

        //<div class="x-tree-col"><div class="x-tree-col-text">

        for (var i = 1, len = cols.length; i < len; i++) {
            var d = cols[i].dataIndex;
            var v = (a[d] != null) ? a[d] : '';
            if (cols[i].renderer) {
                v = cols[i].renderer(v);
            }
            cells[i].firstChild.innerHTML = v;
        }
    },

    updateNodeTags: function (arrNewTags) {
        var thisTree = this;

        var selNode = thisTree.getSelectionModel().getSelectedNode();
        if (!selNode || !thisTree.isFile(selNode)) {
            return;
        }


        selNode['attributes']['tag'] = arrNewTags;
        thisTree.refreshNodeColumns(selNode);

        thisTree.toggleTagPercentageBtn();
    },

    updateNodeDependent: function (dependentId, dependentName) {
        var thisTree = this;

        var selNode = thisTree.getSelectionModel().getSelectedNode();
        if (!selNode || !thisTree.isFile(selNode)) {
            return;
        }


        selNode['attributes']['dependent']    = dependentName;
        selNode['attributes']['dependent_id'] = dependentId;
        thisTree.refreshNodeColumns(selNode);
    }
});
