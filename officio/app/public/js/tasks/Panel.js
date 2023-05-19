var TasksPanel = function (config) {
    Ext.apply(this, config);

    var activeStatusFilter = 'active';
    if (typeof config.activeStatusFilter !== 'undefined') {
        activeStatusFilter = config.activeStatusFilter;
    }

    this.TasksToolbar = new TasksToolbar(this, {activeStatusFilter: activeStatusFilter});

    this.cookieName = empty(config.clientId) ? 'tasksGridWidth' : 'tasksClientsGridWidth';
    var defaultWidth = empty(config.clientId) ? 600 : 450;
    var tasksGridWidth = Ext.state.Manager.get(this.cookieName);

    this.TasksGrid = new TasksGrid(this, {
        region: 'west',
        split: true,
        width: tasksGridWidth ? tasksGridWidth : defaultWidth,
        booClientTab: !empty(config.clientId)
    });
    this.TasksGrid.on('resize', this.updateTasksGridWidth, this);

    this.ThreadsGrid = new ThreadsGrid(this, {
        region: 'center',
        layout: 'fit',
        booShowToolbar: true
    });

    var panelId = 'tasks-panel-global';
    if (config.clientId) {
        if (config.booProspect) {
            panelId = 'tasks-panel-prospect-' + config.clientId;
        } else {
            panelId = 'tasks-panel-client-' + config.clientId;
        }
    }
    TasksPanel.superclass.constructor.call(this, {
        id: panelId,
        layout: 'border',
        items: [
            {
                xtype: 'container',
                region: 'north',
                height: 55,
                items: this.TasksToolbar
            },
            this.TasksGrid,
            this.ThreadsGrid
        ]
    });
};

Ext.extend(TasksPanel, Ext.Panel, {
    updateTasksGridWidth: function (grid, width) {
        Ext.state.Manager.set(this.cookieName, width);
    }
});

Ext.reg('appTasksPanel', TasksPanel);
