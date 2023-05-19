var PTErrorCodesGrid = function() {
    this.error_code_record = Ext.data.Record.create([
        {name: 'pt_error_id', type:'int'},
        {name: 'pt_error_code', type:'string'},
        {name: 'pt_error_description', type:'string'}
    ]);

    // Init basic properties
    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-pt-error-codes/get-codes',
        method: 'POST',
        autoLoad: true,
        
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'pt_error_id'
        }, this.error_code_record),
        
        sortInfo: {
            field: "pt_error_code",
            direction: "ASC"
        }
    });
    
    this.columns = [
        {
            header: 'Code',
            sortable: true,
            align: 'center',
            dataIndex: 'pt_error_code',
            width: 10
        }, {
            id: 'pt_error_description_column',
            header: 'Description',
            sortable: true,
            dataIndex: 'pt_error_description'
        }
    ];
    
    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('Add Code'),
            tooltip: 'Add new error code',

            // Place a reference in the GridPanel
            ref: '../addErrorBtn',
            handler: this.addError.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Code'),
            tooltip: 'Edit selected error code',

            // Place a reference in the GridPanel
            ref: '../editErrorBtn',
            disabled: true,
            handler: this.editError.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Code'),
            tooltip: 'Delete selected error code',

            // Place a reference in the GridPanel
            ref: '../deleteErrorBtn',
            disabled: true,
            handler: this.deleteError.createDelegate(this)
        }
    ];
    
    this.bbar = new Ext.PagingToolbar({
        pageSize: countErrorsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: '',
        emptyMsg: 'No codes to display'
    });
    
    
    PTErrorCodesGrid.superclass.constructor.call(this, {
        autoWidth: true,
        height: 500,
        autoExpandColumn: 'pt_error_description_column',
        stripeRows: true,
        cls: 'extjs-grid',
        loadMask: {msg: 'Loading...'},
        bodyStyle: 'background-color:#fff',
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),

        viewConfig: {
            emptyText: 'No error codes found',
            forceFit: true,
            enableRowBody: true
        }
    });

    this.getSelectionModel().on({
        'selectionchange': function (sm) {
            var selCodesCount = sm.getCount();
            if (selCodesCount == 1) {
                this.grid.editErrorBtn.enable();
            } else {
                this.grid.editErrorBtn.disable();
            }

            if (selCodesCount > 0) {
                this.grid.deleteErrorBtn.enable();
            } else {
                this.grid.deleteErrorBtn.disable();
            }
        }
    });
};

Ext.extend(PTErrorCodesGrid, Ext.grid.GridPanel, {
    showEditCodeWindow: function(data, thisGrid) {
        var fp = new Ext.FormPanel({
            layout: 'form',
            bodyStyle: 'padding:5px;',
            defaultType: 'textfield',
            items: [
                {
                    name:  'error-code-id',
                    xtype: 'hidden',
                    value:      data.pt_error_id
                }, {
                    name:       'error-code',
                    fieldLabel: 'Code',
                    width:      70,
                    allowBlank: false,
                    value:      data.pt_error_code
                }, {
                    name:       'error-description',
                    fieldLabel: 'Description',
                    width: 250,
                    allowBlank: false,
                    value:      data.pt_error_description
                }
            ]
        });

        var title = empty(data) ? 'Add new error code' : 'Edit error code';

        var win = new Ext.Window({
            title: title,
            layout: 'form',
            modal: true,
            width: 380,
            autoHeight: true,
            autoShow: true,
            resizable: false,
            items: fp,
            buttons: [
                {
                    text: 'Cancel',
                    handler: function() {
                        win.close();
                    }
                },
                {
                    text: 'Save',
                    cls: 'orange-btn',
                    handler: function() {
                        if(fp.getForm().isValid()){
                             win.getEl().mask('Saving...');

                             fp.getForm().submit({
                                 url: baseUrl + '/manage-pt-error-codes/save-code',

                                 success: function(form, action) {
                                     thisGrid.store.reload();
                                     win.close();
                                     
                                     Ext.simpleConfirmation.success(action.result.message);
                                 },

                                 failure: function(form, action) {
                                     if(!empty(action.result.message)) {
                                         Ext.simpleConfirmation.error(action.result.message);
                                     } else {
                                         Ext.simpleConfirmation.error('Cannot save info');
                                     }

                                     win.getEl().unmask();
                                 }
                             });
                        }
                    }
                }
            ]
        });
        win.show();
    },

    addError: function() {
        var rec = new this.error_code_record({});
        this.showEditCodeWindow(rec, this);
    },

    editError: function() {
        var rec = this.getSelectionModel().getSelected();
        this.showEditCodeWindow(rec.data, this);
    },

    deleteError: function() {
        var thisGrid = this;
        var rec = this.getSelectionModel().getSelected();

        var question = String.format(
            'Are you sure want to delete <i>{0}</i> error code?',
            rec.data.pt_error_description
        );

        Ext.MessageBox.buttonText.yes = 'Yes, delete';
        Ext.Msg.confirm('Please confirm', question, function(btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Removing...');

                Ext.Ajax.request({
                    url: baseUrl + '/manage-pt-error-codes/delete-code',
                    params: {
                        pt_error_id: Ext.encode(rec.data.pt_error_id)
                    },

                    success: function(res) {
                        Ext.getBody().unmask();

                        var result = Ext.decode(res.responseText);
                        if (result.success) {
                            // reload invoices list
                            Ext.simpleConfirmation.success(result.message);
                            thisGrid.store.reload();
                        } else {
                            Ext.simpleConfirmation.error(result.message);
                        }
                    },

                    failure: function() {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(
                            'Internal server error. Please try again.'
                        );
                    }
                });
            }
        });

    }
});