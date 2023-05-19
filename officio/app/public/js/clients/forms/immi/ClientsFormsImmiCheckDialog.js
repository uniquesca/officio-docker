var ClientsFormsImmiDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var usersCombo = new Ext.form.ComboBox({
        id:         'immi_account_member_id',
        fieldLabel: 'Login to ImmiAccount as',
        width:      270,

        store: {
            xtype:  'store',
            reader: new Ext.data.JsonReader({
                id: 'option_id'
            }, [{name: 'option_id'}, {name: 'option_name'}]),
            data:   arrApplicantsSettings.vevo_members_list
        },

        displayField:   'option_name',
        valueField:     'option_id',
        mode:           'local',
        searchContains: true,
        forceSelection: true,
        triggerAction:  'all',
        allowBlank:     false,
        selectOnFocus:  true
    });

    if (arrApplicantsSettings.vevo_members_list.length == 1) {
        usersCombo.setValue(arrApplicantsSettings.vevo_members_list[0].option_id);
    }

    this.mainForm = new Ext.form.FormPanel({
        labelWidth: 165,
        items:      [{
            xtype: 'panel',
            style: 'font-size: 12px; padding: 5px 2px 15px;',
            html:  'eVisa application for <b>Permanent Employer Sponsored or Nominated Visa</b>'
        }, usersCombo, {
            xtype:      'fieldset',
            title:      'Nomination details',
            labelWidth: 150,
            autoHeight: true,
            style:      'margin-top: 15px;',

            items: [{
                id:         'immi_ref_num_type',
                xtype:      'combo',
                width:      270,
                fieldLabel: 'Reference number type',

                store: new Ext.data.SimpleStore({
                    fields: ['option_name'],
                    data:   [['Application ID/Nomination approval number'], ['Nomination TRN']]
                }),

                displayField:   'option_name',
                valueField:     'option_name',
                mode:           'local',
                editable:       false,
                forceSelection: true,
                triggerAction:  'all',
                allowBlank:     false,
                selectOnFocus:  true,
                value:          'Application ID/Nomination approval number',
                listeners:      {
                    beforeselect: this.toggleReferenceNumber.createDelegate(this)
                }
            }, {
                id:         'immi_trn',
                fieldLabel: 'Transaction Reference Number (TRN)',
                xtype:      'textfield',
                hidden:     true,
                disabled:   true,
                allowBlank: false,
                width:      270
            }, {
                id:         'immi_ref_num',
                fieldLabel: 'Reference number',
                xtype:      'textfield',
                allowBlank: false,
                width:      270
            }]
        }, {
            xtype:      'fieldset',
            title:      'Current application',
            labelWidth: 150,
            autoHeight: true,

            items: [{
                id:         'immi_subclass',
                xtype:      'combo',
                width:      50,
                fieldLabel: 'Subclass',

                store: new Ext.data.SimpleStore({
                    fields: ['option_name'],
                    data:   [['186'], ['187']]
                }),

                displayField:   'option_name',
                valueField:     'option_name',
                mode:           'local',
                editable:       false,
                forceSelection: true,
                triggerAction:  'all',
                allowBlank:     false,
                selectOnFocus:  true
            }, {
                id:         'immi_app_stream',
                xtype:      'combo',
                width:      270,
                fieldLabel: 'Visa application stream ',

                store: new Ext.data.SimpleStore({
                    fields: ['option_name'],
                    data:   [['Direct entry'], ['Agreement'], ['Temporary residence transition']]
                }),

                displayField:   'option_name',
                valueField:     'option_name',
                mode:           'local',
                editable:       false,
                forceSelection: true,
                triggerAction:  'all',
                allowBlank:     false,
                selectOnFocus:  true
            }]
        }, {
            xtype: 'panel',
            style: 'font-size: 12px; padding: 10px 5px 5px 2px;',
            html:  'By clicking "Submit Application" they will agree to ImmiAccount Terms and conditions.'
        }]
    });

    ClientsFormsImmiDialog.superclass.constructor.call(this, {
        title:       'eVisa application',
        iconCls:     'forms-btn-icon-submit-immi',
        resizable:   false,
        bodyStyle:   'padding: 5px; background-color:white;',
        autoHeight:  true,
        autoWidth:   true,
        modal:       true,
        items:       this.mainForm,

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: 'Submit Application',
                cls: 'orange-btn',
                width: 120,
                handler: this.submitInfo.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ClientsFormsImmiDialog, Ext.Window, {
    toggleReferenceNumber: function (combo, record) {
        var booShowTRN = record.data.option_name == 'Nomination TRN';
        Ext.getCmp('immi_trn').setVisible(booShowTRN);
        Ext.getCmp('immi_trn').setDisabled(!booShowTRN);

        Ext.getCmp('immi_ref_num').setVisible(!booShowTRN);
        Ext.getCmp('immi_ref_num').setDisabled(booShowTRN);

        this.syncShadow();
    },

    submitInfo: function () {
        if (this.mainForm.getForm().isValid()) {
            ClientsFormsImmiSender.start({
                form_id:                Ext.encode(this.formId),
                immi_account_member_id: Ext.encode(Ext.getCmp('immi_account_member_id').getValue()),
                immi_ref_num_type:      Ext.encode(Ext.getCmp('immi_ref_num_type').getValue()),
                immi_trn:               Ext.encode(Ext.getCmp('immi_trn').getValue()),
                immi_ref_num:           Ext.encode(Ext.getCmp('immi_ref_num').getValue()),
                immi_subclass:          Ext.encode(Ext.getCmp('immi_subclass').getValue()),
                immi_app_stream:        Ext.encode(Ext.getCmp('immi_app_stream').getValue())
            });

            this.close();
        }
    }
});