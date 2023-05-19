var CompaniesFailedInvoicesWindow = function(arrInvoicesData) {
    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-company/get-companies',
        method: 'POST',

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            fields: [
                {name: 'company_invoice_id', type: 'int'},
                {name: 'invoice_number',     type: 'string'},
                {name: 'invoice_date',       type: 'date', dateFormat: 'Y-m-d'},
                {name: 'subject',            type: 'string'},
                {name: 'total',              type: 'float'}
            ]
        })
    });

    this.grid = new Ext.grid.GridPanel({
        store: this.store,
        columns: [
            {
                header: 'Number',
                width: 100,
                sortable: true,
                dataIndex: 'invoice_number'
            }, {
                header: 'Date',
                width: 80,
                sortable: true,
                dataIndex: 'invoice_date',
                renderer: function(val, row, rec) {
                    return empty(val) ? '-' : Ext.util.Format.date(val, dateFormatFull);
                }
            }, {
                id:'invoice_subject',
                header: 'Subject',
                width: 75,
                sortable: true,
                dataIndex: 'subject'
            }, {
                header: 'Amount',
                width: 75,
                sortable: true,
                dataIndex: 'total',
                renderer: 'usMoney'
            }, {
                header: 'Charge',
                width: 65,
                align: 'center',
                renderer: this.renderAction.createDelegate(this),
                dataIndex: 'company_invoice_id'
            }
        ],
        stripeRows: true,
        autoExpandColumn: 'invoice_subject',
        height: 350,
        autoWidth: true
    });

    CompaniesFailedInvoicesWindow.superclass.constructor.call(this, {
        id: 'charge-invoice-window',
        title: 'Failed invoices list',
        layout:'fit',
        modal: true,
        width: 600,
        height: 350,
        plain: true,

        items: this.grid,

        buttonAlign: 'center',
        buttons: [
            {
                text: 'Cancel',
                handler: this.close.createDelegate(this, [])
            }, {
                text: 'OK',
                cls: 'orange-btn',
                handler: function () {
                    var question = String.format(
                        'Uncharged invoices will be discounted to the case. Are you sure you want to continue?'
                    );

                    Ext.Msg.confirm('Please confirm', question, function (btn) {
                        if (btn == 'yes') {
                            var grid = Ext.getCmp('companies-grid');
                            grid.sendRequestToUpdateStatus(true);
                        }
                    });
                }
            }
        ]
    });

    this.on('beforeshow', this.loadInvoicesList.createDelegate(this, [arrInvoicesData]));
    this.on('close', this.cancelStatusUpdate.createDelegate(this, []));
};

Ext.extend(CompaniesFailedInvoicesWindow, Ext.Window, {
    loadInvoicesList: function(arrInvoicesData) {
        this.grid.store.loadData(arrInvoicesData);
    },

    cancelStatusUpdate: function() {
        var grid = Ext.getCmp('companies-grid');
        grid.updateToolbarButtons();
    },

    renderAction: function(val, cell, rec) {
        return String.format(
            '<img src="{1}/images/icons/money.png" /> <a href="#" title="Charge Invoice" onclick="Ext.getCmp(\'charge-invoice-window\').chargeInvoice({0}); return false;" class="normal_link">Charge</a>',
            rec.data.company_invoice_id,
            topBaseUrl
        );
    },

    chargeInvoice: function(invoiceId) {
        var win = Ext.getCmp('charge-invoice-window');

        // Charge invoice
        // Check if there are no failed invoices - send request to update company status
        win.getEl().mask('Charging...');
        Ext.Ajax.request({
            url: baseUrl + '/manage-bad-debts-log/charge-invoice',

            params: {
                invoice: invoiceId,
                load_failed_invoices: true
            },

            success: function(f) {
                var result = Ext.decode(f.responseText);
                if (result.success) {
                    // Get failed invoices list, if it is empty - update company status
                    if(result.totalCount > 0) {
                        // Load refreshed invoices list
                        win.loadInvoicesList(result);
                    } else {
                        win.close();

                        // Update company status
                        var grid = Ext.getCmp('companies-grid');
                        grid.sendRequestToUpdateStatus();
                    }
                } else {
                    Ext.simpleConfirmation.error(result.message);
                }

                win.getEl().unmask();
            },

            failure: function() {
                Ext.simpleConfirmation.error('Cannot charge invoice. Please try again later.');
                win.getEl().unmask();
            }
        });
    }
});