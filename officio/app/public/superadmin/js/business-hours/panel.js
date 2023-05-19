var BusinessSchedulePanel = function (config) {
    Ext.apply(this, config);

    var arrItems = [];

    if (arrBusinessHoursAccess && arrBusinessHoursAccess['view-workdays']) {
        this.workdaysPanel = new BusinessScheduleWorkdaysPanel({
            member_id:  this.member_id,
            company_id: this.company_id
        });

        arrItems.push({
            title:      _('Workdays'),
            xtype:      'fieldset',
            autoHeight: true,

            items: this.workdaysPanel
        });
    }

    // Spacer
    if (arrBusinessHoursAccess && arrBusinessHoursAccess['view-workdays'] && arrBusinessHoursAccess['view-holidays']) {
        arrItems.push({
            html: '&nbsp;'
        });
    }

    if (arrBusinessHoursAccess && arrBusinessHoursAccess['view-holidays']) {
        this.holidaysGrid = new BusinessScheduleHolidaysGrid({
            member_id:  this.member_id,
            company_id: this.company_id,
            style:      'margin-top: 5px',
            region:     'center'
        });

        arrItems.push({
            title:      _('Holidays, non-workdays and special days (Limit access on these dates.)'),
            xtype:      'fieldset',
            autoHeight: true,
            items:      this.holidaysGrid
        });
    }

    if (!arrItems.length) {
        arrItems.push({
            xtype: 'panel',
            html:  '<div style="color: red;">' + _('Insufficient access rights to manage workdays or holidays.') + '</div>'
        });
    }

    BusinessSchedulePanel.superclass.constructor.call(this, {
        frame:      false,
        autoWidth:  true,
        autoHeight: true,

        bodyStyle: {
            background: '#ffffff',
            padding:    '7px'
        },

        items: arrItems
    });
};

Ext.extend(BusinessSchedulePanel, Ext.Panel, {});