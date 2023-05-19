var ApplicantsProfileLinkedCasesLinkCaseDialog = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;
    this.parentTabPanel = this.owner.owner.owner;

    var ds = new Ext.data.Store({
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-cases-list',
            method: 'post',
        }),

        baseParams: {
            parentMemberId: 0,
            booLimitCases: 1,
            booCategoryMustBeLinked: 1,
            exceptCaseId: config.caseId
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'clientId'},
            {name: 'clientFullName'},
            {name: 'emailAddresses'}
        ])
    });

    this.casesCombo = new Ext.form.ComboBox({
        fieldLabel: _('Select an exisiting Case'),
        emptyText: _('Enter individual name or case number...'),
        store: ds,
        valueField: 'clientId',
        displayField: 'clientFullName',
        forceSelection: true,
        itemSelector: 'div.x-combo-list-item',
        triggerClass: 'x-form-search-trigger',
        listClass: 'no-pointer',
        typeAhead: false,
        selectOnFocus: true,
        allowBlank: false,
        pageSize: 0,
        minChars: 2,
        queryDelay: 750,
        width: 400,
        listWidth: 389,
        doNotAutoResizeList: true
    });

    ApplicantsProfileLinkedCasesLinkCaseDialog.superclass.constructor.call(this, {
        title: '<i class="las la-user-plus"></i>' + _('Link to a Case'),
        layout: 'form',
        labelWidth: 160,
        resizable: false,
        autoHeight: true,
        autoWidth: true,
        modal: true,
        items: this.casesCombo,

        bbar: new Ext.Toolbar({
            cls: 'no-bbar-borders',
            items: [
                {
                    text: '<i class="las la-plus"></i>' + _('New Case'),
                    cls: 'orange-btn',
                    hidden: !this.parentTabPanel.hasAccessTo(this.memberType, 'add'),
                    handler: this.addLinkedNewCase.createDelegate(this)
                }, '->', {
                    text: _('Cancel'),
                    handler: function () {
                        thisWindow.close();
                    }
                }, {
                    text: '<i class="las la-link"></i>' + _('Link'),
                    cls: 'orange-btn',
                    ctCls: 'x-toolbar-cell-no-right-padding',
                    handler: this.linkToCase.createDelegate(this, [false])
                }
            ]
        })
    });

    thisWindow.on('show', function () {
        thisWindow.casesCombo.clearInvalid();
    });
};

Ext.extend(ApplicantsProfileLinkedCasesLinkCaseDialog, Ext.Window, {
    linkToCase: function (booConfirmation) {
        var thisWindow = this;

        if (thisWindow.casesCombo.isValid()) {
            thisWindow.getEl().mask(_('Linking...'));
            Ext.Ajax.request({
                url: baseUrl + '/applicants/profile/link-case-to-case',
                params: {
                    caseIdLinkFrom: Ext.encode(thisWindow.caseId),
                    caseIdLinkTo: Ext.encode(thisWindow.casesCombo.getValue()),
                    booConfirmation: Ext.encode(booConfirmation)
                },

                success: function (f) {
                    var resultData = Ext.decode(f.responseText);
                    switch (resultData.msg_type) {
                        case 'error':
                            Ext.simpleConfirmation.error(resultData.msg);
                            thisWindow.getEl().unmask();
                            break;

                        case 'confirmation':
                            Ext.Msg.confirm(_('Please confirm'), resultData.msg, function (btn) {
                                if (btn === 'yes') {
                                    thisWindow.linkToCase(true);
                                } else {
                                    thisWindow.getEl().unmask();
                                }
                            });
                            break;

                        default:
                            thisWindow.getEl().mask(resultData.msg);
                            setTimeout(function () {
                                thisWindow.parentTabPanel.applicantsCasesNavigationPanel.refreshCasesList();

                                thisWindow.owner.getStore().load();
                                thisWindow.close();
                            }, 1500);
                            break;
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error(_('Information was not saved. Please try again later.'));
                    thisWindow.getEl().unmask();
                }
            });
        }
    },

    addLinkedNewCase: function () {
        var thisWindow = this;

        var newCaseIACombo = new Ext.form.ComboBox({
            width: 400,
            store: {
                xtype: 'store',
                autoLoad: true,
                proxy: new Ext.data.HttpProxy({
                    url: topBaseUrl + '/applicants/index/get-applicants-list'
                }),

                baseParams: {
                    memberType: 'individual'
                },

                reader: new Ext.data.JsonReader({
                    root: 'items',
                    totalProperty: 'count',
                    idProperty: 'user_id',
                    fields: [
                        'user_id',
                        'user_type',
                        'user_name',
                        'applicant_id',
                        'applicant_name'
                    ]
                })
            },
            mode: 'local',
            displayField: 'user_name',
            valueField: 'user_id',
            emptyText: _('Please select an Existing Applicant...'),
            triggerAction: 'all',
            allowBlank: false,
            forceSelection: true,
            selectOnFocus: true,
            editable: true,
            disabled: true,
            hidden: true
        });

        var help = String.format(
            "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
            _('Use this option, if the contact information of the individual you are about to open a case for already exists.')
        );

        var newClientIAContainer = new Ext.Container({
            cls: 'x-table-layout-cell-top-align',

            items: [
                {
                    xtype: 'box',
                    style: 'padding: 5px 0;',
                    autoEl: {
                        tag: 'div',
                        html: _('Please select an existing individual client or create a new client to add the case for:')
                    }
                }, {
                    xtype: 'radio',
                    boxLabel: _('A <b>New</b> Individual Applicant'),
                    checked: true,
                    colspan: 3,
                    name: 'adding-case-to',
                    inputValue: 'new-client',
                    style: 'margin: 0 0 2px 15px',
                    listeners: {
                        'check': function(radio, booChecked) {
                            if (booChecked) {
                                newCaseIACombo.clearInvalid();
                                newCaseIACombo.setDisabled(true);
                                newCaseIACombo.setVisible(false);
                            }
                        }
                    }
                }, {
                    xtype: 'container',
                    layout: 'column',
                    height: 38,
                    width: 600,
                    items: [
                        {
                            xtype: 'container',
                            style: 'margin-top: 7px',
                            items:  {
                                xtype: 'radio',
                                boxLabel: _('An <b>Existing</b> Applicant') + help,
                                name: 'adding-case-to',
                                inputValue: 'existing-client',
                                width: 210,
                                listeners: {
                                    'check': function (radio, booChecked) {
                                        if (booChecked) {
                                            newCaseIACombo.clearInvalid();
                                            newCaseIACombo.setDisabled(false);
                                            newCaseIACombo.setVisible(true);
                                        }
                                    }
                                }
                            }
                        },

                        newCaseIACombo
                    ]
                }
            ]
        });

        var win = new Ext.Window({
            title: '<i class="las la-plus"></i>' + _('New Case'),
            layout: 'form',
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,
            items: newClientIAContainer,

            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: _('Next') + ' ' + '<i class="las la-arrow-right"></i>',
                    cls: 'orange-btn',
                    handler: function () {
                        var arrRadios = newClientIAContainer.find('name', 'adding-case-to');
                        if (!arrRadios.length) {
                            return;
                        }

                        var booCloseCurrentDialog = false;
                        var radio = arrRadios[0];
                        var oTabPanel = Ext.getCmp(thisWindow.panelType + '-tab-panel');
                        if (radio.getValue() && radio.getRawValue() == 'new-client') {
                            oTabPanel.openApplicantTab({
                                applicantId: 0,
                                applicantName: '',
                                memberType: 'client',
                                newClientForceTo: 'individual',
                                caseId: 0,
                                caseName: 'Case 1',
                                caseType: '',
                                caseIdLinkedTo: thisWindow.caseId,
                                caseEmployerId: null,
                                caseEmployerName: null,
                                booHideNewClientType: true,
                                showOnlyCaseTypes: 'individual'
                            });
                            booCloseCurrentDialog = true;
                        } else {
                            if (newCaseIACombo.isValid()) {
                                var rec = newCaseIACombo.getStore().getById(newCaseIACombo.getValue());
                                if (rec) {
                                    oTabPanel.openApplicantTab({
                                        applicantId: rec.data.user_id,
                                        applicantName: rec.data.user_name,
                                        memberType: rec.data.user_type,
                                        caseId: 0,
                                        caseName: 'Case 1',
                                        caseType: '',
                                        caseIdLinkedTo: thisWindow.caseId,
                                        caseEmployerId: null,
                                        caseEmployerName: null,
                                        showOnlyCaseTypes: 'individual'
                                    }, 'case_details');
                                    booCloseCurrentDialog = true;
                                }
                            }
                        }

                        // Close current dialog only when we can do this
                        if (booCloseCurrentDialog) {
                            setTimeout(function() {
                                win.close();
                            }, 50);
                        }
                    }
                }
            ]
        });

        win.show();
        win.center();

        this.close();
    }
});