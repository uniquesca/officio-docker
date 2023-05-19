var ApplicantsProfilePaymentDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var cc_expire_month = new Ext.form.ComboBox({
        name:       'cc_month',
        hiddenName: 'cc_month',
        width:      110,
        fieldLabel: _('Month'),

        store: new Ext.data.SimpleStore({
            fields: ['month_id', 'month_name'],
            data: [
                ['01', _('01 - January')],
                ['02', _('02 - February')],
                ['03', _('03 - March')],
                ['04', _('04 - April')],
                ['05', _('05 - May')],
                ['06', _('06 - June')],
                ['07', _('07 - July')],
                ['08', _('08 - August')],
                ['09', _('09 - September')],
                ['10', _('10 - October')],
                ['11', _('11 - November')],
                ['12', _('12 - December')]
            ]
        }),

        mode:           'local',
        valueField:     'month_id',
        displayField:   'month_name',
        triggerAction:  'all',
        forceSelection: true,
        readOnly:       true,
        typeAhead:      true,
        selectOnFocus:  true,
        editable:       false,
        allowBlank:     false
    });

    var startYear = new Date().getFullYear();
    var cc_exp_year_data = [];
    for (var i = startYear; i <= startYear + 10; i++) {
        var year_id = i + '';
        cc_exp_year_data.push({
            year_id:   year_id.substr(2),
            year_name: i
        });
    }

    var cc_expire_year = new Ext.form.ComboBox({
        name:       'cc_year',
        hiddenName: 'cc_year',
        fieldLabel: 'Year',
        width:      80,

        store: new Ext.data.Store({
            data:   cc_exp_year_data,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{name: 'year_id'}, {name: 'year_name'}]))
        }),

        mode:           'local',
        valueField:     'year_id',
        displayField:   'year_name',
        triggerAction:  'all',
        forceSelection: true,
        readOnly:       true,
        typeAhead:      true,
        selectOnFocus:  true,
        editable:       false,
        allowBlank:     false
    });

    this.formPanel = new Ext.FormPanel({

        labelWidth: 125,
        bodyStyle: 'padding: 5px;',

        items: [
            {
                xtype: 'hidden',
                name:  'clientId',
                value: thisWindow.clientId
            }, {
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
                style:     'padding-bottom: 15px;',
                value:     _('Please ensure that all the required documents have been uploaded and all the forms are duly complete.')
            }, {
                xtype:     'displayfield',
                hideLabel: true,
                width:     380,
                style:     'padding-bottom: 15px;',
                value:     String.format(
                    _('An initial payment of {0} is required to make this submission. ') +
                    _('The balance of fees can be paid after the application is submitted.'),
                    formatMoney(this.currency, this.systemAccessFee, true, true)
                )
            }, {
                name:       'cc_name',
                xtype:      'textfield',
                fieldLabel: _('Name on Credit Card'),
                width:      250,
                allowBlank: false
            }, {
                name:       'cc_num',
                xtype:      'textfield',
                fieldLabel: _('Credit Card Number'),
                maskRe:     /\d/,
                vtype:      'cc_number',
                width:      250,
                allowBlank: false
            }, {
                layout: 'column',
                items:  [
                    {
                        layout: 'form',
                        items:  cc_expire_month
                    }, {
                        layout:     'form',
                        labelWidth: 40,
                        style:      'padding-left:15px;',
                        items:      cc_expire_year
                    }
                ]
            }, {
                layout: 'column',
                items:  [
                    {
                        layout: 'form',
                        items:  {
                            name:       'cc_cvn',
                            xtype:      'textfield',
                            maskRe:     /\d/,
                            fieldLabel: _('CVN'),
                            vtype:      'cc_cvn',
                            width:      45,
                            allowBlank: false
                        }
                    }, {
                        xtype:     'box',
                        autoEl:    {
                            tag:    'img',
                            src:    topBaseUrl + '/images/icons/help.png',
                            width:  16,
                            height: 16,
                            style:  'padding: 3px'
                        },
                        listeners: {
                            scope:  this,
                            render: function (c) {
                                new Ext.ToolTip({
                                    target:    c.getEl(),
                                    anchor:    'right',
                                    title:     _('About CVN'),
                                    autoLoad:  {
                                        url: topBaseUrl + '/help/public/get-cvn-info'
                                    },
                                    width:     400,
                                    autoHide:  false,
                                    closable:  true,
                                    draggable: true
                                });
                            }
                        }
                    }
                ]
            }
        ]
    });

    ApplicantsProfilePaymentDialog.superclass.constructor.call(this, {
        layout:      'form',
        resizable:   false,
        iconCls:     'icon-applicant-submit-to-government',
        bodyStyle:   'padding: 5px; background-color: white;',
        buttonAlign: 'rigth',
        title:       _('Submit to ') + current_member_company_name,
        autoHeight:  true,
        autoWidth:   true,
        modal:       true,

        items: [
            this.formPanel
        ],

        buttons: [
            {
                text:    _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            },
            {
                text:    _('Submit Now'),
                cls:     'orange-btn',
                handler: this.savePayment.createDelegate(thisWindow)
            }
        ]
    });
};

Ext.extend(ApplicantsProfilePaymentDialog, Ext.Window, {
    savePayment: function () {
        var thisWindow = this;
        if (thisWindow.formPanel.getForm().isValid()) {
            thisWindow.getEl().mask(_('Processing...'));

            thisWindow.formPanel.getForm().submit({
                url: topBaseUrl + '/applicants/index/submit-to-government',

                success: function (form, action) {
                    var resultData = action && action.result;
                    if (resultData.success) {
                        var msg = resultData.message && !empty(resultData.message) ? resultData.message : _('Done!');
                        Ext.simpleConfirmation.success(msg);

                        if (resultData.booUpdateInfo) {
                            thisWindow.owner.owner.updateClientInfoEverywhere(resultData.arrUpdatedInfo);
                        }

                        thisWindow.owner.owner.makeReadOnlyClient();

                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    } else {
                        thisWindow.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
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