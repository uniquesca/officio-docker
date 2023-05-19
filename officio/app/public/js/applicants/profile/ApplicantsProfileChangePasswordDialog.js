ApplicantsProfileChangePasswordDialog = function(config, owner) {
    var thisDialog = this;
    this.owner = owner;
    Ext.apply(this, config);

    this.usernameHiddenFieldId = new Ext.form.Hidden({
        value: ''
    });

    this.usernameField = new Ext.form.TextField({
        width: 150,
        maxlength: 32,
        fieldLabel: 'Username',
        value: ''
    });

    this.passwordField = new Ext.ux.PasswordMeter({
        width: 150,
        maxlength: passwordMaxLength,
        fieldLabel: 'New password'
    });

    this.templatesCheckbox = new Ext.form.Checkbox({
        checked:   true,
        boxLabel:  'Email&nbsp;Case',
        hideLabel: true,
        handler:   function (obj, checked) {
            thisDialog.templatesCombo.setDisabled(!checked);
        }
    });

    this.templatesCombo = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data:   [],
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'templateId'},
                {name: 'templateName'}
            ]))
        }),
        width:          280,
        mode:           'local',
        valueField:     'templateId',
        displayField:   'templateName',
        triggerAction:  'all',
        lazyRender:     true,
        forceSelection: true,
        hideLabel:      true,
        readOnly:       true,
        selectOnFocus:  true,
        editable:       false
    });


    ApplicantsProfileChangePasswordDialog.superclass.constructor.call(this, {
        title: 'Change Password',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        buttonAlign: 'right',
        items: new Ext.FormPanel({
            style: 'background-color:#fff; padding:5px;',
            items: [
                this.usernameField, {
                    cls:          'change_login_table',
                    layout:       'table',
                    layoutConfig: { columns: 2 },
                    items: [
                        {
                            layout: 'form',
                            items:  thisDialog.passwordField
                        }, {
                            layout: 'form',
                            style:  'padding:0 0 5px 5px;',
                            items:  {
                                xtype: 'button',
                                text: 'Generate new password',
                                handler: function () {
                                    var newPassword = '';
                                    do {
                                        newPassword = generatePassword();
                                    } while (!thisDialog.isCorrectPassword(newPassword));

                                    thisDialog.passwordField.setValue(newPassword);
                                    thisDialog.passwordField.updateMeter(newPassword);
                                }
                            }
                        }
                    ]
                }, {
                    layout: 'table',
                    layoutConfig: { columns: 2 },
                    items: [
                        {
                            layout: 'form',
                            items: this.templatesCheckbox
                        },
                        {
                            layout: 'form',
                            style:  'padding-left:5px;',
                            items: this.templatesCombo
                        }
                    ]
                }
            ]
        }),

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    thisDialog.close();
                }
            }, {
                text: 'Save',
                cls: 'orange-btn',
                handler: thisDialog.saveUpdatedCredentials.createDelegate(this)
            }
        ]
    });

    thisDialog.on('show', thisDialog.loadCredentials.createDelegate(this));
};

Ext.extend(ApplicantsProfileChangePasswordDialog, Ext.Window, {
    showErrorMsg: function (msg) {
        var msg_pref = 'Please review the fields highlighted in red with an error.';
        Ext.simpleConfirmation.msg('Info', msg_pref + '<div style="color: #FF3C3C; padding-top: 10px;">' + msg + '</div>', 4000);
    },

    loadCredentials: function() {
        var thisDialog = this;

        thisDialog.getEl().mask('Loading...');
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/get-login-info',
            params: {
                member_id: thisDialog.memberId
            },

            success: function (f) {
                var result = Ext.decode(f.responseText);
                thisDialog.usernameField.setValue(result.username);
                thisDialog.usernameHiddenFieldId.setValue(result.username_field_id);

                if (result.templates.length) {
                    thisDialog.templatesCombo.getStore().loadData(result.templates);
                    thisDialog.templatesCombo.setValue(result.templates[0].templateId);
                } else {
                    thisDialog.templatesCombo.setVisible(false);
                    thisDialog.templatesCombo.ownerCt.ownerCt.setVisible(false);
                    thisDialog.syncShadow();
                }

                thisDialog.getEl().unmask();
            },

            failure: function () {
                thisDialog.close();
                Ext.simpleConfirmation.error('Password cannot be changed. Please try again later.');
            }
        });
    },

    isCorrectUsername: function(strUsername) {
        var usernameRegex = /^[a-zA-ZÀ-ÿ0-9_.@-]{3,64}/;
        return usernameRegex.test(strUsername);
    },

    isCorrectPassword: function(strPassword) {
        var passRegex = new RegExp(passwordValidationRegexp); // @see var passwordValidationRegexp; e.g. in layouts/main/main.phtml
        return passRegex.test(strPassword);
    },

    saveUpdatedCredentials: function() {
        var thisDialog = this;

        //get values
        var pass = thisDialog.passwordField.getValue();
        var uname = thisDialog.usernameField.getValue();
        var send_email = thisDialog.templatesCombo.isVisible() ? thisDialog.templatesCheckbox.getValue() : false;
        var template_id = thisDialog.templatesCombo.isVisible() ? thisDialog.templatesCombo.getValue() : 0;

        var strUsernameError = '',
            strPasswordError = '',
            strTemplateError = '',
            booError = false;

        if (!empty(uname) || !empty(pass)) {
            // Check username
            if (!thisDialog.isCorrectUsername(uname)) {
                strUsernameError = 'For username please use 3 to 64 characters. You may use letters, numbers, underscores, @ sign and dot (.).';
            }

            // Check password
            if (!thisDialog.isCorrectPassword(pass)) {
                strPasswordError = passwordValidationRegexpMessage;
            }
        }

        if (send_email && getComboBoxIndex(thisDialog.templatesCombo) == -1) {
            strTemplateError = 'Please select template';
        }

        if (!empty(strUsernameError)) {
            thisDialog.usernameField.markInvalid(strUsernameError);
            booError = true;
        }

        if (!empty(strPasswordError)) {
            thisDialog.passwordField.markInvalid(strPasswordError);
            booError = true;
        }

        if (!empty(strTemplateError)) {
            thisDialog.templatesCombo.markInvalid(strTemplateError);
            booError = true;
        }

        if (booError) {
            return false;
        }

        //send
        thisDialog.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: baseUrl + '/applicants/profile/update-login-info',
            params: {
                member_id: thisDialog.memberId,
                username_field_id: Ext.encode(thisDialog.usernameHiddenFieldId.getValue()),
                username: Ext.encode(uname),
                password: Ext.encode(pass)
            },
            success: function (f) {
                var result = Ext.decode(f.responseText);

                thisDialog.getEl().unmask();
                if (result.success) {
                    //congrats
                    Ext.simpleConfirmation.msg('Info', result.message, 3000);

                    // Update username/password fields
                    if (!empty(thisDialog.usernameFieldId)) {
                        var usernameField = Ext.getCmp(thisDialog.usernameFieldId);
                        if (usernameField) {
                            usernameField.setValue(result.username);
                        }
                    }
                    if (!empty(thisDialog.passwordFieldId)) {
                        var passwordField = Ext.getCmp(thisDialog.passwordFieldId);
                        if (passwordField) {
                            passwordField.setValue(result.password);
                        }
                    }

                    if (!empty(result.applicantEncodedPassword)) {
                        var thisPanel = thisDialog.owner;
                        var thisTabPanel = thisPanel.owner.owner;
                        thisTabPanel.applicantEncodedPassword = result.applicantEncodedPassword;
                    }

                    //send email
                    if (result.message && send_email) {
                        show_email_dialog({
                            data: result.message,
                            member_id: 0,
                            encoded_password: result.applicantEncodedPassword,
                            parentMemberId: thisDialog.memberId,
                            template_id: template_id,
                            booHideSendAndSaveProspect: true,
                            booNewEmail: true,
                            booProspect: false
                        });
                    }

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
        return true;
    }
});