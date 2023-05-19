ChangeVevoCredentialsDialog = function(config) {
    var thisDialog = this;
    Ext.apply(this, config);

    this.usernameField = new Ext.form.TextField({
        fieldLabel: 'Username',
        allowBlank: false,
        width: 320,
        value: thisDialog.username
    });

    this.passwordField = new Ext.form.TextField({
        fieldLabel: 'New Password',
        width: 320,
        inputType: 'password'
    });

    ChangeVevoCredentialsDialog.superclass.constructor.call(this, {
        title: 'Change ImmiAccount Credentials',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        items: new Ext.FormPanel({
            style: 'background-color:#fff; padding:5px;',
            items: [
                {
                    layout:       'table',
                    layoutConfig: { columns: 2 },
                    items: [
                        {
                            layout: 'form',
                            items:  [
                                thisDialog.usernameField,
                                thisDialog.passwordField,
                                {
                                    xtype: 'label',
                                    style: 'display: block; padding-top: 5px; height: 20px; font-style: italic;',
                                    html: 'Note: If new password will be not entered - already saved password will be used.'
                                }
                            ]
                        }
                    ]
                }
            ]
        }),

        buttons: [
            {
                text: 'Check',
                handler: thisDialog.check.createDelegate(this)
            }, {
                text: 'Save',
                handler: thisDialog.saveUpdatedCredentials.createDelegate(this)
            }, {
                text: 'Cancel',
                handler: function () {
                    thisDialog.close();
                }
            }
        ]
    });
};

Ext.extend(ChangeVevoCredentialsDialog, Ext.Window, {
    showErrorMsg: function (msg) {
        var msg_pref = 'Please review the fields highlighted in red with an error.';
        Ext.simpleConfirmation.msg('Info', msg_pref + '<div style="color: #FF3C3C; padding-top: 10px;">' + msg + '</div>', 4000);
    },

    check: function() {
        var thisDialog = this;

        var vevoLogin = thisDialog.usernameField.getValue();

        if (empty(vevoLogin)) {
            return;
        }

        var vevoPass = thisDialog.passwordField.getValue();

        thisDialog.getEl().mask('Checking ImmiAccount...');

        Ext.Ajax.request({
            url: baseUrl + '/manage-members/check-vevo-account',
            params: {
                member_id: thisDialog.memberId,
                login: Ext.encode(vevoLogin),
                password: Ext.encode(vevoPass)
            },
            success: function (result) {
                thisDialog.getEl().unmask();

                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    Ext.simpleConfirmation.success(resultData.message);
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },
            failure: function () {
                thisDialog.getEl().unmask();
                Ext.simpleConfirmation.error('Cannot check ImmiAccount. Please try later');
            }
        });
    },

    saveUpdatedCredentials: function() {
        var thisDialog = this;

        //get values
        var vevoLogin = thisDialog.usernameField.getValue();

        if (empty(vevoLogin)) {
            return;
        }

        var vevoPass = thisDialog.passwordField.getValue();

        //send
        thisDialog.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: baseUrl + '/manage-members/change-vevo-credentials',
            params: {
                member_id: thisDialog.memberId,
                login: Ext.encode(vevoLogin),
                password: Ext.encode(vevoPass)
            },
            success: function (f) {
                thisDialog.getEl().unmask();
                var result = Ext.decode(f.responseText);
                if (result.success) {
                    var vevoLoginField = $("#vevo_login");
                    if (empty(vevoLogin)) {
                        var vevoPasswordField = $("#vevo_password");
                        vevoLoginField.val('');
                        vevoLoginField.show();
                        vevoPasswordField.val('');
                        vevoPasswordField.show();
                        $(".vevo-change-field").hide();
                    } else {
                        vevoLoginField.val(vevoLogin);
                        $("#vevo_login_text").text(vevoLogin);
                    }
                    Ext.simpleConfirmation.success('Credentials were saved successfully');
                } else {
                    Ext.simpleConfirmation.error(result.message);
                }

                thisDialog.close();
            },
            failure: function () {
                thisDialog.getEl().unmask();
                Ext.simpleConfirmation.error('Cannot save ImmiAccount credentials. Please try later.');
            }
        });
    }
});