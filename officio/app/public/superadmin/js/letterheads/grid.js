function letterheadsGrid() {
    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header: 'Name',
                dataIndex: 'name',
                align: 'left',
                width: 250
            }, {
                header: 'Type',
                dataIndex: 'type',
                fixed: true,
                width: 200
            }, {
                header: 'Date',
                dataIndex: 'date',
                width: 200,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull)
            }, {
                header: 'Created by',
                dataIndex: 'created_by',
                width: 200
            }
        ],
        defaultSortable: true
    });

    // this could be inline, but we want to define the Transaction record
    // type so we can add records dynamically
    var TransactionRecord = Ext.data.Record.create([
        {name: 'letterhead_id'},
        {name: 'name'},
        {name: 'date', type: 'date', dateFormat: dateFormatFull},
        {name: 'type'},
        {name: 'created_by'}
    ]);

    var params = {
        withoutOther: true,
        template_for: ''
    };

    var store = new Ext.data.Store({
        url: topBaseUrl + '/documents/index/get-letterheads-list',
        autoLoad: true,
        baseParams: params,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, TransactionRecord),
        listeners: { load: function () {
            Ext.getBody().unmask();
        } }
    });

    var getSelections = function() {
        var sel = Ext.getCmp('letterheads-grid').getSelectionModel().getSelections();
        if (sel) {
            var tickets = [];
            for (var i = 0; i < sel.length; i++) {
                tickets.push(sel[i].data.letterhead_id);
            }

            return tickets;
        }

        return [];
    };

    var trustSummaryTBar = [
        new Ext.Toolbar.Button({
            text: '<i class="las la-plus"></i>' + _('New Letterhead'),
            id: 'btn-add-letterhead',
            cls: 'no-icon-menu',
            handler: function () {
                letterhead({
                    action: 'add'
                });
            }
        }), new Ext.Toolbar.Button({
            text: '<i class="las la-book-open"></i>' + _('Open Letterhead'),
            id: 'btn-open-letterhead',
            menu: {
                cls: 'no-icon-menu',
                showSeparator: false,
                items: [
                    {
                        text: 'Open first page image',
                        handler: function () {
                            var records = getSelections();
                            if (records.length === 0 || records.length > 1) {
                                Ext.simpleConfirmation.msg('Info', 'Please select one letterhead to open file');
                                return;
                            }
                            openLetterheadFile(records[0], 1);
                        }
                    }, {
                        text: 'Open subsequent pages image',
                        handler: function () {
                            var records = getSelections();
                            if (records.length === 0 || records.length > 1) {
                                Ext.simpleConfirmation.msg('Info', 'Please select one letterhead to open file');
                                return;
                             }
                            openLetterheadFile(records[0], 2);
                        }
                    }
                ]
            },
            cls: 'no-icon-menu'
        }), new Ext.Toolbar.Button({
            text: '<i class="las la-edit"></i>' + _('Edit Letterhead'),
            id: 'btn-edit-letterhead',
            cls: 'no-icon-menu',
            handler: function () {
                var records = getSelections();
                if (records.length === 0 || records.length > 1) {
                    Ext.simpleConfirmation.msg('Info', 'Please select one letterhead to edit');
                    return;
                }

                letterhead({
                    action: 'edit',
                    letterhead_id: records[0]
                });
            }
        }), new Ext.Toolbar.Button({
            text: '<i class="las la-trash"></i>' + _('Delete Letterhead'),
            id: 'btn-delete-letterhead',
            cls: 'no-icon-menu',
            handler: function () {
                var records = getSelections();
                if (records.length === 0 || records.length > 1) {
                    Ext.simpleConfirmation.msg('Info', 'Please select one letterhead to delete');
                    return;
                }

                letterhead({
                    action: 'delete',
                    letterhead_id: records[0]
                });
            }
        }), '->', new Ext.Toolbar.Button({
            text: '<i class="las la-undo-alt"></i>',
            handler: function () {
                refreshList();
            }
        })
    ];

    // create the editor grid
    new Ext.grid.GridPanel({
        id: 'letterheads-grid',
        renderTo: 'letterheads_container',
        store: store,
        cls: 'extjs-grid',
        cm: cm,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autowidth: true,
        height: getSuperadminPanelHeight(),
        buttonAlign: 'left',
        viewConfig: {deferEmptyText: 'No entries found.', emptyText: 'No entries found.' },
        loadMask: true,
        tbar: trustSummaryTBar,
        listeners: {
            render: function () {
            }
        }
    });
}

Ext.onReady(function() {
    //Ext.QuickTips.init();
    $('#letterheads_container').css('min-height', getSuperadminPanelHeight() + 'px');
    letterheadsGrid();
    updateSuperadminIFrameHeight('#letterheads_container');
});