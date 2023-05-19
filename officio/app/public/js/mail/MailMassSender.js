var MailMassSender = function (config) {
    this.config = config;
    var booShowDetails = true;

    // Get active mail account
    var accounts = mail_settings.accounts;
    this.config.accountId = 0;
    for (var i = 0; i < accounts.length; i++) {
        if (empty(i) || accounts[i].is_default === 'Y') {
            this.config.accountId = accounts[i].account_id;
            break;
        }
    }

    // Used to interrupt mails sending
    this.booStopSending = false;

    this.statusProgressBar = new Ext.ProgressBar({
        text: '',
        width: 350
    });

    this.statusTextArea = new Ext.form.HtmlEditor({
        hideLabel: true,
        hidden: !booShowDetails,
        value: '',
        width: 350
    });

    this.cancelBtn = new Ext.Button({
        text: 'Cancel',
        scope: this,
        width: 60,
        handler: this.stopSending.createDelegate(this)
    });

    this.closeBtn = new Ext.Button({
        text: 'Close',
        hidden: true,
        scope: this,
        width: 60,
        handler: this.closeDialog.createDelegate(this)
    });

    this.statusPanel = new Ext.Panel({
        layout: 'column',
        defaults: {
            layout: 'form',
            border: false,
            bodyStyle: 'padding: 4px 0;'
        },
        items: [
            {
                xtype: 'fieldset',
                width: 310,
                items: {
                    xtype: 'checkbox',
                    hideLabel: true,
                    boxLabel: 'Show Details',
                    checked: booShowDetails,
                    scope: this,
                    handler: this.toggleTextArea.createDelegate(this)
                }
            }, {
                xtype: 'fieldset',
                width: 85,
                style: 'text-align: right;',
                items: [this.cancelBtn, this.closeBtn]
            }
        ]
    });

    this.FieldsForm = new Ext.Panel({
        frame: false,
        bodyStyle: 'padding:5px',
        items: [
            this.statusProgressBar,
            this.statusPanel,
            this.statusTextArea
        ]
    });

    MailMassSender.superclass.constructor.call(this, {
        y: 250,
        autoWidth: true,
        autoHeight: true,
        closable: false,
        plain: true,
        border: false,
        modal: true,
        buttonAlign: 'center',
        items: this.FieldsForm
    });

    // Apply parameters and send request
    this.on('show', this.initDialog.createDelegate(this), this);
};

Ext.extend(MailMassSender, Ext.Window, {
    // Actions related to current dialog
    initDialog: function() {
        this.resetStatus();
        this.resetTextArea();

        this.statusTextArea.getToolbar().hide();
        this.sendRequest(this.config.arrMemberIds, this.config.templateId, 0, this.config.arrMemberIds.length);
        this.syncShadow();
    },

    closeDialog: function () {
        this.close();
    },

    // Actions related to text area (html editor)
    toggleTextArea: function (checkbox, booChecked) {
        this.statusTextArea.setVisible(booChecked);
        this.syncShadow();
    },

    updateTextArea: function (strValue, booAddBR) {
        var currValue = this.statusTextArea.getValue();
        if (booAddBR && !empty(currValue)) {
            currValue += '<br/>';
        }
        this.statusTextArea.setValue(currValue + strValue);

        var height = this.statusTextArea.getEl().dom.scrollHeight;
        this.statusTextArea.getWin().scrollTo(0, height);
    },

    resetTextArea: function () {
        this.statusTextArea.setValue();
    },


    // Actions related to progress bar
    resetStatus: function () {
        this.updateStatus(0, 'Connecting to server...');
    },

    updateStatus: function (intProgress, strNewStatus) {
        this.statusProgressBar.updateProgress(intProgress, strNewStatus, false);
    },

    updateTextStatusOnly: function (txtStatus) {
        this.statusProgressBar.updateText(txtStatus);
    },

    showErrorStatus: function (strErrorMessage) {
        var txtStatus = String.format(
            '<span style="color: red;">{0}</span>',
            strErrorMessage
        );
        this.updateTextStatusOnly(txtStatus);
    },


    // Global functionality
    stopSending: function () {
        this.booStopSending = true;
        this.updateTextStatusOnly('Stopping...');
        this.showCloseButton();
    },

    showCloseButton: function () {
        this.cancelBtn.hide();
        this.closeBtn.show();
    },

    sendRequest: function (arrMemberIds, templateId, processedCount, totalCount) {
        var wnd = this;
        if(wnd.booStopSending || totalCount === 0) {
            wnd.showCloseButton();
            wnd.updateTextStatusOnly('Cancelled');
            wnd.updateTextArea('<span style="color: red;">*** Cancelled by user ***</span>', true);
            return;
        }

        Ext.Ajax.request({
            url: baseUrl + '/mailer/index/send-mailer',
            timeout: 5 * 60 * 1000, // 5 minutes

            params: {
                'accountId':      Ext.encode(wnd.config.accountId || 0),
                'booProspects':   Ext.encode(wnd.config.booProspects || false),
                'arrMemberIds':   Ext.encode(arrMemberIds),
                'templateId':     Ext.encode(templateId),
                'processedCount': Ext.encode(processedCount),
                'totalCount':     Ext.encode(totalCount)
            },

            success: function (f) {
                if (wnd.isVisible()) {
                    var result = Ext.decode(f.responseText);
                    if (result.success) {
                        // Update progress
                        var txtStatus = String.format(
                            'Processed {0} out of {1} {2}s...',
                            result.processedCount,
                            result.totalCount,
                            wnd.config.booProspects ? 'prospect' : 'client'
                        );

                        var percentStatus = result.processedCount / result.totalCount;
                        wnd.updateStatus(percentStatus, txtStatus);

                        // Update details
                        wnd.updateTextArea(result.textStatus, false);

                        // Run another request if needed
                        if (result.arrMemberIds.length) {
                            wnd.sendRequest(result.arrMemberIds, templateId, result.processedCount, result.totalCount);
                        } else {
                            wnd.showCloseButton();
                            wnd.updateTextArea('<span style="color: green;">*** Finished successfully ***</span>', true);
                        }
                    } else {
                        wnd.updateTextStatusOnly('Cancelled');
                        wnd.updateTextArea('<span style="color: red;">*** ' + result.msg + ' ***</span>', true);
                        wnd.showCloseButton();
                    }
                }
            },

            failure: function () {
                if (wnd.isVisible()) {
                    wnd.showErrorStatus('Cannot send mails');
                }
            }
        });
    }
});

var showConfirmationMassEmailDialog = function (panelType, arrSelectedProspectsIds, arrAllProspectsIds) {
    var clientLabelSingular;
    var clientLabelPlural;

    var templateUrl;
    var templateParams;
    var templateRoot;

    var booProspects;
    switch (panelType) {
        case 'prospects':
        case 'marketplace':
            clientLabelSingular = _('prospect');
            clientLabelPlural = _('prospects');

            templateUrl = baseUrl + '/superadmin/manage-company-prospects/get-templates-list';
            templateRoot = 'rows';
            templateParams = {
                member_id:      0,
                show_templates: true,
                templates_type: 'prospects'
            };

            booProspects = 1;
            break;

        case 'applicants':
        case 'contacts':
            clientLabelSingular = _('client');
            clientLabelPlural = _('clients');

            templateUrl = topBaseUrl + '/templates/index/get-email-template';
            templateRoot = 'templates';
            templateParams = {
                member_id:      0,
                show_templates: true,
                templates_type: ''
            };

            booProspects = 0;
            break;

        default:
            return;
    }

    var oRadioGroup = new Ext.form.RadioGroup({
        fieldLabel: _('Recipients'),
        columns: 1,
        width: 550,

        items: [
            {
                boxLabel: String.format(_('Send to {0} selected {1}'), arrSelectedProspectsIds.length, arrSelectedProspectsIds.length === 1 ? clientLabelSingular : clientLabelPlural),
                name: 'rb-col',
                inputValue: 'selected',
                disabled: arrSelectedProspectsIds.length === 0,
                checked: arrSelectedProspectsIds.length !== 0
            }, {
                boxLabel: String.format(_('Send to all {0} {1}'), arrAllProspectsIds.length, arrAllProspectsIds.length === 1 ? clientLabelSingular : clientLabelPlural),
                name: 'rb-col',
                inputValue: 'all',
                autoWidth: true,
                checked: arrSelectedProspectsIds.length === 0
            }
        ]
    });

    var oCombo = new Ext.form.ComboBox({
        fieldLabel:     _('Select email template'),

        store: new Ext.data.Store({
            url:      templateUrl,
            baseParams: templateParams,
            autoLoad: true,

            reader: new Ext.data.JsonReader({
                root: templateRoot,
                // totalProperty: 'totalCount',
                id:   'templateId'
            }, [{name: 'templateId'}, {name: 'templateName'}])
        }),

        mode:           'local',
        valueField:     'templateId',
        displayField:   'templateName',
        triggerAction:  'all',
        forceSelection: true,
        emptyText:      _('Choose a template...'),
        readOnly:       true,
        typeAhead:      true,
        selectOnFocus:  true,
        editable:       false,
        width:          550,
        listWidth:      527
    });

    var wnd = new Ext.Window({
        title:       '<i class="las la-mail-bulk"></i>' + _('Send Mass Email'),
        plain:       false,
        modal:       true,
        resizable:   false,
        closable:    true,
        autoWidth:   true,
        layout:      'form',
        labelAlign:  'top',

        items: [oRadioGroup, oCombo],

        buttons: [{
            text:    _('Cancel'),
            handler: function () {
                wnd.close();
            }
        }, {
            text: _('Send Mass Email'),
            cls:  'orange-btn',

            handler: function () {
                var strWarning = '';

                var oSelectedRadio = oRadioGroup.getValue();
                if (empty(oSelectedRadio)) {
                    strWarning = _('Please check the option.');
                }

                var booAllRecords = oSelectedRadio.inputValue === 'all';
                if (empty(strWarning) && booAllRecords && empty(arrAllProspectsIds.length)) {
                    strWarning = String.format(_('No {0} found...'), clientLabelPlural);
                }

                if (empty(strWarning) && !booAllRecords && empty(arrSelectedProspectsIds.length)) {
                    strWarning = String.format(_('No {0} are selected.<br/>Please select at least one {1}.'), clientLabelPlural, clientLabelSingular);
                }

                var templateId = oCombo.getValue();
                if (empty(strWarning) && (empty(templateId) || templateId === oCombo.emptyText)) {
                    strWarning = _('Please select a template to email.');
                }

                if (!empty(strWarning)) {
                    Ext.simpleConfirmation.warning(strWarning);
                    return;
                }


                var arrMemberIds = booAllRecords ? arrAllProspectsIds : arrSelectedProspectsIds;
                var msg = String.format('You are about to send {0} {1} to {2}. You have selected <i>{3}</i>. Are you sure you want to proceed?',
                    arrMemberIds.length,
                    arrMemberIds.length === 1 ? _('email') : _('emails'),
                    booAllRecords ? (arrMemberIds.length === 1 ? clientLabelSingular + _(' that has been found') : clientLabelPlural + _(' that have been found')) : _('selected ') + (arrMemberIds.length === 1 ? clientLabelSingular : clientLabelPlural),
                    oCombo.getRawValue()
                );

                Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
                    if (btn == 'yes') {
                        var wndMassEmail = new MailMassSender({
                            arrMemberIds: arrMemberIds,
                            templateId:   templateId,
                            booProspects: booProspects
                        });
                        wndMassEmail.show();
                        wndMassEmail.center();

                        wnd.close();
                    }
                });

            }
        }]
    });

    wnd.show();
    wnd.center();
};