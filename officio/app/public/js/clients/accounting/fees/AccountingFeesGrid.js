var AccountingFeesGrid = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisGrid = this;
    var gridId = 'accounting_fees_grid_' + config.caseId + '_' + config.caseTAId;

    this.booReadOnlyFeesGrid = false;
    this.booShowFullCurrency = owner.arrMemberTA.length > 1;

    var TransactionRecord = Ext.data.Record.create([
        {name: 'fee_id', type: 'string'},
        {name: 'real_id', type: 'int'},
        {name: 'fee_due_date', type: 'string'},
        {name: 'fee_due_date_ymd', type: 'string'},
        {name: 'fee_due_timestamp', type: 'int'},
        {name: 'fee_description', type: 'string'},
        {name: 'fee_description_gst', type: 'string'},
        {name: 'fee_amount', type: 'string'},
        {name: 'fee_gst_province_id', type: 'int'},
        {name: 'fee_gst', type: 'string'},
        {name: 'fee_note', type: 'string'},
        {name: 'fee_status', type: 'string'},
        {name: 'invoice_id', type: 'int'},
        {name: 'invoice_num', type: 'string'},
        {name: 'type', type: 'string'},
        {name: 'can_edit', type: 'boolean'}
    ]);

    this.dateColumnWidth = 150;
    this.descriptionColumnWidth = 200;
    this.amountColumnWidth = 100;
    this.statusColumnWidth = 300;

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    var cm = new Ext.grid.ColumnModel({
        defaults: {
            menuDisabled: true
        },

        defaultSortable: true,
        columns: [
            sm,
            {
                header: _('Due Date'),
                dataIndex: 'fee_due_timestamp', // use the timestamp for records sorting
                align: 'left',
                width: this.dateColumnWidth,
                renderer: function (val, p, record) {
                    return record.data.fee_due_date;
                }
            }, {
                id: expandCol,
                header: _('Description'),
                dataIndex: 'fee_description',
                align: 'left',
                width: this.descriptionColumnWidth,
                renderer: function (val, p, record) {
                    if (!is_client && thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'new_fees')) {
                        if (record.data.type == 'ps') {
                            val = String.format(
                                "<a href='#' class='blklink edit_schedule_record' onclick='return false;'>{0}</a>",
                                val
                            );
                        } else if (record.data.type == 'payment' && empty(record.data['invoice_id'])) {
                            val = String.format(
                                "<a href='#' class='blklink edit_payment_record' onclick='return false;'>{0}</a>",
                                val
                            );
                        }
                    }

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
                width: this.amountColumnWidth,
                renderer: function (val, p, record) {
                    if (empty(record.data['fee_gst']) || empty(parseFloat(record.data['fee_gst']))) {
                        return formatMoney(thisGrid.caseTACurrency, val, false);
                    }

                    return '<div>' + formatMoney(thisGrid.caseTACurrency, val, false) + '</div>' + '<div style="padding-top:5px;">' + formatMoney(thisGrid.caseTACurrency, record.data['fee_gst'], false) + '</div>';
                }
            }, {
                header: _('Status'),
                dataIndex: 'fee_status',
                width: this.statusColumnWidth,
                renderer: function (val, p, record) {
                    var strStatus = '';
                    var invoiceNowLink = '';

                    if (!is_client && thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice')) {
                        invoiceNowLink = String.format(
                            '<a href="#" onclick="return false;" class="invoice_now_link" style="display: block; float: right">{0}</a>',
                            _('Invoice Now')
                        );
                    }

                    switch (record.data.fee_status) {
                        case 'due':
                        case 'due_can_be_linked':
                            strStatus = String.format(
                                '<div style="color: red; float: left"><i class="las la-exclamation-triangle"></i> {0}</div>',
                                _('DUE')
                            ) + invoiceNowLink;
                            break;

                        case 'assigned':
                            if (isNaN(record.data['invoice_num'])) {
                                strStatus = record.data['invoice_num'];

                                if (strStatus == 'Statement' && thisGrid.booShowFullCurrency) {
                                    strStatus += ' (' + thisGrid.caseTACurrencySymbol + ')'
                                }
                            } else {
                                strStatus = String.format(
                                    _('Invoiced (#{0})'),
                                    record.data['invoice_num']
                                );
                            }
                            break

                        default:
                            break;
                    }

                    if (!empty(record.data['fee_note'])) {
                        var note = _('Notes: ') + record.data['fee_note'];

                        strStatus += String.format(
                            "<div ext:qtip='{0}' style='clear: both'>{1}</div>",
                            Ext.util.Format.stripTagsLeaveBr(note).replaceAll("'", "&#39;"),
                            note
                        )
                    }

                    return strStatus;
                }
            }
        ]
    });

    this.store = new Ext.data.Store({
        url: baseUrl + '/clients/accounting/get-client-fees',
        autoLoad: true,
        remoteSort: true,
        baseParams: {
            member_id: thisGrid.caseId,
            ta_id: thisGrid.caseTAId,
            start: 0,
            limit: arrApplicantsSettings.accounting.recordsOnPage
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, TransactionRecord),

        listeners: {
            load: function (store) {
                // Don't show a pager if all records are visible
                if (store.getTotalCount() > arrApplicantsSettings.accounting.recordsOnPage) {
                    pagingBar.show();
                }

                // Don't show the header (columns) if there are no records in the grid
                $('#' + gridId + ' .x-grid3-header').toggle(!empty(store.getTotalCount()));
            }
        }
    });
    this.store.setDefaultSort('fee_due_timestamp', 'ASC');
    this.store.on('load', this.updateTotals.createDelegate(this));

    var pagingBar = new Ext.PagingToolbar({
        hidden: true,
        pageSize: arrApplicantsSettings.accounting.recordsOnPage,
        store: this.store
    });

    var thisGridTopBar = [
        {
            xtype: 'label',
            cls: 'accounting_label',
            html: _('Fees & Disbursements') + ' (' + thisGrid.caseTACurrencySymbol + ')',
        }, {
            text: '<i class="las la-plus"></i>' + _('New Fees or Disbursements'),
            tooltip: _('Add a fee or disbursement to the payment schedule.'),
            cls: 'main-btn',
            hidden: is_client || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'new_fees'),
            ref: '../NewFeesDisbursementsBtn',
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);

                var wnd = new AccountingFeesDialog({
                    caseId: thisGrid.caseId,
                    caseTAId: thisGrid.caseTAId,
                    caseTACurrency: thisGrid.caseTACurrency,
                    caseTACurrencySymbol: thisGrid.caseTACurrencySymbol,
                    booPrimaryTA: thisGrid.owner.primaryTAId == thisGrid.caseTAId,
                    arrCaseDateFields: thisGrid.owner.arrCaseDateFields,
                    arrCaseStatusFields: thisGrid.owner.arrCaseStatusFields
                }, thisGrid);

                wnd.show();
                wnd.center();
            }
        }, {
            text: _('Invoice Now'),
            tooltip: _('Create an invoice for a selected due fee or payment.'),
            cls: 'secondary-btn',
            hidden: is_client || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_invoice'),
            ref: '../InvoiceNowBtn',
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);

                var recs = thisGrid.getSelectionModel().getSelections();

                var strError = '';
                if (empty(recs.length)) {
                    strError = _('Please select at least one fee or disbursement that is due to invoice.');
                } else {
                    var arrFees = [];
                    var arrPSRecords = [];
                    for (var i = 0; i < recs.length; i++) {
                        var idParts = recs[i].data.fee_id.split('-');
                        var id = parseInt(idParts[1]);
                        if (recs[i].data.type == 'ps') {
                            arrPSRecords.push(id);
                        } else {
                            arrFees.push(id);
                        }

                        if (empty(strError) && empty(recs[i].data.fee_amount)) {
                            strError = _('Choose Fees Due only');
                        }

                        if (empty(strError) && parseFloat(recs[i].data.fee_amount) < 0) {
                            strError = _('You can\'t generate invoice for negative amount');
                        }

                        if (empty(strError) && !empty(recs[i].data.invoice_id)) {
                            strError = _('At least one of the selected rows already has an invoice.');
                        }

                        if (!empty(strError)) {
                            break;
                        }
                    }
                }

                if (!empty(strError)) {
                    Ext.simpleConfirmation.warning(strError);
                    return false;
                }

                if (arrPSRecords.length) {
                    var question = _('You have selected to generate invoice(s) for fees that have not yet become due. The fee will be marked as due today.<br><br>Would you like to proceed anyway?');
                    Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                        if (btn === 'yes') {
                            var wnd = new GenerateInvoiceFromTemplateDialog({
                                arrFees: arrFees,
                                arrPSRecords: arrPSRecords,
                                member_id: thisGrid.caseId,
                                ta_id: thisGrid.caseTAId,
                                invoice_id: 0,
                                invoice_payments_amount: 0,
                                booReadOnly: thisGrid.booReadOnlyFeesGrid,
                                invoice_mode: 'new_invoice'
                            }, thisGrid);

                            wnd.showDialog();
                        }
                    });
                } else {
                    var wnd = new GenerateInvoiceFromTemplateDialog({
                        arrFees: arrFees,
                        arrPSRecords: arrPSRecords,
                        member_id: thisGrid.caseId,
                        ta_id: thisGrid.caseTAId,
                        invoice_id: 0,
                        invoice_payments_amount: 0,
                        booReadOnly: thisGrid.booReadOnlyFeesGrid,
                        invoice_mode: 'new_invoice'
                    }, thisGrid);

                    wnd.showDialog();
                }
            }
        }, {
            text: '<i class="las la-dollar-sign"></i>' + _('Generate Receipt'),
            ref: '../generateReceipt',
            // Temporary hidden
            hidden: true,
            // hidden: is_client || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_receipt'),
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);

                // TODO: fix
                Ext.simpleConfirmation.warning('TODO: fix');
            }
        }, {
            xtype: 'tbseparator',
            // Temporary hidden
            hidden: true,
            // hidden: is_client || !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'generate_receipt'),
        }, {
            text: '<i class="las la-credit-card"></i>' + _('Pay by Credit Card'),
            ref: '../payByCreditCardButton',
            cls: 'orange-btn',
            hidden: true,
            handler: function () {
                // NOTE: temporary disable
                Ext.simpleConfirmation.warning('We are undergoing system maintenance.<br>This feature is not available at this time.<br>To prevent any delays, you can continue to submit your application, by clicking on <b>Submit to CBIU Dominica</b> button.');
                return;

                // show_hide_gridpanel(thisGrid.caseId, gridId, true);
                //
                // var data = thisGrid.store.reader.jsonData;
                // var wnd = new CreditCardPaymentDialog({
                //     member_id:         thisGrid.caseId,
                //     ta_id:             thisGrid.caseTAId,
                //     amountOutstanding: data.amountOutstanding,
                //     amountPaid:        data.amountPaid,
                //     balance:           data.balance
                // }, this);
                // wnd.show();
                // wnd.center();
            }
        }, {
            xtype: 'tbseparator',
            ref: '../payByCreditCardSeparator',
            hidden: true
        }, {
            text: '<i class="las la-exclamation-triangle"></i>' + _('Delete'),
            ref: '../errorCorrection',
            hidden: true, // hide by default, show only if there are selected records and all of them can be "corrected"
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);
                thisGrid.showErrorCorrection(thisGrid.getSelectionsIds());
            }
        }, '->', {
            text: '<i class="las la-list"></i>' + _('Payment Schedule Template'),
            tooltip: _('Create or select an existing payment schedule template for the client.'),
            // show for the primary T/A only and if we have access to this
            hidden: is_client || (thisGrid.owner.primaryTAId != thisGrid.caseTAId && !thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'allow_manage_ps_records')),
            ref: '../PaymentScheduleTemplatesBtn',
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);

                var wnd = new AccountingFeesManageTemplatesDialog({
                    caseId: thisGrid.caseId,
                    caseTAId: thisGrid.caseTAId,
                    caseTACurrency: thisGrid.caseTACurrency,
                    caseTACurrencySymbol: thisGrid.caseTACurrencySymbol,
                    booPrimaryTA: thisGrid.owner.primaryTAId == thisGrid.caseTAId,
                    arrCaseDateFields: thisGrid.owner.arrCaseDateFields,
                    arrCaseStatusFields: thisGrid.owner.arrCaseStatusFields
                }, thisGrid);

                wnd.show();
                wnd.center();
            }
        }, {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            handler: function () {
                show_hide_gridpanel(thisGrid.caseId, gridId, true);
                thisGrid.reloadFeesList();
            }
        }, {
            text: String.format('<i class="lar la-minus-square" title="{0}"></i>', _('Hide fees & disbursements.')),
            handler: function () {
                var booExpanded = $('#' + gridId + ' .x-grid3-viewport').is(":visible");
                if (booExpanded) {
                    this.setText(String.format('<i class="lar la-plus-square" title="{0}"></i>', _('Show fees & disbursements.')));
                } else {
                    this.setText(String.format('<i class="lar la-minus-square" title="{0}"></i>', _('Hide fees & disbursements.')));
                }

                show_hide_gridpanel(thisGrid.caseId, gridId, !booExpanded);
            }
        }
    ];


    this.totalPanel = new Ext.Panel();

    AccountingFeesGrid.superclass.constructor.call(this, {
        id: gridId,
        autoExpandColumn: expandCol,
        cm: cm,
        sm: sm,
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
        tbar: thisGridTopBar,
        bbar: [
            pagingBar,
            this.totalPanel
        ]
    });

    var booCanDoErrorsCorrection = !is_client && thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'error_correction');
    if (booCanDoErrorsCorrection) {
        thisGrid.getSelectionModel().on('selectionchange', function () {
            var sel = thisGrid.getSelectionModel().getSelections();

            var booAllCanBeEdited = false;
            if (sel.length >= 1) {
                booAllCanBeEdited = true;
                var arrPayments = thisGrid.getSelectionsIds()
                Ext.each(arrPayments, function (oPayment) {
                    if (!oPayment.booCanEdit) {
                        booAllCanBeEdited = false;
                    }
                });
            }

            thisGrid.errorCorrection.setVisible(booAllCanBeEdited && !thisGrid.booReadOnlyFeesGrid);
        }, thisGrid);

        this.on('rowcontextmenu', this.onRowContextMenu, this);
    }

    this.on('cellclick', this.onCellClick, this);
};

Ext.extend(AccountingFeesGrid, Ext.grid.GridPanel, {
    // Show context menu
    onRowContextMenu: function (grid, row, e) {
        e.stopEvent();
        grid.getSelectionModel().selectRow(row);

        if (!grid.booReadOnlyFeesGrid) {
            // Check if we can "edit" all selected records
            var booAllCanBeEdited = true;
            var arrPayments = grid.getSelectionsIds()
            Ext.each(arrPayments, function (oPayment) {
                if (!oPayment.booCanEdit) {
                    booAllCanBeEdited = false;
                }
            });

            if (booAllCanBeEdited) {
                var contextMenuFT = new Ext.menu.Menu({
                    cls: 'no-icon-menu',
                    enableScrolling: false,
                    items: [
                        {
                            ref: '../addEditNotesMenuItem',
                            text: '<i class="las la-edit"></i>' + _('Add or Update Notes'),
                            handler: function () {
                                show_hide_gridpanel(grid.caseId, grid.id, true);
                                grid.addEditNotes();
                            }
                        }, {
                            text: '<i class="las la-exclamation-triangle"></i>' + _('Delete'),
                            handler: function () {
                                show_hide_gridpanel(grid.caseId, grid.id, true);
                                grid.showErrorCorrection(arrPayments);
                            }
                        }
                    ]
                });

                contextMenuFT.showAt(e.getXY());
            }
        }
    },

    getSelectionsIds: function (booPaymentsOnly) {
        var grid = this;
        var recs = grid.getSelectionModel().getSelections();
        if (recs.length === 0) {
            Ext.simpleConfirmation.msg('Info', 'Please select a payment(s)');
            return false;
        }

        var recIds = [];
        var idParts = [];
        for (var i = 0; i < recs.length; i++) {
            if (booPaymentsOnly) {
                if (recs[i].data.type != 'payment') {
                    Ext.simpleConfirmation.msg('Info', 'Please select a payment(s) and not invoice(s)');
                    return false;
                }
                idParts = recs[i].data.fee_id.split('-');
                recIds.push(parseInt(idParts[1]));
            } else {
                idParts = recs[i].data.fee_id.split('-');
                recIds.push({
                    id: parseInt(idParts[1]),
                    type: recs[i].data.type,
                    booCanEdit: recs[i].data.can_edit,
                    booIsInvoiced: !empty(recs[i].data.invoice_id)
                });
            }
        }
        return recIds;
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var thisGrid = this;

        if ($(e.getTarget()).hasClass('edit_schedule_record')) {
            var record = thisGrid.getStore().getAt(rowIndex);
            var idParts = record.data.fee_id.split('-')
            var payment_schedule_id = parseInt(idParts[1]);

            Ext.getBody().mask(_('Loading...'));

            Ext.Ajax.request({
                url: baseUrl + '/clients/accounting/get-payment-schedule',
                params: {
                    payment_schedule_id: payment_schedule_id
                },
                success: function (f, o) {
                    Ext.getBody().unmask();

                    var resultData = Ext.decode(f.responseText);

                    var wnd = new ManageScheduleRecordDialog({
                        booUseDefaultSaveHandler: true,
                        booShowDeleteButton: thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'allow_manage_ps_records') && !thisGrid.booReadOnlyFeesGrid,
                        booShowUpdateButton: !thisGrid.booReadOnlyFeesGrid,
                        caseId: thisGrid.caseId,
                        caseTAId: thisGrid.caseTAId,
                        arrCaseDateFields: thisGrid.owner.arrCaseDateFields,
                        arrCaseStatusFields: thisGrid.owner.arrCaseStatusFields,
                        arrProvinces: arrApplicantsSettings.accounting.arrProvinces,
                        oRecord: {
                            id: payment_schedule_id,
                            data: {
                                'payment_id': payment_schedule_id,
                                'type': resultData.payment.type,
                                'amount': resultData.payment.amount,
                                'tax_id': resultData.payment.gst_province_id,
                                'due_on_id': empty(resultData.payment.based_on_profile_date_field) ? resultData.payment.based_on_account : resultData.payment.based_on_profile_date_field,
                                'description': resultData.payment.description,
                                'due_date': resultData.payment.based_on_date
                            }
                        }
                    }, thisGrid);
                    wnd.show();
                    wnd.center();
                },

                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(_('Cannot load Payment data'));
                }
            });
        } else if ($(e.getTarget()).hasClass('edit_payment_record')) {
            var record = thisGrid.getStore().getAt(rowIndex);

            var wnd = new AccountingFeesManageDialog({
                booUseDefaultSaveHandler: true,
                booShowDeleteButton: thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'allow_manage_ps_records') && !thisGrid.booReadOnlyFeesGrid,
                booShowUpdateButton: !thisGrid.booReadOnlyFeesGrid,
                caseId: thisGrid.caseId,
                caseTAId: thisGrid.caseTAId,
                arrCaseDateFields: thisGrid.owner.arrCaseDateFields,
                arrCaseStatusFields: thisGrid.owner.arrCaseStatusFields,
                arrProvinces: arrApplicantsSettings.accounting.arrProvinces,
                oRecord: {
                    id: record.data.real_id,
                    data: {
                        'payment_id': record.data.real_id,
                        'type': 'date',
                        'amount': record.data.fee_amount,
                        'tax_id': record.data.fee_gst_province_id,
                        'description': record.data.fee_description,
                        'due_date': record.data.fee_due_date_ymd
                    }
                }
            }, thisGrid);
            wnd.show();
            wnd.center();
        } else if ($(e.getTarget()).hasClass('invoice_now_link')) {
            thisGrid.InvoiceNowBtn.handler.call(thisGrid.InvoiceNowBtn.scope);
        } else if ($(e.getTarget()).hasClass('assign_fee_to_invoice_link')) {
            var record = thisGrid.getStore().getAt(rowIndex);

            var wnd = new AccountingFeesAssignDialog({
                caseId: thisGrid.caseId,
                caseTAId: thisGrid.caseTAId,
                caseTACurrency: Ext.getCmp('accounting_invoices_panel_' + thisGrid.caseId).getCurrencyByTAId(thisGrid.caseTAId),
                fees: [record.data.real_id],
                feesAmount: record.data.fee_amount
            });
            wnd.showDialog();
        }
    },

    addEditNotes: function () {
        var thisGrid = this;
        var rec = this.getSelectionModel().getSelected();

        var notes = new Ext.form.TextField({
            fieldLabel: 'Notes',
            width: 400,
            maxLength: 1024,
            value: rec.data.fee_note
        });

        var win = new Ext.Window({
            title: '<i class="las la-edit"></i>' + _('Add or Update Notes'),
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,
            items: {
                xtype: 'form',
                labelAlign: 'top',
                items: [notes]
            },
            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: _('Save'),
                    cls: 'orange-btn',
                    handler: function () {
                        updateNotes(win, thisGrid.caseId, thisGrid.caseTAId, rec.data.type, rec.data.real_id, notes.getValue());
                    }
                }
            ]
        });

        win.show();
        win.center();
    },

    showErrorCorrection: function (arrPayments) {
        var thisGrid = this;

        if (!arrPayments) {
            return;
        }

        // Don't allow deleting the payment in specific situations
        // E.g. when this record was created from Stripe
        var paymentsCount = 0;
        var invoicedPaymentsCount = 0;
        var booAllCanBeEdited = true;
        Ext.each(arrPayments, function (oPayment) {
            if (!oPayment.booCanEdit) {
                booAllCanBeEdited = false;
            }

            if (oPayment.booIsInvoiced) {
                invoicedPaymentsCount++;
            }
            paymentsCount++;
        });

        var errorMessage = '';
        if (!empty(invoicedPaymentsCount)) {
            errorMessage = invoicedPaymentsCount === 1 ? _('This fee is assigned to an invoice.<br>You cannot delete the fee unless you delete the invoice or un-assign this fee from the invoice.') : _('These fees are assigned to invoice(s).<br>You cannot delete the fees unless you delete the assigned invoices or un-assign these fees from the invoices.');
        } else if (!booAllCanBeEdited) {
            errorMessage = paymentsCount === 1 ? _('This payment cannot be deleted.') : _('These payments cannot be deleted.');
        }

        if (!empty(errorMessage)) {
            Ext.simpleConfirmation.error(errorMessage);
            return;
        }

        var question = paymentsCount === 1 ? _('Are you sure you want to delete the selected Fee or Disbursement?') : _('Are you sure you want to delete the selected Fees or Disbursements?')
        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/clients/accounting/reverse-transaction',
                    params: {
                        payments: Ext.encode(arrPayments),
                        member_id: thisGrid.caseId
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.reloadFeesList();

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
                        Ext.simpleConfirmation.error(msg + _(' Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },

    updateTotals: function (store) {
        var thisGrid = this;
        thisGrid.totalPanel.removeAll();

        if (!empty(store.getTotalCount())) {
            // Show only if we have records in the grid
            var totalGst = '';
            if (!empty(store.reader.jsonData.total_gst)) {
                totalGst = String.format(
                    _('+ {0} (tax)'),
                    formatMoney(thisGrid.caseTACurrency, store.reader.jsonData.total_gst, true, false)
                )
            }

            var firstColsWidth = 0;
            $('#' + thisGrid.id + ' .x-grid3-hd').each(function (index) {
                if (index <= 3) {
                    firstColsWidth += $(this).width();
                }
            });

            var warningBox = new Ext.BoxComponent({
                xtype: 'box',
                width: firstColsWidth - 250, // We'll update this when we'll know the width of the "total section"
                autoEl: {
                    tag: 'div',
                    style: 'color: red; white-space: normal; padding-left: 15px; padding-right: 15px',
                    html: empty(store.reader.jsonData.unassigned_invoices_amount) ? '&nbsp;' : '<i class="las la-exclamation-triangle"></i>' +
                        String.format(
                            _("Your account needs some cleaning up. You have a total of {0} payments that you have received but have not linked to any fees. Officio doesn't know which fees the payment(s) need to be applied to. Please click on {1}Assign Fees to update your records."),
                            formatMoney(thisGrid.caseTACurrency, store.reader.jsonData.unassigned_invoices_amount, true, false),
                            '<i class="las la-balance-scale-right blink" style="color: red"></i>'
                        )
                }
            });

            var totalDueContainer = new Ext.Container({
                xtype: 'container',
                items: [
                    {
                        xtype: 'box',
                        cls: 'summary-info-border',
                        autoEl: {
                            tag: 'div',
                            style: 'text-align: center;',
                            html: String.format(
                                _('Total Due Now: <div class="total-amount">{0}</div>'),
                                formatMoney(thisGrid.caseTACurrency, store.reader.jsonData.total_due, true, false)
                            )
                        }
                    }
                ]
            });

            var totalContainer = new Ext.Container({
                xtype: 'container',
                items: [
                    {
                        xtype: 'box',
                        cls: 'summary-info-border fees-total',
                        autoEl: {
                            tag: 'div',
                            style: 'text-align: center;',
                            html: String.format(
                                _('Total Fees: <div class="total-amount">{0}</div>'),
                                formatMoney(thisGrid.caseTACurrency, store.reader.jsonData.total, true, false) + '</span>'
                            )
                        }
                    }, {
                        xtype: 'box',
                        hidden: empty(totalGst),
                        cls: 'fees-taxes',
                        autoEl: {
                            tag: 'div',
                            style: 'text-align: center;',
                            html: totalGst
                        }
                    }
                ]
            });

            thisGrid.totalPanel.add({
                xtype: 'container',
                cls: 'summary-info cell-align-top',
                layout: 'table',

                items: [
                    totalDueContainer,
                    warningBox,
                    totalContainer
                ],

                listeners: {
                    'afterrender': function () {
                        setTimeout(function (){
                            // change the width of the warning section -> because the total section should be aligned with the Amount column
                            warningBox.setWidth(
                                firstColsWidth -
                                $('#' + totalDueContainer.id).outerWidth() -
                                $('#' + totalContainer.id + ' .fees-total').outerWidth() + // We want to show the Total aligned with the amount column
                                20 // additional padding
                            );
                        }, 100);
                    }
                }
            });
        }

        thisGrid.totalPanel.doLayout();


        if (store.getTotalCount() > 0 && !is_client && thisGrid.owner.hasAccessToAccounting('fees_and_disbursements', 'pay_by_cc')) {
            thisGrid.payByCreditCardButton.setVisible(true);
            thisGrid.payByCreditCardSeparator.setVisible(true);
        }
    },

    reloadFeesList: function () {
        this.getStore().load();
    },

    makeReadOnlyAccountingFeesGrid: function () {
        this.booReadOnlyFeesGrid = true;

        if (this.NewFeesDisbursementsBtn) {
            this.NewFeesDisbursementsBtn.setVisible(false);
        }

        if (this.InvoiceNowBtn) {
            this.InvoiceNowBtn.setVisible(false);
        }

        if (this.PaymentScheduleTemplatesBtn) {
            this.PaymentScheduleTemplatesBtn.setVisible(false);
        }
    }
});