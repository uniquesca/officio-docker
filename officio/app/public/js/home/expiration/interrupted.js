function showChargeInterruptedWindow() {
    Ext.Ajax.request({
        url: baseUrl + '/default/trial/charge-interrupted',
        success: function (res, request) {
            var dialog = new Ext.Window({
                id: 'suspendChargingWindow',
                title: 'Subscription notice',
                iconCls: 'trial_window_icon',
                layout: 'fit',
                modal: true,
                closable: false,
                resizable: false,
                width: 630,
                height: 480,
                y: 10,

                items: {
                    html: res.responseText
                }
            });

            dialog.on('show', function () {
                initCreditCardSection();

                $('input[name=cc_info_changed]').change(function () {
                    toggleCCInfoBlock($(this).val() != 'changed');
                });
            });

            dialog.show();
        },

        failure: function (form, action) {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error("Can't load information.");
        }
    });
}

var submitInterruptionForm = function () {
    var selOption = $('input[name=cc_info_changed]:checked').val();

    if (selOption == 'changed') {
        submitSubscriptionData('charge_interrupted');
    } else {
        var win = Ext.getCmp('suspendChargingWindow');
        win.getEl().mask('Sending Information...');

        Ext.Ajax.request({
            url: baseUrl + '/default/trial/suspend',
            params: {},

            success: function (result, request) {
                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    Ext.simpleConfirmation.success(resultDecoded.message);
                    win.close();
                } else {
                    Ext.simpleConfirmation.error(resultDecoded.message);
                    win.getEl().unmask();
                }
            },

            failure: function (form, action) {
                Ext.simpleConfirmation.error("Can't save information.");
                win.getEl().unmask();
            }
        });
    }
};

function interruptionLogout() {
    window.location = baseUrl + '/auth/logout'
}