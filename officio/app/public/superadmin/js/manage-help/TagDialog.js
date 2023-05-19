var HelpTagsDialog = function (config, parentGrid, oTagRecord) {
    var thisDialog = this;
    Ext.apply(thisDialog, config);

    thisDialog.parentGrid = parentGrid;
    thisDialog.tagRecord  = oTagRecord;

    this.HelpTagsForm = new Ext.form.FormPanel({
        style: 'background-color: #fff; padding: 5px;',

        items: [
            {
                name:  'faq_tag_id',
                xtype: 'hidden',
                value: thisDialog.tagRecord.data['faq_tag_id']
            }, {
                name:       'faq_tag_text',
                xtype:      'textfield',
                fieldLabel: 'Tag',
                allowBlank: false,
                value:      thisDialog.tagRecord.data['faq_tag_text']
            }
        ]
    });

    HelpTagsDialog.superclass.constructor.call(this, {
        title:      empty(thisDialog.tagRecord.data['faq_tag_id']) ? '<i class="las la-plus"></i>' + _('Add Tag') : '<i class="las la-edit"></i>' + _('Edit Tag'),
        modal:      true,
        autoHeight: true,
        autoWidth:  true,
        resizable:  false,

        items: this.HelpTagsForm,

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

Ext.extend(HelpTagsDialog, Ext.Window, {
    showDialog: function () {
        this.show();
        this.center();
    },

    closeDialog: function () {
        this.close();
    },

    saveChanges: function () {
        var thisDialog = this;

        // Make sure that option is selected
        if (!thisDialog.HelpTagsForm.getForm().isValid()) {
            return;
        }

        thisDialog.HelpTagsForm.getForm().submit({
            url:              baseUrl + '/manage-faq/save-help-tag',
            waitMsg:          _('Saving...'),
            clientValidation: true,

            success: function () {
                thisDialog.parentGrid.store.reload();


                thisDialog.getEl().mask(_('Done!'));
                setTimeout(function () {
                    thisDialog.closeDialog();
                }, 750);
            },

            failure: function (form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});
