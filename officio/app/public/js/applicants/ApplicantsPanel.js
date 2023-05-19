var ApplicantsPanel = function (config) {
    var thisPanel = this;

    Ext.apply(this, config);

    var arrSearchObjects       = [];
    this.ApplicantsSearchPanel = null;
    this.FavoriteSearchesPanel = null;

    // Check access rights, collect the list of available panels
    this.arrSectionsWithAccess = [];

    // Always show "Search Panel"
    // this.arrSectionsWithAccess.push('search-panel');
    this.arrSectionsWithAccess.push('favorite-searches');

    var booHasAccess = false;
    if (config.panelType === 'contacts') {
        booHasAccess = arrApplicantsSettings.access.contact.search.view_queue_panel && arrApplicantsSettings.queue_settings.queue_allowed.length > 0;
    } else {
        booHasAccess = arrApplicantsSettings.access.search.view_queue_panel && arrApplicantsSettings.queue_settings.queue_allowed.length > 0;
    }

    if (booHasAccess) {
        this.arrSectionsWithAccess.push('queue-panel');
    }

    if (allowedClientSubTabs.has('tasks') && config.panelType === 'applicants') {
        // this.arrSectionsWithAccess.push('tasks-panel');
    }

    this.visiblePanelsCount = this.arrSectionsWithAccess.length - 1;
    if (this.arrSectionsWithAccess.has('queue-panel') && this.arrSectionsWithAccess.has('tasks-panel')) {
        this.visiblePanelsCount -= 1;
    }

    // Load saved settings
    var savedSettings = this.loadWestPanelSettings();
    var arrCurrentHash = parseUrlHash(location.hash);
    var queuePanelHeight = arrCurrentHash[1] === 'queue' && this.arrSectionsWithAccess.has('queue-panel') ? savedSettings['search_section_height_for_queue_tab'] : savedSettings['search_section_height_for_client_tab'];
    var otherPanelHeight = empty(this.visiblePanelsCount) ? 0 : (initPanelSize() - queuePanelHeight - 35) / this.visiblePanelsCount;

    var tools = [{
        id:      'refresh',
        handler: function (e, target, panel) {
            if (panel.isVisible() && typeof panel.refreshList === 'function') {
                panel.refreshList();
            }
        }
    }];


    this.ApplicantsQueueLeftSectionPanel = null;
    if (this.arrSectionsWithAccess.has('queue-panel')) {
        this.ApplicantsQueueLeftSectionPanel = new ApplicantsQueueLeftSectionPanel({
            title: String.format(
                '<span title="' + _('Select the {0}(s) for which you want to view the client files') + '">{1}</span>',
                arrApplicantsSettings.office_label,
                arrApplicantsSettings.office_label + 's'
            ),

            subPanelType: 'queue-panel',
            hideMode: 'offsets',
            cls: 'big-items-look big-items-look-no-left-padding',
            panelType: config.panelType,
            arrQueuesToShow: config.arrQueuesToShow,
            height: queuePanelHeight
        }, this);
        arrSearchObjects.push(this.ApplicantsQueueLeftSectionPanel);

        if (this.arrSectionsWithAccess.has('search-panel') || this.arrSectionsWithAccess.has('favorite-searches')) {
            this.ApplicantsQueueLeftSectionPanel.on('render', function () {
                var resizer = new Ext.Resizable(thisPanel.ApplicantsQueueLeftSectionPanel.getId(), {
                    handles: 's',
                    pinned: true
                });
                resizer.on('resize', thisPanel.onSearchPanelResize.createDelegate(thisPanel));
            });
        }
    }

    this.ApplicantsTasksPanel = null;
    if (this.arrSectionsWithAccess.has('tasks-panel')) {
        this.ApplicantsTasksPanel = new ApplicantsTasksPanel({
            title:        _('Tasks'),
            subPanelType: 'tasks-panel',
            hideMode:     'offsets',
            tools:        tools,
            cls:          'big-items-look',
            hidden:       this.arrSectionsWithAccess.has('queue-panel'),
            height:       queuePanelHeight,
            panelType:    config.panelType
        }, this);
        arrSearchObjects.push(this.ApplicantsTasksPanel);

        if (this.arrSectionsWithAccess.has('search-panel') || this.arrSectionsWithAccess.has('favorite-searches')) {
            this.ApplicantsTasksPanel.on('render', function () {
                var resizer = new Ext.Resizable(thisPanel.ApplicantsTasksPanel.getId(), {
                    handles: 's',
                    pinned: true
                });
                resizer.on('resize', thisPanel.onSearchPanelResize.createDelegate(thisPanel));
            });
        }
    }

    if (this.arrSectionsWithAccess.has('search-panel')) {
        this.ApplicantsSearchPanel = new ApplicantsSearchPanel({
            title:        _('Search'),
            subPanelType: 'search-panel',
            cls:          'big-items-look',
            tools:        tools,
            panelType:    config.panelType,
            height:       otherPanelHeight
        }, this);

        arrSearchObjects.push(this.ApplicantsSearchPanel);
    } else if (this.arrSectionsWithAccess.has('favorite-searches')) {
        this.FavoriteSearchesPanel = new ApplicantsSearchFavoritePanel({
            cls:          'big-items-look',
            subPanelType: 'favorite-searches',
            panelType:    config.panelType,
            height:       otherPanelHeight
        }, this);

        arrSearchObjects.push(this.FavoriteSearchesPanel);
    }

    var westPanelMinWidth = 250, westPanelMaxWidth = 400;
    var westPanelWidth = savedSettings['panel_width'];
    westPanelWidth        = parseInt(westPanelWidth, 10);
    westPanelWidth        = westPanelWidth < westPanelMinWidth ? westPanelMinWidth : westPanelWidth;
    this.westPanelWidth   = westPanelWidth;

    this.queuePanel = new ApplicantsQueuePanel({
        panelType: config.panelType,
        region: 'center',
        width: Ext.getCmp(config.panelType + '-tab').getWidth() - westPanelWidth,
        owner: thisPanel
    }, this);


    this.westPanel = new Ext.Panel({
        width:    westPanelWidth,
        height:   initPanelSize(),
        stateful: false,
        cls:      'applicants-search-and-tasks',

        items: arrSearchObjects
    });

    ApplicantsPanel.superclass.constructor.call(this, {
        layout: 'border',
        height: initPanelSize(),
        collapseMode: 'mini',
        stateful: false,

        defaults: {
            stateful: false,
            split: true,
            animFloat: false,
            autoHide: false
        },

        items: [
            {
                xtype: 'panel',
                region: 'west',
                cls: 'applicants-search-and-tasks-west',
                width: westPanelWidth,
                minWidth: westPanelMinWidth,
                maxWidth: westPanelMaxWidth,
                height: initPanelSize(),
                hidden: !arrApplicantsSettings.access.search.view_left_panel,
                items: this.westPanel
            },
            this.queuePanel
        ]
    });

    if (arrApplicantsSettings.access.search.view_left_panel) {
        thisPanel.on('render', function () {
            setTimeout(function () {
                thisPanel.queuePanel.on('resize', thisPanel.onPanelResize.createDelegate(thisPanel), thisPanel);
            }, 100);
        });
    }
};

Ext.extend(ApplicantsPanel, Ext.Panel, {
    getDefaultOffices: function () {
        var arrDefaultRecords = Ext.state.Manager.get('checked_offices');

        // Check if all saved in cookies records are still correct (current user has access to)
        if (!empty(arrDefaultRecords)) {
            var filteredOffices = [];
            Ext.each(arrDefaultRecords, function (officeIdInCookie) {
                var booFoundOffice = false;
                Ext.each(arrApplicantsSettings.queue_settings.queue_allowed, function (oOfficeInfo) {
                    if (parseInt(oOfficeInfo['option_id'], 0) === parseInt(officeIdInCookie, 0)) {
                        booFoundOffice = true;
                        return false;
                    }
                });

                if (booFoundOffice) {
                    filteredOffices.push(officeIdInCookie);
                }
            });

            arrDefaultRecords = filteredOffices;
        }

        // Not saved in cookie or no access rights? Preselect the default one
        if (empty(arrDefaultRecords) || empty(arrDefaultRecords.length)) {
            arrDefaultRecords = [arrApplicantsSettings.office_default_selected === 'all' ? 0 : arrApplicantsSettings.office_default_selected];
        }

        return arrDefaultRecords;
    },

    getSettings: function (id) {
        return Ext.state.Manager.get(id);
    },

    saveSettings: function (id, value) {
        Ext.state.Manager.set(id, value);
    },

    onPanelResize: function (panel, adjWidth) {
        // Resize the west panel + all what's inside
        var newWidth = this.getWidth() - adjWidth - 5;
        this.westPanel.setWidth(newWidth);
        for (var i = 0; i < this.westPanel.items.length; i++) {
            this.westPanel.get(i).setWidth(newWidth);
            this.westPanel.get(i).syncSize();
        }

        // Resize all inner items of the Queue panel
        for (var i = 0; i < this.queuePanel.items.length; i++) {
            this.queuePanel.get(i).setWidth(adjWidth - 40);
            this.queuePanel.get(i).syncSize();
        }

        // Save changes to the cookie
        var savedSettings = this.loadWestPanelSettings();
        savedSettings['panel_width'] = newWidth;
        this.saveSettings(this.panelType + '_west_panel_visible_sections', savedSettings);
    },

    onSearchPanelResize: function (resizer, width, height) {
        var mainPanel       = this.westPanel;
        var westPanelHeight = mainPanel.getHeight();
        var mainPanelId = this.arrSectionsWithAccess.has('queue-panel') ? 'queue-panel' : 'tasks-panel'

        var visibleOtherSectionsCount = 0;
        for (var i = 0; i < mainPanel.items.length; i++) {
            var section = mainPanel.get(i);

            if ((section['subPanelType'] !== mainPanelId) && section.isVisible()) {
                visibleOtherSectionsCount++;
            }
        }

        // Make sure that other sections will be visible too
        var minOtherSectionHeight = 75;
        height = height > westPanelHeight - (visibleOtherSectionsCount * minOtherSectionHeight) ? westPanelHeight - (visibleOtherSectionsCount * minOtherSectionHeight) : height;
        height = height - 10;

        if (this.ApplicantsQueueLeftSectionPanel) {
            this.ApplicantsQueueLeftSectionPanel.setHeight(height);
        } else if (this.ApplicantsTasksPanel) {
            this.ApplicantsTasksPanel.setHeight(height);
        }

        var booQueueTabActive = false;
        for (i = 0; i < mainPanel.items.length; i++) {
            section = mainPanel.get(i);
            if (section.isVisible()) {
                if (section['subPanelType'] === mainPanelId) {
                    if (empty(visibleOtherSectionsCount)) {
                        section.setHeight(westPanelHeight);
                    }

                    if (section['subPanelType'] === 'queue-panel') {
                        booQueueTabActive = true;
                    }
                } else {
                    section.setHeight((westPanelHeight - height - 35) / visibleOtherSectionsCount);
                }
            }
        }

        var savedSettings = this.loadWestPanelSettings();
        if (booQueueTabActive) {
            savedSettings['search_section_height_for_queue_tab'] = height;
        } else {
            savedSettings['search_section_height_for_client_tab'] = height;
        }

        this.saveSettings(this.panelType + '_west_panel_visible_sections', savedSettings);
    },

    loadWestPanelSettings: function () {
        var savedSettings = this.getSettings(this.panelType + '_west_panel_visible_sections');

        if (empty(savedSettings)) {
            savedSettings = {
                'panel_width':                          250,
                'search_section_height_for_queue_tab':  initPanelSize() / (this.visiblePanelsCount + 1),
                'search_section_height_for_client_tab': initPanelSize() / (this.visiblePanelsCount + 1)
            };
        }

        return savedSettings;
    },

    openAdvancedSearchPanel: function (searchId, searchName, searchQuery) {
        var thisPanel = this;

        if (!empty(thisPanel.ApplicantsAdvancedSearchPanel)) {
            this.queuePanel.remove(thisPanel.ApplicantsAdvancedSearchPanel);
        }

        thisPanel.ApplicantsAdvancedSearchPanel = new ApplicantsAdvancedSearchPanel({
            panelType: thisPanel.panelType,
            width: Ext.getCmp(thisPanel.panelType + '-tab').getWidth() - thisPanel.westPanelWidth - 40,
            searchId: searchId,
            savedSearchName: searchName,
            searchQuery: searchQuery,
            booShowAnalyticsTab: false,
            booReadOnlySearch: true,
            tabNumber: ''
        }, Ext.getCmp(thisPanel.panelType + '-tab-panel'));

        this.queuePanel.add(this.ApplicantsAdvancedSearchPanel);
        this.queuePanel.doLayout();
    }
});