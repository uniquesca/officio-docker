Ext.onReady(function(){
    Ext.QuickTips.init();
    $('#admin-manage-cmi').css('min-height', getSuperadminPanelHeight() + 'px');

    var recordsOnPage = 20;
    
    var cm = new Ext.grid.ColumnModel({
        columns: [{
                header: 'CMI',
                dataIndex: 'cmi_id',
                width: 150,
                align: 'right'
            }, {
                header: 'Regulator',
                dataIndex: 'regulator_id',
                width: 150,
                align: 'right'
            }, {
                header: 'Company',
                width: 400,
                dataIndex: 'companyName'
            }
        ],
        defaultSortable: true
    });
    
    var store = new Ext.data.Store({
        url: baseUrl + '/manage-cmi/get-cmi-list',
        baseParams: { 
            start: 0,
            limit: recordsOnPage
        },
        autoLoad: true,
        remoteSort: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'cmi_id'},
            {name: 'regulator_id'},
            {name: 'company_id'},
            {name: 'companyName'}
        ])),
       listeners: {
           load: function(rstore) {
               if(rstore.getTotalCount() > recordsOnPage) {
                   pagingBar.show();
               }
           }
       }
    });
    
    var pagingBar = new Ext.PagingToolbar({
        pageSize: recordsOnPage,
        hidden: true,
        store: store
    });
    
    var grid = new Ext.grid.GridPanel({
        renderTo: 'admin-manage-cmi',
        store: store,
        cm: cm, 
        sm: new Ext.grid.CheckboxSelectionModel(), 
        autoHeight: true,
        split: true,
        stripeRows: true,
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        viewConfig: { emptyText: 'No CMI records found.' },
        bbar: pagingBar,
        tbar: [{
            text: '<i class="las la-plus"></i>' + _('Import from CSV file'),
            handler: function () {
                addCSV();
            }
        }, '->', {
            xtype: 'appGridSearch',
            width: 250,
            emptyText: 'Search CMI records...',
            store: store
        }]
    });
    
    grid.getView().getRowClass = function(record, index){
        return (empty(record.data.company_id) ? '' : 'green-row');
    };
});

function getfileextension(filename) {  
    if( filename.length === 0 ) return ''; 
    var dot = filename.lastIndexOf('.'); 
    if( dot == -1 ) return ''; 
    var extension = filename.substr(dot + 1, filename.length); 
    return extension; 
} 

function addCSV() {
    
    var fupload = new Ext.form.FileUploadField({
        width: 300,
        fieldLabel: 'Select file',
        name: 'import-file'
    }); 
    
    var pan = new Ext.FormPanel({
        fileUpload: true,
        frame: false,
        bodyStyle:'padding:5px',
        labelWidth: 60,
        items: [fupload]
    });

    var saveBtn = new Ext.Button({
        text: 'Import',
        cls:  'orange-btn',
        handler: function() {
        
            var att = fupload.getValue();
            if(empty(att)) {
                Ext.simpleConfirmation.error('Please select file to upload!');
            }
            
            if(getfileextension(att) == 'csv') {
                if(pan.getForm().isValid()) {
                
                    Ext.MessageBox.show({
                       title: 'Uploading...',
                       msg: 'Uploading...',
                       width: 300,
                       wait: true,
                       waitConfig: {interval:200},
                       closable: false,
                       icon: 'ext-mb-upload'
                    });
                    
                    pan.getForm().submit({
                        url: baseUrl + '/manage-cmi/import-from-csv',
                        success: function(f, o) {
                            Ext.MessageBox.hide();
                            
                            var resultData = Ext.decode(o.response.responseText);
                            if(empty(resultData.error)) {
                                Ext.getBody().mask('Done! Reloading, please wait...');
                                win.close();
                                location.reload();
                            } else {
                                Ext.simpleConfirmation.error(resultData.error);
                            }
                        },
                        failure: function(f, o) {
                            Ext.MessageBox.hide();
                            Ext.simpleConfirmation.error('Can\'t upload file! Please try again.');
                        }
                    });
                }
            } else {
                Ext.simpleConfirmation.error('Please select CSV file.');
            }
        }
    });
    
    var closeBtn = new Ext.Button({
        text: 'Cancel',
        handler: function(){
            win.close();
        }
    });

    var win = new Ext.Window({
        id: 'email-templates-win',
        initHidden: false,
        title: 'Import records from CSV file',
        modal: true,
        bodyStyle: 'padding:5px',
        autoHeight: true,
        resizable: false,
        width: 400,
        layout: 'form',
        items: pan,
        buttons: [closeBtn, saveBtn]
    });
    
    win.show();
    win.center();
}