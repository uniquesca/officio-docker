var ContextIdsGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;

    this.ContextIdRecord = Ext.data.Record.create([
        {
            name: 'faq_context_id',
            type: 'int'
        },
        {name: 'faq_context_id_text'},
        {name: 'faq_context_id_description'},
        {name: 'faq_context_id_module_description'},
        {name: 'faq_assigned_tags'},
        {name: 'faq_assigned_tags_ids'},
        {name: 'faq_assigned_articles'}
    ]);

    // Init basic properties
    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-faq/get-context-ids',
        method: 'POST',
        autoLoad: true,

        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'faq_context_id'
        }, this.ContextIdRecord),

        sortInfo: {
            field: 'faq_context_id',
            direction: 'DESC'
        }
    });

    this.columns = [
        {
            header: _('Context Id'),
            sortable: true,
            dataIndex: 'faq_context_id_text',
            width: 200
        }, {
            header: _('Context ID Description'),
            sortable: true,
            dataIndex: 'faq_context_id_description',
            renderer: this.rendererDescription.createDelegate(this),
            width: 200
        }, {
            header: _('Preview'),
            sortable: true,
            align: 'center',
            dataIndex: 'faq_context_id_module_description',
            width: 100,
            renderer: this.rendererArticlePreview.createDelegate(this)
        }, {
            header: _('Assigned Tags'),
            sortable: false,
            id: 'col_faq_context_id_text',
            dataIndex: 'faq_assigned_tags',
            width: 200,
            renderer: function (value) {
                return value.length ? implode(', ', value) : '';
            }
        }
    ];

    this.selModel = new Ext.grid.CheckboxSelectionModel();
    this.columns.unshift(this.selModel);

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New'),
            ref: 'contextIdCreateBtn',
            scope: this,
            hidden: true, // Don't allow to create a new id -> because it should be added manually to the code
            handler: this.addContextId.createDelegate(this)
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit'),
            ref: '../contextIdEditBtn',
            scope: this,
            disabled: true,
            handler: this.onDblClick.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete'),
            ref: '../contextIdDeleteBtn',
            scope: this,
            disabled: true,
            hidden: true, // Don't allow to delete an id -> because it should be removed manually from the code
            handler: this.deleteContextId.createDelegate(this)
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            xtype: 'button',
            scope: this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize: 100500,
        store: this.store,
        displayInfo: true,
        emptyMsg: _('No records to display')
    });

    ContextIdsGrid.superclass.constructor.call(this, {
        autoWidth: true,
        height: getSuperadminPanelHeight() - 20,
        autoExpandColumn: 'col_faq_context_id_text',
        stripeRows: true,
        cls: 'extjs-grid',
        viewConfig: {emptyText: _('No records were found.')},
        loadMask: true
    });

    this.on('rowdblclick', this.onDblClick, this);
    this.on('cellclick', this.onCellClick, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(ContextIdsGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();

        this.contextIdEditBtn.setDisabled(sel.length !== 1);
        this.contextIdDeleteBtn.setDisabled(sel.length === 0);
    },

    rendererDescription: function (value) {
        return Ext.util.Format.nl2br(value);
    },

    rendererArticlePreview: function (value) {
        return String.format(
            '<a href="#" onclick="return false;" class="article-preview-link"><i class="las la-search article-preview-link-image" title="' + _('Click to preview') + '"></i>' + _('Preview') + '</a>'
        );
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var rec = grid.getStore().getAt(rowIndex);
        var target = $(e.getTarget());
        if (target.hasClass('article-preview-link') || target.hasClass('article-preview-link-image')) {
            showHelpContextMenu(e.getTarget(), rec.data['faq_context_id_text']);
        }
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        this.editContextId(this, record);
    },

    addContextId: function () {
        var record = new this.ContextIdRecord({
            faq_context_id: 0
        });

        var dialog = new ContextIdsDialog({
            arrTags: this.getStore().reader.jsonData.arrTags
        }, this, record);

        dialog.showDialog();
    },

    editContextId: function (grid, record) {
        var dialog = new ContextIdsDialog({
            arrTags: grid.getStore().reader.jsonData.arrTags
        }, grid, record);
        dialog.showDialog();
    },

    deleteContextId: function () {
        // Don't allow to delete an id -> because it should be removed manually from the code
        Ext.simpleConfirmation.error(_('Must be manually deleted in the DB'));
    }
});