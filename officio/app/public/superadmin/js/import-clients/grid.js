var ImportClientNotesGrid = function (config) {
    Ext.apply(this, config);

    // Init basic properties
    this.store = new Ext.data.Store({
        url: baseUrl + '/import-client-notes/get-files',
        method: 'POST',
        autoLoad: false,
        remoteSort: false,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'file_id',
            fields: [
                {name: 'file_id', type: 'int'},
                {name: 'file_name', type: 'string'}
            ]
        }),

        sortInfo: {
            field: 'file_name',
            direction: "DESC"
        }
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        this.sm,
        {
            id: 'col_file_name',
            header: _('File Name'),
            sortable: true,
            dataIndex: 'file_name',
            width: 50
        }
    ];

    this.importNotesBtn = new Ext.Button({
        text: '<i class="las la-file-import"></i>' + _('Import Notes'),
        handler: this.runImport.createDelegate(this),
        disabled: true
    });

    this.tbar = [
        this.importNotesBtn,
        {
            text: '<i class="las la-file-upload"></i>' + _('New Import File'),
            handler: function () {
                Ext.simpleConfirmation.warning(_('You can import additional files by going to "Import Clients" and uploading an Excel file.'));
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 25,
        store: this.store,
        displayInfo: true,
        emptyMsg: _('No files to display')
    });

    ImportClientNotesGrid.superclass.constructor.call(this, {
        autoWidth:        true,
        height:           getSuperadminPanelHeight() - 30,
        autoExpandColumn: 'col_file_name',
        stripeRows:       true,
        cls:              'extjs-grid',
        viewConfig: {
            deferEmptyText: false,
            emptyText: _('No files were found. Please upload xls file in the Import Clients tab.'),
            forceFit: true
        },
        loadMask:         true
    });

    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
    this.on('afterrender', this.initDataLoad, this);
};

Ext.extend(ImportClientNotesGrid, Ext.grid.GridPanel, {
    initDataLoad: function () {
        var oThis = this;
        oThis.store.loadData(arrFiles);

        if (selectedFileId) {
            setTimeout(function() {
                var rec = oThis.store.getById(selectedFileId);
                if (rec) {
                    oThis.getSelectionModel().selectRecords([rec]);
                }
            }, 50);
        }

    },

    updateToolbarButtons: function (sm) {
        var filesCount = sm.getCount();
        if (filesCount === 1) {
            this.importNotesBtn.enable();
        } else {
            this.importNotesBtn.disable();
        }
    },

    runImport: function () {
        var oSelected = this.getSelectionModel().getSelected();
        if (oSelected) {
            window.location = baseUrl + '/import-client-notes/step2?file_id=' + oSelected.data.file_id;
        }
    }
});