var exportProfilesAndCasesDialog = function (config) {
    var thisDialog = this;
    Ext.apply(this, config);

    this.exportClientsAtOnce = 5000;
    this.progressBarWidth = 460;
    this.totalClientsCount = 0;

    this.arrGeneratedFiles = [];
    this.generatedCombinedFile = '';

    // Pointer to the ajax request, so can be used to determine if ajax request is active or stop it
    this.ajaxRequestId = null;

    this.exporttingProgressBar = new Ext.ProgressBar({
        width: this.progressBarWidth,
        text: _('Click on the Export button to start...'),
        cls: 'left-align'
    });

    this.stopProgressButton = new Ext.Button({
        xtype: 'button',
        width: 20,
        hidden: true,
        ctCls: 'x-toolbar-cell-no-right-padding',
        tooltip: _('Click here to cancel'),
        iconCls: 'mail-attachment-cancel',
        style: 'margin-left: 5px',
        handler: function () {
            if (thisDialog.arrGeneratedFiles.length) {
                thisDialog.warningMessage.setValue(
                    _('You cancelled before the request was completed. You can click on the Download button to download the partial list.')
                );
            } else {
                thisDialog.warningMessage.hide();
            }

            if (!empty(thisDialog.ajaxRequestId)) {
                Ext.Ajax.abort(thisDialog.ajaxRequestId);
            }

            this.setDisabled(true);
            thisDialog.showError(_('Exporting cancelled.'));
        }
    });


    this.closeDialogBtn = new Ext.Button({
        text: _('Close'),
        handler: function () {
            thisDialog.close();
        }
    });

    this.downloadGeneratedFilesBtn = new Ext.Button({
        text: '<i class="las la-file-download"></i>' + _('Download'),
        cls: 'orange-btn',
        hidden: true,
        handler: thisDialog.downloadGeneratedFiles.createDelegate(this)
    });

    this.startExportingBtn = new Ext.Button({
        text: _('Export'),
        cls: 'orange-btn',
        handler: thisDialog.export.createDelegate(this)
    });

    this.warningMessage = new Ext.form.DisplayField({
        hideLabel: true,
        style: 'font-size: 14px; margin-bottom: 10px',
        value: _('This process may take a few minutes. If you cancel before the request is completed, you will be able to download the partially processed list.')
    });

    exportProfilesAndCasesDialog.superclass.constructor.call(this, {
        title: _('Export Profiles and Cases'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        layout: 'form',

        items: {
            xtype: 'container',
            width: this.progressBarWidth,
            items: [
                this.warningMessage,
                {
                    xtype: 'container',
                    layout: 'column',
                    items: [
                        this.exporttingProgressBar,
                        this.stopProgressButton
                    ]
                }
            ]
        },

        buttons: [
            this.closeDialogBtn,
            this.downloadGeneratedFilesBtn,
            this.startExportingBtn
        ],

        tools: [{
            // Don't show a close/cross button by default
            id: 'close',
            hidden: true
        }]
    });
};

Ext.extend(exportProfilesAndCasesDialog, Ext.Window, {
    sendRequestToExport: function (start) {
        var dialog = this;

        dialog.ajaxRequestId = Ext.Ajax.request({
            url: topBaseUrl + '/superadmin/manage-company/export-profiles-and-cases',

            params: {
                companyId: dialog.companyId,
                start: start,
                limit: dialog.exportClientsAtOnce,
                totalClientsCount: dialog.totalClientsCount
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    dialog.totalClientsCount = resultData.totalClientsCount;
                    dialog.exporttingProgressBar.updateProgress(resultData.progressPercent / 100, resultData.progressMessage);

                    dialog.arrGeneratedFiles.push(resultData.filePath);

                    if (resultData.booContinue) {
                        dialog.sendRequestToExport(start + dialog.exportClientsAtOnce);
                    } else {
                        dialog.exporttingProgressBar.setWidth(dialog.progressBarWidth);
                        dialog.stopProgressButton.hide();
                        dialog.closeDialogBtn.show();

                        if (dialog.arrGeneratedFiles.length) {
                            dialog.warningMessage.setValue(
                                _('Please click on the Download button to download the generated list.')
                            );

                            dialog.downloadGeneratedFilesBtn.show();
                        }

                        dialog.syncShadow();
                    }
                } else {
                    dialog.showError(resultData.msg);
                    dialog.warningMessage.hide();
                }
            },

            failure: function () {
                dialog.showError(_('Error'));
                dialog.warningMessage.hide();

                Ext.simpleConfirmation.error(_('Request failed. Please try again later.'));
            }
        });
    },

    showError: function (msg) {
        var dialog = this;

        dialog.exporttingProgressBar.updateProgress(100, '<span style="font-weight: normal">' + msg + '</span>');
        dialog.exporttingProgressBar.setWidth(dialog.progressBarWidth);
        dialog.stopProgressButton.hide();
        dialog.closeDialogBtn.show();

        if (dialog.arrGeneratedFiles.length) {
            dialog.downloadGeneratedFilesBtn.show();
        }

        dialog.syncShadow();
    },

    export: function () {
        var dialog = this;

        dialog.startExportingBtn.hide();
        dialog.closeDialogBtn.hide();
        dialog.exporttingProgressBar.updateProgress(0, _('Processing...'));
        dialog.exporttingProgressBar.setWidth(dialog.progressBarWidth - 40);
        dialog.stopProgressButton.show();
        dialog.syncShadow();

        this.sendRequestToExport(0);
    },

    downloadGeneratedFiles: function () {
        var dialog = this;
        if (empty(dialog.generatedCombinedFile)) {
            dialog.getEl().mask(_('Preparing...'));
            Ext.Ajax.request({
                url: topBaseUrl + '/superadmin/manage-company/generate-profiles-and-cases-export-file',

                params: {
                    companyId: Ext.encode(dialog.companyId),
                    arrFiles: Ext.encode(dialog.arrGeneratedFiles)
                },

                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    dialog.getEl().unmask();

                    if (resultData.success) {
                        dialog.generatedCombinedFile = resultData.filePath;
                        dialog.downloadGeneratedFiles();
                    } else {
                        Ext.simpleConfirmation.error(resultData.msg);
                    }
                },

                failure: function () {
                    dialog.getEl().unmask();
                    Ext.simpleConfirmation.error(_('Request failed. Please try again later.'));
                }
            });
        } else {
            // The file was generated, now download it
            submit_hidden_form(
                topBaseUrl + '/superadmin/manage-company/download-exported-profiles-and-cases',
                {
                    companyId: Ext.encode(dialog.companyId),
                    filePath: Ext.encode(dialog.generatedCombinedFile)
                }
            );
        }
    }
});