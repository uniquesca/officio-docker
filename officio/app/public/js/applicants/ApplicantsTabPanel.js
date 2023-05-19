var ApplicantsTabPanel = function(config) {
    Ext.apply(this, config);
    var thisTabPanel = this;

    this.defaultTabId = config.panelType + '-tab-default';
    this.advancedSearchTabId = config.panelType + '-tab-advanced-search';
    this.analyticsTabId = config.panelType + '-tab-analytics';
    this.advancedSearchTabNumber = 0;
    this.analyticsTabNumber = 0;
    this.clientType = config.panelType == 'applicants' ? 'client' : 'contact';
    this.clientLabel = config.panelType == 'applicants' ? _('Client') : _('Contact');

    var booCanAddClient = false;
    if (config.panelType == 'applicants') {
        booCanAddClient = this.hasAccess('employer', 'add') || this.hasAccess('individual', 'add');
    } else {
        booCanAddClient = this.hasAccess('contact', 'add');
    }

    var arrPlugins = [
        new Ext.ux.TabUniquesNavigationMenu({}),

        new Ext.ux.TabUniquesMenu({
            panelType: config.panelType,
            arrOpenedTabs: [],
            lastViewedRecordsTab:   null,
            lastViewedRecordsPopup: null,
            lastViewedRecordsGrid:  null,

            // Load previously opened cases
            arrRecentlyOpenedTabs: function() {
               var arrTabs = [];

                if (config.panelType === 'applicants') {
                    var recordOrder = 0;
                    Ext.each(arrApplicantsSettings.last_saved_cases, function (oRecord) {
                        var parentName = oRecord.applicantName;
                        if (oRecord.memberType == 'employer' && !empty(oRecord.caseId) && !empty(oRecord.caseType)) {
                            if (!empty(oRecord.caseEmployerName)) {
                                parentName = oRecord.caseEmployerName;
                            }

                            parentName += ' | ' + thisTabPanel.getCaseTypeNameByCaseTypeId(oRecord.caseType);
                        } else if (!empty(oRecord.caseEmployerName) && oRecord.applicantId != oRecord.caseEmployerId) {
                            parentName = oRecord.caseEmployerName + ' | ' + parentName;
                        }

                        var showCaseName = '';
                        if (typeof oRecord.caseId !== 'undefined') {
                            if (empty(oRecord.caseId) || empty(oRecord.caseType)) {
                                showCaseName = _('Case 1');
                            } else {
                                showCaseName = empty(oRecord.caseName) ? '' : '(' + oRecord.caseName + ')';
                            }
                        }

                        oRecord.tabId = thisTabPanel.generateTabId(thisTabPanel.panelType, oRecord.applicantId, oRecord.caseId);
                        oRecord.activeTab = empty(oRecord.caseId) ? 'profile' : 'case_details';
                        arrTabs.push({
                            r_id: oRecord.tabId,
                            r_name: (parentName + ' ' + showCaseName).trim(),
                            r_order: recordOrder++,
                            r_is_opened: false,
                            tab_opening_params: oRecord
                        });
                    });
                }

               return arrTabs;
            }(),

            openTabOfTheRecord: function (oParams) {
                if (oParams.hasOwnProperty('tabId')) {
                    var tab = Ext.getCmp(oParams['tabId']);
                    if (tab) {
                        thisTabPanel.setActiveTab(tab);
                        return false;
                    }
                }

                if (oParams.hasOwnProperty('tabType') && oParams['tabType'] === 'advanced_search') {
                    thisTabPanel.openAdvancedSearchTab(oParams['searchId'], oParams['savedSearchName'], oParams['booShowAnalyticsTab'], true);
                } else {
                    if (oParams.hasOwnProperty('activeTab')) {
                        thisTabPanel.openApplicantTab(oParams, oParams['activeTab']);
                    } else {
                        thisTabPanel.openApplicantTab(oParams);
                    }
                }
            }
        })
    ];

    var arrComponentsToRender = [];

    // Always show the quick search (except of clients)
    var booShowQuickSearch = !is_client;
    if (booShowQuickSearch) {
        arrComponentsToRender.push({
            id: config.panelType + '_quicksearchfield',
            xtype: 'quicksearchfield',
            quickSearchFieldEmptyText: config.panelType === 'applicants' ? _('Search clients...') : _('Search contacts...'),
            quickSearchFieldEmptyTextOnHover: _('Enter search keywords...'),
            quickSearchFieldParamName: 'search_query',
            booViewSavedSearches: hasAccessToRules(config.panelType, 'search', 'view_saved_searches'),
            booViewAdvancedSearch: hasAccessToRules(config.panelType, 'search', 'view_advanced_search'),

            quickSearchFieldStore: new Ext.data.Store({
                autoLoad: false,

                proxy: new Ext.data.HttpProxy({
                    url:    topBaseUrl + '/applicants/search/get-applicants-list',
                    method: 'post'
                }),

                baseParams: {
                    // search_query will be set in the Ext.ux.QuickSearchField
                    search_for: this.panelType,
                    quick_search: 1,
                    boo_conflict_search: 0//trim(this.owner.conflictSearchFieldValue) != '' ? 1 : 0
                },

                reader: new Ext.data.JsonReader({
                    root: 'items',
                    totalProperty: 'count',
                    fields: [
                        'user_id',
                        'user_type',
                        'user_name',
                        'user_parent_id',
                        'user_parent_name',
                        'case_type_id',
                        'applicant_id',
                        'applicant_name',
                        'applicant_type'
                    ]
                })
            }),

            quickSearchFieldRecordRenderer: function (val, p, rec) {
                var name = rec.data.user_name,
                    case_number = '';
                if (rec.data.applicant_name && rec.data.user_type != 'case' && (!empty(rec.data.user_parent_id) || rec.data.user_type == 'individual')) {
                    case_number = empty(rec.data.applicant_name) ? '' : ' (' + rec.data.applicant_name + ')';
                    name = rec.data.user_name + case_number;
                } else if(rec.data.user_type == 'case' && rec.data.applicant_type == 'employer') {
                    case_number = empty(rec.data.user_name) ? '' : ' (' + rec.data.user_name + ')';

                    name = thisTabPanel.getCaseTypeNameByCaseTypeId(rec.data.case_type_id) + case_number;
                }

                var indent = 0;
                if (!empty(rec.data.user_parent_id)) {
                    indent = rec.json.linked_to_case ? 20 : 10;
                }

                return String.format(
                    '<div style="padding-left: {0}"><a href="#" class="{1}" onclick="return false;" />{2}</a></div>',
                    indent + 'px',
                    '',
                    name
                );
            },

            quickSearchFieldOnRecordClick: function(rec) {
                switch (rec.data.user_type) {
                    case 'case':
                        thisTabPanel.openApplicantTab({
                            applicantId:      rec.data.applicant_id,
                            applicantName:    rec.data.applicant_name,
                            memberType:       rec.data.applicant_type,
                            caseId:           rec.data.user_id,
                            caseName:         rec.data.user_name,
                            caseType:         rec.data.case_type_id,
                            caseEmployerId:   rec.data.applicant_id,
                            caseEmployerName: rec.data.applicant_name
                        }, 'profile');
                        break;

                    case 'individual':
                        if (empty(rec.data.applicant_id)) {
                            thisTabPanel.openApplicantTab({
                                applicantId:      rec.data.user_id,
                                applicantName:    rec.data.user_name,
                                memberType:       rec.data.user_type
                            });
                        } else {
                            thisTabPanel.openApplicantTab({
                                applicantId:      rec.data.user_id,
                                applicantName:    rec.data.user_name,
                                memberType:       rec.data.user_type,
                                caseId:           rec.data.applicant_id,
                                caseName:         rec.data.applicant_name,
                                caseType:         rec.data.case_type_id,
                                caseEmployerId:   rec.data.user_parent_id,
                                caseEmployerName: rec.data.user_parent_name
                            }, 'profile');
                        }
                        break;

                    case 'employer':
                    case 'contact':
                    default:
                        thisTabPanel.openApplicantTab({
                            applicantId:      rec.data.user_id,
                            applicantName:    rec.data.user_name,
                            memberType:       rec.data.user_type
                        });
                }
            },

            quickSearchFieldAdvancedSearchLinkOnClick: function() {
                thisTabPanel.openAdvancedSearchTab(0);
            },

            quickSearchFieldAdvancedSearchOnDelete: function(rec) {
                var oQuickSearchField = this;
                var oQuickSearchFieldGrid = this.thisQuickSearchSavedSearchesGrid;
                var search_id = rec.data['search_id'];

                oQuickSearchFieldGrid.getEl().mask(_('Processing...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/applicants/search/delete-saved-search',
                    params: {
                        search_id: search_id
                    },
                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);

                        var showMsgTime = 2000;
                        if (resultData.success) {
                            showMsgTime = 1000;
                            var idx = oQuickSearchField.quickSearchFieldAdvancedSearchStore.find('search_id', search_id);
                            if (idx != -1) { // Element exists - remove
                                oQuickSearchField.quickSearchFieldAdvancedSearchStore.removeAt(idx);
                                oQuickSearchField.thisQuickSearchResultsWindow.syncShadow();
                            }
                        }

                        var msg = String.format(
                            '<span style="color: {0}">{1}</span>',
                            resultData.success ? 'black' : 'red',
                            resultData.success ? _('Done!') : resultData.message
                        );

                        oQuickSearchFieldGrid.getEl().mask(msg);
                        setTimeout(function () {
                            oQuickSearchFieldGrid.getEl().unmask();
                        }, showMsgTime);
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Selected search cannot be deleted. Please try again later.'));
                        oQuickSearchFieldGrid.getEl().unmask();
                    }
                });
            },

            quickSearchFieldAdvancedSearchOnDetailsClick: function(rec) {
                if (rec.data.search_type !== 'system') {
                    thisTabPanel.openAdvancedSearchTab(rec.data.search_id, rec.data.search_name);
                }
            },

            quickSearchFieldAdvancedSearchOnFavoritesClick: function(rec) {
                thisTabPanel.markSearchAsFavorite(true, rec.data.search_id);
            },

            quickSearchFieldAdvancedSearchOnClick: function(rec) {
                thisTabPanel.openQuickAdvancedSearchTab(rec.data.search_id, rec.data.search_name);
            },

            quickSearchFieldOnShowDetailsClick: function(searchQuery) {
                var searchName = String.format("Search: '{0}'", searchQuery);
                thisTabPanel.openQuickAdvancedSearchTab('quick_search', searchName, searchQuery);
            },

            quickSearchFieldAdvancedSearchStore: new Ext.data.Store({
                autoLoad: false,
                remoteSort: true,

                proxy: new Ext.data.HttpProxy({
                    url: topBaseUrl + '/applicants/search/get-saved-searches'
                }),

                baseParams: {
                    search_type: Ext.encode(config.panelType)
                },

                reader: new Ext.data.JsonReader({
                    root: 'items',
                    totalProperty: 'count',
                    idProperty: 'search_id',
                    fields: [
                        'search_id',
                        'search_type',
                        'search_name',
                        'search_can_be_set_default',
                        'search_default',
                        'search_is_favorite'
                    ]
                })
            })
        });
    }

    this.newClientButtonId;
    if (booCanAddClient) {
        if (arrComponentsToRender.length) {
            arrComponentsToRender.push({
                html:  '',
                style: 'margin-left: 10px'
            });
        }

        this.newClientButtonId = Ext.id();
        arrComponentsToRender.push({
            id: this.newClientButtonId,
            xtype: 'button',
            text: '<i class="las la-plus"></i>' + _('New') + ' ' + thisTabPanel.clientLabel,
            tooltip: String.format(_('Add a new {0} to your account and open a new {0} file'), thisTabPanel.clientLabel.toLowerCase()),
            tooltipType: 'title',
            cls: 'orange-btn',
            handler: thisTabPanel.openApplicantTab.createDelegate(this, [
                {
                    applicantId: 0,
                    applicantName: '',
                    memberType: thisTabPanel.clientType
                }
            ])
        });
    }

    if (arrComponentsToRender.length) {
        arrPlugins.push(new Ext.ux.TabCustomRightSection({
            additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
            arrComponentsToRender: arrComponentsToRender
        }));
    }

    ApplicantsTabPanel.superclass.constructor.call(this, {
        id: config.panelType + '-tab-panel',
        autoHeight: true,

        plugins: arrPlugins,

        items: [
            {
                id: this.defaultTabId,
                hidden: true,
                style: 'padding: 5px;',
                height: initPanelSize() - 12,
                items: [
                    {
                        html: '<div class="white18">' + thisTabPanel.clientLabel + 's:' + '</div>' +
                            '<div class="white12" style="padding-bottom: 10px;">' +
                                (config.panelType == 'applicants' ?
                                  _('To view an applicants profile, click on their name on the left panel.') :
                                  _('To view a contacts profile, click on their name on the left panel.')
                                ) +
                            '</div>'
                    }, {
                        xtype: 'button',
                        text: _('+ New ') + thisTabPanel.clientLabel,
                        ctCls: 'orange-btn',
                        scale: 'medium',
                        hidden: !booCanAddClient,
                        style: 'padding-bottom: 10px;',
                        width: 130,
                        handler: this.openApplicantTab.createDelegate(this, [
                            {
                                applicantId:   0,
                                applicantName: '',
                                memberType:    thisTabPanel.clientType
                            }
                        ])
                    }, {
                        xtype: 'button',
                        text: _('Show ') + arrApplicantsSettings.office_label,
                        ctCls: 'orange-btn',
                        scale: 'medium',
                        hidden: !arrApplicantsSettings.access.queue.view || config.panelType !== 'applicants',
                        style: 'padding-bottom: 10px;',
                        width: 130,
                        handler: this.openQueueTab.createDelegate(this, [[], true])
                    }
                ],
                listeners: {
                    activate: function () {
                        thisTabPanel.fixParentPanelHeight();
                    }
                }
            }
        ]
    });

    this.on('afterrender', this.onAfterRender, this);
    this.on('add', this.toggleApplicantTab, this);
    this.on('remove', this.toggleApplicantTab, this);
    this.on('tabchange', this.onSwitchApplicantTab, this);

    this.on('beforeremove', function (tp, oTab) {
        if (oTab.hasOwnProperty('searchId') && empty(oTab.searchId)) {
            // Mark "New Advanced Search" as deleted -> so it will be not saved in the "Recently Viewed" section
            oTab.really_deleted = true;
        }
    });
};

Ext.extend(ApplicantsTabPanel, Ext.TabPanel, {
    hasAccess: function(section, action) {
        var booHasAccess = false;
        if (typeof arrApplicantsSettings != 'undefined' && typeof arrApplicantsSettings['access'][section] != 'undefined') {
            if (Array.isArray(arrApplicantsSettings['access'][section])) {
                booHasAccess = arrApplicantsSettings['access'][section].has(action);
            } else if(arrApplicantsSettings['access'][section].hasOwnProperty(action) && arrApplicantsSettings['access'][section][action]) {
                booHasAccess = true;
            }
        }

        return booHasAccess;
    },

    isNumber: function(n) {
        return !isNaN(parseFloat(n)) && isFinite(n);
    },

    onAfterRender: function() {
        var currentHash = location.hash;
        var arrCurrentHash = parseUrlHash(currentHash);

        // Always open the Queue tab first but don't enable automatically
        var booShowQueueTab = false;
        var arrQueuesToSelect = [];
        if (arrCurrentHash[1] == 'queue' && !empty(arrCurrentHash[2])) {
            arrQueuesToSelect = [arrCurrentHash[2]];
        }
        this.openQueueTab(arrQueuesToSelect, false);
        setUrlHash(currentHash);

        if (arrCurrentHash[1] === 'advanced_search') {
            var searchId = 0;
            var searchName = '';
            switch (arrCurrentHash[2]) {
                case 'clients_uploaded_documents':
                    searchId = arrCurrentHash[2];
                    searchName = 'Clients uploaded documents';
                    break;

                case 'clients_have_payments_due':
                    searchId = arrCurrentHash[2];
                    searchName = 'Clients have payments due';
                    break;

                case 'clients_completed_forms':
                    searchId = arrCurrentHash[2];
                    searchName = 'Clients completed forms';
                    break;

                case 'today_clients':
                    searchId = arrCurrentHash[2];
                    searchName = 'Today Clients';
                    break;

                default:
                    if (!isNaN(arrCurrentHash[2])) {
                        searchId = arrCurrentHash[2];
                    }
                    break;
            }

            this.openAdvancedSearchTab(searchId, searchName);
        } else if (arrCurrentHash[1] == 'analytics') {
            this.openAnalyticsTab();
        } else if (arrCurrentHash[1] == 'queue') {
            booShowQueueTab = true;
        } else {
            var applicantId = 0;
            var memberType = '';
            var booNewApplicant = false;
            if (this.isNumber(arrCurrentHash[1])) {
                applicantId = arrCurrentHash[1];
            } else {
                switch (arrCurrentHash[1]) {
                    case 'new_contact':
                        memberType = 'contact';
                        booNewApplicant = true;
                        break;

                    case 'new_client':
                        memberType = 'client';
                        booNewApplicant = true;
                        break;

                    default:
                }
            }

            var caseId = null;
            if (arrCurrentHash[2] == 'cases') {
                caseId = arrCurrentHash[3];
            }

            if (empty(applicantId) && empty(caseId)) {
                if (booNewApplicant) {
                    this.openApplicantTab({
                        applicantId:   0,
                        applicantName: '',
                        memberType:    memberType
                    });
                } else {
                    this.openApplicantTab();
                }
            } else {
                this.loadShortInfo(applicantId, caseId, arrCurrentHash[4] == 'tasks' ? 'tasks' : undefined);
            }
        }

        if (booShowQueueTab) {
            // Just show the tab, it was initialized above
            this.openQueueTab(arrQueuesToSelect, true);
        }
    },

    loadShortInfo: function (applicantId, caseId, activeTab) {
        var thisPanel = this;

        // Send request to get all info we need to open the tab
        var parentTab = thisPanel.getEl();
        parentTab.mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/load-short-info',
            params: {
                applicantId: Ext.encode(applicantId),
                caseId:      Ext.encode(caseId)
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisPanel.applicantId = applicantId;
                    thisPanel.caseId = caseId;

                    thisPanel.openApplicantTab(
                        {
                            applicantId:      applicantId,
                            applicantName:    resultData.applicantName,
                            memberType:       resultData.memberType,
                            caseId:           empty(resultData.caseId) ? undefined : resultData.caseId,
                            caseName:         resultData.caseName,
                            caseType:         resultData.caseType,
                            caseEmployerId:   resultData.caseEmployerId,
                            caseEmployerName: resultData.caseEmployerName
                        },
                        activeTab
                    );
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                }
                parentTab.unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                parentTab.unmask();
            }
        });
    },

    onSwitchApplicantTab: function(panel, tab) {
        // Automatically fire event 'show' for the first active sub tab
        if (tab.items && tab.items.getCount()) {
            var subTabPanel = tab.items.get(0);
            if (typeof subTabPanel.getActiveTab == 'function') {
                var activeSubTab = tab.items.get(0).getActiveTab();
                if (activeSubTab) {
                    activeSubTab.fireEvent('show');
                }
            }

            if (typeof subTabPanel.highlightTitles == 'function') {
                subTabPanel.highlightTitles(panel.getEl());
            }

            // If the currently opened tab is a "New Client/Contact" -> disable the button in the toolbar
            if (!empty(this.newClientButtonId)) {
                var tabId = this.generateTabId(this.panelType, 0);
                Ext.getCmp(this.newClientButtonId).setDisabled(tab.id == tabId);
            }
        }

        setUrlHash(tab.hash);
        this.caseId = tab.caseId;
    },

    toggleQuickAdvancedSearchTab: function(showWhat) {
        // Make sure that Queue tab is active
        var queueTab = Ext.getCmp(this.panelType + '_queue_tab');
        if (queueTab) {
            this.setActiveTab(queueTab);
        }

        var applicantsPanel = Ext.getCmp(this.panelType + '-panel');
        if (applicantsPanel) {
            applicantsPanel.queuePanel.queueGrid.setVisible(showWhat === 'queue_panel');
            if (!empty(applicantsPanel.ApplicantsAdvancedSearchPanel)) {
                applicantsPanel.ApplicantsAdvancedSearchPanel.setVisible(showWhat === 'advanced_search_panel');
            }
        }
    },

    openQuickAdvancedSearchTab: function (searchId, searchName, searchQuery) {
        var applicantsPanel = Ext.getCmp(this.panelType + '-panel');
        if (applicantsPanel) {
            if (applicantsPanel.ApplicantsQueueLeftSectionPanel) {
                try {
                    var sm = applicantsPanel.ApplicantsQueueLeftSectionPanel.ApplicantsQueueLeftSectionGrid.getSelectionModel();
                    sm.suspendEvents();
                    sm.clearSelections();
                    sm.resumeEvents();
                } catch (e) {
                }
            }

            this.toggleQuickAdvancedSearchTab('advanced_search_panel');
            applicantsPanel.openAdvancedSearchPanel(searchId, searchName, searchQuery);
        }
    },

    generateSearchTabTitle: function (searchId, savedSearchName) {
        var title = savedSearchName + _(' - search');
        if (empty(searchId)) {
            var titlePrefix = _('Advanced Search ');
            var myregexp = new RegExp(titlePrefix + '(\\d+)');
            var openedTabsCount = 0;
            this.items.each(function (oTab) {
                if (oTab.hasOwnProperty('searchId') && empty(oTab.searchId)) {
                    // Try to find the max number from the title
                    // e.g. we closed #1 and #2 is opened -> the next one will be #3
                    var match = myregexp.exec(oTab.title);
                    if (match != null) {
                        openedTabsCount = Math.max(openedTabsCount, match[1]);
                    } else {
                        openedTabsCount++;
                    }
                }
            });

            title = titlePrefix + (openedTabsCount + 1);
        }

        return '<div class="tab-title">' + title + '</div>';
    },

    openAdvancedSearchTab: function (searchId, savedSearchName, booShowAnalyticsTab, booOpenPreviouslyOpenedTab, booReadOnlySearch) {
        var thisTabPanel = this;

        var tabId = null;
        var searchTab;
        var searchNumber = '';
        if (hasAccessToRules(thisTabPanel.panelType, 'search', 'allow_multiple_tabs')) {
            // Try to find previously opened first tab
            if (booOpenPreviouslyOpenedTab) {
                this.items.each(function (oTab) {
                    if (oTab.hasOwnProperty('searchId') && oTab.searchId === searchId) {
                        tabId = oTab.id;
                        return false;
                    }
                });
            }

            // Generate a new tab id if the tab wasn't found
            if (empty(tabId)) {
                thisTabPanel.advancedSearchTabNumber++;
                searchNumber = '-' + thisTabPanel.advancedSearchTabNumber;
                tabId = this.advancedSearchTabId + searchNumber;
            }
            searchTab = Ext.getCmp(tabId);
        } else {
            // We allow to open only one Advanced search tab
            tabId = this.advancedSearchTabId;
            searchTab = Ext.getCmp(tabId);
            if (searchTab && searchTab.searchId !== searchId) {
                this.remove(tabId);
                searchTab = null;
            }
        }

        if (!searchTab) {
            var panel = new ApplicantsAdvancedSearchPanel({
                id: tabId,
                panelType: thisTabPanel.panelType,
                title: this.generateSearchTabTitle(searchId, savedSearchName),
                hash: '#' + thisTabPanel.panelType + '/advanced_search/' + searchId,
                style: 'background-color: white; margin: 20px',
                closable: true,
                autoWidth: true,
                searchId: searchId,
                savedSearchName: savedSearchName,
                booShowAnalyticsTab: booShowAnalyticsTab,
                booReadOnlySearch: booReadOnlySearch,
                tabNumber: searchNumber,

                oOpenTabParams: {
                    tabType: 'advanced_search',
                    tabId:                      tabId,
                    searchId:                   searchId,
                    savedSearchName:            savedSearchName,
                    booShowAnalyticsTab:        booShowAnalyticsTab
                },

                listeners: {
                    activate: function () {
                        setUrlHash(this.hash);
                        thisTabPanel.fixParentPanelHeight();
                    }
                }
            }, thisTabPanel);
            searchTab = this.add(panel);
        }

        searchTab.show();
        var newAdvancedMinHeight = initPanelSize() - $('#applicants-tab-panel').find('.x-tab-panel-header').outerHeight() - 12;
        $('#' + tabId).css('min-height', newAdvancedMinHeight + 'px');
    },

    openAnalyticsTab: function (oAnalyticsSettings) {
        var thisTabPanel = this;
        thisTabPanel.analyticsTabNumber++;

        var analyticsTabNumber = hasAccessToRules(thisTabPanel.panelType, 'analytics', 'allow_multiple_tabs') ? '-' + thisTabPanel.analyticsTabNumber : '';
        var analyticsTab       = Ext.getCmp(this.analyticsTabId + analyticsTabNumber);

        var booCreateTab = false;
        if (!analyticsTab) {
            booCreateTab = true;
        } else if (!empty(oAnalyticsSettings) && !empty(oAnalyticsSettings['analytics_id']) && analyticsTab.analyticsId !== oAnalyticsSettings['analytics_id']) {
            booCreateTab = true;
            this.remove(this.analyticsTabId + analyticsTabNumber);
        }

        if (booCreateTab) {
            var panel    = new ApplicantsAnalyticsPanel({
                id:                     this.analyticsTabId + analyticsTabNumber,
                panelType:              thisTabPanel.panelType,
                title:                  '<div class="tab-title">' + _('Analytics') + '</div> ',
                hash:                   '#' + thisTabPanel.panelType + '/analytics',
                iconCls:                'icon-applicant-analytics',
                closable:               true,
                autoWidth:              true,
                booStandaloneAnalytics: true,
                analyticsId:            empty(oAnalyticsSettings) || empty(oAnalyticsSettings['analytics_id']) ? 0 : oAnalyticsSettings['analytics_id'],
                oAnalyticsSettings:     oAnalyticsSettings,
                analyticsTabNumber:     analyticsTabNumber,

                listeners: {
                    activate: function () {
                        setUrlHash(this.hash);
                        thisTabPanel.fixParentPanelHeight();
                    }
                }
            }, thisTabPanel);
            analyticsTab = this.add(panel);
        }

        analyticsTab.show();
        var newAdvancedMinHeight = initPanelSize() - $('#applicants-tab-panel').find('.x-tab-panel-header').outerHeight() - 12;
        $('#' + thisTabPanel.panelType + '-tab-analytics' + analyticsTabNumber).css('min-height', newAdvancedMinHeight + 'px');
    },


    getCaseTypeNameByCaseTypeId: function(caseTypeId) {
        var caseTypeName = '';
        Ext.each(arrApplicantsSettings.case_templates, function(caseTemplate) {
            if (caseTemplate.case_template_id == caseTypeId) {
                caseTypeName = caseTemplate.case_template_name;
            }
        });

        return caseTypeName;
    },


    generateTabId: function(panelType, applicantId, caseId) {
        var tabId = panelType + '-tab-' + applicantId;
        if (typeof caseId != 'undefined') {
            tabId += '-' + caseId;
        }

        return tabId;
    },

    openQueueTab: function(arrQueuesToShow, booShowTab) {
        // Don't show the Queue tab if there is no access to it
        if (!arrApplicantsSettings.access.queue.view) {
            return false;
        }

        var thisTabPanel = this;
        var applicantTabId = thisTabPanel.panelType + '_queue_tab';
        var queueTab = Ext.getCmp(applicantTabId);

        var oApplicantsPanel;
        if (!queueTab) {
            oApplicantsPanel = new ApplicantsPanel({
                id: thisTabPanel.panelType + '-panel',
                panelType: thisTabPanel.panelType,
                arrQueuesToShow: arrQueuesToShow,
                applicantId: null
            });

            queueTab = this.add({
                id: applicantTabId,
                xtype: 'panel',
                oOpenTabParams: {
                    tabType: 'queue',
                    tabId: applicantTabId
                },
                panelType: thisTabPanel.panelType,
                hash: '#' + thisTabPanel.panelType + '/queue',
                title: String.format(
                    '<span title="{0}">{1}</span>',
                    thisTabPanel.panelType === 'applicants' ? _('Go to Clients list') : _('Go to Contacts list'),
                    thisTabPanel.panelType === 'applicants' ? _('Clients') : _('Contacts')
                ),
                scripts: true,
                closable: false,
                autoHeight: true,
                items: oApplicantsPanel,

                listeners: {
                    activate: function () {
                        thisTabPanel.fixParentPanelHeight();
                    }
                }
            });

            if (booShowTab) {
                queueTab.show();
            }
        } else {
            if (booShowTab) {
                queueTab.show();
            }

            if (arrQueuesToShow.length) {
                oApplicantsPanel = Ext.getCmp(thisTabPanel.panelType + '-panel');
                oApplicantsPanel.queuePanel.queueGrid.applyNewOffices(arrQueuesToShow);

                if (oApplicantsPanel.ApplicantsQueueLeftSectionPanel) {
                    var sm = oApplicantsPanel.ApplicantsQueueLeftSectionPanel.ApplicantsQueueLeftSectionGrid.getSelectionModel();
                    sm.suspendEvents();
                    sm.clearSelections();

                    var arrRecordToSelect = [];
                    Ext.each(arrQueuesToShow, function (selectQueue) {
                        var rec = oApplicantsPanel.ApplicantsQueueLeftSectionPanel.ApplicantsQueueLeftSectionGrid.store.getById(selectQueue);
                        if (rec) {
                            arrRecordToSelect.push(rec);
                        }
                    });

                    if (arrRecordToSelect.length) {
                        sm.selectRecords(arrRecordToSelect);
                    }

                    sm.resumeEvents();
                }
            }

            this.toggleQuickAdvancedSearchTab('queue_panel');
        }
    },

    showAddClientNumberExceededNotification: function() {
        var notification = new Ext.Panel({
            style: 'font-size: 14px; padding: 5px;',
            html: String.format(
                'You have exceeded the number of clients allowed in this plan. </br>' +
                'You are currently using the <b>{0} plan</b> that gives you a maximum of <b>{1}</b> clients. </br>' +
                'To proceed please click on "Upgrade Now" upgrade to the next plan.</br>' +
                'The following amount will be charged to the credit card that we have on file for you: </br>' +
                'Your next billing date: <b>{2}</b></br>' +
                'Prorated amount until the next billing date: <b>{3} + GST/HST</b>',
                arrApplicantsSettings.subscription_name,
                arrApplicantsSettings.free_clients_count,
                arrApplicantsSettings.next_billing_date,
                formatMoney(site_currency, arrApplicantsSettings.amount_upgrade)
            )
        });

        var window = new Ext.Window({
            title:      _('Upgrade to the next plan'),
            autoWidth:  true,
            autoHeight: true,
            modal:      true,
            layout:     'form',
            labelWidth: 100,
            buttonAlign: 'right',
            items:      notification,

            buttons: [
                {
                    text: 'Cancel',
                    handler: function () {
                        window.close();
                        return false;
                    }
                }, {
                    text: 'Upgrade Now',
                    cls: 'orange-btn',
                    handler: function () {
                        window.getEl().mask('Processing...');

                        Ext.Ajax.request({
                            url: baseUrl + '/applicants/profile/upgrade-subscription-plan',
                            params: {},
                            success: function (f) {
                                var result = Ext.decode(f.responseText);

                                window.getEl().unmask();
                                if (result.success) {
                                    //congrats
                                    Ext.simpleConfirmation.success(result.message);
                                    arrApplicantsSettings.free_clients_count = result.free_clients_count;
                                    arrApplicantsSettings.subscription_name = result.subscription_name;

                                    window.close();
                                } else {
                                    Ext.simpleConfirmation.error(result.message);
                                }
                            },
                            failure: function () {
                                window.getEl().unmask();
                                Ext.simpleConfirmation.error(_('Internal error. Please try again later.'));
                            }
                        });
                    }
                }
            ]
        });

        window.show();
        window.center();
    },

    createNewCase: function (oParams) {
        var thisTabPanel = this;

        var el = Ext.getBody();
        el.mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/create-case',
            params: oParams,

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    if (empty(resultData.caseId)) {
                        // Just open the tab -> case is not created
                        oParams.forceToOpenTab = true;
                    } else {
                        // Case was just created or was created before
                        oParams.caseId = resultData.caseId;
                        if (resultData.showCaseWasAlreadyCreated) {
                            Ext.simpleConfirmation.info(_("You have already started a new case but haven't saved it yet.<br>Please save or delete this case before adding another case."));
                        } else {
                            thisTabPanel.refreshClientsList(thisTabPanel.panelType, oParams.applicantId, oParams.caseId, true);
                        }
                    }

                    // If case was just assigned to the employer - use this info
                    if (!empty(resultData.caseEmployerId)) {
                        oParams.caseEmployerId = resultData.caseEmployerId;
                        oParams.caseEmployerName = resultData.caseEmployerName;
                    }

                    thisTabPanel.openApplicantTab(oParams, 'case_details');
                    el.unmask();
                } else {
                    Ext.simpleConfirmation.error(resultData.message, resultData.message_title);
                    el.unmask();
                }
            },

            failure: function () {
                el.unmask();
                Ext.simpleConfirmation.error(_('Action failed. Please try again later.'));
            }
        });
    },

    /**
     * Such New tabs can be opened:
     * 1. New Employer - show Employer's groups/fields only
     * 2. New case for Employer - show "Case's groups/fields" only
     * 3. New case with selected IA for Employer - show IA + Case groups/fields
     * 4. New IA - show IA + Case profiles
     *
     * Such Edit tabs can be opened:
     * 1. Employer - show Employer's groups/fields only
     * 2. Edit case assigned to Employer only - show "Case's groups/fields" only
     * 3. Edit case assigned to IA + Employer - show IA + Case groups/fields
     * 4. Edit IA - show IA groups/fields + allow edit assigned cases
     *
     */
    openApplicantTab: function(oParams, activeTab) {
        oParams = oParams || {};

        var thisTabPanel = this;
        var arrCurrentHash = parseUrlHash(location.hash);
        if (this.isNumber(oParams.applicantId)) {
            var booReallyDeleted = false;
            if (activeTab === 'case_details' && empty(oParams.caseId)) {
                // Don't show New Case tab in the recently viewed list
                booReallyDeleted = true;
                if (!oParams.forceToOpenTab) {
                    // Create a case + assign to the client(s)
                    thisTabPanel.createNewCase(oParams);
                    return false;
                }
            }

            var applicantTabId = thisTabPanel.generateTabId(thisTabPanel.panelType, oParams.applicantId, oParams.caseId);
            var applicantTab = Ext.getCmp(applicantTabId);

            if (empty(activeTab)) {
                activeTab = 'profile';
                if(arrCurrentHash[1] == oParams.applicantId && arrCurrentHash[2] !== undefined) {
                    if (arrCurrentHash[2] == 'cases' && arrCurrentHash[4] !== undefined) {
                        activeTab = arrCurrentHash[4];
                    } else {
                        activeTab = arrCurrentHash[2];
                    }
                }
            }

            var booSwitchToTab = true;
            if (!applicantTab) {
                if (oParams.memberType != 'contact') {
                    var booNewClient = false;

                    if (empty(oParams.applicantId)) {
                        booNewClient = true;
                    }

                    if (booNewClient && parseInt(arrApplicantsSettings.free_clients_count) > 0 && parseInt(arrApplicantsSettings.free_clients_count) <= parseInt(arrApplicantsSettings.clients_count)) {
                        this.showAddClientNumberExceededNotification();
                        return false;
                    }
                }

                var placeholderId = Ext.id();
                var parentName = oParams.applicantName;
                if (oParams.memberType == 'employer' && !empty(oParams.caseId) && !empty(oParams.caseType)) {
                    parentName = oParams.caseEmployerName + ' | ' + thisTabPanel.getCaseTypeNameByCaseTypeId(oParams.caseType);
                } else if (!empty(oParams.caseEmployerName) && oParams.applicantId != oParams.caseEmployerId) {
                    parentName = oParams.caseEmployerName + ' | ' + parentName;
                } else if ((oParams.memberType == 'employer' || oParams.memberType == 'individual') && !empty(oParams.caseId) && empty(oParams.caseType)) {
                    // This is needed to automatically show "New Case" during title generation (addCaseNameTab method)
                    oParams.caseName = '';
                }

                var tabTitle = String.format(
                    '<div class="tab-title tab-title-applicant-name" style="float: left; padding-right: 5px;">{0}</div>' +
                    '<div style="float: right;" id="{1}" ></div>',
                    empty(oParams.applicantName) ? (empty(oParams.applicantId) ? _('New ') + oParams.memberType : _('Loading...')) : parentName,
                    placeholderId
                );

                var hash = empty(oParams.applicantId) ? '#' + thisTabPanel.panelType + '/new_' + oParams.memberType : '#' + thisTabPanel.panelType + '/' + oParams.applicantId + '/' + activeTab;

                thisTabPanel.caseId = oParams.caseId;
                thisTabPanel.applicantId = oParams.applicantId;

                var oProfilePanel = new ApplicantsProfileTabPanel({
                    caseId:               oParams.caseId,
                    caseType:             oParams.caseType,
                    caseEmployerId:       oParams.caseEmployerId,
                    caseEmployerName:     oParams.caseEmployerName,
                    applicantId:          oParams.applicantId,
                    applicantName:        oParams.applicantName,
                    memberType:           oParams.memberType,
                    panelType:            thisTabPanel.panelType,
                    activeTab:            activeTab,
                    caseIdLinkedTo:       oParams.caseIdLinkedTo,
                    newClientForceTo:     oParams.newClientForceTo,
                    booHideNewClientType: oParams.booHideNewClientType,
                    showOnlyCaseTypes:    oParams.showOnlyCaseTypes,
                    filterCaseLinkTo:     oParams.filterCaseLinkTo
                }, this);

                oParams.tabId = applicantTabId;
                applicantTab = this.add(
                    {
                        id: applicantTabId,
                        xtype: 'panel',
                        panelType: thisTabPanel.panelType,
                        hash: hash,
                        applicantId: oParams.applicantId,
                        caseId: oParams.caseId,
                        title: tabTitle,
                        scripts: true,
                        closable: true,
                        autoHeight: true,
                        really_deleted: booReallyDeleted,
                        items: oProfilePanel,

                        oOpenTabParams: oParams,

                        listeners: {
                            activate: function () {
                                if (!empty(oProfilePanel.applicantsCasesNavigationPanel)) {
                                    oProfilePanel.applicantsCasesNavigationPanel.fireEvent('show');
                                }

                                thisTabPanel.fixParentPanelHeight();
                            },

                            afterrender: function() {
                                if (!empty(oParams.applicantId) && typeof oParams.caseName != 'undefined' && oParams.caseName !== null) {
                                    thisTabPanel.addCaseNameTab(placeholderId, oParams.caseName, oParams.caseId, oParams.caseType);
                                }
                            },

                            beforeclose: function () {
                                // Prevent this tab closing if there are unsaved changes
                                var thisClientProfileForm = oProfilePanel.applicantsProfileForm;
                                var thisIndividualProfileForm = oProfilePanel.individualProfileForm;
                                var thisCaseProfileForm = oProfilePanel.caseProfileForm;

                                var booIsDirtyClientProfile = !empty(thisClientProfileForm) && thisClientProfileForm.booRendered && thisClientProfileForm.booIsDirty;
                                var booIsDirtyIndividualProfile = !empty(thisIndividualProfileForm) && thisIndividualProfileForm.booRendered && thisIndividualProfileForm.booIsDirty;
                                var booIsDirtyCasesProfile = !empty(thisCaseProfileForm) && thisCaseProfileForm.booRendered && thisCaseProfileForm.booIsDirty;
                                if (booIsDirtyClientProfile || booIsDirtyIndividualProfile || booIsDirtyCasesProfile) {
                                    // Show different messages - depends on where there are unsaved changes
                                    var question = '';
                                    if (booIsDirtyClientProfile) {
                                        question = _('There are unsaved changes on the Profile subtab. Are you sure you want to close this tab?');
                                    } else if (booIsDirtyIndividualProfile) {
                                        question = _('There are unsaved changes on the Employee subtab. Are you sure you want to close this tab?');
                                    } else {
                                        question = _('There are unsaved changes on the Case Details subtab. Are you sure you want to close this tab?');
                                    }

                                    Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                                        if (btn === 'yes') {
                                            // Force to close this tab
                                            if (booIsDirtyClientProfile) {
                                                thisClientProfileForm.booIsDirty = false;
                                            }

                                            if (booIsDirtyIndividualProfile) {
                                                thisIndividualProfileForm.booIsDirty = false;
                                            }

                                            if (booIsDirtyCasesProfile) {
                                                thisCaseProfileForm.booIsDirty = false;
                                            }

                                            Ext.getCmp(applicantTabId).fireEvent('close');
                                            thisTabPanel.remove(applicantTabId);
                                        } else {
                                            // Switch to the Client Profile or Individual Profile or Case Details
                                            // It depends on where the changes are not saved
                                            if (booIsDirtyClientProfile) {
                                                oProfilePanel.setActiveTab(thisClientProfileForm.ownerCt);
                                            } else if (booIsDirtyIndividualProfile) {
                                                oProfilePanel.setActiveTab(thisIndividualProfileForm.ownerCt);
                                            } else {
                                                oProfilePanel.setActiveTab(thisCaseProfileForm.ownerCt);
                                            }
                                        }
                                    });

                                    return false;
                                }
                            },

                            close: function () {
                                var thisCaseProfileForm = oProfilePanel.caseProfileForm;
                                if (!empty(thisCaseProfileForm) && thisCaseProfileForm.booRendered && empty(thisCaseProfileForm.caseId)) {
                                    thisCaseProfileForm.sendRequestToReleaseNewCaseNumber();
                                }

                                if (is_client && !arrApplicantsSettings.access.queue.view) {
                                    // make sure that at least one tab will be opened for the client
                                    setTimeout(function () {
                                        var tabsCount = 0;
                                        thisTabPanel.items.each(function (item) {
                                            if (!empty(item.hidden)) {
                                                tabsCount++;
                                            }
                                        });

                                        if (empty(tabsCount)) {
                                            setUrlHash(hash);
                                            thisTabPanel.loadShortInfo(oParams.applicantId);
                                        }
                                    }, 50);
                                }
                            }
                        }
                    }
                );

                booSwitchToTab = false;
            }

            applicantTab.show();

            if (this.panelType == 'applicants') {
                var elemsApplicants = $('#applicants-tab').find('.clients-tab-panel .x-tab-panel-body');
                var newMinHeightApplicants = initPanelSize() - 61;
                // fix: set right minHeight when creating new employer or case
                if (empty(oParams.applicantId)) {
                    newMinHeightApplicants = initPanelSize() - 37;
                }
                elemsApplicants.filter(':last').css('cssText', 'min-height:' + newMinHeightApplicants + 'px !important;');
            } else {
                var elemsContacts = $('#contacts-tab').find('.clients-tab-panel .x-tab-panel-body');
                var newMinHeightContacts = initPanelSize() - 37;
                elemsContacts.filter(':last').css('cssText', 'min-height:' + newMinHeightContacts + 'px !important;');
            }

            if (booSwitchToTab) {
                var applicantsProfileTabPanel = applicantTab.items.first();

                if (applicantsProfileTabPanel) {
                    var casesTab, casesGrid;
                    if (!empty(oParams.filterCaseLinkTo)) {
                        casesTab = applicantsProfileTabPanel.getSpecificTab(activeTab);
                        if (casesTab && casesTab.items && casesTab.items.getCount()) {
                            casesGrid = casesTab.items.get(0);
                            casesGrid.filterCaseLinkTo = oParams.filterCaseLinkTo;
                        }
                    }

                    if (empty(activeTab)) {
                        applicantsProfileTabPanel.switchToFirstTab();
                    } else {
                        applicantsProfileTabPanel.switchToSpecificTab(activeTab);
                    }

                    if (casesGrid) {
                        casesGrid.setCaseLinkedTo(oParams.filterCaseLinkTo);
                    }
                }
            }

            thisTabPanel.applicantId = oParams.applicantId;
            thisTabPanel.caseId = oParams.caseId;
            thisTabPanel.caseName = oParams.caseName;
        } else {
            this.toggleApplicantTab();
        }
    },

    refreshClientsList: function(panelType, applicantId, caseId, booRefreshCasesGrid) {
        // Reload quick search result + tasks panel
        var panel = Ext.getCmp(panelType + '-panel');
        if (panel) {
            if (panel.ApplicantsSearchPanel) {
                panel.ApplicantsSearchPanel.ApplicantsSearchGrid.store.reload();
            }

            if (panel.ApplicantsTasksPanel) {
                panel.ApplicantsTasksPanel.ApplicantsTasksGrid.store.reload();
            }

            if (panel.queuePanel) {
                panel.queuePanel.queueGrid.store.reload();
            }
        }

        // Automatically refresh all 'cases navigation' panels opened for this client
        // Refresh only if case id is provided
        if (!empty(caseId)) {
            var mainTabPanel = Ext.getCmp(panelType + '-tab-panel');
            var activeTab = mainTabPanel.getActiveTab();
            mainTabPanel.items.each(function (oTab) {
                // Detect if we want to reload the list of cases now or later
                var booReloadNow = oTab.id == activeTab.id;
                var activeCasesPanels = oTab.findByType('ApplicantsProfileTabPanel');
                Ext.each(activeCasesPanels, function (item) {
                    if (!empty(item.applicantsCasesNavigationPanel) && item.applicantsCasesNavigationPanel.applicantId == applicantId) {
                        if (booReloadNow) {
                            item.applicantsCasesNavigationPanel.refreshCasesList();
                        } else {
                            item.applicantsCasesNavigationPanel.autoRefreshCasesList = true;
                        }
                    }
                });
            });

            if (booRefreshCasesGrid) {
                var arrApplicantsCasesGrid = mainTabPanel.findByType('ApplicantsCasesGrid');
                Ext.each(arrApplicantsCasesGrid, function(item) {
                    if (item.applicantId == applicantId) {
                        item.autoRefreshCasesList = true;
                    }
                });
            }
        }
    },

    addCaseNameTab: function(placeholderId, caseName, caseId, caseType) {
        var showCaseName = '';
        if (empty(caseId) || empty(caseType)) {
            showCaseName = _('Case 1');
        } else {
            showCaseName = empty(caseName) ? '' : '(' + caseName + ')';
        }

        new Ext.form.DisplayField({
            cls:      'tab-title tab-title-case-name',
            renderTo: placeholderId,
            value:    showCaseName
        });
    },

    getActiveCaseId: function() {
        return this.caseId ? this.caseId : null;
    },

    getActiveCaseName: function() {
        return this.caseName ? this.caseName : null;
    },

    toggleApplicantTab: function() {
        // Show/hide default tab (without tab title)
        var booShowTabsPanel = this.items.getCount() > 1;

        var defaultTab = Ext.getCmp(this.defaultTabId);
        if (booShowTabsPanel) {
            this.hideTabStripItem(defaultTab);
            this.removeClass('strip-hidden');
        } else {
            this.unhideTabStripItem(defaultTab);
            defaultTab.show();
            defaultTab.setHeight(initPanelSize() - 12);
            this.addClass('strip-hidden');
        }

        // Set correct url location
        var activeTab = this.getActiveTab();
        var hasAccess = hasAccessToRules(this.panelType, 'search', 'allow_multiple_tabs');
        if (activeTab && ([this.defaultTabId, this.advancedSearchTabId].has(activeTab.id) || (hasAccess && activeTab.id.indexOf(this.advancedSearchTabId) != -1))) {
            var newHash = '#' + this.panelType;
            if (activeTab.id == this.advancedSearchTabId || (hasAccess && activeTab.id.indexOf(this.advancedSearchTabId) != -1)) {
                newHash += '/advanced_search';
            }
            setUrlHash(newHash);
        }
    },

    fixParentPanelHeight: function() {
        var thisPanel = this;
        var activeTab = this.getActiveTab();
        if (activeTab) {

            var toolbar;
            if (this.panelType == 'applicants') {
                toolbar = $('#applicants-tab').find('.clients-tab-panel').children('.x-tab-panel-header');
            } else {
                toolbar = $('#contacts-tab').find('.clients-tab-panel').children('.x-tab-panel-header');
            }

            // Magic digits! :)
            var tabId = activeTab.getId();
            var minHeight = initPanelSize();
            var newHeight = 0;
            var additionalHeight = tabId == this.defaultTabId ? 15 : toolbar.height() + 21;

            var tabHeight = activeTab.getHeight();
            var hasAccess = hasAccessToRules(this.panelType, 'search', 'allow_multiple_tabs');
            if (tabId == this.defaultTabId || tabId == this.advancedSearchTabId || tabId == 'help-tab' || (hasAccess && tabId.indexOf(this.advancedSearchTabId) != -1)) {
                newHeight = tabHeight < minHeight ? minHeight + 12 : tabHeight + additionalHeight;
            } else {
                newHeight = tabHeight + 50 < minHeight ? minHeight + 12 : tabHeight + additionalHeight;
            }
            // this.ownerCt.setHeight(newHeight);
        }
    },

    sortFieldsByName: function (a, b) {
        var aName = a.field_name.toLowerCase();
        var bName = b.field_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    getGroupedFields: function (type, notAllowedFieldTypes, booSkipRepeatableGroups) {
        var arrGroupedFields = [];
        notAllowedFieldTypes = empty(notAllowedFieldTypes) ? [] : notAllowedFieldTypes;

        if (type == 'all' || type == 'profile') {
            var arrSearchTypes = [];
            if (this.panelType === 'contacts') {
                arrSearchTypes.push('contact');
            } else {
                arrSearchTypes = ['individual'];
                if (arrApplicantsSettings.access.employers_module_enabled) {
                    arrSearchTypes.unshift('employer');
                }
            }

            arrSearchTypes.forEach(function(currentType) {
                Ext.each(arrApplicantsSettings.groups_and_fields[currentType][0]['fields'], function(group) {
                    if (booSkipRepeatableGroups && group.group_repeatable === 'Y') {
                        return;
                    }

                    Ext.each(group.fields, function (field) {
                        if (field.field_encrypted == 'N' && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                            field.field_template_id = 0;
                            field.field_template_name = '';
                            field.field_group_id = group.group_id;
                            field.field_group_name = group.group_title;
                            field.field_client_type = currentType;
                            arrGroupedFields.push(field);
                        }
                    });
                });
            });
        }

        if (this.panelType !== 'contacts' && (type == 'all' || type == 'case')) {
            var arrGroupedCaseFields = [];

            var arrFieldIds = [];
            for (var templateId in arrApplicantsSettings.case_group_templates) {
                if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                    Ext.each(arrApplicantsSettings.case_group_templates[templateId], function(group){
                        Ext.each(group.fields, function(field){
                            if (field.field_encrypted == 'N' && !arrFieldIds.has(field.field_unique_id) && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                                arrFieldIds.push(field.field_unique_id);
                                field.field_client_type = 'case';
                                field.field_group_name = empty(field.field_group_name) ? 'Case Details' : field.field_group_name;
                                arrGroupedCaseFields.push(field);
                            }
                        });
                    });
                }
            }
            arrGroupedCaseFields.sort(this.sortFieldsByName);

            arrGroupedFields.push.apply(arrGroupedFields, arrGroupedCaseFields);

            // Add special fields to the top of the list
            if (notAllowedFieldTypes.indexOf('date') == -1) {
                arrGroupedFields.unshift({
                    field_id: 'created_on',
                    field_unique_id: 'created_on',
                    field_name: 'Created On',
                    field_type: 'date',
                    field_client_type: 'case'
                });
            }

            if (notAllowedFieldTypes.indexOf('special') == -1) {
                arrGroupedFields.unshift({
                    field_id: 'clients_have_payments_due',
                    field_unique_id: 'clients_have_payments_due',
                    field_name: 'Clients have payments due',
                    field_type: 'special',
                    field_client_type: 'case'
                });

                arrGroupedFields.unshift({
                    field_id: 'clients_uploaded_documents',
                    field_unique_id: 'clients_uploaded_documents',
                    field_name: 'Clients uploaded documents',
                    field_type: 'special',
                    field_client_type: 'case'
                });

                arrGroupedFields.unshift({
                    field_id: 'clients_completed_forms',
                    field_unique_id: 'clients_completed_forms',
                    field_name: 'Clients completed forms',
                    field_type: 'special',
                    field_client_type: 'case'
                });

                arrGroupedFields.unshift({
                    field_id: 'ob_total',
                    field_unique_id: 'ob_total',
                    field_name: 'Cases who owe money',
                    field_type: 'special',
                    field_client_type: 'case'
                });

                arrGroupedFields.unshift({
                    field_id: 'ta_total',
                    field_unique_id: 'ta_total',
                    field_name: 'Available Total',
                    field_type: 'special',
                    field_client_type: 'case'
                });
            }
        }

        return arrGroupedFields;
    },

    markSearchAsFavorite: function (booQuickSearch, searchId) {
        if (empty(searchId)) {
            Ext.simpleConfirmation.warning(_('Please save this search first and then add to your favourites.'));
            return;
        }

        var thisPanel = this;
        var quickSearchField = Ext.getCmp(thisPanel.panelType + '_quicksearchfield');

        var el = booQuickSearch && quickSearchField ? quickSearchField.thisQuickSearchSavedSearchesGrid.getEl() : Ext.getBody();
        el.mask('Updating...');

        Ext.Ajax.request({
            url:    topBaseUrl + '/applicants/search/toggle-favorite',
            params: {
                searchId: searchId
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    var leftPanel = Ext.getCmp(thisPanel.panelType + '_favorite_search_panel');
                    if (leftPanel) {
                        leftPanel.ApplicantsSearchFavoriteGrid.getStore().load();
                    }


                    if (quickSearchField) {
                        quickSearchField.thisQuickSearchSavedSearchesGrid.getStore().load();
                    }

                    thisPanel.items.each(function (oTab) {
                        if (oTab && oTab.searchId && oTab.searchId == searchId && typeof oTab.updateFavoriteButton === 'function') {
                            oTab.updateFavoriteButton(resultData.search_is_favorite);
                        }
                    });

                    el.unmask();
                } else {
                    if (booQuickSearch) {
                        el.mask(resultData.message);
                        setTimeout(function(){
                            el.unmask();
                        }, 750);
                    } else {
                        Ext.simpleConfirmation.error(resultData.message);
                        el.unmask();
                    }
                }
            },

            failure: function () {
                el.unmask();
                Ext.simpleConfirmation.error(_('Action failed. Please try again later.'));
            }
        });
    }
});
