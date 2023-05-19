var ApplicantsCasesNavigationGrid = function(config, owner) {
    var thisGrid = this;
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        autoLoad: false,
        remoteSort: true,
        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-assigned-cases-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'case_id',
            fields: [
                'case_id',
                'case_first_name',
                'case_last_name',
                'case_full_name',
                'case_type',
                'case_type_id',
                'case_email',
                'case_file_number',
                {name: 'case_created_on', type: 'date', dateFormat: Date.patterns.ISO8601Long},
                'applicant_id',
                'applicant_name',
                'applicant_type',
                'employer_id',
                'employer_name',
                'employer_linked_case_file_number',
                'employer_sub_case'
            ]
        })
    });
    this.store.setDefaultSort('case_type', 'DESC');
    this.store.on('beforeload', this.applyCustomParams.createDelegate(this));
    this.store.on('load', this.checkLoadedData.createDelegate(this));

    var expandCol = Ext.id();
    this.columns = [
        {
            header: '',
            dataIndex: 'case_id',
            width: 10,
            fixed: true,
            renderer: function (val, a, record) {
                // This is a reserved column - we'll show a circle for the active/selected case
                return '';
            }
        }, {
            id: expandCol,
            header: '',
            dataIndex: 'case_full_name',
            renderer: function (val, a, row) {
                var employerName = '';
                if (!empty(row.data.employer_name) && config.memberType !== 'employer') {
                    employerName = '<br/>' + _('Employer: ') + row.data.employer_name;

                    if (!empty(row.data.employer_linked_case_file_number)) {
                        employerName += ' (' + row.data.employer_linked_case_file_number + ')';
                    }
                }

                // For the employer we show client name + file number in brackets
                var clientName = '';
                row.data.case_full_name = empty(row.data.case_full_name) ? '' : ' (' + row.data.case_full_name + ')';
                if (config.memberType == 'employer' && row.data.applicant_type !== 'employer' && row.data.applicant_id != row.data.employer_id) {
                    clientName = '<br/>' + row.data.applicant_name;
                }

                var caseType = empty(row.data.case_type) ? _('Case 1') : row.data.case_type;
                var tooltip = (caseType + clientName + row.data.case_full_name + employerName).replaceAll('"', "'");

                var link = String.format(
                    '<span ext:qtip="{1}">{0}</span>{2}<span class="this_client_case_name" ext:qtip="{1}">{3}</span>{4}',
                    caseType,
                    tooltip,
                    empty(clientName) ? '' : String.format('<span class="this_client_name" ext:qtip="{1}">{0}</span>', clientName, tooltip),
                    row.data.case_full_name,
                    empty(employerName) ? '' : String.format('<span class="this_employer_name" ext:qtip="{1}">{0}</span>', employerName, tooltip)
                );

                return String.format(
                    '<a href="#applicants/{0}/cases/{1}/{2}" class="blulinkun norightclick" onclick="return false;">{3}</a>',
                    row.data.applicant_id,
                    row.data.case_id,
                    'case_details',
                    link
                );
            }
        }
    ];

    var genId = Ext.id();
    ApplicantsCasesNavigationGrid.superclass.constructor.call(this, {
        id: genId,
        sm: new Ext.grid.RowSelectionModel({
            singleSelect: true,
            listeners:    {
                'beforerowselect': function () {
                    return false;
                }
            }
        }),

        cls: 'no-borders-grid',
        hideHeaders: true,
        border: false,
        stripeRows: false,
        autoExpandColumn: expandCol,
        loadMask: {
            msg: _('Loading...')
        },
        viewConfig: {
            getRowClass: this.applyRowClass.createDelegate(this),
            emptyText: this.getEmptyTextBasedOnCheckbox(false),

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 20,
            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + genId + ' .x-grid3-scroller').elements[0];
                if (scroller.clientWidth === scroller.offsetWidth) {
                    // no scroller
                    this.scrollOffset = 2;
                } else {
                    // there is a scroller
                    this.scrollOffset = this.orgScrollOffset;
                }
                this.fitColumns(false);
            }
        }
    });

    this.on('cellclick', this.openCaseTab.createDelegate(this));
};

Ext.extend(ApplicantsCasesNavigationGrid, Ext.grid.GridPanel, {
    getEmptyTextBasedOnCheckbox: function (booActive) {
        var emptyText;
        if (booActive) {
            emptyText = _('There are no active cases assigned to this client.');
        } else {
            emptyText = _('There are no cases assigned to this client.');
        }

        return emptyText;
    },

    applyCustomParams: function (store) {
        var booActive = true;
        this.activeCaseRowIndex = null;

        if (!empty(Ext.getCmp(this.owner.activeCasesCheckboxId))) {
            booActive = Ext.getCmp(this.owner.activeCasesCheckboxId).getValue();
        } else {
            // Load checked state from cookies
            var cookieName = 'client_profile_active_cases_checkbox';
            var savedInCookie = Ext.state.Manager.get(cookieName);
            if (typeof savedInCookie != 'undefined') {
                booActive = savedInCookie;
            }
        }

        // Change empty text - it is based on the checkbox's state
        this.getView().emptyText = this.getEmptyTextBasedOnCheckbox(booActive);

        var params = {
            applicantId: this.applicantId,
            booOnlyActiveCases: booActive
        };
        store.baseParams = store.baseParams || {};
        Ext.apply(store.baseParams, params);
    },

    // Apply custom class for row
    applyRowClass: function (record, rowIndex) {
        var arrClasses = [];
        if (parseInt(this.caseId, 10) === parseInt(record.data.case_id, 10)) {
            arrClasses.push('active-record');

            // Remember currently active/selected row
            this.activeCaseRowIndex = rowIndex;
        }

        if (record.data.employer_sub_case) {
            arrClasses.push('second-level');
        } else {
            arrClasses.push('first-level');
        }

        return arrClasses.join(' ');
    },

    checkLoadedData: function (store, records) {
        try {
            var thisGrid = this;
            var view = thisGrid.getView();

            setTimeout(function () {
                if (thisGrid.activeCaseRowIndex !== null) {
                    var resolved = view.resolveCell(thisGrid.activeCaseRowIndex, 0, false);
                    view.scroller.dom.scrollTop = resolved.row.offsetTop;
                }
            }, 100);

            this.owner.addNewCaseButton.setVisible(this.store.reader.jsonData.booAllowCreateCases && !this.owner.booHideNewCaseButton);
        } catch (e) {
        }
    },

    openCaseTab: function(grid, rowIndex, columnIndex, e) {
        var thisGrid = this;
        var rec = grid.getStore().getAt(rowIndex);
        if (rec && this.store.reader.jsonData) {
            // Always open the Case Details tab
            var subTab = 'case_details';
            var data = this.store.reader.jsonData;
            if (rec.data.applicant_id == data.applicant_id && data.applicant_type == 'employer') {
                grid.owner.owner.owner.owner.openApplicantTab({
                    applicantId: rec.data.applicant_id,
                    applicantName: rec.data.applicant_name,
                    memberType: rec.data.applicant_type,
                    caseId: rec.data.case_id,
                    caseName: rec.data.case_full_name,
                    caseType: rec.data.case_type_id,
                    caseEmployerId: data.applicant_id,
                    caseEmployerName: data.applicant_name
                }, subTab);
            } else {
                var employerId = null,
                    employerName = null;
                if (rec.data.applicant_type == 'individual' && thisGrid.memberType == 'employer') {
                    employerId = thisGrid.applicantId;
                    employerName = thisGrid.applicantName;
                } else if (!empty(rec.data.employer_id)) {
                    employerId = rec.data.employer_id;
                    employerName = rec.data.employer_name;
                }

                grid.owner.owner.owner.owner.openApplicantTab({
                    applicantId: rec.data.applicant_id,
                    applicantName: rec.data.applicant_name,
                    memberType: rec.data.applicant_type,
                    caseId: rec.data.case_id,
                    caseName: rec.data.case_full_name,
                    caseType: rec.data.case_type_id,
                    caseEmployerId: employerId,
                    caseEmployerName: employerName
                }, subTab);
            }
        }
    }
});