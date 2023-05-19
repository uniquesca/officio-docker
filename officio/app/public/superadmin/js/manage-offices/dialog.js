var ManageOfficeDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    // Use this separator to distinguish offices in the combo
    this.foldersSeparator = ';';

    var help = String.format(
        "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
        _('You can add a Shared Worspace sub folder through the Documents section of the Clients module.')
    );

    this.thisForm = new Ext.form.FormPanel({
        labelAlign: 'top',

        items: [
            {
                xtype: 'hidden',
                name:  'division_id',
                value: config.params.division.division_id
            }, {
                id:         'division_name',
                fieldLabel: _('Name'),
                labelSeparator: '',
                name:       'name',
                xtype:      'textfield',
                value:      config.params.division.name,
                allowBlank: false,
                width:      450
            }, {
                xtype:      'lovcombo',
                fieldLabel: _('Assign to roles'),
                labelSeparator: '',
                name:       'assign_to_roles',
                hiddenName: 'assign_to_roles',
                width:      450,

                store:         {
                    xtype:  'store',
                    reader: new Ext.data.JsonReader({
                        id: 'role_id'
                    }, [
                        {name: 'role_id'},
                        {name: 'role_type'},
                        {name: 'role_name'}
                    ]),
                    data:   arrRoles
                },
                triggerAction: 'all',
                valueField:    'role_id',
                displayField:  'role_name',
                mode:          'local',
                hidden:        !empty(config.params.division.division_id),
                useSelectAll:  false,

                // Automatically select/check all admin roles
                value: this.getAdminRolesIds()
            }, {
                xtype:      'lovcombo',
                fieldLabel: _('Access to Shared Worskpace Sub Folders') + help,
                labelSeparator: '',
                name:       'folders_access',
                hiddenName: 'folders_access',
                separator:  this.foldersSeparator,
                width:      450,

                store: {
                    xtype:  'store',
                    reader: new Ext.data.JsonReader({
                        id: 'folder_id'
                    }, [
                        {name: 'folder_id'},
                        {name: 'folder_name'}
                    ]),
                    data:   arrFolders
                },

                triggerAction: 'all',
                valueField:    'folder_id',
                displayField:  'folder_name',
                mode:          'local',
                useSelectAll:  false,
                value: this.getFoldersHasAccessTo(config.params.division.folders_no_access)
            }, {
                xtype:     'checkbox',
                boxLabel:  _('Submit cases to this ') + officeLabel,
                name:      'access_assign_to',
                hideLabel: true,
                hidden:    !booAuthorizedAgentsManagementEnabled,
                checked:   config.params.division.access_assign_to === 'Y'
            }, {
                xtype:     'checkbox',
                boxLabel:  _('Return cases to this ') + officeLabel,
                name:      'access_owner_can_edit',
                hideLabel: true,
                hidden:    !booAuthorizedAgentsManagementEnabled,
                checked:   config.params.division.access_owner_can_edit === 'Y'
            }, {
                xtype:     'checkbox',
                boxLabel:  String.format(_("Permanent {0} <i class='las la-info-circle' ext:qtip='Once sent to this {1}, a case will always stay in that {1}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>"), officeLabel, officeLabel.replaceAll("'", "&#39;")),
                name:      'access_permanent',
                hideLabel: true,
                hidden:    !booAuthorizedAgentsManagementEnabled,
                checked:   config.params.division.access_permanent === 'Y'
            }
        ]
    });

    ManageOfficeDialog.superclass.constructor.call(this, {
        y: 10,
        title: empty(config.params.division.division_id) ? '<i class="las la-plus"></i>' + _('New') + ' ' + officeLabel : '<i class="las la-edit"></i>' + _('Edit') + ' ' + officeLabel,
        modal: true,
        autoHeight: true,
        autoWidth: true,
        layout: 'form',
        items: this.thisForm,

        buttons: [
            {
                text:    _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text:    empty(config.params.division.division_id) ? _('Create') : _('Save'),
                cls:     'orange-btn',
                handler: this.saveSettings.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ManageOfficeDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.center();
        this.syncShadow();

        Ext.getCmp('division_name').clearInvalid();
    },

    saveSettings: function () {
        var thisDialog = this;

        if (thisDialog.thisForm.getForm().isValid()) {
            var name = Ext.getCmp('division_name').getValue();
            if (name.indexOf(',') !== -1) {
                Ext.simpleConfirmation.error('A comma is not allowed in the name.');
                return;
            }

            thisDialog.getEl().mask(_('Saving...'));

            thisDialog.thisForm.getForm().submit({
                url: baseUrl + '/manage-offices/save-record',

                success: function (form, action) {
                    thisDialog.getEl().mask('Done!');
                    setTimeout(function () {
                        thisDialog.close();

                        if (action && action.result) {
                            thisDialog.owner.reloadGridAndSelectOffice(action.result.division_id);
                        }
                    }, 750);
                },

                failure: function (form, action) {
                    switch (action.failureType) {
                        case Ext.form.Action.CLIENT_INVALID:
                            Ext.simpleConfirmation.error('Form fields may not be submitted with invalid values');
                            break;

                        case Ext.form.Action.CONNECT_FAILURE:
                            Ext.simpleConfirmation.error('Cannot save info');
                            break;

                        case Ext.form.Action.SERVER_INVALID:
                        default:
                            Ext.simpleConfirmation.error(action.result.message);
                            break;
                    }

                    thisDialog.getEl().unmask();
                }
            });
        }
    },

    getAdminRolesIds: function () {
        var arrAdminRoles = [];

        for (var i = 0; i < arrRoles.length; i++) {
            if (arrRoles[i]['role_type'] === 'admin') {
                arrAdminRoles.push(arrRoles[i]['role_id'])
            }
        }

        return arrAdminRoles;
    },

    getFoldersHasAccessTo: function (arrNoAccessFolders) {
        var arrHasAccessToFolders = [];
        for (var i = 0; i < arrFolders.length; i++) {
            var booFound = false;
            for (var j = 0; j < arrNoAccessFolders.length; j++) {
                if (arrFolders[i]['folder_name'] === arrNoAccessFolders[j]) {
                    booFound = true;
                    break;
                }
            }

            if (!booFound) {
                arrHasAccessToFolders.push(arrFolders[i]['folder_id']);
            }
        }

        return arrHasAccessToFolders.join(this.foldersSeparator);
    }
});