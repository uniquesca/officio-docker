var PUABusinessContactDialog = function (parentGrid, oPUARecord) {
    this.parentGrid = parentGrid;

    this.FieldsForm = new Ext.form.FormPanel({
        frame:      false,
        fileUpload: true,
        bodyStyle:  'padding: 5px',
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name:  'pua_id'
            }, {
                xtype: 'hidden',
                name:  'pua_type'
            }, {
                xtype:       'container',
                layout:      'form',
                defaultType: 'textfield',
                defaults:    {width: 500},

                items: [
                    {
                        fieldLabel: 'Name',
                        name:       'pua_business_contact_name',
                        allowBlank: false
                    }, {
                        fieldLabel: 'Type of Business Contact or Service',
                        name:       'pua_business_contact_or_service',
                        xtype:      'combo',
                        emptyText:  'Type or select from the list...',

                        store: new Ext.data.ArrayStore({
                            fields: ['text'],
                            data:   [
                                ['Banks'], ['Accountant'], ['Bookkeeper'], ['Lawyer'], ['Staff'], ['Emails'], ['Social media'], ['Internet Services'], ['Landlord'], ['Insurance'], ['Phone/Communication'], ['Sales Agent'], ['Important Client'], ['Other']
                            ]
                        }),

                        mode:           'local',
                        displayField:   'text',
                        valueField:     'text',
                        typeAhead:      true,
                        forceSelection: false,
                        triggerAction:  'all',
                        selectOnFocus:  true,
                        editable:       true
                    }, {
                        fieldLabel: 'Phone',
                        name:       'pua_business_contact_phone'
                    }, {
                        fieldLabel: 'Email',
                        vtype:      'email',
                        name:       'pua_business_contact_email'
                    }, {
                        fieldLabel: 'Username (if applicable)',
                        name:       'pua_business_contact_username'
                    }, {
                        fieldLabel: 'Password (if applicable)',
                        name:       'pua_business_contact_password'
                    }, {
                        fieldLabel: 'Instructions',
                        xtype:      'textarea',
                        name:       'pua_business_contact_instructions'
                    }
                ]
            }
        ]
    });

    this.buttons = [
        {
            text:    'Close',
            scope:   this,
            handler: function () {
                this.close();
            }
        },
        {
            text:    'Save',
            cls:     'orange-btn',
            handler: this.saveChanges.createDelegate(this)
        }
    ];

    PUABusinessContactDialog.superclass.constructor.call(this, {
        title:       empty(oPUARecord['data']['pua_id']) ? '<i class="las la-plus"></i>' + _('New Business Contact or Service') : '<i class="las la-edit"></i>' + _('Edit Business Contact or Service'),
        y:           10,
        autoWidth:   true,
        autoHeight:  true,
        closeAction: 'close',
        plain:       true,
        modal:       true,
        buttonAlign: 'center',
        items:       this.FieldsForm
    });

    this.on('show', this.initDialog.createDelegate(this, [oPUARecord]), this);
};

Ext.extend(PUABusinessContactDialog, Ext.Window, {
    initDialog: function (oPUARecord) {
        this.FieldsForm.getForm().loadRecord(oPUARecord);
    },

    saveChanges: function () {
        var thisDialog = this;

        if (thisDialog.FieldsForm.getForm().isValid()) {
            // Prevent submitting emptyText
            var setupEmptyFields = function (f) {
                if (f.el && f.el.getValue() == f.emptyText) {
                    f.el.dom.value = "";
                }
                if (f.items && f.items.length && f.rendered) {
                    f.items.each(setupEmptyFields);
                }
            };
            thisDialog.FieldsForm.items.each(setupEmptyFields);

            thisDialog.getEl().mask('Saving...');

            thisDialog.FieldsForm.getForm().submit({
                url: baseUrl + '/manage-members-pua/manage',

                success: function (form, action) {
                    thisDialog.parentGrid.store.reload();

                    thisDialog.getEl().mask(empty(action.result.message) ? 'Done!' : action.result.message);
                    setTimeout(function () {
                        thisDialog.close();
                    }, 750);
                },

                failure: function (form, action) {
                    if (!empty(action.result.message)) {
                        Ext.simpleConfirmation.error(action.result.message);
                    } else {
                        Ext.simpleConfirmation.error('Cannot save info.');
                    }

                    thisDialog.getEl().unmask();
                }
            });
        }
    }
});