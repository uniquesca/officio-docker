var arrTaskFields = [
    'task_id',
    {name: 'task_flag', type: 'int'},
    'task_subject',
    'client_type',
    'task_type',
    'task_days_count',
    'task_days_type',
    'task_days_when',
    'task_read_permission',
    'task_full_permission',
    'task_profile_field',
    'task_profile_field_label',
    'member_id',
    'member_parent_id',
    'task_created_by',
    'member_full_name',
    {name: 'task_created_by_id', type: 'int'},
    'task_assigned_to',
    'task_assigned_cc',
    'task_priority',
    {name: 'task_deadline', type: 'date', dateFormat: Date.patterns.ISO8601Short},
    {name: 'task_deadline_date', mapping: 'task_deadline', type: 'date', dateFormat: Date.patterns.ISO8601Short},
    {name: 'task_create_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
    {name: 'task_due_on', type: 'date', dateFormat: Date.patterns.ISO8601Short},
    {name: 'task_due_on_date', mapping: 'task_due_on', type: 'date', dateFormat: Date.patterns.ISO8601Short},
    'task_notify_client',
    'task_is_due',
    'task_completed',
    {name: 'task_unread', type: 'boolean'},
    {name: 'task_can_edit', type: 'int'},
    'auto_task_type'
];

TasksGrid = function (viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);
    var genId = Ext.id();

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/tasks/index/get-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'tasks',
            totalProperty: 'count',
            idProperty: 'task_id',
            fields: arrTaskFields
        })
    });
    this.store.setDefaultSort('task_due_on', 'DESC');
    this.store.on('beforeload', this.applyTasksFilter, this);

    // Automatically set focus to specific task
    // @NOTE: we do this only once
    this.store.on('load', this.setAutoFocusToTask, this, {single: true});

    this.store.on('load', this.toggleTaskGridColumns, this);

    // Use/load default columns that must be showed
    var cookieId = config.booClientTab ? 'client-tasks-columns-settings' : 'tasks-columns-settings';
    var arrDefaultShowColumns = Ext.state.Manager.get(cookieId);
    if (!arrDefaultShowColumns) {
        arrDefaultShowColumns = ['task_created_by', 'task_flag', 'task_subject', 'task_due_on_date', 'task_deadline'];
    }

    var subjectColId = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.flagColumnId = Ext.id();
    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm, {
                header: '<i class="las la-user-circle" title="Created By"></i>',
                dataIndex: 'task_created_by',
                width: 40,
                align: 'left',
                renderer: this.formatOwner.createDelegate(this),
                sortable: false,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_created_by')
            }, {
                id: this.flagColumnId,
                header: '<i class="lar la-flag" title="Flag"></i>',
                dataIndex: 'task_flag',
                width: 40,
                renderer: this.formatFlag.createDelegate(this),
                sortable: true,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_flag')
            }, {
                id: subjectColId,
                header: 'Subject',
                dataIndex: 'task_subject',
                renderer: this.formatSubject.createDelegate(this),
                sortable: true,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_subject')
            }, {
                header: 'Due on',
                dataIndex: 'task_due_on_date',
                renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                width: 120,
                align: 'center',
                sortable: true,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_due_on_date')
            }, {
                header: 'Age',
                dataIndex: 'task_due_on',
                width: 100,
                align: 'center',
                renderer: this.formatAge.createDelegate(this, [true, true], true),
                sortable: true,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_due_on')
            }, {
                header: 'Complete by',
                dataIndex: 'task_deadline',
                width: 100,
                align: 'center',
                renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                sortable: true,
                menuDisabled: true,
                hidden: !arrDefaultShowColumns.has('task_deadline')
            }, {
                header: '<i class="las la-check" title="Task Complete"></i>',
                dataIndex: 'task_completed',
                width: 28,
                renderer: function (v) {
                    return (v === 'Y') ? '<i class="las la-check" title="Task Complete"></i>' : '';
                },
                fixed: true,
                menuDisabled: true,
                sortable: true
            }
        ]
    });


    TasksGrid.superclass.constructor.call(this, {
        id: genId,
        cls: 'tasks-grid',
        border: false,
        loadMask: {msg: 'Loading...'},
        cm: cm,
        sm: sm,
        stateful: false,
        stateId: 'tasks-grid' + (config.booClientTab ? '-clients-tab' : ''),
        autoExpandColumn: subjectColId,
        stripeRows: true,

        viewConfig: {
            emptyText: String.format(
                '<img src="{0}/images/sample_tasks_explained.jpg" alt="{1}" style="width: 100%" />',
                topBaseUrl,
                _('There are no tasks to show.')
            ),
            getRowClass: this.applyRowClass,

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 20,
            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth) {
                    // no scroller
                    this.scrollOffset = 2;
                } else {
                    // there is a scroller
                    this.scrollOffset = this.orgScrollOffset;
                }
                this.fitColumns(false);
            }
        }
    });

    this.on('cellclick', this.onCellClick, this);
    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.getSelectionModel().on('selectionchange', this.viewer.TasksToolbar.updateToolbarButtons, this.viewer);

    // Remember columns changes
    this.getColumnModel().on('hiddenchange', function () {
        Ext.state.Manager.set(cookieId, this.getColumnIds());
    }, this);
};

Ext.extend(TasksGrid, Ext.grid.GridPanel, {
    getColumnIds: function () {
        var cols = [];
        var columns = this.getColumnModel().config;
        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex)) {
                cols.push(columns[i].dataIndex);
            }
        }

        return cols;
    },

    setFocusToTask: function (taskId) {
        var index = this.getStore().find('task_id', taskId);
        if (index >= 0) {
            // Select found task
            this.getSelectionModel().selectRow(index);

            // Scroll to just selected task - so it will be visible
            var rowEl = this.getView().getRow(index);
            rowEl.scrollIntoView(this.getGridEl(), false);
        }
    },

    setAutoFocusToTask: function () {
        if (this.viewer.taskId) {
            this.setFocusToTask(this.viewer.taskId);
        }
    },

    toggleTaskGridColumns: function () {
        var store = this.getStore();
        var items = store.data.items;
        var booHideCompletedColumn = true;
        var booHideDeadlineColumn = true;
        for (var i = 0; i < items.length; i++) {
            if (items[i].data.task_completed === 'Y') {
                booHideCompletedColumn = false;
            }

            if (!empty(items[i].data.task_deadline)) {
                booHideDeadlineColumn = false;
            }
        }

        var oModel = this.getColumnModel();
        oModel.setHidden(oModel.findColumnIndex('task_completed'), booHideCompletedColumn);
        oModel.setHidden(oModel.findColumnIndex('task_deadline'), booHideDeadlineColumn);

        // Don't show the headers section if no records were loaded -
        // because we'll show the image that contains headers in it
        $('#' + this.id + ' .x-grid3-header').toggle(!empty(store.getTotalCount()));
        this.syncSize();
    },

    applyTasksFilter: function (store, options) {
        options.params = options.params || {};

        var params = {};

        if (!empty(this.viewer.clientId)) {
            params['clientId'] = this.viewer.clientId;
            params['booProspect'] = this.viewer.booProspect;
        }

        this.viewer.TasksToolbar.taskFilterBtn.menu.items.each(
            function (i) {
                switch (i.getXType()) {
                    case 'menucheckitem':
                        if (i.checked) {
                            params[i.name] = i.value;
                        }
                        break;
                    case 'combo':
                    case 'hidden':
                        params[i.getName()] = i.getValue();
                        break;
                    default:
                }
            }
        );

        Ext.apply(options.params, params);
    },

    // Apply custom class for row in relation to several criteria
    applyRowClass: function (record) {
        var priority = record.data['task_priority'];
        if (empty(priority)) {
            // Use default
            priority = record.data['task_is_due'] === 'Y' ? 'regular' : 'low';
        }

        // If there is a deadline, and it is less than 5 days - show in red color
        if (record.data['task_completed'] !== 'Y' && !empty(record.data['task_deadline_date'])) {
            var today = new Date();
            var one_day = 1000 * 60 * 60 * 24;
            if (Math.floor(Math.abs(record.data['task_deadline_date'] - today) / one_day) <= 5) {
                priority = 'critical';
            }
        }

        var strCls = 'task-priority-' + priority;
        if (record.data['task_unread']) {
            strCls += ' task-unread';
        }

        return strCls;
    },

    getSelectedTaskIds: function () {
        var selRecords = this.getSelectionModel().getSelections();

        var task_ids = [];
        for (var i = 0; i < selRecords.length; i++)
            task_ids.push(selRecords[i]['data'].task_id);

        return task_ids;
    },

    // Return true if flags are equal
    compareFlags: function (intFlag, strFlag) {
        return strFlag === this.getStringFlagById(intFlag);
    },

    // Update task flag for selected task item
    setTaskFlag: function (grid, rowIndex) {
        var rec = grid.store.getAt(rowIndex);

        // If there is no flag - set default flag
        // If there is a flag - remove it (set to blank)
        var defaultFlag = 'red';
        this.sendRequestToUpdateFlag(
            rec.data['task_id'],
            this.compareFlags(rec.data['task_flag'], 'empty') ? defaultFlag : 'empty'
        );
    },

    sendRequestToUpdateFlag: function (taskId, strFlag) {
        // Get record by task id
        var record;
        var grid = this;
        var idx = grid.store.findExact('task_id', taskId);
        if (idx !== -1) {
            record = this.store.getAt(idx);
        }

        // user ca not change flag for completed task
        if (record.data.task_completed === 'Y')
            return;

        if (record) {
            // Show 'loading' image during request
            var currentFlag = record.data['task_flag'];
            grid.updateTaskRecordFlag(record, this.getIntFlagByString('loading'));

            // Send request to update a flag
            Ext.Ajax.request({
                url: topBaseUrl + '/tasks/index/update-task-flag',
                params: {
                    'task_id': Ext.encode(taskId),
                    'task_flag': Ext.encode(strFlag)
                },

                success: function (res) {
                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        // Update task record with new flag
                        grid.updateTaskRecordFlag(record, grid.getIntFlagByString(strFlag));

                        grid.viewer.ThreadsGrid.store.reload();
                    } else {
                        // Otherwise show error
                        Ext.simpleConfirmation.error(result.msg);
                        grid.updateTaskRecordFlag(record, currentFlag);
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error('Internal server error. Please try again.');
                    grid.updateTaskRecordFlag(record, currentFlag);
                }
            });
        }
    },

    updateTaskRecordFlag: function (record, intFlag) {
        record.beginEdit();
        record.set('task_flag', intFlag);
        record.endEdit();
        this.store.commitChanges();
    },

    // Change task flag to specific
    changeFlag: function (menuItem, booChecked) {
        if (booChecked) {
            var currentFlag = this.ctxFlagRecord.data['task_flag'];
            var newFlag = menuItem.taskFlag;

            if (!this.compareFlags(currentFlag, newFlag)) {
                // Send request to update the flag
                this.sendRequestToUpdateFlag(
                    this.ctxFlagRecord.data['task_id'],
                    newFlag
                );
            }
        }
    },

    // Identify int flag id and return string id
    getStringFlagById: function (intFlag) {
        var strFlag;
        switch (intFlag) {
            case 1:
                strFlag = 'red';
                break;
            case 2:
                strFlag = 'blue';
                break;
            case 3:
                strFlag = 'yellow';
                break;
            case 4:
                strFlag = 'green';
                break;
            case 5:
                strFlag = 'orange';
                break;
            case 6:
                strFlag = 'purple';
                break;
            case 7:
                strFlag = 'complete';
                break;
            default:
                strFlag = 'empty';
                break;
        }

        return strFlag;
    },

    // Identify string flag id and return int id
    getIntFlagByString: function (strFlag) {
        var intFlag;
        switch (strFlag) {
            case 'red':
                intFlag = 1;
                break;
            case 'blue':
                intFlag = 2;
                break;
            case 'yellow':
                intFlag = 3;
                break;
            case 'green':
                intFlag = 4;
                break;
            case 'orange':
                intFlag = 5;
                break;
            case 'purple':
                intFlag = 6;
                break;
            case 'complete':
                intFlag = 7;
                break;
            default:
                intFlag = 0;
                break;
        }

        return intFlag;
    },

    // Show 'flag' context menu when right-click on the 'flag' column
    showFlagContextMenu: function (grid, rowIndex, e) {
        // Save the record we show a context menu to
        this.ctxFlagRecord = grid.store.getAt(rowIndex);
        var currentFlag = this.ctxFlagRecord.data['task_flag'];

        if (!this.flagMenu) {
            // Create context menu on first right click
            var arrFlagItems = [{
                text: '<i class="lar la-flag" style="color: red"></i>' + 'Red Flag',
                flag: 'red'
            }, {
                text: '<i class="lar la-flag" style="color: blue"></i>' + 'Blue Flag',
                flag: 'blue'
            }, {
                text: '<i class="lar la-flag" style="color: yellow"></i>' + 'Yellow Flag',
                flag: 'yellow'
            }, {
                text: '<i class="lar la-flag" style="color: green"></i>' + 'Green Flag',
                flag: 'green'
            }, {
                text: '<i class="lar la-flag" style="color: orange"></i>' + 'Orange Flag',
                flag: 'orange'
            }, {
                text: '<i class="lar la-flag" style="color: purple"></i>' + 'Purple Flag',
                flag: 'purple'
            }, {text: '-'}, {
                text: '<i class="lar la-flag"></i>' + 'Clear Flag',
                flag: 'empty'
            }];

            var arrMenuItems = [];
            Ext.each(arrFlagItems, function (item) {
                if (item.text === '-') {
                    arrMenuItems.push('-');
                } else {
                    arrMenuItems.push({
                        text: item.text,
                        'taskFlag': item.flag,
                        checked: item.flag === 'empty' ? false : grid.compareFlags(currentFlag, item.flag),
                        group: 'task-flag-group',
                        checkHandler: grid.changeFlag.createDelegate(grid)
                    });
                }
            });

            this.flagMenu = new Ext.menu.Menu({
                cls: 'no-icon-menu',
                showSeparator: false,
                items: arrMenuItems
            });
        } else {
            // Select menu element in relation to currently selected menu item's flag
            this.flagMenu.items.each(function (menuItem) {
                if (menuItem.getXType() === 'menucheckitem') {
                    menuItem.setChecked(
                        grid.compareFlags(currentFlag, menuItem.taskFlag) && menuItem.taskFlag !== 'empty',
                        true
                    );
                }
            });
        }

        // Show context menu
        e.stopEvent();
        this.flagMenu.showAt(e.getXY());
    },

    getFlagColumnNumber: function () {
        return this.getColumnModel().getIndexById(this.flagColumnId);
    },

    // In relation to the task's item flag - show an icon
    formatFlag: function (flag) {
        var icon;
        var color = this.getStringFlagById(flag);
        switch (color) {
            case 'loading':
            case 'empty':
                icon = 'lar la-flag';
                color = '';
                break;

            case 'complete':
                icon = 'las la-flag-checkered';
                color = '';
                break;

            default:
                icon = 'lar la-flag';
                break;
        }

        return String.format(
            '<i class="main-flag-icon {0}" {1}></i>',
            icon,
            empty(color) ? '' : 'style="color: ' + color + '"'
        );
    },

    //  An icon indicating:
    //  - The user is the owner of the task,
    //  - Recipient (To)
    //  - The follower (CC)
    formatOwner: function (flag, item, rec) {
        var strIcon = '';
        var strIconTitle = '';
        if (rec.data['task_created_by_id'] === curr_member_id) {
            strIcon = 'las la-user-circle';
            strIconTitle = 'You are an owner of this task';
        } else {
            var arrUsers, i;
            if (empty(strIcon)) {
                arrUsers = rec.data['task_assigned_to'];
                for (i = 0; i < arrUsers.length; i++) {
                    if (parseInt(arrUsers[i][0], 10) === parseInt(curr_member_id, 10)) {
                        strIcon = 'las la-user';
                        strIconTitle = "You are in 'TO' field of this task";
                        break;
                    }
                }
            }

            if (empty(strIcon)) {
                arrUsers = rec.data['task_assigned_cc'];
                for (i = 0; i < arrUsers.length; i++) {
                    if (parseInt(arrUsers[i][0], 10) === parseInt(curr_member_id, 10)) {
                        strIcon = 'las la-users';
                        strIconTitle = "You are in 'CC' field of this task";
                        break;
                    }
                }
            }
        }

        return String.format(
            '<i class="task-owner {0}" title="{1}"></i>',
            strIcon,
            strIconTitle
        );
    },

    onCellClick: function (grid, rowIndex, cellIndex) {
        if (cellIndex === grid.getFlagColumnNumber()) {
            this.setTaskFlag(grid, rowIndex);
        }
    },

    onCellRightClick: function (grid, rowIndex, cellIndex, e) {
        if (cellIndex === grid.getFlagColumnNumber()) {
            // Show 'Flag' context menu
            this.showFlagContextMenu(grid, rowIndex, e);
        } else {
            // Show 'General' context menu
            this.onContextClick(grid, rowIndex, e);
        }
    },

    // Show context menu
    onContextClick: function (grid, index, e) {
        var rec = grid.store.getAt(index);
        var toolbar = this.viewer.TasksToolbar;
        var booIsTaskCompleted = rec.data['task_completed'] === 'Y';

        var menu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            items: [{
                text: '<i class="las la-plus"></i>' + _('New'),
                handler: toolbar.createTask.createDelegate(toolbar)
            }, {
                text: '<i class="las la-reply"></i>' + _('Reply'),
                disabled: booIsTaskCompleted,
                handler: toolbar.replyTask.createDelegate(toolbar)
            }, {
                text: '<i class="las la-marker"></i>' + _('Mark as...'),
                disabled: booIsTaskCompleted,
                menu: {
                    items: [
                        {
                            text: 'Read',
                            checked: !rec.data['task_unread'],
                            group: 'group-tasks-read',
                            handler: toolbar.markTaskAs.createDelegate(toolbar, [true])
                        }, {
                            text: 'Unread',
                            checked: rec.data['task_unread'],
                            group: 'group-tasks-read',
                            handler: toolbar.markTaskAs.createDelegate(toolbar, [false])
                        }
                    ]
                }

            }, {
                text: booIsTaskCompleted ? '<i class="las la-check"></i>' + _('Uncomplete') : '<i class="las la-check"></i>' + _('Complete'),
                handler: booIsTaskCompleted ? toolbar.markUncomplete.createDelegate(toolbar) : toolbar.markComplete.createDelegate(toolbar)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit Task'),
                disabled: booIsTaskCompleted,
                handler: toolbar.changeParticipants.createDelegate(toolbar, [])
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                hidden: toolbar.booDeleteButtonHidden,
                disabled: booIsTaskCompleted,
                handler: toolbar.deleteTask.createDelegate(toolbar)
            }, '-', {
                text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                scope: this,
                handler: function () {
                    grid.store.load();
                }
            }]
        });
        e.stopEvent();

        // Select row which was selected by right click
        grid.getSelectionModel().selectRow(index);

        menu.showAt(e.getXY());
    },

    formatSubject: function (val, p, rec) {
        if (this.booClientTab) {
            return String.format(
                '<div class="task_subject_column">{0}</div>',
                Ext.util.Format.nl2br(rec.data['task_subject'])
            );
        } else {
            var member_name = '';
            if (empty(rec.data['member_id'])) {
                // General Task
                member_name = 'General Task';
            } else if (rec.data['client_type'] == 'prospect') {
                // Prospect's Tasks Tab, assigned to prospect
                member_name = '<a href="#" onclick="setUrlHash(\'#prospects/prospect/' + rec.data['member_id'] + '/tasks/' + rec.data['task_id'] + '/\'); setActivePage(); return false;">' + rec.data['member_full_name'] + '</a>';
            } else {
                // Client's Tasks Tab, assigned to client
                member_name = '<a href="#" onclick="setUrlHash(\'#applicants/' + rec.data['member_parent_id'] + '/cases/' + rec.data['member_id'] + '/tasks/\'); setActivePage(); return false;">' + rec.data['member_full_name'] + '</a>';
            }

            return String.format(
                '<div class="task_subject_column"><div>{0}</div><div>{1}</div></div>',
                member_name,
                Ext.util.Format.nl2br(rec.data['task_subject'])
            );
        }
    },

    formatAge: function (date, item, rec, c, d, e, show_ago, dont_show_negative) {
        dont_show_negative = !!dont_show_negative;

        // only show the Deadline and Age values only for Active tasks. Don't show any value for completed tasks
        if (rec.data.task_completed === 'Y' || empty(date)) {
            return '';
        }

        // The number of milliseconds in one day
        var ONE_DAY = 1000 * 60 * 60 * 24;
        var today = new Date();
        var todayMorning = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        var val = Math.ceil((todayMorning.getTime() - date.getTime()) / ONE_DAY);

        if (dont_show_negative && val < 0) {
            val = null;
        }

        var age = '';
        if (val !== null) {
            var when = '';
            if (empty(val)) {
                when = 'Today';
            } else {
                val = parseInt(val, 10);
                when = String.format(
                    '{0} {1}{2}',
                    Math.abs(val),
                    Math.abs(val) === 1 ? 'day' : 'days',
                    show_ago ? ((val > 0) ? ' ago' : ' left') : ''
                );
            }

            age = String.format(
                '<div ext:qtip="{0}" style="cursor: help">{1}</div>',
                _('Due on: ') + Ext.util.Format.date(date, dateFormatFull),
                when
            );
        }

        return age;
    }
});

Ext.reg('appTasksGrid', TasksGrid);