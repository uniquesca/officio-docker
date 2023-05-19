function showHistory(taId, defaultFilter) {
    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header: "Activity",
                id: 'action',
                dataIndex: 'action',
                renderer: function (value) {
                    return "<p style='white-space:normal'>" + value + "</p>";
                }
            },
            {
                header: "User",
                dataIndex: 'user',
                width: 140
            },
            {
                header: "Date of Event",
                dataIndex: 'date_of_event',
                width: 130
            }
        ],
        defaultSortable: true
    });

    var store = new Ext.data.Store({
        url: baseUrl + '/trust-account/history',
        baseParams: {ta_id: taId},
        autoLoad: true,

        reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, Ext.data.Record.create([
                {name: 'history_id'},
                {name: 'action_id'},
                {name: 'action'},
                {name: 'user'},
                {name: 'date_of_event'},
                {name: 'dt_start'},
                {name: 'dt_end'}
            ])
        )
    });

    store.on('load', function () {
        if (!empty(defaultFilter)) {
            var idx = filter.store.findExact('action_id', defaultFilter);
            if (idx !== -1) {
                var record = filter.store.getAt(idx);
                filter.fireEvent('select', filter, record);
            }
        } else {
            toggleBbar();
        }
    }, this);

    var grid = new Ext.grid.GridPanel({
        store: store,
        cm: cm,
        collapsible: true,
        animCollapse: false,
        split: true,
        stripeRows: true,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autoExpandColumn: 'action',
        loadMask: true,
        autoScroll: true,
        autoWidth: true,
        height: 275,
        cls: 'extjs-grid',
        viewConfig: {emptyText: 'No logs found.'},

        listeners: {
            celldblclick: function () {
                var sel = this.getSelectionModel().getSelected();
                if (sel.json.action_id == 2) {
                    win.close();

                    if (typeof window['FtaSetFilter'] !== 'undefined') {
                        FtaSetFilterAndApply({
                            filter: 'period',
                            start_date: sel.json.dt_start,
                            end_date: sel.json.dt_end
                        }, taId);
                    }
                }
            }
        }
    });

    var filter = new Ext.form.ComboBox({
        store: new Ext.data.SimpleStore({
            fields: ['action_id', 'action_name'],

            data: [
                [0, 'All Actions'],
                [1, 'Assigned transactions'],
                [2, 'Imported transactions'],
                [3, 'Send Receipt of Payment'],
                [4, 'Updated transactions'],
                [5, 'Unassigned transactions'],
                [6, 'Reconciliation Reports']
            ]
        }),

        mode: 'local',
        displayField: 'action_name',
        valueField: 'action_id',
        triggerAction: 'all',
        hideLabel: true,
        editable: false,
        value: empty(defaultFilter) ? 'Filter by Action Type' : defaultFilter,

        listeners: {
            select: function (combo, record) {
                if (getComboBoxIndex(filter) === -1) {
                    return;
                }

                if (empty(record.data.action_id)) {
                    if (grid.store.isFiltered()) {
                        grid.store.clearFilter();
                    }
                } else {
                    grid.store.filter('action_id', record.data.action_id);
                }

                toggleBbar();
            }
        }
    });

    var toggleBbar = function () {
        var arrRecords = grid.store.collect('action_id');
        win.bbar.setVisible(arrRecords.has(2));
    }

    var pan = new Ext.FormPanel({
        layout: 'form',
        border: false,
        bodyStyle: 'padding: 5px 5px 20px 5px;',
        items: [filter, grid]
    });

    var win = new Ext.Window({
        title: '<i class="las la-history"></i>' + _('Import & Change History'),
        modal: true,
        width: 950,
        autoHeight: true,
        border: false,
        plain: true,
        items: pan,
        resizable: false,

        bbar: new Ext.Toolbar({
            cls: 'no-bbar-borders',
            items: {
                xtype: 'label',
                text: 'To view a list of imported records, double click on "Imported bank record" Activity item'
            }
        }),

        buttons: [
            {
                text: 'Close',
                cls: 'orange-btn',
                handler: function () {
                    win.close();
                }
            }
        ]
    });

    win.show();
    win.center();
}