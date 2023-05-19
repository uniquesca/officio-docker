var booExportCheckingInProgress = false;

var ExportMailChecker = {
    intervalId: 0,
    showPBar: function() {
        var progressPanel = Ext.getCmp('export-mailprogress-panel');
        if (progressPanel) {
            progressPanel.show();
            progressPanel.getEl().setOpacity(0);
            progressPanel.getEl().fadeIn({duration: 2});
        }
    },

    hidePBar: function() {
        var progressPanel = Ext.getCmp('export-mailprogress-panel');
        if (progressPanel) {
            progressPanel.getEl().fadeOut({duration: 2});
        }
    },

    getPBar: function() {
        return Ext.getCmp('export-mail-ext-progressbar');
    },

    resetPBar: function() {
        var pbar = this.getPBar();
        pbar.updateProgress(0, 'Connecting...');
    },

    updateStatus: function (result) {
        var pbar = ExportMailChecker.getPBar();

        pbar.updateProgress(result.p / 100, result.s);

        if (result.p >= 99) {
            // Hide progressbar

            pbar.updateProgress(100, 'Done!');

            booExportCheckingInProgress = false;
        }
    },

    stopLoading: function()
    {
        $('#mail_iframe_check_email').attr('src','about:blank');
    },

    onIFrameLoad: function() {
        if (booExportCheckingInProgress) {
            booExportCheckingInProgress = false;
        }

        var cancelButton = Ext.getCmp('export_email_close_button');
        if (cancelButton) {
            cancelButton.show();
        }

        var stopButton = Ext.getCmp('export-mail-ext-progressbar-body');
        if (stopButton) {
            stopButton.disable();
            stopButton.hide();
            Ext.getCmp('export-mail-ext-progressbar').setWidth(420);

        }

    },

    export: function(accountId, companyId, accountName, folderIds, userId) {
        if (empty(accountId) || empty(companyId) || empty(folderIds) || empty(userId)) {
            return false;
        }

        if (!booExportCheckingInProgress) {
            booExportCheckingInProgress = true;

            this.showPBar();
            this.resetPBar();

            // Check if frame was already created
            var iframeId = 'mail_iframe_check_email';
            var frame = Ext.get(iframeId);

            var oParams = {
                accountId: accountId,
                companyId: companyId,
                accountName: accountName,
                folderIds: folderIds,
                userId: userId
            };
            var src = baseUrl + '/manage-company/export-emails/?' + $.param(oParams);

            if (!frame) {
                var body = Ext.getBody();

                // Create a hidden frame
                frame = body.createChild({
                    id: iframeId,
                    name: iframeId,
                    src: src,
                    tag: 'iframe',
                    cls: 'x-hidden'
                });

                // Listen for iframe full load
                Ext.EventManager.on(frame.dom, 'load', this.onIFrameLoad, this);
            } else {
                // Reload already created frame
                frame.setLocation(src);
            }
        }
    }
};
