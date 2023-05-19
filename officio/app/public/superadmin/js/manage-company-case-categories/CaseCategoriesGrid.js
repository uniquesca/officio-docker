var CaseCategoriesGrid = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisGrid = this;

    this.caseCategoryRecord = Ext.data.Record.create([
        {name: 'client_category_id', type: 'int'},
        {name: 'client_category_parent_id', type: 'int'},
        {name: 'client_category_assigned_list_id', type: 'int'},
        {name: 'client_category_assigned_list_name'},
        {name: 'client_category_name'},
        {name: 'client_category_abbreviation'},
        {name: 'client_category_link_to_employer'},
        {name: 'client_category_order', type: 'int'}
    ]);

    this.store = new Ext.data.Store({
        autoLoad: false,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'client_category_id'
        }, this.caseCategoryRecord),

        sortInfo: {
            field: 'client_category_order',
            direction: 'ASC'
        }
    });

    var categoriesActions = new Ext.ux.grid.RowActions({
        header: _('Order'),
        keepSelection: true,
        widthIntercept: 30,
        hidden: config.booReadOnly,

        actions: [
            {
                iconCls: 'move_option_up',
                tooltip: String.format(_('Move {0} up'), categories_field_label_singular)
            }, {
                iconCls: 'move_option_down',
                tooltip: String.format(_('Move {0} down'), categories_field_label_singular)
            }
        ],

        callbacks: {
            'move_option_up': this.moveOption.createDelegate(this),
            'move_option_down': this.moveOption.createDelegate(this)
        }
    });

    this.columns = [
        {
            id: 'col_client_category_name',
            header: categories_field_label_singular,
            sortable: false,
            dataIndex: 'client_category_name',
            width: 80
        }, {
            header: _('Abbreviation'),
            sortable: false,
            dataIndex: 'client_category_abbreviation',
            fixed: true,
            align: 'center',
            width: 140
        }, {
            header: _('Link to Employer'),
            dataIndex: 'client_category_link_to_employer',
            width: 160,
            fixed: true,
            align: 'center',
            renderer: function (val) {
                return val == 'Y' ? _('Yes') : _('No')
            }
        }, {
            header: statuses_field_label_singular + _(' Workflow'),
            dataIndex: 'client_category_assigned_list_name',
            width: 230,
            fixed: true,
        },
        categoriesActions
    ];

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New') + ' ' + categories_field_label_singular,
            ref: 'caseCategoryCreateBtn',
            disabled: config.booReadOnly,
            height: 35,
            scope: this,
            handler: this.addCaseCategory.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit'),
            ref: '../caseCategoryEditBtn',
            scope: this,
            disabled: true,
            handler: this.onDblClick.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete'),
            ref: '../caseCategoryDeleteBtn',
            scope: this,
            hidden: true, // Temporary hidden, can be removed if server side will be ready
            disabled: true,
            handler: this.deleteCaseCategory.createDelegate(this)
        }
    ];

    CaseCategoriesGrid.superclass.constructor.call(this, {
        autoWidth: true,
        autoExpandColumn: 'col_client_category_name',
        stripeRows: true,
        viewConfig: {
            deferEmptyText: _('No records were found.'),
            emptyText: _('No records were found.')
        },
        loadMask: true,
        plugins: [categoriesActions],
        bodyCfg: {
            cls: 'extjs-panel-with-border',
        }
    });

    this.on('rowdblclick', this.onDblClick.createDelegate(this), this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this), this);
};

Ext.extend(CaseCategoriesGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();
        var booHasDefault = false;
        for (var i = 0; i < sel.length; i += 1) {
            if (!empty(sel[i].data.client_category_parent_id)) {
                booHasDefault = true;
            }
        }

        this.caseCategoryEditBtn.setDisabled(sel.length !== 1 || booHasDefault);
        this.caseCategoryDeleteBtn.setDisabled(sel.length === 0 || booHasDefault);
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        this.editCaseCategory(this, record);
    },

    addCaseCategory: function () {
        var record = new this.caseCategoryRecord({
            client_category_id: 0
        });

        var dialog = new CaseCategoriesDialog(this, record, true);
        dialog.showDialog();
    },

    editCaseCategory: function (grid, record) {
        if (!empty(record.data.client_category_parent_id) || grid.booReadOnly) {
            return;
        }

        var dialog = new CaseCategoriesDialog(grid, record, false);
        dialog.showDialog();
    },

    deleteCaseCategory: function () {
        var thisGrid = this;
        var categoriesStore = thisGrid.getStore();
        var sel = thisGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to delete <i>{0}</i>?'),
                sel.length == 1 ? sel[0].data.client_category_name : sel.length + _(' records')
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    for (var i = 0; i < sel.length; i++) {
                        categoriesStore.remove(sel[i]);
                    }

                    var arrCategories = [];
                    for (var i = 0; i < categoriesStore.getCount(); i++) {
                        var rec = categoriesStore.getAt(i);
                        arrCategories.push(rec.data);
                    }

                    // Reload data in the store
                    thisGrid.loadCaseTypeCategories(arrCategories);
                }
            });
        }
    },

    moveOption: function (grid, record, action, selectedRow) {
        var categoriesStore = this.getStore();
        var booUp = action === 'move_option_up';

        // Move option only if:
        // 1. It is not the first, if moving up
        // 2. It is not the last, if moving down
        var booMove = false;
        var index;
        if (categoriesStore.getCount() > 0) {
            if (booUp && selectedRow > 0) {
                index = selectedRow - 1;
                booMove = true;
            } else if (!booUp && selectedRow < categoriesStore.getCount() - 1) {
                index = selectedRow + 1;
                booMove = true;
            }
        }

        if (booMove) {
            var row = categoriesStore.getAt(selectedRow);
            categoriesStore.removeAt(selectedRow);
            categoriesStore.insert(index, row);

            // Update order for each record
            var arrCategories = [];
            for (var i = 0; i < categoriesStore.getCount(); i++) {
                var rec = categoriesStore.getAt(i);
                rec.beginEdit();
                rec.set('client_category_order', i);
                rec.endEdit();

                arrCategories.push(rec.data);
            }

            // Reload data in the store
            this.loadCaseTypeCategories(arrCategories);
        }
    },

    loadCaseTypeCategories: function (arrCategories) {
        this.getStore().loadData({
            rows: arrCategories,
            totalCount: arrCategories.length
        });
    }
});