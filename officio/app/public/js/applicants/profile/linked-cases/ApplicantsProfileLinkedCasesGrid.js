var ApplicantsProfileLinkedCasesGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;
    this.autoRefreshCasesList = true;

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
        'parent_first_name',
        'parent_DOB',
        'parent_country_of_residence'
    ];

    this.store = new Ext.data.Store({
        remoteSort: false,
        baseParams: {
            applicantId: config.applicantId,
            caseIdLinkedTo: config.caseId,
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

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            id: expandCol,
            header: _('Case File Number'),
            dataIndex: 'case_file_number',
            sortable: true,
            width: 200
        }, {
            header: arrApplicantsSettings.last_name_label,
            dataIndex: 'parent_last_name',
            sortable: true,
            width: 200
        }, {
            header: arrApplicantsSettings.first_name_label,
            dataIndex: 'parent_first_name',
            sortable: true,
            width: 200
        }, {
            header: _('Date of birth'),
            dataIndex: 'parent_DOB',
            renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesShort),
            sortable: true,
            width: 120
        }, {
            header: _('Country of Residence'),
            dataIndex: 'parent_country_of_residence',
            sortable: true,
            width: 190
        }, {
            header: arrApplicantsSettings.file_status_label,
            dataIndex: 'file_status',
            sortable: true,
            width: 300
        }, {
            header: _('Date Signed'),
            dataIndex: 'case_date_signed_on',
            renderer: Ext.util.Format.dateRenderer(Date.patterns.UniquesShort),
            sortable: true,
            width: 120
        }
    ];

    this.filterCheckboxLoadActiveCases = new Ext.form.Checkbox({
        hideLabel: true,
        checked: true,
        boxLabel: _('Show only Active Cases'),
        listeners: {
            'check': thisGrid.refreshAssignedCasesList.createDelegate(thisGrid)
        }
    });

    this.tbar = [
        {
            text: '<i class="las la-user-plus"></i>' + _('Link Case'),
            ref: '../assignCaseBtn',
            disabled: true,
            hidden: !this.booAllowUpdateCases,
            handler: this.assignCase.createDelegate(this)
        }, {
            text: '<i class="las la-user-minus"></i>' + _('Unlink Case'),
            ref: '../unassignCaseBtn',
            disabled: true,
            hidden: !this.booAllowUpdateCases,
            handler: this.unassignCase.createDelegate(this)
        },
        this.filterCheckboxLoadActiveCases,
        '->', {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            handler: thisGrid.refreshAssignedCasesList.createDelegate(thisGrid)
        }
    ];

    ApplicantsProfileLinkedCasesGrid.superclass.constructor.call(this, {
        cls: 'search-result',
        border: false,
        loadMask: {msg: _('Loading...')},
        sm: sm,
        columns: this.columns,
        store: this.store,
        height: 250,
        stripeRows: true,
        autoExpandColumn: expandCol,
        autoExpandMin: 130,
        viewConfig: {
            emptyText: _('There are no records to show.')
        }
    });

    thisGrid.on('cellclick', thisGrid.openCaseTab.createDelegate(thisGrid));
    thisGrid.on('afterrender', thisGrid.onGridShow.createDelegate(this));
    thisGrid.getSelectionModel().on('selectionchange', thisGrid.updateToolbarButtons, thisGrid);
};

Ext.extend(ApplicantsProfileLinkedCasesGrid, Ext.grid.GridPanel, {
    onGridShow: function () {
        if (this.rendered && this.autoRefreshCasesList) {
            this.getStore().load();
        }
        this.autoRefreshCasesList = false;
    },

    openCaseTab: function (grid, rowIndex, columnIndex) {
        if (columnIndex) {
            var rec = grid.getStore().getAt(rowIndex);

            if (rec && this.store.reader.jsonData) {
                var data = this.store.reader.jsonData,
                    tabPanel = grid.owner.owner.owner;
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
        var sel = this.getSelectionModel().getSelections();
        var booIsSelectedAtLeastOne = sel.length >= 1;
        var booIsSelectedOnlyOne = sel.length === 1;

        if (this.booAllowUpdateCases) {
            this.assignCaseBtn.setDisabled(false);
            this.unassignCaseBtn.setDisabled(!booIsSelectedAtLeastOne);
        }
    },

    showAssignCasesLinkCaseDialog: function () {
        var thisGrid = this;

        var oDialog = new ApplicantsProfileLinkedCasesLinkCaseDialog({
            panelType: thisGrid.panelType,
            applicantId: thisGrid.applicantId,
            applicantName: thisGrid.applicantName,
            memberType: thisGrid.memberType,
            caseId: thisGrid.caseId
        }, thisGrid);

        oDialog.show();
        oDialog.center();
    },

    assignCase: function () {
        var thisGrid = this;

        var nominationCeilingFieldValue = Ext.getCmp(thisGrid.nominationCeilingFieldId).getValue();

        var question = '';
        if (!empty(nominationCeilingFieldValue)) {
            var caseAssignedCount = thisGrid.getStore().getCount();
            if (caseAssignedCount == nominationCeilingFieldValue - 1) {
                question = _('This Sponsorship has only one open Nomination remaining after this application.<br><br>Do you still wish to continue?');
            } else if (caseAssignedCount >= nominationCeilingFieldValue) {
                question = _('This Sponsorship has already reached its approved Nomination Ceiling.<br><br>Do you still wish to continue?');
            }
        }

        if (!empty(question)) {
            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    thisGrid.showAssignCasesLinkCaseDialog();
                }
            });
        } else {
            thisGrid.showAssignCasesLinkCaseDialog();
        }
    },

    unassignCase: function () {
        var thisGrid = this;
        var sel = thisGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to unlink <i>{0}</i>?'),
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
                                thisGrid.getEl().mask(_('Done!'));

                                setTimeout(function () {
                                    thisGrid.owner.owner.applicantsCasesNavigationPanel.refreshCasesList();

                                    thisGrid.getEl().unmask();
                                    thisGrid.refreshAssignedCasesList();
                                }, 2000);
                            } else {
                                thisGrid.getEl().unmask();
                                Ext.simpleConfirmation.error(resultData.msg);
                            }
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

    refreshAssignedCasesList: function () {
        this.store.reload();
    },

    checkLoadedResult: function () {
        var thisGrid = this;
        if (thisGrid.store.reader.jsonData && thisGrid.store.reader.jsonData.msg && !thisGrid.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', this.store.reader.jsonData.msg);
            thisGrid.getEl().mask(msg);
        } else {
            thisGrid.getEl().unmask();
        }

        thisGrid.updateToolbarButtons();
    },

    applyParams: function (store, options) {
        this.getEl().unmask();

        options.params = options.params || {};

        var params = {
            booOnlyActiveCases: Ext.encode(this.filterCheckboxLoadActiveCases.getValue())
        };

        Ext.apply(options.params, params);
    },

    makeReadOnly: function (store, options) {
        this.assignCaseBtn.setDisabled(true);
        this.unassignCaseBtn.setDisabled(true);
    }
});

Ext.reg('ApplicantsProfileLinkedCasesGrid', ApplicantsProfileLinkedCasesGrid);
