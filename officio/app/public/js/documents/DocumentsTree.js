var DocumentsTree = function (config, owner) {
    var thisTree = this;
    this.owner = owner;
    Ext.apply(this, config);

    var memberId = config.memberId;
    var booProspects = ['prospects', 'marketplace'].has(thisTree.panelType);

    // Google Drive related variables
    this.googleDrivePickerMode = null;
    this.googleDriveFolderId = null;
    this.googleDriveFolderName = null;
    this.googleDriveCurrentNode = null;
    this.googleDriveOauthToken = null;
    this.googleDriveMemberId = null;
    this.googleDrivePickerApiLoaded = false;
    this.googleDriveSaveFileWindow = null;

    this.folderContextMenu = new Ext.menu.Menu({
        cls: 'no-icon-menu',
        showSeparator: false,
        enableScrolling: false,

        items: [
            {
                text: '<i class="las la-check-double"></i>' + _('Select all'),
                handler: function () {
                    selectAllInTree(thisTree);
                }
            }, '-', {
                text: '<i class="fas fa-folder-plus"></i>' + _('New Folder'),
                itemId: config.panelType + '-tpmenu-add-folder-' + memberId,
                hidden: is_client,
                disabled: true,
                handler: function () {
                    var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                    addFolder(thisTree, false, useMemberId);
                }
            }, {
                text: '<i class="lar la-edit"></i>' + _('Rename'),
                itemId: config.panelType + '-tpmenu-rename-folder-' + memberId,
                handler: function () {
                    var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                    var useType = config.panelType;
                    if (booProspects) {
                        useType = 'prospects';
                    }

                    renameDocument(thisTree, useType, useMemberId);
                }
            }, {
                itemId: config.panelType + '-tpmenu-delete-folder-' + memberId,
                text: '<i class="las la-trash"></i>' + _('Delete'),
                disabled: true,
                hidden: !arrDocumentsAccess.has('delete'),
                handler: function () {
                    thisTree.owner.deleteDocuments();
                }
            }, '-', {
                text: '<i class="las la-plus"></i>' + _('New Document'),
                itemId: config.panelType + '-tpmenu-letter-' + memberId,
                hidden: is_client,
                disabled: true,
                handler: function () {
                    owner.newletter();
                }
            }, {
                text: '<i class="las la-file-upload"></i>' + _('Upload'),
                itemId: config.panelType + '-tpmenu-upload-' + memberId,
                handler: function () {
                    uploadFile(thisTree, memberId);
                }
            }, {
                text: '<i class="las la-file-upload"></i>' + _('Add file from URL'),
                itemId: config.panelType + '-tpmenu-add-url-' + memberId,
                handler: function () {
                    thisTree.uploadFileFromUrl(thisTree, memberId);
                }
            }, {
                text: '<i class="las la-file-upload"></i>' + _('Add file from Dropbox'),
                hidden: empty(dropbox_app_id),
                itemId: config.panelType + '-tpmenu-add-dropbox-' + memberId,
                handler: function () {
                    thisTree.uploadFileFromDropbox(thisTree, memberId);
                }
            }, {
                text: '<i class="las la-file-upload"></i>' + _('Add file from Google Drive'),
                hidden: empty(google_drive_app_id),
                itemId: config.panelType + '-tpmenu-add-gdrive-' + memberId,
                handler: function () {
                    thisTree.uploadFileFromGoogleDrive(thisTree, memberId);
                }
            }, {
                text: '<i class="far fa-file-archive"></i>' + _('Download as ZIP'),
                hidden: booProspects,

                menu: {
                    cls: 'no-icon-menu',
                    showSeparator: false,

                    items: [{
                        text: '<i class="far fa-file-archive"></i>' + _('Download All as ZIP'),
                        handler: function () {
                            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                            zipDocuments(thisTree, config.panelType, useMemberId, 'all');
                        }
                    }, {
                        text: '<i class="far fa-file-archive"></i>' + _('Download this folder files as ZIP'),
                        handler: function () {
                            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                            zipDocuments(thisTree, config.panelType, useMemberId, 'menu');
                        }
                    }]
                }
            }
        ]
    });

    this.fileContextMenu = new Ext.menu.Menu({
        cls: 'no-icon-menu',
        showSeparator: false,
        allowOtherMenus: true,
        enableScrolling: false,

        items: [
            {
                text: '<i class="las la-check-double"></i>' + _('Select all'),
                handler: function () {
                    selectAllInTree(thisTree);
                }
            }, '-', {
                text: '<i class="las la-search"></i>' + _('Preview'),
                itemId: config.panelType + '-tpmenu-prev-' + memberId,
                disabled: true,
                handler: function () {
                    open_selected_files(owner, thisTree, false, memberId);
                }
            }, {
                text: '<i class="las la-file-download"></i>' + _('Download'),
                handler: function () {
                    downloadFiles(thisTree, memberId);
                }
            }, {
                text: '<i class="far fa-file-archive"></i>' + _('Download as ZIP'),
                hidden: booProspects,

                menu: {
                    cls: 'no-icon-menu',
                    showSeparator: false,

                    items: [{
                        text: '<i class="far fa-file-archive"></i>' + _('Download All as ZIP'),
                        handler: function () {
                            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                            zipDocuments(thisTree, config.panelType, useMemberId, 'all');
                        }
                    }, {
                        text: '<i class="far fa-file-archive"></i>' + _('Download this file as ZIP'),
                        handler: function () {
                            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                            zipDocuments(thisTree, config.panelType, useMemberId, 'menu');
                        }
                    }]
                }
            }, {
                text: '<i class="las la-file-download"></i>' + _('Save file to Dropbox'),
                itemId: config.panelType + '-tpmenu-save-dropbox-' + memberId,
                hidden: empty(dropbox_app_id),
                handler: function () {
                    thisTree.saveFileToDropbox(thisTree, memberId);
                }
            }, {
                text: '<i class="las la-file-download"></i>' + _('Save file to Google Drive'),
                itemId: config.panelType + '-tpmenu-save-gdrive-' + memberId,
                hidden: empty(google_drive_app_id),
                handler: function () {
                    thisTree.saveFileToGoogleDrive(thisTree, memberId);
                }
            }, {
                text: '<i class="lar la-edit"></i>' + _('Rename'),
                itemId: config.panelType + '-tpmenu-rename-' + memberId,
                handler: function () {
                    var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                    var useType = config.panelType;
                    if (booProspects) {
                        useType = 'prospects';
                    }

                    renameDocument(thisTree, useType, useMemberId);
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                itemId: config.panelType + '-tpmenu-delete-' + memberId,
                hidden: !arrDocumentsAccess.has('delete'),
                disabled: true,
                handler: function () {
                    thisTree.owner.deleteDocuments();
                }
            }, {
                itemId: config.panelType + '-tpmenu-convert-' + memberId,
                text: '<i class="lar la-file-pdf"></i>' + _('Convert to PDF'),
                hidden: true,
                handler: function () {
                    var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                    thisTree.convertToPdf(useMemberId);
                }
            }, {
                itemId: config.panelType + '-tpmenu-email-inbox-' + memberId,
                text: '<i class="las la-mail-bulk"></i>' + _('Send to Inbox'),
                handler: function () {
                    documentSaveEmailToInbox(thisTree, memberId, booProspects);
                }
            }, {
                itemId: config.panelType + '-tpmenu-down-email-print-' + memberId,
                text: '<i class="las la-print"></i>' + _('Print'),
                hidden: true,
                handler: function () {
                    var useMemberId = config.panelType === 'mydocs' ? curr_member_id : memberId;
                    documentPrintEmail(thisTree, useMemberId);
                }
            }, '-', {
                itemId: config.panelType + '-tpmenu-move-' + memberId,
                text: '<i class="las la-arrows-alt"></i>' + _('Move to'),
                menu: {}
            }, {
                itemId: config.panelType + '-tpmenu-copy-' + memberId,
                text: '<i class="lar la-copy"></i>' + _('Copy to'),
                menu: {}
            }
        ]
    });

    var dateColumnWidth = 105;
    var sizeColumnWidth = 100;
    this.treePadding = 20;

    // Custom urls + params, depends on the tab
    var dataUrl;
    var baseParams = {};
    var destination;
    if (booProspects) {
        dataUrl = baseUrl + '/prospects/index/get-documents-tree';
        baseParams = {
            panelType: config.panelType,
            prospect_id: memberId
        };
        destination = 'prospects';
    } else {
        if (config.panelType === 'clients') {
            baseParams = {
                member_id: memberId
            };
        }

        dataUrl = topBaseUrl + '/documents/index/get-tree';
        destination = 'documents';
    }

    DocumentsTree.superclass.constructor.call(this, {
        rootVisible: false,
        autoScroll: true,
        bodyBorder: false,
        border: false,
        lines: true,
        root: initAsyncTree(false),
        destination: destination,

        columns: [
            {
                header: _('Folders &amp; Files'),
                dataIndex: 'filename',
                sortable: true,
                width: config.width - (dateColumnWidth + sizeColumnWidth + this.treePadding),
                dynamicWidthOriginal: dateColumnWidth + sizeColumnWidth + this.treePadding,
                dynamicWidth: dateColumnWidth + sizeColumnWidth + this.treePadding,
                renderer: function (val, i, rec) {
                    return renderDocFileName(val, i, rec, false);
                }
            },
            {
                header: _('Date'),
                sortable: true,
                dataIndex: 'date',
                width: dateColumnWidth,
                staticWidthOriginal: dateColumnWidth,
                staticWidth: dateColumnWidth,
                renderer: function (val, p, record) {
                    if (val) {
                        var date = new Date(record['time'] * 1000);
                        var hours = date.getHours();
                        var minutes = "0" + date.getMinutes();
                        var seconds = "0" + date.getSeconds();

                        return String.format(
                            '<span title="{0}">{1}</span>',
                            val + ' ' + hours + ':' + minutes.substr(-2) + ':' + seconds.substr(-2),
                            val
                        );
                    }
                }
            },
            {
                header: _('Size'),
                sortable: true,
                dataIndex: 'filesize',
                width: sizeColumnWidth,
                staticWidthOriginal: sizeColumnWidth,
                staticWidth: sizeColumnWidth,
                renderer: renderDocFileSize
            }
        ],

        loader: new Ext.tree.TreeLoader({
            dataUrl: dataUrl,
            timeout: 300000,
            baseParams: baseParams,
            uiProviders: {'col': Ext.tree.ColumnNodeUI},

            // Is used in the "Zip all" method
            loadedData: [],

            listeners: {
                beforeload: function () {
                    Ext.getBody().mask('Loading...');
                },

                load: function (loader, node, response) {
                    var resultData = Ext.decode(response.responseText);
                    if (resultData && resultData.error) {
                        Ext.simpleConfirmation.warning(resultData.error);
                    } else {
                        loader.loadedData = resultData;

                        thisTree.setAllowedItems();

                        thisTree.initFoldersElements({
                            fMoveMenu: thisTree.owner.toolbarPanel.docsMoveBtn,
                            tMoveMenu: thisTree.fileContextMenu.getComponent(config.panelType + '-tpmenu-move-' + memberId),
                            fCopyMenu: thisTree.owner.toolbarPanel.docsCopyBtn,
                            tCopyMenu: thisTree.fileContextMenu.getComponent(config.panelType + '-tpmenu-copy-' + memberId)
                        });
                    }

                    Ext.getBody().unmask();
                },

                loadexception: function (loader, node, response) {
                    var resultData = Ext.decode(response.responseText);
                    var errorMessage = resultData && resultData.error ? resultData.error : 'Error happened. Please try again later.';

                    Ext.simpleConfirmation.warning(errorMessage);
                    Ext.getBody().unmask();
                }
            }
        }),

        listeners: {
            checkchange: function (node, checked) {
                thisTree.checkFolderFiles(node, checked);
                node.select();
                thisTree.setAllowedItems(node, !isSelectedFiles(thisTree));
            },

            click: function (node) {
                node.select();
                if (isFolder(node)) {
                    node.expand();
                } else {
                    open_focused_file(thisTree.owner, thisTree, node, true);
                }
                thisTree.setAllowedItems(node, !isSelectedFiles(thisTree));
            },

            dblclick: function (node) {
                switchCheckNode(node);
                open_focused_file(thisTree.owner, thisTree, node, false);
            },

            contextmenu: function (node, e) {
                node.select();
                e.stopEvent();
                thisTree.setAllowedItems(node, !isSelectedFiles(thisTree));

                if (isFolder(node)) {
                    thisTree.folderContextMenu.showAt(e.getXY());
                } else {
                    thisTree.fileContextMenu.showAt(e.getXY());
                }
            },

            expandnode: function () {
                if (thisTree.panelType === 'clients') {
                    fixDocumentsParentTabHeight(config.memberId);
                }
            },

            collapsenode: function () {
                if (thisTree.panelType === 'clients') {
                    fixDocumentsParentTabHeight(config.memberId);
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

                    if (booAtLeastOneNodeSelected || booAtLeastOneNodeChecked) {
                        // Refresh the toolbar
                        thisTree.setAllowedItems();
                    }
                }
            },

            afterrender: function () {
                initTreeDragAndDrop(thisTree, config.memberId);
                thisTree.initTreeColumnsSorting();

                // Automatically collapse (hide columns) if needed
                if (thisTree.booIsPreviewExpanded) {
                    thisTree.toggleColumns(true);
                }
            },

            resize: function (thisTree, adjWidth, adjHeight) {
                // Resize the first column, all others will be the same
                var bw = Ext.isBorderBox ? 0 : 2;
                var widths = [];
                Ext.each(thisTree.columns, function (oColumn) {
                    if (oColumn.staticWidth) {
                        widths.push(oColumn.staticWidth);
                    } else {
                        widths.push(adjWidth - oColumn.dynamicWidth);
                    }
                });

                var totalWidth = 0;
                for (var i = 0; i < widths.length; i++) {
                    totalWidth += widths[i];
                    Ext.select("div.x-tree-hd:nth-child(" + (i + 1) + ")", false, thisTree.id).setWidth(widths[i] - bw);
                    Ext.select("div.x-tree-col:nth-child(" + (i + 1) + ")", false, thisTree.id).setWidth(widths[i] - bw);
                    thisTree.columns[i].width = widths[i];
                }
                thisTree.headers.setWidth(totalWidth);
                thisTree.innerCt.setWidth(totalWidth);
            }
        },

        bbar: {
            height: 25
        }
    });
};

Ext.extend(DocumentsTree, Ext.tree.ColumnTree, {
    initTreeColumnsSorting: function () {
        var thisTree = this;

        var sort_cookie = Cookies.get('ys-sort_files') || null;
        sort_cookie = !empty(sort_cookie) ? sort_cookie.split('_') : ['date', 'desc'];
        var defaultOrder = sort_cookie[0];
        var defaultSort = sort_cookie[1];

        var arrHeaders = thisTree.headers.query('.x-tree-hd');
        Ext.each(arrHeaders, function (oHeader, index) {
            if (thisTree.columns[index].sortable) {
                var oHeaderEl = Ext.get(oHeader);

                oHeaderEl.on('click', function () {
                    // Remove previously set sorting
                    Ext.each(arrHeaders, function (oHeader, checkIndex) {
                        if (index !== checkIndex) {
                            Ext.get(oHeader)
                                .removeClass('x-tree-hd-sort-desc')
                                .removeClass('x-tree-hd-sort-asc');
                        }
                    });

                    // Change sorting icon
                    var sort = oHeaderEl.hasClass('x-tree-hd-sort-desc') ? 'asc' : 'desc';
                    var order = thisTree.columns[index].dataIndex;
                    oHeaderEl.addClass(sort === 'asc' ? 'x-tree-hd-sort-asc' : 'x-tree-hd-sort-desc');
                    oHeaderEl.removeClass(sort === 'asc' ? 'x-tree-hd-sort-desc' : 'x-tree-hd-sort-asc');

                    // Save changes to cookies (will be used on the server side)
                    if (order == 'date' && sort == 'desc') {
                        // Delete if this is a default value
                        Cookies.remove('ys-sort_files');
                    } else {
                        Cookies.set('ys-sort_files', order + "_" + sort, {expires: 365});
                    }

                    // refresh the tree
                    reload_keep_expanded(thisTree);
                });

                // Apply the correct icon
                if (thisTree.columns[index].dataIndex === defaultOrder) {
                    oHeaderEl.addClass(defaultSort === 'asc' ? 'x-tree-hd-sort-asc' : 'x-tree-hd-sort-desc');
                }
            }
        });
    },

    setAllowedItems: function (node, is_folder) {
        var thisTree = this;
        var booLibreOfficeSupported = false;
        var allowRW = false;
        var allowEdit = false;
        var allowDelete = false;
        var allowCopy = false;
        var allowSaveToInbox = false;
        var isEmlFile = false;
        var booProspects = ['prospects', 'marketplace'].has(thisTree.panelType);

        if (!empty(node)) {
            if (!is_folder) {
                booLibreOfficeSupported = node.attributes.libreoffice_supported;
                allowSaveToInbox = node.attributes.allowSaveToInbox;
                isEmlFile = node.attributes.filename.substr(node.attributes.filename.lastIndexOf('.') + 1) === 'eml';
            }

            if (booProspects) {
                var children = thisTree.getRootNode().childNodes;
                var isRootDirectory = getNodeId(node) == getNodeId(children[0]);

                allowRW = true;
                allowEdit = is_folder;
                allowDelete = !isRootDirectory;
                allowCopy = !isRootDirectory;
            } else {
                if (!is_folder) {
                    node = node.parentNode;
                }

                allowRW = node.attributes.allowRW;
                allowEdit = node.attributes.allowEdit;
                allowDelete = (is_folder && node.attributes.allowDeleteFolder && !is_client) || (!is_folder && allowEdit);
                allowCopy = true;
            }
        } else {
            is_folder = is_folder || true;
        }

        // Context menu items for Folders
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-letter-' + thisTree.memberId).setDisabled(!allowRW);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-upload-' + thisTree.memberId).setDisabled(!allowRW);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-add-url-' + thisTree.memberId).setDisabled(!allowRW);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-add-dropbox-' + thisTree.memberId).setDisabled(!allowRW);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-add-gdrive-' + thisTree.memberId).setDisabled(!allowRW);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-add-folder-' + thisTree.memberId).setDisabled(!allowEdit);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-rename-folder-' + thisTree.memberId).setDisabled(!allowDelete);
        thisTree.folderContextMenu.getComponent(thisTree.panelType + '-tpmenu-delete-folder-' + thisTree.memberId).setDisabled(!allowDelete);

        // Context menu items for Files
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-delete-' + thisTree.memberId).setDisabled(!allowDelete);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-convert-' + thisTree.memberId).setVisible(!is_folder && booLibreOfficeSupported);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-rename-' + thisTree.memberId).setDisabled(!allowDelete);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-move-' + thisTree.memberId).setDisabled(!allowDelete);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-prev-' + thisTree.memberId).setDisabled(is_folder);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-email-inbox-' + thisTree.memberId).setVisible(!is_folder && allowSaveToInbox);
        thisTree.fileContextMenu.getComponent(thisTree.panelType + '-tpmenu-down-email-print-' + thisTree.memberId).setVisible(!is_folder && isEmlFile && !booProspects);

        // Toolbar items
        thisTree.owner.toolbarPanel.docsNewDocumentBtn.setDisabled(!allowRW);
        thisTree.owner.toolbarPanel.docsUploadBtn.setDisabled(!allowRW);
        thisTree.owner.toolbarPanel.docsDeleteBtn.setDisabled(!allowDelete);
        thisTree.owner.toolbarPanel.docsConvertBtn.setDisabled(!(!is_folder && booLibreOfficeSupported));
        thisTree.owner.toolbarPanel.docsMoveBtn.setDisabled(!allowDelete || is_folder);
        thisTree.owner.toolbarPanel.docsPreviewBtn.setDisabled(is_folder);
        thisTree.owner.toolbarPanel.docsEmailBtn.setDisabled(is_folder);
        thisTree.owner.toolbarPanel.docsCopyBtn.setDisabled(!allowCopy || is_folder);
        thisTree.owner.toolbarPanel.docsAddFromUrlBtn.setDisabled(!allowRW || !is_folder);
        thisTree.owner.toolbarPanel.docsAddFromDropboxBtn.setDisabled(!allowRW || !is_folder);
        thisTree.owner.toolbarPanel.docsSaveToDropboxBtn.setDisabled(is_folder);
        thisTree.owner.toolbarPanel.docsAddFromGoogleDriveBtn.setDisabled(!allowRW || !is_folder);
        thisTree.owner.toolbarPanel.docsSaveToGoogleDriveBtn.setDisabled(is_folder);
        thisTree.owner.toolbarPanel.docsRenameBtn.setDisabled(!allowDelete);
        thisTree.owner.toolbarPanel.docsSendToInboxBtn.setDisabled(is_folder || !allowSaveToInbox);
        thisTree.owner.toolbarPanel.docsPrintEmlBtn.setVisible(!is_folder && isEmlFile && !booProspects);

        var downloadGroup = thisTree.owner.toolbarPanel.docsDownloadMenuBtn;
        if (booProspects) {
            // For Prospects, we show only the "main" download button - so we'll disable it
            downloadGroup.setDisabled(is_folder);
        } else {
            // For Clients/My Docs we show the "main" download button with menu - so we'll disable inner Download menu item
            downloadGroup.setDisabled(false);
            thisTree.owner.toolbarPanel.docsDownloadBtn.setDisabled(is_folder);
        }

        var addFolderGroup = thisTree.owner.toolbarPanel.docsNewFolderMenuBtn;
        if (thisTree.panelType === 'clients' || thisTree.panelType === 'mydocs') {
            if (typeof checkIfCanEdit !== 'undefined' && !checkIfCanEdit()) {
                // Disable main root button, so context menu will be not possible to open
                addFolderGroup.setDisabled(true);
            } else {
                addFolderGroup.setDisabled(false);

                // Add folder button can be enabled/disabled - based on folder's access rights
                thisTree.owner.toolbarPanel.docsNewFolderBtn.setDisabled(!allowEdit);
            }
        } else {
            addFolderGroup.setDisabled(!allowEdit);
        }
    },

    toggleColumns: function (booCollapseTree) {
        var thisTree = this;
        var hiddenColumnsWidth = 0;

        // In reality, we don't hide columns, but set the column width to 2 px
        var hiddenColumnWidth = 2;
        Ext.each(this.columns, function (oColumn, index) {
            if (empty(index)) {
                oColumn.dynamicWidth = booCollapseTree ? (hiddenColumnWidth * (thisTree.columns.length - 1)) + thisTree.treePadding : oColumn.dynamicWidthOriginal;
            } else {
                oColumn.staticWidth = booCollapseTree ? hiddenColumnWidth : oColumn.staticWidthOriginal;

                hiddenColumnsWidth += oColumn.staticWidthOriginal;
            }
        });

        var width = booCollapseTree ? this.getWidth() - hiddenColumnsWidth : this.getWidth() + hiddenColumnsWidth;
        this.setWidth(width);
        this.owner.syncSize();
    },

    initFoldersElements: function (elements) {
        var tree = this;
        var childNodes = tree.getRootNode().childNodes;
        var copy_menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            showSeparator: false,
            enableScrolling: false
        });

        var move_menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            showSeparator: false,
            enableScrolling: false
        });

        //get folders
        var parseChildren = function (childNodes) {
            var menu = [];

            for (var i = 0; i < childNodes.length; i++) {
                if (!isFolder(childNodes[i]) || ((childNodes[i].attributes && !childNodes[i].attributes.allowRW) || (!childNodes[i].attributes && !childNodes[i].allowRW))) {
                    continue;
                }

                var arrChildren = parseChildren(childNodes[i].attributes ? childNodes[i].attributes.children : childNodes[i].children);

                menu.push({
                    attributes: {el_id: getNodeId(childNodes[i])},
                    text: '<i class="far fa-folder"></i>' + getNodeFileName(childNodes[i]),
                    menu: arrChildren.length > 0 ? {
                        cls: 'no-icon-menu',
                        showSeparator: false,
                        items: arrChildren
                    } : false
                });
            }

            return menu;
        };

        //get menu
        menu = parseChildren(childNodes);

        //create menus
        move_menu.add(menu);
        copy_menu.add(menu);

        var setMenuEvent = function (thisMenu, action) {
            thisMenu.items.each(function () {
                this.on('click', function (m) {
                    if (action === 'move') {
                        tree.moveFilesTo(getNodeId(m), getSelectedFiles(tree));
                    } else { //copy
                        tree.copyFilesTo(getNodeId(m), getSelectedFiles(tree));
                    }
                });

                if (thisMenu.items.length > 0 && this.menu) {
                    setMenuEvent(this.menu, action);
                }
            });
        };

        // actions
        setMenuEvent(move_menu, 'move');
        setMenuEvent(copy_menu, 'copy');

        var addMenuItems = function (menu_object, set_menu) {
            if (menu_object) {
                if (menu.length < 1) {
                    menu_object.disable();
                } else {
                    menu_object.menu = set_menu;
                }
            }
        };

        // create and show menu

        if (elements.fMoveMenu) {
            addMenuItems(elements.fMoveMenu.items[0], move_menu);
        }

        if (elements.tMoveMenu) {
            addMenuItems(elements.tMoveMenu, move_menu);
        }

        if (elements.fCopyMenu) {
            addMenuItems(elements.fCopyMenu.items[0], copy_menu);
        }

        if (elements.tCopyMenu) {
            addMenuItems(elements.tCopyMenu, copy_menu);
        }
    },

    // Check if this file(s) may be moved
    moveFilesTo: function (folder_id, files) {
        var tree = this;

        var arrFilesHashes = [];
        for (var i = 0; i < files.length; i++) {
            if (!files[i].parentNode.attributes.allowEdit) {
                Ext.simpleConfirmation.warning('You can\'t move file <i>' + getNodeFileName(nodes[i]) + '</i>');
                return false;
            }

            arrFilesHashes.push(getNodeHash(files[i]));
        }

        if (tree.owner.documentsPreviewPanel.areFilesOpenedThatCanBeChanged(arrFilesHashes)) {
            Ext.simpleConfirmation.warning(
                _('There are files opened in the preview. Please close them and try again.')
            );

            return false;
        }

        var booAskRewrite = false;
        for (var j = 0; j < files.length; j++) {
            if (!empty(files[j])) {
                var checkFileName = getNodeFileName(tree.getNodeById(files[j].id)); //we need element id (not el_id)
                booAskRewrite = checkIfFileExists(tree, folder_id, checkFileName);
                if (booAskRewrite) {
                    break;
                }
            }
        }

        if (booAskRewrite) {
            Ext.Msg.confirm(_('Please confirm'), _('The document already exists, would you like to overwrite it?'), function (btn) {
                if (btn === 'yes') {
                    tree.runMove(files, folder_id);
                }
            });
        } else {
            tree.runMove(files, folder_id);
        }
    },

    //move file(s) to another folder
    runMove: function (files, folder_id) {
        //move files
        var tree = this;
        var dest_folder = findChildByNodeId(tree, folder_id);
        if (dest_folder.attributes) {
            for (var i = 0; i < files.length; i++) {
                dest_folder.appendChild(files[i]);
                files[i].select();
            }
        }

        //get id's
        var arrFilesIds = [];
        var arrFilesHashes = [];
        for (i = 0; i < files.length; i++) {
            arrFilesIds.push(getNodeId(files[i]));
            arrFilesHashes.push(getNodeHash(files[i]));
        }

        Ext.getBody().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/move-files',
            params: {
                files: Ext.encode(arrFilesIds),
                folder_id: folder_id,
                member_id: tree.memberId
            },

            success: function () {
                reload_keep_expanded(tree);
                tree.owner.documentsPreviewPanel.closePreviews(arrFilesHashes);
                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error('Can\'t move Document' + (files.length > 1 ? 's' : ''));
                Ext.getBody().unmask();
            }
        });
    },


    // check if this file(s) may be copied
    copyFilesTo: function (folder_id, files) {
        var tree = this;
        var booAskRewrite = false;
        for (var j = 0; j < files.length; j++) {
            if (!empty(files[j])) {
                var checkFileName = getNodeFileName(tree.getNodeById(files[j].id)); //we need element id (not el_id)
                booAskRewrite = checkIfFileExists(tree, folder_id, checkFileName);
                if (booAskRewrite) {
                    break;
                }
            }
        }

        if (booAskRewrite) {
            Ext.Msg.confirm(_('Please confirm'), _('The document already exists, would you like to overwrite it?'), function (btn) {
                if (btn === 'yes') {
                    tree.runCopy(files, folder_id, tree.memberId);
                }
            });
        } else {
            tree.runCopy(files, folder_id, tree.memberId);
        }
    },

    //copy file(s) to another folder
    runCopy: function (files, folder_id) {
        var tree = this;

        //get id's
        for (var i = 0; i < files.length; i++) {
            files[i] = getNodeId(files[i]);
        }

        Ext.getBody().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/copy-files',
            params: {
                files: Ext.encode(files),
                folder_id: folder_id,
                member_id: tree.memberId
            },

            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    reload_keep_expanded(tree);
                } else {
                    Ext.simpleConfirmation.error(result.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Can\'t copy Document' + (files.length > 1 ? 's' : ''));
                Ext.getBody().unmask();
            }
        });
    },

    // check/uncheck system
    checkFolderFiles: function (node, checked) {
        var thisTree = this;
        if (isFolder(node)) {
            //expand tree leaf
            node.expand();

            //check or uncheck all files under folder (if all subfiles was checked)
            node.eachChild(function (subnode) {
                subnode.getUI().toggleCheck(checked);
            });
        } else if (isChecked(node.parentNode) && !checked) {
            //remove check from folder if any file under folder was unchecked
            node.parentNode.getUI().checkbox.checked = false;
            node.parentNode.getUI().checkbox.defaultChecked = false;
            node.parentNode.getUI().node.attributes.checked = false;
        }
    },

    getFolderFilesHashes: function (folder) {
        var tree = this;
        var arrFilesHashes = [];

        var children = folder.attributes ? folder.attributes.children : folder.children;
        for (var i = 0; i < children.length; i++) {
            if (!isFolder(children[i])) {
                arrFilesHashes.push(getNodeHash(children[i]));
            } else {
                var innerFiles = tree.getFolderFilesHashes(children[i]);
                arrFilesHashes = arrFilesHashes.concat(innerFiles);
            }
        }

        return arrFilesHashes;
    },

    uploadFileFromUrl: function(tree, member_id) {
        var tree = this;
        var node = getFocusedNode(tree);
        if (node && !isFolder(node)) {
            node = node.parentNode;
        }

        if (node) {

            var folderId = getNodeId(node);

            var text_field = new Ext.form.TextField({
                labelStyle: 'padding-top: 12px; width: 70px;',
                fieldLabel: _('File URL'),
                width: 400,
                enableKeyEvents: true,
                listeners: {
                    keyup: function (field, e) {
                        if (e.getKey() == Ext.EventObject.ENTER) {
                            Ext.getCmp('add-file-from-url').handler.call(Ext.getCmp('add-file-from-url').scope);
                        }
                    }
                }
            });

            var win = new Ext.Window({
                title: _('Add file from URL'),
                modal: true,
                layout: 'form',
                autoWidth: true,
                autoHeight: true,
                resizable: false,
                closeAction: 'close',
                items: {
                    xtype: 'form',
                    bodyStyle: 'padding:5px',
                    labelWidth: 70,
                    items: text_field
                },

                listeners: {
                    show: function() {
                        setTimeout(function (){
                            text_field.focus();
                        }, 50);
                    }
                },

                buttons: [
                    {
                        text: _('Cancel'),
                        handler: function () {
                            win.close();
                        }
                    }, {
                        id: 'add-file-from-url',
                        text: _('OK'),
                        cls: 'orange-btn',
                        handler: function () {
                            var urlString = trim(text_field.getValue());

                            if (!isValidUrl(urlString)) {
                                Ext.simpleConfirmation.error('Please enter a valid URL.');
                                return false;
                            }

                            // Special processing for dropbox and google drive sharing links
                            if(urlString.indexOf('https://www.dropbox.com/') === 0
                                || urlString.indexOf('https://drive.google.com/') === 0){
                                win.close();
                                tree.uploadFileFromUrlSubmit(tree, member_id, folderId, urlString);
                                return;
                            }

                            var onlyFileName = urlString;
                            if (urlString.lastIndexOf('/') > -1) {
                                onlyFileName = urlString.substring(urlString.lastIndexOf('/') + 1, urlString.length);
                                onlyFileName = onlyFileName.split('?')[0];
                            }

                            if(onlyFileName.length == 0){
                                Ext.simpleConfirmation.error('Please enter a valid file URL.');
                                return false;
                            }

                            if(checkIfFileExists(tree, folderId, onlyFileName)){
                                var msg = String.format(_('{0} already exists, would you like to overwrite it?'), onlyFileName);
                                Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
                                    if (btn === 'yes') {
                                        win.close();
                                        tree.uploadFileFromUrlSubmit(tree, member_id, folderId, urlString);
                                    }
                                });
                            } else {
                                win.close();
                                tree.uploadFileFromUrlSubmit(tree, member_id, folderId, urlString);
                            }
                        }
                    }
                ]
            });
            win.show();

        } else {
            Ext.simpleConfirmation.warning(_('Please select folder to upload'));
        }
    },

    uploadFileFromUrlSubmit: function(tree, memberId, folderId, fileUrl){
        Ext.getBody().mask(_('Loading...'));

        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/files-upload-from-dropbox',
            params: {
                member_id: memberId,
                folder_id: folderId,
                file_url: fileUrl
            },

            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    reload_keep_expanded(tree);
                } else {
                    Ext.simpleConfirmation.error(result.error);
                }
            },

            failure: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                Ext.simpleConfirmation.error(_('An error occurred with the upload.' + (result.error ? ' ' + result.error : '')));
            }
        });
    },

    uploadFileFromDropbox: function(tree, member_id) {
        var tree = this;
        var node = getFocusedNode(tree);
        if (node && !isFolder(node)) {
            node = node.parentNode;
        }

        if (node) {
            Dropbox.appKey = dropbox_app_id;
            Dropbox.choose({
                success: function(files) {

                    var str_filename  = files[0].link;
                    var only_filename = files[0].name;

                    var folderId = getNodeId(node);
                    if (checkIfFileExists(tree, folderId, only_filename)) {

                        var msg = String.format(_('{0} already exists, would you like to overwrite it?'), only_filename);
                        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
                            if (btn === 'yes') {
                                tree.uploadFileFromDropboxSubmit(tree, member_id, folderId, str_filename);
                            }
                        });
                    } else {
                        tree.uploadFileFromDropboxSubmit(tree, member_id, folderId, str_filename);
                    }

                },
                cancel: function() {},
                linkType: "direct",
                sizeLimit: 1024 * 1024 * 20, // 20MB
            });
        } else {
            Ext.simpleConfirmation.warning(_('Please select folder to upload'));
        }
    },

    uploadFileFromDropboxSubmit: function (tree, memberId, folderId, fileUrl) {
        Ext.getBody().mask(_('Loading...'));

        // Try to get links
        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/files-upload-from-dropbox',
            params: {
                member_id: memberId,
                folder_id: folderId,
                file_url: fileUrl
            },

            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    reload_keep_expanded(tree);
                } else {
                    Ext.simpleConfirmation.error(result.error);
                }
            },

            failure: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                Ext.simpleConfirmation.error(_('An error occurred with Dropbox.' + (result.error ? ' ' + result.error : '')));
            }
        });
    },

    saveFileToDropbox: function (tree, member_id) {
        var node = getFocusedNode(tree);
        if (node) {
            var files = getSelectedFiles(tree, true);

            Ext.Ajax.request({
                url: topBaseUrl + '/' + tree.destination + '/index/get-file-download-url',
                params: {
                    member_id: member_id,
                    id: files[0]
                },

                success: function (res) {
                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        Dropbox.appKey = dropbox_app_id;
                        Dropbox.save(result.url, result.file_name, {
                            success: function () {
                                Ext.getBody().unmask();
                                Ext.simpleConfirmation.success(_('File saved to Dropbox'));
                            },
                            progress: function (progress) {
                                Ext.getBody().mask(_('Loading (' + Math.round(progress * 100) + '%)...'));
                            },
                            error: function () {
                                Ext.getBody().unmask();
                                Ext.simpleConfirmation.error(_('An error occurred with Dropbox'));
                            }
                        });

                    } else {
                        Ext.simpleConfirmation.error(result.error);
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error(_('An error occurred with this operation'));
                }
            });
        }
    },

    saveFileToGoogleDrive: function (tree, member_id) {
        var tree = this;

        tree.googleDrivePickerMode = 'select-folder';
        tree.googleDriveFolderId = null;
        tree.googleDriveFolderName = 'My Drive';
        tree.googleDriveOauthToken = null;
        tree.googleDriveMemberId = member_id;

        var node = getFocusedNode(tree);
        if (node) {

            gapi.load('auth2', function () {
                tree.googleDriveOnAuthApiLoad();
            });
            gapi.load('picker', function () {
                tree.googleDriveOnPickerApiLoad();
            });

        } else {
            Ext.simpleConfirmation.warning(_('Please select a file to upload'));
        }

    },

    uploadFileFromGoogleDrive: function (tree, member_id) {
        var tree = this;

        tree.googleDrivePickerMode = 'select-file';
        tree.googleDriveCurrentNode = null;
        tree.googleDriveOauthToken = null;
        tree.googleDriveMemberId = member_id;

        var node = getFocusedNode(tree);
        if (node && !isFolder(node)) {
            node = node.parentNode;
        }

        if (node) {

            tree.googleDriveCurrentNode = node;

            gapi.load('auth2', function () {
                tree.googleDriveOnAuthApiLoad();
            });
            gapi.load('picker', function () {
                tree.googleDriveOnPickerApiLoad();
            });

        } else {
            Ext.simpleConfirmation.warning(_('Please select folder to upload'));
        }
    },

    googleDriveOnAuthApiLoad: function () {
        var tree = this;

        gapi.auth2.init({
            'client_id': google_drive_client_id,
            'scope': 'https://www.googleapis.com/auth/drive.file',
        }).then(function () {
            tree.googleDriveInitAuth();
        }, function (error) {
            Ext.simpleConfirmation.warning(_('Failed to initialize Google Drive. Please contact Officio for assistance.'));
        });
    },

    googleDriveOnPickerApiLoad: function () {
        var tree = this;
        tree.googleDrivePickerApiLoaded = true;
    },

    googleDriveInitAuth: function () {
        var tree = this;

        var googleAuth = gapi.auth2.getAuthInstance();

        googleAuth.signIn().then(function (user) {
            tree.googleDriveGetAccessToken();
        }, function (error) {
            Ext.simpleConfirmation.warning(_('Failed to initialize Google Drive. Please contact Officio for assistance.'));
        });
    },

    googleDriveGetAccessToken: function () {
        var tree = this;

        var googleAuth = gapi.auth2.getAuthInstance();
        var googleUser = googleAuth.currentUser.get();
        var authResponse = googleUser.getAuthResponse(true);

        tree.googleDriveOauthToken = authResponse.access_token;

        if (tree.googleDrivePickerMode == 'select-file') {
            tree.googleDriveCreatePicker();
        } else if (tree.googleDrivePickerMode == 'select-folder') {
            tree.googleDriveSaveFileOpenWindow();
        }
    },

    googleDriveSaveFileOpenWindow: function () {
        var tree = this;

        var node = getFocusedNode(tree);
        if (node) {
            var files = getSelectedFiles(tree);
            var fileId = getNodeId(files[0]);
            var fileName = getNodeFileName(files[0]);


            if (tree.googleDriveSaveFileWindow) {
                tree.googleDriveSaveFileWindow.destroy();
            }
            tree.googleDriveSaveFileWindow = new Ext.Window({
                title: _('Save file to Google Drive'),
                modal: true,
                layout: 'fit',
                width: 500,
                autoHeight: true,
                resizable: false,
                closeAction: 'close',
                bodyStyle: 'padding:20px;',
                html: String.format(
                    _('<p>Officio would like to save an item to your Google Drive.<br><br><strong>{0}</strong> will appear in: <strong><span id="google-drive-folder-name">{1}</span></strong></span></p>'),
                    fileName,
                    tree.googleDriveFolderName
                ),

                buttons: [
                    {
                        text: _('Cancel'),
                        handler: function () {
                            tree.googleDriveSaveFileWindow.close();
                        }
                    }, {
                        text: _('Change Folder'),
                        handler: function () {
                            tree.googleDriveSaveFileWindow.close();
                            tree.googleDriveCreatePicker();
                        }
                    }, {
                        text: _('Save'),
                        cls: 'orange-btn',
                        handler: function () {

                            tree.googleDriveSaveFileWindow.close();
                            Ext.getBody().mask(_('Loading...'));

                            Ext.Ajax.request({
                                url: topBaseUrl + '/' + tree.destination + '/index/save-file-to-google-drive',
                                params: {
                                    member_id: tree.googleDriveMemberId,
                                    id: fileId,
                                    google_drive_folder_id: tree.googleDriveFolderId,
                                    google_drive_oauth_token: tree.googleDriveOauthToken,
                                },
                                success: function (res) {

                                    Ext.getBody().unmask();

                                    var result = Ext.decode(res.responseText);
                                    if (result.success) {
                                        Ext.simpleConfirmation.success(_('File saved to Google Drive'));
                                    } else {
                                        Ext.simpleConfirmation.error(result.error);
                                    }
                                },
                                failure: function (res) {
                                    Ext.getBody().unmask();

                                    var result = Ext.decode(res.responseText);
                                    Ext.simpleConfirmation.error(_('An error occurred with this operation.' + (result.error ? ' ' + result.error : '')));
                                }
                            });

                        }
                    }
                ]
            });
            tree.googleDriveSaveFileWindow.show();
        }

    },

    googleDriveCreatePicker: function () {
        var tree = this;

        if (tree.googleDrivePickerApiLoaded && tree.googleDriveOauthToken) {

            if (tree.googleDrivePickerMode == 'select-file') {

                var view = new google.picker.DocsView(google.picker.ViewId.DOCS);
                view.setParent('root');
                view.setMode(google.picker.DocsViewMode.GRID);
                view.setIncludeFolders(true);

                var title = "Select a file";

                var allowedMimeTypes = [
                    // Images
                    "image/png",
                    "image/jpeg",
                    "image/tiff",
                    "image/gif",

                    // Microsoft
                    "application/msword",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
                    "application/vnd.ms-powerpoint",
                    "application/vnd.openxmlformats-officedocument.presentationml.presentation",
                    "application/vnd.ms-excel",
                    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",

                    // Other
                    "application/pdf",
                    "application/zip",
                    "text/plain",
                ];
                view.setMimeTypes(allowedMimeTypes.join());

                var callback = function (data) {
                    tree.googleDrivePickerCallbackFile(data);
                };

            } else if (tree.googleDrivePickerMode == 'select-folder') {
                var view = new google.picker.DocsView(google.picker.ViewId.FOLDERS);
                view.setParent('root');
                view.setSelectFolderEnabled(true);
                view.setMode(google.picker.DocsViewMode.GRID);

                var title = "Select a folder";

                var callback = function (data) {
                    tree.googleDrivePickerCallbackFolder(data);
                };
            }

            var picker = new google.picker.PickerBuilder()
                .setAppId(google_drive_app_id)
                .setOAuthToken(tree.googleDriveOauthToken)
                .addView(view)
                .setDeveloperKey(google_drive_api_key)
                .setTitle(title)
                .setCallback(callback)
                .build();
            picker.setVisible(true);
        } else {
            Ext.simpleConfirmation.warning(_('Failed to initialize Google Drive (CreatePicker). Please contact Officio for assistance.'));
        }
    },

    googleDrivePickerCallbackFolder: function (data) {
        var tree = this;

        if (data.action == google.picker.Action.PICKED) {

            tree.googleDriveFolderId = data.docs[0].id;
            tree.googleDriveFolderName = data.docs[0].name;

            tree.googleDriveSaveFileOpenWindow();

        } else if (data.action == google.picker.Action.CANCEL) {

            tree.googleDriveSaveFileOpenWindow();

        }
    },

    googleDrivePickerCallbackFile: function (data) {
        var tree = this;

        if (data.action == google.picker.Action.PICKED) {
            var googleDriveFileId = data.docs[0].id;
            var fileName = data.docs[0].name;

            var folderId = getNodeId(tree.googleDriveCurrentNode);
            if (checkIfFileExists(tree, folderId, fileName)) {

                var msg = String.format(_('{0} already exists, would you like to overwrite it?'), fileName);
                Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
                    if (btn === 'yes') {
                        tree.googleDriveUploadFileSubmit(tree.googleDriveMemberId, folderId, googleDriveFileId, fileName);
                    }
                });
            } else {
                tree.googleDriveUploadFileSubmit(tree.googleDriveMemberId, folderId, googleDriveFileId, fileName);
            }
        }
    },

    googleDriveUploadFileSubmit: function (member_id, folderId, googleDriveFileId, fileName) {
        var tree = this;

        Ext.getBody().mask(_('Loading...'));

        Ext.Ajax.request({
            url: topBaseUrl + '/' + tree.destination + '/index/files-upload-from-google-drive',
            params: {
                member_id: member_id,
                folder_id: folderId,
                file_name: fileName,
                google_drive_file_id: googleDriveFileId,
                google_drive_oauth_token: tree.googleDriveOauthToken,
            },

            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    reload_keep_expanded(tree);
                } else {
                    Ext.simpleConfirmation.error(result.error);
                }
            },

            failure: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                Ext.simpleConfirmation.error(_('An error occurred with Google Drive.' + (result.error ? ' ' + result.error : '')));
            }
        });
    },

    convertToPdf: function (member_id) {
        var tree = this;

        var file_id = '';
        var filename = '';
        var folder_id = getFocusedFolderId(tree);
        var node = getFocusedFile(tree);
        if (node && !isFolder(node)) {
            filename = getNodeFileName(node);
            file_id = getNodeId(node);
        }

        if (folder_id !== false && !empty(file_id) && !empty(filename)) {
            Ext.getBody().mask(_('Converting. Please wait...'));

            Ext.Ajax.request({
                url: topBaseUrl + '/' + tree.destination + '/index/convert-to-pdf',
                params: {
                    folder_id: folder_id,
                    member_id: member_id,
                    file_id: Ext.encode(file_id),
                    filename: Ext.encode(filename),
                    boo_temp: 0
                },

                success: function (result) {
                    Ext.getBody().unmask();

                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        Ext.simpleConfirmation.success(_('File was converted successfully.'));
                        reload_keep_expanded(tree);
                    } else {
                        if (typeof resultData == 'object') {
                            Ext.simpleConfirmation.error(resultData.error);
                        } else {
                            Ext.simpleConfirmation.error(resultData);
                        }
                    }
                },

                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(_('File cannot be converted. Please try again later.'));
                }
            });
        }
    }
});