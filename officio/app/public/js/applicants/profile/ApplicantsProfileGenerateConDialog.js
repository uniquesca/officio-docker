ApplicantsProfileGenerateConDialog = function (config, owner) {
    var thisDialog = this;
    this.owner     = owner;
    Ext.apply(this, config);

    this.buttons = [
        {
            text: _('Close'),
            scope: this,
            handler: function () {
                this.close();
            }
        }, {
            text: '<i class="las la-envelope"></i>' + _('Email Draft'),
            ref: '../generateDraftBtn',
            width: 100,
            style: 'margin-right: 20px',
            disabled: true,
            handler: this.generateDraft.createDelegate(this)
        }, {
            text: '<i class="las la-file-pdf"></i>' + _('Generate PDF'),
            cls: 'orange-btn',
            ref: '../generatePdfBtn',
            width: 100,
            disabled: true,
            handler: this.submitData.createDelegate(this)
        }
    ];

    ApplicantsProfileGenerateConDialog.superclass.constructor.call(this, {
        title:   '<i class="las la-book"></i>' + _('Generate Certificate of Naturalisation'),

        y:           10,
        width:       820,
        height:      600,
        autoScroll:  true,
        closeAction: 'close',
        plain:       false,
        modal:       true,
        buttonAlign: 'right',
        bodyStyle:   'background-color: #EDEDED;',

        autoLoad: {
            url:      topBaseUrl + '/applicants/profile/generate-con/',
            params:   {client_id: config.caseId},
            callback: function (el, booSuccess, response) {
                if ($('#generated_con_form').length) {
                    thisDialog.generatePdfBtn.setDisabled(false);
                    thisDialog.generateDraftBtn.setDisabled(false);
                } else {
                    Ext.simpleConfirmation.error(response.responseText);
                    thisDialog.close();
                }
            }
        }
    });
};

Ext.extend(ApplicantsProfileGenerateConDialog, Ext.Window, {
    generateDraft: function () {
        var thisDialog = this;

        var oParams = $('#generated_con_form').serializeArray();
        oParams.push({
            name:  'is_draft',
            value: 1
        });
        oParams.push({
            name:  'client_id',
            value: this.caseId
        });

        var oAllParams = {};
        for (var i = 0; i < oParams.length; i++) {
            oAllParams[oParams[i]['name']] = oParams[i]['value'];
        }

        thisDialog.getEl().mask(_('Loading...'));
        Ext.Ajax.request({
            url:    topBaseUrl + '/applicants/profile/export-con',
            params: oAllParams,

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    var original_file_name = 'CON Draft.pdf';

                    var arrAttachments = [{
                        'file_id':               resultData.file_id,
                        'link':                  '#',
                        'onclick':               'submit_hidden_form(\'' + topBaseUrl + '/documents/index/get-pdf/\', {file: \'' + original_file_name + '\', member_id: \'' + thisDialog.caseId + '\', id: \'' + resultData.file_id + '\', boo_tmp: 1}); return false;',
                        'name':                  original_file_name,
                        'size':                  resultData.file_size,
                        'path':                  resultData.file_path,
                        'libreoffice_supported': false,
                    }];

                    show_email_dialog({
                        member_id:                thisDialog.caseId,
                        booNewEmail:              false,
                        emailSubject:             '',
                        emailMessage:             '',
                        attach:                   arrAttachments,
                        booShowTemplates:         true,
                        booCreateFromMailTab:     false,
                        booDontPreselectTemplate: false,
                        booProspect:              false,
                        template_id:              resultData.template_id
                    });
                } else {
                    Ext.simpleConfirmation.error(resultData.msg);
                }

                thisDialog.getEl().unmask();
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                thisDialog.getEl().unmask();
            }
        });
    },

    submitData: function () {
        var thisDialog = this;

        var oParams = $('#generated_con_form').serializeArray();
        oParams.push({
            name:  'is_draft',
            value: 0
        });
        oParams.push({
            name:  'client_id',
            value: this.caseId
        });

        $.fileDownload(
            topBaseUrl + '/applicants/profile/export-con',
            {
                httpMethod: 'POST',
                modal:      false,
                data:       oParams,

                prepareCallback: function () {
                    thisDialog.getEl().mask(_('Generating...'));
                },

                successCallback: function () {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.msg(_('Success'), _('CON was generated successfully.'), 5000);
                },

                failCallback: function (responseHtml) {
                    thisDialog.getEl().unmask();
                    Ext.simpleConfirmation.error(responseHtml);
                }
            }
        );
    }
});