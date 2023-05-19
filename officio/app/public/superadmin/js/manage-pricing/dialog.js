var ManagePricingDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisDialog   = this;
    var data         = config.params.data;
    var booAddAction = config.params.action == 'add';

    var categorySettingsFieldSet = new Ext.form.FieldSet({
        title:      'Category Settings',
        labelWidth: 120,
        autoHeight: true,
        items: [{
            layout: 'column',

            items: [
                {
                    xtype: 'hidden',
                    name: 'pricing_category_id',
                    value: config.params.pricing_category_id
                },
                {
                    columnWidth: 0.5,
                    layout: 'form',

                    items: [
                        {
                            fieldLabel: 'Category Name',
                            name:       'name',
                            xtype:      'textfield',
                            allowBlank: false,
                            disabled:   !data.allow_edit_name && !booAddAction,
                            width:      115,
                            value:      config.params.pricingCategoryName
                        },
                        {
                            fieldLabel: 'Key String',
                            name:       'key_string',
                            xtype:      'textfield',
                            disabled:   !data.allow_edit_name && !booAddAction,
                            width:      115,
                            value:      data.key_string
                        }, {
                            fieldLabel: 'Replacing the General pricing?',
                            name:       'replacing_general',
                            xtype:      'checkbox',
                            hidden:     !data.allow_edit_name,
                            width:      115,
                            checked:    data.replacing_general === 'Y'
                        }
                    ]
                },
                {
                    columnWidth: 0.5,
                    layout:      'form',

                    items: [
                        {
                            fieldLabel: 'Expiry Date',
                            name:       'expiry_date',
                            xtype:      'datefield',
                            format:     dateFormatFull,
                            disabled:   !data.allow_edit_name && !booAddAction,
                            width:      115,
                            value:      data.expiry_date
                        }, {
                            fieldLabel: 'Default Subscription Term',
                            name:       'default_subscription_term',
                            hiddenName: 'default_subscription_term',
                            xtype:      'combo',
                            store: new Ext.data.Store({
                                data: [
                                    {'termId': 'annual', 'termName': 'Annual'},
                                    {'termId': 'monthly', 'termName': 'Monthly'}
                                ],
                                reader: new Ext.data.JsonReader(
                                    {id: 0},
                                    Ext.data.Record.create([
                                        {name: 'termId'},
                                        {name: 'termName'}
                                    ])
                                )
                            }),
                            mode:          'local',
                            valueField:    'termId',
                            displayField:  'termName',
                            triggerAction:  'all',
                            forceSelection: true,
                            value:          data.default_subscription_term,
                            readOnly:       true,
                            typeAhead:      false,
                            selectOnFocus:  true,
                            editable:       false,
                            width:          115,
                            listWidth:      115
                        }
                    ]
                }
            ]
        },{
            fieldLabel: 'Promo Message',
            name:       'key_message',
            xtype:      'textfield',
            disabled:   !data.allow_edit_name,
            width:      380,
            value:      data.key_message
        }],
    });


    var pricingFieldSet = new Ext.form.FieldSet({
        title:      'Pricing',
        labelWidth: 200,
        autoHeight: true,
        layout:     'column',

        items: [
            {
                columnWidth: 0.5,
                layout:      'form',

                items: [
                    {
                        fieldLabel: 'Additional GB/month',
                        name:       'price_storage_1_gb_monthly',
                        xtype:      'moneyfield',
                        value:      data.price_storage_1_gb_monthly
                    }
                ]
            },
            {
                columnWidth: 0.5,
                layout:      'form',

                items: [
                    {
                        fieldLabel: 'Additional GB/year',
                        name:       'price_storage_1_gb_annual',
                        xtype:      'moneyfield',
                        value:      data.price_storage_1_gb_annual
                    }
                ]
            }
        ]
    });

    var arrTabs = [];
    for (var i = 0; i < arrSubscriptions.length; i++) {
        var sId = arrSubscriptions[i]['subscription_id'];

        var newTab = new Ext.Panel({
            title:      arrSubscriptions[i]['subscription_name'],
            autoWidth:  true,
            autoHeight: true,
            labelWidth: 200,
            bodyStyle:  'padding: 8px',
            layout:     'column',

            items: [
                {
                    columnWidth: 0.5,
                    layout:      'form',

                    items: [
                        {
                            fieldLabel:    'Free storage included',
                            name:          sId + '_free_storage',
                            xtype:         'numberfield',
                            width:         60,
                            allowDecimals: false,
                            allowNegative: false,
                            minValue:      1,
                            maxValue:      1000,
                            allowBlank:    false,
                            value:         data[sId + '_free_storage']
                        },
                        {
                            fieldLabel:     'Free clients included: <img src="/images/icons/help.png" ext:qtip="0 if there is no limit." />',
                            labelSeparator: '',
                            name:           sId + '_free_clients',
                            xtype:          'numberfield',
                            width:          60,
                            allowDecimals:  false,
                            allowNegative:  false,
                            minValue:       0,
                            maxValue:       1000,
                            allowBlank:     false,
                            value:          data[sId + '_free_clients']
                        },
                        {
                            fieldLabel: 'Allow add more users than allowed',
                            name:       sId + '_users_add_over_limit',
                            xtype:      'checkbox',
                            checked:    parseInt(data[sId + '_users_add_over_limit'], 10) > 0,
                            inputValue: 1
                        },
                        {
                            fieldLabel: 'Additional users/year',
                            name:       sId + '_price_license_user_annual',
                            xtype:      'moneyfield',
                            value:      data[sId + '_price_license_user_annual']
                        },
                        {
                            fieldLabel: 'Package price per year',
                            name:       sId + '_price_package_yearly',
                            xtype:      'moneyfield',
                            value:      data[sId + '_price_package_yearly']
                        }
                    ]
                },
                {
                    columnWidth: 0.5,
                    layout:      'form',

                    items: [
                        {
                            fieldLabel:    'User licenses included',
                            name:          sId + '_user_included',
                            xtype:         'numberfield',
                            width:         60,
                            allowDecimals: false,
                            minValue:      1,
                            maxValue:      1000,
                            allowBlank:    false,
                            value:         data[sId + '_user_included']
                        },
                        {
                            fieldLabel: 'Additional users/month',
                            name:       sId + '_price_license_user_monthly',
                            xtype:      'moneyfield',
                            value:      data[sId + '_price_license_user_monthly']
                        },
                        {
                            fieldLabel: 'Package price per month',
                            name:       sId + '_price_package_monthly',
                            xtype:      'moneyfield',
                            value:      data[sId + '_price_package_monthly']
                        },
                        {
                            fieldLabel: 'Package price per 2 years',
                            name:       sId + '_price_package_2_years',
                            xtype:      'moneyfield',
                            value:      data[sId + '_price_package_2_years']
                        }
                    ]
                }
            ]
        });

        arrTabs.push(newTab);
    }

    if (empty(arrTabs.length)) {
        // Can't be here
        arrTabs.push({
            xtype: 'panel',
            title: 'No subscriptions',
            items: {html: '<div style="padding: 5px;">No subscriptions!</div>'}
        });
    }

    var packagesTabPanel = new Ext.TabPanel({
        items:          arrTabs,
        activeTab:      0,
        deferredRender: false,

        listeners: {
            tabchange: function () {
                thisDialog.syncShadow();
            }
        }
    });

    this.mainFormPanel = new Ext.FormPanel({
        labelWidth: 200,
        bodyStyle:  'padding:5px',

        items: [
            categorySettingsFieldSet,
            pricingFieldSet,
            packagesTabPanel
        ]
    });


    ManagePricingDialog.superclass.constructor.call(this, {
        title:      booAddAction ? '<i class="las la-plus"></i>' + _('Add New Pricing Category') : '<i class="las la-edit"></i>' + _('Edit Pricing Category'),
        modal:      true,
        autoWidth:  true,
        autoHeight: true,
        resizable:  false,
        items:      this.mainFormPanel,

        buttons: [
            {
                text:    'Cancel',
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text:    '<i class="las la-save"></i>' + _('Save'),
                cls:     'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(ManagePricingDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();
    },

    loadSettings: function () {
        this.mainFormPanel.getForm().clearInvalid();
    },

    saveChanges: function () {
        var thisDialog = this;
        if (!thisDialog.mainFormPanel.getForm().isValid()) {
            return false;
        }

        thisDialog.getEl().mask('Saving...');
        this.mainFormPanel.getForm().submit({
            url: baseUrl + '/manage-pricing/save',

            success: function () {
                thisDialog.owner.store.reload();

                Ext.simpleConfirmation.msg('Info', 'Done!');
                thisDialog.close();
            },

            failure: function (form, action) {
                thisDialog.getEl().unmask();

                var msg = action && action.result && action.result.message ? action.result.message : 'Information cannot be saved. Please try again later.';
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});