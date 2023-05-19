var CreditCardPaymentDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var startYear = new Date().getFullYear();
    var cc_exp_year_data = [];
    for (var i = startYear; i <= startYear + 10; i++) {
        var year_id = i + '';
        cc_exp_year_data.push({
            year_id:   year_id.substr(2),
            year_name: i
        });
    }

    var currency   = getCurrencyByTAId(thisWindow.ta_id);
    this.formPanel = new Ext.FormPanel({
        labelWidth: 130,
        bodyStyle: 'padding: 5px;',

        items: [
            {
                xtype: 'hidden',
                name:  'member_id',
                value: thisWindow.member_id
            }, {
                xtype: 'hidden',
                name:  'ta_id',
                value: thisWindow.ta_id
            }, {
                xtype:      'displayfield',
                fieldLabel: _('Amount Outstanding'),
                width:      120,
                labelStyle: 'width: auto',
                style:      'text-align:right',
                value:      formatMoney(currency, thisWindow.amountOutstanding, true, true)
            }, {
                xtype:      'displayfield',
                fieldLabel: _('Amount Paid'),
                width:      120,
                labelStyle: 'width: auto',
                style:      'text-align:right',
                value:      formatMoney(currency, thisWindow.amountPaid, true, true)
            }, {
                xtype:      'displayfield',
                fieldLabel: _('Balance'),
                width:      120,
                labelStyle: 'width: auto',
                style:      'text-align:right',
                value:      formatMoney(currency, thisWindow.balance, true, true)
            }, {
                xtype:         'numberfield',
                name:          'amount',
                fieldLabel:    _('Amount in ') + getCurrencySymbolByTAId(thisWindow.ta_id),
                allowNegative: false,
                allowBlank:    false,
                minValue:      0.01,
                maxValue:      thisWindow.balance,
                width:         120
            }, {
                xtype:     'displayfield',
                hideLabel: true,
                style:     'padding: 10px 0;',
                value:     _('<hr style="display: block; height: 1px; border: 0; border-top: 1px solid #b5b8c8;">')
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
                        items:  {
                            xtype:      'combo',
                            name:       'cc_month',
                            hiddenName: 'cc_month',
                            width:      120,
                            fieldLabel: _('Expiration Date'),
                            emptyText: 'Month',

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
                        }
                    }, {
                        layout:     'form',
                        style:      'padding-left: 10px;',

                        items: {
                            xtype:      'combo',
                            name:       'cc_year',
                            hiddenName: 'cc_year',
                            emptyText: 'Year',
                            hideLabel: true,
                            width:      120,

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
                        }
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

    CreditCardPaymentDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-credit-card"></i>' + _('Pay by Credit Card'),
        layout:      'form',
        bodyStyle:   'padding: 5px; background-color: white;',
        resizable:   false,
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
            }, {
                text:    _('Pay'),
                cls:     'orange-btn',
                handler: this.savePayment.createDelegate(thisWindow)
            }
        ]
    });

    thisWindow.on('show', function () {
        // Don't highlight fields as incorrect
        thisWindow.formPanel.getForm().reset();
    });
};

Ext.extend(CreditCardPaymentDialog, Ext.Window, {
    savePayment: function () {
        var thisWindow = this;
        if (thisWindow.formPanel.getForm().isValid()) {
            thisWindow.getEl().mask('Processing...');
            thisWindow.formPanel.getForm().submit({
                url: baseUrl + '/default/tran-page/process-payment',

                success: function () {
                    Ext.simpleConfirmation.success('Payment was successfully processed.');
                    refreshAccountingTabByTA(thisWindow.member_id, thisWindow.ta_id);
                    thisWindow.close();
                },

                failure: function (form, action) {
                    var msg = !empty(action.result.message) ? action.result.message : 'Cannot process information. Please try again later.';
                    Ext.simpleConfirmation.error(msg);

                    thisWindow.getEl().unmask();
                }
            });
        }
    }
});