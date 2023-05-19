var DefaultAnalyticsGrid = function (config) {
    var thisGrid = this;
    Ext.apply(this, config);

    var analyticsRecord = Ext.data.Record.create([
        {name: 'analytics_id'},
        {
            name:     'analytics_name',
            sortType: function (s) {
                // Custom "natural" sorting - so the same as it is on the php side
                return String(s).toLowerCase();
            }
        },
        {name: 'analytics_params'}
    ]);

    this.store = new Ext.data.Store({
        url:        baseUrl + '/manage-default-analytics/get-default-analytics',
        remoteSort: false,
        autoLoad:   true,

        sortInfo: {
            field:     'analytics_name',
            direction: 'ASC'
        },

        reader: new Ext.data.JsonReader(
            {
                id:            'analytics_id',
                root:          'rows',
                totalProperty: 'totalCount'
            }, analyticsRecord
        )
    });
    this.store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));

    this.tbar = [
        {
            text:    '<i class="las la-plus"></i>' + _('Add'),
            tooltip: 'Add a new analytics record',
            handler: this.addAnalyticsRecord.createDelegate(this)
        }, {
            text:     '<i class="las la-edit"></i>' + _('Edit'),
            tooltip:  'Edit selected analytics record',
            disabled: true,
            ref:      '../analyticsEditBtn',
            handler:  this.editAnalyticsRecord.createDelegate(this)
        }, {
            text:     '<i class="las la-trash"></i>' + _('Delete'),
            tooltip:  'Delete selected analytics records',
            disabled: true,
            ref:      '../analyticsRecordsDeleteBtn',
            handler:  this.deleteRecords.createDelegate(this)
        }, '->', {
            text:     '<i class="las la-undo-alt"></i>',
            scope:   this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.sm = new Ext.grid.CheckboxSelectionModel();

    var descriptionColumnId = Ext.id();

    this.cm = new Ext.grid.ColumnModel({

        columns: [
            this.sm,
            {
                id:        descriptionColumnId,
                header:    'Title',
                width:     250,
                dataIndex: 'analytics_name'
            }
        ],

        defaultSortable: true
    });

    DefaultAnalyticsGrid.superclass.constructor.call(this, {
        loadMask: true,
        cls:      'extjs-grid',

        autoExpandColumn: descriptionColumnId,
        autoExpandMin:    250,
        stripeRows:       true,

        viewConfig: {
            deferEmptyText: false,
            emptyText:      'No records found.',
            forceFit:       true
        }
    });

    this.on('rowdblclick', this.editAnalyticsRecord.createDelegate(this));
    this.on('rowcontextmenu', this.showContextMenu, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(DefaultAnalyticsGrid, Ext.grid.GridPanel, {
    applyParams: function (store, options) {
        // Apply filter variables
        var params = {
            analytics_type: this.analytics_type
        };

        Ext.apply(options.params, params);
    },

    updateToolbarButtons: function () {
        var sel = this.view.grid.getSelectionModel().getSelections();

        this.analyticsEditBtn.setDisabled(sel.length !== 1);
        this.analyticsRecordsDeleteBtn.setDisabled(empty(sel.length));
    },

    showContextMenu: function (grid, rowIndex, e) {
        this.menu = new Ext.menu.Menu({
            items: [
                {
                    text:    '<i class="las la-plus"></i>' + _('Add'),
                    scope:   this,
                    handler: grid.addAnalyticsRecord.createDelegate(grid)
                }, {
                    text:    '<i class="las la-edit"></i>' + _('Edit'),
                    scope:   this,
                    handler: grid.editAnalyticsRecord.createDelegate(grid)
                }, {
                    text:    '<i class="las la-trash"></i>' + _('Delete'),
                    scope:   this,
                    handler: grid.deleteRecords.createDelegate(grid)
                }, {
                    text:    '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    scope:   this,
                    handler: function () {
                        grid.store.reload();
                    }
                }
            ]
        });

        //Mark row as selected
        grid.getView().grid.getSelectionModel().selectRow(rowIndex);

        // Show menu
        e.stopEvent();
        this.menu.showAt(e.getXY());
    },

    addAnalyticsRecord: function () {
        var wnd = new DefaultAnalyticsDialog({
            analytics_record: {},
            analytics_type:   this.analytics_type
        }, this);

        wnd.showDialog();
    },

    editAnalyticsRecord: function () {
        var sel = this.getSelectionModel().getSelected();
        var wnd = new DefaultAnalyticsDialog({
            analytics_record: sel.data,
            analytics_type:   this.analytics_type
        }, this);

        wnd.showDialog();
    },

    deleteRecords: function () {
        var thisGrid = this;
        Ext.Msg.confirm('Please confirm', 'Are you sure want to delete selected record(s)?', function (btn) {
            if (btn === 'yes') {
                thisGrid.getEl().mask('Deleting...');

                var ids         = [],
                    arrSelected = thisGrid.getSelectionModel().getSelections();

                for (var i = 0; i < arrSelected.length; i++) {
                    ids.push(arrSelected[i].data.analytics_id);
                }

                Ext.Ajax.request({
                    url: baseUrl + '/manage-default-analytics/delete',

                    params: {
                        analytics_ids:  Ext.encode(ids),
                        analytics_type: Ext.encode(thisGrid.analytics_type)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.store.reload();

                            var msg = empty(resultData.message) ? 'Done!' : resultData.message;
                            Ext.simpleConfirmation.msg('Info', msg, 1500);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                        thisGrid.getEl().unmask();
                    },

                    failure: function () {
                        thisGrid.getEl().unmask();
                        Ext.simpleConfirmation.error('Cannot delete selected record(s). Please try again later.');
                    }
                });
            }
        });
    }
});