function openTicket(ticket_id, company_id) {
    ticket({
        action: 'open',
        ticket_id: ticket_id,
        company_id: company_id
    });
}

var TicketsGrid = function(config) {
    Ext.apply(this, config);
    var thisGrid = this;

    this.booHiddenTicketsAdd = !arrTicketsAccessRights.add;
    this.booHiddenTicketsChangeStatus = !arrTicketsAccessRights.change_status;
    this.booShowToolbar = !this.booHiddenTicketsAdd || !this.booHiddenTicketsChangeStatus;

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header: '',
                align: 'center',
                width: 25,
                fixed: true,
                dataIndex: 'ticket_id',
                renderer: function (val) {
                    var title = 'Ticket';
                    return String.format('<i class="las la-file" title="{0}" onclick="openTicket({1},{2});"></i>', title, val, thisGrid.company_id);
                }
            }, {
                id: 'ticket',
                header: 'Message',
                dataIndex: 'ticket',
                width: 400,
                renderer: function (val, p, record) {
                    if (record.data['status'] == 'Not Resolved') {
                        return '<span style="color: red">' + val + '</span>';
                    }
                    return val;
                }
            }, {
                header: 'Company',
                dataIndex: 'company_name',
                width: 100,
                hidden: this.company_id != 'all'
            }, {
                header: 'Contacted by',
                dataIndex: 'contacted_by',
                width: 100
            }, {
                header: 'Status',
                dataIndex: 'status',
                width: 90
            }, {
                header: 'User',
                dataIndex: 'user',
                width: 120
            }, {
                header: 'Posted by',
                dataIndex: 'author',
                width: 120
            }, {
                header: 'Posted on',
                dataIndex: 'date',
                renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
                width: 190,
                fixed: true
            }
        ],
        defaultSortable: true
    });

    var arrTicketFields = [
        {name: 'ticket_id', type: 'int'},
        {name: 'ticket', type: 'string'},
        {name: 'company_name', type: 'string'},
        {name: 'status', type: 'string'},
        {name: 'contacted_by', type: 'string'},
        {name: 'date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'author', type: 'string'},
        {name: 'user', type: 'string'}
    ];

    var TransactionRecord = Ext.data.Record.create(arrTicketFields);

    this.store = new Ext.data.Store({
        url: baseUrl + '/tickets/get-tickets',
        autoLoad: false,
        remoteSort: true,
        baseParams: {
            company_id: this.company_id,
            start: 0,
            limit: this.ticketsOnPage
        },
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, TransactionRecord)
    });
    if (this.company_id == 'all') {
        this.store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));
    }
    this.store.on('load', this.toggleBottomBar, this);
    this.store.setDefaultSort('date', 'DESC');

    if(this.booShowToolbar) {
        this.tbar = [
            {
                text: '<i class="las la-folder-plus"></i>' + _('Add Ticket'),
                hidden: this.booHiddenTicketsAdd,
                handler: this.addTicket.createDelegate(this)
            }, {
                text: '<i class="las la-print"></i>' + _('Print'),
                handler: this.printTickets.createDelegate(this)
            }, {
                text: '<i class="las la-edit"></i>' +_('Change Status'),
                ref: '../changeTicketStatusBtn',
                hidden: true,
                handler: this.changeTicketStatus.createDelegate(this)
            }, '->', {
                id: 'refresh-tickets',
                text: '<i class="las la-undo-alt"></i>',
                handler: this.refreshList.createDelegate(this)
            }
        ];
    }

    this.bbar = new Ext.PagingToolbar({
        pageSize: this.ticketsOnPage,
        hidden: true,
        store: this.store
    });

    TicketsGrid.superclass.constructor.call(this, {
        id: 'tickets-grid-' + this.company_id,
        cls: 'extjs-grid',
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autoHeight: true,
        split: true,
        stripeRows: true,
        scroll: false,

        autoExpandColumn: 'ticket',
        autoExpandMin: 150,
        loadMask: true,
        buttonAlign: 'left',
        autoScroll: true,

        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No tickets found.',
            forceFit: true,
            style: {overflow: 'auto', overflowX: 'hidden'}
        }
    });

    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(TicketsGrid, Ext.grid.GridPanel, {
    applyParams: function(store, options) {
        options.params = options.params || {};
        var filterCompanyId = 'all';
        var filterStatus = '';
        var sortDirection = 'DESC';
        if (Ext.getCmp('tickets_filter_date')) {
            sortDirection = Ext.getCmp('tickets_filter_date').getGroupValue();
        }

        if (Ext.getCmp('tickets_filter_status')) {
            filterStatus = Ext.getCmp('tickets_filter_status').getGroupValue();
        }

        if (Ext.getCmp('tickets_filter_company') && !empty(Ext.getCmp('tickets_filter_company').getRawValue())) {
            filterCompanyId = Ext.getCmp('tickets_filter_company').getValue();
        }

        // Apply filter variables
        var params = {
            dir:           sortDirection,
            sort:          'date',
            filter_status: Ext.encode(filterStatus),
            limit:         this.ticketsOnPage,
            company_id:    filterCompanyId
        };
        Ext.apply(options.params, params);
    },

    toggleBottomBar: function() {
        if (this.store.getTotalCount() > this.ticketsOnPage) {
            this.getBottomToolbar().show();
        }
    },

    updateToolbarButtons: function() {
        var sm = this.getSelectionModel();
        if(this.booShowToolbar) {
            // Ticket buttons
            this.changeTicketStatusBtn.setVisible(!this.booHiddenTicketsChangeStatus);
        }
    },

    getSelections: function() {
        var sel = this.getSelectionModel().getSelections();
        if (sel) {
            var tickets = [];
            for (var i = 0; i < sel.length; i++) {
                tickets.push(sel[i].data.ticket_id);
            }

            return tickets;
        }

        return [];
    },

    addTicket: function(){
        ticket({
            action: 'add',
            company_id: this.company_id
        });
    },

    changeTicketStatus: function(){
        var records = this.getSelections();
        if (records.length === 0 || records.length > 1) {
            Ext.simpleConfirmation.msg('Info', 'Please select one ticket to change status');
            return;
        }
        ticket({
            action: 'change_status',
            ticket_id: records[0],
            company_id: this.company_id
        });
    },

    printTickets: function() {
        print($('#' + this.getId() + ' .x-grid3').html(), 'File Tickets');
    },

    refreshList: function(){
        this.store.reload();
    },

    onCellRightClick: function(grid, index, cellIndex, e) {
        var rec = grid.store.getAt(index);

        var menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            items: [
                {
                    text: '<i class="las la-folder-plus"></i>' + _('Add Ticket'),
                    hidden: this.booHiddenTicketsAdd,
                    handler: this.addTicket.createDelegate(this)
                }, {
                    text: '<i class="las la-edit"></i>' +_('Change Status'),
                    hidden: this.booHiddenTicketsChangeStatus,
                    handler: this.changeTicketStatus.createDelegate(this)
                }, '-', {
                    text: '<i class="las la-print"></i>' + _('Print'),
                    handler: this.printTickets.createDelegate(this)
                }, {
                    text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    handler: this.refreshList.createDelegate(this)
                }
            ]
        });
        e.stopEvent();

        // Select row which was selected by right click
        grid.getSelectionModel().selectRow(index);

        menu.showAt(e.getXY());
    }
});

