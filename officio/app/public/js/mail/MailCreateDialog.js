/* global topBaseUrl, is_superadmin, post_max_size, mail_settings */
/* jslint browser:true */
var MailSendSaveDialog = function (panelType, booProspect, parentMemberId, clientOrProspectId) {
    this.booProspect = booProspect;

    var send_save_dialog = this;

    var ds = new Ext.data.Store({
        id: 'mail-clients-list-store',
        proxy: new Ext.data.HttpProxy({
            url: booProspect ? topBaseUrl + '/prospects/index/get-all-prospects-list' : topBaseUrl + '/applicants/index/get-cases-list',
            method: 'post'
        }),

        baseParams: {
            panelType: panelType,
            parentMemberId: parentMemberId ? parentMemberId : 0
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'clientId'},
            {name: 'clientFullName'},
            {name: 'emailAddresses'}
        ]),
        listeners:
            {
                load: function (store) {
                    var booOnlySave = (Ext.getCmp('mail-create-to') === undefined || Ext.getCmp('mail-create-to').getRawValue() === '');

                    var dropdown_list_items = [];
                    var selected_mails_items = [];

                    clients_combo.store.each(function (item) {
                        if (item.data['emailAddresses'] !== '') {
                            var tmp = [item.data['clientId'], item.data['emailAddresses']];

                            dropdown_list_items.push(tmp);
                        }
                    });


                    if (!booOnlySave) {
                        if (Ext.getCmp('mail-create-to') !== undefined) {
                            selected_mails_items.push(Ext.getCmp('mail-create-to').getRawValue());
                            if (Ext.getCmp('mail-create-cc').getRawValue() !== '') selected_mails_items.push(Ext.getCmp('mail-create-cc').getRawValue());
                            if (Ext.getCmp('mail-create-bcc').getRawValue() !== '') selected_mails_items.push(Ext.getCmp('mail-create-bcc').getRawValue());
                        }
                    } else {
                        Ext.getCmp('mail-ext-emails-grid').getSelectionModel().each(function (item) {
                            selected_mails_items.push(item.data.mail_from);
                            selected_mails_items.push(item.data.mail_to);
                            if (item.data.mail_cc) selected_mails_items.push(item.data.mail_cc);
                            if (item.data.mail_bcc) selected_mails_items.push(item.data.mail_bcc);
                        });
                    }

                    var selected_mails_parsed = [];
                    var j;
                    for (var i = 0; i < selected_mails_items.length; i++) {
                        var selected_mail = selected_mails_items[i];
                        selected_mail = selected_mail.replace('&lt;', '<').replace('&gt;', '>');

                        var emailsArray = selected_mail.match(/([a-zA-Z0-9._\-]+@[a-zA-Z0-9._\-]+\.[a-zA-Z0-9._\-]+)/gi);
                        if (emailsArray) {
                            for (j = 0; j < emailsArray.length; j++) {
                                selected_mails_parsed.push(emailsArray[j]);
                            }
                        }
                    }

                    var clientOrProspectIdToSet = 0;
                    if (store.getCount() == 1) {
                        // If there is one option only - automatically select it in the combo
                        clientOrProspectIdToSet = store.getAt(0).data.clientId;
                    } else {
                        // Try to find the client/prospect by the id + email(s)
                        var firstFoundClientOrProspectId = null;
                        for (i = 0; i < selected_mails_parsed.length; i++) {
                            for (j = 0; j < dropdown_list_items.length; j++) {
                                if (selected_mails_parsed[i] == dropdown_list_items[j][1] && empty(firstFoundClientOrProspectId)) {
                                    firstFoundClientOrProspectId = dropdown_list_items[j][0];
                                }

                                // If id and email are the same
                                if (!empty(clientOrProspectId) && parseInt(clientOrProspectId, 10) === parseInt(dropdown_list_items[j][0], 10) && selected_mails_parsed[i] == dropdown_list_items[j][1]) {
                                    clientOrProspectIdToSet = clientOrProspectId;
                                    break;
                                }
                            }
                        }

                        if (empty(clientOrProspectIdToSet)) {
                            if (empty(firstFoundClientOrProspectId)) {
                                // client/prospect was NOT found by "email" or "id and email"
                                if (!empty(clientOrProspectId)) {
                                    // search if there is such client id in the combo
                                    for (j = 0; j < dropdown_list_items.length; j++) {
                                        if (parseInt(clientOrProspectId, 10) === parseInt(dropdown_list_items[j][0], 10)) {
                                            clientOrProspectIdToSet = clientOrProspectId;
                                            break;
                                        }
                                    }
                                }
                            } else {
                                // client/prospect was found by email
                                clientOrProspectIdToSet = firstFoundClientOrProspectId;
                            }
                        }
                    }

                    if (!empty(clientOrProspectIdToSet)) {
                        clients_combo.setValue(clientOrProspectIdToSet);
                    }
                }
            }
    });

    ds.load();

    var clients_combo = new Ext.form.ComboBox({
        id: 'mail-save-send-clients',
        fieldLabel: 'Save to',
        store: ds,
        mode: 'local',
        valueField: 'clientId',
        displayField: 'clientFullName',
        triggerAction: 'all',
        lazyRender: true,
        forceSelection: true,
        emptyText: this.booProspect ? 'Please select a Prospect' : 'Please select a Case',
        typeAhead: true,
        selectOnFocus: true,
        searchContains: true, // custom property
        queryDelay: 750,
        width: 400
    });

    var save_this_mail_checkbox = new Ext.form.Checkbox({
        hideLabel: true,
        boxLabel: this.booProspect ? "Save this email to Prospect's folder" : "Save this email to Case's folder",
        id: 'mail-save-this-mail',
        checked: true
    });

    var save_original_mail_checkbox = new Ext.form.Checkbox({
        hideLabel: true,
        boxLabel: this.booProspect ? "Save the original email to Prospect's folder" : "Save the original email to Case's folder",
        id: 'mail-save-original-mail'
    });

    var save_attach_separately = new Ext.form.Checkbox({
        hideLabel: true,
        boxLabel: 'Save attachment(s) separately',
        id: 'mail-save-attach-separately'
    });

    var remove_original_mail_checkbox = new Ext.form.Checkbox({
        hideLabel: true,
        boxLabel: this.booProspect ? 'Remove original email from Inbox after Saving to Prospect' : 'Remove original email from Inbox after Saving to Case',
        id: 'mail-remove-original-mail'
    });

    var sendSaveBtn = {
        xtype: 'button',
        text: _('Send'),
        cls: 'orange-btn',
        id: 'mail-send-save-submit-button',
        handler: function () {
            Ext.getCmp('mail-create-dialog').sendEmail();
        }
    };

    var saveBtn = {
        xtype: 'button',
        text: _('Save'),
        cls: 'orange-btn',
        id: 'mail-send-submit-button',
        handler: function () {
            send_save_dialog.saveEmail();
        }
    };

    var closeBtn = {
        xtype: 'button',
        text: 'Cancel',
        handler: function () {
            send_save_dialog.close();
        }
    };

    var pan = new Ext.FormPanel({
        ref: '../mailPanel',
        cls: 'st-panel',
        fileUpload: true,
        bodyStyle: 'padding:5px;',
        autoHeight: true,
        scope: this,
        labelAlign: 'top',
        items: [clients_combo, save_this_mail_checkbox, save_original_mail_checkbox, save_attach_separately, remove_original_mail_checkbox]
    });

    MailSendSaveDialog.superclass.constructor.call(this, {
        title: _('Send Mail Options'),
        closeAction: 'close',
        id: 'mail-send-save-dialog',
        stateful: false,
        modal: true,
        autoHeight: true,
        resizable: false,
        y: 10,
        autoWidth: true,
        layout: 'form',
        items: pan,
        buttons: [closeBtn, sendSaveBtn, saveBtn]
    });
};

Ext.extend(MailSendSaveDialog, Ext.Window, {
    showDialog: function (booOnlySave, booNewEmail) {
        if (booOnlySave === undefined)
            booOnlySave = false;

        if (booOnlySave) {
            Ext.getCmp('mail-save-attach-separately').show();
            Ext.getCmp('mail-save-this-mail').hide();
            Ext.getCmp('mail-save-original-mail').hide();
            Ext.getCmp('mail-remove-original-mail').setValue(true);

            Ext.getCmp('mail-send-save-submit-button').hide();
            Ext.getCmp('mail-send-submit-button').show();

            var selected_mails = Ext.getCmp('mail-main-toolbar').getCurrentSelectedEmail(true);

            var booMailsHaveAttach = false;
            for (var i = 0; i < selected_mails.length; i++) {
                if (selected_mails[i]['data']['has_attachment']) {
                    booMailsHaveAttach = true;
                    break;
                }
            }

            Ext.getCmp('mail-save-attach-separately').setDisabled(!booMailsHaveAttach);

            sendEmailDialog.setTitle(sendEmailDialog.booProspect ? 'Save to Prospect' : 'Save to Case');
        } else {
            Ext.getCmp('mail-save-attach-separately').hide();
            Ext.getCmp('mail-save-this-mail').show();
            Ext.getCmp('mail-save-original-mail').show();

            Ext.getCmp('mail-send-save-submit-button').show();
            Ext.getCmp('mail-send-submit-button').hide();

            if (booNewEmail === undefined)
                booNewEmail = false;

            if (booNewEmail) {
                Ext.getCmp('mail-save-original-mail').disable();
                Ext.getCmp('mail-remove-original-mail').disable();
            } else {
                Ext.getCmp('mail-save-original-mail').enable();
                Ext.getCmp('mail-save-original-mail').setValue(true);
                Ext.getCmp('mail-remove-original-mail').enable();
                Ext.getCmp('mail-remove-original-mail').setValue(true);
            }

            sendEmailDialog.setTitle('Send Mail Options');
        }

        this.show();
    },

    createDialog: function (booOnlySave, booNewEmail) {
        this.showDialog(booOnlySave, booNewEmail);
    },

    saveEmail: function () {
        var win = sendEmailDialog;

        var clientField = Ext.getCmp('mail-save-send-clients');
        var selClientId = clientField.getValue();

        if (empty(selClientId) || getComboBoxIndex(clientField) == -1) {
            clientField.markInvalid('Please select a case.');
            return false;
        }

        var save_mail_dialog = this;

        var mailToolBar = Ext.getCmp('mail-main-toolbar');
        var selRecords = mailToolBar.getCurrentSelectedEmail(true);


        var selRecordsIds = [];
        for (var i = 0; i < selRecords.length; i++) {
            var row = selRecords[i];
            selRecordsIds.push(row.data.mail_id);
        }

        var remove_original_mail = Ext.getCmp('mail-remove-original-mail').getValue();

        win.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/save',
            params: {
                email_ids: Ext.encode(selRecordsIds),
                account_id: Ext.encode(mailToolBar.getSelectedAccountId()),
                save_to_client: Ext.encode(selClientId),
                save_to_type: this.booProspect ? 'prospect' : 'client',
                remove_original_mail: Ext.encode(remove_original_mail),
                save_attach_separately: Ext.encode(Ext.getCmp('mail-save-attach-separately').getValue())
            },
            success: function (res) {
                var result = Ext.decode(res.responseText);
                if (result.success) {
                    save_mail_dialog.close();

                    Ext.simpleConfirmation.success(_('Mail was successfully saved'));

                    var grid = Ext.getCmp('mail-ext-emails-grid');

                    if (grid !== undefined && remove_original_mail)
                        grid.store.reload();
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                    win.getEl().unmask();
                }
            },
            failure: function () {
                Ext.simpleConfirmation.error(_('An error occurred when saving mail'));

                win.getEl().unmask();
            }
        });
    }
});

var MailCreateDialog = function (options) {
    var thisDialog = this;

    this.thisDialogOptions = options;
    this.labelsWidth = 90;
    this.attachmentsLinkWidth = 90;
    this.width = 1000;
    this.minWidth = 1000;
    this.fieldsWidth = thisDialog.width - thisDialog.attachmentsLinkWidth - 15 - thisDialog.labelsWidth;

    var mailPanelType = new Ext.form.Hidden({
        id: 'mail-create-panel-type',
        name: 'panel-type',
        value: empty(options) || empty(options.panelType) ? '' : options.panelType
    });

    var replied = new Ext.form.Hidden({
        id: 'mail-create-replied',
        name: 'replied',
        value: 0
    });

    var forwarded = new Ext.form.Hidden({
        id: 'mail-create-forwarded',
        name: 'forwarded',
        value: 0
    });

    var original_mail_id = new Ext.form.Hidden({
        id: 'mail-create-original-mail-id',
        name: 'original_mail_id',
        value: 0
    });

    var member_id_for_activity = new Ext.form.Hidden({
        id: 'mail-member-id-for-activity',
        name: 'member_id_for_activity',
        value: (!empty(options) && !empty(options.panelType) && options.panelType == 'marketplace' && !empty(options.member_id)) ? options.member_id : ''
    });

    var ts = new Ext.data.Store({
        data: [],
        reader: new Ext.data.JsonReader(
            {id: 0},
            Ext.data.Record.create([
                {name: 'templateId'},
                {name: 'templateName'}
            ])
        )
    });

    var help = String.format(
        "<i class='las la-question-circle' ext:qtip='{0}' ext:qwidth='450' style='cursor: help; margin-left: 10px; vertical-align: text-bottom'></i>",
        !empty(options) && !empty(options.panelType) && ['prospects', 'marketplace'].has(options.panelType) ? _('Templates can be managed (i.e., added/edited/deleted) through the Prospect Templates module.') : _('Templates can be managed (i.e., added/edited/deleted) through the Client Templates module.')
    );

    var templates = new Ext.form.ComboBox({
        id: 'mail-create-template',
        fieldLabel: _('Template:') + help,
        labelSeparator: '',
        store: ts,
        mode: 'local',
        valueField: 'templateId',
        displayField: 'templateName',
        triggerAction: 'all',
        lazyRender: true,
        forceSelection: true,
        emptyText: _('Select a template...'),
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: thisDialog.fieldsWidth
    });

    templates.on('select', function () {
        var mailFieldValue = Ext.getCmp('mail-create-message').getValue();
        mailFieldValue = mailFieldValue.replace('&lt;', '<').replace('&gt;', '>');
        if (mailFieldValue != options.emailMessage) {
            Ext.Msg.confirm(_('Please confirm'), _('You have chosen to open a new template. The information from this template will overwrite your work. Are you sure you want to proceed?'), function (btn) {
                if (btn == 'yes') {
                    thisDialog.fillFieldsAfterTemplateParsing(options);
                }
            });
        } else {
            thisDialog.fillFieldsAfterTemplateParsing(options);
        }
    });

    this.editor = new Ext.ux.form.FroalaEditor({
        id: 'mail-create-message',
        region: 'center',
        height: 250,
        heightDifference: 65,
        widthDifference: 10,
        hideLabel: true,
        allowBlank: false,
        value: '',
        booAllowImagesUploading: true
    });

    var booHiddenSendAndSaveToCase = typeof options !== 'undefined' && !empty(options.save_to_prospect) || typeof is_superadmin !== 'undefined' && is_superadmin;
    var booHiddenSendAndSaveToProspect = (typeof options !== 'undefined' && ((!empty(options.member_id) && !options.booProspect) || options.booHideSendAndSaveProspect)) || typeof is_superadmin !== 'undefined' && is_superadmin;
    var send_mail_toolbar = new Ext.Toolbar({
        style: 'margin-top: 5px;',
        items: [
            {
                text: '<i class="lar la-paper-plane"></i>' + _('Send'),
                hidden: (!booHiddenSendAndSaveToCase || !booHiddenSendAndSaveToProspect) && mail_settings['hide_send_button'],
                handler: function () {
                    thisDialog.sendEmail();
                }
            }, {
                id: 'mail-tb-btn-send-and-save',
                text: '<i class="lar la-save"></i>' + _('Send & Save to a Case'),
                hidden: booHiddenSendAndSaveToCase,

                width: 150,
                handler: function () {
                    thisDialog.sendSaveEmail(false, options.booNewEmail);
                }
            }, {
                text: '<i class="lar la-save"></i>' + _('Send & Save to a Prospect'),
                hidden: booHiddenSendAndSaveToProspect,
                width: 150,
                handler: function () {
                    Ext.getCmp('mail-create-save-to-prospect').setValue(1);
                    thisDialog.sendSaveEmail(false, options.booNewEmail, true);
                }
            }, '->', {
                xtype: 'calendlybutton',
                text: '<i class="las la-calendar"></i>' + _('Insert Calendly Link'),
                cls: 'secondary-toolbar-btn',
                hidden: !(typeof calendly_enabled !== 'undefined' && calendly_enabled),
                editor: this.editor
            }, {
                id: 'mail-tb-btn-save-draft',
                text: '<i class="las la-envelope-open-text"></i>' + _('Save draft'),
                hidden: empty(mail_settings.accounts.length) || !mail_settings['show_email_tab'] || (typeof options !== 'undefined' && !empty(options.hideDraftButton)),
                handler: function () {
                    thisDialog.saveDraft();
                }
            }
        ]
    });

    var from = new Ext.form.ComboBox({
        id: 'mail-create-from',
        fieldLabel: _('From'),
        name: 'from',
        store: new Ext.data.Store({
            data: mail_settings.accounts,
            reader: new Ext.data.JsonReader(
                {id: 0},
                Ext.data.Record.create([
                    {name: 'account_id'},
                    {name: 'account_name'}
                ])
            )
        }),
        mode: 'local',
        hiddenName: 'from',
        valueField: 'account_id',
        displayField: 'account_name',
        triggerAction: 'all',
        lazyRender: true,
        forceSelection: true,
        readOnly: true,
        typeAhead: true,
        selectOnFocus: true,
        editable: false,
        width: thisDialog.fieldsWidth
    });

    var email, cc, bcc;
    // If there is no access to the Mail module - use simple text fields, instead of comboboxes
    if (allowedPages.has('email')) {
        var ds = new Ext.data.Store({
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/mailer/index/get-to-mails/',
                method: 'post'
            }),

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'to'},
                {name: 'name'},
                {name: 'type'}
            ]),
            listeners:
                {
                    beforeload: function () {
                        var toolbar = Ext.getCmp('mail-main-toolbar');
                        var accountId = toolbar ? toolbar.getSelectedInToAccountId() : 0;

                        this.proxy.setUrl(topBaseUrl + '/mailer/index/get-to-mails?account_id=' + accountId);
                    }
                }
        });

        var resultTpl = new Ext.XTemplate(
            '<tpl for="."><div class="x-combo-list-item" style="padding: 2px;">',
            '<IMG border="0" src="' + topBaseUrl + '/images/mail/16x16/to-type-{type}.png" title="{type}" align="absmiddle">&nbsp;&nbsp;',
            '{[this.formatName(values)]}',
            '</div></tpl>', {
                formatName: function (record_data) {
                    var name = record_data.name ? record_data.name : '';
                    var to = record_data.to;

                    var query = ds.reader.jsonData.query;

                    return (name ? '"' + this.highlight(name, query) + '" ' : '') + (name ? '&lt;' + this.highlight(to, query) + '&gt;' : this.highlight(to, query));
                },
                highlight: function (str, query) {
                    highlightedRow = str.replace(
                        new RegExp('(' + preg_quote(query) + ')', 'gi'),
                        "<b style='background-color: #FFFF99;'>$1</b>"
                    );

                    return highlightedRow;
                }
            }
        );

        email = new Ext.form.ComboBox({
            id: 'mail-create-to',
            name: 'email',
            fieldLabel: _('To'),
            store: ds,
            displayField: 'to',
            typeAhead: false,
            emptyText: '',
            loadingText: _('Searching...'),
            width: thisDialog.fieldsWidth,
            listClass: 'no-pointer',
            pageSize: 10,
            minChars: 2,
            queryDelay: 750,
            hideTrigger: true,
            tpl: resultTpl,
            allowBlank: false,
            itemSelector: 'div.x-combo-list-item',
            cls: 'with-right-border',
            onSelect: function (record) {
                thisDialog.selectDeliveries(record, this);
            }
        });

        cc = new Ext.form.ComboBox({
            id: 'mail-create-cc',
            name: 'cc',
            fieldLabel: _('CC'),
            store: ds,
            displayField: 'to',
            typeAhead: false,
            emptyText: '',
            loadingText: _('Searching...'),
            width: thisDialog.fieldsWidth,
            listClass: 'no-pointer',
            pageSize: 10,
            minChars: 2,
            queryDelay: 750,
            hideTrigger: true,
            tpl: resultTpl,
            itemSelector: 'div.x-combo-list-item',
            cls: 'with-right-border',
            onSelect: function (record) {
                thisDialog.selectDeliveries(record, this);
            }
        });

        bcc = new Ext.form.ComboBox({
            id: 'mail-create-bcc',
            name: 'bcc',
            fieldLabel: _('BCC'),
            store: ds,
            displayField: 'to',
            typeAhead: false,
            emptyText: '',
            loadingText: _('Searching...'),
            width: thisDialog.fieldsWidth,
            listClass: 'no-pointer',
            pageSize: 10,
            minChars: 2,
            queryDelay: 750,
            hideTrigger: true,
            tpl: resultTpl,
            itemSelector: 'div.x-combo-list-item',
            cls: 'with-right-border',
            onSelect: function (record) {
                thisDialog.selectDeliveries(record, this);
            }
        });
    } else {
        email = new Ext.form.TextField({
            id: 'mail-create-to',
            name: 'email',
            fieldLabel: _('To'),
            width: thisDialog.fieldsWidth
        });

        cc = new Ext.form.TextField({
            id: 'mail-create-cc',
            name: 'cc',
            fieldLabel: _('CC'),
            width: thisDialog.fieldsWidth
        });

        bcc = new Ext.form.TextField({
            id: 'mail-create-bcc',
            name: 'bcc',
            fieldLabel: _('BCC'),
            width: thisDialog.fieldsWidth
        });
    }

    var subject = new Ext.form.TextField({
        id: 'mail-create-subject',
        name: 'subject',
        fieldLabel: 'Subject',
        width: thisDialog.fieldsWidth
    });

    var draft_id = new Ext.form.Hidden({
        id: 'mail-create-draft-id',
        name: 'draft_id',
        value: 0
    });

    var create_from_mail_tab = new Ext.form.Hidden({
        id: 'mail-create-from-mail-tab',
        name: 'create_from_mail_tab',
        value: 0
    });

    var save_to_prospect = new Ext.form.Hidden({
        id: 'mail-create-save-to-prospect',
        name: 'create-save-to-prospect',
        value: 0
    });

    this.email_fs = new Ext.Container({
        layout: 'form',
        items: [
            templates,
            from,
            {
                layout: 'table',
                xtype: 'panel',
                hidden: typeof options !== 'undefined' && options.hideTo,
                layoutConfig: {
                    columns: 2
                },
                items: [
                    {
                        layout: 'form',
                        xtype: 'panel',
                        items: [
                            email
                        ]
                    }, {
                        xtype: 'container',
                        width: thisDialog.attachmentsLinkWidth,
                        style: 'text-align: center; margin-bottom: 12px',
                        hidden: typeof options !== 'undefined' && (options.hideCc || options.hideBcc),
                        items: [
                            {
                                xtype: 'box',
                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    'class': 'blulinkunb',
                                    style: 'margin-right: 10px',
                                    title: '',
                                    html: _('CC')
                                },
                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            thisDialog.toggleSection('cc', true);
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }, {
                                xtype: 'box',
                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    'class': 'blulinkunb',
                                    title: '',
                                    html: _('BCC')
                                },
                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            thisDialog.toggleSection('bcc', true);
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }
                        ]
                    }
                ]
            }, {
                layout: 'form',
                items: {
                    xtype: 'displayfield',
                    labelStyle: 'width: ' + thisDialog.labelsWidth + 'px',
                    style: 'padding-top: 9px',
                    fieldLabel: 'To',
                    value: typeof options !== 'undefined' && !empty(options.toName) ? options.toName : ''
                },
                hidden: typeof options === 'undefined' || empty(options.toName)
            },
            cc,
            bcc,
            {
                layout: 'table',
                xtype: 'panel',
                layoutConfig: {
                    columns: 2
                },
                items: [
                    {
                        layout: 'form',
                        xtype: 'panel',
                        items: [
                            subject
                        ]
                    }, {
                        xtype: 'container',
                        width: thisDialog.attachmentsLinkWidth,
                        style: 'text-align: right;',
                        items: [
                            {
                                xtype: 'box',
                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    'class': 'blulinkunb',
                                    style: 'margin-bottom: 12px; display: block;',
                                    title: '',
                                    html: _('Attachments')
                                },
                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            thisDialog.toggleSection('attachments', true);
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }
                        ]
                    }
                ]
            }
        ]
    });

    this.attachments_fs = new Ext.form.FieldSet({
        hidden: true,
        hideLabel: true,
        collapsible: false,
        scope: this,
        layout: 'form',
        style: 'max-height: 135px;',
        cls: 'no-borders-fieldset',
        items: [
            {
                html: '<div id="fine-uploader"></div>',
                xtype: 'panel',
                style: 'max-height: 105px; overflow: auto; width: 100%;',
                listeners: {
                    afterrender: function () {
                        $(document)
                            .off('click', '#attach-from-documents-button')
                            .on('click', '#attach-from-documents-button', function () {
                                var dialog = new MailAttachFromDocumentsDialog({
                                    booProspect: options.booProspect,
                                    parentMemberId: options.parentMemberId,
                                    member_id: options.member_id
                                }, thisDialog);

                                dialog.showDialog();
                            });

                        thisDialog.uploaded_attachments = [];
                        thisDialog.upload_finished = true;

                        var uploader = new qq.FineUploader({
                            debug: false,
                            element: document.getElementById('fine-uploader'),
                            template: 'qq-template',
                            request: {
                                endpoint: topBaseUrl + '/documents/index/upload-file'
                            },
                            cors: {
                                expected: true
                            },
                            resume: {
                                enabled: true
                            },
                            callbacks: {
                                onComplete: function (id, name, response) {
                                    if (response.success) {
                                        thisDialog.uploaded_attachments.push({
                                            id: id,
                                            tmp_name: response['tmpPath'],
                                            name: response['name'],
                                            size: response['size'],
                                            extention: response['extension']
                                        });

                                        // make filename a link
                                        var oParams = {
                                            type: 'uploaded',
                                            attach_id: response['tmpName'],
                                            name: response['name']
                                        };
                                        var src = topBaseUrl + '/mailer/index/download-attach?' + $.param(oParams);

                                        $("li[qq-file-id='" + id + "']").find("span.qq-upload-file-selector.qq-upload-file").html('<a class="bluelink" href="' + src + '">' + response.name + '</a>');
                                    } else {
                                        Ext.simpleConfirmation.error(qq.format("Error on file {}.  Reason: {}", name, response.msg));
                                    }

                                },
                                onAllComplete: function () {
                                    thisDialog.upload_finished = true;
                                    thisDialog.resyncWindowSize();
                                },
                                onError: function (id, name, errorReason, xhrOrXdr) {
                                    Ext.simpleConfirmation.error(qq.format("Error on file {}.  Reason: {}", name, errorReason));
                                },
                                onValidate: function (data) {
                                    if (data.size > parseInt(post_max_size)) {
                                        Ext.simpleConfirmation.error(qq.format("Error on file {}.  Reason: {}", data.name, 'Max allowed file size is ' + (post_max_size / 1024 / 1024) + 'Mb'));
                                        return false;
                                    }
                                }
                            }
                        });
                    }
                }
            }
        ]
    });

    MailCreateDialog.superclass.constructor.call(this, {
        title: '<i class="lar la-paper-plane"></i>' + _('Send Email'),
        id: 'mail-create-dialog',
        stateful: false,
        closeAction: 'close',
        resizable: true,
        minHeight: 700,
        minimizable: true,
        maximizable: true,
        animCollapse: false,
        style: "max-height: 100vh",
        y: 10,
        tbar: send_mail_toolbar,

        // Is used to identify if the dialog was opened/showed or just initialized
        isMailDialogOpened: false,

        // Save the position/dimensions before switching to/from the full screen
        isMinimized: false,
        oldPosition: null,

        // attachment-related vars
        upload_finished: true,
        previously_att_global: [],
        uploaded_attachments: [],

        items: [{
            xtype: 'form',
            fileUpload: true,
            autoHeight: true,
            labelWidth: this.labelsWidth,
            items: [
                // hidden fields
                original_mail_id,
                replied,
                mailPanelType,
                forwarded,
                draft_id,
                create_from_mail_tab,
                save_to_prospect,

                // blocks of fields
                this.email_fs,
                this.attachments_fs,
                this.editor
            ]
        }],

        tools: [
            {
                id: 'fullscreen',
                qtip: _('Toggle the full screen'),
                handler: function () {
                    if (thisDialog.isMinimized) {
                        // Dialog is minimized - restore and after that switch to the full screen
                        thisDialog.on('maximize', thisDialog.toggleFullScreen.createDelegate(thisDialog), thisDialog, {single: true});
                        thisDialog.maximize();
                    } else {
                        thisDialog.toggleFullScreen();
                    }
                }
            }, {
                id: 'minimize',
                qtip: _('Click to minimize the dialog'),
                handler: function () {
                    thisDialog.minimize();
                }
            }, {
                id: 'maximize',
                qtip: _('Click to maximize the dialog'),
                hidden: true,
                handler: function () {
                    thisDialog.maximize();
                }
            }, {
                id: 'close',
                qtip: _('Click to close the dialog'),
                handler: function () {
                    thisDialog.close();
                }
            }
        ],

        listeners: {
            render: function () {
                thisDialog.isMailDialogOpened = true;
            },

            show: function () {
                thisDialog.syncShadow();
            },

            destroy: function () {
                if (thisDialog.next_member)
                    show_email_dialog(thisDialog.next_member);
            },

            close: function () {
                if (!thisDialog.next_member) {
                    if (Ext.getCmp('mail-ext-emails-grid') !== undefined && Ext.getCmp('mail-create-draft-id').getValue() > 0) {
                        Ext.getCmp('mail-ext-emails-grid').store.reload();
                    }
                }

                this.isMailDialogOpened = false;
            },

            minimize: function () {
                this.isMinimized = true;

                this.collapse();
                this.setPagePosition(0, Ext.getBody().getViewSize().height - 40);
                this.tools.minimize.hide();
                this.tools.maximize.show();
            },

            maximize: function () {
                this.isMinimized = false;

                this.restore();
                this.center();
                this.tools.maximize.hide();
                this.tools.minimize.show();
            },

            resize: function (wnd, width, height) {
                if (typeof width !== 'undefined' && typeof height !== 'undefined' && width !== 'auto' && height !== 'auto') {
                    thisDialog.attachments_fs.setWidth(width - 22);
                    thisDialog.editor.setWidth(width - 22);

                    var newFieldsWidth = width - thisDialog.labelsWidth - thisDialog.attachmentsLinkWidth - 15;

                    templates.getResizeEl().setWidth(newFieldsWidth);
                    templates.setWidth(newFieldsWidth);
                    from.getResizeEl().setWidth(newFieldsWidth);
                    from.setWidth(newFieldsWidth);
                    email.setWidth(newFieldsWidth);
                    subject.setWidth(newFieldsWidth);
                    cc.setWidth(newFieldsWidth);
                    bcc.setWidth(newFieldsWidth);

                    // Calculate the height of the Editor field to be set
                    height = Math.max(thisDialog.minHeight, height);
                    var editorHeight = height - thisDialog.tbar.getHeight() - thisDialog.email_fs.getHeight() - 60;
                    if (thisDialog.attachments_fs.isVisible()) {
                        editorHeight -= thisDialog.attachments_fs.getHeight() + 10; // bottom padding
                    }
                    editorHeight = Math.max(editorHeight, 150);
                    thisDialog.editor.setHeight(editorHeight);
                }
            }
        }
    });
};

Ext.extend(MailCreateDialog, Ext.Window, {
    formatMailLabel: function (label) {
        label = label.replace(/&lt;/g, '<');
        label = label.replace(/&gt;/g, '>');

        return label;
    },

    showDialog: function (options) {
        this.show();
        if (options !== undefined)
            this.next_member = options.next_member;
    },

    toggleFullScreen: function () {
        var thisDialog = this;
        var currentPosition = thisDialog.getPosition();
        var currentSize = thisDialog.getSize();

        var booExpand = true;
        if (currentPosition[0] === 0 && currentPosition[1] === 0 && currentSize.width === Ext.getBody().getViewSize().width && currentSize.height === Ext.getBody().getViewSize().height) {
            // Don't expand if already expanded
            booExpand = false;
        }

        if (booExpand) {
            // Save the previous position
            thisDialog.oldPosition = {
                x: currentPosition[0],
                y: currentPosition[1],
                w: currentSize.width,
                h: currentSize.height
            };

            // Switch to the full screen
            var newWidth = Ext.getBody().getViewSize().width;
            var newHeight = Ext.getBody().getViewSize().height;

            thisDialog.setPosition(0, 0);
            thisDialog.setSize(newWidth, newHeight);
        } else if (!empty(thisDialog.oldPosition)) {
            // Restore the previous position (before switching to the full screen)
            thisDialog.setSize(thisDialog.oldPosition.w, thisDialog.oldPosition.h);
            thisDialog.setPosition(thisDialog.oldPosition.x, thisDialog.oldPosition.y);

            thisDialog.oldPosition = null;
        }
    },

    toggleSection: function (section, booResize, booShow) {
        var thisDialog = this;
        switch (section) {
            case 'attachments':
                if (booShow === true || booShow === false) {
                    // Use the provided value
                } else {
                    booShow = !thisDialog.attachments_fs.isVisible();
                }
                thisDialog.attachments_fs.setVisible(booShow);
                break;

            case 'cc':
                var ofField = Ext.getCmp('mail-create-cc');
                if (booShow === true || booShow === false) {
                    // Use the provided value
                } else {
                    booShow = !ofField.isVisible();
                }

                ofField.setVisible(booShow);
                break;

            case 'bcc':
                var ofField = Ext.getCmp('mail-create-bcc');
                if (booShow === true || booShow === false) {
                    // Use the provided value
                } else {
                    booShow = !ofField.isVisible();
                }

                ofField.setVisible(booShow);
                break;

            default:
                break;
        }

        if (booResize) {
            thisDialog.resyncWindowSize();
        }
    },

    resyncWindowSize: function () {
        var thisDialog = this;

        setTimeout(function () {
            thisDialog.syncShadow();
            var currentSize = thisDialog.getSize();
            thisDialog.fireEvent('resize', thisDialog, currentSize.width, currentSize.height);
        }, 1);
    },

    getCurrentAccountSignature: function () {
        var signature = '';
        if (mail_settings.accounts.length > 0) {
            var toobar = Ext.getCmp('mail-main-toolbar');
            if (toobar) {
                var currentId = toobar.getSelectedAccountId();
                var currentAccount;
                for (var i = 0; i < mail_settings.accounts.length; i++) {
                    if (currentId === mail_settings.accounts[i].account_id) {
                        currentAccount = mail_settings.accounts[i];
                    }
                }

                if (currentAccount.signature && currentAccount.signature != '<br>') {
                    signature = '<br /><br />' + currentAccount.signature;
                }
            } else {
                var accounts = mail_settings.accounts;
                for (var j = 0; j < accounts.length; j++) {
                    if (accounts[j].is_default && accounts[j].signature && accounts[j].signature != '<br>') {
                        signature = '<br /><br />' + accounts[j].signature;
                        break;
                    }
                }
            }
        }

        return signature;
    },

    removeOwnEmail: function (strInputEmails) {
        var strResult = '';
        if (empty(strInputEmails)) {
            return strResult;
        }

        strInputEmails = strInputEmails.replace(/&lt;/g, '<').replace(/&gt;/g, '>');
        var arrEmails = strInputEmails.replace(/;/g, ',').split(',');

        if (arrEmails.length) {
            var toolbar = Ext.getCmp('mail-main-toolbar');
            var defaultMail = '';
            if (toolbar) {
                defaultMail = toolbar.getSelectedAccountMail();
            } else {
                var accounts = mail_settings.accounts;
                for (var j = 0; j < accounts.length; j++) {
                    if (accounts[j].is_default) {
                        defaultMail = accounts[j].account_name;
                        break;
                    }
                }
            }

            if (!empty(defaultMail)) {
                // Find if current email is used - remove it
                var arrCorrectEmails = [];
                for (var i = 0; i < arrEmails.length; i++) {
                    if (empty(stristr(arrEmails[i], defaultMail))) {
                        arrCorrectEmails[arrCorrectEmails.length] = this.formatMailLabel(arrEmails[i]);
                    }
                }

                // Unite all pieces again
                if (arrCorrectEmails.length) {
                    strResult = implode(', ', arrCorrectEmails);
                }
            }
        }

        return strResult;
    },

    createReplyDialog: function (reply_all, d) {
        if (empty(d)) {
            // Get selected mail and all related info
            var selRecord = Ext.getCmp('mail-main-toolbar').getCurrentSelectedEmail();
            if (!selRecord) {
                Ext.simpleConfirmation.warning('Please select email to reply');
            }

            // Show dialog
            d = selRecord.data;
        }

        var sendTo = d.mail_from;
        var sendCC, sendBCC;
        if (reply_all) {
            // Use to, cc and bcc fields
            var strReplyAllTo = this.removeOwnEmail(d.mail_to);
            if (!empty(strReplyAllTo)) {
                sendTo += ', ' + strReplyAllTo;
            }

            var strReplyAllCC = this.removeOwnEmail(d.mail_cc);
            if (!empty(strReplyAllCC)) {
                sendCC = strReplyAllCC;
            }

            var strReplyAllBCC = this.removeOwnEmail(d.mail_bcc);
            if (!empty(strReplyAllBCC)) {
                sendBCC = strReplyAllBCC;
            }
        }

        var signature = this.getCurrentAccountSignature();

        var emailMessage = Ext.util.Format.stripScripts(d.mail_body);
        emailMessage = emailMessage.replace(/html \{ visibility:hidden; \}/ig, '');
        emailMessage = emailMessage.replace(/<noscript[^>]*?>[\s\S]*?<\/noscript>/ig, '');
        emailMessage = emailMessage.replace(/onLoad="(.*)"/ig, '');

        emailMessage = ((!empty(signature)) ? signature : '') + '<br /><br />' + d.mail_date + ' ' + d.mail_from + ' ' + '<br><blockquote style="border-left:1px solid #CCCCCC; padding-left:10px;">' + emailMessage + '</blockquote><br />';

        show_email_dialog({
            booNewEmail: false,
            emailTo: sendTo ? this.formatMailLabel(sendTo) : '',
            emailCC: sendCC ? this.formatMailLabel(sendCC) : '',
            emailBCC: sendBCC ? this.formatMailLabel(sendBCC) : '',
            emailSubject: 'Re: ' + d.mail_subject,
            emailMessage: '<!--reply_or_forward_text-->' + emailMessage,
            booShowTemplates: true,
            booCreateFromMailTab: true,
            booDontPreselectTemplate: true
        });

        // set reply flag to 1
        Ext.getCmp('mail-create-replied').setValue(1);

        // set replied mail id
        Ext.getCmp('mail-create-original-mail-id').setValue(d.mail_id);
    },

    createForwardDialog: function (d) {
        if (empty(d)) {
            // Get selected mail and all related info
            var selRecord = Ext.getCmp('mail-main-toolbar').getCurrentSelectedEmail();
            if (!selRecord) {
                Ext.simpleConfirmation.warning('Please select email to forward');
            }

            // Show dialog
            d = selRecord.data;
        }

        var signature = this.getCurrentAccountSignature();

        var emailMessage = ((!empty(signature) && signature != '<br>') ? signature : '') + '<br /><br />---------- Forwarded message ----------<br />' + d.mail_date + ' ' + d.mail_from + '<br /><br />' + d.mail_body;

        show_email_dialog({
            booNewEmail: false,
            emailSubject: 'Fwd: ' + d.mail_subject,
            emailMessage: '<!--reply_or_forward_text-->' + emailMessage,
            attachments: d.attachments,
            booShowTemplates: true,
            booCreateFromMailTab: true,
            booDontPreselectTemplate: true
        });

        // set forward flag to 1
        Ext.getCmp('mail-create-forwarded').setValue(1);

        // set replied mail id
        Ext.getCmp('mail-create-original-mail-id').setValue(d.mail_id);
    },

    isEmailInUserAccounts: function (email) {
        for (var i = 0; i < mail_settings.accounts.length; i++) {
            if (mail_settings.accounts[i]['account_name'] == email) {
                return mail_settings.accounts[i]['account_id'];
            }
        }

        return false;
    },

    // Setup or reset each field's value
    setMailFormValues: function (options) {
        var thisDialog = this;
        options = options || {};

        // Get mail account id
        var emailFrom = options.emailFrom;
        var from_mail_account_id = this.isEmailInUserAccounts(options.emailFrom);

        // Not found account?
        if (empty(from_mail_account_id)) {
            from_mail_account_id = 0;
            var toolBar = Ext.getCmp('mail-main-toolbar');
            if (!empty(toolBar)) {
                // Get currently selected account
                from_mail_account_id = toolBar.getSelectedAccountId();
            } else if (mail_settings.accounts.length > 0) {
                from_mail_account_id = mail_settings.accounts[0]['account_id'];
            }
        }

        options.emailFrom = from_mail_account_id;
        if (empty(from_mail_account_id)) {
            Ext.getCmp('mail-create-from').hide();
        } else {
            var fromCombo = Ext.getCmp('mail-create-from');
            if (fromCombo) {
                // Check if value is integer - means account id
                if (empty(emailFrom) || /^(\d)*$/i.test(emailFrom)) {
                    fromCombo.setValue(options.emailFrom);
                    fromCombo.setDisabled(false);
                } else {
                    // Disable From field + show email address from template
                    fromCombo.setRawValue(emailFrom);
                    fromCombo.setDisabled(true);
                }
            }
        }

        if (!empty(options.draftId))
            Ext.getCmp('mail-create-draft-id').setValue(options.draftId);

        if (!empty(options.emailTo))
            Ext.getCmp('mail-create-to').setValue(options.emailTo ? options.emailTo.replace('%20', ' ') : '');

        if (!empty(options.emailCC)) {
            var ccVal = options.emailCC ? options.emailCC : '';
            thisDialog.toggleSection('cc', false, !empty(ccVal));
            Ext.getCmp('mail-create-cc').setValue(ccVal);
        } else {
            thisDialog.toggleSection('cc', false, false);
        }

        if (!empty(options.emailBCC)) {
            var bccVal = options.emailBCC ? options.emailBCC : '';
            thisDialog.toggleSection('bcc', false, !empty(bccVal));
            Ext.getCmp('mail-create-bcc').setValue(bccVal);
        } else {
            thisDialog.toggleSection('bcc', false, false);
        }

        if (!empty(options.emailSubject))
            Ext.getCmp('mail-create-subject').setValue(options.emailSubject ? options.emailSubject : '');

        if (!empty(options.emailMessage)) {
            Ext.getCmp('mail-create-message').setValue(options.emailMessage ? options.emailMessage : '');
        }

        if (!empty(options.save_to_prospect)) {
            Ext.getCmp('mail-create-save-to-prospect').setValue(options.save_to_prospect ? 1 : 0);
        }

        if (!empty(options.booCreateFromMailTab)) {
            Ext.getCmp('mail-create-from-mail-tab').setValue(options.booCreateFromMailTab ? 1 : 0);
        }

        if (!empty(options.booShowTemplates) && options.booShowTemplates) {
            Ext.getCmp('mail-create-template').show();

            if (options.template_id) {
                if (options.booFirstTime) {
                    Ext.getCmp('mail-create-template').reset();
                    Ext.getCmp('mail-create-template').store.removeAll();
                    Ext.getCmp('mail-create-template').store.loadData(options.templates);
                }

                if (!options.booDontPreselectTemplate)
                    Ext.getCmp('mail-create-template').setValue(options.template_id);
            }
        } else {
            Ext.getCmp('mail-create-template').hide();
        }

        // Auto attached files

        var arrInsertAttachments = [];
        if (options.attachments !== undefined && options.attachments.length > 0 && options.booFirstTime) {
            thisDialog.previously_att_global = options.attachments;
            arrInsertAttachments = options.attachments;
        }

        if (options.letter_templates_attachments !== undefined && options.letter_templates_attachments.length > 0) {
            $.each(options.letter_templates_attachments, function (k, v) {
                thisDialog.previously_att_global.push(v);
            });
            if (!options.booFirstTime || (options.attachments === undefined || options.attachments.length == 0)) {
                arrInsertAttachments = options.letter_templates_attachments;
            }
        }

        if (arrInsertAttachments !== undefined && arrInsertAttachments.length > 0) {
            var aa_files = '';

            for (var i = 0; i < arrInsertAttachments.length; i++) {
                var file_id = arrInsertAttachments[i].id;
                var num = thisDialog.previously_att_global.length - 1 + i;

                arrInsertAttachments[i].unique_file_id = 'assigned-attachment-' + num;

                var download_link = '';

                if (!arrInsertAttachments[i].letter_template_attachment) {
                    download_link = (arrInsertAttachments[i].link) ? arrInsertAttachments[i].link : topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id;
                } else {
                    if (arrInsertAttachments[i]['template_file_attachment']) {
                        download_link = topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id + '&type=template_file_attachment&name=' + arrInsertAttachments[i].original_file_name + '&template_id=' + arrInsertAttachments[i].template_id;
                    } else {
                        download_link = topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id + '&type=letter_template&name=' + arrInsertAttachments[i].original_file_name + '&template_id=' + arrInsertAttachments[i].template_id;
                    }
                }

                var onclick = arrInsertAttachments[i].onclick ? 'onclick="' + arrInsertAttachments[i].onclick + '"' : '';

                var convertOnclick = 'Ext.getCmp(\'mail-create-dialog\').convertFileToPdf(' + options.member_id + ', {file_id: \'' + file_id + '\', filename: \'' + arrInsertAttachments[i].original_file_name + '\', booProspect: ' + options.booProspect + ', unique_file_id: \'' + arrInsertAttachments[i].unique_file_id + '\', booTemp: ' + arrInsertAttachments[i].letter_template_attachment + '})';

                var convertToPDFLink = arrInsertAttachments[i].libreoffice_supported ? '<img src="' + topBaseUrl + '/images/pdf.png" class="attachment-convert" onclick="' + convertOnclick + '"  alt="Convert to PDF" title="Convert to PDF" />' : '';

                aa_files += '<li id="' + arrInsertAttachments[i].unique_file_id + '" class="qq-file-id-' + file_id + ' qq-upload-success ' + (arrInsertAttachments[i].letter_template_attachment ? ' letter-template-attachment' : '') + '" qq-file-id="' + file_id + '">' +
                    '<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>' +
                    '<div class="qq-progress-bar-container-selector qq-hide">' +
                    '<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>' +
                    '</div>' +

                    '<span class="qq-upload-spinner-selector qq-upload-spinner qq-hide"></span>' +
                    '<div class="qq-thumbnail-wrapper" style="display: none;">' +
                    '<a class="preview-link" target="_blank">' +
                    '<img class="qq-thumbnail-selector" qq-max-size="120" qq-server-scale>' +
                    '</a>' +
                    '</div>' +
                    '<img src="' + topBaseUrl + '/images/deleteicon.gif" class="attachment-cancel" onclick="Ext.getCmp(\'mail-create-dialog\').removeAttachment(this); return false;" alt="Cancel" />' +
                    convertToPDFLink +
                    '<div class="qq-file-info">' +
                    '<div class="qq-file-name">' +
                    '<span class="qq-upload-file-selector qq-upload-file" title="' + arrInsertAttachments[i].original_file_name + '">' +
                    '<a href="' + download_link + '" class="bluelink" target="_blank"' + onclick + '>' + (arrInsertAttachments[i].original_file_name.length > 20 ? arrInsertAttachments[i].original_file_name.substr(0, 20) + '...' : arrInsertAttachments[i].original_file_name) + '</a>' +
                    '</span>' +
                    '<span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>' +
                    '</div>' +
                    '<span class="qq-upload-size-selector qq-upload-size">' + arrInsertAttachments[i].size + '</span>' +
                    '</div>' +
                    '</li>';

            }
            setTimeout(function () {
                if (empty($('.qq-upload-list-selector.qq-upload-list').html())) {
                    $('.qq-upload-list-selector.qq-upload-list').prepend(aa_files);
                } else if (options.letter_templates_attachments !== undefined && options.letter_templates_attachments.length > 0) {
                    $('.qq-upload-list-selector.qq-upload-list').append(aa_files);
                }
            }, 300);

            this.toggleSection('attachments', false, true);
        }

        if (options.member_id) {
            // update handler
            Ext.getCmp('mail-tb-btn-send-and-save').setHandler(function () {
                Ext.getCmp('mail-create-dialog').sendEmail(options.member_id);
            });
        }

        this.resyncWindowSize();
    },

    selectDeliveries: function (record, object) {
        var cur_val = object.getRawValue();
        var query = record.store.baseParams.query.split(/,|;/).pop();

        var prev_mails = cur_val.substr(0, cur_val.length - query.length);
        if (prev_mails.length > 0)
            prev_mails += ' ';

        var selName = record.data.name;
        var selTo = record.data.to;
        var add_mail = (selName ? '"' + selName + '" ' : '') + (selName ? '&lt;' + selTo + '&gt;' : selTo);

        add_mail = add_mail.replace('&lt;', '<');
        add_mail = add_mail.replace('&gt;', '>');

        object.setRawValue(prev_mails + add_mail + ', ');

        object.collapse();
    },

    fillFieldsAfterTemplateParsing: function (options) {
        options.template_id = Ext.getCmp('mail-create-template').getValue();
        options.booFirstTime = false;

        if (Ext.getCmp('mail-create-from-mail-tab').getValue()) {
            options.parse_to_field = true;
        }

        Ext.getCmp('mail-create-dialog').parseTemplate(options);
    },

    sendEmail: function (save_to_client_id) {
        var thisDialog = this;

        // Check if all attachments were uploaded
        if (!thisDialog.upload_finished) {
            Ext.simpleConfirmation.warning(_('Please wait while all attachments will be uploaded or cancel uploading'));
            return;
        }

        // Email To field validation
        if (empty(Ext.getCmp('mail-create-to').getValue())) {
            Ext.simpleConfirmation.warning('Please enter the recipient\'s email');
            return;
        }

        // Email Message field validation
        var emailBody = Ext.getCmp('mail-create-message').getValue();
        if (empty(emailBody)) {
            Ext.simpleConfirmation.warning('Please enter the email\'s message');
            return;
        }

        var send_save = Ext.getCmp('mail-save-send-clients') === undefined ? 0 : 1;
        var save_to_client = false;
        var save_this_mail = false;
        var save_original_mail = false;
        var remove_original_mail = false;

        if (send_save) {
            var clientField = Ext.getCmp('mail-save-send-clients');
            var selClientId = clientField.getValue();

            if (empty(selClientId) || getComboBoxIndex(clientField) == -1) {
                clientField.markInvalid('Please select a case.');
                return false;
            }

            save_to_client = selClientId;
            save_this_mail = Ext.getCmp('mail-save-this-mail').getValue();
            save_original_mail = Ext.getCmp('mail-save-original-mail').getValue();
            remove_original_mail = Ext.getCmp('mail-remove-original-mail').getValue();
        }

        // Force using a provided client id
        if (save_to_client_id) {
            send_save = 1;
            save_this_mail = 'true';
            save_to_client = save_to_client_id;
        }

        // Send
        //get attachments list
        var att = [];
        var previously_att = thisDialog.previously_att_global;

        if (previously_att !== undefined && previously_att.length > 0) {
            for (var i = 0; i < previously_att.length; i++) {
                if ($('#' + previously_att[i].unique_file_id).length !== 0) {
                    att.push(previously_att[i]);
                }
            }
        }

        var forwarded = Ext.getCmp('mail-create-forwarded').getValue();
        var replied = Ext.getCmp('mail-create-replied').getValue();

        var toolbar = Ext.getCmp('mail-main-toolbar');
        var accountId = toolbar ? toolbar.getSelectedInToAccountId() : 0;
        if (!accountId) {
            var fromField = Ext.getCmp('mail-create-from');
            if (!empty(fromField.getValue())) {
                accountId = fromField.getValue();
            }
        }

        thisDialog.getEl().mask('Sending...');

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/send',
            params: {
                // letter
                account_id: accountId,
                from: accountId,
                email: Ext.encode(Ext.getCmp('mail-create-to').getValue()),
                cc: Ext.encode(Ext.getCmp('mail-create-cc').getValue()),
                bcc: Ext.encode(Ext.getCmp('mail-create-bcc').getValue()),
                subject: Ext.encode(Ext.getCmp('mail-create-subject').getValue()),
                message: Ext.encode(Ext.getCmp('mail-create-message').getValue()),
                'mail-create-template': Ext.encode(Ext.getCmp('mail-create-template').getValue()),
                // attachments
                attachments_array: Ext.encode(thisDialog.uploaded_attachments),
                attached: Ext.encode(att), // pre-attached files: if user mails file from Docs or forwards mail with attaches
                // send and save
                send_save: send_save,
                save_to_client: save_to_client,
                save_this_mail: save_this_mail,
                save_original_mail: save_original_mail,
                remove_original_mail: remove_original_mail,
                original_mail_id: Ext.getCmp('mail-create-original-mail-id').getValue(),
                save_to_prospect: Ext.getCmp('mail-create-save-to-prospect').getValue(),
                // hidden
                forwarded: forwarded,
                replied: replied,
                panel_type: Ext.getCmp('mail-create-panel-type').getValue(),
                create_from_mail_tab: Ext.getCmp('mail-create-from-mail-tab').getValue(),
                draft_id: Ext.getCmp('mail-create-draft-id').getValue(),
                member_id_for_activity: Ext.getCmp('mail-member-id-for-activity').getValue()
            },
            waitMsg: 'Sending...',
            success: function (f) {
                var result = Ext.decode(f.responseText);

                //next member
                if (result.success) {
                    thisDialog.close();

                    if (Ext.getCmp('mail-send-save-dialog') !== undefined)
                        Ext.getCmp('mail-send-save-dialog').close();


                    var grid = Ext.getCmp('mail-ext-emails-grid');
                    if (grid) {
                        var booRefreshGrid = false;

                        var selected_folder = Ext.getCmp('mail-ext-folders-tree').getSelectionModel().getSelectedNode();
                        if (selected_folder) {
                            var sel_folder_id = selected_folder.attributes.folder_id;
                            if (remove_original_mail || sel_folder_id == 'drafts' || sel_folder_id == 'sent') {
                                booRefreshGrid = true;
                            }
                        }

                        if (!empty(forwarded) || !empty(replied)) {
                            booRefreshGrid = true;
                        }

                        if (booRefreshGrid) {
                            grid.store.reload();
                        }
                    }


                    if (empty(thisDialog.next_member))
                        Ext.simpleConfirmation.msg('Info', 'Mail was successfully sent');
                } else {
                    Ext.simpleConfirmation.error(result.msg);

                    thisDialog.getEl().unmask();
                }
            },
            failure: function () {
                Ext.simpleConfirmation.error('An error occurred when sending mail');
                thisDialog.getEl().unmask();
            }
        }); // end of Ajax request
    },

    sendSaveEmail: function (booOnlySave, booNewEmail, booProspect) {
        var thisDialog = this;
        if (booOnlySave === undefined)
            booOnlySave = false;

        if (!booOnlySave) {
            // Check if all attachments were uploaded
            if (!thisDialog.upload_finished) {
                Ext.simpleConfirmation.warning(_('Please wait while all attachments will be uploaded or cancel uploading'));
                return;
            }

            // Email To field validation
            if (empty(Ext.getCmp('mail-create-to').getValue())) {
                Ext.simpleConfirmation.warning('Please enter the recipient\'s email');
                return;
            }

            // Email Message field validation
            var emailBody = Ext.getCmp('mail-create-message').getValue();
            if (empty(emailBody)) {
                Ext.simpleConfirmation.warning('Please enter the email\'s message');
                return;
            }
        }

        var booContact = this.thisDialogOptions && this.thisDialogOptions.booContact !== undefined ? this.thisDialogOptions.booContact : false;
        var parentMemberId = this.thisDialogOptions && !booContact ? this.thisDialogOptions.parentMemberId : null;
        var clientOrProspectId = this.thisDialogOptions && !empty(this.thisDialogOptions.member_id) ? this.thisDialogOptions.member_id : null;

        sendEmailDialog = new MailSendSaveDialog(Ext.getCmp('mail-create-panel-type').getValue(), booProspect, parentMemberId, clientOrProspectId);
        sendEmailDialog.createDialog(booOnlySave, booNewEmail);
    },

    saveDraft: function () {
        var thisDialog = this;

        // Check if all attachments were uploaded
        if (!thisDialog.upload_finished) {
            Ext.simpleConfirmation.warning(_('Please wait while all attachments will be uploaded or cancel uploading'));
            return;
        }

        thisDialog.getEl().mask('Saving...');

        var att = [];
        var previously_att = thisDialog.previously_att_global;

        if (previously_att !== undefined && previously_att.length > 0) {
            for (var i = 0; i < previously_att.length; i++) {
                if ($('#' + previously_att[i].unique_file_id).length !== 0) {
                    att.push(previously_att[i]);
                }
            }
        }

        var accounts = mail_settings.accounts;
        var default_account_id = 0;
        for (var i = 0; i < accounts.length; i++) {
            if (accounts[i].is_default == 'Y') {
                default_account_id = accounts[i].account_id;
                break;
            }
        }

        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/save-draft',
            params:
                {
                    // letter
                    account_id: typeof (Ext.getCmp('mail-main-toolbar')) !== 'undefined' ? Ext.getCmp('mail-main-toolbar').getSelectedInToAccountId() : default_account_id,
                    email: Ext.encode(Ext.getCmp('mail-create-to').getValue()),
                    cc: Ext.encode(Ext.getCmp('mail-create-cc').getValue()),
                    bcc: Ext.encode(Ext.getCmp('mail-create-bcc').getValue()),
                    subject: Ext.encode(Ext.getCmp('mail-create-subject').getValue()),
                    message: Ext.encode(Ext.getCmp('mail-create-message').getValue()),
                    'mail-create-template': Ext.encode(Ext.getCmp('mail-create-template').getValue()),
                    // attachments
                    attachments_array: Ext.encode(thisDialog.uploaded_attachments),
                    attached: Ext.encode(att), // files, which were attached before user started to edit draft
                    // hidden
                    create_from_mail_tab: Ext.getCmp('mail-create-from-mail-tab').getValue(),
                    draft_id: Ext.getCmp('mail-create-draft-id').getValue()
                },

            success: function (f, o) {
                var result = Ext.decode(f.responseText);

                if (result.success) {
                    Ext.getCmp('mail-create-draft-id').setValue(result.draft_id);

                    var result_att = Ext.decode(result.attachments);

                    for (var i = 0; i < result_att.length; i++)
                        result_att[i]['unique_file_id'] = 'assigned-attachment-' + i;

                    thisDialog.previously_att_global = result_att;
                    thisDialog.uploaded_attachments = [];

                    // update attachments HTML
                    var aa_files = '';

                    for (i = 0; i < thisDialog.previously_att_global.length; i++) {
                        var file_id = thisDialog.previously_att_global[i].id;
                        thisDialog.previously_att_global[i].unique_file_id = 'assigned-attachment-' + i;

                        var download_link = (thisDialog.previously_att_global[i].link) ? thisDialog.previously_att_global[i].link : topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id;

                        aa_files += '<li id="' + thisDialog.previously_att_global[i].unique_file_id + '" class="qq-file-id-' + file_id + ' qq-upload-success" qq-file-id="' + file_id + '">' +
                            '<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>' +
                            '<div class="qq-progress-bar-container-selector qq-hide">' +
                            '<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>' +
                            '</div>' +

                            '<span class="qq-upload-spinner-selector qq-upload-spinner qq-hide"></span>' +
                            '<div class="qq-thumbnail-wrapper" style="display: none;">' +
                            '<a class="preview-link" target="_blank">' +
                            '<img class="qq-thumbnail-selector" qq-max-size="120" qq-server-scale>' +
                            '</a>' +
                            '</div>' +
                            '<img src="' + topBaseUrl + '/images/deleteicon.gif" class="attachment-cancel" onclick="Ext.getCmp(\'mail-create-dialog\').removeAttachment(this); return false;" alt="Cancel" />' +
                            '<div class="qq-file-info">' +
                            '<div class="qq-file-name">' +
                            '<span class="qq-upload-file-selector qq-upload-file" title="' + thisDialog.previously_att_global[i].original_file_name + '">' +
                            '<a href="' + download_link + '" class="bluelink" target="_blank">' + (thisDialog.previously_att_global[i].original_file_name.length > 20 ? thisDialog.previously_att_global[i].original_file_name.substr(0, 20) + '...' : thisDialog.previously_att_global[i].original_file_name) + '</a>' +
                            '</span>' +
                            '<span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>' +
                            '</div>' +
                            '<span class="qq-upload-size-selector qq-upload-size">' + thisDialog.previously_att_global[i].size + '</span>' +
                            '</div>' +
                            '</li>';
                    }

                    $('.qq-upload-list-selector.qq-upload-list').html(aa_files);

                    thisDialog.resyncWindowSize();
                    thisDialog.getEl().mask('Done');
                    setTimeout(function () {
                        thisDialog.getEl().unmask();
                    }, 750);
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                    thisDialog.getEl().unmask();
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('An error occurred when saving draft');
                thisDialog.getEl().unmask();
            }
        }); // end of Ajax request
    },

    parseTemplate: function (options) {
        var thisDialog = this;
        thisDialog.getEl().mask(_('Loading...'));

        var sendToEmail = empty(options.emailTo) ? (empty(options.data.email) ? '' : options.data.email) : options.emailTo;

        var use_parse_to_field = (!empty(options.parse_to_field) && options.parse_to_field) ? 1 : 0;

        Ext.Ajax.request({
            url: topBaseUrl + '/templates/index/get-message',
            params: {
                template_id: options.template_id,
                member_id: options.member_id,
                parentMemberId: options.parentMemberId,
                email: Ext.encode(sendToEmail),
                booProspect: Ext.encode(options.booProspect),
                use_parse_to_field: use_parse_to_field,
                encoded_password: empty(options.encoded_password) ? '' : Ext.encode(options.encoded_password),
                save_to_prospect: Ext.encode(options.save_to_prospect),
                parse_to_field: (!empty(options.parse_to_field) && options.parse_to_field) ? Ext.getCmp('mail-create-to').getValue() : '',
                invoice_id: (!empty(options.invoice_id) && options.invoice_id) ? options.invoice_id : ''
            },

            success: function (f) {
                var data = Ext.decode(f.responseText);
                if (data) {
                    var reply_or_forward_text = use_parse_to_field === 0 ? '' : '<br><br>' + Ext.getCmp('mail-create-message').getValue();

                    options.emailMessage = data.message ? data.message.replace(/\r\n/g, '<br />') + '<!--reply_or_forward_text-->' + reply_or_forward_text.split('<!--reply_or_forward_text-->').pop() : '';

                    options.emailSubject = data.subject ? data.subject : '';
                    if (data.from) options.emailFrom = data.from;

                    options.emailTo = use_parse_to_field === 0 ? (empty(sendToEmail) ? data.email : sendToEmail) : Ext.getCmp('mail-create-to').getValue();

                    options.emailCC = data.cc ? data.cc : '';
                    options.emailBCC = data.bcc ? data.bcc : '';
                    options.letter_templates_attachments = data.attachments;

                    if (thisDialog.previously_att_global !== undefined) {
                        for (var j = thisDialog.previously_att_global.length - 1; j >= 0; j--) {
                            if (thisDialog.previously_att_global[j]['letter_template_attachment']) {
                                thisDialog.previously_att_global.splice(j, 1);
                            }
                        }
                        $('.letter-template-attachment').remove();
                    }

                }

                thisDialog.setMailFormValues(options);
                Ext.getCmp('mail-create-subject').focus();

                thisDialog.getEl().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error('Cannot load template');
                thisDialog.getEl().unmask();
            }
        });
    },

    removeAttachment: function (el) {
        var thisDialog = this;

        var id = $(el).parent('li').attr('qq-file-id');
        $(el).parent('li').remove();

        for (var i = 0; i < thisDialog.uploaded_attachments.length; i++) {
            if (thisDialog.uploaded_attachments[i]['id'] == id) {
                thisDialog.uploaded_attachments.splice(i, 1);
                break;
            }
        }

        thisDialog.resyncWindowSize();
    },

    convertFileToPdf: function (member_id, options) {
        var thisDialog = this;
        var file_id = options['file_id'];
        var filename = options['filename'];
        var booTemp = options['booTemp'];

        if (!empty(file_id) && !empty(filename)) {
            thisDialog.getEl().mask(_('Converting. Please wait...'));

            Ext.Ajax.request({
                url: topBaseUrl + '/' + (options.booProspect ? 'prospects' : 'documents') + '/index/convert-to-pdf',
                params: {
                    folder_id: 'tmp',
                    member_id: member_id,
                    file_id: Ext.encode(file_id),
                    filename: Ext.encode(filename),
                    boo_temp: booTemp ? 1 : 0
                },

                success: function (result) {
                    thisDialog.getEl().unmask();

                    try {
                        var resultData = Ext.decode(result.responseText);
                    } catch (e) {
                        var resultData = result.responseText;
                    }

                    if (resultData.success) {
                        Ext.simpleConfirmation.success('File was converted successfully.');
                        $('#' + options.unique_file_id).remove();

                        var original_file_name = filename.substr(0, filename.lastIndexOf('.')) + '.pdf';
                        var file_id = resultData.file_id;
                        var link = '#';
                        var size = resultData.file_size;
                        var download_link = link ? link : topBaseUrl + '/mailer/index/download-attach?attach_id=' + file_id;
                        var onclick = 'submit_hidden_form(\'' + topBaseUrl + '/' + (options.booProspect ? 'prospects' : 'documents') + '/index/get-pdf/\', {member_id: \'' + member_id + '\', file: \'' + original_file_name + '\', id: \'' + file_id + '\', boo_tmp: 1}); return false;';

                        var aa_file = '<li id="' + options.unique_file_id + '" class="qq-file-id-' + file_id + ' qq-upload-success ' + (booTemp ? ' letter-template-attachment' : '') + '" qq-file-id="' + file_id + '">' +
                            '<span role="status" class="qq-upload-status-text-selector qq-upload-status-text"></span>' +
                            '<div class="qq-progress-bar-container-selector qq-hide">' +
                            '<div role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" class="qq-progress-bar-selector qq-progress-bar"></div>' +
                            '</div>' +

                            '<span class="qq-upload-spinner-selector qq-upload-spinner qq-hide"></span>' +
                            '<div class="qq-thumbnail-wrapper" style="display: none;">' +
                            '<a class="preview-link" target="_blank">' +
                            '<img class="qq-thumbnail-selector" qq-max-size="120" qq-server-scale>' +
                            '</a>' +
                            '</div>' +
                            '<img src="' + topBaseUrl + '/images/deleteicon.gif" class="attachment-cancel" onclick="Ext.getCmp(\'mail-create-dialog\').removeAttachment(this); return false;" alt="Cancel" />' +
                            '<div class="qq-file-info">' +
                            '<div class="qq-file-name">' +
                            '<span class="qq-upload-file-selector qq-upload-file" title="' + original_file_name + '">' +
                            '<a href="' + download_link + '" class="bluelink" target="_blank" onclick="' + onclick + '">' + (original_file_name.length > 20 ? original_file_name.substr(0, 20) + '...' : original_file_name) + '</a>' +
                            '</span>' +
                            '<span class="qq-edit-filename-icon-selector qq-edit-filename-icon" aria-label="Edit filename"></span>' +
                            '</div>' +
                            '<span class="qq-upload-size-selector qq-upload-size">' + size + '</span>' +
                            '</div>' +
                            '</li>';

                        $('.qq-upload-list-selector.qq-upload-list').prepend(aa_file);

                        if (thisDialog.previously_att_global !== undefined && thisDialog.previously_att_global.length > 0) {
                            for (var i = 0; i < thisDialog.previously_att_global.length; i++) {
                                if (thisDialog.previously_att_global[i].unique_file_id == options.unique_file_id) {
                                    thisDialog.previously_att_global[i].id = file_id;
                                    thisDialog.previously_att_global[i].libreoffice_supported = false;
                                    thisDialog.previously_att_global[i].onclick = onclick;
                                    thisDialog.previously_att_global[i].original_file_name = original_file_name;
                                    thisDialog.previously_att_global[i].size = size;
                                    thisDialog.previously_att_global[i].letter_template_attachment = booTemp;
                                }
                            }
                        }

                        thisDialog.resyncWindowSize();
                    } else {
                        if (typeof resultData == 'object') {
                            Ext.simpleConfirmation.error(resultData.error);
                        } else {
                            Ext.simpleConfirmation.error(resultData);
                        }
                    }
                },

                failure: function () {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.error('File cannot be converted. Please try again later.');
                }
            });
        }
    }
});