var ClientTrackerAddDialog = function (arrOptions, viewer, booCompanies) {
    booCompanies = !!booCompanies;

    if (empty(arrOptions.timeActual)) {
        arrOptions.timeActual = 0;
    }
    var hours = Math.floor(arrOptions.timeActual / (60));
    var mins = arrOptions.timeActual - (hours * 60);

    var actualTimeWorked = String.format(
        _('{0}h {1}min'),
        hours,
        mins
    );

    var clientOrCompanyName = '';
    if (arrOptions.clientName) {
        clientOrCompanyName = arrOptions.clientName;
    } else {
        // Load client's/company's name from the tab's title
        if (!booCompanies) {
            var applicantsTab = Ext.getCmp(arrOptions.panelType + '-tab-panel');
            if (applicantsTab) {
                applicantsTab.items.each(function (currentItem) {
                    var childProfileForm = currentItem.items.first().applicantsProfileForm;
                    if (childProfileForm && childProfileForm.caseId == arrOptions.clientId) {
                        clientOrCompanyName = childProfileForm.getCurrentApplicantTabName();
                    }
                });
            }
        } else {
            var tab = Ext.getCmp('company-tab-' + arrOptions.companyId);
            if (tab) {
                clientOrCompanyName = $(tab.title).text();
            } else if (booCompanies && companyDetails) {
                clientOrCompanyName = companyDetails.company_name;
            }
        }
    }

    this.clientId = arrOptions.clientId;
    this.companyId = arrOptions.companyId;
    this.viewer = viewer;

    this.track_id = new Ext.form.Hidden({
        value: arrOptions.trackInfo ? arrOptions.trackInfo.track_id : ''
    });

    this.track_time_actual = new Ext.form.Hidden({
        value: arrOptions.timeActual
    });

    this.action = new Ext.form.Hidden({
        value: arrOptions.action
    });

    this.hours = new Ext.form.TextField({
        width: 60,
        allowBlank: false,
        value: arrOptions.trackInfo ? Math.floor(arrOptions.trackInfo.track_time_billed / 60) : hours,
        listeners: {
            change: this.updateTotal.createDelegate(this)
        }
    });

    this.mins = new Ext.form.TextField({
        width: 60,
        allowBlank: false,
        style: 'margin-right:3px;',
        value: arrOptions.trackInfo ? arrOptions.trackInfo.track_time_billed % 60 : mins,
        listeners: {
            change: this.updateTotal.createDelegate(this)
        }
    });

    this.round = new Ext.form.Hidden({
        value: arrOptions.trackInfo ? arrOptions.trackInfo.track_round_up : (typeof (arrTimeTrackerSettings) !== 'undefined' ? parseInt(arrTimeTrackerSettings.round_up, 10) : 0)
    });

    var rateVal = arrOptions.trackInfo ? arrOptions.trackInfo.track_rate : (typeof (arrTimeTrackerSettings) !== 'undefined' ? arrTimeTrackerSettings.rate : 0);
    this.rate = new Ext.form.TextField({
        fieldLabel: _('Rate/Hour'),
        labelStyle: 'margin-left:2px;',
        width: 230,
        allowBlank: false,
        colspan: 2,
        value: Ext.util.Format.number(parseFloat(rateVal), '0.00'),
        listeners: {
            change: this.updateTotal.createDelegate(this)
        }
    });

    this.total = new Ext.form.TextField({
        width: 230,
        colspan: 2,
        readOnly: true,
        value: arrOptions.trackInfo ? arrOptions.trackInfo.track_total : ''
    });

    this.date = new Ext.form.DateField({
        value: arrOptions.trackInfo && arrOptions.trackInfo.track_posted_on_date ? arrOptions.trackInfo.track_posted_on_date : new Date(),
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort,
        allowBlank: false,
        maxLength: 12, // Fix issue with date entering in 'full' format
        width: 230,
        colspan: 3
    });

    this.comment = new Ext.form.TextArea({
        labelStyle: 'margin-left:2px;',
        colspan: 3,
        width: 435,
        height: 85,
        value: arrOptions.trackInfo ? arrOptions.trackInfo.track_comment : ''
    });

    ClientTrackerAddDialog.superclass.constructor.call(this, {
        title: '<i class="las la-stopwatch"></i>' + _('Time Tracker'),
        closeAction: 'close',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        labelWidth: 117,

        items: [
            {
                layout: 'table',
                cls: 'x-table-layout-cell-bottom-padding',
                layoutConfig: {
                    columns: 5,
                    tableAttrs: {
                        cellspacing: 2
                    }
                },
                items: [
                    {
                        xtype: 'label',
                        text: (!booCompanies ? _('Case') : _('Company')) + ' ' + clientOrCompanyName,
                        colspan: 5,
                        style: 'line-height:30px;'
                    },

                    {
                        xtype: 'label',
                        html: _('Date'),
                        colspan: 2
                    },
                    this.date,

                    {
                        xtype: 'label',
                        text: '',
                        colspan: 2
                    }, {
                        xtype: 'label',
                        html: _('HH'),
                        style: 'margin-left:9px;'
                    }, {
                        xtype: 'label',
                        html: _('MM'),
                        style: 'margin-left:9px;'
                    }, {
                        xtype: 'label',
                        text: ''
                    },

                    {
                        xtype: 'label',
                        html: _('Billable time')
                    }, {
                        xtype: 'label',
                        style: 'margin: 0 5px;',
                        html: String.format(
                            "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-right: 0; vertical-align: text-bottom'></i>",
                            _('The "actual time worked" and "billable time" may vary, as billable time follows the logic you defined in the Admin to round up hours.')
                        )
                    },
                    this.hours,
                    this.mins,
                    {
                        xtype: 'label',
                        style: 'width:240px;',
                        text: _('(Actual time worked ') + actualTimeWorked + ')'
                    },

                    {
                        xtype: 'label',
                        html: _('Rate/Hour')
                    }, {
                        xtype: 'label',
                        style: 'margin: 0 0 0 5px;',
                        html: String.format(
                            "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-right: 0; vertical-align: text-bottom'></i>&nbsp;&nbsp;$",
                            _('You can predefine the Rate/Hour in the Admin for each user.')
                        )
                    },
                    this.rate,
                    {
                        xtype: 'label',
                        text: ''
                    },

                    {
                        xtype: 'label',
                        html: _('Total')
                    }, {
                        xtype: 'label',
                        style: 'margin: 0 0 0 32px;',
                        html: '$'
                    },
                    this.total,
                    {
                        xtype: 'label',
                        text: ''
                    },

                    {
                        xtype: 'label',
                        html: _('Description')
                    }, {
                        xtype: 'label',
                        text: ''
                    },
                    this.comment,

                    this.round,
                    this.track_id,
                    this.track_time_actual
                ]
            }
        ],

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.addRecord.createDelegate(this, [false, booCompanies])
            }
        ],
        listeners: {
            render: function (i) {
                i.updateTotal(true);
            }
        }
    });
};

Ext.extend(ClientTrackerAddDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    addRecord: function (booSilence, booCompanies) {
        // Recalculate total if dialog wasn't showed
        this.updateTotal(true);

        var action = this.action.getValue();
        if (action != 'create' && action != 'edit' && action != 'add')
            return; // cheater detected

        var win = this;

        if (this.getEl() && !booSilence) {
            this.getEl().mask(_('Saving...'));
        }

        var track_time_billed = parseInt(this.hours.getValue(), 10) * 60 + parseInt(this.mins.getValue(), 10);
        var track_time_actual = action == 'add' ? track_time_billed : parseInt(this.track_time_actual.getValue(), 10);
        Ext.Ajax.request({
            url: topBaseUrl + '/clients/time-tracker/' + action + '/',
            params: {
                track_id: this.track_id.getValue(),
                track_member_id: this.clientId,
                track_company_id: this.companyId,
                track_time_billed: track_time_billed,
                track_time_actual: track_time_actual,
                track_round_up: this.round.getValue(),
                track_rate: this.rate.getValue(),
                track_total: this.total.getValue(),
                track_date: empty(this.date.getValue()) ? '' : Ext.util.Format.date(this.date.getValue(), 'Y-m-d'),
                track_comment: this.comment.getValue(),
                track_type: booCompanies ? 'company' : 'client'
            },
            success: function (f) {
                if (!booSilence) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        win.close();
                        Ext.simpleConfirmation.msg(_('Info'), _('Time tracker item saved successfully.'), 4000);

                        if (win.viewer)
                            win.viewer.ClientTrackerGrid.store.reload();
                    } else {
                        win.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.msg);
                    }
                }
            },

            failure: function () {
                if (!booSilence) {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(_('Internal error. Please, try again later.'));
                }
            }
        });
    },

    updateTotal: function (booUpdateMins) {
        var hours = this.hours.getValue();
        var mins = this.mins.getValue();
        var round = parseInt(this.round.getValue(), 10);
        var rate = this.rate.getValue();

        if (hours === '' || mins === '' || rate === '') {
            this.total.setValue('');
            return;
        }

        hours = parseInt(hours, 10);
        mins = parseInt(mins, 10);
        rate = parseFloat(rate);

        if (round && mins)
            mins = Math.ceil(mins / round) * round;

        hours += mins / 60;

        var total = Math.round(hours * rate * 100) / 100;

        this.total.setValue(total.toFixed(2));

        if (booUpdateMins === true)
            this.mins.setValue(mins);
    }
});