var AutomaticRemindersConditionsGrid = function(config, owner) {
    var thisGrid = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.store = new Ext.data.Store({
        url: baseUrl + '/automatic-reminder-conditions/get-grid',
        baseParams: {
            reminder_id: thisGrid.owner.params.reminder_id,
            condition_ids: Ext.encode([])
        },
        autoLoad: false,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'condition_id'},
            {name: 'condition_text'}
        ]))
    });

    this.store.on('load', this.checkLoadedData.createDelegate(this));

    var thisGridId         = Ext.id();
    var conditionTextColId = Ext.id();
    AutomaticRemindersConditionsGrid.superclass.constructor.call(this, {
        id: thisGridId,
        autoExpandColumn: conditionTextColId,
        colModel: new Ext.grid.ColumnModel([
            {
                id: conditionTextColId,
                header: '',
                dataIndex: 'condition_text',
                sortable: true
            }
        ]),

        viewConfig: {
            deferEmptyText: false,
            emptyText:      _('No conditions found.'),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 20,

            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + thisGridId + ' .x-grid3-scroller').elements[0];
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
                handler: thisGrid.addAutomaticReminderCondition.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit'),
                ref: '../editConditionBtn',
                disabled: true,
                handler: thisGrid.editAutomaticReminderCondition.createDelegate(thisGrid)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                ref: '../deleteConditionBtn',
                disabled: true,
                handler: thisGrid.deleteAutomaticReminderCondition.createDelegate(thisGrid)
            }
        ]
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
    this.on('rowdblclick', this.editAutomaticReminderCondition.createDelegate(this));

    // Hide header, for some reason hideHeaders: true fails...
    this.on('render', function (grid) {
        grid.getView().el.select('.x-grid3-header').setStyle('display', 'none');
    });
};

Ext.extend(AutomaticRemindersConditionsGrid, Ext.grid.GridPanel, {
    checkLoadedData: function(store) {
        this.owner.booHasChangedFieldConditions = store.reader.jsonData && store.reader.jsonData.booHasChangedFieldConditions;
    },

    updateToolbarButtons: function() {
        var sel = this.getSelectionModel().getSelections();
        var booIsSelectedOnlyOne = sel.length == 1;

        this.editConditionBtn.setDisabled(!booIsSelectedOnlyOne);
        this.deleteConditionBtn.setDisabled(!booIsSelectedOnlyOne);
    },

    addAutomaticReminderCondition: function () {
        var thisGrid = this;
        var dialog = new AutomaticRemindersConditionsDialog({
            params: {
                action: 'add',
                reminder_id: thisGrid.owner.params.reminder_id
            }
        }, thisGrid.owner);
        dialog.showDialog();
    },

    editAutomaticReminderCondition: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (node) {
            var dialog = new AutomaticRemindersConditionsDialog({
                params: {
                    action: 'edit',
                    reminder_id: thisGrid.owner.params.reminder_id,
                    condition_id: node.data.condition_id
                }
            }, thisGrid.owner);
            dialog.showDialog();
        }
    },

    deleteAutomaticReminderCondition: function () {
        var thisGrid = this;
        var node = thisGrid.getSelectionModel().getSelected();
        if (!node) {
            return;
        }

        var msg = String.format(
            _('Are you sure you want to delete') + '<br/><i style="white-space: nowrap">{0}</i>?',
            node.data.condition_text
        );

        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + "/automatic-reminder-conditions/delete",
                    params: {
                        reminder_id: thisGrid.owner.params.reminder_id,
                        condition_id: node.data.condition_id
                    },
                    
                    success: function (f) {
                        var resultData = Ext.decode(f.responseText);

                        if (!resultData.success) {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error(resultData.error);
                        } else {
                            Ext.getBody().mask('Deleted!');
                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);

                            if (empty(thisGrid.owner.params.reminder_id)) {
                                var arrNewConditionIds = [];
                                Ext.each(thisGrid.owner.newConditionIds, function (conditionIdToCheck) {
                                    if (conditionIdToCheck != node.data.condition_id) {
                                        arrNewConditionIds.push(conditionIdToCheck);
                                    }
                                });

                                thisGrid.owner.newConditionIds               = arrNewConditionIds;
                                thisGrid.getStore().baseParams.condition_ids = Ext.encode(thisGrid.owner.newConditionIds);
                            }


                            thisGrid.getStore().reload();

                            if (!empty(thisGrid.owner.params.reminder_id)) {
                                thisGrid.owner.owner.getStore().reload();
                            }
                        }

                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_("Can't delete condition"));
                    }
                });
            }
        });
    }
});