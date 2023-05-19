function copyToClipboard(text) {
    clipboard.copy(text).then(function () {
        Ext.simpleConfirmation.msg('Info', 'Url was successfully copied to the clipboard.', 1000);
    }, function () {
        Ext.simpleConfirmation.error('Url was not copied to the clipboard.<br>Please open the edit dialog and manually copy from it.');
    });
}

var DivisionsGroupsGrid = function () {
    var thisGrid = this;

    // Init basic properties
    this.store = new Ext.data.Store({
        url:      baseUrl + '/manage-divisions-groups/get-list',
        method:   'POST',
        autoLoad: true,

        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount',
            idProperty:    'division_group_id',

            fields: [
                {
                    name: 'division_group_id',
                    type: 'int'
                }, {
                    name: 'division_group_company',
                    type: 'string'
                }, {
                    name: 'division_group_status',
                    type: 'string'
                }, {
                    name: 'division_group_registration_hash',
                    type: 'string'
                }
            ]
        }),

        sortInfo: {
            field:     'division_group_id',
            direction: 'DESC'
        }
    });

    var expandCol = Ext.id();

    this.selModel = new Ext.grid.CheckboxSelectionModel();
    this.columns  = [
        this.selModel, {
            id:        expandCol,
            header:    'Company',
            sortable:  true,
            width:     100,
            dataIndex: 'division_group_company'
        }, {
            header:    'Registration link',
            sortable:  true,
            dataIndex: 'division_group_registration_hash',
            renderer:  this.formatRegistrationLink.createDelegate(this),
            width:     40
        }, {
            header:    'Status',
            sortable:  true,
            dataIndex: 'division_group_status',
            renderer:  this.formatStatus.createDelegate(this),
            width:     40
        }
    ];


    this.tbar = [
        {
            text:    'Create Authorised Agent',
            iconCls: 'division-group-icon-add',
            handler: function () {
                var wnd = new DivisionsGroupsDialog({
                    params: {
                        division_group_id: 0
                    }
                }, thisGrid);
                wnd.showDialog();
            }
        }, '-', {
            text:     'Edit Authorised Agent',
            iconCls:  'division-group-icon-edit',
            ref:      '../editRecordBtn',
            disabled: true,
            handler:  function () {
                var r   = thisGrid.getSelectionModel().getSelected();
                var wnd = new DivisionsGroupsDialog({
                    params: {
                        division_group_id: r.data.division_group_id
                    }
                }, thisGrid);
                wnd.showDialog();
            }
        }, '-', {
            text:     'Change Status',
            iconCls:  'division-group-icon-status',
            ref:      '../changeRecordStatusBtn',
            disabled: true,
            menu:     {
                id:    'status-menu',
                items: [
                    {
                        text:         'Active',
                        value:        'active',
                        checked:      true,
                        group:        'rp-group',
                        checkHandler: this.changeStatus,
                        scope:        this
                    }, {
                        text:         'Inactive',
                        value:        'inactive',
                        checked:      false,
                        group:        'rp-group',
                        checkHandler: this.changeStatus,
                        scope:        this
                    }
                ]
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize:    25,
        store:       this.store,
        displayInfo: true,
        emptyMsg:    'No records to display'
    });

    DivisionsGroupsGrid.superclass.constructor.call(this, {
        autoWidth:        true,
        height:           getSuperadminPanelHeight(),
        autoExpandColumn: expandCol,
        stripeRows:       true,
        cls:              'extjs-grid',
        renderTo:         'divisions_groups_container',
        loadMask:         true,

        viewConfig: {
            forceFit:  true,
            emptyText: 'No records were found.'
        }
    });

    this.getSelectionModel().on('selectionchange', this.onSelectionChange.createDelegate(this));
    this.on('rowdblclick', this.onDblClick, this);
};

Ext.extend(DivisionsGroupsGrid, Ext.grid.GridPanel, {
    getSelectedRecord: function () {
        var sel = this.getSelectionModel().getSelections();
        return sel.length > 0 ? sel[0].data : false;
    },

    onSelectionChange: function (sm) {
        var selRecordsCount = sm.getCount();
        var booOneSelected  = selRecordsCount === 1;

        this.editRecordBtn.setDisabled(!booOneSelected);
        this.changeRecordStatusBtn.setDisabled(!booOneSelected);

        if (booOneSelected) {
            var sel   = this.getSelectedRecord();
            var items = this.changeRecordStatusBtn.menu.items.items;
            switch (sel.division_group_status) {
                case 'active':
                    items[0].setChecked(true, true);
                    break;

                case 'suspended':
                    items[2].setChecked(true, true);
                    break;

                //case 'inactive':
                default:
                    items[1].setChecked(true, true);
                    break;
            }
        }
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        var wnd = new DivisionsGroupsDialog({
            params: {
                division_group_id: record.data.division_group_id
            }
        }, this);
        wnd.showDialog();
    },

    formatRegistrationLink: function (hash) {
        return String.format('<a href="{0}" target="_blank" class="blulinkun">Open in a new tab</a> <br><a href="{0}" onclick="copyToClipboard(this.href); return false;" class="blulinkun">Copy to the clipboard</a>', topBaseUrl + '/api/index/register-agent/hash/' + hash);
    },

    formatStatus: function (status) {
        var strResult = status;
        switch (status) {
            case 'inactive' :
                strResult = '<span style="color: red">Inactive</span>';
                break;

            case 'suspended' :
                strResult = '<span style="color: gray">Suspended</span>';
                break;

            case 'active' :
                strResult = '<span style="color: green">Active</span>';
                break;

            default:
                break;
        }
        return strResult;
    },

    changeStatus: function (item, booChecked) {
        if (booChecked) {
            var thisGrid = this;
            var sel      = thisGrid.getSelectedRecord();

            var radios    = Ext.getCmp('status-menu').items.items;
            var newStatus = '';
            Ext.each(radios, function (item) {
                if (item.checked) {
                    newStatus = item.value;
                }
            });

            Ext.Ajax.request({
                url: baseUrl + '/manage-divisions-groups/update-record-status',

                params: {
                    division_group_id:     sel.division_group_id,
                    division_group_status: newStatus
                },

                success: function (f) {
                    var result = Ext.decode(f.responseText);
                    if (result.success) {
                        thisGrid.store.reload();
                    } else {
                        Ext.simpleConfirmation.error(result.msg);
                    }

                    Ext.getBody().unmask();
                },

                failure: function () {
                    Ext.simpleConfirmation.error('Cannot update status. Please try again later.');
                    Ext.getBody().unmask();
                }
            });
        }
    }
});