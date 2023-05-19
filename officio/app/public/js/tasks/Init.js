var initTasks = function (taskId, activeStatusFilter) {
    var divId = 'general-tasks-container';

    var el = $('#' + divId);
    if (el.length) {
        // Clear loading image
        el.empty();

        var oTasksPanel = new TasksPanel({
            title: _('My Tasks'), // Can be changed in the tasks/toolbar.js
            taskId: taskId,
            activeStatusFilter: activeStatusFilter,
            autoWidth: true,
            height: typeof (is_superadmin) === 'undefined' ? initPanelSize() - 27 : initPanelSize() - 55
        });

        var cpan = new Ext.TabPanel({
            id: 'my-tasks-tab-panel',
            renderTo: divId,
            autoWidth: true,
            autoHeight: true,
            plain: true,
            activeTab: 0,
            enableTabScroll: true,
            minTabWidth: 200,
            cls: 'clients-tab-panel',
            bodyStyle: typeof (is_superadmin) === 'undefined' ? 'padding: 10px' : 'padding: 10px 10px 0 10px',

            plugins: [
                new Ext.ux.TabUniquesNavigationMenu({}),

                new Ext.ux.TabCustomRightSection({
                    additionalCls: 'x-tab-tabmenu-right-section-with-right-margin',
                    arrComponentsToRender: [{
                        xtype: 'button',
                        text: '<i class="las la-plus"></i>' + _('New Task'),
                        ctCls: 'orange-btn',
                        handler: function () {
                            oTasksPanel.TasksToolbar.createTask();
                        }
                    }]
                })
            ],

            items: oTasksPanel
        });

        cpan.doLayout();
    }
};
