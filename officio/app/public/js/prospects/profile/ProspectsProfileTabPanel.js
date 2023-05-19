var ProspectsProfileTabPanel = function(config, owner) {
    var tabPanel = this;
    this.owner = owner;
    Ext.apply(this, config);

    var pid    = config.options.pid;
    var subtab = config.options.subtab;
    var tabId  = config.options.tabId;

    this.profileToolbar = null;

    // Tab template
    var subTabObj = function(title, subtab, callback) {
        return {
            title: title,
            id: tabId + '-sub-tab-' + subtab,
            tabId: tabId,
            subtab: subtab,
            cls: 'clients-sub-tab-panel-white',
            booRefreshOnActivate: false,
            autoLoad: {
                url: baseUrl + '/prospects/index/get-prospects-page',
                scripts: true,
                params: {
                    panelType: Ext.encode(tabPanel.panelType),
                    tab:       Ext.encode(config.options.tab),
                    tabId:     Ext.encode(config.options.tabId),
                    pid:       Ext.encode(pid),
                    subtab:    Ext.encode(subtab)
                },

                callback: function(options, success, response) {
                    if (success) {
                        if (empty(response.responseText)) {
                            Ext.simpleConfirmation.msg('Info', 'You have no access to this prospect.', 3000);
                            tabPanel.remove(tabId);
                            return;
                        }

                        //call special function for each sub tub
                        callback();

                        tabPanel.owner.fixParentPanelHeight(subtab == 'tasks');
                    } else {
                        Ext.simpleConfirmation.error('Can\'t receive prospects info');
                    }
                }
            },
            listeners: {
                activate: function(tab) {
                    if (tab.booRefreshOnActivate) {
                        tab.doAutoLoad();
                        tab.booRefreshOnActivate = false;
                    }

                    if (config.options.tab === 'new-prospect') {
                        tabPanel.hash = setUrlHash('#' + config.panelType + '/' + config.options.tab);
                    } else {
                        tabPanel.hash = setUrlHash('#' + config.panelType + '/prospect/' + pid + '/' + subtab);
                    }

                    // Check if we need show/hide buttons section
                    tabPanel.profileToolbar = initCustomProspectToolbar(tabPanel.panelType, tabPanel.options.pid);
                    if (tabPanel.profileToolbar) {
                        var booShowSection = false;
                        var parentTabPanel = Ext.getCmp(config.panelType + '-tab-panel');
                        Ext.each(parentTabPanel.arrProspectNavigationSubTabs, function (oSubTab) {
                            if (oSubTab.itemId == subtab) {
                                booShowSection = oSubTab.booWithToolbar;
                            }
                        });

                        tabPanel.profileToolbar.toggleThisToolbar(booShowSection);
                    }

                    // Update changed height
                    tabPanel.owner.fixParentPanelHeight();
                }
            }
        };
    };

    var arrTabs = [
        {
            title: '<i class="las la-arrow-left"></i>' + _('Back to ') + (config.panelType === 'marketplace' ? _('Marketplace') : _('Prospects')),
            tabIdentity: 'back_to_prospects'
        }
    ];

    //create tabs
    arrTabs.push(subTabObj('<i class="las la-user"></i>' + _('Profile'), 'profile', function() {
        initSelectFields();
        initNocField(tabId, config.panelType);
        initPostSecondariesField(tabId, config.panelType);
        initMarks();
        loadDatePicker();
        loadNumberField();
        initEditableComboDNA(config.panelType);
        initEditableCombo(config.panelType);
        initComboMultiple();

        initializeAllProspectMainFields(tabId, config.panelType);
    }));

    if (!empty(pid)) {
        arrTabs.push(subTabObj('<i class="las la-briefcase"></i>' + _('Occupations'), 'occupations', function () {
            initQnrSearch(pid, '', tabPanel.panelType);
            tabPanel.initResumeField();
            initSelectFields();
            loadDatePicker();
            initJobFieldsByTable(tabId, config.panelType);
            initSpouseJobSection(true, '#' + tabId, config.panelType);

            // Update changed height
            tabPanel.owner.fixParentPanelHeight();
        }));

        arrTabs.push(subTabObj('<i class="las la-money-bill-wave"></i>' + _('Business/Finance'), 'business', function () {
            initSelectFields();
            loadDatePicker();
            initAssessment('#' + tabId);
            initExperienceBusiness('#' + tabId, config.panelType);
        }));

        arrTabs.push(subTabObj('<i class="las la-list-alt"></i>' + _('Assessment'), 'assessment', function () {
            initSelectFields();
            loadDatePicker();
            initAssessmentFields(pid, '#' + tabId, config.panelType);
        }));

        if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.arrAccess[config.panelType].tabs.tasks.view) {
            arrTabs.push(subTabObj('<i class="las la-clipboard-check"></i>' + _('Tasks'), 'tasks', function () {
                if (typeof initProspectsTasks === 'function') {
                    initProspectsTasks(config.panelType, pid, config.options.taskId);
                }
            }));
        }

        if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.arrAccess[config.panelType].tabs.notes.view) {
            arrTabs.push(subTabObj('<i class="las la-sticky-note"></i>' + _('Notes'), 'notes', function () {
                if (typeof initProspectsNotes === 'function') {
                    initProspectsNotes(config.panelType, pid);
                }
            }));
        }

        if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.arrAccess[config.panelType].tabs.documents.view) {
            arrTabs.push(subTabObj('<i class="las la-folder"></i>' + _('Documents'), 'documents', function () {
                if (typeof initProspectsDocuments === 'function') {
                    initProspectsDocuments(config.panelType, pid, tabPanel);
                }
            }));
        }
    }

    // Set correctly tab, in relation to the url hash
    var activeTabNumber = 1;
    Ext.each(arrTabs, function (oTab, index) {
        if (tabId + '-sub-tab-' + subtab == oTab.id) {
            activeTabNumber = index;
        }
    });

    ProspectsProfileTabPanel.superclass.constructor.call(this, {
        id: config.panelType + '-sub-tab-panel-' + pid,
        hideMode: 'visibility',
        width: owner.getWidth(),
        plain: true,
        enableTabScroll: true,
        height: initPanelSize(),
        defaults: {
            autoScroll: true
        },
        cls: 'clients-sub-tab-panel clients-sub-tab-panel-white',
        headerCfg: {
            cls: 'clients-sub-tab-header x-tab-panel-header x-vr-tab-panel-header'
        },
        activeTab: activeTabNumber,
        items: arrTabs,

        listeners: {
            'beforetabchange': function (oTabPanel, newTab) {
                if (newTab.tabIdentity === 'back_to_prospects') {
                    var oTabPanel = Ext.getCmp(config.panelType + '-tab-panel');
                    if (oTabPanel) {
                        // Switch to the main tab
                        oTabPanel.setActiveTab(1);
                    }

                    return false;
                }
            }
        }
    });

    this.on('afterrender', this.onTabAfterRender, this);
};

Ext.extend(ProspectsProfileTabPanel, Ext.ux.VerticalTabPanel, {
    onTabAfterRender: function() {
        var thisTabPanel = this;
        if (empty(thisTabPanel.options.title)) {
            Ext.Ajax.request({
                url:     baseUrl + "/prospects/index/get-prospect-title",
                params: {
                    prospectId: thisTabPanel.options.pid,
                    panelType: thisTabPanel.panelType
                },

                success: function (f) {
                    if (!empty(f.responseText)) {
                        thisTabPanel.options.title = f.responseText;
                        thisTabPanel.owner.getActiveTab().setTitle(thisTabPanel.options.title);
                        thisTabPanel.owner.fireEvent('titlechange', thisTabPanel.owner);
                    }
                }
            });
        } else {
            thisTabPanel.owner.getActiveTab().setTitle(thisTabPanel.options.title);
        }
    },

    initResumeField: function () {

        var downloadLink = $('.form-resume a[data-rel=download]');

        // Remove click handler previously attached using .on()
        downloadLink.off('click');

        // show field to upload resume
        $(document).on('click', '.form-resume input[type=button]', function(){

            var parent = $(this).closest('.form-resume');

            parent.find('.form-resume-view').hide();
            parent.find('.form-resume-edit').show();

            // cancel button
            $('.form-resume a[data-rel=cancel]').click(function(){
                parent.find('input[type=file]').val('');
                parent.find('.form-resume-edit').hide();
                parent.find('.form-resume-view').show();
            });

            return false;
        });

        // remove button
        $(document).on('click', '.form-resume a[data-rel=remove]', function(){

            var parent = $(this).closest('.form-resume');
            var action = $(this).attr('href');

            if(action.length === 0) {
                return false;
            }

            Ext.Msg.show({
                title: 'Please confirm',
                msg: 'Are you sure you want to remove this resume?',
                buttons: {yes: 'Yes', no: 'Cancel'},
                minWidth: 300,
                modal: true,
                icon: Ext.MessageBox.WARNING,
                fn: function(btn) {
                    if (btn == 'yes') {

                        // send request to remove picture in "silent" mode
                        Ext.Ajax.request({
                            url: action,
                            failure: function(){

                                Ext.simpleConfirmation.error('Can\'t remove resume: internal error');

                                parent.find('.form-resume-view').show();
                                parent.find('.form-resume-edit').hide();
                                parent.find('.form-resume-edit a[data-rel=cancel]').show();
                            }
                        });

                        parent.find('.form-resume-view').hide();
                        parent.find('.form-resume-edit').show();
                        parent.find('.form-resume-edit a[data-rel=cancel]').hide();
                    }
                }
            });

        });

        // download link
        downloadLink.on('click', function() {
            var action = $(this).attr('href');

            if(action.length === 0) {
                return false;
            }
            window.open(action);

        });
    },

    switchToSpecificTab: function(subtab) {
        var tab = Ext.getCmp(this.options.tabId + '-sub-tab-' + subtab);

        if (tab) {
            tab.show();
        }
    }
});
