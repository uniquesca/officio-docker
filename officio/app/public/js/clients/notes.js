var ActivitiesGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;
    this.booHiddenTaskAdd = true;
    this.booHiddenNotesAdd = true;
    this.booHiddenNotesEdit = true;
    this.booHiddenNotesDelete = true;
    if (config.userType === 'prospect') {
        if (typeof arrProspectSettings != 'undefined') {
            var arrProspectsAccessRights = arrProspectSettings.arrAccess['prospects'].tabs;
            this.booHiddenTaskAdd = !arrProspectsAccessRights.tasks.add;
            this.booHiddenNotesAdd = !arrProspectsAccessRights.notes.add;
            this.booHiddenNotesEdit = !arrProspectsAccessRights.notes.edit;
            this.booHiddenNotesDelete = !arrProspectsAccessRights.notes.delete;
        }
    } else {
        this.booHiddenTaskAdd = !arrNotesToolbarOptions['access'].has('booHiddenTaskAdd');
        this.booHiddenNotesAdd = !arrNotesToolbarOptions['access'].has('booHiddenNotesAdd');
        this.booHiddenNotesEdit = !arrNotesToolbarOptions['access'].has('booHiddenNotesEdit');
        this.booHiddenNotesDelete = !arrNotesToolbarOptions['access'].has('booHiddenNotesDelete');
    }

    if (is_client) {
        this.booShowToolbar = !this.booHiddenNotesAdd || !this.booHiddenNotesEdit || !this.booHiddenNotesDelete;
    } else {
        this.booShowToolbar = !this.booHiddenTaskAdd || !this.booHiddenNotesAdd || !this.booHiddenNotesEdit || !this.booHiddenNotesDelete;
    }

    this.rowExpander = new Ext.ux.grid.RowExpander({
        tplContent: new Ext.Template('<p>{rec_tasks_content}</p>'),
        tplAuthor: new Ext.Template('<p>{rec_tasks_author}</p>'),
        tplDate: new Ext.Template('<p>{rec_tasks_date}</p>'),
        renderer: function (v, p, record) {
            if (!empty(record.get('rec_tasks_content'))) {
                p.cellAttr = 'rowspan="2"';
                return '<div class="x-grid3-row-expander"></div>';
            } else {
                p.id = '';
                return '&#160;';
            }
        },
        expandOnEnter: false,
        expandOnDblClick: false
    });
    this.rowExpander.on('expand', this.fixParentTabHeight, this);
    this.rowExpander.on('collapse', this.fixParentTabHeight, this);

    var viewer = {
        clientId: config.member_id,
        booProspect: this.userType == 'prospect',
        TasksGrid: this
    };
    this.tasksTB = new TasksToolbar(viewer, {});

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.rowExpander,
            {
                header: '',
                dataIndex: 'rec_type',
                align: 'center',
                width: 25,
                fixed: true,
                renderer: function (value, p, record) {
                    var img, title;
                    switch (record.data.rec_type) {
                        case 'task':
                            img = 'las la-clipboard';
                            title = 'Active task';
                            break;

                        case 'task_complete':
                            img = 'las la-clipboard-check';
                            title = 'Task complete';
                            break;

                        default:
                            img = 'lar la-sticky-note';
                            title = 'Note';
                    }

                    return String.format('<i class="{0}" title="{1}" style="font-size: 18px"></i>', img, title);
                }
            }, {
                id: 'message',
                header: 'Message',
                dataIndex: 'message',
                width: 250,
                renderer: function (value, p, record) {
                    var is_due = record.data.task_is_due !== 'N' || record.data.task_completed === 'Y';
                    var task_due_on = record.data.task_due_on ? new Date(record.data.task_due_on) : null;
                    var to_be_triggered = task_due_on ? '. To be triggered on ' + task_due_on.format(dateFormatFull) : '';

                    return String.format(
                        "<p style='white-space: normal !important; color:{0};' {1}>{2}{3}</p>",
                        is_due ? 'black' : 'gray',
                        record.data.rtl ? "class='rtl'" : '',
                        value,
                        is_due ? '' : ' <span style="color:red;">(Task not yet active' + to_be_triggered + ')</span>'
                    );
                }
            }, {
                id: 'has-attachment',
                header: '<div class="note-with-attachment">&nbsp;</div>',
                dataIndex: 'has_attachment',
                width: 40,
                renderer: this.formatAttachment,
                sortable: true,
                fixed: true
            }, {
                id: 'posted-by',
                header: 'Posted by',
                dataIndex: 'author',
                width: 200,
                fixed: true
            }, {
                id: 'posted-on',
                header: 'Posted on',
                dataIndex: 'date',
                renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
                width: 200,
                fixed: true
            }, {
                id: 'visible-to-clients',
                header: 'Visible to Client',
                dataIndex: 'visible_to_clients',
                width: 150,
                align: 'center',
                hidden: is_client || ['prospect', 'superadmin'].has(this.userType),
                fixed: true
            }
        ],
        defaultSortable: true
    });

    var thisArrTaskFields = [];
    if (typeof arrTaskFields !== 'undefined') {
        thisArrTaskFields = arrTaskFields;
    }

    var arrNoteFields = [
        {name: 'rec_type'},
        {name: 'rec_additional_info'},
        {name: 'rec_tasks_content'},
        {name: 'rec_tasks_author'},
        {name: 'rec_tasks_date'},
        {name: 'rec_id', type: 'int'},
        {name: 'message', type: 'string'},
        {name: 'date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'author', type: 'string'},
        {name: 'visible_to_clients', type: 'string'},
        {name: 'has_attachment', type: 'boolean'},
        {name: 'file_attachments'},
        {name: 'rtl', type: 'boolean'},
        {name: 'is_system', type: 'boolean'},
        {name: 'allow_edit', type: 'boolean'}
    ];

    var arrFields = arrNoteFields.concat(thisArrTaskFields);
    var TransactionRecord = Ext.data.Record.create(arrFields);

    this.store = new Ext.data.Store({
        url: this.userType == 'prospect' ? topBaseUrl + '/prospects/index/get-notes' : topBaseUrl + '/notes/index/get-notes',
        autoLoad: !!config.storeAutoLoad,
        remoteSort: true,
        baseParams: {
            member_id: this.member_id,
            company_id: this.company_id,
            start: 0,
            limit: this.notesOnPage,
            type: Ext.encode(this.tabType)
        },
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, TransactionRecord)
    });
    this.store.on('beforeload', this.applyParams.createDelegate(this));
    this.store.on('load', this.toggleBottomBar, this);
    this.store.setDefaultSort('date', 'DESC');

    if (this.booShowToolbar) {
        var tooltip = this.userType == 'prospect' ? _('Print all visible notes for this prospect.') : _('Print all visible notes for this client.');

        this.tbar = [
            {
                text: '<i class="las la-plus"></i>' + _('New Note'),
                cls: 'main-btn',
                hidden: this.booHiddenNotesAdd,
                handler: this.addNote.createDelegate(this)
            }, {
                text: String.format('<i class="las la-print" ext:qtip="{0}"></i>', tooltip) + _('Print'),
                tooltip: tooltip,
                handler: this.printNotes.createDelegate(this)
            }, {
                text: '<i class="las la-reply"></i>' + _('Reply Task'),
                ref: '../replyTaskBtn',
                hidden: true,
                disabled: true,
                handler: this.replyTask.createDelegate(this)
            }, {
                text: '<i class="las la-check"></i>' + _('Complete Task'),
                ref: '../completeTaskBtn',
                hidden: true,
                disabled: true,
                handler: this.completeTask.createDelegate(this)
            }, {
                text: '<i class="lar la-edit"></i>' + _('Edit Task'),
                ref: '../editTaskPropertiesBtn',
                hidden: true,
                disabled: true,
                handler: this.editTaskProperties.createDelegate(this)
            }, {
                text: '<i class="lar la-edit"></i>' + _('Edit Note'),
                ref: '../editNoteBtn',
                hidden: true,
                disabled: true,
                handler: this.editNote.createDelegate(this)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Note'),
                ref: '../deleteNoteBtn',
                hidden: true,
                disabled: true,
                handler: this.deleteNote.createDelegate(this)
            }, '->', {
                xtype: 'container',
                layout: 'column',
                cls: 'secondary-btn',
                hidden: thisGrid.userType == 'prospect',
                items: [
                    {
                        xtype: 'checkbox',
                        ref: '../../showSystemNotesCheckbox',
                        hideLabel: true,
                        checked: Ext.state.Manager.get('notesShowSystemNotes'),
                        listeners: {
                            'check': function (checkbox, booChecked) {
                                if (booChecked) {
                                    Ext.state.Manager.set('notesShowSystemNotes', booChecked);
                                } else {
                                    Ext.state.Manager.clear('notesShowSystemNotes');
                                }

                                thisGrid.refreshList();
                            }
                        }
                    }, {
                        text: arrNotesToolbarOptions.systemLogsCheckboxLabel,
                        xtype: 'button',
                        style: 'margin-top: 5px',
                        tooltip: _('See the note-specific system activities on your Officio account.'),
                        handler: function () {
                            thisGrid.showSystemNotesCheckbox.setValue(!thisGrid.showSystemNotesCheckbox.getValue());
                        }
                    }
                ],
                listeners: {
                    'render': function () {
                        var metrics = Ext.util.TextMetrics.createInstance(this.getEl());
                        this.setWidth(metrics.getWidth(arrNotesToolbarOptions.systemLogsCheckboxLabel) + 95);
                    }
                }
            }, {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                handler: this.refreshList.createDelegate(this)
            }, {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    var helpContextId = '';
                    switch (config.panelType) {
                        case 'prospects':
                            helpContextId = 'prospects-file-notes';
                            break;

                        case 'marketplace':
                            helpContextId = 'marketplace-file-notes';
                            break;

                        default:
                            helpContextId = 'clients-file-notes';
                            break;
                    }

                    showHelpContextMenu(this.getEl(), helpContextId);
                }
            }
        ];
    }

    this.bbar = new Ext.PagingToolbar({
        pageSize: this.notesOnPage,
        hidden: true,
        store: this.store
    });

    ActivitiesGrid.superclass.constructor.call(this, {
        id: this.userType == 'prospect' ? 'prospect-notes-grid-' + this.member_id : this.tabType + '-notes-grid-' + this.member_id,
        stateId: this.userType == 'prospect' ? 'prospect-notes-grid' : this.tabType + '-notes-grid',
        cls: 'extjs-grid',
        plugins: this.rowExpander,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        split: true,
        stripeRows: true,
        autoExpandColumn: 'message',
        autoExpandMin: 150,
        loadMask: true,
        buttonAlign: 'left',
        autoScroll: true,
        listeners: {
            dblclick: {
                fn: function () {
                    var records = this.getSelectionModel().getSelections();
                    if (records.length !== 1) {
                        Ext.simpleConfirmation.msg(_('Info'), _('Please select one record to edit'));
                        return;
                    }

                    if (records[0]['data']['rec_type'] === 'task') {
                        if (this.editTaskPropertiesBtn && this.editTaskPropertiesBtn.isVisible() && !this.editTaskPropertiesBtn.disabled) {
                            this.editTaskProperties();
                        }
                    } else {
                        if (this.editNoteBtn && this.editNoteBtn.isVisible() && !this.editNoteBtn.disabled) {
                            this.editNote();
                        }
                    }
                },
                // You can also pass 'body' if you don't want click on the header or
                // docked elements
                element: 'el',
                scope: this
            }
        },

        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No notes/tasks found.',
            forceFit: true
        }
    });

    // Automatically load array of activities
    if (typeof arrClientNotes != 'undefined' && !empty(arrClientNotes) && this.userType != 'prospect') {
        this.store.loadData(arrClientNotes);
    }

    this.on('cellcontextmenu', this.onCellRightClick, this);
    this.on('contextmenu', function (e) {
        e.preventDefault();
    }, this);

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(ActivitiesGrid, Ext.grid.GridPanel, {
    applyParams: function(store, options) {
        options.params = options.params || {};

        var params = {
            show_system_records: this.userType == 'prospect' ? 0 : (this.showSystemNotesCheckbox && this.showSystemNotesCheckbox.getValue() ? 1 : 0),
        };

        Ext.apply(options.params, params);
    },

    toggleBottomBar: function () {
        if (this.store.getTotalCount() > this.notesOnPage) {
            this.getBottomToolbar().show();
        }

        this.fixParentTabHeight();
    },

    fixParentTabHeight: function () {
        if (this.userType == 'prospect') {
            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        } else {
            var applicantTab = Ext.getCmp(this.panelType + '-tab-' + this.applicantId + '-' + this.member_id);
            if (applicantTab) {
                applicantTab.items.get(0).owner.fixParentPanelHeight();
            }
        }
    },

    updateToolbarButtons: function () {
        var sm = this.getSelectionModel();
        if (this.booShowToolbar) {
            var booEnableNoteButtons = false;
            var booIsSystem = false;
            var booEnableTaskButtons = false;
            var booEnableTaskCompleteButton = false;
            var node = sm.getSelected();
            if (node && node.data) {
                booIsSystem = node.data.is_system;
                booEnableNoteButtons = node.data.allow_edit;
                booEnableTaskButtons = !empty(node.data.task_id) && !is_client;
                booEnableTaskCompleteButton = booEnableTaskButtons && node.data.task_completed !== 'Y';
            }

            // Note buttons
            this.editNoteBtn.setVisible(!this.booHiddenNotesEdit && booEnableNoteButtons);
            this.deleteNoteBtn.setVisible(!this.booHiddenNotesDelete && booEnableNoteButtons && !booIsSystem);

            this.editNoteBtn.setDisabled(!booEnableNoteButtons);
            this.deleteNoteBtn.setDisabled(!booEnableNoteButtons);

            // Task buttons
            this.replyTaskBtn.setVisible(booEnableTaskButtons);
            this.completeTaskBtn.setVisible(booEnableTaskButtons);
            this.editTaskPropertiesBtn.setVisible(booEnableTaskButtons);

            this.replyTaskBtn.setDisabled(!booEnableTaskCompleteButton);
            this.completeTaskBtn.setDisabled(!booEnableTaskCompleteButton);
            this.editTaskPropertiesBtn.setDisabled(!booEnableTaskCompleteButton);
        }
    },

    getSelections: function () {
        var sel = this.getSelectionModel().getSelections();
        if (sel) {
            var notes = [];
            for (var i = 0; i < sel.length; i++) {
                notes.push(sel[i].data.rec_id);
            }

            return notes;
        }

        return [];
    },

    addNote: function () {
        note({
            action: 'add',
            member_id: this.member_id,
            company_id: this.company_id,
            type: this.userType,
            tabType: this.tabType
        });
    },

    editNote: function () {
        var records = this.getSelections();
        if (records.length === 0 || records.length > 1) {
            Ext.simpleConfirmation.msg('Info', 'Please select one note to edit');
            return;
        }

        note({
            action: 'edit',
            note_id: records[0],
            member_id: this.member_id,
            company_id: this.company_id,
            type: this.userType,
            tabType: this.tabType
        });
    },

    deleteNote: function () {
        var records = this.getSelections();
        if (records.length === 0) {
            Ext.simpleConfirmation.msg('Info', 'Please select a note(s) to delete');
            return;
        }

        note({
            action: 'delete',
            note_id: records[0],
            member_id: this.member_id,
            company_id: this.company_id,
            type: this.userType,
            tabType: this.tabType
        });
    },


    printNotes: function () {
        print($('#' + this.getId() + ' .x-grid3-body').html(), 'File Notes');
    },

    refreshList: function () {
        this.store.reload();
    },


    addTask: function () {
        var viewer = {
            viewer: {
                TasksGrid: this
            }
        };
        var win = new NewTaskDialog(viewer, {booShowClient: false, member_id: this.member_id, booProspect: false, company_id: this.company_id});
        win.showTaskDialog();
    },

    replyTask: function () {
        var viewer = {
            viewer: {
                TasksGrid: this
            }
        };
        var win = new TasksReplyDialog(viewer);
        win.show();
        win.center();
    },

    completeTask: function () {
        this.tasksTB.markComplete();
    },

    editTaskProperties: function () {
        this.tasksTB.changeParticipants(this.company_id);
    },

    viewOnTasksTab: function () {
        var viewer = {
            TasksGrid: this
        };

        var task_id = viewer.TasksGrid.getSelectionModel().getSelections()[0].data.task_id;

        setUrlHash('#tasks/' + task_id);
        setActivePage();
    },

    // Format attachment column. Show image, if has_attachment==true
    formatAttachment: function (has_attachment) {
        if (!has_attachment)
            return;

        return '<div class="note-with-attachment">&nbsp;</div>';
    },

    onCellRightClick: function (grid, index, cellIndex, e) {
        var rec = grid.store.getAt(index);
        var booIsNote = rec.data.rec_type === 'note';
        var booIsTaskCompleted = rec.data.task_completed === 'Y';

        var arrMenuItems = [
            {
                text: '<i class="las la-reply"></i>' + _('Reply Task'),
                hidden: booIsNote || is_client,
                disabled: booIsTaskCompleted,
                handler: this.replyTask.createDelegate(this)
            }, {
                text: '<i class="las la-check"></i>' + _('Complete Task'),
                hidden: booIsNote || is_client,
                disabled: booIsTaskCompleted,
                handler: this.completeTask.createDelegate(this)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit Task'),
                hidden: booIsNote || is_client,
                disabled: booIsTaskCompleted,
                handler: this.editTaskProperties.createDelegate(this)
            }, {
                text: '<i class="las la-search"></i>' + _('View in Tasks Window'),
                hidden: booIsNote || is_client || !empty(this.company_id),
                handler: this.viewOnTasksTab.createDelegate(this)
            }, {
                text: '<i class="las la-plus"></i>' + _('New Note'),
                hidden: !booIsNote || this.booHiddenNotesAdd,
                handler: this.addNote.createDelegate(this)
            }, {
                text: '<i class="las la-edit"></i>' + _('Edit Note'),
                hidden: !booIsNote || this.booHiddenNotesEdit || !rec.data.allow_edit,
                handler: this.editNote.createDelegate(this)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Note'),
                hidden: !booIsNote || this.booHiddenNotesDelete || !rec.data.allow_edit,
                handler: this.deleteNote.createDelegate(this)
            }, '-'
        ];

        if (rec.data.has_attachment && !empty(rec.data.file_attachments) && rec.data.file_attachments.length) {
            var arrAttachments = [];

            Ext.each(rec.data.file_attachments, function (v) {
                arrAttachments.push({
                    text: String.format(
                        '<span ext:qtip="{0}"><i class="las la-file-download"></i>{1}</span>',
                        _('Click to download ') + v['name'].replaceAll("'", "&#39;"),
                        v['name']
                    ),
                    handler: function () {
                        var downloadUrl = grid.userType == 'prospect' ? topBaseUrl + '/prospects/index/download-attachment' : topBaseUrl + '/notes/index/download-attachment';
                        var oParams = {
                            note_id: rec.data['rec_id'],
                            member_id: grid.member_id,
                            type: 'note_file_attachment',
                            attach_id: v['file_id'],
                            name: v['name']
                        };

                        submit_hidden_form(downloadUrl, oParams);
                    }
                });
            });

            arrMenuItems.push({
                text: '<i class="las la-paperclip"></i>' + (rec.data.file_attachments.length === 1 ? _('Download Attachment') : _('Download Attachments')),
                menu: arrAttachments
            });
            arrMenuItems.push('-');
        }

        arrMenuItems.push({
            text: '<i class="las la-print"></i>' + _('Print'),
            handler: this.printNotes.createDelegate(this)
        });

        arrMenuItems.push({
            text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
            handler: this.refreshList.createDelegate(this)
        });

        var menu = new Ext.menu.Menu({
            items: arrMenuItems
        });
        e.stopEvent();

        // Select row which was selected by right click
        grid.getSelectionModel().selectRow(index);

        menu.showAt(e.getXY());
    }
});
