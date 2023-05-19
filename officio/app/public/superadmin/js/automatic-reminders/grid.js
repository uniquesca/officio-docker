var AutomaticRemindersGrid = function (config) {
    var thisGrid = this;
    Ext.apply(this, config);

    var autoExpandColumnId = Ext.id();
    this.cm = new Ext.grid.ColumnModel({
        columns: [
            {
                id: autoExpandColumnId,
                header: _('Task'),
                dataIndex: 'reminder'
            }, {
                header: _('Triggers'),
                dataIndex: 'triggers',
                width: 350
            }, {
                header: _('Conditions'),
                dataIndex: 'conditions',
                width: 350
            }, {
                header: _('Actions'),
                dataIndex: 'actions',
                width: 350
            }
        ],
        defaultSortable: true
    });

    this.TransactionRecord = Ext.data.Record.create([
        {name: 'reminder_id', type: 'int'},
        {name: 'reminder', type: 'string'},
        {name: 'active_clients_only', type: 'string'},
        {name: 'triggers', type: 'string'},
        {name: 'trigger_types'},
        {name: 'conditions', type: 'string'},
        {name: 'actions', type: 'string'}
    ]);

    var recordsOnPage = 100;
    this.store = new Ext.data.Store({
        url: baseUrl + '/automatic-reminders/get-grid',
        method: 'POST',
        autoLoad: true,
        remoteSort: false,
        baseParams: {
            start: 0,
            limit: recordsOnPage
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, this.TransactionRecord)
    });

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New Task'),
            cls: 'main-btn',
            handler: thisGrid.addAutomaticReminder.createDelegate(thisGrid)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Task'),
            ref: '../editTaskPropertiesBtn',
            disabled: true,
            handler: thisGrid.editAutomaticReminder.createDelegate(thisGrid)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Task'),
            ref: '../deleteTaskBtn',
            disabled: true,
            handler: thisGrid.deleteAutomaticReminder.createDelegate(thisGrid)
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: recordsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: _('Displaying automatic tasks {0} - {1} of {2}'),
        emptyMsg: _('No automatic tasks to display')
    });

    AutomaticRemindersGrid.superclass.constructor.call(this, {
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autoExpandColumn: autoExpandColumnId,
        stripeRows: true,
        cls: 'extjs-grid',
        loadMask: true,
        viewConfig: {
            emptyText: _('No automatic tasks found')
        }
    });

    this.on('rowdblclick', this.editAutomaticReminder.createDelegate(this));
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this), this);
};

Ext.extend(AutomaticRemindersGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function (sm) {
        var booOneSelected = (sm.getSelections().length === 1);
        this.deleteTaskBtn.setDisabled(!booOneSelected);
        this.editTaskPropertiesBtn.setDisabled(!booOneSelected);
    },

    deleteAutomaticReminder: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (!node) {
            return;
        }

        var question = String.format(
            _('Are you sure you want to delete Automatic Task <i>{0}</i>?'),
            node.data.reminder
        );

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/automatic-reminders/delete',
                    params: {
                        reminder_id: node.data.reminder_id
                    },

                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.success(_('Automatic Task <i>' + node.data.reminder + '</i> was successfully deleted.'));

                            thisGrid.store.reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_("Can't delete Automatic Task"));
                    }
                });
            }
        });
    },

    addAutomaticReminder: function () {
        var dialog = new AutomaticRemindersDialog({
            params: {
                action: 'add',
                reminder_id: 0,
                trigger_types: []
            }
        }, this);
        dialog.showDialog();
    },

    editAutomaticReminder: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (node) {
            var dialog = new AutomaticRemindersDialog({
                params: {
                    action: 'edit',
                    reminder_id: node.data.reminder_id,
                    trigger_types: node.data.trigger_types
                }
            }, thisGrid);
            dialog.showDialog();
        }
    }
});