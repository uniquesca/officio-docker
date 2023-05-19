var AutomaticRemindersTriggersGrid = function(config, owner) {
    var thisGrid = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.store = new Ext.data.Store({
        url: baseUrl + '/automatic-reminder-triggers/get-grid',
        baseParams: {
            reminder_id: thisGrid.owner.params.reminder_id,
            trigger_ids: Ext.encode([])
        },
        autoLoad: false,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'trigger_id'},
            {name: 'trigger_type_id'},
            {name: 'trigger_text'}
        ]))
    });

    this.store.on('load', this.checkLoadedData.createDelegate(this));

    var genId            = Ext.id();
    var triggerTextColId = Ext.id();
    AutomaticRemindersTriggersGrid.superclass.constructor.call(this, {
        id: genId,
        autoExpandColumn: triggerTextColId,
        colModel: new Ext.grid.ColumnModel([
            {
                id: triggerTextColId,
                dataIndex: 'trigger_text',
                sortable: true
            }
        ]),

        viewConfig: {
            deferEmptyText: false,
            emptyText:      _('No triggers found.'),

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
                handler: thisGrid.addAutomaticReminderTrigger.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit'),
                ref: '../editTriggerBtn',
                disabled: true,
                handler: thisGrid.editAutomaticReminderTrigger.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                ref: '../deleteTriggerBtn',
                disabled: true,
                handler: thisGrid.deleteAutomaticReminderTrigger.createDelegate(thisGrid)
            }
        ]
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
    this.on('rowdblclick', this.editAutomaticReminderTrigger.createDelegate(this));

    // Hide header, for some reason hideHeaders: true fails...
    this.on('render', function (grid) {
        grid.getView().el.select('.x-grid3-header').setStyle('display', 'none');
    });
};

Ext.extend(AutomaticRemindersTriggersGrid, Ext.grid.GridPanel, {
    checkLoadedData: function(store) {
        this.owner.booShowChangedFieldCondition = store.reader.jsonData && store.reader.jsonData.booShowChangedFieldCondition;
    },

    updateToolbarButtons: function() {
        var sel = this.getSelectionModel().getSelections();
        var booIsSelectedOnlyOne = sel.length == 1;

        this.editTriggerBtn.setDisabled(!booIsSelectedOnlyOne);
        this.deleteTriggerBtn.setDisabled(!booIsSelectedOnlyOne);
    },

    addAutomaticReminderTrigger: function () {
        var thisGrid = this;
        var dialog = new AutomaticRemindersTriggersDialog({
            params: {
                trigger_id: 0,
                reminder_id: thisGrid.owner.params.reminder_id
            }
        }, thisGrid.owner);
        dialog.showDialog();
    },

    editAutomaticReminderTrigger: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (node) {
            var dialog = new AutomaticRemindersTriggersDialog({
                params: {
                    reminder_id: thisGrid.owner.params.reminder_id,
                    trigger_id: node.data.trigger_id,
                    trigger_type_id: node.data.trigger_type_id
                }
            }, thisGrid.owner);
            dialog.showDialog();
        }
    },

    deleteAutomaticReminderTrigger: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (!node) {
            return;
        }

        var message = 'Are you sure you want to delete this trigger?';
        if (node.data.trigger_type_id == '8' && thisGrid.getStore().reader.jsonData.booLastFieldValueChangedTrigger &&
            thisGrid.owner.booHasChangedFieldConditions) {
            message += ' All "Changed field is" conditions will be deleted.'
        }
        Ext.Msg.confirm(_('Please confirm'), _(message), function (btn) {
            if (btn == 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + "/automatic-reminder-triggers/delete",
                    params: {
                        reminder_id: thisGrid.owner.params.reminder_id,
                        trigger_id: node.data.trigger_id
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
                                var arrNewTriggerIds = [];
                                Ext.each(thisGrid.owner.newTriggerIds, function (triggerIdToCheck) {
                                    if (triggerIdToCheck != node.data.trigger_id) {
                                        arrNewTriggerIds.push(triggerIdToCheck);
                                    }
                                });

                                thisGrid.owner.newTriggerIds = arrNewTriggerIds;
                                thisGrid.getStore().baseParams.trigger_ids = Ext.encode(thisGrid.owner.newTriggerIds);
                            }

                            thisGrid.getStore().reload();
                            thisGrid.owner.conditionsGrid.getStore().reload();

                            if (!empty(thisGrid.owner.params.reminder_id)) {
                                thisGrid.owner.owner.getStore().reload();
                            }
                        }
                    },

                    failure: function () {
                        thisGrid.getEl().unmask();
                        Ext.simpleConfirmation.error(_("Can't delete trigger"));
                    }
                });
            }
        });
    }
});