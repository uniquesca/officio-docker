/* global arrTATabs, arrApplicantsSettings, site_currency */
var ApplicantsCasesGrid = function (config, owner) {
    var thisGrid = this;
    this.owner = owner;
    Ext.apply(this, config);

    this.autoRefreshCasesList = false;
    this.booAllowUpdateCases = true;

    var arrFieldsToShow = [
        'case_id',
        'case_full_name',
        'case_type_id',
        'case_email',
        'case_file_number',
        {name: 'case_date_signed_on', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        'applicant_id',
        'applicant_name',
        'applicant_type'
    ];

    var arrAdditionalFields = [
        'file_status',
        'visa_subclass',
        'outstanding_balance_secondary',
        'outstanding_balance_primary',
        'registered_migrant_agent',
        'parent_last_name',
        'parent_first_name'
    ];

    this.store = new Ext.data.Store({
        remoteSort: false,
        baseParams: {
            applicantId: config.applicantId,
            arrFieldsToLoad: Ext.encode(arrAdditionalFields),
            booLoadDetailedCaseInfo: true
        },

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-assigned-cases-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'case_id',
            fields: arrFieldsToShow.concat(arrAdditionalFields)
        })
    });
    this.store.on('beforeload', this.applyParams.createDelegate(this));
    this.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.store.on('loadexception', this.checkLoadedResult.createDelegate(this));

    this.store.setDefaultSort('case_file_number', 'ASC');

    var booHiddenSecondaryTAColumn = true;
    if (typeof arrTATabs !== 'undefined' && arrTATabs.length > 0) {
        for (var i = 0; i < arrTATabs.length; i++) {
            var obj = arrTATabs[i];
            if (obj.currency != site_currency) {
                booHiddenSecondaryTAColumn = false;
            }
        }
    }

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            id: expandCol,
            header: _('Case File #'),
            dataIndex: 'case_file_number',
            sortable: true,
            width: 200
        }, {
            header: arrApplicantsSettings['last_name_label'],
            dataIndex: 'parent_last_name',
            sortable: true,
            width: 120
        }, {
            header: arrApplicantsSettings['first_name_label'],
            dataIndex: 'parent_first_name',
            sortable: true,
            width: 120
        }, {
            header: _('Date Signed'),
            dataIndex: 'case_date_signed_on',
            renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesShort),
            sortable: true,
            width: 110
        }, {
            header: arrApplicantsSettings['file_status_label'],
            dataIndex: 'file_status',
            sortable: true,
            width: 120
        }, {
            header: arrApplicantsSettings['visa_subclass_label'],
            dataIndex: 'visa_subclass',
            sortable: true,
            width: 120
        }, {
            header: 'OSB (' + site_currency_label + ')',
            tooltip: 'Outstanding Balance in ' + site_currency_label,
            dataIndex: 'outstanding_balance_primary',
            align: 'right',
            sortable: true,
            width: 80
        }, {
            header: _('OSB (Other)'),
            tooltip: 'Outstanding Balance for other currencies.',
            dataIndex: 'outstanding_balance_secondary',
            sortable: true,
            align: 'right',
            hidden: booHiddenSecondaryTAColumn,
            width: 80
        }, {
            header: arrApplicantsSettings['rma_label'],
            dataIndex: 'registered_migrant_agent',
            sortable: true,
            width: 100
        }
    ];

    this.tbar = [
        {
            text: '<i class="las la-user-minus"></i>' + _('Unassign Case'),
            ref: '../unassignCaseBtn',
            disabled: true,
            hidden: true,
            handler: this.unassignCase.createDelegate(this)
        }, {
            text: '<i class="las la-plus"></i>' + _('Add New Case'),
            ref: '../addCaseBtn',
            hidden: !owner.owner.hasAccess('case', 'add'),
            handler: this.addNewCase.createDelegate(this),
            disabled: true
        }, {
            text: '<i class="las la-copy"></i>' + _('Duplicate Case'),
            ref: '../duplicateCaseBtn',
            disabled: true,
            hidden: true,
            handler: this.duplicateCase.createDelegate(this)
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Case'),
            ref: '../deleteCaseBtn',
            disabled: true,
            hidden: !owner.owner.hasAccess('case', 'delete'),
            handler: this.deleteCase.createDelegate(this)
        }
    ];

    // Additional filter toolbar
    this.filterCheckboxLoadActiveCases = new Ext.form.Checkbox({
        hideLabel: true,
        checked: true,
        boxLabel: _('Show only Active Cases'),
        listeners: {
            'check': thisGrid.refreshAssignedCasesList.createDelegate(thisGrid)
        }
    });

    this.filterComboCaseLinkedTo = new Ext.form.ComboBox({
        fieldLabel: _('Show Cases Linked to'),
        labelStyle: 'font-size: 14px; width: 150px; padding-top: 13px',
        store: new Ext.data.Store({
            autoLoad: true,
            baseParams: {
                employerId: config.applicantId
            },

            proxy: new Ext.data.HttpProxy({
                url: baseUrl + '/applicants/profile/load-employer-cases-list'
            }),

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'case_id',
                fields: [
                    'case_id',
                    'case_and_applicant_name'
                ]
            }),

            data: {
                items: [
                    {
                        'case_id': 0,
                        'case_and_applicant_name': _('-- All --')
                    }
                ],
                totalProperty: 1
            }
        }),
        displayField: 'case_and_applicant_name',
        valueField: 'case_id',
        mode: 'local',
        width: 400,
        value: 0, // Select All by default
        forceSelection: true,
        triggerAction: 'all',
        selectOnFocus: true,
        editable: false,
        typeAhead: false,
        listeners: {
            'select': thisGrid.refreshAssignedCasesList.createDelegate(thisGrid)
        }
    });

    this.tbar2 = new Ext.Panel({
        layout: 'column',
        style: 'background-color: #fff; padding: 15px;',
        items: [
            {
                xtype: 'container',
                layout: 'form',
                style: 'padding-top: 10px',
                items: this.filterCheckboxLoadActiveCases
            }, {
                xtype: 'container',
                layout: 'form',
                labelWidth: 150,
                style: 'padding: 3px 5px 0 50px;',
                hidden: config.memberType != 'employer',
                items: this.filterComboCaseLinkedTo
            }
        ]
    });

    this.casesGrid = new Ext.grid.GridPanel({
        cls: 'search-result',
        border: false,
        loadMask: {msg: _('Loading...')},
        sm: sm,
        columns: this.columns,
        store: this.store,
        height: initPanelSize() - 160,
        stripeRows: true,
        stateful: true,
        stateId: 'client_cases_columns_settings',
        autoExpandColumn: expandCol,
        viewConfig: {
            emptyText: _('There are no records to show.')
        }
    });
    this.casesGrid.on('cellclick', this.openCaseTab.createDelegate(this.casesGrid));
    this.casesGrid.on('show', this.onGridShow.createDelegate(this));
    this.casesGrid.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);

    ApplicantsCasesGrid.superclass.constructor.call(this, {
        border: false,
        style: 'padding: 17px 20px;',
        items: [
            this.tbar2,
            this.casesGrid
        ]
    });
};

Ext.extend(ApplicantsCasesGrid, Ext.Panel, {
    onGridShow: function () {
        if (this.rendered && this.autoRefreshCasesList) {
            this.casesGrid.getStore().load();
        }
        this.autoRefreshCasesList = false;
    },

    openCaseTab: function (grid, rowIndex, columnIndex) {
        if (columnIndex) {
            var rec = grid.getStore().getAt(rowIndex);

            if (rec && this.store.reader.jsonData) {
                var data = this.store.reader.jsonData,
                    tabPanel = grid.ownerCt.owner.owner;
                if (rec.data.applicant_id == data.applicant_id && data.applicant_type == 'employer') {
                    tabPanel.openApplicantTab({
                        applicantId: rec.data.applicant_id,
                        applicantName: rec.data.applicant_name,
                        memberType: rec.data.applicant_type,
                        caseId: rec.data.case_id,
                        caseName: rec.data.case_full_name,
                        caseType: rec.data.case_type_id,
                        caseEmployerId: data.applicant_id,
                        caseEmployerName: data.applicant_name
                    }, 'case_details');
                } else {
                    tabPanel.openApplicantTab({
                        applicantId: rec.data.applicant_id,
                        applicantName: rec.data.applicant_name,
                        memberType: rec.data.applicant_type,
                        caseId: rec.data.case_id,
                        caseName: rec.data.case_full_name,
                        caseType: rec.data.case_type_id,
                        caseEmployerId: null,
                        caseEmployerName: null
                    }, 'case_details');
                }
            }
        }
    },

    updateToolbarButtons: function () {
        if (this.booAllowUpdateCases) {
            var sel = this.casesGrid.getSelectionModel().getSelections();
            var booIsSelectedAtLeastOne = sel.length >= 1;
            var booIsSelectedOnlyOne = sel.length === 1;

            this.unassignCaseBtn.setDisabled(!booIsSelectedAtLeastOne);
            this.duplicateCaseBtn.setDisabled(!booIsSelectedOnlyOne);
            this.deleteCaseBtn.setDisabled(!booIsSelectedOnlyOne);
        }
    },

    unassignCase: function () {
        var thisGrid = this;
        var sel = this.casesGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to unassign <i>{0}</i>?'),
                sel.length == 1 ? sel[0].data.case_full_name : sel.length + _(' cases')
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    var arrCases = [];
                    for (var i = 0; i < sel.length; i++) {
                        arrCases.push(sel[i].data.case_id);
                    }

                    thisGrid.getEl().mask(_('Processing...'));
                    Ext.Ajax.request({
                        url: topBaseUrl + '/applicants/profile/unassign-case',
                        params: {
                            applicantId: Ext.encode(thisGrid.applicantId),
                            arrCases: Ext.encode(arrCases)
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);

                            if (resultData.success) {
                                thisGrid.casesGrid.store.reload();
                            }

                            var showMsgTime = resultData.success ? 1000 : 2000;
                            var msg = String.format(
                                '<span style="color: {0}">{1}</span>',
                                resultData.success ? 'black' : 'red',
                                resultData.success ? _('Done!') : resultData.msg
                            );
                            thisGrid.getEl().mask(msg);

                            setTimeout(function () {
                                thisGrid.getEl().unmask();
                            }, showMsgTime);
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error(_('Case cannot be unassigned. Please try again later.'));
                            thisGrid.getEl().unmask();
                        }
                    });
                }
            });
        }
    },

    addNewCase: function () {
        if (this.memberType == 'individual') {
            this.owner.owner.openApplicantTab({
                applicantId: this.applicantId,
                applicantName: this.applicantName,
                memberType: this.memberType,
                caseId: 0,
                caseName: _('Case 1'),
                caseType: '',
                caseEmployerId: null,
                caseEmployerName: null
            }, 'case_details');
        } else {
            this.owner.owner.openApplicantTab({
                applicantId: this.applicantId,
                applicantName: this.applicantName,
                memberType: this.memberType,
                caseId: 0,
                caseName: _('Case 1'),
                caseType: '',
                caseEmployerId: this.applicantId,
                caseEmployerName: this.applicantName
            }, 'case_details');
        }
    },

    duplicateCase: function () {
        var thisGrid = this;
        var sel = this.casesGrid.getSelectionModel().getSelections();

        var arrCases = [];
        for (var i = 0; i < sel.length; i++) {
            arrCases.push(sel[i].data.case_id);
        }

        thisGrid.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/clients/index/duplicate',
            params: {
                client_id: arrCases[0]
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                thisGrid.getEl().unmask();

                if (resultData.success) {
                    thisGrid.casesGrid.store.reload();
                } else {
                    Ext.simpleConfirmation.error(resultData.error);
                }
            },
            failure: function () {
                Ext.simpleConfirmation.error(_('Case cannot be duplicated. Please try again later.'));
                thisGrid.getEl().unmask();
            }
        });
    },

    deleteCase: function () {
        var thisGrid = this,
            selModel = this.casesGrid.getSelectionModel();

        if (!selModel.hasSelection()) {
            return;
        }

        var sel = selModel.getSelections(),
            caseId = 0;
        if (sel.length) {
            caseId = sel[0].data.case_id;
        }

        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to permanently delete this case and all associated information and files?'), function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/applicants/profile/delete',
                    params: {
                        applicantId: Ext.encode(caseId)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            thisGrid.casesGrid.getStore().load();

                            var tabPanel = thisGrid.owner.owner,
                                caseTabId = tabPanel.generateTabId(tabPanel.panelType, thisGrid.applicantId, caseId);

                            // Close opened case's tab
                            var oTab = Ext.getCmp(caseTabId);
                            if (oTab) {
                                oTab.really_deleted = true;
                                tabPanel.remove(caseTabId);
                            }

                            // Refresh all places where this case is used/showed
                            tabPanel.refreshClientsList(tabPanel.panelType, thisGrid.applicantId, caseId, false);

                            Ext.getBody().mask(resultData.msg);
                            setTimeout(function () {
                                Ext.getBody().unmask();
                            }, 750);
                        } else {
                            Ext.simpleConfirmation.error(resultData.msg);
                            Ext.getBody().unmask();
                        }
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot delete case. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    },

    refreshAssignedCasesList: function () {
        this.casesGrid.store.reload();
    },

    checkLoadedResult: function () {
        var thisGrid = this;
        if (thisGrid.store.reader.jsonData && thisGrid.store.reader.jsonData.msg && !thisGrid.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', this.store.reader.jsonData.msg);
            thisGrid.getEl().mask(msg);
            setTimeout(function () {
                thisGrid.getEl().unmask();
            }, 2000);
        } else {
            thisGrid.getEl().unmask();
        }

        try {
            thisGrid.booAllowUpdateCases = thisGrid.store.reader.jsonData.booAllowUpdateCases;

            thisGrid.addCaseBtn.setDisabled(!thisGrid.store.reader.jsonData.booAllowCreateCases);
            thisGrid.deleteCaseBtn.setDisabled(!thisGrid.store.reader.jsonData.booAllowDeleteCases);
        } catch (e) {
        }
    },

    applyParams: function (store, options) {
        this.getEl().unmask();

        options.params = options.params || {};

        var params = {
            caseIdLinkedTo: Ext.encode(this.filterComboCaseLinkedTo.getValue()),
            booOnlyActiveCases: Ext.encode(this.filterCheckboxLoadActiveCases.getValue())
        };

        Ext.apply(options.params, params);
    },

    setCaseLinkedTo: function (caseId) {
        var thisPanel = this;
        var combo = this.filterComboCaseLinkedTo;

        if (!empty(caseId) && combo.getValue() == caseId) {
            // Case is already selected in the combo, so don't reload cases combo
            // just refresh the grid
            thisPanel.refreshAssignedCasesList();
        } else {
            // Refresh combo, set value to it and reload grid
            thisPanel.filterComboCaseLinkedTo.getStore().on('load', function () {
                combo.setValue(caseId);
                thisPanel.refreshAssignedCasesList();
            }, thisPanel, {single: true});
            thisPanel.filterComboCaseLinkedTo.getStore().load();
        }
    },

    makeReadOnly: function (store, options) {
        this.addCaseBtn.setDisabled(true);
        this.deleteCaseBtn.setDisabled(true);
        this.duplicateCaseBtn.setDisabled(true);
        this.unassignCaseBtn.setDisabled(true);
    }
});

Ext.reg('ApplicantsCasesGrid', ApplicantsCasesGrid);
