var ApplicantsProfileSubmitDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    ApplicantsProfileSubmitDialog.superclass.constructor.call(this, {
        title:        _('Submit to ') + current_member_company_name,
        layout:      'form',
        resizable:   false,
        iconCls:     'icon-applicant-submit-to-government',
        bodyStyle:   'padding: 5px; background-color: white;',
        buttonAlign: 'right',
        autoHeight:  true,
        autoWidth:   true,
        modal:       true,

        items: [
            {
                xtype:     'displayfield',
                hideLabel: true,
                value:     String.format(
                    _('You are about to submit the Citizenship application for: <div style="font-weight: bold; padding-bottom: 15px;">{0}</div>'),
                    this.applicantName
                )
            }, {
                xtype:     'displayfield',
                hideLabel: true,
                width:     380,
                value:     _('Please ensure that all the required documents have been uploaded and all the forms are duly complete.')
            }
        ],

        buttons: [
            {
                text:    _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text:    _('Submit Now'),
                cls:     'orange-btn',
                handler: this.submitClientToGovernment.createDelegate(thisWindow)
            }
        ]
    });
};

Ext.extend(ApplicantsProfileSubmitDialog, Ext.Window, {
    submitClientToGovernment: function () {
        var win = this;
        win.getEl().mask(_('Submitting...'));

        Ext.Ajax.request({
            url:    topBaseUrl + '/applicants/index/submit-to-government',
            params: {
                clientId: Ext.encode(this.clientId)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    var msg = resultData.message && !empty(resultData.message) ? resultData.message : _('Done!');
                    Ext.simpleConfirmation.success(msg);

                    if (resultData.booUpdateInfo) {
                        win.owner.owner.updateClientInfoEverywhere(resultData.arrUpdatedInfo);
                    }

                    win.owner.owner.makeReadOnlyClient();

                    win.getEl().unmask();
                    win.close();
                } else {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Error happened. Please try again later.'));
                win.getEl().unmask();
            }
        });
    }
});