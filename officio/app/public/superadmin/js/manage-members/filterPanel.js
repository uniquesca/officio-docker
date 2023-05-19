var ManageMembersFilterPanel = function (config, parentPanel) {
    var filterForm = this;
    Ext.apply(this, config);

    this.parentPanel = parentPanel;

    var oDefaultSettings = filterForm.loadDefaultSettings();

    ManageMembersFilterPanel.superclass.constructor.call(this, {
        collapsible: true,
        collapsed:   false,
        initialSize: 270,
        width:       270,
        split:       true,

        labelAlign:  'top',
        buttonAlign: 'center',
        cls:         'filter-panel',

        bodyStyle: {
            background: '#ffffff',
            padding:    '7px'
        },

        items: [
            {
                id:        'filter_hide_inactive_users',
                xtype:     'checkbox',
                hideLabel: true,
                checked:   oDefaultSettings.filter_hide_inactive_users,
                boxLabel:  _('Hide inactive users'),
                listeners: {
                    'check': filterForm.rememberSettingsAndRunSearch.createDelegate(this)
                }
            }, {
                id:         'filter_email',
                fieldLabel: _('Email'),
                value:      oDefaultSettings.filter_email,
                xtype:      'textfield',
                width:      250
            }, {
                id:         'filter_first_name',
                fieldLabel: arrSettings.first_name_label,
                xtype:      'textfield',
                value:      oDefaultSettings.filter_first_name,
                width:      250
            }, {
                id:         'filter_last_name',
                fieldLabel: arrSettings.last_name_label,
                xtype:      'textfield',
                value:      oDefaultSettings.filter_last_name,
                width:      250
            }, {
                id:         'filter_username',
                fieldLabel: _('Username'),
                xtype:      'textfield',
                value:      oDefaultSettings.filter_username,
                width:      250
            }, {
                id:         'filter_role',
                xtype:      'lovcombo',
                fieldLabel: _('Role'),
                hidden: typeof arrSettings.companyId === "undefined" || !parseInt(arrSettings.companyId, 10),

                store: new Ext.data.Store({
                    data:   arrSettings.arrRoles,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                        {name: 'role_id'}, {name: 'role_name'}
                    ]))
                }),

                mode:           'local',
                valueField:     'role_id',
                displayField:   'role_name',
                editable:       true,
                triggerAction:  'all',
                typeAhead:      true,
                forceSelection: true,
                selectOnFocus:  true,
                emptyText:      _('All roles...'),
                value:          oDefaultSettings.filter_role,
                width:          250,
                listWidth:      250
            }, {
                id:         'filter_division',
                xtype:      'combo',
                fieldLabel: _(arrSettings.officeLabel),
                hidden: typeof arrSettings.companyId === "undefined" || !parseInt(arrSettings.companyId, 10),

                store: new Ext.data.Store({
                    data:   arrSettings.arrDivisions,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                        {name: 'division_name'}
                    ]))
                }),

                mode:           'local',
                valueField:     'division_name',
                displayField:   'division_name',
                editable:       true,
                triggerAction:  'all',
                typeAhead:      true,
                forceSelection: false,
                selectOnFocus:  true,
                emptyText:      _('Please type or select from the list...'),
                value:          oDefaultSettings.filter_division,
                width:          250,
                listWidth:      250
            }
        ],

        buttons: [
            {
                text:    _('Reset'),
                handler: filterForm.resetFilterForm.createDelegate(this)
            },
            {
                text:    _('Apply Filter'),
                cls: 'orange-btn',
                handler: filterForm.rememberSettingsAndRunSearch.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ManageMembersFilterPanel, Ext.form.FormPanel, {
    getDefaultSettings: function () {
        return {
            'filter_hide_inactive_users': false,
            'filter_email':               '',
            'filter_username':            '',
            'filter_first_name':          '',
            'filter_last_name':           '',
            'filter_role':                '',
            'filter_division':            ''
        };
    },

    loadDefaultSettings: function () {
        return Ext.state.Manager.get('ManageMembersGridSettings' + arrSettings.companyId) || this.getDefaultSettings();
    },

    saveDefaultSettings: function (oNewSettings) {
        Ext.state.Manager.set('ManageMembersGridSettings' + arrSettings.companyId, oNewSettings);
    },

    getAllFilterValues: function () {
        return {
            'filter_hide_inactive_users': Ext.getCmp('filter_hide_inactive_users').getValue(),
            'filter_email':               Ext.getCmp('filter_email').getValue(),
            'filter_first_name':          Ext.getCmp('filter_first_name').getValue(),
            'filter_last_name':           Ext.getCmp('filter_last_name').getValue(),
            'filter_username':            Ext.getCmp('filter_username').getValue(),
            'filter_role':                Ext.getCmp('filter_role').getValue(),
            'filter_division':            Ext.getCmp('filter_division').getValue()
        };
    },

    rememberSettingsAndRunSearch: function () {
        if (this.getForm().isValid()) {
            this.parentPanel.membersGrid.getStore().reload();

            this.saveDefaultSettings(this.getAllFilterValues());

        }
    },

    resetFilterForm: function () {
        var MemberRecord = Ext.data.Record.create([
            {name: 'filter_hide_inactive_users'}, {name: 'filter_email'}, {name: 'filter_first_name'}, {name: 'filter_last_name'}, {name: 'filter_username'}, {name: 'filter_role'}, {name: 'filter_division'}
        ]);

        this.getForm().loadRecord(new MemberRecord(this.getDefaultSettings()));

        this.rememberSettingsAndRunSearch();
    }
});