var MassEmailDialog = function (arrCompanyIds) {
    // Templates combobox
    this.templateCombo = new Ext.form.ComboBox({
        fieldLabel: 'Template',
        allowBlank: false,

        store: new Ext.data.Store({
            url: baseUrl + '/manage-templates/get-templates',
            method: 'POST',
            autoLoad: true,

            baseParams: {
                // Load templates only this type
                template_type: 'mass_email'
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                idProperty: 'template_id',
                fields: [
                    {name: 'template_id', type: 'int'},
                    {name: 'title', type: 'string'}
                ]
            })
        }),

        mode: 'local',
        valueField: 'template_id',
        displayField: 'title',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Please select template...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 270,
        listWidth: 270
    });

    // Send to combobox
    this.sendToCombo = new Ext.form.ComboBox({
        fieldLabel: 'Send to',
        allowBlank: false,
        store: new Ext.data.ArrayStore({
            fields: ['to_id', 'to_label'],
            data : [
                ['admin', 'Admin users only'],
                ['all',   'All users except Agents and Cases']
            ]
        }),
        mode: 'local',
        typeAhead: false,
        forceSelection: true,
        editable: false,
        triggerAction: 'all',
        emptyText: 'Select a state...',
        selectOnFocus: true,
        width: 270,
        listWidth: 270,
        value: 'admin',

        displayField: 'to_label',
        valueField: 'to_id'
    });

    this.ignoreSending = new Ext.form.Checkbox({
        boxLabel: 'Respect the Do not send email policy',
        checked: true
    });

    this.FieldsForm = new Ext.form.FormPanel({
        frame: false,
        bodyStyle: 'padding:5px',
        labelWidth: 60,
        items: [
            this.templateCombo,
            this.sendToCombo,
            this.ignoreSending
        ]
    });

    this.buttons = [
        {
            text: 'Close',
            scope: this,
            handler: function () {
                this.close();
            }
        },
        {
            text: 'Send',
            cls: 'orange-btn',
            handler: this.startMassMailing.createDelegate(this, [arrCompanyIds])
        }
    ];

    MassEmailDialog.superclass.constructor.call(this, {
        title: 'Mass email',
        y: 250,
        autoWidth: true,
        autoHeight: true,
        closeAction: 'close',
        modal: true,
        buttonAlign: 'center',
        items: this.FieldsForm
    });
};

Ext.extend(MassEmailDialog, Ext.Window, {
    startMassMailing: function (arrCompanyIds) {
        var wnd = this;
        if (!wnd.FieldsForm.getForm().isValid()) {
            return;
        }

        // All params are correct, show progress bar dialog
        var sender = new MassEmailSender(arrCompanyIds, wnd.templateCombo.getValue(), wnd.sendToCombo.getValue(), [], wnd.ignoreSending.checked);
        sender.show();

        // Close this window
        wnd.close();
    }
});