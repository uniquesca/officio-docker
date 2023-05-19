Ext.ns('Ext.ux');
Ext.ux.TabUniquesMenu = Ext.extend(Object, {
    originalStorageProvider: null,

    arrOpenedTabs:          [],
    arrRecentlyOpenedTabs:  [],
    lastViewedRecordsTab:   null,
    lastViewedRecordsPopup: null,
    lastViewedRecordsGrid:  null,

    openTabOfTheRecord: function (oOpenTabParams) {
        console.log('Please update', oOpenTabParams);
    },

    constructor: function (config) {
        config = config || {};
        Ext.apply(this, config);
    },

    init: function (tp) {
        var thisRef = this;
        if (tp instanceof Ext.TabPanel) {
            tp.on('render', thisRef.initLastViewedRecordsTab.createDelegate(tp, [thisRef]));
            tp.on('tabchange', thisRef.onTabChange, thisRef);
            tp.on('titlechange', thisRef.onTabTitleChange.createDelegate(tp, [thisRef]));
            tp.on('remove', thisRef.onTabRemove, thisRef);
            tp.on('add', thisRef.onTabAdd, thisRef);
        }
    },

    onTabTitleChange: function (thisRef) {
        var activeTab = this.getActiveTab();
        if (activeTab && activeTab.closable) {
            thisRef.saveLastOpenedRecords(this, activeTab);
        }
    },

    initLastViewedRecordsTab: function (thisRef) {
        thisRef.lastViewedRecordsTab = this.itemTpl.insertBefore(this.edge, {
            text:    '<span class="last-viewed-records-count"></span>',
            iconCls: 'last-viewed-records'
        }, true);

        thisRef.lastViewedRecordsTab.on({
            mousedown: {
                fn: thisRef.onMouseClick,
                scope: thisRef
            },

            mouseover: {
                fn:    thisRef.onMouseOver,
                scope: thisRef
            },

            mouseout: {
                fn:    thisRef.onMouseOut,
                scope: thisRef
            }
        });

        thisRef.initLastViewedRecordsPopup(this);
    },

    initLastViewedRecordsPopup: function (thisTabPanel) {
        var thisRef      = this;
        var genId        = Ext.id();
        var mainColumnId = Ext.id();

        thisRef.lastViewedRecordsGrid = new Ext.grid.GridPanel({
            id:               genId,
            sm:               new Ext.grid.RowSelectionModel({singleSelect: true}),
            split:            true,
            width:            400,
            autoHeight:       true,
            stripeRows:       true,
            loadMask:         true,
            autoScroll:       true,
            autoExpandColumn: mainColumnId,

            cls: 'recently-opened-records-grid',

            store: new Ext.data.GroupingStore({
                data: thisRef.getLastOpenedRecords(),
                groupField: 'r_is_opened',
                remoteGroup: true,
                sortInfo: {field: 'r_order', direction: "ASC"},

                reader: new Ext.data.JsonReader({
                    id: 'r_id'
                }, [
                    {
                        name: 'r_order'
                    }, {
                        name: 'r_id'
                    }, {
                        name: 'r_name'
                    }, {
                        name: 'r_is_opened'
                    }, {
                        name: 'tab_opening_params'
                    }
                ]),

                listeners: {
                    load: function (store, records) {
                        var minWidth = 200; // Minimum width
                        var maxWidth = minWidth;

                        // Update the width - based on the longest text
                        Ext.each(records, function (item) {
                            var metrics = Ext.util.TextMetrics.createInstance(thisTabPanel.getEl());
                            maxWidth = Ext.max([metrics.getWidth(item['data']['r_name']) + 80, maxWidth]);
                        });

                        setTimeout(function () {
                            if (wnd) {
                                // Paddings = 10px * 2
                                var padding = maxWidth == minWidth ? 20 : 0;
                                wnd.setWidth(maxWidth + padding);
                                thisRef.lastViewedRecordsGrid.setWidth(maxWidth);
                            }
                        }, 50);
                    }
                }
            }),

            hideHeaders: true,

            cm: new Ext.grid.ColumnModel({
                columns: [
                    {
                        dataIndex: 'r_is_opened',
                        hidden: true,
                        renderer: function (val, p, record) {
                            return record.data['r_is_opened'] ? 'Currently Open' : 'Recently viewed';
                        }
                    }, {
                        id:        mainColumnId,
                        dataIndex: 'r_name',
                        header:    '',
                        renderer:  function (val, p, record) {
                            var selected = '';
                            var aTab     = thisTabPanel.getActiveTab();
                            thisTabPanel.items.each(function (tab) {
                                if (aTab && record.data['r_id'] === aTab.id) {
                                    selected = 'active-row';
                                    return false;
                                } else if (tab.id === record.data['r_id']) {
                                    selected = 'selected-row';
                                    return false;
                                } else {
                                    selected = 'normal-row';
                                }
                            });

                            return '<a href="#" onclick="return false;" class="recently-opened-link ' + selected + '">' + record.data['r_name'] + '</a>';
                        }
                    }, {
                        dataIndex: 'r_name',
                        header: '',
                        width: 20,
                        renderer: function (val, p, record) {
                            return record.data['r_is_opened'] ? '<a href="#" onclick="return false;"><i class="recently-opened-link-close las la-times"></i></a>' : '';
                        }
                    }
                ]
            }),

            view: new Ext.grid.GroupingView({
                deferEmptyText: 'No records found.',
                emptyText: 'No records found.',
                forceFit: true,
                showGroupName: false,
                groupTextTpl: '{text}',

                // Prevent groups toggling (by clicking on the group name)
                toggleGroup: Ext.emptyFn,

                //  hack will make sure that there is no blank space if there is no scroller:
                scrollOffset: 20,

                onLayout: function () {
                    // store the original scrollOffset
                    if (!this.orgScrollOffset) {
                        this.orgScrollOffset = this.scrollOffset;
                    }

                    var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                    if (scroller.clientWidth === scroller.offsetWidth) {
                        // no scroller
                        this.scrollOffset = 2;
                    } else {
                        // there is a scroller
                        this.scrollOffset = this.orgScrollOffset;
                    }

                    this.fitColumns(false);
                }
            }),

            listeners: {
                cellclick: function (grid, rowIndex, columnIndex, e) {
                    var record = grid.getStore().getAt(rowIndex);  // Get the Record
                    var oParams = record.data['tab_opening_params'];

                    if ($(e.getTarget()).hasClass('recently-opened-link')) {
                        thisRef.openTabOfTheRecord(oParams);
                        wnd.hide();
                    } else if ($(e.getTarget()).hasClass('recently-opened-link-close')) {
                        if (oParams.hasOwnProperty('tabId')) {
                            var tab = Ext.getCmp(oParams['tabId']);
                            if (tab) {
                                var openedCount = 0;
                                grid.getStore().each(function (oRecord) {
                                    if (oRecord.data['r_is_opened']) {
                                        openedCount++;
                                    }
                                });

                                tab.fireEvent('close');
                                thisTabPanel.remove(tab);

                                // Don't close if there are opened tabs/clients, so they can be closed too
                                if (openedCount <= 1) {
                                    wnd.hide();
                                }
                            }
                        }
                    }
                }
            }
        });

        var wnd = new Ext.Window({
            closable:   false,
            resizable:  false,
            plain:      true,
            border:     false,
            autoHeight: true,
            width:      400,
            frame:      false,
            cls:        'recently-opened-records-dialog',

            anim: {
                endOpacity: 1,
                easing:     'easeIn',
                duration:   .3
            },

            items: [
                thisRef.lastViewedRecordsGrid
            ],

            listeners: {
                show: {
                    fn: function (w) {
                        wnd.getEl().fadeIn(w.anim);
                    }
                }
            }
        });

        this.lastViewedRecordsPopup = wnd;
    },

    openPopupIfNotOpened: function () {
        var wnd = this.lastViewedRecordsPopup;
        if (wnd && !wnd.isVisible()) {
            wnd.show();
            wnd.alignTo(this.lastViewedRecordsTab, 'bl', [2, -2]);
        }
    },

    onMouseClick: function (e) {
        var thisRef = this;
        e.stopEvent();

        if (arrHomepageSettings.settings.mouse_over_settings.includes('recently-viewed-menu')) {
            return;
        }

        clearTimeout(thisRef.timeoutId);
        this.openPopupIfNotOpened();
    },

    onMouseOver: function () {
        var thisRef = this;
        clearTimeout(thisRef.timeoutId);

        if (arrHomepageSettings.settings.mouse_over_settings.includes('recently-viewed-menu')) {
            this.openPopupIfNotOpened();
        }
    },

    onMouseOut: function () {
        var thisRef = this;
        var wnd = thisRef.lastViewedRecordsPopup;
        if (wnd && wnd.isVisible()) {
            thisRef.timeoutId = setTimeout(function () {
                thisRef.checkIsMouseOutsideDialog();
            }, 500);
        }
    },

    // Don't hide the popup if cursor is above the icon or this popup
    checkIsMouseOutsideDialog: function () {
        var thisRef = this;
        var wnd     = thisRef.lastViewedRecordsPopup;

        if (wnd && !$('#' + wnd.id).is(':hover') && !$('#' + thisRef.lastViewedRecordsTab.id).is(':hover')) {
            wnd.hide();
            clearTimeout(thisRef.timeoutId);
        } else {
            thisRef.timeoutId = setTimeout(function () {
                thisRef.checkIsMouseOutsideDialog();
            }, 500);
        }
    },

    onTabRemove: function (tp, removedTab) {
        if (tp instanceof Ext.TabPanel) {
            var thisRef = this;
            // Remove from the "last opened list"
            Ext.each(thisRef.arrOpenedTabs, function (tab, index) {
                if (!tab || tab.id === removedTab.id) {
                    thisRef.arrOpenedTabs.splice(index, 1);
                }
            });

            // If the tab was really removed - remove from the list at all
            if (removedTab.hasOwnProperty('really_deleted') && removedTab.really_deleted) {
                var arrLastViewedRecords = this.getLastOpenedRecords();

                Ext.each(arrLastViewedRecords, function (tab, index) {
                    if (tab && tab.r_id === removedTab.id) {
                        arrLastViewedRecords.splice(index, 1);
                        return false;
                    }
                });

                this.setLastOpenedRecords(arrLastViewedRecords);
            }

            var activeTab = tp.getActiveTab();
            if (thisRef.arrOpenedTabs.length) {
                // Automatically activate the "first" tab
                // Make sure that onTabChange will be called
                if (activeTab && activeTab.id == thisRef.arrOpenedTabs[0].id) {
                    this.onTabChange(tp, activeTab);
                } else {
                    tp.setActiveTab(thisRef.arrOpenedTabs[0]);
                }
            } else if (tp.items.length) {
                // Automatically activate the "last" (non closable) tab
                try {
                    // It can fail (for some reason that extjs only knows why),
                    // so simply ignore it
                    tp.setActiveTab(tp.items.length - 1);

                    // Also force to call the "tab change" - to remove the tab even if it is not active
                    if (!activeTab.closable && removedTab.closable) {
                        this.onTabChange(tp, activeTab);
                    }
                } catch (e) {
                }
            }
        }
    },

    onTabAdd: function (tp, activeTab) {
        if (tp instanceof Ext.TabPanel && activeTab.closable) {
            var thisRef = this;
            Ext.each(thisRef.arrOpenedTabs, function (tab, index) {
                if (!tab || tab.id === activeTab.id) {
                    thisRef.arrOpenedTabs.splice(index, 1);
                }
            });

            thisRef.arrOpenedTabs.unshift(activeTab);
        }
    },

    onTabChange: function (tp, activeTab) {
        var thisRef = this;

        var openedTabsCount = 0;
        tp.items.each(function (tab) {
            if (tab.closable) {
                // If we want to show the main tab + 1 last opened case/prospect/contact tab - uncomment
                // if (activeTab.closable) {
                    if (tab.id === activeTab.id) {
                        tp.unhideTabStripItem(tab);
                    } else {
                        tp.hideTabStripItem(tab);
                    }
                // }

                openedTabsCount++;
            }
        });

        if (activeTab.closable) {
            // Place this tab as the first one and save list to the cookie
            thisRef.saveLastOpenedRecords(tp, activeTab);
        } else {
            if (empty(thisRef.arrOpenedTabs.length)) {
                thisRef.saveLastOpenedRecords(tp, null);
            } else {
                // Force to highlight the active records / tabs
                this.lastViewedRecordsGrid.getStore().loadData(this.getLastOpenedRecords());
            }
        }

        // Update the count of currently opened tabs that can be closed
        Ext.fly(thisRef.lastViewedRecordsTab).child('span.last-viewed-records-count', true).innerText = empty(openedTabsCount) ? '' : openedTabsCount;
    },

    getSettingsKeyName: function () {
        return 'recently-viewed-' + this.panelType;
    },

    setOurStorageProvider: function () {
        if (window.localStorage) {
            Ext.state.Manager.setProvider(new Ext.ux.state.LocalStorageProvider({prefix: 'uniques-'}))
            this.originalStorageProvider = Ext.state.Manager.getProvider();
        }
    },

    restoreStorageProvider: function () {
        if (this.originalStorageProvider) {
            Ext.state.Manager.setProvider(this.originalStorageProvider)
        }
    },

    getLastOpenedRecords: function () {
        // this.setOurStorageProvider();
        // var arrLastViewedRecords = Ext.state.Manager.get(this.getSettingsKeyName()) || [];
        // this.restoreStorageProvider();
        //
        // return arrLastViewedRecords;
        return this.arrRecentlyOpenedTabs;
    },

    setLastOpenedRecords: function (arrLastViewedRecords) {
        this.arrRecentlyOpenedTabs = arrLastViewedRecords;
        // this.setOurStorageProvider();
        // Ext.state.Manager.set(this.getSettingsKeyName(), arrLastViewedRecords);
        // this.restoreStorageProvider();
    },

    saveLastOpenedRecords: function (tp, activeTab) {
        // Try to load from the cookie
        var arrLastViewedRecords = this.getLastOpenedRecords();

        // Add active tab to the top
        var recordOrder = 0;
        var arrSortedLastViewedRecords = [];
        var arrIds = [];

        tp.items.each(function (oTab) {
            // Show Queue (Clients) and Advanced Search tabs at the top of the list
            if (oTab.id != tp.defaultTabId && oTab.hasOwnProperty('oOpenTabParams') && ['advanced_search'].has(oTab.oOpenTabParams.tabType) && !arrIds.has(oTab.id)) {
                arrSortedLastViewedRecords.push({
                    r_order:            recordOrder++,
                    r_id:               oTab.id,
                    r_name:             Ext.fly(tp.getTabEl(oTab)).child('span.x-tab-strip-text', true).innerText.replace(/[\n\r]/g, ' '),
                    r_is_opened:        true,
                    tab_opening_params: oTab.oOpenTabParams
                });

                arrIds.push(oTab.id);
            }
        });

        // Show opened tabs at the top, under the currently active tab
        for (var i = tp.items.getCount(); i > 0 ; i--) {
            var oTab = tp.items.item(i - 1);
            if (oTab.closable) {
                var booFound = false;
                Ext.each(arrLastViewedRecords, function (oSaved) {
                    if (oSaved && oTab.id === oSaved.r_id && !arrIds.has(oTab.id)) {
                        oSaved['r_is_opened'] = true;
                        oSaved['r_order'] = recordOrder++;
                        arrSortedLastViewedRecords.push(oSaved);
                        arrIds.push(oTab.id);

                        booFound = true;
                    }
                });

                // If the tab wasn't opened before (e.g. from the advanced search)
                if (!booFound && !arrIds.has(oTab.id)) {
                    arrSortedLastViewedRecords.push({
                        r_order:            recordOrder++,
                        r_id:               oTab.id,
                        r_name:             Ext.fly(tp.getTabEl(oTab)).child('span.x-tab-strip-text', true).innerText.replace(/[\n\r]/g, ' '),
                        r_is_opened:        true,
                        tab_opening_params: oTab.oOpenTabParams
                    });

                    arrIds.push(oTab.id);
                }
            }
        }

        // Add all other not opened records
        Ext.each(arrLastViewedRecords, function (tab) {
            if (tab && !arrIds.has(tab.r_id)) {
                tab['r_is_opened'] = false;
                tab['r_order'] = recordOrder++;
                arrSortedLastViewedRecords.push(tab);
                arrIds.push(tab.r_id);
            }
        });

        // Don't show more than X records
        // 36px for 1 record
        // 30px for each header
        var maxToShow = Math.floor((initPanelSize() - 60) / 36);
        maxToShow = maxToShow < 5 ? 5 : maxToShow;
        if (arrSortedLastViewedRecords.length > maxToShow) {
            arrSortedLastViewedRecords.splice(maxToShow);
        }

        this.setLastOpenedRecords(arrSortedLastViewedRecords);

        this.lastViewedRecordsGrid.getStore().loadData(arrSortedLastViewedRecords);
    }
});
