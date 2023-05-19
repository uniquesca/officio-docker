var ClientTrackerMarkDialog = function (viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);

    var win = this;
    var sel = viewer.getSelectionModel().getSelections();
    this.arrTrackIds = [];
    this.sumDue = 0;
    this.sumMin = 0;
    var companyTA = [];
    Ext.each(sel, function (item) {
        if (item.data.track_billed==='N')
        {
            win.arrTrackIds.push(item.data.track_id);
            win.sumDue+=parseFloat(item.data.track_total);
            win.sumMin+=parseInt(item.data.track_time_billed_rounded);
            if (companyTA.length===0)
                Ext.each(item.data.ta_ids, function (i) {
                    companyTA.push([i.id, i.name+' ('+i.currency_name+')']);
                });
        }
    });

    this.textlabel = new Ext.form.Label({
        html: _("Marking as Billed will transfer the total for the marked records to the 'Fee Due' column of Fees Due table.<br><br>"),
        style: 'font-size: 14px;'
    });

    this.date = new Ext.form.DateField({
        fieldLabel: _('Date'),
        value: new Date(),
        width: 160,
        allowBlank: false,
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort
    });

    this.due = new Ext.form.TextField({
        fieldLabel: _('Fee Due'),
        width: 160,
        readOnly: true,
        style: 'color:#656565;',
        value: '$'+this.sumDue.toFixed(2)
    });

    this.feeGST = new Ext.form.ComboBox({
        store: new Ext.data.SimpleStore({
            fields: ['province_id', 'province'],
            data: empty(arrTimeTrackerSettings.arrProvinces) ? [
                [0, _('Exempt')]
            ] : arrTimeTrackerSettings.arrProvinces
        }),
        displayField: 'province',
        valueField: 'province_id',
        typeAhead: false,
        mode: 'local',
        triggerAction: 'all',
        selectOnFocus: false,
        editable: false,
        grow: true,
        fieldLabel: _('Tax'),
        value: Ext.state.Manager.get('tax') ? Ext.state.Manager.get('tax') : 0,
        width: 300,
        listWidth: 300,
        allowBlank: false,
        listeners: {
            'select' : function(combo) {
                Ext.state.Manager.set('tax', combo.getValue());
            }
        }
    });

    this.desc = new Ext.form.TextField({
        fieldLabel: _('Description'),
        allowBlank: false,
        width: 600,
        value: 'For professional services rendered (' + ((this.sumMin - this.sumMin % 60) / 60) + 'h ' + (this.sumMin % 60) + 'min' + ')'
    });

    this.ta = new Ext.form.ComboBox({
        fieldLabel: _('Currency'),
        readOnly: true,
        store: new Ext.data.SimpleStore({
            fields: ['ta_id', 'ta_name'],
            data: companyTA
        }),
        valueField: 'ta_id',
        displayField: 'ta_name',
        editable: false,
        typeAhead: false,
        mode: 'local',
        triggerAction: 'all',
        emptyText: _('Please select...'),
        allowBlank: false,
        selectOnFocus: false,
        width: 300,
        listWidth: 300,
        value: companyTA.length===1 ? companyTA[0][0] : null
    });

    this.form = new Ext.FormPanel({
        labelWidth: 80,
        bodyStyle: 'padding: 7px;',
        items: [this.textlabel, this.ta, this.date, this.due, this.feeGST, this.desc]
    });

    ClientTrackerMarkDialog.superclass.constructor.call(this, {
        title: '<i class="las la-check"></i>' + _('Mark as Billed'),
        closeAction: 'close',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        items: this.form,
        buttons: [
            {
                text: _('No'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: _('Yes'),
                cls: 'orange-btn',
                handler: this.markAsBilled.createDelegate(this, [false])
            }
        ]
    });
};

Ext.extend(ClientTrackerMarkDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    processDialog: function () {
        this.show();
        this.center();
    },

    markAsBilled: function () {
        if (this.form.getForm().isValid())
        {
            var win = this;
            win.getEl().mask(_('Processing...'));

            Ext.Ajax.request({
                url: baseUrl + '/clients/time-tracker/mark-billed/',
                params: {
                    track_ids: Ext.encode(win.arrTrackIds),
                    due:       this.due.getValue().substr(1),
                    desc:      this.desc.getValue(),
                    date:      this.date.getRawValue(),
                    ta_id:     this.ta.getValue(),
                    gst_province_id: Ext.encode(this.feeGST.getValue()),
                    client_id: this.viewer.viewer.clientId
                },
                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        var accountingPanel = Ext.getCmp('accounting_invoices_panel_' + win.viewer.viewer.clientId);
                        if (accountingPanel) {
                            accountingPanel.reloadClientAccounting();
                        }

                        Ext.simpleConfirmation.msg(_('Info'), _('Successfully marked as Billed'));

                        win.viewer.store.reload();

                        win.closeDialog();
                    } else {
                        win.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.msg);
                    }
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(_('Internal error. Please, try again later'));
                }
            });
        }
    }
});