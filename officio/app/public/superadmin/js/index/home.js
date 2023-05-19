Ext.onReady(function () {
    var storageProvider = new Ext.ux.state.LocalStorageProvider({prefix: 'uniques-sup-'});
    if (storageProvider.getStorageObject() === false) {
        // Use cookie storage if local storage is not available
        storageProvider = new Ext.state.CookieProvider({
            expires: new Date(new Date().getTime() + (1000 * 60 * 60 * 24 * 365)) //1 year from now
        })
    }
    Ext.state.Manager.setProvider(storageProvider);

    var el = Ext.get('user-navigation-button');
    if (el) {
        el.on('click', function () {
            var wnd = new UserProfileDialog({});
            wnd.show();
            wnd.center();
            wnd.syncShadow();
        });
    }

    function toggleTabPanelHeaders() {
        var tabsCount = mainTabPanel.items.getCount();
        if (tabsCount) {
            for (var i = 0; i < tabsCount; i++) {
                mainTabPanel.hideTabStripItem(i);
            }

            mainTabPanel.getEl().addClass('main-tabs-no-bg');
        }
    }

    var cookieName = 'superadmin-last-tab';
    var mainTabPanel = new Ext.TabPanel({
        id: 'main-tab-panel',
        renderTo: 'main-tabs',
        cls: 'main-tabs',
        hideMode: 'visibility',
        enableTabScroll: true,
        autoWidth: true,
        autoHeight: true,
        plain: true,
        defaults: {
            autoScroll: true,
            autoHeight: true
        },
        headerCfg: {
            cls: 'main-tab-header x-tab-panel-header'
        },

        listeners: {
            add: function () {
                toggleTabPanelHeaders();
            },

            remove: function () {
                toggleTabPanelHeaders();
            },

            tabchange: function (thisTabPanel, tab) {
                if (!empty(tab.id)) {
                    Ext.state.Manager.set(cookieName, tab.id);
                }
            }
        }
    });

    if (arrAccessRights['view_companies']) {
        mainTabPanel.add({
            title: _('Companies'),
            id: 'superadmin-companies-tab',
            html: '<div id="companies-container"></div>',
            booTabActivated: false,

            listeners: {
                activate: function () {
                    if (!this.booTabActivated) {
                        new CompaniesTabPanel();
                        this.booTabActivated = true;
                    }
                }
            }
        });
    } else {
        // Hide companies search field
        // because we even don't initialize Companies tab
        $('#company_search').hide();
    }

    if (arrAccessRights['tasks-view']) {
        mainTabPanel.add({
            title: 'Tasks',
            html: '<div id="general-tasks-container"></div>',
            booTabActivated: false,

            listeners: {
                'activate': function () {
                    if (!this.booTabActivated) {
                        initTasks(null, 'active');
                        this.booTabActivated = true;
                    }
                }
            }
        });
    }

    if (arrTicketsAccessRights.tickets) {
        mainTabPanel.add({
            title: _('All Tickets'),
            id: 'all-tickets-tab',
            height: initPanelSize() - 32,
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: [new Ext.ux.TabUniquesNavigationMenu({})],

            items: {
                title: _('All Tickets'),
                height: initPanelSize() - 32,
                html: '<div id="general-tickets-container"></div>',
                booTabActivated: false,

                listeners: {
                    'activate': function () {
                        if (!this.booTabActivated) {
                            new TicketsPanel({
                                height: initPanelSize() - 32,
                                renderTo: 'general-tickets-container'
                            });

                            this.booTabActivated = true;
                        }
                    }
                }
            }
        });
    }

    if (booHasAccessToMail) {
        mainTabPanel.add({
            title: 'Email',
            html: "<div id='mail-container'></div>",
            booTabActivated: false,

            listeners: {
                'activate': function () {
                    if (!this.booTabActivated) {
                        initMail();
                        this.booTabActivated = true;
                    }
                }
            }
        });
    }

    if (arrAccessRights['calendar-view']) {
        mainTabPanel.add({
            title: _('Calendar'),
            id: 'calendar-tab',
            height: initPanelSize() - 32,
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: [new Ext.ux.TabUniquesNavigationMenu({})],

            items: {
                title: _('Calendar'),
                height: initPanelSize() - 32,
                autoLoad: {
                    url: topBaseUrl + '/calendar/index',
                    scripts: true
                }
            }
        });
    }

    if (arrAccessRights['my-documents-view']) {
        mainTabPanel.add({
            title: 'My Documents',
            id: 'mydocs-tab',
            html: '<div id="mydocs-content"></div>',
            listeners: {
                activate: function () {
                    loadMyDocs();
                }
            }
        });
    }

    if (arrAccessRights['admin_tab']) {
        mainTabPanel.add({
            title: _('Admin'),
            id: 'admin-tab',
            height: initPanelSize() - 32,
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: [new Ext.ux.TabUniquesNavigationMenu({})],

            items: {
                title: _('Admin'),
                autoHeight: true,
                html: '<iframe id="admin_section_frame" src="' + baseUrl + '/manage-company' + '" style="border:none;" frameborder="0" width="100%" height="650px" scrolling="no" class="admin_frame"></iframe>',

                listeners: {
                    afterrender: function () {
                        adjustHeight();
                    }
                }
            }
        });
    }

    // This is a default tab
    var activeTab = arrAccessRights['admin_tab'] ? 'admin-tab' : 0;

    // Load last used tab (if it is still correct)
    var savedLastOpenedTab = Ext.state.Manager.get(cookieName);
    if (!empty(savedLastOpenedTab)) {
        var booFound = false;
        mainTabPanel.items.each(function (oTab) {
            if (!empty(oTab.id) && oTab.id == savedLastOpenedTab) {
                activeTab = savedLastOpenedTab;
                return false;
            }
        });
    }

    mainTabPanel.setActiveTab(activeTab);
    toggleTabPanelHeaders();
});

function showCompanyPage(companyId, companyName) {
    var tabId = 'company-tab-' + companyId;
    if (!Ext.getCmp(tabId)) {
        var src = baseUrl + '/manage-company/edit?company_id=' + companyId;
        Ext.getCmp('companies-tab-panel').add({
            id: tabId,
            closable: true,
            tabCreatedOn: new Date(),
            title: companyName,
            autoHeight: true,
            width: $('#companies-tab-panel').outerWidth() - 50,
            html: '<iframe id="edit_company_frame_' + companyId + '" src="' + src + '" style="border:none;" frameborder="0" width="100%" height="650px" scrolling="auto"></iframe>'
        });
    }
    Ext.getCmp('companies-tab-panel').setActiveTab(tabId);
    Ext.getCmp('superadmin_companies_search').collapse();
}

function showTopBanner(message) {
    notif({
        message: message,
        delayBeforeShow: 0
    });
}