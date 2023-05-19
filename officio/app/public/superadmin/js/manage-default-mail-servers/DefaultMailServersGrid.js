var DefaultMailServersGrid = function (config) {
    Ext.apply(this, config);

    var recordsOnPage = 25;
    var thisGrid      = this;

    var sm = new Ext.grid.CheckboxSelectionModel({
        listeners: {
            selectionchange: function () {
                var sel = thisGrid.getSelectionModel().getSelections();

                var booSelected    = sel.length > 0;
                var booSelectedOne = sel.length === 1;

                Ext.getCmp('default-mail-servers-button-edit').setDisabled(!booSelectedOne);
                Ext.getCmp('default-mail-servers-button-delete').setDisabled(!booSelected);
            }
        }
    });

    var cm = new Ext.grid.ColumnModel({
        columns:         [
            sm, {
                header:    _('Name'),
                dataIndex: 'name'
            }, {
                header:    _('Type'),
                dataIndex: 'type'
            }, {
                header:    _('Host'),
                dataIndex: 'host'
            }, {
                header:    _('Port'),
                dataIndex: 'port'
            }, {
                header:    _('Encryption mode'),
                dataIndex: 'ssl'
            }
        ],
        defaultSortable: true
    });

    var store = new Ext.data.Store({
        url: baseUrl + '/manage-default-mail-servers/get-list/',

        baseParams: {
            start: 0,
            limit: recordsOnPage
        },

        autoLoad:   true,
        remoteSort: true,
        reader:     new Ext.data.JsonReader({
            root:          'results',
            totalProperty: 'count'
        }, Ext.data.Record.create([
            {name: 'id'}, {name: 'name'}, {name: 'type'}, {name: 'host'}, {name: 'port'}, {name: 'ssl'}
        ]))
    });

    var pagingBar = new Ext.PagingToolbar({
        pageSize:    recordsOnPage,
        store:       store,
        displayInfo: true,
        displayMsg:  'Records {0} - {1} of {2}',
        emptyMsg:    'No records to display'
    });

    DefaultMailServersGrid.superclass.constructor.call(this, {
        store:      store,
        cm:         cm,
        sm:         sm,
        autoHeight: true,
        split:      true,
        stripeRows: true,
        loadMask:   true,
        autoScroll: true,
        cls:        'extjs-grid',
        viewConfig: {
            emptyText: _('No servers found.'),
            forceFit:  true
        },

        bbar: pagingBar,
        tbar: [
            {
                text:    '<i class="las la-plus"></i>' + _('Add'),
                handler: this.addMailServer.createDelegate(this)
            }, {
                text:     '<i class="las la-edit"></i>' + _('Edit'),
                id:       'default-mail-servers-button-edit',
                disabled: true,
                handler:  this.editMailServer.createDelegate(this)
            }, {
                text:     '<i class="las la-trash"></i>' + _('Delete'),
                id:       'default-mail-servers-button-delete',
                disabled: true,
                handler:  this.deleteMailServer.createDelegate(this)
            }
        ]
    });
};

Ext.extend(DefaultMailServersGrid, Ext.grid.GridPanel, {
    addMailServer: function () {
        var dialog = new DefaultMailServerDialog({
            title:      _('Add default mail server'),
            parentGrid: this
        }, null);

        dialog.show();
        dialog.center();
    },

    editMailServer: function () {
        var sel    = this.getSelectionModel().getSelections();
        var dialog = new DefaultMailServerDialog({
            title:      _('Update default mail server'),
            parentGrid: this
        }, sel[0].data);

        dialog.show();
        dialog.center();
    },

    deleteMailServer: function () {
        var grid = this;
        var sel  = grid.getSelectionModel().getSelections();

        if (empty(sel.length)) {
            return false;
        }

        var msg = sel.length === 1 ? 'Are you sure you want to delete this mail server?' : _('Are you sure you want to delete these mail servers?');
        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                var ids = [];
                for (var i = 0; i < sel.length; i++) {
                    ids.push(sel[i].data.id);
                }

                Ext.Ajax.request({
                    url:    baseUrl + '/manage-default-mail-servers/delete/',
                    params: {
                        ids: Ext.encode(ids)
                    },

                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultData = Ext.decode(result.responseText);

                        if (resultData.success) {
                            grid.store.reload();
                            Ext.simpleConfirmation.msg(_('Info'), _('Mail server(s) was successfully deleted'));
                        } else Ext.simpleConfirmation.error(resultData.message);
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Can\'t delete. Please try again later.'));
                    }
                });
            }
        });
    }
});
