var ApplicantsProfileTabPanel = function(config, owner) {
    var thisTabPanel = this;
    this.owner = owner;
    Ext.apply(this, config);

    var oTabPanel = Ext.getCmp(config.panelType + '-tab-panel');
    var queueTab = oTabPanel.getItem(config.panelType + '_queue_tab');
    var booshowQueueTab = oTabPanel && queueTab;

    var arrTabs = [
        {
            title: !booshowQueueTab ? '' : '<i class="las la-arrow-left"></i>' + _('Back to ') + (config.panelType == 'applicants' ? _('Clients') : _('Contacts')),
            tabIdentity: 'back_to_clients'
        }
    ];
    this.booCanEdit = true;

    var booShowBothEmployerAndIndividualTabs = false;
    var parentClientId = config.applicantId;
    var parentClientName = config.applicantName;
    if (config.memberType !== 'contact' && !empty(config.applicantId) && !empty(config.caseEmployerId) && config.caseEmployerId != config.applicantId) {
        // Force to show both IA and Employer tabs
        config.memberType = 'employer';
        parentClientId = config.caseEmployerId;
        parentClientName = config.caseEmployerName;
        booShowBothEmployerAndIndividualTabs = true;
    }

    var title = config.memberType === 'employer' ? _('Employer') : _('Profile');

    this.applicantsProfileForm = new ApplicantsProfileForm({
        caseId:               config.caseId,
        caseType:             config.caseType,
        caseEmployerId:       config.caseEmployerId,
        caseEmployerName:     config.caseEmployerName,
        caseIdLinkedTo:       config.caseIdLinkedTo,
        applicantId:          parentClientId,
        applicantName:        parentClientName,
        memberType:           config.memberType,
        panelType:            config.panelType,
        booLoadCaseInfoOnly:  false,
        newClientForceTo:     config.newClientForceTo,
        booHideNewClientType: config.booHideNewClientType,
        showOnlyCaseTypes:    config.showOnlyCaseTypes
    }, thisTabPanel);

    arrTabs.push({
        title: '<i class="las la-user"></i>' + title,
        tabIdentity: 'profile',
        bodyStyle: 'padding: 17px 20px;',
        items: this.applicantsProfileForm,
        listeners: {
            'activate': function (tab) {
                thisTabPanel.unhideTabStripItem(tab);
                thisTabPanel.removeClass('strip-hidden-no-border');

                thisTabPanel.applicantsProfileForm.profileToolbar.toggleThisToolbar(true);
                if (!thisTabPanel.booCanEdit) {
                    thisTabPanel.applicantsProfileForm.makeReadOnlyClient();
                }
            },

            'deactivate': function() {
                thisTabPanel.applicantsProfileForm.profileToolbar.toggleThisToolbar(false);
            }
        }
    });

    this.individualProfileForm = null;
    if (booShowBothEmployerAndIndividualTabs) {
        this.individualProfileForm = new ApplicantsProfileForm({
            caseId:               config.caseId,
            caseType:             config.caseType,
            caseEmployerId:       config.caseEmployerId,
            caseEmployerName:     config.caseEmployerName,
            caseIdLinkedTo:       config.caseIdLinkedTo,
            applicantId:          config.applicantId,
            applicantName:        config.applicantName,
            memberType:           'individual',
            panelType:            config.panelType,
            booLoadCaseInfoOnly:  false,
            newClientForceTo:     config.newClientForceTo,
            booHideNewClientType: config.booHideNewClientType,
            showOnlyCaseTypes:    config.showOnlyCaseTypes
        }, thisTabPanel);


        arrTabs.push({
            title: '<i class="las la-user"></i>' + _('Employee'),
            tabIdentity: 'profile',
            bodyStyle: 'padding: 17px 20px;',
            items: this.individualProfileForm,
            listeners: {
                'activate': function (tab) {
                    thisTabPanel.unhideTabStripItem(tab);
                    thisTabPanel.removeClass('strip-hidden-no-border');

                    thisTabPanel.individualProfileForm.profileToolbar.toggleThisToolbar(true);
                    if (!thisTabPanel.booCanEdit) {
                        thisTabPanel.individualProfileForm.makeReadOnlyClient();
                    }
                },

                'deactivate': function() {
                    thisTabPanel.individualProfileForm.profileToolbar.toggleThisToolbar(false);
                }
            }
        });
    }


    if (config.memberType != 'contact' && !empty(config.applicantId) && typeof config.caseId != 'undefined') {
        this.caseProfileForm =  new ApplicantsProfileForm({
                caseId:              config.caseId,
                caseType:            config.caseType,
                caseEmployerId:      config.caseEmployerId,
                caseEmployerName:    config.caseEmployerName,
                caseIdLinkedTo:      config.caseIdLinkedTo,
                applicantId:         config.applicantId,
                applicantName:       config.applicantName,
                memberType:          'case',
                panelType:           config.panelType,
                booLoadCaseInfoOnly: true,
                newClientForceTo:     config.newClientForceTo,
                booHideNewClientType: config.booHideNewClientType,
                showOnlyCaseTypes:    config.showOnlyCaseTypes
            }, thisTabPanel);

        arrTabs.push({
            title: '<i class="las la-id-card"></i>' + _('Case Details'),
            tabIdentity: 'case_details',
            bodyStyle: 'padding: 17px 20px;',
            items: this.caseProfileForm,
            listeners: {
                'activate': function () {
                    if (!thisTabPanel.booCanEdit) {
                        thisTabPanel.caseProfileForm.makeReadOnlyClient();
                    }
                }
            }
        });

        if (allowedClientSubTabs.has('tasks')) {
            arrTabs.push({
                title: '<i class="las la-clipboard-check"></i>' + _('Tasks'),
                tabIdentity: 'tasks',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.clientId != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var hash = parseUrlHash(location.hash);
                            var taskId = hash[2] === 'tasks' && !empty(hash[3]) ? hash[3] : config.taskId;

                            var tasksPanel = new TasksPanel({
                                style: 'padding: 17px 0 17px 20px',
                                clientId: caseId,
                                taskId: taskId,
                                booProspect: false,
                                autoWidth: true,
                                height: initPanelSize()
                            });
                            this.add(tasksPanel);
                            this.doLayout();
                            this.booTabActivated = true;
                        }
                    }
                }
            });
        }

        if (allowedClientSubTabs.has('notes')) {
            arrTabs.push({
                title: '<i class="las la-sticky-note"></i>' + _('File Notes'),
                tabIdentity: 'notes',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.member_id != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var activitiesPanel = new ActivitiesGrid({
                                style:         'padding: 12px 0 12px 15px;',
                                member_id:     caseId,
                                userType:      is_client ? 'client' : 'user',
                                panelType:     config.panelType,
                                tabType:       'general',
                                applicantId:   config.applicantId,
                                storeAutoLoad: true,
                                notesOnPage:   20,
                                height:        initPanelSize(),
                            });

                            this.add(activitiesPanel);
                            this.doLayout();
                            this.booTabActivated = true;
                        }
                    }
                }

            });
        }

        if (allowedClientSubTabs.has('decision-rationale')) {
            arrTabs.push({
                title: '<i class="las la-comment"></i>' + _(decisionRationaleTabName),
                tabIdentity: 'decision-rationale',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.member_id != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var activitiesPanel = new ActivitiesGrid({
                                style: 'padding: 12px 0 12px 15px;',
                                member_id: caseId,
                                userType: is_client ? 'client' : 'user',
                                panelType: config.panelType,
                                tabType: 'draft',
                                applicantId: config.applicantId,
                                storeAutoLoad: true,
                                notesOnPage: 20,
                                height: initPanelSize(),
                            });

                            this.add(activitiesPanel);
                            this.doLayout();
                            this.booTabActivated = true;
                        }
                    }
                }

            });
        }

        if (allowedClientSubTabs.has('forms')) {
            arrTabs.push({
                title: '<i class="las la-file-signature"></i>' + _('Forms'),
                tabIdentity: 'forms',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.clientId != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            this.add({
                                clientId:  caseId,
                                xtype:     'panel',
                                layout:    'form',
                                autoWidth: true,
                                height:    initPanelSize(),
                                bodyStyle: 'padding: 12px 0 12px 15px;',
                                items:     new FormsGrid(caseId, true, thisTabPanel.panelType)
                            });
                            this.doLayout();
                            this.booTabActivated = true;
                        }

                        if (!thisTabPanel.booCanEdit) {
                            Ext.getCmp('forms-main-grid' + caseId).makeReadOnly();
                        }
                    }
                }
            });
        }

        if (allowedClientSubTabs.has('documents')) {
            arrTabs.push({
                title: '<i class="las la-folder"></i>' + _('Documents'),
                tabIdentity: 'documents',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.clientId != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var tabId = 'ctab-client-' + caseId + '-sub-tab-documents';
                            this.add({
                                id:          tabId,
                                clientId:    caseId,
                                panelType:   config.panelType,
                                applicantId: config.applicantId,
                                html:        '<div id="folders-tree-' + caseId + '"></div>'
                            });
                            this.doLayout();
                            showDocuments(caseId);
                            this.booTabActivated = true;
                            var elems = $('#folders-tree-' + caseId).find('.x-tab-panel-body');
                            var newFoldersMinHeight = initPanelSize() - 27;
                            elems.css('cssText', 'min-height:' + newFoldersMinHeight + 'px !important;');
                        }
                    }
                }
            });
        }

        if (allowedClientSubTabs.has('documents_checklist')) {
            arrTabs.push({
                title: '<i class="las la-list-alt"></i>' + _('Checklist'),
                tabIdentity: 'documents_checklist',
                booTabActivated: false,
                bodyStyle: 'padding: 12px 0 12px 15px;',
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.clientId != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var tabId = 'ctab-client-' + caseId + '-sub-tab-documents-checklist';

                            var checklistTree = new DocumentsChecklistTree({
                                id:          tabId,
                                clientId:    caseId,
                                panelType:   config.panelType,
                                applicantId: config.applicantId,
                                height: initPanelSize() - 30
                            });

                            this.add(checklistTree);
                            this.doLayout();

                            this.booTabActivated = true;
                        }
                    }
                }
            });
        }

        if (allowedClientSubTabs.has('accounting')) {
            arrTabs.push({
                title: '<i class="las la-money-bill-wave"></i>' + _('Accounting'),
                tabIdentity: 'accounting',
                booTabActivated: false,
                items: [],
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        if (this.booTabActivated) {
                            var clientAccountingPanel = Ext.getCmp('accounting_invoices_panel_' + caseId);
                            if (clientAccountingPanel) {
                                switch (clientAccountingPanel.refreshThisAccountingPanel) {
                                    case 'refresh':
                                        clientAccountingPanel.refreshAccountingTab();
                                        clientAccountingPanel.refreshThisAccountingPanel = false;
                                        break;

                                    case 'reload':
                                        clientAccountingPanel.reloadClientAccounting();
                                        clientAccountingPanel.refreshThisAccountingPanel = false;
                                        break;

                                    default:
                                        break;
                                }

                                if (!thisTabPanel.booCanEdit) {
                                    clientAccountingPanel.makeReadOnlyAccountingTab();
                                }
                            }

                            return;
                        }

                        var clientAccountingPanel = new AccountingPanel({
                            caseId: caseId,
                            autoWidth: true,
                            autoHeight: true
                        }, thisTabPanel);

                        this.add(clientAccountingPanel);
                        this.doLayout();
                        this.booTabActivated = true;
                    }
                }
            });
        }

        if (allowedClientSubTabs.has('time_tracker')) {
            arrTabs.push({
                title: '<i class="las la-stopwatch"></i>' + _('Time Log'),
                tabIdentity: 'time_tracker',
                booTabActivated: false,
                items: [],
                bodyStyle: 'padding: 17px 20px;',
                listeners: {
                    'activate': function (panel, params) {
                        var caseId = params && params.caseId ? params.caseId : owner.getActiveCaseId();
                        var currentTabPanel = this.items.first();

                        if (this.booTabActivated) {
                            if (currentTabPanel && currentTabPanel.clientId != caseId) {
                                this.removeAll();
                                this.booTabActivated = false;
                            }
                        }

                        if (!this.booTabActivated) {
                            var pan = new Ext.Panel({
                                items: [{
                                    html: '<div id="time-tracker-result-count-' + caseId + '"  style="float:left; font-weight: bold; font-size: 22px; padding: 4px 4px 0;"></div>'
                                }]
                            });

                            var timeTrackerPanel = new ClientTrackerPanel({
                                booCompanies: false,
                                clientId: caseId,
                                panelType: thisTabPanel.panelType,
                                autoWidth: true,
                                height: initPanelSize() - 60
                            });
                            this.add(timeTrackerPanel);
                            this.add(pan);

                            this.doLayout();
                            this.booTabActivated = true;
                        }
                    }
                }
            });
        }
    }

    if (config.memberType == 'employer' || config.memberType == 'individual' || is_client) {
        if (thisTabPanel.hasAccessTo(thisTabPanel.memberType, 'add')) {
            this.createNewCaseButtonContainerId = Ext.id();
            arrTabs.push({
                title: '<div id="' + this.createNewCaseButtonContainerId + '"></div>',
                disabled: true
            });
        } else {
            arrTabs.push({
                title: '<div style="border-top: 1px solid #FFF;"></div>',
                disabled: true
            });
        }

        var casesGrid = new ApplicantsCasesGrid({
            applicantId: empty(config.caseEmployerId) ? config.applicantId : config.caseEmployerId,
            applicantName: empty(config.caseEmployerId) ? config.applicantName : config.caseEmployerName,
            memberType: empty(config.caseEmployerId) ? config.memberType : 'employer',
            filterCaseLinkTo: config.filterCaseLinkTo,
            booTabActivated: false
        }, this);

        var oCasesTab = {
            title: '<i class="las la-link"></i>' + (config.memberType == 'employer' ? _("Employer's Cases") : _("Client's Cases")),
            tabIdentity: 'cases',
            items: casesGrid,
            listeners: {
                'show': function () {
                    casesGrid.casesGrid.fireEvent('show');
                },

                'activate': function () {
                    if (!casesGrid.booTabActivated) {
                        casesGrid.setCaseLinkedTo(empty(casesGrid.filterCaseLinkTo) ? 0 : casesGrid.filterCaseLinkTo);
                        casesGrid.booTabActivated = true;
                    }

                    if (!thisTabPanel.booCanEdit) {
                        casesGrid.makeReadOnly();
                    }
                }
            }
        };

        arrTabs.push(oCasesTab);
    }

    // Set correctly tab, in relation to the url hash
    var activeTabNumber = 1;
    Ext.each(arrTabs, function(oTab, index){
        if (thisTabPanel.activeTab == oTab.tabIdentity) {
            activeTabNumber = index;
        }
    });

    ApplicantsProfileTabPanel.superclass.constructor.call(this, {
        hideMode: 'visibility',
        width: owner.getWidth(),
        plain: true,
        enableTabScroll: true,
        height: initPanelSize(),
        timeTrackerTimeSpent: 0,
        defaults: {
            autoScroll: true
        },
        cls: 'clients-sub-tab-panel',
        headerCfg: {
            cls: 'clients-sub-tab-header x-tab-panel-header x-vr-tab-panel-header'
        },
        activeTab: activeTabNumber,
        items: arrTabs,

        listeners: {
            'beforetabchange': function (oPanel, newTab) {
                if (newTab.tabIdentity === 'back_to_clients') {
                    if (booshowQueueTab) {
                        // Switch to the queue tab
                        oTabPanel.setActiveTab(queueTab);
                    }

                    return false;
                }
            }
        }
    });

    this.on('tabchange', this.onTabChange, this);
    this.on('beforetabchange', this.onBeforeTabChange.createDelegate(this), this);
    this.on('beforedestroy', this.showTimeTrackerDialog.createDelegate(this, []), this);
    this.on('afterrender', this.switchApplicantHash.createDelegate(this), this);
    if (config.memberType != 'contact') {
        this.on('afterrender', this.renderAssignedCasesPanel.createDelegate(this), this);
    }
};

Ext.extend(ApplicantsProfileTabPanel, Ext.ux.VerticalTabPanel, {
    renderAssignedCasesPanel: function () {
        var thisTabPanel = this;

        // Don't show the checkbox temporary
        thisTabPanel.booHiddenCheckbox = true;

        // Generate the button first - later we want to calculate the height of the section
        if (!empty(thisTabPanel.createNewCaseButtonContainerId)) {
            new Ext.Button({
                renderTo: thisTabPanel.createNewCaseButtonContainerId,
                cls: 'blue-btn btn-always-pointer',
                width: 130,
                style: 'margin-left: 35px;',
                text: '<i class="las la-plus"></i>' + _('New Case'),
                tooltip: _('Add another case for this client.'),
                tooltipType: 'title',

                handler: function () {
                    if (empty(thisTabPanel.individualProfileForm)) {
                        // Only 1 Employer or 1 Individual profile is shown
                        oClientForm.openNewCaseTab();
                    } else {
                        // Both Employer and Individual tabs are visible
                        var employerRadio = new Ext.form.Radio({
                            boxLabel: _('Employer'),
                            inputValue: 'employer',
                            name: 'rb-col',
                            checked: true,
                            hideLabel: true
                        });

                        var individualRadio = new Ext.form.Radio({
                            boxLabel: _('Employee'),
                            inputValue: 'individual',
                            name: 'rb-col',
                            hideLabel: true
                        });

                        var wnd = new Ext.Window({
                            title: '<i class="las la-plus"></i>' + _('New Case'),
                            layout: 'form',
                            modal: true,
                            resizable: false,
                            plain: false,
                            autoHeight: true,
                            autoWidth: true,
                            labelAlign: 'top',
                            items: [
                                {
                                    xtype: 'container',
                                    width: 400,
                                    layout: 'column',
                                    style: 'margin: 20px',
                                    items: [
                                        {html: '<div style="margin: 2px 20px 0 0;">' + _('Add a New Case for:') + '</div>'},
                                        employerRadio,
                                        {html: '&nbsp;&nbsp;&nbsp;&nbsp;'},
                                        individualRadio
                                    ]
                                }
                            ],

                            buttons: [
                                {
                                    text: _('Cancel'),
                                    handler: function () {
                                        wnd.close();
                                    }
                                }, {
                                    text: _('Add'),
                                    cls: 'orange-btn',
                                    handler: function () {
                                        if (employerRadio.getValue()) {
                                            thisTabPanel.owner.openApplicantTab({
                                                applicantId: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantId : thisTabPanel.caseEmployerId,
                                                applicantName: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantName : thisTabPanel.caseEmployerName,
                                                memberType: 'employer',
                                                caseId: 0,
                                                caseName: _('Case 1'),
                                                caseType: '',
                                                caseEmployerId: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantId : thisTabPanel.caseEmployerId,
                                                caseEmployerName: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantName : thisTabPanel.caseEmployerName
                                            }, 'case_details');
                                        } else {
                                            thisTabPanel.owner.openApplicantTab({
                                                applicantId: thisTabPanel.applicantId,
                                                applicantName: thisTabPanel.applicantName,
                                                memberType: thisTabPanel.memberType,
                                                caseId: 0,
                                                caseName: _('Case 1'),
                                                caseType: '',
                                                caseEmployerId: null,
                                                caseEmployerName: null
                                            }, 'case_details');
                                        }

                                        wnd.close();
                                    }
                                }
                            ]
                        });

                        wnd.show();
                    }
                }
            });
        }

        var sectionHeight = initPanelSize() - $('#' + this.id + ' .clients-sub-tab-header').height() - 20;
        sectionHeight = Math.max(50, sectionHeight);

        var assignedCasesPanelId = Ext.id();
        $('#' + this.id + ' .clients-sub-tab-header .x-tab-strip-wrap').after(String.format(
            '<div id="{0}" style="height: {1}px; overflow-y: auto; margin: 10px 5px 10px 10px" class="assigned-cases-section"></div>',
            assignedCasesPanelId,
            sectionHeight
        ));

        var oClientForm = empty(thisTabPanel.caseProfileForm) ? thisTabPanel.applicantsProfileForm : thisTabPanel.caseProfileForm;
        thisTabPanel.applicantsCasesNavigationPanel = new ApplicantsCasesNavigationPanel({
            renderTo: assignedCasesPanelId,
            autoWidth: true,
            autoHeight: true,
            booHiddenCheckbox: true,
            applicantsCasesNavigationGridHeight: sectionHeight,
            booHideNewCaseButton: true,
            autoRefreshCasesList: true,
            caseId: thisTabPanel.caseId,
            applicantId: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantId : thisTabPanel.caseEmployerId,
            applicantName: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.applicantName : thisTabPanel.caseEmployerName,
            memberType: empty(thisTabPanel.caseEmployerId) ? thisTabPanel.memberType : 'employer'
        }, oClientForm);
    },

    hasAccessTo: function(section, action) {
        var booHasAccess = false;
        if (typeof arrApplicantsSettings != 'undefined' && typeof arrApplicantsSettings['access'][section] != 'undefined') {
            if (Array.isArray(arrApplicantsSettings['access'][section])) {
                booHasAccess = arrApplicantsSettings['access'][section].has(action);
            } else if(arrApplicantsSettings['access'][section].hasOwnProperty(action) && arrApplicantsSettings['access'][section][action]) {
                booHasAccess = true;
            }
        }

        return booHasAccess;
    },

    showTimeTrackerDialog: function () {
        // Stop the timer if was started and not stopped
        if (!empty(this.timeTrackerIntervalId)) {
            clearInterval(this.timeTrackerIntervalId);
            this.timeTrackerIntervalId = null;
        }

        if (this.ownerCt && this.ownerCt.really_deleted) {
            // This case was deleted, don't show the Time Tracker Dialog
            return;
        }

        var oClientForm = empty(this.caseProfileForm) ? this.applicantsProfileForm : this.caseProfileForm;
        var caseId = oClientForm.caseId;
        var caseName = oClientForm.getCurrentApplicantTabName();
        if (!empty(caseId) && typeof(arrTimeTrackerSettings) != 'undefined' && arrTimeTrackerSettings.access.has('show-popup') && arrTimeTrackerSettings.tracker_enable == 'Y' && !empty(this.timeTrackerTimeSpent)) {
            // Calculate difference in minutes
            var difference = Ext.util.Format.round(this.timeTrackerTimeSpent / 60, 0);

            var dialog = new ClientTrackerAddDialog({
                action: 'create',
                timeActual: difference < 1 ? 1 : difference,
                clientId: caseId,
                clientName: caseName
            });

            if (arrTimeTrackerSettings.disable_popup == 'Y') {
                dialog.addRecord(true);
            } else {
                dialog.show();
                dialog.center();
                dialog.syncShadow();
            }
        }
    },

    highlightTitles: function (oTab) {
        var arrTitles = oTab.query('.tab-title');
        Ext.each(arrTitles, function (theTitle) {
            // Prevent highlight several times
            var oTitle = Ext.get(theTitle);
            if (oTitle && !oTitle.hasActiveFx()) {
                oTitle.highlight('FF8432', {attr: 'color', duration: 1});
            }
        });
    },

    onBeforeTabChange: function (tabPanel, tab) {
        var booOpen = true;
        if (tabPanel.panelType == 'applicants' && !empty(tabPanel.applicantId) && typeof tabPanel.caseId != 'undefined' && empty(tabPanel.caseType)) {
            // For the new case - only "case details" tab is allowed
            var arrAllowedTabs = ['case_details'];
            if (!empty(tabPanel.caseId)) {
                // For already created case - we allow to open Client's Profile and Client's Cases tabs
                arrAllowedTabs.push('profile');
                arrAllowedTabs.push('cases');
            }

            if (!arrAllowedTabs.has(tab.tabIdentity)) {
                Ext.simpleConfirmation.info(_('You have attempted to add a new case for this client.<br>Please select an Immigration Program before you can proceed.'));
                booOpen = false;

                setTimeout(function () {
                    tabPanel.setActiveTab(tabPanel.getSpecificTab('case_details'));
                }, 100);
            }
        }

        return booOpen;
    },

    onTabChange: function(tabpanel, tab) {
        this.switchApplicantHash();

        // Update changed height
        this.owner.fixParentPanelHeight();
    },

    switchApplicantHash: function() {
        if (!empty(this.applicantId)) {
            var tab = this.owner.getActiveTab();
            var subTab = this.getActiveTab();
            if (subTab) {
                tab.hash = '#' + this.panelType + '/' + this.applicantId + '/' + subTab.initialConfig.tabIdentity;
                setUrlHash(tab.hash);
            }
        }
    },

    switchToFirstTab: function() {
        this.items.first().show();
    },

    getSpecificTab: function(activeTab) {
        var tab = null;
        this.items.each(function(item) {
            if (item.tabIdentity == activeTab) {
                tab = item;
            }
        });

        return tab;
    },

    switchToSpecificTab: function(activeTab) {
        var tab = this.getSpecificTab(activeTab);

        if (tab) {
            tab.show();
        }
    }
});

Ext.reg('ApplicantsProfileTabPanel', ApplicantsProfileTabPanel);