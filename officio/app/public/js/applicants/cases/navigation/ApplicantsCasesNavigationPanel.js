var ApplicantsCasesNavigationPanel = function(config, owner) {
    var thisPanel = this;
    Ext.apply(this, config);
    this.owner = owner;

    this.autoRefreshCasesList = true;

    this.ApplicantsCasesNavigationGrid = new ApplicantsCasesNavigationGrid({
        autoWidth:     true,
        height:        config.applicantsCasesNavigationGridHeight,
        caseId:        config.caseId,
        applicantId:   config.applicantId,
        applicantName: config.applicantName,
        memberType:    config.memberType
    }, thisPanel);

    this.activeCasesCheckboxId = Ext.id();

    // By default, checkbox will be checked
    var booChecked = true;

    // Load checked state from cookies
    var cookieName = 'client_profile_active_cases_checkbox';
    var savedInCookie = Ext.state.Manager.get(cookieName);
    if (!config.booHiddenCheckbox && typeof savedInCookie != 'undefined') {
        booChecked = savedInCookie;
    }

    this.addNewCaseButton = new Ext.Button({
        cls: 'blue-btn',
        width: 130,
        style: 'margin-left: 35px;',
        text: '<i class="las la-plus"></i>' + _('New Case'),
        tooltip: _("Add a new case to a particular client's file."),
        tooltipType: 'title',
        hidden: config.booHideNewCaseButton,

        handler: function () {
            owner.openNewCaseTab();
        }
    });

    ApplicantsCasesNavigationPanel.superclass.constructor.call(this, {
        cls: 'no-borders-panel',

        items: [
            {
                xtype: 'container',
                layout: 'column',
                items: [
                    {
                        xtype: 'container',
                        style: 'padding-top: 7px',
                        hidden: config.booHiddenCheckbox,

                        items: {
                            id: this.activeCasesCheckboxId,
                            xtype: 'checkbox',
                            checked: booChecked,
                            boxLabel: _('Active cases only'),
                            style: 'vertical-align: middle',

                            listeners: {
                                'check': function (checkbox, checked) {
                                    Ext.state.Manager.set(cookieName, checked);
                                    thisPanel.refreshCasesList();
                                }
                            }
                        }
                    }
                ]
            },
            this.addNewCaseButton,
            this.ApplicantsCasesNavigationGrid
        ]
    });

    this.on('show', this.onPanelShow.createDelegate(this));
};

Ext.extend(ApplicantsCasesNavigationPanel, Ext.Panel, {
    onPanelShow: function() {
        if (this.autoRefreshCasesList) {
            this.refreshCasesList();
            this.autoRefreshCasesList = false;
        }
    },

    refreshCasesList: function() {
        this.ApplicantsCasesNavigationGrid.getStore().load();
    }
});

Ext.reg('ApplicantsCasesNavigationPanel', ApplicantsCasesNavigationPanel);