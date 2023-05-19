var ZohoKeysDialog = function(config, record) {
    var thisDialog = this;
    Ext.apply(this, config);

    this.zohoKeyPreviousField = new Ext.form.Hidden({
        name: 'zohoKeyPrevious',
        value: empty(record) ? '' : record.data.zoho_key
    });

    this.zohoKeyField = new Ext.form.TextField({
        name: 'zohoKey',
        maxLength: 255,
        allowBlank: false,
        width: 300,
        fieldLabel: 'Key'
    });

    if (!empty(record)) {
        this.zohoKeyField.setValue(record.data.zoho_key);
    }

    this.zohoKeyStatus = new Ext.form.ComboBox({
        name: 'zohoKeyStatus',
        hiddenName: 'zohoKeyStatus',
        store: new Ext.data.SimpleStore({
            fields: ['location', 'locationName'],
            data: [
                ['enabled', 'Enabled'],
                ['disabled', 'Disabled']
            ]
        }),
        mode: 'local',
        displayField: 'locationName',
        valueField: 'location',
        typeAhead: false,
        allowBlank: false,
        triggerAction: 'all',
        selectOnFocus: false,
        editable: true,
        grow: true,
        fieldLabel: 'Status',
        value: empty(record) ? 'enabled' : record.data.zoho_key_status,
        width: 300,
        listWidth: 300
    });

    this.zohoForm = new Ext.FormPanel({
        style: 'background-color: #fff; padding: 5px;',
        labelWidth: 70,
        items: [
            thisDialog.zohoKeyPreviousField,
            thisDialog.zohoKeyField,
            thisDialog.zohoKeyStatus
        ]
    });

    ZohoKeysDialog.superclass.constructor.call(this, {
        modal:       true,
        autoHeight:  true,
        autoWidth:   true,
        resizable:   false,
        items:       this.zohoForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: thisDialog.saveInfo.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadInfo.createDelegate(this));
};

Ext.extend(ZohoKeysDialog, Ext.Window, {
    loadInfo: function() {
    },

    saveInfo: function() {
        var thisDialog = this,
            booReallyValid = this.zohoForm.getForm().isValid();

        if (booReallyValid) {
            thisDialog.getEl().mask('Saving...');
            this.zohoForm.getForm().submit({
                url: baseUrl + '/zoho/save-key',
                params: {
                },

                success: function (form, action) {
                    thisDialog.parentGrid.getStore().reload();

                    thisDialog.getEl().mask('Done!');
                    setTimeout(function() {
                        thisDialog.close();
                    }, 750);
                },

                failure: function (form, action) {
                    thisDialog.getEl().unmask();

                    var msg = action && action.result && action.result.message ? action.result.message : 'Information cannot be saved. Please try again later.';
                    Ext.simpleConfirmation.error(msg);
                }
            });
        }
    }
});