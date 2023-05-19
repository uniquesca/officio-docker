ApplicantsAdvancedSearchPanel = function(config, owner) {
    var thisPanel = this;
    Ext.apply(this, config);
    this.owner = owner;
    this.savedSearchId = 0;
    this.searchQuery = '';
    this.savedSearchColumns = [];
    this.tabNumber = config.tabNumber;
    this.booAllowedMultipleTabs = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['search']['allow_multiple_tabs'] : arrApplicantsSettings['access']['search']['allow_multiple_tabs'];

    this.currentRowsCount = 0;

    this.arrSearchFilters = arrApplicantsSettings.filters;

    this.readOnlySearchCriteria = new Ext.form.DisplayField({
        hideLabel: true
    });

    this.editSearchButton = new Ext.Button({
        style: 'margin-left: 20px;',
        text: '<i class="las la-pen"></i>' + _('Edit'),
        handler: this.editSavedSearch.createDelegate(this)
    });

    this.advancedSearchForm = new Ext.Panel({
        hidden: config.booReadOnlySearch,
        bodyStyle: 'padding: 5px; background-color: #E8E9EB;',
        style: 'background-color: #E8E9EB;',
        items: [{
            xtype: 'hidden',
            id: 'max_rows_count' + (thisPanel.booAllowedMultipleTabs ? '_' + thisPanel.tabNumber : ''),
            name: 'max_rows_count',
            value: 0
        }]
    });

    var radioGroupHeight = 0;
    var booHiddenRadioPanel = !arrApplicantsSettings.access.employers_module_enabled || config.panelType === 'contacts' || config.booReadOnlySearch;
    if (!booHiddenRadioPanel) {
        radioGroupHeight = 24 + 16 * 2;
    }

    if (this.booAlwaysShowGrid) {
        this.advancedSearchGridHeight = 200;
    } else {
        this.advancedSearchGridHeight = config.booReadOnlySearch ? (initPanelSize() - 70 - radioGroupHeight) : (initPanelSize() - 240 - radioGroupHeight);
    }

    this.filterClientType = 'individual';

    // Load from settings for system and new advanced searches
    // because we don't save settings in the DB
    if (config.panelType !== 'contacts') {
        var booSystemSearch = ['all', 'last4me', 'last4all', 'quick_search'].has(config.searchId);
        if (booSystemSearch || empty(config.searchId)) {
            var saved = Ext.state.Manager.get(thisPanel.getSearchRadioCookieName());
            if (['individual', 'employer'].has(saved)) {
                this.filterClientType = saved;
            }
        }
    }

    this.radiosPanel = new Ext.Panel({
        layout: 'column',
        style: 'margin-top: 16px; margin-bottom: 16px',
        hidden: booHiddenRadioPanel,
        disabled: config.booReadOnlySearch,

        items: [
            {
                boxLabel: _('Individual Cases'),
                name: 'filter_client_type_radio',
                xtype: 'radio',
                value: 'individual',
                checked: this.filterClientType !== 'employer',
                listeners: {
                    check: function (radio, booChecked) {
                        if (booChecked) {
                            Ext.state.Manager.clear(thisPanel.getSearchRadioCookieName());

                            thisPanel.filterClientType = radio.value;

                            if (Ext.getCmp(thisPanel.advancedSearchTabPanelId).isVisible()) {
                                thisPanel.applyFilter();
                            }
                        }
                    }
                }
            }, {
                xtype: 'box',
                width: 30,
                autoEl: {
                    tag: 'div',
                    html: '&nbsp;'
                }
            }, {
                boxLabel: _('Employer Cases'),
                name: 'filter_client_type_radio',
                xtype: 'radio',
                value: 'employer',
                checked: this.filterClientType === 'employer',
                listeners: {
                    check: function (radio, booChecked) {
                        if (booChecked) {
                            Ext.state.Manager.set(thisPanel.getSearchRadioCookieName(), 'employer');

                            thisPanel.filterClientType = radio.value;

                            if (Ext.getCmp(thisPanel.advancedSearchTabPanelId).isVisible()) {
                                thisPanel.applyFilter();
                            }
                        }
                    }
                }
            }
        ]
    });

    this.advancedSearchGrid = new ApplicantsAdvancedSearchGrid({
        panelType: config.panelType,
        booHideMassEmailing: config.booHideMassEmailing,
        booHideGridToolbar: config.booHideGridToolbar,
        height: this.advancedSearchGridHeight
    }, this);


    // Show Analytics sub tab only if:
    // - we were redirected from the Analytics tab
    // - user has access to the Analytics tab
    var booHasAccessToAnalytics = config.booShowAnalyticsTab && (config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['analytics']['view_saved'] : arrApplicantsSettings.access.analytics.view_saved);

    this.analyticsPanel = new ApplicantsAnalyticsPanel({
        title:                 'Analytics',
        iconCls:               'icon-applicant-analytics',
        panelType:             config.panelType,
        disabled:              !booHasAccessToAnalytics,
        booForceShowAnalytics: config.booForceShowAnalytics
    }, this);

    this.advancedSearchTabPanelId = Ext.id();
    this.advancedSearchTopContainerId = Ext.id();

    this.searchActiveCasesCheckboxId  = 'applicants-active-cases-checkbox' + (thisPanel.booAllowedMultipleTabs ? '-' + thisPanel.tabNumber : '');
    this.searchRelatedCasesCheckboxId = 'applicants-related-cases-checkbox' + (thisPanel.booAllowedMultipleTabs ? '-' + thisPanel.tabNumber : '');

    this.addSearchRowButton = new Ext.Button({
        xtype:   'button',
        text:    '<i class="las la-plus"></i>' + _('Add Filtering Criteria'),
        width:   130,
        hidden:  this.booReadOnlySearch,
        handler: this.addNewRow.createDelegate(this, [false])
    });

    this.searchFavoriteButton = new Ext.Button({
        text: this.getFavouriteButtonText(false),
        style: 'padding: 4px 10px;',
        hidden: this.booHideSearchFavoriteButton,
        handler: this.markSearchAsFavorite.createDelegate(this)
    });

    this.searchCriteriaContainerId = Ext.id();
    this.searchCriteriaBackBtnId = Ext.id();
    ApplicantsAdvancedSearchPanel.superclass.constructor.call(this, {
        cls: 'extjs-panel-with-border',
        buttonAlign: 'left',
        autoHeight: true,
        items: [
            {
                xtype: 'hidden',
                ref: 'searchNameField'
            }, {
                id: this.searchCriteriaBackBtnId,
                xtype: 'button',
                text: '<i class="las la-arrow-left"></i>' + (config.panelType === 'contacts' ? _('Back to Contacts') : _('Back to Clients')),
                style: 'margin-bottom: 15px',
                hidden: this.booReadOnlySearch || this.booHideBackButton,
                handler: this.openQuickSavedSearch.createDelegate(this)
            }, {
                xtype: 'button',
                text: '<i class="las la-arrow-left"></i>' + _('Back to Default Searches'),
                style: 'margin: 15px',
                hidden: !this.booShowBackSuperadminButton,
                handler: function () {
                    location.href = baseUrl + '/default-searches/index?search_type=' + thisPanel.panelType;
                }
            }, {
                id: this.searchCriteriaContainerId,
                xtype: 'container',
                cls: this.booReadOnlySearch ? 'whole-search-criteria-container-no-bg' : 'whole-search-criteria-container',
                items: [
                    {
                        xtype: 'container',
                        cls: 'text-search-criteria',
                        hidden: !this.booReadOnlySearch,
                        layout: 'column',
                        items: [
                            {
                                xtype: 'container',
                                layout: 'form',
                                items: this.readOnlySearchCriteria
                            }, this.editSearchButton
                        ]
                    }, this.advancedSearchForm, {
                        id: this.advancedSearchTopContainerId,
                        layout: 'column',
                        bodyStyle: 'background-color: #E8E9EB;',
                        hidden: this.booReadOnlySearch,

                        items: [{
                            xtype: 'container',
                            items: [
                                {
                                    xtype: 'container',
                                    style: 'margin-bottom: 10px; width: 200px',
                                    layout: 'column',
                                    items: [
                                        this.addSearchRowButton
                                    ]
                                },
                                {
                                    xtype: 'container',
                                    cls: 'active-clients-checkbox',
                                    items: {
                                        id: this.searchActiveCasesCheckboxId,
                                        xtype: 'checkbox',
                                        name: 'active-clients',
                                        checked: true,
                                        hidden: this.panelType === 'contacts',
                                        boxLabel: _('Search only among Active Cases')
                                    }
                                },
                                {
                                    id: this.searchRelatedCasesCheckboxId,
                                    xtype: 'checkbox',
                                    name: 'related-cases',
                                    checked: this.panelType !== 'contacts',
                                    hidden: true,
                                    boxLabel: _('Display Related Cases & Profiles with a Case')
                                }
                            ]
                        }, {
                            xtype: 'container',
                            style: 'margin: 30px 5px 5px 155px',
                            layout: 'column',
                            width: 350,

                            items: [
                                {
                                    xtype: 'button',
                                    text: _('Reset'),
                                    style: 'margin-top: 8px;',
                                    width: 80,
                                    handler: this.resetFilter.createDelegate(this, [false])
                                }, {
                                    xtype: 'button',
                                    text: '<i class="las la-search"></i>' + _('Search'),
                                    cls: 'orange-btn',
                                    width: 110,
                                    hidden: config.booHideSearchButton ? true : false,
                                    handler: this.applyFilter.createDelegate(this)
                                }, {
                                    xtype: 'button',
                                    cls: 'blue-btn',
                                    text: '<i class="las la-save"></i>' + _('Save'),
                                    style: 'margin-left: 10px',
                                    handler: this.saveSearch.createDelegate(this)
                                }, this.searchFavoriteButton
                            ]
                        }
                        ]
                    },
                    this.radiosPanel
                ]
            },

            {
                xtype: 'tabpanel',
                cls: 'clients-sub-tab-panel clients-sub-sub-tab-panel extjs-grid-noborder',
                id: this.advancedSearchTabPanelId,
                hidden: !config.booAlwaysShowGrid,
                deferredRender: false,
                activeTab: 0,

                items: [
                    {
                        title: _('Search Results'),
                        xtype: 'container',
                        autoHeight: true,

                        items: [
                            this.advancedSearchGrid
                        ],

                        listeners: {
                            'activate': function () {
                                thisPanel.owner.fixParentPanelHeight();
                            }
                        }
                    },

                    this.analyticsPanel
                ],

                listeners: {
                    'render': function () {
                        // Hide tab headers, so only the first one will be active
                        if (!booHasAccessToAnalytics) {
                            for (var i = 0; i < this.items.length; i++) {
                                this.hideTabStripItem(i);
                            }

                            // Don't show panel's header - no extra borders
                            this.getEl().addClass('tab-panel-invisible-tabs');
                        }
                    }
                }
            }
        ]
    });

    this.on('render', this.addNewRow.createDelegate(this, [true]), this);
    this.on('afterrender', this.loadSavedSearch.createDelegate(this, [config.searchId, config.searchQuery]), this);
    this.on('afterrender', this.addEnterKeyListener.createDelegate(this, []));
};

Ext.extend(ApplicantsAdvancedSearchPanel, Ext.Panel, {
    fixParentPanelHeight: function() {
        this.owner.fixParentPanelHeight();
    },

    getGroupedFields: function(type, notAllowedFieldTypes) {
        return this.owner.getGroupedFields(type, notAllowedFieldTypes, true);
    },

    getSearchRadioCookieName: function () {
        var suffix = '';
        if (['all', 'last4me', 'last4all', 'quick_search'].has(this.searchId)) {
            suffix = '_' + this.searchId;
        }

        return this.panelType + '_adv_srch_radio' + suffix;
    },

    loadSavedSearch: function (savedSearchId, searchQuery) {
        var thisPanel = this;

        if (!empty(savedSearchId)) {
            thisPanel.getEl().mask(_('Loading...'));
            Ext.Ajax.request({
                url: topBaseUrl + '/applicants/search/load-search',
                params: {
                    search_type: this.panelType,
                    search_id: savedSearchId,
                    search_query: searchQuery
                },
                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        // Apply loaded params
                        try {
                            thisPanel.savedSearchId = savedSearchId;
                            thisPanel.searchQuery = searchQuery;

                            var arrParams = Ext.decode(resultData.search);

                            var booSystemSearch = ['all', 'last4me', 'last4all', 'quick_search'].has(savedSearchId);

                            // Don't show the Favourites button for system or new searches
                            if (this.searchFavoriteButton && !thisPanel.booHideSearchFavoriteButton) {
                                thisPanel.searchFavoriteButton.setVisible(!booSystemSearch && !empty(savedSearchId));
                            }

                            // Try to load the list of columns from the cookie (for system and new searches only),
                            // If not available - use the default loaded
                            var arrSaved = Ext.state.Manager.get(thisPanel.advancedSearchGrid.getSearchSettingsCookieName());
                            if (arrSaved && arrSaved.length && (booSystemSearch || empty(savedSearchId))) {
                                thisPanel.savedSearchColumns = arrSaved;
                            } else {
                                thisPanel.savedSearchColumns = Ext.decode(resultData.search_columns);
                            }

                            thisPanel.resetFilter(false);

                            // Set main params
                            thisPanel.searchNameField.setValue(resultData.search_name);

                            // Update/use saved filter type
                            if (['individual', 'employer'].has(arrParams.filter_client_type_radio)) {
                                thisPanel.filterClientType = arrParams.filter_client_type_radio;

                                var radios = thisPanel.radiosPanel.find('name', 'filter_client_type_radio');
                                if (radios.length) {
                                    if (thisPanel.filterClientType == 'employer') {
                                        radios[0].setValue(false);
                                        radios[1].setValue(true);
                                    } else {
                                        radios[0].setValue(true);
                                        radios[1].setValue(false);
                                    }
                                }
                            }


                            thisPanel.updateFavoriteButton(resultData.search_is_favorite);

                            // Make sure that tab's title is correct
                            thisPanel.setTitle(thisPanel.owner.generateSearchTabTitle(savedSearchId, resultData.search_name));

                            // Add new rows, set needed values
                            var rowsCount = parseInt(arrParams['max_rows_count'], 10);
                            var realRowsCount = 0;
                            for (var i = 1; i <= rowsCount; i++) {
                                if (arrParams['operator_' + i]) {
                                    realRowsCount++;
                                }
                            }
                            thisPanel.advancedSearchForm.setVisible(true);
                            Ext.getCmp(thisPanel.advancedSearchTopContainerId).setVisible(true);

                            // Make sure we have all rows
                            for (var j = 1; j < realRowsCount; j++) {
                                thisPanel.addNewRow(false);
                            }


                            // Set values in correct rows
                            var formItems = thisPanel.advancedSearchForm.items;
                            var currentRow = 0;
                            var strSearchCriteria = '';
                            for (i = 1; i <= rowsCount; i++) {
                                if (arrParams['operator_' + i]) {
                                    currentRow++;

                                    var panel = formItems.itemAt(currentRow);

                                    var operatorCombo = thisPanel.findPanelField(panel, 'operator_' + currentRow);
                                    var operatorComboValue = thisPanel.setComboValueAndFireBeforeSelectEvent(operatorCombo, arrParams['operator_' + i]);
                                    if (i > 1) {
                                        strSearchCriteria += '<br/>' + operatorComboValue;
                                    }

                                    if (arrParams.hasOwnProperty('field_client_type_radio_' + i)) {
                                        var searchInCombo = thisPanel.findPanelField(panel, 'field_client_type_radio_' + currentRow);
                                        var searchInComboValue = thisPanel.setComboValueAndFireBeforeSelectEvent(searchInCombo, arrParams['field_client_type_radio_' + i]);
                                        if (!empty(searchInComboValue)) {
                                            strSearchCriteria += ' ' + searchInComboValue + ' -';
                                        }
                                    }

                                    thisPanel.advancedSearchGrid.applyColumns(thisPanel.savedSearchColumns, thisPanel.filterClientType == 'employer');
                                    if (booSystemSearch) {
                                        setTimeout(function () {
                                            thisPanel.advancedSearchGrid.autoResizeColumns();
                                        }, 50);
                                    }

                                    var fieldsCombo = thisPanel.findPanelField(panel, 'field_' + currentRow);
                                    var fieldsComboValue = thisPanel.setComboValueAndFireBeforeSelectEvent(
                                        fieldsCombo,
                                        arrParams['field_' + i],
                                        [
                                            ['field_unique_id', arrParams['field_' + i]],
                                            ['field_client_type', arrParams['field_client_type_' + i]]
                                        ]
                                    );

                                    // Update client type AFTER the field was selected, so a correct value will be set
                                    var clientType = thisPanel.findPanelField(panel, 'field_client_type_' + currentRow);
                                    clientType.setValue(arrParams['field_client_type_' + i]);

                                    strSearchCriteria += ' "' + fieldsComboValue + '"';

                                    if (arrParams.hasOwnProperty('filter_' + i)) {
                                        var filterCombo = thisPanel.findPanelField(panel, 'filter_' + currentRow);
                                        var filterComboValue = thisPanel.setComboValueAndFireBeforeSelectEvent(filterCombo, arrParams['filter_' + i]);
                                        strSearchCriteria += ' ' + filterComboValue;

                                        var optionCombo;
                                        if (['is_one_of', 'is_none_of'].has(arrParams['filter_' + i])) {
                                            optionCombo = thisPanel.findPanelField(panel, 'option_' + currentRow, 'lovcombo');
                                        } else {
                                            optionCombo = thisPanel.findPanelField(panel, 'option_' + currentRow, 'combo');
                                        }
                                        optionCombo.setValue(arrParams['option_' + i]);

                                        strSearchCriteria += ' ' + optionCombo.getRawValue();
                                    }

                                    var textField = thisPanel.findPanelField(panel, 'text_' + currentRow);
                                    textField.setValue(arrParams['text_' + i]);
                                    strSearchCriteria += ' ' + textField.getRawValue();
                                    strSearchCriteria = trim(strSearchCriteria);

                                    if (!empty(arrParams['date_' + i])) {
                                        var dateField = thisPanel.findPanelField(panel, 'date_' + currentRow);
                                        dateField.setValue(Date.parseDate(arrParams['date_' + i], Date.patterns.ISO8601Short));
                                        strSearchCriteria += ' ' + Ext.util.Format.date(dateField.getValue(), dateFormatFull);
                                    }

                                    if (!empty(arrParams['date_from_' + i])) {
                                        var dateFromField = thisPanel.findPanelField(panel, 'date_from_' + currentRow);
                                        dateFromField.setValue(Date.parseDate(arrParams['date_from_' + i], Date.patterns.ISO8601Short));
                                        strSearchCriteria += ' ' + Ext.util.Format.date(dateFromField.getValue(), dateFormatFull);
                                    }

                                    if (!empty(arrParams['date_to_' + i])) {
                                        var dateToField = thisPanel.findPanelField(panel, 'date_to_' + currentRow);
                                        dateToField.setValue(Date.parseDate(arrParams['date_to_' + i], Date.patterns.ISO8601Short));
                                        strSearchCriteria += ' &amp; ' + Ext.util.Format.date(dateToField.getValue(), dateFormatFull);
                                    }

                                    if (!empty(arrParams['date_next_num_' + i])) {
                                        var dateNextNumField = thisPanel.findPanelField(panel, 'date_next_num_' + currentRow);
                                        dateNextNumField.setValue(arrParams['date_next_num_' + i]);
                                        strSearchCriteria += ' ' + dateNextNumField.getValue();
                                    }

                                    if (!empty(arrParams['date_next_period_' + i])) {
                                        var dateNextDateField = thisPanel.findPanelField(panel, 'date_next_period_' + currentRow);
                                        dateNextDateField.setValue(arrParams['date_next_period_' + i]);
                                        strSearchCriteria += ' ' + dateNextDateField.getRawValue();
                                    }

                                    thisPanel.findPanelField(panel, 'field_type_' + currentRow).setValue(arrParams['field_type_' + i]);
                                }
                            }

                            var activeCasesCheckbox = Ext.getCmp(thisPanel.searchActiveCasesCheckboxId);
                            activeCasesCheckbox.setValue(arrParams['active-clients']);
                            if (activeCasesCheckbox.getValue()) {
                                strSearchCriteria += '<br>' + activeCasesCheckbox.boxLabel;
                            }

                            var dsiplayRelatedCasesCheckbox = Ext.getCmp(thisPanel.searchRelatedCasesCheckboxId);
                            dsiplayRelatedCasesCheckbox.setValue(arrParams['related-cases']);
                            if (dsiplayRelatedCasesCheckbox.isVisible() && dsiplayRelatedCasesCheckbox.getValue()) {
                                strSearchCriteria += '<br>' + dsiplayRelatedCasesCheckbox.boxLabel;
                            }

                            var fullLabel = '';
                            var showingLabel = String.format(
                                '<span class="not-real-link-big-label">{0}</span>',
                                savedSearchId == 'quick_search' ? _('Search results for:') : _('Showing:')
                            );
                            if (booSystemSearch) {
                                thisPanel.editSearchButton.setVisible(false);

                                fullLabel = '<a href="#" class="not-real-link-big" style="float: right; margin-top: 6px;" onclick="return false;">' + showingLabel + resultData.search_name + '</a>';
                            } else {
                                if (!thisPanel.booReadOnlySearch) {
                                    Ext.getCmp(thisPanel.searchCriteriaContainerId).removeClass('whole-search-criteria-container-no-bg').addClass('whole-search-criteria-container');
                                }

                                var booExpanded = Ext.state.Manager.get('toggle_search_criteria', false);
                                fullLabel = '<div class="search-criteria-container">' +
                                    '<div><span class="not-real-link-big">' + showingLabel + resultData.search_name + '</span><a href="#" onclick="Ext.getCmp(\'' + thisPanel.id + '\').toggleSearchCriteria(this); return false;" class="blulinkunb">' + (booExpanded ? '<i class="up"></i>' : '<i class="down"></i>') + '</a></div>' +
                                    '<div class="search-criteria-details" style="display: ' + (booExpanded ? 'block' : 'none') + '">' + strSearchCriteria + '</div>' +
                                    '</div>';
                            }
                            thisPanel.readOnlySearchCriteria.setValue(fullLabel);

                            if (thisPanel.booReadOnlySearch) {
                                if (thisPanel.panelType === 'applicants') {
                                    thisPanel.advancedSearchGrid.activeCasesCheckboxRO.setVisible(true);
                                    thisPanel.advancedSearchGrid.activeCasesCheckboxRO.setValue(arrParams['active-clients']);
                                }

                                thisPanel.advancedSearchForm.setVisible(false);
                                Ext.getCmp(thisPanel.advancedSearchTopContainerId).setVisible(false);

                                setTimeout(function () {
                                    thisPanel.updateAdvancedSearchGridHeight();
                                }, 100)
                            }


                            if (typeof arrParams.analytics !== 'undefined') {
                                thisPanel.analyticsPanel.applySettings(arrParams.analytics);
                            }


                            // Run search!
                            if (thisPanel.booReadOnlySearch) {
                                thisPanel.applyFilter();
                            }
                        } catch (e) {
                            console.log(e);
                            resultData.success = false;
                            resultData.message = _('Incorrect data.');
                        }

                        thisPanel.getEl().unmask();
                    } else {
                        try {
                            Ext.simpleConfirmation.error(resultData.message);
                            thisPanel.really_deleted = true;
                            thisPanel.owner.remove(thisPanel);
                        } catch (e) {
                        }
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error(_('Saved search cannot be loaded. Please try again later.'));
                    thisPanel.getEl().unmask();
                }
            });
        } else {
            // Don't show the Favourites button for new searches
            thisPanel.searchFavoriteButton.setVisible(false);
        }
    },

    updateAdvancedSearchGridHeight: function () {
        var gridHeight = this.advancedSearchGridHeight;
        var thisSearchCriteriaPanel = $('#' + this.id + ' .search-criteria-details');
        if (thisSearchCriteriaPanel.is(':visible')) {
            gridHeight -= (thisSearchCriteriaPanel.height() + 20);
        }
        this.advancedSearchGrid.setHeight(gridHeight);
    },

    toggleSearchCriteria: function (link) {
        var link = $(link);
        var thisPanel = this;
        link.parents('.search-criteria-container').find('.search-criteria-details').slideToggle('slow', function () {
            if ($(this).is(":visible")) {
                link.html('<i class="up"></i>');
                Ext.state.Manager.set('toggle_search_criteria', true);
            } else {
                link.html('<i class="down"></i>');
                Ext.state.Manager.clear('toggle_search_criteria');
            }

            thisPanel.updateAdvancedSearchGridHeight();
        });
    },

    addEnterKeyListener: function () {
        var thisPanel = this;

        $("#" + thisPanel.id).attr('tabIndex', '1');

        $("#" + thisPanel.id).keypress(function (e) {
            if (e.keyCode == 13) {
                thisPanel.applyFilter();
            }
        });
    },

    setComboValueAndFireBeforeSelectEvent: function (combo, value, what) {
        var index = -1;
        if (!empty(what)) {
            index = combo.store.findBy(function (record) {
                // Search by ALL params
                var booOk = true;
                Ext.each(what, function (arrWhat) {
                    if (record['data'][arrWhat[0]] != arrWhat[1]) {
                        booOk = false;
                    }
                });

                return booOk;
            });
        } else {
            index = combo.store.find(combo.valueField, value);
        }

        var strVisibleValue = '';
        if (index !== -1) {
            var record = combo.store.getAt(index);
            var realValue = record.data[combo.valueField];
            strVisibleValue = record.data[combo.displayField];

            combo.fireEvent('beforeselect', combo, record, realValue);
            combo.setValue(realValue);
        }

        return strVisibleValue;
    },

    switchFilterFields: function(combo, comboRec) {
        var type = comboRec.data.option_id;

        var form = combo.ownerCt.ownerCt;
        var fieldsCombo = form.items.item(3).items.item(0);

        fieldsCombo.getStore().loadData(this.getGroupedFields(type));

        // Reset fields combo value
        fieldsCombo.reset();

        var clientType = form.items.item(1);

        clientType.setValue('');

        // Hide all search fields (except of the combobox fields)
        for (var i = 0; i < form.items.length; i++) {
            if (i > 3 && i != 12) {
                form.items.item(i).setVisible(false);
            }
        }
    },

    toggleGridAndToolbar: function(booShow) {
        var booShowGrid = this.booAlwaysShowGrid ? true : booShow;
        this.advancedSearchGrid.setVisible(booShowGrid);

        Ext.getCmp(this.advancedSearchTabPanelId).setVisible(booShowGrid);

        if (booShowGrid) {
            Ext.getCmp(this.advancedSearchTabPanelId).activate(0);

            if (this.booReadOnlySearch) {
                this.fixSearchGridHeight();
            }
        }
    },

    findPanelField: function(panel, fieldName, fieldType) {
        var oField;
        var allChildren = this.getAllChildren(panel, this);
        for (var i = 0; i < allChildren.length; i++) {
            if(allChildren[i]['name'] == fieldName) {
                if (fieldType) {
                    if (allChildren[i]['xtype'] == fieldType) {
                        oField = allChildren[i];
                    }
                } else {
                    oField = allChildren[i];
                }
            }
        }
        
        return oField;
    },

    getAllChildren: function (panel, container) {
        /*Get children of passed panel or an empty array if it doesn't have them.*/
        var children = panel.items ? panel.items.items : [];

        /*For each child get their children and concatenate to result.*/
        Ext.each(children, function (child) {
            children = children.concat(container.getAllChildren(child, container));
        });

        return children;
    },

    resetFilter: function(booRefreshGrid) {
        var thisPanel = this;
        var fields = this.getAllChildren(this.advancedSearchForm, this);

        // Remove all rows except of the first one
        Ext.each(fields, function (item) {
            if (/^remove_row_\d+$/i.test(item.name) && item.name !== 'remove_row_1') {
                thisPanel.removeRow(item);
            }
        });

        // Maybe the list of fields was changed
        fields = this.getAllChildren(this.advancedSearchForm, this);
        Ext.each(fields, function (item) {
            if (/^field_\d+$/i.test(item.name)) {
                // Successful match
                item.reset();

                // Hide other fields
                thisPanel.showFilterOptions(item);
            }
        });

        if(booRefreshGrid) {
            this.applyFilter();
        }
    },

    getFieldValues: function() {
        var fields = this.getAllChildren(this.advancedSearchForm.ownerCt, this);
        var o = {},
            n,
            key,
            val;
        Ext.each(fields, function (f) {
            if (typeof f.disabled !== 'undefined' && typeof f.getName !== 'undefined' && !f.disabled) {
                n = f.getName();
                key = o[n];
                val = f.getValue();

                if (typeof n !== 'undefined') {
                    if (f.getXType() == 'radio') {
                        if (val) {
                            o[n] = f.value;
                        }
                    } else {
                        if (f.getXType() == 'datefield') {
                            val = Ext.util.Format.date(val, 'Y-m-d');
                        }

                        if (Ext.isDefined(key)){
                            if(Ext.isArray(key)){
                                o[n].push(val);
                            }else{
                                o[n] = [key, val];
                            }
                        } else {
                            o[n] = val;
                        }
                    }
                }
            }
        });
        return o;
    },

    checkIsValidSearch: function() {
        var booIsValidForm = true;
        var fields = this.getAllChildren(this.advancedSearchForm, this);
        Ext.each(fields, function (item) {
            if(typeof item.isValid !== 'undefined') {
                if(!item.isValid()) {
                    booIsValidForm = false;
                }
            }
        });

        return booIsValidForm;
    },

    applyFilter: function() {
        if (this.checkIsValidSearch()) {
            if (typeof (is_superadmin) === 'undefined' || (typeof (is_superadmin) !== 'undefined' && !is_superadmin)) {
                this.advancedSearchGrid.store.load();
            }
        }

        // Hide/show the Employer column on radio option change
        if (typeof (is_superadmin) !== 'undefined' && is_superadmin) {
            this.advancedSearchGrid.toggleEmployerColumn();
        }
    },

    getColumnIds: function() {
        var cols = [];
        var columns = this.advancedSearchGrid.getColumnModel().config;
        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex)) {
                cols.push(columns[i].dataIndex);
            }
        }

        return cols;
    },

    saveSearch: function () {
        var thisPanel = this;
        if (this.checkIsValidSearch()) {
            var wnd = new Ext.Window({
                title:      _('Save search'),
                layout:     'form',
                modal:      true,
                resizable:  false,
                plain:      false,
                autoHeight: true,
                autoWidth:  true,
                labelAlign: 'top',

                items: {
                    xtype:      'textfield',
                    fieldLabel: _('Name to save'),
                    emptyText:  _('Please enter the name...'),
                    ref:        'tmpSearchName',
                    allowBlank: false,
                    width:      400,
                    value:      thisPanel.searchNameField.getValue()
                },

                buttons: [
                    {
                        text: 'Cancel',
                        handler: function () {
                            wnd.close();
                        }
                    }, {
                        text: 'Save',
                        cls: 'orange-btn',
                        handler: function () {
                            if (wnd.tmpSearchName.isValid()) {
                                thisPanel.searchNameField.setValue(wnd.tmpSearchName.getValue());
                                thisPanel.sendRequestSaveSearch();
                                wnd.close();
                            }
                        }
                    }
                ],

                listeners: {
                    'show': function () {
                        wnd.tmpSearchName.clearInvalid();
                        wnd.tmpSearchName.focus(false, 50);
                    }
                }
            });

            wnd.show();
        }
    },

    sendRequestSaveSearch: function() {
        var thisPanel = this,
            store = this.advancedSearchGrid.getStore();

        thisPanel.getEl().mask(_('Saving...'));

        var cmModel = this.advancedSearchGrid.getColumnModel().config;
        // @NOTE: we skip first column because it is a checkbox
        // Also, skip the grouping Employer column
        var arrColumns = [];
        for (var i = 1; i < cmModel.length; i++) {
            if (!cmModel[i].hidden && cmModel[i].id != thisPanel.advancedSearchGrid.employerColumnId) {
                arrColumns.push({
                    id:    cmModel[i].dataIndex,
                    width: cmModel[i].width
                });
            }
        }

        // Prepare all params (fields + sort info)
        var arrSortInfo = {
            'sort': store.sortInfo.field,
            'dir':  store.sortInfo.direction
        };

        var searchName = this.searchNameField.getValue();

        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/search/save-search',

            params: {
                search_id: this.savedSearchId,
                search_columns: Ext.encode({
                    arrColumns: arrColumns,
                    arrSortInfo: arrSortInfo
                }),
                search_type: Ext.encode(this.panelType),
                search_name: Ext.encode(searchName),
                search_analytics: Ext.encode(this.analyticsPanel.getFilterFields()),
                advanced_search_params: Ext.encode(this.getFieldValues())
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    // Refresh list of Saved Searches
                    var wnd = Ext.getCmp(thisPanel.panelType + '-saved-searches-window');
                    if (wnd) {
                        wnd.SavedSearchesGrid.store.reload();
                    }

                    // Maybe tab's title must be changed too
                    thisPanel.setTitle(thisPanel.owner.generateSearchTabTitle(resultData.savedSearchId, searchName));
                    thisPanel.owner.fireEvent('titlechange', thisPanel.owner);

                    // Refresh the "read only" opened saved search
                    var applicantsPanel = Ext.getCmp(thisPanel.panelType + '-panel');
                    if (applicantsPanel && applicantsPanel.ApplicantsAdvancedSearchPanel && applicantsPanel.ApplicantsAdvancedSearchPanel.searchId == resultData.savedSearchId) {
                        applicantsPanel.ApplicantsAdvancedSearchPanel.loadSavedSearch(resultData.savedSearchId, searchName);
                    }

                    // Update new id
                    thisPanel.savedSearchId = resultData.savedSearchId;
                    thisPanel.searchId = resultData.savedSearchId;

                    // Show the Favourites button after the search was saved successfully
                    if (!thisPanel.booHideSearchFavoriteButton) {
                        thisPanel.searchFavoriteButton.setVisible(true);
                    }
                }

                var showMsgTime = resultData.success ? 1000 : 2000;
                var msg = String.format(
                    '<span style="color: {0}">{1}</span>',
                    resultData.success ? 'black' : 'red',
                    resultData.success ? _('Done!') : resultData.message
                );

                thisPanel.getEl().mask(msg);
                setTimeout(function () {
                    thisPanel.getEl().unmask();
                }, showMsgTime);
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Search cannot be saved. Please try again later.'));
                thisPanel.getEl().unmask();
            }
        });
    },

    showFilterOptions: function(combo, filterComboRec, index, fieldsComboRec, fired) {
        var form      = combo.ownerCt.ownerCt;
        var formItems = form.items;

        var clientType          = formItems.item(1);
        var fieldsCombo         = formItems.item(3).items.item(0);
        var filtersCombo        = formItems.item(4).items.item(0);
        var textField           = formItems.item(5).items.item(0);
        var optionsField        = formItems.item(6).items.item(0);
        var multiOptionsField   = formItems.item(7).items.item(0);
        var dateField           = formItems.item(8).items.item(0);
        var dateRangeFieldFrom  = formItems.item(9).items.item(0);
        var dateRangeLabelTo    = formItems.item(9).items.item(1);
        var dateRangeFieldTo    = formItems.item(9).items.item(2);
        var dateNextNumberField = formItems.item(10).items.item(0);
        var dateNextToField     = formItems.item(10).items.item(1);


        if(!fieldsComboRec) {
            var fieldsComboVal = fieldsCombo.getValue();

            var idx = fieldsCombo.store.find(fieldsCombo.valueField, fieldsComboVal);
            if (idx !== -1) {
                fieldsComboRec = fieldsCombo.store.getAt(idx);
            }
        }

        var booShowFilters      = false;
        var booShowDateFrom     = false;
        var booShowDateRange    = false;
        var booShowDateNext     = false;
        var booShowText         = false;
        var booShowOptions      = false;
        var booShowMultiOptions = false;
        var arrOptionsData      = [];
        var arrOpts             = [];
        if(fieldsComboRec && filterComboRec) {
            booShowFilters = true;
            var filterVal = filterComboRec.data.filter_id;
            switch (fieldsComboRec.data.field_type) {
                case 'short_date':
                case 'date':
                case 'date_repeatable':
                case 'office_change_date_time':
                    if(['is', 'is_not', 'is_before', 'is_after', 'is_between_today_and_date', 'is_between_date_and_today'].has(filterVal)) {
                        booShowDateFrom = true;
                    }

                    if(['is_between_2_dates'].has(filterVal)) {
                        booShowDateRange = true;
                    }

                    if(['is_in_the_next', 'is_in_the_previous'].has(filterVal)) {
                        booShowDateNext = true;
                    }
                    break;

                case 'combo':
                case 'multiple_combo':
                    if(['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }

                    arrOptionsData = arrApplicantsSettings.options[clientType.getValue()][fieldsComboRec.data.field_id];
                    break;

                case 'office_multi':
                    if(['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }
                    arrOptionsData = arrApplicantsSettings.options['general']['office'];
                    break;

                case 'agents':
                case 'office':
                case 'categories':
                case 'contact_sales_agent':
                case 'country':
                case 'employer_contacts':
                case 'authorized_agents':
                    if (['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }
                    arrOptionsData = arrApplicantsSettings.options['general'][fieldsComboRec.data.field_type];
                    break;

                case 'case_status':
                    if (['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }
                    arrOptionsData = arrApplicantsSettings.options.general.case_statuses['all'];
                    break;

                case 'case_type':
                    if (['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }

                    Ext.each(arrApplicantsSettings.case_templates, function(caseTemplate) {
                        arrOptionsData.push({
                            option_id:   caseTemplate.case_template_id,
                            option_name: caseTemplate.case_template_name
                        });
                    });
                    break;

                case 'assigned_to':
                case 'staff_responsible_rma':
                case 'active_users':
                    if (['is_one_of', 'is_none_of'].has(filterVal)) {
                        booShowMultiOptions = true;
                    } else {
                        booShowOptions = true;
                    }

                    arrOptionsData = arrApplicantsSettings.options['general'][fieldsComboRec.data.field_type];
                    arrOptionsData.forEach(function(element) {
                        if (element['status'] == 1) {
                            arrOpts.push(element);
                        }
                    });
                    arrOptionsData = arrOpts;
                    break;

                case 'float':
                case 'number':
                case 'auto_calculated':
                    booShowText = true;
                    break;

                // case 'text':
                // case 'password':
                // case 'email':
                // case 'phone':
                // case 'memo':
                default:
                    if (['contains', 'does_not_contain', 'is', 'is_not', 'starts_with', 'ends_with'].has(filterVal)) {
                        booShowText = true;
                    }
                    break;
            }
        }

        // Show only required fields, hide others
        filtersCombo.ownerCt.setVisible(booShowFilters);
        filtersCombo.setVisible(booShowFilters);
        textField.ownerCt.setVisible(booShowText);
        textField.setVisible(booShowText);
        optionsField.ownerCt.setVisible(booShowOptions);
        optionsField.setVisible(booShowOptions);
        multiOptionsField.ownerCt.setVisible(booShowMultiOptions);
        multiOptionsField.setVisible(booShowMultiOptions);
        dateField.ownerCt.setVisible(booShowDateFrom);
        dateField.setVisible(booShowDateFrom);

        dateRangeFieldFrom.ownerCt.setVisible(booShowDateRange);
        dateRangeFieldFrom.setVisible(booShowDateRange);
        dateRangeLabelTo.setVisible(booShowDateRange);
        dateRangeFieldTo.setVisible(booShowDateRange);

        dateNextNumberField.ownerCt.setVisible(booShowDateNext);
        dateNextNumberField.setVisible(booShowDateNext);
        dateNextToField.setVisible(booShowDateNext);

        // Disable other fields - for correct form validation
        filtersCombo.setDisabled(!booShowFilters);
        textField.setDisabled(!booShowText);
        optionsField.setDisabled(!booShowOptions);
        multiOptionsField.setDisabled(!booShowMultiOptions);
        dateField.setDisabled(!booShowDateFrom);
        dateRangeFieldFrom.setDisabled(!booShowDateRange);
        dateRangeLabelTo.setDisabled(!booShowDateRange);
        dateRangeFieldTo.setDisabled(!booShowDateRange);
        dateNextNumberField.setDisabled(!booShowDateNext);
        dateNextToField.setDisabled(!booShowDateNext);

        if (booShowOptions || booShowMultiOptions)
        {
            if (booShowOptions) {
                optionsField.store.loadData(arrOptionsData);
            }

            if (booShowMultiOptions) {
                multiOptionsField.store.loadData(arrOptionsData);
            }

            if (fired && !this.booReadOnlySearch) {
                optionsField.reset();
                multiOptionsField.reset();
            }
        }
    },

    showRelatedCasesCheckbox: function() {
        var thisPanel = this;
        var formItems = thisPanel.advancedSearchForm.items;

        Ext.getCmp(thisPanel.searchRelatedCasesCheckboxId).setVisible(false);

        for (var i = 1; i < formItems.length; i++) {
            var panel = formItems.itemAt(i);

            if (panel && panel.isVisible()) {

                var clientType = panel.items.items[1];
                var clientTypeProfilesRadio = panel.items.items[2].items.items[0];

                if (clientType.getValue() == 'employer' || clientType.getValue() == 'individual' || clientTypeProfilesRadio.getValue() == 'profile') {
                    Ext.getCmp(thisPanel.searchRelatedCasesCheckboxId).setVisible(true);
                    break;
                }
            }

        }
        thisPanel.owner.fixParentPanelHeight();
    },

    showFilter: function(fieldsCombo, fieldsComboRec) {
        var form        = fieldsCombo.ownerCt.ownerCt,
            formItems   = form.items,
            filterCombo = formItems.item(4).items.items[0],
            clientType  = formItems.item(1),
            fieldType   = formItems.item(11),
            filterComboData = [],
            booShowFilterOptions = true;

        clientType.setValue(fieldsComboRec.data.field_client_type);

        fieldType.setValue(fieldsComboRec.data.field_type);

        var filterComboWidth = 200;
        var filterPanelWidth = 130;
        switch (fieldsComboRec.data.field_type) {
            case 'float':
            case 'number':
            case 'auto_calculated':
                filterComboWidth = 120;
                filterComboData = this.arrSearchFilters['number'];
                break;

            case 'date':
            case 'office_change_date_time':
                filterComboWidth = 300;
                filterPanelWidth = 310;
                filterComboData = this.arrSearchFilters['date'];
                break;

            case 'date_repeatable':
                filterComboWidth = 350;
                filterPanelWidth = 360;
                filterComboData = this.arrSearchFilters['date_repeatable'];
                break;

            case 'combo':
            case 'multiple_combo':
            case 'agents':
            case 'office':
            case 'office_multi':
            case 'assigned_to':
            case 'staff_responsible_rma':
            case 'active_users':
            case 'contact_sales_agent':
            case 'country':
            case 'employer_contacts':
            case 'authorized_agents':
            case 'categories':
            case 'case_status':
            case 'case_type':
                filterComboWidth = 140;
                filterPanelWidth = 150;
                filterComboData = this.arrSearchFilters['combo'];
                break;

            case 'checkbox':
                filterComboWidth = 170;
                filterPanelWidth = 180;
                filterComboData = this.arrSearchFilters['checkbox'];
                break;

            // For special fields we don't need to show filters
            case 'special':
                booShowFilterOptions = false;
                break;

            case 'multiple_text_fields':
                filterComboWidth = 170;
                filterPanelWidth = 180;
                filterComboData = this.arrSearchFilters['multiple_text_fields'];
                break;

            // case 'textfield':
            default:
                filterComboWidth = 170;
                filterPanelWidth = 180;
                filterComboData = this.arrSearchFilters['text'];
                break;
        }

        if (booShowFilterOptions) {
            filterCombo.setDisabled(false);
            filterCombo.store.loadData(filterComboData);

            var filterValIndex = 0;
            var filterValue  = filterComboData[filterValIndex][0];
            var filterRecord = filterCombo.store.getAt(filterValIndex);

            filterCombo.setValue(filterValue);
            filterCombo.fireEvent('beforeselect', filterCombo, filterRecord, filterValue, fieldsComboRec, true);
            filterCombo.show();

            // Update combo width in relation to the filter type
            filterCombo.setWidth(filterComboWidth);
            filterCombo.ownerCt.setWidth(filterPanelWidth);
        } else {
           filterCombo.setVisible(false);
           this.showFilterOptions(filterCombo);
        }
    },

    denyRemoveSingleRow: function() {
        var thisPanel = this;
        Ext.each(thisPanel.advancedSearchForm.items.items, function (rec) {
            if (rec.getXType() == 'panel' && rec.items.items.length) {
                rec.items.items[12].items.items[0].setVisible(thisPanel.currentRowsCount > 1);
                rec.items.items[12].items.items[0].setDisabled(thisPanel.currentRowsCount == 1);
                rec.items.items[0].items.items[0].setVisible(false);
                rec.items.items[0].items.items[1].setVisible(true);
                return false;
            }
        });
    },

    removeRow: function(btn) {
        var thisPanel = this;
        thisPanel.currentRowsCount -= 1;

        btn.ownerCt.ownerCt.hide();
        btn.ownerCt.ownerCt.removeAll();

        thisPanel.showRelatedCasesCheckbox();
        thisPanel.addSearchRowButton.setDisabled(false);
        thisPanel.fixSearchGridHeight();
        thisPanel.owner.fixParentPanelHeight();
        thisPanel.denyRemoveSingleRow();
    },

    addNewRow: function(booFirst) {
        var thisPanel = this;
        var maxAllowedRows = advancedSearchRowsMaxCount;
        if (this.currentRowsCount >= maxAllowedRows)
            return;

        // Generate row id + save max index
        var maxRowsCountField = Ext.getCmp('max_rows_count' + (thisPanel.booAllowedMultipleTabs ? '_' + thisPanel.tabNumber : ''));
        var maxRowsCount = parseInt(maxRowsCountField.getValue(), 10);

        if(empty(maxRowsCount)) {
            maxRowsCount = 1;
        } else {

            var fields = this.getAllChildren(this.advancedSearchForm, this);

            var booFound = false;
            do {
                booFound = false;
                Ext.each(fields, function (item) {
                    if(typeof item.getName !== 'undefined') {
                        if(item.getName() === 'operator_' + maxRowsCount) {
                            booFound = true;
                        }
                    }
                });

                if (booFound) {
                    maxRowsCount += 1;
                }
            } while (booFound);
        }

        maxRowsCountField.setValue(maxRowsCount);
        this.currentRowsCount += 1;

        var dateFieldFromId = Ext.id();
        var dateFieldToId = Ext.id();

        var arrSearchFor = [];
        Ext.each(arrApplicantsSettings.search_for, function(oClientType){
            if (oClientType['search_for_id'] != 'accounting') {
                arrSearchFor.push(oClientType);
            }
        });

        var newEl = [
            {
                width: 115,
                bodyStyle: 'background-color: #E8E9EB;',
                items: [{
                    xtype: 'combo',
                    name: 'operator_' + maxRowsCount,
                    hiddenName: 'operator_' + maxRowsCount,
                    store: {
                        xtype: 'arraystore',
                        fields: ['operator_id', 'operator_name'],
                        data: [['and', 'AND'], ['or', 'OR']]
                    },
                    displayField: 'operator_name',
                    valueField:   'operator_id',
                    mode: 'local',
                    value: 'and',
                    hidden: booFirst,
                    width: 100,
                    forceSelection: true,
                    editable: false,
                    triggerAction: 'all',
                    selectOnFocus:true,
                    typeAhead: false
                }, {
                    xtype: 'label',
                    text: _('Search Criteria'),
                    style: 'display: block; margin-top: 10px; font-size: 16px',
                    hidden: !booFirst
                }]
            }, {
                xtype: 'hidden',
                name: 'field_client_type_' + maxRowsCount
            }, {
                width: 180,
                // Temporary don't show
                hidden: true,
                // hidden: this.panelType === 'contacts',
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                items: {
                    xtype: 'combo',
                    name: 'field_client_type_radio_' + maxRowsCount,
                    width: 165,
                    allowBlank: true,
                    store: new Ext.data.SimpleStore({
                        fields: ['option_id', 'option_name'],
                        data: [
                            ['profile', 'Profiles'],
                            ['case', 'Case']
                        ]
                    }),
                    displayField: 'option_name',
                    valueField:   'option_id',
                    hiddenName: 'field_client_type_radio_' + maxRowsCount,
                    typeAhead: false,
                    editable: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    emptyText:'Select option...',
                    searchContains: true,
                    selectOnFocus:true,
                    listeners: {
                        beforeselect: this.switchFilterFields.createDelegate(this),
                        select: this.showRelatedCasesCheckbox.createDelegate(this),
                        afterrender: function() {
                            this.reset();
                        }
                    }
                }
            },
            {
                width: 260,
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                items: {
                    xtype: 'combo',
                    name: 'field_' + maxRowsCount,
                    width: 250,
                    listWidth: 250,
                    allowBlank: false,
                    store: new Ext.data.Store({
                        reader: new Ext.data.JsonReader({
                            fields: [
                                {name: 'field_id'},
                                {name: 'field_unique_id'},
                                {name: 'field_name'},
                                {name: 'field_type'},
                                {name: 'field_group_id'},
                                {name: 'field_group_name'},
                                {name: 'field_client_type'},
                                {name: 'field_template_id'},
                                {name: 'field_template_name'}
                            ]
                        }),
                        data: this.getGroupedFields('all', ['password'])
                    }),
                    displayField: 'field_name',
                    valueField:   'field_id',
                    hiddenName: 'field_' + maxRowsCount,
                    typeAhead: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    emptyText:'Select a Field Name...',
                    searchContains: true,
                    selectOnFocus:true,
                    tpl: new Ext.XTemplate(
                        '<tpl for=".">',
                            '<tpl if="(this.field_template_id != values.field_template_id && !empty(values.field_template_id))">',
                                '<tpl exec="this.field_template_id = values.field_template_id"></tpl>',
                                '<h1 style="padding: 2px; background-color: #96BCEB;">{field_template_name}</h1>',
                            '</tpl>',
                            '<tpl if="this.field_group_name != values.field_group_name">',
                                '<tpl exec="this.field_group_name = values.field_group_name"></tpl>',
                                '<h1 style="padding: 2px 5px;">{field_group_name}</h1>',
                            '</tpl>',
                            '<tpl if="field_type == \'special\'">',
                                '<div class="x-combo-list-item search-list-odd-row" style="padding-left: 20px;">{field_name}</div>',
                            '</tpl>',
                            '<tpl if="field_type != \'special\'">',
                                '<div class="x-combo-list-item" style="padding-left: 20px;">{field_name}</div>',
                            '</tpl>',
                        '</tpl>'
                    ),
                    listeners: {
                        beforeselect: this.showFilter.createDelegate(this),
                        select: this.showRelatedCasesCheckbox.createDelegate(this),
                        afterrender: function() {
                            this.reset();
                        }
                    }
                }
            }, {
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'combo',
                    name: 'filter_' + maxRowsCount,
                    hiddenName: 'filter_' + maxRowsCount,
                    bodyStyle: 'background-color: #E8E9EB;',
                    allowBlank: false,
                    store: {
                        xtype: 'arraystore',
                        fields: ['filter_id', 'filter_name'],
                        data: []
                    },
                    displayField: 'filter_name',
                    valueField: 'filter_id',
                    hidden: true,
                    typeAhead: false,
                    editable: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    listeners: {
                        beforeselect: this.showFilterOptions.createDelegate(this)
                    }
                }
            }, {
                width: 210,
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'textfield',
                    name: 'text_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 190
                }
            }, {
                width: 210,
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'combo',
                    name: 'option_' + maxRowsCount,
                    hiddenName: 'option_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 190,
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'}
                        ])
                    },
                    displayField: 'option_name',
                    valueField:   'option_id',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    editable: false,
                    listeners: {
                        beforeselect: function (combo, record) {
                            // Show the tooltip (because the combo is small, option can be not fully visible)
                            new Ext.ToolTip({
                                target: combo.getEl(),
                                html: record.data.option_name
                            });
                        }
                    }
                }
            }, {
                width: 310,
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'lovcombo',
                    name: 'option_' + maxRowsCount,
                    hiddenName: 'option_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 290,
                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'},
                            {name: 'option_name'}
                        ])
                    },
                    displayField: 'option_name',
                    valueField:   'option_id',
                    separator:';',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    editable: false
                }
            }, {
                width: 150,
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'datefield',
                    name: 'date_' + maxRowsCount,
                    hiddenName: 'date_' + maxRowsCount,
                    format: dateFormatFull,
                    altFormats: dateFormatFull + '|' + dateFormatShort,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 135
                }
            }, {
                width: 390,
                layout: 'column',
                bodyStyle: 'background-color: #E8E9EB;',
                items: [
                    {
                        xtype: 'datefield',
                        id: dateFieldFromId,
                        name: 'date_from_' + maxRowsCount,
                        hiddenName: 'date_from_' + maxRowsCount,
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        allowBlank: false,
                        hidden: true,
                        disabled: true,
                        vtype: 'daterange',
                        endDateField: dateFieldToId,
                        width: 140
                    }, {
                        xtype: 'displayfield',
                        value: '&amp;',
                        style: 'background-color: #E8E9EB; margin: 10px 0 5px 5px; color: #677788; font-size: 12px; font-weight: bold;',
                        width: 15,
                        hidden: true
                    }, {
                        xtype: 'datefield',
                        id: dateFieldToId,
                        name: 'date_to_' + maxRowsCount,
                        hiddenName: 'date_to_' + maxRowsCount,
                        format: dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        allowBlank: false,
                        hidden: true,
                        disabled: true,
                        vtype: 'daterange',
                        startDateField: dateFieldFromId,
                        width: 140
                    }
                ]
            }, {
                width: 180,
                layout: 'column',
                bodyStyle: 'background-color: #E8E9EB;',
                items: [
                    {
                        xtype: 'numberfield',
                        name: 'date_next_num_' + maxRowsCount,
                        hiddenName: 'date_next_num_' + maxRowsCount,
                        style: 'margin-right: 10px',
                        allowBlank: false,
                        allowDecimals: false,
                        allowNegative: false,
                        minValue: 0,
                        value: 0,
                        hidden: true,
                        disabled: true,
                        width: 60
                    }, {
                        xtype: 'combo',
                        name: 'date_next_period_' + maxRowsCount,
                        hiddenName: 'date_next_period_' + maxRowsCount,
                        allowBlank: false,
                        hidden: true,
                        disabled: true,
                        width: 110,
                        store: {
                            xtype: 'arraystore',
                            fields: ['option_id', 'option_name'],
                            data:   [
                                ['D', 'Day(s)'],
                                ['W', 'Week(s)'],
                                ['M', 'Month(s)'],
                                ['Y', 'Years(s)']
                            ]
                        },
                        value: 'D',
                        displayField: 'option_name',
                        valueField:   'option_id',
                        typeAhead: false,
                        mode: 'local',
                        triggerAction: 'all',
                        editable: false
                    }
                ]
            }, {
                xtype: 'hidden',
                name: 'field_type_' + maxRowsCount
            }, {
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'button',
                    cls: 'applicant-advanced-search-hide-row',
                    text: '<i class="las la-times"></i>',
                    hideMode: 'visibility',
                    disabled: booFirst,
                    name: 'remove_row_' + maxRowsCount,
                    tooltip: 'Remove this row.',
                    handler: this.removeRow.createDelegate(this)
                }
            }
        ];

        this.advancedSearchForm.add({
            xtype: 'panel',
            layout: 'column',
            bodyStyle: 'background-color: #E8E9EB; font-size: 12px;',
            items: newEl
        });

        this.advancedSearchForm.doLayout();

        this.fixSearchGridHeight();

        this.owner.fixParentPanelHeight();

        if (this.currentRowsCount >= maxAllowedRows) {
            this.addSearchRowButton.setDisabled(true);
        }

        thisPanel.denyRemoveSingleRow();
    },

    fixSearchGridHeight: function () {
        // Don't try to fix the height if the grid is hidden
        if (!this.advancedSearchGrid.isVisible()) {
            return;
        }

        if (this.booReadOnlySearch) {
            this.updateAdvancedSearchGridHeight();
        } else {
            var newGridHeight = initPanelSize() - $('#' + Ext.getCmp(this.searchCriteriaContainerId).id).outerHeight() - 70;
            if (Ext.getCmp(this.searchCriteriaBackBtnId).isVisible()) {
                newGridHeight -= $('#' + Ext.getCmp(this.searchCriteriaBackBtnId).id).outerHeight();
            }

            var oneRowRecordHeight = 39;
            // Show at least 10 records if we have more than 5 records in the result
            var recordsCount = Ext.min([this.advancedSearchGrid.store.getCount(), 5]);

            // At least show 2 records (so "no records" will be correctly visible too)
            recordsCount = Ext.max([recordsCount, 2]);

            // At the same time, if we have more space - we can use it
            newGridHeight = Ext.max([(recordsCount * oneRowRecordHeight) + 80, newGridHeight]);

            this.advancedSearchGrid.setHeight(newGridHeight);
            this.advancedSearchGrid.doLayout();
        }
    },

    _exportToExcel: function(thisPanel, btn, format) {
        if (thisPanel.advancedSearchGrid.store.totalLength > arrApplicantsSettings.export_range) {
            var scrollMenu = new Ext.menu.Menu();
            var start = 0;
            var end = 0;
            var rangeCount = Math.ceil(thisPanel.advancedSearchGrid.store.totalLength / arrApplicantsSettings.export_range);
            for (var i = 0; i < rangeCount; ++i) {
                start = i * arrApplicantsSettings.export_range + 1;
                end = (i + 1) * arrApplicantsSettings.export_range;
                if (i == (rangeCount - 1)) {
                    end = thisPanel.advancedSearchGrid.store.totalLength;
                }
                scrollMenu.add({
                    text:      'Export ' + (start) + ' - ' + (end) + ' records',
                    listeners: {
                        click: thisPanel.exportToExcel.createDelegate(thisPanel, [start - 1, format])
                    }
                });
            }

            if (30 * i > window.innerHeight) {
                var intend = 0;
                if (Ext.getBody().getHeight() != window.innerHeight) {
                    intend = Ext.getBody().getHeight() - window.innerHeight;
                }
                scrollMenu.showAt([btn.getEl().getX() + btn.getWidth(), intend]);
            } else {
                scrollMenu.show(btn.getEl())
            }

        } else {
            thisPanel.exportToExcel(0, format);
        }
    },

    exportToExcel: function (exportStart, format) {
        if (empty(this.advancedSearchGrid.getStore().getCount())) {
            Ext.simpleConfirmation.warning('There is nothing to export.');
            return;
        }

        // Get visible columns
        var cm = [];
        var cmModel = this.advancedSearchGrid.getColumnModel().config;
        // @NOTE: we skip the first column because it is a checkbox
        for (var i = 1; i < cmModel.length; i++) {
            if (!cmModel[i].hidden) {
                cm.push({
                    id:    cmModel[i].dataIndex,
                    name:  cmModel[i].header,
                    width: cmModel[i].width
                });
            }
        }

        var store = this.advancedSearchGrid.getStore();
        var max = exportStart + arrApplicantsSettings.export_range;
        if (max > store.reader.jsonData.all_ids.length) {
            max = store.reader.jsonData.all_ids.length;
        }

        var filteredIds = [];
        for (i = exportStart; i < max; i++) {
            if (i in store.reader.jsonData.all_ids) {
                filteredIds.push(store.reader.jsonData.all_ids[i]);
            }
        }

        submit_hidden_form(baseUrl + '/applicants/search/export-to-excel', {
            format:     Ext.encode(format),
            searchType: Ext.encode(this.panelType),
            arrColumns: Ext.encode(cm),
            arrAllIds:  Ext.encode(filteredIds)
        });
    },

    prepareClientInfo: function (rec, arrData) {
        if (!empty(rec.case_id)) {
            if (rec.applicant_type == 'employer') {
                arrData.push({
                    'user_id':   rec.applicant_id,
                    'user_type': rec.applicant_type,
                    'user_name': rec.applicant_name
                });
                arrData.push({
                    'user_id':        rec.case_id,
                    'user_name':      rec.case_name,
                    'user_type':      'case',
                    'user_parent_id': rec.applicant_id,
                    'applicant_id':   rec.applicant_id,
                    'applicant_name': rec.applicant_name,
                    'applicant_type': rec.applicant_type
                });
            } else {
                arrData.push({
                    'user_id':        rec.applicant_id,
                    'user_name':      rec.applicant_name,
                    'user_type':      rec.applicant_type,
                    'applicant_id':   rec.case_id,
                    'applicant_name': rec.case_name
                });
            }
        } else {
            arrData.push({
                'user_id':   rec.applicant_id,
                'user_type': rec.applicant_type,
                'user_name': rec.applicant_name
            });
        }

        return arrData;
    },

    showOnSideBar: function() {
        var quickSearchPanel = Ext.getCmp(this.panelType + '_quick_search_panel');
        if (quickSearchPanel) {
            var panel = this,
                store = this.advancedSearchGrid.getStore();

            if (store.getTotalCount() <= store.baseParams.limit) {
                // Information is already loaded, show these records
                var arrData = [];
                store.each(function (rec) {
                    arrData = panel.prepareClientInfo(rec.data, arrData);
                });

                quickSearchPanel.ApplicantsSearchGrid.getStore().loadData({
                    success: true,
                    msg:     '',
                    items:   arrData,
                    count:   arrData.length
                });

                quickSearchPanel.setCurrentSearchName('Advanced search');
            } else {
                Ext.getBody().mask('Processing...');

                // Prepare all params (fields + sort info)
                var allParams   = store.baseParams;
                var arrSortInfo = {
                    'sort': store.sortInfo.field,
                    'dir':  store.sortInfo.direction
                };
                Ext.apply(allParams, arrSortInfo);

                Ext.Ajax.request({
                    url:    topBaseUrl + '/applicants/search/run-and-get-main-info',
                    params: allParams,

                    success: function (f) {
                        Ext.getBody().unmask();
                        var resultData = Ext.decode(f.responseText);

                        if (!resultData.success) {
                            Ext.simpleConfirmation.error(resultData.message);
                        } else {
                            var arrData = [];
                            Ext.each(resultData.items, function (rec) {
                                arrData = panel.prepareClientInfo(rec, arrData);
                            });

                            quickSearchPanel.ApplicantsSearchGrid.getStore().loadData({
                                success: true,
                                msg:     '',
                                items:   arrData,
                                count:   arrData.length
                            });

                            quickSearchPanel.setCurrentSearchName(resultData.searchName);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Data cannot be loaded. Please try again later.'));
                    }
                });
            }

        }
    },

    getFavouriteButtonText: function (booIsSearchFavorite) {
        return String.format(
            '<i class="{0}" title="{1}"></i>',
            booIsSearchFavorite ? 'las la-heart applicant_saved_search_favorite' : 'lar la-heart applicant_saved_search_not_favorite',
            _('Click to mark/unmark this search as a Favorite search.')
        )
    },

    updateFavoriteButton: function (booIsSearchFavorite) {
        this.searchFavoriteButton.setText(this.getFavouriteButtonText(booIsSearchFavorite));
    },

    markSearchAsFavorite: function () {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.markSearchAsFavorite(false, this.searchId);
        }
    },

    editSavedSearch: function () {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.openAdvancedSearchTab(this.savedSearchId, this.searchNameField.getValue());
        }
    },

    openQuickSavedSearch: function () {
        var queueTab = Ext.getCmp(this.panelType + '_queue_tab');
        if (queueTab) {
            queueTab.show();
        } else {
            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.openQuickAdvancedSearchTab(this.savedSearchId, this.searchNameField.getValue());
            }
        }
    }
});
