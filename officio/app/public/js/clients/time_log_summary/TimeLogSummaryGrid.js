var TimeLogSummaryGrid = function (config, viewer) {
    this.viewer = viewer;
    Ext.apply(this, config);

    this.recordsOnPage = 1000;

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        baseParams: {},

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/clients/time-tracker/time-log-summary-load'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'track_id',
            fields: [
                'track_id',
                'track_client_id',
                'track_member_id',
                'track_client_name',
                'track_case_file_number',
                'track_posted_on_date',
                {name: 'track_posted_on', type: 'date', dateFormat: Date.patterns.ISO8601Short},
                'track_posted_by_member_name',
                'track_posted_by_member_email',
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
    this.store.setDefaultSort('track_client_name', 'ASC');
    this.store.on('load', this.showPagingToolbar, this);

    var subjectColId = Ext.id();
    var arrColumns = [
        {
            id: subjectColId,
            header: _('User Name'),
            dataIndex: 'track_posted_by_member_name',
            sortable: true
        }, {
            header: _('User Email'),
            width: 200,
            dataIndex: 'track_posted_by_member_email',
            sortable: true
        }, {
            header: _('Client Name'),
            width: 200,
            dataIndex: 'track_client_name',
            sortable: true,
            renderer: function (val, p, record) {
                return String.format(
                    "<a href='#' class='blklink open_client_tab' onclick='return false;'>{0}</a>",
                    val
                );
            }
        }, {
            header: _('Case File Number'),
            width: 200,
            dataIndex: 'track_case_file_number',
            sortable: true,
            renderer: function (val, p, record) {
                return String.format(
                    "<a href='#' class='blklink open_client_tab' onclick='return false;'>{0}</a>",
                    val
                );
            }
        }, {
            header: _('Date'),
            width: 120,
            dataIndex: 'track_posted_on',
            sortable: true,
            renderer: function (val, i, rec) {
                return rec.data['track_posted_on_date'];
            }
        }, {
            header: _('Hours worked'),
            width: 200,
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
            header: _('Billed'),
            width: 90,
            dataIndex: 'track_billed',
            sortable: true,
            align: 'center',
            renderer: function (v) {
                return (v === 'Y') ? '<img src="' + baseUrl + '/images/icons/tick.png" />' : '';
            }
        }
    ];

    this.cm = new Ext.grid.ColumnModel({
        columns: arrColumns,
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


    TimeLogSummaryGrid.superclass.constructor.call(this, {
        cls: 'time-tracker-grid',
        border: false,
        loadMask: {msg: _('Loading...')},
        stateful: true,
        stateId: 'client-time-log-summary-grid',
        autoExpandColumn: subjectColId,
        stripeRows: true,
        viewConfig: {
            deferEmptyText: _('There are no records to show.'),
            emptyText: _('There are no records to show.')
        }
    });

    this.on('cellcontextmenu', this.onCellRightClick.createDelegate(this));
    this.on('cellclick', this.onCellClick.createDelegate(this));
};

Ext.extend(TimeLogSummaryGrid, Ext.grid.GridPanel, {
    showPagingToolbar: function () {
        if (this.store.getTotalCount() > this.recordsOnPage) {
            this.pagingBar.show();
        }
    },

    // Show context menu
    onCellRightClick: function (grid, rowIndex, cellIndex, e) {
        e.stopEvent();
    },

    exportTimeLogSummary: function () {
        var thisGrid = this;

        //get visible columns
        var cm = [];
        var cmModel = thisGrid.getColumnModel().config;
        // @NOTE: we skip first column because it is a checkbox
        for (var i = 1; i < cmModel.length; i++) {
            if (!cmModel[i].hidden) {
                cm.push({id: cmModel[i].dataIndex, name: cmModel[i].header, width: cmModel[i].width});
            }
        }

        // Prepare all params (fields + sort info)
        var store = thisGrid.getStore();
        var allParams = store.baseParams;
        var arrSortInfo = {
            'sort': store.sortInfo.field,
            'dir': store.sortInfo.direction
        };
        Ext.apply(allParams, arrSortInfo);

        var oAllParams = {
            cm: Ext.encode(cm)
        };

        for (i in allParams) {
            if (allParams.hasOwnProperty(i)) {
                oAllParams[i] = allParams[i];
            }
        }

        submit_hidden_form(topBaseUrl + '/clients/time-tracker/time-log-summary-export', oAllParams);
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var thisGrid = this;
        if ($(e.getTarget()).hasClass('open_client_tab')) {
            e.stopEvent();

            var rec = grid.getStore().getAt(rowIndex);

            setUrlHash(String.format(
                '#applicants/{0}/cases/{1}/case_details',
                rec.data.track_client_id,
                rec.data.track_member_id
            ));
            setActivePage();
        }
    }
});