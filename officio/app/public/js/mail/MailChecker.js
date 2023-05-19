var booCheckingInProgress = false;
var selected_folder_to_refresh;

// Used when 'folder select' event occurs
// We don't need to check emails in folder directly after the "check email"
var booRefreshEmailsInFolder;

function refreshMailsList() {
    Ext.getCmp('mail-ext-emails-grid').store.reload();
}


var MailChecker = {
    intervalId: 0,
    showPBar: function() {
        Ext.getCmp('mail-folders-tree-bbar-text-panel').hide();

        var progressPanel = Ext.getCmp('mail-folders-tree-bbar-progress-panel');
        if (progressPanel) {
            progressPanel.show();
            progressPanel.getEl().setOpacity(0);
            progressPanel.getEl().fadeIn({duration: 2});
        }
    },

    hidePBar: function() {
        var progressPanel = Ext.getCmp('mail-folders-tree-bbar-progress-panel');
        if (progressPanel) {
            progressPanel.getEl().fadeOut({duration: 2});
        }
    },

    getPBar: function() {
        return Ext.getCmp('mail-ext-progressbar');
    },

    resetPBar: function() {
        var pbar = this.getPBar();
        pbar.updateProgress(0, 'Connecting to server...');
    },

    createLockFile: function(acc_id)
    {
        Ext.Ajax.request({
            url: topBaseUrl + '/lock.php',
            params: {
                account_id: acc_id
            }
        });
    },

    updateStatus: function (result) {
        var pbar = MailChecker.getPBar();
        var cancelBtn = Ext.getCmp('mail-ext-progressbar-cancel-btn');
        cancelBtn.disable();

        if (result.e) {
            result.s = '<span>' + result.s + '</span>';

            cancelBtn.officio = {
                lock_file: result.e,
                progress: result.p
            };
            cancelBtn.enable();
        }

        pbar.updateProgress(result.p / 100, result.s);

        if (result.p >= 100) {
            // Hide progressbar
            MailChecker.hidePBar();

            clearInterval(this.intervalId);
            var gridWithPreview = Ext.getCmp('mail-grid-with-preview');
            Ext.getCmp('mail-ext-emails-grid').store.on('beforeload', gridWithPreview.preview.clear, gridWithPreview.preview);

            // Refresh folder (update label)
            // Reload list of all folders
            var tree = Ext.getCmp('mail-ext-folders-tree');
            if (tree && !result.e) {
                var count = parseInt(result.c, 10);
                tree.updateFolderLabel(tree.getRootNode().findChild('folder_id', 'inbox'), count);

                if (result.r) {
                    booRefreshEmailsInFolder = false;

                    // Refresh folders list, keep focus on selected folder
                    selected_folder_to_refresh = tree.getSelectionModel().getSelectedNode();
                    if (!empty(selected_folder_to_refresh) && selected_folder_to_refresh.attributes) {
                        selected_folder_to_refresh = selected_folder_to_refresh.attributes.real_folder_id;
                    }

                    tree.getRootNode().reload();
                }
            }

            booCheckingInProgress = false;

            var tbCheckEmail = Ext.getCmp('mail-toolbar-check-mail');
            var tbCheckingEmail = Ext.getCmp('mail-toolbar-checking-mail');
            if (tbCheckEmail != undefined && tbCheckEmail != undefined) {
                tbCheckEmail.setVisible(true);
                tbCheckingEmail.setVisible(false);
            }

            if (Ext.getCmp('mail-toolbar-account') != undefined) {
                Ext.getCmp('mail-toolbar-account').enable();
            }

            if (Ext.getCmp('mail-settings-accounts-grid') != undefined) {
                var sm = Ext.getCmp('mail-settings-accounts-grid').getSelectionModel();
                sm.fireEvent('selectionchange', sm);
            }

            if (result.s == 'Done with warnings')
                Ext.simpleConfirmation.warning('You have received an email that exceeds 16 MB. Please delete this email from your server, and try again.');
        } else {
            cancelBtn.enable();
        }
    },

    onIFrameLoad: function() {
        if (booCheckingInProgress) {
            // Frame wasn't fully loaded -
            // Show error message and hide progress bar
            var pbar = MailChecker.getPBar();
            pbar.updateProgress(100, '<span style="color: #000;">Server Error.</span>');
            clearInterval(this.intervalId);
            var gridWithPreview = Ext.getCmp('mail-grid-with-preview');
            Ext.getCmp('mail-ext-emails-grid').store.on('beforeload', gridWithPreview.preview.clear, gridWithPreview.preview);

            // Hide progressbar
            setTimeout(function() {
                MailChecker.hidePBar();

                var tbCheckEmail = Ext.getCmp('mail-toolbar-check-mail');
                var tbCheckingEmail = Ext.getCmp('mail-toolbar-checking-mail');
                if (tbCheckEmail != undefined && tbCheckEmail != undefined) {
                    tbCheckEmail.setVisible(true);
                    tbCheckingEmail.setVisible(false);
                }
                if (Ext.getCmp('mail-toolbar-account')!=undefined)
                    Ext.getCmp('mail-toolbar-account').enable();
            }, 2000);

            booCheckingInProgress = false;
        }
    },

    check: function(account, manual, booCheckInboxOnly) {
        if (empty(account)) {
            return false;
        }

        // Cancel emails checking for folder (if active)
        var grid = Ext.getCmp('mail-ext-emails-grid');
        if (grid && !empty(grid.ajaxRequestToCheckFolder)) {
            Ext.Ajax.abort(grid.ajaxRequestToCheckFolder);
        }

        if (!booCheckingInProgress) {
            booCheckingInProgress = true;

            var tbCheckEmail = Ext.getCmp('mail-toolbar-check-mail');
            var tbCheckingEmail = Ext.getCmp('mail-toolbar-checking-mail');
            if (tbCheckEmail != undefined && tbCheckEmail != undefined) {
                tbCheckEmail.setVisible(false);
                tbCheckingEmail.setVisible(true);
            }

            var tbAccountBtn = Ext.getCmp('mail-toolbar-account');
            if (tbAccountBtn != undefined) {
                tbAccountBtn.disable();
            }

            this.showPBar();
            this.resetPBar();

            // Check if frame was already created
            var iframeId = 'mail_iframe_check_email';
            var frame = Ext.get(iframeId);

            var oParams = {
                account_id: account,
                manual: manual === true ? 1 : 0,
                check_inbox_only: booCheckInboxOnly ? 1 : 0
            };
            var src = topBaseUrl + '/mailer/index/check-email/?' + $.param(oParams);

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

            var gridWithPreview = Ext.getCmp('mail-grid-with-preview');
            grid.store.un('beforeload', gridWithPreview.preview.clear, gridWithPreview.preview);

            this.intervalId = setInterval(refreshMailsList, 5000);
        }
    }
};
