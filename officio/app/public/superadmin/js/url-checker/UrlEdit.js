var UrlEdit = function(rec, arrForms) {
    var booAddUrl = rec.id === 0;

    this.UrlField = new Ext.form.TextField({
        fieldLabel: 'Address',
        width: 400,
        vtype: 'url',
        allowBlank: false,
        value: rec.url
    });

    this.UrlAssignedFormCombo = new Ext.form.ComboBox({
        fieldLabel: 'Assigned Form',
        width: 400,
        listWidth: 400,

        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'form_id'
            }, [
                {name: 'form_id'},
                {name: 'file_name'}
            ]),
            data: arrForms
        },
        mode:           'local',
        displayField:   'file_name',
        valueField:     'form_id',
        triggerAction:  'all',
        searchContains: true,
        forceSelection: true,
        selectOnFocus:  true,
        editable:       true,
        allowBlank:     false,

        value: rec.assigned_form_id
    });

    this.UrlDescriptionField = new Ext.form.TextArea({
        fieldLabel: 'Description',
        width: 405,
        value: rec.url_description
    });

    this.UrlIdField = new Ext.form.Hidden({
        xtype: 'hidden',
        value: rec.id
    });

    this.UrlPanel = new Ext.FormPanel({
        bodyStyle:  'padding:5px;',
        labelWidth: 110,
        autoHeight: true,
        scope:      this,
        items:      [
            this.UrlField,
            this.UrlAssignedFormCombo,
            this.UrlDescriptionField,
            this.UrlIdField
        ]
    });

    UrlEdit.superclass.constructor.call(this, {
        title: booAddUrl ? '<i class="las la-plus"></i>' + _('Add Url') : '<i class="las la-edit"></i>' + _('Edit Url'),
        closeAction: 'close',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        items: this.UrlPanel,
        buttons: [
            {
                text: 'Cancel',
                scope: this,
                handler: function() {
                    this.close();
                }
            },
            {
                text: 'Save',
                cls: 'orange-btn',
                scope: this,
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });
};

Ext.extend(UrlEdit, Ext.Window, {
    saveChanges: function() {
        var win = this;
        var form = win.UrlPanel;
        if(form.getForm().isValid()) {
            win.getEl().mask('Saving...');


            Ext.Ajax.request({
                url:    baseUrl + '/url-checker/save',
                params: {
                    id:               Ext.encode(win.UrlIdField.getValue()),
                    url:              Ext.encode(win.UrlField.getValue()),
                    url_description:  Ext.encode(win.UrlDescriptionField.getValue()),
                    assigned_form_id: Ext.encode(win.UrlAssignedFormCombo.getValue())
                },

                success: function(result, request) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        // Refresh urls list
                        Ext.getCmp('url_grid').store.reload();

                        win.getEl().mask('Done');
                        setTimeout(function() {
                            win.getEl().unmask();
                            win.close();
                        }, 750);
                    } else {
                        // Show error message
                        Ext.Msg.alert('Error', resultData.message);
                        win.getEl().unmask();
                    }
                },
                failure: function() {
                    Ext.Msg.alert('Error', 'An error occurred during data saving.<br/>Please try again later.');
                    win.getEl().unmask();
                }
            });
        }
    }
});