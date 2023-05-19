Ext.onReady(function(){
    showLastLoggedInGrid();
});

function showLastLoggedInGrid() {
    
    Ext.QuickTips.init();
    
    var companiesCount = new Ext.Toolbar.TextItem('');
    var pagingBar = new Ext.ux.StatusBar({
        statusAlign: 'right',
        items: [            
            {
                text: 'Filter',
                menu: [{
                    id: 'booShowOnlyTrial',
                    text: 'Show only trial companies',
                    checked: false,
                    listeners: {
                        checkchange: function(item, booChecked) {
                            store.reload();
                        }
                    }
                }]
            }, '->',
            companiesCount
        ]
    });

    var rec = Ext.data.Record.create([
        {name: 'name'},
        {name: 'companyName'},
        {name: 'member_id'},
        {name: 'lastLogin'},
        {name: 'username'},
        {name: 'lastActivity'}
    ]);
    
    var store = new Ext.data.GroupingStore({
        reader: new Ext.data.JsonReader({
            id: 0, 
            root: 'data'
        }, rec),
        
        autoLoad: true,
        sortInfo: {field: 'lastLogin', direction: "ASC"}, //order in-groups fields
        groupField: 'lastActivity', //order group fields
        
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/last-logged-in/load',
            method: 'POST'
        }),        
        
        listeners: {
            'beforeload' : function(store, options) {
                options.params = options.params || {};
                
                // New variable
                var params = {
                    booShowOnlyTrial: Ext.getCmp('booShowOnlyTrial').checked
                };
                Ext.apply(options.params, params);
            },
            
            'load': function(store, records, options) {
                var count = String.format(
                    'Total: {0} companies',
                    store.reader.jsonData.companies_count
                );
                
                Ext.fly(companiesCount.getEl()).update(count);
            }
        }
    });
    
    
    var grid = new Ext.grid.GridPanel({
        bbar: pagingBar,
        
        store: store,
        loadMask: true,
        columns: [
            {id:'companyName', hidden:true, dataIndex: 'companyName'},
            {id:'lastActivity', hidden:true, dataIndex: 'lastActivity'},
            {header: "Member ID", width: 50, sortable: true, dataIndex: 'member_id'},
            {header: "Name", width: 150, sortable: true, dataIndex: 'name'},
            {header: "Username", width: 150, sortable: true, dataIndex: 'username'},
            {header: "Last Logged In", width: 100, sortable: true, renderer: Ext.util.Format.dateRenderer('m/d/Y'), dataIndex: 'lastLogin'}
        ],
        
        view: new Ext.grid.GroupingView({
            deferEmptyText: 'No records found.',
            emptyText: 'No records found.',
            forceFit: true,
            groupTextTpl: '{[values.rs[0].data["companyName"]]} (Last Activity: {[values.rs[0].data["lastActivity"]]})'
        }),

        viewConfig: { emptyText: 'No companies found.' },
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        stripeRows: true,
        cls: 'extjs-grid',
        height: getSuperadminPanelHeight(),
        renderTo: 'admin-last-logged-in-content',
        listeners: {
            afterrender: function () {
                updateSuperadminIFrameHeight('#admin-last-logged-in-content');
            }
        }
    });
}