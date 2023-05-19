function showTrialWindow() {
   Ext.Ajax.request({
        url: baseUrl + '/default/trial/index',
        success: function(res) {
            var trialWin = new Ext.Window({
                id: 'trial_window',
                iconCls: 'trial_window_icon',
                layout: 'fit',
                modal: true,
                closable: false, // We'll use our custom buttons
                resizable: false,
                width: 700,
                height: 430,
                y: 10,
                buttonAlign: 'center',

                items: {
                    html: res.responseText
                },

                tools: [{
                    id: 'trial_wnd_btn_close',
                    qtip: 'Close',
                    hidden: true,
                    handler: function() {
                        trialWin.close();
                    }
                }],

                buttons: [{
                    text: 'Cancel',
                    iconCls: 'trial_window_skip',
                    hidden: true,
                    handler: function() {
                        trialWin.close();
                    }
                }]
            });

            trialWin.on('close', function() {
                hideExpirationUserTip();
            });

            trialWin.on('beforeshow', function() {
                var booExpired = isExpired(false);
                if (!booExpired) {
                    this.buttons[0].show();
                    this.tools.trial_wnd_btn_close.show();
                }
                this.setTitle(booExpired ? 'Trial period has expired' : 'Trial period is expiring', false);
                this.setIconClass(booExpired ? 'trial_window_icon_expired' : 'trial_window_icon');
                this.setHeight(booExpired ? 400 : 430);
            });

            trialWin.on('show', function() {
                initCreditCardSection();

                new Ext.form.ComboBox({
                    id: 'trial_licenses',
                    typeAhead: true,
                    triggerAction: 'all',
                    transform: 'trial_user_licenses',
                    width: 45,
                    listWidth: 62,
                    allowBlank: false,
                    editable: false,
                    forceSelection: true,
                    listeners: {
                        'select': function() {
                            recalculateTotal();
                        }
                    }
                });
            });

            trialWin.show();
        },
        failure: function() {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error("Can't load information.");
        }
    });
}

function recalculateTotal(booNotHighlight) {
    var currency = site_currency;
    var currencySign = '';
    if (currency == 'aud' || currency == 'cad' || currency == 'usd') {
        currencySign = '$';
    }


    var plan = $('#trial_selected_plan').val();
    var totalLicensesCount = parseInt(Ext.getCmp('trial_licenses').getValue(), 10);
    var activeUsersCount = parseInt($('#trial_active_users').val(), 10);
    var freeUsersCount = parseInt($('#trial_free_users').val(), 10);
    var companyGst = parseInt($('#trial_company_gst').val(), 10);
    var companyGstType = $('#trial_company_gst_type').val();
    var companyGstTaxLabel = $('#trial_company_gst_tax_label').val();
    if (companyGstType == 'auto') {
        companyGstType = $('#trial_company_gst_default_type').val();
    }

    var subscription, pricePerLicense, price_per;
    if (plan == 'monthly') {
        subscription = parseFloat($('#trial_fee_monthly').val());
        pricePerLicense = parseFloat($('#trial_license_monthly').val());
        price_per = 'month';
    } else {
        subscription = parseFloat($('#trial_fee_annual').val());
        pricePerLicense = parseFloat($('#trial_license_annual').val());
        price_per = 'year';
    }

    // Update license price
    var licensePriceContent = String.format(
        '{0}{1}/{2}',
        currencySign, pricePerLicense, price_per
    );
    $('#trial_user_license_price').html(licensePriceContent);



    // Calculate "Additional user licenses"
    var licensesToPay = totalLicensesCount - freeUsersCount;
    licensesToPay = licensesToPay <= 0 ? 0 : licensesToPay;

    // Show tooltip if (combo < "# of Active Users")
    if (!booNotHighlight && (totalLicensesCount < activeUsersCount)) {
        showExpirationUserTip(false);
    } else {
        hideExpirationUserTip();
    }

    var newUserLicensesPrice = licensesToPay * pricePerLicense;
    highlightElementValue('trial_licenses_calculated', formatMoney(currency, newUserLicensesPrice, true), booNotHighlight);

    // Update licenses prices content
    if (licensesToPay > 0) {
        var strLicenses = licensesToPay == 1 ? 'license' : 'licenses';
        var content = String.format(
            '({0} {1} @ {2}{3}/{4})',
            licensesToPay, strLicenses, currencySign, pricePerLicense, price_per
        );

        $('#trial_additional_user_licenses').html(content).show();
    } else {
        $('#trial_additional_user_licenses').hide();
    }


    // Calculate "GST/HST"
    var subtotal = subscription + newUserLicensesPrice;

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
    highlightElementValue('trial_total_calculated', formatMoney(currency, newTotal, true), booNotHighlight);
}

function showPlan(booMonthly) {
    $('#trial_plan_selection').hide();
    $('#trial_details').show();

    var selPlan;
    if (booMonthly) {
        $('.trial_monthly_plan').show();
        $('.trial_annual_plan').hide();
        selPlan = 'monthly';
    } else {
        $('.trial_monthly_plan').hide();
        $('.trial_annual_plan').show();
        selPlan = 'annual';
    }

    $('#trial_selected_plan').val(selPlan);

    var height = isExpired(false) ? 610 : 640;
    Ext.getCmp('trial_window').setHeight(height);

    recalculateTotal(true);
}

function showPlanSelection() {
    $('#trial_plan_selection').show();
    $('#trial_details').hide();
    hideExpirationUserTip();

    var height = isExpired(false) ? 400 : 430;
    Ext.getCmp('trial_window').setHeight(height);
}
