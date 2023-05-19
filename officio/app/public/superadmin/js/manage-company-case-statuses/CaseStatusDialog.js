var CaseStatusDialog = function (config, owner, oCaseStatusRecord) {
    var thisDialog = this;
    Ext.apply(thisDialog, config);

    thisDialog.owner = owner;
    thisDialog.caseStatusRecord = oCaseStatusRecord;

    this.CaseStatusForm = new Ext.form.FormPanel({
        style: 'background-color: #fff; padding: 5px;',

        items: [
            {
                name: 'client_status_id',
                xtype: 'hidden',
                value: thisDialog.caseStatusRecord.data['client_status_id']
            }, {
                name: 'client_status_name',
                ref: '../caseStatusNameField',
                xtype: 'textfield',
                fieldLabel: _('Name'),
                emptyText: _('Please enter the name...'),
                allowBlank: false,
                width: 300,
                value: thisDialog.caseStatusRecord.data['client_status_name']
            }
        ]
    });

    CaseStatusDialog.superclass.constructor.call(this, {
        title: empty(thisDialog.caseStatusRecord.data['client_status_id']) ? '<i class="las la-plus"></i>' + _('New') + ' ' + statuses_field_label_singular : '<i class="las la-edit"></i>' + _('Edit') + ' ' + statuses_field_label_singular,
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,

        items: this.CaseStatusForm,

        buttons: [
            {
                text: _('Cancel'),
                scope: this,
                handler: this.closeDialog
            },
            {
                text: _('Save'),
                cls: 'orange-btn',
                scope: this,
                handler: this.saveChanges
            }
        ],

        listeners: {
            'show': function () {
                var thisDialog = this;
                setTimeout(function () {
                    thisDialog.caseStatusNameField.focus();
                }, 50)
            }
        }
    });
};

Ext.extend(CaseStatusDialog, Ext.Window, {
    showDialog: function () {
        this.show();
        this.center();
    },

    closeDialog: function () {
        this.close();
    },

    saveChanges: function () {
        var thisDialog = this;

        // Make sure that option is selected
        if (!thisDialog.CaseStatusForm.getForm().isValid()) {
            return;
        }

        thisDialog.CaseStatusForm.getForm().submit({
            url: baseUrl + '/manage-company-case-statuses/save-case-status',
            waitMsg: _('Saving...'),
            clientValidation: true,

            success: function (form, action) {
                thisDialog.owner.reloadCaseStatusesList(action.result.status_id, action.result.status_name);

                thisDialog.getEl().mask(_('Done!'));
                setTimeout(function () {
                    if (typeof window.top.refreshSettings == 'function') {
                        window.top.refreshSettings('case_status');
                    }

                    thisDialog.closeDialog();
                }, 750);
            },

            failure: function (form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});
