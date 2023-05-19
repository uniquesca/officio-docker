MailFolders = function(options) {
    var thisTree = this;

    this.bbar = new Ext.Panel({
        id: 'mail-ext-folders-tree-bbar'
    });

    this.bbar.add(
        new Ext.Container({
            style: 'margin-top: 10px; margin-left: 5px',
            items: {
                xtype: 'button',
                text: '<i class="las la-plus" style="padding-right: 4px"></i>' + _('New Folder'),
                width: 100,

                handler: function () {
                    thisTree.showAddFolderWindow(0, 0, 0);
                }
            }
        })
    );

    this.bbar.add(
        new Ext.Container({
            style: 'margin-top: 10px; margin-left: 5px',
            items: {
                xtype: 'button',
                text: '<i class="las la-cog"></i>' + 'Account Settings',
                width: 100,
                handler: function () {
                    Ext.getCmp('mail-tabpanel').showSettingsTab();
                }
            }
        })
    );


    this.bbar.add(
        new Ext.Toolbar({
            id: 'mail-folders-tree-bbar',
            style: 'margin-top: 20px; margin-left: 5px',
            height: 27,
            items: [
                {
                    id: 'mail-folders-tree-bbar-text-panel',
                    xtype: 'statusbar',
                    style: 'border-color: #CEDEEE;',
                    width: options.defaultFolderWidth - 10,
                    hidden: true
                },
                {
                    id: 'mail-folders-tree-bbar-progress-panel',
                    xtype: 'toolbar',
                    cls: 'toolbar-noborder',
                    ctCls: 'x-toolbar-cell-no-right-padding',
                    width: options.defaultFolderWidth - 10,
                    hidden: true,
                    items: [
                        {
                            id: 'mail-ext-progressbar',
                            xtype: 'progress',
                            ctCls: 'x-toolbar-cell-no-right-padding',
                            width: options.defaultFolderWidth - 30,
                            cls: 'left-align'
                        }, '->', {
                            id: 'mail-ext-progressbar-cancel-btn',
                            xtype: 'button',
                            width: 20,
                            disabled: true,
                            tooltip: _('Click here to cancel.'),
                            iconCls: 'mail-attachment-cancel',
                            ctCls: 'x-toolbar-cell-no-right-padding',

                            // these are internal params, will be passed from MailChecker
                            officio: {
                                lock_file: 0,
                                progress: 100
                            },
                            handler: function () {
                                MailChecker.createLockFile(Ext.getCmp('mail-main-toolbar').getSelectedAccountId());
                                MailChecker.getPBar().updateProgress(this.officio.progress / 100, _('Cancelling. Please wait...'));

                                this.disable();
                            }
                        }
                    ],

                    listeners: {
                        'resize': function (tbar, width) {
                            Ext.getCmp('mail-folders-tree-bbar').setWidth(width);
                            Ext.getCmp('mail-folders-tree-bbar-progress-panel').setWidth(width);
                            Ext.getCmp('mail-ext-progressbar').setWidth(width - 30);
                        }
                    }
                }
            ]
        })
    );

    MailFolders.superclass.constructor.call(this, {
        id: 'mail-ext-folders-tree',
        autoWidth: true,
        autoHeight: true,
        collapsible: true,
        margins: '0 0 5 5',
        cmargins: '0 5 5 5',
        rootVisible: false,
        lines: false,
        animate: true,
        enableDD: true,
        ddGroup: 'folders-tree-ddgrop',
        dropConfig: {ddGroup: 'folders-tree-ddgrop', appendOnly: true},
        autoScroll: true,
        root: new Ext.tree.AsyncTreeNode(),

        loader: new Ext.tree.TreeLoader({
            clearOnLoad: false,
            preloadChildren: false,
            collapseFirst: false,
            dataUrl: topBaseUrl + '/mailer/index/folders-list',

            listeners: {
                beforeload: function(loader, nodes) {
                    var tree = Ext.getCmp('mail-ext-folders-tree');

                    // If there are no accounts -
                    // it is not needed to load folders list
                    if (mail_settings.accounts.length === 0) {
                        tree.showBlankTab(true);
                        return false;
                    }

                    // Check which account is selected
                    var loader = tree.getLoader();
                    loader.baseParams = loader.baseParams || {};

                    var params = {
                        account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId())
                    };
                    Ext.apply(loader.baseParams, params);
                },

                load: function(loader, nodes) {
                    var tree = Ext.getCmp('mail-ext-folders-tree');

                    // Automatically select first folder
                    if (nodes.childNodes.length > 0) {
                        // If there are folders - select the first one
                        if (!empty(selected_folder_to_refresh)) {
                            tree.getRootNode().findChild('real_folder_id', selected_folder_to_refresh, true).select();

                            selected_folder_to_refresh = '';
                        }
                        else
                            nodes.firstChild.select();

                        // Update Inbox label
                        tree.getRootNode().cascade(function(folder) {
                            tree.updateFolderLabel(folder);
                        });
                    } else {
                        tree.showBlankTab(false);
                    }
                }
            }
        })
    });


    this.addEvents({folderselect: true});
    this.getSelectionModel().on({
        'selectionchange' : function(sm, node) {
            var grid = Ext.getCmp('mail-ext-emails-grid');
            var preview = Ext.getCmp('mail-ext-emails-grid-preview');

            if (node !== null && empty(node.attributes.selectable))
            {
                grid.getEl().mask('<div align="center">Unable to display the folder. This folder cannot contain items.<br/>This is most likely a limitation of your IMAP server.</div>');
                grid.store.removeAll();

                if(preview && preview.getEl()) {
                    preview.getEl().mask();
                    preview.clear();
                }

                return;
            } else {
                grid.getEl().unmask();
                if(preview && preview.getEl()) {
                    preview.getEl().unmask();
                }
            }

            if (node) {
                this.fireEvent('folderselect', node);
            }
        },
        scope: this
    });

    this.on('contextmenu', this.onContextMenu, this);
    this.on('textchange', this.updateTabLabel, this);
    this.on('beforenodedrop', this.dragAndDrop, this);
    this.on('startdrag', this.startDrag, this);
    this.on('enddrag', this.endDrag, this);
    this.on('movenode', this.MoveFolder, this);
    this.on('afterrender', this.onAfterRender, this);
    this.on('expandnode', this.onExpandNode, this);
};

Ext.extend(MailFolders, Ext.tree.TreePanel, {
    onAfterRender: function () {
        setTimeout(
            function () {
                // We need to be sure that progressbar will be visible, e.g. for FF
                // because it shows the url/status in that coreer
                var bottomGap = 40;

                var maxFoldersHeight = $('#mail-left-side').outerHeight() - ($('#mail-toolbar-account-group').outerHeight() + $('#mail-ext-folders-tree-bbar').outerHeight() + bottomGap);
                maxFoldersHeight = maxFoldersHeight <= 100 ? 100 : maxFoldersHeight;

                $('#mail-ext-folders-tree .x-panel-body').css({
                    "max-height": maxFoldersHeight + 'px'
                });

                Ext.getCmp('mail-main-toolbar').setAutoCheck();
            }, 100
        );
    },

    onExpandNode: function(node) {
        var tree = Ext.getCmp('mail-ext-folders-tree');
        node.cascade(function(folder) {
            tree.updateFolderLabel(folder);
        });
    },

    showBlankTab: function(booAccountsAreEmpty) {
        // Clear all previously loaded mails
        // We need use this method instead of removeAll
        // because count + pages must be reset too
        Ext.getCmp('mail-ext-emails-grid').store.loadData({
            'mails': [],
            'totalCount': 0,
            'totalUnread': 0
        });
    },

    updateFolderLabel: function(folder, intUnreadCount) {
        if (folder) {
            if (typeof(intUnreadCount) === 'undefined' ||
                intUnreadCount === null) {
                intUnreadCount = folder.attributes.unread_count;
            }

            var icon = '';
            if (folder.attributes.folder_label) {
                if (folder.attributes.folder_label.toLowerCase() === 'inbox') {
                    icon = '<i class="las la-inbox las-icon-folder" style="vertical-align: text-bottom;"></i>';
                } else if (folder.attributes.folder_label.toLowerCase() === 'trash') {
                    icon = '<i class="las la-trash-alt las-icon-folder"></i>';
                } else if (folder.attributes.folder_label.toLowerCase() === 'sent') {
                    icon = '<i class="las la-paper-plane las-icon-folder"></i>';
                } else if (folder.attributes.folder_label.toLowerCase() === 'drafts') {
                    icon = '<i class="las la-file las-icon-folder"></i>';
                } else {
                    icon = '<i class="las la-folder las-icon-folder"></i>';
                }
            }



            var newLabel = String.format(
                intUnreadCount > 0 ? '{0} {1} ({2})' : '{0} {1}',
                icon, folder.attributes.folder_label, intUnreadCount
            );

            // Update label
            folder.attributes.unread_count = intUnreadCount;
            folder.setText(newLabel);

            if (parseInt(intUnreadCount, 10))
                folder.ui.addClass('mail-unread-messages-folder');
            else
                folder.ui.removeClass('mail-unread-messages-folder');
        }
    },

    // Update tab's title with icon
    // only if node is selected (active)
    updateTabLabel: function(node) {
        // if (node.isSelected()) {
        //     var first_tab = Ext.getCmp('mail-main-tab');
        //     if (first_tab) {
        //         first_tab.setTitle('Email > ' + node.attributes.text.replace(/<\/?[^>]+(>|$)/g, ''));
        //     }
        // }
    },

    // when user moves/deletes mail, we need to update unread messages count ib both folders. So, let's do it :)
    // ATTENTION: this func was written on 1st April, so it can contain some inadequate code. Please, be patient.
    updateFoldersLabelsAfterMoveDelete: function(src_folder_id, dst_folder_id, mail_ids) {
        var src_folder;
        if (src_folder_id === null)
            src_folder = this.getSelectionModel().getSelectedNode();
        else
            src_folder = this.getRootNode().findChild('folder_id', src_folder_id);

        var dst_folder;
        if (dst_folder_id == 'trash') {
            dst_folder = this.getRootNode().findChild('folder_id', dst_folder_id);
        } else {
            dst_folder = this.getRootNode().findChild('real_folder_id', dst_folder_id, true);
        }

        var grid = Ext.getCmp('mail-ext-emails-grid');

        var deleted_unread_count = 0;
        for (var i = 0; i < mail_ids.length; i++) {
            if (grid.store.getAt(grid.store.findExact('mail_id', mail_ids[i])).data.mail_unread) {
                deleted_unread_count++;
            }
        }

        if (dst_folder != src_folder) // if we are not in Trash
            this.updateFolderLabel(dst_folder, parseInt(dst_folder.attributes.unread_count, 10) + deleted_unread_count);

        this.updateFolderLabel(src_folder, parseInt(src_folder.attributes.unread_count, 10) - deleted_unread_count);
    },

    dragAndDrop: function(e) {
        if (e.data.node && e.data.node.attributes.is_default && e.point == 'append') {
            return false;
        }

        // e.data.selections is the array of selected records
        if (Ext.isArray(e.data.selections))
        {
            // reset cancel flag
            e.cancel = false;

            var grid = Ext.getCmp('mail-ext-emails-grid');

            // setup dropNode (it can be array of nodes)
            var r;
            var mail_ids = [];
            for (var i = 0; i < e.data.selections.length; i++)
            {
                // get record from selections
                r = e.data.selections[i];

                mail_ids.push(r.data.mail_id);
            }

            // INC unread count in Trash, DEC unread count in src folder
            this.updateFoldersLabelsAfterMoveDelete(null, e.target.attributes.real_folder_id, mail_ids);

            for (i = 0; i < e.data.selections.length; i++)
            {
                // get record from selections
                r = e.data.selections[i];

                // just remove this letter from mails grid
                grid.store.remove(r);
            }

            // and now send request to move these mails to other folder
            this.moveMailsToFolder(mail_ids, e.target.attributes.real_folder_id);

            // we want Ext to complete the drop, thus return true
            return true;
        }
    },

    startDrag: function(tree) {
        // allow move folder up/down
        tree.dropZone.appendOnly = false;
    },

    endDrag: function(tree, node) {
        // disallow move folder up/down
        tree.dropZone.appendOnly = true;
        
        node.select();
    },

    MoveFolder: function(tree, node, old_parent, new_parent, index) {
        var new_parent_in_tree = tree.getNodeById(new_parent.attributes.id);

        node.attributes.level = (new_parent_in_tree.isRoot) ? 0 : parseInt(new_parent_in_tree.attributes.level, 10) + 1;

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/move-folder',
            params: {
                account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId()),
                folder_id: Ext.encode(node.attributes.real_folder_id),
                parent_folder_id: Ext.encode(new_parent_in_tree.isRoot ? 0 : new_parent_in_tree.attributes.real_folder_id),
                order: Ext.encode(index)
            },

            success: function(res) {
                var result = Ext.decode(res.responseText);
                if (empty(result.success)) {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(result.msg);
                }
            },

            failure: function() {
                Ext.simpleConfirmation.error(
                    'Internal server error. Please try again.'
                );
            }
        });
    },

    updateCurrentFolderLabel: function(unreadCount, booIncrement, booRefresh) {
        var folder = this.getSelectionModel().getSelectedNode();

        if (folder) {
            // Get unread emails count
            if (typeof(unreadCount) === 'undefined' ||
                unreadCount === null) {
                unreadCount = folder.attributes.unread_count;
            }

            if (booIncrement) {
                unreadCount = folder.attributes.unread_count + unreadCount;
            }

            // Update label
            this.updateFolderLabel(folder, unreadCount);

            if (folder.isSelected() &&
                folder.attributes.unread_count != unreadCount &&
                booRefresh !== false) {
                // Refresh emails grid if all mails were marked
                Ext.getCmp('mail-ext-emails-grid').store.reload();
            }
        }
    },

    // Clear trash
    clearTrash: function() {
        // Show mask
        Ext.getBody().mask('Clearing...');

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/clear-trash',
            params: {
                account_id: Ext.getCmp('mail-main-toolbar').getSelectedAccountId()
            },

            success: function(res) {
                var result = Ext.decode(res.responseText);
                if (result.success) {
                    Ext.getBody().unmask();

                    // reload mails list
                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    grid.store.reload();

                    // Update trash folder:
                    // 1. Update label
                    // 2. Remove all sub folders
                    var tree = Ext.getCmp('mail-ext-folders-tree');
                    var trash = tree.getRootNode().findChild('folder_id', 'trash');
                    tree.updateFolderLabel(trash, 0);

                    while(trash.firstChild)
                    {
                        trash.removeChild(trash.firstChild);
                    }
                } else {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(result.msg);
                }
            },

            failure: function() {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(
                    'Internal server error. Please try again.'
                );
            }
        });
    },

    // move mail(s) to folder
    moveMailsToFolder: function(mails_array, folder_id) {
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/move-mails',
            params: {
                account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId()),
                mails_array: Ext.encode(mails_array),
                folder_id: Ext.encode(folder_id)
            },

            success: function(res) {
                var result = Ext.decode(res.responseText);
                if (empty(result.success)) {
                    Ext.simpleConfirmation.error(result.msg);
                } else {

                    // select first element in grid
                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    if (grid.store.getCount() > 0) {
                        grid.getSelectionModel().selectFirstRow();
                    } else {
                        grid.store.reload();
                    }
                }
            },

            failure: function() {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(
                    'Internal server error. Please try again.'
                );
            }
        });
    },

    onContextMenu: function(node, e) {
        if (!this.menu) { // create context menu on first right click
            this.menu = new Ext.menu.Menu({
                cls: 'no-icon-menu',
                items: [{
                    id: 'mail-folder-menu-open',
                    text: '<i class="las la-folder-open"></i>' + _('Open Folder'),
                    scope: this,
                    handler: function() {
                        this.ctxNode.select();
                    }
                },'-', {
                    id: 'mail-folder-menu-add',
                    text: '<i class="las la-folder-plus"></i>' + _('Create Sub Folder'),
                    handler: function() {
                        this.showAddFolderWindow(this.ctxNode.attributes.real_folder_id, this.ctxNode.attributes.id, parseInt(this.ctxNode.attributes.level, 10) + 1);
                    },
                    scope: this
                },{
                    id: 'mail-folder-menu-rename',
                    text: '<i class="las la-edit"></i>' + _('Rename Folder'),
                    handler: function() {
                        this.showRenameFolderWindow(this.ctxNode.attributes.real_folder_id, this.ctxNode.attributes.folder_label, this.ctxNode.id);
                    },
                    scope: this
                },{
                    id: 'mail-folder-menu-delete',
                    text: '<i class="las la-trash"></i>' + _('Delete Folder'),
                    scope: this,
                    handler: function() {
                        if (this.ctxNode) {
                        this.removeFolder(this.ctxNode);
                            this.ctxNode.ui.removeClass('x-node-ctx');
                        this.ctxNode = null;
                    }
                    }
                },'-', {
                    id: 'mail-trash-clear',
                    text: '<i class="las la-trash-alt"></i>' + _('Clear trash'),
                    handler: this.clearTrash,
                    scope: this
                }]
            });
            this.menu.on('hide', this.onContextHide, this);
        }

        if (this.ctxNode) {
            this.ctxNode.ui.removeClass('x-node-ctx');
            this.ctxNode = null;
        }

        // if this is not trash => hide "Clear trash" item
        if (node.attributes.folder_id != 'trash') {
            this.menu.items.get('mail-trash-clear').hide();
        } else {
            this.menu.items.get('mail-trash-clear').show();
        }

        // if this is basic folder or not selectable folder => hide "Remove" item
        if (node.attributes.folder_id === '0' && !empty(node.attributes.selectable)) {
            this.menu.items.get('mail-folder-menu-delete').show();
        } else {
            this.menu.items.get('mail-folder-menu-delete').hide();
        }

        // if this is not selectable folder => hide "Rename" item
        if (!empty(node.attributes.selectable)) {
            this.menu.items.get('mail-folder-menu-rename').show();
        } else {
            this.menu.items.get('mail-folder-menu-rename').hide();
        }

        // hide all separators, which we don't need
        
        var items_to_show = [];
        var items_to_hide = [];
        
        // collect all visible items and separators
        this.menu.items.each(function(){
            if (!this.hidden || this.activeClass=='')
                items_to_show.push(this);
                
            if (this.activeClass=='')
                this.show();
        });
        
        // remove separators from the start
        i=0;
        while (i<items_to_show.length)
        {
            if (items_to_show[i].activeClass=='')
                items_to_hide.push(items_to_show[i].id);
            else
                break;
            
            i++;
        }
        
        // remove separators from the end
        i=items_to_show.length-1;
        while (i>=0)
        {
            if (items_to_show[i].activeClass=='')
                items_to_hide.push(items_to_show[i].id);
            else
                break;
            
            i--;
        }
        
        // remove double separators
        var is_prev_separator=false;
        for (i=0; i<items_to_show.length; i++)
        {
            if (is_prev_separator && items_to_show[i].activeClass=='')
                items_to_hide.push(items_to_show[i].id);
                
            is_prev_separator=items_to_show[i].activeClass=='';
        }
        
        // AT LAST hide those separators, that we decided to hide
        for (i=0; i<items_to_hide.length; i++)
            this.menu.items.get(items_to_hide[i]).hide();

        this.ctxNode = node;
        if (this.ctxNode) {
        this.ctxNode.ui.addClass('x-node-ctx');
        }

        // Disable or enable 'Open folder' menu item
        var fOpen = this.menu.items.get('mail-folder-menu-open');
        fOpen.setDisabled(node.isSelected());

        this.menu.showAt(e.getXY());
    },

    onContextHide: function() {
        if (this.ctxNode) {
            this.ctxNode.ui.removeClass('x-node-ctx');
            this.ctxNode = null;
        }
    },

    showAddFolderWindow: function(parent_node_id, node_id, level) {
        Ext.Msg.show({
            title: 'New Folder',
            msg: 'Folder name:',
            buttons: Ext.Msg.OKCANCEL,
            icon: 'dlg-folder-new',
            prompt: true,
            fn: function(btn, text) {
                if (btn == 'ok') {
                    //validate folder name
                    /*if (/^([a-zA-Z0-9_-])([a-zA-Z0-9_ -]){0,254}$/.test(text) === false) {
                        Ext.simpleConfirmation.error('Incorrect folder name (only letters, numbers, spaces, dashes and underscores allowed)');
                        return false;
                    }*/

                    // Send request to save new folder in DB
                    Ext.Ajax.request({
                        url: topBaseUrl + '/mailer/index/create-folder',
                        params: {
                            account_id: Ext.getCmp('mail-main-toolbar').getSelectedAccountId(),
                            parent_folder_id: parent_node_id,
                            level: level,
                            new_name: Ext.encode(text)
                        },
                        success: function(res) {
                            var result = Ext.decode(res.responseText);
                            if (result.success) {
                                var tree = Ext.getCmp('mail-ext-folders-tree');
                                tree.getRootNode().reload();
                            } else {
                                Ext.simpleConfirmation.error(result.msg);
                            }
                        },
                        failure: function() {
                            Ext.simpleConfirmation.error('Folder can\'t be created. Please try again later.');
                            Ext.getBody().unmask();
                        }
                    });
                }
            }
        });
    },

    showRenameFolderWindow: function(real_folder_id, old_folder_name, node_id) {
        //prompt to enter new folder name
        Ext.Msg.prompt('Rename folder', 'Folder name:', function(btn, text) {
        if (btn == 'ok') {
            //validate folder name
            /*if (/^([a-zA-Z0-9_-])([a-zA-Z0-9_ -]){0,254}$/.test(text) === false) {
                Ext.simpleConfirmation.error('Incorrect folder name (only letters, numbers, spaces, dashes and underscores allowed)');
                return false;
            }*/

            //send request to save filename in DB
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/rename-folder',
            params: {
              account_id: Ext.getCmp('mail-main-toolbar').getSelectedAccountId(),
              real_folder_id: real_folder_id,
              new_name: Ext.encode(text)
            },
            success: function(res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    //update folder name
                    var tree = Ext.getCmp('mail-ext-folders-tree');
                    var current_folder = tree.getNodeById(node_id);

                    current_folder.attributes.folder_label = text;

                    Ext.getCmp('mail-ext-folders-tree').updateFolderLabel(current_folder, current_folder.attributes.unread_count);
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                }
            },
            failure: function() {
                Ext.simpleConfirmation.error('Folder can\'t be renamed. Please try again later.');
                Ext.getBody().unmask();
            }
            });
        }
        return true;
        }, null, false, old_folder_name);
    },

    selectFolder: function(folder_id) {
        this.getNodeById(folder_id).select();
    },

    // private
    // Send request to server to delete folder
    sendRequestToDeleteFolder: function(real_folder_id) {
        Ext.getBody().mask('Removing...');

        var selAccountId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/delete-folder',
            params: {
                account_id: Ext.encode(selAccountId),
                real_folder_id: Ext.encode(real_folder_id)
            },

            success: function(res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    // reload folders tree
                     Ext.getCmp('mail-ext-folders-tree').getRootNode().reload();
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                }
            },

            failure: function() {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(
                    'Internal server error. Please try again.'
                );
            }
        });
    },

    removeFolder: function(node) {
        var real_folder_id = node.attributes.real_folder_id;

        // if this folder is in Trash => no need to ask, whether user wants to delete folder
        if (node.parentNode.attributes.folder_id == 'trash') {
            Ext.getCmp('mail-ext-folders-tree').sendRequestToDeleteFolder(real_folder_id);
            return;
        }

        var question = String.format(
            'Are you sure want to delete folder "<i>{0}</i>"?',
            node.text.replace(/<\/?[^>]+>/gi, '').trim()
        );

        Ext.Msg.confirm('Please confirm', question, function(btn) {
            if (btn == 'yes') {
                Ext.getCmp('mail-ext-folders-tree').sendRequestToDeleteFolder(real_folder_id);
            }
        });
    },

    // prevent the default context menu when you miss the node
    afterRender: function() {
        MailFolders.superclass.afterRender.call(this);
        this.el.on('contextmenu', function(e) {
            e.preventDefault();
        });

        // Reload folders list
        this.getRootNode().reload();
    }
});

Ext.reg('appMailFolders', MailFolders);
