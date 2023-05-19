var ManageVACsGrid = function () {
    var thisGrid = this;

    // Init basic properties
    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-vac/get-list',
        method: 'POST',
        autoLoad: true,
        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'client_vac_id',

            fields: [
                {
                    name: 'client_vac_id',
                    type: 'int'
                }, {
                    name: 'client_vac_country',
                    type: 'string'
                }, {
                    name: 'client_vac_city',
                    type: 'string'
                }, {
                    name: 'client_vac_link',
                    type: 'string'
                }, {
                    name: 'client_vac_order',
                    type: 'int'
                }, {
                    name: 'client_vac_deleted',
                    type: 'string'
                }
            ]
        }),

        sortInfo: {
            field: 'client_vac_order',
            direction: 'DESC'
        }
    });

    var action = new Ext.ux.grid.RowActions({
        header: _('Order'),
        keepSelection: true,
        width: 30,
        autoWidth: false,

        actions: [
            {
                iconCls: 'move_option_up',
                tooltip: _('Move Up')
            }, {
                iconCls: 'move_option_down',
                tooltip: _('Move Down')
            }
        ],

        callbacks: {
            'move_option_up': this.moveOption.createDelegate(this),
            'move_option_down': this.moveOption.createDelegate(this)
        }
    });

    var expandCol = Ext.id();

    this.selModel = new Ext.grid.RowSelectionModel({
        singleSelect: true
    });

    this.columns = [
        {
            id: expandCol,
            header: _('City'),
            sortable: false,
            dataIndex: 'client_vac_city',
            width: 150,
            renderer: this.isDeletedRenderer
        }, {
            id: expandCol,
            header: _('Country or Province'),
            sortable: false,
            dataIndex: 'client_vac_country',
            width: 150,
            renderer: this.isDeletedRenderer
        }, {
            header: _('Link'),
            sortable: false,
            dataIndex: 'client_vac_link',
            width: 150,
            renderer: this.isDeletedRenderer
        }
    ];

    this.columns.push(action);

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New VAC'),
            cls: 'main-btn',
            ref: '../createVACBtn',
            handler: this.addVAC.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit VAC'),
            disabled: true,
            ref: '../editVACBtn',
            handler: this.editVAC.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete VAC'),
            disabled: true,
            ref: '../deleteVACBtn',
            handler: this.deleteVAC.createDelegate(this)
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            tooltip: _('Reload the list'),
            handler: this.refreshList.createDelegate(this)
        }
    ];


    this.bbar = new Ext.PagingToolbar({
        pageSize: 100500,
        store: this.store,
        displayInfo: true,
        emptyMsg: _('No records to display')
    });


    ManageVACsGrid.superclass.constructor.call(this, {
        autoWidth: true,
        height: getSuperadminPanelHeight(),
        autoExpandColumn: expandCol,
        stripeRows: true,
        cls: 'extjs-grid',
        renderTo: 'vac_list_container',
        plugins: [action],
        loadMask: true,

        viewConfig: {
            forceFit: true,
            emptyText: _('No records were found.')
        }
    });

    this.getSelectionModel().on('selectionchange', this.onSelectionChange.createDelegate(this));
    this.on('rowdblclick', this.editVAC.createDelegate(this), this);
    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.on('contextmenu', function (e) {
        e.preventDefault();
    }, this);
};

Ext.extend(ManageVACsGrid, Ext.grid.GridPanel, {
    isDeletedRenderer: function (val, p, record) {
        return String.format(
            '<span style="{0}" {1}>{2}</span>',
            record.data.client_vac_deleted === 'Y' ? 'text-decoration: line-through red;' : '',
            record.data.client_vac_deleted === 'Y' ? 'title="' + _('This VAC is marked as deleted because there is/was case with this VAC assigned to') + '"' : '',
            val
        );
    },

    onSelectionChange: function (sm) {
        var selRecordsCount = sm.getCount();
        var booOneSelected = selRecordsCount === 1;

        this.editVACBtn.setDisabled(!booOneSelected);
        this.deleteVACBtn.setDisabled(!booOneSelected);
    },

    addVAC: function () {
        var wnd = new ManageVACDialog({
            params: {
                VAC: {
                    client_vac_id: 0,
                    client_vac_country: '',
                    client_vac_city: '',
                    client_vac_link: ''
                }
            }
        }, this);
        wnd.showDialog();
    },

    editVAC: function () {
        var record = this.selModel.getSelected();

        var wnd = new ManageVACDialog({
            params: {
                VAC: {
                    client_vac_id: record.data.client_vac_id,
                    client_vac_country: record.data.client_vac_country,
                    client_vac_city: record.data.client_vac_city,
                    client_vac_link: record.data.client_vac_link
                }
            }
        }, this);
        wnd.showDialog();
    },

    setFocusToVAC: function (client_vac_id) {
        var index = this.getStore().find('client_vac_id', client_vac_id);
        if (index >= 0) {
            // Select found VAC
            this.getSelectionModel().selectRow(index);

            // Scroll to just selected VAC - so it will be visible
            var rowEl = this.getView().getRow(index);
            rowEl.scrollIntoView(this.getGridEl(), false);
        }
    },

    reloadGridAndSelectVAC: function (client_vac_id) {
        var thisGrid = this;
        thisGrid.getStore().on('load', this.setFocusToVAC.createDelegate(thisGrid, [client_vac_id]), this, {single: true});
        thisGrid.getStore().load();

        if (typeof window.top.refreshSettings === 'function') {
            window.top.refreshSettings('visa_office');
        }
    },

    moveOption: function (grid, record, action, row) {
        // Don't try to move the first/last record
        if ((action === 'move_option_up' && row === 0) || (action === 'move_option_down' && row === grid.getStore().getCount() - 1)) {
            return;
        }

        var thisGrid = this;

        thisGrid.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/manage-vac/move-record',

            params: {
                client_vac_id: Ext.encode(record.data.client_vac_id),
                direction_up: Ext.encode(action === 'move_option_up')
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisGrid.reloadGridAndSelectVAC(record.data.client_vac_id);
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                }
                thisGrid.getEl().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                thisGrid.getEl().unmask();
            }
        });
    },

    deleteVAC: function () {
        var thisGrid = this;
        var record = thisGrid.selModel.getSelected();
        var question = String.format(
            _('Are you sure want to delete "<i>{0}</i>"?'),
            record.data.client_vac_city,
        );

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/manage-vac/delete-record',

                    params: {
                        client_vac_id: Ext.encode(record.data.client_vac_id)
                    },

                    success: function (f) {
                        thisGrid.getEl().unmask();

                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            thisGrid.store.reload();

                            if (typeof window.top.refreshSettings === 'function') {
                                window.top.refreshSettings('visa_office');
                            }

                            Ext.simpleConfirmation.info(result.message);
                        } else {
                            Ext.simpleConfirmation.error(result.message);
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot delete. Please try again later.'));
                        thisGrid.getEl().unmask();
                    }
                });
            }
        });
    },

    refreshList: function () {
        this.store.reload();
    },

    onCellRightClick: function (grid, index, cellIndex, e) {
        var menu = new Ext.menu.Menu({
            items: [
                {
                    text: '<i class="las la-edit"></i>' + _('Edit'),
                    handler: this.editVAC.createDelegate(grid)
                }, {
                    text: '<i class="las la-trash"></i>' + _('Delete'),
                    handler: this.deleteVAC.createDelegate(grid)
                }, '-', {
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
