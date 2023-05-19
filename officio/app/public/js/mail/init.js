var initMail = function() {
    new MailTabPanel();
};

/*
options:
member_id
data
template_id
templates_type
email
attach [link, name, file/file_id (for documents)]
*/
var show_email_dialog = function(options) {
    var oDialog = Ext.getCmp('mail-create-dialog');
    if (oDialog && oDialog.isMailDialogOpened) {
        Ext.simpleConfirmation.warning('The "New Email" window is already open.');
        return;
    }

    options.booShowTemplates = typeof allowedPages != 'undefined' && allowedPages.has('templates-view');

    // attachments
    if (options.attach !== undefined) {
        options.attachments = [];
        for (var i = 0; i < options.attach.length; i++) {
            options.attachments.push({
                'id': options.attach[i]['file_id'],
                'link': options.attach[i]['link'],
                'onclick': options.attach[i]['onclick'],
                'original_file_name': options.attach[i]['name'],
                'size': options.attach[i]['size'],
                'libreoffice_supported': options.attach[i]['libreoffice_supported'],
                'path': options.attach[i]['path']
            });
        }
    }

    // Data options
    if (!options.data) {
        options.data = {};
    }

    // Don't show templates combo if there is no access to the Templates module
    var booHasAccess = options.save_to_prospect ? allowedPages.has('prospects') : allowedPages.has('templates-view');

    if (!booHasAccess) {
        options.booShowTemplates = false;
    }

    var create_dialog = new MailCreateDialog(options);
    create_dialog.showDialog(options);

    // Send additional request to load templates
    // only when that is required
    if (options.booShowTemplates) {
        // Load templates list
        create_dialog.getEl().mask('Loading...');
        Ext.Ajax.request({
            url: topBaseUrl + ((options.save_to_prospect || options.booProspect) ? '/superadmin/manage-company-prospects/get-templates-list' : '/templates/index/get-email-template'),

            params: {
                member_id: options.member_id,
                parent_member_id: !empty(options.parentMemberId) ? options.parentMemberId : 0,
                show_templates: Ext.encode(options.booShowTemplates),
                templates_type: (options.save_to_prospect || options.booProspect) ? 'prospects' : options.templates_type
            },

            success: function(f)
            {
                try {
                    var result = Ext.decode(f.responseText);

                    if (result.prospectEmail) {
                        options.emailTo = result.prospectEmail;
                    } else if (!empty(options.panelType) && options.panelType === 'marketplace') {
                        Ext.simpleConfirmation.warning('There is no email address set to this prospect.');

                        // Close this dialog with delay - because of js error.
                        setTimeout(function() {
                            create_dialog.close();
                        }, 300);
                        return;
                    }

                    if (result.to && !options.booCreateFromProfile) {
                        options.emailTo = result.to;
                    }

                    // Use default template if provided
                    var arrTemplates = [];
                    if (options.save_to_prospect && result.rows) {
                        arrTemplates = result.rows;
                    } else if(result.templates) {
                        arrTemplates = result.templates;
                    }

                    if (options.save_to_prospect || !options.template_id) {
                        if (result.default_template_id) {
                           options.template_id = result.default_template_id;
                       } else if (arrTemplates.length) {
                           options.template_id = arrTemplates[0].templateId;
                       }
                    }

                    // We have template_id
                    if ((options.template_id || options.templates_type)) {
                        if (options.booShowTemplates) {
                            options.templates = arrTemplates;
                        }

                        options.booFirstTime = true;

                        if (!options.booDontPreselectTemplate) {
                            create_dialog.parseTemplate(options);
                        } else {
                            create_dialog.setMailFormValues(options);
                            create_dialog.getEl().unmask();
                        }
                    } else {
                        // Simply apply provided values
                        create_dialog.setMailFormValues(options);
                        create_dialog.getEl().unmask();
                    }
                } catch (e) {
                    create_dialog.getEl().unmask();
                }
            },

            failure: function() {
                Ext.Msg.alert('Status', 'Cannot open send templates dialog');
                create_dialog.getEl().unmask();
            }
        });
    } else {
        // Parse provided template
        options.booFirstTime = true;
        if (!empty(options.template_id)) {
            create_dialog.parseTemplate(options);
        } else {
            // Simply apply provided values
            create_dialog.setMailFormValues(options);
        }
    }
};