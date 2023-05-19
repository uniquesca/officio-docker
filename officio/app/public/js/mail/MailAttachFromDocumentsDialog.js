var MailAttachFromDocumentsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisDialog   = this;
    var arrTabs = [];

    this.booShowProspects = typeof arrProspectSettings != 'undefined' && arrProspectSettings.arrAccess['prospects'].tabs.documents.view;
    this.booShowClients   = typeof allowedClientSubTabs != 'undefined' && allowedClientSubTabs.has('documents');
    this.booShowMyDocs    = typeof allowedMyDocsSubTabs != 'undefined' && allowedMyDocsSubTabs.has('documents');

    if (this.booShowProspects) {
        var prospectsTree = new Ext.tree.ColumnTree({
            id: 'attach-from-prospects-tree',
            autoWidth: true,
            height: 500,
            rootVisible: false,
            autoScroll: true,
            bodyBorder: false,
            border: false,
            lines: true,
            root: initAsyncTree(false),
            destination: 'documents',

            columns: [
                {
                    id: 'filename',
                    header: 'Folders &amp; Files',
                    dataIndex: 'filename',
                    sortable: true,
                    width: 400,
                    renderer: function (val, i, rec) {
                        return renderDocFileName(val, i, rec, true);
                    }
                },
                {
                    header: 'Date',
                    dataIndex: 'date',
                    width: 105
                },
                {
                    header: 'Size',
                    dataIndex: 'filesize',
                    width: 50,
                    renderer: renderDocFileSize
                }
            ],

            loader: new Ext.tree.TreeLoader({
                dataUrl: baseUrl + '/prospects/index/get-documents-tree',
                uiProviders: {'col': Ext.tree.ColumnNodeUI},
                loadedData: [],

                listeners: {
                    beforeload: function () {
                        var tree = Ext.getCmp('attach-from-prospects-tree');
                        var loader = tree.getLoader();

                        if (!loader.baseParams.prospect_id) {
                            return false;
                        } else {
                            thisDialog.getEl().mask('Loading...');
                        }
                    },
                    load: function (loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        loader.loadedData = resultData;
                        if (resultData && resultData.error) {
                            Ext.simpleConfirmation.warning(resultData.error);
                        }
                        thisDialog.getEl().unmask();
                    },

                    loadexception: function(loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        var errorMessage = resultData && resultData.error ? resultData.error : 'Error happened. Please try again later.';

                        Ext.simpleConfirmation.warning(errorMessage);
                        thisDialog.getEl().unmask();
                    }
                }
            }),

            listeners: {
                checkchange: function (node, checked) {
                    var thisTree = this;
                    if (isFolder(node)) {
                        //expand tree leaf
                        node.expand();

                        //check or uncheck all files under folder (if all subfiles was checked)
                        node.eachChild(function (subnode) {
                            subnode.getUI().toggleCheck(checked);
                        });
                    } else if (isChecked(node.parentNode) && !checked) { //remove check from folder if any file under folder was unchecked
                        node.parentNode.getUI().checkbox.checked = false;
                        node.parentNode.getUI().checkbox.defaultChecked = false;
                        node.parentNode.getUI().node.attributes.checked = false;
                    } else if (!isChecked(node.parentNode) && checked && thisTree.getChecked('', node.parentNode).length == node.parentNode.childNodes.length) {
                        node.parentNode.getUI().checkbox.checked = true;
                        node.parentNode.getUI().checkbox.defaultChecked = true;
                        node.parentNode.getUI().node.attributes.checked = true;
                    }

                    node.select();
                },

                click: function (node) {
                    node.select();
                    if (isFolder(node)) {
                        if (node.expanded) {
                            node.collapse();
                        } else {
                            node.expand();
                        }
                    }
                },

                beforecollapsenode: function (node) {
                    // Clear inner selections ONLY if the current folder isn't checked
                    if (!node.attributes.checked) {
                        // Uncheck all checked sub nodes
                        var booAtLeastOneNodeSelected = false;
                        var booAtLeastOneNodeChecked = false;
                        node.cascade(function (subNode) {
                            if (node !== subNode && subNode.isSelected()) {
                                booAtLeastOneNodeSelected = true;
                            }

                            if (subNode.attributes.checked) {
                                subNode.attributes.checked = false;
                                subNode.getUI().toggleCheck(false);

                                booAtLeastOneNodeChecked = true;
                            }
                        });

                        // Select and unselect the current node - so we can clear all previous selections
                        if (booAtLeastOneNodeSelected) {
                            node.select();
                            node.unselect();
                        }
                    }
                }
            },

            bbar: {
                height: 25
            }
        });

        var prospectsStore = new Ext.data.Store({
            id: 'prospects-store',
            proxy: new Ext.data.HttpProxy({
                url:     topBaseUrl + '/prospects/index/get-all-prospects-list',
                method: 'post'
            }),

            baseParams: {
                panelType: 'prospects'
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'clientId'},
                {name: 'clientFullName'}
            ]),
            listeners:
                {
                    load: function(store) {
                        // If there is one option only - automatically select it in the combo
                        if (store.getCount() == 1) {
                            prospectsCombo.setValue(store.getAt(0).data.clientId);
                            prospectsCombo.fireEvent('select', prospectsCombo);
                        } else if (thisDialog.booProspect && !empty(thisDialog.member_id)) {
                            prospectsCombo.setValue(thisDialog.member_id);
                            prospectsCombo.fireEvent('select', prospectsCombo);
                        }
                    }
                }
        });

        prospectsStore.load();

        var prospectsCombo = new Ext.form.ComboBox({
            store: prospectsStore,
            mode: 'local',
            valueField: 'clientId',
            displayField: 'clientFullName',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            emptyText: 'Please select a Prospect',
            typeAhead: true,
            selectOnFocus: true,
            searchContains: true, // custom property
            width: 556,
            style: 'margin-bottom: 8px',
            listeners:
                {
                    select: function() {
                        // Check which account is selected
                        var loader = prospectsTree.getLoader();
                        loader.baseParams = loader.baseParams || {};

                        var params = {
                            prospect_id: this.getValue()
                        };
                        Ext.apply(loader.baseParams, params);
                        reload_keep_expanded(prospectsTree);
                    }
                }
        });

        var prospectsTab = new Ext.Panel({
            id:         'attach-from-prospects-tab',
            title:      'Prospects',
            autoWidth:  true,
            autoHeight: true,
            labelWidth: 200,
            bodyStyle:  'padding: 8px',

            items: [
                prospectsCombo,
                prospectsTree
            ]
        });

        arrTabs.push(prospectsTab);
    }

    if (this.booShowClients) {
        var clientsTree = new Ext.tree.ColumnTree({
            id: 'attach-from-clients-tree',
            autoWidth: true,
            height: 500,
            rootVisible: false,
            autoScroll: true,
            bodyBorder: false,
            border: false,
            lines: true,
            root: initAsyncTree(false),
            destination: 'documents',

            columns: [
                {
                    id: 'filename',
                    header: 'Folders &amp; Files',
                    dataIndex: 'filename',
                    sortable: true,
                    width: 400,
                    renderer: function (val, i, rec) {
                        return renderDocFileName(val, i, rec, true);
                    }
                },
                {
                    header: 'Date',
                    dataIndex: 'date',
                    width: 105
                },
                {
                    header: 'Size',
                    dataIndex: 'filesize',
                    width: 50,
                    renderer: renderDocFileSize
                }
            ],

            loader: new Ext.tree.TreeLoader({
                dataUrl: baseUrl + '/documents/index/get-tree',
                timeout: 300000,
                baseParams: {},
                uiProviders: {'col': Ext.tree.ColumnNodeUI},
                loadedData: [],

                listeners: {
                    beforeload: function () {
                        var tree = Ext.getCmp('attach-from-clients-tree');
                        var loader = tree.getLoader();

                        if (!loader.baseParams.member_id) {
                            return false;
                        } else {
                            thisDialog.getEl().mask('Loading...');
                        }
                    },
                    load: function (loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        loader.loadedData = resultData;
                        if (resultData && resultData.error) {
                            Ext.simpleConfirmation.warning(resultData.error);
                        }
                        thisDialog.getEl().unmask();
                    },

                    loadexception: function(loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        var errorMessage = resultData && resultData.error ? resultData.error : 'Error happened. Please try again later.';

                        Ext.simpleConfirmation.warning(errorMessage);
                        thisDialog.getEl().unmask();
                    }
                }
            }),

            listeners: {
                checkchange: function (node, checked) {
                    var thisTree = this;
                    if (isFolder(node)) {
                        //expand tree leaf
                        node.expand();

                        //check or uncheck all files under folder (if all subfiles was checked)
                        node.eachChild(function (subnode) {
                            subnode.getUI().toggleCheck(checked);
                        });
                    } else if (isChecked(node.parentNode) && !checked) { //remove check from folder if any file under folder was unchecked
                        node.parentNode.getUI().checkbox.checked = false;
                        node.parentNode.getUI().checkbox.defaultChecked = false;
                        node.parentNode.getUI().node.attributes.checked = false;
                    } else if (!isChecked(node.parentNode) && checked && thisTree.getChecked('', node.parentNode).length == node.parentNode.childNodes.length) {
                        node.parentNode.getUI().checkbox.checked = true;
                        node.parentNode.getUI().checkbox.defaultChecked = true;
                        node.parentNode.getUI().node.attributes.checked = true;
                    }

                    node.select();
                },

                click: function (node) {
                    node.select();
                    if (isFolder(node)) {
                        if (node.expanded) {
                            node.collapse();
                        } else {
                            node.expand();
                        }
                    }
                },

                beforecollapsenode: function (node) {
                    // Clear inner selections ONLY if the current folder isn't checked
                    if (!node.attributes.checked) {
                        // Uncheck all checked sub nodes
                        var booAtLeastOneNodeSelected = false;
                        var booAtLeastOneNodeChecked = false;
                        node.cascade(function (subNode) {
                            if (node !== subNode && subNode.isSelected()) {
                                booAtLeastOneNodeSelected = true;
                            }

                            if (subNode.attributes.checked) {
                                subNode.attributes.checked = false;
                                subNode.getUI().toggleCheck(false);

                                booAtLeastOneNodeChecked = true;
                            }
                        });
                    }
                }
            },

            bbar: {
                height: 25
            }
        });

        var clientsStore = new Ext.data.Store({
            proxy: new Ext.data.HttpProxy({
                url:     topBaseUrl + '/applicants/index/get-cases-list',
                method: 'post'
            }),

            baseParams: {
                panelType: this.panelType,
                parentMemberId: 0
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'clientId'},
                {name: 'clientFullName'}
            ]),
            listeners:
                {
                    load: function(store) {
                        // If there is one option only - automatically select it in the combo
                        if (store.getCount() == 1) {
                            clientsCombo.setValue(store.getAt(0).data.clientId);
                            clientsCombo.fireEvent('select', clientsCombo);
                        } else if (!thisDialog.booProspect && !empty(thisDialog.member_id)) {
                            clientsCombo.setValue(thisDialog.member_id);
                            clientsCombo.fireEvent('select', clientsCombo);
                        }
                    }
                }
        });

        clientsStore.load();

        var clientsCombo = new Ext.form.ComboBox({
            id: 'clients-combo',
            store: clientsStore,
            mode: 'local',
            valueField: 'clientId',
            displayField: 'clientFullName',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            emptyText: 'Please select a Case',
            typeAhead: true,
            selectOnFocus: true,
            searchContains: true, // custom property
            width: 540,
            style: 'margin-bottom: 8px',
            listeners:
                {
                    select: function() {
                        // Check which account is selected
                        var loader = clientsTree.getLoader();
                        loader.baseParams = loader.baseParams || {};

                        var params = {
                            member_id: this.getValue()
                        };
                        Ext.apply(loader.baseParams, params);
                        reload_keep_expanded(clientsTree);
                    }
                }
        });

        var clientsTab = new Ext.Panel({
            id:         'attach-from-clients-tab',
            title:      'Clients',
            autoWidth:  true,
            autoHeight: true,
            labelWidth: 200,
            bodyStyle: 'padding: 8px',

            items: [
                clientsCombo,
                clientsTree
            ]
        });

        arrTabs.push(clientsTab);
    }

    if (this.booShowMyDocs) {
        var myDocsTree = new Ext.tree.ColumnTree({
            id: 'attach-from-my-documents-tree',
            autoWidth: true,
            height: 500,
            rootVisible: false,
            autoScroll: true,
            bodyBorder: false,
            border: false,
            lines: true,
            root: initAsyncTree(false),
            destination: 'documents',

            columns: [
                {
                    id: 'filename',
                    header: 'Folders &amp; Files',
                    dataIndex: 'filename',
                    sortable: true,
                    width: 400,
                    renderer: function (val, i, rec) {
                        return renderDocFileName(val, i, rec, true);
                    }
                },
                {
                    header: 'Date',
                    dataIndex: 'date',
                    width: 105
                },
                {
                    header: 'Size',
                    dataIndex: 'filesize',
                    width: 50,
                    renderer: renderDocFileSize
                }
            ],

            loader: new Ext.tree.TreeLoader({
                dataUrl: baseUrl + '/documents/index/get-tree',
                timeout: 300000,
                uiProviders: {'col': Ext.tree.ColumnNodeUI},
                loadedData: [],

                listeners: {
                    beforeload: function () {
                        thisDialog.getEl().mask('Loading...');
                    },
                    load: function (loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        loader.loadedData = resultData;
                        if (resultData && resultData.error) {
                            Ext.simpleConfirmation.warning(resultData.error);
                        }
                        thisDialog.getEl().unmask();
                    },

                    loadexception: function(loader, node, response) {
                        var resultData = Ext.decode(response.responseText);
                        var errorMessage = resultData && resultData.error ? resultData.error : 'Error happened. Please try again later.';

                        Ext.simpleConfirmation.warning(errorMessage);
                        thisDialog.getEl().unmask();
                    }
                }
            }),

            listeners: {
                checkchange: function (node, checked) {
                    var thisTree = this;
                    if (isFolder(node)) {
                        //expand tree leaf
                        node.expand();

                        //check or uncheck all files under folder (if all subfiles was checked)
                        node.eachChild(function (subnode) {
                            subnode.getUI().toggleCheck(checked);
                        });
                    } else if (isChecked(node.parentNode) && !checked) { //remove check from folder if any file under folder was unchecked
                        node.parentNode.getUI().checkbox.checked = false;
                        node.parentNode.getUI().checkbox.defaultChecked = false;
                        node.parentNode.getUI().node.attributes.checked = false;
                    } else if (!isChecked(node.parentNode) && checked && thisTree.getChecked('', node.parentNode).length == node.parentNode.childNodes.length) {
                        node.parentNode.getUI().checkbox.checked = true;
                        node.parentNode.getUI().checkbox.defaultChecked = true;
                        node.parentNode.getUI().node.attributes.checked = true;
                    }

                    node.select();
                },

                click: function (node) {
                    node.select();
                    if (isFolder(node)) {
                        if (node.expanded) {
                            node.collapse();
                        } else {
                            node.expand();
                        }
                    }
                },

                contextmenu: function (node, e) {
                    node.select();
                    e.stopEvent();
                    setAllowedItems(node, !isSelectedFiles(tree));
                    if (isFolder(node)) {
                        folderContextMenu.showAt(e.getXY());
                    } else {
                        treeMenu.showAt(e.getXY());
                    }
                },

                beforecollapsenode: function (node) {
                    // Clear inner selections ONLY if the current folder isn't checked
                    if (!node.attributes.checked) {
                        // Uncheck all checked sub nodes
                        var booAtLeastOneNodeSelected = false;
                        var booAtLeastOneNodeChecked = false;
                        node.cascade(function (subNode) {
                            if (node !== subNode && subNode.isSelected()) {
                                booAtLeastOneNodeSelected = true;
                            }

                            if (subNode.attributes.checked) {
                                subNode.attributes.checked = false;
                                subNode.getUI().toggleCheck(false);

                                booAtLeastOneNodeChecked = true;
                            }
                        });
                    }
                }
            },

            bbar: {
                height: 25
            }
        });

        var myDocsTab = new Ext.Panel({
            id:         'attach-from-my-documents-tab',
            title:      'My Documents',
            autoWidth:  true,
            autoHeight: true,
            labelWidth: 200,
            bodyStyle:  'padding: 8px',

            items: [
                myDocsTree
            ]
        });

        arrTabs.push(myDocsTab);
    }

    this.tabPanel = new Ext.TabPanel({
        items: arrTabs,
        activeTab: 0,
        deferredRender: false,
        plain: true,
        cls: 'tabs-second-level',

        listeners: {
            tabchange: function () {
                thisDialog.syncShadow();
            }
        }
    });

    this.mainFormPanel = new Ext.FormPanel({
        labelWidth: 200,
        bodyStyle:  'padding:5px',

        items: [
            thisDialog.tabPanel
        ]
    });


    MailAttachFromDocumentsDialog.superclass.constructor.call(this, {
        id:          'mail-attach-from-documents-dialog',
        title:       '<i class="las la-paperclip"></i>' + _('Attach from the Documents Tab'),
        modal:       true,
        autoWidth:   true,
        autoHeight:  true,
        resizable:   false,
        closeAction: 'close',
        items:       this.mainFormPanel,

        buttons: [
            {
                text:    'Cancel',
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text:    'Attach',
                cls:     'orange-btn',
                handler: this.attachFiles.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(MailAttachFromDocumentsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    loadSettings: function () {
        if (this.booProspect) {
            this.tabPanel.setActiveTab('attach-from-prospects-tab');
        } else if (this.member_id !== undefined) {
            this.tabPanel.setActiveTab('attach-from-clients-tab');
        } else {
            this.tabPanel.setActiveTab('attach-from-my-documents-tab');
        }
        this.mainFormPanel.getForm().clearInvalid();
    },

    attachFiles: function () {
        var thisDialog = this;
        var arrTrees = ['attach-from-prospects-tree', 'attach-from-clients-tree', 'attach-from-my-documents-tree'];

        var aa_files = '';

        Ext.each(arrTrees, function(treeId){
            var booProspect = treeId === 'attach-from-prospects-tree';
            var tree = Ext.getCmp(treeId);
            if (tree) {
                var files = getSelectedFiles(tree);

                if (files && files.length > 0) {
                    var attachments = [];

                    var loader = tree.getLoader();
                    var member_id = loader.baseParams.member_id;

                    for (var i = 0; i < files.length; i++) {
                        attachments.push({
                            original_file_name: getNodeFileName(files[i]),
                            link: '#',
                            onclick: 'submit_hidden_form(\'' + topBaseUrl + '/' + tree.destination + '/index/get-file/\', {id: \'' + getNodeId(files[i]) + '\', member_id: ' + member_id + '}); return false;',
                            size: files[i].attributes.filesize,
                            libreoffice_supported: files[i].attributes.libreoffice_supported,
                            id: getNodeId(files[i])
                        });
                    }

                    if (attachments.length > 0) {
                        $.each(attachments, function(k, v) {
                            thisDialog.owner.previously_att_global.push(v);
                        });

                        for (var i = 0; i < attachments.length; i++) {
                            var file_id = attachments[i].id;
                            var num = thisDialog.owner.previously_att_global.length - 1 + i;

                            attachments[i].unique_file_id = 'assigned-attachment-' + num;

                            var download_link = (attachments[i].link) ? attachments[i].link : topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id;

                            var onclick = attachments[i].onclick ? 'onclick="'+attachments[i].onclick+'"' : '';

                            var convertOnclick = 'Ext.getCmp(\'mail-create-dialog\').convertFileToPdf(' + member_id + ', {file_id: \'' + file_id + '\', filename: \'' + attachments[i].original_file_name + '\', booProspect: ' + booProspect + ', unique_file_id: \'' + attachments[i].unique_file_id + '\', booTemp: ' + attachments[i].letter_template_attachment + '})';

                            var convertToPDFLink = attachments[i].libreoffice_supported ? '<img src="' + topBaseUrl + '/images/pdf.png" class="attachment-convert" onclick="' + convertOnclick + '"  alt="Convert to PDF" title="Convert to PDF" />' : '';

                            aa_files += '<li id="' + attachments[i].unique_file_id + '" class="qq-file-id-' + file_id + ' qq-upload-success ' + (attachments[i].letter_template_attachment ? ' letter-template-attachment' : '') + '" qq-file-id="' + file_id + '">' +
                                '<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>' +
                                '<div class="qq-progress-bar-container-selector qq-hide">' +
                                '<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>' +
                                '</div>' +

                                '<span class="qq-upload-spinner-selector qq-upload-spinner qq-hide"></span>' +
                                '<div class="qq-thumbnail-wrapper" style="display: none;">' +
                                '<a class="preview-link" target="_blank">' +
                                '<img class="qq-thumbnail-selector" qq-max-size="120" qq-server-scale>' +
                                '</a>' +
                                '</div>' +
                                '<img src="' + topBaseUrl + '/images/deleteicon.gif" class="attachment-cancel" onclick="Ext.getCmp(\'mail-create-dialog\').removeAttachment(this); return false;" alt="Cancel" />' +
                                convertToPDFLink +
                                '<div class="qq-file-info">' +
                                '<div class="qq-file-name">' +
                                '<span class="qq-upload-file-selector qq-upload-file" title="' + attachments[i].original_file_name + '">' +
                                '<a href="' + download_link + '" class="bluelink" target="_blank"' +onclick+'>' + (attachments[i].original_file_name.length > 20 ? attachments[i].original_file_name.substr(0, 20) + '...' : attachments[i].original_file_name) + '</a>' +
                                '</span>' +
                                '<span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>' +
                                '</div>' +
                                '<span class="qq-upload-size-selector qq-upload-size">' + attachments[i].size + '</span>' +
                                '</div>' +
                                '</li>';
                        }

                        thisDialog.owner.toggleSection('attachments', true);
                    }
                }
            }
        });

        if (aa_files) {
            setTimeout(function() {
                if (empty($('.qq-upload-list-selector.qq-upload-list').html())) {
                    $('.qq-upload-list-selector.qq-upload-list').prepend(aa_files);
                } else {
                    $('.qq-upload-list-selector.qq-upload-list').append(aa_files);
                }
            }, 300);
            thisDialog.close();
        } else {
            Ext.simpleConfirmation.warning('Please select files to attach.');
        }
    }
});