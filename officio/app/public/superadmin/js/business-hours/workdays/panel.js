var BusinessScheduleWorkdaysPanel = function (config) {
    Ext.apply(this, config);

    var arrFormItems = [];

    this.mainRadio = new Ext.form.RadioGroup({
        fieldLabel: empty(this.company_id) ? _("Limit user's access to business hours only") : _("Limit all company users access to business hours only"),
        width:      200,
        name:       'business_time_enabled',

        items: [
            {
                boxLabel:   _('No'),
                name:       'business_time_enabled',
                inputValue: 'N'
            },
            {
                boxLabel:   _('Yes'),
                name:       'business_time_enabled',
                inputValue: 'Y'
            }
        ]
    });

    arrFormItems.push({
        xtype:      'container',
        layout:     'form',
        labelAlign: 'top',
        items:      this.mainRadio
    });

    this.arrDays = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday'
    ];

    var arrCells = [];
    for (var i = 0; i < this.arrDays.length; i++) {
        arrCells.push({
            xtype:    'checkbox',
            name:     this.arrDays[i] + '_time_enabled',
            boxLabel: _(ucfirst(this.arrDays[i])),
            width:    120,

            listeners: {
                'check': this.toggleTimeFields.createDelegate(this, [
                    this.arrDays[i] + '_time_enabled',
                    this.arrDays[i] + '_time_from',
                    this.arrDays[i] + '_time_to'
                ])
            }
        });

        arrCells.push({
            xtype:      'container',
            layout:     'form',
            style:      'margin-left: 20px',
            labelWidth: 40,

            items: {
                xtype:          'timefield',
                fieldLabel:     _('From'),
                labelSeparator: '',
                width:          125,
                labelStyle:     'padding-top: 5px; width: 30px;',
                format:         'H:i',
                altFormats:     'H:i',
                name:           this.arrDays[i] + '_time_from',
                hiddenName:     this.arrDays[i] + '_time_from',
                allowBlank:     false,

                listeners: {
                    'select': this.checkDates.createDelegate(this, [
                        this.arrDays[i] + '_time_from',
                        this.arrDays[i] + '_time_to',
                        'end'
                    ])
                }
            }
        });

        arrCells.push({
            xtype:      'container',
            layout:     'form',
            style:      'margin-left: 20px',
            labelWidth: 20,

            items: {
                xtype:          'timefield',
                fieldLabel:     _('To'),
                labelSeparator: '',
                labelStyle:     'padding-top: 5px; width: 15px;',
                width:          125,
                format:         'H:i',
                altFormats:     'H:i',
                name:           this.arrDays[i] + '_time_to',
                hiddenName:     this.arrDays[i] + '_time_to',
                allowBlank:     false,

                listeners: {
                    'select': this.checkDates.createDelegate(this, [
                        this.arrDays[i] + '_time_from',
                        this.arrDays[i] + '_time_to',
                        'start'
                    ])
                }
            }
        });
    }

    arrFormItems.push({
        xtype:  'container',
        layout: 'table',
        cls:    'workdays-table',

        layoutConfig: {
            columns: 3
        },

        items: arrCells
    });

    BusinessScheduleWorkdaysPanel.superclass.constructor.call(this, {
        collapsible: true,
        collapsed:   false,
        split:       true,
        buttonAlign: 'center',
        cls:         'filter-panel',

        bodyStyle: {
            background: '#ffffff',
            padding:    '7px'
        },

        items: arrFormItems,

        buttons: [
            {
                text: _('Save Changes'),
                cls: 'orange-btn',
                width: 100,
                hidden: !arrBusinessHoursAccess['update-workdays'],
                handler: this.saveWorkdaysData.createDelegate(this)
            }
        ]
    });

    this.on('render', this.loadWorkdaysData.createDelegate(this));
};

Ext.extend(BusinessScheduleWorkdaysPanel, Ext.form.FormPanel, {
    toggleTimeFields: function (thisCheckboxName, timeFromFieldName, timeToFieldName) {
        var checkbox = this.find('name', thisCheckboxName)[0];
        var timeFrom = this.find('name', timeFromFieldName)[0];
        var timeTo   = this.find('name', timeToFieldName)[0];

        var booEnable = checkbox.getValue();

        if (!booEnable) {
            timeFrom.setValue('');
            timeTo.setValue('');
        }

        timeFrom.clearInvalid();
        timeTo.clearInvalid();
        timeFrom.setDisabled(!booEnable);
        timeTo.setDisabled(!booEnable);
    },

    checkDates: function (timeFromFieldName, timeToFieldName, startOrEnd) {
        var timeFrom = this.find('name', timeFromFieldName)[0];
        var timeTo   = this.find('name', timeToFieldName)[0];

        timeFrom.clearInvalid();
        timeTo.clearInvalid();

        var startValue = timeFrom.getValue();
        var endValue   = timeTo.getValue();

        if (empty(startValue) || empty(endValue)) {
            return;
        }

        var d1 = new Date('2018-01-01 ' + startValue);
        var d2 = new Date('2018-01-01 ' + endValue);
        if (d1.getTime() > d2.getTime()) {
            if (startOrEnd === 'start') {
                timeTo.setValue(startValue);
            } else {
                timeFrom.setValue(endValue);
                this.checkDates(timeFromFieldName, timeToFieldName, 'start');
            }
        }
    },

    initMainEvents: function () {
        var thisForm = this;
        thisForm.mainRadio.on('change', function () {
            var radio         = this.getValue();
            var booYesChecked = radio.inputValue === 'Y';
            for (var i = 0; i < thisForm.arrDays.length; i++) {
                var dayCheckbox = thisForm.find('name', thisForm.arrDays[i] + '_time_enabled')[0];

                if (!booYesChecked) {
                    dayCheckbox.setValue(false);
                    dayCheckbox.setDisabled(true);
                } else {
                    dayCheckbox.setDisabled(false);

                    if ([
                        'monday',
                        'tuesday',
                        'wednesday',
                        'thursday',
                        'friday'
                    ].has(thisForm.arrDays[i])) {
                        dayCheckbox.setValue(true);

                        var timeFrom = thisForm.find('name', thisForm.arrDays[i] + '_time_from')[0];
                        timeFrom.setValue('08:00');

                        var timeTo = thisForm.find('name', thisForm.arrDays[i] + '_time_to')[0];
                        timeTo.setValue('18:00');
                    }
                }
            }
        });
    },

    loadWorkdaysData: function () {
        var thisForm = this;

        thisForm.getEl().mask(_('Loading...'));

        thisForm.getForm().load({
            url: baseUrl + '/manage-business-hours/load-workdays-data',

            params: {
                member_id:  thisForm.member_id,
                company_id: thisForm.company_id
            },

            success: function () {
                for (var i = 0; i < thisForm.arrDays.length; i++) {
                    var dayCheckbox = thisForm.find('name', thisForm.arrDays[i] + '_time_enabled')[0];

                    dayCheckbox.fireEvent('check', dayCheckbox, dayCheckbox.getValue());
                }

                thisForm.getEl().unmask();

                if (thisForm.mainRadio.getValue().inputValue === 'N') {
                    thisForm.initMainEvents();
                } else {
                    thisForm.initMainEvents.defer(100, thisForm);
                }
            },

            failure: function (form, action) {
                thisForm.getEl().unmask();
                thisForm.initMainEvents.defer(100, thisForm);

                try {
                    Ext.simpleConfirmation.error(action.result.message);
                } catch (e) {
                }
            }
        });
    },


    saveWorkdaysData: function () {
        var thisForm      = this;
        var booValid      = thisForm.getForm().isValid();
        var radio         = thisForm.mainRadio.getValue();
        var booYesChecked = radio.inputValue === 'Y';

        if (booValid && booYesChecked) {
            // Do checks only if "Yes" is checked
            var booAtLeastOneCheckboxChecked = false;
            var booShowWarning               = false;
            for (var i = 0; i < thisForm.arrDays.length; i++) {
                var dayCheckbox = thisForm.find('name', thisForm.arrDays[i] + '_time_enabled')[0];
                var timeFrom    = thisForm.find('name', thisForm.arrDays[i] + '_time_from')[0];
                var timeTo      = thisForm.find('name', thisForm.arrDays[i] + '_time_to')[0];

                if (dayCheckbox.getValue()) {
                    booAtLeastOneCheckboxChecked = true;
                }

                if (!timeFrom.disabled && !timeTo.disabled) {
                    var booFromValid = timeFrom.isValid();
                    var booToValid   = timeTo.isValid();

                    if (booFromValid && booToValid && timeFrom.getValue() === timeTo.getValue()) {
                        timeFrom.markInvalid();
                        timeTo.markInvalid();

                        booShowWarning = true;
                    }
                }
            }


            if (!booAtLeastOneCheckboxChecked) {
                booValid = false;
                Ext.simpleConfirmation.error(_('Please check at least one checkbox.'));
            } else if (booShowWarning) {
                booValid = false;
                Ext.simpleConfirmation.error(_('Please make sure that the opening time is before the closing time.'));
            }
        }


        if (booValid) {
            thisForm.getEl().mask(_('Saving...'));

            thisForm.getForm().submit({
                url:    baseUrl + '/manage-business-hours/save-workdays-data',
                params: {
                    member_id:  thisForm.member_id,
                    company_id: thisForm.company_id
                },

                success: function (form, action) {
                    if (!empty(action.result.message)) {
                        Ext.simpleConfirmation.msg(_('Info'), action.result.message);
                    }

                    thisForm.getEl().unmask();
                },

                failure: function (f, action) {
                    thisForm.getEl().unmask();
                    if (action.result && !empty(action.result.message)) {
                        Ext.simpleConfirmation.error(action.result.message);
                    } else {
                        Ext.simpleConfirmation.error(_('Cannot save info'));
                    }
                }
            });
        }
    }
});