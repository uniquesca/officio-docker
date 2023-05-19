var CaseCategoriesDialog = function (parentGrid, oCaseCategoryRecord, booNewRecord) {
    var thisDialog = this;

    thisDialog.parentGrid = parentGrid;
    thisDialog.caseCategoryRecord = oCaseCategoryRecord;
    thisDialog.booNewRecord = booNewRecord;

    this.caseCategoryName = new Ext.form.TextField({
        fieldLabel: _('Name'),
        emptyText: _('Please enter the name...'),
        allowBlank: false,
        width: 300,
        value: thisDialog.caseCategoryRecord.data['client_category_name']
    });

    this.caseCategoryAbbreviation = new Ext.form.TextField({
        fieldLabel: _('Abbreviation'),
        allowBlank: true,
        width: 300,
        value: thisDialog.caseCategoryRecord.data['client_category_abbreviation']
    });

    this.caseCategoryStatusList = new Ext.form.ComboBox({
        fieldLabel: statuses_field_label_singular + _(' Workflow'),
        emptyText: _('Please select...'),
        allowBlank: true,
        width: 300,
        store: new Ext.data.Store({
            reader: new Ext.data.JsonReader({
                idProperty: 'client_status_list_id',
                fields: [
                    {name: 'client_status_list_id'},
                    {name: 'client_status_list_name'}
                ]
            }),

            data: arrCaseStatusLists
        }),
        mode: 'local',
        valueField: 'client_status_list_id',
        displayField: 'client_status_list_name',
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        value: thisDialog.caseCategoryRecord.data['client_category_assigned_list_id']
    });

    this.caseCategoryLinkToEmployerYes = new Ext.form.Radio({
        name: 'client_category_link_to_employer',
        xtype: 'radio',
        boxLabel: _('Yes'),
        hideLabel: true,
        inputValue: 'Y',
        checked: thisDialog.caseCategoryRecord.data['client_category_link_to_employer'] === 'Y'
    });

    this.caseCategoryLinkToEmployerNo = new Ext.form.Radio({
        name: 'client_category_link_to_employer',
        xtype: 'radio',
        boxLabel: _('No'),
        hideLabel: true,
        inputValue: 'N',
        checked: thisDialog.caseCategoryRecord.data['client_category_link_to_employer'] !== 'Y'
    });

    this.caseCategoryForm = new Ext.form.FormPanel({
        labelWidth: 150,
        style: 'padding: 5px;',

        items: [
            this.caseCategoryName,
            this.caseCategoryAbbreviation,
            this.caseCategoryStatusList,

            {
                xtype: 'container',
                layout: 'column',
                items: [
                    {
                        xtype: 'label',
                        style: 'padding-right: 40px;',
                        text: _('Link to Employer:')
                    },
                    this.caseCategoryLinkToEmployerYes,
                    {
                        html: '&nbsp;&nbsp;&nbsp;'
                    },
                    this.caseCategoryLinkToEmployerNo
                ]
            }

        ]
    });

    CaseCategoriesDialog.superclass.constructor.call(this, {
        title: empty(thisDialog.caseCategoryRecord.data['client_category_id']) ? '<i class="las la-plus"></i>' + _('Add') + ' ' + categories_field_label_singular : '<i class="las la-edit"></i>' + _('Edit') + ' ' + categories_field_label_singular,
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,

        items: this.caseCategoryForm,

        buttons: [
            {
                text: _('Cancel'),
                scope: this,
                handler: this.closeDialog
            },
            {
                text: _('Save'),
                cls: 'orange-btn',
                scope: this,
                handler: this.saveChanges
            }
        ]
    });
};

Ext.extend(CaseCategoriesDialog, Ext.Window, {
    showDialog: function () {
        this.show();
        this.center();
    },

    closeDialog: function () {
        this.close();
    },

    saveChanges: function () {
        var thisDialog = this;

        // Make sure that all fields are correct
        if (!thisDialog.caseCategoryForm.getForm().isValid()) {
            return;
        }

        var categoriesStore = thisDialog.parentGrid.getStore();

        if (thisDialog.booNewRecord) {
            // Create a new record, add it to the bottom
            var maxOrder = 0;
            for (var i = 0; i < categoriesStore.getCount(); i++) {
                var rec = categoriesStore.getAt(i);
                maxOrder = Math.max(maxOrder, rec.data['client_category_order']);
            }

            var record = new thisDialog.parentGrid.caseCategoryRecord({
                client_category_id: null,
                client_category_parent_id: 0,
                client_category_assigned_list_id: thisDialog.caseCategoryStatusList.getValue(),
                client_category_assigned_list_name: thisDialog.caseCategoryStatusList.getRawValue(),
                client_category_name: thisDialog.caseCategoryName.getValue(),
                client_category_abbreviation: thisDialog.caseCategoryAbbreviation.getValue(),
                client_category_link_to_employer: thisDialog.caseCategoryLinkToEmployerYes.getValue() ? 'Y' : 'N',
                client_category_order: maxOrder + 1
            });

            categoriesStore.insert(0, record);
        } else {
            // Apply changes to the selected record
            var rec = thisDialog.parentGrid.getSelectionModel().getSelected();
            rec.beginEdit();
            rec.set('client_category_name', thisDialog.caseCategoryName.getValue());
            rec.set('client_category_abbreviation', thisDialog.caseCategoryAbbreviation.getValue());
            rec.set('client_category_link_to_employer', thisDialog.caseCategoryLinkToEmployerYes.getValue() ? 'Y' : 'N');
            rec.set('client_category_assigned_list_id', thisDialog.caseCategoryStatusList.getValue());
            rec.set('client_category_assigned_list_name', thisDialog.caseCategoryStatusList.getRawValue());
            rec.endEdit();
        }

        // Get the list of records
        var arrCategories = [];
        for (var i = 0; i < categoriesStore.getCount(); i++) {
            var rec = categoriesStore.getAt(i);
            arrCategories.push(rec.data);
        }

        // Reload data in the store
        thisDialog.parentGrid.loadCaseTypeCategories(arrCategories);

        thisDialog.closeDialog();
    }
});
