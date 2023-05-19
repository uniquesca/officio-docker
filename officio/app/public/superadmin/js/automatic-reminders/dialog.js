var AutomaticRemindersDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisWindow = this;

    // These ids must be assigned on new reminder creation
    this.newTriggerIds = config.params.trigger_types;
    this.newConditionIds = [];
    this.newActionIds = [];

    this.booShowChangedFieldCondition = false;
    this.booHasChangedFieldConditions = false;

    this.reminderNameField = new Ext.form.TextField({
        fieldLabel: _('Name'),
        emptyText: _('Please enter a name...'),
        allowBlank: false,
        width: 380
    });

    this.reminderActiveCasesOnly = new Ext.form.Checkbox({
        boxLabel: _('Search only among Active Cases'),
        style: 'vertical-align: middle'
    });

    this.mainDetails = new Ext.FormPanel({
        frame: false,
        bodyStyle: 'padding:5px',
        height: 50,
        labelWidth: 60,
        items: [
            thisWindow.reminderNameField
        ]
    });

    this.triggersPanel = new AutomaticRemindersTriggersPanel({
        params: config.params,
        width: 650
    }, thisWindow);

    this.conditionsGrid = new AutomaticRemindersConditionsGrid({
        cls: 'extjs-grid',
        width: 650,
        height: 200
    }, thisWindow);

    this.actionsGrid = new AutomaticRemindersActionsGrid({
        cls: 'extjs-grid',
        width: 650,
        height: 200
    }, thisWindow);

    this.settingsTabPanel = new Ext.Panel({
        border: false,
        autHeight: true,
        autoWidth: true,
        deferredRender: false,
        items: [
            thisWindow.mainDetails,

            {
                xtype: 'container',
                layout: 'column',
                items: [
                    {
                        xtype: 'box',
                        cls: 'automatic_reminder_label',
                        autoEl: {
                            tag: 'div',
                            html: _('Conditions') + String.format(
                                "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
                                _('Conditions are particular pieces of information from the client`s file. For example, a particular field from the Profile or Case Details section, such as case status.')
                            )
                        }
                    }, {
                        xtype: 'box',
                        cls: 'automatic_reminder_label_warning',
                        autoEl: {
                            tag: 'div',
                            html: _('Automatic task will be processed when all conditions are true.')
                        }
                    }
                ]
            },
            thisWindow.conditionsGrid,

            {
                xtype: 'box',
                cls: 'automatic_reminder_label',
                autoEl: {
                    tag: 'div',
                    html: _('Actions') + String.format(
                        "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
                        _('Actions are instructions as to what should happen if the Conditions are met. For example, an email will be sent to the client.')
                    )
                }
            },
            thisWindow.actionsGrid,

            {
                xtype: 'container',
                layout: 'column',
                items: [
                    {
                        xtype: 'box',
                        cls: 'automatic_reminder_label',
                        autoEl: {
                            tag: 'div',
                            html: _('Triggers') + String.format(
                                "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
                                _('Triggers are the internal processes that cause the Actions to take place. For example, when a file status is changed.')
                            )
                        }
                    }, {
                        xtype: 'box',
                        cls: 'automatic_reminder_label_warning',
                        autoEl: {
                            tag: 'div',
                            style: '',
                            html: _("Automatic task's conditions will be checked when any of the triggers is called.")
                        }
                    }
                ]
            },

            thisWindow.triggersPanel,

            {
                xtype: 'container',
                style: 'margin-top: 15px',
                items: this.reminderActiveCasesOnly
            }
        ]
    });

    AutomaticRemindersDialog.superclass.constructor.call(this, {
        title: config.params.action == 'add' ? '<i class="las la-plus"></i>' + _('Add new Automatic Task') : '<i class="las la-edit"></i>' + _('Edit Automatic Task'),
        y: 10,
        modal: true,
        autoWidth: true,
        autoHeight: true,
        resizable: false,
        bodyStyle: 'padding: 10px 20px; max-height: 650px; overflow: auto;',
        items: this.settingsTabPanel,
        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            },
            {
                text: _('Save'),
                cls: 'orange-btn',
                handler: this.saveChanges.createDelegate(this)
            }
        ]
    });

    this.on('show', this.loadSettings.createDelegate(this));
};

Ext.extend(AutomaticRemindersDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showDialog: function () {
        this.show();
        this.syncShadow();
    },

    loadSettings: function () {
        var thisWindow = this;

        thisWindow.getEl().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/automatic-reminders/get-reminder-info',
            params: {
                reminder_id: thisWindow.params.reminder_id
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    // Set values
                    if (thisWindow.params.action == 'edit') {
                        var reminder = resultData.reminder;

                        thisWindow.reminderNameField.setValue(reminder.reminder);
                        thisWindow.reminderActiveCasesOnly.setValue(reminder.active_clients_only === 'Y');
                        thisWindow.conditionsGrid.getStore().loadData(resultData.conditions);
                        thisWindow.actionsGrid.getStore().loadData(resultData.actions);
                    }

                    thisWindow.getEl().unmask();
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                    thisWindow.close();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                thisWindow.close();
            }
        });
    },

    saveChanges: function () {
        var thisWindow = this;

        if (!thisWindow.reminderNameField.isValid()) {
            return false;
        }

        if (empty(thisWindow.newTriggerIds) || empty(thisWindow.newTriggerIds.length)) {
            var msg = _('Please check at least one Trigger.');
            thisWindow.triggersPanel.triggersCombo.markInvalid(msg);
            Ext.simpleConfirmation.error(msg);
            return false;
        }

        var booCreateReminder = thisWindow.params.action == 'add';
        var actionIds = !booCreateReminder ? [] : thisWindow.newActionIds;
        var conditionIds = !booCreateReminder ? [] : thisWindow.newConditionIds;

        // Save
        thisWindow.getEl().mask(_('Saving...'));
        Ext.Ajax.request({
            url: baseUrl + '/automatic-reminders/process-reminder',
            params: {
                reminder_id: Ext.encode(thisWindow.params.reminder_id),
                reminder: Ext.encode(thisWindow.reminderNameField.getValue()),
                active_clients_only: Ext.encode(thisWindow.reminderActiveCasesOnly.getValue()),
                trigger_types: Ext.encode(thisWindow.newTriggerIds),
                condition_ids: Ext.encode(conditionIds),
                action_ids: Ext.encode(actionIds)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    thisWindow.owner.getStore().load();

                    thisWindow.getEl().mask(_('Done!'));
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