var AccessLogsGrid = function (config) {
    var thisGrid = this;
    Ext.apply(this, config);

    var logRecord = Ext.data.Record.create([
        {name: 'log_id'},
        {name: 'log_user'},
        {name: 'log_client'},
        {name: 'log_description'},
        {name: 'log_ip'},
        {name: 'log_created_on', type: 'date', dateFormat: Date.patterns.ISO8601Long}
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/access-logs/list',
        remoteSort: true,
        sortInfo: {
            field: 'log_created_on',
            direction: 'DESC'
        },
        autoLoad: false,

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
            {
                id: 'log_id',
                root: 'rows',
                totalProperty: 'totalCount'
            }, logRecord
        )
    });
    this.store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));
    this.store.on('load', thisGrid.checkLoadedResult.createDelegate(thisGrid));

    this.bbar = new Ext.PagingToolbar({
        hidden: false,
        pageSize: arrSettings['logs_per_page'],
        store: this.store,
        displayInfo: true,
        displayMsg: 'Records {0} - {1} of {2}',
        emptyMsg: 'No records to display'
    });

    this.tbar = [
        {
            text: '<i class="las la-file-excel"></i>' + _('Export Events to Excel (CSV)'),
            tooltip: _('Export all currently filtered log records.'),
            ref: '../logRecordsExportBtn',
            disabled: true,
            hidden: !arrSettings['export'],
            handler: this.exportRecordsToExcel.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Event'),
            tooltip: _('Delete selected log records.'),
            ref: '../logRecordsDeleteBtn',
            disabled: true,
            hidden: !arrSettings['delete'],
            handler: this.deleteRecords.createDelegate(this)
        }, '->', {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            scope: this,
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
                header: _('User'),
                width: 250,
                fixed: true,
                dataIndex: 'log_user',
                renderer: thisGrid.renderColumnWithTooltip
            }, {
                id: descriptionColumnId,
                header: _('Event Description'),
                width: 250,
                dataIndex: 'log_description',
                renderer: thisGrid.renderColumnWithTooltip
            }, {
                header: _('Client/Case'),
                width: 250,
                fixed: true,
                sortable: false,
                dataIndex: 'log_client',
                renderer: thisGrid.renderColumnWithTooltip
            }, {
                header: _('IP'),
                width: 150,
                fixed: true,
                hidden: true, // Don't show by default
                dataIndex: 'log_ip'
            }, {
                header: _('Date'),
                width: 200,
                fixed: true,
                renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
                dataIndex: 'log_created_on'
            }
        ],
        defaultSortable: true
    });

    AccessLogsGrid.superclass.constructor.call(this, {
        id: 'log_grid',
        height: 560,
        loadMask: true,
        cls: 'extjs-grid',

        autoExpandColumn: descriptionColumnId,
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText: '<span style="font-size: 18px">' + _('Select your filter and click Search.') + '</span>',
            forceFit: true
        }
    });

    this.on('rowcontextmenu', this.showContextMenu, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(AccessLogsGrid, Ext.grid.GridPanel, {
    renderColumnWithTooltip: function (val) {
        return String.format(
            "<div ext:qtip='{0}' ext:qwidth='450' style='cursor: help;'>{1}</div>",
            val.replaceAll("'", "&#39;"),
            val
        );
    },

    checkLoadedResult: function () {
        // Remove the default empty text, use this one if no records are found
        this.getView().emptyText = _('No records found.');
        this.getView().applyEmptyText();

        var booDisable = this.store.getCount() < 1;
        this.logRecordsExportBtn.setDisabled(booDisable);
    },

    getAllFilteringParams: function () {
        var store = this.getStore();
        var date_filter = Ext.getCmp('log_filter_date').getValue();
        var date_from_val = '';
        var date_to_val = '';

        if (date_filter == 'from_to') {
            date_from_val = Ext.get('log_filter_date_from').getValue();
            var date_from = new Date();
            if (!empty(date_from_val)) {
                date_from = Date.parseDate(date_from_val, dateFormatFull);
                date_from_val = date_from.format(dateFormatShort);
            }

            date_to_val = Ext.get('log_filter_date_to').getValue();
            var date_to = new Date();
            if (!empty(date_to_val)) {
                date_to = Date.parseDate(date_to_val, dateFormatFull);
                date_to_val = date_to.format(dateFormatShort);
            }
        }

        // Apply filter variables
        var params = {
            dir: store.sortInfo.direction,
            sort: store.sortInfo.field,
            filter_date_by: Ext.encode(date_filter),
            filter_date_from: Ext.encode(date_from_val),
            filter_date_to: Ext.encode(date_to_val),
            filter_company: Ext.encode(Ext.getCmp('log_filter_company').getValue()),
            filter_type: Ext.encode(Ext.getCmp('log_filter_type').getValue()),
            filter_user: Ext.encode(Ext.getCmp('log_filter_users').getValue()),
            filter_case: Ext.encode(Ext.getCmp('log_filter_cases').getValue())
        };

        return params;
    },

    applyParams: function (store, options) {
        options.params = options.params || {};

        Ext.apply(options.params, this.getAllFilteringParams());
    },

    updateToolbarButtons: function () {
        var sel = this.view.grid.getSelectionModel().getSelections();

        this.logRecordsDeleteBtn.setDisabled(sel.length == 0);
    },

    showContextMenu: function (grid, rowIndex, e) {
        this.menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            items: [
                {
                    text: '<i class="las la-file-excel"></i>' + _('Export all filtered events to Excel (CSV)'),
                    scope: this,
                    hidden: !arrSettings['export'],
                    handler: grid.exportRecordsToExcel.createDelegate(grid)
                }, {
                    text: '<i class="las la-trash"></i>' + _('Delete'),
                    scope: this,
                    hidden: !arrSettings['delete'],
                    handler: grid.deleteRecords.createDelegate(grid)
                }, {
                    text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    scope: this,
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

    deleteRecords: function () {
        var thisGrid = this;
        Ext.Msg.confirm(_('Please confirm'), _('Are you sure want to delete selected log record(s)?'), function (btn) {
            if (btn == 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                var ids = [],
                    arrSelected = thisGrid.getSelectionModel().getSelections();

                for (var i = 0; i < arrSelected.length; i++) {
                    ids.push(arrSelected[i].data.log_id);
                }

                Ext.Ajax.request({
                    url: baseUrl + '/access-logs/delete',
                    params: {
                        ids: Ext.encode(ids)
                    },
                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.store.reload();
                            Ext.simpleConfirmation.msg(_('Info'), resultData.message, 1500);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                        thisGrid.getEl().unmask();
                    },

                    failure: function () {
                        thisGrid.getEl().unmask();
                        Ext.simpleConfirmation.error(_('Cannot delete log record(s). Please try again later.'));
                    }
                });
            }
        });
    },

    exportRecordsToExcel: function () {
        var oParams = this.getAllFilteringParams();
        oParams.type = 'export_to_csv';
        submit_hidden_form(baseUrl + '/access-logs/list', oParams);
    }
});