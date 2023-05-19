var ZohoKeysGrid = function (config) {
    Ext.apply(this, config);

    var defaultForm = Ext.data.Record.create([
        {name: 'zoho_key'},
        {name: 'zoho_key_status'}
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/zoho/get-keys-list',
        remoteSort: true,
        sortInfo: {field: 'zoho_key', direction: 'ASC'},
        autoLoad: true,

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
            {
                id: 'zoho_key',
                root: 'rows',
                totalProperty: 'totalCount'
            }, defaultForm
        )
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();

    this.bbar = new Ext.PagingToolbar({
        hidden: false,
        pageSize: keys_perpage,
        store: this.store,
        displayInfo: true,
        displayMsg: 'Keys {0} - {1} of {2}',
        emptyMsg: 'No keys to display',
        items: [
            '<div style="padding-left: 50px; font-weight: bold; color: red;">' +
                '<i class="las la-exclamation-triangle" style="padding-right: 5px;"></i>' +
                _('Important: please be sure that there is at least one enabled key!') +
            '</div>'
        ]
    });

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('Add Key'),
            tooltip: 'Add a new key',
            scope: this,
            handler: this.addZohoKey.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Key'),
            ref: '../editKeyBtn',
            disabled: true,
            tooltip: 'Edit selected key',
            scope: this,
            handler: this.editZohoKey.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Key'),
            ref: '../deleteKeyBtn',
            disabled: true,
            tooltip: 'Remove the selected key',
            handler: this.deleteZohoKey.createDelegate(this)
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            tooltip: 'Click to refresh list of keys',
            scope: this,
            handler: function () {
                this.store.reload();
            }
        }
    ];

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.sm,
            {
                id: 'default-zoho-keys-grid-column',
                header: 'Key',
                width: 250,
                dataIndex: 'zoho_key'
            }, {
                header: 'Status',
                width: 20,
                align: 'center',
                dataIndex: 'zoho_key_status',
                renderer: function (val) {
                    return String.format(
                        '<img src="' + baseUrl + '/images/icons/{0}" title="{1}" alt="{1}" />',
                        val === 'enabled' ? 'tick.png' : 'cancel.png',
                        val === 'enabled' ? 'Enabled' : 'Disabled'
                    );
                }
            }
        ],
        defaultSortable: true
    });


    ZohoKeysGrid.superclass.constructor.call(this, {
        height: 560,
        loadMask: true,
        autoExpandColumn: 'default-zoho-keys-grid-column',
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText: 'No keys found.',
            forceFit: true
        }
    });

    this.on('rowcontextmenu', this.showContextMenu, this);
    this.on('rowdblclick', this.editZohoKey.createDelegate(this));
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(ZohoKeysGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function (sm) {
        var sel = sm.getSelections();

        this.editKeyBtn.setDisabled(sel.length != 1);
        this.deleteKeyBtn.setDisabled(sel.length == 0);
    },

    addZohoKey: function () {
        var dialog = new ZohoKeysDialog({
            title: '<i class="las la-plus"></i>' + _('Add Zoho Key'),
            parentGrid: this
        }, null);
        dialog.show();
        dialog.center();
    },

    editZohoKey: function () {
        var sel = this.getSelectionModel().getSelected();
        var dialog = new ZohoKeysDialog({
            title: '<i class="las la-edit"></i>' + _('Edit Zoho Key'),
            parentGrid: this
        }, sel);
        dialog.show();
        dialog.center();
    },

    deleteZohoKey: function () {
        var grid = this;

        Ext.Msg.confirm('Please confirm', 'Are you sure want to delete this key(s)?', function (btn) {
            if (btn == 'yes') {
                grid.getEl().mask('Deleting...');

                var arrKeys = [];
                for (var i = 0; i < grid.getSelectionModel().getSelections().length; i++) {
                    arrKeys.push(grid.getSelectionModel().getSelections()[i].data.zoho_key);
                }

                Ext.Ajax.request({
                    url: baseUrl + '/zoho/delete-keys',
                    params: {
                        arrKeys: Ext.encode(arrKeys)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            grid.store.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                        grid.getEl().unmask();
                    },

                    failure: function () {
                        grid.getEl().unmask();
                        Ext.simpleConfirmation.error('Cannot delete key(s). Please try again later.');
                    }
                });
            }
        });
    },

    showContextMenu: function (grid, rowIndex, e) {
        this.menu = null;
        this.menu = new Ext.menu.Menu({
            items: [{
                text: '<i class="las la-plus"></i>' + _('Add Key'),
                scope: this,
                handler: grid.addZohoKey.createDelegate(grid)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit Key'),
                scope: this,
                handler: grid.editZohoKey.createDelegate(grid)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Key'),
                scope: this,
                handler: grid.deleteZohoKey.createDelegate(grid)
            }, '-', {
                text: '<i class="las la-redo-alt"></i>' + _('Refresh'),
                scope: this,
                handler: function () {
                    this.store.reload();
                }
            }]
        });

        //Mark row as selected
        grid.getView().grid.getSelectionModel().selectRow(rowIndex);

        // Show menu
        e.stopEvent();
        this.menu.showAt(e.getXY());
    }
});