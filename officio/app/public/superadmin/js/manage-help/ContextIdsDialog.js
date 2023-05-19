var ContextIdsDialog = function (config, parentGrid, oContextIdRecord) {
    var thisDialog = this;
    Ext.apply(thisDialog, config);

    thisDialog.parentGrid      = parentGrid;
    thisDialog.contextIdRecord = oContextIdRecord;

    var arrTags = [
        {
            'faq_tag_id':   0,
            'faq_tag_text': 'Check/Uncheck All'
        }
    ];

    var help = String.format(
        "<i class='las la-info-circle' ext:qtip='{0}' ext:qwidth='460' style='cursor: help; margin-left: 5px; vertical-align: text-bottom'></i>",
        _('Context Id can be changed only manually in the DB because the same ID must be added on the tab by the developers.')
    );

    this.ContextIdForm = new Ext.form.FormPanel({
        style: 'background-color: #fff; padding: 5px;',

        items: [
            {
                name:  'faq_context_id',
                xtype: 'hidden',
                value: thisDialog.contextIdRecord.data['faq_context_id']
            }, {
                name:       'faq_context_id_text',
                xtype:      'textfield',
                style:      'color: #909090',
                fieldLabel: _('Context Id') + help,
                allowBlank: false,
                readOnly:   true,
                width:      800,
                value:      thisDialog.contextIdRecord.data['faq_context_id_text']
            }, {
                name:       'faq_context_id_description',
                xtype:      'textarea',
                fieldLabel: 'Context ID Description',
                allowBlank: true,
                width:      800,
                value:      thisDialog.contextIdRecord.data['faq_context_id_description']
            }, {
                name: 'faq_context_id_module_description',
                xtype: 'froalaeditor',
                fieldLabel: 'Module Description',
                width: 830,
                height: 200,
                allowBlank: true,
                value: thisDialog.contextIdRecord.data['faq_context_id_module_description'],
                booUseSimpleFroalaPanel: true
            }, {
                name:       'faq_assigned_tags_ids',
                hiddenName: 'faq_assigned_tags_ids',
                xtype:      'lovcombo',
                fieldLabel: 'Assigned Tags',
                allowBlank: true,
                width:      800,

                store: {
                    xtype:  'store',
                    reader: new Ext.data.JsonReader({
                        id: 'faq_tag_id'
                    }, [
                        {name: 'faq_tag_id'},
                        {name: 'faq_tag_text'}
                    ]),

                    data: arrTags.concat(config.arrTags)
                },

                triggerAction: 'all',
                valueField:    'faq_tag_id',
                displayField:  'faq_tag_text',
                mode:          'local',
                useSelectAll:  true,
                value:         thisDialog.contextIdRecord.data['faq_assigned_tags_ids']
            }
        ]
    });

    ContextIdsDialog.superclass.constructor.call(this, {
        title:      empty(thisDialog.contextIdRecord.data['faq_context_id']) ? '<i class="las la-plus"></i>' + _('Add Context Id') : '<i class="las la-edit"></i>' + _('Edit Context Id'),
        modal:      true,
        autoHeight: true,
        autoWidth:  true,
        resizable:  false,

        items: this.ContextIdForm,

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

Ext.extend(ContextIdsDialog, Ext.Window, {
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
        if (!thisDialog.ContextIdForm.getForm().isValid()) {
            return;
        }

        thisDialog.ContextIdForm.getForm().submit({
            url:              baseUrl + '/manage-faq/save-context-id',
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
