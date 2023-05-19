function initDashboard() {
    var cpan = Ext.getCmp('dashboard-tab-panel');
    if (!empty(cpan)) {
        cpan.destroy();
    }

    var oDashboardContainer = new DashboardContainer({
        autoHeight: true
    });

    var arrComponentsToRender = [];
    if (allowedPages.has('applicants')) {
        var thisPanelType = 'applicants';
        arrComponentsToRender.push({
            xtype: 'quicksearchfield',
            quickSearchFieldEmptyText: _('Search clients...'),
            quickSearchFieldEmptyTextOnHover: _('Enter search keywords...'),
            quickSearchFieldParamName: 'search_query',
            booViewSavedSearches: hasAccessToRules(thisPanelType, 'search', 'view_saved_searches'),
            booViewAdvancedSearch: hasAccessToRules(thisPanelType, 'search', 'view_advanced_search'),

            quickSearchFieldStore: new Ext.data.Store({
                autoLoad: false,

                proxy: new Ext.data.HttpProxy({
                    url:    topBaseUrl + '/applicants/search/get-applicants-list',
                    method: 'post'
                }),

                baseParams: {
                    // search_query will be set in the Ext.ux.QuickSearchField
                    search_for:          thisPanelType,
                    quick_search: 1,
                    boo_conflict_search: 0
                },

                reader: new Ext.data.JsonReader({
                    root:          'items',
                    totalProperty: 'count',
                    fields:        ['user_id', 'user_type', 'user_name', 'user_parent_id', 'user_parent_name', 'case_type_id', 'applicant_id', 'applicant_name', 'applicant_type']
                })
            }),

            quickSearchFieldRecordRenderer: function (val, p, rec) {
                var name = rec.data.user_name, case_number = '';
                if (rec.data.applicant_name && rec.data.user_type != 'case' && (!empty(rec.data.user_parent_id) || rec.data.user_type == 'individual')) {
                    case_number = empty(rec.data.applicant_name) ? '' : ' (' + rec.data.applicant_name + ')';
                    name = rec.data.user_name + case_number;
                } else if (rec.data.user_type == 'case' && rec.data.applicant_type == 'employer') {
                    case_number = empty(rec.data.user_name) ? '' : ' (' + rec.data.user_name + ')';

                    var caseTypeName = '';
                    Ext.each(arrApplicantsSettings.case_templates, function(caseTemplate) {
                        if (caseTemplate.case_template_id == rec.data.case_type_id) {
                            caseTypeName = caseTemplate.case_template_name;
                        }
                    });

                    name = caseTypeName + case_number;
                }

                var indent = 0;
                if (!empty(rec.data.user_parent_id)) {
                    indent = rec.json.linked_to_case ? 20 : 10;
                }

                return String.format('<div style="padding-left: {0}"><a href="#" class="{1}" onclick="return false;" />{2}</a></div>', indent + 'px', '', name);
            },

            quickSearchFieldOnRecordClick: function (rec) {
                setUrlHash('#applicants');
                setActivePage();

                var thisTabPanel = Ext.getCmp(thisPanelType + '-tab-panel');
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
                                applicantId:   rec.data.user_id,
                                applicantName: rec.data.user_name,
                                memberType:    rec.data.user_type
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
                            applicantId:   rec.data.user_id,
                            applicantName: rec.data.user_name,
                            memberType:    rec.data.user_type
                        });
                }
            },

            quickSearchFieldAdvancedSearchLinkOnClick: function () {
                setUrlHash('#applicants/advanced_search/');
                setActivePage();
            },

            quickSearchFieldAdvancedSearchOnDelete: function (rec) {
                var oQuickSearchField = this;
                var oQuickSearchFieldGrid = this.thisQuickSearchSavedSearchesGrid;
                var search_id = rec.data['search_id'];

                oQuickSearchFieldGrid.getEl().mask(_('Processing...'));
                Ext.Ajax.request({
                    url:     topBaseUrl + '/applicants/search/delete-saved-search',
                    params:  {
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

                        var msg = String.format('<span style="color: {0}">{1}</span>', resultData.success ? 'black' : 'red', resultData.success ? _('Done!') : resultData.message);

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

            quickSearchFieldAdvancedSearchOnDetailsClick: function (rec) {
                if (rec.data.search_type !== 'system') {
                    setUrlHash('#applicants/advanced_search/' + rec.data.search_id);
                    setActivePage();
                }
            },

            quickSearchFieldAdvancedSearchOnFavoritesClick: function (rec) {
                var quickSearchField = this;

                var el = quickSearchField.thisQuickSearchSavedSearchesGrid.getEl();
                el.mask('Updating...');

                Ext.Ajax.request({
                    url:    topBaseUrl + '/applicants/search/toggle-favorite',
                    params: {
                        searchId: rec.data.search_id
                    },

                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);

                        if (resultData.success) {
                            // Reload the list for the current quick search field
                            quickSearchField.thisQuickSearchSavedSearchesGrid.getStore().load();

                            // Reload left section of the Clints tab
                            var leftPanel = Ext.getCmp(thisPanelType + '_favorite_search_panel');
                            if (leftPanel) {
                                leftPanel.ApplicantsSearchFavoriteGrid.getStore().load();
                            }

                            // Update button in the opened search in the Clients tab
                            var thisTabPanel = Ext.getCmp(thisPanelType + '-tab-panel');
                            if (thisTabPanel) {
                                thisTabPanel.items.each(function (oTab) {
                                    if (oTab && oTab.searchId && oTab.searchId == searchId && typeof oTab.updateFavoriteButton === 'function') {
                                        oTab.updateFavoriteButton(resultData.search_is_favorite);
                                    }
                                });
                            }

                            // Update the list of the quick search that is located in the Clients tab
                            var anotherQuickSearchField = Ext.getCmp(thisPanelType + '_quicksearchfield');
                            if (anotherQuickSearchField) {
                                anotherQuickSearchField.thisQuickSearchSavedSearchesGrid.getStore().load();
                            }
                        }

                        el.mask(resultData.message);
                        setTimeout(function () {
                            el.unmask();
                        }, 750);
                    },

                    failure: function () {
                        el.unmask();
                        Ext.simpleConfirmation.error(_('Action failed. Please try again later.'));
                    }
                });
            },

            quickSearchFieldAdvancedSearchOnClick: function (rec) {
                setUrlHash('#applicants');
                setActivePage();

                var thisTabPanel = Ext.getCmp(thisPanelType + '-tab-panel');
                thisTabPanel.openQuickAdvancedSearchTab(rec.data.search_id, rec.data.search_name);
            },

            quickSearchFieldOnShowDetailsClick: function (searchQuery) {
                setUrlHash('#applicants');
                setActivePage();

                var thisTabPanel = Ext.getCmp(thisPanelType + '-tab-panel');
                var searchName = String.format("Search: '{0}'", searchQuery);
                thisTabPanel.openQuickAdvancedSearchTab('quick_search', searchName, searchQuery);
            },

            quickSearchFieldAdvancedSearchStore: new Ext.data.Store({
                autoLoad:   false,
                remoteSort: true,

                proxy: new Ext.data.HttpProxy({
                    url: topBaseUrl + '/applicants/search/get-saved-searches'
                }),

                baseParams: {
                    search_type: Ext.encode(thisPanelType)
                },

                reader: new Ext.data.JsonReader({
                    root:          'items',
                    totalProperty: 'count',
                    idProperty:    'search_id',
                    fields:        ['search_id', 'search_type', 'search_name', 'search_can_be_set_default', 'search_default', 'search_is_favorite']
                })
            })
        });
    }

    var footerText = String.format(
        _('<div style="float: left;">Copyright &copy; {0}-{1} {2}&nbsp;&nbsp;Reproduction in any form is prohibited.</div>'),
        site_version === 'canada' ? '1996' : '2013',
        new Date().getFullYear(),
        site_company_name
    );

    if (site_version === 'canada') {
        footerText += '<div style="float: right"><a href="http://www.uniques.ca/" target="_blank" style="color: #848A8D">' + _('Powered by Uniques Software Corp.') + '</a></div>';
    }
    footerText += '<div style="clear: both"></div>';

    cpan = new Ext.TabPanel({
        id: 'dashboard-tab-panel',
        renderTo: 'homepage-tab',
        autoWidth: true,
        autoHeight: true,
        plain: true,
        activeTab: 0,
        enableTabScroll: true,
        minTabWidth: 200,
        cls: 'clients-tab-panel',

        plugins: [
            new Ext.ux.TabUniquesNavigationMenu({}),

            new Ext.ux.TabCustomRightSection({
                additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
                arrComponentsToRender: arrComponentsToRender
            })
        ],

        items: {
            title: _('Dashboard'),
            xtype: 'container',
            items: [oDashboardContainer, {
                xtype:  'box',
                autoEl: {
                    tag:     'div',
                    'class': 'footertxt',
                    html:    footerText
                }
            }]
        }
    });

    cpan.doLayout();
}