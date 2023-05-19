var ApplicantsSearchFavoriteGrid = function(config, owner) {
    var thisGrid = this;
    this.owner = owner;
    Ext.apply(this, config);

    // Use/show "All Saved Searches" by default
    this.booLoadFavoritesOnly = 0;

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/search/get-saved-searches'
        }),

        baseParams: {
            search_type: Ext.encode(config.panelType),
            favorites: this.booLoadFavoritesOnly
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
                'search_default'
            ]
        }),

        listeners: {
            'load': function () {
                // This fix is needed when we refresh the list from another tab
                try {
                    thisGrid.syncSize();
                } catch (e) {
                }
            },

            'beforeload': function (store, options) {
                options.params = options.params || {};

                var params = {
                    favorites: thisGrid.booLoadFavoritesOnly
                };

                Ext.apply(options.params, params);
            }
        }
    });

    var subjectColId = Ext.id();
    this.columns = [
        {
            id: subjectColId,
            header: _('Name'),
            dataIndex: 'search_name',
            sortable: true,
            width: 300,
            renderer: function(val) {
                return String.format(
                    '<a href="#" onclick="return false;" title="{0}" />{0}</a>',
                    val
                );
            }
        }
    ];

    var genId = Ext.id();
    ApplicantsSearchFavoriteGrid.superclass.constructor.call(this, {
        id: genId,
        cls: 'no-borders-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        viewConfig: {
            emptyText: _('There are no records to show.'),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 18,
            onLayout: function(){
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth)
                {
                  // no scroller
                  this.scrollOffset = 2;
                } else {
                  // there is a scroller
                  this.scrollOffset = this.orgScrollOffset;
                }
                this.fitColumns(false);
            }

        }
    });

    this.on('cellclick', this.onCellClick, this);
    this.on('rowcontextmenu', this.onRowContextMenu, this);
};

Ext.extend(ApplicantsSearchFavoriteGrid, Ext.grid.GridPanel, {
    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var rec = grid.getStore().getAt(rowIndex);
        if (rec) {
            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            tabPanel.openQuickAdvancedSearchTab(rec.data.search_id, rec.data.search_name);
        }
    },

    // Show context menu
    onRowContextMenu: function (grid, rowIndex, e) {
        e.stopEvent();

        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        var rec = grid.getStore().getAt(rowIndex);
        if (rec && rec.data.search_type != 'system') {
            var contextMenu = new Ext.menu.Menu({
                cls: 'no-icon-menu',
                enableScrolling: false,
                items: [
                    {
                        text: '<i class="las la-pen"></i>' + _('Edit Saved Search'),
                        handler: function () {
                            grid.getSelectionModel().selectRow(rowIndex);
                            tabPanel.openAdvancedSearchTab(rec.data.search_id, rec.data.search_name);
                        }
                    }
                ]
            });

            contextMenu.showAt(e.getXY());
        }
    }
});
