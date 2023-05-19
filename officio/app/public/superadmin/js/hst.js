var generateGrid = function(booOfficio) {
    var Province = Ext.data.Record.create([
        {
            name: 'province_id',
            type: 'int'
        }, {
            name: 'province_label',
            type: 'string'
        }, {
            name: 'tax_rate',
            type: 'float'
        }, {
            name: 'tax_label',
            type: 'string'
        }, {
            name: 'tax_type',
            type: 'string'
        }, {
            name: 'is_system',
            type: 'string'
        }
    ]);


    var store = new Ext.data.GroupingStore({
        reader: new Ext.data.JsonReader({fields: Province}),
        
        // Use remote data
        url: baseUrl + '/manage-hst/get-provinces',
        baseParams: {booOfficio: booOfficio},
        method: 'POST',
        autoLoad: true
    });

    var editor = new Ext.ux.grid.RowEditor({
        saveText: 'Update',
        errorSummary: false,
        
        listeners: {
            canceledit: function() {
                store.rejectChanges();
            },
            
            afteredit: function(editor, changes, record, rowIndex) {
                var body = Ext.getBody();
                body.mask('Updating...');

                Ext.Ajax.request({
                    url: baseUrl + '/manage-hst/update-province',
                    params: {
                        arrProvince: Ext.encode( record.data ),
                        booOfficio: booOfficio
                    },

                    success: function(result){
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            // Show confirmation message
                            body.mask('Done.');
                            
                            store.commitChanges();

                            setTimeout(function(){
                              body.unmask();
                            }, 750);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message, '', function() {editor.startEditing(rowIndex, true);});
                            body.unmask();
                        }
                    },

                    failure: function(){
                        editor.startEditing(rowIndex, true);
                        
                        Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                        body.unmask();
                    }
                });
            }
        }
    });

    var taxTypeCombo = new Ext.form.ComboBox({
        store: new Ext.data.SimpleStore({
            fields: ['tax_type', 'tax_type_name'],
            data: [
                ['exempt', 'Exempt'],
                ['included', 'Included'],
                ['excluded', 'Excluded']
            ]
        }),
        displayField:   'tax_type_name',
        valueField:     'tax_type',
        mode:           'local',
        triggerAction:  'all',
        lazyRender:     true,
        forceSelection: true,
        allowBlank:     false,
        editable:       false,
        typeAhead:      false
    });

    var grid = new Ext.grid.GridPanel({
        title: booOfficio ? 'Officio' : 'Companies',
        store: store,
        width: 600,
        autoHeight: true,
        stripeRows: true,
        loadMask: true,
        margins: '0 5 5 5',
        autoExpandColumn: 'province_label',
        plugins: [editor],

        view: new Ext.grid.GroupingView({
            deferEmptyText: 'No provinces found.',
            emptyText: 'No provinces found.',
            markDirty: false
        }),

        columns: [
            new Ext.grid.RowNumberer(),
            {
                id: 'province_label',
                header: 'Province',
                dataIndex: 'province_label',
                width: 200,
                sortable: true,
                editor: {
                    xtype: 'textfield',
                    allowBlank: false
                }
            }, {
                header: 'Tax Rate',
                dataIndex: 'tax_rate',
                width: 90,
                fixed: true,
                sortable: true,
                renderer: function(val){
                    // Ensure val is a string
                    val = val + "";
                    
                    // Remove trailing zeros
                    val.replace(/0+$/ig, '');
                    

                    // Calculate digits count after the decimal point
                    var pos = val.indexOf('.') + 1;
                    var decimalCount = pos > 0 ? val.length - pos : 0;
                    var format;
                    switch (decimalCount) {
                        case 1:
                            format = '0.0%';
                            break;
                        case 2:
                            format = '0.00%';
                            break;
                        case 3:
                            format = '0.000%';
                            break;
                        case 4:
                            format = '0.0000%';
                            break;
                        default:
                            format = '0%'; // show only digits, no precision
                            break;
                    }

                    return Ext.util.Format.number(val, format);
                },
                
                editor: {
                    xtype: 'numberfield',
                    allowBlank: false,
                    decimalPrecision: 4,
                    minValue: 0
                }
            }, {
                header: 'Tax Name',
                dataIndex: 'tax_label',
                align: 'center',
                sortable: true,
                width: 100,
                fixed: true,
                editor: {
                    xtype: 'textfield',
                    allowBlank: false
                }
            }, {
                header: 'Tax Type',
                dataIndex: 'tax_type',
                align: 'left',
                sortable: true,
                width: 90,
                editor: taxTypeCombo,
                renderer: function (val, a, row) {
                    return ucfirst(row.data.tax_type);
                }
            }, {
                header: 'Is system',
                dataIndex: 'is_system',
                align: 'center',
                sortable: false,
                width: 90
            }
        ],
        
        bbar: [
            'Please double click to edit the row.'
        ]        
    });

    grid.getView().getRowClass = function (record) {
        return record.data.is_system == 'Yes' ? 'bold-row ' : '';
    };
    
    return grid;
};

Ext.onReady(function(){
    Ext.QuickTips.init();

    new Ext.TabPanel({
        renderTo: 'admin-manage-hst',
        cls: 'tabs-second-level',
        autoWidth: true,
        height: getSuperadminPanelHeight(),
        activeTab: 0,
        frame: false,
        plain: true,
        defaults:{autoHeight: false},
        items:[
            generateGrid(true),
            generateGrid(false)
        ]
    });
});
