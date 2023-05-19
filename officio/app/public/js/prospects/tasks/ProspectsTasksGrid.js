var ProspectsTasksGrid = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/prospects/index/get-done-tasks-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'task_id',
            fields: [
                'task_id',
                'task_subject',
                'member_id',
                'member_full_name'
            ]
        })
    });
    this.store.setDefaultSort('task_id', 'DESC');

    var subjectColId = Ext.id();
    this.columns = [
        {
            id: subjectColId,
            header: _('Name'),
            dataIndex: 'task_subject',
            sortable: true,
            width: 300,
            renderer: function(val, a, row) {
                return String.format(
                    '<a href="#" class="blulinkun norightclick" onclick="return false;" />{0}</a>' +
                    '<br>' +
                    '<a href="#" class="blulinkun norightclick" onclick="return false;" />{1}</a>',
                    row.data.task_subject,
                    row.data.member_full_name
                );
            }
        }
    ];

    ProspectsTasksGrid.superclass.constructor.call(this, {
        cls: 'no-borders-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        viewConfig: {
            emptyText: _('No tasks to display.')
        }
    });

    this.on('cellclick', this.onCellClick.createDelegate(this));
    this.getSelectionModel().on('beforerowselect', function(){ return false;}, this);
};

Ext.extend(ProspectsTasksGrid, Ext.grid.GridPanel, {
    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var rec = this.store.getAt(rowIndex);
        if (e.getTarget().tagName == 'A') {
            if (!empty(rec.data.member_id)) {
                var tabPanel = Ext.getCmp(grid.panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.openProspectTask(rec.data.member_id, rec.data.task_id);
                }
            } else {
                setUrlHash('#tasks/' + rec.data.task_id);
                setActivePage();
            }
        }
    }
});