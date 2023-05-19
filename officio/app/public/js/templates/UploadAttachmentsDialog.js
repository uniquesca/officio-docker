var UploadAttachmentsDialog = function (config) {
    Ext.apply(this, config);

    var thisDialog = this;

    this.dropzoneDivId            = Ext.id();
    this.thisDialogTimeoutId      = null;
    this.thisDialogDropZone       = null;
    this.totalProcessedFilesCount = 0;

    thisDialog.thisDialogCancelButton = new Ext.Button({
        text:    _('Close'),
        handler: this.onDialogCancel.createDelegate(this)
    });


    UploadAttachmentsDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-file-upload"></i>' + _('Upload file(s)'),
        closeAction: 'close',
        modal:       true,
        resizable:   false,
        autoHeight:  true,
        autoWidth:   true,
        layout:      'form',
        buttonAlign: 'center',

        items: {
            xtype:  'box',
            autoEl: {
                tag:     'div',
                id:      this.dropzoneDivId,
                'class': 'dropzone documents-files-upload-dropzone-general'
            }
        },

        buttons: [
            thisDialog.thisDialogCancelButton
        ]
    });

    this.on('show', this.initDropzone.createDelegate(this));
    this.on('close', this.onDialogClose.createDelegate(this));
};

Ext.extend(UploadAttachmentsDialog, Ext.Window, {
    onDialogCancel: function () {
        var thisDialog = this;
        thisDialog.thisDialogDropZone.removeAllFiles(true);
        thisDialog.close();
    },

    onDialogClose: function () {
        var thisDialog = this;

        // When all is done - refresh the tree
        if (!empty(thisDialog.totalProcessedFilesCount)) {

        }
    },

    initDropzone: function () {
        var thisDialog = this;

        var defaultMessage = String.format(
            '<img src="{0}/images/upload.svg" width="48" height="48" class="grey-out" /><br><br>' +
            _('Drop files here or click to upload ') +
            '<span style="display: block; font-size: small; padding-top: 10px;">' +
                _('Maximum file size: {1}MB') + '<br><br>' +
            '</span>' +
            '<span class="documents-files-upload-dropzone-dpi-message">' +
                _('For best performance, please scan your documents in <br>black and white at a maximum resolution of 300 DPI.') +
            '</span>',
            topBaseUrl,
            post_max_size / 1024 / 1024
        );

        thisDialog.thisDialogDropZone = new Dropzone('#' + this.dropzoneDivId, {
            url:                topBaseUrl + '/templates/index/upload-attachments',
            uploadMultiple:     false,
            autoProcessQueue:   false,
            paramName:          'template-attachment',
            parallelUploads:    50,
            maxFiles:           50,
            timeout:            1000 * 60 * 5, // 5 minutes
            maxFilesize:        post_max_size / 1024 / 1024, // MB
            dictFileTooBig:     _('File is too big ({{filesize}}MiB).'),
            dictDefaultMessage: defaultMessage,

            params: {
                template_id: thisDialog.settings.template_id,
                act: thisDialog.settings.act,
                files: 0
            },

            init: function () {
                // Show errors if any
                this.on('error', function (file, error) {
                    error = error.hasOwnProperty('error') ? error.error : error;
                    Ext.ux.PopupMessage.msg(_('Error'), file.name + ': ' + error);
                });

                this.on('addedfile', function () {
                    window.clearTimeout(thisDialog.thisDialogTimeoutId);
                    thisDialog.thisDialogTimeoutId = setTimeout(function () {
                        thisDialog.doUpload();
                    }, 100);
                });

                // Save the count of all successfully uploaded files
                this.on('success', function (file, response) {
                    thisDialog.settings.onFileUpload(response.files[0]);
                    thisDialog.totalProcessedFilesCount++;
                });
            }
        });
    },

    doUpload: function () {
        var thisDialog = this;

        // If there are files for uploading - go on
        var files = thisDialog.thisDialogDropZone.getQueuedFiles();
        if (!files.length) {
            return;
        }

        // Check if there are duplicates
        var countDuplicates = 0;
        var only_filename   = '';

        // Show confirmation if there are duplicates
        if (countDuplicates > 0) {
            var msg = String.format(countDuplicates === 1 ? _('{1} already exists, would you like to overwrite it?') :
                _('{0} documents already exist, would you like to overwrite them?'), countDuplicates, only_filename);
            Ext.Msg.confirm('Please confirm', msg, function (btn) {
                if (btn === 'yes') {
                    thisDialog.thisDialogDropZone.processQueue();
                } else {
                    // On "no" - clear the queued files
                    for (var j = 0; j < files.length; j++) {
                        thisDialog.thisDialogDropZone.removeFile(files[j]);
                    }
                }
            });
        } else {
            thisDialog.thisDialogDropZone.processQueue();
        }
    }
});