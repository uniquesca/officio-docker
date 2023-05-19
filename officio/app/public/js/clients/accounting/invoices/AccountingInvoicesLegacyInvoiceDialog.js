var AccountingInvoicesLegacyInvoiceDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    thisWindow.invoiceNumberField = new Ext.form.Hidden({
        allowBlank: false
    });

    thisWindow.templatesCombo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'templateId'},
                {name: 'templateName'}
            ]))
        }),
        mode: 'local',
        valueField: 'templateId',
        displayField: 'templateName',
        triggerAction: 'all',
        lazyRender: true,
        forceSelection: true,
        emptyText: _('Please select a template...'),
        selectOnFocus: true,
        typeAhead: true,
        width: 400,
        editable: false,

        listeners: {
            'beforeselect': function () {
                if (thisWindow.sendOptionsCombo.disabled) {
                    thisWindow.sendOptionsCombo.setValue(1);
                    thisWindow.sendOptionsCombo.setDisabled(false);
                    thisWindow.sendBtn.setDisabled(false);
                }
            }
        }
    });

    thisWindow.sendOptionsCombo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'optionId'},
                {name: 'optionName'}
            ]))
        }),
        mode: 'local',
        disabled: true,
        allowBlank: false,
        valueField: 'optionId',
        displayField: 'optionName',
        triggerAction: 'all',
        lazyRender: true,
        forceSelection: true,
        emptyText: _('Send option...'),
        selectOnFocus: true,
        width: 200,
        typeAhead: true,
        editable: false,

        listeners: {
            'beforeselect': function (combo, rec) {
                var btnLabel = _('Preview Email');
                switch (rec['data']['optionId']) {
                    case 1 : // send as email
                        btnLabel = _('Preview Email');
                        break;

                    case 4 :  // Save to Documents
                        btnLabel = _('Save');
                        break;

                    case 6 :  // download as doc
                        btnLabel = _('Download');
                        break;

                    default:
                        break;
                }

                thisWindow.sendBtn.setText(btnLabel);
            }
        }
    });

    AccountingInvoicesLegacyInvoiceDialog.superclass.constructor.call(this, {
        title: _('Legacy Email Templates'),
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,

        items: {
            xtype: 'form',
            layout: 'table',
            bodyStyle: 'padding: 5px;',
            layoutConfig: {columns: 3},
            items: [thisWindow.templatesCombo, {html: '&nbsp;'}, thisWindow.sendOptionsCombo]
        },

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Preview Email'),
                cls: 'orange-btn',
                ref: '../sendBtn',
                disabled: true,
                handler: this.generateLegacyInvoice.createDelegate(thisWindow)
            }
        ]
    });
};

Ext.extend(AccountingInvoicesLegacyInvoiceDialog, Ext.Window, {
    showDialog: function () {
        this.loadNewInvoiceDetails();
    },

    loadNewInvoiceDetails: function () {
        var thisWindow = this;

        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/get-legacy-invoice-templates',

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                Ext.getBody().unmask();

                if (resultData.success) {
                    thisWindow.on('show', thisWindow.applyLoadedInfo.createDelegate(thisWindow, [resultData]), thisWindow, {single: true});
                    thisWindow.show();
                    thisWindow.center();
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

    applyLoadedInfo: function (resultData) {
        var thisWindow = this;

        thisWindow.templatesCombo.getStore().loadData(resultData.templates);
        thisWindow.sendOptionsCombo.getStore().loadData(resultData.send_as_options);
    },

    generateLegacyInvoice: function () {
        var thisWindow = this;
        var title = 'Invoice';

        switch (thisWindow.sendOptionsCombo.getValue()) {
            case 1 : // send as email
                show_email_dialog({
                    member_id: thisWindow.caseId,
                    invoice_id: thisWindow.invoiceId,
                    template_id: thisWindow.templatesCombo.getValue()
                });

                thisWindow.close();
                break;

            case 4 :  // Save to Documents
                Ext.getBody().mask(_('Saving Document...'));
                Ext.Ajax.request({
                    url: baseUrl + '/documents/index/save-doc-file',
                    params: {
                        member_id: Ext.encode(thisWindow.caseId),
                        template_id: Ext.encode(thisWindow.templatesCombo.getValue()),
                        invoice_id: Ext.encode(thisWindow.invoiceId),
                        title: Ext.encode(title)
                    },
                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.msg(_('Info'), _('Document saved in the client Correspondence folder'));
                        }
                    },
                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_("Can't create Document"));
                    }
                });
                thisWindow.close();
                break;

            case 6 :  // download as doc
                var oParams = {
                    member_id: thisWindow.caseId,
                    template_id: thisWindow.templatesCombo.getValue(),
                    invoice_id: thisWindow.invoiceId,
                    title: title
                };
                Ext.ux.Popup.show(baseUrl + '/documents/index/download-doc-file/?' + $.param(oParams), true);

                thisWindow.close();
                break;

            default:
                break;
        }
    }
});