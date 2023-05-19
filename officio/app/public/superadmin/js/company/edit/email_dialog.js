var companyEmailDialog = function(companyId) {
    // Templates combobox
    this.templateCombo = new Ext.form.ComboBox({
        fieldLabel: 'Template',
        allowBlank: false,

        store: new Ext.data.Store({
            method: 'POST',
            
            baseParams: {
                // Load templates only this type
                template_type: 'mass_email'
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                idProperty: 'template_id',
                fields: [
                   {name: 'template_id', type: 'int'},
                   {name: 'title',       type: 'string'}
                ]
            })
        }),

        mode: 'local',
        valueField: 'template_id',
        displayField: 'title',
        triggerAction: 'all',
        forceSelection: true,
        emptyText: 'Please select template...',
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: 320,
        listWidth: 320
    });

    // "Send to" grid
    this.gridStore = new Ext.data.Store({
        method: 'POST',
        autoLoad: false,

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            idProperty: 'user_id',
            fields: [
               {name: 'user_id',    type: 'int'},
               {name: 'user_name',  type: 'string'},
               {name: 'user_roles', type: 'string'}
            ]
        })
    });
    var grid_sm = new Ext.grid.CheckboxSelectionModel();
    this.sendToGrid = new Ext.grid.GridPanel({
        autoWidth: true,
        height: 200,
        sm: grid_sm,
        store: this.gridStore,
        columns: [
            grid_sm, {
                id: 'col_user',
                header: 'User',
                sortable: true,
                dataIndex: 'user_name'
            }, {
                header: 'Roles',
                sortable: true,
                width: 140,
                dataIndex: 'user_roles'
            }
        ],
        autoExpandColumn: 'col_user',
        stripeRows: true,
        viewConfig: { emptyText: 'No users were found.' },
        loadMask: true
    });

    this.FieldsForm = new Ext.form.FormPanel({
        frame: false,
        bodyStyle: 'padding:5px',
        labelWidth: 60,
        items: [
            this.templateCombo,
            this.sendToGrid
        ]
    });

    this.buttons = [{
            text: 'Close',
            scope: this,
            handler: function(){
                this.close();
            }
        },
        {
            text:'Send',
            cls: 'orange-btn',
            handler: this.startMassMailing.createDelegate(this, [companyId])
        }];

    companyEmailDialog.superclass.constructor.call(this, {
        title: 'Send email',
        y: 250,
        autoWidth: true,
        autoHeight: true,
        closeAction: 'close',
        plain: true,
        modal: true,
        buttonAlign: 'center',
        items: this.FieldsForm
    });

    this.on('show', this.initDialog.createDelegate(this, [companyId]), this);
};

Ext.extend(companyEmailDialog, Ext.Window, {
    initDialog: function(companyId) {
        var win = this;
        win.getEl().mask('Loading...');
        Ext.Ajax.request({
            url: baseUrl + '/manage-company/get-company-details',

            params: {
                companyId: Ext.encode(companyId)
            },

            success: function(f) {
                var result = Ext.decode(f.responseText);
                if (empty(result.error_message)) {
                    win.templateCombo.store.loadData(result.templates);
                    win.sendToGrid.store.loadData(result.users);
                } else {
                    Ext.simpleConfirmation.error(result.error_message);
                }

                win.getEl().unmask();
            },

            failure: function() {
                Ext.simpleConfirmation.error('Cannot load information. Please try again later.');
                win.getEl().unmask();
            }
        });
    },

    startMassMailing: function(companyId) {
        var wnd = this;
        if(!wnd.FieldsForm.getForm().isValid()) {
            return;
        }

        var selectedUsers = wnd.sendToGrid.getSelectionModel().getSelections();
        if(!selectedUsers) {
            Ext.simpleConfirmation.warning('Please select at least one user.');
            return;
        }

        var arrUserIds = [];
        for(var i=0; i< selectedUsers.length; i++) {
            arrUserIds.push(selectedUsers[i].data.user_id);
        }


        // All params are correct, show progress bar dialog
        var sender = new MassEmailSender([companyId], wnd.templateCombo.getValue(), '', arrUserIds);
        sender.show();

        // Close this window
        wnd.close();
    }
});