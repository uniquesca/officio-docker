var CaseStatusesManageDialog = function (config, parentGrid) {
    var thisDialog = this;
    Ext.apply(thisDialog, config);

    thisDialog.parentGrid = parentGrid;

    var autoExpandColumnId = Ext.id();

    this.caseStatusRecord = Ext.data.Record.create([
        {name: 'client_status_id', type: 'int'},
        {name: 'client_status_parent_id', type: 'int'},
        {name: 'client_status_name'}
    ]);

    this.CaseStatusesGrid = new Ext.grid.GridPanel({
        store: new Ext.data.Store({
            url: baseUrl + '/manage-company-case-statuses/get-case-statuses',
            method: 'POST',
            autoLoad: true,

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                idProperty: 'client_status_id'
            }, this.caseStatusRecord),

            sortInfo: {
                field: 'client_status_name',
                direction: 'ASC'
            }
        }),

        columns: [
            {
                id: autoExpandColumnId,
                header: _('Name'),
                sortable: true,
                dataIndex: 'client_status_name',
                width: 200
            }, {
                header: _('Default'),
                sortable: true,
                dataIndex: 'client_status_parent_id',
                hidden: boo_hide_default_column,
                fixed: true,
                align: 'center',
                width: 100,
                renderer: function (value) {
                    return empty(value) ? _('No') : _('Yes');
                }
            }
        ],

        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('New ') + statuses_field_label_singular,
                ref: 'caseStatusCreateBtn',
                cls: 'main-btn',
                scope: this,
                handler: this.addCaseStatus.createDelegate(this)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit ') + statuses_field_label_singular,
                ref: '../caseStatusEditBtn',
                scope: this,
                disabled: true,
                handler: this.onDblClick.createDelegate(this)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete ') + statuses_field_label_singular,
                ref: '../caseStatusDeleteBtn',
                scope: this,
                disabled: true,
                handler: this.deleteCaseStatus.createDelegate(this)
            }, '->', {
                text: '<i class="las la-undo-alt"></i>',
                ctCls: 'x-toolbar-cell-no-right-padding',
                handler: function () {
                    thisDialog.CaseStatusesGrid.store.reload();
                }
            }
        ],

        width: 800,
        height: 500,
        stripeRows: true,
        autoExpandColumn: autoExpandColumnId,
        cls: 'extjs-grid',
        viewConfig: {emptyText: _('No records were found.')},
        loadMask: true
    });

    CaseStatusesManageDialog.superclass.constructor.call(this, {
        title: '<i class="las la-list"></i>' + _('Workflow Master List'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,

        items: this.CaseStatusesGrid,

        buttons: [
            {
                text: _('Close'),
                cls: 'orange-btn',
                scope: this,
                handler: this.closeDialog
            }
        ]
    });

    this.CaseStatusesGrid.on('rowdblclick', this.onDblClick.createDelegate(this), this);
    this.CaseStatusesGrid.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this), this);
};

Ext.extend(CaseStatusesManageDialog, Ext.Window, {
    updateToolbarButtons: function () {
        var sel = this.CaseStatusesGrid.getSelectionModel().getSelections();
        var booHasDefault = false;
        for (var i = 0; i < sel.length; i += 1) {
            if (!empty(sel[i].data.client_status_parent_id)) {
                booHasDefault = true;
            }
        }

        this.CaseStatusesGrid.caseStatusEditBtn.setDisabled(sel.length !== 1 || booHasDefault);
        this.CaseStatusesGrid.caseStatusDeleteBtn.setDisabled(sel.length === 0 || booHasDefault);
    },

    showDialog: function () {
        this.show();
        this.center();
    },

    closeDialog: function () {
        this.close();
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.CaseStatusesGrid.selModel.getSelected();

        this.editCaseStatus(record);
    },

    reloadCaseStatusesList: function () {
        this.CaseStatusesGrid.getStore().load();
    },

    addCaseStatus: function () {
        var record = new this.caseStatusRecord({
            client_status_id: 0
        });

        var dialog = new CaseStatusDialog({}, this, record);
        dialog.showDialog()
    },

    editCaseStatus: function (record) {
        if (!empty(record.data.client_status_parent_id)) {
            return;
        }

        var dialog = new CaseStatusDialog({}, this, record);
        dialog.showDialog();
    },

    deleteCaseStatus: function () {
        var thisGrid = this.CaseStatusesGrid;
        var sel = thisGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to delete <i>{0}</i>?'),
                sel.length == 1 ? sel[0].data.client_status_name : sel.length + _(' records')
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    var arrCaseStatusIds = [];
                    for (var i = 0; i < sel.length; i++) {
                        arrCaseStatusIds.push(sel[i].data.client_status_id);
                    }

                    thisGrid.getEl().mask(_('Processing...'));
                    Ext.Ajax.request({
                        url: baseUrl + '/manage-company-case-statuses/delete-case-statuses',
                        params: {
                            arrCaseStatusIds: Ext.encode(arrCaseStatusIds)
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);

                            if (resultData.success) {
                                if (typeof window.top.refreshSettings == 'function') {
                                    window.top.refreshSettings('case_status');
                                }

                                thisGrid.store.reload();
                                thisGrid.getEl().mask(_('Done!'));
                                setTimeout(function () {
                                    thisGrid.getEl().unmask();
                                }, 1000);
                            } else {
                                Ext.simpleConfirmation.error(resultData.message);
                                thisGrid.getEl().unmask();
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error(_('Internal error. Please try again later.'));
                            thisGrid.getEl().unmask();
                        }
                    });
                }
            });
        }
    }
});
