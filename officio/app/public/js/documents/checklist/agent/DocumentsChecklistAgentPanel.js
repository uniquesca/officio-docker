var __booEditFormForSafari = Ext.isMac;
var __booMergeDataOnServer = false;

var DocumentsChecklistAgentPanel = function (config) {
    Ext.apply(this, config);

    DocumentsChecklistAgentPanel.superclass.constructor.call(this, {
        autoWidth:  true,
        autoHeight: true,
        style:      'padding: 5px;',

        items: []
    });

    this.on('render', this.loadDocumentsChecklist.createDelegate(this), this);
};

Ext.extend(DocumentsChecklistAgentPanel, Ext.Panel, {
    loadDocumentsChecklist: function () {
        var thisPanel = this;
        Ext.getBody().mask(_('Processing...'));
        Ext.Ajax.request({
            url: baseUrl + '/documents/checklist/get-list',
            params: {
                clientId: thisPanel.clientId,
                booAgent: Ext.encode(true)
            },

            success: function (result) {
                Ext.getBody().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.error) {
                    // Show error message
                    Ext.simpleConfirmation.error(resultDecoded.error);
                } else {
                    var booSubmitted = resultDecoded.submitted;
                    var arrDocuments = [];
                    Ext.each(resultDecoded.list, function (oDocument) {
                        var uploadLink;
                        var booCanBeEdited = !(booSubmitted && oDocument['read_only_if_submitted']);
                        if (!booCanBeEdited) {
                            uploadLink = new Ext.form.DisplayField({
                                xtype:  'displayfield',
                                style:  'font-size: 12px; padding-left: 10px; margin-top: 10px; color: #4D4D4E',
                                value:  _(oDocument['text'])
                            });
                        } else {
                            uploadLink = new Ext.BoxComponent({
                                xtype: 'box',
                                style: 'padding-left: 10px; margin-top: 10px',
                                width: 360,

                                autoEl: {
                                    tag:        'a',
                                    href:       '#',
                                    style:      'font-size: 12px; display: block;',
                                    'class':    'blulinkunb',
                                    html:       _(oDocument['text']),
                                    'ext:qtip': String.format(_('Click to upload {0} file(s).'), _(oDocument['text']))
                                },

                                listeners: {
                                    scope:  this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            thisPanel.uploadDocument(oDocument['el_id'], oDocument['text']);
                                        }, this, {stopEvent: true});
                                    }
                                }
                            });
                        }

                        var children = [];
                        var arrDependents = [];
                        if (!empty(oDocument.children)) {
                            Ext.each(oDocument.children, function (child) {
                                var booFileIsNotUploaded = !child.is_file;
                                var booPreview           = !child.showPreview;
                                var formData             = child.form_data;
                                var formFormat           = !empty(formData) && formData.client_form_format ? formData.client_form_format : '';

                                if (!empty(formData) && booCanBeEdited) {
                                    arrDependents.push([child['dependent'], formData, formFormat]);
                                }

                                children.push(
                                    {
                                        xtype:  'container',
                                        layout: 'column',

                                        items: [
                                            {
                                                xtype:  'box',
                                                hidden: !booFileIsNotUploaded || !booCanBeEdited,
                                                style:  'margin-left: 50px; margin-top:3px;',

                                                autoEl: {
                                                    tag:        'a',
                                                    href:       '#',
                                                    'class':    'blulinkunb',
                                                    style:      'font-size: 12px; font-weight: normal; color: #000',
                                                    html:       child['dependent'],
                                                    'ext:qtip': String.format(_('Click to upload {0} file(s) for {1}'), oDocument['text'], child['dependent'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            thisPanel.uploadDocument(oDocument['el_id'], oDocument['text'], child['dependent_id']);
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }, {
                                                xtype:  'box',
                                                hidden: booFileIsNotUploaded,
                                                style:  'margin-left: 50px; margin-top:3px;',

                                                autoEl: {
                                                    tag:        'a',
                                                    href:       '#',
                                                    'class':    'blulinkunb preview',
                                                    style:      'font-size: 12px; font-weight: normal; color: #000',
                                                    html:       child['dependent'] + ' ' + child['text'],
                                                    'ext:qtip': booPreview ? String.format(_('Click to preview {0}'), child['text']) : String.format(_('Click to download {0}'), child['text'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            if (this.showPreview) {
                                                                var previewDialogId = 'docs-checklist-file-preview';
                                                                var previewDialog   = Ext.getCmp(previewDialogId);
                                                                if (previewDialog) {
                                                                    previewDialog.close();
                                                                }

                                                                previewDialog = new Ext.Window({
                                                                    id:     previewDialogId,
                                                                    title:  'Preview ' + this.text,
                                                                    layout: 'fit',
                                                                    modal:  false,
                                                                    width:  650,
                                                                    height: 320,

                                                                    tools: [
                                                                        {
                                                                            id:      'save',
                                                                            qtip:    String.format(_('Click to download {0}'), child['text']),
                                                                            handler: function () {
                                                                                thisPanel.downloadDocument(child['id']);
                                                                            }
                                                                        }
                                                                    ],

                                                                    items: {
                                                                        xtype:          'iframepanel',
                                                                        cls:            'preview-file-iframe',
                                                                        header:         false,
                                                                        deferredRender: false,
                                                                        defaultSrc:     baseUrl + '/documents/checklist/download/id/' + child['id'] + '/preview/1',
                                                                        autoWidth:      true,
                                                                        frameConfig:    {
                                                                            autoLoad: {
                                                                                width: '100%'
                                                                            },
                                                                            style:    'height: 100%'
                                                                        },

                                                                        listeners: {
                                                                            'documentloaded': function (p1) {
                                                                                try {
                                                                                    // Try to remove padding that is automatically generated by browser
                                                                                    // when we preivew image files
                                                                                    $('#' + p1.id).contents().find('head').append($("<style type='text/css'>  body {margin: 0}  </style>"));
                                                                                } catch (e) {
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                });
                                                                previewDialog.show();
                                                                previewDialog.alignTo(Ext.getBody(), 'r-r', [-25, 0]);
                                                            } else {
                                                                thisPanel.downloadDocument(child['id']);
                                                            }
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }, {
                                                xtype:  'box',
                                                style:  'margin: 8px 5px 0; cursor: pointer;',
                                                hidden: booFileIsNotUploaded || !booCanBeEdited,

                                                autoEl: {
                                                    tag:        'img',
                                                    src:        topBaseUrl + '/images/icons/cross_blue.png',
                                                    'ext:qtip': String.format(_('Click to delete {0}'), child['text'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            thisPanel.deleteDocument(child['id'], child['text']);
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }, {
                                                xtype:  'box',
                                                style:  'margin: 8px 5px 0 0; cursor: pointer;',
                                                hidden: booFileIsNotUploaded,

                                                autoEl: {
                                                    tag:        'img',
                                                    height:     '10px',
                                                    src:        topBaseUrl + '/images/icons/download.png',
                                                    'ext:qtip': String.format(_('Click to download {0}'), child['text'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            thisPanel.downloadDocument(child['id']);
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }
                                            /*
                                            , {
                                                xtype:  'displayfield',
                                                hidden: empty(formData),
                                                style:  'font-size: 12px; margin-left: 10px; margin-top:3px; color: #4D4D4E',
                                                value:  '('
                                            }, {
                                                xtype:  'box',
                                                hidden: empty(formData),
                                                style:  'margin-top:3px;',

                                                autoEl: {
                                                    tag:        'a',
                                                    href:       '#',
                                                    'class':    'blulinkunb',
                                                    style:      'font-size: 12px; margin-left: 2px; font-weight: normal;',
                                                    html:       'View',
                                                    'ext:qtip': String.format(_('Click to download a read only {0} pdf file for {1}'), oDocument['text'], child['dependent'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            thisPanel.viewForm(formData, thisPanel.clientId);
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }, {
                                                xtype:  'displayfield',
                                                hidden: empty(formData) || !booCanBeEdited,
                                                style:  'font-size: 12px; margin-left: 5px; margin-top:3px; color: #4D4D4E',
                                                value:  '|'
                                            }, {
                                                xtype:  'box',
                                                hidden: empty(formData) || !booCanBeEdited,
                                                style:  'margin-left: 10px; margin-top:3px; margin-left:5px;',

                                                autoEl: {
                                                    tag:        'a',
                                                    href:       '#',
                                                    'class':    'blulinkunb',
                                                    style:      'font-size: 12px; font-weight: normal;',
                                                    html:       'Edit',
                                                    'ext:qtip': String.format(_('Click to edit {0} for {1}'), oDocument['text'], child['dependent'])
                                                },

                                                listeners: {
                                                    scope:  this,
                                                    render: function (c) {
                                                        c.getEl().on('click', function () {
                                                            thisPanel.showEditForm(formData, formFormat);
                                                        }, this, {stopEvent: true});
                                                    }
                                                }
                                            }, {
                                                xtype:  'displayfield',
                                                hidden: empty(formData),
                                                style:  'font-size: 12px; margin-left: 5px; margin-top:3px; color: #4D4D4E',
                                                value:  'PDF Form)'
                                            }
                                             */
                                        ]
                                    }
                                );
                            });
                        }

                        var document = {
                            xtype:  'container',
                            layout: 'table',
                            layoutConfig: { columns: 4 },

                            items: [
                                uploadLink,
                                {
                                    html: '&nbsp;&nbsp;&nbsp;'
                                        }, {
                                    xtype: 'box',
                                    hidden: empty(arrDependents.length),
                                    autoEl: {
                                        tag: 'a',
                                        href: '#',
                                        'class': 'bluelink',
                                        style: 'padding-right: 20px; text-decoration: none;',
                                        html: 'Download filled forms <i class="las la-caret-down"></i>'
                                    },
                                    listeners: {
                                        scope: this,
                                        render: function (c) {
                                            var element = c.getEl();
                                            c.getEl().on('click', function () {
                                                thisPanel.showMenuToLink(arrDependents, element);
                                            }, this, {stopEvent: true});
                                        }
                                    }
                                }, {
                                    hidden: empty(arrDependents.length),
                                    html:   String.format(
                                        _('<img src="{0}/images/icons/help.png" width="16" height="16" alt="Help" ext:qtip="Based on the information provided the following forms are prepared. You can review, print and upload them." style="cursor: help" />'),
                                        topBaseUrl
                                    )
                                }
                            ]
                        };

                        arrDocuments.push(
                            document,
                            children
                        );
                    });

                    if (empty(arrDocuments.length)) {
                        arrDocuments.push({
                            html: _('There are no required documents.')
                        });
                    }

                    thisPanel.removeAll();

                    var infoAboutPage = {
                        xtype:  'container',
                        layout: 'column',

                        items: [
                            {
                                xtype: 'displayfield',
                                style: 'font-size: 12px; padding-left: 10px; font-weight: bold; color: #4D4D4E',
                                value: 'The following documents are required for the Citizenship application. Please click on each document type to start uploading.'
                            }
                        ]
                    };

                    thisPanel.add(infoAboutPage);
                    thisPanel.add(arrDocuments);
                    thisPanel.doLayout();

                    var tabPanel = Ext.getCmp(thisPanel.panelType + '-tab-panel');
                    if (tabPanel) {
                        tabPanel.fixParentPanelHeight();
                    }
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(_('Internal error. Please try again later.'));
            }
        });
    },

    showEditForm: function (r, strFormFormat) {
        var thisPanel = this;
        if (r) {
            var pdf_id = r.client_form_id;

            var msg = String.format(
                'Since the last time you worked on this form, a newer version has become available.<br/><br/>' +
                'Would you like Officio to transfer your data from the old form to the new form?'
            );

            // Check if there is NOT installed Adobe Acrobat PDF plugin
            // Show different messages for different cases
            var booPluginInstalled = (PluginDetect.isMinVersion('AdobeReader', '0') >= 0);

            if (strFormFormat == 'pdf' && r.client_form_format != 'pdf' && !booPluginInstalled) {
                var msg1 = String.format(
                    'Your browser is not configured properly to open PDF forms.<br/>' +
                    'Your form will now open in HTML mode.'
                );

                Ext.Msg.show({
                    title: 'Please confirm',
                    msg: msg1,
                    minWidth: 300,
                    modal: true,
                    icon:     Ext.MessageBox.WARNING,
                    buttons:  {
                        yes: 'Ok',
                        no:  'Cancel'
                    },

                    fn: function (btn) {
                        if (btn === 'yes') {
                            switch (r.client_form_format) {
                                case 'xod':
                                    var oParams = {
                                        p: pdf_id,
                                        m: thisPanel.clientId,
                                        a: r.client_form_annotations,
                                        h: r.client_form_help_article_id
                                    };

                                    if (r.client_form_version_latest) {
                                        Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                                    } else {
                                        Ext.MessageBox.minWidth = 540;
                                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                                            var latest = btn === 'yes' ? '1' : '0';

                                            oParams['l'] = latest;
                                            Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                                            if (!empty(latest)) {
                                                setTimeout(function () {
                                                    //thisPanel.store.reload();
                                                }, (2000));
                                            }
                                        });
                                    }
                                    break;

                                default:
                                    if (is_client && r.locked && r.client_form_format === 'angular') {
                                        window.open(baseUrl + '/pdf/' + r.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
                                    } else {
                                        window.open(baseUrl + '/pdf/' + r.client_form_version_id + '/?assignedId=' + pdf_id);
                                    }
                            }
                        }
                    }
                });

                return false;
            }

            switch (strFormFormat) {
                case 'xod':
                    var oParams = {
                        p: pdf_id,
                        m: thisPanel.clientId,
                        a: r.client_form_annotations,
                        h: r.client_form_help_article_id
                    };

                    if (r.client_form_version_latest) {
                        Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                    } else {
                        Ext.MessageBox.minWidth = 540;
                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                            var latest = btn === 'yes' ? '1' : '0';

                            oParams['l'] = latest;
                            Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                        });
                    }
                    break;

                case 'pdf':
                    var pdf_name = r.file_name_stripped;

                    if (r.use_revision && r.use_revision === 'Y') {
                        this.downloadRevision(pdf_id, 0, pdf_name);
                        return;
                    }

                    var msgError;
                    var linkStyle = 'style="color: #16429B; font-size: 12px;"';

                    if (!booPluginInstalled) {
                        if (Ext.isChrome) {
                            msgError = String.format(
                                'Your browser is not configured properly.<br/>' +
                                ' As a result, Officio forms cannot function properly.<br/><br/>' +
                                ' To configure Chrome to operate with Officio forms,<br/>please click here: <a href="{0}" target="_blank" {1}>{0}</a>.' +
                                ' Alternatively, you can use a different browser.',
                                site_version == 'australia' ? 'https://secure.officio.com.au/help/public/#q16' : 'http://uniques.ca/officio_support/chrome',
                                linkStyle
                            );
                        } else {
                            msgError = String.format(
                                'Adobe Acrobat/Reader does not exist, or it is not set as your default plugin for your browser.' +
                                ' As a result, your forms cannot function properly.<br/><br/>' +
                                ' Please install Adobe Reader by clicking here: <a href="{0}" target="_blank" {1}>{0}</a>.' +
                                ' Once Adobe Reader is installed, close your browser, and open it again.',
                                'http://get.adobe.com/reader',
                                linkStyle
                            );
                        }
                    }

                    // Show a warning message and don't allow to open a form
                    if (!empty(msgError)) {
                        Ext.simpleConfirmation.warning(msgError);
                        return;
                    }

                    var oParams = {
                        member_id:          thisPanel.clientId,
                        pdf_id:             pdf_id,
                        pdf_name:           pdf_name,
                        pdf_data:           r,
                        use_latest_version: false
                    };

                    var booInNewTab = false;
                    if (r.client_form_type === 'bar' || r.client_form_version_latest) {
                        this.openPdfForm(oParams, booInNewTab);
                    } else {
                        Ext.MessageBox.minWidth = 540;
                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                            if (btn === 'yes') {
                                // Update record in the grid - we don't want refresh it again
                                r.client_form_version_latest = true;
                                r.commit();

                                // And open the latest form
                                oParams.use_latest_version = true;
                                thisPanel.openPdfForm(oParams, booInNewTab);
                            } else {
                                thisPanel.openPdfForm(oParams, booInNewTab);
                            }
                        });
                    }
                    break;

                default:
                    if (is_client && r.locked && r.client_form_format === 'angular') {
                        window.open(baseUrl + '/pdf/' + r.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
                    } else {
                        window.open(baseUrl + '/pdf/' + r.client_form_version_id + '/?assignedId=' + pdf_id);
                    }
                    break;
            }
        }
    },

    showMenuToLink: function (arrDependents, element) {
        var thisPanel = this;
        var menuForms = new Ext.menu.Menu({
            cls: 'no-icon-menu'
        });
        var iter;
        for (var i = 0; i < arrDependents.length; i++) {
            iter = i;
            menuForms.add({
                text: arrDependents[iter][0],
                handler: function () {
                    thisPanel.showEditForm(arrDependents[iter][1], arrDependents[iter][2]);
                }
            });
        }
        var getY = element.getY() + 20;
        menuForms.showAt([element.getX(), getY]);
    },

    openPdfForm: function (oParams, booInNewTab) {
        var tab_container_id = 'forms-tab-' + oParams.member_id;
        var tab_id           = tab_container_id + '_pdf' + oParams.pdf_id;

        // Check the form type (to generate the url)
        var open_pdf_url = '';
        if (oParams.pdf_data.client_form_type === 'bar') {
            open_pdf_url = baseUrl + '/forms/index/open-xdp/pdfid/' + oParams.pdf_id + '/' + oParams.pdf_name + '.xdp';
        } else {
            var latest  = oParams.use_latest_version ? '1' : '0';
            var pdf_url = baseUrl + '/forms/index/open-assigned-pdf/pdfid/' + oParams.pdf_id + '/latest/' + latest;
            if (__booMergeDataOnServer) {
                open_pdf_url = pdf_url + '/merge/1/file/' + oParams.pdf_name + '.pdf';
            } else {
                var mergeXfdf = __booEditFormForSafari ? '/merge/1' : '';
                var xfdf_url  = baseUrl + '/forms/index/open-assigned-xfdf/pdfid/' + oParams.pdf_id + mergeXfdf;
                open_pdf_url  = pdf_url + '#FDF=' + xfdf_url;
            }
        }

        // Check if we need to show in new window or tab
        if (booInNewTab !== false) {
            // Generate tab title
            var tab_title;
            if (empty(oParams.pdf_data.family_member_lname) && empty(oParams.pdf_data.family_member_fname)) {
                tab_title = oParams.pdf_data.file_name_stripped;
            } else {
                tab_title = oParams.pdf_data.family_member_lname;
                if (!empty(tab_title) && !empty(oParams.pdf_data.family_member_fname)) {
                    tab_title += ', ';
                }

                tab_title += oParams.pdf_data.family_member_fname + ' - ' + oParams.pdf_name;
            }

            // Show in the tab
            var tabPanel = Ext.getCmp(tab_container_id);

            // Open new or activate existing tab
            var newTab = Ext.getCmp(tab_id);
            if (!newTab) {
                newTab = tabPanel.add({
                    id:             tab_id,
                    xtype:          'iframepanel',
                    title:          tab_title,
                    closable:       true,
                    deferredRender: false,
                    defaultSrc:     open_pdf_url,
                    frameConfig:    {
                        autoLoad: {
                            id:    'assignedpdf-iframe-' + this.member_id + '-' + oParams.pdf_id,
                            width: '100%'
                        },
                        style:    'height: 555px;'
                    }
                });
            }

            tabPanel.doLayout();  //if TabPanel is already rendered
            tabPanel.setActiveTab(newTab);
        } else {
            // Show in new window
            window.open(open_pdf_url);
        }
    },

    viewForm: function (record, clientId) {
        var pdf_id   = record.client_form_id;
        var pdf_name = record.file_name_stripped;

        if (record.use_revision && record.use_revision === 'Y') {
            this.downloadRevision(pdf_id, 0, pdf_name);
        } else if (record.client_form_format === 'angular' || record.client_form_format === 'html') {
            window.open(baseUrl + '/pdf/' + record.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
        } else {
            window.open(baseUrl + '/forms/index/print/member_id/' + clientId + '/pdfid/' + pdf_id);
        }
    },

    uploadDocument: function (required_file_id, folder_name, dependent_id) {
        var thisPanel = this;

        var dialog = new DocumentsChecklistUploadDialog({
            floating: {shadow: false},
            settings: {
                booAgent:         true,
                panel:            thisPanel,
                destinationUrl:   baseUrl + '/documents/checklist/upload',
                member_id:        thisPanel.clientId,
                dependent_id:     dependent_id,
                required_file_id: required_file_id,
                folder_name:      folder_name
            }
        });

        dialog.show();
        dialog.center();
    },

    downloadDocument: function (id) {
        window.open(baseUrl + '/documents/checklist/download/id/' + id);
    },

    deleteDocument: function (id, name) {
        var thisPanel = this;

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete <span style="font-style: italic;">' + name + '</span>?', function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url:    baseUrl + '/documents/checklist/delete',
                    params: {
                        clientId: Ext.encode(thisPanel.clientId),
                        fileId:   Ext.encode(id)
                    },

                    success: function (result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.info(resultDecoded.message);
                            thisPanel.loadDocumentsChecklist();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('File <span style="font-style: italic;">' + name + '</span> cannot be deleted. Please try again later.');
                    }
                });
            }
        });
    }
});
