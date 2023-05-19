Ext.ns('Ext.ux');
Ext.ux.TabUniquesMenuSimple = Ext.extend(Object, {
    booAllowTabClosing: false,

    defaultEmptyText: 'No records found.',

    // private
    lastViewedRecordsGrid: null,

    constructor: function (config) {
        config = config || {};
        Ext.apply(this, config);
    },

    init: function (tp) {
        var thisRef = this;
        if (tp instanceof Ext.TabPanel) {
            tp.on('render', thisRef.initLastViewedRecordsTab.createDelegate(tp, [thisRef]));
            tp.on('tabchange', thisRef.onTabChange, thisRef);
            tp.on('beforeremove', thisRef.onTabRemove, thisRef);
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
        var thisRef = this;
        var genId = Ext.id();
        var mainColumnId = Ext.id();

        thisRef.lastViewedRecordsGrid = new Ext.grid.GridPanel({
            id:               genId,
            sm:               new Ext.grid.RowSelectionModel({singleSelect: true}),
            split:            true,
            width:            300,
            autoHeight:       true,
            stripeRows:       true,
            loadMask:         true,
            autoScroll:       true,
            autoExpandColumn: mainColumnId,

            cls: 'recently-opened-records-grid',

            store: new Ext.data.Store({
                data: [],

                reader: new Ext.data.JsonReader({
                    id: 'r_id'
                }, [{
                    name: 'r_id'
                }, {
                    name: 'r_name'
                }, {
                    name: 'tab_opening_params'
                }])
            }),

            hideHeaders: true,

            cm: new Ext.grid.ColumnModel({
                columns: [{
                    id:        mainColumnId,
                    dataIndex: 'r_name',
                    header:    '',
                    renderer:  function (val, p, record) {
                        var selected = '';
                        var aTab = thisTabPanel.getActiveTab();
                        thisTabPanel.items.each(function (tab) {
                            if (aTab && record.data['r_id'] === aTab.id) {
                                selected = 'active-row';
                                return false;
                            } else if (tab.pid === record.data['r_id']) {
                                selected = 'selected-row';
                                return false;
                            } else {
                                selected = 'normal-row';
                            }
                        });

                        return '<div class="' + selected + '">' + record.data['r_name'] + '</div>';
                    }
                }]
            }),

            viewConfig: {
                deferEmptyText: thisRef.defaultEmptyText,
                emptyText: thisRef.defaultEmptyText,

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
            },

            listeners: {
                cellclick: function (grid, rowIndex) {
                    var record = grid.getStore().getAt(rowIndex);  // Get the Record
                    thisTabPanel.setActiveTab(record.data['r_id']);

                    wnd.hide();
                }
            }
        });

        var wnd = new Ext.Window({
            closable:   false,
            resizable:  false,
            plain:      true,
            border:     false,
            autoHeight: true,
            width:      300,
            frame:      false,

            cls: 'recently-opened-records-dialog',

            anim: {
                endOpacity: 1,
                easing:     'easeIn',
                duration:   .3
            },

            items: [thisRef.lastViewedRecordsGrid],

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
            wnd.alignTo(this.lastViewedRecordsTab.child('span.last-viewed-records', true), 'bl', [-5, 2]);
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
        var wnd = thisRef.lastViewedRecordsPopup;

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
            if (this.lastViewedRecordsGrid.getStore().getCount()) {
                var booFound = false;
                var nextRec = null;
                this.lastViewedRecordsGrid.getStore().each(function (rec) {
                    if (booFound && nextRec === null) {
                        nextRec = rec;
                    }

                    if (rec.data['r_id'] === removedTab.id) {
                        booFound = true;
                    }
                });

                if (nextRec === null) {
                    nextRec = this.lastViewedRecordsGrid.getStore().getAt(0);
                }

                if (nextRec) {
                    tp.setActiveTab(nextRec.data['r_id']);
                }
            }

            if (!this.booAllowTabClosing) {
                return false;
            }
        }
    },

    onTabChange: function (tp, activeTab) {
        var thisRef = this;
        var openedTabsCount = 0;
        tp.items.each(function (tab) {
            if (tab.closable) {
                var booShow = false;
                // If we want to show the main tab + 1 last opened T/A tab - uncomment
                if (tab.id === activeTab.id/* || (!activeTab.closable && openedTabsCount === 0)*/) {
                    booShow = true;
                }

                if (booShow) {
                    tp.unhideTabStripItem(tab);
                } else {
                    tp.hideTabStripItem(tab);
                }

                openedTabsCount++;
            }
        });
        this.lastViewedRecordsGrid.getStore().loadData(this.getLastOpenedRecords(tp));

        // Update the count of currently opened tabs that can be closed
        Ext.fly(thisRef.lastViewedRecordsTab).child('span.last-viewed-records-count', true).innerText = empty(openedTabsCount) ? '' : openedTabsCount;
    },

    getLastOpenedRecords: function (tp) {
        var arrTabs = [];

        tp.items.each(function (tab) {
            if (tab.closable) {
                arrTabs.push({
                    r_id:   tab.id,
                    r_name: Ext.fly(tp.getTabEl(tab)).child('span.x-tab-strip-text', true).innerText.replace(/[\n\r]/g, ' ')
                });
            }
        });

        return arrTabs;
    }
});