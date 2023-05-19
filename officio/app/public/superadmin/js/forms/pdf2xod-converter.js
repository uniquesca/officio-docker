/**
 *
 * @param arrPdfIds array id of pdfs
 * @param mode
 * @constructor
 */
var Pdf2XodConverter = function (arrPdfIds, mode) {
        var booShowDetails = true;
        this.mode = mode;

        // Used to interrupt pdf converting
        this.booStopSending = false;

        this.statusProgressBar = new Ext.ProgressBar({
            text: '',
            width: 500
        });

        this.statusTextArea = new Ext.form.HtmlEditor({
            hideLabel: true,
            hidden: !booShowDetails,
            value: '',
            width: 500,
            height:300
        });

        this.cancelBtn = new Ext.Button({
            text: 'Cancel',
            scope: this,
            width: 60,
            handler: this.stopSending.createDelegate(this)
        });

        this.closeBtn = new Ext.Button({
            text: 'Close',
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
                    width: 460,
                    items: {
                        xtype: 'checkbox',
                        style: 'margin-top: 5px;',
                        hideLabel: true,
                        boxLabel: 'Show Details',
                        checked: booShowDetails,
                        scope: this,
                        handler: this.toggleTextArea.createDelegate(this)
                    }
                }, {
                    xtype: 'fieldset',
                    width: 85,
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

    Pdf2XodConverter.superclass.constructor.call(this, {
            autoWidth: true,
            autoHeight: true,
            closable: false,
            plain: true,
            modal: true,
            buttonAlign: 'center',
            items: this.FieldsForm
        });

        // Apply parameters and send request
        this.on('show', this.initDialog.createDelegate(this, [arrPdfIds]), this);
    };

    Ext.extend(Pdf2XodConverter, Ext.Window, {
        // Actions related to current dialog
        initDialog: function(arrPdfIds) {
            this.resetStatus();
            this.resetTextArea();
            this.statusTextArea.getToolbar().hide();

            this.booStopSending = false;
            this.sendRequest(arrPdfIds, 0, arrPdfIds.length);
            this.syncShadow();
        },

        closeDialog: function () {
            this.close();
            Ext.getCmp('forms-main-grid').store.reload();
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
            this.updateStatus(0, 'Connecting to server...');
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
            this.updateTextStatusOnly('Stopping...');
        },

        showCloseButton: function () {
            this.cancelBtn.hide();
            this.closeBtn.show();
        },

        sendRequest: function (arrPdfIds, processedCount, totalCount) {
            var wnd = this;
            if(wnd.booStopSending || totalCount === 0) {
                wnd.showCloseButton();
                wnd.updateTextStatusOnly('Cancelled');
                wnd.updateTextArea('<span style="color: red;">*** Cancelled by user ***</span>');
                return;
            }

            Ext.Ajax.request({
                url: baseUrl + '/forms/pdf2xod',
                timeout: 5 * 60 * 1000, // 5 minutes

                params: {
                    'mode':            Ext.encode(wnd.mode),
                    'arr_ids':         Ext.encode(arrPdfIds),
                    'processed_count': Ext.encode(processedCount),
                    'total_count':     Ext.encode(totalCount)
                },

                success: function (f) {
                    var result = Ext.decode(f.responseText);
                    wnd.updateTextArea(result.text_status);

                    // Update progress
                    var txtStatus = String.format(
                        'Processed {0} out of {1} files...',
                        result.processed_count,
                        result.total_count
                    );

                    var percentStatus = result.processed_count / result.total_count;
                    wnd.updateStatus(percentStatus, txtStatus);

                    // Run another request if needed
                    if (result.arr_ids.length) {
                        wnd.sendRequest(result.arr_ids, result.processed_count, result.total_count);
                    } else {
                        wnd.showCloseButton();
                        wnd.updateTextArea('<span style="color: green;">*** Done ***</span>');
                    }
                },

                failure: function () {
                    wnd.showErrorStatus('Cannot perform action');
                    wnd.showCloseButton();
                }
            });
        }
    });