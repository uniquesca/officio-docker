var ManagePricingGrid = function (config) {
    var thisGrid = this;
    Ext.apply(this, config);

    var autoExpandColumnId = Ext.id();

    this.cm = new Ext.grid.ColumnModel({
        columns:         [
            new Ext.grid.CheckboxSelectionModel(),
            {
                header:    'Name',
                dataIndex: 'name',
                id:        autoExpandColumnId
            },
            {
                header:    'Expiry Date',
                dataIndex: 'expiry_date'
            },
            {
                header:    'Key String',
                dataIndex: 'key_string'
            },
            {
                header:    'Promo Message',
                dataIndex: 'key_message'
            }
        ],
        defaultSortable: true
    });

    var arrRecordFields = [
        {
            name: 'pricing_category_id',
            type: 'int'
        },
        {name: 'allow_delete'},
        {name: 'allow_edit_name'},

        {name: 'name'},
        {name: 'expiry_date'},
        {name: 'key_string'},
        {name: 'key_message'},
        {name: 'default_subscription_term'},
        {name: 'replacing_general'},
        {name: 'price_storage_1_gb_monthly'},
        {name: 'price_storage_1_gb_annual'}
    ];

    for (var i = 0; i < arrSubscriptions.length; i++) {
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'price_license_user_annual'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'price_license_user_monthly'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'price_package_2_years'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'price_package_monthly'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'price_package_yearly'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'users_add_over_limit'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'user_included'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'free_storage'});
        arrRecordFields.push({name: arrSubscriptions[i]['subscription_id'] + '_' + 'free_clients'});
    }

    this.store = new Ext.data.Store({
        url:        baseUrl + '/manage-pricing/get-pricing-categories-list',
        autoLoad:   true,
        remoteSort: true,
        reader:     new Ext.data.JsonReader({
            id:            'pricing_category_id',
            root:          'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create(arrRecordFields))
    });

    this.tbar = [
        {
            text:    '<i class="las la-plus"></i>' + _('Add'),
            handler: function () {
                thisGrid.editPricingCategory(thisGrid, true);
            }
        },
        {
            text:     '<i class="las la-edit"></i>' + _('Edit'),
            ref:      '../editButton',
            disabled: true,
            handler:  function () {
                thisGrid.editPricingCategory(thisGrid, false, thisGrid.getSelectionModel().getSelected());
            }
        },
        {
            text:     '<i class="las la-trash"></i>' + _('Delete'),
            ref:      '../deleteButton',
            disabled: true,
            handler:  function () {
                thisGrid.deletePricingCategory(thisGrid, thisGrid.getSelectionModel().getSelections());
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize:    25,
        store:       this.store,
        displayInfo: true,
        displayMsg:  'Displaying pricing categories {0} - {1} of {2}',
        emptyMsg:    'No pricing categories to display'
    });

    ManagePricingGrid.superclass.constructor.call(this, {
        loadMask:         true,
        stripeRows:       true,
        cls:              'extjs-grid',
        sm:               new Ext.grid.CheckboxSelectionModel(),
        autoExpandColumn: autoExpandColumnId,
        autoExpandMin:    100,
        viewConfig:       {
            emptyText: 'No Pricing Categories found',
            forceFit:  true
        }
    });

    this.getSelectionModel().on('selectionchange', function () {
        var sel                             = thisGrid.getSelectionModel().getSelections();
        var booIsSelectedOneCategory        = sel.length == 1;
        var booIsSelectedAtLeastOneCategory = sel.length >= 1;

        var booDisableDeleteButton = !booIsSelectedAtLeastOneCategory;

        var nodes = thisGrid.getSelectionModel().getSelections();
        for (var i = 0; i < nodes.length; i++) {
            if (!nodes[i].data.allow_delete) {
                booDisableDeleteButton = true;
                break;
            }
        }

        thisGrid.editButton.setDisabled(!booIsSelectedOneCategory);
        thisGrid.deleteButton.setDisabled(booDisableDeleteButton);
    });

    this.on('rowdblclick', function (g, row) {
        thisGrid.editPricingCategory(thisGrid, false, thisGrid.getStore().getAt(row));
    });
};

Ext.extend(ManagePricingGrid, Ext.grid.GridPanel, {
    editPricingCategory: function (grid, booAddAction, s) {
        var data                = false;
        var pricingCategoryId   = '';
        var pricingCategoryName = '';

        if (booAddAction) {
            var store = grid.getStore();
            var items = store.data.items;
            for (var i = 0; i < items.length; i++) {
                if (items[i].data.name == "General") {
                    data = items[i].data;
                    break;
                }
            }
        }

        if (!booAddAction && s) {
            data                = s.data;
            pricingCategoryId   = data.pricing_category_id;
            pricingCategoryName = data.name;
        }

        var dialog = new ManagePricingDialog({
            params: {
                data:                data,
                action:              booAddAction ? 'add' : 'edit',
                pricing_category_id: pricingCategoryId,
                pricingCategoryName: pricingCategoryName
            }
        }, grid);

        dialog.showDialog();
    },

    deletePricingCategory: function (grid, s) {
        if (!s || s.length === 0) {
            Ext.simpleConfirmation.msg('Info', 'Please select pricing category to delete');
            return false;
        }

        var msg;
        if (s.length === 1) {
            msg = String.format('Are you sure you want to delete <i>{0}</i> pricing category?', s[0]['data']['name']);
        } else {
            msg = String.format('Are you sure you want to delete {0} selected pricing categories?', s.length)
        }

        Ext.Msg.confirm('Please confirm', msg, function (btn) {
            if (btn == 'yes') {
                //get id's
                var ids = [];
                for (var i = 0; i < s.length; i++) {
                    ids.push(s[i].data.pricing_category_id);
                }

                //delete
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url:     baseUrl + "/manage-pricing/delete-pricing-category",
                    params:  {
                        pricing_category_ids: Ext.encode(ids)
                    },
                    success: function (f) {

                        var result = Ext.decode(f.responseText);

                        if (!result.success) {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error(result.message);
                        } else {
                            grid.store.reload();

                            Ext.getBody().mask('Done');
                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);
                        }
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error('An error occurred when deleting selected pricing categor' + (s.length > 1 ? 'ies' : 'y'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    }
});