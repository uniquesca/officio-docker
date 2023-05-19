Ext.onReady(function(){
    // Init tooltips
    Ext.QuickTips.init();
    $('#trial_pricing').css('min-height', getSuperadminPanelHeight() + 'px');

    var globalFieldSet = new Ext.form.FieldSet({
        title: 'Global',
        labelWidth: 250,
        autoHeight: true,
        layout: 'column',
        items: [
            {
                columnWidth: 0.5,
                layout: 'form',
                items: [
                    {
                        id: 'cutting_of_service_days',
                        xtype: 'numberfield',
                        fieldLabel: 'Cutting of Service (days)',
                        allowBlank: false,
                        allowNegative: false,
                        allowDecimals: false,
                        minValue: 1,
                        width: 100,
                        value: arrPriceSettings.cutting_of_service_days
                    }, {
                        id: 'price_training',
                        xtype: 'moneyfield',
                        fieldLabel: 'Training',
                        width: 100,
                        value: arrPriceSettings.before_expiration.feeTraining
                    }
                ]
            }, {
                columnWidth: 0.5,
                layout: 'form',
                items: [
                    {
                        id: 'last_charge_failed_show_days',
                        xtype: 'numberfield',
                        fieldLabel: 'Show last charge failed dialog in (days)',
                        allowBlank: false,
                        allowNegative: false,
                        allowDecimals: false,
                        minValue: 1,
                        width: 100,
                        value: arrPriceSettings.last_charge_failed_show_days
                    }
                ]
            }
        ]
    });


    var fp = new Ext.FormPanel({
        frame: false,
        labelWidth: 150,
        width: 800,
        renderTo: 'trial_pricing',
        bodyStyle: 'padding:0 10px 0;',
        buttonAlign: 'center',
        items: [
            globalFieldSet,
            {
                xtype: 'fieldset',
                title: 'Before expiry',
                autoHeight: true,
                items: [
                    {
                        id: 'before_exp_free_users',
                        xtype: 'numberfield',
                        fieldLabel: 'Free Users Count',
                        allowBlank: false,
                        allowDecimals: false,
                        allowNegative: false,
                        minValue: 1,
                        width: 100,
                        value: arrPriceSettings.before_expiration.freeUsers
                    }, {
                        xtype: 'moneyfield',
                        id: 'before_exp_fee_annual',
                        fieldLabel: 'Fee Annual',
                        width: 100,
                        value: arrPriceSettings.before_expiration.feeAnnual
                    }, {
                        id: 'before_exp_fee_annual_discount',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Annual Discount',
                        minValue: 0,
                        width: 100,
                        value: arrPriceSettings.before_expiration.feeAnnualDiscount
                    }, {
                        id: 'before_exp_fee_monthly',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Monthly',
                        width: 100,
                        value: arrPriceSettings.before_expiration.feeMonthly
                    }, {
                        id: 'before_exp_fee_monthly_discount',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Monthly Discount',
                        minValue: 0,
                        width: 100,
                        value: arrPriceSettings.before_expiration.feeMonthlyDiscount
                    }, {
                        id: 'before_exp_license_annual',
                        xtype: 'moneyfield',
                        fieldLabel: 'User License Annual',
                        width: 100,
                        value: arrPriceSettings.before_expiration.licenseAnnual
                    }, {
                        id: 'before_exp_license_monthly',
                        xtype: 'moneyfield',
                        fieldLabel: 'User License Monthly',
                        width: 100,
                        value: arrPriceSettings.before_expiration.licenseMonthly
                    }, {
                        id: 'before_exp_discount_label',
                        xtype: 'froalaeditor',
                        booAllowImagesUploading: true,
                        fieldLabel: 'Top Discount Label',
                        width: 450,
                        height: 150,
                        layout: 'fit',
                        value: arrPriceSettings.before_expiration.discountLabel
                    }
                ]
            }, {
                xtype: 'fieldset',
                title: 'After expiry',
                autoHeight: true,
                items: [
                    {
                        id: 'after_exp_free_users',
                        xtype: 'numberfield',
                        fieldLabel: 'Free Users Count',
                        allowBlank: false,
                        allowDecimals: false,
                        allowNegative: false,
                        minValue: 1,
                        width: 100,
                        value: arrPriceSettings.after_expiration.freeUsers
                    }, {
                        id: 'after_exp_fee_annual',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Annual',
                        width: 100,
                        value: arrPriceSettings.after_expiration.feeAnnual
                    }, {
                        id: 'after_exp_fee_annual_discount',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Annual Discount',
                        minValue: 0,
                        width: 100,
                        value: arrPriceSettings.after_expiration.feeAnnualDiscount
                    }, {
                        id: 'after_exp_fee_monthly',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Monthly',
                        width: 100,
                        value: arrPriceSettings.after_expiration.feeMonthly
                    }, {
                        id: 'after_exp_fee_monthly_discount',
                        xtype: 'moneyfield',
                        fieldLabel: 'Fee Monthly Discount',
                        minValue: 0,
                        width: 100,
                        value: arrPriceSettings.after_expiration.feeMonthlyDiscount
                    }, {
                        id: 'after_exp_license_annual',
                        xtype: 'moneyfield',
                        fieldLabel: 'User License Annual',
                        width: 100,
                        value: arrPriceSettings.after_expiration.licenseAnnual
                    }, {
                        id: 'after_exp_license_monthly',
                        xtype: 'moneyfield',
                        fieldLabel: 'User License Monthly',
                        allowBlank: false,
                        width: 100,
                        value: arrPriceSettings.after_expiration.licenseMonthly
                    }, {
                        id: 'after_exp_discount_label',
                        xtype: 'froalaeditor',
                        booAllowImagesUploading: true,
                        fieldLabel: 'Top Discount Label',
                        allowBlank: false,
                        width: 450,
                        height: 150,
                        layout: 'fit',
                        value: arrPriceSettings.after_expiration.discountLabel
                    }
                ]
            }
        ],
        buttons: [{
            text: 'Reset',
            handler: function(){
                fp.getForm().reset();
            }
        },
            {
            text: 'Save',
            cls:  'orange-btn',
            handler: function(){
               if(fp.getForm().isValid()){
                    Ext.getBody().mask('Saving...');
                    
                    fp.getForm().submit({
                        url: baseUrl + '/manage-trial-pricing/save',

                        success: function(form, action) {
                            Ext.simpleConfirmation.success(action.result.message);
                            Ext.getBody().unmask();
                        },

                        failure: function(form, action) {
                            if(!empty(action.result.message)) {
                                Ext.simpleConfirmation.error(action.result.message);
                            } else {
                                Ext.simpleConfirmation.error('Cannot save info');
                            }
                            
                            Ext.getBody().unmask();
                        }
                    });
                }
            }
        }]
    });

    updateSuperadminIFrameHeight('#trial_pricing');
});