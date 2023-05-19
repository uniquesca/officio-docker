var AutomaticRemindersTriggersPanel = function (config, owner) {
    Ext.apply(this, config);
    this.owner = owner;

    var thisPanel = this;

    thisPanel.triggersCombo = new Ext.ux.form.LovCombo({
        width: config.width + 12,
        minWidth: config.width + 12,

        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'automatic_reminder_trigger_type_id'
            }, [{name: 'automatic_reminder_trigger_type_id'}, {name: 'automatic_reminder_trigger_type_name'}]),
            data: arrTriggerSettings.arrTypes
        },

        triggerAction: 'all',
        valueField: 'automatic_reminder_trigger_type_id',
        displayField: 'automatic_reminder_trigger_type_name',
        mode: 'local',
        useSelectAll: false,
        allowBlank: false,
        value: empty(config.params.reminder_id) || empty(config.params.trigger_types) ? [] : config.params.trigger_types,

        listeners: {
            'select': function (combo) {
                var arrSelectedTriggers = [];
                var strChecked = combo.getValue();
                if (strChecked !== '') {
                    arrSelectedTriggers = strChecked.split(combo.separator);
                }
                thisPanel.owner.newTriggerIds = arrSelectedTriggers;
            }
        }
    });

    AutomaticRemindersTriggersPanel.superclass.constructor.call(this, {
        items: thisPanel.triggersCombo
    });
};

Ext.extend(AutomaticRemindersTriggersPanel, Ext.Panel, {});