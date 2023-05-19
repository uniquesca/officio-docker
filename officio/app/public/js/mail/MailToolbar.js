/* global topBaseUrl, is_superadmin, post_max_size, mail_settings */
/* jslint browser:true */

var checkEMailTimeout;
var MailToolbar = function (config) {
    var thisToolbar = this;

    // Allow "check emails for Inbox folder only" for IMAP account only
    var booShowCheckEmailMenu = this.getSelectedAccountType() == 'imap';

    MailToolbar.superclass.constructor.call(this, {
        id: 'mail-main-toolbar',
        region: 'north',
        height: 44,
        enableOverflow: true,
        previewMode: config.previewMode,
        listeners: {
            afterrender: function () {
                Ext.getCmp('mail-toolbar-check-mail').setDisabled(this.getSelectedAccountIsIncEnabled() == 'N');
            }
        },

        items: [
            {
                xtype: 'buttongroup',
                id: 'mail-toolbar-check-mail',
                items: [
                    {
                        id: 'mail-toolbar-check-mail-button',
                        hidden: booShowCheckEmailMenu,
                        xtype: 'button',
                        text: '<i class="las la-inbox"></i>' + _('Check email'),
                        handler: this.checkMailManually.createDelegate(this, [false])
                    }, {
                        id: 'mail-toolbar-check-mail-splitbutton',
                        hidden: !booShowCheckEmailMenu,
                        xtype: 'splitbutton',
                        width: 120,
                        text: '<i class="las la-inbox"></i>' + _('Check email'),
                        tooltip: _('Clicking this option only checks the Inbox.<br>To check emails in all folders, please select that option from the drop-down menu.'),
                        handler: this.checkMailManually.createDelegate(this, [true]),
                        menu: {
                            items: [{
                                text: '<i class="las la-inbox"></i>' + _('Check email in Inbox only'),
                                handler: this.checkMailManually.createDelegate(this, [true])
                            }, {
                                text: '<i class="las la-arrow-circle-down"></i>' + _('Check email in all folders'),
                                handler: this.checkMailManually.createDelegate(this, [false])
                            }]
                        }
                    }
                ]
            }, {
                id: 'mail-toolbar-checking-mail',
                hidden: !booShowCheckEmailMenu,
                disabled: true,
                xtype: 'button',
                width: 120,
                text: '<i class="las la-inbox"></i>' + _('Checking email'),
            }, {
                id: 'mail-toolbar-create-mail',
                text: '<i class="las la-file-medical"></i>' + _('New'),
                cls: 'main-btn',
                scope: this,
                handler: this.createMail
            }, {
                id: 'mail-toolbar-save',
                disabled: true,
                hidden: typeof is_superadmin !== 'undefined' && is_superadmin,
                text: '<i class="las la-save"></i>' + _('Save'),
                tooltip: allowedPages.has('prospects') ? _('Save selected email to Case or Prospect.') : _('Save selected email to Case.'),
                cls: 'secondary-btn',
                menu: [
                    {
                        text: _('Save to Case'),
                        handler: this.showSaveDialog,
                        scope: this
                    }, {
                        text: _('Save to Prospect'),
                        hidden: !allowedPages.has('prospects'),
                        handler: this.showSaveToProspectDialog,
                        scope: this
                    }
                ]
            }, {
                id: 'mail-toolbar-reply-mail',
                text: '<i class="las la-reply"></i>' + _('Reply'),
                tooltip: _('Reply to selected email.'),
                disabled: true,
                scope: this,
                handler: this.replyMail
            }, {
                id: 'mail-toolbar-reply-all-mail',
                text: '<i class="las la-reply-all"></i>' + _('Reply All'),
                tooltip: _('Reply to all recipients of the selected email.'),
                disabled: true,
                scope: this,
                handler: this.replyAllMail
            }, {
                id: 'mail-toolbar-forward-mail',
                text: '<i class="las la-arrow-circle-right"></i>' + _('Forward'),
                tooltip: _('Forward selected email to another recipient.'),
                disabled: true,
                scope: this,
                handler: this.forwardMail
            }, {
                id: 'mail-toolbar-print',
                disabled: true,
                text: '<i class="las la-print"></i>' + _('Print'),
                tooltip: _('Print selected email.'),
                scope: this,
                handler: this.printEmail
            }, {
                id: 'mail-toolbar-mark-as',
                disabled: true,
                text: '<i class="las la-envelope-square"></i>' + _('Mark as...'),
                tooltip: _('Mark selected email(s) as read or unread.'),
                menu: {
                    items: [
                        {
                            handler: this.markAs.createDelegate(this, [1]),
                            scope: this,
                            text: '<i class="las la-envelope"></i>' + _('Read')
                        }, {
                            handler: this.markAs.createDelegate(this, [0]),
                            scope: this,
                            text: '<i class="las la-envelope-open"></i>' + _('Unread')
                        }
                    ]
                }
            }, {
                id: 'mail-toolbar-delete-selected-mail',
                text: '<i class="las la-trash"></i>' + _('Delete selected'),
                disabled: true,
                scope: this,
                handler: this.deleteSelectedMail
            }, {
                html: '&nbsp;'
            }, {
                text: '<i class="las la-columns"></i>' + _('Viewing Area'),
                tooltip: _('Change the email viewing area to bottom, right, or hide.'),

                menu: {
                    id: 'reading-menu',
                    cls: 'reading-menu',
                    width: 150,
                    items: [{
                        text: _('Bottom'),
                        checked: thisToolbar.previewMode == 'bottom',
                        group: 'rp-group',
                        scope: this,
                        iconCls: 'icon-preview-bottom',
                        checkHandler: function (m, pressed) {
                            thisToolbar.previewMode = 'bottom';
                            Ext.getCmp('mail-grid-with-preview').movePreview(thisToolbar.previewMode, pressed);
                        }
                    }, {
                        text: _('Right'),
                        checked: thisToolbar.previewMode == 'right',
                        group: 'rp-group',
                        scope: this,
                        iconCls: 'icon-preview-right',
                        checkHandler: function (m, pressed) {
                            thisToolbar.previewMode = 'right';
                            Ext.getCmp('mail-grid-with-preview').movePreview(thisToolbar.previewMode, pressed);
                        }
                    }, {
                        text: _('Hide'),
                        checked: thisToolbar.previewMode == 'hide',
                        group: 'rp-group',
                        scope: this,
                        iconCls: 'icon-preview-hide',
                        checkHandler: function (m, pressed) {
                            thisToolbar.previewMode = 'hide';
                            Ext.getCmp('mail-grid-with-preview').movePreview(thisToolbar.previewMode, pressed);
                        }
                    }]
                }
            }, '->', {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function (btn) {
                    showHelpContextMenu(btn.getEl(), 'my-email');
                }
            }
        ]
    });
};

Ext.extend(MailToolbar, Ext.Toolbar, {
    getMenuAndDefaultBtn: function () {
        var accounts = mail_settings.accounts;
        var accountsMenu = null;

        var accountName = '';
        var accountId = 0;
        if (accounts.length > 0) {
            accountName = accounts[0].account_name;
            accountId = accounts[0].account_id;
            accountsMenu = [];
            for (var i = 0; i < accounts.length; i++) {
                accountsMenu[accountsMenu.length] = {
                    handler: this.switchAccount.createDelegate(
                        this,
                        [accounts[i]]
                    ),
                    scope: this,
                    account_id: accounts[i].account_id,
                    text: '<i class="las la-user"></i>' + accounts[i].account_name
                };

                if (accounts[i].is_default == 'Y') {
                    accountName = accounts[i].account_name;
                    accountId = accounts[i].account_id;
                }
            }
        }

        return {
            accountsMenu: accountsMenu,
            accountName: accountName,
            accountId: accountId
        };
    },

    refreshAccountsButton: function () {
        var btn = Ext.getCmp('mail-toolbar-account');
        var group = Ext.getCmp('mail-toolbar-account-group');

        // Get menu and text to show in the button
        var oData = this.getMenuAndDefaultBtn();
        var accountsMenu = oData.accountsMenu;
        if (accountsMenu === null) {
            accountsMenu = [];
        }

        // Update active account (default)
        btn.account_id = oData.accountId;
        btn.account_name = oData.accountName;
        Ext.getCmp('mail-tabpanel').updateAccountButton(oData.accountName, mail_settings.accounts.length);

        // Hide button (its parent group)
        // If there is at least 1 account
        group.setVisible(accountsMenu.length >= 1);


        var tabPanel = Ext.getCmp('mail-tabpanel');
        if (accountsMenu.length === 0) {
            // There are no accounts, so disable all not allowed things
            // (tabs, toolbars)
            tabPanel.disableTabAndToolbar();
        } else {
            tabPanel.enableTabAndToolbar();
        }

        // Refresh layout
        this.doLayout();
    },

    checkMail: function () {
        MailChecker.check(
            this.getSelectedAccountId(),
            false,
            this.getSelectedAccountType() == 'imap'
        );
    },

    checkMailManually: function (booCheckInboxOnly) {
        MailChecker.check(
            this.getSelectedAccountId(),
            true,
            booCheckInboxOnly
        );
    },

    getEmailDialog: function () {
        var sendEmailDialog = Ext.getCmp('mail-create-dialog');
        if (sendEmailDialog === undefined) {
            sendEmailDialog = new MailCreateDialog();
        }

        return sendEmailDialog;
    },

    createMail: function () {
        var oDialog = this.getEmailDialog();
        var signature = oDialog.getCurrentAccountSignature();
        oDialog.destroy();

        show_email_dialog({
            booNewEmail: true,
            emailMessage: signature,
            booShowTemplates: true,
            booCreateFromMailTab: true,
            booDontPreselectTemplate: true
        });
    },

    replyMail: function () {
        this.getEmailDialog().createReplyDialog(false);
    },

    replyAllMail: function () {
        this.getEmailDialog().createReplyDialog(true);
    },

    showSaveDialog: function () {
        this.getEmailDialog().sendSaveEmail(true);
    },

    showSaveToProspectDialog: function () {
        this.getEmailDialog().sendSaveEmail(true, false, true);
    },

    printEmail: function () {
        // create hidden iframe and print it
        var sel_mail_data = Ext.getCmp('mail-ext-emails-grid').getSelectionModel().getSelections()[0].data;

        var iframe_exists = document.getElementById('hidden_preview_iframe') !== null;

        // create iframe or use already created
        iframe = (iframe_exists) ? document.getElementById('hidden_preview_iframe') : document.createElement("IFRAME");

        if (iframe) {
            if (!iframe_exists) {
                iframe.style.width = "0px";
                iframe.style.height = "0px";
                iframe.style.border = "0px";
                iframe.id = "hidden_preview_iframe";
                iframe.name = "hidden_preview_iframe";

                document.body.appendChild(iframe);
            }

            var doc = iframe.contentDocument;

            if (empty(doc))
                doc = iframe.contentWindow.document;

            doc.open();
            doc.write(Ext.getCmp('mail-grid-with-preview').getTemplate(false).apply(sel_mail_data));
            doc.close();

            window.frames['hidden_preview_iframe'].focus();
            window.frames['hidden_preview_iframe'].print();
        }
    },

    forwardMail: function () {
        this.getEmailDialog().createForwardDialog();
    },

    /**
     * Load currently selected email. There are two cases:
     * 1. Grid is showed and record is selected
     * 2. Opened email tab, so we'll use it
     */
    getCurrentSelectedEmail: function (booSeveral) {
        var selRecord;

        var activeTab = Ext.getCmp('mail-tabpanel').getActiveTab();
        if (activeTab && activeTab.email_data) {
            selRecord = {data: activeTab.email_data};
            if (booSeveral) {
                selRecord = [selRecord];
            }
        } else {
            var grid = Ext.getCmp('mail-ext-emails-grid');
            if (booSeveral) {
                selRecord = grid.getSelectionModel().getSelections();
            } else {
                selRecord = grid.getSelectionModel().getSelected();
            }
        }

        return selRecord;
    },

    deleteSelectedMail: function () {
        var selRecords = this.getCurrentSelectedEmail(true);

        if (selRecords.length <= 0) {
            // Can't be here, but...
            return;
        }

        var msgAreYouSure = String.format(
            'Are you sure you want to delete {0}?',
            selRecords.length == 1 ? (empty(selRecords[0].id) ? 'this mail' : 'selected mail') : 'selected mails'
        );

        Ext.Msg.confirm('Please confirm', msgAreYouSure, function (btn) {
            if (btn == 'yes') {
                // Show mask
                Ext.getBody().mask('Deleting...');

                var selAccountId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();

                var mail_ids = [];
                for (var i = 0; i < selRecords.length; i++) {
                    mail_ids.push(selRecords[i]['data'].mail_id);
                }

                Ext.Ajax.request({
                    url: topBaseUrl + '/mailer/index/delete',
                    params: {
                        mail_id: Ext.encode(mail_ids),
                        account_id: Ext.encode(selAccountId)
                    },

                    success: function () {
                        Ext.getBody().unmask();

                        var tree = Ext.getCmp('mail-ext-folders-tree');

                        // INC unread count in Trash, DEC unread count in src folder
                        tree.updateFoldersLabelsAfterMoveDelete(null, 'trash', mail_ids);

                        // Reload the grid
                        var grid = Ext.getCmp('mail-ext-emails-grid');
                        grid.store.reload();

                        // Close all opened emails if they were deleted
                        var tabPan = Ext.getCmp('mail-tabpanel');
                        for (var i = 0; i < mail_ids.length; i++) {
                            tabPan.remove('mail_' + mail_ids[i]);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(
                            'Internal server error. Please try again.'
                        );
                    }
                });
            }
        });
    },

    getSelectedAccountMail: function () {
        var btn = Ext.getCmp('mail-toolbar-account');
        var currentText = '';
        if (btn) {
            currentText = btn.account_name;
        } else {
            var oData = this.getMenuAndDefaultBtn();
            currentText = oData.accountName;
        }

        return currentText;
    },

    getSelectedAccountId: function () {
        var btn = Ext.getCmp('mail-toolbar-account');
        var currentId = 0;
        if (btn) {
            currentId = btn.account_id;
        } else {
            var oData = this.getMenuAndDefaultBtn();
            currentId = oData.accountId;
        }

        return currentId;
    },

    getSelectedAccountType: function () {
        var currentId = this.getSelectedAccountId();

        var accounts = mail_settings.accounts;
        for (var i = 0; i < accounts.length; i++) {
            if (currentId === accounts[i].account_id) {
                return accounts[i].inc_type;
            }
        }

        return 'pop3';
    },

    getSelectedInToAccountId: function () {
        var accountId = 0;

        var fromField = Ext.getCmp('mail-create-from');
        if (!empty(fromField.getValue())) {
            accountId = fromField.getValue();
        }

        if (empty(accountId)) {
            var email = fromField.getRawValue();
            var accounts = mail_settings.accounts;
            for (var i = 0; i < accounts.length; i++) {
                if (email === accounts[i].account_name) {
                    accountId = accounts[i].account_id;
                }
            }
        }

        return accountId;
    },

    getSelectedAccountIsAutoCheck: function () {
        var accounts = mail_settings.accounts;
        for (var i = 0; i < accounts.length; i++) {
            if (this.getSelectedAccountId() === accounts[i].account_id) {
                return accounts[i].auto_check;
            }
        }
        return 0;
    },

    getSelectedAccountIsIncEnabled: function () {
        var accounts = mail_settings.accounts;
        for (var i = 0; i < accounts.length; i++) {
            if (this.getSelectedAccountId() === accounts[i].account_id) {
                return accounts[i].inc_enabled;
            }
        }
        return 'N';
    },

    getSelectedAccountIsAutoCheckEvery: function () {
        var accounts = mail_settings.accounts;
        for (var i = 0; i < accounts.length; i++) {
            if (this.getSelectedAccountId() === accounts[i].account_id) {
                return accounts[i].auto_check_every;
            }
        }
        return 0;
    },

    getSelectedAccountPerPage: function () {
        var accounts = mail_settings.accounts;
        for (var i = 0; i < accounts.length; i++) {
            if (this.getSelectedAccountId() === accounts[i].account_id) {
                return parseInt(accounts[i].per_page, 10);
            }
        }
        return 0;
    },

    reSetEmailCheckingTimeout: function () {
        // Reset timeout
        clearTimeout(checkEMailTimeout);

        var check_every = this.getSelectedAccountIsAutoCheckEvery();
        if (check_every > 0) {
            // Run timeout again
            checkEMailTimeout = setTimeout(
                function () {
                    var tBar = Ext.getCmp('mail-main-toolbar');
                    tBar.checkMail();
                    tBar.reSetEmailCheckingTimeout();
                },
                check_every * 60 * 1000
            );
        }
    },

    setAutoCheck: function () {
        // Check emails right now
        if (this.getSelectedAccountIsAutoCheck() == 'Y') {
            this.checkMail();
        }

        // And check every X minutes
        // when not needed - reset it
        this.reSetEmailCheckingTimeout();
    },

    switchAccount: function (account) {
        if (this.getSelectedAccountId() != account.account_id) {
            // Another account was selected
            var btn = Ext.getCmp('mail-toolbar-account');
            if (btn) {
                btn.account_id = account.account_id;
                btn.account_name = account.account_name;
                btn.setText(Ext.getCmp('mail-tabpanel').getAccountButtonLabel(account.account_name, mail_settings.accounts.length > 1));
            }

            var toolbar = this;

            // If auto_check==true for this acc => check mail
            toolbar.setAutoCheck();

            // Disable/enable 'Check Email' button's container
            Ext.getCmp('mail-toolbar-check-mail').setDisabled(this.getSelectedAccountIsIncEnabled() == 'N');

            // Hide show "Check email" buttons in relation to the email account's type
            var booImapAccount = account.inc_type == 'imap';
            Ext.getCmp('mail-toolbar-check-mail-button').setVisible(!booImapAccount);
            Ext.getCmp('mail-toolbar-check-mail-splitbutton').setVisible(booImapAccount);
            toolbar.doLayout();

            // refresh per_page count
            var bbar = Ext.getCmp('mail-ext-emails-grid').getBottomToolbar();
            bbar.pageSize = this.getSelectedAccountPerPage();

            // Refresh folders and mails list
            Ext.getCmp('mail-ext-folders-tree').getRootNode().reload();
            Ext.getCmp('mail-tabpanel').changeBooShowCheckEmail(this.getSelectedAccountType() == 'imap');
            Ext.getCmp('mail-tabpanel').doLayout();
        }
    },

    markAs: function (booMarkAsRead) {
        // Show mask
        Ext.getBody().mask('Loading...');

        var tree = Ext.getCmp('mail-ext-folders-tree');
        var grid = Ext.getCmp('mail-ext-emails-grid');
        var selAccountId = Ext.getCmp('mail-main-toolbar').getSelectedAccountId();

        var selRecords = this.getCurrentSelectedEmail(true);
        var mail_ids = [];
        for (var i = 0; i < selRecords.length; i++) {
            mail_ids.push(selRecords[i]['data'].mail_id);
        }

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/mark-mail-as-read',
            params: {
                mail_id: Ext.encode(mail_ids),
                account_id: Ext.encode(selAccountId),
                folder_id: Ext.encode(tree ? tree.getSelectionModel().getSelectedNode().attributes.id : 0),
                mail_as_read: Ext.encode(booMarkAsRead)
            },

            success: function (res) {
                var result = Ext.decode(res.responseText);
                if (result.success) {
                    Ext.getBody().unmask();
                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    grid.store.reload();
                } else {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(result.msg);
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(
                    'Internal server error. Please try again.'
                );
            }
        });
    },

    showSettings: function () {
        Ext.getCmp('mail-tabpanel').showSettingsTab();
    }
});

Ext.reg('appMailToolbar', MailToolbar);
