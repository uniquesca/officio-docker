var MailSettingsTokensDialog = function (owner) {
    this.owner = owner;

    var thisDialog = this;
    var mainColumnId = Ext.id();

    var oTokenRecord = Ext.data.Record.create([
        {name: 'token_id', type: 'int'},
        {name: 'token_provider', type: 'string'},
        {name: 'token_email', type: 'string'},
        {name: 'token_type', type: 'string'}
    ]);

    var sm = new Ext.grid.CheckboxSelectionModel();
    sm.on('selectionchange', this.updateToolbarButtons.createDelegate(this));
    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm,
            {
                id: mainColumnId,
                header: _('Email'),
                dataIndex: 'token_email'
            }, {
                header: _('Provider'),
                dataIndex: 'token_provider',
                width: 110
            }, {
                header: _('Type'),
                dataIndex: 'token_type',
                width: 90
            }
        ],
        defaultSortable: true
    });

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: false,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/mailer/settings/get-tokens'
        }),

        baseParams: {
            member_id: Ext.encode(curr_member_id)
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, oTokenRecord),

        listeners: {
            load: function (store, records) {
                if (empty(records.length)) {
                    // Reload the list of accounts -> so, the 'manage tokens' button will be hidden automatically
                    thisDialog.owner.store.reload();
                }
            }
        }
    });
    this.store.setDefaultSort('token_email', 'ASC');

    this.tokensGrid = new Ext.grid.GridPanel({
        store: this.store,
        sm: sm,
        cm: cm,

        tbar: [
            {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                disabled: true,
                ref: '../toolbarButtonDelete',
                handler: thisDialog.deleteTokens.createDelegate(this)
            }
        ],

        width: 600,
        height: 300,
        split: true,
        stripeRows: true,
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        autoExpandColumn: mainColumnId,
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            emptyText: _('There are no records to show.')
        }
    });

    MailSettingsTokensDialog.superclass.constructor.call(this, {
        title: '<i class="las la-key"></i>' + _('Manage oAuth Tokens'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        y: 10,
        items: this.tokensGrid,
        buttons: [
            {
                text: _('Close'),
                cls: 'orange-btn',
                handler: function () {
                    thisDialog.close();
                }
            }
        ]
    });
};

Ext.extend(MailSettingsTokensDialog, Ext.Window, {
    updateToolbarButtons: function () {
        var sel = this.tokensGrid.getSelectionModel().getSelections();
        var booIsSelectedAtLeastOne = sel.length >= 1;

        this.tokensGrid.toolbarButtonDelete.setDisabled(!booIsSelectedAtLeastOne);
    },

    deleteTokens: function () {
        var thisDialog = this;
        var thisGrid = thisDialog.tokensGrid;
        var arrSelectedRecords = thisGrid.getSelectionModel().getSelections();

        var question = '';
        if (empty(arrSelectedRecords.length)) {
            Ext.simpleConfirmation.error(_('Please select at least one record to delete'));
            return;
        } else if (arrSelectedRecords.length > 1) {
            question = String.format(
                _('Are you sure you want to delete these {0} selected records?'),
                arrSelectedRecords.length
            );
        } else {
            question = String.format(
                _('Are you sure you want to delete <i>{0} ({1})</i>?'),
                arrSelectedRecords[0].data['token_email'],
                arrSelectedRecords[0].data['token_type']
            );
        }

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                var arrRecordsIds = [];
                for (var i = 0; i < arrSelectedRecords.length; i++) {
                    arrRecordsIds.push(arrSelectedRecords[i].data['token_id']);
                }

                thisDialog.getEl().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: topBaseUrl + '/mailer/settings/delete-tokens',

                    params: {
                        tokens: Ext.encode(arrRecordsIds),
                        member_id: Ext.encode(curr_member_id)
                    },

                    success: function (res) {
                        thisDialog.getEl().mask(_('Done!'));
                        setTimeout(function () {
                            thisDialog.getEl().unmask();
                            thisGrid.store.reload();
                        }, 750);
                    },

                    failure: function (response) {
                        thisDialog.getEl().unmask();

                        var errorMessage = _('Internal error. Please try again later.');
                        try {
                            var resultData = Ext.decode(response.responseText);
                            if (resultData && resultData.message) {
                                errorMessage = resultData.message;
                            }
                        } catch (e) {
                        }

                        Ext.simpleConfirmation.error(errorMessage);
                    }
                });
            }
        });
    }
});