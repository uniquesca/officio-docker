var DocumentsChecklistTagsDialog = function (config, parent) {
    var thisDialog = this;

    Ext.apply(this, config);
    this.parent = parent;

    var tagStore = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/documents/checklist/get-all-tags'
        }),

        baseParams: {
            clientId: config.clientId
        },

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',

            fields: [
                'tag'
            ]
        })
    });

    this.tagsCombo = new Ext.ux.form.SuperBoxSelect({
        allowAddNewData: true,
        labelAlign: 'top',
        fieldLabel: 'Tags',
        emptyText: _('Type and hit Enter to add a new tag OR select from the list...'),
        resizable: true,
        name: 'tags',
        cls: 'with-right-border',
        width: 600,
        listWidth: 557,
        store: tagStore,
        mode: 'local',
        displayField: 'tag',
        valueField: 'tag',
        extraItemCls: 'document-checklist-tag',

        listeners: {
            'newitem': function (bs, v) {
                var newObj = {
                    tag: v
                };
                bs.addItem(newObj);
            },

            'clear': function () {
                thisDialog.syncShadow();
            },

            'removeitem': function () {
                thisDialog.syncShadow();
            }
        }
    });

    DocumentsChecklistTagsDialog.superclass.constructor.call(this, {
        title: '<i class="las la-tags"></i>' + _('Set Tags'),
        closeAction: 'close',
        modal: true,
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        layout: 'form',
        bodyStyle: 'padding: 5px',

        items: this.tagsCombo,

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.saveTagsChanges.createDelegate(this, [false])
            }
        ]
    });

    this.on('beforeshow', this.setSavedTags.createDelegate(this, [config.arrSavedTags]));
};

Ext.extend(DocumentsChecklistTagsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    saveTagsChanges: function () {
        var thisWindow = this;
        var arrNewTags = thisWindow.tagsCombo.getValueEx();

        thisWindow.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/documents/checklist/set-tags',
            params: {
                clientId: Ext.encode(thisWindow.clientId),
                fileId: Ext.encode(thisWindow.fileId),
                tags: Ext.encode(arrNewTags)
            },

            success: function (result) {
                thisWindow.getEl().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    Ext.simpleConfirmation.info(resultDecoded.message);

                    // Update tags list for the selected node in the tree
                    // to avoid tree refreshing
                    var arrNodeNewTags = [];
                    for (var i = 0; i < arrNewTags.length; i++) {
                        arrNodeNewTags.push(arrNewTags[i]['tag']);
                    }

                    thisWindow.parent.updateNodeTags(arrNodeNewTags.sort());

                    // Close this dialog
                    thisWindow.close();
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultDecoded.message);
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Internal error. Please try again later.'));
            }
        });
    },

    setSavedTags: function (arrSavedTags) {
        var arrCorrectTags = [];
        for (var i = 0; i < arrSavedTags.length; i++) {
            arrCorrectTags.push({
                tag: arrSavedTags[i]
            });
        }

        if (arrCorrectTags.length) {
            this.tagsCombo.setValueEx(arrCorrectTags);
        }
    }
});