var UploadNotesAttachmentsDialog = function (config) {
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


    UploadNotesAttachmentsDialog.superclass.constructor.call(this, {
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

Ext.extend(UploadNotesAttachmentsDialog, Ext.Window, {
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
            url:                thisDialog.settings.type == 'prospect' ? topBaseUrl + '/prospects/index/upload-attachments' : topBaseUrl + '/notes/index/upload-attachments',
            uploadMultiple:     false,
            autoProcessQueue:   false,
            paramName:          'note-attachment',
            parallelUploads:    50,
            maxFiles:           50,
            timeout:            1000 * 60 * 5, // 5 minutes
            maxFilesize:        post_max_size / 1024 / 1024, // MB
            dictFileTooBig:     _('File is too big ({{filesize}}MiB).'),
            dictDefaultMessage: defaultMessage,

            params: {
                note_id: thisDialog.settings.note_id,
                act: thisDialog.settings.act,
                type: thisDialog.settings.tabType,
                company_id: thisDialog.settings.company_id,
                member_id: thisDialog.settings.member_id,
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
                    note_file_attachments.push({
                        attach_id: response.files[0].tmp_name,
                        tmp_name: response.files[0].tmp_name,
                        name: response.files[0].name,
                        size: response.files[0].size,
                        extension: response.files[0].extension
                    });

                    var downloadUrl = thisDialog.settings.type == 'prospect' ? topBaseUrl + '/prospects/index/download-attachment' : topBaseUrl + '/notes/index/download-attachment';
                    $('#attachments-panel').append('<div style="display: inline; padding-right: 7px;" id="' + response.files[0].tmp_name + '"><A onclick="submit_hidden_form(\'' + downloadUrl + '\', {member_id: \'' + thisDialog.settings.member_id + '\', type: \'uploaded\', attach_id: \'' + response.files[0].tmp_name + '\', name: \'' + response.files[0].name + '\'}); return false;" class="bluelink" href="#">' + response.files[0].name + '</A> <span style="font-size: 11px;">(' + response.files[0].file_size + ')</span> <img src="' + topBaseUrl + '/images/deleteicon.gif" class="template-attachment-cancel" onclick="removeNoteAttachment(this); return false;" alt="Cancel" /></div>');

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

        thisDialog.thisDialogDropZone.processQueue();
    }
});