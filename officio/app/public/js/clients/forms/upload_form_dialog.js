var FormsUploadDialog = function(record, grid) {
    var thisDialog           = this;
    this.dropzoneDivId       = Ext.id();
    this.thisDialogTimeoutId = null;
    this.thisDialogDropZone  = null;
    this.record              = record;
    this.grid                = grid;

    var currentClientName = '';
    var clientsTabPanel = Ext.getCmp('applicants-tab-panel');
    if (clientsTabPanel) {
         currentClientName = clientsTabPanel.getActiveCaseName();
    }

    var label = String.format(
        'Please upload your latest revision to the<br/>' +
        'form: <b>{0}</b><br/>' +
        'for case: <b>{1}</b><br/><br/>' +
        '<span style="color: red;">Please do not upload any other form in this section as your main form will be erased.</span><br/><br/>',
        record.data.file_name,
        currentClientName
    );

    this.thisDialogCancelButton = new Ext.Button({
        text:    _('Close'),
        handler: function () {
            thisDialog.close();
        }
    });

    this.uploadForm = new Ext.form.FormPanel({
        fileUpload: true,
        labelWidth: 100,
        defaults: {
            msgTarget: 'side'
        },

        items: [
            {
                xtype: 'label',
                style: 'font-size: 13px;',
                html: label
            }, {
                xtype:  'box',
                autoEl: {
                    tag:     'div',
                    id:      this.dropzoneDivId,
                    'class': 'dropzone form-upload-dropzone'
                }
            }
        ]
    });

    this.buttons = [
        thisDialog.thisDialogCancelButton
    ];

    FormsUploadDialog.superclass.constructor.call(this, {
        title: 'Upload form',
        width: 560,
        autoHeight: true,

        plain: false,
        bodyStyle: 'padding: 10px 5px 5px; background-color:#fff;',
        modal: true,
        resizable: false,

        items: this.uploadForm
    });

    this.on('show', this.initDropzone.createDelegate(this));
};


Ext.extend(FormsUploadDialog, Ext.Window, {
    initDropzone: function(){
        var thisDialog = this;

        var defaultMessage = String.format(
            '<img src="{0}/images/upload.svg" width="48" height="48" class="grey-out" /><br><br>' +
            _('Drop PDF file here or click to upload ') +
            '<span style="display: block; font-size: small; padding-top: 10px;">' +
            _('Maximum file size: {1}MB') + '<br><br>' +
            '</span>',
            topBaseUrl,
            post_max_size / 1024 / 1024
        );

        thisDialog.thisDialogDropZone = new Dropzone('#' + this.dropzoneDivId, {
            url:                baseUrl + '/forms/index/upload-revision',
            uploadMultiple:     false,
            autoProcessQueue:   false,
            paramName:          'form-revision',
            maxFiles:           1,
            timeout:            1000 * 60 * 5, // 5 minutes
            maxFilesize:        post_max_size / 1024 / 1024, // MB
            dictFileTooBig:     _('File is too big ({{filesize}}MiB).'),
            acceptedFiles:      ".pdf",
            dictDefaultMessage: defaultMessage,

            params: {
                form_id: thisDialog.record.data.client_form_id
            },

            init: function () {
                // Show errors if any
                this.on('error', function (file, result) {
                    var error = result.hasOwnProperty('msg') ? result.msg : error;
                    Ext.simpleConfirmation.error(error);
                    thisDialog.grid.store.reload();
                    thisDialog.close();
                });

                this.on('addedfile', function () {
                    window.clearTimeout(thisDialog.thisDialogTimeoutId);
                    thisDialog.thisDialogTimeoutId = setTimeout(function () {
                        thisDialog.doUpload();
                    }, 100);
                });

                this.on('success', function (file, result) {
                    if (result.success) {
                        Ext.simpleConfirmation.success('Done.');
                        thisDialog.grid.store.reload();
                    } else {
                        Ext.simpleConfirmation.error(result.msg);
                    }
                    thisDialog.close();
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