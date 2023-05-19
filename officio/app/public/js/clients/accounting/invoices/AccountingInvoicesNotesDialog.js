var AccountingInvoicesNotesDialog = function (config) {
    Ext.apply(this, config);

    var thisWindow = this;

    this.invoiceDialogFormPanel = new Ext.form.FormPanel({
        bodyStyle: 'padding: 5px',
        labelWidth: 50,
        items: [
            {
                name: 'notes',
                ref: '../notesField',
                fieldLabel: _('Note'),
                xtype: 'textarea',
                maxLength: 255,
                width: 500,
                height: 120,
                readOnly: thisWindow.booReadOnly,
                value: thisWindow.invoiceNote
            }
        ]
    });

    AccountingInvoicesNotesDialog.superclass.constructor.call(this, {
        title: _('Internal Notes'),
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,

        items: [this.invoiceDialogFormPanel],

        buttons: [
            {
                text: thisWindow.booReadOnly ? _('Close') : _('Cancel'),
                cls: thisWindow.booReadOnly ? 'orange-btn' : '',
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: _('Save'),
                hidden: thisWindow.booReadOnly,
                cls: 'orange-btn',
                handler: this.saveNotes.createDelegate(thisWindow)
            }
        ]
    });
};

Ext.extend(AccountingInvoicesNotesDialog, Ext.Window, {
    saveNotes: function () {
        var thisWindow = this;

        if (!thisWindow.notesField.isValid()) {
            return;
        }

        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/update-notes',
            params: {
                member_id: thisWindow.caseId,
                update_id: thisWindow.invoiceId,
                update_type: 'invoice',
                update_notes: Ext.encode(thisWindow.notesField.getValue())
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    Ext.getCmp('accounting_invoices_panel_' + thisWindow.caseId).reloadInvoicesList();

                    // Reload the T/A summary dialog too (if it is opened)
                    var wnd = Ext.getCmp('accounting_ta_summary_dialog_' + thisWindow.caseId + '_' + thisWindow.caseTAId);
                    if (wnd) {
                        wnd.reloadTASummary();
                    }

                    thisWindow.getEl().mask(_('Done!'));
                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    }, 750);
                } else {
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