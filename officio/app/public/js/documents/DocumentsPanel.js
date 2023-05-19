var DocumentsPanel = function (config) {
    Ext.apply(this, config);

    // Load "is preview panel expanded" setting
    var booIsPreviewExpanded = Ext.state.Manager.get(config.panelType + '-preview-expanded', false);

    this.toolbarPanel = new DocumentsToolbar({
        panelType: config.panelType,
        memberId: config.memberId,
    }, this);

    this.documentsTree = new DocumentsTree({
        id: config.treeId,
        style: 'padding-left: 20px',
        split: true,
        cls: 'tree',
        layout: 'fit',
        region: 'west',
        width: config.treeWidth,
        height: config.treeHeight,
        booIsPreviewExpanded: booIsPreviewExpanded,

        panelType: config.panelType,
        memberId: config.memberId
    }, this);

    this.documentsPreviewPanel = new DocumentsPreviewPanel({
        region: 'center',
        layout: 'fit',
        panelType: config.panelType,
        booIsPreviewExpanded: booIsPreviewExpanded
    }, this);

    DocumentsPanel.superclass.constructor.call(this, {
        cls: 'documents-panel',
        layout: 'border',
        height: config.treeHeight,

        items: [
            {
                xtype: 'container',
                region: 'north',
                height: 73,
                style: 'margin-top: 17px',
                items: this.toolbarPanel
            },
            this.documentsTree,
            this.documentsPreviewPanel
        ]
    });
};

Ext.extend(DocumentsPanel, Ext.Panel, {
    openPreview: function (fileId, fileHash, fileName, booPreview, memberId) {
        var thisPanel = this;
        memberId = empty(memberId) ? 0 : memberId;

        // If this is an image or pdf or eml - don't send request to server
        var newTab;
        var format = fileName.substr(fileName.lastIndexOf('.') + 1).toLowerCase();
        if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'].has(format)) {
            var filePath = topBaseUrl + '/' + thisPanel.documentsTree.destination + '/index/get-file?attachment=0&member_id=' + memberId + '&id=' + escape(fileId);

            if (!booPreviewFilesInNewBrowser) {
                newTab = thisPanel.documentsPreviewPanel.addNewComponentTab(fileName, fileHash, 'iframepanel', false);
                if (newTab) {
                    newTab.setSrc(filePath);
                }
            } else {
                // Show/open only on double click (or when a Preview is used)
                if (!booPreview) {
                    Ext.ux.Popup.show(filePath, true, fileName);
                }
            }

            return;
        } else if (format === 'pdf') {
            var filePath = topBaseUrl + '/' + thisPanel.documentsTree.destination + '/index/get-pdf?id=' + escape(fileId) + '&member_id=' + escape(memberId) + '&file=' + fileName;

            if (!booPreviewFilesInNewBrowser) {
                // Open in the preview panel
                newTab = thisPanel.documentsPreviewPanel.addNewComponentTab(fileName, fileHash, 'iframepanel', false);
                if (newTab) {
                    newTab.setSrc(filePath);
                }
            } else {
                // Show/open only on double click (or when a Preview is used)
                if (!booPreview) {
                    Ext.ux.Popup.show(filePath, true, fileName);
                }
            }
            return;
        } else if (format === 'eml') {
            if (booPreviewFilesInNewBrowser) {
                if (booPreview) {
                    // Don't try to run a request to the server
                    return;
                } else {
                    newTab = Ext.ux.Popup.show('about:blank', true);
                    if (!newTab) {
                        return;
                    }
                }
            } else {
                // Don't try to send an additional request if we already opened the preview
                newTab = thisPanel.documentsPreviewPanel.addNewComponentTab(fileName, fileHash, 'iframepanel', false);
                if (!newTab) {
                    return;
                }
            }

        } else if (booPreview && (!zoho_enabled || booPreviewFilesInNewBrowser)) {
            // Don't try to send an additional request if we simply clicked on the file AND
            // zoho isn't enabled OR booPreviewFilesInNewBrowser is enabled
            return;
        }

        // Send request to know how to preview the file:
        // 1. Email -> preview eml file in the tab
        // 2. Zoho  -> file is supported by Zoho, preview in the tab
        // 3. Download file
        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/' + thisPanel.documentsTree.destination + '/index/preview',

            params: {
                file_id: fileId,
                member_id: memberId
            },

            success: function (form) {
                Ext.getBody().unmask();

                var resultData = Ext.decode(form.responseText);
                if (!resultData.success) {
                    Ext.simpleConfirmation.error(resultData.message);
                    return;
                }

                switch (resultData.type) {
                    case 'email' :
                        var iframe;
                        if (booPreviewFilesInNewBrowser) {
                            iframe = newTab;
                        } else {
                            // Generate new iframe
                            newTab.setSrc(Ext.isIE && Ext.isSecure ? Ext.SSL_SECURE_URL : 'about:blank');

                            var container = $('#' + newTab.items.items[0].id);
                            iframe = container.find('iframe')[0];
                        }

                        // Set content to this new iframe
                        var emailContent = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"\n' +
                            '"http://www.w3.org/TR/html4/strict.dtd">\n' +
                            '<html>\n' +
                            '<head>\n' +
                            '<title>' + fileName + '</title>\n' +
                            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">\n' +
                            '<link href="' + baseUrl + '/styles/main.css" rel="stylesheet" type="text/css" />' +
                            '<script src="' + baseUrl + '/js/documents/emlFileUtils.js?' + (new Date()).getTime() + '" type="application/javascript"></script>' +
                            '</head>' +
                            '<body onLoad="" style="padding: 10px">\n' +
                            resultData.content + '\n' +
                            '</body>\n' +
                            '</html>';

                        var doc;
                        if (iframe.document) {
                            doc = iframe.document;
                        } else {
                            doc = iframe.contentDocument ? iframe.contentDocument : (iframe.contentWindow ? iframe.contentWindow.document : null);
                        }

                        if (doc) {
                            doc.open();
                            doc.write(emailContent);
                            doc.close();
                        }
                        break;

                    case 'zoho' :
                        if (booPreviewFilesInNewBrowser) {
                            submit_hidden_form(resultData.file_path);
                            return;
                        }

                        newTab = thisPanel.documentsPreviewPanel.addNewComponentTab(resultData.filename, fileHash, 'panel', true);
                        if (!newTab) {
                            return;
                        }

                        var zohoIframe = String.format(
                            '<iframe src="{0}" style="border:none;" frameborder="0" width="100%" height="{1}px" scrolling="auto"></iframe>',
                            resultData.file_path,
                            newTab.appliedHeight
                        );

                        newTab.body.update(zohoIframe);
                        break;

                    // case 'file':
                    default:
                        if (!booPreview) {
                            submit_hidden_form(resultData.file_path);
                        }
                        break;
                }
            },

            failure: function () {
                Ext.getBody().unmask();
            }
        });
    },

    deleteDocuments: function () {
        var thisPanel = this;
        var member_id = thisPanel.panelType === 'mydocs' ? curr_member_id : thisPanel.memberId;
        var nodes = getSelectedNodes(thisPanel.documentsTree);
        var booAreNotEmptyFolders = false;
        var arrFilesHashes = [];
        var arrSelectedFiles = [];
        var arrSelectedFolders = [];
        var arrDefaultFolders = [];
        var arrNotEmptyFolders = [];

        for (var i = 0; i < nodes.length; i++) {
            var nodeName = getNodeFileName(nodes[i]);
            if (isFolder(nodes[i])) {
                // This is a folder
                if (!nodes[i].attributes.allowDeleteFolder) {
                    Ext.simpleConfirmation.warning('You can\'t delete folder <i>' + nodeName + '</i>');
                    return false;
                }

                if (nodes[i].childNodes.length > 0) {
                    arrNotEmptyFolders.push(nodeName);
                }

                if (nodes[i].attributes.isDefaultFolder) {
                    arrDefaultFolders.push(nodeName);
                }

                arrSelectedFolders.push(nodeName);
            } else {
                // This is a file
                if (!nodes[i].parentNode.attributes.allowEdit) {
                    Ext.simpleConfirmation.warning('You can\'t delete file <i>' + nodeName + '</i>');
                    return false;
                }

                arrFilesHashes.push(getNodeHash(nodes[i]));
                arrSelectedFiles.push(nodeName);
            }
        }

        if (nodes.length <= 0) {
            Ext.simpleConfirmation.warning(_('Please select a file or folder to delete.'));
            return false;
        }

        if (thisPanel.documentsPreviewPanel.areFilesOpenedThatCanBeChanged(arrFilesHashes)) {
            Ext.simpleConfirmation.warning(
                _('There are files opened in the preview. Please close them and try again.')
            );

            return false;
        }

        // Generate a confirmation message
        var question = '';
        if (arrDefaultFolders.length) {
            var warning = '';
            if (is_client) {
                if (arrDefaultFolders.length === 1) {
                    warning = _('WARNING: <i>{0}</i> is a default folder. If it will be deleted - you will not have access to it anymore.');
                } else {
                    warning = _('WARNING: such default folders were selected for deletion: <i>{0}</i>. If they will be deleted - you will not have access to them anymore.');
                }
            } else {
                if (arrDefaultFolders.length === 1) {
                    warning = _('WARNING: <i>{0}</i> is a default folder. If it will be deleted - clients will not have access to it anymore.');
                } else {
                    warning = _('WARNING: such default folders were selected for deletion: <i>{0}</i>. If they will be deleted - clients will not have access to them anymore.');
                }
            }

            question += String.format(
                warning,
                arrDefaultFolders.join(', ')
            );
        }

        if (arrNotEmptyFolders.length) {
            if (arrSelectedFolders.length === arrNotEmptyFolders.length && arrNotEmptyFolders.length === arrDefaultFolders.length) {
                // All selected folders are not empty and all of them are default
                question += String.format(
                    gt.ngettext(
                        ' This folder is not empty.',
                        ' These folders are not empty.',
                        arrNotEmptyFolders.length
                    ),
                    arrDefaultFolders.join(', ')
                ) + '<br />';
            } else {
                if (!empty(question)) {
                    question += ' ';
                }

                question += _('Some selected folders are not empty.') + '<br />';
            }
        }

        if (!empty(question)) {
            question += '<br />';
        }

        if (arrSelectedFolders.length && arrSelectedFiles.length) {
            question += String.format(
                _('Are you sure you want to delete {0} {1} and {2} {3}?'),
                arrSelectedFiles.length,
                arrSelectedFiles.length == 1 ? _('file') : _('files'),
                arrSelectedFolders.length,
                arrSelectedFolders.length == 1 ? _('folder') : _('folders')
            );
        } else {
            question += String.format(
                gt.ngettext(
                    'Are you sure you want to delete <i>{0}</i> {1}?',
                    'Are you sure you want to delete these {2} {3}?',
                    nodes.length
                ),
                arrSelectedFiles.length ? arrSelectedFiles[0] : arrSelectedFolders[0],
                arrSelectedFiles.length ? _('file') : _('folder'),
                arrSelectedFiles.length ? arrSelectedFiles.length : arrSelectedFolders.length,
                arrSelectedFiles.length ? _('files') : _('folders'),
            );
        }

        //confirmation & sending
        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {

                //get only id's and remove nodes
                var nodes_ids = [];
                for (i = 0; i < nodes.length; i++) {
                    nodes_ids.push(getNodeId(nodes[i])); //get id
                }

                var errorMessage = gt.ngettext(
                    'Selected object was not deleted. Please try again later.',
                    'Selected objects were not deleted. Please try again later.',
                    nodes_ids.length
                );

                Ext.getBody().mask(_('Processing...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/' + thisPanel.documentsTree.destination + '/index/delete',
                    params: {
                        nodes: Ext.encode(nodes_ids),
                        member_id: member_id
                    },

                    success: function (result) {
                        result = Ext.decode(result.responseText);
                        if (!result.success) {
                            Ext.simpleConfirmation.error(result.message);
                        }

                        // Refresh the tree, even if no success (because some items maybe were deleted)
                        reload_keep_expanded(thisPanel.documentsTree);
                        thisPanel.documentsPreviewPanel.closePreviews(arrFilesHashes);

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(errorMessage);
                        Ext.getBody().unmask();
                    }
                });
            }
        });

        return true;
    },

    newletter: function () {
        if (this.panelType === 'mydocs') {
            memberId = null;
            booProspects = null;
        } else {
            memberId = this.memberId;
            booProspects = ['prospects', 'marketplace'].has(this.panelType)
        }

        var tree = this.documentsTree;
        var is_mydocs = empty(memberId);
        // Don't show the Cases list combo for superadmin
        if (typeof (is_superadmin) !== 'undefined' && is_superadmin) {
            is_mydocs = false;
        }

        var folder_id = getFocusedFolderId(tree);
        if (!folder_id) {
            Ext.simpleConfirmation.warning('Please select folder to create new document');
            return false;
        }

        var clients_list = false;
        if (is_mydocs) {
            var clients_list_store = new Ext.data.Store({
                url: topBaseUrl + '/applicants/index/get-cases-list',

                baseParams: {
                    parentMemberId: 0
                },

                autoLoad: true,
                reader: new Ext.data.JsonReader({
                    root: 'rows',
                    totalProperty: 'totalCount',
                    id: 'clientId'
                }, [
                    {name: 'clientId'},
                    {name: 'clientFullName'}
                ])
            });

            clients_list = new Ext.form.ComboBox({
                store: clients_list_store,
                mode: 'local',
                displayField: 'clientFullName',
                valueField: 'clientId',
                triggerAction: 'all',
                selectOnFocus: true,
                hidden: true,
                width: 400,
                emptyText: 'Please choose a case',
                fieldLabel: 'Please choose a case'
            });
        }

        var file_name = new Ext.form.TextField({
            fieldLabel: 'File name',
            maxlength: '32',
            width: 400,
            regex: /^(?:^[^ \\\/:*?""<>|]+([ ]+[^ \\\/:*?""<>|]+)*$)$/i,
            regexText: 'Invalid file name'
        });

        var templatesStore;

        if (typeof allowedMyDocsSubTabs != 'undefined' && allowedMyDocsSubTabs.has('templates')) {
            var url, params;
            if (booProspects) {
                url = topBaseUrl + '/superadmin/manage-company-prospects/get-templates-list';
                params = {
                    member_id: 0,
                    show_templates: true,
                    show_no_template: true,
                    templates_type: 'prospects'
                };
            } else {
                url = topBaseUrl + '/templates/index/get-templates-list';
                params = {
                    withoutOther: false,
                    msg_type: 5,
                    template_for: '',
                    templates_type: Ext.encode('Letter')
                };
            }

            templatesStore = new Ext.data.Store({
                url: url,
                autoLoad: true,
                baseParams: params,
                reader: new Ext.data.JsonReader({
                    root: 'rows',
                    totalProperty: 'totalCount',
                    id: 'templateId'
                }, [
                    {name: 'templateId'},
                    {name: 'templateName'}
                ]),
                listeners: {
                    load: function () {
                        var realValue = 0;
                        var record = templates.store.getAt(0);
                        templates.fireEvent('beforeselect', templates, record, realValue);
                        templates.setValue(realValue);

                        win.getEl().unmask();
                    }
                }
            });
        } else {
            templatesStore = new Ext.data.SimpleStore({
                fields: ['templateId', 'templateName'],
                data: [
                    [0, 'No Template']
                ]
            });
        }

        var templates = new Ext.form.ComboBox({
            store: templatesStore,
            mode: 'local',
            width: 400,
            valueField: 'templateId',
            displayField: 'templateName',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            fieldLabel: 'Template to use',

            listeners: {
                'beforeselect': function (combo, record, index) {
                    if (clients_list) {
                        clients_list.setVisible(!empty(record['data']['templateId']));
                    }
                }
            }
        });

        if (typeof allowedMyDocsSubTabs == 'undefined' || !allowedMyDocsSubTabs.has('templates')) {
            var realValue = 0;
            var record = templates.store.getAt(0);
            templates.fireEvent('beforeselect', templates, record, realValue);
            templates.setValue(realValue);
        }

        var win = new Ext.Window({
            title: 'New Document',
            modal: true,
            width: 580,
            autoHeight: true,
            resizable: false,
            items: new Ext.FormPanel({
                layout: 'form',
                labelWidth: 150,
                bodyStyle: 'padding:5px;',
                items: (clients_list ? [file_name, templates, clients_list] : [file_name, templates])
            }),
            listeners: {
                show: function () {
                    if ((typeof allowedMyDocsSubTabs != 'undefined' && allowedMyDocsSubTabs.has('templates')) || is_mydocs) {
                        win.getEl().mask('Loading');
                    }
                }
            },
            buttons: [
                {
                    text: 'Cancel',
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: 'Add',
                    cls: 'orange-btn',
                    handler: function () {
                        var is_error = false;

                        var template_id = templates.getValue();
                        var folder_id = getFocusedFolderId(tree);
                        var filename = file_name.getValue();

                        if (getComboBoxIndex(templates) === -1) {
                            templates.markInvalid();
                            is_error = true;
                        }

                        if (is_mydocs) {
                            if (!empty(template_id)) {
                                // For my docs if there is a template - a client/case should be selected too
                                if (getComboBoxIndex(clients_list) === -1) {
                                    clients_list.markInvalid();
                                    is_error = true;
                                } else {
                                    memberId = clients_list.getValue();
                                }
                            }
                        }

                        if (trim(filename) === '' && empty(template_id)) {
                            file_name.markInvalid('File name cannot be empty');
                            is_error = true;
                        }

                        if (!is_error) {
                            var filenameWithExt = (empty(filename) ? templates.getRawValue() : filename) + '.docx';
                            if (checkIfFileExists(tree, folder_id, filenameWithExt)) {
                                Ext.simpleConfirmation.warning('A document with this name already exists. Please change the name and try again.');
                                is_error = true;
                            }
                        }

                        if (!is_error) {
                            win.getEl().mask('Creating...');
                            Ext.Ajax.request({
                                url: topBaseUrl + '/' + tree.destination + '/index/create-letter',
                                params: {
                                    template_id: template_id,
                                    member_id: memberId,
                                    folder_id: folder_id,
                                    filename: Ext.encode(filename)
                                },

                                success: function (f) {
                                    var resultData = Ext.decode(f.responseText);
                                    if (resultData.success) {

                                        reload_keep_expanded(tree);
                                        win.close();

                                        if (resultData.file_id) {
                                            tree.owner.openPreview(resultData.file_id, resultData.path_hash, resultData.filename, false, memberId);
                                        }
                                    } else {
                                        win.getEl().unmask();
                                        Ext.simpleConfirmation.error('An error occurred during letter creation');
                                    }
                                },

                                failure: function () {
                                    Ext.simpleConfirmation.error('Can\'t create Letter');
                                    win.getEl().unmask();
                                }
                            });
                        }
                    }
                }
            ]
        });

        win.show();
        win.center();

        return true;
    }
});
