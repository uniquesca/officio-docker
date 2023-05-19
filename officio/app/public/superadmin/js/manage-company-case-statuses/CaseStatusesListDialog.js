var CaseStatusesListDialog = function (config, parentGrid, oCaseStatusListRecord) {
    var thisWindow = this;
    Ext.apply(thisWindow, config);

    thisWindow.parentGrid = parentGrid;
    thisWindow.caseStatusListRecord = oCaseStatusListRecord;

    this.arrAllCaseStatuses = [];
    this.arrAssignedCaseStatuses = [];

    this.caseStatusRecord = Ext.data.Record.create([
        {name: 'client_status_id', type: 'int'},
        {name: 'client_status_parent_id', type: 'int'},
        {name: 'client_status_name'}
    ]);

    var gridPanelWidth = 450;
    var gridPanelSpacerWidth = 10;
    var totalDialogWidth = gridPanelWidth * 2 + gridPanelSpacerWidth + 15;

    var arrSavedWorflows = [{
        client_status_list_id: 0,
        client_status_list_name: _('Blank List')
    }];

    thisWindow.parentGrid.store.each(function (rec) {
        arrSavedWorflows.push(rec.data)
    });

    this.caseStatusListCopyCombo = new Ext.form.ComboBox({
        fieldLabel: _('Create the New Workflow based on'),
        hidden: !empty(thisWindow.caseStatusListRecord.data['client_status_list_id']),
        store: new Ext.data.Store({
            data: arrSavedWorflows,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'client_status_list_id'},
                {name: 'client_status_list_name'}
            ]))
        }),
        mode: 'local',
        valueField: 'client_status_list_id',
        displayField: 'client_status_list_name',
        triggerAction: 'all',
        lazyRender: true,
        typeAhead: true,
        forceSelection: true,
        width: gridPanelWidth,
        value: 0,

        listeners: {
            'beforeselect': function (combo, record) {
                thisWindow.loadWorkflowDetails(record.data.client_status_list_id, false);
            }
        }
    });

    this.caseStatusListName = new Ext.form.TextField({
        fieldLabel: _('Workflow name'),
        emptyText: _('Please enter the name...'),
        maxLength: 255,
        allowBlank: false,
        width: totalDialogWidth
    });

    this.caseCategoriesCombo = new Ext.ux.form.LovCombo({
        fieldLabel: _('Use this Workflow for the following ') + categories_field_label_plural,
        emptyText: _('Please select...'),
        allowBlank: true,
        hidden: boo_hide_default_column,
        width: totalDialogWidth,
        store: new Ext.data.Store({
            remoteSort: false,

            sortInfo: {
                field: 'case_type_and_category_name',
                direction: 'ASC'
            },

            reader: new Ext.data.JsonReader({
                idProperty: 'client_category_id',
                fields: [
                    {name: 'client_category_id'},
                    {name: 'client_category_name'},
                    {name: 'client_type_name'},
                    {
                        name:    'case_type_and_category_name',
                        convert: thisWindow.generateCaseTypeAndCategoryName.createDelegate(thisWindow)
                    },
                ]
            }),

            data: []
        }),
        mode: 'local',
        valueField: 'client_category_id',
        displayField: 'case_type_and_category_name',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false
    });

    var assignedStatusesActions = new Ext.ux.grid.RowActions({
        header: _('Order'),
        keepSelection: true,

        actions: [
            {
                iconCls: 'move_option_up',
                tooltip: _('Move ' + statuses_field_label_singular + ' up')
            }, {
                iconCls: 'move_option_down',
                tooltip: _('Move ' + statuses_field_label_singular + ' down')
            }, {
                iconCls: 'move_option_right',
                tooltip: _('Unassign ' + statuses_field_label_singular)
            }
        ],

        callbacks: {
            'move_option_up': this.moveOption.createDelegate(this),
            'move_option_down': this.moveOption.createDelegate(this),
            'move_option_right': this.moveOption.createDelegate(this)
        }
    });

    this.assignedCaseStatusesGrid = new Ext.grid.GridPanel({
        title: _('Assigned') + ' ' + statuses_field_label_plural,
        bodyCfg: {
            cls: 'extjs-panel-with-border',
        },
        width: gridPanelWidth,
        height: 400,
        hideHeaders: true,
        plugins: [assignedStatusesActions],
        columns: [
            {
                header: _('Name'),
                sortable: false,
                dataIndex: 'client_status_name',
                width: 100
            },
            assignedStatusesActions
        ],

        viewConfig: {
            forceFit: true,
            deferEmptyText: _('There are no assigned') + ' ' + statuses_field_label_plural,
            emptyText: _('There are no assigned') + ' ' + statuses_field_label_plural
        },

        store: new Ext.data.SimpleStore({
            fields: ['client_status_id', 'client_status_name'],
            data: []
        })
    });


    var availableStatusesActions = new Ext.ux.grid.RowActions({
        header: _('Order'),
        keepSelection: true,

        actions: [
            {
                iconCls: 'move_option_left',
                tooltip: _('Assign') + ' ' + statuses_field_label_singular
            }
        ],

        callbacks: {
            'move_option_left': this.moveOption.createDelegate(this)
        }
    });

    this.availableCaseStatusesGrid = new Ext.grid.GridPanel({
        title: _('Available') + ' ' + statuses_field_label_plural,
        bodyCfg: {
            cls: 'extjs-panel-with-border',
        },
        width: gridPanelWidth,
        height: 400,
        hideHeaders: true,
        plugins: [availableStatusesActions],
        columns: [
            availableStatusesActions,
            {
                header: _('Name'),
                sortable: false,
                dataIndex: 'client_status_name',
                width: 100
            }
        ],

        viewConfig: {
            forceFit: true,
            deferEmptyText: _('All ' + statuses_field_label_plural + ' are assigned.'),
            emptyText: _('All ' + statuses_field_label_plural + ' are assigned.')
        },

        store: new Ext.data.SimpleStore({
            fields: ['client_status_id', 'client_status_name'],
            data: []
        })
    });

    CaseStatusesListDialog.superclass.constructor.call(this, {
        title: empty(thisWindow.caseStatusListRecord.data['client_status_list_id']) ? '<i class="las la-plus"></i>' + _('New Workflow') : '<i class="las la-edit"></i>' + _('Edit Workflow'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,

        items: {
            xtype: 'container',
            layout: 'form',
            labelAlign: 'top',
            style: 'background-color: #fff; padding: 5px;',

            items: [
                this.caseStatusListCopyCombo,
                this.caseStatusListName,
                this.caseCategoriesCombo,
                {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        this.assignedCaseStatusesGrid,
                        {
                            width: gridPanelSpacerWidth,
                            html: '&nbsp;&nbsp;'
                        },
                        this.availableCaseStatusesGrid
                    ]
                }
            ]
        },

        buttons: [
            {
                text: '<i class="las la-plus"></i>' + _('New') + ' ' + statuses_field_label_singular,
                style: 'margin-right: 20px',
                scope: this,
                handler: this.openNewCaseStatusDialog
            }, {
                text: _('Cancel'),
                height: 36,
                style: 'padding-top: 6px',
                scope: this,
                handler: this.closeDialog
            }, {
                text: '<i class="lar la-save"></i>' + _('Save'),
                cls: 'orange-btn',
                scope: this,
                handler: this.saveMappingChanges
            }
        ]
    });
};

Ext.extend(CaseStatusesListDialog, Ext.Window, {
    generateCaseTypeAndCategoryName: function (v, record) {
        return record.client_type_name + ' - ' + record.client_category_name;
    },

    loadWorkflowDetails: function (listId, booShowAndApply) {
        var thisWindow = this;
        var el = booShowAndApply ? Ext.getBody() : thisWindow.getEl();

        el.mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/manage-company-case-statuses/get-case-status-list-info',

            params: {
                client_status_list_id: listId
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                el.unmask();

                if (resultData.success) {
                    if (booShowAndApply) {
                        thisWindow.on('show', thisWindow.showCaseStatusesListDetails.createDelegate(thisWindow, [resultData, true]), thisWindow, {single: true});
                        thisWindow.show();
                        thisWindow.center();
                    } else {
                        thisWindow.showCaseStatusesListDetails(resultData, false);
                    }
                } else {
                    // Show an error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot load data.<br/>Please try again later.'));
                el.unmask();
            }
        });
    },

    showDialog: function () {
        this.loadWorkflowDetails(this.caseStatusListRecord.data['client_status_list_id'], true);
    },

    closeDialog: function () {
        this.close();
    },

    showCaseStatusesListDetails: function (resultData, booUpdateName) {
        var thisWindow = this;

        if (!empty(resultData.case_status_list_name) && booUpdateName) {
            thisWindow.caseStatusListName.setValue(resultData.case_status_list_name);
        }

        thisWindow.caseCategoriesCombo.getStore().loadData(resultData.case_categories);
        if (resultData.assigned_categories_ids.length) {
            thisWindow.caseCategoriesCombo.setValue(resultData.assigned_categories_ids);
        }

        thisWindow.arrAllCaseStatuses = resultData.case_statuses;
        thisWindow.arrAssignedCaseStatuses = resultData.assigned_statuses_ids;

        var arrAssignedStatuses = [];
        Ext.each(thisWindow.arrAssignedCaseStatuses, function (assignedStatusId) {
            Ext.each(thisWindow.arrAllCaseStatuses, function (oStatus) {
                if (oStatus.client_status_id == assignedStatusId) {
                    arrAssignedStatuses.push([oStatus.client_status_id, oStatus.client_status_name]);
                }
            });
        });

        var arrAvailableStatuses = [];
        Ext.each(thisWindow.arrAllCaseStatuses, function (oStatus) {
            if (!thisWindow.arrAssignedCaseStatuses.has(oStatus.client_status_id)) {
                arrAvailableStatuses.push([oStatus.client_status_id, oStatus.client_status_name]);
            }
        });

        thisWindow.assignedCaseStatusesGrid.getStore().removeAll();
        thisWindow.assignedCaseStatusesGrid.getStore().loadData(arrAssignedStatuses);
        thisWindow.assignedCaseStatusesGrid.syncSize();

        thisWindow.availableCaseStatusesGrid.getStore().removeAll();
        thisWindow.availableCaseStatusesGrid.getStore().loadData(arrAvailableStatuses);
        thisWindow.availableCaseStatusesGrid.syncSize();
    },

    moveOption: function (grid, record, action, selectedRow) {
        var thisWindow = this;
        var assignedStatusesStore = thisWindow.assignedCaseStatusesGrid.getStore();
        var availableStatusesStore = thisWindow.availableCaseStatusesGrid.getStore();

        switch (action) {
            case 'move_option_up':
            case 'move_option_down':
                var booUp = action === 'move_option_up';

                // Move option only if:
                // 1. It is not the first, if moving up
                // 2. It is not the last, if moving down
                var booMove = false;
                var index;
                if (assignedStatusesStore.getCount() > 0) {
                    if (booUp && selectedRow > 0) {
                        index = selectedRow - 1;
                        booMove = true;
                    } else if (!booUp && selectedRow < assignedStatusesStore.getCount() - 1) {
                        index = selectedRow + 1;
                        booMove = true;
                    }
                }

                if (booMove) {
                    var row = assignedStatusesStore.getAt(selectedRow);
                    assignedStatusesStore.removeAt(selectedRow);
                    assignedStatusesStore.insert(index, row);
                } else {
                    return;
                }
                break;

            case 'move_option_right':
                // Unassign status
                var row = assignedStatusesStore.getAt(selectedRow);
                assignedStatusesStore.removeAt(selectedRow);
                availableStatusesStore.insert(availableStatusesStore.getCount(), row);
                availableStatusesStore.sort('client_status_name', 'ASC');
                break;

            case 'move_option_left':
                // Assign status
                var row = availableStatusesStore.getAt(selectedRow);
                availableStatusesStore.removeAt(selectedRow);
                assignedStatusesStore.insert(assignedStatusesStore.getCount(), row);
                break;

            default:
                return;
                break;
        }

        var arrAssignedStatuses = [];
        assignedStatusesStore.each(function (oStoreRecord) {
            arrAssignedStatuses.push(oStoreRecord.data.client_status_id);
        });
        thisWindow.arrAssignedCaseStatuses = arrAssignedStatuses;
    },

    saveMappingChanges: function () {
        var thisWindow = this;

        if (!thisWindow.caseStatusListName.isValid()) {
            return;
        }

        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/manage-company-case-statuses/manage-case-status-list',
            timeout: 10 * 60 * 1000, // 10 minutes

            params: {
                client_status_list_id: Ext.encode(thisWindow.caseStatusListRecord.data['client_status_list_id']),
                client_status_list_name: Ext.encode(thisWindow.caseStatusListName.getValue()),
                assigned_case_categories: Ext.encode(thisWindow.caseCategoriesCombo.getValue()),
                assigned_case_statuses: Ext.encode(thisWindow.arrAssignedCaseStatuses)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    thisWindow.parentGrid.store.reload();

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        if (typeof window.top.refreshSettings == 'function') {
                            window.top.refreshSettings('case_status');
                        }

                        thisWindow.closeDialog();
                    }, 750);
                } else {
                    // Show an error message
                    Ext.simpleConfirmation.error(resultData.message);
                    thisWindow.getEl().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot save data.<br/>Please try again later.'));
                thisWindow.getEl().unmask();
            }
        });
    },

    reloadCaseStatusesList: function (newStatusId, newStatusName) {
        var record = new this.caseStatusRecord({
            client_status_id: newStatusId,
            client_status_name: newStatusName
        });

        // Add this record to the list + sort all records
        var grid = this.availableCaseStatusesGrid;
        grid.getStore().insert(grid.getStore().getCount(), record);
        grid.getStore().sort('client_status_name', 'ASC');
        grid.syncSize();
    },

    openNewCaseStatusDialog: function () {
        var record = new this.caseStatusRecord({
            client_status_id: 0
        });

        var dialog = new CaseStatusDialog({}, this, record);
        dialog.showDialog();
    }
});
