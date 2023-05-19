var AccountingPanel = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    // This will be used as an indicator to reload/refresh the Accounting tab on tab activation
    this.refreshThisAccountingPanel = false;

    this.arrCaseDateFields = [];
    this.arrCaseStatusFields = [];
    this.arrMemberTA = [];
    this.caseEmail = null;
    this.primaryTAId = null;
    this.secondaryTAId = null;
    this.primaryCurrency = null;
    this.booCanEditClient = false;
    this.switchTAMode = [];

    this.caseAccountingToolbar = null;
    this.arrFeesGrids = [];
    this.caseInvoicesGrid = null;

    AccountingPanel.superclass.constructor.call(this, {
        id: 'accounting_invoices_panel_' + config.caseId,
        style: 'padding: 12px 20px; min-height: ' + (initPanelSize() - 40) + 'px',
        items: []
    });

    this.on('afterrender', this.loadClientAccountingSettings.createDelegate(this));
};

Ext.extend(AccountingPanel, Ext.Panel, {
    hasAccessToAccounting: function (accessSection, accessRule) {
        var booHasAccess = false;
        if (typeof arrApplicantsSettings !== 'undefined') {
            booHasAccess = (typeof arrApplicantsSettings['access']['accounting'][accessSection][accessRule] !== 'undefined' && arrApplicantsSettings['access']['accounting'][accessSection][accessRule]);
        }

        return booHasAccess;
    },

    getTAInfo: function (ta_id, booReverse) {
        var thisPanel = this;
        for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
            if (booReverse) {
                if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] != ta_id && !empty(arrApplicantsSettings.accounting.arrCompanyTA[i][0])) {
                    return arrApplicantsSettings.accounting.arrCompanyTA[i];
                }
            } else {
                if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] == ta_id) {
                    return arrApplicantsSettings.accounting.arrCompanyTA[i];
                }
            }
        }

        // Can't be here, but...
        return [];
    },

    getCurrencySymbolByTAId: function (ta_id, booReverse) {
        var thisPanel = this;
        for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
            if (booReverse) {
                if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] != ta_id && !empty(arrApplicantsSettings.accounting.arrCompanyTA[i][0])) {
                    return arrApplicantsSettings.accounting.arrCompanyTA[i][3];
                }
            } else {
                if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] == ta_id) {
                    return arrApplicantsSettings.accounting.arrCompanyTA[i][3];
                }
            }
        }

        // Can't be here, but...
        return '';
    },

    getCurrencyByTAId: function (ta_id) {
        var thisPanel = this;
        for (var i = 0; i < arrApplicantsSettings.accounting.arrCompanyTA.length; i++) {
            if (arrApplicantsSettings.accounting.arrCompanyTA[i][0] == ta_id) {
                return arrApplicantsSettings.accounting.arrCompanyTA[i][2];
            }
        }

        // Can't be here, but...
        return '';
    },

    loadClientAccountingSettings: function () {
        var thisPanel = this;
        Ext.getBody().mask(_('Loading...'));

        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/get-case-accounting-settings',
            params: {
                caseId: Ext.encode(thisPanel.caseId)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    Ext.getBody().unmask();

                    thisPanel.caseEmail = resultData.caseEmail;
                    thisPanel.arrCaseDateFields = resultData.arrCaseDateFields;
                    thisPanel.arrCaseStatusFields = resultData.arrCaseStatusFields;
                    thisPanel.arrMemberTA = resultData.arrMemberTA;
                    thisPanel.primaryTAId = resultData.primaryTAId;
                    thisPanel.secondaryTAId = resultData.secondaryTAId;
                    thisPanel.primaryCurrency = resultData.primaryCurrency;
                    thisPanel.booCanEditClient = resultData.booCanEditClient;
                    thisPanel.switchTAMode = resultData.switchTAMode;

                    if (empty(resultData.arrMemberTA.length)) {
                        thisPanel.showPleaseCreateTA();
                    } else {
                        thisPanel.showClientAccountingBlocks();
                    }
                } else {
                    // Close the current tab
                    thisPanel.owner.remove(thisPanel);
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Information was not loaded. Please try again later.'));
                Ext.getBody().unmask();
            }
        });
    },

    showClientAccountingBlocks: function () {
        var thisPanel = this;


        thisPanel.caseAccountingToolbar = new AccountingToolbar({
            caseId: thisPanel.caseId,
            caseEmail: thisPanel.caseEmail,
            switchTAMode: thisPanel.switchTAMode
        }, thisPanel);

        thisPanel.add(thisPanel.caseAccountingToolbar);

        thisPanel.arrFeesGrids = [];
        Ext.each(thisPanel.arrMemberTA, function (oClientTADetails) {
            var feesGrid = new AccountingFeesGrid({
                caseId: thisPanel.caseId,
                caseTAId: oClientTADetails.id,
                caseTACurrency: thisPanel.getCurrencyByTAId(oClientTADetails.id),
                caseTACurrencySymbol: thisPanel.getCurrencySymbolByTAId(oClientTADetails.id),
                booCanEditClient: thisPanel.booCanEditClient
            }, thisPanel);

            thisPanel.add({
                xtype: 'container',
                cls: 'accounting_header',
                items: feesGrid
            });

            thisPanel.arrFeesGrids.push(feesGrid);
        });

        thisPanel.caseInvoicesGrid = new AccountingInvoicesGrid({
            caseId: thisPanel.caseId,
            booCanEditClient: thisPanel.booCanEditClient
        }, thisPanel);

        thisPanel.add({
            xtype: 'container',
            cls: 'accounting_header',
            items: thisPanel.caseInvoicesGrid
        });

        thisPanel.doLayout();
    },

    showPleaseCreateTA: function () {
        var thisPanel = this;
        var noTAMessage = '';

        // Show different message in relation to the situation / access rights
        if (arrApplicantsSettings.accounting.arrCompanyTA.length <= 1) {
            // The first option is "Not used for this case"
            if (thisPanel.hasAccessToAccounting('general', 'can_add_ta')) {
                noTAMessage = String.format(
                    _('You don\'t have any {0} defined.<br/>') +
                    _('You can add a {0} for your company in the <a href="#" class="blulinkunb" onClick="addAdminPage(); return false;">Admin tab</a>.'),
                    arrApplicantsSettings.ta_label
                );
            } else {
                noTAMessage = String.format(
                    _('No {0} is defined for your company.<br/>The Admin of your company can add {0} to the system.'),
                    arrApplicantsSettings.ta_label
                );
            }
        } else {
            if (is_client) {
                noTAMessage = _('No entries on Accounting available.');
            } else {
                noTAMessage = [
                    {
                        html: String.format(
                            _('Please assign {0} to this Case by clicking '),
                            arrApplicantsSettings.ta_label
                        )
                    }, {
                        xtype: 'box',
                        'autoEl': {
                            'tag': 'a',
                            'href': '#',
                            'style': 'padding-left: 5px',
                            'class': 'blulinkun12',
                            'html': _('here')
                        }, // Thanks to IE - we need to use quotes...

                        listeners: {
                            scope: this,
                            render: function (c) {
                                c.getEl().on('click', function () {
                                    thisPanel.showAssignTADialog();
                                }, this, {stopEvent: true});
                            }
                        }
                    }
                ];

                thisPanel.showAssignTADialog();
            }
        }

        if (typeof noTAMessage === 'string') {
            noTAMessage = {
                html: noTAMessage
            }
        }

        thisPanel.add({
            xtype: 'container',
            cls: 'no_ta_created_container',
            layout: 'column',
            items: noTAMessage
        });

        thisPanel.doLayout();
    },

    showAssignTADialog: function (title) {
        var win = new AssignAccountDialog({
            title: empty(title) ? '<i class="las la-tools"></i>' + _('Add ') + arrApplicantsSettings.ta_label : title,
            caseId: this.caseId,
            primaryTAId: this.primaryTAId,
            secondaryTAId: this.secondaryTAId,
            primaryCurrency: this.primaryCurrency,
            secondaryTACurrency: this.getCurrencyByTAId(this.secondaryTAId),
            switchTAMode: this.switchTAMode
        }, this);
        win.show();
        win.center();
    },

    // Reload Invoices grid if exists
    reloadInvoicesList: function () {
        if (this.caseInvoicesGrid) {
            this.caseInvoicesGrid.reloadInvoicesList();
        }
    },

    // Reload all grids
    reloadClientAccounting: function () {
        var thisPanel = this;

        // Identify if the Accounting tab is currently active (and visible)
        // If it is not - we just mark the tab to reload the data after it will be activated
        var booActiveAccountingTab = false;
        if (Ext.getCmp('main-tab-panel').getActiveTab().id === 'applicants-tab') {
            var tab = thisPanel.owner.getActiveTab();
            if (tab.tabIdentity == 'accounting') {
                booActiveAccountingTab = true;
            }
        }

        if (booActiveAccountingTab) {
            thisPanel.reloadInvoicesList();

            Ext.each(thisPanel.arrFeesGrids, function (oFeesGrid) {
                oFeesGrid.reloadFeesList();
            });
        } else {
            thisPanel.refreshThisAccountingPanel = 'reload';
        }
    },

    // Remove all grids and create from scratch
    refreshAccountingTab: function () {
        var thisPanel = this;
        var tab = thisPanel.owner.getActiveTab();
        if (tab.tabIdentity == 'accounting') {
            thisPanel.removeAll();
            thisPanel.loadClientAccountingSettings();
        } else {
            thisPanel.refreshThisAccountingPanel = 'refresh';
        }
    },

    makeReadOnlyAccountingTab: function () {
        this.caseAccountingToolbar.makeReadOnlyAccountingToolbar();
        this.caseInvoicesGrid.makeReadOnlyAccountingInvoicesGrid();

        Ext.each(this.arrFeesGrids, function(oFeesGrid) {
            oFeesGrid.makeReadOnlyAccountingFeesGrid();
        });
    }
});