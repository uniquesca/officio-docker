var AccountingFeesAssignDialog = function (config) {
    Ext.apply(this, config);

    var thisWindow = this;

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel({
        singleSelect: true
    });

    var oInvoiceRecord = Ext.data.Record.create([
        {name: 'invoice_id', type: 'int'},
        {name: 'invoice_date', type: 'date', dateFormat: Date.patterns.ISO8601Short},
        {name: 'invoice_num', type: 'string'},
        {name: 'invoice_amount', type: 'float'}
    ]);

    var genId = Ext.id();
    this.invoicesToAssignGrid = new Ext.grid.GridPanel({
        id: genId,
        width: 600,
        height: 250,
        autoScroll: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: expandCol,
        stripeRows: true,
        cls: 'hidden-bottom-scroll',
        sm: sm,

        viewConfig: {
            emptyText: _('There are no invoices to assign.'),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 18,
            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth) {
                    // no scroller
                    this.scrollOffset = 2;
                } else {
                    // there is a scroller
                    this.scrollOffset = this.orgScrollOffset;
                }
                this.fitColumns(false);
            }
        },

        store: new Ext.data.Store({
            autoLoad: true,
            remoteSort: true,

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/clients/accounting/get-invoices-to-assign-fee'
            }),

            baseParams: {
                member_id: Ext.encode(thisWindow.caseId),
                ta_id: Ext.encode(thisWindow.caseTAId),
                fees: Ext.encode(thisWindow.fees)
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, oInvoiceRecord)
        }),

        columns: [
            sm,
            {
                header: _('Date'),
                dataIndex: 'invoice_date',
                align: 'left',
                fixed: true,
                width: 150,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull)
            }, {
                id: expandCol,
                header: _('Invoice #'),
                dataIndex: 'invoice_num',
                align: 'left',
                width: 200
            }, {
                header: _('Amount'),
                dataIndex: 'invoice_amount',
                align: 'right',
                width: 100,
                renderer: function (val, p, record) {
                    return formatMoney(thisWindow.caseTACurrency, val, false);
                },
            }
        ]
    });

    this.invoicesToAssignGrid.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.invoicesToAssignGrid.store.on('loadexception', this.checkLoadedResult.createDelegate(this));

    AccountingFeesAssignDialog.superclass.constructor.call(this, {
        title: _('Assign Fees or Disbursements to Invoice'),
        cls: 'fee_assign_dialog',
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [
            {
                xtype: 'label',
                style: 'display: block; margin-bottom: 10px;',
                text: thisWindow.fees.length === 1 ? _('Please link this fee to one of these invoices:') : _('Please link these fees to one of these invoices:')
            },
            this.invoicesToAssignGrid,
            {
                xtype: 'label',
                style: 'display: block; margin-top: 10px;',
                text: _('You can choose one or more fees to link to one of the above invoices.')
            }, {
                xtype: 'label',
                style: 'display: block; margin-top: 5px;',
                text: _('The total of the fees must be equal to the invoice amount.')
            }
        ],

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Assign'),
                cls: 'orange-btn',
                disabled: true,
                ref: '../assignInvoicesButton',
                handler: this.assignFeeToInvoice.createDelegate(thisWindow)
            }
        ]
    });

    sm.on('selectionchange', this.recalculateTotal.createDelegate(this), this);
};

Ext.extend(AccountingFeesAssignDialog, Ext.Window, {
    showDialog: function () {
        var thisWindow = this;
        thisWindow.show();
        thisWindow.center();
    },

    checkLoadedResult: function() {
        var thisWindow = this;
        var thisGrid = thisWindow.invoicesToAssignGrid;
        if (thisGrid.store.reader.jsonData && thisGrid.store.reader.jsonData.message && !thisGrid.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', thisGrid.store.reader.jsonData.message);
            thisGrid.getEl().mask(msg);
        } else {
            thisGrid.getEl().unmask();
        }
    },

    recalculateTotal: function () {
        var thisWindow = this;

        var totalSelected = 0;
        var recs = thisWindow.invoicesToAssignGrid.getSelectionModel().getSelections();
        for (var i = 0; i < recs.length; i++) {
            totalSelected += parseFloat(recs[i]['data']['invoice_amount']);
        }

        var booCorrectAmount = !empty(totalSelected) && (totalSelected == parseFloat(thisWindow.feesAmount));

        thisWindow.assignInvoicesButton.setDisabled(!booCorrectAmount);
    },

    assignFeeToInvoice: function () {
        var thisWindow = this;

        var selRow = thisWindow.invoicesToAssignGrid.getSelectionModel().getSelected();
        if (empty(selRow)) {
            // Cannot be here
            return;
        }


        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/assign-fees-to-invoice',
            params: {
                member_id: Ext.encode(thisWindow.caseId),
                ta_id: Ext.encode(thisWindow.caseTAId),
                invoice_id: Ext.encode(selRow['data']['invoice_id']),
                fees: Ext.encode(thisWindow.fees)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.caseId).reloadClientAccounting();

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    }, 750);
                } else {
                    // Show an error message
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error('Can\'t save info');
            }
        });
    }
});