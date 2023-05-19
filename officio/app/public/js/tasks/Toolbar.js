var tasksTimeoutMarkAsRead;
var TasksToolbar = function (viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);
    var toolbar = this;
    var menuSuffix = '';
    var activeStatusFilter = 'active';
    if (typeof config.activeStatusFilter !== 'undefined' && ['', 'active', 'completed', 'due_today', 'due_tomorrow', 'due_next_7_days'].has(config.activeStatusFilter)) {
        activeStatusFilter = config.activeStatusFilter;
    }

    this.booDeleteButtonHidden = false;
    this.booHideCombobox = false;
    this.booHideAnyoneOption = true;
    this.helpContextId = '';
    if (typeof arrAccessRights === 'undefined') {
        if (viewer.clientId) {
            this.booHideCombobox = true;
            this.booHideAnyoneOption = false;
            if (viewer.booProspect) {
                menuSuffix = '-prospect';
                this.booDeleteButtonHidden = !arrNotesToolbarOptions['access'].has('booHiddenProspectsTasksDelete');

                this.helpContextId = 'prospects-tasks';
            } else {
                this.booDeleteButtonHidden = !arrNotesToolbarOptions['access'].has('booHiddenClientsTasksDelete');

                this.helpContextId = 'clients-tasks';
            }

            menuSuffix += '-' + viewer.clientId;
        } else {
            // My Tasks
            this.booDeleteButtonHidden = !arrNotesToolbarOptions['access'].has('booHiddenTasksDelete');
            this.booHideCombobox = !arrNotesToolbarOptions['access'].has('booTasksViewUsers');

            this.helpContextId = 'my-tasks';
        }
    } else {
        // Superadmin
        this.booDeleteButtonHidden = !arrAccessRights['tasks-delete'];
        this.booHideCombobox = !arrAccessRights['tasks-view-users'];
    }

    var generatedComboId = Ext.id();

    var arrMenuItems = [
        '<b class="menu-title">' + _('Status:') + '</b>', {
            text: _('Active'),
            checked: activeStatusFilter == 'active',
            group: 'group-tasks-status' + menuSuffix,
            name: 'task-filter-status',
            value: 'active',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            text: _('Completed'),
            group: 'group-tasks-status' + menuSuffix,
            checked: activeStatusFilter == 'completed',
            name: 'task-filter-status',
            value: 'completed',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            text: _('Active or Completed'),
            group: 'group-tasks-status' + menuSuffix,
            checked: activeStatusFilter == '',
            name: 'task-filter-status',
            value: '',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            text: _('Due as of Today'),
            group: 'group-tasks-status' + menuSuffix,
            checked: activeStatusFilter == 'due_today',
            name: 'task-filter-status',
            value: 'due_today',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            text: _('Due Tomorrow'),
            group: 'group-tasks-status' + menuSuffix,
            checked: activeStatusFilter == 'due_tomorrow',
            name: 'task-filter-status',
            value: 'due_tomorrow',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            text: _('Due over the next 7 days'),
            group: 'group-tasks-status' + menuSuffix,
            checked: activeStatusFilter == 'due_next_7_days',
            name: 'task-filter-status',
            value: 'due_next_7_days',
            handler: toolbar.filterTasks.createDelegate(this)
        }, {
            xtype: 'hidden',
            name: 'task-filter-owner',
            value: curr_member_id
        }
    ];

    if (!viewer.booProspect) {
        var arrAdditionalMenuItems = [
            {
                xtype: 'menutextitem',
                text: toolbar.booHideCombobox ? String.format('<b class="menu-title">{0}</b>', _('Assigned:')) : '<div class="menu-title" style="height: 40px"><div style="float:left; padding-top: 12px; font-weight: bold">' + _('Show tasks for:') + '&nbsp;&nbsp;</div> <div id="' + generatedComboId + '" style="float: left;"></div></div>'
            }, {
                text: toolbar.getRadioText('anyone', true, ''),
                checked: !toolbar.booHideAnyoneOption,
                hidden: toolbar.booHideAnyoneOption,
                group: 'group-tasks-assigned' + menuSuffix,
                name: 'task-filter-assigned',
                value: 'anyone',
                handler: this.filterTasks.createDelegate(this)
            }, {
                text: toolbar.getRadioText('me', true, ''),
                checked: toolbar.booHideAnyoneOption,
                group: 'group-tasks-assigned' + menuSuffix,
                name: 'task-filter-assigned',
                value: 'me',
                handler: this.filterTasks.createDelegate(this)
            }, {
                text: toolbar.getRadioText('following', true, ''),
                checked: false,
                group: 'group-tasks-assigned' + menuSuffix,
                name: 'task-filter-assigned',
                value: 'following',
                handler: this.filterTasks.createDelegate(this)
            }, {
                text: toolbar.getRadioText('me_and_following', true, ''),
                checked: false,
                group: 'group-tasks-assigned' + menuSuffix,
                name: 'task-filter-assigned',
                value: 'me_and_following',
                handler: this.filterTasks.createDelegate(this)
            }, {
                text: toolbar.getRadioText('others', true, ''),
                checked: false,
                group: 'group-tasks-assigned' + menuSuffix,
                name: 'task-filter-assigned',
                value: 'others',
                handler: this.filterTasks.createDelegate(this)
            }
        ];

        arrMenuItems = arrMenuItems.concat(arrAdditionalMenuItems);
    }

    this.filterMenu = new Ext.menu.Menu({
        items: arrMenuItems,

        listeners: {
            'show': {
                single: true,

                fn: function () {
                    if (!toolbar.booHideCombobox) {
                        var arrUsers = typeof arrTasksSettings === 'undefined' ? [] : arrTasksSettings.users.slice(0);
                        if (arrUsers.length) {
                            // Remove 'All Users' option - we don't need to show it in the filter combo
                            arrUsers.splice(0, 1);

                            // Move current user's name to the top of the list
                            Ext.each(arrUsers, function (oUser, index) {
                                if (parseInt(oUser[0], 10) === curr_member_id) {
                                    var oUserClone = oUser.slice();
                                    arrUsers.splice(index, 1);

                                    oUserClone[1] += _(' (me)');
                                    arrUsers.unshift(oUserClone);

                                    return false;
                                }
                            });
                        }

                        toolbar.selectedUserCombo = new Ext.form.ComboBox({
                            renderTo: generatedComboId,

                            store: new Ext.data.ArrayStore({
                                fields: ['owner_id', 'owner_name'],
                                data: arrUsers
                            }),

                            displayField: 'owner_name',
                            valueField: 'owner_id',
                            typeAhead: true,
                            editable: false,
                            mode: 'local',
                            triggerAction: 'all',
                            selectOnFocus: true,
                            width: 220,
                            listWidth: 220,
                            value: curr_member_id,

                            getListParent: function () {
                                // Required to show the list above the combo
                                return this.el.up('.x-menu');
                            },

                            tpl: new Ext.XTemplate('<tpl for=".">', '<tpl if="owner_id != ' + curr_member_id + '">', '<div class="x-combo-list-item">{owner_name}</div>', '</tpl>', '<tpl if="owner_id == ' + curr_member_id + '">', '<div class="x-combo-list-item" style="font-weight: bold">{owner_name}</div>', '</tpl>', '</tpl>'),

                            listeners: {
                                'select': function (combo, rec) {
                                    var booMe = rec.data[combo.valueField] == curr_member_id;
                                    var userName = rec.data[combo.displayField];
                                    var arrRadios = toolbar.filterMenu.find('name', 'task-filter-assigned');
                                    Ext.each(arrRadios, function (oRadio) {
                                        var newText = toolbar.getRadioText(oRadio.value, booMe, userName);
                                        if (!empty(newText)) {
                                            oRadio.setText(newText);
                                        }
                                    });

                                    toolbar.filterMenu.find('name', 'task-filter-owner')[0].setValue(rec.data[combo.valueField]);


                                    combo.checked = false;
                                    toolbar.filterTasks(combo);
                                }
                            }
                        });
                    }
                }
            }
        }
    });

    TasksToolbar.superclass.constructor.call(this, {
        enableOverflow: true,
        defaultType: 'button',

        items: [
            {
                text: '<i class="las la-plus"></i>' + _('New Task'),
                cls: 'main-btn',
                ref: 'taskCreateBtn',
                scope: this,
                handler: this.createTask
            }, {
                text: '<i class="las la-columns"></i>' + _('Select Columns'),
                tooltip: _('Select the information columns you want to display.'),
                id: 'select-columns',
                handler: this.selectColumns.createDelegate(this)
            }, {
                text: '<i class="las la-check"></i>' + _('Mark as complete'),
                disabled: true,
                hidden: true,
                ref: 'taskMarkAsCompleteBtn',
                handler: this.markComplete.createDelegate(this)
            }, {
                text: '<i class="lar la-flag"></i>' + _('Set Personal Priority'),
                disabled: true,
                hidden: true, // Temporary hidden, can be removed later
                ref: 'taskSetPersonalPriorityBtn',
                menu: {
                    width: 140,
                    items: [
                        {
                            cls: 'task-priority-low',
                            text: 'Low',
                            checked: false,
                            pr_value: 'low',
                            group: 'group-tasks-priority',
                            checkHandler: toolbar.setPriority.createDelegate(this)
                        }, {
                            cls: 'task-priority-regular',
                            text: 'Regular',
                            checked: false,
                            pr_value: 'regular',
                            group: 'group-tasks-priority',
                            checkHandler: toolbar.setPriority.createDelegate(this)
                        }, {
                            cls: 'task-priority-medium',
                            text: 'Medium',
                            checked: false,
                            pr_value: 'medium',
                            group: 'group-tasks-priority',
                            checkHandler: toolbar.setPriority.createDelegate(this)
                        }, {
                            cls: 'task-priority-high',
                            text: 'High',
                            checked: false,
                            pr_value: 'high',
                            group: 'group-tasks-priority',
                            checkHandler: toolbar.setPriority.createDelegate(this)
                        }, {
                            cls: 'task-priority-critical',
                            text: 'Critical',
                            checked: false,
                            pr_value: 'critical',
                            group: 'group-tasks-priority',
                            checkHandler: toolbar.setPriority.createDelegate(this)
                        }
                    ]
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                disabled: true,
                hidden: true,
                ref: 'taskDeleteBtn',
                handler: this.deleteTask.createDelegate(this)
            }, '->', {
                xtype: 'container',
                layout: 'column',
                width: 165,
                cls: 'secondary-btn',
                items: [
                    {
                        xtype: 'checkbox',
                        ref: '../taskShowDetailsCheckbox',
                        hideLabel: true,
                        checked: Ext.state.Manager.get('tasksShowDetails'),
                        listeners: {
                            'check': function (checkbox, booChecked) {
                                if (booChecked) {
                                    Ext.state.Manager.set('tasksShowDetails', booChecked);
                                } else {
                                    Ext.state.Manager.clear('tasksShowDetails');
                                }

                                viewer.ThreadsGrid.loadList(true);
                            }
                        }
                    }, {
                        text: _('Show System Logs'),
                        xtype: 'button',
                        style: 'margin-top: 5px',
                        tooltip: _('See the task-specific system activities on your Officio account.'),
                        handler: function () {
                            toolbar.taskShowDetailsCheckbox.setValue(!toolbar.taskShowDetailsCheckbox.getValue());
                        }
                    }
                ]
            }, {
                text: toolbar.getActiveFilterLabel(),
                tooltip: toolbar.viewer.booProspect ? _('Organize by task status.') : _('Organize by task status, or by task assigned.'),
                ref: 'taskFilterBtn',
                style: 'margin-left: 10px',
                menu: this.filterMenu,
                labelSeparator: ''
            }, {
                xtype: 'tbseparator',
                hidden: !empty(viewer.clientId)
            }, {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                handler: toolbar.refreshTasksList.createDelegate(toolbar)
            }, {
                xtype: 'button',
                ctCls: 'x-toolbar-cell-no-right-padding',
                text: String.format('<i class="las la-question-circle help-icon" title="{0}"></i>', _('View the related help topics.')),
                hidden: !allowedPages.has('help') || empty(toolbar.helpContextId),
                handler: function () {
                    showHelpContextMenu(this.getEl(), toolbar.helpContextId);
                }
            }
        ]
    });
};

Ext.extend(TasksToolbar, Ext.Toolbar, {
    getActiveFilterLabel: function () {
        var toolbar = this;
        var strFilter = '<i class="las la-filter"></i>' + _('Filter:');

        var arrRadios = toolbar.filterMenu.find('name', 'task-filter-status');
        Ext.each(arrRadios, function (oRadio) {
            if (oRadio.checked) {
                strFilter += ' ' + oRadio.text;
            }
        });

        if (!toolbar.viewer.booProspect) {
            arrRadios = toolbar.filterMenu.find('name', 'task-filter-assigned');
            Ext.each(arrRadios, function (oRadio) {
                if (oRadio.checked) {
                    strFilter += ' ' + oRadio.text;
                }
            });
        } else {
            strFilter += ' ' + _('Tasks');
        }

        return strFilter;
    },

    getRadioText: function (radioValue, booMe, userName) {
        var newText = '';
        switch (radioValue) {
            case 'anyone':
                newText = String.format(
                    _('Tasks assigned to <b>{0}</b>'),
                    _('anyone')
                );
                break;

            case 'me':
                newText = String.format(
                    _('Tasks assigned to <b>{0}</b>'),
                    booMe ? _('me') : userName
                );
                break;

            case 'following':
                newText = String.format(
                    _("Tasks that <b>{0}</b> following (cc'd)"),
                    booMe ? _("I'm") : userName + _(' is')
                );
                break;

            case 'me_and_following':
                newText = String.format(
                    _('Tasks that assigned to <b>{0}</b> and following'),
                    booMe ? _('me') : userName
                );
                break;

            case 'others':
                newText = String.format(
                    _('Tasks that <b>{0}</b> assigned to others'),
                    booMe ? _('I') : userName
                );
                break;

            default:
        }

        return newText;
    },

    refreshTasksList: function () {
        this.viewer.TasksGrid.getStore().load();
    },

    updateToolbarButtons: function (viewer) {
        var toolbar = this;
        var sel = this.TasksGrid.getSelectionModel().getSelections();
        var booIsSelectedOne = sel.length === 1;
        var booIsSelectedMoreThanOne = sel.length > 1;
        var booIsSelectedAtLeastOne = sel.length >= 1;
        var booIsFirstTaskComplete = booIsSelectedAtLeastOne ? sel[0]['data']['task_completed'] === 'Y' : false;

        var booHasFullAccessToAllTasks = true;
        for (var i = 0; i < sel.length; i++) {
            if (!sel[i]['data']['task_full_permission']) {
                booHasFullAccessToAllTasks = false;
                break;
            }
        }

        // When a task is completed, users should not be able to Edit, Reply to or set personal priority
        this.TasksToolbar['taskMarkAsCompleteBtn'].setDisabled(!booIsSelectedAtLeastOne || booIsFirstTaskComplete || !booHasFullAccessToAllTasks);
        this.TasksToolbar['taskSetPersonalPriorityBtn'].setDisabled(!booIsSelectedAtLeastOne || booIsFirstTaskComplete);
        this.TasksToolbar['taskDeleteBtn'].setDisabled(!booIsSelectedAtLeastOne || booIsFirstTaskComplete || !booHasFullAccessToAllTasks);

        this.TasksToolbar['taskMarkAsCompleteBtn'].setVisible(booIsSelectedMoreThanOne);
        this.TasksToolbar['taskDeleteBtn'].setVisible(booIsSelectedMoreThanOne && !toolbar.booDeleteButtonHidden);


        // Reload thread list with delay - this is a fix when we use 'check all' option
        (function () {
            toolbar.ThreadsGrid.loadList();
        }).defer(100);

        // after 3 seconds mark task as read
        clearTimeout(tasksTimeoutMarkAsRead);
        var records = viewer.grid.getSelectionModel().getSelections();
        if (records.length === 1) {
            var record = records[0];

            if (record.data.task_unread) {
                tasksTimeoutMarkAsRead = setTimeout(
                    function () {
                        return viewer.grid.viewer.TasksToolbar.markTaskAs(true, true);
                    },
                    3000
                );
            }
        }
    },

    createTask: function () {
        var win = new NewTaskDialog(this, {booShowClient: empty(this.viewer.clientId), member_id: this.viewer.clientId, booProspect: this.viewer.booProspect});
        win.showTaskDialog();
    },

    replyTask: function () {
        var win = new TasksReplyDialog(this);
        win.show();
        win.center();
    },

    markTaskAs: function (booAsRead, dontShowProcess) {
        var rec = this.viewer.TasksGrid.getSelectionModel().getSelected();

        if (!rec) {
            return;
        }

        if (!dontShowProcess) {
            Ext.getBody().mask('Updating...');
        }

        Ext.Ajax.request({
            url: topBaseUrl + '/tasks/index/mark-as-read/',
            params: {
                task_id: rec.data['task_id'],
                as_read: Ext.encode(booAsRead)
            },
            success: function (result) {
                if (!dontShowProcess)
                    Ext.getBody().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    // Update record's info
                    rec.data['task_unread'] = !booAsRead;

                    // update the view
                    rec.commit();

                    // Always reload tasks list on the home page
                    var oDashboardContainer = Ext.getCmp('dashboard-container');
                    if (oDashboardContainer) {
                        oDashboardContainer.reloadBlockInfo('tasks');
                    }
                } else {
                    if (!dontShowProcess) {
                        Ext.simpleConfirmation.error(resultDecoded.msg);
                    }
                }
            },
            failure: function () {
                if (!dontShowProcess) {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error('An error occurred. Please try again later');
                }
            }
        });
    },

    markComplete: function () {
        var grid = this.viewer.TasksGrid;
        var selRecords = grid.getSelectionModel().getSelections();

        if (selRecords.length <= 0) {// :)
            return;
        }

        var msg = String.format(
            'Are you sure you want to mark <span style="font-weight: bold; font-style: italic;">{0}</span> {1} as complete?',
            selRecords.length === 1 ? selRecords[0]['data']['task_subject'] : selRecords.length,
            selRecords.length === 1 ? 'task' : 'tasks'
        );


        Ext.Msg.confirm(
            _('Please confirm'),
            msg,
            function (btn) {
                if (btn === 'yes') {
                    var task_ids = [];
                    for (var i = 0; i < selRecords.length; i++) {
                        task_ids.push(selRecords[i]['data'].task_id);
                    }

                    Ext.getBody().mask('Updating...');

                    Ext.Ajax.request({
                        url: topBaseUrl + '/tasks/index/mark-complete/',
                        params: {
                            task_ids: Ext.encode(task_ids)
                        },
                        success: function (result) {
                            Ext.getBody().unmask();

                            var resultDecoded = Ext.decode(result.responseText);
                            if (resultDecoded.success) {
                                Ext.simpleConfirmation.msg('Info', 'Task' + (task_ids.length === 1 ? '' : 's') + ' marked as complete');
                                grid.store.load();

                                // Always reload tasks list on the home page
                                var oDashboardContainer = Ext.getCmp('dashboard-container');
                                if (oDashboardContainer) {
                                    oDashboardContainer.reloadBlockInfo('tasks');
                                }
                            } else {
                                Ext.simpleConfirmation.error(resultDecoded.msg);
                            }
                        },
                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error('An error occurred. Please try again later');
                        }
                    });
                }
            });
    },

    markUncomplete: function () {
        var grid = this.viewer.TasksGrid;
        var selRecords = grid.getSelectionModel().getSelections();

        if (selRecords.length !== 1) {
            return;
        }

        var msg = String.format(
            'Are you sure you want to mark <span style="font-weight: bold; font-style: italic;">{0}</span> task as uncomplete?',
            selRecords[0]['data']['task_subject']
        );

        Ext.Msg.confirm(
            'Please confirm',
            msg,
            function (btn) {
                if (btn === 'yes') {
                    var task_id = selRecords[0].data.task_id;

                    Ext.getBody().mask('Updating...');

                    Ext.Ajax.request({
                        url: topBaseUrl + '/tasks/index/mark-complete?uncomplete=1',
                        params: {
                            task_ids: Ext.encode([task_id])
                        },
                        success: function (result) {
                            Ext.getBody().unmask();

                            var resultDecoded = Ext.decode(result.responseText);
                            if (resultDecoded.success) {
                                Ext.simpleConfirmation.msg('Info', 'Task marked as uncomplete');
                                grid.store.load();

                                // Always reload tasks list on the home page
                                var oDashboardContainer = Ext.getCmp('dashboard-container');
                                if (oDashboardContainer) {
                                    oDashboardContainer.reloadBlockInfo('tasks');
                                }
                            } else {
                                Ext.simpleConfirmation.error(resultDecoded.msg);
                            }
                        },
                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error('An error occurred. Please try again later');
                        }
                    });
                }
            }
        );
    },

    setFilter: function (activeStatusFilter) {
        var thisToolbar = this;
        thisToolbar.filterMenu.items.each(function (oItem) {
            if (typeof oItem.name !== 'undefined' && oItem.name == 'task-filter-status' && oItem.value == activeStatusFilter) {
                thisToolbar.filterTasks(oItem);
            }
        });
    },

    filterTasks: function (item) {
        if (!item.checked) {
            if (item.setChecked) {
                item.setChecked(true);
            }

            this.viewer.TasksGrid.store.load();
        }


        // Update the label of the Filter button
        this.taskFilterBtn.setText(this.getActiveFilterLabel());

        if (empty(this.viewer.clientId)) {
            var mainTabPanel = Ext.getCmp('my-tasks-tab-panel');
            if (mainTabPanel) {
                var strTabTitle = 'My Tasks';
                if (this.selectedUserCombo && parseInt(this.selectedUserCombo.getValue(), 10) !== parseInt(curr_member_id, 10)) {
                    strTabTitle = _('Tasks for ') + this.selectedUserCombo.getRawValue();
                }
                mainTabPanel.getActiveTab().setTitle(strTabTitle);
            }
        }

        // @NOTE: please change these settings to control
        // if menu must be hidden after user action (radio/combobox selection)
        var booCloseMenu = true;
        if (booCloseMenu && item.getXType() === 'combo') {
            // Manually hide menu
            this.filterMenu.hide();
        }

        return booCloseMenu;
    },

    changeParticipants: function (companyId) {
        var booShowClient = false;
        var clientId = this.viewer.clientId;

        if (empty(clientId)) {
            var sel = this.viewer.TasksGrid.getSelectionModel().getSelected();
            if (sel && sel.data && sel.data['client_type'] && sel.data['client_type'] === 'client') {
                clientId = sel.data['member_id'];
                booShowClient = true;
            }
        }

        var win = new NewTaskDialog(this, {booShowClient: booShowClient, member_id: clientId, booProspect: this.viewer.booProspect, booEditMode: true, company_id: companyId});
        win.showTaskDialog();
    },

    setPriority: function (item, booChecked) {
        if (booChecked) {
            var priority = item.pr_value;

            var grid = this.viewer.TasksGrid;
            var task_ids = this.viewer.TasksGrid.getSelectedTaskIds();

            if (task_ids.length <= 0) { // :)
                return;
            }

            Ext.getBody().mask('Updating...');

            Ext.Ajax.request({
                url: topBaseUrl + '/tasks/index/change-priority/',
                params: {
                    task_ids: Ext.encode(task_ids),
                    priority: priority
                },
                success: function (result) {
                    Ext.getBody().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        Ext.simpleConfirmation.msg('Info', 'Personal priority changed');
                        grid.store.load();
                    } else {
                        Ext.simpleConfirmation.error(resultDecoded.msg);
                    }

                    // uncheck
                    Ext.getCmp(item.id).setChecked(false);
                },
                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error('An error occurred. Please try again later');

                    // uncheck
                    Ext.getCmp(item.id).setChecked(false);
                }
            });
        }
    },

    deleteTask: function () {
        var grid = this.viewer.TasksGrid;
        var selRecords = grid.getSelectionModel().getSelections();

        if (selRecords.length <= 0) {
            return;
        }

        //Create parameter to prevent tasks deleting with url if Uniques rule forbid this
        var tasks_type;
        if (this.viewer.clientId) {
            if (this.viewer.booProspect) {
                tasks_type = 'prospects';
            } else {
                tasks_type = 'clients';
            }
        } else {
            tasks_type = 'tasks';
        }

        var msg = String.format(
            'Are you sure you want to delete <span style="font-weight: bold; font-style: italic;">{0}</span> {1}?',
            selRecords.length === 1 ? selRecords[0]['data']['task_subject'] : selRecords.length,
            selRecords.length === 1 ? 'task' : 'tasks'
        );

        Ext.Msg.confirm(
            'Please confirm',
            msg,
            function (btn) {
                if (btn === 'yes') {
                    var task_ids = [];
                    for (var i = 0; i < selRecords.length; i++) {
                        task_ids.push(selRecords[i]['data'].task_id);
                    }

                    Ext.getBody().mask('Updating...');

                    Ext.Ajax.request({
                        url: topBaseUrl + '/tasks/index/delete/',
                        params: {
                            task_ids: Ext.encode(task_ids),
                            tasks_type: Ext.encode(tasks_type)
                        },
                        success: function (result) {
                            Ext.getBody().unmask();

                            var resultDecoded = Ext.decode(result.responseText);
                            if (resultDecoded.success) {
                                Ext.simpleConfirmation.msg('Info', 'Task' + (task_ids.length === 1 ? '' : 's') + ' was successfully deleted');
                                grid.store.load();

                                // Always reload tasks list on the home page
                                var oDashboardContainer = Ext.getCmp('dashboard-container');
                                if (oDashboardContainer) {
                                    oDashboardContainer.reloadBlockInfo('tasks');
                                }
                            } else {
                                Ext.simpleConfirmation.error(resultDecoded.msg);
                            }
                        },
                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error('An error occurred. Please try again later');
                        }
                    });
                }
            }
        );
    },
    selectColumns: function (viewer) {
        var cm = this.viewer.TasksGrid.getColumnModel();
        var arrColumnsMenu = [];
        Ext.each(cm.columns, function (oColumn, index) {
            if (empty(index)) {
                return;
            }

            arrColumnsMenu.push({
                xtype: 'menucheckitem',
                text: oColumn.header,
                checked: !oColumn.hidden,
                hideOnClick: false,
                checkHandler: function (item, e) {
                    cm.setHidden(cm.findColumnIndex(oColumn.dataIndex), !e);
                }
            });
        });

        var contextMenu = new Ext.menu.Menu({
            enableScrolling: false,
            items: arrColumnsMenu
        });
        var btn = Ext.getCmp('select-columns');
        contextMenu.showAt([btn.getEl().getX(), btn.getEl().getY() + btn.getHeight()]);
    }
});

Ext.reg('appTaskToolbar', TasksToolbar);