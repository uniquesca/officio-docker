var GenerateInvoiceFromTemplateDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    thisWindow.lastSelectedTemplateCookieId = 'accounting_invoice_template_id';
    thisWindow.lastSelectedTemplate = Ext.state.Manager.get(thisWindow.lastSelectedTemplateCookieId);
    if (empty(thisWindow.lastSelectedTemplate)) {
        thisWindow.lastSelectedTemplate = 0;
    }

    this.invoiceTemplatesCombo = new Ext.form.ComboBox({
        xtype: 'combo',
        fieldLabel: _('Invoice Template'),
        itemCls: 'no-margin-bottom',
        allowBlank: false,
        width: 250,

        store: {
            xtype: 'store',
            autoLoad: true,

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/clients/accounting/get-new-invoice-templates'
            }),

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'templateId',
                fields: [
                    'templateId',
                    'templateName'
                ]
            }),

            listeners: {
                'load': function (store, records) {
                    if (records.length) {
                        if (empty(thisWindow.lastSelectedTemplate)) {
                            // Auto select the first template
                            thisWindow.invoiceTemplatesCombo.setValue(records[0]['data']['templateId']);
                        } else {
                            thisWindow.invoiceTemplatesCombo.setValue(thisWindow.lastSelectedTemplate);
                        }
                    }
                }
            }
        },

        displayField: 'templateName',
        valueField: 'templateId',
        typeAhead: false,
        mode: 'local',
        triggerAction: 'all',
        editable: false,

        listeners: {
            'beforeselect': function (combo, rec) {
                thisWindow.lastSelectedTemplate = rec.data.templateId;
                Ext.state.Manager.set(thisWindow.lastSelectedTemplateCookieId, thisWindow.lastSelectedTemplate);

                thisWindow.loadRenderedInvoiceTemplate();
            }
        }
    });

    var dialogHeight = $(window).height() - 20;
    var dialogWidth = 1000;

    GenerateInvoiceFromTemplateDialog.superclass.constructor.call(this, {
        title: empty(thisWindow.invoice_id) ? _('Generate Invoice') : _('View Invoice Details'),
        y: 10,
        width: dialogWidth,
        height: dialogHeight,
        autoScroll: true,
        closeAction: 'close',
        modal: true,

        items: [
            {
                xtype: 'container',
                layout: 'column',
                width: '100%',
                items: [
                    {
                        xtype: 'container',
                        layout: 'form',
                        labelWidth: 120,
                        width: 370,
                        style: 'margin-bottom: 10px',
                        hidden: thisWindow.booReadOnly,
                        items: this.invoiceTemplatesCombo
                    }, {
                        html: '&nbsp;',
                        columnWidth: 1
                    }, {
                        width: 120,
                        height: 37,
                        xtype: 'button',
                        text: '<i class="las la-save"></i>' + _('Save Notes'),
                        ref: '../../saveInvoiceNotesButton',
                        hideMode: 'visibility',
                        hidden: true,
                        scope: this,
                        handler: this.saveInvoiceNotes.createDelegate(this)
                    }, {
                        width: 120,
                        height: 37,
                        xtype: 'button',
                        text: _('Pay Now'),
                        hidden: thisWindow.booReadOnly || !(thisWindow.invoice_mode === 'view_invoice' && !empty(thisWindow.invoice_outstanding_amount)),
                        scope: this,
                        handler: function () {
                            thisWindow.owner.payInvoice();
                            this.close();
                        }
                    }, {
                        width: 120,
                        height: 37,
                        xtype: 'button',
                        text: thisWindow.invoice_mode === 'view_invoice' || thisWindow.booReadOnly ? _('Close') : _('Cancel'),
                        cls: thisWindow.invoice_mode === 'view_invoice' || thisWindow.booReadOnly ? 'orange-btn' : '',
                        scope: this,
                        handler: function () {
                            this.close();
                        }
                    }, {
                        width: thisWindow.invoice_mode === 'save_to_documents' ? 180 : 120,
                        height: 37,
                        xtype: 'button',
                        text: thisWindow.getSubmitButtonTitle(),
                        cls: 'orange-btn',
                        hidden: thisWindow.invoice_mode === 'view_invoice' || thisWindow.booReadOnly,
                        handler: this.submitInvoiceDetails.createDelegate(this)
                    }
                ]
            },
            {
                xtype: 'iframepanel',
                ref: '../invoiceIframePanel',
                header: false,
                defaultSrc: 'about:blank',
                frameConfig: {
                    autoLoad: {
                        width: '100%'
                    },
                    style: 'height: ' + (dialogHeight - 110) + 'px;'
                },

                listeners: {
                    'documentloaded': function (thisIframePanel) {
                        try {
                            thisWindow.invoiceIframePanel.getFrame().unmask();

                            var notes = $('#' + thisIframePanel.id).contents().find('textarea[name=invoice_recipient_notes]');
                            if (!thisWindow.booReadOnly) {
                                $('#' + thisIframePanel.id).contents().find('.assign-fees-link').click(function () {
                                    thisWindow.assignInvoiceToFee();
                                });

                                if (thisWindow.invoice_mode !== 'new_invoice') {
                                    if (!empty(notes)) {
                                        thisWindow.originalNotesToRecipient = notes.val();
                                        notes.on('input selectionchange propertychange', function (e) {
                                            thisWindow.saveInvoiceNotesButton.setVisible(e.target.value != thisWindow.originalNotesToRecipient);
                                        });
                                    }
                                }
                            } else {
                                if (!empty(notes)) {
                                    if (empty(notes.val())) {
                                        notes.hide();
                                    } else {
                                        notes.prop('disabled', true);
                                    }
                                }
                            }
                        } catch (e) {
                        }
                    }
                }
            }
        ]
    });
};

Ext.extend(GenerateInvoiceFromTemplateDialog, Ext.Window, {
    showDialog: function () {
        var thisWindow = this;

        if (thisWindow.arrPSRecords.length) {
            thisWindow.loadNewInvoiceDetails();
        } else {
            thisWindow.show();
            thisWindow.loadRenderedInvoiceTemplate();
        }
    },

    getSubmitButtonTitle: function () {
        var title;

        switch (this.invoice_mode) {
            case 'new_invoice':
                title = _('Generate');
                break;

            case 'view_invoice':
                title = _('Not visible');
                break;

            case 'email_invoice':
                title = _('Email');
                break;

            case 'save_to_documents':
                title = _('Save to Documents');
                break;

            default:
                title = _('UNKNOWN');
                break;
        }

        return title;
    },

    loadNewInvoiceDetails: function () {
        var thisWindow = this;

        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/get-new-invoice-details',

            params: {
                fees: Ext.encode(thisWindow.arrFees),
                ps_records: Ext.encode(thisWindow.arrPSRecords),
                member_id: thisWindow.member_id,
                ta_id: thisWindow.ta_id
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                Ext.getBody().unmask();

                if (resultData.success) {
                    // PS records were converted to Fees
                    thisWindow.arrFees = resultData.arrFeesIds;

                    // Refresh the grid too
                    thisWindow.owner.reloadFeesList();

                    thisWindow.on('show', thisWindow.loadRenderedInvoiceTemplate.createDelegate(thisWindow), thisWindow, {single: true});
                    thisWindow.show();
                } else {
                    // Show an error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot load data.<br/>Please try again later.'));
                Ext.getBody().unmask();
            }
        });
    },

    loadRenderedInvoiceTemplate: function () {
        var thisWindow = this;

        var loadingDiv = String.format(
            '<div style="margin: 80px auto; width: 210px;"><img src="{0}/images/loadingAnimation.gif" alt="{1}" /></div>',
            topBaseUrl,
            _('Loading...')
        );
        thisWindow.invoiceIframePanel.getFrame().mask(loadingDiv);

        var oParams = {
            fees: Ext.encode(thisWindow.arrFees),
            member_id: Ext.encode(thisWindow.member_id),
            ta_id: Ext.encode(thisWindow.ta_id),
            invoice_id: Ext.encode(thisWindow.invoice_id),
            template_id: Ext.encode(thisWindow.lastSelectedTemplate)
        };

        thisWindow.invoiceIframePanel.setSrc(baseUrl + '/clients/accounting/get-new-invoice-from-template/?' + $.param(oParams));
    },

    submitInvoiceDetails: function () {
        var thisWindow = this;

        switch (thisWindow.invoice_mode) {
            case 'new_invoice':
                thisWindow.generateInvoice();
                break;

            case 'view_invoice':
                // Cannot be here
                break;

            case 'email_invoice':
                thisWindow.saveInvoiceToDocuments(false);
                break;

            case 'save_to_documents':
                thisWindow.saveInvoiceToDocuments(true);
                break;

            default:
                // Cannot be here
                break;
        }
    },

    generateInvoice: function () {
        var thisWindow = this;

        var iframe = $('#' + this.invoiceIframePanel.id).find('iframe')[0];
        var form = $(iframe).contents().find('form');
        var invoiceNumber = $(form).find('input[name=invoice_number]').val();
        var invoiceRecipientNotes = $(form).find('textarea[name=invoice_recipient_notes]').val();
        var date = $(form).find('input[name=date]').val();

        var errors = [];
        if (empty(invoiceNumber)) {
            errors.push(_('Invoice # is a required field.'));
        }

        if (empty(date)) {
            errors.push(_('Date of invoice is a required field.'));
        }

        if (errors.length > 0) {
            Ext.simpleConfirmation.error(errors.join('<br />'), _('Error(s)'));
            return false;
        }

        thisWindow.getEl().mask(_('Generating...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/save-invoice',
            params: {
                member_id: Ext.encode(thisWindow.member_id),
                transfer_to_ta_id: Ext.encode(thisWindow.ta_id),
                template_id: Ext.encode(thisWindow.invoiceTemplatesCombo.getValue()),
                fees: Ext.encode(thisWindow.arrFees),
                invoice_number: Ext.encode(invoiceNumber),
                invoice_recipient_notes: Ext.encode(invoiceRecipientNotes),
                date: Ext.encode(date)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisWindow.invoice_id = resultData.invoice_id;

                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.member_id).reloadClientAccounting();

                    var question = _('The invoice was successfully generated.<br/><br/>Would you like to email the invoice to your client?');
                    Ext.Msg.confirm(_('Invoice Generated'), question, function (btn) {
                            if (btn == 'yes') {
                                thisWindow.saveInvoiceToDocuments(false);
                            }
                        }
                    );

                    thisWindow.hide();
                    thisWindow.getEl().unmask();
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                    thisWindow.getEl().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Can\'t save invoice.'));
                thisWindow.getEl().unmask();
            }
        });
    },

    saveInvoiceToDocuments: function (booSaveToDocs) {
        var thisWindow = this;

        var caseId = thisWindow.member_id;
        var invoiceId = thisWindow.invoice_id;
        var templateId = thisWindow.invoiceTemplatesCombo.getValue();

        var el = thisWindow.getEl();
        if (!booSaveToDocs) {
            // We want to open the Email dialog, so close this one
            thisWindow.close();

            el = Ext.getBody();
        }

        el.mask(_('Generating...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/create-invoice-pdf',
            params: {
                member_id: Ext.encode(caseId),
                invoice_id: Ext.encode(invoiceId),
                template_id: Ext.encode(templateId),
                copy_to_correspondence: Ext.encode(booSaveToDocs)
            },

            success: function (result) {
                el.unmask();

                var resultData = Ext.decode(result.responseText);
                if (resultData.error) {
                    Ext.simpleConfirmation.error(resultData.error);
                } else {
                    if (booSaveToDocs) {
                        Ext.simpleConfirmation.success(_('Invoice was successfully saved to Correspondence folder.'));
                        thisWindow.close();
                    } else {
                        var oParams = {
                            member_id: caseId,
                            invoice_id: invoiceId,
                            invoice_path: resultData.file_id,
                            download: 1
                        };

                        var attachments = [];
                        attachments.push({
                            name: resultData.file_name,
                            link: topBaseUrl + '/clients/accounting/get-invoice-pdf?' + $.param(oParams),
                            size: resultData.file_size,
                            libreoffice_supported: false,
                            file_id: resultData.file_id
                        });

                        show_email_dialog({
                            member_id: caseId,
                            attach: attachments,
                            booProspect: false,
                            save_to_prospect: false,
                            booDontPreselectTemplate: false
                        });
                    }
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Cannot open invoice document. Please try again later.'));
                el.unmask();
            }
        });
    },

    saveInvoiceNotes: function () {
        var thisWindow = this;

        var iframe = $('#' + this.invoiceIframePanel.id).find('iframe')[0];
        var form = $(iframe).contents().find('form');
        var invoiceRecipientNotes = $(form).find('textarea[name=invoice_recipient_notes]').val();

        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/update-notes',
            params: {
                member_id: thisWindow.member_id,
                update_id: thisWindow.invoice_id,
                update_type: 'invoice_recipient_notes',
                update_notes: Ext.encode(invoiceRecipientNotes)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    thisWindow.originalNotesToRecipient = invoiceRecipientNotes;
                    thisWindow.saveInvoiceNotesButton.setVisible(false);

                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.member_id).reloadInvoicesList();

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                    }, 750);
                } else {
                    thisWindow.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Cannot save notes.<br/>Please try again later.'));
            }
        });
    },

    assignInvoiceToFee: function () {
        var thisWindow = this;

        var wnd = new AccountingInvoicesAssignDialog({
            caseId: thisWindow.member_id,
            caseTAId: thisWindow.ta_id,
            caseTACurrency: Ext.getCmp('accounting_invoices_panel_' + thisWindow.member_id).getCurrencyByTAId(thisWindow.ta_id),
            invoiceId: thisWindow.invoice_id,
            invoiceAmount: thisWindow.invoice_amount,
            invoicePaymentsAmount: thisWindow.invoice_payments_amount,
            invoiceNumber: thisWindow.invoice_number
        });

        wnd.showDialog();
        thisWindow.close();
    }
});