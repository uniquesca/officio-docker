var NewTaskDialog = function (viewer, config) {
    var sel = viewer ? viewer.viewer.TasksGrid.getSelectionModel().getSelected() : {};
    this.selectedRecordInGrid = sel;
    var thisDialog = this;

    var plain_to = [];
    var plain_cc = [];

    if (config.booEditMode) {
        var to = sel.data.task_assigned_to;
        var cc = sel.data.task_assigned_cc;
        Ext.each(to, function (v) {
            plain_to.push(v[0]);
        });

        Ext.each(cc, function (v) {
            plain_cc.push(v[0]);
        });
    }

    var member_id = config.member_id;
    var booShowClient = !!config.booShowClient;

    this.booShowClient = booShowClient;
    this.booProspect = config.booProspect;
    this.booEditMode = config.booEditMode;
    this.company_id = config.company_id;
    this.checkedType = this.booEditMode ? sel.data.task_type : 'S';

    if (member_id) {
        this.member_id = member_id;
    }

    var win = this;
    this.viewer = viewer;

    // Show users/admin that current user has no access to, but which are assigned in task details
    var arrToUsers = arrTasksSettings['users'].slice();

    // When create New Task - display only active users in "Assign To" combo 
    if (!config.booEditMode) {
        arrToUsers = this.filterUsers(arrToUsers);
    }

    if (plain_to.length) {
        var arrNotShowedUserIds = [];
        Ext.each(plain_to, function (userIdToCheck) {
            var booFound = false;
            Ext.each(arrToUsers, function (arrUserInfo) {
                if (arrUserInfo[0] == userIdToCheck) {
                    booFound = true;
                }
            });

            if (!booFound) {
                arrNotShowedUserIds.push(userIdToCheck);
            }
        });

        if (arrNotShowedUserIds.length) {
            Ext.each(arrNotShowedUserIds, function (userIdToFind) {
                Ext.each(arrTasksSettings['all_users'], function (arrUserInfo) {
                    if (arrUserInfo[0] == userIdToFind) {
                        arrToUsers.push(arrUserInfo);
                    }
                });
            });
        }
        var activeUsers = this.filterUsers(arrToUsers);
        var activeUsersIds = [];

        Ext.each(activeUsers, function (arrUserInfo) {
            activeUsersIds.push(arrUserInfo[0]);
        });
        var i = 0;
        while (i < arrToUsers.length) {
            if ((activeUsersIds.indexOf(arrToUsers[i][0]) == -1) && (plain_to.indexOf(arrToUsers[i][0]) == -1)) {
                arrToUsers.splice(i, 1);
                i--;
            }
            i++;
        }
    }

    // Show users/admin that current user has no access to, but which are assigned in task details
    var arrCCUsers = arrTasksSettings['users'].slice();

    // When create New Task - display only active users in "CC" combo 
    if (!config.booEditMode) {
        arrCCUsers = this.filterUsers(arrCCUsers);
    }

    if (plain_cc.length) {
        var arrNotShowedCCUserIds = [];
        Ext.each(plain_cc, function (userIdToCheck) {
            var booFound = false;
            Ext.each(arrCCUsers, function (arrUserInfo) {
                if (arrUserInfo[0] == userIdToCheck) {
                    booFound = true;
                }
            });

            if (!booFound) {
                arrNotShowedCCUserIds.push(userIdToCheck);
            }
        });

        if (arrNotShowedCCUserIds.length) {
            Ext.each(arrNotShowedCCUserIds, function (userIdToFind) {
                Ext.each(arrTasksSettings['all_users'], function (arrUserInfo) {
                    if (arrUserInfo[0] == userIdToFind) {
                        arrCCUsers.push(arrUserInfo);
                    }
                });
            });
        }
        activeUsers = this.filterUsers(arrCCUsers);
        activeUsersIds = [];

        Ext.each(activeUsers, function (arrUserInfo) {
            activeUsersIds.push(arrUserInfo[0]);
        });

        i = 0;
        while (i < arrCCUsers.length) {
            if ((activeUsersIds.indexOf(arrCCUsers[i][0]) == -1) && (plain_cc.indexOf(arrCCUsers[i][0]) == -1)) {
                arrCCUsers.splice(i, 1);
                i--;
            }
            i++;
        }
    } else {
        // Filter users if we edit Task but "CC to" combo is empty
        arrCCUsers = this.filterUsers(arrCCUsers);
    }

    this.arrStoreFields = ['id', 'name'];
    var store_to = new Ext.data.SimpleStore({
        fields: this.arrStoreFields,
        data: arrToUsers
    });

    if (!config.booEditMode && empty(plain_to.length)) {
        // Automatically preselect the user if there is only one user in the list
        if (arrToUsers.length === 2) {
            plain_to.push(arrToUsers[1][0]);
        } else {
            // Load if needed in showTaskDialog
        }
    }

    var store_cc = new Ext.data.SimpleStore({
        fields: this.arrStoreFields,
        data: arrCCUsers
    });


    var subjectStore = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/tasks/index/subject-suggestion',
            method: 'post'
        }),

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'task'}
        ])
    });

    // Custom rendering Template
    var subjectTpl = new Ext.XTemplate(
        '<tpl for=".">',
        '<div class="x-combo-list-item search-item">' +
        '<span>{task:this.highlightSearch}</span>' +
        '</div>',
        '</tpl>', {
            highlightSearch: function (highlightedRow) {
                var data = subjectStore.reader.jsonData;
                for (var i = 0, len = data.search.length; i < len; i++) {
                    var val = data.search[i];
                    highlightedRow = highlightedRow.replace(
                        new RegExp('(' + preg_quote(val) + ')', 'gi'),
                        "<b style='background-color: #FFFF99;'>$1</b>"
                    );
                }
                return highlightedRow;
            }
        }
    );

    this.subject = new Ext.form.ComboBox({
        fieldLabel: 'Task',
        labelSeparator: '',
        style: 'width: 649px',
        allowBlank: false,
        store: subjectStore,
        displayField: 'task',
        typeAhead: false,
        loadingText: 'Searching...',
        listClass: 'no-pointer',
        cls: 'with-right-border',
        pageSize: 10,
        minChars: 3,
        hideTrigger: true,
        tpl: subjectTpl,
        disabled: this.booEditMode ? (sel.data.task_created_by_id != curr_member_id && !is_administrator && !arrTasksSettings.loose_task_rules) : false,
        itemSelector: 'div.x-combo-list-item'
    });

    if (this.booEditMode) {
        this.subject.setValue(sel.data.task_subject);
    }

    var deadlineFieldId = Ext.id();
    var booHiddenDeadline = this.booEditMode ? (sel.data.task_type != 'S' || empty(sel.data.task_deadline)) : true;
    this.deadline_label = new Ext.form.Label({
        cls: 'x-form-item-label',
        style: 'padding: 10px 10px 0 50px',
        forId: deadlineFieldId,
        hidden: booHiddenDeadline,
        html: _('Complete by') +
            String.format(
                '<i ext:qtip="{0}" class="las la-question-circle" style="font-size: 24px; margin-left: 5px; cursor: help; vertical-align: middle;"></i>',
                _('If this task requires a length of time to complete, please specify a completion date.')
            )
    });

    this.deadline = new Ext.form.DateField({
        id: deadlineFieldId,
        fieldLabel: '',
        emptyText: 'Optional',
        labelSeparator: '',
        value: this.booEditMode ? sel.data.task_deadline : null,
        hidden: booHiddenDeadline,
        disabled: this.booEditMode ? (sel.data.task_created_by_id != curr_member_id && !is_administrator && !arrTasksSettings.loose_task_rules) : false,
        width: 150,
        format: dateFormatFull,
        altFormats: dateFormatFull + '|' + dateFormatShort
    });

    this.cookieTasksDialogMessageHeight = 'tasksDialogMessageHeight';
    this.minMessageFieldHeight = 95;
    this.messageFieldHeight = Ext.state.Manager.get(this.cookieTasksDialogMessageHeight);
    this.messageFieldHeight = empty(this.messageFieldHeight) || this.messageFieldHeight < this.minMessageFieldHeight ? this.minMessageFieldHeight : this.messageFieldHeight;
    this.message = new Ext.form.TextArea({
        fieldLabel: 'Notes',
        labelSeparator: '',
        style: 'width: 649px',
        hidden: this.booEditMode,
        height: this.messageFieldHeight
    });

    this.to_combo = new Ext.ux.form.LovCombo({
        fieldLabel: this.booEditMode ? 'Assigned to' : 'Assign to',
        labelSeparator: '',
        anchor: '100%',
        maxHeight: 200,
        hideOnSelect: false,
        store: store_to,
        triggerAction: 'all',
        valueField: 'id',
        displayField: 'name',
        mode: 'local',
        searchContains: true,
        useSelectAll: true,
        value: plain_to.join(';'),
        separator: ';',
        allowBlank: false
    });

    this.cc_combo = new Ext.ux.form.LovCombo({
        fieldLabel: this.booEditMode ? "CC'd to" : 'CC to',
        labelSeparator: '',
        width: 662,
        maxHeight: 200,
        hideOnSelect: false,
        store: store_cc,
        triggerAction: 'all',
        valueField: 'id',
        displayField: 'name',
        mode: 'local',
        searchContains: true,
        value: plain_cc.join(';'),
        separator: ';',
        useSelectAll: true,
        hidden: true
    });

    this.clients_list = new Ext.form.ComboBox({
        store: new Ext.data.Store({
            data: arrTasksSettings.clients,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                {name: 'clientId'},
                {name: 'clientFullName'}
            ]))
        }),

        mode: 'local',
        searchContains: true,
        displayField: 'clientFullName',
        valueField: 'clientId',
        triggerAction: 'all',
        selectOnFocus: true,
        forceSelection: true,
        fieldLabel: 'Case',
        labelSeparator: '',
        width: 662,
        hidden: !booShowClient,
        value: booShowClient && !empty(member_id) ? member_id : 0,

        // Show 'General Task' option as separated
        tpl: new Ext.XTemplate(
            '<tpl for=".">',
            '<div class="x-combo-list-item {[this.getClientClass(values)]}">',
            '<span>{clientFullName}</span>',
            '<div class="x-clear"></div>',
            '</div>',
            '</tpl>', {
                getClientClass: function (data) {
                    return data.clientId > 0 ? "" : "tasks-combo-general-task";
                }
            }
        ),

        listeners: {
            select: this.toggleClientsField.createDelegate(this)
        }
    });

    this.type1 = new Ext.form.Radio({
        boxLabel: '&nbsp;',
        name: 'rb-auto',
        inputValue: 1,
        checked: this.booEditMode ? sel.data.task_type == 'S' : true,
        listeners: {
            check: this.hideClientFields.createDelegate(this, ['S'], true)
        }
    });

    this.type2 = new Ext.form.Radio({
        boxLabel: '&nbsp;',
        name: 'rb-auto',
        inputValue: 2,
        hidden: this.booEditMode ? !(sel.data.task_type == 'B' || sel.data.task_type == 'C') : true,
        checked: this.booEditMode ? (sel.data.task_type == 'B' || sel.data.task_type == 'C') : false,
        listeners: {
            check: this.hideClientFields.createDelegate(this, ['B'], true)
        }
    });

    this.type3 = new Ext.form.Radio({
        boxLabel: '&nbsp;',
        name: 'rb-auto',
        inputValue: 4,
        checked: this.booEditMode ? sel.data.task_type == 'P' : false,
        hidden: this.booProspect || !empty(config.company_id) || (this.booEditMode && sel.data.task_type != 'P') || !this.booEditMode,
        listeners: {
            check: this.hideClientFields.createDelegate(this, ['P'], true)
        }
    });

    this.specific_date = new Ext.form.DateField({
        minValue: this.booEditMode && sel.data.task_type == 'S' ? null : this.getTodayDate(),
        value: this.booEditMode && sel.data.task_type == 'S' ? sel.data.task_due_on : this.getTodayDate(),
        width: 150,
        format: dateFormatFull,
        maxLength: 12, // Fix issue with date entering in 'full' format
        altFormats: dateFormatFull + '|' + dateFormatShort
    });

    this.radioOnContainerId = Ext.id();
    this.moreOptionsLinkId = Ext.id();
    this.specific_date_fs = new Ext.form.FieldSet({
        checkboxToggle: false,
        collapsed: false,
        style: 'border:none; padding: 10px 0 4px 0; margin:0;',
        items: [
            {
                layout: 'column',
                items: [

                    {
                        xtype: 'container',
                        width: 85,
                        items: [
                            {
                                html: 'Due on',
                                cls: 'x-form-item-label'
                            }
                        ]
                    }, {
                        id: this.radioOnContainerId,
                        xtype: 'container',
                        style: 'padding-top: 7px',
                        hidden: true,
                        items: [this.type1]
                    },
                    {
                        xtype: 'container',
                        style: 'padding-right: 5px;',
                        items: [
                            this.specific_date
                        ]
                    },
                    {
                        xtype: 'container',
                        items: [
                            {
                                xtype: 'panel',
                                layout: 'column',
                                width: 310,
                                items: [
                                    this.deadline_label,
                                    this.deadline
                                ]
                            }
                        ]
                    },
                    {
                        xtype: 'box',
                        autoEl: {
                            id: this.moreOptionsLinkId,
                            tag: 'a',
                            href: '#',
                            'class': 'blulinkunb',
                            style: 'padding: 2px 3px; float: right;',
                            title: '',
                            html: 'more options'
                        },
                        listeners: {
                            scope: this,
                            render: function (c) {
                                c.getEl().on('click', function () {
                                    thisDialog.showFewerOrMore($('#' + thisDialog.moreOptionsLinkId).html() === 'more options');
                                }, this, {stopEvent: true});
                            }
                        }
                    }
                ]
            }
        ]
    });

    this.business_days = new Ext.form.NumberField({
        width: 74,
        hideLabel: true,
        allowDecimals: false,
        allowNegative: false,
        enableKeyEvents: true,
        value: this.booEditMode && (sel.data.task_type == 'B' || sel.data.task_type == 'C') ? sel.data.task_days_count : null,
        listeners: {
            keyup: function (field) {
                var newDate;
                var newValue = field.getValue();
                if (newValue !== '') {
                    if (win.bcCombo.getValue() === 'BUSINESS') {
                        newDate = win.calculateWorkingDate(newValue);
                    } else {
                        newDate = win.calculateDate(newValue);
                    }
                }

                win.business_date.setValue(newDate);
                win.business_date.fireEvent('change', win.business_date, newDate);
            }
        }
    });

    var bcComboValue = 'CALENDAR';

    if (this.booEditMode && sel.data.task_type == 'B') {
        bcComboValue = 'BUSINESS';
    }

    this.bcCombo = new Ext.form.ComboBox({
        store: new Ext.data.SimpleStore({
            fields: ['days_id', 'days_name'],
            data: [
                ['CALENDAR', 'Calendar days from today'],
                ['BUSINESS', 'Business days from today']
            ]
        }),
        mode: 'local',
        displayField: 'days_name',
        valueField: 'days_id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        editable: false,
        value: bcComboValue,
        width: 400,
        listWidth: 400,
        listeners: {
            'beforeselect': function (combo, record) {
                var daysNumber = win.business_days.getValue();
                var newDate;
                if (record.data.days_id === 'BUSINESS') {
                    newDate = win.calculateWorkingDate(daysNumber);
                } else {
                    newDate = win.calculateDate(daysNumber);
                }

                win.business_date.setValue(newDate);
                win.business_date.fireEvent('change', win.business_date, newDate);
            }
        }
    });

    this.business_date = new Ext.form.DateField({
        minValue: this.booEditMode && (sel.data.task_type == 'B' || sel.data.task_type == 'C') ? null : this.getTomorrowDate(),
        hideLabel: true,
        width: 150,
        format: dateFormatFull,
        hidden: true,
        value: this.booEditMode && (sel.data.task_type == 'B' || sel.data.task_type == 'C') ? sel.data.task_due_on : null,
        maxLength: 12, // Fix issue with date entering in 'full' format
        altFormats: dateFormatFull + '|' + dateFormatShort,

        listeners: {
            'change': function (field, value) {
                thisDialog.business_date_displayfield.setValue(thisDialog.getDateFormattedValue(value));
            }
        }
    });

    this.business_date_displayfield = new Ext.form.DisplayField({
        style: 'padding-top: 10px; font-size: 13px;',
        value: this.booEditMode && (sel.data.task_type == 'B' || sel.data.task_type == 'C') ? this.getDateFormattedValue(sel.data.task_due_on) : ''
    });

    this.business_days_fs = new Ext.form.FieldSet({
        autoHeight: true,
        checkboxToggle: false,
        collapsed: false,
        hidden: !this.booEditMode || (this.booEditMode && !(sel.data.task_type == 'B' || sel.data.task_type == 'C')),
        style: 'border:none; padding: 0 0 4px 0; margin:0;',
        items: [
            {
                layout: 'column',

                items: [
                    {
                        xtype: 'container',
                        style: 'padding: 7px 0 0 85px',
                        items: [
                            this.type2
                        ]
                    },
                    {
                        xtype: 'container',
                        style: 'padding-right: 5px;',
                        items: [
                            this.business_days
                        ]
                    },
                    {
                        xtype: 'container',
                        layout: 'column',
                        style: 'padding-right: 5px;',
                        items: [
                            this.bcCombo
                        ]
                    },
                    this.business_date,
                    this.business_date_displayfield
                ]
            }
        ]
    });

    this.custom_number = new Ext.form.NumberField({
        width: 74,
        hideLabel: true,
        value: this.booEditMode && sel.data.task_type == 'P' ? sel.data.task_days_count : 5,
        allowDecimals: false,
        allowNegative: false
    });

    var customDaysDefaultValue;
    if (this.booEditMode && sel.data.task_type == 'P') {
        if (['CALENDAR', 'BUSINESS'].has(sel.data.task_days_type) && ['BEFORE', 'AFTER'].has(sel.data.task_days_when)) {
            customDaysDefaultValue = sel.data.task_days_type + '_' + sel.data.task_days_when;
        }
    } else {
        customDaysDefaultValue = 'CALENDAR_AFTER';
    }

    this.custom_days = new Ext.form.ComboBox({
        store: new Ext.data.SimpleStore({
            fields: ['days_id', 'days_name'],
            data: [
                ['CALENDAR_BEFORE', 'Calendar days before'],
                ['CALENDAR_AFTER', 'Calendar days after'],
                ['BUSINESS_BEFORE', 'Business days before'],
                ['BUSINESS_AFTER', 'Business days after']
            ]
        }),
        mode: 'local',
        displayField: 'days_name',
        valueField: 'days_id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        editable: false,
        value: customDaysDefaultValue,
        width: 200,
        listWidth: 200
    });

    this.custom_prof_store = new Ext.data.Store({
        url: topBaseUrl + '/tasks/index/get-date-fields',
        baseParams: {member_id: member_id},
        autoLoad: !empty(member_id) && !booShowClient && !win.booProspect,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            id: 'field_id'
        }, [
            {name: 'field_id'},
            {name: 'label'}
        ])
    });

    this.custom_prof = new Ext.form.ComboBox({
        store: this.custom_prof_store,
        mode: 'local',
        displayField: 'label',
        editable: false,
        valueField: 'field_id',
        triggerAction: 'all',
        selectOnFocus: true,
        hideLabel: true,
        emptyText: _('Please select...'),
        width: 207,
        listWidth: 270
    });

    if (this.booEditMode && sel.data.task_type == 'P') {
        win.custom_prof_store.on('load', function (store, records) {
            if (!empty(records.length)) {
                win.custom_prof.setValue(sel.data.task_profile_field);
            }
        }, win, {single: true});
    }

    // Change the empty text if there are no records in the combo
    win.custom_prof_store.on('load', function (store, records) {
        if (empty(records.length)) {
            win.custom_prof.emptyText = _('No date fields in this case.');
            win.custom_prof.applyEmptyText();
            win.custom_prof.reset();
        }
    }, win);

    this.custom_fs = new Ext.form.FieldSet({
        autoHeight: true,
        checkboxToggle: false,
        collapsed: false,
        style: 'border:none; padding: 0 0 4px 0; margin:0;',
        hidden: !this.booEditMode || this.booProspect || (this.booEditMode && sel.data.task_type != 'P'),
        items: [
            {
                layout: 'column',
                items: [
                    {
                        xtype: 'container',
                        style: 'padding: 7px 0 0 85px',
                        items: [
                            this.type3
                        ]
                    },
                    {
                        xtype: 'container',
                        style: 'padding-right: 5px;',
                        items: [
                            this.custom_number
                        ]
                    },
                    {
                        xtype: 'container',
                        style: 'padding-right: 5px;',
                        items: [
                            this.custom_days
                        ]
                    },
                    {
                        xtype: 'container',
                        style: 'padding-right: 5px;',
                        items: [
                            this.custom_prof
                        ]
                    }
                ]
            }
        ]
    });

    this.panItems = [
        this.specific_date_fs,
        this.business_days_fs,
        this.custom_fs
    ];

    this.priority_flag = new Ext.form.ComboBox({
        fieldLabel: 'Priority Flag',
        labelSeparator: '',

        store: {
            xtype: 'arraystore',
            fields: ['priority_flag_id', 'priority_flag_name', 'priority_flag_label', 'priority_flag_color'],
            data: [
                [1, 'red', 'Red Flag', 'red'],
                [2, 'blue', 'Blue Flag', 'blue'],
                [3, 'yellow', 'Yellow Flag', 'yellow'],
                [4, 'green', 'Green Flag', 'green'],
                [5, 'orange', 'Orange Flag', 'orange'],
                [6, 'purple', 'Purple Flag', 'purple'],
                [0, 'empty', 'Clear Flag', '#616366']
            ]
        },

        mode: 'local',
        displayField: 'priority_flag_label',
        valueField: 'priority_flag_id',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        value: this.booEditMode && sel.data.task_flag ? sel.data.task_flag : null,
        width: 150,
        listWidth: 130,
        cellId: 'td-reminder-priority',
        tpl: new Ext.XTemplate(
            '<tpl for=".">' +
            '<div class="x-combo-list-item" style="padding: 2px;">' +
            '<i class="lar la-flag" style="color: {priority_flag_color}; padding: 0 10px"></i>' +
            '<span class="x-menu-item-text">{priority_flag_label}</span>' +
            '</div>' +
            '</tpl>'
        )
    });

    this.notify_client = new Ext.form.Checkbox({
        boxLabel: 'CC Client',
        hidden: this.booProspect,
        checked: this.booEditMode && sel.data.task_notify_client === 'Y',
        hideLabel: true
    });

    this.displayPanel = new Ext.form.FormPanel({
        ref: '../displayPanel',
        labelWidth: 80,
        autoHeight: true,
        bodyStyle: 'padding:5px;',
        items: [
            this.subject,
            {
                layout: 'table',
                xtype: 'panel',
                anchor: '100%',
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%'
                        }
                    },
                    columns: 2
                },
                items: [
                    {
                        layout: 'form',
                        xtype: 'panel',
                        items: [
                            this.to_combo
                        ]
                    }, {
                        xtype: 'panel',
                        autoEl: {
                            tag: 'a',
                            href: '#',
                            'class': 'blulinkunb',
                            style: 'padding: 0px 3px 12px; float: right;',
                            title: '',
                            html: 'cc'
                        },
                        listeners: {
                            scope: this,
                            render: function (c) {
                                c.getEl().on('click', function () {
                                    thisDialog.cc_combo.setVisible(!thisDialog.cc_combo.isVisible());
                                }, this, {stopEvent: true});
                            }
                        }
                    }
                ]
            },

            this.cc_combo,
            this.clients_list,
            this.message,
            this.panItems,

            {
                xtype: 'container',
                layout: 'form',
                style: 'margin-top: 10px',
                items: this.priority_flag
            }
        ]
    });

    NewTaskDialog.superclass.constructor.call(this, {
        title: this.booEditMode ? '<i class="lar la-edit"></i>' + _('Edit Task') : '<i class="las la-plus"></i>' + _('New Task'),
        cls: 'task-reply-window',
        layout: 'fit',
        modal: true,
        autoHeight: true,
        stateful: false,
        width: 800,

        resizable: false,
        items: this.displayPanel,
        closeAction: 'close',
        bbar: [
            {
                xtype: 'container',
                style: 'padding-left: 5px',
                items: this.notify_client
            }, '->', {
                xtype: 'button',
                text: 'Cancel',
                handler: function () {
                    win.close();
                }
            }, {
                xtype: 'button',
                text: 'Save',
                cls: 'orange-btn',
                handler: this.submitData.createDelegate(this)
            }
        ]
    });

    this.on('show', this.onDialogShow, this);
};

Ext.extend(NewTaskDialog, Ext.Window, {
    onDialogShow: function () {
        this.toggleClientsField();
        this.hideClientFields(null, true, this.checkedType);

        if (this.booEditMode && this.checkedType !== 'S') {
            this.showFewerOrMore(true);
        }
    },

    showFewerOrMore: function (booShow) {
        var thisDialog = this;

        $('#' + thisDialog.moreOptionsLinkId).html(booShow ? 'fewer options' : 'more options');

        Ext.getCmp(thisDialog.radioOnContainerId).setVisible(booShow);
        thisDialog.type2.setVisible(booShow);
        thisDialog.business_days_fs.setVisible(booShow);
        thisDialog.type3.setVisible(booShow);

        thisDialog.deadline_label.setVisible(booShow);
        thisDialog.deadline.setVisible(booShow);

        var booCanBeChecked = !this.clients_list.isVisible() || !empty(this.clients_list.getValue());
        thisDialog.custom_fs.setVisible(booShow && booCanBeChecked && !this.booProspect);

        if (!booShow) {
            thisDialog.type1.setValue(true);
        }
        thisDialog.syncShadow();
    },

    // Load users list in array format [[id, name], [id, name], ...]
    getCheckedUsersInCombo: function (combo) {
        var win = this;
        var arrUsers = [];

        var strCheckedUserIds = combo.getCheckedValue();
        if (!empty(strCheckedUserIds)) {
            var arrCheckedUserIds = strCheckedUserIds.split(combo.separator);
            combo.store.data.each(function (r) {
                if (!empty(r.data[win.arrStoreFields[0]]) && arrCheckedUserIds.has(r.data[win.arrStoreFields[0]])) {
                    arrUsers.push(
                        [r.get(win.arrStoreFields[0]), r.get(win.arrStoreFields[1])]
                    );
                }
            });
        }

        return arrUsers;
    },

    calculateWorkingDate: function (days) {
        if (empty(days)) {
            return new Date().format(dateFormatShort);
        }

        var stDate = new Date();
        var startDateYear = stDate.getFullYear();
        var startDateMonth = stDate.getMonth();
        var startDateDat = stDate.getDate();

        var ssDate = new Date(startDateYear, startDateMonth, startDateDat);

        for (d = 0; d < days; d++) {
            if (ssDate.getDay() === 5 || ssDate.getDay() === 6) {
                ++days;
            }

            ssDate = new Date(ssDate.valueOf() + 86400000);
        }

        return ssDate.format(dateFormatShort);
    },

    calculateDate: function (days) {
        if (empty(days)) {
            return new Date().format(dateFormatShort);
        }

        var stDate = new Date();
        var startDateYear = stDate.getFullYear();
        var startDateMonth = stDate.getMonth();
        var startDateDat = stDate.getDate();

        ssDate = new Date(startDateYear, startDateMonth, startDateDat);
        ssDate = new Date(ssDate.valueOf() + 86400000 * days);

        return ssDate.format(dateFormatShort);
    },

    getTodayDate: function () {
        var d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), d.getDate());
    },

    getTomorrowDate: function () {
        var d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
    },

    showTaskDialog: function () {
        var thisDialog = this;

        // When we show a "new task" dialog for the case - preselect the "To" in relation to the case's "Processing" field
        var booLoadTo = !thisDialog.booShowClient && !thisDialog.booEditMode && !this.booProspect && empty(thisDialog.to_combo.value);

        // Preload the list of clients if we show the "clients list" combo
        var booLoadClients = empty(arrTasksSettings.clients.length) && thisDialog.booShowClient;

        if (booLoadTo || booLoadClients) {
            Ext.getBody().mask(_('Loading...'));

            Ext.Ajax.request({
                url: topBaseUrl + '/tasks/index/get-task-settings',
                params: {
                    booLoadTo: Ext.encode(booLoadTo),
                    booLoadClients: Ext.encode(booLoadClients),
                    caseId: Ext.encode(this.member_id)
                },

                success: function (result) {
                    Ext.getBody().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        if (booLoadClients) {
                            arrTasksSettings.clients = resultDecoded.arrClients;
                            thisDialog.clients_list.getStore().loadData(arrTasksSettings.clients);
                        }

                        if (booLoadTo && resultDecoded.arrTo.length) {
                            thisDialog.to_combo.value = resultDecoded.arrTo.join(thisDialog.to_combo.separator);
                        }

                        thisDialog.show();
                        thisDialog.center();
                    } else {
                        Ext.simpleConfirmation.error(result.message);
                    }
                },

                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error('An error occurred. Please try again later');
                }
            });
        } else {
            thisDialog.show();
            thisDialog.center();
        }
    },

    // In relation to the selected client -
    // we need to check if 'based on client's profile' field can be checked/entered
    toggleClientsField: function () {
        // Check if a client is selected in the clients combo
        var selectedClientId = this.clients_list.getValue();
        var booCanBeChecked = !this.clients_list.isVisible() || !empty(selectedClientId);

        // There are 3 cases if we need to show 'Notify Client' checkbox:
        // 1. General (when clients combo is showed) -> show only if client is selected in this combo
        // 2. Client's Tasks tab - show always
        // 3. Prospect's Tasks tab - hide always

        var booShowNotify = false;
        if (this.booEditMode) {
            booShowNotify = !this.booProspect && !empty(this.selectedRecordInGrid.data.member_id);
        } else {
            booShowNotify = (!this.booShowClient || !empty(this.clients_list.getValue())) && !this.booProspect && empty(this.company_id);
        }

        this.notify_client.setVisible(booShowNotify);

        if (!booCanBeChecked && this.type3.checked) {
            this.type1.setValue(true);
        }
        this.type3.setDisabled(!booCanBeChecked);

        if (this.business_days_fs.isVisible()) {
            this.custom_fs.setVisible(booCanBeChecked && !this.booProspect);
        }

        // Automatically reload store for the combo
        // if another client is selected
        if (booShowNotify && !empty(selectedClientId)) {
            this.custom_prof_store.baseParams.member_id = selectedClientId;
            this.custom_prof_store.reload();
        }

        this.syncShadow();
    },

    hideClientFields: function (r, booChecked, strCheckedType) {
        if (booChecked) {
            this.checkedType = strCheckedType;

            var booDisableFirstRow = strCheckedType !== 'S';
            this.specific_date.setDisabled(booDisableFirstRow);
            this.deadline_label.setDisabled(booDisableFirstRow);
            this.deadline.setDisabled(booDisableFirstRow);

            var booDisableSecondRow = strCheckedType !== 'B' && strCheckedType !== 'C';
            this.business_days.setDisabled(booDisableSecondRow);
            this.bcCombo.setDisabled(booDisableSecondRow);
            this.business_date.setDisabled(booDisableSecondRow);
            this.business_date_displayfield.setDisabled(booDisableSecondRow);

            var booDisableThirdRow = strCheckedType !== 'P';
            this.custom_number.setDisabled(booDisableThirdRow);
            this.custom_days.setDisabled(booDisableThirdRow);
            this.custom_prof.setDisabled(booDisableThirdRow);
        }
    },

    filterUsers: function (arrUsers) {
        var activeUsers = [];
        Ext.each(arrUsers, function (arrUserInfo) {
            if (arrUserInfo[1] == 'All Users' || (arrUserInfo[3] !== 'undefined') && (arrUserInfo[3] == 1)) {
                activeUsers.push(arrUserInfo);
            }
        });
        return activeUsers;
    },

    submitData: function () {
        //get reminder type
        var date, number, ba, prof, days;
        var booIsError = !this.displayPanel.getForm().isValid();
        var type_checked = this.checkedType;
        // Check and mark client fields invalid
        switch (this.checkedType) {
            case 'S':
                date = this.specific_date.getValue();
                if (empty(date)) {
                    this.specific_date.markInvalid('This is a required field');
                    booIsError = true;
                } else {
                    if (!this.specific_date.isValid()) {
                        booIsError = true;
                    }
                }
                break;

            case 'B':
                number = this.business_days.getValue();
                if (empty(number)) {
                    this.business_days.markInvalid(null);
                    booIsError = true;
                }

                var bcComboValue = this.bcCombo.getValue();
                if (empty(bcComboValue)) {
                    this.bcCombo.markInvalid(null);
                    booIsError = true;
                }

                if (bcComboValue == 'CALENDAR') {
                    type_checked = 'C';
                }

                date = this.business_date.getValue();
                if (empty(date)) {
                    this.business_date.markInvalid('This is a required field');
                    booIsError = true;
                } else {
                    if (!this.business_date.isValid()) {
                        booIsError = true;
                    }
                }
                break;

            case 'P':
                number = this.custom_number.getValue();

                if (empty(number)) {
                    this.custom_number.markInvalid(null);
                    booIsError = true;
                }

                var daysBeforeAfter = this.custom_days.getValue();
                if (empty(daysBeforeAfter)) {
                    this.custom_days.markInvalid(null);
                    booIsError = true;
                } else {
                    switch (daysBeforeAfter) {
                        case 'CALENDAR_BEFORE':
                            days = 'CALENDAR';
                            ba = 'BEFORE';
                            break;

                        case 'CALENDAR_AFTER':
                            days = 'CALENDAR';
                            ba = 'AFTER';
                            break;

                        case 'BUSINESS_BEFORE':
                            days = 'BUSINESS';
                            ba = 'AFTER';
                            break;

                        case 'BUSINESS_AFTER':
                            days = 'BUSINESS';
                            ba = 'AFTER';
                            break;

                        default:
                    }
                }

                prof = this.custom_prof.getValue();
                if (empty(prof)) {
                    this.custom_prof.markInvalid(null);
                    booIsError = true;
                }
                break;

            default:
                break;
        }

        if (!booIsError) {
            var win = this;
            var tasks_grid = this.viewer ? this.viewer.viewer.TasksGrid : null;
            var threads_grid = this.viewer ? this.viewer.viewer.ThreadsGrid : null;
            var deadlineDate = this.deadline.getValue();

            win.getEl().mask('Saving...');

            Ext.Ajax.request({
                url: win.booEditMode ? topBaseUrl + '/tasks/index/change-participants/' : topBaseUrl + '/tasks/index/add/',
                params: {
                    task_id: win.booEditMode ? Ext.encode(win.selectedRecordInGrid.data.task_id) : 0,

                    client_type: this.booProspect ? 'prospect' : 'client',
                    task_to: Ext.encode(this.to_combo.getValue()),
                    task_cc: Ext.encode(this.cc_combo.getValue()),
                    task_subject: Ext.encode(this.subject.getValue()),
                    task_message: Ext.encode(this.message.getValue()),
                    task_deadline: empty(deadlineDate) ? '' : Ext.util.Format.date(deadlineDate, 'Y-m-d'),

                    member_id: !this.booShowClient ? this.member_id : this.clients_list.getValue(),
                    company_id: this.company_id,
                    type_checked: type_checked,
                    date: Ext.encode(date),
                    number: number,
                    days: days,
                    ba: ba,
                    prof: prof,
                    task_notify_client: this.notify_client.checked && this.notify_client.isVisible() ? 'Y' : 'N',
                    flag: this.priority_flag.getValue()
                },
                success: function (res) {
                    win.getEl().unmask();

                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        // Reload:
                        // 1. Home page
                        // 2. General Tasks tab
                        // 3. Client's Tasks sub tab

                        if (tasks_grid) {
                            tasks_grid.store.reload();
                        } else {
                            // Home page: show confirmation message
                            var msg = win.booEditMode ? 'Task was updated successfully.' : 'Task was created successfully.';
                            Ext.simpleConfirmation.success(msg);
                        }

                        if (win.booEditMode && threads_grid) {
                            // update record's data
                            var rec = tasks_grid.getSelectionModel().getSelected();
                            rec.data['task_assigned_to'] = win.getCheckedUsersInCombo(win.to_combo);
                            rec.data['task_assigned_cc'] = win.getCheckedUsersInCombo(win.cc_combo);
                            rec.data['task_deadline'] = win.deadline.getValue();
                            rec.data['task_deadline_date'] = win.deadline.getValue();
                            rec.data['task_subject'] = win.subject.getValue();
                            rec.data['task_notify_client'] = win.notify_client.getValue() ? 'Y' : 'N';

                            // update the view
                            rec.commit();

                            threads_grid.loadList(true, false);
                        }

                        // Always reload tasks list on the home page
                        var oDashboardContainer = Ext.getCmp('dashboard-container');
                        if (oDashboardContainer) {
                            oDashboardContainer.reloadBlockInfo('tasks');
                        }

                        win.close();
                    } else {
                        Ext.simpleConfirmation.error(result.msg);
                    }
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error('Internal server error. Please try again.');
                }
            });
        }
    },

    getDateFormattedValue: function (dateValue) {
        var returnValue = '';
        if (!empty(dateValue) && dateValue !== '0000-00-00') {
            returnValue = Ext.util.Format.date(dateValue, Date.patterns.LongDate)
        }

        return returnValue;
    }
});

Ext.reg('appTasksNewTaskDialog', NewTaskDialog);