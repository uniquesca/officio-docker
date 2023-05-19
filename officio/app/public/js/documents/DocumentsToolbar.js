var DocumentsToolbar = function (config, owner) {
    var thisToolbar = this;
    this.owner = owner;
    Ext.apply(this, config);

    var booProspects = ['prospects', 'marketplace'].has(config.panelType);

    this.docsNewDocumentBtn = new Ext.Button({
        text: '<i class="las la-plus" style="padding-left: 0"></i>' + _('New Document'),
        tooltip: _('Create a new document for a selected folder.'),
        hidden: is_client,
        disabled: true,
        handler: function () {
            owner.newletter();
        }
    });

    this.docsNewFolderBtn = new Ext.menu.Item({
        text: '<i class="fas fa-folder-plus"></i>' + _('New Sub Folder'),
        disabled: true,
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            addFolder(owner.documentsTree, false, useMemberId);
        }
    });

    this.docsNewRootFolderBtn = new Ext.menu.Item({
        text: '<i class="fas fa-folder-plus"></i>' + _('New Top Level Folder'),
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            addFolder(owner.documentsTree, true, useMemberId);
        }
    });

    this.docsNewDefaultFoldersBtn = new Ext.menu.Item({
        text: '<i class="fas fa-folder-plus"></i>' + _('Default Folders'),
        hidden: config.panelType !== 'clients',
        handler: function () {
            addDefaultFolders(owner.documentsTree, config.memberId);
        }
    });


    this.docsNewFolderMenuBtn = new Ext.Button({
        text: '<i class="las la-plus"></i>' + _('New Folder'),
        itemId: config.panelType + '-fmenu-add-folder-group-' + config.memberId,
        tooltip: _('Add a new folder, sub-folder, or default folder.'),
        disabled: true,
        hidden: is_client,

        handler: function () {
            if (config.panelType !== 'clients' && config.panelType !== 'mydocs') {
                var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
                addFolder(owner.documentsTree, false, useMemberId);
            }
        },

        menu: config.panelType !== 'clients' && config.panelType !== 'mydocs' ? null : {
            cls: 'no-icon-menu',
            showSeparator: false,

            items: [
                this.docsNewFolderBtn,
                this.docsNewRootFolderBtn,
                this.docsNewDefaultFoldersBtn
            ]
        }
    });


    this.docsDownloadBtn = new Ext.menu.Item({
        text: '<i class="las la-file-download"></i>' + _('Download'),
        handler: function () {
            downloadFiles(owner.documentsTree, config.memberId);
        },
    });

    this.docsDownloadAllAsZipBtn = new Ext.menu.Item({
        text: '<i class="far fa-file-archive"></i>' + _('Download All files and folders as a ZIP'),
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            zipDocuments(owner.documentsTree, config.panelType, useMemberId, 'all');
        },
    });

    this.docsDownloadSelectedAsZipBtn = new Ext.menu.Item({
        text: '<i class="far fa-file-archive"></i>' + _('Download selected files/folders as ZIP'),
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            zipDocuments(owner.documentsTree, config.panelType, useMemberId, 'toolbar');
        },
    });

    this.docsUploadBtn = new Ext.Button({
        text: '<i class="las la-file-upload"></i>' + _('Upload'),
        tooltip: _('Upload an existing document to a selected folder.'),
        cls: 'main-btn',
        handler: function () {
            uploadFile(owner.documentsTree, config.memberId);
        }
    });

    this.docsDownloadMenuBtn = new Ext.Button({
        text: '<i class="las la-file-download"></i>' + _('Download'),
        tooltip: _('Download selected document(s).'),
        ref: 'docsDownloadMenuBtn',
        disabled: true,
        handler: function () {
            if (booProspects) {
                downloadFiles(owner.documentsTree, config.memberId);
            }
        },

        menu: booProspects ? null : {
            cls: 'no-icon-menu',
            showSeparator: false,

            items: [
                this.docsDownloadBtn,
                this.docsDownloadAllAsZipBtn,
                this.docsDownloadSelectedAsZipBtn
            ]
        }
    });

    this.docsEmailBtn = new Ext.Button({
        text: '<i class="lar la-envelope"></i>' + _('Email'),
        tooltip: _('Email selected document.'),
        hidden: !allowedPages.has('email') || is_client,
        disabled: true,
        handler: function () {
            documentSendEmail(owner.documentsTree, config.memberId, booProspects);
        }
    });

    this.docsPreviewBtn = new Ext.Button({
        text: '<i class="las la-search"></i>' + _('Preview'),
        disabled: true,
        handler: function () {
            open_selected_files(owner, owner.documentsTree, false, config.memberId);
        }
    });

    this.docsDeleteBtn = new Ext.Button({
        text: '<i class="las la-trash"></i>' + _('Delete'),
        tooltip: _('Delete selected document(s) or folder(s).'),
        hidden: !arrDocumentsAccess.has('delete'),
        disabled: true,
        handler: function () {
            thisToolbar.owner.deleteDocuments();
        }
    });

    this.docsRenameBtn = new Ext.menu.Item({
        text: '<i class="lar la-edit"></i>' + _('Rename'),
        disabled: true,
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            var useType = config.panelType;
            if (['prospects', 'marketplace'].has(config.panelType)) {
                useType = 'prospects';
            }

            renameDocument(owner.documentsTree, useType, useMemberId);
        }
    });

    this.docsConvertBtn = new Ext.menu.Item({
        text: '<i class="lar la-file-pdf"></i>' + _('Convert to PDF'),
        disabled: false,
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            owner.documentsTree.convertToPdf(useMemberId);
        }
    });

    // Note that we use Ext.Action instead of the Ext.Button
    // because we update sub menu after the tree is loaded
    this.docsMoveBtn = new Ext.Action({
        text: '<i class="las la-arrows-alt"></i>' + _('Move to'),
        disabled: true,
        menu: {}
    });

    // Note: see comment for the docsMoveBtn
    this.docsCopyBtn = new Ext.Action({
        text: '<i class="lar la-copy"></i>' + _('Copy to'),
        disabled: true,
        menu: {}
    });

    this.docsAddFromUrlBtn = new Ext.menu.Item({
        text: '<i class="las la-file-upload"></i>' + _('Add file from URL'),
        disabled: true,
        handler: function () {
            owner.documentsTree.uploadFileFromUrl(owner.documentsTree, config.memberId);
        }
    });

    this.docsAddFromDropboxBtn = new Ext.menu.Item({
        text: '<i class="las la-file-upload"></i>' + _('Add file from Dropbox'),
        hidden: empty(dropbox_app_id),
        disabled: true,
        handler: function () {
            owner.documentsTree.uploadFileFromDropbox(owner.documentsTree, config.memberId);
        }
    });

    this.docsSaveToDropboxBtn = new Ext.menu.Item({
        text: '<i class="las la-file-download"></i>' + _('Save file to Dropbox'),
        hidden: empty(dropbox_app_id),
        disabled: true,
        handler: function () {
            owner.documentsTree.saveFileToDropbox(owner.documentsTree, config.memberId);
        }
    });

    this.docsAddFromGoogleDriveBtn = new Ext.menu.Item({
        text: '<i class="las la-file-upload"></i>' + _('Add file from Google Drive'),
        hidden: empty(google_drive_app_id),
        disabled: true,
        handler: function () {
            owner.documentsTree.uploadFileFromGoogleDrive(owner.documentsTree, config.memberId);
        }
    });

    this.docsSaveToGoogleDriveBtn = new Ext.menu.Item({
        text: '<i class="las la-file-download"></i>' + _('Save file to Google Drive'),
        hidden: empty(google_drive_app_id),
        disabled: true,
        handler: function () {
            owner.documentsTree.saveFileToGoogleDrive(owner.documentsTree, config.memberId);
        }
    });

    this.docsSendToInboxBtn = new Ext.menu.Item({
        text: '<i class="las la-mail-bulk"></i>' + _('Send to Inbox'),
        hidden: is_client,
        disabled: true,
        handler: function () {
            documentSaveEmailToInbox(owner.documentsTree, config.memberId, booProspects);
        }
    });

    this.docsPrintEmlBtn = new Ext.menu.Item({
        text: '<i class="las la-print"></i>' + _('Print'),
        itemId: config.panelType + '-fpmenu-email-print-' + config.memberId,
        hidden: true,
        handler: function () {
            var useMemberId = config.panelType === 'mydocs' ? curr_member_id : config.memberId;
            documentPrintEmail(owner.documentsTree, useMemberId);
        }
    });

    var booHideSharedWorkspace = Cookies.get('ys-hide_shared_workspace') || false;
    this.docsHideSharedBtn = new Ext.Action({
        text: '<i class="las la-share-alt"></i>' + (booHideSharedWorkspace ? _('Show Shared Workspace') : _('Hide Shared Workspace')),
        hidden: is_client || booProspects,
        booHideShared: booHideSharedWorkspace ? 0 : 1,

        handler: function (button) {
            // set cookie
            if (this.booHideShared) {
                // This cookie will be used on server
                Cookies.set('ys-hide_shared_workspace', 1, {expires: 365});
            } else {
                Cookies.remove('ys-hide_shared_workspace');
            }

            // change button title
            button.setText('<i class="las la-share-alt"></i>' + (this.booHideShared ? _('Show Shared Workspace') : _('Hide Shared Workspace')));

            this.booHideShared = !this.booHideShared;

            // refresh grid
            reload_keep_expanded(owner.documentsTree);
        }
    });

    DocumentsToolbar.superclass.constructor.call(this, {
        items: [
            {
                // A spacer
                xtype: 'box',
                style: 'width: 0',
                autoEl: {
                    tag: 'div',
                    html: '&nbsp;'
                }
            },
            this.docsUploadBtn,
            this.docsDownloadMenuBtn,
            this.docsNewDocumentBtn,
            this.docsNewFolderMenuBtn,
            this.docsEmailBtn,
            this.docsDeleteBtn,
            {
                text: '<i class="las" style="padding-right: 0">&nbsp;</i>' + _('Other options'),
                menu: {
                    cls: 'no-icon-menu',
                    showSeparator: false,

                    items: [
                        this.docsRenameBtn,
                        this.docsConvertBtn,
                        this.docsMoveBtn,
                        this.docsCopyBtn,
                        this.docsAddFromUrlBtn,
                        this.docsAddFromDropboxBtn,
                        this.docsSaveToDropboxBtn,
                        this.docsAddFromGoogleDriveBtn,
                        this.docsSaveToGoogleDriveBtn,
                        this.docsSendToInboxBtn,
                        this.docsPrintEmlBtn,
                        this.docsHideSharedBtn
                    ]
                }
            }, '->', {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                ctCls: 'x-toolbar-cell-no-right-padding',
                handler: function () {
                    reload_keep_expanded(owner.documentsTree);
                }
            }, {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    var helpContextId = '';
                    switch (config.panelType) {
                        case 'clients':
                            helpContextId = 'clients-documents';
                            break;

                        case 'mydocs':
                            helpContextId = 'my-documents';
                            break;

                        case 'prospects':
                            helpContextId = 'prospects-documents';
                            break;

                        case 'marketplace':
                            helpContextId = 'marketplace-documents';
                            break;

                        default:
                            break;
                    }


                    showHelpContextMenu(this.getEl(), helpContextId);
                }
            }
        ]
    });
};

Ext.extend(DocumentsToolbar, Ext.Toolbar, {});
