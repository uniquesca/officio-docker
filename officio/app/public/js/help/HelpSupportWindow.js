var HelpSupportWindow = function (config) {
    Ext.apply(this, config);

    var thisHelpSupportWindow = this;

    this.formPanel = new Ext.form.FormPanel({
        labelWidth: 80,
        labelAlign: 'top',

        items: [
            {
                xtype: 'box',
                autoEl: {
                    tag: 'div',
                    style: 'font-size: 16px; padding-bottom: 20px',
                    html: _('How can we contact you?')
                }
            }, {
                xtype: 'container',
                layout: 'table',
                bodyStyle: 'padding: 5px;',
                layoutConfig: {columns: 3},
                items: [
                    {
                        xtype: 'container',
                        layout: 'form',
                        items: {
                            name: 'name',
                            xtype: 'textfield',
                            fieldLabel: _('Name'),
                            width: 400,
                            allowBlank: false
                        }
                    }, {
                        html: '&nbsp;&nbsp;&nbsp;'
                    }, {
                        xtype: 'container',
                        layout: 'form',
                        items: {
                            name: 'company',
                            xtype: 'textfield',
                            fieldLabel: _('Company'),
                            width: 400,
                            allowBlank: false
                        }
                    }, {
                        xtype: 'container',
                        layout: 'form',
                        items: {
                            name: 'email',
                            xtype: 'textfield',
                            fieldLabel: _('Email'),
                            width: 400,
                            allowBlank: false
                        }
                    }, {
                        html: '&nbsp;&nbsp;&nbsp;'
                    }, {
                        xtype: 'container',
                        layout: 'form',
                        items: {
                            name: 'phone',
                            xtype: 'textfield',
                            fieldLabel: _('Phone No.'),
                            width: 400,
                            allowBlank: false
                        }
                    }
                ]
            },
            {
                name: 'description',
                xtype: 'textarea',
                fieldLabel: _('My Request'),
                width: 800,
                height: 150,
                allowBlank: false
            }
        ]
    });

    HelpSupportWindow.superclass.constructor.call(this, {
        title: _('Support Request'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        items: this.formPanel,

        buttons: [{
            text: _('Cancel'),
            handler: this.closeThisWindow.createDelegate(thisHelpSupportWindow)
        }, {
            text: _('Submit'),
            cls: 'orange-btn',
            handler: this.submitSupportRequest.createDelegate(thisHelpSupportWindow)
        }]
    });
};

Ext.extend(HelpSupportWindow, Ext.Window, {
    showDialog: function () {
        this.loadInvoiceDetails();
    },

    closeThisWindow: function () {
        this.close();
    },

    loadInvoiceDetails: function () {
        var thisWindow = this;

        Ext.getBody().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/help/index/get-support-request-info',
            method: 'post',

            success: function (result, request) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    thisWindow.show();
                    thisWindow.center();

                    var oSupportRequestRecord = Ext.data.Record.create([]);
                    thisWindow.formPanel.getForm().loadRecord(new oSupportRequestRecord(resultData.arrRequestInfo));
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                }

                Ext.getBody().unmask();
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(_('Can\'t load info. Please try again later.'));
            }
        });
    },

    submitSupportRequest: function () {
        var thisWindow = this;
        if (thisWindow.formPanel.getForm().isValid()) {
            thisWindow.getEl().mask(_('Submitting...'));

            thisWindow.formPanel.getForm().submit({
                url: baseUrl + '/help/index/send-support-request',

                success: function (form, action) {
                    thisWindow.getEl().mask(_('Your request was successfully sent!'));
                    setTimeout(function () {
                        thisWindow.closeThisWindow();
                    }, 750);
                },

                failure: function (form, action) {
                    if (!empty(action.result.message)) {
                        Ext.simpleConfirmation.error(action.result.message);
                    } else {
                        Ext.simpleConfirmation.error(_('Internal error. Please try again later.'));
                    }

                    thisWindow.getEl().unmask();
                }
            });
        }
    }
});