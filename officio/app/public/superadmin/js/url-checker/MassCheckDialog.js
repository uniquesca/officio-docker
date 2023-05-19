var MassCheckDialog = function (arrUrls, grid) {
    var booShowDetails = true;

    this.grid = grid;
    // Used to interrupt the checker
    this.booStopSending = false;

    this.arrUrls = arrUrls;
    this.processedCount = 0;
    this.totalCount = arrUrls.length;

    this.statusProgressBar = new Ext.ProgressBar({
        text: '',
        width: 500
    });

    this.statusTextArea = new Ext.form.HtmlEditor({
        hideLabel: true,
        hidden: !booShowDetails,
        value: '',
        width: 500
    });

    this.cancelBtn = new Ext.Button({
        text: _('Cancel'),
        scope: this,
        width: 60,
        handler: this.stopSending.createDelegate(this)
    });

    this.closeBtn = new Ext.Button({
        text: _('Close'),
        hidden: true,
        scope: this,
        width: 60,
        handler: this.closeDialog.createDelegate(this)
    });

    this.statusPanel = new Ext.Panel({
        layout: 'column',
        defaults: {
            layout: 'form',
            border: false,
            bodyStyle: 'padding: 4px 0;'
        },
        items: [
            {
                xtype: 'fieldset',
                width: 450,
                items: {
                    xtype: 'checkbox',
                    style: 'margin-top: 5px; margin-left: 5px;',
                    hideLabel: true,
                    boxLabel: _('Show Details'),
                    checked: booShowDetails,
                    scope: this,
                    handler: this.toggleTextArea.createDelegate(this)
                }
            }, {
                xtype: 'panel',
                width: 60,
                style: 'text-align: right;',
                items: [this.cancelBtn, this.closeBtn]
            }
        ]
    });

    this.FieldsForm = new Ext.Panel({
        frame: false,
        bodyStyle: 'padding:5px',
        items: [
            this.statusProgressBar,
            this.statusPanel,
            this.statusTextArea
        ]
    });

    MassCheckDialog.superclass.constructor.call(this, {
        y: 250,
        autoWidth: true,
        autoHeight: true,
        closable: false,
        plain: true,
        modal: true,
        buttonAlign: 'center',
        items: this.FieldsForm
    });

    // Apply parameters and send request
    this.on('show', this.initDialog.createDelegate(this), this);
};

Ext.extend(MassCheckDialog, Ext.Window, {
    // Actions related to current dialog
    initDialog: function() {
        this.resetStatus();
        this.resetTextArea();

        this.statusTextArea.getToolbar().hide();

        this.booStopSending = false;

        this.processedCount = 0;
        this.sendRequest();

        this.syncShadow();
    },

    closeDialog: function () {
        this.close();
    },


    // Actions related to text area (html editor)
    toggleTextArea: function (checkbox, booChecked) {
        this.statusTextArea.setVisible(booChecked);
        this.syncShadow();
    },

    updateTextArea: function (strValue) {
        var currValue = this.statusTextArea.getValue();
        if (!empty(currValue)) {
            currValue += '<br/>';
        }
        this.statusTextArea.setValue(currValue + strValue);
    },

    resetTextArea: function () {
        this.statusTextArea.setValue();
    },


    // Actions related to progress bar
    resetStatus: function () {
        this.updateStatus(0, _('Connecting to server...'));
    },

    updateStatus: function (intProgress, strNewStatus) {
        this.statusProgressBar.updateProgress(intProgress, strNewStatus, false);
    },

    updateTextStatusOnly: function (txtStatus) {
        this.statusProgressBar.updateText(txtStatus);
    },

    showErrorStatus: function (strErrorMessage) {
        var txtStatus = String.format(
            '<span style="color: red;">{0}</span>',
            strErrorMessage
        );
        this.updateTextStatusOnly(txtStatus);
    },


    // Global functionality
    stopSending: function () {
        this.booStopSending = true;
        this.updateTextStatusOnly(_('Stopping...'));
    },

    showCloseButton: function () {
        this.cancelBtn.hide();
        this.closeBtn.show();
    },

    sendRequest: function (processedCount) {
        var wnd = this;
        if(wnd.booStopSending || empty(wnd.totalCount)) {
            wnd.showCloseButton();
            wnd.updateTextStatusOnly(_('Cancelled'));
            wnd.updateTextArea('<span style="color: red;">*** ' + _('Cancelled by user') + ' ***</span>');
            return;
        }

        Ext.Ajax.request({
            url: baseUrl + '/url-checker/check',
            timeout: 5 * 60 * 1000, // 5 minutes

            params: {
                'arr_url': Ext.encode(wnd.arrUrls[wnd.processedCount])
            },

            success: function (f) {
                var result = Ext.decode(f.responseText);
                if (result.success) {
                    wnd.processedCount++;

                    // Apply changes to the grid's record
                    var index = wnd.grid.store.find('id', result.url_id);
                    if (index >= 0) {
                        var rec = wnd.grid.store.getAt(index);
                        rec.set('new_hash', result.url_hash);
                        rec.set('error_message', result.url_error);

                        var booCommitChanges = false;
                        if (result.url_error != '') {
                            rec.set('status', 'error');
                            booCommitChanges = true;
                        } else if (rec.data.new_hash != '' && rec.data.hash != rec.data.new_hash) {
                            rec.set('status', 'changed');
                            booCommitChanges = true;
                        }

                        if (booCommitChanges) {
                            wnd.grid.updateHashButton.setDisabled(false);
                            wnd.grid.store.commitChanges();
                        }
                    }

                    // Update progress
                    var txtStatus = String.format(
                        _('Processed {0} out of {1} urls...'),
                        wnd.processedCount,
                        wnd.totalCount
                    );

                    var percentStatus = wnd.processedCount / wnd.totalCount;
                    wnd.updateStatus(percentStatus, txtStatus);

                    // Update details
                    wnd.updateTextArea(result.text_status);

                    // Run another request if needed
                    if (wnd.processedCount < wnd.totalCount) {
                        wnd.sendRequest(wnd.processedCount, wnd.totalCount);
                    } else {
                        wnd.showCloseButton();
                        wnd.updateTextArea('<span style="color: green;">*** ' + _('Finished successfully') + ' ***</span>');
                    }
                } else {
                    wnd.showErrorStatus(result.message);
                }
            },

            failure: function () {
                wnd.showErrorStatus(_('Cannot check urls'));
            }
        });
    }
});
