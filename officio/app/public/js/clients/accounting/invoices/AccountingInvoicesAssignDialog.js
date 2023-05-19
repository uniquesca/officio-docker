var AccountingInvoicesAssignDialog = function (config) {
    Ext.apply(this, config);

    var thisWindow = this;

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();

    var TransactionRecord = Ext.data.Record.create([
        {name: 'fee_id', type: 'string'},
        {name: 'real_id', type: 'int'},
        {name: 'fee_due_date', type: 'string'},
        {name: 'fee_due_timestamp', type: 'int'},
        {name: 'fee_description', type: 'string'},
        {name: 'fee_description_gst', type: 'string'},
        {name: 'fee_amount', type: 'string'},
        {name: 'fee_gst_province_id', type: 'int'},
        {name: 'fee_gst', type: 'string'},
        {name: 'fee_notes', type: 'string'}
    ]);

    this.selectedTotalDisplayField = new Ext.form.DisplayField({
        style: 'margin-right: 15px',
        value: formatMoney(thisWindow.caseTACurrency, 0, true)
    });

    this.windowStatus = new Ext.form.DisplayField({
        height: 23
    });

    var bbar;
    if (empty(thisWindow.invoicePaymentsAmount)) {
        bbar = {
            xtype: 'panel',
            cls: 'no-bbar-borders',
            style: 'margin-top: 10px',
            layout: 'column',
            items: [
                {
                    xtype: 'container',
                    columnWidth: 0.5,
                    items: this.windowStatus
                },
                {
                    xtype: 'container',
                    layout: 'table',
                    layoutConfig: {
                        tableAttrs: {
                            style: 'float: right; margin-right: 13px'
                        },
                        columns: 2
                    },
                    columnWidth: 0.5,
                    items: [
                        {
                            xtype: 'label',
                            style: 'margin-right: 10px',
                            text: _('Selected Total:')
                        },
                        this.selectedTotalDisplayField
                    ]
                }
            ]
        }
    } else {
        bbar = {
            xtype: 'panel',
            cls: 'no-bbar-borders',
            items: [
                {
                    xtype: 'toolbar',
                    cls: 'no-bbar-borders',
                    style: 'margin-top: 10px',
                    items: [
                        {
                            xtype: 'label',
                            text: _('Invoice Payment(s) Total:')
                        }, {
                            xtype: 'displayfield',
                            value: formatMoney(thisWindow.caseTACurrency, thisWindow.invoicePaymentsAmount, true)
                        }, '->', {
                            xtype: 'label',
                            text: _('Selected Total:')
                        }, this.selectedTotalDisplayField
                    ]
                },
                {
                    xtype: 'panel',
                    style: 'margin-top: 10px',
                    items: this.windowStatus
                }
            ]
        }
    }

    var genId = Ext.id();
    this.feesToAssignGrid = new Ext.grid.GridPanel({
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
            emptyText: _('There are no fees to assign.'),

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
                url: baseUrl + '/clients/accounting/get-payments-to-assign-invoice'
            }),

            baseParams: {
                member_id: thisWindow.caseId,
                ta_id: thisWindow.caseTAId,
                invoice_id: thisWindow.invoiceId
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, TransactionRecord)
        }),

        columns: [
            sm,
            {
                header: _('Due Date'),
                dataIndex: 'fee_due_timestamp', // use the timestamp for records sorting
                align: 'left',
                width: 150,
                renderer: function (val, p, record) {
                    return record.data.fee_due_date;
                }
            }, {
                id: expandCol,
                header: _('Description'),
                dataIndex: 'fee_description',
                align: 'left',
                width: 200,
                renderer: function (val, p, record) {
                    if (!empty(record.data['fee_gst']) && !empty(parseFloat(record.data['fee_gst']))) {
                        val += String.format(
                            "<div style='color:#666666; padding-top: 5px;'>{0}</div>",
                            record.data['fee_description_gst']
                        );
                    }

                    return val;
                }
            }, {
                header: _('Amount'),
                dataIndex: 'fee_amount',
                align: 'right',
                width: 100,
                renderer: function (val, p, record) {
                    if (empty(record.data['fee_gst']) || empty(parseFloat(record.data['fee_gst']))) {
                        return formatMoney(thisWindow.caseTACurrency, val, false);
                    }

                    return '<div>' + formatMoney(thisWindow.caseTACurrency, val, false) + '</div>' + '<div style="padding-top:5px;">' + formatMoney(thisWindow.caseTACurrency, record.data['fee_gst'], false) + '</div>';
                }
            }
        ],

        bbar: bbar
    });

    this.feesToAssignGrid.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.feesToAssignGrid.store.on('loadexception', this.checkLoadedResult.createDelegate(this));

    AccountingInvoicesAssignDialog.superclass.constructor.call(this, {
        title: _('Assign Fees or Disbursements to Invoice number ') + thisWindow.invoiceNumber,
        cls: 'invoice_assign_dialog',
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [
            {
                xtype: 'label',
                style: 'display: block; margin-bottom: 10px;',
                text: _('Fees and Disbursements not assigned to an invoice')
            },
            this.feesToAssignGrid
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
                ref: '../assignFeesButton',
                handler: this.assignInvoiceToFees.createDelegate(thisWindow)
            }
        ]
    });

    this.on('show', this.recalculateTotal.createDelegate(this));
    sm.on('selectionchange', this.recalculateTotal.createDelegate(this), this);
};

Ext.extend(AccountingInvoicesAssignDialog, Ext.Window, {
    showDialog: function () {
        var thisWindow = this;
        thisWindow.show();
        thisWindow.center();
    },

    checkLoadedResult: function () {
        var thisWindow = this;
        var thisGrid = thisWindow.feesToAssignGrid;
        if (thisGrid.store.reader.jsonData && thisGrid.store.reader.jsonData.message && !thisGrid.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', thisGrid.store.reader.jsonData.message);
            thisGrid.getEl().mask(msg);
        } else {
            thisGrid.getEl().unmask();

            // Preselect already assigned fees
            if (thisGrid.store.reader.jsonData.assignedFees.length) {
                var arrRows = [];
                Ext.each(thisGrid.store.reader.jsonData.assignedFees, function (feeId) {
                    var index = thisGrid.store.find('real_id', feeId);
                    if (index >= 0) {
                        arrRows.push(index);
                    }
                });

                thisGrid.getSelectionModel().selectRows(arrRows);
            }
        }
    },

    recalculateTotal: function () {
        var thisWindow = this;

        var totalSelected = 0;
        var recs = thisWindow.feesToAssignGrid.getSelectionModel().getSelections();
        for (var i = 0; i < recs.length; i++) {
            var gst = 0;
            if (!empty(recs[i]['data']['fee_gst']) && !empty(parseFloat(recs[i]['data']['fee_gst']))) {
                gst = parseFloat(recs[i]['data']['fee_gst']);
            }

            totalSelected += parseFloat(recs[i]['data']['fee_amount']) + gst;
        }

        var msg = '';
        var msgType = '';
        var booCorrectAmount = false;
        if (empty(thisWindow.invoicePaymentsAmount)) {
            if (empty(totalSelected)) {
                msg = _('Please select at least one fee');
                msgType = 'warning';
            } else {
                msgType = 'success';
                booCorrectAmount = true;
            }
        } else {
            if (empty(totalSelected)) {
                msg = _('Please select at least one fee');
                msgType = 'warning';
            } else if (totalSelected < parseFloat(thisWindow.invoicePaymentsAmount)) {
                msg = _('The selected total fees should be more or equal to the invoice payment(s) total.');
                msgType = 'error';
            } else {
                msgType = 'success';
                booCorrectAmount = true;
            }
        }
        var color = msgType == 'warning' ? 'orange' : (msgType == 'success' ? 'green' : 'red');

        this.selectedTotalDisplayField.setValue(
            String.format(
                '<div style="color: {0};">{1}</div>',
                color,
                formatMoney(thisWindow.caseTACurrency, totalSelected, true)
            )
        );

        this.windowStatus.setValue(
            empty(msg) ? '&nbsp;' :
                String.format(
                    '<div style="color: {0};">{1}{2}</div>',
                    color,
                    msgType == 'warning' ? '<i class="las la-exclamation-triangle"></i>' : (msgType == 'success' ? '<i class="las la-check"></i>' : '<i class="las la-exclamation-circle"></i>'),
                    msg
                )
        );

        thisWindow.assignFeesButton.setDisabled(!booCorrectAmount);
    },

    assignInvoiceToFees: function () {
        var thisWindow = this;

        var recs = thisWindow.feesToAssignGrid.getSelectionModel().getSelections();
        var arrSelectedFees = [];
        for (var i = 0; i < recs.length; i++) {
            arrSelectedFees.push(recs[i]['data']['fee_id']);
        }

        if (empty(arrSelectedFees)) {
            // Cannot be here
            return;
        }


        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/assign-invoice-to-fees',
            params: {
                member_id: Ext.encode(thisWindow.caseId),
                ta_id: Ext.encode(thisWindow.caseTAId),
                invoice_id: Ext.encode(thisWindow.invoiceId),
                fees: Ext.encode(arrSelectedFees)
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