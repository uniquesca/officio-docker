var ProspectsTodayGrid = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        data: [],
        reader: new Ext.data.JsonReader(
            {id: 0},
            Ext.data.Record.create([
                {name: 'itemId'},
                {name: 'itemName'},
                {name: 'itemUnreadCount', type: 'int'}
            ])
        )
    });

    var subjectColId = Ext.id();
    this.columns = [
        {
            id: subjectColId,
            header: _('Name'),
            dataIndex: 'itemName',
            sortable: true,
            width: 300,
            renderer: function(val, a, row) {
                var title = row.data.itemName;

                // Show unread count
                if (row.data.itemUnreadCount > 0) {
                    title += ' (' + row.data.itemUnreadCount + ' unread)';
                }

                if(row.data.itemId === 'invited') {
                    title += String.format(
                        "<i class='las la-info-circle' ext:qtip='{0}' ext:qwidth='520' style='cursor: help; margin-left: 5px; padding-right: 0; vertical-align: text-bottom'></i>",
                        _('Contains Prospects that have specifically chosen you to respond to them. Pay special attention to this group.')
                    );
                }

                return String.format(
                    '<a href="#" class="blulinkun norightclick" onclick="return false;" />{0}</a>',
                    title
                );
            }
        }
    ];

    ProspectsTodayGrid.superclass.constructor.call(this, {
        cls: 'no-borders-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        viewConfig: {
            emptyText: _('There are no items to show.'),
            scrollOffset: 0
        }
    });

    this.on('render', this.onAfterRender.createDelegate(this));
    this.getSelectionModel().on('rowselect', this.onRowSelection.createDelegate(this));
};

Ext.extend(ProspectsTodayGrid, Ext.grid.GridPanel, {
    selectActiveFilter: function (activeFilter) {
        var grid = this;
        var store = grid.getStore();
        var index = store.find('itemId', activeFilter);
        if (index >= 0) {
            setTimeout(function (grid, index) {
                grid.getSelectionModel().selectRow(index, false, false);
            }, 100, grid, index);
        }
    },

    onAfterRender: function () {
        // Automatically load list of items
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');

        var arrItems = [];
        var defaultTab = 'all-prospects';
        Ext.each(tabPanel.arrProspectNavigationTabs, function (oItem) {
            if (oItem.booIsTodayItem) {
                arrItems.push(oItem);

                if (oItem.booIsDefaultItem) {
                    defaultTab = oItem.itemId;
                }
            }
        });
        this.store.loadData(arrItems);

        this.selectActiveFilter(empty(this.initSettings.tab) ? defaultTab : this.initSettings.tab);

        this.refreshUnreadCount();
    },

    onRowSelection: function (sm, index, record) {
        // Clear search query from base params
        var grid = this;
        var mainGrid = Ext.getCmp(grid.panelType + '-grid');
        if (mainGrid) {
            var prospectsStore = mainGrid.getStore();
            prospectsStore.setBaseParam('filter');
        }

        var tabPanel = Ext.getCmp(grid.panelType + '-tab-panel');
        tabPanel.loadProspectsTab({tab: record.data.itemId});
    },

    refreshUnreadCount: function (type) {
        var todayGrid = this;
        var arrData   = todayGrid.getStore().data.items;
        var types     = [];

        type = type === 'all-prospects' ? '' : type;
        arrData.forEach(function (elem) {
            if (empty(type) || elem.data.itemId === 'all-prospects' || elem.data.itemId === type) {
                types.push(elem.data.itemId);
            }
        });

        Ext.Ajax.request({
            url:    baseUrl + '/prospects/index/get-prospects-unread-counts',
            params: {
                panelType: this.panelType,
                types:     Ext.encode(types)
            },

            success: function (f) {
                var result = Ext.decode(f.responseText);
                if (result.success) {
                    arrData.forEach(function (elem) {
                        if (result.counts.hasOwnProperty(elem.data.itemId)) {
                            elem.data.itemUnreadCount = result.counts[elem.data.itemId];
                        }
                    });

                    todayGrid.getView().refresh();
                }
            }
        });
    }
});