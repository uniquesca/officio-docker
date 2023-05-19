// Create user extensions namespace (Ext.ux)
Ext.namespace('Ext.ux');

Ext.ux.QuickSearchField = Ext.extend(Ext.Container, {
    booViewSavedSearches: true,
    booViewAdvancedSearch: true,

    quickSearchFieldEmptyText: 'Search...',
    quickSearchFieldEmptyTextOnHover: 'Search...',
    quickSearchFieldParamName: '',
    quickSearchFieldStore: null,
    quickSearchFieldRecordRenderer: null,
    quickSearchFieldOnRecordClick: null,
    quickSearchFieldOnShowDetailsClick: null,

    quickSearchFieldAdvancedSearchLinkOnClick: null,
    quickSearchFieldAdvancedSearchOnClick: null,
    quickSearchFieldAdvancedSearchOnDetailsClick: null,
    quickSearchFieldAdvancedSearchOnFavoritesClick: null,
    quickSearchFieldAdvancedSearchOnDelete: null,
    quickSearchFieldAdvancedSearchStore: null,

    // private
    thisQuickSearchField: null,
    thisQuickSearchExpander: null,
    thisQuickSearchResultsWindow: null,
    thisQuickSearchSavedSearchesGrid: null,
    thisQuickSearchResultsWindowGrid: null,

    // private
    initComponent: function () {
        Ext.ux.QuickSearchField.superclass.initComponent.call(this);

        this.layout = 'column';
        this.on('render', this.initAllComponents.createDelegate(this), this, {buffer: 50});
    },

    // private
    initAllComponents: function (thisQuickSearchField) {
        var thisRef = this;

        var minWidth = 275;
        var maxWidth = 500;
        var quickSearchFieldWidth = Ext.max([minWidth, Math.round(Ext.getBody().getViewSize().width / 3)]);
        quickSearchFieldWidth = Ext.min([maxWidth, quickSearchFieldWidth]);

        var mainColumnId = Ext.id();
        var savedSearchesColumnId = Ext.id();
        var savedSearchesButtonId = Ext.id();

        this.quickSearchFieldAdvancedSearchStore.on('load', function () {
            thisRef.thisQuickSearchResultsWindow.syncShadow();
        });

        thisRef.thisQuickSearchSavedSearchesGrid = new Ext.grid.GridPanel({
            sm: new Ext.grid.RowSelectionModel({
                singleSelect: true,
                listeners: {
                    'beforerowselect': function () {
                        return false;
                    }
                }
            }),

            split: true,
            hidden: true,
            autoWidth: true,
            autoHeight: true,
            stripeRows: true,
            loadMask: true,
            autoScroll: true,
            cls: 'extjs-grid no-cell-borders-grid',
            autoExpandColumn: savedSearchesColumnId,
            hideHeaders: true,

            store: this.quickSearchFieldAdvancedSearchStore,

            cm: new Ext.grid.ColumnModel({
                columns: [
                    {
                        id: savedSearchesColumnId,
                        dataIndex: 'search_name',
                        header: '',
                        renderer: function (val, i, rec) {
                            return String.format(
                                '<a href="#" class="applicant_saved_search_name" {0} onclick="return false;">{1}</a>',
                                rec.data.search_type == 'system' ? 'style="font-weight: bold;"' : '',
                                val
                            );
                        }
                    }, {
                        dataIndex: 'search_type',
                        width: 90,
                        align: 'right',
                        renderer: function (val, i, rec) {
                            var show = '';
                            if (rec.data.search_type == 'system') {
                                if (rec.data.search_can_be_set_default) {
                                    // show = rec.data.search_default ?
                                    //     'default' :
                                    //     '<a href="#" class="applicant_saved_search_set_as_default" onclick="return false;">' +
                                    //         _('(set as default)') +
                                    //         '</a>';
                                }
                            } else {
                                show = String.format(
                                    '<a href="#" class="{0} la-heart" onclick="return false;" title="{1}">&nbsp;</a>' +
                                    '<a href="#" class="applicant_saved_search_details las la-pen" onclick="return false;" title="{2}">&nbsp;</a>' +
                                    '<a href="#" class="applicant_saved_search_delete las la-trash" onclick="return false;" title="{3}">&nbsp;</a>',
                                    rec.data.search_is_favorite ? 'las applicant_saved_search_favorite' : 'lar applicant_saved_search_not_favorite',
                                    rec.data.search_is_favorite ? _('Click to unmark as a favorite Saved Search') : _('Click to mark as a favorite Saved Search'),
                                    _('Edit a Saved Search'),
                                    _('Delete a Saved Search')
                                );
                            }
                            return '<div class="gray_txt">' + show + '</div>';
                        }
                    }
                ]
            }),

            viewConfig: {
                deferEmptyText: 'No records found.',
                emptyText: 'No records found.',

                scrollOffset: 10,
                minHeight: 67,
                maxHeight: 400,

                onLayout: function () {
                    // Resize the body, so its height will be between the min and max height
                    var bodySize = this.mainBody.getSize();
                    if (this.minHeight !== undefined) {
                        bodySize.height = Math.max(bodySize.height, this.minHeight);
                    }

                    var topBodySize = Ext.getBody().getViewSize();
                    this.maxHeight = Math.max(400, Math.round(topBodySize.height * 2 / 3));
                    if (this.maxHeight !== undefined) {
                        bodySize.height = Math.min(bodySize.height, this.maxHeight);
                    }

                    // Fix scroller - so its height will be the same as of the body
                    // + browser's scroller will be automatically generated too
                    this.scroller.setHeight(bodySize.height);
                    this.scroller.setStyle('overflow-x', 'hidden');
                    this.scroller.setStyle('overflow-y', 'auto');
                    this.el.setHeight(bodySize.height);

                    // store the original scrollOffset
                    if (!this.orgScrollOffset) {
                        this.orgScrollOffset = this.scrollOffset;
                    }

                    // Fix Grid's scroller offset - to be sure that all columns will be fully visible
                    if (this.maxHeight !== undefined && this.maxHeight <= bodySize.height) {
                        // there is a scroller
                        this.scrollOffset = this.orgScrollOffset;
                    } else {
                        // no scroller
                        this.scrollOffset = 0;
                    }

                    this.fitColumns(false);
                }
            },

            listeners: {
                cellclick: function (grid, rowIndex, columnIndex, e) {
                    var rec = grid.getStore().getAt(rowIndex);  // Get the Record

                    var target = $(e.getTarget());
                    if (target.hasClass('applicant_saved_search_name')) {
                        // // Update currently selected search
                        thisRef.quickSearchFieldAdvancedSearchOnClick(rec);
                        thisRef.thisQuickSearchResultsWindow.hideWithEffect();
                    } else if (target.hasClass('applicant_saved_search_delete')) {
                        var question = String.format(
                            'Are you sure you want to delete <i>{0}</i>?',
                            rec.data.search_name
                        );

                        Ext.Msg.confirm('Please confirm', question, function (btn) {
                            if (btn === 'yes') {
                                thisRef.quickSearchFieldAdvancedSearchOnDelete(rec);
                            }

                            thisRef.thisQuickSearchResultsWindow.show();
                        });
                    } else if (target.hasClass('applicant_saved_search_favorite') || target.hasClass('applicant_saved_search_not_favorite')) {
                        thisRef.quickSearchFieldAdvancedSearchOnFavoritesClick(rec);
                    } else if (target.hasClass('applicant_saved_search_details')) {
                        thisRef.quickSearchFieldAdvancedSearchOnDetailsClick(rec);
                        thisRef.thisQuickSearchResultsWindow.hideWithEffect();
                    }
                },

                hide: function () {
                    var btn = Ext.getCmp(savedSearchesButtonId);
                    if (btn) {
                        $('#' + btn.id).parent().removeClass('active');
                    }
                },

                show: function () {
                    var btn = Ext.getCmp(savedSearchesButtonId);
                    if (btn) {
                        $('#' + btn.id).parent().addClass('active');
                    }
                }
            }
        });

        thisRef.thisQuickSearchResultsWindowGrid = new Ext.grid.GridPanel({
            sm: new Ext.grid.RowSelectionModel({
                singleSelect: true,

                listeners: {
                    'beforerowselect': function () {
                        return false;
                    }
                }
            }),

            hidden: true,
            split: true,
            autoWidth: true,
            stripeRows: true,
            loadMask: true,
            autoScroll: true,
            cls: 'extjs-grid no-cell-borders-grid',
            autoExpandColumn: mainColumnId,

            store: this.quickSearchFieldStore,

            hideHeaders: true,
            cm: new Ext.grid.ColumnModel({
                columns: [
                    {
                        id: mainColumnId,
                        dataIndex: '',
                        header: '',
                        renderer: function (val, p, record) {
                            return thisRef.quickSearchFieldRecordRenderer(val, p, record);
                        }
                    }
                ]
            }),

            viewConfig: {
                deferEmptyText: 'No records found.',
                emptyText: 'No records found.',

                scrollOffset: 10,
                minHeight: 67,
                maxHeight: 400,

                onLayout: function () {
                    // Resize the body, so its height will be between the min and max height
                    var bodySize = this.mainBody.getSize();
                    if (this.minHeight !== undefined) {
                        bodySize.height = Math.max(bodySize.height, this.minHeight);
                    }

                    var topBodySize = Ext.getBody().getViewSize();
                    this.maxHeight = Math.max(400, Math.round(topBodySize.height * 2 / 3));
                    if (this.maxHeight !== undefined) {
                        bodySize.height = Math.min(bodySize.height, this.maxHeight);
                    }

                    // Fix scroller - so its height will be the same as of the body
                    // + browser's scroller will be automatically generated too
                    this.scroller.setHeight(bodySize.height);
                    this.scroller.setStyle('overflow-x', 'hidden');
                    this.scroller.setStyle('overflow-y', 'auto');
                    this.el.setHeight(bodySize.height);

                    // store the original scrollOffset
                    if (!this.orgScrollOffset) {
                        this.orgScrollOffset = this.scrollOffset;
                    }

                    // Fix Grid's scroller offset - to be sure that all columns will be fully visible
                    if (this.maxHeight !== undefined && this.maxHeight <= bodySize.height) {
                        // there is a scroller
                        this.scrollOffset = this.orgScrollOffset;
                    } else {
                        // no scroller
                        this.scrollOffset = 0;
                    }

                    this.fitColumns(false);
                }
            },

            listeners: {
                cellclick: function (grid, rowIndex) {
                    var record = grid.getStore().getAt(rowIndex);  // Get the Record
                    thisRef.quickSearchFieldOnRecordClick(record);
                    thisRef.thisQuickSearchResultsWindow.hideWithEffect();
                }
            }
        });

        this.quickSearchFieldStore.on('beforeload', function (store, options) {
            store.isLoading = true;
            options.params = options.params || {};

            // New variable
            var params = {};
            params[thisRef.quickSearchFieldParamName] = Ext.encode(thisRef.thisQuickSearchField.getValue());

            Ext.apply(options.params, params);
        });

        this.quickSearchFieldStore.on('load', function (store) {
            store.isLoading = false;
            thisRef.thisQuickSearchResultsWindow.syncShadow();
        });

        this.quickSearchFieldStore.on('loadexception', function (store) {
            store.isLoading = false;
        });

        var arrGridLinks = [
            {
                xtype: 'box',
                hidden: !thisRef.booViewAdvancedSearch,

                'autoEl': {
                    'tag': 'a',
                    'href': '#',
                    'class': 'blulinkun',
                    'html': 'Advanced Search'
                }, // Thanks to IE - we need to use quotes...

                listeners: {
                    scope: this,
                    render: function (c) {
                        c.getEl().on('click', function (e) {
                            thisRef.quickSearchFieldAdvancedSearchLinkOnClick();
                            thisRef.thisQuickSearchResultsWindow.hideWithEffect();
                        }, this, {stopEvent: true});
                    }
                }
            }
        ];

        // Add only if it should be visible - to prevent extra td creation
        if (thisRef.booViewSavedSearches) {
            arrGridLinks.push({
                id: savedSearchesButtonId,
                xtype: 'box',

                'autoEl': {
                    'tag': 'a',
                    'href': '#',
                    'class': 'blulinkun',
                    'html': 'Saved Searches'
                }, // Thanks to IE - we need to use quotes...

                listeners: {
                    scope: this,
                    render: function (c) {
                        c.getEl().on('click', function () {
                            if (!thisRef.thisQuickSearchSavedSearchesGrid.isVisible()) {
                                thisRef.thisQuickSearchResultsWindowGrid.hide();
                                thisRef.thisQuickSearchSavedSearchesGrid.show();
                                thisRef.thisQuickSearchSavedSearchesGrid.syncSize();

                                if (!thisRef.quickSearchFieldAdvancedSearchStore.getCount()) {
                                    thisRef.quickSearchFieldAdvancedSearchStore.load();
                                }
                            } else {
                                var val = thisRef.thisQuickSearchField.getValue();
                                if (val !== '') {
                                    thisRef.thisQuickSearchResultsWindowGrid.show();
                                }
                                thisRef.thisQuickSearchSavedSearchesGrid.hide();
                            }

                            thisRef.thisQuickSearchResultsWindow.syncShadow();
                        }, this, {stopEvent: true});
                    }
                }
            });
        }

        thisRef.thisQuickSearchResultsWindow = new Ext.Window({
            layout: 'fit',
            closable: false,
            resizable: false,
            plain: true,
            autoHeight: true,
            frame: false,
            cls: 'quick-search-field-dialog',

            // Don't steal the focus, when this popup will be shown
            defaultButton: {
                getEl: Ext.emptyFn,
                focus: Ext.emptyFn
            },

            items: [
                {
                    xtype: 'container',
                    width: quickSearchFieldWidth + 29,
                    autoHeight: true,
                    style: 'background-color: #F0F0F1',

                    items: [
                        {
                            xtype: 'container',
                            cls: 'bottom-buttons-container',
                            layout: 'table',
                            layoutConfig: {
                                tableAttrs: {
                                    style: {
                                        width: '100%'
                                    }
                                },
                                columns: thisRef.booViewSavedSearches ? 2 : 1
                            },

                            items: arrGridLinks
                        },
                        thisRef.thisQuickSearchResultsWindowGrid,
                        thisRef.thisQuickSearchSavedSearchesGrid
                    ]
                }
            ],

            listeners: {
                show: function () {
                    // Try to show this popup behind previously opened dialogs
                    thisQuickSearchField.thisQuickSearchResultsWindow.setZIndex(8000);

                    thisQuickSearchField.thisQuickSearchResultsWindow.alignTo(thisQuickSearchField.thisQuickSearchField.getEl(), 'bl', [0, -2]);

                    thisQuickSearchField.thisQuickSearchResultsWindow.getEl().slideIn('t', {
                        stopFx: true,
                        easing: 'easeOut',
                        duration: .2,
                        callback: function () {
                            thisQuickSearchField.thisQuickSearchResultsWindow.syncShadow();
                        }
                    });
                }
            },

            hideWithEffect: function () {
                thisRef.thisQuickSearchResultsWindow.getEl().slideOut('t', {
                    easing: 'easeOut',
                    duration: .2,
                    stopFx: true,
                    callback: function () {
                        thisRef.thisQuickSearchResultsWindow.hide();
                    }
                });
            }
        });


        thisRef.thisQuickSearchField = new Ext.form.TextField({
            cls: 'quick-search-field',
            emptyText: this.quickSearchFieldEmptyText,
            width: quickSearchFieldWidth,
            enableKeyEvents: true
        });

        thisRef.thisQuickSearchField.on('render', thisRef.onQuickSearchFieldRender.createDelegate(thisRef), thisRef, {buffer: 500});
        thisRef.thisQuickSearchField.on('keyup', thisRef.onQuickSearchFieldKeyUp.createDelegate(thisRef), thisRef);

        thisRef.thisQuickSearchExpander = new Ext.BoxComponent({
            xtype: 'box',

            'autoEl': {
                'tag': 'a',
                'href': '#',
                'class': 'quick-search-field-options',
                'html': '<i class="las la-search"></i>'
            },

            listeners: {
                scope: this,
                render: function (c) {
                    c.getEl().on('click', function () {
                        if (!empty(thisRef.thisQuickSearchField.getValue())) {
                            thisRef.quickSearchFieldOnShowDetailsClick(thisRef.thisQuickSearchField.getValue());
                        }
                    }, this, {stopEvent: true});

                    thisRef.onQuickSearchFieldRender(c);
                }
            }
        });

        thisRef.add([
            this.thisQuickSearchField,
            this.thisQuickSearchExpander
        ]);

        thisRef.doLayout();
    },

    onQuickSearchFieldRender: function (c) {
        var thisRef = this;
        var wnd = thisRef.thisQuickSearchResultsWindow;

        var openPopupIfNotOpened = function () {
            if (!wnd.isVisible()) {
                wnd.show();
                thisRef.thisQuickSearchSavedSearchesGrid.hide();

                if (thisRef.thisQuickSearchField.getValue() === '') {
                    thisRef.thisQuickSearchResultsWindowGrid.hide();
                } else {
                    thisRef.thisQuickSearchResultsWindowGrid.show();
                }
            }
        };

        var el = c.getEl();
        el.on('paste', function () {
            c.fireEvent('keyup', c);
        });

        el.on('mousedown', function () {
            if (!arrHomepageSettings.settings.mouse_over_settings.includes('search-menu')) {
                openPopupIfNotOpened();
            }
        });

        el.on('mouseover', function () {
            // Change the default empty text if no data was entered
            if (empty(thisRef.thisQuickSearchField.getValue())) {
                thisRef.thisQuickSearchField.emptyText = thisRef.quickSearchFieldEmptyTextOnHover;
                thisRef.thisQuickSearchField.applyEmptyText();
                thisRef.thisQuickSearchField.reset();
            }

            if (arrHomepageSettings.settings.mouse_over_settings.includes('search-menu')) {
                openPopupIfNotOpened();
            }
        });

        var timeoutId;
        var checkIsMouseOutsideDialog = function () {
            if (wnd && !$('#' + thisRef.thisQuickSearchField.id).is(':hover') && !$('#' + thisRef.thisQuickSearchExpander.id).is(':hover') && !$('#' + wnd.id).is(':hover') && Ext.get(document.activeElement).id !== thisRef.thisQuickSearchField.id) {
                clearTimeout(timeoutId);
                wnd.hideWithEffect();
            } else {
                timeoutId = setTimeout(function () {
                    checkIsMouseOutsideDialog();
                }, 500);
            }
        };

        el.on('mouseout', function () {
            // Reset the default empty text if no data was entered
            if (empty(thisRef.thisQuickSearchField.getValue())) {
                thisRef.thisQuickSearchField.emptyText = thisRef.quickSearchFieldEmptyText;
                thisRef.thisQuickSearchField.applyEmptyText();
                thisRef.thisQuickSearchField.reset();
            }

            if (wnd && wnd.isVisible()) {
                timeoutId = setTimeout(function () {
                    checkIsMouseOutsideDialog();
                }, 500);
            }
        });
    },

    onQuickSearchFieldKeyUp: function (field, e) {
        var thisRef = this;
        var entered = trim(field.getValue());
        var booEnter = false;

        // This method can be called in the 'paste' event, so we don't have an event/key here
        if (typeof e !== 'undefined') {
            // Skip special keys other than: Enter, Shift, Backspace keys
            if (e.getKey() !== e.ENTER && e.getKey() !== e.BACKSPACE && ((e.hasModifier() && !e.shiftKey) || e.isNavKeyPress() || e.isSpecialKey())) {
                return;
            }

            // Additional fix for Mac
            if (Ext.isMac && (e.getKey() === 224 || e.getKey() === 93 || e.getKey() === 91)) {
                return;
            }

            booEnter = e.getKey() === e.ENTER && entered !== '';
        }

        var store = thisRef.quickSearchFieldStore;
        if (booEnter) {
            if (store.isLoading) {
                var conn = store.proxy.getConnection();
                conn.abort();
            }

            thisRef.quickSearchFieldOnShowDetailsClick(entered);
            thisRef.thisQuickSearchResultsWindow.hide();
        } else {
            thisRef.thisQuickSearchResultsWindow.show();

            // Hide saved searches grid
            thisRef.thisQuickSearchSavedSearchesGrid.hide();

            // Show search results grid + button if there is something entered
            if (entered === '') {
                thisRef.thisQuickSearchResultsWindowGrid.hide();
            } else {
                thisRef.thisQuickSearchResultsWindowGrid.show();

                clearTimeout(store.timeOutDelay);
                store.timeOutDelay = setTimeout(function () {
                    if (store.isLoading) {
                        var conn = store.proxy.getConnection();
                        conn.abort();
                    }

                    store.load();
                }, entered.length > 3 ? 500 : 3000);
            }

            thisRef.thisQuickSearchResultsWindow.syncShadow();
        }
    }
}); // end of extend

// register xtype
Ext.reg('quicksearchfield', Ext.ux.QuickSearchField);
