var DocumentsChecklistReassignDialog = function (config, parent) {
    var thisDialog = this;

    Ext.apply(this, config);
    this.parent = parent;

    this.familyMembersStore = new Ext.data.Store({
        url:        baseUrl + '/documents/checklist/get-family-members',
        baseParams: {
            member_id:        config.clientId,
            required_file_id: config.fileId
        },

        autoLoad: true,

        listeners: {
            load: function () {
                thisDialog.familyMembersCombo.setValue(config.dependentId);
            }
        },

        reader: new Ext.data.JsonReader({
            id:            'real_id',
            root:          'family_members',
            totalProperty: 'totalCount'
        }, [
            {name: 'value'},
            {name: 'lName'},
            {name: 'fName'},
            {name: 'real_id'},
            {name: 'order'}
        ])
    });

    this.familyMembersStore.on('beforeload', function () {
        thisDialog.getEl().mask(_('Loading...'));
    });

    this.familyMembersStore.on('load', function () {
        thisDialog.getEl().unmask();
    });

    this.familyMembersStore.on('exception', function () {
        thisDialog.getEl().unmask();
    });

    this.familyMembersCombo = new Ext.form.ComboBox({
        fieldLabel:     _('Reassign file to'),
        width:          250,
        store:          thisDialog.familyMembersStore,
        displayField:   'value',
        valueField:     'real_id',
        mode:           'local',
        lazyInit:       false,
        typeAhead:      false,
        editable:       false,
        forceSelection: true,
        triggerAction:  'all',
        allowBlank:     false,
        selectOnFocus:  true
    });

    DocumentsChecklistReassignDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-user-check"></i>' + _('Reassign File'),
        closeAction: 'close',
        modal:       true,
        resizable:   false,
        autoHeight:  true,
        autoWidth:   true,
        layout:      'form',
        bodyStyle:   'padding: 10px 5px 0',

        items: this.familyMembersCombo,

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: _('Reassign'),
                cls: 'orange-btn',
                handler: this.assignFileToDependent.createDelegate(this, [false])
            }
        ]
    });
};

Ext.extend(DocumentsChecklistReassignDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    assignFileToDependent: function () {
        var thisWindow = this;

        if (thisWindow.familyMembersCombo.getValue() === thisWindow.dependentId) {
            thisWindow.closeDialog();
            return;
        }

        thisWindow.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url:    baseUrl + '/documents/checklist/reassign',
            params: {
                clientId:    Ext.encode(thisWindow.clientId),
                fileId:      Ext.encode(thisWindow.fileId),
                dependentId: Ext.encode(thisWindow.familyMembersCombo.getValue())
            },

            success: function (result) {
                thisWindow.getEl().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    Ext.simpleConfirmation.info(resultDecoded.message);

                    // Update dependent for the selected node in the tree to avoid tree refreshing
                    thisWindow.parent.updateNodeDependent(thisWindow.familyMembersCombo.getValue(), thisWindow.familyMembersCombo.getRawValue());

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
    }
});