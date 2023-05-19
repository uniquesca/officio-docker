var DocumentsChecklistTagPercentageDialog = function (config, parent) {
    var thisDialog = this;

    Ext.apply(this, config);
    this.parent = parent;

    var tagStore = new Ext.data.Store({
        sortInfo: {field: "percentage", direction: "DESC"},
        data: config.tags,
        reader: new Ext.data.JsonReader({id: 'tag'}, Ext.data.Record.create([
            {name: 'tag'},
            {name: 'percentage', type:'float'}
        ]))
    });

    var grid = new Ext.grid.GridPanel({
        store: tagStore,
        cm: new Ext.grid.ColumnModel({
            defaults: {
                menuDisabled: true,
                sortable: true
            },

            columns: [
                {
                    header: _('Tag'),
                    dataIndex: 'tag',
                    width: 180
                }, {
                    header: _('Percentage'),
                    dataIndex: 'percentage',
                    width: 150,
                    renderer: this.formatPercentage.createDelegate(this)
                }
            ]
        }),
        sm: new Ext.grid.RowSelectionModel({singleSelect:true}),

        stripeRows: true,
        autoWidth: true,
        autoHeight: true
    });

    DocumentsChecklistTagsDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-percent"></i>' + _('Tag Percentage'),
        closeAction: 'close',
        modal:       true,
        resizable:   false,
        autoHeight:  true,
        autoWidth:   true,
        layout:      'form',
        bodyStyle:   'padding: 5px',

        items: grid,

        buttons: [
            {
                text: _('Close'),
                cls: 'orange-btn',
                handler: this.closeDialog.createDelegate(this)
            }
        ]
    });
};

Ext.extend(DocumentsChecklistTagPercentageDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    formatPercentage: function (percentage) {
        return percentage + '%';
    }

});