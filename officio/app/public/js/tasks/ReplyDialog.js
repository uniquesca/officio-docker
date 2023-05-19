TasksReplyDialog = function(viewer) {
    var win = this;
    this.viewer = viewer;

    this.replyForm = new Ext.FormPanel({
        bodyStyle: 'padding: 5px',
        items: {
            xtype: 'textarea',
            ref: '../taskReplyMsg',
            hideLabel: true,
            allowBlank: false,
            width: 650,
            height: 249
        }
    });

    TasksReplyDialog.superclass.constructor.call(this, {
        title: '<i class="las la-reply"></i>' + _('Reply'),
        plain: true,
        modal: true,
        border: false,
        autoWidth: true,
        autoHeight: true,
        items: this.replyForm,
        closeAction: 'close',
        buttons: [
            {
                text: 'Cancel',
                handler: function() {
                    win.close();
                }
            }, {
                text: 'Save',
                cls: 'orange-btn',
                handler: this.submitData.createDelegate(this)
            }
        ]
    });

    // Automatically set focus to the text field
    this.on('show', this.setAutoFocus, this);
    this.on('resize', this.setTextareaSize, this);
};

Ext.extend(TasksReplyDialog, Ext.Window, {
    setAutoFocus: function() {
        this.taskReplyMsg.focus(false, 200);
    },

    setTextareaSize: function (obj, w, h) {
        if (!empty(this.taskReplyMsg)) {
            this.taskReplyMsg.setHeight(h - 100);
            this.taskReplyMsg.setWidth(w);
        }
    },

    submitData: function() {
        var win = this;

        var sel = win.viewer.viewer.TasksGrid.getSelectionModel().getSelected();
        if (!sel) {
            Ext.simpleConfirmation.error('Please select a task');
            return;
        }

        if(win.replyForm.getForm().isValid()) {
            win.getEl().mask('Saving...');
            Ext.Ajax.request({
                url: topBaseUrl + '/tasks/index/add-message/',
                params: {
                    task_id: sel.data['task_id'],
                    message: Ext.encode(win['taskReplyMsg'].getValue())
                },
                success: function (result) {
                    win.getEl().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (!resultDecoded.success)
                        Ext.simpleConfirmation.error(resultDecoded.msg);
                    else {
                        win.close();

                        // Reload threads grid
                        if (win.viewer.viewer.ThreadsGrid) {
                            win.viewer.viewer.ThreadsGrid.loadList(true);
                        } else {
                            win.viewer.viewer.TasksGrid.store.reload();
                        }

                    }
                },
                failure: function () {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error('An error occurred. Please try again later');
                }
            });
        }
    }
});

Ext.reg('appTasksReplyDialog', TasksReplyDialog);