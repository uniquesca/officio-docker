var ThreadsGrid = function(viewer, config) {
    this.viewer = viewer;
    Ext.apply(this, config);
    this.loadedTaskId = 0;
    this.currentSortingDirection = 'DESC';
    var threadsGrid = this;

    this.store = new Ext.data.Store({
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/tasks/index/get-threads-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'threads',
            totalProperty: 'count',
            idProperty: 'thread_id',
            fields: [
                'thread_id',
                'thread_content',
                {name: 'thread_created_on', type: 'date', dateFormat: Date.patterns.ISO8601Long},
                {name: 'thread_officio_said', type: 'int'},
                'thread_created_by'
            ]
        }),

        listeners: {
            'beforeload': function(store, options) {
                options.params = options.params || {};

                var sel = viewer.TasksGrid.getSelectionModel().getSelected();

                // data from the Grid
                var params = {
                    task_id:             sel.data.task_id,
                    show_system_records: viewer.TasksToolbar['taskShowDetailsCheckbox'].getValue() ? 1 : 0,
                    dir:                 threadsGrid.currentSortingDirection
                };

                Ext.apply(options.params, params);
            }
        }
    });
    this.store.setDefaultSort('timestamp', this.currentSortingDirection);

    this.columns = [{
        header:       '',
        dataIndex:    'thread_content',
        disabled:     true,
        menuDisabled: true,
        renderer:     this.formatThread.createDelegate(this)
    }];

    var genId = Ext.id();
    ThreadsGrid.superclass.constructor.call(this, {
        id: genId,
        cls: 'threads-grid',
        border: false,
        stripeRows: true,
        loadMask: {msg: _('Loading...')},
        viewConfig: {
            deferEmptyText: _('Please select one task to see its details'),
            emptyText: _('Please select one task to see its details'),
            forceFit: true,

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 0,

            onLayout: function(){
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

    this.getSelectionModel().on('beforerowselect', function () {
        return false;
    });

    this.on('render', this.loadList, this);
};

Ext.extend(ThreadsGrid, Ext.grid.GridPanel, {
    generateRecipients: function(arrUsers) {
        var strUsers = '';
        var strExtra = '';
        var extraCount = 0;

        var showMaxUsers = 4;
        showMaxUsers = arrUsers.length === showMaxUsers ? showMaxUsers : showMaxUsers - 1;

        var showExtraUsersInRow = 2;
        if(arrUsers && arrUsers.length) {
            for (var i = 0; i < arrUsers.length; i++) {
                if(i < showMaxUsers) {
                    strUsers += empty(strUsers) ? '' : ', ';
                    strUsers += arrUsers[i][1];
                } else {
                    strExtra += empty(strExtra) ? '' : ', ';
                    strExtra += !empty(extraCount) && empty(extraCount % showExtraUsersInRow) ? '<br/>' : '';
                    strExtra += arrUsers[i][1];
                    extraCount++;
                }
            }

            if (arrUsers.length > showMaxUsers) {
                strUsers += String.format(
                    ' and <span class="thread-header-more" ext:qtip="{0}" ext:qwidth="350">{1} other{2}</span>',
                    strExtra,
                    arrUsers.length - showMaxUsers,
                    arrUsers.length - showMaxUsers === 1 ? '' : 's'
                );
            }
        }

        return strUsers;
    },

    loadList: function(booForceLoad, booDontReloadStore) {
        var selModel = this.viewer.TasksGrid.getSelectionModel();
        var view = this.getView();

        if (selModel.getCount() === 1) {
            var selRow = selModel.getSelected();
            if (booForceLoad || this.loadedTaskId != selRow.data['task_id']) {
                var strCc = this.generateRecipients(selRow.data['task_assigned_cc']);
                var strTo = this.generateRecipients(selRow.data['task_assigned_to']);

                var booHideDeadline = empty(selRow.data['task_deadline']) || selRow.data['task_completed'] === 'Y';

                var sortingButtonId = Ext.id();
                var editTaskLinkId = Ext.id();
                var replyTaskLinkId = Ext.id();
                var markTaskAsCompleteLinkId = Ext.id();
                var deleteTaskLinkId = Ext.id();
                var activateLabel = '';
                if (selRow.data['task_type'] == 'S' || selRow.data['task_type'] == 'B' || selRow.data['task_type'] == 'C') {
                    activateLabel = 'on ' + selRow.data['task_due_on'].format(Date.patterns.UniquesShort);
                } else if (selRow.data['task_type'] == 'P') {
                    activateLabel = 'in ' + selRow.data['task_days_count'] + ' ' + selRow.data['task_days_type'].toLowerCase() + ' days ' + selRow.data['task_days_when'].toLowerCase() + ' ' + selRow.data['task_profile_field_label'];
                }

                if (selRow.data['task_type'] == 'B') {
                    activateLabel += ' (Business days)';
                } else if (selRow.data['task_type'] == 'C') {
                    activateLabel += ' (Calendar days)';
                }

                var newHeader = String.format(
                    '<table class="thread-header" cellpadding="0" cellspacing="0">' +
                        '<tr>' +
                            '<td class="thread-header-title" style="width: 90px">Task:</td>' +
                            '<td colspan="2" style="overflow: hidden">{0}</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td class="thread-header-title">Assigned to:</td>' +
                            '<td colspan="2" style="overflow: hidden">{4}</td>' +
                        '</tr>' +
                        '<tr>' +
                            '<td class="thread-header-title" style="{7}">CC:</td>' +
                            '<td colspan="2" style="{7} overflow: hidden">{8}</td>' +
                        '</tr>' +

                        '<tr>' +
                            '<td class="thread-header-title">Due on:</td>' +
                            '<td>{2}</td>' +
                            '<td style="{5} text-align: right;"><span class="thread-header-title">Complete by:</span>{6}</td>' +
                        '</tr>' +

                        '<tr style="{9}">' +
                            '<td colspan="3" class="thread-header-title">Generated by Auto Task</td>' +
                        '</tr>' +

                        '<tr>' +
                            '<td colspan="2" style="vertical-align: middle; background-color: #FFF; padding: 10px">' +
                                '<span id="{11}-placeholder"></span>' +
                                '<span id="{12}-placeholder"></span>' +
                                '<span id="{10}-placeholder"></span>' +
                                '<span id="{13}-placeholder"></span>' +
                            '</td>' +
                            '<td style="text-align: right; background-color: #FFF; padding: 10px">' +
                                '<span id="{3}-placeholder" style="display: block; float: right"></span>' +
                            '</td>' +
                        '</tr>' +

                        '<tr>' +
                            '<td colspan="3" style="vertical-align: top; background-color: #FFF;">' +
                                '<div style="border-top: 1px solid #E9EAEC; margin: 5px 0 10px 0; line-height: 0">&nbsp;</div>' +
                            '</td>' +
                        '</tr>' +
                    '</table>',
                    selRow.data['task_subject'],
                    sortingButtonId,
                    activateLabel,
                    sortingButtonId,
                    strTo,
                    booHideDeadline ? 'display: none;' : '',
                    booHideDeadline ? '' : 'on ' + selRow.data['task_deadline'].format(Date.patterns.UniquesShort),
                    empty(strCc) ? 'display: none;' : '',
                    strCc,
                    empty(selRow.data['auto_task_type']) ? 'display: none;' : '',
                    editTaskLinkId,
                    replyTaskLinkId,
                    markTaskAsCompleteLinkId,
                    deleteTaskLinkId
                );
                this.getColumnModel().setColumnHeader(0, newHeader);

                var thisThreadsGrid = this;
                var booIsTaskCompleted = selRow.data['task_completed'] === 'Y';
                var booIsReadPermission = selRow.data['task_read_permission'];
                var booIsFullPermission = selRow.data['task_full_permission'];

                // Edit Task link
                new Ext.BoxComponent({
                    renderTo: editTaskLinkId + '-placeholder',
                    disabled: booIsTaskCompleted,

                    autoEl: {
                        tag: 'a',
                        href: '#',
                        style: 'margin-left: 20px;',
                        html: '<i class="lar la-edit"></i>' + _('Edit Task')
                    },

                    listeners: {
                        scope:  this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                if (!c.disabled) {
                                    thisThreadsGrid.viewer.TasksToolbar.changeParticipants();
                                }
                            }, this, {stopEvent: true});
                        }
                    }
                });

                // Reply Task link
                new Ext.BoxComponent({
                    renderTo: replyTaskLinkId + '-placeholder',
                    disabled: booIsTaskCompleted || !booIsReadPermission,

                    autoEl: {
                        tag: 'a',
                        href: '#',
                        'class': 'secondary-btn',
                        html: '<i class="las la-reply"></i>' + _('Reply')
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                if (!c.disabled) {
                                    thisThreadsGrid.viewer.TasksToolbar.replyTask();
                                }
                            }, this, {stopEvent: true});
                        }
                    }
                });

                // Mark as complete link
                new Ext.BoxComponent({
                    renderTo: markTaskAsCompleteLinkId + '-placeholder',
                    disabled: booIsTaskCompleted || !booIsFullPermission,

                    autoEl: {
                        tag: 'a',
                        href: '#',
                        style: 'margin-left: 20px;',
                        html: '<i class="las la-check"></i>' + _('Mark as complete')
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                if (!c.disabled) {
                                    thisThreadsGrid.viewer.TasksToolbar.markComplete();
                                }
                            }, this, {stopEvent: true});
                        }
                    }
                });

                // Delete link
                new Ext.BoxComponent({
                    renderTo: deleteTaskLinkId + '-placeholder',
                    hidden: thisThreadsGrid.viewer.TasksToolbar.booDeleteButtonHidden,
                    disabled: !booIsFullPermission,

                    autoEl: {
                        tag: 'a',
                        href: '#',
                        style: 'margin-left: 20px;',
                        html: '<i class="las la-trash"></i>' + _('Delete')
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                if (!c.disabled) {
                                    thisThreadsGrid.viewer.TasksToolbar.deleteTask();
                                }
                            }, this, {stopEvent: true});
                        }
                    }
                });

                // Generate sorting button
                new Ext.Button({
                    renderTo: sortingButtonId + '-placeholder',
                    text: this.getSortingText(this.currentSortingDirection.toLowerCase() === 'asc'),
                    cls: 'secondary-btn',
                    handler: this.changeSorting.createDelegate(this)
                });

                // Show column header
                view.el.select('.x-grid3-header').setStyle('display', 'block');
                view.el.select('.x-grid3-scroller').removeClass('empty');
                view.deferEmptyText = 'Thread list is empty';
                view.emptyText = 'Thread list is empty';

                if (!booDontReloadStore)
                    this.store.load();

                this.loadedTaskId = selRow.data['task_id'];
            }
        } else {
            // Hide column header
            view.el.select('.x-grid3-header').setStyle('display', 'none');
            view.el.select('.x-grid3-scroller').addClass('empty');
            view.deferEmptyText = _('Please select one task to see its details');
            view.emptyText = _('Please select one task to see its details');

            this.store.removeAll();
            this.loadedTaskId = 0;
        }
    },

    getSortingText: function(booAsc) {
        return String.format(
            _('<span style="font-weight: normal;">Sort by:</span> <span style="font-weight: 500">Date</span> {0}'),
            booAsc ? '<i class="las la-angle-down"></i>' : '<i class="las la-angle-up"></i>'
        );
    },

    changeSorting: function(btn) {
        this.currentSortingDirection = this.currentSortingDirection === 'DESC' ? 'ASC' : 'DESC';
        btn.setText(this.getSortingText(this.currentSortingDirection.toLowerCase() === 'asc'));
        this.store.reload();
    },

    formatThread: function(subject, p, record) {
        var sub = subject ? (/<[a-z][\s\S]*>/i.test(subject) ? subject : Ext.util.Format.nl2br(subject)) : '(no subject)';

        var message = '';
        if (record.data['thread_officio_said']) {
            message = sub;
        } else {
            message = record.data['thread_created_by'];
            if (!empty(sub)) {
                message += ': ' + sub;
            }
        }

        return String.format(
            '<table style="width: 100%; margin: 5px">' +
                '<tr>' +
                    '<td>{0}</td>' +
                    '<td style="text-align:right; padding-right: 20px;">{1}&nbsp;&nbsp;{2}</td>' +
                '</tr>' +
            '</table>',
            message,
            empty(record.data['thread_created_on']) ? '' : record.data['thread_created_on'].format(Date.patterns.UniquesShort),
            empty(record.data['thread_created_on']) ? '' : record.data['thread_created_on'].format(Date.patterns.UniquesTime)
        );
    }
});

Ext.reg('appThreadsGrid', ThreadsGrid);