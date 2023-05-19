MailSettingsGrid = function (config) {
    Ext.apply(this, config);
    var thisGrid = this;

    this.store = new Ext.data.Store({
        url: topBaseUrl + '/mailer/settings/get',
        baseParams: {member_id: Ext.encode(curr_member_id)},
        autoLoad: config.autoLoadData,
        remoteSort: false,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'id'},
            {name: 'email'},
            {name: 'friendly_name'},
            {name: 'auto_check'},
            {name: 'auto_check_every'},
            {name: 'signature'},

            {name: 'inc_enabled'},
            {name: 'inc_type'},
            {name: 'inc_host'},
            {name: 'inc_port'},
            {name: 'inc_login'},
            {name: 'inc_password'},
            {name: 'inc_ssl'},
            {name: 'inc_login_type'},

            {name: 'out_use_own'},
            {name: 'out_host'},
            {name: 'out_port'},
            {name: 'out_auth_required'},
            {name: 'out_login'},
            {name: 'out_password'},
            {name: 'out_ssl'},
            {name: 'out_save_sent'},
            {name: 'out_login_type'},

            {name: 'per_page'},
            {name: 'timezone'},

            {name: 'inc_leave_messages'},
            {name: 'inc_only_headers'},
            {name: 'inc_fetch_from_date'},

            {name: 'is_default'}
        ])),

        listeners: {
            load: function (store, records) {
                // Check if accounts list was changed or not
                var booRefreshAccounts = false;
                var btn = Ext.getCmp('mail-toolbar-account');

                // If there are settings (when email tab is used)
                if (typeof (mail_settings) !== 'undefined') {
                    if (records.length > 0 && mail_settings.accounts.length > 0) {
                        // Check if in new list there are new accounts
                        Ext.each(records, function (item) {
                            var booAccountExists = false;
                            Ext.each(mail_settings.accounts, function (account) {
                                if (account.account_id == item.id) {
                                    booAccountExists = true;
                                }
                            });

                            // This account is new
                            if (!booAccountExists) {
                                booRefreshAccounts = true;
                            }
                        });


                        if (!booRefreshAccounts) {
                            // Check if some accounts were removed
                            Ext.each(mail_settings.accounts, function (item) {
                                var booAccountExists = false;
                                Ext.each(records, function (account) {
                                    if (account.id == item.account_id) {
                                        booAccountExists = true;
                                    }
                                });

                                // This account is old
                                if (!booAccountExists) {
                                    booRefreshAccounts = true;
                                }
                            });
                        }
                    } else if (records.length != mail_settings.accounts.length) {
                        // One list was empty, but another - not
                        booRefreshAccounts = true;
                    }

                    // Refresh accounts list
                    // to use the latest settings
                    var arrNewAccounts = [];
                    Ext.each(records, function (account, index) {
                        arrNewAccounts[arrNewAccounts.length] = {
                            'account_id': account.data.id,
                            'account_name': account.data.email,
                            'signature': account.data.signature,
                            'auto_check': account.data.auto_check,
                            'auto_check_every': account.data.auto_check_every,
                            'is_default': account.data.is_default,
                            'per_page': account.data.per_page,
                            'inc_enabled': account.data.inc_enabled
                        };

                        if (mail_settings.accounts[index] && btn && btn.account_id === mail_settings.accounts[index].account_id && account.data.inc_enabled != mail_settings.accounts[index].inc_enabled) {
                            booRefreshAccounts = true;
                        }
                    });
                    mail_settings.accounts = arrNewAccounts;
                }

                // If accounts list was changed -
                // refresh accounts list button and refresh main window
                if (booRefreshAccounts) {
                    // Refresh toolbar account button/menu
                    Ext.getCmp('mail-main-toolbar').refreshAccountsButton();

                    // Refresh folders and mails lists...
                    Ext.getCmp('mail-ext-folders-tree').getRootNode().reload();
                } else {
                    // Maybe account was renamed
                    if (btn) {
                        for (var i = 0; i < mail_settings.accounts.length; i++) {
                            if (btn.account_id === mail_settings.accounts[i].account_id) {
                                Ext.getCmp("mail-tabpanel").updateAccountButton(mail_settings.accounts[i].account_name, mail_settings.accounts.length);
                                break;
                            }
                        }
                    }
                }

                // refresh per_page
                var grid = Ext.getCmp('mail-ext-emails-grid');
                if (grid) {
                    var bbar = grid.getBottomToolbar();

                    bbar.pageSize = Ext.getCmp('mail-main-toolbar').getSelectedAccountPerPage();

                    //goto page 1
                    bbar.doLoad(0);
                }

                // Show a warning message if we have only one account without "incoming mail server" enabled
                var booOneInactiveAccount = mail_settings.accounts.length === 1 && mail_settings.accounts[0].inc_enabled !== 'Y';
                if (booOneInactiveAccount && !config.dontShowWarning) {
                    Ext.simpleConfirmation.info(
                        _('Please configure incoming mail server settings for your email account to access your inbox from Officio')
                    );
                }

                // Enable the "back" button only if we can switch to it
                Ext.getCmp('mail-settings-tb-back-to-email').setDisabled(empty(mail_settings.accounts.length) || booOneInactiveAccount);

                thisGrid.bbarPanel.setVisible(empty(mail_settings.accounts.length));

                // Show 'manage oAuth tokens' button if there are saved tokens
                thisGrid.oauthTokensButton.setVisible(store.reader.jsonData.booManageOAuthTokens);
            }
        }
    });

    var sm = new Ext.grid.CheckboxSelectionModel({
        listeners: {
            // On selection change, set enabled state of the removeButton
            // which was placed into the GridPanel using the ref config
            selectionchange: function (sm) {
                var sel = sm.getSelections();
                var count = sel.length;

                // Enable 'Delete' button only if
                // at least one account is selected
                if (count) {
                    this.grid.removeButton.enable();
                } else {
                    this.grid.removeButton.disable();
                }

                // Enable 'Edit' button only if
                // only one account is selected
                if (count == 1) {
                    this.grid.editButton.enable();
                } else {
                    this.grid.editButton.disable();
                }

                // Enable 'Set as default' button only if
                // only one account is selected (which is not default)
                if (count == 1 && sel[0].data['is_default'] != 'Y') {
                    this.grid.defaultButton.enable();
                } else {
                    this.grid.defaultButton.disable();
                }
            }
        }
    });

    var expandCol = Ext.id();
    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm, {
                header: _('Email'),
                dataIndex: 'email',
                width: 30,
            },
            {
                id: expandCol,
                dataIndex: 'is_default',

                renderer: function (value) {
                    return value == 'Y' ? _('Default account') : '';
                }

            }
        ],
        defaultSortable: true
    });

    this.bbarPanel = new Ext.Panel({
        width: 400,
        style: 'margin: 0 auto',
        hidden: true,

        items: [
            {
                xtype: 'box',
                autoEl: {
                    tag: 'div',
                    style: 'text-align: center',
                    html: '<i class="las la-inbox" style="font-size: 130px; color: #BDC1C6"></i>' +
                        '<div style="font-weight: bold; font-size: 24px; margin: 6px 0 14px">' + _('No email accounts are added yet') + '</div>' +
                        '<div style="font-size: 16px; margin-bottom: 32px; line-height: 21px">' + _('By adding your email accounts to Officio email, you can access your emails, send emails, and save client or prospect emails to their records') + '</div>'
                }
            }, {
                xtype: 'button',
                text: '<i class="las la-plus"></i>' + _('Add Email Account'),
                cls: 'orange-btn',
                style: 'margin: 0 auto',
                handler: this.showEditEmailAccount.createDelegate(this, [true])
            }
        ]
    });

    MailSettingsGrid.superclass.constructor.call(this, {
        id: 'mail-settings-accounts-grid',
        style: 'margin: 20px',
        sm: sm,
        cm: cm,
        autoHeight: true,
        split: true,
        stripeRows: true,
        autoScroll: true,
        autoExpandColumn: expandCol,


        viewConfig: {
            forceFit: true,
            getRowClass: this.applyRowClass,
            emptyText: '<i class="las la-level-up-alt" style="transform: scaleX(-1)"></i>' + _('Click Add to add an email account')
        },

        tbar: {
            xtype: 'panel',
            items: [
                {
                    xtype: 'toolbar',
                    items: [
                        {
                            hidden: config.hideBackButton,
                            id: 'mail-settings-tb-back-to-email',
                            text: '<i class="las la-arrow-left"></i>' + _('Back to my Email'),
                            disabled: true,

                            handler: function () {
                                var oTabPanel = Ext.getCmp('mail-tabpanel');
                                var tab = oTabPanel.getItem('mail-main-tab');
                                if (oTabPanel && tab) {
                                    // Close the current Settings tab
                                    var settingsTab = oTabPanel.getActiveTab();
                                    oTabPanel.remove(settingsTab);

                                    // Switch to the Email tab
                                    oTabPanel.setActiveTab(tab);
                                    Ext.getCmp('mail-ext-folders-tree').onAfterRender();
                                }
                            }
                        },
                        {
                            text: '<i class="las la-plus"></i>' + _('Add'),
                            handler: this.showEditEmailAccount.createDelegate(this, [true])
                        }, {
                            text: '<i class="las la-edit"></i>' + _('Edit'),
                            ref: '../../editButton',
                            disabled: true,
                            handler: this.showEditEmailAccount.createDelegate(this, [false])
                        }, {
                            text: '<i class="las la-trash"></i>' + _('Remove'),
                            ref: '../../removeButton',
                            disabled: true,
                            handler: this.deleteEmailAccount.createDelegate(this, [])
                        }, {
                            text: '<i class="las la-check"></i>' + _('Set as Default'),
                            ref: '../../defaultButton',
                            disabled: true,
                            handler: this.setAsDefaultEmailAccount.createDelegate(this, [])
                        }, {
                            text: '<i class="las la-key"></i>' + _('Manage oAuth Tokens'),
                            ref: '../../oauthTokensButton',
                            hidden: true,
                            handler: this.manageOAuthTokens.createDelegate(this, [])
                        }, '->', {
                            xtype: 'button',
                            text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                            hidden: typeof allowedPages === 'undefined' || !allowedPages.has('help'),
                            handler: function () {
                                showHelpContextMenu(this.getEl(), 'my-email-settings');
                            }
                        }
                    ]
                }
            ]
        },

        bbar: this.bbarPanel
    });

    this.on('rowdblclick', this.showEditEmailAccount.createDelegate(this, [false]));
};

Ext.extend(MailSettingsGrid, Ext.grid.GridPanel, {
    // Apply custom class for each row
    applyRowClass: function (record) {
        return (record.data['is_default'] == 'Y' ? 'bold-row' : '');
    },

    showEditEmailAccount: function (booAdd) {
        var data = [];
        if (!booAdd) {
            var sel = this.getSelectionModel().getSelections();

            // Check if at least one account is selected
            if (sel.length === 0) {
                Ext.simpleConfirmation.warning(
                    _('Please select one email account and try again.')
                );
                return;
            }

            data = sel[0].data;
        } else {
            // Turn on "own SMTP" by default for a new account
            data.out_use_own = 'Y';
        }

        var win = new MailSettingsDialog({
            booAdd: booAdd,
            oData: data
        }, this);

        win.show();
    },

    /******** deleteEmailAccount ********/
    deleteEmailAccount: function () {
        var sel = this.getSelectionModel().getSelections();

        // Check if "Check email" or "New mail" is not in progress
        if (typeof booCheckingInProgress != 'undefined' && booCheckingInProgress) {
            Ext.simpleConfirmation.warning(
                _('You cannot delete account, because email checking is in progress.')
            );
            return;
        }

        var oDialog = Ext.getCmp('mail-create-dialog');
        if (oDialog && oDialog.isMailDialogOpened) {
            Ext.simpleConfirmation.warning(
                _('You cannot delete account, because a new mail window is opened.')
            );
            return;
        }


        // Check if at least one account is selected
        if (sel.length === 0) {
            Ext.simpleConfirmation.warning(
                _('Please select at least one email account and try again.')
            );
            return;
        }

        // Show a confirmation
        var msg;
        var arrAccounts = [];
        if (sel.length > 1) {
            msg = String.format(
                _('Are you sure you want to delete <i>{0}</i> selected email accounts?'),
                sel.length
            );

            Ext.each(sel, function (item) {
                arrAccounts[arrAccounts.length] = item.data['id'];
            });
        } else {
            msg = String.format(
                _('Are you sure you want to delete <i>{0}</i>?'),
                sel[0].data.email
            );
            arrAccounts[0] = sel[0].data.id;
        }

        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                // Send request to delete selected accounts
                Ext.Ajax.request({
                    url: topBaseUrl + '/mailer/settings/delete-email-account',

                    params: {
                        accounts: Ext.encode(arrAccounts),
                        member_id: Ext.encode(curr_member_id)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            Ext.getBody().mask(_('Done!'));

                            Ext.getCmp('mail-settings-accounts-grid').store.reload();

                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultData.message);
                            Ext.getBody().unmask();
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(
                            _('Selected Email Account cannot be deleted. Please try again later.')
                        );
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },


    /******** setAsDefaultEmailAccount ********/
    setAsDefaultEmailAccount: function () {
        var sel = this.getSelectionModel().getSelections();

        // Check if only one account is selected
        if (sel.length != 1) {
            Ext.simpleConfirmation.warning(
                _('Please select one email account and try again.')
            );
            return;
        }

        var data = sel[0].data;

        Ext.getBody().mask(_('Please wait...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/settings/set-as-default',
            params: {
                email_account_id: data.id,
                member_id: Ext.encode(curr_member_id)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    Ext.getBody().mask(_('Done!'));

                    Ext.getCmp('mail-settings-accounts-grid').store.reload();

                    setTimeout(function () {
                        Ext.getBody().unmask();
                    }, 750);
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                    Ext.getBody().unmask();
                }
            },

            failure: function () {
                var msg = String.format(
                    _("Can't set email {0} as default. Please try again later."),
                    data.email
                );
                Ext.simpleConfirmation.error(msg);
                Ext.getBody().unmask();
            }
        });
    },

    manageOAuthTokens: function () {
        var oMailSettingsTokensDialog = new MailSettingsTokensDialog(this);
        oMailSettingsTokensDialog.show();
    }
});

Ext.reg('appMailSettingsGrid', MailSettingsGrid);
