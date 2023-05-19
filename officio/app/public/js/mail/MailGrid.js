// This global variable is used to mark selected mail as read
// in several seconds delay
var timeoutMarkAsRead;

MailGrid = function(viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);

    // Pointer to ajax request, so can be used to determine if ajax request to check emails in folder is active
    this.ajaxRequestToCheckFolder = null;

    this.store = new Ext.data.Store({
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/mailer/index/mails-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'mails',
            totalProperty: 'totalCount',
            idProperty: 'mail_id',
            fields: [
                'mail_id',
                'mail_id_folder',
                'mail_from',
                'mail_to',
                'mail_cc',
                'mail_bcc',
                'mail_subject',
                {name: 'mail_date', type: 'date', dateFormat: 'Y-m-d h:i:s'},
                'mail_body',
                {name: 'mail_unread', type: 'boolean'},
                {name: 'mail_replied', type: 'boolean'},
                {name: 'mail_forwarded', type: 'boolean'},
                {name: 'mail_flag', type: 'int'},
                'has_attachment',
                'attachments',
                'is_downloaded'
            ]
        })
    });
    this.store.setDefaultSort('mail_date', 'DESC');

    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            // @NOTE: if column will be moved (e.g. added new columns) -
            // update also getFlagColumnNumber function
            header: '<i class="las la-flag"></i>',
            dataIndex: 'mail_flag',
            width: 30,
            renderer: this.formatFlag.createDelegate(this),
            sortable: true,
            fixed: true
        }, {
            header: 'From',
            dataIndex: 'mail_from',
            width: 100,
            sortable: true
        }, {
            header: 'Subject',
            dataIndex: 'mail_subject',
            sortable: true,
            width: 100,
            renderer: this.formatTitle.createDelegate(this)
        }, {
            header: 'To',
            dataIndex: 'mail_to',
            width: 100,
            hidden: true,
            sortable: true
        },{
            header: '<div class="mail-with-attachment">&nbsp;</div>',
            dataIndex: 'has_attachment',
            width: 40,
            renderer: this.formatAttachment,
            sortable: true,
            fixed: true
        }, {
            header: 'Date',
            dataIndex: 'mail_date',
            width: 180,
            renderer: this.formatDate,
            sortable: true,
            fixed: true
        }
    ];

    var currentAccount_key = 0;
    var currentId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();
    for (var i = 0; i < mail_settings.accounts.length; i++)
        if (currentId === mail_settings.accounts[i].account_id)
            currentAccount_key = i;

    MailGrid.superclass.constructor.call(this, {
        id: 'mail-ext-emails-grid',
        region: 'center',
        enableDragDrop: true,
        ddGroup: 'folders-tree-ddgrop',
        ddText: 'Place this email(s)',

        sm: sm,

        viewConfig: {
            forceFit: true,
            enableRowBody: true,
            showPreview: config.showSummary,
            getRowClass: this.applyRowClass
        },

        bbar: new Ext.PagingToolbar({
            hidden: false,
            pageSize: ((currentAccount_key !== undefined && mail_settings['accounts'].length>0) ? parseInt(mail_settings['accounts'][currentAccount_key]['per_page'], 10) : 50),
            store: this.store,
            displayInfo: true,
            displayMsg: 'Emails {0} - {1} of {2}',
            emptyMsg: 'No emails to display'
        })
    });

    // this.on('rowcontextmenu', this.onContextClick, this);
    this.on('cellclick', this.onCellClick, this);
    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.on('render', this.initQuickSearch, this);
    this.getSelectionModel().on('rowselect', this.selectionChange, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(MailGrid, Ext.grid.GridPanel, {
    // Quick search needs to know where it will search ;)
    initQuickSearch: function() {
        var quickSearch = Ext.getCmp('mail-toolbar-quick-search');
        if (quickSearch) {
            quickSearch.setStore(this.store);
        }
    },

    // If there is selected row
    // and that row wasn't selected by context menu -
    // mark email as read in several seconds
    selectionChange: function(sm, index, record) {
        clearTimeout(timeoutMarkAsRead);
        if (record && !this.ctxRow) {
            if (record.data.mail_unread &&
               this.viewer.getSettings('mail_preview_mode') != 'hide') {
                var oGrid = this;

                timeoutMarkAsRead = setTimeout(
                    function() {
                        return oGrid.markAsReadUnread(record, false);
                    },
                    3000
                );
            }
        }
    },

    updateToolbarButtons: function() {
        var sel = this.view.grid.getSelectionModel().getSelections();
        var booIsSelectedOneMail = sel.length == 1;
        var booIsSelectedAtLeastOneMail = sel.length >= 1;

        Ext.getCmp('mail-toolbar-reply-mail').setDisabled(!booIsSelectedOneMail);
        Ext.getCmp('mail-toolbar-reply-all-mail').setDisabled(!booIsSelectedOneMail);
        Ext.getCmp('mail-toolbar-forward-mail').setDisabled(!booIsSelectedOneMail);
        Ext.getCmp('mail-toolbar-mark-as').setDisabled(!booIsSelectedAtLeastOneMail);
        Ext.getCmp('mail-toolbar-save').setDisabled(!booIsSelectedAtLeastOneMail);
        Ext.getCmp('mail-toolbar-delete-selected-mail').setDisabled(!booIsSelectedAtLeastOneMail);
        Ext.getCmp('mail-toolbar-print').setDisabled(!booIsSelectedOneMail);
    },


    // Column 'flag' number of the grid
    getFlagColumnNumber: function() {
        return 1;
    },

    onCellClick: function(grid, rowIndex, cellIndex, e) {
        if (cellIndex == grid.getFlagColumnNumber()) {
            this.setMailFlag(grid, rowIndex, cellIndex, e);
        }
    },

    onCellRightClick: function(grid, rowIndex, cellIndex, e) {
        if (cellIndex == grid.getFlagColumnNumber()) {
            // Show 'Flag' context menu
            this.showFlagContextMenu(grid, rowIndex, e);
        } else {
            // Show 'General' context menu
            this.onContextClick(grid, rowIndex, e);
        }
    },

    updateMailRecordFlag: function(record, intFlag) {
        record.beginEdit();
        record.set('mail_flag', intFlag);
        record.endEdit();
        this.store.commitChanges();
    },

    sendRequestToUpdateFlag: function(mailId, strFlag) {
        // Get record by email id
        var record;
        var grid = this;
        var idx = grid.store.findExact('mail_id', mailId);
        if (idx != -1) {
            record = this.store.getAt(idx);
        }

        if (record) {
            // Show 'loading' image during request
            var currentFlag = record.data['mail_flag'];
            grid.updateMailRecordFlag(record, this.getIntFlagByString('loading'));

            // Send request to update a flag
            Ext.Ajax.request({
                url: topBaseUrl + '/mailer/index/update-mail-flag',
                params: {
                    'mail_id':   Ext.encode(mailId),
                    'mail_flag': Ext.encode(strFlag)
                },

                success: function(res) {
                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        // Update email record with new flag
                        grid.updateMailRecordFlag(record, grid.getIntFlagByString(strFlag));
                    } else {
                        // Otherwise show error
                        Ext.simpleConfirmation.error(result.msg);
                        grid.updateMailRecordFlag(record, currentFlag);
                    }
                },

                failure: function() {
                    Ext.simpleConfirmation.error('Internal server error. Please try again.');
                    grid.updateMailRecordFlag(record, currentFlag);
                }
            });
        }
    },

    // Update mail flag for selected mail item
    setMailFlag: function(grid, rowIndex) {
        var rec = grid.store.getAt(rowIndex);

        // If there is no flag - set default flag
        // If there is a flag - remove it (set to blank)
        var defaultFlag = 'complete';
        this.sendRequestToUpdateFlag(
            rec.data['mail_id'],
            this.compareFlags(rec.data['mail_flag'], 'empty') ? defaultFlag : 'empty'
        );
    },

    // Change mail flag to specific
    changeFlag: function(menuItem, booChecked) {
        if (booChecked) {
            var currentFlag = this.ctxFlagRecord.data['mail_flag'];
            var newFlag = menuItem.mailFlag;

            if (!this.compareFlags(currentFlag, newFlag)) {
                // Send request to update the flag
                this.sendRequestToUpdateFlag(
                    this.ctxFlagRecord.data['mail_id'],
                    newFlag
                );
            }
        }
    },

    // Identify int flag id and return string id
    getStringFlagById: function(intFlag) {
        var strFlag;
        switch (intFlag) {
            case 1:       strFlag = 'red';      break;
            case 2:       strFlag = 'blue';     break;
            case 3:       strFlag = 'yellow';   break;
            case 4:       strFlag = 'green';    break;
            case 5:       strFlag = 'orange';   break;
            case 6:       strFlag = 'purple';   break;
            case 7:       strFlag = 'complete'; break;
            case 100500:  strFlag = 'loading';  break;
            default:      strFlag = 'empty';    break;
        }

        return strFlag;
    },

    // Identify string flag id and return int id
    getIntFlagByString: function(strFlag) {
        var intFlag;
        switch (strFlag) {
            case 'red':      intFlag = 1; break;
            case 'blue':     intFlag = 2; break;
            case 'yellow':   intFlag = 3; break;
            case 'green':    intFlag = 4; break;
            case 'orange':   intFlag = 5; break;
            case 'purple':   intFlag = 6; break;
            case 'complete': intFlag = 7; break;
            case 'loading':  intFlag = 100500; break;
            default:         intFlag = 0; break;
        }

        return intFlag;
    },

    // Return true if flags are equal
    compareFlags: function(intFlag, strFlag) {
        return strFlag == this.getStringFlagById(intFlag);
    },

    // Show 'flag' context menu when right click on 'flag' column
    showFlagContextMenu: function(grid, rowIndex, e) {
        // Save record we show a context menu to
        this.ctxFlagRecord = grid.store.getAt(rowIndex);
        var currentFlag = this.ctxFlagRecord.data['mail_flag'];

        if (!this.flagMenu) {
            // Create context menu on first right click
            var arrFlagItems = [{
                text: '<i class="lar la-flag mail-flag-red"></i>' + 'Red Flag',
                flag: 'red'
            }, {
                text: '<i class="lar la-flag mail-flag-blue"></i>' + 'Blue Flag',
                flag: 'blue'
            }, {
                text: '<i class="lar la-flag mail-flag-yellow"></i></i>' + 'Yellow Flag',
                flag: 'yellow'
            }, {
                text: '<i class="lar la-flag mail-flag-green"></i>' + 'Green Flag',
                flag: 'green'
            }, {
                text: '<i class="lar la-flag mail-flag-orange"></i></i>' + 'Orange Flag',
                flag: 'orange'
            }, {
                text: '<i class="lar la-flag mail-flag-purple"></i>' + 'Purple Flag',
                flag: 'purple'
            }, {text: '-'}, {
                text: '<i class="lar la-flag mail-flag-gray"></i>' + 'Clear Flag',
                flag: 'empty'
            }];

            var arrMenuItems = [];
            Ext.each(arrFlagItems, function(item) {
                if (item.text === '-') {
                    arrMenuItems.push('-');
                } else {
                    arrMenuItems.push({
                        text:         item.text,
                        'mailFlag':   item.flag,
                        checked:      item.flag === 'empty' ? false : grid.compareFlags(currentFlag, item.flag),
                        group:        'mail-flag-group',
                        checkHandler: grid.changeFlag.createDelegate(grid)
                    });
                }
            });

            this.flagMenu = new Ext.menu.Menu({
                cls:           'no-icon-menu',
                showSeparator: false,
                items: arrMenuItems
            });
        } else {
            // Select menu element in relation to currently selected menu item's flag
            this.flagMenu.items.each(function(menuItem) {
                if (menuItem.getXType() == 'menucheckitem') {
                    menuItem.setChecked(
                        grid.compareFlags(currentFlag, menuItem.mailFlag) && menuItem.mailFlag != 'empty',
                        true
                    );
                }
            });
        }

        // Show context menu
        e.stopEvent();
        this.flagMenu.showAt(e.getXY());
    },


    // In relation to mail item flag - show an icon
    formatFlag: function(flag) {
        var stringFlag =this.getStringFlagById(flag);
        var flagClass = 'lar la-flag mail-flag-' + stringFlag;
        if (stringFlag == 'complete') {
            flagClass = 'las la-check mail-flag-' + stringFlag;
        }

        return '<i class="main-flag-icon mail-flag-icon ' + flagClass + '" title="Toggle a flag"></i>';
    },


    // Show context menu
    onContextClick: function(grid, index, e) {
        if (!this.menu) { // create context menu on first right click
            this.menu = new Ext.menu.Menu({
                id: 'grid-ctx',
                items: [{
                    text: '<i class="las la-search"></i>' + _('View in new tab'),
                    scope: this,
                    handler: function() {
                        this.viewer.openTab(this.ctxRecord);
                    }
                },{
                    text: '<i class="lar la-save"></i>' + _('Save to Case'),
                    scope: this,
                    handler: function() {
                        Ext.getCmp('mail-main-toolbar').showSaveDialog();
                    }
                }, {
                    text: 'Mark',
                    scope: this,
                    menu: {
                        items: [
                            {
                                id: 'mail-menu-item-read',
                                text: 'As read',
                                checked: false,
                                scope: this,
                                handler: function() {
                                    this.markAsReadUnread(this.ctxRecord);
                                }
                            }, {
                                text: 'All emails as read (in the current folder)',
                                scope: this,
                                handler: function() {
                                    this.markAllMailsAsRread();
                                }
                            }, {
                                text: 'All emails as unread (in the current folder)',
                                scope: this,
                                handler: function() {
                                    this.markAllMailsAsUnread();
                                }
                            }
                        ]
                    }
                },{
                    text: '<i class="las la-trash"></i>' + _('Delete'),
                    scope: this,
                    handler: function() {
                        var tree = Ext.getCmp('mail-ext-folders-tree');
                        var record_to_delete = this.ctxRecord;

                        var boo_trash = tree.getSelectionModel().getSelectedNode().attributes.folder_id == 'trash';

                        // show confirmation window only if we're not in trash folder
                        if (!boo_trash)
                        {
                        Ext.Msg.confirm('Please confirm', 'Are you sure want to delete this message?', function(btn, text) {
                                if (btn == 'yes')
                                {
                                Ext.getCmp('mail-ext-emails-grid').deleteMail(record_to_delete);

                                    // INC unread count in Trash, DEC unread count in src folder
                                    tree.updateFoldersLabelsAfterMoveDelete(null, 'trash', new Array(record_to_delete.data.mail_id));
                            }
                        });
                    }
                        else
                        {
                            Ext.getCmp('mail-ext-emails-grid').deleteMail(record_to_delete);

                            // INC unread count in Trash, DEC unread count in src folder
                            tree.updateFoldersLabelsAfterMoveDelete(null, 'trash', new Array(record_to_delete.data.mail_id));
                        }
                    }
                },'-', {
                    text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    scope: this,
                    handler: function() {
                        this.ctxRow = null;
                        this.store.reload();
                    }
                }]
            });
            this.menu.on('hide', this.onContextHide, this);
        }
        e.stopEvent();
        this.ctxRow = this.view.getRow(index);
        this.ctxRecord = this.store.getAt(index);

        // Select row which was selected by right click
        this.view.grid.getSelectionModel().selectRow(index);

        // Menu item update checkbox in relation to record attribute
        Ext.getCmp('mail-menu-item-read').setChecked(
            !this.ctxRecord.data.mail_unread
        );

        this.menu.showAt(e.getXY());
    },

    // Reset custom variable on context menu hide
    onContextHide: function() {
        if (this.ctxRow) {
            this.ctxRow = null;
        }
    },

    // private
    // Send request to server to mark email(s) as read or unread
    sendRequestToUpdateMail: function(booMarkAsRead, record, booShowMask) {
        // Show mask only if context menu was used
        if (booShowMask !== false) {
            Ext.getBody().mask('Saving...');
        }

        var selAccountId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();
        var tree = Ext.getCmp('mail-ext-folders-tree');

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/mark-mail-as-read',
            params: {
                account_id:   Ext.encode(selAccountId),
                folder_id:    Ext.encode(tree ? tree.getSelectionModel().getSelectedNode().attributes.id : 0),
                mail_id:      Ext.encode(empty(record) ? 0 : record.data.mail_id),
                mail_as_read: Ext.encode(booMarkAsRead)
            },

            success: function(res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {

                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    var updateUnread = 0;

                    // In emergency we don't need update folder's label
                    var booUpdate = false;

                    if (!empty(record)) {
                        // Mark selected row as read/unread
                        var rowIndex = grid.getStore().indexOf(record);
                        var selRow = null;
                        if (rowIndex >= 0) {
                            selRow = grid.view.getRow(rowIndex);
                        }

                        if (selRow && Ext.fly(selRow)) {
                            if (booMarkAsRead) {
                                Ext.fly(selRow).removeClass('mail-item-unread');
                                Ext.fly(selRow).addClass('mail-item-read')
                            } else {
                                Ext.fly(selRow).removeClass('mail-item-read');
                                Ext.fly(selRow).addClass('mail-item-unread');
                            }

                            // Update record's data
                            record.data.mail_unread = !booMarkAsRead;

                            // Update Folder too
                            // (decrease or increase unread mails number)
                            updateUnread = booMarkAsRead ? -1 : 1;

                            booUpdate = true;
                        }
                    } else {
                        // All records were marked as read -
                        // so simply refresh the list
                        grid.store.reload();
                        booUpdate = true;
                    }

                    if (booUpdate && tree) {
                        // Update Folder too
                        tree.updateCurrentFolderLabel(
                            updateUnread, true, false
                        );
                    }

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

    // private
    // Send request to server to delete email
    sendRequestToDeleteMail: function(record) {
        // Show mask
        Ext.getBody().mask('Deleting...');

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/delete',
            params: {
                account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId()),
                mail_id: empty(record) ? 0 : Ext.encode(record.data.mail_id)
            },

            success: function(res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    grid.store.reload();
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

    // Mark all emails as read, send request to server
    markAllMailsAsRread: function() {
        this.sendRequestToUpdateMail(true);
    },

    // Mark all emails as unread, send request to server
    markAllMailsAsUnread: function() {
        this.sendRequestToUpdateMail(false);
    },

    // Mark specific emails as read or unread, send request to server
    markAsReadUnread: function(record, booShowMask) {
        clearTimeout(timeoutMarkAsRead);

        var booMarkAsRead = record.data.mail_unread;
        var grid = Ext.getCmp('mail-ext-emails-grid');
        grid.sendRequestToUpdateMail(booMarkAsRead, record, booShowMask);
    },

    // Delete a single mail
    deleteMail: function(record) {
        this.sendRequestToDeleteMail(record);
    },

    // Load emails list from server
    loadMails: function(folder_info, booDoNotDownloadEmails) {
        this.store.baseParams = {
            account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId()),
            folder_id: Ext.encode(folder_info.real_folder_id)
        };

        var activePage = this.getBottomToolbar().getPageData().activePage;

        var currentAccount_key = 0;
        var currentId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();
        for (var i = 0; i < mail_settings.accounts.length; i++) {
            if (currentId === mail_settings.accounts[i].account_id) {
                currentAccount_key = i;
            }
        }

        var booLimitExceeded = false;

        if (currentAccount_key !== undefined && mail_settings['accounts'].length > 0) {
            var limit = parseInt(mail_settings['accounts'][currentAccount_key]['per_page'], 10);
            if (limit < parseInt(folder_info.total_count, 10)) {
                booLimitExceeded = true;
            }
        }

        if (activePage == 1 || !booLimitExceeded) {
        this.store.load();
        }

        if (Ext.getCmp('mail-main-toolbar').getSelectedAccountType() == 'imap' && !booDoNotDownloadEmails) {
            this.store.on('load', this.downloadNewEmails.createDelegate(this, [folder_info]), this, {single: true});
        }
    },

    // Show status under the folders tree - which folders are now checked
    checkFoldersQueue: function(arrFolders) {
        var sb = Ext.getCmp('mail-folders-tree-bbar-text-panel');
        if (!sb || booCheckingInProgress) {
            return;
        }

        if (!arrFolders.length) {
            sb.clearStatus();
            sb.hide();
        } else {
            var statusText = String.format(
                '<div style="padding: 3px 0 0 25px">Checking {0}...</div>',
                arrFolders.length == 1 ? arrFolders[0]['folder_name'] : arrFolders.length + ' folders'
            );

            sb.setStatus({
                iconCls: 'x-status-busy',
                text: statusText
            });
            sb.show();
        }
    },

    // Download emails list for specific folder
    downloadNewEmails: function (oFolderInfo) {
        // If we check for new emails already - exit
        if (booCheckingInProgress) {
            return;
        }

        var thisGrid = this,
            accountId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId(),
            folderId = oFolderInfo.real_folder_id;

        // If request to check emails in folder was already sent - stop it and run a new one
        if (!empty(thisGrid.ajaxRequestToCheckFolder)) {
            Ext.Ajax.abort(thisGrid.ajaxRequestToCheckFolder);
        }

        // Show "Checking folder..." message in statusbar
        thisGrid.checkFoldersQueue([{
            'account_id':  accountId,
            'folder_id':   folderId,
            'folder_name': oFolderInfo.folder_label
        }]);

        thisGrid.ajaxRequestToCheckFolder = Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/check-emails-in-folder',
            params: {
                account_id: Ext.encode(accountId),
                folder_id: Ext.encode(folderId)
            },

            success: function (res) {
                var oTree = Ext.getCmp('mail-ext-folders-tree');
                var result = Ext.decode(res.responseText);

                if (oTree) {
                    var currentlySelectedFolder = oTree.getSelectionModel().getSelectedNode();

                    if (result.newEmailsCount > 0) {
                        if (currentlySelectedFolder && currentlySelectedFolder.attributes.real_folder_id == folderId) {
                            thisGrid.loadMails(oFolderInfo, true);
                        }
                    }
                    if (result.folders) {
                        for (var i = 0; i < result.folders.length; i++) {
                            currentlySelectedFolder.appendChild(result.folders[i]);
                        }
                    }
                }

                // Update statusbar that folder is not checked anymore
                thisGrid.checkFoldersQueue([]);
            },

            failure: function () {
                // Update statusbar that folder is not checked anymore
                thisGrid.checkFoldersQueue([]);
            }
        });
    },


    // Toggle preview section in the grid
    // Also save settings
    togglePreview: function(show) {
        this.viewer.saveSettings('mail_hide_summary', !show);

        this.view.showPreview = show;
        this.view.refresh();
    },

    // Apply custom class for row in relation to several criteria
    applyRowClass: function(record) {
        return record.data.mail_unread ? 'mail-item-unread ' : 'mail-item-read ';
    },

    // Format date column in relation to the row's data
    formatDate: function(date) {
        if (!date) {
            return '';
        }

        var str_date = '';
        var now = new Date();
        var d = now.clearTime(true);
        var notime = date.clearTime(true).getTime();

        if (notime == d.getTime()) {
            str_date = '<span class="hide_while_print">Today</span><span class="show_while_print" style="display:none;">'+date.dateFormat('M d,')+'</span> ' + date.dateFormat('H:i');
        }
        else {
            str_date = date.dateFormat('M d, Y H:i');
        }

        return '<div class="mail-date">' + str_date + '</div>';
    },

    // Format attachment column. Show image if has_attachment==true
    formatAttachment: function(has_attachment) {
        if (!has_attachment)
            return;

        return '<div class="mail-with-attachment">&nbsp;</div>';
    },

    // Format title/subject column in relation to the row's data
    formatTitle: function(subject, p, record) {
        var iconClass = 'las la-envelope-open';
        var iconTitle = '';
        if(record.data.mail_replied && record.data.mail_forwarded) {
            iconClass = 'las la-exchange-alt';
            iconTitle = _('Email was replied and forwarded');
        } else if (record.data.mail_replied) {
            iconClass = 'las la-reply';
            iconTitle = _('Email was replied');
        } else if (record.data.mail_forwarded) {
            iconClass = 'las la-redo';
            iconTitle = _('Email was forwarded');
        } else if (record.data.mail_unread) {
            iconClass = 'las la-envelope';
            iconTitle = _('Email is unread');
        }

        return String.format(
            '<div class="mail-item-subject"><i class="{0}" title="{1}"></i> {2}</div>',
            iconClass,
            iconTitle,
            subject ? subject : _('(no subject)')
        );
    }
});

Ext.reg('appMailGrid', MailGrid);
