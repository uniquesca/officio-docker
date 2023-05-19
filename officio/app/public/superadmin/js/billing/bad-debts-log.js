BadDebtsGrid = function(config) {
    Ext.apply(this, config);

    var thisGrid = this;

    // Init basic properties
    this.store = new Ext.data.GroupingStore({
        url: baseUrl + '/manage-bad-debts-log/get-companies',
        method: 'POST',
        autoLoad: true,
        
        remoteGroup:true,
        remoteSort: false,
        groupField: 'company_id',
        
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'invoice_id',
            fields: [
               {name: 'company_id',     type: 'int'},
               {name: 'company_name',   type: 'string'},
               {name: 'admin_name',     type: 'string'},
               {name: 'company_status', type: 'int'},
               
               {name: 'invoice_id',                 type: 'int'},
               {name: 'invoice_total',              type: 'float'},
               {name: 'invoice_subject',            type: 'string'},
               {name: 'invoice_date',               type: 'date', dateFormat: 'Y-m-d'},
               {name: 'invoice_extended_date_till', type: 'date', dateFormat: 'Y-m-d'},
               {name: 'invoice_error_code',         type: 'string'},
               {name: 'invoice_error_message',      type: 'string'}
            ]
        }),
        
        sortInfo: {
            field: "company_name",
            direction: "ASC"
        }
    });
    
    this.columns = [
        {
            header: 'Company Id',
            sortable: true,
            dataIndex: 'company_id',
            hidden: true,
            width: 50
        }, {
            header: 'Company Name',
            sortable: true,
            dataIndex: 'company_name',
            hidden: true,
            width: 50
        }, {
            header: 'Id',
            sortable: true,
            align: 'center',
            dataIndex: 'invoice_id',
            width: 10
        }, {
            id: 'invoice_subject',
            header: 'Subject',
            sortable: true,
            renderer: this.formatSubject.createDelegate(this),
            dataIndex: 'invoice_subject'
        }, {
            header: 'Total',
            sortable: true,
            renderer: 'usMoney',
            align: 'right',
            dataIndex: 'invoice_total',
            width: 15
        }, {
            header: 'Date',
            dataIndex: 'invoice_date',
            align: 'center',
            sortable: true,
            renderer: function(val) {
                return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
            },
            width: 20
        }, {
            header: 'Extended till',
            dataIndex: 'invoice_extended_date_till',
            align: 'center',
            sortable: true,
            renderer: function(val) {
                return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
            },
            width: 20
        }, {
            header: 'PT Error',
            sortable: true,
            dataIndex: 'invoice_error_message',
            width: 30
        }
    ];
    
    this.tbar = [
        {
            text: '<i class="las la-money-bill"></i>' + _('Charge Invoice'),
            tooltip: 'Charge selected invoice.',

            // Place a reference in the GridPanel
            ref: '../chargeInvoiceBtn',
            disabled: true,
            handler: this.chargeInvoice.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Invoices'),
            tooltip: 'Delete selected invoices.',

            // Place a reference in the GridPanel
            ref: '../deleteInvoiceBtn',
            disabled: true,
            handler: this.deleteInvoice.createDelegate(this)
        }, 
        '->', 
        {
            xtype: 'appGridSearch',
            width: 250,
            emptyText: 'Search company...',
            store: this.store
        }
    ];
    
    this.bbar = new Ext.PagingToolbar({
        pageSize: countInvoicesOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: '',
        emptyMsg: 'No invoices to display'
    });
    
    
    this.view = new Ext.grid.GroupingView({
        forceFit:true,
        groupTextTpl: '<span class=\'{[values.rs[0].data["company_status"] == 2 ? "invoice_company_name_suspended" : ""]}\'>' +
                          '{[ values.rs[0].data["company_name"] ]} ({[ values.rs[0].data["admin_name"] ]})' +
                          ' - {[values.rs.length]} {[values.rs.length > 1 ? "Invoices" : "Invoice"]}' +
                      '</span>',
        getRowClass: this.applyRowClass,
        emptyText: 'No companies with bad debts log were found.'
    });
    
    this.getSelectionModel().on({
        'selectionchange': function (sm) {
            var selInvoicesCount = sm.getCount();
            if (selInvoicesCount == 1) {
                this.grid.chargeInvoiceBtn.enable();
            } else {
                this.grid.chargeInvoiceBtn.disable();
            }
            
            if (selInvoicesCount > 0) {
                this.grid.deleteInvoiceBtn.enable();
            } else {
                this.grid.deleteInvoiceBtn.disable();
            }
        }
    });

    this.contextMenu = new Ext.menu.Menu({
        enableScrolling: false,
        items: [{
            text: 'Save error code',
            cls: 'log-menu-error-add',
            handler: function() {
                thisGrid.addErrorCode();
            }
        }]
    });

    BadDebtsGrid.superclass.constructor.call(this, {
        id: 'bad-debts-log-grid',
        autoWidth: true,
        autoExpandColumn: 'invoice_subject',
        stripeRows: true,
        cls: 'extjs-grid',
        loadMask: true
    });

    this.on('rowcontextmenu', this.onContextClick, this);
};

Ext.extend(BadDebtsGrid, Ext.grid.GridPanel, {
    onContextClick : function(obj, row, e){
        e.stopEvent();

        this.getSelectionModel().selectRow(row);
        var rec = this.getSelectionModel().getSelected();
        if(!empty(rec.data.invoice_error_code)) {
            this.contextMenu.showAt(e.getXY());
        }
    },

    addErrorCode: function() {
        var rec = this.getSelectionModel().getSelected();
        if(rec) {

            Ext.getBody().mask('Saving...');
            Ext.Ajax.request({
                url: baseUrl + '/manage-pt-error-codes/save-code',
                params: {
                    'error-code':        rec.data.invoice_error_code,
                    'error-description': rec.data.invoice_error_message
                },

                success: function(res) {
                    Ext.getBody().unmask();

                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        Ext.simpleConfirmation.success(result.message);
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
    },


    formatSubject: function(val, item, rec) {
        return String.format(
            '<a href="{0}/manage-company/edit?company_id={1}" title="{2}" class="normal_link" target="_blank">{2}</a>',
            baseUrl,
            rec.data.company_id,
            val
        );
    },

    // Apply custom class for row in relation to several criteria
    applyRowClass: function(record) {
        return record.data.company_status == 2 ? 'invoice_company_suspended' : '';
    },
    
    
    // Delete selected invoices
    deleteInvoice: function() {
        var thisGrid = this;
        var sel = this.getSelectionModel().getSelections();
        if(empty(sel.length)) {
            return false;
        }
        
        
        var arrInvoices = [];
        for(i=0; i<sel.length; i++) {
            arrInvoices.push(sel[i].data.invoice_id);
        }
        
        var question = String.format(
            'Are you sure want to delete {0} selected {1}?',
            sel.length,
            sel.length == 1 ? 'invoice' : 'invoices'
        );

        Ext.MessageBox.buttonText.yes = 'Yes, delete';
        Ext.Msg.confirm('Please confirm', question, function(btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Removing...');

                Ext.Ajax.request({
                    url: baseUrl + '/manage-bad-debts-log/delete-invoices',
                    params: {
                        invoices: Ext.encode(arrInvoices)
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
    },
    
    
    // Charge selected invoice (one at once)
    chargeInvoice: function() {
        var thisGrid = this;
        var sel = this.getSelectionModel().getSelections();
        if(sel.length != 1) {
            return false;
        }

        Ext.getBody().mask('Sending request, please wait...');

        Ext.Ajax.request({
            url: baseUrl + '/manage-bad-debts-log/charge-invoice',
            params: {
                invoice: Ext.encode(sel[0].data.invoice_id)
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