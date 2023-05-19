// Create user extensions namespace (Ext.ux)
Ext.namespace('Ext.ux');

Ext.ux.NOCSearchField = Ext.extend(Ext.form.TextField, {
    NOCSearchFieldEmptyText: 'Search...',
    NOCSearchFieldEmptyTextOnHover: 'Search...',
    NOCSearchFieldParamName: 'query',
    NOCSearchFieldParamNameIsNOC: 1,
    NOCSearchFieldDisplayField: 'noc_job_and_code',
    NOCSearchFieldWidth: 50,
    NOCSearchPopupWidth: 570,
    NOCSearchFieldOnRecordClick: null,
    NOCSearchFieldOnAfterRender: null,
    NOCSearchFieldOnKeyUp: null,

    // private
    NOCSearchFieldStore: null,
    thisNOCSearchResultsWindow: null,
    thisNOCSearchResultsWindowGrid: null,

    // private
    initComponent: function () {
        Ext.ux.NOCSearchField.superclass.initComponent.call(this);

        // this.layout = 'column';
        this.cls = 'profile-job-noc-search with-right-border';
        this.emptyText = this.NOCSearchFieldEmptyText;
        this.width = this.NOCSearchFieldWidth;
        this.enableKeyEvents = true;

        this.on('render', this.initAllComponents.createDelegate(this), this, {buffer: 50});
        this.on('render', this.onNOCSearchFieldRender.createDelegate(this), this, {buffer: 500});
        this.on('keyup', this.onNOCSearchFieldKeyUp.createDelegate(this), this);
    },

    // private
    initAllComponents: function () {
        var thisRef = this;

        var mainColumnId = Ext.id();
        var savedSearchesColumnId = Ext.id();
        var savedSearchesButtonId = Ext.id();

        var nocReader = new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            id: 'code'
        }, [
            {name: 'code', mapping: 'noc_code'},
            {name: 'title', mapping: 'noc_job_title'},
            {name: 'noc_job_and_code'}
        ]);

        this.NOCSearchFieldStore = new Ext.data.Store({
            booDataLoaded: false,
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/qnr/index/search',
                method: 'post'
            }),

            reader: nocReader
        });

        this.languageEnglishRadio = new Ext.form.Radio({
            boxLabel: _('English'),
            name: this.id + '_noc_language',
            checked: true,
            listeners: {
                check: function (radio, booChecked) {
                    if (booChecked) {
                        thisRef.NOCSearchFieldStore.reload();
                    }
                }
            }
        });
        this.languageFrenchRadio = new Ext.form.Radio({
            boxLabel: _('French'),
            name: this.id + '_noc_language',
            listeners: {
                check: function (radio, booChecked) {
                    if (booChecked) {
                        thisRef.NOCSearchFieldStore.reload();
                    }
                }
            }
        });

        var statusBar = new Ext.ux.StatusBar({
            width: 190,
            defaultText: '',
            statusAlign: 'right',
            items: [
                this.languageEnglishRadio,
                this.languageFrenchRadio
            ]
        });

        var pagingBar = new Ext.PagingToolbar({
            pageSize: 10,
            store: this.NOCSearchFieldStore,
            displayInfo: false
        });

        thisRef.thisNOCSearchResultsWindowGrid = new Ext.grid.GridPanel({
            sm: new Ext.grid.RowSelectionModel({
                singleSelect: true,

                listeners: {
                    'beforerowselect': function () {
                        return false;
                    }
                }
            }),

            hidden: false,
            split: true,
            autoWidth: true,
            stripeRows: true,
            loadMask: true,
            autoScroll: true,
            cls: 'extjs-grid no-cell-borders-grid',
            autoExpandColumn: mainColumnId,

            store: this.NOCSearchFieldStore,
            bbar: new Ext.Toolbar({
                style: 'magin-bottom: 0',
                items: [statusBar, '->', pagingBar],
            }),

            hideHeaders: true,
            cm: new Ext.grid.ColumnModel({
                columns: [
                    {
                        id: mainColumnId,
                        dataIndex: '',
                        header: '',
                        renderer: function (val, p, record) {
                            return thisRef.NOCSearchFieldRecordRenderer(val, p, record);
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
                    thisRef.NOCSearchFieldOnRecordClick(record);
                    thisRef.thisNOCSearchResultsWindow.hideWithEffect();
                }
            }
        });

        this.NOCSearchFieldStore.on('beforeload', function (store, options) {
            store.isLoading = true;
            options.params = options.params || {};

            // New variables
            var params = {};
            params[thisRef.NOCSearchFieldParamName] = thisRef.getValue();
            params['search_noc'] = thisRef.NOCSearchFieldParamNameIsNOC;
            params['lang'] = thisRef.languageEnglishRadio.getValue() ? 'en' : 'fr';

            Ext.apply(options.params, params);
        });

        this.NOCSearchFieldStore.on('load', function (store) {
            store.isLoading = false;
            store.booDataLoaded = true;
            thisRef.thisNOCSearchResultsWindow.syncShadow();
        });

        this.NOCSearchFieldStore.on('loadexception', function (store) {
            store.isLoading = false;
            store.booDataLoaded = true;
        });

        thisRef.thisNOCSearchResultsWindow = new Ext.Window({
            layout: 'fit',
            closable: false,
            resizable: false,
            plain: true,
            autoHeight: true,
            frame: false,
            cls: 'quick-search-field-dialog quick-search-field-dialog-with-top-border',

            // Don't steal the focus, when this popup will be shown
            defaultButton: {
                getEl: Ext.emptyFn,
                focus: Ext.emptyFn
            },

            items: [
                {
                    xtype: 'container',
                    width: thisRef.NOCSearchPopupWidth,
                    autoHeight: true,
                    style: 'background-color: #F0F0F1',

                    items: [
                        thisRef.thisNOCSearchResultsWindowGrid
                    ]
                }
            ],

            listeners: {
                show: function () {
                    // Try to show this popup behind previously opened dialogs
                    thisRef.thisNOCSearchResultsWindow.setZIndex(8000);

                    thisRef.thisNOCSearchResultsWindow.alignTo(thisRef.getEl(), 'bl', [0, -2]);

                    thisRef.thisNOCSearchResultsWindow.getEl().slideIn('t', {
                        stopFx: true,
                        easing: 'easeOut',
                        duration: .2,
                        callback: function () {
                            thisRef.thisNOCSearchResultsWindow.syncShadow();
                        }
                    });
                }
            },

            hideWithEffect: function () {
                thisRef.thisNOCSearchResultsWindow.getEl().slideOut('t', {
                    easing: 'easeOut',
                    duration: .2,
                    stopFx: true,
                    callback: function () {
                        thisRef.thisNOCSearchResultsWindow.hide();
                    }
                });
            }
        });
    },

    onNOCSearchFieldRender: function (c) {
        var thisRef = this;
        var wnd = thisRef.thisNOCSearchResultsWindow;

        var openPopupIfNotOpened = function () {
            if (!wnd.isVisible()) {
                if (thisRef.getValue() !== '') {
                    wnd.show();

                    if (!thisRef.NOCSearchFieldStore.booDataLoaded) {
                        thisRef.NOCSearchFieldStore.reload();
                    }
                }
            }
        };

        var el = c.getEl();
        el.on('paste', function () {
            c.fireEvent('keyup', c);
        });

        el.on('mousedown', function () {
            openPopupIfNotOpened();
        });

        el.on('mouseover', function () {
            // Change the default empty text if no data was entered
            if (empty(thisRef.getValue())) {
                thisRef.emptyText = thisRef.NOCSearchFieldEmptyTextOnHover;
                thisRef.applyEmptyText();
                thisRef.reset();
            }

            openPopupIfNotOpened();
        });

        var timeoutId;
        var checkIsMouseOutsideDialog = function () {
            if (wnd && !$('#' + thisRef.appliedToId).is(':hover') && !$('#' + wnd.id).is(':hover') && Ext.get(document.activeElement).id !== thisRef.appliedToId) {
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
            if (empty(thisRef.getValue())) {
                thisRef.emptyText = thisRef.NOCSearchFieldEmptyText;
                thisRef.applyEmptyText();
                thisRef.reset();
            }

            if (wnd && wnd.isVisible()) {
                timeoutId = setTimeout(function () {
                    checkIsMouseOutsideDialog();
                }, 500);
            }
        });

        if (!empty(thisRef.NOCSearchFieldOnAfterRender)) {
            thisRef.NOCSearchFieldOnAfterRender();
        }
    },

    onNOCSearchFieldKeyUp: function (field, e) {
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
        }

        if (!empty(thisRef.NOCSearchFieldOnKeyUp)) {
            thisRef.NOCSearchFieldOnKeyUp();
        }

        // Show search results grid + button if there is something entered
        if (entered === '') {
            thisRef.thisNOCSearchResultsWindow.hide();
        } else {
            thisRef.thisNOCSearchResultsWindow.show();
            thisRef.thisNOCSearchResultsWindow.syncShadow();

            if (entered.length > 1) {
                var store = thisRef.NOCSearchFieldStore;
                clearTimeout(store.timeOutDelay);
                store.timeOutDelay = setTimeout(function () {
                    if (store.isLoading) {
                        var conn = store.proxy.getConnection();
                        conn.abort();
                    }

                    store.load();
                }, 500);
            }
        }
    },

    NOCSearchFieldRecordRenderer: function (val, p, record) {
        var res = '<div>' + this.highlightSearch(record.data[this.NOCSearchFieldDisplayField]) + '</div>';

        return res;
    },

    highlightSearch: function (highlightedRow) {
        var data = this.NOCSearchFieldStore.reader.jsonData;
        for (var i = 0, len = data.search.length; i < len; i++) {
            var val = data.search[i];
            highlightedRow = highlightedRow.replace(
                new RegExp('(' + preg_quote(val) + ')', 'gi'),
                "<b style='background-color: #FFFF99;'>$1</b>"
            );
        }
        return highlightedRow;
    }
}); // end of extend

// register xtype
Ext.reg('NOCSearchField', Ext.ux.NOCSearchField);
