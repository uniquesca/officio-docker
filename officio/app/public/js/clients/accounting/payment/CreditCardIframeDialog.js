var CreditCardIframeDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var vpSizePG = Ext.getBody().getViewSize();
    CreditCardIframeDialog.superclass.constructor.call(this, {
        id:               'CreditCardIframeDialog',
        title:           '<i class="las la-credit-card"></i>' + _('Pay by Credit Card'),
        width:            vpSizePG.width * 0.98,
        height:           vpSizePG.height * 0.98,
        autoScroll:       true,
        animCollapse:     false,
        disableMessaging: false,
        modal:            true,
        defaultSrc:       baseUrl + '/default/tran-page/pre-request?member_id=' + config.member_id + '&ta_id=' + config.ta_id + '&invoice_id=' + config.invoice_id,

        frameConfig: {
            allowtransparency: 'true',

            style: {
                "background-color": "lightyellow"
            }
        },

        listeners: {
            documentloaded: function (frame) {
                var frameForm = $(frame.getFrameDocument()).find('form[name="officioSubmitFormToTranPage"]');
                if (frameForm) {
                    frameForm.submit();
                }
            }
        }
    });
};

Ext.extend(CreditCardIframeDialog, Ext.ux.ManagedIFrame.Window, {
});

function closeCreditCardIframeDialog() {
    Ext.getCmp('CreditCardIframeDialog').close();
}

function closeCreditCardIframeDialogAndRefreshGrid(member_id, ta_id) {
    Ext.getCmp('CreditCardIframeDialog').close();
    refreshAccountingTabByTA(member_id, ta_id);
}