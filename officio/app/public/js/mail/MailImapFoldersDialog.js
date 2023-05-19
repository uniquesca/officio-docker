var MailImapFoldersDialog = function(config) {
    Ext.apply(this, config);

    var thisDialog = this;

    this.combosStore = new Ext.data.Store({
        data: [], // will be loaded when data will be received from server OR when check/uncheck item in the tree
        reader: new Ext.data.JsonReader(
            {id: 'folder_id'},
            Ext.data.Record.create([
                {name: 'folder_id'},
                {name: 'level'},
                {name: 'name'}
            ])
        )
    });

    var tpl = new Ext.XTemplate(
        '<tpl for=".">',
            '<tpl if="level == 0">',
                '<h1 class="x-combo-list-item" style="padding: 2px 5px;">{name}</h1>',
            '</tpl>',

            '<tpl if="level == 1">',
                '<div class="x-combo-list-item" style="padding-left: 20px;">{name}</div>',
            '</tpl>',
        '</tpl>'
    );

    this.InboxImapFoldersCombo = new Ext.form.ComboBox({
        store: this.combosStore,
        fieldLabel: 'Inbox',
        valueField: 'folder_id',
        displayField: 'name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 324,
        tpl: tpl
    });

    this.SentImapFoldersCombo = new Ext.form.ComboBox({
        store: this.combosStore,
        fieldLabel: 'Sent',
        valueField: 'folder_id',
        displayField: 'name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 324,
        tpl: tpl
    });

    this.DraftsImapFoldersCombo = new Ext.form.ComboBox({
        store: this.combosStore,
        fieldLabel: 'Drafts',
        valueField: 'folder_id',
        displayField: 'name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 324,
        tpl: tpl
    });

    this.TrashImapFoldersCombo = new Ext.form.ComboBox({
        store: this.combosStore,
        fieldLabel: 'Trash',
        valueField: 'folder_id',
        displayField: 'name',
        typeAhead: false,
        editable: false,
        mode: 'local',
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        width: 324,
        tpl: tpl
    });

    this.ImapFoldersTree = new MailImapFoldersTree({height: 200}, this);

    var panel = new Ext.FormPanel({
        autoWidth: true,
        autoHeight: true,
        labelAlign: 'top',
        bodyStyle: 'padding: 8px',
        items: [
            {
                xtype: 'fieldset',
                title: 'Mapping Folders',
                style: 'padding: 0 5px',
                items: [
                    {
                        layout: 'table',
                        cls: 'cell-align-middle',
                        defaults: {
                            bodyStyle: 'margin: 5px'
                        },
                        layoutConfig: { columns: 2 },
                        items: [
                            {
                                layout: 'form',
                                items: this.InboxImapFoldersCombo
                            },
                            {
                                layout: 'form',
                                items: this.SentImapFoldersCombo
                            },
                            {
                                layout: 'form',
                                items: this.DraftsImapFoldersCombo
                            },
                            {
                                layout: 'form',
                                items: this.TrashImapFoldersCombo
                            }
                        ]
                    }
                ]
            },
            this.ImapFoldersTree
        ]
    });

    MailImapFoldersDialog.superclass.constructor.call(this, {
        title: '<i class="las la-folder"></i>' + _('IMAP Folders'),
        modal: true,
        resizable: false,
        autoWidth: true,
        autoHeight: true,
        layout: 'form',
        items: panel,
        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
            }, {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.saveSettings.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this, []), this);
};

Ext.extend(MailImapFoldersDialog, Ext.Window, {
    loadSettings: function () {
        var thisDialog = this;

        thisDialog.getEl().mask('Loading folders...');
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/settings/get-imap-folders',
            timeout: 600000,
            params: {
                account_id: thisDialog.accountId,
                member_id: Ext.encode(curr_member_id)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);

                if (resultData.success) {
                    thisDialog.combosStore.on('load', function() {
                        thisDialog.InboxImapFoldersCombo.setValue(resultData.inbox);
                        thisDialog.SentImapFoldersCombo.setValue(resultData.sent);
                        thisDialog.DraftsImapFoldersCombo.setValue(resultData.drafts);
                        thisDialog.TrashImapFoldersCombo.setValue(resultData.trash);
                    }, this, {single: true});
                    thisDialog.combosStore.loadData(resultData.folders);

                    thisDialog.ImapFoldersTree.runReload();

                    thisDialog.getEl().unmask();
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                    thisDialog.close();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Can not load IMAP folders.<br/>Please try again later.');
                thisDialog.getEl().unmask();
            }
        });
    },

    saveSettings: function() {
        var thisDialog = this;

        var mappingValuesArray = [
            thisDialog.InboxImapFoldersCombo.getValue(),
            thisDialog.SentImapFoldersCombo.getValue(),
            thisDialog.DraftsImapFoldersCombo.getValue(),
            thisDialog.TrashImapFoldersCombo.getValue()
        ];

        var map = {}, i, size, booDuplicateValues = false;

        for (i = 0, size = mappingValuesArray.length; i < size; i++){
            if (mappingValuesArray[i] != 0 && map[mappingValuesArray[i]]){
                booDuplicateValues = true;
            }
            map[mappingValuesArray[i]] = true;
        }

        if (booDuplicateValues) {
            Ext.simpleConfirmation.warning('You have selected duplicate folders for mapping in comboboxes!');
            return;
        }


        var tree = thisDialog.ImapFoldersTree;
        var ImapFoldersSubscribe = [];
        if (tree) {
            i = 0;
            tree.getRootNode().cascade(function(folder) {
                if (!folder.isRoot) {
                    ImapFoldersSubscribe[i++] = {
                        folder_id: folder.attributes.real_folder_id,
                        checked: tree.isChecked(folder)
                    };
                }
            });
        }


        thisDialog.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/settings/save-imap-folders',
            params: {
                account_id: thisDialog.accountId,
                member_id: Ext.encode(curr_member_id),

                inbox: thisDialog.InboxImapFoldersCombo.getValue(),
                sent: thisDialog.SentImapFoldersCombo.getValue(),
                drafts: thisDialog.DraftsImapFoldersCombo.getValue(),
                trash: thisDialog.TrashImapFoldersCombo.getValue(),

                imap_folders_visibility: Ext.encode(ImapFoldersSubscribe)
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    // Refresh accounts list
                    Ext.getCmp('mail-settings-accounts-grid').store.reload();

                    thisDialog.getEl().mask('Done');
                    setTimeout(function () {
                        thisDialog.getEl().unmask();
                        thisDialog.close();
                        Ext.getCmp('mail-ext-folders-tree').getRootNode().reload();
                    }, 750);
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultData.message);
                    thisDialog.getEl().unmask();
                }
            },
            failure: function () {
                Ext.simpleConfirmation.error('An error occurred during IMAP folders settings saving.<br/>Please try again later.');
                thisDialog.getEl().unmask();
            }
        });
    }
});