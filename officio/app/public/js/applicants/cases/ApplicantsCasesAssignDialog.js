var ApplicantsCasesAssignDialog = function (config, owner) {
    config = config || {};
    Ext.apply(this, config);
    this.owner = owner;

    var thisWindow = this;

    thisWindow.linkToCasesCombo = new Ext.form.ComboBox({
        fieldLabel: _('Please select the ') + thisWindow.caseTypeLMIALabel,
        emptyText: _('Please select...'),
        width: 550,
        allowBlank: false,

        store: {
            xtype: 'store',
            autoLoad: true,
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/applicants/index/get-cases-list',
                method: 'post'
            }),

            baseParams: {
                parentMemberId: 0,
                booLimitCases: 1,
                booCategoryMustBeLinked: 0,
                exceptCaseId: config.caseId
            },

            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'clientId'},
                {name: 'caseName'},
                {name: 'clientName'},
                {name: 'clientFullName'}
            ]),

            // Allow filter 'any match values'
            filter: function (filters, value) {
                var escapeRegexRe = /([-.*+?\^${}()|\[\]\/\\])/g;
                Ext.data.Store.prototype.filter.apply(this, [
                    filters,
                    value ? new RegExp(value.replace(escapeRegexRe, "\\$1"), 'i') : value
                ]);
            }
        },

        itemSelector: 'div.x-combo-list-item',
        tpl: new Ext.XTemplate(
            '<tpl for=".">',
            '<tpl if="(this.clientName != values.clientName)">',
            '<tpl exec="this.clientName = values.clientName"></tpl>',
            '<h1 style="padding: 2px;">{clientName}</h1>',
            '</tpl>',

            '<div class="x-combo-list-item" style="padding-left: 20px;">{caseName}</div>',
            '</tpl>'
        ),

        mode: 'local',
        valueField: 'clientId',
        displayField: 'clientFullName',
        triggerAction: 'all',
        forceSelection: true,
        selectOnFocus: true,
        editable: true,
        disabled: true // Fix to mark as invalid, will be enabled later
    });
    thisWindow.linkToCasesCombo.getStore().on('load', this.checkLoadedData.createDelegate(this));

    var form = new Ext.form.FormPanel({
        labelWidth: 65,
        labelAlign: 'top',
        items: [
            thisWindow.linkToCasesCombo
        ]
    });

    ApplicantsCasesAssignDialog.superclass.constructor.call(this, {
        title: '<i class="las la-link"></i>' + _('Link to') + ' ' + thisWindow.caseTypeLMIALabel,
        buttonAlign: 'right',
        modal: true,
        autoWidth: true,
        autoHeight: true,

        items: form,

        buttons: [
            {
                text: _('Cancel'),
                handler: this.closeDialog.createDelegate(this)
            }, {
                text: '<i class="las la-link"></i>' + _('Link'),
                cls: 'orange-btn',
                handler: this.linkCaseToLMIACase.createDelegate(this)
            }
        ]
    });

    // We need this to prevent mark combo as invalid automatically
    this.on('show', function () {
        thisWindow.linkToCasesCombo.setDisabled(false);
    });
};

Ext.extend(ApplicantsCasesAssignDialog, Ext.Window, {
    closeDialog: function () {
        this.close();
    },

    showThisDialogCorrectly: function () {
        var win = this;
        win.show();
        win.center();
        win.setPosition(null, 160);
    },

    checkLoadedData: function (store) {
        // If there is only one option - automatically select it in the combo
        if (store.getCount() == 1) {
            var rec = store.getAt(0);
            this.linkToCasesCombo.setValue(rec.data[this.linkToCasesCombo.valueField]);
        }
    },

    linkCaseToLMIACase: function () {
        var thisWindow = this;

        if (thisWindow.linkToCasesCombo.isValid()) {
            var caseIdLinkTo = thisWindow.linkToCasesCombo.getValue();

            thisWindow.getEl().mask(_('Linking...'));
            Ext.Ajax.request({
                url: baseUrl + '/applicants/profile/link-case-to-employer',
                params: {
                    linkTo: 'lmia-case',
                    caseIdLinkFrom: Ext.encode(thisWindow.caseId),
                    caseIdLinkTo: Ext.encode(caseIdLinkTo)
                },
                success: function (f) {
                    var resultData = Ext.decode(f.responseText);
                    if (resultData.success) {
                        var thisTabPanel = thisWindow.owner.owner.owner.owner;

                        // Close this tab
                        var tab = thisTabPanel.getActiveTab();
                        thisTabPanel.remove(tab);

                        // Show a 'success' message + open the same tab
                        thisWindow.getEl().mask(resultData.msg);
                        setTimeout(function () {
                            thisTabPanel.openApplicantTab({
                                applicantId: resultData.applicantId,
                                applicantName: resultData.applicantName,
                                memberType: 'employer',
                                caseId: thisWindow.caseId,
                                caseName: resultData.caseName,
                                caseType: resultData.caseType,
                                caseEmployerId: resultData.employerId,
                                caseEmployerName: resultData.employerName
                            }, 'case_details');

                            thisWindow.getEl().unmask();

                            thisWindow.closeDialog();
                        }, 750);
                    } else {
                        Ext.simpleConfirmation.error(resultData.msg);
                        thisWindow.getEl().unmask();
                    }
                },

                failure: function () {
                    Ext.simpleConfirmation.error(_('Information was not saved. Please try again later.'));
                    thisWindow.getEl().unmask();
                }
            });
        }
    }
});