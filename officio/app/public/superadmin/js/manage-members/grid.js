var ManageMembersGrid = function (config, parentPanel) {
    var thisGrid = this;
    Ext.apply(this, config);

    this.parentPanel = parentPanel;

    var memberRecord = Ext.data.Record.create([
        {
            name: 'member_id',
            type: 'int'
        }, {
            name: 'company_id',
            type: 'int'
        }, {
            name: 'member_first_name',
            type: 'string'
        }, {
            name: 'member_last_name',
            type: 'string'
        }, {
            name: 'member_username',
            type: 'string'
        }, {
            name: 'member_role',
            type: 'string'
        }, {
            name: 'member_email',
            type: 'string'
        }, {
            name: 'member_office',
            type: 'string'
        }, {
            name: 'member_status',
            type: 'string'
        }, {
            name: 'member_created_on',
            type: 'date',
            dateFormat: Date.patterns.ISO8601Long
        }
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-members/list',
        remoteSort: true,

        sortInfo: {
            field: 'member_created_on',
            direction: 'DESC'
        },
        autoLoad: true,

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader({
            id:            'member_id',
            root:          'rows',
            totalProperty: 'totalCount'
        }, memberRecord)
    });
    this.store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));

    this.bbar = new Ext.PagingToolbar({
        pageSize:    arrSettings['membersPerPageCount'],
        store:       this.store,
        displayInfo: true,
        displayMsg:  _('Records {0} - {1} of {2}'),
        emptyMsg:    _('No records to display')
    });

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New User'),
            cls: 'main-btn',
            hidden: !arrSettings['access']['add'],
            handler: this.addMember.createDelegate(this)
        }, {
            text:     '<i class="las la-edit"></i>' + _('Edit User'),
            ref:      '../memberRecordsEditBtn',
            disabled: true,
            hidden:   !arrSettings['access']['view'],
            handler:  this.onDblClick.createDelegate(this)
        }, {
            text:     '<i class="las la-trash"></i>' + _('Delete User'),
            ref:      '../memberRecordsDeleteBtn',
            disabled: true,
            hidden:   !arrSettings['access']['delete'],
            handler:  this.deleteMember.createDelegate(this)
        }, '-', {
            text:     '<i class="las la-exchange-alt"></i>' + _('Change Status'),
            ref:      '../memberRecordsChangeStatusBtn',
            disabled: true,
            hidden:   !arrSettings['access']['edit'],
            menu:     {
                items: [
                    {
                        text:    _('Active'),
                        handler: this.changeMemberStatus.createDelegate(this, [true])
                    }, {
                        text:    _('Inactive'),
                        handler: this.changeMemberStatus.createDelegate(this, [false])
                    }
                ]
            }
        }, '->', {
            text:    '<i class="las la-undo-alt"></i>',
            scope:   this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.sm = new Ext.grid.CheckboxSelectionModel();

    var autoExpandColumnId = Ext.id();

    var arrColumns = [
        this.sm, {
            header:    _('Id'),
            width:     40,
            dataIndex: 'member_id'
        }, {
            header:    arrSettings.first_name_label,
            width:     70,
            dataIndex: 'member_first_name',
            renderer:  this.formatNameLink.createDelegate(this)
        }, {
            header:    arrSettings.last_name_label,
            width:     70,
            dataIndex: 'member_last_name',
            renderer:  this.formatNameLink.createDelegate(this)
        }, {
            id:        autoExpandColumnId,
            header:    _('Username'),
            width:     120,
            dataIndex: 'member_username'
        }, {
            header:    _('Role'),
            sortable:  false,
            width:     75,
            dataIndex: 'member_role'
        }, {
            header:    _('Email'),
            width:     75,
            renderer:  this.formatEmail.createDelegate(this),
            dataIndex: 'member_email'
        }, {
            header:    _(arrSettings.officeLabel),
            sortable:  false,
            width:     75,
            dataIndex: 'member_office'
        }, {
            header:    _('Status'),
            width:     50,
            align:     'center',
            renderer:  this.formatStatus.createDelegate(this),
            dataIndex: 'member_status'
        }, {
            header:    _('Reg Date'),
            width:     75,
            renderer:  Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
            dataIndex: 'member_created_on'
        }
    ];

    var arrActions = [];
    if (arrSettings['access']['login-as-member']) {
        arrActions.push({
            iconCls: 'las la-sign-in-alt',
            style: 'padding-left: 45%;',
            tooltip: _('Click to login as this user')
        });
    }

    var actions = new Ext.ux.grid.RowActions({
        header:        _('Actions'),
        width:         50,
        autoWidth:     false,
        keepSelection: true,

        actions: arrActions,

        callbacks: {
            'las la-sign-in-alt': this.loginAsMember.createDelegate(this)
        }
    });

    arrColumns.push(actions);


    this.cm = new Ext.grid.ColumnModel({
        columns:         arrColumns,
        defaultSortable: true
    });

    ManageMembersGrid.superclass.constructor.call(this, {
        height: 560,
        loadMask: true,
        stateful: true,
        stateId: 'manage-users-grid',
        cls: 'extjs-grid',
        plugins: [actions],

        autoExpandColumn: autoExpandColumnId,
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText:      _('No records found.'),
            forceFit:       true
        }
    });

    this.on('rowcontextmenu', this.showContextMenu, this);
    this.on('rowdblclick', this.onDblClick, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(ManageMembersGrid, Ext.grid.GridPanel, {
    formatStatus: function (status) {
        var strResult = status;
        switch (status) {
            case 0 :
            case '0':
            case 'inactive' :
                strResult = '<span style="color: red">' + _('Inactive') + '</span>';
                break;

            case 'suspended' :
                strResult = '<span style="color: gray">' + _('Suspended') + '</span>';
                break;

            case 1:
            case '1':
            case 'active' :
                strResult = '<span style="color: green">' + _('Active') + '</span>';
                break;

            default:
                break;
        }
        return strResult;
    },

    formatEmail: function (val) {
        return String.format('<a href="mailto:{0}" class="blulinkun" title="Click to send email to {0}">{0}</a>', val);
    },

    formatNameLink: function (val, a, record) {
        var formattedName = '';

        if (arrSettings['access']['view']) {
            var url = String.format('{0}/manage-members/edit?member_id={1}', baseUrl, record.data.member_id);

            formattedName = String.format('<a href="{0}" {1} class="blulinkun" title="Click to open user\'s profile">{2}</a>', url, arrSettings['booEditInNewTab'] ?
                'target="_blank"' : '', val)
        } else {
            formattedName = val;
        }

        return formattedName;
    },

    applyParams: function (store, options) {
        options.params = options.params || {};

        var oFilterParams = this.parentPanel.membersFilterForm.getAllFilterValues();

        // Encode all what we pass to the server
        for (var property in oFilterParams) {
            if (oFilterParams.hasOwnProperty(property)) {
                oFilterParams[property] = Ext.encode(oFilterParams[property]);
            }
        }

        // Apply filter variables
        var params = {
            dir:        store.sortInfo.direction,
            sort:       store.sortInfo.field,
            company_id: arrSettings.companyId
        };
        Ext.apply(params, oFilterParams);

        Ext.apply(options.params, params);
    },

    updateToolbarButtons: function () {
        var sel = this.view.grid.getSelectionModel().getSelections();

        this.memberRecordsChangeStatusBtn.setDisabled(sel.length === 0);
        this.memberRecordsDeleteBtn.setDisabled(sel.length === 0);
        this.memberRecordsEditBtn.setDisabled(sel.length !== 1);
    },

    showContextMenu: function (grid, rowIndex, e) {
        grid.getSelectionModel().selectRow(rowIndex);

        var selItem   = grid.getSelectionModel().getSelected();
        var selStatus = selItem.data.member_status;

        this.menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            items: [
                {
                    text:    '<i class="las la-plus"></i>' + _('Add User'),
                    hidden:  !arrSettings['access']['add'],
                    handler: this.addMember.createDelegate(this)
                }, {
                    text:    '<i class="las la-edit"></i>' + _('Edit User'),
                    hidden:  !arrSettings['access']['view'],
                    handler: this.onDblClick.createDelegate(this)
                }, {
                    text:    '<i class="las la-trash"></i>' + _('Delete User'),
                    scope:   this,
                    hidden:  !arrSettings['access']['delete'],
                    handler: grid.deleteMember.createDelegate(grid)
                }, {
                    text:    '<i class="las la-capsules"></i>' + _('Change status'),
                    hidden:  !arrSettings['access']['edit'],
                    menu:    {
                        items: [
                            {
                                text:         _('Active'),
                                checked:      !empty(selStatus),
                                group:        'theme',
                                checkHandler: function (item, checked) {
                                    if (checked) {
                                        grid.changeMemberStatus(true);
                                    }
                                }
                            }, {
                                text:         _('Inactive'),
                                checked:      empty(selStatus),
                                group:        'theme',
                                checkHandler: function (item, checked) {
                                    if (checked) {
                                        grid.changeMemberStatus(false);
                                    }
                                }
                            }
                        ]
                    }
                }, '-', {
                    text:    '<i class="las la-sign-in-alt"></i>' + _('Login as this user'),
                    hidden:  !arrSettings['access']['login-as-member'],
                    handler: grid.loginAsMember.createDelegate(grid)
                }, '-', {
                    text:    '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    cls:     'x-btn-text-icon',
                    scope:   this,
                    handler: function () {
                        grid.store.reload();
                    }
                }
            ]
        });

        // Show menu
        e.stopEvent();
        this.menu.showAt(e.getXY());
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        this.editMember(this, record);
    },

    addMember: function () {
        if (arrSettings['access']['add']) {
            var msg;
            if (arrSettings['arrCompanyDetails']['booCanAddUsersOverLimit']) {
                if (arrSettings['arrCompanyDetails']['currentUsersCount'] < arrSettings['arrCompanyDetails']['usersLimitInPackage']) {
                    Ext.getBody().mask(_('Loading...'));
                    location.href = baseUrl + '/manage-members/add';
                } else {
                    msg = String.format(
                        _('Your account currently includes {0} user licenses. Adding another user will cost {1}/month.<br><br>If some of your existing licenses are not used, you can deactivate them and they will not be counted towards your available licenses.<br><br>For bulk purchases, please call us{2}: {3}.'),
                        arrSettings['arrCompanyDetails']['usersLimitInPackage'],
                        '$' + arrSettings['arrCompanyDetails']['pricePerUserLicense'],
                        site_version === 'australia' ? '' : _('toll-free'),
                        site_company_phone
                    );

                    Ext.Msg.show({
                        title:   _('Warning'),
                        msg:     msg,
                        buttons: Ext.Msg.OKCANCEL,
                        icon:    Ext.MessageBox.WARNING,
                        fn:      function (btn) {
                            if (btn === 'ok') {
                                Ext.getBody().mask(_('Loading...'));
                                location.href = baseUrl + '/manage-members/add';
                            }
                        }
                    });
                }
            } else {
                msg = _('Please contact ' + site_company_phone + ' for assistance with your subscription package.');
                Ext.Msg.show({
                    title:   _('Warning'),
                    msg:     msg,
                    buttons: Ext.Msg.OK,
                    icon:    Ext.MessageBox.WARNING
                });
            }
        }
    },

    editMember: function (grid, record) {
        if (arrSettings['access']['view']) {
            var url = String.format('{0}/manage-members/edit?member_id={1}', baseUrl, record.data.member_id);

            if (arrSettings['booEditInNewTab']) {
                window.open(url);
            } else {
                Ext.getBody().mask(_('Loading...'));

                location.href = url;
            }
        }
    },

    loginAsMember: function (grid, record) {
        if (arrSettings['access']['login-as-member']) {
            Ext.getBody().mask(_('Processing...'));

            var url = String.format('{0}/manage-company-as-admin/{2}/{1}', baseUrl, record.data.member_id, record.data.company_id);

            top.window.open(url, '_self');
        }
    },

    changeMemberStatus: function (booActivate) {
        if (arrSettings['access']['edit']) {
            var thisGrid = this;
            Ext.getBody().mask(_('Processing...'));

            var ids = [], arrSelected = thisGrid.getSelectionModel().getSelections();

            for (var i = 0; i < arrSelected.length; i++) {
                ids.push(arrSelected[i].data.member_id);
            }

            Ext.Ajax.request({
                url:    baseUrl + '/manage-members/change-status',
                params: {
                    arrMemberIds: Ext.encode(ids),
                    booActivate:  Ext.encode(booActivate)
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        thisGrid.store.reload();
                        Ext.simpleConfirmation.msg(_('Info'), resultData.message, 1500);
                    } else {
                        Ext.simpleConfirmation.error(resultData.message);
                    }

                    Ext.getBody().unmask();
                },

                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(_('Cannot change the status. Please try again later.'));
                }
            });
        }
    },

    deleteMember: function () {
        var thisGrid    = this;
        var arrSelected = thisGrid.getSelectionModel().getSelections();
        var msg         = String.format(_('Are you sure want to delete {0}?'), arrSelected.length === 1 ? arrSelected[0].data.member_username :
            arrSelected.length + _(' selected users'));

        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                var ids = [];

                for (var i = 0; i < arrSelected.length; i++) {
                    ids.push(arrSelected[i].data.member_id);
                }

                Ext.Ajax.request({
                    url:    baseUrl + '/manage-members/delete',
                    params: {
                        arrMemberIds: Ext.encode(ids)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.store.reload();
                            Ext.simpleConfirmation.msg(_('Info'), resultData.message, 1500);
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(_('Cannot delete the record(s). Please try again later.'));
                    }
                });
            }
        });
    }
});