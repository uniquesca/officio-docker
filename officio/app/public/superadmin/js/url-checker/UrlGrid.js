var UrlGrid = function() {

    this.UrlRecord = Ext.data.Record.create([
        {name: 'id'},
        {name: 'url'},
        {name: 'url_description'},
        {name: 'assigned_form_id', type: 'int'},
        {name: 'status'},
        {name: 'hash'},
        {name: 'new_hash'},
        {name: 'error_message'},
        {name: 'updated', type: 'date', dateFormat: Date.patterns.ISO8601Long}
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/url-checker/get-list',
        remoteSort: false,
        autoLoad: true,

        baseParams: {
            start: 0,
            limit: 100000
        },

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
        {
            root: 'rows',
            totalProperty: 'totalCount'
        }, this.UrlRecord),

        listeners: {
            load: function(store, records) {
                var grid = Ext.getCmp('url_grid');
                var checkAllBtn = grid.checkButton.menu.items.item(0);
                checkAllBtn.setDisabled(records.length === 0);
                grid.updateHashButton.setDisabled(true);
            }
        }
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();

    UrlGrid.superclass.constructor.call(this, {
        id: 'url_grid',
        region: 'center',
        renderTo: 'url-checker-content',
        cls: 'extjs-grid',
        height: getSuperadminPanelHeight(),
        columns: [
            this.sm,
            {
                header: 'Open',
                width: 40,
                align: 'center',
                sortable: false,
                dataIndex: 'id',
                renderer: this.formatUrl.createDelegate(this)
            }, {
                id:'url',
                header: 'Url',
                width: 160,
                sortable: true,
                dataIndex: 'url'
            }, {
                header: 'Last Checked',
                width: 130,
                sortable: true,
                dataIndex: 'updated',
                align: 'center',
                renderer: Ext.util.Format.dateRenderer(Date.patterns.ISO8601Long)
            }, {
                header: 'Status',
                width: 75,
                sortable: true,
                dataIndex: 'status',
                align: 'center',
                renderer: this.formatStatus.createDelegate(this)
            }
        ],
        stripeRows: true,
        autoExpandColumn: 'url',
        autoWidth: true,
        loadMask: true,
        viewConfig: { deferEmptyText: 'No urls found.', emptyText: 'No urls found.' },

        // config options for stateful behavior
        stateful: true,
        stateId: 'url_grid',

        tbar: [{
            text: '<i class="las la-plus"></i>' + _('Add Url'),
            handler: this.addUrl.createDelegate(this)
        },{
            text: '<i class="las la-edit"></i>' + _('Edit Url'),
            disabled: true,
            ref: '../editButton',
            handler: this.editUrl.createDelegate(this)
        },{
            text: '<i class="las la-trash"></i>' + _('Delete Url(s)'),
            disabled: true,
            ref: '../deleteButton',
            handler: this.deleteUrl.createDelegate(this)
        }, '->', {
            text: '<i class="las la-save"></i>' + _('Update Hash'),
            disabled: true,
            ref: '../updateHashButton',
            handler: this.updateHash.createDelegate(this)
        }, {
            text: '<i class="las la-search"></i>' + _('Check'),
            ref: '../checkButton',
            menu: [
                {
                    text: 'Check All',
                    ref: '../checkButtonAll',
                    disabled: true,
                    handler: this.runScan.createDelegate(this, [true])
                }, {
                    text: 'Check Selected',
                    ref: '../checkButtonSelected',
                    disabled: true,
                    handler: this.runScan.createDelegate(this, [false])
                }
            ]

        }],

        // paging bar on the bottom
        bbar: new Ext.PagingToolbar({
            pageSize: 100500,
            store: this.store,
            displayInfo: true,
            displayMsg: 'Displaying urls {0} - {1} of {2}',
            emptyMsg: "No urls to display"
        })
    });

    this.on('rowcontextmenu', this.onContextClick, this);
    this.on('rowdblclick', this.editUrl, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(UrlGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function() {
        var grid = this.view.grid;
        var sel = grid.getSelectionModel().getSelections();
        var booIsSelectedOneMail = sel.length == 1;
        var booIsSelectedAtLeastOneMail = sel.length >= 1;

        grid.editButton.setDisabled(!booIsSelectedOneMail);
        grid.deleteButton.setDisabled(!booIsSelectedAtLeastOneMail);

        var checkSelectedBtn = grid.checkButton.menu.items.item(1);
        checkSelectedBtn.setDisabled(!booIsSelectedAtLeastOneMail);
    },

    // Show context menu
    onContextClick: function(grid, index, e) {
        e.stopEvent();
    },

    formatUrl: function(url, p, record) {
        return String.format('<a href="{0}" target="_blank"><i class="las la-link"></i></a>', record.data.url, baseUrl);
    },

    formatStatus: function(status, p, record) {
        var color = 'green';
        switch(status) {
            case 'error':
                color = 'orange';
                status = 'Error: ' + record.data.error_message;
                break;

            case 'not_checked':
                color = 'gray';
                status = 'Not checked';
                break;

            case 'changed':
                color = 'red';
                status = 'Changed';
                break;

            case 'ok':
            default:
                status = 'Ok';
        }
        
        return String.format('<span style="color: {0};">{1}</span>', color, status);
    },


    addUrl: function() {
        var grid = this.view.grid;
        var editWnd = new UrlEdit({
            'id':              0,
            'url':             '',
            'url_description': '',
            'assigned_form_id': 0
        }, grid.getStore().reader.jsonData.arrForms);
        editWnd.show();
    },

    editUrl: function() {
        var grid = this.view.grid;
        var sel = grid.getSelectionModel().getSelected();
        var editWnd = new UrlEdit(sel.data, grid.getStore().reader.jsonData.arrForms);
        editWnd.show();
    },

    deleteUrl: function() {
        var grid = this.view.grid;
        Ext.Msg.confirm('Please confirm', 'Delete selected url(s)?', function(btn) {
            if (btn == 'yes') {
                var sel = grid.getSelectionModel().getSelections();
                var arrRecords = [];
                for(var i = 0; i< sel.length; i++) {
                    arrRecords.push(sel[i].data.id);
                }

                Ext.Ajax.request({
                    url: baseUrl + '/url-checker/delete',
                    params: {
                        arr_urls: Ext.encode(arrRecords)
                    },

                    success: function(result, request) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            // Update records
                            grid.store.reload();

                            Ext.simpleConfirmation.info('Selected records were deleted successfully.');
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function() {
                        Ext.simpleConfirmation.error('An error occurred during delete. Please try again later.');
                    }
                });
            }
        });
    },

    updateHash: function() {
        var grid = this.view.grid;
        var arrRecords = [];
        grid.store.each(function(rec) {
            if(rec.data.hash != rec.data.new_hash) {
                arrRecords.push(rec.data);
            }
        });

        Ext.Ajax.request({
            url: baseUrl + '/url-checker/update-hash',
            params: {
                arr_urls: Ext.encode(arrRecords)
            },

            success: function(result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    // Update records
                    grid.store.reload();
                    Ext.simpleConfirmation.info('Hashes were updated successfully.');
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },
            
            failure: function() {
                Ext.simpleConfirmation.error('An error occurred during saving. Please try again later.');
            }
        });
    },

    runScan: function(booAll) {
        var grid = this.view.grid;
        var arrRecords = [];
        if(booAll) {
            grid.store.each(function(rec) {
                arrRecords.push(rec.data);
            });
        } else {
            var sel = grid.getSelectionModel().getSelections();
            for(var i = 0; i< sel.length; i++) {
                arrRecords.push(sel[i].data);
            }
        }

        if (empty(arrRecords.length)) {
            Ext.simpleConfirmation.warning('No urls to process.');
        } else {
            var checkDialog = new MassCheckDialog(arrRecords, grid);
            checkDialog.show();
        }
    }
});