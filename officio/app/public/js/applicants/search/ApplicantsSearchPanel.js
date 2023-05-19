ApplicantsSearchPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);
    var thisPanel = this;

    // Save current search - will be used in the grid
    this.useQuickSearch           = false;
    this.conflictSearchFieldValue = '';
    this.selectedSavedSearch      = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['search']['active_saved_search'] : arrApplicantsSettings.active_saved_search;
    this.selectedSavedSearchName  = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['search']['active_saved_search_name'] : arrApplicantsSettings.active_saved_search_name;

    this.ApplicantsSearchGrid = new ApplicantsSearchGrid({
        region: 'center',
        height: 250,
        panelType: config.panelType
    }, this);

    this.quickSearchField = new Ext.form.TextField({
        hideLabel: true,
        width: '100%',
        enableKeyEvents: true,
        listeners: {
            'keypress': function(field, event){
                if (event.getKey() === event.ENTER && field.getValue().length>=2){
                    thisPanel.refreshApplicantsSearchList(true);
                }
            },
            'valid': function(field){
                if (field.getValue().length===1)
                    field.markInvalid('Please enter at least 2 characters');
                else
                    field.getEl().removeClass('x-form-invalid');
            }
        }
    });

    this.currentActiveSearchField = new Ext.form.DisplayField({
        colspan: 2,
        cls:     'applicants-search-title',
        value:   ''
    });

    var booViewSavedSearches  = hasAccessToRules(config.panelType, 'search', 'view_saved_searches');
    var booViewAdvancedSearch = hasAccessToRules(config.panelType, 'search', 'view_advanced_search');
    var booViewAnalyticsTab   = hasAccessToRules(config.panelType, 'analytics', 'view_saved');

    var booHiddenSearchLinkSection = !booViewSavedSearches && !booViewAdvancedSearch && !booViewAnalyticsTab;
    ApplicantsSearchPanel.superclass.constructor.call(this, {
        id: config.panelType + '_quick_search_panel',
        layout: 'border',

        items: [
            {
                region: 'north',
                xtype: 'panel',
                layout: 'table',
                cls: 'garytxt white-bg',
                style: 'padding: 5px; background-color: #ffffff;',
                height: booHiddenSearchLinkSection ? 70 : ((booViewSavedSearches || booViewAdvancedSearch) && booViewAnalyticsTab ? 105 : 85),
                defaults: {
                    width: '100%'
                },
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%',
                            'background-color': 'white'
                        }
                    },
                    columns: 2
                },

                items: [
                    {
                        colspan: 2,
                        html: '<img src="' + topBaseUrl + '/images/orange-arrow.gif" width="7" height="8" /> ' +
                            '<span style="font-weight: bold;">' + _('Search') + '</span>'
                    },

                    this.quickSearchField, {
                        xtype: 'button',
                        cellCls: 'search-button-container',
                        style: 'padding-left: 10px',
                        text: '<i class="las la-search"></i>',
                        handler: function() {
                            var searchVal = trim(thisPanel.quickSearchField.getValue());
                            if (searchVal.length >= 2) {
                                thisPanel.refreshApplicantsSearchList(true);
                            }
                        }
                    },

                    {
                        colspan: 2,
                        xtype: 'panel',
                        layout: 'table',
                        layoutConfig: {
                            tableAttrs: {
                                style: {
                                    width: '100%'
                                }
                            },
                            columns: 2
                        },
                        hidden: booHiddenSearchLinkSection,
                        items: [
                            {
                                xtype: 'box',
                                hidden: !booViewSavedSearches || !booViewAdvancedSearch,
                                autoEl: {tag: 'a', href: '#', 'class': 'bluelink', html: _('Saved Searches')},
                                listeners: {
                                    scope: this,
                                    render: function(c){
                                        c.getEl().on('click', thisPanel.showSavedSearchesMenu.createDelegate(this, [c]), this, {stopEvent: true});
                                    }
                                }
                            }, {
                                xtype: 'box',
                                style: 'float: right;',
                                hidden: !booViewAdvancedSearch,
                                autoEl: {tag: 'a', href: '#', 'class': 'bluelink', html: _('Advanced Search')},
                                listeners: {
                                    scope: this,
                                    render: function(c){
                                        c.getEl().on('click', thisPanel.openAdvancedSearchTab.createDelegate(this, [0]), this, {stopEvent: true});
                                    }
                                }
                            },

                            {
                                colspan: 2,
                                height:  5,
                                hidden:  !booViewSavedSearches || !booViewAdvancedSearch,
                                html:    '&nbsp;'
                            },

                            {
                                xtype: 'box',
                                hidden: !booViewAnalyticsTab,
                                autoEl: {tag: 'a', href: '#', 'class': 'bluelink', html: _('Saved Analytics')},
                                listeners: {
                                    scope: this,
                                    render: function(c){
                                        c.getEl().on('click', thisPanel.showSavedAnalyticsMenu.createDelegate(this, [c]), this, {stopEvent: true});
                                    }
                                }
                            }, {
                                xtype: 'box',
                                style: 'float: right;',
                                hidden: !booViewAnalyticsTab,
                                autoEl: {tag: 'a', href: '#', 'class': 'bluelink', html: _('Analytics')},
                                listeners: {
                                    scope: this,
                                    render: function(c){
                                        c.getEl().on('click', thisPanel.openAnalyticsTab.createDelegate(this, []), this, {stopEvent: true});
                                    }
                                }
                            }
                        ]
                    },
                    this.currentActiveSearchField
                ]
            },

            this.ApplicantsSearchGrid
        ]
    });
};

Ext.extend(ApplicantsSearchPanel, Ext.Panel, {
    showSavedSearchesMenu: function(link) {
        var windowId = this.panelType + '-saved-searches-window';
        var wnd = Ext.getCmp(windowId);
        if (!wnd) {
            wnd = new ApplicantsSavedSearchWindow({
                id:        windowId,
                panelType: this.panelType
            }, this);

            wnd.show();
            wnd.alignTo(link.getEl(), 'tl-bl?', [0, 0]);
        } else {
            if (wnd.isVisible()) {
                wnd.hide();
            } else {
                wnd.show();
            }
        }
    },

    showSavedAnalyticsMenu: function(link) {
        var windowId = this.panelType + '-saved-analytics-window';
        var wnd = Ext.getCmp(windowId);
        if (!wnd) {
            wnd = new ApplicantsAnalyticsWindow({
                id:        windowId,
                panelType: this.panelType
            }, this);

            wnd.show();
            wnd.alignTo(link.getEl(), 'tl-bl?', [0, 0]);
        } else {
            if (wnd.isVisible()) {
                wnd.hide();
            } else {
                wnd.show();
            }
        }
    },

    openAdvancedSearchTab: function(searchId, searchName, booShowAnalyticsTab, booOpenPreviouslyOpenedTab) {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        tabPanel.openAdvancedSearchTab(searchId, searchName, booShowAnalyticsTab, booOpenPreviouslyOpenedTab);
    },

    openAnalyticsTab: function(oAnalyticsSettings) {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        tabPanel.openAnalyticsTab(oAnalyticsSettings);
    },

    refreshList: function () {
        this.refreshApplicantsSearchList();
    },

    refreshApplicantsSearchList: function(booQuickSearch) {
        if (booQuickSearch && !this.quickSearchField.isValid()) {
            return;
        }

        var val = this.quickSearchField.getValue().trim();
        if (booQuickSearch && val.length < this.quickSearchField.minLength) {
            this.quickSearchField.markInvalid();
            return;
        }

        this.useQuickSearch = booQuickSearch;
        this.ApplicantsSearchGrid.store.reload();
    },

    setCurrentSearchName: function(currentSearchName) {
        currentSearchName = String.format('{0}', Ext.util.Format.ellipsis(currentSearchName, 40, true));
        this.currentActiveSearchField.setValue(currentSearchName);
    }
});