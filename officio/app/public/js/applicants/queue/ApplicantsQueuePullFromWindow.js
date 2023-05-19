var ApplicantsQueuePullFromWindow = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;
    ApplicantsQueuePullFromWindow.superclass.constructor.call(this, {
        title: 'Pull a Case from ' + arrApplicantsSettings.office_label,
        layout: 'fit',
        resizable: false,
        bodyStyle: 'padding: 5px; background-color:white;',
        buttonAlign: 'right',
        modal: true,
        autoWidth: true,
        items: [
            {
                xtype: 'form',
                labelWidth: 60,
                items: [
                    {
                        id: 'pullFromCombo',
                        xtype: 'combo',
                        width: 250,
                        fieldLabel: 'Pull from',
                        store: {
                            xtype: 'store',
                            reader: new Ext.data.JsonReader({
                                id: 'option_id'
                            }, [
                                {name: 'option_id'},
                                {name: 'option_name'}
                            ]),
                            data: arrApplicantsSettings.queue_settings['fields_options']['office_pull_from']
                        },
                        displayField: 'option_name',
                        valueField: 'option_id',
                        mode: 'local',
                        lazyInit: false,
                        typeAhead: false,
                        editable: false,
                        forceSelection: true,
                        triggerAction: 'all',
                        allowBlank: false,
                        selectOnFocus: true
                    }, {
                        id: 'pushToCombo',
                        xtype: 'combo',
                        width: 250,
                        fieldLabel: 'Push to',
                        store: {
                            xtype: 'store',
                            reader: new Ext.data.JsonReader({
                                id: 'option_id'
                            }, [
                                {name: 'option_id'},
                                {name: 'option_name'}
                            ]),
                            data: arrApplicantsSettings.queue_settings['fields_options']['office_push_to']
                        },
                        displayField: 'option_name',
                        valueField: 'option_id',
                        mode: 'local',
                        lazyInit: false,
                        typeAhead: false,
                        editable: false,
                        forceSelection: true,
                        triggerAction: 'all',
                        allowBlank: false,
                        selectOnFocus: true
                    }, {
                        xtype: 'displayfield',
                        hideLabel: true,
                        style: 'padding-top: 15px;',
                        value: 'Are you sure you would like to proceed? '
                    }
                ]
            }
        ],

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: 'Pull a Case',
                cls: 'orange-btn',
                iconCls: 'icon-applicant-queue-pull-from-queue',
                handler: this.pullFromQueue.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ApplicantsQueuePullFromWindow, Ext.Window, {
    pullFromQueue: function() {
        var win = this;

        var pullFromQueueId = Ext.getCmp('pullFromCombo').getValue();

        if (empty(pullFromQueueId)) {
            Ext.simpleConfirmation.warning('Please select "Pull from" option.', 'Warning');
            return false;
        }

        var pushToQueueId = Ext.getCmp('pushToCombo').getValue();

        if (empty(pushToQueueId)) {
            Ext.simpleConfirmation.warning('Please select "Push to" option.', 'Warning');
            return false;
        }

        win.getEl().mask('Loading...');

        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/queue/pull-from-queue',
            params: {
                pull_from_queue_id: Ext.encode(pullFromQueueId),
                push_to_queue_id: Ext.encode(pushToQueueId)
            },

            success: function(f)
            {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    if (typeof win.onSuccessUpdate === 'function') {
                        win.onSuccessUpdate();
                    }

                    win.close();
                } else {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function()
            {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                win.getEl().unmask();
            }
        });
    }

});