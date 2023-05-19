var ClientTrackerGrid = function(viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);

    var baseParams = viewer.booCompanies ? {companyId: this.viewer.companyId} : {clientId: this.viewer.clientId};

    this.recordsOnPage = 1000;

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        baseParams: baseParams,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/clients/time-tracker/get-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'track_id',
            fields: [
                'track_id',
                'track_member_id',
                'track_posted_on_date',
                'track_posted_by_member_name',
                'track_time_billed_rounded',
                'track_time_billed',
                'track_time_actual',
                'track_round_up',
                'track_rate',
                'track_total',
                'track_comment',
                'track_billed',
                'ta_ids'
            ]
        })
    });
    this.store.setDefaultSort('track_id', 'DESC');
    this.store.on('load', this.showPagingToolbar, this);
    this.store.on('load', this.updateSummary, this);

    var subjectColId = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    var arrColumns = [
        sm, {
            header: _('Date'),
            width: 120,
            dataIndex: 'track_posted_on_date',
            sortable: true
        }, {
            id: subjectColId,
            header: _('Subject'),
            dataIndex: 'track_comment',
            sortable: true,
            width: 250,
            renderer: function (v) {
                return Ext.util.Format.nl2br(v);
            }
        }, {
            header: _('Hours worked'),
            width: 130,
            dataIndex: 'track_time_billed_rounded',
            sortable: true,
            renderer: function (v) {
                return Ext.util.Format.round(v / 60, 4);
            }
        }, {
            header: _('Rate/hour'),
            width: 120,
            dataIndex: 'track_rate',
            sortable: true,
            align: 'center',
            renderer: function (v) {
                return '$' + v;
            }
        }, {
            header: _('Total'),
            width: 120,
            dataIndex: 'track_total',
            sortable: true,
            align: 'center',
            renderer: function (v) {
                return '$' + v;
            }
        }, {
            header: _('Posted by'),
            width: 150,
            dataIndex: 'track_posted_by_member_name',
            sortable: true
        }, {
            header: _('Billed'),
            width: 90,
            dataIndex: 'track_billed',
            sortable: true,
            align: 'center',
            renderer: function (v) {
                return (v === 'Y') ? '<IMG src=' + baseUrl + '/images/icons/tick.png>' : '';
            }
        }
    ];

    this.cm = new Ext.grid.ColumnModel({
        columns:         arrColumns,
        defaultSortable: true,

        defaults: {
            menuDisabled: true
        }
    });

    this.pagingBar = new Ext.PagingToolbar({
        pageSize: this.recordsOnPage,
        hidden: true,
        store: this.store
    });

    this.bbar = [this.pagingBar];


    ClientTrackerGrid.superclass.constructor.call(this, {
        cls: 'time-tracker-grid',
        border: false,
        loadMask: {msg: _('Loading...')},
        sm: sm,
        stateful: true,
        stateId: 'client-tracker-grid',
        autoExpandColumn: subjectColId,
        stripeRows: true,
        viewConfig: {
            emptyText: _('There are no records to show.')
        }
    });

    this.getSelectionModel().on('selectionchange', this.viewer.ClientTrackerToolbar.updateToolbarButtons, this);
    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.on('dblclick', this.viewer.ClientTrackerToolbar.editTimeTracker, this.viewer.ClientTrackerToolbar);
};

Ext.extend(ClientTrackerGrid, Ext.grid.GridPanel, {
    updateSummary: function() {
        var clientTrackerContainer = Ext.getDom('time-tracker-result-count-' + this.viewer.clientId);
        if (!empty(clientTrackerContainer)) {
            var totalHours = !empty(this.store.reader.jsonData.totalHours) ? this.store.reader.jsonData.totalHours : 0;
            var totalRate = !empty(this.store.reader.jsonData.totalRate) ? this.store.reader.jsonData.totalRate : 0;
            var res = String.format(
                _('Total: {0} hrs, {1}'),
                Math.round(totalHours*100)/100,
                formatMoney('usd', totalRate, true)
            );
            clientTrackerContainer.innerHTML = res;
        }

        this.viewer.ClientTrackerToolbar.updateToolbarButtons();
    },

    showPagingToolbar: function() {
        if (this.store.getTotalCount() > this.recordsOnPage) {
            this.pagingBar.show();
        }
    },

    // Show context menu
    onCellRightClick: function(grid, rowIndex, cellIndex, e) {
        var rec = grid.store.getAt(rowIndex);
        var toolbar = this.viewer.ClientTrackerToolbar;

        var menu = new Ext.menu.Menu({
            items: [{
                text: '<i class="las la-plus"></i>' + _('New Time Entry'),
                hidden: !toolbar.hasAccess('add'),
                handler: toolbar.createTimeTracker.createDelegate(toolbar)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit'),
                hidden: !toolbar.hasAccess('edit'),
                handler: toolbar.editTimeTracker.createDelegate(toolbar)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                hidden: !toolbar.hasAccess('delete'),
                handler: toolbar.deleteTimeTracker.createDelegate(toolbar)
            }, {
                text: '<i class="las la-print"></i>' + _('Print'),
                handler: toolbar.printTimeTracker.createDelegate(toolbar)
            }, {
                text: '<i class="las la-check"></i>' + _('Mark as Billed'),
                disabled: rec.data['track_billed'] === 'Y',
                hidden: grid.booCompanies || is_client,
                handler: toolbar.markAsBilledTimeTracker.createDelegate(toolbar)
            }, '-', {
                text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                scope: this,
                handler: function() {
                    grid.store.reload();
                }
            }]
        });
        e.stopEvent();

        // Select row which was selected by right click
        grid.getSelectionModel().selectRow(rowIndex);

        menu.showAt(e.getXY());
    }
});

Ext.reg('appClientTrackerGrid', ClientTrackerGrid);