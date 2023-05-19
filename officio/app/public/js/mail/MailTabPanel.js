MailTabPanel = function() {
    var panelsSizeCookie = Ext.state.Manager.get('mail_pans_size', '250,0,133');
    var panelsSizeCookieArray = panelsSizeCookie.split(',');
    var defaultFolderWidth = parseInt(panelsSizeCookieArray[0], 10);

    var minWidth = 200;
    var maxWidth = 400;
    defaultFolderWidth = Math.max(defaultFolderWidth, minWidth);
    defaultFolderWidth = Math.min(defaultFolderWidth, maxWidth);

    var cookiePreviewMode = Ext.state.Manager.get('mail_preview_mode');
    var previewMode = cookiePreviewMode === null || cookiePreviewMode === undefined ? 'right' : cookiePreviewMode;

    var toolbar = new MailToolbar({
        previewMode: previewMode
    });

    var mail_folders = new MailFolders({
        defaultFolderWidth: defaultFolderWidth
    });

    var mail_grid_with_preview = new MailGridWithPreview({
        previewMode: previewMode
    });

    var arrComponentsToRender = [];
    arrComponentsToRender.push([{
        id: 'mail-toolbar-quick-search',
        xtype: 'appMailGridSearch',
        width: 400,
        emptyText: _('Search emails...')
    }]);

    arrComponentsToRender.push({
        html: '',
            style: 'margin-left: 10px'
        });

        arrComponentsToRender.push({
            xtype: 'button',
            text: '<i class="las la-plus"></i>' + _('New Email'),
            ctCls: 'orange-btn',
            handler: toolbar.createMail.createDelegate(toolbar)
        });

    var arrPlugins = [];
    if (arrComponentsToRender.length) {
        arrPlugins.push(new Ext.ux.TabCustomRightSection({
            arrComponentsToRender: arrComponentsToRender
        }));
    }

    this.booShowCheckEmailMenu = toolbar.getSelectedAccountType() == 'imap';


    var oData = Ext.getCmp('mail-main-toolbar').getMenuAndDefaultBtn();

    var self = this;
    var menuTabId = Ext.id();
    MailTabPanel.superclass.constructor.call(this, {
        id: 'mail-tabpanel',
        renderTo: 'mail-container',
        hideMode: 'visibility',
        cls: 'clients-tab-panel',
        style: 'background-color: white;',
        autoWidth: true,
        height: typeof (is_superadmin) === 'undefined' ? initPanelSize(true) : initPanelSize(true) - 32,
        plain: true,
        tabWidth: 150,
        minTabWidth: 120,
        enableTabScroll: true,
        deferredRender: false,
        activeTab: 1,

        plugins: arrPlugins,

        defaults: {
            autoScroll: true
        },

        items: [
            {
                id: menuTabId,
                text:      '&nbsp;',
                iconCls:   'main-navigation-icon',
                listeners: {
                    'render': function (oThisTab) {
                        var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                            navigationTab: Ext.get(oThisTab.tabEl)
                        });

                        oMenuTab.initNavigationTab(oMenuTab);
                    }
                }
            }, {
                id: 'mail-main-tab',
                autoWidth: true,
                layout: 'border',
                defaults: {
                    collapsible: false,
                    split: false,
                    bodyPadding: 15
                },
                title: _('My Email'),
                items: [
                    {
                        id: 'mail-messages-panel',
                        layout: 'border',
                        region: 'center',
                        items: [toolbar, mail_grid_with_preview]
                    },
                    {
                        id: 'mail-left-side',
                        stateful: false,
                        region: 'west',
                        split: true,
                        width: defaultFolderWidth,
                        minWidth: minWidth,
                        height: initPanelSize(true) - 55,
                        items: [
                            {
                                id: 'mail-toolbar-account-group',
                                xtype: 'container',
                                style: 'margin-top: 10px; margin-left: 10px; display: grid',
                                hidden: empty(mail_settings.accounts.length),

                                items: [{
                                    xtype: 'button',
                                    id: 'mail-toolbar-account',
                                    account_id: oData.accountId,
                                    account_name: oData.accountName,
                                    text: this.getAccountButtonLabel(oData.accountName, mail_settings.accounts.length > 1),
                                    scale: 'medium',
                                    width: 190,
                                    disabled: mail_settings.accounts.length < 2,

                                    handler: function (btn) {
                                        var oData = Ext.getCmp('mail-main-toolbar').getMenuAndDefaultBtn();
                                        var accountsMenu = [];
                                        Ext.each(oData.accountsMenu, function (oMenuItem) {
                                            if (oMenuItem.account_id != btn.account_id) {
                                                accountsMenu.push(oMenuItem);
                                            }
                                        });

                                        var menu = new Ext.menu.Menu({
                                            cls: 'no-icon-menu',
                                            items: accountsMenu
                                        });

                                        menu.show(btn.getEl())
                                    }
                                }]
                            },
                            mail_folders
                        ],

                        listeners: {
                            'resize': function () {
                                self.fireEvent('resize_panels');
                            }
                        }
                    }
                ]
            }
        ],

        listeners: {
            'beforetabchange': function (oTabPanel, newTab) {
                var booSwitchTab = true;
                switch (newTab.id) {
                    case menuTabId:
                        booSwitchTab = false;
                        break;

                    case 'mail-main-tab':
                        // Don't allow to switch to the Email tab
                        // - if there are no email accounts
                        // - if there is only one without "incoming mail server" checked
                        if (empty(mail_settings.accounts.length)) {
                            booSwitchTab = false;
                        } else if (mail_settings.accounts.length === 1) {
                            booSwitchTab = mail_settings.accounts[0].inc_enabled === 'Y';
                        }

                        if (booSwitchTab) {
                            oTabPanel.items.each(function (tab) {
                                // Close all tabs except of the main + navigation tabs
                                if (tab.id === 'mail-settings-tab' || /^mail_(\d+)$/.test(tab.id)) {
                                    oTabPanel.remove(tab);
                                }
                            });
                        }
                        break;

                    default:
                }

                return booSwitchTab;
            }
        }
    });

    this.addEvents({resize_panels: true});
    this.on({
        'activate': function () {
            // Fix issue when switch between the main tabs
            // and if window document height was changed - this Mail panel will be not showed
            this.setHeight(initPanelSize(true));
        },

        'resize_panels': function () {
            if (Ext.getCmp('right-preview').rendered) {
                var folders_w = Ext.getCmp('mail-ext-folders-tree').getWidth();

                var right_preview_w = Ext.getCmp('right-preview').getWidth();

                var bottom_preview_h = 0;
                if (right_preview_w === 0)
                    bottom_preview_h = Ext.getCmp('bottom-preview').getHeight();


                // Update width of the folders tree toolbar items
                Ext.getCmp('mail-folders-tree-bbar').items.each(function (item) {
                    item.setWidth(folders_w - 10);
                });

                Ext.state.Manager.set('mail_pans_size', '' + folders_w + ',' + right_preview_w + ',' + bottom_preview_h);
            }
        },
        scope: this
    });

    mail_folders.on('folderselect', function(node) {
        // Load mails list for selected folder
        mail_grid_with_preview.loadMails(node.attributes, !booRefreshEmailsInFolder);
        booRefreshEmailsInFolder = true;

        // Update tab label and icon
        Ext.getCmp('mail-ext-folders-tree').updateTabLabel(node);
    });

    // Disable tab panel and toolbar (not all buttons)
    // and show a settings tab automatically
    // if there are no mail accounts
    var booShowSettingsTab = false;
    if (empty(mail_settings.accounts.length)) {
        booShowSettingsTab = true;
    } else if (mail_settings.accounts.length === 1) {
        booShowSettingsTab = mail_settings.accounts[0].inc_enabled !== 'Y';
    }

    if (booShowSettingsTab) {
        self.disableTabAndToolbar();

        self.showSettingsTab();
    }
};

Ext.extend(MailTabPanel, Ext.TabPanel, {
    getAccountButtonLabel: function (accountName, booWithIcon) {
        var label = Ext.util.Format.ellipsis(accountName, 25) + (booWithIcon ? '<i class="down"></i>' : '');

        return label;
    },

    disableTabAndToolbar: function () {
        // Disable the first tab
        var first_tab = Ext.getCmp('mail-main-tab');
        first_tab.getEl().mask('', 'x-hidden');

        // Disable toolbar buttons, except of settings
        var toolbar = Ext.getCmp('mail-main-toolbar');
        if (toolbar.items.length > 0) {
            var groups = toolbar.items.items;
            Ext.each(groups, function(item, index) {
                item.setDisabled(true);
            });
        }
    },

    enableTabAndToolbar: function() {
        var first_tab = Ext.getCmp('mail-main-tab');
        first_tab.getEl().unmask();

        // Disable toolbar buttons, except of settings
        var toolbar = Ext.getCmp('mail-main-toolbar');
        if (toolbar.items.length > 0) {
            var groups = toolbar.items.items;
            Ext.each(groups, function(item, index) {
                item.setDisabled(false);
            });
        }
    },

    changeBooShowCheckEmail: function(booShowCheckEmailMenu) {
        this.booShowCheckEmailMenu = booShowCheckEmailMenu;
    },

    showSettingsTab: function() {
        var settingsTabId = 'mail-settings-tab';
        var tab_panel = Ext.getCmp('mail-tabpanel');
        var tab = tab_panel.getItem(settingsTabId);

        if (!tab) {
            var booSettingsTabClosable = true;
            if (empty(mail_settings.accounts.length)) {
                booSettingsTabClosable = false;
            } else if (mail_settings.accounts.length === 1) {
                booSettingsTabClosable = mail_settings.accounts[0].inc_enabled === 'Y';
            }

            // MailSettingsGrid
            tab = new Ext.Panel({
                id: settingsTabId,
                title: _('Account Settings'),
                closable: booSettingsTabClosable,
                border: true,
                items: new MailSettingsGrid({
                    // Width is required to be sure that otherwise there are issues related to this
                    width: tab_panel.getWidth() - 40,
                    autoLoadData: true,
                    hideBackButton: false,
                    dontShowWarning: false
                })
            });
            tab_panel.add(tab);
        }

        tab_panel.setActiveTab(tab);
    },

    updateAccountButton(accountName, accountsLength) {
        var btn = Ext.getCmp("mail-toolbar-account")
        btn.setText(Ext.getCmp('mail-tabpanel').getAccountButtonLabel(accountName, accountsLength > 1));
        btn.setDisabled(accountsLength < 2);
    }
});

Ext.reg('appMailTabPanel', MailTabPanel);
