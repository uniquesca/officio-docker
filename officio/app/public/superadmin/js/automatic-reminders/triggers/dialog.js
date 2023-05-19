var AutomaticRemindersTriggersDialog = function(config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var arrRadios = [];
    Ext.each(arrTriggerSettings.arrTypes, function (oType, index) {
        var booChecked = false;
        if (empty(config.params.trigger_id)) {
            // Automatically check the first item
            booChecked = empty(index);
        } else {
            booChecked = oType.automatic_reminder_trigger_type_id == config.params.trigger_type_id;
        }

        arrRadios.push({
            boxLabel: oType.automatic_reminder_trigger_type_name,
            name: 'automatic_reminder_trigger_type_radio',
            inputValue: oType.automatic_reminder_trigger_type_id,
            width: 420,
            checked: booChecked
        });
    });

    this.radioTriggerTypesGroup = new Ext.form.RadioGroup({
        hideLabel:  true,
        allowBlank: false,
        columns:    1,
        cls:        'automatic_reminder_trigger_label',
        items:      arrRadios
    });

    AutomaticRemindersTriggersDialog.superclass.constructor.call(this, {
        title:      empty(config.params.trigger_id) ? '<i class="las la-plus"></i>' + _('Add new trigger') : '<i class="las la-edit"></i>' + _('Edit trigger'),
        modal:      true,
        autoWidth:  true,
        autoHeight: true,
        resizable:  false,
        layout:     'form',

        items: {
            xtype: 'form',
            frame: false,
            items: [
                {
                    xtype: 'label',
                    html: _('Trigger is about when the Auto Task should start.<br>Please choose an event that should trigger this auto-task.')
                },
                this.radioTriggerTypesGroup
            ]
        },

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: empty(config.params.trigger_id) ? _('Add trigger') : _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });
};

Ext.extend(AutomaticRemindersTriggersDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function() {
        this.show();
        this.center();
        this.syncShadow();
    },

    saveChanges: function() {
        var thisWindow    = this;
        var booError      = false;
        var triggerTypeId = 0;

        if (thisWindow.radioTriggerTypesGroup.isValid()) {
            var checkedRadio = thisWindow.radioTriggerTypesGroup.getValue();
            triggerTypeId = checkedRadio.inputValue;
        } else {
            booError = true;
        }

        if (booError) {
            thisWindow.getEl().unmask();
            return false;
        }

        if (thisWindow.params.trigger_id && thisWindow.params.trigger_type_id == '8' && triggerTypeId != '8' &&
            thisWindow.owner.triggersGrid.getStore().reader.jsonData.booLastFieldValueChangedTrigger &&
            thisWindow.owner.booHasChangedFieldConditions) {
            var message = 'Are you sure you want to change this trigger? All "Changed field is" conditions will be deleted.';
            Ext.Msg.confirm(_('Please confirm'), _(message), function (btn) {
                if (btn == 'yes') {
                    thisWindow.saveRequest(triggerTypeId);
                }
            });
        } else {
            thisWindow.saveRequest(triggerTypeId);
        }
    },

    saveRequest: function(triggerTypeId) {
        var thisWindow = this;

        // save
        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + "/automatic-reminder-triggers/save",
            params: {
                reminder_id: thisWindow.params.reminder_id,
                trigger_id: thisWindow.params.trigger_id,
                trigger_type_id: triggerTypeId
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisWindow.getEl().mask('Done!');

                    if (empty(thisWindow.params.reminder_id)) {
                        thisWindow.owner.newTriggerIds.push(resultData.trigger_id);
                        thisWindow.owner.triggersGrid.getStore().baseParams.trigger_ids = Ext.encode(thisWindow.owner.newTriggerIds);
                    }

                    thisWindow.owner.triggersGrid.getStore().reload();
                    thisWindow.owner.conditionsGrid.getStore().reload();

                    if (!empty(thisWindow.params.reminder_id)) {
                        thisWindow.owner.owner.getStore().reload();
                    }

                    setTimeout(function () {
                        thisWindow.getEl().unmask();
                        thisWindow.close();
                    }, 750);
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                    thisWindow.getEl().unmask();
                }
            },

            failure: function () {
                thisWindow.getEl().unmask();
                Ext.simpleConfirmation.error(_('Saving Error'));
            }
        });
    }
});