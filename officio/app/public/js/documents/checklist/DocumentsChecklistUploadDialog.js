var DocumentsChecklistUploadDialog = function (config) {
    Ext.apply(this, config);

    var thisDialog = this;

    this.dropzoneDivId         = Ext.id();
    this.thisDialogDropZone    = null;
    this.addedFilesCount       = 0;
    this.familyMembersComboIds = [];

    this.familyMembersStore = new Ext.data.Store({
        url: baseUrl + '/documents/checklist/get-family-members',
        baseParams: {
            member_id: thisDialog.settings.member_id,
            required_file_id: getNodeId(thisDialog.settings.node)
        },

        reader: new Ext.data.JsonReader(
            {
                id: 'real_id',
                root: 'family_members',
                totalProperty: 'totalCount'
            }, [
                {name: 'value'},
                {name: 'lName'},
                {name: 'fName'},
                {name: 'real_id'},
                {name: 'order'}
            ])
    });

    thisDialog.thisDialogUploadButton = new Ext.Button({
        text: _('Upload'),
        cls: 'orange-btn',
        handler: function () {
            thisDialog.doUpload()
        }
    });

    thisDialog.thisDialogCancelButton = new Ext.Button({
        text:    _('Cancel'),
        handler: this.onDialogCancel.createDelegate(this)
    });

    thisDialog.formPanel = new Ext.FormPanel({
        layout: 'form',

        items: [
            {
                layout: 'form',
                id: 'documents-checklist-files-list',
                hidden: true,
                items: [
                    {
                        xtype:  'container',
                        layout: 'column',
                        fileUpload: true,
                        width: 790,
                        style: 'padding: 5px 5px 5px 10px;',
                        items: [
                            {
                                layout: 'form',
                                columnWidth: 0.4,
                                items: {
                                    xtype: 'label',
                                    style: 'font-size: 14px; padding-right: 5px;',
                                    text: ''
                                }
                            }, {
                                layout: 'form',
                                columnWidth: 0.6,
                                items: {
                                    xtype: 'label',
                                    style: 'font-size: 14px; padding-left: 135px;',
                                    text: 'Document belongs to:'
                                }
                            }
                        ]
                    }
                ],
            },
            {
                xtype: 'box',
                autoEl: {
                    tag: 'div',
                    id: this.dropzoneDivId,
                    'class': 'dropzone documents-files-upload-dropzone-checklist'
                }
            }
        ]
    });

    DocumentsChecklistUploadDialog.superclass.constructor.call(this, {
        title:       '<i class="las la-file-upload"></i>' + _('Upload file(s)') + (this.settings.folder_name ? '. ' + _('Folder name: ') + this.settings.folder_name : ''),
        closeAction: 'close',
        modal:       true,
        resizable:   false,
        autoHeight:  true,
        width:       810,
        layout:      'form',

        items: [thisDialog.formPanel],

        buttons: [
            thisDialog.thisDialogCancelButton,
            thisDialog.thisDialogUploadButton
        ]
    });

    this.on('beforeshow', this.loadFamilyMembersStore, this);
    this.on('show', this.initDropzone.createDelegate(this));
};

Ext.extend(DocumentsChecklistUploadDialog, Ext.Window, {

    loadFamilyMembersStore: function() {
        this.familyMembersStore.load();
    },

    onDialogCancel: function () {
        var thisDialog = this;
        thisDialog.close();
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
            url:                thisDialog.settings.tree.destinationUrl,
            uploadMultiple:     true,
            autoProcessQueue:   false,
            paramName:          'docs-upload-file',
            parallelUploads:    10,
            maxFiles:           10,
            timeout:            1000 * 60 * 5, // 5 minutes
            maxFilesize:        post_max_size / 1024 / 1024, // MB
            dictFileTooBig:     _('File is too big ({{filesize}}MiB).'),
            dictDefaultMessage: defaultMessage,

            params: {
                member_id: thisDialog.settings.member_id,
                required_file_id: getNodeId(thisDialog.settings.node)
            },

            init: function () {
                // Show errors if any
                this.on('error', function (file, error) {
                    thisDialog.thisDialogDropZone.removeFile(file);
                    error = error.hasOwnProperty('error') ? error.error : error;
                    Ext.ux.PopupMessage.msg(_('Error'), file.name + ': ' + error);
                });

                this.on('addedfile', function (file) {
                    var count = thisDialog.addedFilesCount;
                    if (count > 9 || file.size > post_max_size) {
                        return;
                    }

                    var familyMembersComboId = Ext.id();

                    var familyMembersCombo = new Ext.form.ComboBox({
                        id: familyMembersComboId,
                        width: 430,
                        hideLabel: true,
                        store: thisDialog.familyMembersStore,
                        displayField: 'value',
                        valueField: 'real_id',
                        mode: 'local',
                        lazyInit: false,
                        typeAhead: false,
                        editable: false,
                        forceSelection: true,
                        triggerAction: 'all',
                        allowBlank: false,
                        selectOnFocus: true,
                        listeners: {
                            select: function(c, r) {
                                // Check if there are duplicates
                                var realDependentId = r.data.real_id;
                                var booDuplicate    = false;
                                var only_filename   = file.upload.filename;
                                var str_filename    = file.upload.filename;
                                var folderId        = getNodeId(thisDialog.settings.node);

                                if (str_filename.lastIndexOf('/') > 0) {
                                    only_filename = str_filename.substring(str_filename.lastIndexOf('/') + 1, str_filename.length);
                                } else if (str_filename.lastIndexOf('\\') > 0) {
                                    only_filename = str_filename.substring(str_filename.lastIndexOf('\\') + 1, str_filename.length);
                                }

                                if (checkIfFileExists(thisDialog.settings.tree, folderId, only_filename, true, realDependentId)) {
                                    booDuplicate = true;
                                }

                                // Show confirmation if there are duplicates
                                if (booDuplicate) {
                                    var msg = String.format('{0} already exists for selected dependant, would you like to overwrite it?', only_filename);
                                    Ext.Msg.confirm('Please confirm', msg, function (btn) {
                                        if (btn === 'yes') {
                                            thisDialog.thisDialogDropZone.on('sending', function(file, xhr, formData) {
                                                // Will send the dependent_id along with the file as POST data.
                                                formData.append("dependent_ids[" + count + "]", realDependentId);
                                            });
                                        } else {
                                            thisDialog.thisDialogDropZone.removeFile(file);
                                            Ext.getCmp('documents-checklist-files-list').remove(familyMembersCombo.ownerCt.ownerCt);

                                            var indexOfIdToDelete = thisDialog.familyMembersComboIds.indexOf(familyMembersComboId);
                                            if (indexOfIdToDelete > -1) {
                                                thisDialog.familyMembersComboIds.splice(indexOfIdToDelete, 1);
                                            }

                                            thisDialog.addedFilesCount--;
                                            if (thisDialog.addedFilesCount == 0) {
                                                Ext.getCmp('documents-checklist-files-list').setVisible(false);
                                            }
                                            thisDialog.formPanel.doLayout();
                                        }
                                    });
                                }

                                thisDialog.thisDialogDropZone.on('sending', function(file, xhr, formData) {
                                    // Will send the dependent_id along with the file as POST data.
                                    formData.append("dependent_ids[" + count + "]", realDependentId);
                                });
                            }
                        }
                    });

                    var rowData = {
                        xtype:  'container',
                        layout: 'column',
                        fileUpload: true,
                        width: 790,
                        style: 'padding: 5px 5px 5px 10px;',
                        items: [
                            {
                                layout: 'form',
                                columnWidth: 0.4,
                                style: 'padding-top: 10px',
                                items: {
                                    xtype: 'label',
                                    style: 'font-size: 14px; padding-right: 5px;',
                                    text: file.upload.filename
                                }
                            }, {
                                layout: 'form',
                                columnWidth: 0.6,
                                items: [familyMembersCombo]
                            }
                        ]
                    };

                    Ext.getCmp('documents-checklist-files-list').add(rowData);
                    Ext.getCmp('documents-checklist-files-list').setVisible(true);
                    Ext.getCmp('documents-checklist-files-list').doLayout();
                    thisDialog.addedFilesCount++;
                    thisDialog.familyMembersComboIds.push(familyMembersComboId);
                });

                this.on('successmultiple', function () {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.success('Done.');
                    reload_keep_expanded(thisDialog.settings.tree);
                    thisDialog.close();
                });
            }
        });
    },

    doUpload: function () {
        var thisDialog = this;

        for (i = 0; i < thisDialog.familyMembersComboIds.length; i++) {
            if (empty(Ext.getCmp(thisDialog.familyMembersComboIds[i]).getValue()) && Ext.getCmp(thisDialog.familyMembersComboIds[i]).getValue() !== 0) {
                Ext.simpleConfirmation.warning('Please select a family member for each document uploaded.');
                return;
            }
        }

        // If there are files for uploading - go on
        var files = thisDialog.thisDialogDropZone.getQueuedFiles();

        if (!files.length) {
            return;
        }

        thisDialog.getEl().mask('Uploading...');

        thisDialog.thisDialogDropZone.options.params.files = files.length;
        thisDialog.thisDialogDropZone.processQueue();
    }
});