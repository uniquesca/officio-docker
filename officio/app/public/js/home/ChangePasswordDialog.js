var ChangePasswordDialog = function(config) {
    var thisDialog = this;
    Ext.apply(this, config);

    this.oldPasswordField = new Ext.form.TextField({
        minLength:  passwordMinLength,
        maxLength:  passwordMaxLength,
        allowBlank: false,
        width:      230,
        fieldLabel: _('Current password')
    });

    this.newPasswordField = new Ext.ux.PasswordMeter({
        minLength:  passwordMinLength,
        maxLength:  passwordMaxLength,
        allowBlank: false,
        width:      230,
        fieldLabel: _('New password')
    });

    var warningMessage = '';
    if (config.booFirstTime) {
        warningMessage = _('This is the first time you are logging in to Officio.<br/>Please enter a new password.');
    } else {
        warningMessage = String.format(
            _("Your password was last changed over {0} days ago.<br/>It's time to enter a new password."),
            config.passwordValidDays
        );
    }

    this.changePasswordForm = new Ext.FormPanel({
        style: 'background-color: #fff; padding: 5px;',
        labelWidth: 130,
        items: [
            {
                cls:    'change_login_table',
                layout: 'table',
                layoutConfig: { columns: 2 },
                items: [
                    {
                        colspan: 2,
                        style: 'font-size: 12px; padding-bottom: 20px;',
                        html: warningMessage
                    },

                    {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.oldPasswordField
                    },

                    {
                        layout: 'form',
                        items:  thisDialog.newPasswordField
                    }, {
                        layout: 'form',
                        style:  'padding:0 0 5px 5px;',
                        items:  {
                            xtype: 'button',
                            text: _('Generate new password'),
                            handler: function () {
                                var newPassword = '';
                                do {
                                    newPassword = generatePassword();
                                } while (!thisDialog.isCorrectPassword(newPassword));

                                thisDialog.newPasswordField.setValue(newPassword);
                                thisDialog.newPasswordField.updateMeter(newPassword);
                            }
                        }
                    }
                ]
            }
        ]
    });

    ChangePasswordDialog.superclass.constructor.call(this, {
        title:       _('Please change your password'),
        modal:       true,
        autoHeight:  true,
        autoWidth:   true,
        resizable:   false,
        items:       this.changePasswordForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: thisDialog.saveUpdatedCredentials.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ChangePasswordDialog, Ext.Window, {
    isCorrectPassword: function(strPassword) {
        var passRegex = new RegExp(passwordValidationRegexp); // @see var passwordValidationRegexp; e.g. in layouts/main/main.phtml
        return passRegex.test(strPassword);
    },

    saveUpdatedCredentials: function() {
        var thisDialog = this,
            booReallyValid = this.changePasswordForm.getForm().isValid();

        // Check new password
        if (booReallyValid && !thisDialog.isCorrectPassword(thisDialog.newPasswordField.getValue())) {
            thisDialog.newPasswordField.markInvalid(passwordValidationRegexpMessage);
            booReallyValid = false;
        }

        if (booReallyValid) {
            thisDialog.getEl().mask('Saving...');
            Ext.Ajax.request({
                url: topBaseUrl + '/applicants/profile/change-my-password',
                params: {
                    action:      Ext.encode('changePassword'),
                    oldPassword: Ext.encode(thisDialog.oldPasswordField.getValue()),
                    newPassword: Ext.encode(thisDialog.newPasswordField.getValue())
                },
                success: function (f) {
                    var result = Ext.decode(f.responseText);

                    thisDialog.getEl().unmask();
                    if (result.success) {
                        Ext.simpleConfirmation.msg('Info', result.message, 3000);

                        // Remove this cookie, so this dialog will be not showed on page refresh
                        Cookies.set('subscription_notice', null, {samesite: 'Lax'});

                        thisDialog.close();
                    } else {
                        var showMsg = empty(result.message) ? 'Password cannot be changed. Please try again later.' : result.message;
                        Ext.simpleConfirmation.error(showMsg);
                    }
                },
                failure: function () {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.error('Password cannot be changed. Please try again later.');
                }
            });
        }
    }
});