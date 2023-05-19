var HelpTagsGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;

    this.HelpTagRecord = Ext.data.Record.create([
        {
            name: 'faq_tag_id',
            type: 'int'
        },
        {
            name: 'faq_tag_text',
            type: 'string'
        },
        {
            name: 'assigned_articles_count',
            type: 'int'
        }
    ]);

    // Init basic properties
    this.store = new Ext.data.Store({
        url:      baseUrl + '/manage-faq/get-help-tags',
        method:   'POST',
        autoLoad: true,

        remoteSort: true,

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount',
            idProperty:    'faq_tag_id'
        }, this.HelpTagRecord),

        sortInfo: {
            field:     'faq_tag_id',
            direction: 'DESC'
        }
    });

    this.columns = [
        {
            id:        'col_faq_tag_text',
            header:    'Tag',
            sortable:  true,
            dataIndex: 'faq_tag_text',
            width:     50
        }
    ];

    var grid_sm = new Ext.grid.CheckboxSelectionModel();
    this.columns.unshift(grid_sm);

    this.tbar = [
        {
            text:    '<i class="las la-plus"></i>' + _('New'),
            ref:     'HelpTagCreateBtn',
            scope:   this,
            handler: this.addHelpTag.createDelegate(this)
        }, {
            text:     '<i class="las la-edit"></i>' + _('Edit'),
            ref:      '../HelpTagEditBtn',
            scope:    this,
            disabled: true,
            handler:  this.onDblClick.createDelegate(this)
        }, {
            text:     '<i class="las la-trash"></i>' + _('Delete'),
            ref:      '../HelpTagDeleteBtn',
            scope:    this,
            disabled: true,
            handler:  this.deleteHelpTag.createDelegate(this)
        }, '->', {
            text:    '<i class="las la-undo-alt"></i>',
            xtype:   'button',
            scope:   this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.bbar = new Ext.PagingToolbar({
        pageSize:    25,
        store:       this.store,
        displayInfo: true,
        emptyMsg:    'No records to display'
    });

    HelpTagsGrid.superclass.constructor.call(this, {
        autoWidth:        true,
        height:           getSuperadminPanelHeight() - 20,
        selModel:         grid_sm,
        autoExpandColumn: 'col_faq_tag_text',
        stripeRows:       true,
        cls:              'extjs-grid',
        viewConfig:       {emptyText: 'No records were found.'},
        loadMask:         true
    });

    this.on('rowdblclick', this.onDblClick, this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(HelpTagsGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();

        this.HelpTagEditBtn.setDisabled(sel.length !== 1);
        this.HelpTagDeleteBtn.setDisabled(sel.length === 0);
    },

    onDblClick: function (record) {
        record = (record && record.data) ? record : this.selModel.getSelected();

        this.editHelpTag(this, record);
    },

    addHelpTag: function () {
        var record = new this.HelpTagRecord({
            faq_tag_id: 0
        });

        var dialog = new HelpTagsDialog({}, this, record);
        dialog.showDialog();
    },

    editHelpTag: function (grid, record) {
        var dialog = new HelpTagsDialog({}, grid, record);
        dialog.showDialog();
    },

    deleteHelpTag: function () {
        var thisGrid = this;

        var sel = this.getSelectionModel().getSelections();
        if (empty(sel.length)) {
            return false;
        }


        var arrTagsIds    = [];
        var articlesCount = 0;
        for (var i = 0; i < sel.length; i++) {
            arrTagsIds.push(sel[i].data.faq_tag_id);
            articlesCount += sel[i].data['assigned_articles_count'];
        }

        var question;
        if (sel.length === 1) {
            question = String.format(
                'Are you sure want to delete <i>{0}</i> tag?{1}',
                sel[0]['data']['faq_tag_text'],
                empty(articlesCount) ? '' : '<br><b>This tag is used in ' + articlesCount + ' articles.</b>'
            );
        } else {
            question = String.format(
                'Are you sure want to delete {0} selected tags?{1}',
                sel.length,
                empty(articlesCount) ? '' : '<br><b>These tags are used in ' + articlesCount + ' articles.</b>'
            );
        }

        Ext.MessageBox.buttonText.yes = 'Delete';
        Ext.MessageBox.minWidth = 500;
        Ext.Msg.confirm('Please confirm', question, function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Removing...');

                Ext.Ajax.request({
                    url:    baseUrl + '/manage-faq/delete-help-tags',
                    params: {
                        tags: Ext.encode(arrTagsIds)
                    },

                    success: function (res) {
                        Ext.getBody().unmask();

                        var result = Ext.decode(res.responseText);
                        if (result.success) {
                            // reload the list
                            Ext.simpleConfirmation.success(result.message);
                            thisGrid.store.reload();
                        } else {
                            Ext.simpleConfirmation.error(result.message);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error(
                            'Internal server error. Please try again.'
                        );
                    }
                });
            }
        });
    }
});