var FieldInfoRecord = Ext.data.Record.create([
   {name: 'option_id', type: 'int'},
   {name: 'option_order', type: 'int'},
   {name: 'option_name', type: 'string'}
]);

var checkCategoriesGroup = function() {
    if(Ext.getCmp('categories_group').isValid()) {
        $('#categories_error_container').hide();
    } else {
        $('#categories_error_container').show();
    }
};

var updatePriorities = function() {
    var store = Ext.getCmp('categories_order_grid').getStore();

    var arrCheckboxes = Ext.getCmp('categories_group').items.items;
    for (var i = 0; i < arrCheckboxes.length; i++) {
        var chkBox = Ext.getCmp('category_' + arrCheckboxes[i].inputValue);

        // Check if if element already added
        var idx = store.find('option_id', arrCheckboxes[i].inputValue);
        if(chkBox.getValue()) {
            if (idx === -1) { // Element does not exists - create
                var newEl = new FieldInfoRecord({
                    'option_id'   : arrCheckboxes[i].inputValue,
                    'option_order': store.getCount() + 1,
                    'option_name' : chkBox.boxLabel
                });
                store.add(newEl);
            }
        } else {
            if (idx != -1) { // Element exists - remove
                store.removeAt(idx);
            }
        }
    }
};

var saveCompanyProspects = function() {
    // Check for errors
    var booValid = true;
    var arrCheckComponents = ['categories_group'];
    for (var i = 0; i < arrCheckComponents.length; i++) {
        if(Ext.getCmp(arrCheckComponents[i]) && !Ext.getCmp(arrCheckComponents[i]).isValid()) {
            booValid = false;
        }
    }

    checkCategoriesGroup();

    if(!booValid) {
        return false;
    }

    // Collect info
    var store = Ext.getCmp('categories_order_grid').getStore();
    var arrCategories = [];
    var orderId = 0;

    store.each(function(rec) {
        orderId++;
        arrCategories[arrCategories.length] = {
            'prospect_category_id': parseInt(rec.data.option_id, 10),
            'name':                 rec.data.option_name,
            'order':                orderId
        };
    });


    // Send info via ajax request
    Ext.getBody().mask('Saving...');
    Ext.Ajax.request({
        url: baseUrl + "/manage-company-prospects/save",
        params: {
            'company_id':         Ext.get('company_id') ? Ext.get('company_id').getValue() : null,
            'arrCategories':      Ext.encode(arrCategories)
        },

        success: function(result) {
            Ext.getBody().unmask();

            var resultDecoded = Ext.decode(result.responseText);
            if(resultDecoded.success) {
                Ext.simpleConfirmation.info(resultDecoded.message);
            } else {
                // Show error message
                Ext.simpleConfirmation.error(resultDecoded.message);
            }
        },

        failure: function() {
            Ext.Msg.alert('Status', 'Cannot save changes');
            Ext.getBody().unmask();
        }
    });
};

var initProspectsSettings = function() {
    var arrShowCheckboxes = [];
    for (var i = 0; i < arrCategories.length; i++) {
        var booChecked = false;
        for (j = 0; j < arrCompanyCategories.length; j++) {
            if(arrCompanyCategories[j].prospect_category_id == arrCategories[i].prospect_category_id) {
                booChecked = true;
            }
        }

        arrShowCheckboxes[arrShowCheckboxes.length] = {
            id: 'category_' + arrCategories[i].prospect_category_id,
            name: 'category_' + arrCategories[i].prospect_category_id,
            boxLabel: arrCategories[i].prospect_category_name,
            checked: booChecked,
            inputValue: arrCategories[i].prospect_category_id,
            listeners: {
                check: function() {
                    checkCategoriesGroup();
                    updatePriorities();
                }
            }
        };
    }

    new Ext.form.CheckboxGroup({
        id: 'categories_group',
        xtype: 'checkboxgroup',
        renderTo: 'categories_container',
        allowBlank: false,
        // Put all controls in a single column with width 100%
        columns: 1,
        items: arrShowCheckboxes
    });




    // ORDER
    var action = new Ext.ux.grid.RowActions({
        header:'Order',
        keepSelection: true,

        actions:[{
            text: '<i class="las la-arrow-up"></i>',
            tooltip: 'Move Category Up',
            iconCls: 'move_option_up_btn'
        }, {
            text: '<i class="las la-arrow-down"></i>',
            tooltip: 'Move Category Down',
            iconCls: 'move_option_down_btn'
        }],

        callbacks:{
            'move_option_up_btn': function (grid, record, action, row) {
                moveOption(true, row);
            },
            'move_option_down_btn': function (grid, record, action, row) {
                moveOption(false, row);

            }
        }
    });

    var cm = new Ext.grid.ColumnModel({
        columns: [
            action,
            {
                id: 'name_column',
                header: "Option",
                dataIndex: 'option_name',
                align: 'left',
                width: 250
            }
        ],
        defaultSortable: false
    });
    var optionsStore = new Ext.data.Store({
        autoLoad: false,
        remoteSort: false,
        reader: new Ext.data.ArrayReader({id: 'option_id'}, FieldInfoRecord),

        listeners: {
            load: function(){
                optionsGrid.getSelectionModel().selectFirstRow();
            }
        }
    });

    var selectedRow = 0;
    function moveOption(booUp, selectedRow) {
        // Move option only if:
        // 1. It is not the first, if moving up
        // 2. It is not the last, if moving down
        var booMove = false;
        var cindex;
        if(optionsStore.getCount() > 0) {
            if(booUp && selectedRow > 0) {
                cindex = selectedRow - 1;
                booMove = true;
            } else if(!booUp && selectedRow < optionsStore.getCount()-1) {
                cindex = selectedRow + 1;
                booMove = true;
            }
        }

        if (booMove) {
            var row = optionsStore.getAt(selectedRow);
            optionsStore.removeAt(selectedRow);
            optionsStore.insert(cindex,row);
        }
    }

    var selections = new Ext.grid.RowSelectionModel({
        singleSelect:true,
        listeners: {
            rowselect: function(sm, rowIndex){
                selectedRow = rowIndex;
            }
        }
    });

    var optionsGrid = new Ext.grid.EditorGridPanel({
        id: 'categories_order_grid',
        renderTo: 'categories_order_container',
        autoExpandColumn: 'name_column',
        store: optionsStore,
        cm: cm,
        sm: selections,
        plugins: [action],
        viewConfig:
        {
          emptyText: 'No categories were selected',
          forceFit:true
        },

        autoWidth: true,
        autoHeight: true,
        stripeRows: true,
        cls: 'extjs-grid'
    });
    optionsGrid.show();

    // Show checked categories
    var initData  = [];
    for (i = 0; i < arrCompanyCategories.length; i++) {
        // Collect data for priority section
        initData[initData.length] = [
            arrCompanyCategories[i].prospect_category_id,
            arrCompanyCategories[i].order,
            arrCompanyCategories[i].prospect_category_name
        ];

    }
    optionsStore.loadData(initData);
};