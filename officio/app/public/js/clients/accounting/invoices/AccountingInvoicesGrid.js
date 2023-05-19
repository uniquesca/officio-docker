var AccountingInvoicesGrid = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.booReadOnlyInvoicesGrid = false;

    var thisGrid = this;
    var gridId = Ext.id();

    var oInvoiceRecord = Ext.data.Record.create([
        {name: 'invoice_id', type: 'int'},
        {name: 'invoice_company_ta_id', type: 'int'},
        {name: 'invoice_date', type: 'date', dateFormat: Date.patterns.ISO8601Short},
        {name: 'invoice_num', type: 'string'},
        {name: 'invoice_amount', type: 'float'},
        {name: 'invoice_outstanding_amount', type: 'float'},
        {name: 'invoice_cleared_details'},
        {name: 'invoice_history', type: 'string'},
        {name: 'invoice_payments'},
        {name: 'invoice_assigned_fees_and_payments'},
        {name: 'invoice_payments_amount', type: 'float'},
        {name: 'invoice_currency', type: 'string'},
        {name: 'invoice_notes', type: 'string'},
        {name: 'invoice_details', type: 'string'},
        {name: 'invoice_can_be_deleted', type: 'boolean'},
        {name: 'invoice_is_assigned_to_fee', type: 'boolean'}
    ]);

    // If changed -> please change in the css for invoice-info table too
    this.dateColumnWidth = 130;
    this.invoiceColumnWidth = 200;
    this.amountColumnWidth = 150;
    this.statusColumnWidth = 250;

    // Will be used to show the "Available balance" link with such padding
    this.mainColumnsWidth = 15 + this.dateColumnWidth + this.invoiceColumnWidth + this.amountColumnWidth + this.statusColumnWidth;

    this.rowExpander = new Ext.ux.grid.RowExpander({
        tplContent: new Ext.Template('{invoice_assigned_fees_and_payments}'),
        renderer: function (v, p, record) {
            if (!empty(record.get('invoice_assigned_fees_and_payments'))) {
                p.cellAttr = 'rowspan="2"';
                return '<div class="x-grid3-row-expander"></div>';
            } else {
                p.id = '';
                return '&#160;';
            }
        },
        expandOnEnter: false,
        expandOnDblClick: false
    });

    var expandCol = Ext.id();
    this.booShowFullCurrency = owner.arrMemberTA.length > 1;
    var sm = new Ext.grid.CheckboxSelectionModel();
    var cm = new Ext.grid.ColumnModel({
        defaults: {
            menuDisabled: true
        },

        defaultSortable: true,
        columns: [
            this.rowExpander,
            {
                header: _('Date'),
                dataIndex: 'invoice_date',
                align: 'left',
                width: this.dateColumnWidth,
                fixed: true,
                renderer: function (val, p, record) {
                    return Ext.util.Format.date(val, dateFormatFull);
                }
            }, {
                header: _('Invoice #'),
                dataIndex: 'invoice_num',
                align: 'left',
                width: this.invoiceColumnWidth,
                renderer: function (val, p, record) {
                    var currency = '';
                    if (val == 'Statement' && thisGrid.booShowFullCurrency) {
                        currency = ' (' + formatMoney(record.get('invoice_currency'), 0, true, true).replace(/0\.00/, '') + ')';
                    }

                    var invoiceLink = String.format(
                        "<a href='#' class='blklink view_invoice_details' onclick='return false;' title='{0}'>{1}{2}</a>",
                        _('View Invoice Details'),
                        val,
                        currency
                    );

                    return invoiceLink + String.format(
                        "<a href='#' class='blklink' onclick='return false;' style='padding-left: 5px' title='{0}'>{1}</a>",
                        _('View Invoice Menu'),
                        '<i class="las la-caret-square-down view_invoice"></i>'
                    );
                }
            }, {
                header: _('Amount'),
                dataIndex: 'invoice_amount',
                align: 'right',
                renderer: function (val, p, record) {
                    return formatMoney(record.get('invoice_currency'), val, true, thisGrid.booShowFullCurrency);
                },
                width: this.amountColumnWidth
            }, {
                header: _('Status'),
                dataIndex: 'invoice_history',
                width: this.statusColumnWidth,
                renderer: function (val, p, record) {
                    var strResult;
                    if (record.data['invoice_amount'] > 0) {
                        var outstanding = record.data['invoice_outstanding_amount'];
                        if (outstanding == 0) {
                            strResult = String.format(
                                "<div ext:qtip='{0}' ext:qwidth='450' style='cursor: help; color: green'><i class='las la-check'></i> {1}</div>",
                                val.replace("'", "′"),
                                _('Paid')
                            );
                        } else if (outstanding < 0) {
                            strResult = String.format(
                                "<div ext:qtip='{0}' ext:qwidth='450' style='cursor: help; color: orange'><i class='las la-exclamation-triangle'></i> {1} ({2})</div>",
                                val.replace("'", "′"),
                                _('Overpaid'),
                                formatMoney(record.get('invoice_currency'), -1 * outstanding, false, thisGrid.booShowFullCurrency)
                            );
                        } else {
                            strResult = String.format(
                                "<span ext:qtip='{0}' ext:qwidth='450' style='color: orange; {1}'><i class='las la-exclamation-triangle'></i> {2} ({3})</span>",
                                val.replace("'", "′"),
                                record.data['invoice_amount'] == outstanding ? '' : 'cursor: help;',
                                _('Outstanding'),
                                formatMoney(record.get('invoice_currency'), outstanding, false, thisGrid.booShowFullCurrency)
                            );
                        }
                    }

                    return strResult;
                },
            }, {
                id: expandCol,
                header: _('Notes'),
                dataIndex: 'invoice_notes',
                width: 200,
                renderer: function (val, p, record) {
                    var strResult = '';

                    if (!record.data['invoice_is_assigned_to_fee']) {
                        strResult += String.format(
                            "<a href='#' class='blklink assign_invoice_to_fees' onclick='return false;' ext:qtip='{0}'>{1}</a>",
                            _('Officio does not know to which past due fees in the above table this invoice should be applied to. Please click on Assign Fees to update the invoice.'),
                            '<i class="las la-balance-scale-right blink" style="color: red"></i>' + _('Assign Fees')
                        );
                    }

                    if (!empty(record.data['invoice_details'])) {
                        strResult += String.format(
                            '<div>{0}{1}</div>',
                            _('Description: '),
                            record.data['invoice_details']
                        );
                    }

                    if (!empty(val)) {
                        strResult += String.format(
                            "<a href='#' class='blklink view_invoice_notes' style='{0}' onclick='return false;'>{1}</a>",
                            empty(strResult) ? '' : 'display: block; margin-top: 10px',
                            Ext.util.Format.nl2br(val)
                        );
                    }

                    return strResult;
                }
            }
        ]
    });

    this.store = new Ext.data.Store({
        url: baseUrl + '/clients/accounting/get-client-invoices',
        autoLoad: true,
        remoteSort: false,
        baseParams: {
            member_id: thisGrid.caseId,
            start: 0,
            limit: arrApplicantsSettings.accounting.recordsOnPage
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, oInvoiceRecord),

        listeners: {
            load: function (thisStore) {
                if (thisStore.getTotalCount() > arrApplicantsSettings.accounting.recordsOnPage) {
                    pagingBar.show();
                }

                $('#' + gridId + ' .x-grid3-header').toggle(!empty(thisStore.getTotalCount()));
            }
        }
    });
    this.store.setDefaultSort('invoice_date', 'ASC');
    this.store.on('load', this.updateTotals.createDelegate(this));

    var pagingBar = new Ext.PagingToolbar({
        hidden: true,
        pageSize: arrApplicantsSettings.accounting.recordsOnPage,
        store: this.store
    });

    var paymentSchedulerTBar = [
        {
            xtype: 'label',
            cls: 'accounting_label',
            html: _('Invoices')
        }, {
            text: _('Pay Now'),
            tooltip: _("Mark the sekected client's invoice as PAID."),
            cls: 'main-btn',
            hidden: !checkIfCanEdit() || !thisGrid.owner.hasAccessToAccounting('invoices', 'pay_now'),
            ref: '../payNowBtn',
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);
                thisGrid.payInvoice();
            }
        }, {
            text: '<i class="las la-envelope"></i>' + _('Email Invoice'),
            ref: '../emailInvoiceBtn',
            hidden: true, // hide by default, show only if there is one selected item
            handler: thisGrid.emailInvoice.createDelegate(this)
        }, {
            text: '<i class="las la-comment"></i>' + _('Note'),
            ref: '../changeInvoiceNoteBtn',
            hidden: true, // hide by default, show only if there is one selected item
            handler: thisGrid.showInvoiceNoteDialog.createDelegate(this)
        }, '->', {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);
                thisGrid.reloadInvoicesList();
            }
        }, {
            text: String.format('<i class="lar la-minus-square" title="{0}"></i>', _('Hide invoices')),
            handler: function () {
                var booExpanded = $('#' + gridId + ' .x-grid3-viewport').is(":visible");
                if (booExpanded) {
                    this.setText(String.format('<i class="lar la-plus-square" title="{0}"></i>', _('Show invoices')));
                } else {
                    this.setText(String.format('<i class="lar la-minus-square" title="{0}"></i>', _('Hide invoices')));
                }

                show_hide_gridpanel(thisGrid.caseId, gridId, !booExpanded);
            }
        }
    ];


    this.totalPanel = new Ext.Panel({
        cls: 'summary-info-with-spaces'
    });

    AccountingInvoicesGrid.superclass.constructor.call(this, {
        id: gridId,
        autoExpandColumn: expandCol,
        cm: cm,
        plugins: this.rowExpander,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        stateful: false,
        autoWidth: true,
        autoHeight: true,
        stripeRows: true,
        buttonAlign: 'left',
        cls: 'extjs-grid',
        viewConfig: {
            deferEmptyText: _('No entries found.'),
            emptyText: _('No entries found.')
        },
        loadMask: true,
        tbar: paymentSchedulerTBar,
        bbar: [
            pagingBar,
            this.totalPanel
        ]
    });

    thisGrid.getSelectionModel().on('selectionchange', function () {
        var sel = thisGrid.getSelectionModel().getSelections();
        thisGrid.emailInvoiceBtn.setVisible(sel.length === 1 && allowedPages.has('email'));
        thisGrid.changeInvoiceNoteBtn.setVisible(sel.length === 1 && !thisGrid.booReadOnlyInvoicesGrid && !is_client);
    }, thisGrid);

    this.on('rowcontextmenu', this.onRowContextMenu, this);
    this.on('cellclick', this.onCellClick, this);
};

Ext.extend(AccountingInvoicesGrid, Ext.grid.GridPanel, {
    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var thisGrid = this;
        var record = thisGrid.getStore().getAt(rowIndex);

        if ($(e.getTarget()).hasClass('view_invoice_details')) {
            thisGrid.viewHtmlInvoice('view_invoice');
        } else if ($(e.getTarget()).hasClass('view_invoice_notes')) {
            thisGrid.showInvoiceNoteDialog();
        } else if ($(e.getTarget()).hasClass('view_invoice')) {
            thisGrid.onRowContextMenu(grid, rowIndex, e);
        } else if ($(e.getTarget()).hasClass('assign_invoice_to_fees')) {
            var wnd = new AccountingInvoicesAssignDialog({
                caseId: thisGrid.caseId,
                caseTAId: record.data.invoice_company_ta_id,
                caseTACurrency: Ext.getCmp('accounting_invoices_panel_' + thisGrid.caseId).getCurrencyByTAId(record.data.invoice_company_ta_id),
                invoiceId: record.data.invoice_id,
                invoiceAmount: record.data.invoice_amount,
                invoicePaymentsAmount: record.data.invoice_payments_amount,
                invoiceNumber: record.data.invoice_num
            });

            wnd.showDialog();
        }
    },

    canAllSelectedBeDeleted: function () {
        var thisGrid = this;
        var sel = thisGrid.getSelectionModel().getSelections();

        var booAllCanBeEdited = false;
        if (sel.length >= 1) {
            booAllCanBeEdited = true;
            var arrInvoices = thisGrid.getSelectedInvoices();
            Ext.each(arrInvoices, function (oInvoice) {
                if (!oInvoice.booCanBeDeleted) {
                    booAllCanBeEdited = false;
                }
            });
        }

        return booAllCanBeEdited;
    },

    getSelectedInvoices: function (booGetIdsOnly) {
        var grid = this;
        var recs = grid.getSelectionModel().getSelections();
        if (recs.length === 0) {
            Ext.simpleConfirmation.msg(_('Info'), _('Please select an invoice(s)'));
            return false;
        }

        var recIds = [];
        for (var i = 0; i < recs.length; i++) {
            if (booGetIdsOnly) {
                recIds.push(recs[i].data.invoice_id);
            } else {
                recIds.push({
                    id: recs[i].data.invoice_id,
                    type: 'invoice',
                    booCanBeDeleted: recs[i].data.invoice_can_be_deleted
                });
            }
        }
        return recIds;
    },

    onRowContextMenu: function (thisGrid, rowIndex, e) {
        e.stopEvent();
        thisGrid.getSelectionModel().selectRow(rowIndex);
        var rec = thisGrid.getStore().getAt(rowIndex);

        var arrMenuItems = [
            {
                text: '<i class="las la-file-alt"></i>' + _('View Invoice'),
                handler: thisGrid.viewInvoice.createDelegate(this)
            }, {
                text: '<i class="las la-envelope"></i>' + _('Email Invoice'),
                hidden: !allowedPages.has('email'),
                handler: thisGrid.emailInvoice.createDelegate(this)
            }, {
                text: '<i class="las la-save"></i>' + _('Save to Documents'),
                hidden: thisGrid.booReadOnlyInvoicesGrid || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: thisGrid.saveInvoiceAsDoc.createDelegate(this)
            }, {
                text: '<i class="las la-file-export"></i>' + _('Legacy Email Templates'),
                hidden: !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: thisGrid.generateLegacyInvoice.createDelegate(this)
            }, {
                text: '<i class="las la-plus"></i>' + _('Assign Fees'),
                hidden: thisGrid.booReadOnlyInvoicesGrid || rec.data['invoice_is_assigned_to_fee'] || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: thisGrid.assignInvoiceToFee.createDelegate(this)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Invoice'),
                hidden: !thisGrid.canAllSelectedBeDeleted() || thisGrid.booReadOnlyInvoicesGrid || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: this.showInvoiceErrorCorrection.createDelegate(this)
            }, {
                xtype: 'menuseparator',
                hidden: thisGrid.booReadOnlyInvoicesGrid || !rec.data['invoice_is_assigned_to_fee'] || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
            }, {
                text: '<i class="las la-times"></i>' + _('Remove All Assigned Fees'),
                hidden: thisGrid.booReadOnlyInvoicesGrid || !rec.data['invoice_is_assigned_to_fee'] || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: thisGrid.unassignFeesFromInvoice.createDelegate(this)
            }, {
                text: '<i class="las la-edit"></i>' + _('Change Assigned Fees'),
                hidden: thisGrid.booReadOnlyInvoicesGrid || !rec.data['invoice_is_assigned_to_fee'] || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
                handler: thisGrid.assignInvoiceToFee.createDelegate(this)
            }
        ];

        var arrPaymentsMenu = [];
        Ext.each(rec.data.invoice_payments, function (oPayment) {
            if (oPayment.can_be_deleted && !thisGrid.booReadOnlyInvoicesGrid) {
                arrPaymentsMenu.push({
                    text: _('Paid ') + oPayment.amount + _(' on ') + oPayment.date + ' <i class="las la-trash" style="margin-left: 10px;"></i>',
                    handler: function () {
                        var question = String.format(
                            _('Are you sure you want to delete the payment of {0} made on {1}?'),
                            oPayment.amount,
                            oPayment.date
                        );

                        Ext.Msg.confirm(_('Confirm Delete of Payment'), question, function (btn) {
                            if (btn === 'yes') {
                                thisGrid.deleteInvoicePayment(oPayment.id);
                            }
                        });
                    }
                })
            } else {
                arrPaymentsMenu.push({
                    text: _('Paid ') + oPayment.amount + _(' on ') + oPayment.date,
                    handler: function () {
                        Ext.simpleConfirmation.info(_('This payment is part of an existing reconciliation report and cannot be deleted.'));
                    }
                });
            }
        });

        if (arrPaymentsMenu.length) {
            arrMenuItems.push('-');
            arrMenuItems.push({
                text: '<i class="las la-coins"></i>' + _('Delete Payments'),
                menu: {
                    enableScrolling: false,
                    items: arrPaymentsMenu
                }
            });
        }

        // Context menu
        var contextMenuFT = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            enableScrolling: false,
            items: arrMenuItems
        });

        contextMenuFT.showAt(e.getXY());
    },

    payInvoice: function () {
        var thisGrid = this;

        var strError = '';
        var recs = thisGrid.getSelectionModel().getSelections();
        if (empty(strError) && empty(recs.length)) {
            strError = _('Please select an invoice with the outstanding balance.');
        }

        if (empty(strError) && recs.length != 1) {
            strError = _('Please select only one invoice with the outstanding balance.');
        }

        var record = recs[0];
        if (empty(strError) && empty(record.data['invoice_outstanding_amount'])) {
            strError = _('All payments were done for the selected invoice.');
        }

        if (!empty(strError)) {
            Ext.simpleConfirmation.warning(strError);
            return false;
        }

        var wnd = new MarkAsPaidDialog({
            member_id: thisGrid.caseId,
            ta_id: record.data['invoice_company_ta_id'],
            invoice_id: record.data['invoice_id']
        });
        wnd.showMarkAsPaidDialog();
    },

    unassignFeesFromInvoice: function () {
        var thisGrid = this;

        var selRecord = thisGrid.getSelectionModel().getSelected();
        if (empty(selRecord)) {
            Ext.simpleConfirmation.msg(_('Info'), _('Please select one invoice'));
            return false;
        }

        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to unassign Fees?'), function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Processing...'));
                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/unassign-invoice-fees',
                    params: {
                        caseId: Ext.encode(thisGrid.caseId),
                        caseTAId: Ext.encode(selRecord['data']['invoice_company_ta_id']),
                        invoiceId: Ext.encode(selRecord['data']['invoice_id'])
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (!resultData.success) {
                            Ext.simpleConfirmation.error(resultData.message);
                            Ext.getBody().unmask();
                        } else {
                            Ext.getBody().mask(_('Done!'));
                            setTimeout(function () {
                                Ext.getBody().unmask();
                                thisGrid.owner.reloadClientAccounting();
                            }, 750);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Cannot unassign fees. Please try again later.'));
                    }
                });
            }
        });
    },

    assignInvoiceToFee: function () {
        var thisGrid = this;

        var selRecord = thisGrid.getSelectionModel().getSelected();
        if (empty(selRecord)) {
            Ext.simpleConfirmation.msg(_('Info'), _('Please select one invoice'));
            return false;
        }

        var wnd = new AccountingInvoicesAssignDialog({
            caseId: thisGrid.caseId,
            caseTAId: selRecord['data']['invoice_company_ta_id'],
            caseTACurrency: Ext.getCmp('accounting_invoices_panel_' + thisGrid.caseId).getCurrencyByTAId(selRecord['data']['invoice_company_ta_id']),
            invoiceId: selRecord['data']['invoice_id'],
            invoiceAmount: selRecord['data']['invoice_amount'],
            invoicePaymentsAmount: selRecord['data']['invoice_payments_amount'],
            invoiceNumber: selRecord['data']['invoice_num']
        });

        wnd.showDialog();
    },

    generateLegacyInvoice: function () {
        var thisGrid = this;
        var arrSelected = thisGrid.getSelectedInvoices(true);
        if (empty(arrSelected)) {
            return;
        }

        var wnd = new AccountingInvoicesLegacyInvoiceDialog({
            caseId: thisGrid.caseId,
            invoiceId: arrSelected[0]
        }, thisGrid);
        wnd.showDialog();
    },

    viewHtmlInvoice: function (mode) {
        var thisGrid = this;

        var selRecord = thisGrid.getSelectionModel().getSelected();
        if (empty(selRecord)) {
            Ext.simpleConfirmation.msg(_('Info'), _('Please select one invoice'));
            return false;
        }

        var wnd = new GenerateInvoiceFromTemplateDialog({
            arrFees: [],
            arrPSRecords: [],
            member_id: thisGrid.caseId,
            ta_id: selRecord.data.invoice_company_ta_id,
            invoice_id: selRecord.data.invoice_id,
            invoice_amount: selRecord.data.invoice_amount,
            invoice_payments_amount: selRecord.data.invoice_payments_amount,
            invoice_number: selRecord.data.invoice_num,
            invoice_outstanding_amount: selRecord.data.invoice_outstanding_amount,
            booReadOnly: thisGrid.booReadOnlyInvoicesGrid || !checkIfCanEdit() || !thisGrid.owner.hasAccessToAccounting('invoices', 'pay_now'),
            invoice_mode: mode
        }, thisGrid);

        wnd.showDialog();
    },

    viewInvoice: function () {
        var thisGrid = this;
        var arrSelected = thisGrid.getSelectedInvoices(true);
        if (empty(arrSelected)) {
            return;
        }

        var invoiceId = arrSelected[0];
        Ext.getBody().mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/check-invoice-pdf-exists',
            params: {
                member_id: thisGrid.caseId,
                invoice_id: invoiceId
            },

            success: function (result) {
                Ext.getBody().unmask();
                var resultData = Ext.decode(result.responseText);
                if (empty(resultData.error)) {
                    if (resultData.file_exists) {
                        var oParams = {
                            member_id: thisGrid.caseId,
                            invoice_id: invoiceId,
                            invoice_path: '',
                            download: 0
                        };
                        Ext.ux.Popup.show(baseUrl + '/clients/accounting/get-invoice-pdf?' + $.param(oParams), true);
                    } else {
                        thisGrid.viewHtmlInvoice('view_invoice');
                    }
                } else {
                    Ext.simpleConfirmation.error(resultData.error);
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(_('Cannot open invoice document. Please try later'));
            }
        });
    },

    showInvoiceNoteDialog: function () {
        var thisGrid = this;
        var arrSelected = thisGrid.getSelectedInvoices(true);
        if (empty(arrSelected)) {
            return;
        }

        var selRecord = thisGrid.getSelectionModel().getSelected();
        var wnd = new AccountingInvoicesNotesDialog({
            booReadOnly: is_client,
            caseId: thisGrid.caseId,
            caseTAId: selRecord['data']['invoice_company_ta_id'],
            invoiceId: selRecord['data']['invoice_id'],
            invoiceNote: selRecord['data']['invoice_notes']
        });

        wnd.show();
        wnd.center();
    },

    emailInvoice: function () {
        this.viewHtmlInvoice('email_invoice');
    },

    saveInvoiceAsDoc: function () {
        this.viewHtmlInvoice('save_to_documents');
    },

    showInvoiceErrorCorrection: function () {
        var thisGrid = this;

        var arrInvoices = thisGrid.getSelectedInvoices();
        if (!arrInvoices) {
            return;
        }

        // Don't allow deleting/reversing invoice(s) in specific situations
        var invoicesCount = arrInvoices.length;
        if (invoicesCount > 1) {
            Ext.simpleConfirmation.error(_('Please select only one invoice.'));
        }

        if (!thisGrid.canAllSelectedBeDeleted()) {
            Ext.simpleConfirmation.error(_('This invoice cannot be deleted.'));
            return;
        }

        var rec = thisGrid.getSelectionModel().getSelected();
        var confirmationMessage;
        if (rec.data.invoice_outstanding_amount != rec.data.invoice_amount) {
            // There were payments
            confirmationMessage = _(
                'There are payments made towards this invoice. ' +
                'By deleting this invoice, you will be deleting the records for all those payments.<br><br>' +
                'Are you sure you want to delete the invoice and the following related payments:<br>'
            ) + implode('<br>', rec.data.invoice_history);
        } else {
            // No payments were done yet
            confirmationMessage = _('Are you sure you want to delete the selected invoice?');
        }

        Ext.Msg.confirm(_('Please confirm'), confirmationMessage, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));


                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/reverse-transaction',
                    params: {
                        payments: Ext.encode(arrInvoices),
                        member_id: thisGrid.caseId
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.owner.reloadClientAccounting();

                            Ext.getBody().mask(_('Done!'));
                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                            Ext.getBody().unmask();
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('An error occurred. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },

    updateTotals: function (store) {
        var thisGrid = this;
        var totalWidth = thisGrid.getWidth();
        var minOutstandingLinkWidth = 420;
        var firstColumnWidth = thisGrid.mainColumnsWidth;
        if (totalWidth - minOutstandingLinkWidth < thisGrid.mainColumnsWidth) {
            firstColumnWidth = totalWidth - minOutstandingLinkWidth;
        }

        thisGrid.totalPanel.removeAll();

        var arrItems = [];
        Ext.each(thisGrid.owner.arrMemberTA, function (oMemberTAInfo) {
            Ext.each(store.reader.jsonData.arrTADetails, function (arrTADetails) {
                if (oMemberTAInfo.id == arrTADetails.company_ta_id) {
                    var arrTAInfo = thisGrid.owner.getTAInfo(oMemberTAInfo.id);
                    var verifiedBalanceText = _('Available balance:');
                    var verifiedBalanceAmount = formatMoney(oMemberTAInfo.currency, arrTADetails.available_total, true, false);

                    if (thisGrid.owner.hasAccessToAccounting('invoices', 'trust_account_summary')) {
                        verifiedBalanceText = {
                            xtype: 'box',

                            'autoEl': {
                                'tag': 'a',
                                'href': '#',
                                'class': 'blulinkun', // Thanks to IE - we need to use quotes...
                                'html': verifiedBalanceText
                            },

                            listeners: {
                                scope: this,
                                render: function (c) {
                                    c.getEl().on('click', function (e) {
                                        var wnd = new TrustAccountSummaryDialog({
                                            caseId: thisGrid.caseId,
                                            taId: oMemberTAInfo.id,
                                            caseTACurrency: oMemberTAInfo.currency,
                                            booCanEditClient: thisGrid.booCanEditClient,
                                            totalPaymentsReceived: arrTADetails.total_payments_received,
                                            taFullName: arrTAInfo[1]
                                        }, thisGrid);

                                        wnd.show();
                                        wnd.center();
                                    }, this, {stopEvent: true});
                                }
                            }
                        }

                        verifiedBalanceAmount = {
                            xtype: 'box',
                            cellCls: 'td-amount',

                            'autoEl': {
                                'tag': 'a',
                                'href': '#',
                                'class': 'blulinkun', // Thanks to IE - we need to use quotes...
                                'html': verifiedBalanceAmount
                            },

                            listeners: {
                                scope: this,
                                render: function (c) {
                                    c.getEl().on('click', function (e) {
                                        var wnd = new TrustAccountSummaryDialog({
                                            caseId: thisGrid.caseId,
                                            taId: oMemberTAInfo.id,
                                            caseTACurrency: oMemberTAInfo.currency,
                                            booCanEditClient: thisGrid.booCanEditClient,
                                            totalPaymentsReceived: arrTADetails.total_payments_received,
                                            taFullName: arrTAInfo[1]
                                        }, thisGrid);

                                        wnd.show();
                                        wnd.center();
                                    }, this, {stopEvent: true});
                                }
                            }
                        }
                    } else {
                        verifiedBalanceText = {
                            html: verifiedBalanceText
                        };

                        verifiedBalanceAmount = {
                            cellCls: 'td-amount',
                            html: verifiedBalanceAmount
                        };
                    }

                    arrItems.push({
                        xtype: 'fieldset',
                        cellCls: 'td-width-50',
                        title: String.format(
                            "<a href='#' onclick='return false;' class='blulinkun ta_name_link'>{0} {1}<i class='las la-info-circle' ext:qtip='{2}' style='cursor: help; margin-left: 5px; padding-right: 0; vertical-align: text-bottom'></i></a>",
                            arrApplicantsSettings.ta_label,
                            arrTAInfo[1],
                            String.format(
                                _('This is the {0} summary (i.e., client liability account) for this particular case.'),
                                arrApplicantsSettings.ta_label
                            ).replaceAll("'", "\'")
                        ),
                        autoHeight: true,
                        layout: 'column',

                        items: [
                            {
                                xtype: 'container',
                                layout: 'table',
                                layoutConfig: {
                                    columns: 2
                                },

                                items: [
                                    {
                                        html: _('Total payments received:')
                                    }, {
                                        cellCls: 'td-amount',
                                        html: formatMoney(oMemberTAInfo.currency, arrTADetails.total_payments_received, true, false)
                                    },

                                    verifiedBalanceText, verifiedBalanceAmount
                                ]
                            }
                        ],

                        listeners: {
                            'render': function (obj) {
                                Ext.fly(obj.el).on('click', function (e, t) {
                                    if ($(t).hasClass('ta_name_link')) {
                                        var wnd = new TrustAccountSummaryDialog({
                                            caseId: thisGrid.caseId,
                                            taId: oMemberTAInfo.id,
                                            caseTACurrency: oMemberTAInfo.currency,
                                            booCanEditClient: thisGrid.booCanEditClient,
                                            totalPaymentsReceived: arrTADetails.total_payments_received,
                                            taFullName: arrTAInfo[1]
                                        }, thisGrid);

                                        wnd.show();
                                        wnd.center();
                                    }
                                });
                            }
                        }
                    });

                    if (arrTADetails.show_total_payments_block) {
                        arrItems.push({
                            xtype: 'fieldset',
                            title: String.format(
                                _('Operating Account - {0}'),
                                arrTAInfo[3]
                            ),
                            autoHeight: true,
                            layout: 'column',
                            cellCls: 'td-width-50',

                            items: [
                                {
                                    xtype: 'container',
                                    layout: 'table',
                                    layoutConfig: {
                                        columns: 2
                                    },

                                    items: [
                                        {
                                            xtype: 'box',
                                            autoEl: {
                                                tag: 'div',
                                                html: String.format(
                                                    '<div class="{0}" ext:qtip="{1}">' + _('Total payments received:') + '</div>',
                                                    empty(arrTADetails.payments_in_other_ta_details) ? '' : 'with_help_tooltip',
                                                    arrTADetails.payments_in_other_ta_details.replaceAll("'", "\'")
                                                )
                                            }

                                        }, {
                                            xtype: 'box',
                                            cellCls: 'td-amount',
                                            autoEl: {
                                                tag: 'div',
                                                html: String.format(
                                                    '<div class="{0}" ext:qtip="{1}">{2}</div>',
                                                    empty(arrTADetails.payments_in_other_ta_details) ? '' : 'with_help_tooltip',
                                                    arrTADetails.payments_in_other_ta_details.replaceAll("'", "\'"),
                                                    formatMoney(oMemberTAInfo.currency, arrTADetails.payments_in_other_ta_total, true, false)
                                                )
                                            }

                                        }, {
                                            xtype: 'box',
                                            autoEl: {
                                                tag: 'div',
                                                html: String.format(
                                                    '<div class="{0}" ext:qtip="{1}">' + _('Total adjustments applied:') + '</div>',
                                                    empty(arrTADetails.adjustment_payments_details) ? '' : 'with_help_tooltip',
                                                    arrTADetails.adjustment_payments_details.replaceAll("'", "\'")
                                                )
                                            }


                                        }, {
                                            xtype: 'box',
                                            cellCls: 'td-amount',
                                            autoEl: {
                                                tag: 'div',
                                                html: String.format(
                                                    '<div class="{0}" ext:qtip="{1}">{2}</div>',
                                                    empty(arrTADetails.adjustment_payments_details) ? '' : 'with_help_tooltip',
                                                    arrTADetails.adjustment_payments_details.replaceAll("'", "\'"),
                                                    formatMoney(oMemberTAInfo.currency, arrTADetails.adjustment_payments_total, true, false)
                                                )
                                            }

                                        }
                                    ]
                                }
                            ]
                        });
                    }
                }
            });
        })

        if (arrItems.length) {
            thisGrid.totalPanel.add({
                xtype: 'container',
                cls: 'summary-info x-table-layout-cell-top-align',
                width: thisGrid.getWidth() - 20,
                layout: 'table',
                layoutConfig: {
                    columns: 2
                },
                items: arrItems
            });
        }

        // Show a message if invoices amount is more than a sum of fees due (grouped by T/A)
        if (store.reader.jsonData.booShowMoreInvoicesMessage) {
            thisGrid.totalPanel.add({
                cls: 'summary-info',
                style: 'color: red',
                html: '<i class="las la-exclamation-triangle"></i>' + _('Your total invoice amount is more than fees due.')
            });
        }

        thisGrid.totalPanel.doLayout();
    },

    reloadInvoicesList: function () {
        var thisGrid = this;
        thisGrid.getStore().load();

        // Reload TA Summary dialog's grid if it is opened
        Ext.each(thisGrid.owner.arrMemberTA, function (oMemberTAInfo) {
            var wnd = Ext.getCmp('accounting_ta_summary_dialog_' + thisGrid.caseId + '_' + oMemberTAInfo.id);
            if (wnd) {
                wnd.reloadTASummary();
            }
        });
    },

    deleteInvoicePayment: function (invoicePaymentId) {
        var thisGrid = this;
        Ext.getBody().mask(_('Deleting...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/delete-invoice-payment',
            params: {
                invoice_payment_id: invoicePaymentId
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisGrid.owner.reloadClientAccounting();

                    Ext.getBody().mask(_('Done!'));
                    setTimeout(function () {
                        Ext.getBody().unmask();
                    }, 750);
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                    Ext.getBody().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('An error occurred. Please try again later.'));
                Ext.getBody().unmask();
            }
        });
    },

    makeReadOnlyAccountingInvoicesGrid: function () {
        this.booReadOnlyInvoicesGrid = true;

        this.payNowBtn.setVisible(false);
    }
});