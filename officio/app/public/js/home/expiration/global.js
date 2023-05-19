var tipId = 'expiration_user_licenses_tip';
function hideExpirationUserTip() {
    var tooltip = Ext.getCmp(tipId);
    if (tooltip) {
        tooltip.hide();
    }
}

function showExpirationUserTip(booRenew) {
    var tooltip = Ext.getCmp(tipId);
    if (!tooltip) {
        var strPrefix = booRenew ? 'renew_' : 'trial_';
        var location = Ext.getCmp(strPrefix + 'licenses').getEl().getXY();
        tooltip = new Ext.ToolTip({
            id: tipId,
            anchor: 'bottom',
            anchorOffset: 100,
            dismissDelay: 5000,
            closable: true,
            width: 270,
            html: '<div style="padding: 5px;">' +
                        'You have selected fewer licenses that is already used in your account.<br/><br/>' +
                        'You can de-activate extra licenses for the users that are no longer needed in the Admin section.' +
                    '</div>',

            anchorToTarget: false,
            targetXY: [location[0] + 15, location[1]]
        });
    }
    tooltip.show();
}

var highlightEl = function(fieldId) {
    // Prevent highlight several times
    var field = Ext.get(fieldId);
    if (field && !field.hasActiveFx()) {
        field.highlight('FF8432', { attr: 'color', duration: 2 });
    }
};

function highlightElementValue(elId, newValue, booNotHighlight) {
    var el = $('#' + elId),
        oldVal = el.html();
    el.html(newValue);

    if (oldVal != newValue && !booNotHighlight) {
        highlightEl(elId);
    }
}

function isExpired(booRenew) {
    var fieldId = booRenew ? 'renew_expired' : 'trial_expired';
    return $('#' + fieldId).val() != '0';
}


var initCreditCardSection = function() {
    new Ext.form.ComboBox({
        id: 'subscription_cc_type',
        typeAhead: true,
        triggerAction: 'all',
        transform: 'ccType',
        width: 120,
        listWidth: 120,
        allowBlank: false,
        editable: false,
        forceSelection: true
    });

    new Ext.form.TextField({
        id: 'subscription_cc_name',
        applyTo: 'ccName',
        allowBlank: false,
        width: 300
    });

    new Ext.form.TextField({
        id: 'subscription_cc_number',
        vtype: 'cc_number',
        applyTo: 'ccNumber',
        allowBlank: false,
        width: 300
    });

    if (site_version == 'australia') {
        new Ext.form.TextField({
            id: 'subscription_cc_cvn',
            maskRe: /\d/,
            vtype: 'cc_cvn',
            width: 65,
            applyTo: 'ccCVN',
            allowBlank: false
        });
    }

    new Ext.form.ComboBox({
        id: 'subscription_cc_exp_month',
        typeAhead: true,
        triggerAction: 'all',
        transform: 'ccExpMonth',
        allowBlank: false,
        editable: false,
        width: 120,
        listWidth: 120,
        forceSelection: true
    });

    new Ext.form.ComboBox({
        id: 'subscription_cc_exp_year',
        typeAhead: true,
        triggerAction: 'all',
        transform: 'ccExpYear',
        allowBlank: false,
        editable: false,
        width: 120,
        listWidth: 120,
        forceSelection: true
    });
};

var checkSubscriptionDialogInfo = function(booRenew) {
    var strPrefix = booRenew ? 'renew_' : 'trial_';

    // Check all fields
    var arrFieldsToCheck = [
        strPrefix + 'licenses',
        strPrefix + 'storage',
        'subscription_cc_type',
        'subscription_cc_name',
        'subscription_cc_number',
        'subscription_cc_exp_month',
        'subscription_cc_exp_year'
    ];

    if (site_version == 'australia') {
        arrFieldsToCheck.push('subscription_cc_cvn');
    }

    var booCorrect = true;
    for (var i = 0; i < arrFieldsToCheck.length; i++) {
        var field = Ext.getCmp(arrFieldsToCheck[i]);
        if (field && !field.isValid()) {
            booCorrect = false;
        }
    }

    var selMonth = Ext.getCmp('subscription_cc_exp_month');
    if (empty(selMonth.getValue())) {
        selMonth.markInvalid('This field is required');
        booCorrect = false;
    }

    var selYear = Ext.getCmp('subscription_cc_exp_year');
    if (empty(selYear.getValue())) {
        selYear.markInvalid('This field is required');
        booCorrect = false;
    }

    return booCorrect;
};

var toggleCCInfoBlock = function(booDisable) {
    var arrCCFields = [
        'subscription_cc_type',
        'subscription_cc_name',
        'subscription_cc_number',
        'subscription_cc_exp_month',
        'subscription_cc_exp_year'
    ];

    if (site_version == 'australia') {
        arrCCFields.push('subscription_cc_cvn');
    }

    for (var i = 0; i < arrCCFields.length; i++) {
        var field = Ext.getCmp(arrCCFields[i]);
        if (field) {
            if (booDisable) {
                field.disable();
                field.clearInvalid();
            } else {
                field.enable();
            }
        }
    }
};


function submitSubscriptionData(submissionType) {
    var additionalParams = {};
    var windowId = '';
    switch (submissionType) {
        case 'charge_interrupted':
            windowId = 'suspendChargingWindow';
            booRenew = false;
            break;

        case 'trial':
            windowId = 'trial_window';
            booRenew = false;
            additionalParams = {
                subscription_plan: Ext.encode($('#trial_selected_plan').val()),
                user_licenses: Ext.encode(Ext.getCmp('trial_licenses').getValue())
            };
            break;

        case 'renew':
        default:
            windowId = 'renew_window';
            booRenew = true;
            additionalParams = {
                subscription_plan: Ext.encode($('#renew_selected_plan').val()),
                user_licenses: Ext.encode(Ext.getCmp('renew_licenses').getValue()),
                additional_storage: Ext.encode(Ext.getCmp('renew_storage').getValue())
            };
            break;
    }

    var booCorrect = checkSubscriptionDialogInfo(booRenew);

    // If all fields are correct - submit data
    if (booCorrect) {
        var win = Ext.getCmp(windowId);
        win.getEl().mask('Sending Information...');

        var params = {
            submission_type: Ext.encode(submissionType),
            cc_type: Ext.encode(Ext.getCmp('subscription_cc_type').getValue()),
            cc_name: Ext.encode(Ext.getCmp('subscription_cc_name').getValue()),
            cc_num: Ext.encode(Ext.getCmp('subscription_cc_number').getValue()),
            cc_cvn: Ext.encode((site_version == 'australia' ? Ext.getCmp('subscription_cc_cvn').getValue() : '')),
            cc_exp_month: Ext.encode(Ext.getCmp('subscription_cc_exp_month').getValue()),
            cc_exp_year: Ext.encode(Ext.getCmp('subscription_cc_exp_year').getValue())
        };

        Ext.Ajax.request({
            url: baseUrl + '/default/trial/save',
            params: Ext.apply(params, additionalParams),

            success: function(result, request) {
                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    Ext.simpleConfirmation.success(resultDecoded.message);
                    win.close();
                } else {
                    Ext.simpleConfirmation.error(resultDecoded.message);
                    win.getEl().unmask();
                }
            },

            failure: function(form, action) {
                Ext.simpleConfirmation.error("Can't save information.");
                win.getEl().unmask();
            }
        });
    }
}
