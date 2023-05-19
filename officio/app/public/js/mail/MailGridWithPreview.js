MailGridWithPreview = function (config) {
    // Load custom settings from cookies
    var cookieHideSummary = this.getSettings('mail_hide_summary');
    var booShowSummary = stringToBoolean(cookieHideSummary) ? false : true;
    booShowSummary = false;

    var panelsSizeCookie = this.getSettings('mail_pans_size') ? this.getSettings('mail_pans_size') : '250,0,133';
    var panelsSizeCookieArray = panelsSizeCookie.split(',');
    var defaultRightPreviewWidth = parseInt(panelsSizeCookieArray[1], 10);
    var defaultBottomPreviewHeight = parseInt(panelsSizeCookieArray[2], 10);

    this.fetching_in_progress = false;

    this.preview = new Ext.Panel({
        id: 'mail_preview_panel',
        html: '<iframe id="mail_preview_iframe" name="mail_preview_iframe" style="border:none; background-color:white;" frameborder="0" width="100%" height="100%" scrolling="auto"></iframe>',
        border: true,
        region: 'south',
        clear: function () {
            var iframe = document.getElementById('mail_preview_iframe');
            if (iframe == null) {
                return;
            }

            var doc = iframe.contentDocument;
            if (empty(doc))
                doc = iframe.contentWindow.document;

            var img = String.format(
                '<div style="height: 100%; display: flex; background-color: #E8E9EB;"><img src="{0}/images/mail/empty_panel.png" alt="Please select email" style="{1} margin: auto;"/></div>',
                topBaseUrl,
                Ext.getCmp('mail-main-toolbar').previewMode === 'bottom' ? 'height: 80%;' : 'width: 80%;'
            );

            doc.open();
            doc.write(img);
            doc.close();

            function restyle() {
                var body = iframe.contentDocument.body;
                body.style.padding = 0;
                body.style.margin = 0;
            }

            iframe.onload = restyle;
            restyle();
        }
    });

    this.grid = new MailGrid(this, {
        // Pass this parameter, so summary will be showed
        showSummary: booShowSummary
    });

    var setBottomHeight = defaultBottomPreviewHeight !== 0 ? defaultBottomPreviewHeight : 250;
    var setRightWidth =    defaultRightPreviewWidth !== 0 ? defaultRightPreviewWidth : 350;

    MailGridWithPreview.superclass.constructor.call(this, {
        region: 'center',
        layout: 'border',
        hideTitle: true,
        hideMode: 'offsets',
        id: 'mail-grid-with-preview',
        margins: '10 5 5 0',

        items: [
            this.grid, {
                id: 'bottom-preview',
                layout: 'fit',
                items: config.previewMode == 'bottom' ? this.preview : null,
                hidden: config.previewMode != 'bottom',
                height: parseInt(setBottomHeight, 10),
                split: true,
                border: false,
                region: 'south',
                listeners: {
                    resize: function () {
                        Ext.getCmp('mail-tabpanel').fireEvent('resize_panels');
                    }
                }
            }, {
                id: 'right-preview',
                layout: 'fit',
                border: false,
                region: 'east',
                width: parseInt(setRightWidth, 10),
                split: true,
                items: config.previewMode == 'right' ? this.preview : null,
                hidden: config.previewMode != 'right',
                listeners: {
                    resize: function () {
                        Ext.getCmp('mail-tabpanel').fireEvent('resize_panels');
                    }
                }
            }
        ]
    });

    this.gsm = this.grid.getSelectionModel();

    this.gsm.on('rowselect', function(sm, index, record) {
        if (parseInt(record.data.is_downloaded, 10)===0)
        {
            record.data.mail_body='<IMG src="'+topBaseUrl+'/images/loading.gif" align="texttop"> Loading...';
            this.updatePreview(record.data);

            this.sendRequestToGetMail(record, false);
        }
        else
            this.updatePreview(record.data);
    }, this, {buffer: 250});

    this.grid.store.on('beforeload', this.preview.clear, this.preview);
    this.grid.store.on('load', function() {
        // Update unread messages count
        Ext.getCmp('mail-ext-folders-tree').updateCurrentFolderLabel(
            this.grid.store.reader.jsonData.totalUnread
        );
    }, this);

    this.grid.on('rowdblclick', this.emailDblClick, this);
};

Ext.extend(MailGridWithPreview, Ext.Panel, {
    getSettings: function (id) {
        return Ext.state.Manager.get(id);
    },

    saveSettings: function (id, value) {
        Ext.state.Manager.set(id, value);
    },

    updatePreview: function (data) {
        var iframe = document.getElementById('mail_preview_iframe');
        if (iframe == null) {
            return;
        }

        var doc = iframe.contentDocument;
        if (empty(doc))
            doc = iframe.contentWindow.document;

        doc.open();
        doc.write(this.getTemplate(false).apply(data));
        doc.close();
    },

    loadMails: function (folder_info, booDoNotDownloadEmails) {
        this.grid.loadMails(folder_info, booDoNotDownloadEmails);
    },

    movePreview: function (mode, pressed) {
        if (pressed) {
            var sel_record = this.grid.getSelectionModel().getSelected();
            var preview = this.preview;
            var right = Ext.getCmp('right-preview');
            var bot = Ext.getCmp('bottom-preview');
            switch (mode) {
                case 'bottom':
                    right.hide();
                    bot.add(preview);
                    bot.show();
                    bot.ownerCt.doLayout();

                    // get mail, if only headers were fetched
                    if (sel_record !== undefined && parseInt(sel_record.data.is_downloaded, 10) === 0) {
                        sel_record.data.mail_body = '<IMG src="' + topBaseUrl + '/images/loading.gif" align="texttop"> Loading...';

                        this.sendRequestToGetMail(sel_record, true);
                    } else {
                        preview.clear();
                    }

                    this.saveSettings('mail_preview_mode', 'bottom');
                    break;

                case 'right':
                    bot.hide();
                    right.add(preview);
                    right.show();
                    right.ownerCt.doLayout();

                    // get mail, if only headers were fetched
                    if (sel_record !== undefined && parseInt(sel_record.data.is_downloaded, 10) === 0) {
                        sel_record.data.mail_body = '<IMG src="' + topBaseUrl + '/images/loading.gif" align="texttop"> Loading...';

                        this.sendRequestToGetMail(sel_record, true);
                    } else {
                        preview.clear();
                    }

                    this.saveSettings('mail_preview_mode', 'right');
                    break;

                case 'hide':
                    preview.ownerCt.hide();
                    preview.ownerCt.ownerCt.doLayout();

                    this.saveSettings('mail_preview_mode', 'hide');
                    break;

                default:
                    break;
            }

            // Refresh preview
            if (sel_record)
                this.updatePreview(sel_record.data);
        }
    },

    getTemplate: function (booFullPreview) {
        var strBodyStyle = booFullPreview ? 'class="mail-item-preview-body"' : '';

        var tpl = new Ext.XTemplate(
            '<div class="eml-body" style="padding-left: 5px;">',
            '<link type="text/css" rel="stylesheet" href="' + topBaseUrl + '/assets/plugins/line-awesome/dist/line-awesome/css/line-awesome.min.css"/>',
            '<link type="text/css" rel="stylesheet" href="' + topBaseUrl + '/styles/eml.css"/>',
            '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:white;">',
            '<tr>',
            '<td class="eml-label" width="85">From</td>',
            '<td class="eml-header-value">{mail_from:defaultValue("Unknown")}</td>',
            '<td class="eml-header-value hide_while_print" width="50" align="right"><a onclick="window.print(); return false;" href="#" style="color: #4C83C5; text-decoration: none" title="Click to Print"><i class="las la-print" style="font-size: 20px; vertical-align: middle"></i> Print</a></td>',
            '</tr>',
            '<tr>',
            '<td class="eml-label">To</td>',
            '<td class="eml-header-value" colspan="2">{mail_to}</td>',
            '</tr>',
            '<tpl if="mail_cc != &quot;&quot;"><tr>',
            '<td class="eml-label">CC</td>',
            '<td class="eml-header-value" colspan="2">{mail_cc}</td>',
            '</tr></tpl>',
            '<tpl if="mail_bcc != &quot;&quot;"><tr>',
                    '<td class="eml-label">BCC</td>',
                    '<td class="eml-header-value" colspan="2">{mail_bcc}</td>',
                '</tr></tpl>',
                '<tr>',
                    '<td class="eml-label">Date</td>',
                    '<td class="eml-header-value" colspan="2">{mail_date:this.formatDate}</td>',
                '</tr>',
                '<tr>',
                    '<td class="eml-label">Subject</td>',
                    '<td class="eml-header-value" colspan="2"><b>{mail_subject}</b></td>',
                '</tr>',
                '<tpl if="attachments != &quot;&quot;"><tr>',
                    '<td valign="top" class="eml-label">Attachments</td>',
                    '<td class="eml-header-value" colspan="2">{attachments:this.formatAtt}</td>',
                '</tr></tpl>',
            '</table>',
            '<hr class="eml-divider" />',
            '<div ' + strBodyStyle + '>{mail_body:this.getBody}</div>',
            '</div>',
            '<script language="javascript">as = document.getElementsByTagName("a");',
            'for (var i=0; i<as.length; i++){',
                'if (!as[i].getAttribute("href")) {',
                    'continue;',
                '}',

                'if (as[i].href.indexOf("#")!==0) {',
                    'as[i].target="_blank";',
                '}',

                'if (as[i].href.indexOf("mailto:")===0) {',
                    'as[i].onclick=function(){parent.show_email_dialog({emailTo: this.href.replace("mailto:", "")}); return false;};',
                '}',
            '}',
            '</script>',
            {
                compiled: true,

                getBody: function(v, all) {
                    body_str = Ext.util.Format.stripScripts(v || all.description);
                    if (body_str !== undefined) {
                        body_str = body_str.replace(/<body/g, '<div');
                        body_str = body_str.replace(/<\/body>/g, '</div>');

                        body_str = body_str.replace(/<noscript[^>]*?>[\s\S]*?<\/noscript>/ig, '');
                        body_str = body_str.replace(/onLoad="(.*)"/ig, '');
                    }

                    return body_str === undefined ? '' : body_str;
                },

                formatDate: function(v) {
                    return Ext.getCmp('mail-ext-emails-grid').formatDate(v);
                },

                formatAtt: function(attachments) {
                    var output = '';
                    for (var i = 0; i < attachments.length; i++)
                    {
                        ext = attachments[i].original_file_name.split('.').pop();

                        switch (ext)
                        {
                            case 'jpeg':
                            case 'jpg':
                            case 'gif':
                            case 'png': ext = 'image'; break;

                            case 'doc':
                            case 'eml':
                            case 'html':
                            case 'pdf':
                            case 'txt':
                            case 'xls':
                            case 'rar':
                            case 'zip':
                                break;

                            default:
                                ext = 'file';
                                break;
                        }

                        output += '<div style="float:left; padding-right:6px; padding-bottom:5px;">';
                        output += '<img border="0" align="absmiddle" src="' + topBaseUrl + '/images/mime/' + ext + '.png"> ';
                        output += '<a href="' + topBaseUrl + '/mailer/index/download-attach?attach_id=' + attachments[i].id + '">' + attachments[i].original_file_name + '</a>';
                        output += ' (' + attachments[i].size + '); ';
                        output += '</div>';
                    }

                    return output;
                }
            }
        );

        return tpl;
    },

    sendRequestToGetMail: function(record, is_preview) {
        var _this=this;
        var grid=this.grid;
        var d = record.data;

        var items = Ext.menu.MenuMgr.get('reading-menu').items.items;
        var h = items[2];
        var is_pan_open = !h.checked;

        // don't send request, when preview pan is closed
        if (is_preview && !is_pan_open)
            return;

        if (!_this.fetching_in_progress) {
            _this.fetching_in_progress = true;

            Ext.Ajax.request({
                url: topBaseUrl + '/mailer/index/get-email-by/', // shit name for action, isn't it?
                params: {
                    mail_id: Ext.encode(d.mail_id),
                    account_id: Ext.encode(Ext.getCmp('mail-main-toolbar').getSelectedAccountId())
                },

                success: function (res) {
                    var result = Ext.decode(res.responseText);
                    if (result.success) {
                        var fetched_mail=result.mail;

                        // update record in store
                        var record_in_store = grid.store.getAt(grid.store.findExact('mail_id', d.mail_id));
                        if (record_in_store) {
                            record_in_store.set('is_downloaded', '1');
                            record_in_store.set('mail_body', fetched_mail.mail_body);
                            record_in_store.set('attachments', fetched_mail.attachments);
                            record_in_store.set('has_attachment', fetched_mail.has_attachment);
                            record_in_store.commit();

                            _this.updatePreview(record_in_store.data);

                            if (!is_preview) {
                                _this.updateIframe('frame-mail_' + d.mail_id, record_in_store);
                            }
                        }
                    } else {
                        Ext.simpleConfirmation.error(result.message);
                    }

                    _this.fetching_in_progress = false;
                },

                failure: function() {
                    Ext.simpleConfirmation.error('Internal server error. Please try again.');
                }
            });
        }
    },

    emailDblClick: function(record) {
        record = (record && record.data) ? record : this.gsm.getSelected();

        var d = record.data;

        if (d.mail_id_folder=='drafts')
            this.openEditDraftWindow(record);
        else
        {
            if (parseInt(d.is_downloaded, 10)===0)
            {
                record.data.mail_body='<IMG src="'+topBaseUrl+'/images/loading.gif" align="texttop"> Loading...';
                this.openTab(record);

                this.sendRequestToGetMail(record, false);
            }
            else
                this.openTab(record);
        }
    },

    openEditDraftWindow: function(record) {
        record = (record && record.data) ? record : this.gsm.getSelected();

        var d = record.data;

        show_email_dialog({
            emailFrom: d.mail_from,
            emailTo: d.mail_to.replace(/&gt;/g, '>').replace(/&lt;/g, '<'),
            emailCC: d.mail_cc.replace(/&gt;/g, '>').replace(/&lt;/g, '<'),
            emailBCC: d.mail_bcc.replace(/&gt;/g, '>').replace(/&lt;/g, '<'),
            emailSubject: d.mail_subject,
            emailMessage: d.mail_body,
            draftId: d.mail_id,
            attachments: d.attachments,
            booCreateFromMailTab: true,
            booDontPreselectTemplate: true
        });
    },

    updateIframe: function(frameId, record){
        var iframe = document.getElementById(frameId);
        if (!iframe) {
            return;
        }
        var d = record.data;

        var doc = iframe.contentDocument;
        if (empty(doc)) {
            doc = iframe.contentWindow.document;
        }
        doc.open();
        doc.write(this.getTemplate(true).apply(d));
        doc.close();
    },

    openTab: function(record) {
        record = (record && record.data) ? record : this.gsm.getSelected();

        var d = record.data;
        var id = !d.mail_id ? Ext.id() : 'mail_' + d.mail_id;

        var tab_panel = Ext.getCmp('mail-tabpanel');
        var tab = tab_panel.getItem(id);
        var subject = empty(d.mail_subject) ? '(no subject)' : d.mail_subject;

        if (!tab) {
            var frameId = 'frame-' + id;
            tab = new Ext.Panel({
                id: id,
                cls: 'mail-item-preview',
                title: Ext.util.Format.ellipsis(subject, 50),
                tabTip: subject,
                email_data: d,
                html: '<iframe id="' + frameId + '" name="' + frameId + '" style="border:none;" frameborder="0" width="100%" height="100%" scrolling="auto" src="javascript:false"></iframe>',
                closable: true,
                autoScroll: true,
                border: true
            });
            tab_panel.add(tab);
        }

        tab_panel.setActiveTab(tab);

        this.updateIframe(frameId, record);

        // Mark selected mail as read
        if (d.mail_unread) {
            this.grid.markAsReadUnread(
                record, false
            );
        }
    },

    openAll: function() {
        var tab_panel = Ext.getCmp('mail-tabpanel');
        tab_panel.beginUpdate();
        this.grid.store.data.each(this.openTab, this);
        tab_panel.endUpdate();
    }
});

Ext.reg('appMailGridWithPreview', MailGridWithPreview);