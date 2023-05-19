var AccountingToolbar = function (config, owner) {
    var thisToolbar = this;

    this.owner = owner;
    Ext.apply(this, config);

    var $booShowEditTALink = owner.hasAccessToAccounting('general', 'change_currency');
    var $booShowEmailAccounting = owner.hasAccessToAccounting('general', 'email_accounting');
    var $booShowPrintLink = owner.hasAccessToAccounting('general', 'print');
    var $booShowTopToolbar = $booShowEditTALink || $booShowEmailAccounting || $booShowPrintLink;

    AccountingToolbar.superclass.constructor.call(this, {
        enableOverflow: true,
        hidden: !$booShowTopToolbar,

        items: [
            '->',
            {
                text: '<i class="las la-tools"></i>' + arrApplicantsSettings.ta_label + _(' Settings'),
                hidden: !$booShowEditTALink,
                ref: 'ChangeTAAccountingBtn',
                handler: function () {
                    thisToolbar.owner.showAssignTADialog(this.text);
                }
            }, {
                text: '<i class="lar la-envelope"></i>' + _('Email Accounting Summary'),
                hidden: is_client || !$booShowEmailAccounting,
                ref: 'EmailCaseAccountingBtn',
                handler: thisToolbar.emailAccounting.createDelegate(this)
            }, {
                text: '<i class="las la-print"></i>' + _('Print'),
                tooltip: _("Print client's Accounting Summary."),
                hidden: !$booShowPrintLink,
                handler: thisToolbar.printAccounting.createDelegate(this)
            }, '|', {
                xtype:   'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden:  !allowedPages.has('help'),
                handler: function () {
                    showHelpContextMenu(this.getEl(), 'clients-accounting');
                }
            }
        ]
    });
};

Ext.extend(AccountingToolbar, Ext.Toolbar, {
    emailAccounting: function () {
        var thisToolbar = this;

        Ext.getBody().mask('Creating PDF file...');
        Ext.Ajax.request({
            url: baseUrl + '/clients/accounting/print',
            params: {
                member_id: thisToolbar.caseId,
                destination: 'F'
            },
            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result) {
                    show_email_dialog({
                        member_id: thisToolbar.caseId,
                        email: thisToolbar.caseEmail,
                        attach: [
                            {
                                name: result.filename,
                                link: baseUrl + '/templates/index/view-pdf?file=' + escape(result.check_filename) + '&check_id=' + thisToolbar.caseId + '&check_type=member',
                                size: result.size,
                                path: result.path
                            }
                        ]
                    });
                } else {
                    Ext.simpleConfirmation.error('Can\'t create PDF file');
                }
            },
            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Can\'t create PDF file');
            }
        });
    },

    printAccounting: function () {
        window.open(baseUrl + '/clients/accounting/print?member_id=' + this.caseId + '&file=Accounting_Summary.pdf');
    },

    makeReadOnlyAccountingToolbar: function () {
        // Hide specific buttons in the toolbar
        this.ChangeTAAccountingBtn.setVisible(false);
        this.EmailCaseAccountingBtn.setVisible(false);
        this.ReportsAccountingBtn.setVisible(false);
    }
});