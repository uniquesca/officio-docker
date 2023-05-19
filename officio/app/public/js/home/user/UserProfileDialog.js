var UserProfileDialog = function(config) {
    var thisDialog = this;
    Ext.apply(this, config);

    // Always hide this option
    // @Note: can be enabled later if needed
    this.hideQueueField = true;

    this.firstNameField = new Ext.form.TextField({
        name: 'fName',
        maxLength:  255,
        allowBlank: false,
        disabled:   !userProfileSettings.can_change_name,
        width:      200,
        fieldLabel: _('First Name')
    });

    this.lastNameField = new Ext.form.TextField({
        name: 'lName',
        maxLength:  255,
        allowBlank: false,
        disabled:   !userProfileSettings.can_change_name,
        width:      200,
        fieldLabel: _('Last Name')
    });

    this.emailField = new Ext.form.TextField({
        name: 'emailAddress',
        maxLength:  255,
        allowBlank: false,
        disabled:   !userProfileSettings.can_change_email,
        width:      200,
        vtype:      'email',
        fieldLabel: _('Email Address')
    });

    this.showSpecialAnnouncementsCheckbox = new Ext.form.Checkbox({
        name: 'show_special_announcements',
        boxLabel: _('Show special announcement messages'),
        hidden: !allowedPages.has('lms'),
        hideLabel: true
    });

    this.userNameField = new Ext.form.TextField({
        name: 'username',
        maxLength:  255,
        disabled:   true,
        width:      200,
        fieldLabel: _('Username')
    });

    this.oldPasswordField = new Ext.form.TextField({
        name: 'oldPassword',
        minLength:  passwordMinLength,
        maxLength:  passwordMaxLength,
        allowBlank: false,
        width:      200,
        fieldLabel: _('Current password')
    });

    this.newPasswordField = new Ext.ux.PasswordMeter({
        name: 'newPassword',
        minLength:  passwordMinLength,
        maxLength:  passwordMaxLength,
        allowBlank: true,
        width:      200,
        fieldLabel: _('New password')
    });

    if (this.hideQueueField) {
        // Don't show this field for superadmin
        this.queueShowField = null;
    } else {
        var msgHelp = String.format(
            _('One-click access to {1}s <img src="{0}/images/icons/help.png" width="16" height="16" alt="Print" ext:qtip="When this option is selected, a list of all {1}s will be displayed on the left side-bar and records in each {1} can be accessed by a single click." />'),
            topBaseUrl,
            arrApplicantsSettings.office_label
        );

        this.queueShowField = new Ext.form.Checkbox({
            name: 'queueShowInLeftPanel',
            hideLabel: true,
            checked:   false,
            boxLabel:  msgHelp
        });
    }

    this.profileForm = new Ext.FormPanel({
        style: 'background-color: #fff; padding: 5px;',
        labelWidth: 120,
        items: [
            {
                cls: 'change_login_table',
                layout: 'table',
                layoutConfig: {columns: 2},
                items: [
                    {
                        layout: 'form',
                        colspan: 2,
                        items: thisDialog.firstNameField
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.lastNameField
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.emailField
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.showSpecialAnnouncementsCheckbox
                    }, {
                        // Spacer
                        xtype:  'box',
                        colspan: 2,
                        style:  'padding: 5px',
                        autoEl: {
                            tag:  'div',
                            html: '&nbsp;'
                        }
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.userNameField
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.oldPasswordField
                    }, {
                        layout: 'form',
                        items:  thisDialog.newPasswordField
                    }, {
                        layout: 'form',
                        style:  'padding:0 0 5px 5px;',
                        items:  {
                            xtype: 'box',
                            'autoEl': {'tag': 'a', 'href': '#', 'class': 'bluelink', 'style': 'display: block; margin-top: 5px', 'html': _('Random password'), 'title': _('Click to generate a random password.')}, // Thanks to IE - we need to use quotes...
                            listeners: {
                                scope: this,
                                render: function(c){
                                    c.getEl().on('click', function() {
                                        var newPassword = '';
                                        do {
                                            newPassword = generatePassword();
                                        } while (!thisDialog.isCorrectPassword(newPassword));

                                        thisDialog.newPasswordField.setValue(newPassword);
                                        thisDialog.newPasswordField.updateMeter(newPassword);
                                    }, this, {stopEvent: true});
                                }
                            }
                        }
                    }, {
                        layout: 'form',
                        colspan: 2,
                        items:  thisDialog.queueShowField
                    }
                ]
            }
        ]
    });

    UserProfileDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-cog"></i>' + _('Your Profile'),
        modal:       true,
        autoHeight:  true,
        autoWidth:   true,
        resizable:   false,
        buttonAlign: 'center',
        items:       this.profileForm,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
             },
             {
                text: _('Save'),
                cls: 'orange-btn',
                handler: thisDialog.saveInfo.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadInfo.createDelegate(this));
};

Ext.extend(UserProfileDialog, Ext.Window, {
    isCorrectPassword: function(strPassword) {
        // @see var passwordValidationRegexp; e.g. in layouts/main/main.phtml
        var passRegex = new RegExp(passwordValidationRegexp);
        return passRegex.test(strPassword);
    },

    loadInfo: function() {
        var thisDialog = this;

        thisDialog.getEl().mask('Loading...');

        this.profileForm.getForm().load({
            url: topBaseUrl + '/profile/index/load',

            success: function () {
                thisDialog.getEl().unmask();
            },

            failure: function(form, action) {
                thisDialog.close();

                var msg = action && action.result && action.result.message ? action.result.message : 'Information cannot be loaded. Please try again later.';
                Ext.simpleConfirmation.error(msg);
            }
        });
    },

    isIframe: function () {
        try {
            return window.self !== window.top;
        } catch (e) {
            return true;
        }
    },

    saveInfo: function() {
        var thisDialog = this,
            booReallyValid = this.profileForm.getForm().isValid();

        // Check new password
        if (booReallyValid && !empty(thisDialog.newPasswordField.getValue()) && !thisDialog.isCorrectPassword(thisDialog.newPasswordField.getValue())) {
            thisDialog.newPasswordField.markInvalid(passwordValidationRegexpMessage);
            booReallyValid = false;
        }

        if (booReallyValid) {
            thisDialog.getEl().mask('Saving...');
            this.profileForm.getForm().submit({
                url: topBaseUrl + '/profile/index/save',
                params: {
                },

                success: function (form, action) {
                    var booShowDone = true;
                    if (action && action.result) {
                        $('.user-name').html(action.result.updated_name);

                        if (thisDialog.hideQueueField) {
                            // Do nothing
                        } else {
                            if (thisDialog.isIframe()) {
                                window.parent.arrApplicantsSettings.access.search.view_queue_panel = action.result.view_queue_panel;
                            } else {
                                arrApplicantsSettings.access.search.view_queue_panel = action.result.view_queue_panel;
                            }
                        }

                        if (action.result.settings_changed) {
                            booShowDone = false;
                            Ext.Msg.confirm('Please confirm', 'All settings were saved successfully. Please reload the page to apply changes. Reload now?', function (btn) {
                                if (btn === 'yes') {
                                    thisDialog.getEl().mask('Reloading...');
                                    if (thisDialog.isIframe()) {
                                        parent.location.href = topBaseUrl;
                                    } else {
                                        window.location.href = topBaseUrl;
                                    }
                                } else {
                                    thisDialog.close();
                                }
                            });
                        }
                    }

                    if (booShowDone) {
                        Ext.simpleConfirmation.msg('Info', 'Done!');
                        thisDialog.close();
                    }
                },

                failure: function (form, action) {
                    thisDialog.getEl().unmask();

                    var msg = action && action.result && action.result.message ? action.result.message : 'Information cannot be saved. Please try again later.';
                    Ext.simpleConfirmation.error(msg);
                }
            });
        }
    }
});