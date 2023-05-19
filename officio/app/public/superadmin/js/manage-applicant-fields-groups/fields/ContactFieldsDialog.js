var ContactFieldsDialog = function(config) {
    var thisWindow = this;
    Ext.apply(this, config);

    this.contactFieldsStore = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,
        baseParams: {
            company_id: Ext.encode(companyId),
            block_id:   Ext.encode(config.blockId)
        },

        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/manage-applicant-fields-groups/get-contact-fields'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'field_id',
            fields: [
                'field_id',
                'field_name',
                {name: 'field_encrypted', type: 'boolean'},
                {name: 'field_required', type: 'boolean'},
                {name: 'field_disabled', type: 'boolean'},
                {name: 'field_blocked', type: 'boolean'},
                {name: 'field_placed', type: 'boolean'}
            ]
        })
    });

    this.placedFieldsList = [];
    this.contactFieldsStore.on('load', function(){
        var arrRecordsToSelect = [];
        thisWindow.placedFieldsList = [];
        this.each(function(rec){
            if (rec.data.field_placed) {
                arrRecordsToSelect.push(rec);
                thisWindow.placedFieldsList.push(rec.data.field_id);
            }
        });
        thisWindow.contactFieldsGrid.getSelectionModel().selectRecords(arrRecordsToSelect);
    });


    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.contactFieldsGrid = new Ext.grid.GridPanel({
        sm:               sm,
        height:           350,
        width:            600,
        border:           false,
        stripeRows:       true,
        autoExpandColumn: expandCol,
        store:            this.contactFieldsStore,
        columns: [
            sm, {
                id:        expandCol,
                header:    'Field Name',
                width:     160,
                sortable:  true,
                dataIndex: 'field_name'
            }
        ],
        loadMask: {
            msg: 'Loading...'
        },
        viewConfig: {
            deferEmptyText: 'There are no fields to show.',
            emptyText:      'There are no fields to show.'
        }
    });

    ContactFieldsDialog.superclass.constructor.call(this, {
        iconCls: 'icon-add-contact-fields',
        title: 'Add contact fields to the group',
        autoWidth: true,
        autoHeight: true,

        plain: false,
        buttonAlign: 'center',
        modal: true,
        resizable: false,
        items: this.contactFieldsGrid,

        buttons: [
            {
                text: 'Save',
                handler: this.applyFields.createDelegate(this)
            }, {
                text: 'Cancel',
                handler: this.closeDialog.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ContactFieldsDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    applyFields: function () {
        var thisDialog = this;
        var selFields = thisDialog.contactFieldsGrid.getSelectionModel().getSelections();

        // Collect new checked fields
        var arrAllSelectedFields = [];
        var arrAddedFields = [];
        Ext.each(selFields, function (field) {
            arrAllSelectedFields.push(field.data.field_id);
            if (!thisDialog.placedFieldsList.has(field.data.field_id)) {
                arrAddedFields.push(field.data.field_id);
            }
        });

        // Collect all unchecked fields
        var arrRemovedFields = [];
        Ext.each(thisDialog.placedFieldsList, function (fieldId) {
            if (!arrAllSelectedFields.has(fieldId)) {
                arrRemovedFields.push(fieldId);
            }
        });

        // If no fields were added/removed - simply close dialog
        if (!arrAddedFields.length && !arrRemovedFields.length) {
            thisDialog.close();
            return;
        }

        thisDialog.getEl().mask('Processing...');
        Ext.Ajax.request({
            url: submissionUrl + '/toggle-contact-fields',
            params: {
                company_id:      companyId,
                member_type:  Ext.encode(memberType),
                block_id:        Ext.encode(thisDialog.blockId),
                group_id:        Ext.encode(thisDialog.groupId),
                fields_added:    Ext.encode(arrAddedFields),
                fields_removed:  Ext.encode(arrRemovedFields)
            },

            success: function(result){
                var resultData = Ext.decode(result.responseText);
                if (!resultData.error) {

                    Ext.each(arrRemovedFields, function (fieldId) {
                        removeFieldFromHtml(thisDialog.blockId, fieldId);
                    });

                    Ext.each(arrAddedFields, function (fieldId) {
                        var rec = thisDialog.contactFieldsStore.getById(fieldId);
                        addFieldToHtml(thisDialog.groupId, fieldId, rec.data.field_name, rec.data.field_encrypted, rec.data.field_required, rec.data.field_disabled, rec.data.field_blocked, rec.data.field_use_full_row);
                    });

                    // Show a confirmation
                    thisDialog.getEl().mask(resultData.message);
                    setTimeout(function(){
                        thisDialog.close();
                    }, confirmationTimeOut);
                } else {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function(){
                thisDialog.getEl().unmask();
                Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
            }
        });
    }
});