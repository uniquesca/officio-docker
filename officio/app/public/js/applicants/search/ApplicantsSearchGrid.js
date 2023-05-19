ApplicantsSearchGrid = function(config, owner) {
    var thisGrid = this;
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        autoLoad: true,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/search/get-applicants-list'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            fields: [
                'user_id',
                'user_type',
                'user_name',
                'user_parent_id',
                'user_parent_name',
                'case_type_id',
                'applicant_id',
                'applicant_name',
                'applicant_type'
            ]
        })
    });

    this.store.on('beforeload', this.applyParams.createDelegate(this));
    this.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.store.on('loadexception', this.checkLoadedResult.createDelegate(this));

    var subjectColId = Ext.id();
    this.columns = [
        {
            id: subjectColId,
            header: _('Name'),
            dataIndex: 'user_name',
            sortable: true,
            width: 300,
            renderer: function(val, i, rec) {
                var name = val,
                    case_number = '';
                if (rec.data.applicant_name && rec.data.user_type != 'case' && (!empty(rec.data.user_parent_id) || rec.data.user_type == 'individual')) {
                    case_number = empty(rec.data.applicant_name) ? '' : ' (' + rec.data.applicant_name + ')';
                    name = rec.data.user_name + case_number;
                } else if(rec.data.user_type == 'case' && rec.data.applicant_type == 'employer') {
                    case_number = empty(rec.data.user_name) ? '' : ' (' + rec.data.user_name + ')';

                    var tabPanel = Ext.getCmp(thisGrid.panelType + '-tab-panel');
                    name = tabPanel.getCaseTypeNameByCaseTypeId(rec.data.case_type_id) + case_number;
                }

                var indent = 0;
                if (!empty(rec.data.user_parent_id)) {
                    indent = rec.json.linked_to_case ? 20 : 10;
                }

                return String.format(
                    '<div style="padding-left: {0}"><a href="#" class="{1}" onclick="return false;" />{2}</a></div>',
                    indent + 'px',
                    '',
                    name
                );
            }
        }
    ];

    ApplicantsSearchGrid.superclass.constructor.call(this, {
        cls: 'no-borders-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        style: 'padding-bottom: 5px;',
        viewConfig: {
            emptyText: _('There are no records to show.')
        }
    });

    this.on('cellclick', this.onCellClick.createDelegate(this));

    // Prevent selections in the grid
    this.getSelectionModel().on('beforerowselect', function(){ return false;}, this);
};

Ext.extend(ApplicantsSearchGrid, Ext.grid.GridPanel, {
    applyParams: function(store, options) {
        options.params = options.params || {};

        //variables from Grid
        var params = {};
        if (this.owner.useQuickSearch) {
            var query = trim(this.owner.conflictSearchFieldValue) != '' ? this.owner.conflictSearchFieldValue : this.owner.quickSearchField.getValue();
            params = {
                search_for: this.panelType,
                search_query: Ext.encode(query),
                boo_conflict_search: trim(this.owner.conflictSearchFieldValue) != '' ? 1 : 0
            };
            this.owner.conflictSearchFieldValue = '';
            // Strip tags
            query = query.replace(/<\/?[^>]+>/gi, '');
            store.currentSearch = String.format('keyword: {0}', query);
        } else {
            params = {
                search_for: this.panelType,
                search_id: this.owner.selectedSavedSearch
            };
            store.currentSearch = this.owner.selectedSavedSearchName;
        }

        this.owner.setCurrentSearchName(this.store.currentSearch);
        Ext.apply(options.params, params);
    },

    checkLoadedResult: function() {
        if (this.getEl()) {
            if (this.store.reader.jsonData && this.store.reader.jsonData.msg && !this.store.reader.jsonData.success) {
                var msg = String.format('<span style="color: red">{0}</span>', this.store.reader.jsonData.msg);
                this.getEl().mask(msg);
            } else {
                this.getEl().unmask();
            }
        }
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var rec = this.store.getAt(rowIndex);
        if (e.getTarget().tagName == 'A') {
            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            switch (rec.data.user_type) {
                case 'case':
                    tabPanel.openApplicantTab({
                        applicantId:      rec.data.applicant_id,
                        applicantName:    rec.data.applicant_name,
                        memberType:       rec.data.applicant_type,
                        caseId:           rec.data.user_id,
                        caseName:         rec.data.user_name,
                        caseType:         rec.data.case_type_id,
                        caseEmployerId:   rec.data.applicant_id,
                        caseEmployerName: rec.data.applicant_name
                    }, 'profile');
                    break;

                case 'individual':
                    if (empty(rec.data.applicant_id)) {
                        tabPanel.openApplicantTab({
                            applicantId:      rec.data.user_id,
                            applicantName:    rec.data.user_name,
                            memberType:       rec.data.user_type
                        });
                    } else {
                        tabPanel.openApplicantTab({
                            applicantId:      rec.data.user_id,
                            applicantName:    rec.data.user_name,
                            memberType:       rec.data.user_type,
                            caseId:           rec.data.applicant_id,
                            caseName:         rec.data.applicant_name,
                            caseType:         rec.data.case_type_id,
                            caseEmployerId:   rec.data.user_parent_id,
                            caseEmployerName: rec.data.user_parent_name
                        }, 'profile');
                    }
                    break;

                case 'employer':
                case 'contact':
                default:
                    tabPanel.openApplicantTab({
                        applicantId:      rec.data.user_id,
                        applicantName:    rec.data.user_name,
                        memberType:       rec.data.user_type
                    });
            }
        }
    }
});