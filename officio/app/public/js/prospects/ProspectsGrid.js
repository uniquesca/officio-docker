var ProspectsGrid = function (config, owner) {
    var thisGrid = this;
    Ext.apply(this, config);
    this.owner = owner;

    var recordsOnPage = 20;

    this.store = new Ext.data.Store({
        url: baseUrl + '/prospects/index/get-prospects-list',
        autoLoad: true,
        remoteSort: true,

        baseParams: {
            start: 0,
            limit: recordsOnPage,
            panelType: config.panelType,
            booLoadAllIds: config.panelType !== 'marketplace',
            type: thisGrid.initSettings.tab,
            offices: Ext.encode(thisGrid.initSettings.offices)
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'prospect_id'},
            {name: 'fName'},
            {name: 'fNameReadable'},
            {name: 'qf_country_of_citizenship'},
            {name: 'qf_country_of_residence'},
            {name: 'qf_area_of_interest'},
            {name: 'qf_job_title'},
            {name: 'mp_prospect_expiration_date', type: 'date', dateFormat: Date.patterns.ISO8601Short},
            {name: 'lName'},
            {name: 'email'},
            {name: 'viewed'},
            {name: 'qualified_as'},
            {name: 'qf_agent'},
            {name: 'qf_initial_interview_date', type: 'date', dateFormat: Date.patterns.ISO8601Short},
            {name: 'seriousness'},
            {name: 'qf_cat_net_worth'},
            {name: 'create_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
            {name: 'update_date', type: 'date', dateFormat: Date.patterns.ISO8601Long},
            {name: 'invited_on', type: 'date', dateFormat: Date.patterns.ISO8601Long},
            {name: 'email_sent'}
        ])),

        listeners: {
            'beforeload': function (store, options) {
                options.params = options.params || {};

                var params = {
                    advanced_search_fields: Ext.encode(thisGrid.getColumnIds())
                };

                Ext.apply(options.params, params);
            },

            load: function (res) {
                var msg = String.format(
                    res.totalLength === 1 ? _('Search result: {0} record found') : _('Search result: {0} records found'),
                    res.totalLength
                );
                thisGrid.SearchResultsCount.setValue(msg);
                thisGrid.SearchResultsCountLabel.setVisible(res.totalLength > 0);

                thisGrid.owner.updateProspectActiveFilter();

                setTimeout(function () {
                    thisGrid.owner.owner.fixParentPanelHeight();
                }, 100);
            }
        }
    });

    var help = String.format(
        "<i class='las la-info-circle' ext:qtip='{0}' style='cursor: help; font-size: 20px; padding-left: 5px; vertical-align: text-bottom'></i>",
        _("Identify each prospect's level of seriousness in becoming a client through each prospect's \"Profile\" section.").replaceAll("'", "&#39;")
    );

    // The list of all columns - to check if saved column is in the list
    var arrAllColumns = ['fName', 'lName', 'email', 'qf_country_of_citizenship', 'qf_country_of_residence', 'qf_area_of_interest', 'qualified_as', 'qf_agent', 'qf_initial_interview_date', 'qf_cat_net_worth', 'qf_job_title', 'seriousness', 'create_date', 'update_date', 'email_sent'];

    // The list of default columns - depends on if this is Prospects/Marketplace tab and CA or AU version
    var arrDefaultColumns = ['fName', 'lName', 'qualified_as', 'create_date', 'update_date', 'email_sent'];
    if (this.panelType === 'marketplace') {
        arrDefaultColumns.push('qf_country_of_citizenship');
        arrDefaultColumns.push('qf_area_of_interest');
    } else {
        arrDefaultColumns.push('email');
        arrDefaultColumns.push('seriousness');
    }

    if (site_version === 'australia') {
        arrDefaultColumns.push('qf_agent');
        arrDefaultColumns.push('qf_initial_interview_date');
    } else {
        arrDefaultColumns.push('qf_cat_net_worth');
        arrDefaultColumns.push('qf_job_title');
    }

    // Try to load previously saved columns from cookies/local storage
    var cookieName = this.panelType + '_grid_show_columns';
    var savedInCookie = Ext.state.Manager.get(cookieName);
    var arrShowColumns = [];
    if (!empty(savedInCookie)) {
        var booCorrect = true;
        Ext.each(savedInCookie, function (colId) {
            if (!arrAllColumns.has(colId)) {
                booCorrect = false;
            }
        });

        if (booCorrect) {
            arrShowColumns = savedInCookie;
        } else {
            arrShowColumns = arrDefaultColumns;
        }
    } else {
        arrShowColumns = arrDefaultColumns;
    }

    this.sm = new Ext.grid.CheckboxSelectionModel();
    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.sm,
            {
                header: _('First name'),
                dataIndex: 'fName',
                width: 120,
                hidden: !arrShowColumns.has('fName'),
                renderer: function (value, p, record) {
                    var today = new Date();
                    today.format('Y-m-d');
                    var booPremium = config.panelType === 'marketplace' && !empty(record.data.mp_prospect_expiration_date) && today <= record.data.mp_prospect_expiration_date;
                    return booPremium ? value + "<span class='premium-member' style='margin-left: 7px;' title='Premium Prospect' >&nbsp;P&nbsp;</span>" : value;
                }
            }, {
                header: _('Last Name'),
                dataIndex: 'lName',
                width: 120,
                hidden: !arrShowColumns.has('lName')
            }, {
                header: _('Email'),
                dataIndex: 'email',
                width: 150,
                hidden: !arrShowColumns.has('email')
            }, {
                header: _('Country of Citizenship'),
                dataIndex: 'qf_country_of_citizenship',
                width: 80,
                sortable: false,
                hidden: !arrShowColumns.has('qf_country_of_citizenship')
            }, {
                header: _('Country of Residence'),
                dataIndex: 'qf_country_of_residence',
                width: 100,
                sortable: false,
                hidden: !arrShowColumns.has('qf_country_of_residence')
            }, {
                header: _('Interested In'),
                dataIndex: 'qf_area_of_interest',
                sortable: false,
                width: 60,
                hidden: !arrShowColumns.has('qf_area_of_interest'),
                renderer: function (v) {
                    var exploded = v.split(', ');
                    var res = [];
                    Ext.each(exploded, function (item) {
                        switch (item) {
                            case 'Immigrate To Canada':
                                res.push('Imm');
                                break;

                            case 'Work in Canada':
                                res.push('Wrk');
                                break;

                            case 'Study in Canada':
                                res.push('Std');
                                break;

                            case 'Invest in Canada':
                                res.push('Inv');
                                break;

                            case 'Not sure':
                                res = [];
                                res.push('Imm');
                                res.push('Wrk');
                                res.push('Std');
                                res.push('Inv');
                                return;
                                break;

                            default:
                                res.push(v);
                                break;
                        }
                    });

                    return String.format(
                        '<span title="{0}">{1}</span>',
                        v,
                        res.join(', ')
                    );
                }
            }, {
                header: _('Qualified As'),
                dataIndex: 'qualified_as',
                sortable: false,
                width: 150,
                hidden: !arrShowColumns.has('qualified_as')
            }, {
                header: _('Sales Agent'),
                dataIndex: 'qf_agent',
                sortable: false,
                width: 120,
                hidden: !arrShowColumns.has('qf_agent')
            }, {
                header: _('Interview Date'),
                dataIndex: 'qf_initial_interview_date',
                sortable: false,
                width: 100,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                fixed: true,
                hidden: !arrShowColumns.has('qf_initial_interview_date')
            }, {
                header: _('Networth'),
                dataIndex: 'qf_cat_net_worth',
                width: 100,
                sortable: false,
                hidden: !arrShowColumns.has('qf_cat_net_worth'),
                renderer: function (v) {
                    var res = v;
                    switch (v) {
                        case '0 to 9,999':
                            res = '0-10k';
                            break;

                        case '10,000 to 24,999':
                            res = '10k-25k';
                            break;

                        case '25,000 to 49,999':
                            res = '25k-50k';
                            break;

                        case '50,000 to 99,999':
                            res = '50k-100k';
                            break;

                        case '100,000 to 299,999':
                            res = '100k-300k';
                            break;

                        case '300,000 to 499,999':
                            res = '300k-500k';
                            break;

                        case '500,000 to 799,999':
                            res = '500k-800k';
                            break;

                        case '800,000 to 999,999':
                            res = '800k-1M';
                            break;

                        case '1,000,000 to 1,599,999':
                            res = '1M-1.6M';
                            break;

                        case '1,600,000+':
                            res = '1.6M+';
                            break;

                        case 'Prefer not to disclose':
                            res = '<span title="' + v + '">NS</span>';
                            break;

                        default:
                            res = v;
                            break;
                    }
                    return res;
                }
            }, {
                header: _('Job Title'),
                dataIndex: 'qf_job_title',
                width: 85,
                hidden: !arrShowColumns.has('qf_job_title')
            }, {
                header: _('Seriousness') + help,
                dataIndex: 'seriousness',
                align: 'center',
                width: 120,
                hidden: !arrShowColumns.has('seriousness')
            }, {
                header: _('Created On'),
                dataIndex: 'create_date',
                width: 120,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                fixed: true,
                hidden: !arrShowColumns.has('create_date')
            }, {
                header: _('Updated On'),
                dataIndex: 'update_date',
                width: 120,
                renderer: Ext.util.Format.dateRenderer(dateFormatFull),
                fixed: true,
                hidden: !arrShowColumns.has('update_date')
            }, {
                header: _('Email sent'),
                dataIndex: 'email_sent',
                width: 100,
                align: 'center',
                fixed: true,
                hidden: !arrShowColumns.has('email_sent'),
                renderer: function (v) {
                    return v === 'Y' ? '<i class="las la-envelope-open" title="Yep, email was sent!"></i>' : '';
                }
            }
        ],
        defaultSortable: true
    });

    this.cm.on('hiddenchange', function () {
        Ext.state.Manager.set(cookieName, this.getColumnIds());
    }, this);

    this.bbar = [
        {
            xtype: 'paging',
            pageSize: recordsOnPage,
            store: thisGrid.store
        }, '->',
        {
            xtype: 'displayfield',
            ref: '../SearchResultsCount',
            style: 'font-weight: bold; font-size: 12px; margin-top: 10px',
            value: 'Search result: 0 records found'
        }, {
            xtype: 'displayfield',
            ref: '../SearchResultsCountLabel',
            style: 'font-weight: bold; font-size: 12px; margin-top: 10px; margin-left: 30px; ',
            value: 'Click on each entry to view profile'
        }
    ];

    this.booIsDisabledMassEmail = !allowedPages.has('email') || !arrProspectSettings.arrAccess[config.panelType].mass_email;

    ProspectsGrid.superclass.constructor.call(this, {
        id: config.panelType + '-grid',
        split: true,
        stateful: false,
        stripeRows: true,
        loadMask: true,
        cls: 'extjs-grid search-result',
        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No prospects found.',
            getRowClass: this.applyRowClass,
            scrollOffset: 0, // hide scroll bar "column" in Grid
            forceFit: true
        },

        tbar: [
            {
                text: '<i class="las la-user-check"></i>' + _('Convert to Client'),
                tooltip: _('Change the selected prospect to a client.'),
                hidden: !arrProspectSettings.arrAccess[config.panelType].convert_to_client,
                handler: function () {
                    thisGrid.convertProspectToClient(thisGrid.getSelectionsIds(), thisGrid.getProspectsNames());
                }
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Prospect'),
                tooltip: _('Delete selected prospect.'),
                hidden: !arrProspectSettings.arrAccess[config.panelType].delete_prospect,
                handler: function () {
                    thisGrid.deleteProspect(thisGrid.getSelectionsIds(), false);
                }
            }, {
                text: '<i class="lar la-envelope"></i>' + _('Email'),
                hidden: !allowedPages.has('email') || !arrProspectSettings.arrAccess[config.panelType].toolbar_email || !arrProspectSettings.arrAccess[config.panelType].email,
                handler: function () {
                    var recs = thisGrid.getSelectionModel().getSelections();
                    if (recs.length === 0) {
                        Ext.simpleConfirmation.msg('Info', 'Please select a prospect');
                        return false;
                    } else if (recs.length > 1) {
                        Ext.simpleConfirmation.warning('It is more effective to send personalized email to each prospect. Please select only one prospect to send email to.');
                        return false;
                    }

                    var rec = recs[0].data;
                    var options = {
                        member_id: rec.prospect_id,
                        booProspect: true,
                        booNewEmail: true,
                        templates_type: 'Prospect',
                        save_to_prospect: true,
                        panelType: config.panelType,
                        toName: config.panelType === 'marketplace' ?
                            (rec.fName + ' ' + rec.lName).replace(/Not specified/gi, '') : null,
                        hideDraftButton: config.panelType === 'marketplace',
                        hideTo: config.panelType === 'marketplace',
                        hideCc: config.panelType === 'marketplace',
                        hideBcc: config.panelType === 'marketplace'
                    };

                    show_email_dialog(options);
                }
            }, {
                text: '<i class="las la-mail-bulk"></i>' + _('Mass Email'),
                tooltip: _('Email more than one selected prospect at the same time.'),
                hidden: this.booIsDisabledMassEmail,
                scope: this,
                handler: this.openMassMailsDialog.createDelegate(this, [true])
            }, '->', {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                handler: function () {
                    thisGrid.getStore().reload();
                }
            }, {
                xtype: 'button',
                ctCls: 'x-toolbar-cell-no-right-padding',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    var contextId = config.panelType === 'marketplace' ? 'marketplace-prospects-list' : 'prospects-list';
                    showHelpContextMenu(this.getEl(), contextId);
                }
            }
        ]
    });

    this.on('cellclick', this.onCellClick.createDelegate(this));
    this.on('cellcontextmenu', this.onGridContextMenu.createDelegate(this), this);
    this.on('contextmenu', function (e) {
        e.stopEvent();
    });
};

Ext.extend(ProspectsGrid, Ext.grid.GridPanel, {
    getColumnIds: function () {
        var cols = [];
        var columns = this.getColumnModel().config;
        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex)) {
                cols.push(columns[i].dataIndex);
            }
        }

        return cols;
    },

    onCellClick: function (grid, rowIndex, col) {
        // click on checkbox doesn't have to open tab ;)
        if (col === 0) {
            return;
        }

        var prospectsGrid = this;
        var node = prospectsGrid.getSelectionModel().getSelected();
        if (node) {
            var data = node.data;
            Ext.getCmp(prospectsGrid.panelType + '-tab-panel').loadProspectsTab({
                tab: 'prospect',
                tabId: prospectsGrid.panelType + '-ptab-' + data.prospect_id,
                pid: data.prospect_id,
                title: data.fName + ' ' + data.lName
            });

            // Mark prospect 'as read'
            rowIndex = prospectsGrid.getStore().indexOf(node);
            var selRow = null;
            if (rowIndex >= 0) {
                selRow = prospectsGrid.view.getRow(rowIndex);
            }

            if (selRow && Ext.fly(selRow)) {
                if (Ext.fly(selRow).hasClass('prospect-item-unread')) {
                    setTimeout(function () {
                        // Update count of unread prospects for the current tab
                        var todayGrid = Ext.getCmp(grid.panelType + '-tgrid');
                        todayGrid.refreshUnreadCount(grid.initSettings.tab);
                    }, 1000);
                }

                Ext.fly(selRow).removeClass('prospect-item-unread');
            }
        }
    },

    onGridContextMenu: function (grid, rowIndex, cellIndex, e) {
        if (typeof e === 'undefined') {
            return;
        }

        var thisGrid = this;
        if (!this.menu) {
            // create context menu on first right click
            this.menu = new Ext.menu.Menu({
                cls: 'no-icon-menu',
                items: [
                    {
                        text: 'Mark',
                        scope: this,
                        menu: {
                            items: [
                                {
                                    text: 'As read',
                                    scope: this,
                                    handler: function () {
                                        thisGrid.markProspectAsRead(thisGrid.getSelectionsIds(), true);
                                    }
                                }, {
                                    text: 'As unread',
                                    scope: this,
                                    handler: function () {
                                        thisGrid.markProspectAsRead(thisGrid.getSelectionsIds(), false);
                                    }
                                }
                            ]
                        }
                    }, {
                        text: '<i class="las la-edit"></i>' + _('Convert to Client'),
                        scope: this,
                        hidden: !arrProspectSettings.arrAccess[thisGrid.panelType].convert_to_client,
                        handler: function () {
                            thisGrid.convertProspectToClient(thisGrid.getSelectionsIds(), thisGrid.getProspectsNames());
                        }
                    }, {
                        text: '<i class="las la-trash"></i>' + _('Delete'),
                        scope: this,
                        hidden: !arrProspectSettings.arrAccess[thisGrid.panelType].delete_prospect,
                        handler: function () {
                            thisGrid.deleteProspect(thisGrid.getSelectionsIds(), false);
                        }
                    }, '-', {
                        text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                        scope: this,
                        handler: function () {
                            thisGrid.getStore().reload();
                        }
                    }]
            });
        }

        // Check if we need select the row (we clicked on)
        var recs = thisGrid.getSelectionModel().getSelections();
        var booMarkCurrentRow = false;
        if (!recs.length) {
            // There are no records selected at all
            booMarkCurrentRow = true;
        } else {
            var currentRecord = thisGrid.store.getAt(rowIndex);
            var booFound = false;
            for (var i = 0; i < recs.length; i++) {
                if (recs[i].data.prospect_id === currentRecord.data.prospect_id) {
                    booFound = true;
                }
            }

            // This row wasn't selected before
            if (!booFound) {
                booMarkCurrentRow = true;
            }
        }

        // Select row which was selected by right click
        // if it wasn't already selected
        if (booMarkCurrentRow) {
            thisGrid.getSelectionModel().selectRow(rowIndex);
        }

        this.menu.showAt(e.getXY());
    },

    applyRowClass: function (record) {
        var cls = record.data.viewed !== 'Y' ? 'prospect-item-unread' : '';
        if (!empty(record.data.invited_on)) {
            cls += ' prospect-item-invited';
        }
        return cls;
    },

    openMassMailsDialog: function () {
        var grid = this;
        var arrSelectedProspects = grid.getSelectionModel().getSelections();
        var arrSelectedProspectsIds = [];
        for (var i = 0; i < arrSelectedProspects.length; i++) {
            arrSelectedProspectsIds.push(arrSelectedProspects[i].data.prospect_id);
        }

        var arrAllProspectsIds = grid.store.reader.jsonData.allProspectIds;
        showConfirmationMassEmailDialog(grid.panelType, arrSelectedProspectsIds, arrAllProspectsIds);
    },

    saveProspectForm: function (pid, booAssess) {
        var thisGrid = this;

        if (empty(pid)) {
            // New prospect save
            thisGrid.saveProspect(thisGrid.panelType + '-ptab-new-prospect', booAssess);
        } else {
            var tabPanel = Ext.getCmp(thisGrid.panelType + '-sub-tab-panel-' + pid);
            if (tabPanel) {
                var activeTab = tabPanel.getActiveTab();
                if (activeTab && activeTab.subtab && activeTab.tabId) {
                    var booProceed = false;
                    var parentTabPanel = Ext.getCmp(thisGrid.panelType + '-tab-panel');
                    Ext.each(parentTabPanel.arrProspectNavigationSubTabs, function (oSubTab) {
                        if (oSubTab.itemId == activeTab.subtab) {
                            booProceed = true;
                        }
                    });

                    if (booProceed) {
                        // Check and submit form
                        var tabId = activeTab.tabId;
                        switch (activeTab.subtab) {
                            case 'profile':
                                thisGrid.saveProspect(tabId, booAssess);
                                break;

                            case 'occupations':
                                var formId = tabId + '-occupations-prospects';
                                if (booAssess) {
                                    if (checkJobSearchValid(formId)) {
                                        thisGrid.saveProspectOccupations(tabId, booAssess);
                                    } else {
                                        // ARE YOU CRAZY??? CANNOT FILL THE FORM???
                                        showConfirmationMsg(
                                            'Please fill all the required fields correctly and try again.',
                                            true
                                        );
                                    }
                                } else {
                                    thisGrid.saveProspectOccupations(tabId, booAssess);
                                }
                                break;

                            case 'business':
                                thisGrid.saveProspectBusiness(tabId, booAssess);
                                break;

                            case 'assessment':
                                thisGrid.saveProspectAssessment(tabId, booAssess);
                                break;

                            default:
                                // Do nothing
                                break;
                        }
                    }
                }
            }
        }
    },

    saveProspect: function (tabId, booAssess) {
        // Check if all required fields were filled
        // If not - mark these fields and scroll to the first one
        var thisGrid = this,
            booAllFieldsCorrect = true,
            firstWrongField = null;

        function isElementReallyHidden(el) {
            return $(el).is(":hidden") || $(el).css("visibility") == "hidden" || empty($(el).css('opacity'));
        }

        $('.field_required').each(function () {
            var booElementReallyShowed = !isElementReallyHidden(this);
            $(this).parents().each(function () {
                if (isElementReallyHidden(this)) {
                    booElementReallyShowed = false;
                }
            });

            if ((booAssess || $(this).hasClass('field_required_for_save')) && booElementReallyShowed && !$(this).val()) {
                $(this).addClass('error-field');
                booAllFieldsCorrect = false;

                // Remember the first wrong field - we'll scroll to it
                if (!firstWrongField) {
                    firstWrongField = $(this);
                }
            } else {
                $(this).removeClass('error-field');
            }
        });

        if (!booAllFieldsCorrect) {
            firstWrongField.focus();
            if (!firstWrongField.hasClass('x-form-text')) {
                $('html, body').animate({
                    scrollTop: firstWrongField.position().top
                }, 1000);
                return;
            } else {
                $('html, body').animate({
                    scrollTop: firstWrongField.offset().top
                }, 1000);
                return;
            }
        }

        Ext.getBody().mask('Saving...');

        var optionsPrf = {
            url: baseUrl + "/prospects/index/save",
            type: "post",
            success: function (result) {
                if (result.result === 'added') {
                    thisGrid.closeProspectsTab(thisGrid.panelType + '-ptab-new-prospect');
                    Ext.getCmp(thisGrid.panelType + '-tab-panel').loadProspectsTab({tab: 'prospect', pid: result.prospect_id, title: result.tab_id});
                }

                // Update prospects list and tasks list
                // If all is okay
                if (result.result !== 'error') {
                    Ext.simpleConfirmation.msg('Info', result.msg);
                    // Reload prospects grid
                    Ext.getCmp(thisGrid.panelType + '-grid').getStore().reload();

                    // Reload prospects tasks left section
                    if (Ext.getCmp(thisGrid.panelType + '-left-section-tasks')) {
                        Ext.getCmp(thisGrid.panelType + '-left-section-tasks').refreshProspectsTasksList();
                    }

                    // Reload the assessment tab
                    thisGrid.reloadAssessmentTab(tabId, false);

                    // Reload the occupations tab
                    thisGrid.reloadOccupationsTab(tabId, false);
                } else {
                    thisGrid.showSubmissionResult(tabId, true, result.msg);
                }

                Ext.getBody().unmask();
            },

            error: function () {
                thisGrid.showSubmissionResult(tabId, true, 'Cannot submit data. Please try again later.');
                Ext.getBody().unmask();
            },

            beforeSubmit: function (formData) {
                // Add custom fields
                formData.push({
                    name: 'booAssess',
                    value: booAssess ? 1 : 0
                });

                // Show 'in progress', hide button
                Ext.select('#' + tabId + '-divSuccess').hide();
            }
        };

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({jsonp: null, jsonpCallback: null});

        $('#' + tabId + '-prospects').ajaxSubmit(optionsPrf);
    },

    saveProspectOccupations: function (tabId, booAssess) {
        Ext.getBody().mask('Saving...');

        var thisGrid = this;
        var occupationsTabId = tabId + '-occupations';
        var optionsPrf = {
            url: baseUrl + "/prospects/index/save-occupations",
            type: "post",
            success: function (responseText) {
                var result = Ext.decode(responseText);
                if (!result.error) {
                    // Switch file fields with their 'file blocks'
                    if (result.fileFieldsToUpdate && result.fileFieldsToUpdate.length) {
                        thisGrid.toggleResumeSection(result.fileFieldsToUpdate);
                    }

                    // Force reload for this tab (prospect's assessment)
                    thisGrid.reloadAssessmentTab(tabId, false);
                }

                thisGrid.showSubmissionResult(occupationsTabId, result.error, result.msg);
                Ext.getBody().unmask();
            },

            error: function () {
                thisGrid.showSubmissionResult(occupationsTabId, true, 'Cannot submit data. Please try again later.');
                Ext.getBody().unmask();
            },

            beforeSubmit: function (formData) {
                for (var i = 0; i < formData.length; i++) {
                    if (formData[i]['value'] == "undefined" || formData[i]['value'] === 'Type to search for job title...') {
                        formData[i]['value'] = '';
                    }
                }

                // Add custom fields
                formData.push({
                    name: 'booAssess',
                    value: booAssess ? 1 : 0
                });
            }

        };

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({jsonp: null, jsonpCallback: null});

        document.getElementById(occupationsTabId + '-prospects').method = "post";
        $('#' + occupationsTabId + '-prospects').ajaxSubmit(optionsPrf);
    },

    toggleResumeSection: function (fields) {
        var thisGrid = this;
        for (var i = 0; i < fields.length; i++) {
            var arrFileFieldsContainer = $('#' + fields[i].full_field_id);
            var parent = arrFileFieldsContainer.closest('.form-resume');
            thisGrid.updateResumeDetails(fields[i].prospect_id, fields[i].field_id, parent.attr("id"), fields[i].filename);
        }
    },

    updateResumeDetails: function (prospectId, fieldId, parentId, fileName) {
        var parent = $('#' + parentId);

        parent.find('.form-resume-view').show();
        parent.find('.form-resume-edit').hide();
        parent.find('input[type=file]').val('');
        parent.find('.form-resume-edit a[data-rel=cancel]').show();

        if (prospectId) {
            var downloadLink = parent.find('.form-resume-view a[data-rel=download]');
            var downloadUrl = topBaseUrl + '/prospects/index/download-resume?pid=' + prospectId + '&id=' + fieldId;
            $(downloadLink).attr('href', downloadUrl);
            $(downloadLink).html(fileName);

            var deleteLink = parent.find('.form-resume-view a[data-rel=remove]');
            var newDelUrl = topBaseUrl + '/prospects/index/delete-resume?pid=' + prospectId + '&id=' + fieldId;
            $(deleteLink).attr('href', newDelUrl);
        }
    },

    saveProspectBusiness: function (tabId, booAssess) {
        // Show mask
        Ext.getBody().mask('Saving...');

        var thisGrid = this;
        var businessTabId = tabId + '-business';
        var optionsPrf = {
            url: baseUrl + "/prospects/index/save-business",
            type: "post",

            success: function (responseText) {
                var result = Ext.decode(responseText);

                // Refresh assessment sub tab
                // because points were changed (maybe)
                if (!result.error) {
                    // Force reload for this tab (prospect's assessment)
                    thisGrid.reloadAssessmentTab(tabId, false);
                }

                thisGrid.showSubmissionResult(businessTabId, result.error, result.msg);
                Ext.getBody().unmask();
            },

            error: function () {
                thisGrid.showSubmissionResult(businessTabId, true, 'Cannot submit data. Please try again later.');
                Ext.getBody().unmask();
            },

            beforeSubmit: function (formData) {
                // Add custom fields
                formData.push({
                    name: 'booAssess',
                    value: booAssess ? 1 : 0
                });
            }
        };

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({jsonp: null, jsonpCallback: null});

        $('#' + businessTabId + '-prospects').ajaxSubmit(optionsPrf);
    },

    saveProspectAssessment: function (tabId, booAssess) {
        // Show mask
        Ext.getBody().mask('Saving...');

        var thisGrid = this;
        var assessmentTabId = tabId + '-assessment';
        var optionsPrf = {
            url: baseUrl + "/prospects/index/save-assessment",
            type: "post",

            success: function (responseText) {
                var result = Ext.decode(responseText);
                if (!result.error) {
                    // Refresh assessment sub tab
                    // because points were changed (maybe)
                    thisGrid.reloadAssessmentTab(tabId, true);

                    // Reload prospects grid
                    Ext.getCmp(thisGrid.panelType + '-grid').getStore().reload();
                }

                thisGrid.showSubmissionResult(assessmentTabId, result.error, result.msg);
                Ext.getBody().unmask();
            },

            error: function () {
                thisGrid.showSubmissionResult(assessmentTabId, true, 'Cannot submit data. Please try again later.');
                Ext.getBody().unmask();
            },

            beforeSubmit: function (formData) {
                // Add custom fields
                formData.push({
                    name: 'booAssess',
                    value: booAssess ? 1 : 0
                });
            }
        };

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({jsonp: null, jsonpCallback: null});

        $('#' + assessmentTabId + '-prospects').ajaxSubmit(optionsPrf);
    },


    showSubmissionResult: function (tabId, booError, msg) {
        // Generate message to show
        var showMsg;
        if (booError) {
            showMsg = '<ul>';
            var oneError = true;
            if (Ext.isArray(msg)) {
                for (var i = 0; i < msg.length; i++) {
                    showMsg += '<li>&bull;&nbsp;' + msg[i] + '</li>';
                }

                oneError = msg.length === 1;
            } else {
                showMsg += '<li>&bull;&nbsp;' + msg + '</li>';
            }
            showMsg += '</ul>';

            var title = oneError ? 'Error' : 'Errors';
            Ext.simpleConfirmation.error(showMsg, title);
        } else {
            showMsg = msg;
            Ext.simpleConfirmation.success(showMsg);
        }
    },

    reloadAssessmentTab: function (tabId, booNow) {
        var pointsTab = Ext.getCmp(tabId + '-sub-tab-assessment');
        if (pointsTab) {
            if (booNow) {
                pointsTab.doAutoLoad();
            } else {
                pointsTab.booRefreshOnActivate = true;
            }
        }
    },

    reloadOccupationsTab: function (tabId, booNow) {
        var occupationsTab = Ext.getCmp(tabId + '-sub-tab-occupations');
        if (occupationsTab) {
            if (booNow) {
                occupationsTab.doAutoLoad();
            } else {
                occupationsTab.booRefreshOnActivate = true;
            }
        }
    },

    reloadBusinessTab: function (tabId, booNow) {
        var occupationsTab = Ext.getCmp(tabId + '-sub-tab-business');
        if (occupationsTab) {
            if (booNow) {
                occupationsTab.doAutoLoad();
            } else {
                occupationsTab.booRefreshOnActivate = true;
            }
        }
    },

    convertProspectToClient: function (recs, prospectsNames) {
        if (recs.length > 0) {
            if (recs.length > 1) {
                Ext.Msg.show({
                    title: 'Warning',
                    msg: 'Please select only the one prospect to proceed.',
                    modal: true,
                    buttons: Ext.Msg.OK,
                    icon: Ext.Msg.WARNING,
                    fn: function () {
                    }
                });
                return;
            }

            var thisGrid = this;
            var booIsMarketplace = this.panelType === 'marketplace';

            var arrThisApplicantCaseTemplates = [];
            var checkMemberType = 'individual';
            Ext.each(arrApplicantsSettings.visible_case_templates, function (caseTemplate) {
                if (caseTemplate.case_template_type_names.has(checkMemberType)) {
                    arrThisApplicantCaseTemplates.push({
                        option_id: caseTemplate.case_template_id,
                        option_name: caseTemplate.case_template_name
                    });
                }
            });


            var caseTypeCombo = new Ext.form.ComboBox({
                hidden: empty(arrApplicantsSettings.visible_case_templates.length),
                width: 450,
                fieldLabel: arrApplicantsSettings.case_type_label_singular + (recs.length === 1 ? ' for the New Client' : ' for the New Clients'),

                store: {
                    xtype: 'store',
                    reader: new Ext.data.JsonReader({
                        id: 'option_id'
                    }, [
                        {name: 'option_id'},
                        {name: 'option_name'}
                    ]),
                    data: arrThisApplicantCaseTemplates
                },
                emptyText: 'Please select the ' + arrApplicantsSettings.case_type_label_singular + '...',
                mode: 'local',
                displayField: 'option_name',
                valueField: 'option_id',
                triggerAction: 'all',
                forceSelection: true,
                selectOnFocus: true,
                editable: true,
                disabled: true,
                allowBlank: false
            });

            var caseOfficeCombo;
            if (arrApplicantsSettings.queue_settings.allow_multiple) {
                caseOfficeCombo = new Ext.ux.form.LovCombo({
                    width: 450,
                    fieldLabel: recs.length === 1 ?
                        'Assigned ' + arrApplicantsSettings.office_label + ' for the New Client' :
                        'Assigned ' + arrApplicantsSettings.office_label + ' for the New Clients',

                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader(
                            {
                                id: 'option_id'
                            },
                            [{name: 'option_id'}, {name: 'option_name'}]
                        ),
                        data: arrApplicantsSettings.queue_settings.queue_allowed
                    },

                    emptyText: 'Please select...',
                    triggerAction: 'all',
                    valueField: 'option_id',
                    displayField: 'option_name',
                    mode: 'local',
                    useSelectAll: false,
                    allowBlank: false
                });
            } else {
                caseOfficeCombo = new Ext.form.ComboBox({
                    width: 450,
                    fieldLabel: recs.length === 1 ?
                        'Assigned ' + arrApplicantsSettings.office_label + ' for the New Client' :
                        'Assigned ' + arrApplicantsSettings.office_label + ' for the New Clients',

                    store: {
                        xtype: 'store',
                        reader: new Ext.data.JsonReader({
                            id: 'option_id'
                        }, [
                            {name: 'option_id'}, {name: 'option_name'}
                        ]),
                        data: arrApplicantsSettings.queue_settings.queue_allowed
                    },
                    emptyText: 'Please select...',
                    mode: 'local',
                    displayField: 'option_name',
                    valueField: 'option_id',
                    triggerAction: 'all',
                    forceSelection: true,
                    selectOnFocus: true,
                    editable: false,
                    disabled: true,
                    allowBlank: false
                });
            }

            var bottomText = '';
            var pricePerProspectConvert = 0;
            if (booIsMarketplace) {
                bottomText = recs.length === 1 ?
                    'The Prospect record will be copied to the Clients section after successful payment.' :
                    'The Prospects records will be copied to the Clients section after successful payment.';
                pricePerProspectConvert = arrMarketplaceSettings.price_prospect_convert;
            } else {
                bottomText = recs.length === 1 ?
                    'The records for this Prospect will be permanently moved to the Clients section.' :
                    'The records for these prospects will be permanently moved to the Clients section.';
            }

            var wnd = new Ext.Window({
                title: recs.length === 1 ? '<i class="las la-user-check"></i>' + _('Prospect to Client Conversion') : '<i class="las la-user-check"></i>' + _('Prospects to Clients Conversion'),
                plain: false,
                bodyStyle: 'padding: 10px; background-color: #fff;',
                buttonAlign: 'right',
                modal: true,
                resizable: false,
                autoWidth: true,
                autoHeight: true,
                layout: 'form',
                labelAlign: 'top',

                items: [
                    {
                        xtype: 'displayfield',
                        style: 'padding-bottom: 10px;',
                        width: 450,
                        hideLabel: true,
                        value: String.format(
                            recs.length === 1 ? 'Convert the following Prospect to Client: {0}' : 'Convert the following Prospects to Clients: {0}',
                            prospectsNames
                        )
                    },
                    caseTypeCombo,
                    caseOfficeCombo,
                    {
                        xtype: 'displayfield',
                        width: 450,
                        hideLabel: true,
                        hidden: !booIsMarketplace,
                        style: 'padding-top: 10px;',
                        value: String.format(
                            'You will be charged <b>{0} ({1} {2} * {3}/prospect)</b><br/>Applicable taxes will be applied.',
                            formatMoney(site_currency, pricePerProspectConvert * recs.length, true),
                            recs.length,
                            recs.length === 1 ? 'prospect' : 'prospects',
                            formatMoney(site_currency, pricePerProspectConvert, true)
                        )
                    }, {
                        layout: 'column',
                        hidden: !booIsMarketplace,
                        items: [
                            {
                                xtype: 'label',
                                style: 'font-style: italic; font-size: 12px;',
                                html: 'Note: You can update the credit card on file in the &nbsp;'
                            }, {
                                xtype: 'box',
                                autoEl: {
                                    tag: allowedPages.has('admin') ? 'a' : 'div',
                                    href: '#',
                                    'class': 'blulinkun',
                                    style: 'font-style: italic; font-size: 12px;',
                                    html: _('Admin|Company Settings section.')
                                },
                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        if (allowedPages.has('admin')) {
                                            c.getEl().on('click', function () {
                                                addAdminPage();
                                                wnd.close();
                                            }, this, {stopEvent: true});
                                        }
                                    }
                                }
                            }
                        ]
                    }, {
                        layout: 'column',
                        hidden: !booIsMarketplace,
                        style: 'padding-top: 15px;',
                        items: [
                            {
                                xtype: 'checkbox',
                                ref: '../../termsAgreeCheckbox',
                                hidden: !booIsMarketplace,
                                allowBlank: false,
                                boxLabel: _('I have read &amp; accept the&nbsp;'),
                                validateValue: function (value) {
                                    return !!(value && this.checked);
                                }
                            }, {
                                xtype: 'box',
                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    'class': 'blulinkun',
                                    style: 'font-size: 11px; padding-top: 6px;',
                                    html: _('terms of purchase')
                                },
                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            var termsDialog = new Ext.Window({
                                                title: _('Terms of purchase'),
                                                iconCls: 'icon-marketplace-agreement-dialog',
                                                layout: 'fit',
                                                modal: true,
                                                resizable: false,
                                                width: '60%',
                                                height: Ext.getBody().getViewSize().height * 0.95,
                                                y: 10,
                                                buttonAlign: 'right',

                                                items: {
                                                    xtype: 'box',
                                                    autoEl: {
                                                        tag: 'div',
                                                        style: 'background-color: white;',
                                                        html: '<iframe name="terms_iframe" style="border:none;" width="100%" height="100%" src="//immigrationsquare.com/terms-of-purchase-sp" scrolling="auto"></iframe>'
                                                    }
                                                },

                                                buttons: [{
                                                    text: 'OK',
                                                    cls: 'orange-btn',
                                                    handler: function () {
                                                        termsDialog.close();
                                                    }
                                                }]
                                            });

                                            termsDialog.show();
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }
                        ]
                    }, {
                        xtype: 'box',
                        style: 'font-style: italic; padding-top: 20px;',
                        'autoEl': {
                            'tag': 'div',
                            'html': bottomText
                        }
                    }
                ],

                buttons: [
                    {
                        text: 'Cancel',
                        handler: function () {
                            wnd.close();
                        }
                    },
                    {
                        text: booIsMarketplace ? 'Charge and convert' : 'OK',
                        cls: 'orange-btn',
                        width: booIsMarketplace ? 120 : 75,
                        handler: function () {
                            var booIsValid = true;
                            if (caseTypeCombo.isVisible() && !caseTypeCombo.isValid()) {
                                booIsValid = false;
                            }

                            if (caseOfficeCombo.isVisible() && !caseOfficeCombo.isValid()) {
                                booIsValid = false;
                            }

                            var checkbox = wnd.termsAgreeCheckbox;
                            if (checkbox && checkbox.isVisible() && !checkbox.isValid()) {
                                booIsValid = false;
                                Ext.simpleConfirmation.error('Please agree to the terms of purchase by checking the checkbox.');
                            }

                            if (!booIsValid) {
                                return;
                            }

                            wnd.getEl().mask(_('Converting...'));
                            Ext.Ajax.request({
                                url: baseUrl + '/prospects/index/convert-to-client',
                                params: {
                                    panel_type: Ext.encode(thisGrid.panelType),
                                    prospects: Ext.encode(recs),
                                    case_type: Ext.encode(caseTypeCombo.getValue()),
                                    case_office: Ext.encode(caseOfficeCombo.getValue())
                                },

                                success: function (f) {
                                    var result = Ext.decode(f.responseText);
                                    if (result.success) {

                                        // Reload grid
                                        thisGrid.getStore().reload();

                                        // Close all converted (deleted) prospects opened tabs
                                        if (thisGrid.panelType !== 'marketplace') {
                                            for (var i = 0; i < recs.length; i++) {
                                                thisGrid.closeProspectsTab(thisGrid.panelType + '-ptab-' + recs[i]);
                                            }
                                        }

                                        if (result.show_welcome_message && !empty(result.case_id)) {
                                            wnd.close();
                                            Ext.Msg.confirm(_('Info'), _('New client was successfully added.<br/><br/> Would you like a welcome message to be emailed to this client?'), function (btn) {
                                                if (btn === 'yes') {
                                                    show_email_dialog({member_id: result.case_id, encoded_password: result.applicantEncodedPassword, templates_type: 'welcome'});
                                                }
                                            });
                                        } else {
                                            wnd.getEl().mask(_('Done!'));
                                            setTimeout(function () {
                                                wnd.close();
                                            }, 750);
                                        }
                                    } else {
                                        Ext.simpleConfirmation.error(result.msg);
                                        wnd.getEl().unmask();
                                    }
                                },

                                failure: function () {
                                    wnd.getEl().unmask();
                                    Ext.simpleConfirmation.error(_('Cannot convert prospect to the Case'));
                                }
                            });
                        }
                    }
                ],

                // We need this to prevent mark combo as invalid immediately after show
                listeners: {
                    'show': function () {
                        caseTypeCombo.setDisabled(false);
                        caseOfficeCombo.setDisabled(false);
                        if (caseOfficeCombo.isVisible() && caseOfficeCombo.getXType() === 'lovcombo') {
                            caseOfficeCombo.deselectAll();
                        }
                    }
                }
            });
            wnd.show();
            wnd.center();
            wnd.syncShadow();
        }
    },

    getSelectionsIds: function () {
        var recs = this.getSelectionModel().getSelections();
        if (recs.length === 0) {
            Ext.simpleConfirmation.msg('Info', 'Please select a prospect(s)');
            return false;
        }

        var recIds = [];
        for (var i = 0; i < recs.length; i++) {
            recIds.push(recs[i].data.prospect_id);
        }

        return recIds;
    },

    getProspectsNames: function () {
        var recs = this.getSelectionModel().getSelections();

        var booIsMarketplace = this.panelType === 'marketplace';
        var strResult = '<ul style="list-style: disc inside !important; padding-left: 10px;">';
        for (var i = 0; i < recs.length; i++) {
            if (booIsMarketplace) {
                strResult += String.format(
                    '<li style="font-weight: bold">{0}{1}{2}</li>',
                    empty(recs[i].data.fNameReadable) ? recs[i].data.fName : recs[i].data.fNameReadable,
                    empty(recs[i].data.qf_country_of_citizenship) ? '' : ' from ' + recs[i].data.qf_country_of_citizenship,
                    i === recs.length - 1 ? '' : ';'
                );
            } else {
                strResult += String.format(
                    '<li style="font-weight: bold">{0}</li>',
                    recs[i].data.fName + ' ' + recs[i].data.lName
                );
            }
        }
        strResult += '</ul>';

        return strResult;
    },

    deleteProspect: function (recs, booActiveProspect) {
        if (recs.length > 0) {
            var thisGrid = this;
            var msg = String.format(
                'Are you sure you want to {0}?',
                recs.length > 1 ? 'delete ' + recs.length + ' prospects' : booActiveProspect ? 'permanently delete this prospect' : 'delete selected prospect'
            );

            Ext.Msg.confirm('Please confirm', msg, function (btn) {
                if (btn === 'yes') {
                    Ext.getBody().mask('Deleting...');
                    Ext.Ajax.request({
                        url: baseUrl + '/prospects/index/delete-prospect',
                        params: {
                            prospects: Ext.encode(recs)
                        },

                        success: function (f) {
                            var result = Ext.decode(f.responseText);
                            if (result.success) {
                                // Reload grid
                                thisGrid.getStore().reload();

                                // Close all deleted prospects opened tabs
                                for (var i = 0; i < recs.length; i++) {
                                    thisGrid.closeProspectsTab(thisGrid.panelType + '-ptab-' + recs[i]);
                                }

                                // Show confirmation
                                Ext.getBody().mask('Done!');
                                setTimeout(function () {
                                    Ext.getBody().unmask();
                                }, 750);
                            } else {
                                Ext.simpleConfirmation.error(result.msg);
                                Ext.getBody().unmask();
                            }
                        },

                        failure: function () {
                            Ext.getBody().unmask();
                            Ext.simpleConfirmation.error("Can't delete prospect");
                        }
                    });
                }
            });
        }
    },

    closeProspectsTab: function (tabId) {
        var tab = Ext.getCmp(tabId);
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        if (!tab) {
            // Tab wasn't opened, so let's simulate that we closed it -
            // to remove from the recently viewed dialog
            tab = new Ext.Component({
                id: tabId,
                really_deleted: true
            });
            tabPanel.fireEvent('remove', tabPanel, tab);
            tabPanel.fireEvent('tabchange', tabPanel, tabPanel.getActiveTab());
            return;
        }
        tab.really_deleted = true;

        tabPanel.remove(tabId);
    },

    markProspectAsRead: function (recIds, booAsRead) {
        var grid = this;

        if (recIds.length === 0) {
            Ext.simpleConfirmation.warning('Info', 'Please select a prospect(s)');
        } else {
            // Send request to mark prospects
            Ext.getBody().mask('Processing...');
            Ext.Ajax.request({
                url: baseUrl + '/prospects/index/mark',
                params: {
                    'prospects': Ext.encode(recIds),
                    'booAsRead': Ext.encode(booAsRead)
                },

                success: function (f) {
                    var result = Ext.decode(f.responseText);
                    if (result.success) {
                        // Reload grid
                        grid.getStore().reload();

                        // Update count of unread prospects for current
                        var todayGrid = Ext.getCmp(grid.panelType + '-tgrid');
                        todayGrid.refreshUnreadCount(grid.initSettings.tab);

                        Ext.getBody().mask('Done!');
                        setTimeout(function () {
                            Ext.getBody().unmask();
                        }, 750);
                    } else {
                        Ext.simpleConfirmation.error(result.msg);
                        Ext.getBody().unmask();
                    }
                },

                failure: function () {
                    Ext.getBody().unmask();
                    Ext.simpleConfirmation.error('Cannot send request. Please try again later.');
                }
            });
        }
    }
});

Ext.reg('AppProspectsGrid', ProspectsGrid);
