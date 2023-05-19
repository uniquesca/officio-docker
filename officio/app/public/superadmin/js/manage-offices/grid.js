var ManageOfficesGrid = function () {
    var thisGrid = this;

    // Init basic properties
    this.store = new Ext.data.Store({
        url:        baseUrl + '/manage-offices/get-list',
        method:     'POST',
        autoLoad:   true,
        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount',
            idProperty:    'division_id',

            fields: [
                {
                    name: 'division_id',
                    type: 'int'
                }, {
                    name: 'name',
                    type: 'string'
                }, {
                    name: 'access_owner_can_edit',
                    type: 'string'
                }, {
                    name: 'access_assign_to',
                    type: 'string'
                }, {
                    name: 'access_permanent',
                    type: 'string'
                }, {
                    name: 'order',
                    type: 'int'
                }, {
                    name: 'folders_no_access'
                }
            ]
        }),

        sortInfo: {
            field:     'order',
            direction: 'DESC'
        }
    });

    var action = new Ext.ux.grid.RowActions({
        header:        _('Order'),
        keepSelection: true,

        actions: [
            {
                iconCls: 'move_option_up',
                tooltip: _('Move Up')
            }, {
                iconCls: 'move_option_down',
                tooltip: _('Move Down')
            }
        ],

        callbacks: {
            'move_option_up':   this.moveOption.createDelegate(this),
            'move_option_down': this.moveOption.createDelegate(this)
        }
    });

    var expandCol = Ext.id();

    this.selModel = new Ext.grid.RowSelectionModel({
        singleSelect: true
    });

    this.columns = [
        {
            id:        expandCol,
            header:    _('Name'),
            sortable:  false,
            dataIndex: 'name',
            width:     150
        }, {
            header: _('Shared Folder Access'),
            sortable: false,
            dataIndex: 'folders_no_access',
            renderer: this.formatSharedFolderAccess.createDelegate(this),
            width: 50
        }
    ];

    if (booAuthorizedAgentsManagementEnabled) {
        this.columns.push({
            header:    _('Return cases to this ') + officeLabel,
            sortable:  false,
            align:     'center',
            dataIndex: 'access_owner_can_edit',
            renderer:  this.formatYesNo.createDelegate(this),
            width:     50
        });

        this.columns.push({
            header:    _('Submit cases to this ') + officeLabel,
            sortable:  false,
            align:     'center',
            dataIndex: 'access_assign_to',
            renderer:  this.formatYesNo.createDelegate(this),
            width:     50
        });

        this.columns.push({
            header:    _('Permanent ') + officeLabel,
            sortable:  false,
            align:     'center',
            dataIndex: 'access_permanent',
            renderer:  this.formatYesNo.createDelegate(this),
            width:     50
        });
    }

    this.columns.push(action);

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New') + ' ' + officeLabel,
            cls: 'main-btn',
            ref: '../createOfficeBtn',
            handler: this.addOffice.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit') + ' ' + officeLabel,
            disabled: true,
            ref: '../editOfficeBtn',
            handler: this.editOffice.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete') + ' ' + officeLabel,
            disabled: true,
            ref: '../deleteOfficeBtn',
            handler: this.deleteOffice.createDelegate(this)
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            tooltip: _('Reload the list'),
            handler: this.refreshList.createDelegate(this)
        }
    ];


    this.bbar = new Ext.PagingToolbar({
        pageSize:    25,
        store:       this.store,
        displayInfo: true,
        emptyMsg:    _('No records to display')
    });


    ManageOfficesGrid.superclass.constructor.call(this, {
        autoWidth:        true,
        height:           getSuperadminPanelHeight(),
        autoExpandColumn: expandCol,
        stripeRows:       true,
        cls:              'extjs-grid',
        renderTo:         'offices_list_container',
        plugins:          [action],
        loadMask:         true,

        viewConfig: {
            forceFit:  true,
            emptyText: _('No records were found.')
        }
    });

    this.getSelectionModel().on('selectionchange', this.onSelectionChange.createDelegate(this));
    this.on('rowdblclick', this.editOffice.createDelegate(this), this);
    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.on('contextmenu', function (e) {
        e.preventDefault();
    }, this);
};

Ext.extend(ManageOfficesGrid, Ext.grid.GridPanel, {
    onSelectionChange: function (sm) {
        var selRecordsCount = sm.getCount();
        var booOneSelected  = selRecordsCount === 1;

        this.editOfficeBtn.setDisabled(!booOneSelected);
        this.deleteOfficeBtn.setDisabled(!booOneSelected);
    },

    formatSharedFolderAccess: function (arrNoAccessFolders) {
        var strResult = _('All');

        if (arrNoAccessFolders.length) {
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
                    arrHasAccessToFolders.push(arrFolders[i]['folder_name']);
                }
            }

            if (arrHasAccessToFolders.length !== arrFolders.length) {
                strResult = arrHasAccessToFolders.join(', ');
            }
        }

        return strResult;
    },

    formatYesNo: function (status) {
        var strResult = status;
        switch (status) {
            case 'N' :
                strResult = _('No');
                break;

            case 'Y' :
                strResult = '<span style="color: orange">' + _('Yes') + '</span>';
                break;

            default:
                strResult = '<span style="color: red">' + _('Unknown') + '</span>';
                break;
        }

        return strResult;
    },

    addOffice: function () {
        var wnd = new ManageOfficeDialog({
            params: {
                division: {
                    division_id:           0,
                    name:                  '',
                    access_owner_can_edit: 'N',
                    access_assign_to:      'N',
                    access_permanent:      'N',
                    folders_no_access:     []
                }
            }
        }, this);
        wnd.showDialog();
    },

    editOffice: function () {
        var record = this.selModel.getSelected();

        var wnd = new ManageOfficeDialog({
            params: {
                division: {
                    division_id:           record.data.division_id,
                    name:                  record.data.name,
                    access_owner_can_edit: record.data.access_owner_can_edit,
                    access_assign_to:      record.data.access_assign_to,
                    access_permanent:      record.data.access_permanent,
                    folders_no_access: record.data.folders_no_access
                }
            }
        }, this);
        wnd.showDialog();
    },

    setFocusToOffice: function (division_id) {
        var index = this.getStore().find('division_id', division_id);
        if (index >= 0) {
            // Select found division
            this.getSelectionModel().selectRow(index);

            // Scroll to just selected division - so it will be visible
            var rowEl = this.getView().getRow(index);
            rowEl.scrollIntoView(this.getGridEl(), false);
        }
    },

    reloadGridAndSelectOffice: function (division_id) {
        var thisGrid = this;
        thisGrid.getStore().on('load', this.setFocusToOffice.createDelegate(thisGrid, [division_id]), this, {single: true});
        thisGrid.getStore().load();

        if (typeof window.top.refreshSettings === 'function') {
            window.top.refreshSettings('office');
        }
    },

    moveOption: function (grid, record, action, row) {
        // Don't try to move the first/last record
        if ((action === 'move_option_up' && row === 0) || (action === 'move_option_down' && row === grid.getStore().getCount() - 1)) {
            return;
        }

        var thisGrid = this;

        thisGrid.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/manage-offices/move-record',

            params: {
                division_id:  Ext.encode(record.data.division_id),
                direction_up: Ext.encode(action === 'move_option_up')
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    thisGrid.reloadGridAndSelectOffice(record.data.division_id);
                } else {
                    Ext.simpleConfirmation.error(resultData.message);
                }
                thisGrid.getEl().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                thisGrid.getEl().unmask();
            }
        });
    },

    deleteOffice: function () {
        var thisGrid = this;
        var record = thisGrid.selModel.getSelected();
        var question = String.format(
            _('Are you sure want to delete "<i>{0}</i>" {1}?'),
            record.data.name,
            officeLabel
        );

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/manage-offices/delete-record',

                    params: {
                        division_id: Ext.encode(record.data.division_id)
                    },

                    success: function (f) {
                        thisGrid.getEl().unmask();

                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            thisGrid.store.reload();

                            if (typeof window.top.refreshSettings === 'function') {
                                window.top.refreshSettings('office');
                            }

                            Ext.simpleConfirmation.msg(_('Info'), result.message);
                        } else {
                            Ext.simpleConfirmation.error(result.message);
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot delete. Please try again later.'));
                        thisGrid.getEl().unmask();
                    }
                });
            }
        });
    },

    refreshList: function () {
        this.store.reload();
    },

    onCellRightClick: function (grid, index, cellIndex, e) {
        var menu = new Ext.menu.Menu({
            items: [
                {
                    text:    '<i class="las la-edit"></i>' + _('Edit'),
                    handler: this.editOffice.createDelegate(grid)
                }, {
                    text:    '<i class="las la-trash"></i>' + _('Delete'),
                    handler: this.deleteOffice.createDelegate(grid)
                }, '-', {
                    text:    '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    handler: this.refreshList.createDelegate(this)
                }
            ]
        });
        e.stopEvent();

        // Select row which was selected by right click
        grid.getSelectionModel().selectRow(index);

        menu.showAt(e.getXY());
    }
});
