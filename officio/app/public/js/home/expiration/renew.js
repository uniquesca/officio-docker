function showAccountWillExpireWindow() {
    var backBtn = new Ext.Button({
        text: '<i class="las la-angle-double-left"></i>' + _('Back'),
        hidden: true,
        handler: function () {
            showExpirationPlanSelection();
        }
    });

    var cancelBtn = new Ext.Button({
        text: _('Logout'),
        hidden: true,
        handler: function () {
            renewLogout();
        }
    });

    var remindLaterBtn = new Ext.Button({
        text: _('Remind me later'),
        hidden: true,
        handler: function () {
            closeWindow();
        }
    });

    var renewNowBtn = new Ext.Button({
        text: _('Renew Now'),
        cls: 'orange-btn',
        handler: function () {
            showExpirationPlan();
        }
    });

    var submitBtn = new Ext.Button({
        text: _('Submit Payment'),
        hidden: true,
        cls: 'orange-btn',
        handler: function () {
            submitSubscriptionData('renew');
        }
    });

    Ext.Ajax.request({
        url: baseUrl + '/default/trial/renew',
        success: function (res) {
            var renewWin = new Ext.Window({
                id: 'renew_window',
                layout: 'fit',
                modal: true,
                closable: false, // We'll use our custom buttons
                resizable: false,
                width: 600,
                height: 160,
                y: 10,
                buttonAlign: 'right',

                items: {
                    html: res.responseText
                },

                tools: [{
                    id: 'close',
                    hidden: true,
                    handler: function () {
                        renewWin.close();
                    }
                }],

                buttons: [
                    backBtn, submitBtn, cancelBtn, remindLaterBtn, renewNowBtn
                ],

                showFirstStepButtons: function () {
                    backBtn.hide();
                    submitBtn.hide();

                    if (isExpired(true)) {
                        cancelBtn.show();
                        remindLaterBtn.hide();
                    } else {
                        this.tools.close.show();
                        remindLaterBtn.show();
                        cancelBtn.hide();
                    }

                    renewNowBtn.show();
                },

                showSecondStepButtons: function () {
                    backBtn.show();
                    submitBtn.show();


                    cancelBtn.hide();
                    remindLaterBtn.hide();
                    renewNowBtn.hide();
                },

                recalculateRenewTotal: function (booNotHighlight) {
                    var currency = $('#renew_currency').val();
                    var currencySign = '';
                    if (currency == 'aud' || currency == 'cad' || currency == 'usd') {
                        currencySign = '$';
                    }


                    var plan = $('#renew_selected_plan').val();
                    var totalLicensesCount = parseInt(Ext.getCmp('renew_licenses').getValue(), 10);
                    var totalStorageCount = parseInt(Ext.getCmp('renew_storage').getValue(), 10);
                    var freeStorageCount = parseInt($('#renew_free_storage').val(), 10);

                    var activeUsersCount = parseInt($('#renew_active_users').val(), 10);
                    var freeUsersCount = parseInt($('#renew_free_users').val(), 10);
                    var companyGst = parseInt($('#renew_company_gst').val(), 10);
                    var companyGstType = $('#renew_company_gst_type').val();
                    var companyGstTaxLabel = $('#renew_company_gst_tax_label').val();
                    if (companyGstType == 'auto') {
                        companyGstType = $('#renew_company_gst_default_type').val();
                    }

                    var subscription, pricePerLicense, pricePerStorage, price_per;

                    if (plan == 'monthly') {
                        subscription = parseFloat($('#renew_fee_monthly').val());
                        pricePerLicense = parseFloat($('#renew_license_monthly').val());
                        pricePerStorage = parseFloat($('#renew_fee_storage_monthly').val());
                        price_per = 'month';
                    } else {
                        subscription = parseFloat($('#renew_fee_annual').val());
                        pricePerLicense = parseFloat($('#renew_license_annual').val());
                        pricePerStorage = parseFloat($('#renew_fee_storage_annual').val());
                        price_per = 'year';
                    }

                    // Update license price
                    var licensePriceContent = String.format(
                        '{0}{1}/{2}',
                        currencySign, pricePerLicense, price_per
                    );
                    highlightElementValue('renew_user_license_price', licensePriceContent, booNotHighlight);

                    // Calculate "Additional user licenses"
                    var licensesToPay = totalLicensesCount - freeUsersCount;
                    licensesToPay = licensesToPay <= 0 ? 0 : licensesToPay;

                    // Show tooltip if (combo < "# of Active Users")
                    if (!booNotHighlight && (totalLicensesCount < activeUsersCount)) {
                        showExpirationUserTip(true);
                    } else {
                        hideExpirationUserTip(true);
                    }

                    var newUserLicensesPrice = licensesToPay * pricePerLicense;
                    highlightElementValue('renew_licenses_calculated', formatMoney(currency, newUserLicensesPrice, true), booNotHighlight);

                    // Calculate "Storage" price
                    var newStoragePrice = String.format(
                        '{0}{1}/Gb',
                        currencySign, pricePerStorage, price_per
                    );
                    highlightElementValue('renew_storage_price', newStoragePrice, booNotHighlight);

                    // Calculate "Storage" total
                    var newStorageCountPrice = pricePerStorage * (totalStorageCount - freeStorageCount);
                    newStorageCountPrice = newStorageCountPrice < 0 ? 0 : newStorageCountPrice;
                    highlightElementValue('renew_storage_calculated', formatMoney(currency, newStorageCountPrice, true), booNotHighlight);

                    // Calculate "GST/HST"
                    var subtotal = subscription + newUserLicensesPrice + newStorageCountPrice;

                    var newGSTVal = 0;
                    if (companyGstType == 'included') {
                        $('.gst_row').hide();
                        $('.gst_row_included').show();

                        // Recalculate subtotal
                        // x + x * gst / 100 = amount
                        // so x = amount - amount / (1 + gst/100)
                        var newGSTValUsed = subtotal - subtotal / (1 + companyGst / 100);

                        $('#renew_gst_included_percents').html(companyGst + '% ' + companyGstTaxLabel);
                        highlightElementValue('renew_gst_included', formatMoney(currency, newGSTValUsed, true), booNotHighlight);
                    } else {
                        $('.gst_row').show();
                        $('.gst_row_included').hide();

                        newGSTVal = (companyGst * subtotal) / 100;
                        highlightElementValue('renew_gst_calculated', formatMoney(currency, newGSTVal, true), booNotHighlight);
                    }


                    // Calculate "Total"
                    var newTotal = subtotal + newGSTVal;
                    highlightElementValue('renew_total_calculated', formatMoney(currency, newTotal, true), booNotHighlight);

                    submitBtn.setText(_('Submit Payment') + ' (' + formatMoney(currency, newTotal, true) + ')');
                    // Need this to be sure that updated button will be fully visible
                    this.syncSize();
                }
            });

            renewWin.on('close', function() {
                hideExpirationUserTip();

                if (!isExpired(true)) {
                    Ext.state.Manager.set('renew_hidden_on', new Date().toString());
                }
            });

            renewWin.on('beforeshow', function() {
                var booExpired = isExpired(true);
                this.showFirstStepButtons();

                this.setTitle(_('Subscription notice'));
                this.setHeight(booExpired ? 180 : 200);
            });

            renewWin.on('show', function() {
                initCreditCardSection();

                new Ext.form.NumberField({
                    id: 'renew_licenses',
                    allowBlank: false,
                    allowNegative: false,
                    allowDecimals: false,
                    applyTo: 'renew_user_licenses',
                    width: 65,
                    maxValue: 1000,
                    enableKeyEvents: true,
                    listeners: {
                        'keyup': function () {
                            renewWin.recalculateRenewTotal();
                        }
                    }
                });

                var activeStorage = parseInt($('#renew_active_storage').val(), 10);
                var freeStorage   = parseInt($('#renew_free_storage').val(), 10);
                var minStorage    = activeStorage > freeStorage ? activeStorage : freeStorage;

                new Ext.form.NumberField({
                    id: 'renew_storage',
                    allowBlank: false,
                    allowNegative: false,
                    allowDecimals: false,
                    applyTo: 'renew_storage_count',
                    width: 45,
                    minValue: activeStorage,
                    minText: _('The minimum value is {0}Gb'),
                    maxValue: minStorage + 10,
                    maxText: _('The maximum value is {0}Gb'),
                    enableKeyEvents: true,
                    listeners: {
                        'keyup': function () {
                            renewWin.recalculateRenewTotal();
                        }
                    }
                });
            });

            renewWin.show();
        },
       failure: function () {
           Ext.getBody().unmask();
           Ext.simpleConfirmation.error(_("Can't load information."));
       }
   });
}

function closeWindow() {
    Ext.getCmp("renew_window").close();
}

function showExpirationPlan() {
    $('#renew_plan_selection').hide();
    $('#renew_details').show();

    var height = 780;

    // if Users section is hidden
    if (parseInt($('#renew_active_users').val(), 10) <= parseInt($('#renew_free_users').val(), 10)) {
        height -= 70;
    }

    // if Storage section is hidden
    if (parseInt($('#renew_active_storage').val(), 10) <= parseInt($('#renew_free_storage').val(), 10)) {
        height -= 70;
    }

    var window = Ext.getCmp('renew_window');
    window.setHeight(height);

    updateCurrentPlan();
    window.recalculateRenewTotal(true);
    window.showSecondStepButtons();
}

function showExpirationPlanSelection() {
    $('#renew_plan_selection').show();
    $('#renew_details').hide();
    hideExpirationUserTip();

    var height = isExpired(true) ? 180 : 200;

    var window = Ext.getCmp('renew_window');
    window.setHeight(height);
    window.showFirstStepButtons();
}

function updateSelectedPlan() {
    var currentPlan = $('input[name=renew_selected_plan]:checked').val();
    $('#renew_selected_plan').val(currentPlan);

    var window = Ext.getCmp('renew_window');
    updateCurrentPlan(true);
    window.recalculateRenewTotal(false);
}

function updateCurrentPlan(booHighlight) {
    var plan = $('#renew_selected_plan').val(),
        strSelPlan,
        strSelPlanCharge;

    if (plan == 'monthly') {
        strSelPlan = 'Monthly Plan';
        strSelPlanCharge = parseFloat($('#renew_fee_monthly').val());
    } else {
        strSelPlan = 'Annual Plan';
        strSelPlanCharge = parseFloat($('#renew_fee_annual').val());
    }

    $('#renew_selected_plan_name').html(strSelPlan);

    var currency = $('#renew_currency').val();
    highlightElementValue('renew_selected_plan_charge', formatMoney(currency, strSelPlanCharge, true), !booHighlight);
}

function renewLogout() {
    window.location = baseUrl + '/auth/logout'
}
