function addMainTabPanel() {
    var mainTabPanel = new Ext.TabPanel({
        id: 'main-tab-panel',
        renderTo: 'main-tabs',
        hideMode: 'visibility',
        autoWidth: true,
        plain: true,
        enableTabScroll: true,
        autoHeight: true,
        defaults: {
            autoScroll: true,
            autoHeight: true
        },
        cls: 'main-tabs',
        listeners: {
            add: function () {
                toggleTabPanelHeaders();
            },

            remove: function () {
                toggleTabPanelHeaders();
            },

            tabchange: function () {
                updateQuickLinks();
            },

            'beforetabchange': function (oTabPanel, newTab) {
                if (newTab.booDisallowTabSwitching) {
                    if (allowedPages.has('lms')) {
                        loginLms();
                    }

                    return false;
                }
            }
        },

        headerCfg: {
            cls: 'main-tab-header x-tab-panel-header'
        }
    });

    if (allowedPages.has('homepage')) {
        mainTabPanel.add({
            title: _('Dashboard'),
            id: 'homepage-tab',
            hash: '#homepage',
            booLoaded: false,
            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);

                    if (!this.booLoaded) {
                        initDashboard();
                        this.booLoaded = true;
                    }
                }
            }
        });
    }

    if (allowedPages.has('applicants')) {
        mainTabPanel.add({
            id: 'applicants-tab',
            title: _('Clients'),
            hash: '#applicants',
            booTabActivated: false,
            mainMenuItemWithPadding: true,
            booHideInMainMenu: arrApplicantsSettings.office_default_selected !== 'all',
            scripts: true,
            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);

                    if (!this.booTabActivated && typeof initApplicants === 'function') {
                        var hash = parseUrlHash(location.hash);
                        var applicantId = hash[0] === 'applicants' && !empty(hash[1]) ? hash[1] : null;

                        if (empty(applicantId)) {
                            if (is_client) {
                                applicantId = curr_member_id;
                                setUrlHash(this.hash + '/' + applicantId);
                            } else if (arrApplicantsSettings.access.queue.view) {
                                // Automatically open Queue tab if this is allowed
                                setUrlHash(this.hash + '/queue');
                            }
                        }

                        initApplicants('applicants', applicantId);

                        this.booTabActivated = true;
                    }
                }
            }
        });

        if (arrApplicantsSettings.office_default_selected !== 'all') {
            mainTabPanel.add({
                id: 'applicants-offices-tab',
                parentTitle: _('Clients'),
                mainMenuItemWithPadding: true,
                title: arrApplicantsSettings.office_default_selected === 'all' ? _('All ') + ' ' + arrApplicantsSettings.office_label + 's' : _('My ') + ' ' + arrApplicantsSettings.office_label + 's',
                listeners: {
                    activate: function () {
                        setUrlHash('#applicants/queue/' + arrApplicantsSettings.office_default_selected);
                        setActivePage();
                    }
                }
            });


            mainTabPanel.add({
                id: 'applicants-advanced-search-tab',
                parentTitle: _('Clients'),
                title: _('Advanced Search'),
                listeners: {
                    activate: function () {
                        setUrlHash('#applicants/advanced_search');
                        setActivePage();
                    }
                }
            });
        }
    }

    if (allowedPages.has('prospects')) {
        mainTabPanel.add({
            title: _('Prospects'),
            id: 'prospects-tab',
            hash: '#prospects',
            html: '<div id="prospects-content"></div>',

            listeners: {
                activate: function () {
                    var hash = parseUrlHash(location.hash);
                    setUrlHash(this.hash, 1);

                    if (typeof initProspectsPanel === 'function') {
                        if (hash[0] != 'prospects') {
                            hash[1] = null;
                            hash[2] = null;
                            hash[3] = null;
                        }

                        initProspectsPanel('prospects', hash[1], hash[2], hash[3]);
                    }
                }
            }
        });
    }

    if (allowedPages.has('contacts')) {
        mainTabPanel.add({
            id: 'contacts-tab',
            title: 'Contacts',
            hash: '#contacts',
            scripts: true,
            booTabActivated: false,
            listeners: {
                activate: function () {
                    if (typeof initApplicants === 'function') {
                        var applicantId = null;
                        if (!this.booTabActivated) {
                            setUrlHash(this.hash, 1);

                            var hash = parseUrlHash(location.hash);
                            applicantId = hash[0] === 'contacts' && !empty(hash[1]) ? hash[1] : null;

                            if (empty(applicantId) && arrApplicantsSettings['access']['contact']['queue']['view']) {
                                // Automatically open Queue tab if this is allowed
                                setUrlHash(this.hash + '/queue');
                            }
                            this.booTabActivated = true;
                        }

                        initApplicants('contacts', applicantId);
                    }
                }
            }
        });
    }

    if (allowedPages.has('marketplace')) {
        mainTabPanel.add({
            title: _('Marketplace'),
            id: 'marketplace-tab',
            hash: '#marketplace',
            booLoaded: false,
            scripts: true,
            listeners: {
                activate: function () {
                    var hash = parseUrlHash(location.hash);
                    setUrlHash(this.hash, 1);

                    if (!arrProspectSettings.arrAccess.marketplace.show_warning) {
                        if (typeof initProspectsPanel === 'function') {
                            if (hash[0] != 'marketplace') {
                                hash[1] = null;
                                hash[2] = null;
                                hash[3] = null;
                            }

                            initProspectsPanel('marketplace', hash[1], hash[2], hash[3]);
                        }
                    } else if (!this.booLoaded) {
                        new Ext.BoxComponent({
                            renderTo: 'marketplace-tab',

                            autoEl: {
                                tag: 'img',
                                width: this.getWidth(),
                                height: (544 * this.getWidth()) / 1329, // 1329x544 - dimensions of the image
                                src: baseUrl + '/images/sample_marketplace.png'
                            }
                        });

                        // Show error message if user hasn't MP profiles
                        var msg = String.format(
                            'To take advantage of the Marketplace, please go to {0} and define all the {1} in your company.<br><br>Marketplace is a place where we connect prospective immigrants who are looking for {2} services to {1} like you.',
                            arrProspectSettings.arrAccess.marketplace.manage_marketplace ? '<a href="#" onclick="openMPNewProfilePage(); return false;" style="white-space: nowrap" class="blulinkun12" title="Click to open Admin section and create a new Marketplace Profile">Admin | Marketplace Profiles</a>' : '<span style="font-weight: bold; white-space: nowrap;">Admin | Marketplace Profiles</span>',
                            site_version === 'australia' ? 'Registered Migration Agents' : 'Authorized Representatives',
                            site_version === 'australia' ? 'migration' : 'immigration'
                        );

                        Ext.simpleConfirmation.warning(msg);
                    }

                    this.booLoaded = true;
                }
            }
        });
    }

    if (allowedPages.has('tasks')) {
        mainTabPanel.add({
            title: _('My Tasks'),
            id: 'tasks-tab',
            scripts: true,
            mainMenuItemWithPadding: true,
            autoLoad: {
                url: baseUrl + '/tasks/index',
                scripts: true,
                callback: function () {
                    // Check if related js files were included
                    if (typeof initTasks === 'function') {
                        var hash = parseUrlHash(location.hash);
                        var taskId = null;
                        var activeStatusFilter = null;
                        if (hash[0] === 'tasks' && !empty(hash[1])) {
                            if (['', 'active', 'completed', 'due_today', 'due_tomorrow', 'due_next_7_days'].has(hash[1])) {
                                activeStatusFilter = hash[1];
                            } else {
                                taskId = hash[1];
                            }
                        }

                        initTasks(taskId, activeStatusFilter);
                    }
                }
            },
            hash: '#tasks',
            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);
                }
            }
        });
    }

    if (typeof (mail_settings) !== "undefined" && mail_settings.show_email_tab) {
        mainTabPanel.add({
            title: _('My Email'),
            id: 'email-tab',
            booRefreshOnActivate: false,
            scripts: true,
            autoLoad: {
                url: baseUrl + '/mailer/index',
                scripts: true,
                callback: function () {
                    // Check if related js files were included
                    if (typeof initMail === 'function') {
                        initMail();
                    }
                }
            },
            hash: '#email',
            listeners: {
                activate: function () {
                    if (this.booRefreshOnActivate) {
                        this.doAutoLoad();
                        this.booRefreshOnActivate = false;
                    }
                    setUrlHash(this.hash, 1);

                    var mailPanel = Ext.getCmp('mail-tabpanel');
                    if (mailPanel) {
                        mailPanel.fireEvent('activate', mailPanel);
                    }
                }
            }
        });
    }

    allowedPages = typeof (allowedPages) !== "undefined" ? allowedPages : [];
    allowedMyDocsSubTabs = typeof (allowedMyDocsSubTabs) !== "undefined" ? allowedMyDocsSubTabs : [];

    if (allowedPages.has('calendar')) {
        var arrPlugins = [
            new Ext.ux.TabUniquesNavigationMenu({})
        ];

        if (allowedPages.has('help')) {
            arrPlugins.push(
                new Ext.ux.TabCustomRightSection({
                    additionalCls: 'x-tab-tabmenu-right-section-with-right-margin x-tab-tabmenu-right-section-with-top-margin',
                    arrComponentsToRender: [
                        {
                            xtype: 'button',
                            text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                            handler: function () {
                                showHelpContextMenu(this.getEl(), 'my-calendar');
                            }
                        }
                    ]
                })
            );
        }

        mainTabPanel.add({
            title: _('My Calendar'),
            id: 'calendar-tab',
            hash: '#calendar',
            height: initPanelSize(),
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: arrPlugins,

            items: {
                title: _('My Calendar'),
                height: initPanelSize(),
                autoLoad: {
                    url: baseUrl + '/calendar/index',
                    scripts: true
                }
            },

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);
                }
            }
        });
    }

    if (allowedPages.has('mydocs')) {
        mainTabPanel.add({
            title: _('My Documents'),
            id: 'mydocs-tab',
            html: '<div id="mydocs-content"></div>',
            hash: '#mydocs',

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);
                    loadMyDocs();
                }
            }
        });
    }

    if (allowedMyDocsSubTabs.has('templates')) {
        var arrComponentsToRender = [];
        arrComponentsToRender.push({
            xtype: 'button',
            text: '<i class="las la-plus"></i>' + _('New Template'),
            ctCls: 'orange-btn',
            menu: {
                cls: 'no-icon-menu',
                showSeparator: false,
                items: [{
                    text: '<i class="las la-envelope"></i>' + _('New Email Template'),
                    handler: function () {
                        var thisTemplatesTree = Ext.getCmp('document-templates-tree');
                        if (!thisTemplatesTree.getFocusedFolderId(thisTemplatesTree)) {
                            Ext.simpleConfirmation.warning('Please select Template Folder!');
                        } else {
                            thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), true);
                        }
                    }
                }, {
                    text: '<i class="las la-file"></i>' + _('New Letter Template'),
                    handler: function () {
                        var thisTemplatesTree = Ext.getCmp('document-templates-tree');
                        if (!thisTemplatesTree.getFocusedFolderId(thisTemplatesTree)) {
                            Ext.simpleConfirmation.warning('Please select Template Folder!');
                        } else {
                            thisTemplatesTree.templateAdd(thisTemplatesTree.getFocusedFolderId(thisTemplatesTree), false, true);
                        }
                    }
                }]
            }
        });

        var menuTabId = Ext.id();
        mainTabPanel.add({
            title: _('Client Templates'),
            id: 'clients-templates-tab',
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            hash: '#clients-templates',
            plain: true,
            activeTab: 1,
            autoHeight: true,
            deferredRender: false,
            mainMenuItemWithPadding: true,

            plugins: [
                new Ext.ux.TabUniquesMenuSimple({
                    booAllowTabClosing: true,
                    defaultEmptyText: _('No open templates')
                }),

                new Ext.ux.TabCustomRightSection({
                    additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
                    arrComponentsToRender: arrComponentsToRender
                })
            ],

            items: [
                {
                    id: menuTabId,
                    text: '&nbsp;',
                    iconCls: 'main-navigation-icon',
                    listeners: {
                        'render': function (oThisTab) {
                            var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                                navigationTab: Ext.get(oThisTab.tabEl)
                            });

                            oMenuTab.initNavigationTab(oMenuTab);
                        }
                    }
                }, {
                    id: 'mydocs-templates-sub-tab',
                    title: _('Client Templates'),
                    html: '<div id="mydocs-templates"></div>',
                    style: 'padding: 10px',

                    listeners: {
                        activate: function () {
                            if (!this.tree) {
                                this.tree = new DocumentTemplatesTree({
                                    height: initPanelSize() - 15,
                                    mainColumnWidth: Ext.getCmp('mydocs-templates-sub-tab').getWidth() - 600
                                });
                                this.tree.render('mydocs-templates');
                            }
                        }
                    }
                }
            ],

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);
                },

                'beforetabchange': function (oTabPanel, newTab) {
                    if (newTab.id === menuTabId) {
                        return false;
                    }
                }
            }
        });
    }

    if (allowedPages.has('prospects-templates')) {
        var arrProspectComponentsToRender = [];
        arrProspectComponentsToRender.push({
            xtype: 'button',
            text: '<i class="las la-plus"></i>' + _('New Template'),
            ctCls: 'orange-btn',
            handler: function () {
                var thisGrid = Ext.getCmp('prospect-templates-grid');
                if (thisGrid) {
                    thisGrid.templateOpenDialog(false);
                }
            }
        });

        var prospectsTemplatesMenuTabId = Ext.id();
        mainTabPanel.add({
            title: _('Prospect Templates'),
            id: 'prospects-templates-tab',
            xtype: 'tabpanel',
            hash: '#prospects-templates',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 1,
            autoHeight: true,
            deferredRender: false,

            plugins: [
                new Ext.ux.TabUniquesMenuSimple({
                    booAllowTabClosing: true,
                    defaultEmptyText: _('No open templates')
                }),

                new Ext.ux.TabCustomRightSection({
                    additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
                    arrComponentsToRender: arrProspectComponentsToRender
                })
            ],

            items: [{
                id: prospectsTemplatesMenuTabId,
                text: '&nbsp;',
                iconCls: 'main-navigation-icon',
                listeners: {
                    'render': function (oThisTab) {
                        var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                            navigationTab: Ext.get(oThisTab.tabEl)
                        });

                        oMenuTab.initNavigationTab(oMenuTab);
                    }
                }
            }, {
                id: 'prospects-templates-sub-tab',
                title: _('Prospect Templates'),
                height: initPanelSize() - 5,
                html: '<div id="prospects-templates"></div>',
                style: 'padding: 10px',

                listeners: {
                    activate: function () {
                        if (!this.grid) {
                            this.grid = new ProspectTemplatesGrid({
                                renderTo: 'prospects-templates',
                                mainColumnWidth: this.getWidth() - 410,
                                height: this.height - 20
                            });
                        }
                    }
                }
            }],

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);
                },

                'beforetabchange': function (oTabPanel, newTab) {
                    if (newTab.id === prospectsTemplatesMenuTabId) {
                        return false;
                    }
                }
            }
        });
    }

    if (allowedPages.has('trustac')) {
        mainTabPanel.add({
            title: arrApplicantsSettings.ta_label,
            id: 'trustac-tab',
            booRefreshOnActivate: false,
            hash: '#trustac',
            scripts: true,
            mainMenuItemWithPadding: true,

            autoLoad: {
                url: baseUrl + '/trust-account/index',
                callback: function () {
                    var hash = parseUrlHash(location.hash),
                        taId = hash[0] === 'trustac' && !empty(hash[1]) ? hash[1] : null;

                    initTrustAC(taId);
                }
            },

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);

                    if (this.booRefreshOnActivate) {
                        this.booLoaded = false;
                        this.booRefreshOnActivate = false;
                        this.doAutoLoad();
                    } else {
                        // Try to open a specific T/A
                        var cpan = Ext.getCmp('ta-tab-panel');
                        var hash = parseUrlHash(location.hash),
                            taId = hash[0] === 'trustac' && !empty(hash[1]) ? hash[1] : null;

                        if (cpan && !empty(taId)) {
                            var tabId = 'ta_tab_' + taId;
                            var oTabOpened = Ext.getCmp(tabId);
                            if (oTabOpened) {
                                cpan.setActiveTab(tabId);
                            } else {
                                var gridPanel = Ext.getCmp('ta-tab-panel-grid');
                                if (gridPanel) {
                                    gridPanel.openAccountingTab(tabId);
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    if (allowedPages.has('time-log-summary')) {
        mainTabPanel.add({
            id: 'time-log-summary-tab',
            title: _('Time Log Summary'),
            hash: '#time-log-summary',
            scripts: true,
            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);

                    if (typeof initTimeLogSummary === 'function') {
                        initTimeLogSummary();
                    }
                }
            }
        });
    }

    if (allowedPages.has('lms')) {
        mainTabPanel.add({
            id: 'lms-tab',
            title: '<img style="height: 40px; width: 150px" src="' + baseUrl + '/images/default/logo_officio_studio.png" alt="Studio Learning Platform">',
            titleQuickLink: _('Officio Studio'),
            mainMenuItemWithPadding: true,
            booDisallowTabSwitching: true,
            cellCls: 'x-table-layout-cell-image '
        });
    }

    if (allowedPages.has('help')) {
        var type = 'help';
        var ds = new Ext.data.Store({
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/help/index/search-via-post',
                method: 'post',
            }),

            baseParams: {
                type: type
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'id'},
                {name: 'value'},
                {name: 'type'},
                {name: 'section_type'}
            ])
        });

        var arrComponentsToRender = [];
        arrComponentsToRender.push({
            xtype: 'box',
            autoEl: {
                tag: 'a',
                href: '#',
                style: 'padding: 5px 20px 5px; color: #FFF; font-size: 16px;',
                html: '<i class="las la-question-circle"></i>' + _('Can\'t find your answer here ? Ask us.')
            },

            listeners: {
                scope: this,
                render: function (c) {
                    c.getEl().on('click', function () {
                        var wnd = new HelpSupportWindow();
                        wnd.showDialog();
                    }, this, {stopEvent: true});
                }
            }
        });

        arrComponentsToRender.push({
            id: 'help-toolbar-quick-search',
            xtype: 'combo',
            store: ds,
            displayField: 'value',
            typeAhead: false,
            emptyText: _('Search...'),
            loadingText: _('Searching...'),
            anchor: '100%',
            triggerClass: 'x-form-search-trigger',
            listClass: 'no-pointer',
            pageSize: 0,
            minChars: 2,
            queryDelay: 750,
            width: 400,
            listWidth: 389,
            doNotAutoResizeList: true,
            cls: 'quick-search-field',
            itemSelector: 'div.x-combo-list-item',
            enableKeyEvents: true,

            listeners: {
                keyup: function (combobox, e) {
                    // Run searching on Enter, don't try to run it again
                    if (e.getKey() == e.ENTER) {
                        if (combobox.dqTask) {
                            combobox.dqTask.cancel();
                        }
                        combobox.doQuery(combobox.getRawValue());
                    }
                }
            },

            onSelect: function (record) {
                var combo = this;
                switch (record.data['type']) {
                    case '':
                        break;

                    case 'article':
                    default:
                        var iframePanel = Ext.getCmp('help-centre-iframe');
                        iframePanel.setSrc(topBaseUrl + '/help/index/index?type=' + record.data['section_type'] + '#q' + record.data['id']);

                        // Hide the search list
                        combo.collapse();
                        break;
                }
            }
        });

        mainTabPanel.add({
            id: 'help-tab',
            hash: '#help',

            title: _('Help Centre'),
            deferredRender: false,

            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: [
                new Ext.ux.TabUniquesNavigationMenu({}),

                new Ext.ux.TabCustomRightSection({
                    additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
                    arrComponentsToRender: arrComponentsToRender
                })
            ],

            // We'll open this from the top main menu
            booHideInMainMenu: true,
            booHideInQuickLinks: true,

            items: {
                id: 'help-centre-iframe',
                xtype: 'iframepanel',
                title: _('Help Centre'),
                defaultHelpUrl: baseUrl + '/help/index/index?type=help',
                defaultSrc: baseUrl + '/help/index/index?type=help',
                height: initPanelSize() - 2,
                frameConfig: {
                    autoLoad: {
                        width: '100%'
                    },
                    style: 'height: ' + (initPanelSize() - 2) + 'px;'
                }
            },

            listeners: {
                activate: function (thisTabPanel) {
                    var hash = parseUrlHash(location.hash);
                    if (hash.length == 2 && hash[0] == 'help' && !empty(hash[1])) {
                        var tab = thisTabPanel.getActiveTab();
                        tab.setSrc(tab.defaultHelpUrl + '#' + hash[1]);
                    } else {
                        setUrlHash(this.hash, 1);
                    }
                }
            }
        });

        if (allowedPages.has('lms')) {
            mainTabPanel.add({
                id: 'ilearn-tab',
                hash: '#ilearn',

                title: _('iPractice'),
                xtype: 'tabpanel',
                cls: 'clients-tab-panel',
                plain: true,
                activeTab: 0,
                plugins: [new Ext.ux.TabUniquesNavigationMenu({})],

                // We'll open this from the top main menu
                booHideInMainMenu: true,
                booHideInQuickLinks: true,

                items: {
                    xtype: 'iframepanel',
                    title: _('iPractice'),
                    height: initPanelSize() - 2,
                    deferredRender: false,
                    defaultSrc: lmsSettings.url,

                    frameConfig: {
                        autoLoad: {
                            width: '100%'
                        },

                        style: 'height: ' + (initPanelSize() - 2) + 'px;'
                    }
                },

                listeners: {
                    activate: function () {
                        setUrlHash(this.hash, 1);
                    }
                }
            });
        }
    }

    if (allowedPages.has('admin')) {
        mainTabPanel.add({
            id: 'admin-tab',
            hash: '#admin',
            closable: true,
            title: _('Admin'),
            xtype: 'tabpanel',
            cls: 'clients-tab-panel',
            plain: true,
            activeTab: 0,
            plugins: [new Ext.ux.TabUniquesNavigationMenu({})],

            // We'll open this from the top main menu
            booHideInMainMenu: true,
            booHideInQuickLinks: true,

            items: {
                id: 'admin-sub-tab',
                title: _('Admin'),
                openUrl: baseUrl + '/default/admin/index',
                autoLoad: {
                    url: baseUrl + '/default/admin/index',
                    callback: function () {
                        adjustHeight();

                        var hash = parseUrlHash(location.hash);
                        if (hash.length > 1) {
                            $('#admin_section_frame').attr('src', baseUrl + '/superadmin/' + hash[1] + '#' + hash[2]);
                        } else {
                            var url = Ext.getCmp('admin-tab').openUrl;
                            if (url != $('#admin_section_frame').attr('src')) {
                                $('#admin_section_frame').attr('src', url);
                            }
                        }
                    }
                }
            },

            listeners: {
                activate: function () {
                    setUrlHash(this.hash, 1);

                    this.checkInterval = setInterval(function () {
                        var curHeight = $('#admin_section_frame').height();
                        curHeight = Math.max(initPanelSize() - 2, curHeight);

                        var oTab = Ext.getCmp('admin-sub-tab');
                        if (curHeight != oTab.getHeight()) {
                            oTab.setHeight(curHeight);
                        }
                    }, 500);
                },

                deactivate: function () {
                    if (this.checkInterval) {
                        clearInterval(this.checkInterval);
                    }
                }
            }
        });
    }

    setActivePage();
    toggleTabPanelHeaders();

    if (allowedPages.has('homepage-quick-menu')) {
        renderQuickLinks(arrHomepageSettings.settings.quick_links, false);
    }
}

function toggleTabPanelHeaders() {
    var thisTabPanel = Ext.getCmp('main-tab-panel'),
        tabsCount = thisTabPanel.items.getCount();

    if (tabsCount) {
        for (var i = 0; i < tabsCount; i++) {
            thisTabPanel.hideTabStripItem(i);
        }

        thisTabPanel.getEl().addClass('main-tabs-no-bg');
        // if (tabsCount == 1 && is_client) {
        //     thisTabPanel.hideTabStripItem(0);
        //     thisTabPanel.getEl().addClass('main-tabs-no-bg');
        // } else {
        //     thisTabPanel.unhideTabStripItem(0);
        //     thisTabPanel.getEl().removeClass('main-tabs-no-bg');
        // }
    }
}

function getActiveMainTabPanel() {
    var tabPanel = Ext.getCmp('main-tab-panel');
    if (tabPanel.items.getCount() == 1 && is_client) {
        var activeTab = tabPanel.items.first();
        switch (activeTab.id) {
            case 'applicants-tab':
                tabPanel = Ext.getCmp('applicants-tab-panel');
                break;

            case 'contacts-tab':
                tabPanel = Ext.getCmp('contacts-tab-panel');
                break;

            default:
        }
    }

    return tabPanel;
}

function openMPNewProfilePage() {
    Ext.Msg.hide();

    Ext.getBody().mask('Processing...');
    Ext.Ajax.request({
        url: baseUrl + '/superadmin/marketplace/get-marketplace-profiles-list/',

        params: {
            start: 0,
            limit: 1
        },

        success: function (result) {
            Ext.getBody().unmask();
            window.open(Ext.decode(result.responseText).marketplace_new_profile_url);
        },

        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error('Cannot generate link. Please try again later.');
        }
    });
}

function addAdminPage(url) {
    var tabPanel = getActiveMainTabPanel();
    var adminTab = Ext.getCmp('admin-tab');

    if (adminTab) {
        if (!empty(url)) {
            adminTab.openUrl = url;
            $('#admin_section_frame').attr('src', url);
        }

        tabPanel.setActiveTab('admin-tab');
    }
}

function setActivePage(url) {
    //load tab by URL hash value
    var hash = parseUrlHash(location.hash);
    var mpan = Ext.getCmp('main-tab-panel');

    switch (hash[0]) {
        case 'admin':
            addAdminPage(url);
            break;

        case 'help':
            addHelpPage();
            break;

        case 'ilearn':
            addIlearnPage();
            break;

        default:
            if (hash[0] === 'applicants') {
                // Forces to open the specific tab/subtab
                Ext.getCmp(hash[0] + '-tab').booTabActivated = false;
            }

            mpan.setActiveTab(hash[0] + '-tab');

            var tasksPanelId = '';
            var taskId = '';
            var activeStatusFilter = '';
            if (hash[0] === 'tasks' && !empty(hash[1])) {
                tasksPanelId = 'tasks-panel-global';

                if (['', 'active', 'completed', 'due_today', 'due_tomorrow', 'due_next_7_days'].has(hash[1])) {
                    activeStatusFilter = hash[1];
                } else {
                    taskId = hash[1];
                }
            } else if (hash[0] === 'clients' && !empty(hash[3]) && hash[3] === 'tasks' && !empty(hash[4])) {
                tasksPanelId = 'tasks-panel-client-' + hash['2'];
                taskId = hash[4];
            } else if (hash[0] === 'prospects' && !empty(hash[3]) && hash[3] === 'tasks' && !empty(hash[4])) {
                tasksPanelId = 'tasks-panel-prospect-' + hash['2'];
                taskId = hash[4];
                var pan = Ext.getCmp('prospects-tab-panel');
                if (pan) {
                    pan.openProspectTask(hash[2], hash[4]);
                    break;
                }
            }

            if (!empty(tasksPanelId)) {
                var tasksPanel = Ext.getCmp(tasksPanelId);
                if (tasksPanel) {
                    if (!empty(taskId)) {
                        tasksPanel.TasksGrid.setFocusToTask(taskId);
                    }

                    if (!empty(activeStatusFilter)) {
                        tasksPanel.TasksToolbar.setFilter(activeStatusFilter);
                    }
                }
            }
            break;
    }

    // Automatically activate the first tab
    if (empty(mpan.getActiveTab()) && mpan.items.getCount()) {
        mpan.setActiveTab(mpan.items.first().getId());
    }

    // Check if we need show any subscription dialog
    var cookie = Cookies.get('subscription_notice');
    switch (cookie) {
        case 'trial_expire':
        case 'trial_expired':
            showTrialWindow();
            break;

        case 'account_expire':
            var booShowDialog = true;
            try {
                var hiddenOn = Ext.state.Manager.get('renew_hidden_on');
                if (!empty(hiddenOn)) {
                    var savedDate = new Date(hiddenOn);
                    var today = new Date();

                    // Don't show the dialog if it was shown less than 1 day ago
                    var diffDays = (today - savedDate) / (1000 * 60 * 60 * 24);
                    if (diffDays < 1) {
                        booShowDialog = false;
                    }
                }
            } catch (e) {
            }

            if (booShowDialog) {
                showAccountWillExpireWindow();
            }
            break;

        case 'account_expired':
            showAccountWillExpireWindow();
            break;

        case 'charge_interrupted':
            showChargeInterruptedWindow();
            break;

        case 'account_suspended':
            showAccountSuspendedWindow();
            break;

        case 'password_should_be_changed':
        case 'password_should_be_changed_first_time':
            var wnd = new ChangePasswordDialog({
                booFirstTime: cookie === 'password_should_be_changed_first_time',
                passwordValidDays: passwordValidDays
            });
            wnd.show();
            wnd.center();
            wnd.syncShadow();
            break;

        default:
            // Do nothing
            break;
    }
}

var popupBlockerChecker = {
    openedUrl: null,

    check: function (popup_window, url) {
        var _scope = this;
        this.openedUrl = url;

        if (popup_window) {
            if (/chrome/.test(navigator.userAgent.toLowerCase())) {
                setTimeout(function () {
                    _scope._is_popup_blocked(_scope, popup_window);
                }, 200);
            } else {
                popup_window.onload = function () {
                    _scope._is_popup_blocked(_scope, popup_window);
                };
            }
        } else {
            _scope._displayError();
        }
    },

    _is_popup_blocked: function (scope, popup_window) {
        if ((popup_window.innerHeight > 0) == false) {
            scope._displayError();
        }
    },

    _displayError: function () {
        var id = Ext.id();
        var msg = String.format(
            '<div style="margin: 10px">' +
            'You will be directed to the Learning Platform in a new browser tab. ' +
            '<a href="{0}" target="_blank" class="bluelink" onclick="Ext.getCmp(\'{1}\').close();">OK</a>' +
            '</div>',
            this.openedUrl,
            id
        );

        var win = new Ext.Window({
            id: id,
            title: 'Warning',
            modal: true,
            plain: true,
            width: 250,
            autoHeight: true,
            resizable: false,

            items: {
                html: msg
            }
        });

        win.show();
    }
};

function loginLms(redirectUrl) {
    if (lmsSettings.test_mode) {
        Ext.simpleConfirmation.info(_('Officio Studio is disabled on this website.'));
        return;
    }

    Ext.getBody().mask(_('Generating URL...'));

    Ext.Ajax.request({
        url: baseUrl + '/default/index/generate-lms-url',

        params: {
            redirectUrl: Ext.encode(empty(redirectUrl) ? '' : redirectUrl)
        },

        success: function (result) {
            Ext.getBody().unmask();

            var resultData = Ext.decode(result.responseText);
            if (resultData.success) {
                var popup = window.open('about:blank');
                popupBlockerChecker.check(popup, resultData.url);
                if (popup) {
                    popup.document.write('<div style="margin: 80px auto; padding: 20px; font-size: x-large; border-radius: 10px; background-color: #D8EDFC; color: #6A6C71; width: 400px;">' + _('Loading Officio Studio, please wait...') + '</div>');
                    popup.location.href = resultData.url;
                }
            } else {
                Ext.simpleConfirmation.error(resultData.message);
            }
        },

        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error(_('Cannot generate link. Please try again later.'));
        }
    });
}

function markBannerViewed() {
    Ext.Ajax.request({
        url: baseUrl + '/news/index/save-banner-last-viewed-time',

        success: function (result) {
            var resultDecoded = Ext.decode(result.responseText);
            if (!resultDecoded.success) {
                Ext.simpleConfirmation.error(resultDecoded.message);
            }
        },

        failure: function () {
            Ext.simpleConfirmation.error(_('Cannot save changes. Please try again later'));
        }
    });
}

function showTopBannerMessage() {
    Ext.Ajax.request({
        url: baseUrl + '/news/index/get-top-banner-message',

        success: function (result) {
            var res = Ext.decode(result.responseText);
            if (res.success) {
                notif({
                    message: res.news,

                    onClose: function () {
                        markBannerViewed();
                    },

                    onContentClick: function () {
                        if (allowedPages.has('lms')) {
                            loginLms();
                        }
                    }
                });
            }
        }
    });
}

function initUserMenu() {
    var menuEl = Ext.get('user-navigation-button');
    if (!menuEl) {
        return;
    }

    var booShowChangePassword = typeof (userProfileSettings) !== "undefined" && userProfileSettings.can_edit_profile;
    var booShowMyNotes = allowedPages.has('homepage-user-notes');
    var booShowMyLinks = allowedPages.has('homepage-user-links');
    var booShowEditQuickMenu = allowedPages.has('homepage-quick-menu');

    if (!booShowChangePassword && !booShowMyNotes && !booShowMyLinks && !booShowEditQuickMenu) {
        // We don't want to show a menu because there are no items to show,
        // so hide the menu expanding icon
        var menuArrow = menuEl.query('.dropdown_menu');
        if (menuArrow.length) {
            Ext.get(menuArrow[0]).hide();
        }
        return;
    }

    var menuId = 'user-navigation-menu';
    menuEl.on('mousedown', function (e) {
        var menu = Ext.getCmp(menuId);
        var menuWidth = 220;
        if (!menu) {
            // We use 1 dialog for Links and Notes
            // So, if there is no access - show only 1 section
            var myNotesTitle;
            if (booShowMyNotes && booShowMyLinks) {
                myNotesTitle = _('My Notes and Links');
            } else if (booShowMyNotes) {
                myNotesTitle = _('My Notes');
            } else {
                myNotesTitle = _('My Links');
            }

            var arrMenuItems = [
                {
                    text: _('Change Password'),
                    hidden: !booShowChangePassword,
                    handler: function () {
                        var wnd = new UserProfileDialog({});
                        wnd.show();
                        wnd.center();
                        wnd.syncShadow();
                    }
                }, {
                    xtype: 'menuseparator',
                    hidden: !booShowMyNotes && !booShowMyLinks && is_client
                }, {
                    text: myNotesTitle,
                    hidden: !booShowMyNotes && !booShowMyLinks,
                    handler: function () {
                        var html;
                        if (booShowMyNotes && booShowMyLinks) {
                            html = '<div id="linksForm" style="height: 150px; overflow: auto"></div>' +
                                '<div style="border-bottom: 1px dashed grey; padding: 10px 0; margin-bottom: 10px">' +
                                '<a href="#" onclick="qLinks({action: \'add\'}); return false;">' + _('Add Links') + '</a>' +
                                '</div>' +
                                '<div id="notesForm" style="height: 150px; overflow: auto"></div>' +
                                '<div><a href="#" onclick="note({action: \'add\', type: \'homepage\'}); return false;">' + _('Add Notes') + '</a></div>';
                        } else if (booShowMyNotes) {
                            html = '<div id="notesForm" style="height: 300px; overflow: auto"></div>' +
                                '<div><a href="#" onclick="note({action: \'add\', type: \'homepage\'}); return false;">' + _('Add Notes') + '</a></div>';
                        } else {
                            html = '<div id="linksForm" style="height: 300px; overflow: auto"></div>' +
                                '<div><a href="#" onclick="qLinks({action: \'add\'}); return false;">' + _('Add Links') + '</a></div>';
                        }

                        var wnd = new Ext.Window({
                            layout: 'fit',
                            title: myNotesTitle,
                            resizable: false,
                            width: 600,
                            autoHeight: true,
                            modal: true,
                            y: 10,

                            anim: {
                                endOpacity: 1,
                                easing: 'easeIn',
                                duration: .3
                            },

                            items: {
                                html: html
                            },

                            listeners: {
                                show: function () {
                                    if (booShowMyNotes) {
                                        showHomepageNotes();
                                    }

                                    if (booShowMyLinks) {
                                        showLinks();
                                    }
                                }
                            }
                        });

                        wnd.show();
                    }
                }, {
                    text: _('Edit Quick Menu'),
                    hidden: !booShowEditQuickMenu,
                    handler: function () {
                        showQuickLinksSettingsDialog();
                    }
                }
            ];

            // Temporary removed
            /*
            if (typeof (userProfileSettings) !== "undefined" && userProfileSettings.admin_links) {
                arrMenuItems.push('-');

                Ext.each(userProfileSettings.admin_links, function (oLink) {
                    arrMenuItems.push({
                        text: oLink['title'],
                        handler: function () {
                            setUrlHash('#admin');
                            setActivePage(oLink['link']);
                        }
                    });
                });
            }
            */

            menu = new Ext.menu.Menu({
                id: menuId,
                cls: 'white-big-menu',
                width: menuWidth,

                items: arrMenuItems,

                listeners: {
                    'show': function () {
                        Ext.getCmp('main-tab-panel').setDisabled(true);
                    },
                    'hide': function () {
                        Ext.getCmp('main-tab-panel').setDisabled(false);
                    }
                }
            });
        }
        e.stopEvent();

        var arrCoords = menuEl.getXY();

        // Show the menu on the left side of the profile link
        // But it can be too short, so the menu will be not fully visible
        // That's why try to show it to the left of it if needed
        if (menuEl.getWidth() < menuWidth / 2) {
            arrCoords[0] -= (menuWidth / 2 - menuEl.getWidth());
        }

        arrCoords[1] += 20;
        menu.showAt(arrCoords);
    });

    var menuTimeoutId;
    var checkIsMouseOutsideQuickMenu = function () {
        var menu = Ext.getCmp(menuId);

        if (menu && !$('#user-navigation-button').is(':hover') && !$('#' + menuId).is(':hover')) {
            menu.hide();
            clearTimeout(menuTimeoutId);
        } else {
            menuTimeoutId = setTimeout(function () {
                checkIsMouseOutsideQuickMenu();
            }, 500);
        }
    };

    menuEl.on('mouseout', function () {
        menuTimeoutId = setTimeout(function () {
            checkIsMouseOutsideQuickMenu();
        }, 500);
    });
}

//////////////////////////////////////////////
Ext.onReady(function () {
    Ext.QuickTips.init();

    var storageProvider = new Ext.ux.state.LocalStorageProvider({prefix: 'uniques-'});
    if (storageProvider.getStorageObject() === false) {
        // Use cookie storage if local storage is not available
        storageProvider = new Ext.state.CookieProvider({
            expires: new Date(new Date().getTime() + (1000 * 60 * 60 * 24 * 365)) //1 year from now
        })
    }
    Ext.state.Manager.setProvider(storageProvider);

    initUserMenu();

    // Load employer-related data in a separate request - to speedup home page loading
    refreshSettings('employer_settings');

    UserPingStatus();

    addMainTabPanel();

    // Send a request to load and show a "special announcement" message
    if (allowedPages.has('homepage-announcements') && arrHomepageSettings['announcements']['special_announcement_enabled']) {
        showTopBannerMessage();
    }

    // Don't use the full width - as it is overlapped with the right menu
    $('#officio-quick-links').css('padding-right', ($('.system-menu').width() + 50) + 'px');

    el = Ext.get('officio-menu-dashboard');
    if (el) {
        el.on('click', function () {
            var thisTabPanel = Ext.getCmp('main-tab-panel');
            thisTabPanel.setActiveTab('homepage-tab');
        });
    }
});
