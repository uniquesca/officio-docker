$(document).ready(function() {
    Ext.QuickTips.init();
    
    $('.msgbox').animate({opacity: 1.0}, 2000,function(){
        $(this).fadeOut('slow');
    });
});

function savePackagesInfo() {
    
    if(!canManageCompanyPackages) {
        return false;
    }

    var booGSTAutoSelected = Ext.getCmp('gst-auto') ? Ext.getCmp('gst-auto').getValue() : true;
    Ext.getBody().mask('Saving...');
    Ext.Ajax.request({
        url: baseUrl + "/manage-company/save-packages",
        params: {
            company_id:               company_id,
            trial:                    Ext.encode(Ext.getCmp('trial').getValue()),
            packages:                 Ext.encode(Ext.getCmp('subscription_description').getValue()),
            next_billing_date:        Ext.encode(Ext.getCmp('next_billing_date').getValue()),
            billing_frequency:        Ext.encode(Ext.getCmp('billing_frequency').getValue()),
            internal_note:            Ext.encode(Ext.getCmp('internal_note').getValue()),
            subscription_fee:         Ext.encode(Ext.getCmp('subscription_fee').getValue()),
            gst:                      Ext.encode(booGSTAutoSelected ? 0 : Ext.getCmp('gst_exception_num').getValue()),
            gst_type:                 Ext.encode(booGSTAutoSelected ? 'auto' : Ext.getCmp('gst_exception_type').getValue()),
            free_users:               Ext.encode(Ext.getCmp('free_users').getValue()),
            free_clients:             Ext.encode(Ext.getCmp('free_clients').getValue()),
            free_storage:             Ext.encode(Ext.getCmp('free_storage').getValue()),
            pt_profile_id:            Ext.encode(Ext.getCmp('pt_profile_id').getValue())
        },
        success: function(f) {
            var result = Ext.decode(f.responseText);
            if(!result.success) {
                Ext.getBody().unmask();
                var msg = empty(result.msg) ? "Can't save packages" : result.msg;
                Ext.simpleConfirmation.error(msg);
            } else {
                Ext.getBody().mask('Company packages list was successfully updated.');
                
                // Avoid browser popup ask to resubmit data
                var currentTime = new Date();
                var url = baseUrl + '/manage-company/edit?company_id=' + company_id + '&time='+currentTime.getTime()+'#company-packages';
                window.location.assign(url);
            }
        },
        failure: function() {
            Ext.simpleConfirmation.error("Can't save packages");
            Ext.getBody().unmask();
        }
    });
    
    return true;
}

var checkAccountingDetailsWereChanged = function () {
    var booChanged = false;
    
    if(canManageCompanyPackages) {
        // Check such fields for changes
        var arrFieldsToCheck = [
            'subscription_description',
            'next_billing_date',
            'billing_frequency',
            'free_users',
            'free_clients',
            'free_storage',
            'subscription_fee',
            'gst-auto',
            'gst_exception_num',
            'gst_exception_type',
            'internal_note'
        ];
        
        Ext.each(arrFieldsToCheck, function(fieldId){
            var field = Ext.getCmp(fieldId);
            if(field && field.isDirty()) {
                booChanged = true;
            }
        });
    }
    
    return booChanged;
};

var checkForChangesAndAskUser = function (callback, param, param2) {
    if(!checkAccountingDetailsWereChanged()) {
        return callback(param, param2);
    }

    // Ask user if we need to proceed
    Ext.MessageBox.buttonText.yes = "Yes, ignore not saved changes";
    Ext.Msg.confirm('Please confirm', 'Company details were changed, but these changes were not saved yet. Are you sure want to proceed?',
        function(btn){
            if(btn == 'yes'){
                return callback(param, param2);
            }
        }
    );
};


function getPackagesInfo(info) {
    if(Ext.getCmp('packages-pan') || $('#packages-info').length === 0) {
        return false;
    }
    
    var subscription_details;
    if(canManageCompanyPackages) {
        subscription_details = new Ext.form.ComboBox({
            id: 'subscription_description',
            width: 240,
            listWidth: 240,
            fieldLabel: 'Subscription Plan',
            store: new Ext.data.Store({
                data: info.packages_list,
                reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'subscription_id'}, {name: 'subscription_name'}]))
            }),
            mode: 'local',
            valueField: 'subscription_id',
            displayField: 'subscription_name',
            triggerAction: 'all',
            forceSelection: true,
            readOnly: true,
            typeAhead: true,
            selectOnFocus: true,
            editable: false,
            listeners: {
                beforeselect: function(n, selRecord) {
                    if(selRecord.data.subscription_id != this.getValue()) {
                        Ext.Msg.confirm('Please confirm', 'Do you want to automatically update the Subscription Recurring Charge for this new Subscription plan?', function(btn, text){
                            if(btn == 'yes') {
                                updateUsersCharges();
                                updateSubscriptionAmount();
                            }
                        });
                    }
                }
            }
        });
        
        var sd_items = subscription_details.store.data.items;
        for(var i=0; i<sd_items.length; i++) {
            if(sd_items[i].data.subscription_id == info.subscription) {
                subscription_details.setValue(sd_items[i].data.subscription_id);
                break;
            }
        }
    } else {
        subscription_details = new Ext.form.DisplayField({
            fieldLabel: 'Subscription Plan',
            style: 'padding-top:2px',
            value: info.subscription_name
        });
    }

    
    var trial;
    if(canManageCompanyPackages) {
        trial = new Ext.form.ComboBox({
            id: 'trial',
            width: 240,
            listWidth: 220,
            fieldLabel: 'Trial',
            store: new Ext.data.ArrayStore({
                fields: ['option_id', 'option_text'],
                data : [['Y', 'Yes'], ['N', 'No']]
            }),
            mode: 'local',
            valueField: 'option_id',
            displayField: 'option_text',
            triggerAction: 'all',
            forceSelection: true,
            readOnly: true,
            typeAhead: false,
            selectOnFocus: true,
            editable: false,
            value: info.trial
        });
    } else {
        trial = new Ext.form.DisplayField({
            fieldLabel: 'Trial',
            style: 'padding-top:2px',
            value: info.trial == 'Y' ? 'Yes' : 'No'
        });
    }
    
    var company_setup = new Ext.form.DisplayField({
        fieldLabel: 'Company Setup on',
        style: 'padding-top:2px',
        hidden: !canManageCompanyPackages,
        value: info.company_setup
    });
    
    var account_created = new Ext.form.DisplayField({
        fieldLabel: 'Account Created on',
        style: 'padding-top:2px',
        value: info.account_created_on
    });
    
    var next_billing_date;
    if(canManageCompanyPackages) {
        next_billing_date = new Ext.form.DateField({
            id: 'next_billing_date',
            fieldLabel: 'Next Billing Date',
            width: 240,
            format: dateFormatFull,
            value: info.next_billing_date == '0000-00-00' ? '' : info.next_billing_date
        });
    } else {
        next_billing_date = new Ext.form.DisplayField({
            fieldLabel: 'Next Billing Date',
            style: 'padding-top:2px',
            value: info.next_billing_date == '0000-00-00' ? '-' : Ext.util.Format.date(Date.parseDate(info.next_billing_date, 'Y-m-d'), dateFormatFull)
        });
    }
    
    var updateSubscriptionAmount = function() {
        var subscriptionPlan = subscription_details.getValue();
        var billingFrequency = parseInt(billing_frequency.getValue(), 10);
        
        var subscriptionAmount = 0;
        switch(billingFrequency) {
            case 2  : subscriptionAmount = parseInt(info.prices[subscriptionPlan].annually_price, 10);  break; // Annually
            case 3  : subscriptionAmount = parseInt(info.prices[subscriptionPlan].bi_price, 10); break; // Every two years
            default : subscriptionAmount = parseInt(info.prices[subscriptionPlan].month_price, 10);   break; // Monthly
        }

        // Update new amount
        subscription_fee.setValue(subscriptionAmount);
        subscription_fee.setRawValue(formatMoney('', subscriptionAmount, true));
        highlightEl(subscription_fee);
        
        // Update total
        recalculateBilledFee();
    };
    
    var billing_frequency;
    if(canManageCompanyPackages) {
        billing_frequency = new Ext.form.ComboBox({
            id: 'billing_frequency',
            width: 240,
            fieldLabel: 'Billing Frequency',
            store: new Ext.data.SimpleStore({
                fields: ['billing_id', 'billing_name'],
                data: [[1, 'Monthly'], [2, 'Annually'], [3, 'Every two years']]
            }),
            mode: 'local',
            valueField: 'billing_id',
            displayField: 'billing_name',
            triggerAction: 'all',
            forceSelection: true,
            readOnly: true,
            typeAhead: true,
            selectOnFocus: true,
            editable: false,
            listWidth: 240,
            value: parseInt(info.payment_term, 10) === 0 ? 1 : info.payment_term,
            listeners: {
                beforeselect: function(n, selRecord) {
                    var oldBillingFrequency = this.getValue();
                    var newBillingFrequency = selRecord.data.billing_id;
                    
                    if(oldBillingFrequency != newBillingFrequency) {
                        Ext.Msg.confirm('Please confirm', 'Do you want to automatically update the Subscription Recurring Charge for this new Payment Frequency?', function(btn, text){
                            if(btn == 'yes') {
                                updateStorageCharges(newBillingFrequency);
                                updateUsersCharges(free_users.getValue(), newBillingFrequency);
                                updateSubscriptionAmount();
                            } else {
                                updateStorageCharges(newBillingFrequency);
                                updateUsersCharges(free_users.getValue(), newBillingFrequency);
                            }
                        });
                    } else {
                        updateStorageCharges(newBillingFrequency);
                        updateUsersCharges(free_users.getValue(), newBillingFrequency);
                    }
                }
            }
        });
    } else {
        
        var packageName = '';
        switch(parseInt(info.payment_term, 10)) {
            case 2 : packageName  = 'Annually'; break;
            case 3 : packageName  = 'Every two years'; break;
            default : packageName = 'Monthly'; break;
        }
        
        billing_frequency = new Ext.form.DisplayField({
            fieldLabel: 'Billing Frequency',
            style: 'padding-top:2px',
            value: packageName
        });
    }
    
    var updateUsersCharges = function(usersCount, frequency, subscriptionPlan) {
        frequency            = !frequency ? billing_frequency.getValue() : frequency;
        var billingFrequency = parseInt(frequency, 10);
        usersCount           = !usersCount ? free_users.getValue() : usersCount;
        subscriptionPlan     = !subscriptionPlan ? subscription_details.getValue() : subscriptionPlan;

        var pricePerUserLicense = 0;
        switch (billingFrequency) {
            case 2  :
                pricePerUserLicense = parseInt(info.prices[subscriptionPlan].user_license_annually, 10);
                break; // Annually
            case 3  :
                pricePerUserLicense = parseInt(info.prices[subscriptionPlan].user_license_biannually, 10);
                break; // Every two years
            default :
                pricePerUserLicense = parseInt(info.prices[subscriptionPlan].user_license_monthly, 10);
                break; // Monthly
        }

        var intToPayUsers = active_users.getValue() - usersCount;

        var extraCharge = intToPayUsers <= 0 ? 0 : intToPayUsers * pricePerUserLicense;
        var booHighLight = extraCharge != extra_storage_charges.getValue();

        additional_user_charges.setValue(extraCharge);

        if(booHighLight) {
            highlightEl(additional_user_charges);
        }
        
        // Update total
        recalculateBilledFee();
    };
    
    var free_users;
    if (canManageCompanyPackages) {
        free_users = new Ext.form.NumberField({
            id:              'free_users',
            fieldLabel:      'Free Users Included',
            emptyText:       'Please Enter Free Users Number...',
            width:           240,
            allowNegative:   false,
            allowDecimals:   false,
            value:           info.free_users === 0 ? '' : info.free_users,
            enableKeyEvents: true,
            minValue:        1,
            maxValue:        100000,

            listeners: {
                'keyup': function () {
                    updateUsersCharges(free_users.getValue());
                }
            }
        });
    } else {
        free_users = new Ext.form.DisplayField({
            fieldLabel: 'Free Users Included',
            style: 'padding-top:2px',
            value: info.free_users
        });
    }

    var free_clients;
    if (canManageCompanyPackages) {
        free_clients = new Ext.form.NumberField({
            id:              'free_clients',
            fieldLabel:      'Free Clients Included: <img src="/images/icons/help.png" ext:qtip="0 if there is no limit." />',
            labelSeparator:  '',
            emptyText:       'Please Enter Free Clients Number...',
            width:           240,
            allowNegative:   false,
            allowDecimals:   false,
            value:           info.free_clients,
            enableKeyEvents: true,
            maxValue:        1000000
        });
    } else {
        free_clients = new Ext.form.DisplayField({
            fieldLabel: 'Free Clients Included',
            style:      'padding-top:2px',
            value:      empty(info.free_clients) ? 'Unlimited' : info.free_clients
        });
    }

    var updateStorageCharges = function(frequency) {
        frequency = !frequency ? billing_frequency.getValue() : frequency;
    
        switch(parseInt(frequency, 10)) {
            case 2 : multiply  = 12; break;
            case 3 : multiply  = 24; break;
            default : multiply = 1; break;
        }
    
        var storageToPay = Math.ceil(parseFloat(storage_used.getValue()) - parseFloat(free_storage.getValue()));
        var extraCharge = storageToPay <= 0 ? 0 : storageToPay * info.additional_storage_price * multiply;
        
        // highlight the field
        if(extraCharge != parseFloat(extra_storage_charges.getValue())) {
            highlightEl(extra_storage_charges);
        }
        
        // Update extra charge
        extra_storage_charges.setValue(extraCharge);
        
        
        // Update total
        recalculateBilledFee();
    };
    
    var free_storage;
    if(canManageCompanyPackages) {
        free_storage = new Ext.form.NumberField({
            id: 'free_storage',
            fieldLabel: 'Free Storage in GB Included',
            emptyText: 'Please Enter Free Storage...',
            width: 240,
            allowNegative: false,
            value: info.free_storage,
            enableKeyEvents: true,
            listeners: {
                keyup: function() {
                    updateStorageCharges();
                }
            }
        });
    } else {
        free_storage = new Ext.form.DisplayField({
            fieldLabel: 'Free Storage in GB Included',
            style: 'padding-top:2px',
            value: info.free_storage
        });
    }
    
    var total_billing_amount = new Ext.form.DisplayField({
        id: 'total_billing_amount',
        fieldLabel: 'Subtotal - Recurring Charge',
        style: 'padding-top:2px',
        hidden: !canManageCompanyPackages,
        value: info.billing_amount,
        formatValue: function(val) {
            return formatMoney(site_currency, val, true);
        }
    });
        
    var subscription_fee;
    if(canManageCompanyPackages) {
        subscription_fee = new Ext.form.NumberField({
            id: 'subscription_fee',
            fieldLabel: 'Subscription Recurring Fee',
            width: 180,
            value: info.subscription_fee,
            enableKeyEvents: true,
            listeners: {
                keyup: function() {
                    // Update total
                    recalculateBilledFee();
                }
            }
        });
        
        var subscription_fee_with_comment = new Ext.Panel({
            layout:'column',
            items: [{
                layout: 'form',
                width: 360,
                items: subscription_fee
            }, {
                xtype: 'label',
                style: 'padding-top: 5px',
                html: '<i>(not including additional user or storages charges)</i>'
            }]
        });
    } else {
        subscription_fee = new Ext.form.DisplayField({
            fieldLabel: 'Subscription Recurring Fee',
            style: 'padding-top:2px',
            value: info.subscription_fee,
            formatValue: function(val) {
                return formatMoney(site_currency, val, true) + '&nbsp;&nbsp;<i>(not including additional user or storages charges)</i>';
            }
        });
    }
    
    var gst = new Ext.form.DisplayField({
        id: 'gst_calculated',
        fieldLabel: 'GST/HST',
        style: 'padding-top: 5px',
        width: 50,
        hidden: !canManageCompanyPackages,
        value: 0,
        formatValue: function(val) {
            return formatMoney(site_currency, val, true);
        }
    });
    
    var gst_with_radios;
    if(canManageCompanyPackages) {
        gst_with_radios = new Ext.Panel({
            cls: 'vertical-align-middle',
            layout:'column',
            items: [{
                layout: 'form',
                width: 260,
                items: gst
            }, {
                xtype: 'label',
                style: 'padding-top: 5px',
                text: 'GST/HST %:'
            }, {
                layout: 'form',
                width: 230,
                bodyStyle: 'padding-left:10px;',
                items: [{
                    id: 'gst-auto',
                    xtype: 'radio',
                    width: 215,
                    fieldLabel: '',
                    hideLabel: true,
                    labelSeparator: '',
                    checked: info.gst_type == 'auto',
                    boxLabel: 'Auto Calculated at ' + parseFloat(info.gst_default).toFixed(2) + '%',
                    name: 'gst-radio',
                    inputValue: 'auto'
                }]
            }, {
                layout: 'form',
                width: 100,
                bodyStyle: 'padding-left: 5px;',
                items: [{
                    xtype: 'radio',
                    width: 100,
                    hideLabel: true,
                    labelSeparator: '',
                    checked: info.gst_type != 'auto',
                    boxLabel: 'Exception',
                    name: 'gst-radio',
                    inputValue: 'exception',
                    listeners: {
                        check: function(r, booChecked) {
                            Ext.getCmp('gst_exception_num').setDisabled(!booChecked);
                            Ext.getCmp('gst_exception_num_label').setDisabled(!booChecked);
                            Ext.getCmp('gst_exception_type').setDisabled(!booChecked);
                            recalculateBilledFee();
                        }
                    }
                }]
            },{
                width: 165,
                layout: 'column',
                bodyStyle: 'padding-left:5px;',
                items: [{
                    id: 'gst_exception_num',
                    xtype: 'numberfield',
                    width: 150,
                    minValue: 0,
                    hideLabel: true,
                    disabled: info.gst_type == 'auto',
                    value: info.gst,
                    labelSeparator: '',
                    enableKeyEvents: true,
                    listeners: {
                        keyup: function() {
                            // Update total
                            recalculateBilledFee();
                        }
                    }
                }, {
                    id: 'gst_exception_num_label',
                    xtype: 'label',
                    disabled: info.gst_type == 'auto',
                    style: 'padding: 5px 5px 5px 0;',
                    text: ' %'
                }]
            },{
                layout: 'form',
                width: 250,
                bodyStyle: 'padding-left:5px;',
                items: [{
                    id: 'gst_exception_type',
                    xtype: 'combo',
                    store: new Ext.data.Store({
                        reader: new Ext.data.JsonReader({
                            id: 'exception_type'
                        }, [
                            {name: 'exception_type'},
                            {name: 'exception_name'}
                        ]),

                        data: [
                            {
                                exception_type: 'excluded',
                                exception_name: 'Add to fees above'
                            }, {
                                exception_type: 'included',
                                exception_name: 'Included in fees above'
                            }
                        ]
                    }),
                    displayField: 'exception_name',
                    valueField:   'exception_type',
                    mode: 'local',
                    hideLabel: true,
                    disabled: info.gst_type == 'auto',
                    value: info.gst_type == 'auto' ? 'excluded' : info.gst_type,
                    width: 250,
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus:true,
                    editable: false,
                    typeAhead: false,
                    listeners: {
                        beforeselect: function(combo, record, index) {
                            combo.fireEvent('select', combo, record, index);
                        },
                        select: function() {
                            // Update total
                            recalculateBilledFee();
                        }
                    }
                }]
            }]
        });
    } else {
        gst_with_radios = gst;
    }
    

    
    var billed_fee = new Ext.form.DisplayField({
        id: 'billed_fee',
        fieldLabel: 'Next Total Recurring Charge',
        style: 'padding-top:2px',
        hidden: !canManageCompanyPackages,
        value: info.billed_fee,
        formatValue: function(val) {
            return formatMoney(site_currency, val, true);
        }
    });

    var paymentech_profile_id;
    if(canManageCompanyPackagesExtraDetails) {
        paymentech_profile_id = new Ext.form.TextField({
            id: 'pt_profile_id',
            fieldLabel: 'Paymentech Profile ID',
            width: 240,
            style: 'padding-top:2px',
            value: info.paymentech_profile_id
        });
    } else {
        paymentech_profile_id = new Ext.form.DisplayField({
            id: 'pt_profile_id',
            fieldLabel: 'Paymentech Profile ID',
            style: 'padding-top:2px',
            hidden: !canManageCompanyPackages,
            value: info.paymentech_profile_id
        });
    }

    var internal_note = new Ext.form.TextArea({
        id: 'internal_note',
        fieldLabel: 'Internal Note',
        width: 300,
        height: 100,
        value: info.internal_note,
        hidden: !canManageCompanyPackages,
        style: 'margin-bottom:10px'
    });
    
    var active_users = new Ext.form.DisplayField({
        id: 'active_users',
        fieldLabel: '# of Active Users',
        style: 'padding-top:2px',
        value: info.active_users
    });
    
    var storage_used = new Ext.form.DisplayField({
        id: 'storage_used',
        fieldLabel: 'Storage Used (GB)',
        style: 'padding-top:2px',
        value: info.storage_used
    });
    
    var additional_user_charges = new Ext.form.DisplayField({
        id: 'additional_user_charges',
        fieldLabel: 'Current Additional User Charges',
        style: 'padding-top:2px',
        hidden: !canManageCompanyPackages,
        value: info.additional_user_charges,
        formatValue: function(val) {
            return formatMoney(site_currency, val, true) + '&nbsp;&nbsp;<i>(this may be different on next billing date)</i>';
        }
    });
    
    var extra_storage_charges = new Ext.form.DisplayField({
        id: 'extra_storage_charges',
        fieldLabel: 'Current Extra Storage Charges',
        style: 'padding-top:2px',
        hidden: !canManageCompanyPackages,
        value: info.extra_storage_charges,
        formatValue: function(val) {
            return formatMoney(site_currency, val, true) + '&nbsp;&nbsp;<i>(this may be different on next billing date)</i>';
        }
    });
    
    
    var recalculateBilledFee = function(booSkipTotalBillingAmountUpdate, booNotHighlight) {
        var oldTotalBillingAmount = parseFloat(total_billing_amount.getValue()),
            newTotalBillingAmount = 0;

        var booIsRadioAutoChecked = Ext.getCmp('gst-auto') ? Ext.getCmp('gst-auto').getValue() : true,
            gstUsed = 0,
            gstType = '';
        if (booIsRadioAutoChecked) {
            gstUsed = info.gst_default;
            gstType = info.gst_default_type;
        } else {
            gstType = Ext.getCmp('gst_exception_type').getValue();
            gstUsed = parseFloat(Ext.getCmp('gst_exception_num').getValue());
        }

        if(!booSkipTotalBillingAmountUpdate) {
            // Calculate and update Total Billing Amount
            // also highlight it if value was changed
            newTotalBillingAmount = parseFloat(additional_user_charges.getValue()) +
                                    parseFloat(extra_storage_charges.getValue()) +
                                    parseFloat(subscription_fee.getValue());
        } else {
            newTotalBillingAmount = oldTotalBillingAmount;
        }


        var newGSTVal = 0;
        if (gstType == 'included') {
            // Recalculate subtotal
            // x + x * gst / 100 = amount
            // so x = amount - amount / (1 + gst/100)
            newGSTVal = newTotalBillingAmount - newTotalBillingAmount / (1 + gstUsed / 100);
            newTotalBillingAmount = newTotalBillingAmount - newGSTVal;
        } else {
            newGSTVal = (gstUsed * newTotalBillingAmount) / 100;
        }

        if(!booSkipTotalBillingAmountUpdate) {
            total_billing_amount.setValue(newTotalBillingAmount);
            
            if(oldTotalBillingAmount != newTotalBillingAmount && !booNotHighlight) {
                highlightEl(total_billing_amount);
            }
        }

        // Update GST
        // also highlight it if value was changed
        var oldGSTVal = parseFloat(gst.getValue());
        gst.setValue(newGSTVal);

        if(oldGSTVal != newGSTVal && !booNotHighlight) {
            highlightEl(gst);
        }

        
        
        // Update Billed Fee
        // also highlight it if value was changed
        var newBilledFee = newTotalBillingAmount + newGSTVal;
        var oldBilledFee = parseFloat(billed_fee.getValue());
        billed_fee.setValue(newBilledFee);
        
        if(oldBilledFee != newBilledFee && !booNotHighlight) {
            highlightEl(billed_fee);
        }
    };
    
    var highlightEl = function(field) {
        // Prevent highlight several times
        if(!field.getEl().hasActiveFx()) {
            field.getEl().highlight("FF8432", { attr: 'color', duration: 5 });
        }
    };
    
    // stats fields
    if (Ext.get('stats')) {
        var number_of_clients = new Ext.form.DisplayField({
            fieldLabel: 'Number of Cases',
            style:      'padding-top:2px',
            value:      info.number_of_clients
        });

        var last_ta_upload = new Ext.form.DisplayField({
            fieldLabel: 'Last ' + ta_label + ' uploaded',
            style:      'padding-top:2px',
            value:      info.last_ta_upload
        });

        var last_accounting_subtab_updated = new Ext.form.DisplayField({
            fieldLabel: 'Last Accounting subtab of a case was updated',
            style:      'padding-top:2px',
            value:      info.last_accounting_subtab_updated
        });

        var last_notes_written = new Ext.form.DisplayField({
            fieldLabel: 'Last Notes written',
            style:      'padding-top:2px',
            value:      info.last_notes_written
        });

        var last_task_written = new Ext.form.DisplayField({
            fieldLabel: 'Last Task written',
            style:      'padding-top:2px',
            value:      info.last_task_written
        });

        var last_calendar_entry_written = new Ext.form.DisplayField({
            fieldLabel: 'Last Calendar entry was written',
            style:      'padding-top:2px',
            value:      info.last_calendar_entry_written
        });

        var last_check_email_pressed = new Ext.form.DisplayField({
            fieldLabel: 'Last Check Email was pressed',
            style:      'padding-top:2px',
            value:      info.last_check_email_pressed
        });

        var last_advanced_search = new Ext.form.DisplayField({
            fieldLabel: 'Last Advanced Search',
            style:      'padding-top:2px',
            value:      info.last_advanced_search
        });

        var last_mass_mail = new Ext.form.DisplayField({
            fieldLabel: 'Last Mass Email',
            style:      'padding-top:2px',
            value:      info.last_mass_mail
        });

        var last_doc_uploaded = new Ext.form.DisplayField({
            fieldLabel: 'Last Document was uploaded',
            style:      'padding-top:2px',
            value:      info.last_doc_uploaded
        });
    }

    new Ext.FormPanel({
        id: 'packages-pan',
        renderTo: 'packages-info',
        bodyStyle: 'padding:5px',
        labelWidth: 200,
        items: [
            subscription_details,
            trial,
            account_created,
            company_setup,
            next_billing_date,
            billing_frequency,
            free_users,
            free_clients,
            free_storage,
            active_users,
            storage_used,
            additional_user_charges,
            extra_storage_charges,
            canManageCompanyPackages ? subscription_fee_with_comment : subscription_fee,
            total_billing_amount,
            gst_with_radios,
            billed_fee,
            paymentech_profile_id,
            internal_note
        ],

        listeners: {
            afterlayout: function () {
                recalculateBilledFee(false, true);
            }
        }
    });

    if (Ext.get('stats'))
    {
        new Ext.FormPanel({
            id: 'packgagdfgfdgges-pan',
            renderTo: 'stats',
            bodyStyle: 'padding:5px',
            labelWidth: 200,
            items: [
                number_of_clients,
                last_ta_upload,
                last_accounting_subtab_updated,
                last_notes_written,
                last_task_written,
                //last_calendar_entry_written,
                last_check_email_pressed,
                last_advanced_search,
                last_mass_mail,
                last_doc_uploaded
            ]
        });
    }
    
    return true;
}

function updateCC(company_id) {
    // Show window, with empty fields
    var cc_expire_month = new Ext.form.ComboBox({
        id: 'cc_month',
        width: 170,
        fieldLabel: 'Month',
        store: new Ext.data.SimpleStore({
            fields: ['month_id', 'month_name'],
            data: [
                ['01', '01 - January'],
                ['02', '02 - February'],
                ['03', '03 - March'],
                ['04', '04 - April'],
                ['05', '05 - May'],
                ['06', '06 - June'],
                ['07', '07 - July'],
                ['08', '08 - August'],
                ['09', '09 - September'],
                ['10', '10 - October'],
                ['11', '11 - November'],
                ['12', '12 - December']
            ]
        }),
        mode: 'local',
        valueField: 'month_id',
        displayField: 'month_name',
        triggerAction: 'all',
        forceSelection: true,
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        allowBlank: false
    });
    
    var startYear = new Date().getFullYear();
    var cc_exp_year_data = [];
    for(var i=startYear; i<=startYear + 10; i++) {
        year_id = i + '';
        cc_exp_year_data.push({year_id: year_id.substr(2), year_name: i});
    }
    
    var cc_expire_year = new Ext.form.ComboBox({
        id: 'cc_year',
        fieldLabel: 'Year',
        width: 98,
        store: new Ext.data.Store({
            data: cc_exp_year_data,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'year_id'}, {name: 'year_name'}]))
        }),
        mode: 'local',
        valueField: 'year_id',
        displayField: 'year_name',
        triggerAction: 'all',
        forceSelection: true,
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        allowBlank: false
    });
    
    
    var fp = new Ext.FormPanel({
        
        labelWidth: 150,
        bodyStyle: 'padding: 5px;',

        items: [{
            id: 'customer_name',
            xtype: 'textfield',
            fieldLabel: 'Name on Credit Card',
            width: 319,
            allowBlank: false
        }, {
            id: 'cc_num',
            xtype: 'textfield',
            fieldLabel: 'Credit Card Number',
            maskRe: /\d/,
            vtype: 'cc_number',
            width: 319,
            allowBlank: false
        }, {
            layout: 'column',
            items: [
                {
                    layout: 'form',
                    items: cc_expire_month
                }, {
                    layout: 'form',
                    labelWidth: 40,
                    style: 'padding-left:15px;',
                    items: cc_expire_year
                }
            ]
        }, {
            layout: 'column',
            hidden: site_version != 'australia',
            items: [
                {
                    layout: 'form',
                    items: {
                        id: 'cc_cvn',
                        xtype: 'textfield',
                        maskRe: /\d/,
                        fieldLabel: 'CVN',
                        vtype: 'cc_cvn',
                        width: 45,
                        disabled: site_version != 'australia',
                        allowBlank: false
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag:    'img',
                        src:    topBaseUrl + '/images/icons/help.png',
                        width:  16,
                        height: 16,
                        style:  'padding: 3px'
                    },
                    listeners: {
                        scope: this,
                        render: function(c){
                            new Ext.ToolTip({
                                target:    c.getEl(),
                                anchor:    'right',
                                title:     'About CVN',
                                autoLoad: {
                                    url: topBaseUrl + '/help/public/get-cvn-info'
                                },
                                width:     400,
                                autoHide:  false,
                                closable:  true,
                                draggable: true
                            });
                        }
                    }
                }
            ]
        }]
    });
    
    var sendUpdateCCInfo = function(booForceCreate) {
        Ext.Ajax.request({
            url: baseUrl + "/manage-company/update-packages-cc",
            params: {
                company_id:     Ext.encode(company_id),
                customer_name:  Ext.encode(Ext.getCmp('customer_name').getValue()),
                cc_num:         Ext.encode(Ext.getCmp('cc_num').getValue()),
                cc_cvn:         Ext.encode(Ext.getCmp('cc_cvn').getValue()),
                cc_exp:         Ext.encode(Ext.getCmp('cc_month').getValue() + '/' + Ext.getCmp('cc_year').getValue()),
                booForceCreate: booForceCreate
            },
            success: function(f) {
                var result = Ext.decode(f.responseText);

                // Update profile id
                if (!empty(result.pt_profile_id)) {
                    Ext.getCmp('pt_profile_id').setValue(result.pt_profile_id);
                }

                if (!result.success) {
                    // Show error message
                    Ext.simpleConfirmation.error(result.message);
                    window.getEl().unmask();
                } else {
                    if (!empty(result.message)) {
                        // Show confirmation message
                        Ext.simpleConfirmation.info(result.message);
                        window.close();
                    } else {
                        window.getEl().mask('Changes were successfully saved.');
                        setTimeout(function () {
                            window.close();
                        }, 750);
                    }
                }
            },
            failure: function() {
                Ext.Msg.alert('Status', 'Can\'t save changes');
                window.getEl().unmask();
            }
        });
    };

    var window = new Ext.Window({
        title: '<i class="lar la-money-bill-alt"></i>' + _('Update Credit Card on File'),
        autoWidth: true,
        autoHeight: true,
        modal: true,
        items: fp,

        buttons: [{
            text: 'Cancel',
            handler: function(){
                window.close();
            }
        },
            {
            text: 'Update',
            cls: 'orange-btn',
            handler: function() {
                if(fp.getForm().isValid()){
                    window.getEl().mask('Saving...');
                    
                    Ext.Ajax.request({
                        url: baseUrl + "/manage-company/check-cc-info",
                        params: {
                            company_id:    Ext.encode(company_id),
                            customer_name: Ext.encode(Ext.getCmp('customer_name').getValue()),
                            cc_num:        Ext.encode(Ext.getCmp('cc_num').getValue()),
                            cc_cvn:        Ext.encode(Ext.getCmp('cc_cvn').getValue()),
                            cc_exp:        Ext.encode(Ext.getCmp('cc_month').getValue() + '/' + Ext.getCmp('cc_year').getValue())
                        },
                        success: function(f) {
                            var result = Ext.decode(f.responseText);
                            if(!result.success) {
                                // Show error message
                                Ext.simpleConfirmation.error(result.message);
                                window.getEl().unmask();
                            } else {
                                if(result.booAsk) {
                                    // Ask and on confirmation - send request to update CC info
                                    Ext.MessageBox.buttonText.yes = "Yes, create.";
                                    Ext.Msg.confirm('Please confirm', 'Profile is not yet created. Do you want to create the Profile?',
                                        function(btn){
                                            if(btn === 'yes'){
                                                sendUpdateCCInfo(true);
                                            } else {
                                                window.getEl().unmask();
                                            }
                                        }
                                    );
                                } else {
                                    // Send request to update CC info
                                    sendUpdateCCInfo(false);
                                }
                            }
                        },
                        failure: function() {
                            Ext.Msg.alert('Status', 'Can\'t save changes');
                            window.getEl().unmask();
                        }
                    });
                }
            }
        }]
    });

    window.show();
}

function createInvoice(company_id, booRecurring) {
    // Ask user before run charge
    checkForChangesAndAskUser(runCreateInvoice, company_id, booRecurring);
}

function sendCompanyMail(companyId) {
    var wnd = new companyEmailDialog(companyId);
    wnd.show();
}

function runCreateInvoice(company_id, booRecurring) {
    
    Ext.getBody().mask('Creating...');
    Ext.Ajax.request({
        url: baseUrl + "/manage-templates/create-invoice",
        params: {
            company_id: Ext.encode(company_id),
            booRecurring: Ext.encode(booRecurring)
        },
        success: function(f) {
            var result = Ext.decode(f.responseText);
            if(!result.success) {
                Ext.getBody().unmask();
                // Show error message
                Ext.simpleConfirmation.error(result.message);
            } else {
                
                Ext.getBody().unmask();
                Ext.Msg.show({
                    title: 'Invoice Confirmation',
                    msg: 'Invoice created.',
                    minWidth: 400,
                    modal: true,
                    buttons: Ext.Msg.OK,
                    fn: function(btn){
                        Ext.getBody().mask('Page reloading...');
                        
                        // Reload invoices list
                        var currentTime = new Date();
                        var url = baseUrl + '/manage-company/edit?company_id=' + company_id + '&time='+currentTime.getTime()+'#company-packages';
                        window.location.assign(url);
                    },

                    icon: Ext.Msg.INFO
                });
                
            }
        },
        failure: function() {
            Ext.Msg.alert('Status', 'Request fail (can\'t connect to the server). Please try again.');
            Ext.getBody().unmask();
        }
    });
}
