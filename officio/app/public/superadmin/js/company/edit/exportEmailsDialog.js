ExportEmailsDialog = function(config) {
    var thisDialog = this;
    Ext.apply(this, config);

    thisDialog.selectedMember = null;

    this.users = new Ext.form.ComboBox({
        fieldLabel: 'Users',
        emptyText:  'Please select...',
        width:      320,
        allowBlank: false,
        store: {
            xtype: 'store',
            autoLoad: true,

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/manage-company/get-export-email-users'
            }),

            baseParams: {
                companyId: thisDialog.companyId
            },

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'member_id',
                fields: [
                    'member_id',
                    'name'
                ]
            })
        },
        mode:           'local',
        displayField:   'name',
        valueField:     'member_id',
        triggerAction:  'all',
        forceSelection: true,
        selectOnFocus:  true,
        editable:       true,
        listeners: {
            'select' : function() {
                thisDialog.emlAccounts.getStore().removeAll();
                thisDialog.emlAccounts.setValue('');
                thisDialog.emlAccounts.getStore().load();
                thisDialog.folders.getStore().removeAll();
                thisDialog.folders.setValue('');
                thisDialog.sizeLabel.setText('');
            }
        }
    });

    this.users.getStore().on('load', this.checkUsersLoadedData.createDelegate(this));

    this.emlAccounts = new Ext.form.ComboBox({
        fieldLabel: 'Email Accounts',
        emptyText:  'Please select...',
        width:      320,
        allowBlank: false,
        hidden: false,
        store: {
            xtype: 'store',
            autoLoad: false,

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/manage-company/get-export-email-accounts'
            }),

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'account_id',
                fields: [
                    'account_id',
                    'email'
                ]
            }),

            listeners: {
                beforeload: function(store) {
                    if (!empty(thisDialog.users.getValue())) {
                        store.baseParams.memberId = thisDialog.users.getValue();
                    }
                }
            }
        },
        mode:           'local',
        displayField:   'email',
        valueField:     'account_id',
        triggerAction:  'all',
        forceSelection: true,
        selectOnFocus:  true,
        editable:       true,
        listeners: {
            'select' : function() {
                thisDialog.folders.getStore().removeAll();
                thisDialog.folders.setValue('');
                thisDialog.folders.getStore().load();
                thisDialog.sizeLabel.setText('');
            }
        }
    });

    this.emlAccounts.getStore().on('load', this.checkUsersAccountsLoadedData.createDelegate(this));

    this.folders =  new Ext.ux.form.LovCombo({
        fieldLabel: 'Folders',
        emptyText:  'Please select...',
        width:      320,

        store: {
            xtype: 'store',
            autoLoad: false,
            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/manage-company/get-export-email-accounts-folders'
            }),

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'id',
                fields: [
                    'id',
                    'label',
                    'sum'
                ]
            }),

            listeners: {
                beforeload: function(store) {
                    if (!empty(thisDialog.emlAccounts.getValue())) {
                        store.baseParams.accountId = thisDialog.emlAccounts.getValue();
                    }
                }
            }
        },

        triggerAction: 'all',
        valueField:    'id',
        displayField:  'label',
        mode:          'local',
        useSelectAll:  false,
        allowBlank:    false,
        editable:      true,
        listeners: {
            'select' : function() {
                var values = thisDialog.folders.getValue();
                values = values.split(",");
                var storeItems = thisDialog.folders.getStore().reader.jsonData.items;
                var summarySize = 0;
                storeItems.forEach(function(element) {
                    if (values.indexOf(element.id) > -1) {
                        summarySize += parseInt(element.sum);
                    }
                });

                thisDialog.sizeLabel.setText(thisDialog.bytesToSize(parseInt(summarySize)));

            }
        }
    });

    this.sizeLabel = new Ext.form.Label({
        fieldLabel: 'Size of Backup',
        style: 'margin-top: -20px;',
        text:  ''
    });

    this.progressBar = new Ext.Toolbar({
        id: 'export-mailprogress-panel',
        width: 426,
        style: 'margin: 0 auto;',
        hidden: true,
        items: [
            {
                id: 'export-mail-ext-progressbar',
                xtype: 'progress',
                width: 426 - 30,
                cls: 'left-align'
            }, {
                id: 'export-mail-ext-progressbar-body',
                xtype: 'button',
                width: 20,
                tooltip: _('Click here to cancel'),
                iconCls: 'mail-attachment-cancel',
                handler: function () {
                    ExportMailChecker.stopLoading();
                    this.setDisabled(true);
                    ExportMailChecker.getPBar().updateProgress(100, _('Processing cancelled.'));
                    Ext.getCmp('export-mail-ext-progressbar').setWidth(420);
                }
            }
        ]
    });

    ExportEmailsDialog.superclass.constructor.call(this, {
        title: 'Export emails',
        id: 'export_email_dialog',
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',
        items: new Ext.FormPanel({
            style: 'background-color:#fff; padding:5px;',
            items: [
                {
                    layout:       'table',
                    items: [
                        {
                            layout: 'form',
                            items:  [
                                thisDialog.users,
                                thisDialog.emlAccounts,
                                thisDialog.folders,
                                thisDialog.sizeLabel,
                                thisDialog.progressBar
                            ]
                        }
                    ]
                }
            ]
        }),

        buttons: [
            {
                id: 'export_email_export_button',
                text: 'Export',
                handler: thisDialog.export.createDelegate(this)
            }, {
                id: 'export_email_close_button',
                text: 'Close',
                handler: function () {
                    thisDialog.close();
                }
            }
        ],
        tools: [{
            id:'close',
            hidden: true
        }]
    });
};

Ext.extend(ExportEmailsDialog, Ext.Window, {
    export: function () {
        var dialog = this;
        if (!empty(dialog.users.getValue()) && !empty(dialog.emlAccounts.getValue()) && !empty(dialog.folders.getValue())) {
            Ext.getCmp('export_email_export_button').hide();
            Ext.getCmp('export_email_close_button').hide();

            this.progressBar.show();

            ExportMailChecker.export(
                dialog.emlAccounts.getValue(),
                dialog.companyId,
                dialog.emlAccounts.getRawValue(),
                dialog.folders.getValue(),
                dialog.users.getValue()
            );
        }
    },

    bytesToSize: function(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes == 0) return '0 Byte';
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
    },

    checkUsersLoadedData: function(store) {
        // If there is only one option - automatically select it in the combo
        if (store.getCount() == 1) {
            var rec = store.getAt(0);
            this.users.setValue(rec.data[this.users.valueField]);
            this.emlAccounts.getStore().load();
        }
    },

    checkUsersAccountsLoadedData: function(store) {
        // If there is only one option - automatically select it in the combo
        if (store.getCount() == 1) {
            var rec = store.getAt(0);
            this.emlAccounts.setValue(rec.data[this.emlAccounts.valueField]);
            this.folders.getStore().load();
        }
    }
});
