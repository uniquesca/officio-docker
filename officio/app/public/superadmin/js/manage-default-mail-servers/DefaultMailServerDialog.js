var DefaultMailServerDialog = function(config, data) {
    var thisDialog = this;
    Ext.apply(this, config);

    this.server_id = new Ext.form.Hidden({
        value: data ? data.id : ''
    });

    this.name = new Ext.form.TextField({
        fieldLabel: _('Name'),
        value: data ? data.name : '',
        width: 260,
        allowBlank: false
    });

    this.type = new Ext.form.ComboBox({
        fieldLabel: _('Type'),
        value: data ? data.type : '',
        width: 260,
        emptyText: 'Please select a type...',
        store: new Ext.data.SimpleStore({
            fields: ['type_id', 'type_name'],
            data: [
                ['imap', _('imap')],
                ['pop3', _('pop3')],
                ['smtp', _('smtp')]
            ]
        }),
        mode:           'local',
        valueField:     'type_id',
        displayField:   'type_name',
        triggerAction:  'all',
        forceSelection: true,
        typeAhead:      false,
        selectOnFocus:  true,
        editable:       false,
        allowBlank:     false
    });

    this.host = new Ext.form.TextField({
        fieldLabel: _('Host'),
        value: data ? data.host : '',
        width: 260,
        allowBlank: false
    });

    this.port = new Ext.form.NumberField({
        fieldLabel: _('Port'),
        width: 260,
        value: data ? data.port : '',
        allowBlank: false,
        allowDecimals: false
    });

    this.ssl = new Ext.form.ComboBox({
        fieldLabel: _('Encryption mode'),
        value: data ? data.ssl : '',
        width: 260,
        emptyText: _('Please select a encryption mode...'),

        store: new Ext.data.SimpleStore({
            fields: ['encryption_id', 'encryption_name'],
            data: [
                ['', _('No')],
                ['ssl', _('SSL')],
                ['tls', _('TLS')]
            ]
        }),

        mode:           'local',
        valueField:     'encryption_id',
        displayField:   'encryption_name',
        triggerAction:  'all',
        forceSelection: true,
        typeAhead:      false,
        selectOnFocus:  true,
        editable:       false,
        allowBlank:     true
    });

    this.panel = new Ext.FormPanel({
        frame: false,
        bodyStyle: 'padding:5px',
        labelWidth: 130,
        items: [
            thisDialog.server_id,
            thisDialog.name,
            thisDialog.type,
            thisDialog.host,
            thisDialog.port,
            thisDialog.ssl
        ]
    });

    DefaultMailServerDialog.superclass.constructor.call(this, {
        modal:       true,
        autoHeight:  true,
        autoWidth:   true,
        resizable:   false,
        items:       this.panel,

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

    thisDialog.on('show', function () {
        // Don't highlight fields as incorrect
        thisDialog.items.items[0].getForm().reset();
    });
};

Ext.extend(DefaultMailServerDialog, Ext.Window, {
    saveInfo: function() {
        var thisDialog = this;

        if (thisDialog.panel.getForm().isValid()) {
            thisDialog.getEl().mask(_('Saving...'));
            Ext.Ajax.request({
                url:    baseUrl + '/manage-default-mail-servers/edit/',
                params: {
                    id:   thisDialog.server_id.getValue(),
                    name: thisDialog.name.getValue(),
                    type: thisDialog.type.getValue(),
                    host: thisDialog.host.getValue(),
                    port: thisDialog.port.getValue(),
                    ssl:  thisDialog.ssl.getValue()
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);

                    if (resultData.success) {
                        thisDialog.close();
                        Ext.simpleConfirmation.msg(_('Info'), _('Done!'));
                        thisDialog.parentGrid.getStore().reload();
                    } else {
                        thisDialog.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function () {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(_('Can\'t delete these servers'));
                }
            });
        }
    }
});