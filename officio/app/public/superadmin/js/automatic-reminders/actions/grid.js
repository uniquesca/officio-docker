var AutomaticRemindersActionsGrid = function(config, owner) {
    var thisGrid = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.store = new Ext.data.Store({
        url: baseUrl + '/automatic-reminder-actions/get-grid',
        baseParams: {
            reminder_id: thisGrid.owner.params.reminder_id,
            action_ids: Ext.encode([])
        },
        autoLoad: false,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'action_id'},
            {name: 'action_text'}
        ]))
    });

    var genId           = Ext.id();
    var actionTextColId = Ext.id();
    AutomaticRemindersActionsGrid.superclass.constructor.call(this, {
        id: genId,
        autoExpandColumn: actionTextColId,
        colModel: new Ext.grid.ColumnModel([
            {
                id: actionTextColId,
                dataIndex: 'action_text',
                sortable: true
            }
        ]),

        viewConfig: {
            deferEmptyText: false,
            emptyText:      _('No actions found.'),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 20,

            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth) {
                    // no scroller
                    this.scrollOffset = 2;
                } else {
                    // there is a scroller
                    this.scrollOffset = this.orgScrollOffset;
                }

                this.fitColumns(false);
            }
        },

        sm: new Ext.grid.RowSelectionModel({singleSelect:true}),
        tbar:  [
            {
                text: '<i class="las la-plus"></i>' + _('Add'),
                handler: thisGrid.addAutomaticReminderAction.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit'),
                ref: '../editActionBtn',
                disabled: true,
                handler: thisGrid.editAutomaticReminderAction.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                ref: '../deleteActionBtn',
                disabled: true,
                handler: thisGrid.deleteAutomaticReminderAction.createDelegate(thisGrid)
            }
        ]
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
    this.on('rowdblclick', this.editAutomaticReminderAction.createDelegate(this));

    // Hide header, for some reason hideHeaders: true fails...
    this.on('render', function (grid) {
        grid.getView().el.select('.x-grid3-header').setStyle('display', 'none');
    });
};

Ext.extend(AutomaticRemindersActionsGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function() {
        var sel = this.getSelectionModel().getSelections();
        var booIsSelectedOnlyOne = sel.length == 1;

        this.editActionBtn.setDisabled(!booIsSelectedOnlyOne);
        this.deleteActionBtn.setDisabled(!booIsSelectedOnlyOne);
    },

    addAutomaticReminderAction: function () {
        var thisGrid = this;
        var dialog = new AutomaticRemindersActionsDialog({
            params: {
                action: 'add',
                reminder_id: thisGrid.owner.params.reminder_id
            }
        }, thisGrid.owner);
        dialog.showDialog();
    },

    editAutomaticReminderAction: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (node) {
            var dialog = new AutomaticRemindersActionsDialog({
                params: {
                    action: 'edit',
                    reminder_id: thisGrid.owner.params.reminder_id,
                    action_id: node.data.action_id
                }
            }, thisGrid.owner);
            dialog.showDialog();
        }
    },

    deleteAutomaticReminderAction: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (!node) {
            return;
        }

        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete this action?'), function (btn) {
            if (btn == 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + "/automatic-reminder-actions/delete",
                    params: {
                        reminder_id: thisGrid.owner.params.reminder_id,
                        action_id: node.data.action_id
                    },

                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);

                        if (!resultData.success) {
                            thisGrid.getEl().unmask();
                            Ext.simpleConfirmation.error(resultData.error);
                        } else {
                            thisGrid.getEl().mask('Deleted!');
                            setTimeout(function () {
                                thisGrid.getEl().unmask();
                            }, 750);

                            if (empty(thisGrid.owner.params.reminder_id)) {
                                var arrNewActionIds = [];
                                Ext.each(thisGrid.owner.newActionIds, function (actionIdToCheck) {
                                    if (actionIdToCheck != node.data.action_id) {
                                        arrNewActionIds.push(actionIdToCheck);
                                    }
                                });

                                thisGrid.owner.newActionIds               = arrNewActionIds;
                                thisGrid.getStore().baseParams.action_ids = Ext.encode(thisGrid.owner.newActionIds);
                            }

                            thisGrid.getStore().reload();

                            if (!empty(thisGrid.owner.params.reminder_id)) {
                                thisGrid.owner.owner.getStore().reload();
                            }
                        }

                    },

                    failure: function () {
                        thisGrid.getEl().unmask();
                        Ext.simpleConfirmation.error(_("Can't delete action"));
                    }
                });
            }
        });
    }
});