function deleteTATransactionsByTime(ta_id) {
    var DeleteDialog = function () {
        var delete_dialog = this;

        var ta_id_hidden = new Ext.form.Hidden({
            id: 'ta-id-hidden',
            value: ta_id
        });

        var ba_combo = new Ext.form.ComboBox({
            id: 'trust-acc-before-after',
            store: new Ext.data.SimpleStore({
                fields: ['value', 'text'],
                data: [
                    ['before', 'Before'],
                    ['after', 'After']
                ],
                id: 0
            }),
            mode: 'local',
            hideLabel: true,
            value: 'before',
            valueField: 'value',
            displayField: 'text',
            triggerAction: 'all',
            lazyRender: true,
            forceSelection: true,
            selectOnFocus: true,
            typeAhead: true,
            editable: false,
            width: 195
        });

        var date_picker = new Ext.form.DateField({
            id: 'trust-acc-date-picker',
            value: new Date(),
            format: dateFormatFull,
            maxLength: 12, // Fix issue with date entering in 'full' format
            altFormats: dateFormatFull + '|' + dateFormatShort,
            allowBlank: false,
            hideLabel: true,
            editable: false,
            width: 145
        });

        var deleteLabel = {
            xtype: 'box',
            colspan: 2,
            width: 325,
            style: 'margin-bottom:7px; font-size: 15px; color: #616366;',
            'autoEl': {
                'tag': 'div',
                'html': 'Delete all transactions:'
            }
        };

        var deleteBtn = {
            xtype: 'button',
            text: 'Delete Transactions',
            cls: 'orange-btn',
            id: 'trust-acc-delete-button',
            handler: function () {
                Ext.getCmp('trust-acc-delete-dialog').runDelete();
            }
        };

        var closeBtn = {
            xtype: 'button',
            text: 'Cancel',
            handler: function () {
                delete_dialog.close();
            }
        };

        var pan = new Ext.FormPanel({
            cls: 'st-panel',
            bodyStyle: 'padding: 5px 5px 20px 5px;',
            autoHeight: true,
            scope: this,
            layout: 'table',
            layoutConfig: {columns: 2},
            items: [deleteLabel, ba_combo, date_picker, ta_id_hidden]
        });

        DeleteDialog.superclass.constructor.call(this, {
            title: 'Delete Transactions',
            closeAction: 'close',
            id: 'trust-acc-delete-dialog',
            modal: true,
            autoHeight: true,
            autoWidth: true,
            resizable: false,
            layout: 'form',
            items: pan,
            buttons: [closeBtn, deleteBtn]
        });
    };

    Ext.extend(DeleteDialog, Ext.Window, {
        showDialog: function () {
            this.show();
            this.center();
        },

        createDialog: function () {
            this.showDialog();
        },

        runDelete: function () {
            var ta_id = Ext.getCmp('ta-id-hidden').getValue();
            var date = Ext.getCmp('trust-acc-date-picker').getValue();
            var date_obj = new Date(date);
            var ba = Ext.getCmp('trust-acc-before-after').getValue();

            Ext.Msg.confirm('Please confirm', 'All transactions on and ' + ba + ' ' + date_obj.format(dateFormatFull) + ' will be removed. Are you sure you would like to proceed?', function (btn) {
                if (btn == 'yes') {
                    Ext.getCmp('trust-acc-delete-dialog').getEl().mask('Deleting...');
                    Ext.Ajax.request({
                        url: topBaseUrl + '/trust-account/edit/delete/',
                        params: {
                            ta_id: ta_id,
                            ba: ba,
                            date: Ext.encode(Ext.util.Format.date(date, Date.patterns.ISO8601Short)),
                            time_period: 1
                        },
                        success: function (result) {
                            var resultData = Ext.decode(result.responseText);
                            if (resultData.success) {
                                Ext.simpleConfirmation.msg('Info', 'Selected transactions were successfully deleted.', 3000);

                                Ext.getCmp('editor-grid' + ta_id).store.reload();
                                Ext.getCmp('trust-acc-delete-dialog').close();
                            } else {
                                // Show error message
                                Ext.simpleConfirmation.error(resultData.error);
                                Ext.getCmp('trust-acc-delete-dialog').getEl().unmask();
                            }
                        },
                        failure: function () {
                            Ext.simpleConfirmation.error("Internal error. Please try later");
                            Ext.getCmp('trust-acc-delete-dialog').getEl().unmask();
                        }
                    });
                }
            });
        }
    });

    var deleteDialog = new DeleteDialog();
    deleteDialog.showDialog();
}

function deleteTATransactionsBySelection(ta_id) {
    var arrSelectedIds = getSelectedTransactionsIds(ta_id);
    if (arrSelectedIds.length < 1) {
        Ext.simpleConfirmation.msg('Info', 'Please select one or several transactions and try again');
    } else {
        Ext.Msg.confirm('Please confirm', 'All selected transactions will be removed. Are you sure you would like to proceed?', function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: topBaseUrl + '/trust-account/edit/delete/',
                    params: {
                        ta_id: ta_id,
                        arr_ids: Ext.encode(arrSelectedIds),
                        time_period: 0
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            Ext.simpleConfirmation.msg('Info', 'Selected transactions were successfully deleted.', 3000);

                            Ext.getCmp('editor-grid' + ta_id).store.reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultData.error);
                        }
                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error("Internal error. Please try later");
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    }
}