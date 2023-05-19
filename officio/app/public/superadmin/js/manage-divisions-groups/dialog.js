var DivisionsGroupsDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    this.thisForm = new Ext.form.FormPanel({
        baseCls:    'x-plain',
        labelWidth: 80,
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name:  'division_group_id',
                value: 0
            }, {
                xtype:          'tabpanel',
                plain:          true,
                activeTab:      0,
                deferredRender: false,
                defaults:       {bodyStyle: 'padding:10px'},
                items:          [
                    {
                        title:       'Personal Details',
                        layout:      'form',
                        defaults:    {width: 400},
                        defaultType: 'textfield',

                        items: [
                            {
                                fieldLabel: 'Salutation',
                                xtype:      'combo',

                                store: {
                                    xtype:  'store',
                                    reader: new Ext.data.JsonReader({
                                        id: 'option_id'
                                    }, Ext.data.Record.create([
                                        {name: 'option_id'}, {name: 'option_name'}
                                    ])),
                                    data:   arrSalutations
                                },

                                emptyText:      'Please select...',
                                mode:           'local',
                                valueField:     'option_id',
                                displayField:   'option_name',
                                value:          '',
                                typeAhead:      false,
                                editable:       true,
                                forceSelection: true,
                                triggerAction:  'all',
                                selectOnFocus:  true,
                                name:           'division_group_salutation',
                                hiddenName:     'division_group_salutation',
                                anchor:         '100%'
                            }, {
                                fieldLabel: 'First name',
                                name:       'division_group_first_name',
                                anchor:     '100%'
                            }, {
                                fieldLabel: 'Last name',
                                name:       'division_group_last_name',
                                anchor:     '100%'
                            }, {
                                fieldLabel: 'Position',
                                name:       'division_group_position',
                                anchor:     '100%'
                            }, {
                                fieldLabel: 'Company <em class="required" title="This is a required field">*</em>',
                                name:       'division_group_company',
                                anchor:     '100%',
                                allowBlank: false
                            }
                        ]
                    }, {
                        title:  'Contact Information',
                        layout: 'column',
                        border: false,
                        items:  [
                            {
                                columnWidth: .5,
                                defaults:    {width: 300},
                                defaultType: 'textfield',
                                layout:      'form',
                                border:      false,
                                items:       [
                                    {
                                        fieldLabel: 'Address 1',
                                        name:       'division_group_address1',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'City',
                                        name:       'division_group_city',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Country',
                                        name:       'division_group_country',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Phone (main)',
                                        name:       'division_group_phone_main',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Email (primary)',
                                        name:       'division_group_email_primary',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Fax',
                                        name:       'division_group_fax',
                                        anchor:     '95%'
                                    }
                                ]
                            }, {
                                columnWidth: .5,
                                defaults:    {width: 300},
                                defaultType: 'textfield',
                                layout:      'form',
                                border:      false,
                                items:       [
                                    {
                                        fieldLabel: 'Address 2',
                                        name:       'division_group_address2',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'State',
                                        name:       'division_group_state',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Postal code',
                                        name:       'division_group_postal_code',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Phone (secondary)',
                                        name:       'division_group_phone_secondary',
                                        anchor:     '95%'
                                    }, {
                                        fieldLabel: 'Email (other)',
                                        name:       'division_group_email_other',
                                        anchor:     '95%'
                                    }
                                ]
                            }
                        ]
                    }, {
                        cls:      'x-plain',
                        title:    'Notes',
                        layout:   'fit',
                        defaults: {
                            width:  600,
                            height: 273
                        },
                        items:    {
                            xtype: 'htmleditor',
                            name:  'division_group_notes'
                        }
                    }
                ]
            }
        ]
    });

    DivisionsGroupsDialog.superclass.constructor.call(this, {
        y: 10,
        title:       empty(config.params.division_group_id) ? 'New Authorised Agent' : 'Update Authorised Agent',
        iconCls:     empty(config.params.division_group_id) ? 'division-group-icon-add' : 'division-group-icon-edit',
        modal:       true,
        autoHeight:  true,
        autoWidth:   true,
        layout:      'form',
        plain:       true,
        bodyStyle:   'padding:5px;',
        buttonAlign: 'center',
        items:       this.thisForm,
        buttons:     [
            {
                text:    'Cancel',
                handler: this.closeDialog.createDelegate(this)
            }, {
                cls:     'orange-btn',
                text:    empty(config.params.division_group_id) ? 'Create' : 'Save',
                handler: this.saveSettings.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(DivisionsGroupsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    loadSettings: function () {
        var thisWindow = this;
        if (!empty(thisWindow.params.division_group_id)) {
            thisWindow.getEl().mask('Loading...');

            thisWindow.thisForm.getForm().load({
                url: baseUrl + '/manage-divisions-groups/get-record',

                params: {
                    division_group_id: thisWindow.params.division_group_id
                },

                success: function () {
                    thisWindow.getEl().unmask();
                },

                failure: function (form, action) {
                    try {
                        Ext.simpleConfirmation.error(action.result.msg);
                    } catch (e) {
                        Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                    }

                    thisWindow.closeDialog();
                }
            });
        }
    },

    saveSettings: function () {
        var thisDialog = this;
        if (thisDialog.thisForm.getForm().isValid()) {
            thisDialog.getEl().mask('Saving...');

            thisDialog.thisForm.getForm().submit({
                url: baseUrl + '/manage-divisions-groups/save-record',

                success: function () {
                    thisDialog.owner.store.reload();


                    thisDialog.getEl().mask('Done!');
                    setTimeout(function () {
                        thisDialog.close();
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
                            Ext.simpleConfirmation.error(action.result.msg);
                            break;
                    }

                    thisDialog.getEl().unmask();
                }
            });
        }
    }
});