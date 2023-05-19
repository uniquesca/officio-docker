var ManageVACDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    this.thisForm = new Ext.form.FormPanel({
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name: 'client_vac_id',
                value: config.params.VAC.client_vac_id
            }, {
                id: 'client_vac_city',
                fieldLabel: _('City'),
                labelSeparator: '',
                name: 'client_vac_city',
                xtype: 'textfield',
                value: config.params.VAC.client_vac_city,
                allowBlank: false,
                width: 450
            }, {
                id: 'client_vac_country',
                fieldLabel: _('Country or Province'),
                labelSeparator: '',
                name: 'client_vac_country',
                xtype: 'textfield',
                value: config.params.VAC.client_vac_country,
                allowBlank: true,
                width: 450
            }, {
                id: 'client_vac_link',
                fieldLabel: _('Link'),
                labelSeparator: '',
                name: 'client_vac_link',
                xtype: 'textfield',
                value: config.params.VAC.client_vac_link,
                allowBlank: true,
                width: 450
            },
        ]
    });

    ManageVACDialog.superclass.constructor.call(this, {
        y: 10,
        title: empty(config.params.VAC.client_vac_id) ? '<i class="las la-plus"></i>' + _('New VAC') : '<i class="las la-edit"></i>' + _('Edit VAC'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        layout: 'form',
        items: this.thisForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: empty(config.params.VAC.client_vac_id) ? _('Create') : _('Save'),
                cls: 'orange-btn',
                handler: this.saveSettings.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ManageVACDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();

        Ext.getCmp('client_vac_city').clearInvalid();
        Ext.getCmp('client_vac_country').clearInvalid();
        Ext.getCmp('client_vac_link').clearInvalid();
    },

    saveSettings: function () {
        var thisDialog = this;

        if (thisDialog.thisForm.getForm().isValid()) {
            thisDialog.getEl().mask(_('Saving...'));

            thisDialog.thisForm.getForm().submit({
                url: baseUrl + '/manage-vac/save-record',

                success: function (form, action) {
                    thisDialog.getEl().mask('Done!');
                    setTimeout(function () {
                        thisDialog.close();

                        if (action && action.result) {
                            thisDialog.owner.reloadGridAndSelectVAC(action.result.client_vac_id);
                        }
                    }, 750);
                },

                failure: function (form, action) {
                    switch (action.failureType) {
                        case Ext.form.Action.CLIENT_INVALID:
                            Ext.simpleConfirmation.error('Form fields may not be submitted with invalid values');
                            break;

                        case Ext.form.Action.CONNECT_FAILURE:
                            Ext.simpleConfirmation.error('Cannot save info');
                            break;

                        case Ext.form.Action.SERVER_INVALID:
                        default:
                            Ext.simpleConfirmation.error(action.result.message);
                            break;
                    }

                    thisDialog.getEl().unmask();
                }
            });
        }
    }
});