var CaseStatusesListsGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;

    this.caseStatusListRecord = Ext.data.Record.create([
        {name: 'client_status_list_id', type: 'int'},
        {name: 'client_status_list_parent_id', type: 'int'},
        {name: 'client_status_list_statuses'},
        {name: 'client_status_list_case_types'},
        {name: 'client_status_list_categories'},
        {name: 'client_status_list_name'}
    ]);

    this.store = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/manage-company-case-statuses/get-case-statuses-lists',
            method: 'POST'
        }),
        autoLoad: true,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'client_status_list_id'
        }, this.caseStatusListRecord),

        sortInfo: {
            field: 'client_status_list_name',
            direction: 'ASC'
        }
    });

    this.columns = [
        {
            id: 'col_client_status_list_name',
            header: _('Workflow'),
            sortable: true,
            dataIndex: 'client_status_list_name',
            width: 200
        }, {
            header: statuses_field_label_plural,
            sortable: false,
            dataIndex: 'client_status_list_statuses',
            width: 200,
            renderer: this.renderArrayItems
        }, {
            header: case_type_field_label_plural,
            sortable: false,
            dataIndex: 'client_status_list_case_types',
            width: 400,
            renderer: this.renderArrayItems
        }, {
            header: categories_field_label_plural,
            sortable: false,
            dataIndex: 'client_status_list_categories',
            width: 300,
            renderer: this.renderArrayItems
        }, {
            header: _('Default'),
            sortable: true,
            dataIndex: 'client_status_list_parent_id',
            hidden: boo_hide_default_column,
            fixed: true,
            align: 'center',
            width: 100,
            renderer: function (value) {
                return empty(value) ? _('No') : _('Yes');
            }
        }
    ];

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New Workflow'),
            cls: 'main-btn',
            ref: 'caseStatusListCreateBtn',
            scope: this,
            handler: this.addCaseStatusList.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Workflow'),
            ref: '../caseStatusListEditBtn',
            scope: this,
            disabled: true,
            handler: this.onDblClick.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Workflow'),
            ref: '../caseStatusListDeleteBtn',
            scope: this,
            disabled: true,
            handler: this.deleteCaseStatusList.createDelegate(this)
        }, ' ', {
            text: '<i class="las la-list"></i>' + _('Workflow Master List'),
            ref: '../caseStatusesManageBtn',
            scope: this,
            handler: this.manageCaseStatuses.createDelegate(this)
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            ctCls: 'x-toolbar-cell-no-right-padding',
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 100500,
        store: this.store,
        displayInfo: true,
        emptyMsg: _('No records to display')
    });

    CaseStatusesListsGrid.superclass.constructor.call(this, {
        autoWidth: true,
        height: getSuperadminPanelHeight(true),
        autoExpandColumn: 'col_client_status_list_name',
        stripeRows: true,
        cls: 'extjs-grid',
        viewConfig: {emptyText: _('No records were found.')},
        loadMask: true
    });

    this.on('rowdblclick', this.onDblClick.createDelegate(this), this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this), this);
};

Ext.extend(CaseStatusesListsGrid, Ext.grid.GridPanel, {
    renderArrayItems: function (arrItems) {
        var result = '';
        if (empty(arrItems) || empty(arrItems.length)) {
            result = '<span style="color: red">' + _('Not assigned') + '</span>';
        } else {
            result = String.format(
                "<span ext:qtip='{0}'>{1}</span>",
                implode(',<br/>', arrItems).replaceAll("'", "′"),
                implode(', ', arrItems),
            );
        }

        return result;
    },

    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();
        var booHasDefault = false;
        for (var i = 0; i < sel.length; i += 1) {
            if (!empty(sel[i].data.client_status_list_parent_id)) {
                booHasDefault = true;
            }
        }

        this.caseStatusListEditBtn.setDisabled(sel.length !== 1 || booHasDefault);
        this.caseStatusListDeleteBtn.setDisabled(sel.length === 0 || booHasDefault);
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        this.editCaseStatusList(this, record);
    },

    addCaseStatusList: function () {
        var record = new this.caseStatusListRecord({
            client_status_list_id: 0
        });

        var dialog = new CaseStatusesListDialog({}, this, record);
        dialog.showDialog();
    },

    editCaseStatusList: function (grid, record) {
        if (!empty(record.data.client_status_list_parent_id)) {
            return;
        }

        var dialog = new CaseStatusesListDialog({}, grid, record);
        dialog.showDialog();
    },

    manageCaseStatuses: function (grid) {
        var dialog = new CaseStatusesManageDialog({}, grid);
        dialog.showDialog();
    },

    deleteCaseStatusList: function () {
        var thisGrid = this;
        var sel = thisGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to delete <i>{0}</i>?'),
                sel.length == 1 ? sel[0].data.client_status_list_name : sel.length + _(' records')
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    var arrCaseStatusListIds = [];
                    for (var i = 0; i < sel.length; i++) {
                        arrCaseStatusListIds.push(sel[i].data.client_status_list_id);
                    }

                    thisGrid.getEl().mask(_('Processing...'));
                    Ext.Ajax.request({
                        url: baseUrl + '/manage-company-case-statuses/delete-case-statuses-list',
                        params: {
                            arrCaseStatusListIds: Ext.encode(arrCaseStatusListIds)
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);

                            if (resultData.success) {
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