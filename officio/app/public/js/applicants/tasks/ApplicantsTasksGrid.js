ApplicantsTasksGrid = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-tasks-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'taskId',
            fields: [
                'taskId',
                'taskName',
                'applicantId',
                'applicantName',
                'applicantType',
                'caseId',
                'caseName',
                'caseType',
                'caseAndClientName',
                'caseEmployerId',
                'caseEmployerName'
            ]
        })
    });
    this.store.setDefaultSort('taskId', 'DESC');

    var subjectColId = Ext.id();
    this.columns = [
        {
            id: subjectColId,
            header: _('Name'),
            dataIndex: 'taskName',
            sortable: true,
            width: 300,
            renderer: function(val, a, row) {
                return String.format(
                    '<a href="#" class="blulinkun norightclick" onclick="return false;" />{0}</a>' +
                    '<br>' +
                    '<a href="#" class="blulinkun norightclick" onclick="return false;" />{1}</a>',
                    row.data.taskName,
                    row.data.caseAndClientName
                );
            }
        }
    ];

    ApplicantsTasksGrid.superclass.constructor.call(this, {
        cls: 'no-borders-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        viewConfig: {
            emptyText: _('There are no tasks to show.')
        }
    });

    this.on('cellclick', this.onCellClick.createDelegate(this));
    this.getSelectionModel().on('beforerowselect', function(){ return false;}, this);
};

Ext.extend(ApplicantsTasksGrid, Ext.grid.GridPanel, {
    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var rec = this.store.getAt(rowIndex);
        if (e.getTarget().tagName == 'A') {
            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            tabPanel.openApplicantTab({
                applicantId:      rec.data.applicantId,
                applicantName:    rec.data.applicantName,
                memberType:       rec.data.applicantType,
                caseId:           rec.data.caseId,
                caseName:         rec.data.caseName,
                caseType:         rec.data.caseType,
                caseEmployerId:   rec.data.caseEmployerId,
                caseEmployerName: rec.data.caseEmployerName
            }, 'tasks');
        }
    }
});