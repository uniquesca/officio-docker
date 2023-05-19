var BusinessScheduleHolidaysDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var booAddAction = empty(config.holidayRecord.data.holiday_id);

    this.mainFormPanel = new Ext.FormPanel({
        labelWidth: 100,
        bodyStyle:  'padding:5px',
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name:  'holiday_id'
            },
            {
                xtype:      'textfield',
                name:       'holiday_name',
                fieldLabel: _('Name'),
                emptyText:  _('Please type the name or description'),
                allowBlank: false,
                width:      323
            },
            {
                xtype: 'label',
                style: 'font-size: 12px',
                text:  _('Date:')
            },
            {
                xtype:  'container',
                layout: 'column',

                items: [
                    {
                        xtype:      'datefield',
                        name:       'holiday_date_from',
                        allowBlank: false,
                        format:     dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        emptyText:  _('From...'),
                        width:      160
                    },
                    {
                        html:  '&nbsp;',
                        width: 20
                    },
                    {
                        xtype:      'datefield',
                        name:       'holiday_date_to',
                        allowBlank: true,
                        emptyText:  _('To (optional)...'),
                        format:     dateFormatFull,
                        altFormats: dateFormatFull + '|' + dateFormatShort,
                        width:      160
                    }
                ]
            }
        ]
    });


    BusinessScheduleHolidaysDialog.superclass.constructor.call(this, {
        title:       booAddAction ? '<i class="las la-plus"></i>' + _('Add Holiday') : '<i class="las la-edit"></i>' + _('Edit Holiday'),
        modal:       true,
        autoWidth:   true,
        autoHeight:  true,
        resizable:   false,
        items:       this.mainFormPanel,
        buttonAlign: 'center',

        buttons: [
            {
                text:    _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text: '<i class="las la-save"></i>' + _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(BusinessScheduleHolidaysDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow()
    },

    loadSettings: function () {
        this.mainFormPanel.getForm().loadRecord(this.holidayRecord);
    },

    saveChanges: function () {
        var thisDialog = this;
        if (!thisDialog.mainFormPanel.getForm().isValid()) {
            return false;
        }

        thisDialog.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: empty(thisDialog.holidayRecord.data.holiday_id) ? baseUrl + '/manage-business-hours/holidays-add' : baseUrl + '/manage-business-hours/holidays-edit',

            params: {
                member_id:         thisDialog.member_id,
                company_id:        thisDialog.company_id,
                holiday_id:        thisDialog.mainFormPanel.find('name', 'holiday_id')[0].getValue(),
                holiday_name:      thisDialog.mainFormPanel.find('name', 'holiday_name')[0].getValue(),
                holiday_date_from: Ext.util.Format.date(thisDialog.mainFormPanel.find('name', 'holiday_date_from')[0].getValue(), 'Y-m-d'),
                holiday_date_to:   Ext.util.Format.date(thisDialog.mainFormPanel.find('name', 'holiday_date_to')[0].getValue(), 'Y-m-d')
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisDialog.owner.store.reload();
                    Ext.simpleConfirmation.msg(_('Info'), resultData.message);
                    thisDialog.close();
                } else {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                thisDialog.getEl().unmask();

                var msg = action && action.result && action.result.message ? action.result.message : _('Information cannot be saved. Please try again later.');
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});