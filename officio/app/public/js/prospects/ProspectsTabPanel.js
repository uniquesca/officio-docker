var ProspectsTabPanel = function(config) {
    Ext.apply(this, config);
    var thisTabPanel = this;

    this.defaultTabId = config.panelType + '-tab-default';
    this.widthCookieId = config.panelType + '_west_panel_width';
    this.queuePanelHeightCookieId = config.panelType + '_west_panel_queue_height';

    // Prospects and MP tabs have different items under the Today's Prospects section
    var arrTodayItems = [];
    if (config.panelType === 'marketplace') {
        arrTodayItems = [{
            itemId:           'invited',
            itemName:         'Invited',
            booIsDefaultItem: arrMarketplaceSettings.default_tab === 'invited',
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }, {
            itemId:           'all-prospects',
            itemName:         'All Prospects',
            booIsDefaultItem: arrMarketplaceSettings.default_tab === 'all-prospects',
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }];
    } else {
        arrTodayItems = [{
            itemId:           'waiting-for-assessment',
            itemName:         'Waiting for Assessment',
            booIsDefaultItem: true,
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }, {
            itemId:           'qualified-prospects',
            itemName:         'Qualified Prospects',
            booIsDefaultItem: false,
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }, {
            itemId:           'unqualified-prospects',
            itemName:         'Unqualified Prospects',
            booIsDefaultItem: false,
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }, {
            itemId:           'all-prospects',
            itemName:         'All Prospects',
            booIsDefaultItem: false,
            booIsTodayItem:   true,
            booIsSpecialTab:  true
        }];
    }

    this.arrProspectNavigationTabs = arrTodayItems.concat([{
        itemId:           'office',
        itemName:         arrApplicantsSettings.office_label,
        booIsDefaultItem: false,
        booIsTodayItem:   false,
        booIsSpecialTab:  true
    }, {
        itemId:           'search-prospects',
        itemName:         'Search Result',
        booIsDefaultItem: false,
        booIsTodayItem:   false,
        booIsSpecialTab:  true
    }, {
        itemId:           'new-prospect',
        itemName:         'New Prospect',
        booIsDefaultItem: false,
        booIsTodayItem:   false,
        booIsSpecialTab:  false
    }, {
        itemId:           'advanced-search',
        itemName:         'Advanced Search',
        booIsDefaultItem: false,
        booIsTodayItem:   false,
        booIsSpecialTab:  false
    }, {
        itemId:           'prospect',
        itemName:         '',
        booIsDefaultItem: false,
        booIsTodayItem:   false,
        booIsSpecialTab:  false
    }]);

    // Used to identify where toolbar must be showed
    this.arrProspectNavigationSubTabs = [{
        itemId:         'new-prospect',
        itemName:       'New Prospect',
        booWithToolbar: true
    }, {
        itemId:         'profile',
        itemName:       '',
        booWithToolbar: true
    }, {
        itemId:         'occupations',
        itemName:       '',
        booWithToolbar: true
    }, {
        itemId:         'business',
        itemName:       '',
        booWithToolbar: true
    }, {
        itemId:         'assessment',
        itemName:       '',
        booWithToolbar: true
    }];


    var arrPlugins = [
        new Ext.ux.TabUniquesMenu({
            panelType:              config.panelType,
            arrOpenedTabs:          [],
            arrRecentlyOpenedTabs:  [],
            lastViewedRecordsTab:   null,
            lastViewedRecordsPopup: null,
            lastViewedRecordsGrid:  null,

            openTabOfTheRecord: function (oParams) {
                var tab = Ext.getCmp(oParams['tabId']);
                if (tab) {
                    thisTabPanel.setActiveTab(tab);
                } else {
                    thisTabPanel.loadProspectsTab(oParams);
                }
            }
        })
    ];

    var arrComponentsToRender = [];

    if (arrProspectSettings.arrAccess[config.panelType].left_panel.search_panel) {
        var passportLabel = '';
        if (arrProspectSettings.arrAdvancedSearchFields.length) {
            arrProspectSettings.arrAdvancedSearchFields.map(function (oField) {
                if (oField['q_field_unique_id'] === 'qf_country_of_citizenship') {
                    passportLabel = empty(oField['q_field_prospect_profile_label']) ? oField['q_field_label'] : oField['q_field_prospect_profile_label'];
                }
            });
        }

        arrComponentsToRender.push({
            xtype: 'quicksearchfield',
            quickSearchFieldEmptyText: _('Search prospects...'),
            quickSearchFieldEmptyTextOnHover: _('Enter search keywords...'),
            quickSearchFieldParamName: 'filter',
            booViewSavedSearches: false,
            booViewAdvancedSearch: arrProspectSettings.arrAccess[config.panelType].tabs.advanced_search.view,


            quickSearchFieldStore: new Ext.data.Store({
                autoLoad: false,

                proxy: new Ext.data.HttpProxy({
                    url:    baseUrl + '/prospects/index/get-prospects-list',
                    method: 'post'
                }),

                baseParams: {
                    panelType:     config.panelType,
                    booLoadAllIds: false,
                    type:          'search-prospects'
                },

                reader: new Ext.data.JsonReader({
                    root:          'rows',
                    totalProperty: 'totalCount',
                    id:            'prospect_id'
                }, [
                    {
                        name: 'prospect_id'
                    }, {
                        name: 'lName'
                    }, {
                        name: 'fName'
                    }, {
                        name: 'email'
                    }, {
                        name: 'qf_country_of_citizenship'
                    }
                ])
            }),

            quickSearchFieldRecordRenderer: function (val, p, record) {
                var res = '<div>' + record.data['fName'] + ' ' + record.data['lName'] + '</div>';

                if (!empty(passportLabel)) {
                    res += '<p style="padding-top: 3px;">' + passportLabel + ': ' + record.data['qf_country_of_citizenship'] + '</p>';
                }

                return res;
            },

            quickSearchFieldOnRecordClick: function(record) {
                thisTabPanel.loadProspectsTab({
                    tab:   'prospect',
                    tabId: thisTabPanel.panelType + '-ptab-' + record.data.prospect_id,
                    pid:   record.data.prospect_id,
                    title: record.data.fName + ' ' + record.data.lName
                });
            },

            quickSearchFieldAdvancedSearchLinkOnClick: function() {
                thisTabPanel.loadProspectsTab({tab: 'advanced-search'});
            },

            quickSearchFieldAdvancedSearchOnClick: function(record) {
                thisTabPanel.loadProspectsTab({tab: 'advanced-search'});
            },

            quickSearchFieldAdvancedSearchOnDetailsClick: function(record) {
                thisTabPanel.loadProspectsTab({tab: 'advanced-search'});
            },

            quickSearchFieldAdvancedSearchOnFavoritesClick: function(record) {
            },

            quickSearchFieldAdvancedSearchOnDelete: function(record) {
            },

            quickSearchFieldOnShowDetailsClick: function(strSearchText) {
                // Switch to 'All prospects' tab and run search
                var tabPanel = Ext.getCmp(thisTabPanel.panelType + '-tab-panel');
                tabPanel.loadProspectsTab({
                    tab:          'search-prospects',
                    doNotLoadTab: true
                });

                // Apply query
                var prospectsStore = Ext.getCmp(thisTabPanel.panelType + '-grid').getStore();
                if (prospectsStore) {
                    prospectsStore.setBaseParam('filter', Ext.encode(strSearchText)); //add search query to base params
                    prospectsStore.load(); //reload prospects grid
                }
            },

            quickSearchFieldAdvancedSearchStore: new Ext.data.ArrayStore()
        });
    }

    if (arrProspectSettings.arrAccess[config.panelType].add_new_prospect) {
        if (arrComponentsToRender.length) {
            arrComponentsToRender.push({
                html:  '',
                style: 'margin: 10px'
            });
        }

        arrComponentsToRender.push({
            xtype:   'button',
            text:    '<i class="las la-plus"></i>' + _('New Prospect'),
            ctCls:   'orange-btn',
            handler: function () {
                thisTabPanel.loadProspectsTab({tab: 'new-prospect'});
            }
        });
    }

    if (arrComponentsToRender.length) {
        arrPlugins.push(new Ext.ux.TabCustomRightSection({
            additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
            arrComponentsToRender: arrComponentsToRender
        }));
    }

    ProspectsTabPanel.superclass.constructor.call(this, {
        bodyStyle: 'padding: 5px',
        plugins: arrPlugins,
        tabWidth: 120,
        enableTabScroll: true,
        resizeTabs: false,
        deferredRender: false,
        items: [{
            id: thisTabPanel.defaultTabId,
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
        }],

        listeners: {
            'beforetabchange': function (oTabPanel, newTab) {
                if (newTab.id === thisTabPanel.defaultTabId) {
                    return false;
                }
            }
        }
    });

    this.on('afterrender', this.onAfterRender, this);
    this.on('tabchange', this.onTabChange, this);
};

Ext.extend(ProspectsTabPanel, Ext.TabPanel, {
    onTabChange: function(panel, tab) {
        // If opened prospect's tab firstly (page refresh)
        // try to init the grid - so all items will be correctly rendered
        if (['marketplace-ptab-marketplace', 'prospects-ptab-prospects'].has(tab.id)) {
            try {
                var todayGrid = Ext.getCmp(panel.panelType + '-tgrid');
                if (todayGrid) {
                    todayGrid.syncSize();
                }
            } catch (e) {
            }
        }

        this.fixParentPanelHeight();
    },

    isSpecialProspectsTab: function (tab) {
        var booIsSpecialTab = false;
        Ext.each(this.arrProspectNavigationTabs, function (oItem) {
            if (oItem.itemId == tab) {
                booIsSpecialTab = oItem.booIsSpecialTab;
            }
        });

        return booIsSpecialTab;
    },

    onAfterRender: function () {
        var thisPanel = this;

        if (empty(thisPanel.initSettings.tab) || !thisPanel.isSpecialProspectsTab(thisPanel.initSettings.tab)) {
            var defaultTab = '';
            Ext.each(this.arrProspectNavigationTabs, function (oItem) {
                if (oItem.booIsDefaultItem) {
                    defaultTab = oItem.itemId;
                }
            });
            thisPanel.loadProspectsTab({tab: defaultTab}); //default page
        }

        if (!empty(thisPanel.initSettings.tab)) {
            // Open prospect's tab with a delay - fixes issue with page refresh on the Prospect's page and switching to the main list
            setTimeout(function () {
                thisPanel.loadProspectsTab({
                    tab: thisPanel.initSettings.tab,
                    pid: thisPanel.initSettings.prospectId,
                    subtab: thisPanel.initSettings.subTab
                });
            }, 100);
        }
    },

    openProspectTask: function(prospectId, taskId) {
        this.loadProspectsTab({
            tab:    'prospect',
            pid:    prospectId,
            taskId: taskId,
            subtab: 'tasks',
            title:  ''
        });
        this.fixParentPanelHeight();
    },

    loadProspectsTab: function(options) {
        // get prospects tab-panel
        var pan = this;
        var profileTabPanel = null;

        options.closable = true;

        //set tabId for special tab's
        if (pan.isSpecialProspectsTab(options.tab)) {
            options.tabId = pan.panelType + '-ptab-' + pan.panelType;
            options.closable = false;
        }

        //get options
        options.panelType = options.panelType || pan.panelType;
        options.pid       = options.pid || 0;
        options.tab       = options.tab || pan.panelType;
        options.tabId     = options.tabId || pan.panelType + '-ptab-' + (options.pid ? options.pid : options.tab);
        options.subtab    = options.subtab || 'profile';

        if (['all-prospects', 'unqualified-prospects', 'qualified-prospects', 'waiting-for-assessment', 'invited', 'office'].has(options.tab)) {
            options.tabType = 'prospects';
        } else if (options.tab === 'advanced-search') {
            options.tabType = 'advanced_search';
        } else {
            options.tabType = options.tabId;
        }

        if (!options.title) {
            if (['all-prospects', 'unqualified-prospects', 'qualified-prospects', 'waiting-for-assessment', 'invited', 'office'].has(options.tab)) {
                options.title = pan.panelType == 'marketplace' ? _('Marketplace'): _('Prospects');
            } else {
                Ext.each(this.arrProspectNavigationTabs, function (oItem) {
                    if (oItem.itemId == options.tab) {
                        options.title = oItem.itemName;
                    }
                });
            }
        }

        //check if tab also loaded
        var ccrTab = Ext.getCmp(options.tabId);
        if (ccrTab) {

            ccrTab.show();

            //for special tab's - reload tab
            var booOfficesChanged = false;
            if (options.tab == 'office' && options.offices != ccrTab.offices) {
                booOfficesChanged = true;
            }

            if (booOfficesChanged || (ccrTab.tab !== options.tab && options.tabId === pan.panelType + '-ptab-' + pan.panelType)) {
                ccrTab.tab = options.tab;

                if (!empty(options.title)) {
                    ccrTab.setTitle(options.title);
                    pan.fireEvent('titlechange', pan);
                }

                // Apply filtering by type
                if (!options.doNotLoadTab) {
                    var grid = Ext.getCmp(pan.panelType + '-grid');
                    grid.initSettings.tab = options.tab;
                    grid.initSettings.offices = options.offices;

                    var store = grid.getStore();
                    store.setBaseParam('type', options.tab);
                    store.setBaseParam('offices', Ext.encode(options.offices));
                    store.reload();
                }

                var newUrl = '#' + pan.panelType + '/' + options.tab + ((options.tab === 'prospect' && options.pid) ? '/' + options.pid : '');
                newUrl += options.taskId ? '/' + options.taskId : '';
                setUrlHash(newUrl);
            }

            if (options.taskId) {
                if (!profileTabPanel) {
                    profileTabPanel = Ext.getCmp(options.panelType + '-sub-tab-panel-' + options.pid);
                }

                if (profileTabPanel) {
                    profileTabPanel.switchToSpecificTab('tasks');
                }

                var tasksPanel = Ext.getCmp(pan.panelType + '-tasks-panel-prospect-' + options.pid);
                if (tasksPanel) {
                    tasksPanel.TasksGrid.setFocusToTask(options.taskId);
                }
            }

            if (!options.allowReload) {
                return false;
            }

        } else {
            //add new tab
            pan.add({
                id: options.tabId,
                pid: options.pid,
                tab: options.tab,
                oOpenTabParams: options,
                title: empty(options.title) ? 'Loading...' : options.title,
                width: '100%',
                closable: options.closable,
                html: '<div id="div-' + pan.panelType + '-sub-tab-panel-' + options.pid + '" style="width: 100%; background-color: white"></div>',
                listeners: {
                    activate: function() {
                        //set hash when tab is activated
                        var newUrl = '#' + pan.panelType + '/' + options.tab + ((options.tab === 'prospect' && options.pid) ? '/' + options.pid : '');
                        newUrl += options.taskId ? '/' + options.taskId : '';

                        Ext.getCmp(pan.panelType + '-tab').hash = setUrlHash(newUrl);
                    }
                }
            }).show();
        }

        // Load sub tab content
        if (options.tab === 'prospect' || options.tab === 'new-prospect') {
            profileTabPanel = new ProspectsProfileTabPanel({
                panelType: pan.panelType,
                options: options,
                oOpenTabParams: options
            }, pan);

            Ext.getDom(options.tabId).innerHTML = '';
            profileTabPanel.render(options.tabId);
        } else if (options.tab === 'advanced-search') {
            var prospectsSearchPanel = new prospectsAdvancedSearchPanel({
                panelType: pan.panelType,
                oOpenTabParams: options
            });

            Ext.getDom(options.tabId).innerHTML = '';
            prospectsSearchPanel.render(options.tabId);
        } else if (pan.isSpecialProspectsTab(options.tab)) {
            Ext.getDom(options.tabId).innerHTML = '';

            var arrWestPanelItems = [];
            if (arrProspectSettings.arrAccess[pan.panelType].left_panel.queue_panel) {
                var queuePanelHeight = pan.getSettings(pan.queuePanelHeightCookieId) ? pan.getSettings(pan.queuePanelHeightCookieId) - 6 : initPanelSize() / 2;
                var oProspectsLeftSectionPanel = new ProspectsLeftSectionPanel({
                    title: String.format(
                        '<span title="' + _('Select the {0}(s) for which you want to view the client files') + '">{1}</span>',
                        arrApplicantsSettings.office_label,
                        arrApplicantsSettings.office_label + 's'
                    ),

                    subPanelType: 'queue-panel',
                    hideMode: 'offsets',
                    cls: 'big-items-look big-items-look-no-left-padding',
                    arrQueuesToShow: [],
                    panelType: pan.panelType,
                    height: queuePanelHeight
                }, pan);

                if (options.tab == 'office' && empty(options.offices)) {
                    var arrDefaultRecords = oProspectsLeftSectionPanel.getDefaultOffices();
                    if (empty(arrDefaultRecords) || (arrDefaultRecords.length === 1 && arrDefaultRecords[0] === 'favourite')) {
                        arrDefaultRecords = arrApplicantsSettings.queue_settings.queue_selected;
                        arrDefaultRecords = arrDefaultRecords.split(',');
                    }

                    options.offices = arrDefaultRecords;
                }

                arrWestPanelItems.push(oProspectsLeftSectionPanel);

                oProspectsLeftSectionPanel.on('render', function () {
                    var resizer = new Ext.Resizable(oProspectsLeftSectionPanel.getId(), {
                        handles: 's',
                        pinned: true,
                        listeners: {
                            resize: function (resizer, width, height) {
                                Ext.state.Manager.set(pan.queuePanelHeightCookieId, height);
                            }
                        }
                    });
                });
            }

            var oInitSettings = {
                tabId: options.tabId,
                tab: options.tab,
                offices: options.offices
            };

            if (arrProspectSettings.arrAccess[pan.panelType].left_panel.today_prospects) {
                var prospectsTodayPanel = new ProspectsTodayPanel({
                    title: _('Filter By'),
                    cls: 'big-items-look',
                    height: 145,
                    panelType: pan.panelType,
                    initSettings: oInitSettings
                }, pan);
                arrWestPanelItems.push(prospectsTodayPanel);
            }

            if (pan.panelType === 'marketplace') {
                arrWestPanelItems.push({
                    xtype:  'panel',
                    layout: 'table',
                    cls:    'garytxt14',

                    layoutConfig: {
                        tableAttrs: {
                            style: {
                                width: '100%'
                            }
                        },

                        columns: 1
                    },
                    items: [
                        {
                            style: 'padding: 0 15px',
                            html: _('The <b>Marketplace</b> is a service by Officio that sends prospective Immigration clients your way. ' +
                                   'The Prospects that you see on this page are individuals interested in using Immigration services. ' +
                                   'Whether you find them qualified or not, it is a good practice to communicate with them. ' +
                                   'Think of your response as another way of marketing your services and spreading your name. ' +
                                   'To minimize the amount of time you spend on each response, please use <b>Prospects Templates</b>. ' +
                                   'You can define these templates once under <b>Admin | Prospects Questionnaires | Prospects Templates</b>, and use them many times with ease.<br><br>' +
                                   'Those records marked with <span class="premium-member">&nbsp;P&nbsp;</span> indicate Premium Prospects that are more serious about starting their case.<br><br>' +
                                   'If you need any assistance, please email us at <a href="mailto:support@officio.ca" class="blulinkun blulinkun14 norightclick">support@officio.ca</a> or call 1-888-703-7073.')
                        }
                    ]
                });
            } else {
                arrWestPanelItems.push({
                    xtype: 'container',
                    style: 'margin: 30px 15px 0 15px',
                    items: {
                        xtype: 'checkbox',
                        boxLabel: _('Show only Active Prospects'),
                        checked: Ext.state.Manager.get(pan.panelType + '-active-prospects-checkbox', false),
                        hideLabel: true,

                        listeners: {
                            'check': function (thisCheckbox, activeChecked) {
                                var thisGrid = Ext.getCmp(pan.panelType + '-grid');
                                thisGrid.store.setBaseParam('activeChecked', activeChecked);
                                thisGrid.store.load();

                                Ext.state.Manager.set(pan.panelType + '-active-prospects-checkbox', activeChecked);
                            }
                        }
                    }
                });
            }

            var westPanelMinWidth = 250,
                westPanelMaxWidth = 600;
            var westPanelWidth = pan.getSettings(pan.widthCookieId) ? pan.getSettings(pan.widthCookieId) : westPanelMinWidth;
            westPanelWidth = parseInt(westPanelWidth, 10);
            westPanelWidth = isNaN(westPanelWidth) || westPanelWidth < westPanelMinWidth ? westPanelMinWidth : westPanelWidth;

            var prospectsGridPanel = new ProspectsGridPanel({
                panelType: pan.panelType,
                region: 'center',
                width: Ext.getCmp(pan.panelType + '-tab').getWidth() - westPanelWidth - 40,
                height: initPanelSize(),
                initSettings: oInitSettings
            }, this);

            new Ext.Container({
                layout: 'border',
                renderTo: options.tabId,
                width: Ext.getCmp(pan.panelType + '-tab').getWidth(),
                height: initPanelSize(),

                defaults: {
                    split: true,
                    animFloat: false,
                    autoHide: false
                },

                items: [
                    {
                        id: pan.panelType + '-prospects-west-panel',
                        xtype: 'panel',
                        region: 'west',
                        stateful: false,
                        collapsible: false,
                        width: westPanelWidth,
                        minWidth: westPanelMinWidth,
                        maxWidth: westPanelMaxWidth,
                        cls: 'prospects-search-and-tasks',
                        hidden: !arrProspectSettings.arrAccess[pan.panelType].left_panel.view_left_panel,
                        items: arrWestPanelItems,

                        listeners: {
                            'render': function (panel) {
                                // Apply a listener with a delay - otherwise we'll get an error on render
                                panel.on('resize', pan.onPanelWidthChange, pan, {delay: 100});
                            },
                        }
                    },
                    prospectsGridPanel
                ]
            });
        }

        var elems = $('#' + pan.panelType + '-tab').find('.clients-tab-panel .x-tab-panel-body');

        // Magic digits...
        var newMinHeight         = initPanelSize();
        var newMinHeightProspect = newMinHeight - 55;
        elems.css('cssText', 'min-height:' + newMinHeight + 'px !important;');
        if (elems.length > 1) {
            elems.filter(':last').css('cssText', 'min-height:' + newMinHeightProspect + 'px !important;');
        }

        return true;
    },

    getSettings: function(id) {
        return Ext.state.Manager.get(id);
    },

    saveSettings: function(id, value) {
        Ext.state.Manager.set(id, value);
    },

    onPanelWidthChange: function (panel, adjWidth) {
        if (adjWidth === undefined) {
            return;
        }

        var newWidth = this.getWidth() - adjWidth;
        var prospectsGridPanel = Ext.getCmp(this.panelType + '-grid-panel');
        if (prospectsGridPanel) {
            prospectsGridPanel.setWidth(newWidth);
            prospectsGridPanel.syncSize();

            prospectsGridPanel.prospectsGrid.setWidth(newWidth - 40);
            prospectsGridPanel.prospectsGrid.syncSize();
        }

        this.saveSettings(this.widthCookieId, adjWidth);
    },

    fixParentPanelHeight: function() {
        var thisTabPanel = this;
        var activeTab = this.getActiveTab();
        if (activeTab) {
            var toolbar = Ext.getCmp('button-' + thisTabPanel.panelType + '-toolbar-' + activeTab.pid);

            // Magic digits! :)
            var minHeight = initPanelSize(true);
            var additionalHeight = 120;
            if (toolbar && toolbar.isVisible()) {
                additionalHeight += toolbar.getHeight();
            }

            var tabHeight = 0;
            switch (activeTab.id) {
                case thisTabPanel.panelType + '-ptab-advanced-search':
                    tabHeight = $('#' + thisTabPanel.panelType + '-advanced-search').height();
                    break;

                case thisTabPanel.panelType + '-ptab-prospects':
                    tabHeight = $('#' + thisTabPanel.panelType + '-grid-panel').height();
                    break;

                default:
                    tabHeight = $('#' + activeTab.id).height() - 45;
                    break;
            }

            var newHeight = tabHeight < minHeight ? minHeight : tabHeight + additionalHeight;

            this.setHeight(newHeight);
            this.ownerCt.setHeight(newHeight);
            if (Ext.getCmp(thisTabPanel.panelType + '-prospects-west-panel')) {
                Ext.getCmp(thisTabPanel.panelType + '-prospects-west-panel').setHeight(newHeight);
            }
        }
    }
});
