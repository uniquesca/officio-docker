var SystemVariablesPanel = function () {
    var thisPanel = this;

    var arrGroups     = [];
    var arrGroupItems = [];

    Ext.each(arrVariables, function (oGroup) {
        arrGroupItems = [];
        Ext.each(oGroup.group_items, function (oGroupItem) {
            arrGroupItems.push({
                name:          oGroupItem.variable_name,
                xtype:         oGroupItem.variable_type,
                fieldLabel:    oGroupItem.variable_label,
                value:         oGroupItem.variable_value,
                allowBlank:    false,
                allowNegative: false,
                width:         empty(oGroupItem.variable_width) ? 400 : oGroupItem.variable_width
            });
        });

        arrGroups.push({
            xtype:       'fieldset',
            layout:      'form',
            title:       oGroup.group_name,
            labelWidth:  oGroup.group_labels_width,
            autoHeight:  true,
            collapsible: true,
            items:       arrGroupItems,

            listeners: {
                collapse: function () {
                    updateSuperadminIFrameHeight('#variables_container');
                },

                expand: function () {
                    updateSuperadminIFrameHeight('#variables_container');
                }
            }
        });
    });

    SystemVariablesPanel.superclass.constructor.call(this, {
        frame:       false,
        width:       900,
        bodyStyle:   'padding:0 10px 0;',
        buttonAlign: 'center',
        items:       arrGroups,

        buttons: [
            {
                text: 'Reset',
                handler: function () {
                    thisPanel.getForm().reset();
                }
            }, {
                text: 'Save',
                cls: 'orange-btn',
                handler: thisPanel.saveData.createDelegate(thisPanel)
            }
        ]
    });

    this.on('afterlayout', this.initThisPanel.createDelegate(this));

    this.render('variables_container');
};

Ext.extend(SystemVariablesPanel, Ext.FormPanel, {
    initThisPanel: function () {
        $('#variables_container').css('min-height', getSuperadminPanelHeight() + 'px');
        updateSuperadminIFrameHeight('#variables_container');
    },

    saveData: function () {
        var thisPanel = this;

        if (thisPanel.getForm().isValid()) {
            Ext.getBody().mask('Saving...');

            thisPanel.getForm().submit({
                url: baseUrl + '/system-variables/save',

                success: function (form, action) {
                    Ext.simpleConfirmation.success(action.result.message);
                    Ext.getBody().unmask();
                },

                failure: function (form, action) {
                    if (!empty(action.result.message)) {
                        Ext.simpleConfirmation.error(action.result.message);
                    } else {
                        Ext.simpleConfirmation.error('Cannot save info');
                    }

                    Ext.getBody().unmask();
                }
            });
        }
    }
});
