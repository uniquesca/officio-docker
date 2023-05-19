var ApplicantsAdvancedSearchGrid = function (config, owner) {
    Ext.apply(this, config);
    this.owner = owner;
    var thisGrid = this;

    this.clientsOnPage = 50;
    // Minimum column width - will be used during columns auto resizing
    this.minimumColumnWidth = 150;

    this.store = new Ext.data.Store({
        url: topBaseUrl + '/applicants/search/run-search',
        autoLoad: false,

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            id: 'member_id'
        }, [{name: 'member_id'}])
    });

    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            header: '&nbsp;'
        }
    ];

    this.searchResultMenuBtn = new Ext.Button({
        text: '<i class="las la-columns"></i>' + _('Select Columns'),
        menu: []
    });

    var booShowBulkChangesButton = false;

    var changeFieldsOptions = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['queue']['change'] : arrApplicantsSettings['access']['queue']['change'];
    Object.keys(changeFieldsOptions).forEach(function (key) {
        if (changeFieldsOptions[key]) {
            booShowBulkChangesButton = true;
            return false;
        }
    });

    var arrToolbarButtons = [
        {
            xtype: 'checkbox',
            ref: '../activeCasesCheckboxRO',
            boxLabel: _('Active Cases Only'),
            disabled: true,
            // Will be automatically shown when we show Advanced Search in the read-only mode,
            // the value will be set from the Advanced Search criteria
            hidden: true
        },
        this.searchResultMenuBtn,
        {
            ref: '../bulkChangesButton',
            text: '<i class="las la-user-edit"></i>' + _('Bulk Changes'),
            tooltip: _('Change field value for selected client(s).'),
            hidden: !booShowBulkChangesButton,
            scope: this,
            menu: {
                cls: 'no-icon-menu',
                items: [{
                    text: 'Change all records',
                    handler: this.openChangeOptionsDialog.createDelegate(this, [true])
                }, {
                    text: 'Change selected records',
                    handler: this.openChangeOptionsDialog.createDelegate(this, [false])
                }]
            }
        }, {
            xtype: 'button',
            text: '<i class="las la-print"></i>' + _('Print'),
            ref: '../printButton',
            width: 70,
            hidden: !arrApplicantsSettings.access.search.view_advanced_search_print,
            handler: function () {
                printTable('#' + thisGrid.getId() + ' div.x-panel-body', 'Search Result');
            }
        }, {
            xtype: 'button',
            text: '<i class="las la-file-export"></i>' + _('Export All'),
            ref: '../exportAllButton',
            hidden: !arrApplicantsSettings.access.queue['export'],
            scope: this,
            menu: {
                cls: 'no-icon-menu',
                items: [
                    {
                        text: 'Export All to Excel (XLS)',
                        handler: function () {
                            thisGrid._exportToExcel(thisGrid, thisGrid.exportAllButton, 'xls');
                        }
                    }, {
                        text: 'Export All to Excel (CSV)',
                        handler: function () {
                            thisGrid._exportToExcel(thisGrid, thisGrid.exportAllButton, 'csv');
                        }
                    }
                ]
            }
        }, {
            text: '<i class="las la-mail-bulk"></i>' + _('Mass Email'),
            ref: '../emailTemplateButton',
            hidden: config.booHideMassEmailing || !allowedPages.has('email') || !allowedPages.has('templates-view'),
            scope: this,
            handler: this.openMassMailsDialog.createDelegate(this, [true])
        }
    ];

    this.tbar = new Ext.Toolbar({
        items: arrToolbarButtons
    });

    this.bbar = new Ext.PagingToolbar({
        hidden: config.booHideGridToolbar ? true : false,
        pageSize: this.clientsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: 'Displaying records {0} - {1} of {2}',
        emptyMsg: 'No records to display'
    });

    ApplicantsAdvancedSearchGrid.superclass.constructor.call(this, {
        cls: 'search-result',
        loadMask: {msg: 'Loading...'},
        sm: sm,
        autoWidth: true,
        stripeRows: true,

        view: new Ext.grid.GroupingView({
            deferEmptyText: _('There are no records to show.'),
            emptyText: _('There are no records to show.'),
            forceFit: true,
            showGroupName: false,
            groupTextTpl: '{text}'
        })
    });

    this.on('afterrender', this.applyColumns.createDelegate(this, [[], thisGrid.owner.filterClientType == 'employer']));
    this.on('cellclick', this.openMemberTab.createDelegate(this));
    this.on('mouseover', this.onMouseOver.createDelegate(this));
};

Ext.extend(ApplicantsAdvancedSearchGrid, Ext.grid.GridPanel, {
    onMouseOver: function (e, t) {
        var thisGrid = this;
        var rowIndex = this.getView().findRowIndex(t);
        if ($(e.getTarget()).hasClass('case-dependent-link') && rowIndex !== false) {
            var rec = thisGrid.getStore().getAt(rowIndex);
            thisGrid.showDependentTooltip(Ext.get(e.getTarget()), rec.data.case_id);
        } else {
            if (!empty(thisGrid.dependentTooltipWnd)) {
                thisGrid.dependentTooltipWnd.close();
            }
        }
    },

    openChangeOptionsDialog: function (booAllRecords) {
        if (empty(this.getStore().getCount())) {
            Ext.simpleConfirmation.warning('There are no clients.');
            return;
        }

        var grid = this,
            arrSelectedClients = grid.getSelectionModel().getSelections(),
            arrAllIds = grid.getStore().reader.jsonData.all_ids;


        if ((!booAllRecords && arrSelectedClients.length <= 0) || (booAllRecords && arrAllIds.length == 0)) {
            Ext.simpleConfirmation.warning('No clients are selected.<br/>Please select at least one client.');
        } else {
            var arrSelectedClientIds = [];
            if (booAllRecords) {
                arrSelectedClientIds = arrAllIds;
            } else {
                for (var i = 0; i < arrSelectedClients.length; i++) {
                    if (!empty(arrSelectedClients[i].data.case_id)) {
                        arrSelectedClientIds.push(arrSelectedClients[i].data.case_id);
                    } else {
                        arrSelectedClientIds.push(arrSelectedClients[i].data.applicant_id);
                    }
                }
            }

            // Show dialog
            var wnd = new ApplicantsQueueChangeOptionsWindow({
                panelType: grid.owner.panelType,
                arrSelectedClientIds: arrSelectedClientIds,
                onSuccessUpdate: this.refreshOnSuccess.createDelegate(this)
            }, this);
            wnd.show();
            wnd.center();
        }
    },

    refreshOnSuccess: function () {
        // Reload current grid
        this.getStore().load();

        // Reload left panel(s)
        this.owner.owner.refreshClientsList(this.owner.panelType, 0, 0, false);
    },

    openMassMailsDialog: function () {
        var grid = this;
        var arrSelectedClients = grid.getSelectionModel().getSelections();
        var arrSelectedClientsIds = [];
        for (var i = 0; i < arrSelectedClients.length; i++) {
            if (!empty(arrSelectedClients[i].data.case_id)) {
                arrSelectedClientsIds.push(arrSelectedClients[i].data.case_id);
            } else {
                arrSelectedClientsIds.push(arrSelectedClients[i].data.applicant_id);
            }
        }

        var arrAllClientsIds = grid.store.reader.jsonData.all_ids;
        showConfirmationMassEmailDialog(grid.panelType, arrSelectedClientsIds, arrAllClientsIds);
    },

    applyParams: function (store, options) {
        this.getEl().unmask();
        if (this.owner.checkIsValidSearch(false)) {
            options.params = options.params || {};

            var params = {
                searchType: Ext.encode(this.panelType),
                arrSearchParams: Ext.encode(this.owner.getFieldValues()),
                arrColumns: Ext.encode(this.owner.getColumnIds())
            };

            Ext.apply(store.baseParams, params);

            this.owner.toggleGridAndToolbar(true);
        } else {
            return false;
        }
    },

    toggleEmployerColumn: function () {
        // Turn on/off the grouping + Employer column
        var thisGrid = this;
        var employerColumnIndex = thisGrid.getColumnModel().getIndexById(thisGrid.employerColumnId);
        if (thisGrid.owner.filterClientType === 'individual') {
            // Individual
            thisGrid.getStore().clearGrouping();

            // Hide the employer column
            if (employerColumnIndex !== -1) {
                thisGrid.getColumnModel().setHidden(employerColumnIndex, true);
            }
        } else {
            // Employer
            thisGrid.getStore().groupBy('employer_member_id', true);

            // Show the employer column
            if (employerColumnIndex !== -1) {
                thisGrid.getColumnModel().setHidden(employerColumnIndex, false);
            }
        }
    },

    showDependentTooltip: function (el, caseId) {
        var thisGrid = this;

        var wnd = thisGrid.dependentTooltipWnd;
        if (!empty(wnd) && wnd.caseId != caseId) {
            wnd.close();
            wnd = null;
        }

        if (empty(wnd)) {
            wnd = new Ext.Window({
                caseId: caseId,
                title: _('Dependants'),
                cls: 'dependants-table-container',
                bodyStyle: 'padding: 5px; background-color: white;',
                modal: false,
                autoWidth: true,
                autoHeight: true,
                resizable: false,
                plain: false,
                html: 'Loading...',
                x: el.getX() - 50,
                y: el.getY() + 20,
                autoLoad: {
                    url: baseUrl + '/applicants/profile/get-case-dependents-list',
                    params: {
                        case_id: caseId
                    },

                    callback: function () {
                        try {
                            if (!empty(wnd)) {
                                wnd.setPosition(el.getX() - (wnd.getWidth() / 2), el.getY() + 20);
                                wnd.syncShadow();
                            }
                        } catch (e) {
                        }
                    }
                },

                listeners: {
                    close: function () {
                        thisGrid.dependentTooltipWnd = null;
                    }
                }
            });

            thisGrid.dependentTooltipWnd = wnd;

            wnd.show();
        }
    },

    checkLoadedResult: function () {
        var thisGrid = this;
        if (this.store.reader.jsonData && this.store.reader.jsonData.message && !this.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', this.store.reader.jsonData.message);
            thisGrid.getEl().mask(msg);
            setTimeout(function () {
                thisGrid.getEl().unmask();
            }, 2000);
        } else {
            thisGrid.getEl().unmask();
        }
        var booDisable = this.store.getCount() < 1;
        this.emailTemplateButton.setDisabled(booDisable);
        this.printButton.setDisabled(booDisable);
        this.exportAllButton.setDisabled(booDisable);

        thisGrid.toggleEmployerColumn();

        thisGrid.owner.fixSearchGridHeight();
        thisGrid.owner.owner.fixParentPanelHeight();
    },

    loadException: function (e, store, response) {
        var thisGrid = this;
        var errorMessage = _('Error during data loading. Please try to search again.');

        try {
            // Clear previously loaded results
            thisGrid.getStore().removeAll();

            var resultData = Ext.decode(response.responseText);
            if (resultData && resultData.message) {
                errorMessage = resultData.message;
            }
        } catch (e) {
        }

        // Show the provided error
        var msg = String.format('<span style="color: red; font-size: 16px;">{0}</span>', errorMessage);
        thisGrid.getEl().mask(msg);

        thisGrid.owner.owner.fixParentPanelHeight();
    },

    sortByName: function (a, b) {
        var aName = a.field_name.toLowerCase();
        var bName = b.field_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    // Update columns in relation to the selected option in the 'Search for' combo
    applyColumns: function (arrColumnsList, booShow) {
        var thisGrid = this;
        thisGrid.employerColumnId = Ext.id();

        var arrColumns = [
            thisGrid.getSelectionModel(),
            {
                id: thisGrid.employerColumnId,
                header: _('Employer'),
                sortable: false,
                width: 300,
                dataIndex: 'case_name',
                hidden: !booShow,
                renderer: function (v, u, rec) {
                    var employerName = '';
                    if (rec.data.applicant_type === 'employer') {
                        employerName = rec.data.case_name;
                        if (!empty(rec.data.case_categories)) {
                            employerName = rec.data.case_categories + ' (' + employerName + ')';
                        }
                    } else if (!empty(rec.data.employer_id)) {
                        employerName = rec.data.applicant_name + ' (' + rec.data.case_name + ')';
                    }

                    if (rec.data.employer_sub_case) {
                        employerName = '<div style="padding-left: 20px">' + employerName + '</div>';
                    }

                    return employerName;
                }
            }, {
                // This column is used as a grouping header
                header: '',
                hidden: true,
                sortable: false,
                dataIndex: 'employer_member_id',
                groupRenderer: function (v, u, rec) {
                    var groupName = '';
                    if (rec.data.applicant_type === 'employer') {
                        groupName = rec.data.applicant_name;
                    } else if (!empty(rec.data.employer_id)) {
                        groupName = rec.data.employer_name;
                    }

                    if (!empty(rec.data.employer_doing_business)) {
                        groupName = groupName + ' (' + rec.data.employer_doing_business + ')';
                    }

                    return groupName;
                }
            }
        ];

        var arrReaderFields = [];
        var arrColumnsListIds = [];
        var defaultSortField = site_version == 'australia' ? 'individual_family_name' : 'individual_last_name';
        var defaultSortDirection = 'ASC';
        var defaultColumnWidth = thisGrid.minimumColumnWidth;
        var booAutoResizeColumns = true;
        if (this.panelType === 'contacts') {
            arrReaderFields = [
                'applicant_id',
                'applicant_name',
                'applicant_type',
                'employer_member_id' // this is for the grouping, not used
            ];

            defaultSortField = 'contact_family_name';
        } else {
            arrReaderFields = [
                'applicant_id',
                'applicant_name',
                'applicant_type',
                'case_id',
                'case_name',
                'case_type',
                'case_categories',
                'employer_member_id',
                'employer_doing_business',
                'employer_sub_case',
                'employer_id',
                'employer_name'
            ];
        }


        if (arrColumnsList === undefined) {
            arrColumnsList = [];
        }

        if (arrColumnsList && arrColumnsList.constructor === Array && !arrColumnsList.length) {
            // Try to load from cookie
            var arrSaved = Ext.state.Manager.get(this.getSearchSettingsCookieName());
            if (arrSaved && arrSaved.length) {
                Ext.each(arrSaved, function (columnId) {
                    arrColumnsList.push({
                        id: columnId,
                        width: defaultColumnWidth
                    });
                    arrColumnsListIds.push(columnId);
                });
            }
        } else {
            if (typeof arrColumnsList.arrColumns != 'undefined') {
                defaultSortField = arrColumnsList.arrSortInfo.sort;
                defaultSortDirection = arrColumnsList.arrSortInfo.dir;
                arrColumnsList = arrColumnsList.arrColumns;
                booAutoResizeColumns = false;

                Ext.each(arrColumnsList, function (oColumnInfo) {
                    arrColumnsListIds.push(oColumnInfo.id);
                });
            } else {
                // Support of old version - where array of columns is passed only
                var tmpArrColumnsList = arrColumnsList;
                arrColumnsList = [];
                Ext.each(tmpArrColumnsList, function (columnId) {
                    arrColumnsList.push({
                        id: columnId,
                        width: defaultColumnWidth
                    });
                    arrColumnsListIds.push(columnId);
                });
            }
        }

        // Update Menu items too
        var newMenu = new Ext.menu.Menu({
            cls: 'no-icon-menu',
            enableScrolling: false,
            refreshClientsOnClose: false,

            listeners: {
                'hide': function () {
                    if (this.refreshClientsOnClose) {
                        thisGrid.owner.applyFilter();
                        this.refreshClientsOnClose = false;
                    }
                }
            }
        });

        var colIndex,
            items;

        var arrSearchFor = [];
        if (this.panelType === 'contacts') {
            arrSearchFor = [
                {
                    'search_for_id': 'contact',
                    'search_for_name': 'Contact',
                    'search_for_group': 'Contact'
                }
            ];
        } else {
            arrSearchFor = arrApplicantsSettings.search_for;
        }

        var arrColumnsToSort = [];
        if (arrSearchFor.length) {
            Ext.each(arrSearchFor, function (oClientType) {
                items = [];

                var arrGroupedFields = [];
                if (oClientType.search_for_id == 'case') {
                    var arrGroupedFieldNames = [];
                    var booShowedDependants = false;
                    for (var templateId in arrApplicantsSettings.case_group_templates) {
                        if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                            Ext.each(arrApplicantsSettings.case_group_templates[templateId], function (group) {
                                if (group.group_title === 'Dependants') {
                                    if (!booShowedDependants) {
                                        var oField = {
                                            field_id: 0,
                                            field_unique_id: 'dependants',
                                            field_name: 'Dependants',
                                            field_access: 'F',
                                            field_client_type: 'case',
                                            field_column_show: false,
                                            field_use_full_row: true,
                                            field_custom_height: 0,
                                            field_required: 'N',
                                            field_disabled: 'N',
                                            field_encrypted: 'N',
                                            field_group_name: 'Case Details',
                                            field_grouped_id: 'case_dependants',
                                            field_type: 'dependants',
                                        };
                                        arrGroupedFields.push(oField);
                                        arrGroupedFieldNames.push(oField.field_unique_id);
                                        booShowedDependants = true;
                                    }
                                } else {
                                    Ext.each(group.fields, function (field) {
                                        if (!arrGroupedFieldNames.has(field.field_unique_id)) {
                                            field.field_grouped_id = oClientType.search_for_id + '_' + field.field_unique_id;
                                            arrGroupedFields.push(field);
                                            arrGroupedFieldNames.push(field.field_unique_id);
                                        }
                                    });
                                }
                            });
                        }
                    }
                } else {
                    Ext.each(arrApplicantsSettings.groups_and_fields[oClientType.search_for_id][0]['fields'], function (group) {
                        Ext.each(group.fields, function (field) {
                            field.field_grouped_id = oClientType.search_for_id + '_' + field.field_unique_id;
                            arrGroupedFields.push(field);
                        });
                    });
                }
                arrGroupedFields.sort(thisGrid.sortByName);

                for (var j = 0; j < arrGroupedFields.length; j++) {
                    colIndex = arrGroupedFields[j]['field_grouped_id'];
                    var booShowColumn = arrGroupedFields[j]['field_column_show'];
                    var columnWidth = defaultColumnWidth;
                    if (arrColumnsList.length) {
                        booShowColumn = false;
                        Ext.each(arrColumnsList, function (oColumn) {
                            if (oColumn.id == colIndex) {
                                booShowColumn = true;
                                columnWidth = oColumn.width;
                            }
                        });
                    }

                    var booIsDependentsColumn = colIndex == 'case_dependants';

                    // Show only allowed columns.
                    // Column will be showed if:
                    // 1. It is saved in cookie (for default blank searches)
                    // 2. It is saved in search details (saved search)
                    // 3. For default searches, if this is 'default column'
                    arrColumnsToSort.push({
                        header: arrGroupedFields[j]['field_name'],
                        hidden: !booShowColumn,
                        width: booIsDependentsColumn ? 80 : columnWidth,
                        align: booIsDependentsColumn ? 'center' : 'left',
                        sortable: true,
                        menuDisabled: true,
                        dataIndex: colIndex,
                        renderer: function (name, i, rec) {
                            if (this.dataIndex == 'case_dependants' && !empty(rec.data.case_id)) {
                                return '<i class="las la-user-circle case-dependent-link"></i>';
                            } else if (this.dataIndex.match(/^tag_percentage_/) && !empty(name)) {
                                return name + '%';
                            } else {
                                return name;
                            }
                        }
                    });

                    arrReaderFields.push(colIndex);

                    items.push(new Ext.menu.CheckItem({
                        text: arrGroupedFields[j]['field_name'],
                        field_grouped_id: colIndex,
                        checked: booShowColumn,
                        hideOnClick: false,
                        checkHandler: function (item, e) {
                            thisGrid.getColumnModel().setHidden(thisGrid.getColumnModel().findColumnIndex(item.field_grouped_id), !e);
                            thisGrid.updateDefaultColumnsList();

                            newMenu.refreshClientsOnClose = true;
                        }
                    }));
                }

                // Add group and insert items
                newMenu.addMenuItem({
                    enableScrolling: false,
                    text: oClientType.search_for_group,
                    menu: items
                });
            });
        }
        this.searchResultMenuBtn.menu = newMenu;

        // Sort records as they were saved before (e.g. if moved)
        function sortColumnsByCorrectOrder(a, b) {
            if (arrColumnsListIds.has(a.dataIndex) && arrColumnsListIds.has(b.dataIndex)) {
                return arrColumnsListIds.indexOf(a.dataIndex) < arrColumnsListIds.indexOf(b.dataIndex) ? -1 : 1;
            } else if (arrColumnsListIds.has(a.dataIndex) && !arrColumnsListIds.has(b.dataIndex)) {
                return -1;
            } else if (!arrColumnsListIds.has(a.dataIndex) && arrColumnsListIds.has(b.dataIndex)) {
                return 1;
            }

            return 0;
        }

        arrColumns = arrColumns.concat(arrColumnsToSort.sort(sortColumnsByCorrectOrder));

        var newColModel = new Ext.grid.ColumnModel({
            columns: arrColumns,
            defaultSortable: true,
            listeners: {
                'columnmoved': function () {
                    thisGrid.updateDefaultColumnsList();
                },

                'hiddenchange': function () {
                    thisGrid.autoResizeColumns();
                }
            }
        });

        var store = new Ext.data.GroupingStore({
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/applicants/search/run-search',
                timeout: 5 * 60 * 1000, // 5 minutes
                method: 'post'
            }),
            autoLoad: false,
            remoteSort: true,
            groupField: 'employer_member_id',

            sortInfo: {
                field: defaultSortField,
                direction: defaultSortDirection
            },

            baseParams: {
                start: 0,
                limit: thisGrid.clientsOnPage,
                saved_search_id: thisGrid.owner.savedSearchId,
                search_query: thisGrid.owner.searchQuery
            },

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count'
            }, arrReaderFields)
        });

        store.on('beforeload', this.applyParams.createDelegate(this));
        store.on('load', this.checkLoadedResult.createDelegate(this));
        store.on('loadexception', this.loadException.createDelegate(this));

        this.getBottomToolbar().bind(store);
        this.reconfigure(store, newColModel);

        if (booAutoResizeColumns) {
            this.autoResizeColumns();
        }
    },

    autoResizeColumns: function () {
        var thisGrid = this;

        // Resize visible columns (the checkbox column will be skipped)
        // The width cannot be less than minimumColumnWidth
        var arrColumnIds = thisGrid.owner.getColumnIds();
        if (arrColumnIds.length) {
            var oModel = thisGrid.getColumnModel();
            var checkboxColumnWidth = empty(oModel.columns[0]['dataIndex']) ? oModel.columns[0]['width'] + 10 : 0;
            var columnWidth = Ext.max([thisGrid.minimumColumnWidth, (thisGrid.getWidth() - checkboxColumnWidth - 10) / arrColumnIds.length]);
            Ext.each(oModel.columns, function (oColumn, index) {
                if (!oColumn['hidden'] && !empty(oColumn['dataIndex'])) {
                    oModel.setColumnWidth(index, columnWidth);
                }
            });
        }
    },

    getSearchSettingsCookieName: function () {
        var suffix = '';
        if (['all', 'last4me', 'last4all', 'quick_search'].has(this.owner.savedSearchId)) {
            suffix = '_' + this.owner.savedSearchId;
        }

        var middle = this.panelType === 'contacts' ? '' : '_' + this.owner.filterClientType;

        return this.panelType + middle + '_adv_srch_cols' + suffix;
    },

    updateDefaultColumnsList: function () {
        if (empty(this.owner.savedSearchId) || ['all', 'last4me', 'last4all', 'quick_search'].has(this.owner.savedSearchId)) {
            Ext.state.Manager.set(this.getSearchSettingsCookieName(), this.owner.getColumnIds());
        }
    },

    openMemberTab: function (grid, rowIndex, col) {
        // click on checkbox doesn't have to open tab ;)
        if (col === 0)
            return;

        var rec = grid.getStore().getAt(rowIndex);
        var tabPanel = this.owner.owner;

        if (!empty(rec.data.case_id)) {
            switch (rec.data.applicant_type) {
                case 'individual':
                    tabPanel.openApplicantTab({
                        applicantId: rec.data.applicant_id,
                        applicantName: rec.data.applicant_name,
                        memberType: rec.data.applicant_type,
                        caseId: rec.data.case_id,
                        caseName: rec.data.case_name,
                        caseType: rec.data.case_type
                    }, 'profile');
                    break;

                case 'employer':
                default:
                    tabPanel.openApplicantTab({
                        applicantId: rec.data.applicant_id,
                        applicantName: rec.data.applicant_name,
                        memberType: rec.data.applicant_type,
                        caseId: rec.data.case_id,
                        caseName: rec.data.case_name,
                        caseType: rec.data.case_type,
                        caseEmployerId: rec.data.applicant_id,
                        caseEmployerName: rec.data.applicant_name
                    }, 'profile');
                    break;
            }

        } else {
            tabPanel.openApplicantTab({
                applicantId: rec.data.applicant_id,
                applicantName: rec.data.applicant_name,
                memberType: rec.data.applicant_type
            }, 'profile');
        }
    },

    _exportToExcel: function (thisGrid, btn, format) {
        if (thisGrid.store.totalLength > arrApplicantsSettings.export_range) {
            var scrollMenu = new Ext.menu.Menu();
            var start = 0;
            var end = 0;
            var rangeCount = Math.ceil(thisGrid.store.totalLength / arrApplicantsSettings.export_range);
            for (var i = 0; i < rangeCount; ++i) {
                start = i * arrApplicantsSettings.export_range + 1;
                end = (i + 1) * arrApplicantsSettings.export_range;
                if (i == (rangeCount - 1)) {
                    end = thisGrid.store.totalLength;
                }
                scrollMenu.add({
                    text: 'Export ' + (start) + ' - ' + (end) + ' records',
                    listeners: {
                        click: thisGrid.exportToExcel.createDelegate(thisGrid, [start - 1, format])
                    }
                });
            }

            if (30 * i > Ext.getBody().getHeight()) {
                scrollMenu.showAt([btn.getEl().getX() + btn.getWidth(), 0]);
            } else {
                scrollMenu.show(btn.getEl())
            }
        } else {
            thisGrid.exportToExcel(0, format);
        }
    },

    exportToExcel: function (exportStart, format) {
        var grid = this;
        if (empty(this.getStore().getCount())) {
            Ext.simpleConfirmation.warning('There is nothing to export.');
            return;
        }

        // Get visible columns
        var cm = [];
        var cmModel = this.getColumnModel().config;
        // @NOTE: we skip the first column because it is a checkbox
        for (var i = 1; i < cmModel.length; i++) {
            if (!cmModel[i].hidden) {
                cm.push({
                    id: cmModel[i].dataIndex,
                    name: cmModel[i].header,
                    width: cmModel[i].width
                });
            }
        }

        var store = this.getStore();
        var max = exportStart + arrApplicantsSettings.export_range;
        if (max > store.reader.jsonData.all_ids.length) {
            max = store.reader.jsonData.all_ids.length;
        }

        var filteredIds = [];
        for (i = exportStart; i < max; i++) {
            if (i in store.reader.jsonData.all_ids) {
                filteredIds.push(store.reader.jsonData.all_ids[i]);
            }
        }

        submit_hidden_form(baseUrl + '/applicants/search/export-to-excel', {
            format: Ext.encode(format),
            searchType: Ext.encode(grid.owner.panelType),
            arrColumns: Ext.encode(cm),
            arrAllIds: Ext.encode(filteredIds)
        });
    }
});