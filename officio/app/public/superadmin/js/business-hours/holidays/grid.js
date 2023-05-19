var BusinessScheduleHolidaysGrid = function (config) {
    var thisGrid = this;
    Ext.apply(this, config);

    this.holidayRecord = Ext.data.Record.create([
        {
            name: 'holiday_id',
            type: 'int'
        },
        {
            name: 'company_id',
            type: 'int'
        },
        {
            name: 'holiday_name',
            type: 'string'
        },
        {
            name:       'holiday_date_from',
            type:       'date',
            dateFormat: Date.patterns.ISO8601Short
        },
        {
            name:       'holiday_date_to',
            type:       'date',
            dateFormat: Date.patterns.ISO8601Short
        }
    ]);

    this.store = new Ext.data.Store({
        url:        baseUrl + '/manage-business-hours/holidays-view',
        remoteSort: true,

        sortInfo: {
            field:     'holiday_date_from',
            direction: 'DESC'
        },
        autoLoad: true,

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader({
            id:            'holiday_id',
            root:          'rows',
            totalProperty: 'totalCount'
        }, this.holidayRecord)
    });
    this.store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));

    this.tbar = [
        {
            text:    '<i class="las la-plus"></i>' + _('Add Holiday'),
            hidden:  !arrBusinessHoursAccess['add-holidays'],
            handler: this.addHoliday.createDelegate(this)
        },
        {
            text:     '<i class="las la-edit"></i>' + _('Edit Holiday'),
            ref:      '../holidayEditBtn',
            disabled: true,
            hidden:   !arrBusinessHoursAccess['edit-holidays'],
            handler:  this.onDblClick.createDelegate(this)
        },
        {
            text:     '<i class="las la-trash"></i>' + _('Delete Holiday'),
            ref:      '../holidayDeleteBtn',
            disabled: true,
            hidden:   !arrBusinessHoursAccess['delete-holidays'],
            handler:  this.deleteHoliday.createDelegate(this)
        },
        '->',
        {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            scope:   this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.sm = new Ext.grid.RowSelectionModel({singleSelect: true});

    var arrColumns = [
        {
            header:    _('Name'),
            dataIndex: 'holiday_name',
            renderer:  function (value, p, record) {
                if (!empty(record.data.company_id) && !empty(thisGrid.member_id)) {
                    value += ' <i>(Pre-defined by the company)</i>';
                }

                return value;
            }
        },
        {
            header:    _('Date'),
            width:     300,
            fixed:     true,
            dataIndex: 'holiday_date_from',
            renderer:  function (value, p, record) {
                var date = Ext.util.Format.date(record.data.holiday_date_from, dateFormatFull);
                if (!empty(record.data.holiday_date_to)) {
                    date += ' - ' + Ext.util.Format.date(record.data.holiday_date_to, dateFormatFull);
                }

                return date;
            }
        }
    ];

    this.cm = new Ext.grid.ColumnModel({
        columns:         arrColumns,
        defaultSortable: true
    });

    BusinessScheduleHolidaysGrid.superclass.constructor.call(this, {
        height:     560,
        loadMask:   true,
        cls:        'extjs-grid',
        stripeRows: true,

        viewConfig: {
            deferEmptyText: false,
            emptyText:      _('No records found.'),
            forceFit:       true
        }
    });

    this.on('rowcontextmenu', this.showContextMenu, this);
    this.on('rowdblclick', this.onDblClick, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(BusinessScheduleHolidaysGrid, Ext.grid.GridPanel, {
    applyParams: function (store, options) {
        options.params = options.params || {};

        // Apply filter variables
        var params = {
            dir:        store.sortInfo.direction,
            sort:       store.sortInfo.field,
            member_id:  this.member_id,
            company_id: this.company_id
        };

        Ext.apply(options.params, params);
    },

    checkCanManageSelectedRecord: function (action) {
        var sel = this.view.grid.getSelectionModel().getSelected();

        var booCanEdit   = false;
        var booCanDelete = false;
        if (sel && sel.data) {
            if (!empty(sel.data.company_id) && !empty(this.member_id)) {
                // Cannot edit because this record was created for company,
                // We view it from User's page
            } else {
                booCanEdit   = true;
                booCanDelete = true;
            }
        }

        var booCanDoAction = false;
        switch (action) {
            case 'edit':
                booCanDoAction = arrBusinessHoursAccess['edit-holidays'] && booCanEdit;
                break;

            case 'delete':
                booCanDoAction = arrBusinessHoursAccess['delete-holidays'] && booCanDelete;
                break;

            default:
                break;
        }

        return booCanDoAction;
    },

    updateToolbarButtons: function () {
        this.holidayDeleteBtn.setDisabled(!this.checkCanManageSelectedRecord('delete'));
        this.holidayEditBtn.setDisabled(!this.checkCanManageSelectedRecord('edit'));
    },

    showContextMenu: function (grid, rowIndex, e) {
        grid.getSelectionModel().selectRow(rowIndex);

        this.menu = new Ext.menu.Menu({
            items: [
                {
                    text:    '<i class="las la-plus"></i>' + _('Add Holiday'),
                    hidden:  !arrBusinessHoursAccess['add-holidays'],
                    handler: this.addHoliday.createDelegate(this)
                },
                {
                    text:    '<i class="las la-edit"></i>' + _('Edit Holiday'),
                    hidden:  !arrBusinessHoursAccess['edit-holidays'] || !this.checkCanManageSelectedRecord('edit'),
                    handler: this.onDblClick.createDelegate(this)
                },
                {
                    text:    '<i class="las la-trash"></i>' + _('Delete Holiday'),
                    scope:   this,
                    hidden:  !arrBusinessHoursAccess['delete-holidays'] || !this.checkCanManageSelectedRecord('delete'),
                    handler: grid.deleteHoliday.createDelegate(grid)
                },
                '-',
                {
                    text:   '<i class="las la-undo-alt"></i>' + _('Refresh'),
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

        if (this.checkCanManageSelectedRecord('edit')) {
            this.editHoliday(this, record);
        }
    },

    addHoliday: function () {
        var dialog = new BusinessScheduleHolidaysDialog({
            member_id:     this.member_id,
            company_id:    this.company_id,
            holidayRecord: new this.holidayRecord({})
        }, this);

        dialog.showDialog();
    },

    editHoliday: function (grid, record) {
        var dialog = new BusinessScheduleHolidaysDialog({
            member_id:     this.member_id,
            company_id:    this.company_id,
            holidayRecord: record
        }, grid);

        dialog.showDialog();
    },

    deleteHoliday: function () {
        var thisGrid    = this;
        var arrSelected = thisGrid.getSelectionModel().getSelections();
        var msg         = String.format(_('Are you sure want to delete <i>{0}</i>?'), arrSelected.length === 1 ? arrSelected[0].data.holiday_name : arrSelected.length + _(' selected records'));

        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                var ids = [];

                for (var i = 0; i < arrSelected.length; i++) {
                    ids.push(arrSelected[i].data.holiday_id);
                }

                Ext.Ajax.request({
                    url: baseUrl + '/manage-business-hours/holidays-delete',

                    params: {
                        member_id:  thisGrid.member_id,
                        company_id: thisGrid.company_id,
                        arrIds:     Ext.encode(ids)
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