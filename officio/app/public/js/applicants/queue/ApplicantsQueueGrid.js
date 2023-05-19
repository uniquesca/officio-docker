/* global arrApplicantsSettings */
/* jslint browser:true */
var ApplicantsQueueGrid = function (config, owner) {
    var thisGrid = this;
    this.owner = owner;

    Ext.apply(this, config);

    this.clientsOnPage = 50;
    this.defaultColumnWidth = 150;

    this.selectedQueueDisplayFieldId = Ext.id();
    this.selectedQueueFieldId = Ext.id();
    this.selectedQueueFieldLabelId = Ext.id();
    this.activeCasesFieldId = Ext.id();

    var booShowQueueCombo = false;
    if (config.panelType === 'contacts') {
        booShowQueueCombo = arrApplicantsSettings.access.contact.search.view_queue_panel;
    } else {
        booShowQueueCombo = arrApplicantsSettings.access.search.view_queue_panel;
    }

    var booShowQueueDisplayFieldId = !booShowQueueCombo;
    var clientOrContactLabel = config.panelType === 'contacts' ? 'contact' : 'client';

    this.store = new Ext.data.Store({
        url: this.runQueryUrl,
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
        tooltip: _('Select the information columns you want to display.'),
        menu: []
    });

    this.filterByCaseTypeMenuBtn = new Ext.Button({
        text: '<i class="las la-filter"></i>' + _('Filter by ') + arrApplicantsSettings.case_type_label_singular,
        hidden: config.panelType === 'contacts',
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

    this.tbar = new Ext.Panel();

    this.tbar.add(new Ext.Panel({
        layout: 'column',
        style: 'margin-bottom: 16px',
        hidden: is_client,
        cls: booShowQueueDisplayFieldId ? 'whole-search-criteria-container-no-bg' : 'whole-search-criteria-container',

        items: [{
            id: this.selectedQueueDisplayFieldId,
            xtype: 'displayfield',
            cls: 'not-real-link-big',
            hidden: !booShowQueueDisplayFieldId,
            hideLabel: true,
            value: '',

            listeners: {
                'show': function () {
                    this.ownerCt.addClass('whole-search-criteria-container-no-bg').removeClass('whole-search-criteria-container');
                    thisGrid.fixThisGridHeight();
                },

                'hide': function () {
                    this.ownerCt.removeClass('whole-search-criteria-container-no-bg').addClass('whole-search-criteria-container');
                    thisGrid.fixThisGridHeight();
                }
            }
        }, {
            id: this.selectedQueueFieldLabelId,
            xtype: 'label',
            cls: 'simple_label_with_padding',
            forId: this.selectedQueueFieldId,
            hidden: !booShowQueueCombo,
            text: _('My ') + ' ' + arrApplicantsSettings.office_label + 's:'
        }, {
            id: this.selectedQueueFieldId,
            xtype: 'lovcombo',
            cls: 'simple_combo',
            width: 200,
            minWidth: 200,

            store: {
                xtype: 'store',
                reader: new Ext.data.JsonReader({
                    id: 'option_id'
                }, [{name: 'option_id'}, {name: 'option_name'}]),
                data: []
            },

            triggerAction: 'all',
            valueField: 'option_id',
            displayField: 'option_name',
            mode: 'local',
            hidden: !booShowQueueCombo,
            useSelectAll: false,
            allowBlank: false,
            listeners: {
                select: function () {
                    thisGrid.applyOfficeComboSelectedValues();
                    thisGrid.applyFilter(true);
                },

                'afterrender': this.loadSettings.createDelegate(this, [])
            }
        }]
    }));

    this.filterClientType = 'individual';
    this.filterCookieId = 'clients_queue_default_filter';
    var booShowRadios = config.panelType === 'applicants' && arrApplicantsSettings.access.employers_module_enabled;
    if (booShowRadios) {
        try {
            // Try to load from cookie
            var defaultFilterClientType = Ext.state.Manager.get(thisGrid.filterCookieId, 'individual');
            if (['individual', 'employer'].has(defaultFilterClientType)) {
                this.filterClientType = defaultFilterClientType;
            }
        } catch (e) {
        }
    }

    this.radiosPanel = new Ext.Panel({
        layout: 'column',
        style: 'margin-bottom: 16px',
        hidden: !booShowRadios,

        items: [
            {
                boxLabel: _('Individual Cases'),
                name: 'filter_client_type_radio',
                xtype: 'radio',
                value: 'individual',
                checked: this.filterClientType !== 'employer',
                listeners: {
                    check: function (radio, booChecked) {
                        if (booChecked) {
                            thisGrid.filterClientType = radio.value;
                            thisGrid.applyColumnsAndOtherSettings();

                            Ext.state.Manager.clear(thisGrid.filterCookieId);
                        }
                    }
                }
            }, {
                xtype: 'box',
                width: 30,
                autoEl: {
                    tag: 'div',
                    html: '&nbsp;'
                }
            }, {
                boxLabel: _('Employer Cases'),
                name: 'filter_client_type_radio',
                xtype: 'radio',
                value: 'employer',
                checked: this.filterClientType === 'employer',
                listeners: {
                    check: function (radio, booChecked) {
                        if (booChecked) {
                            thisGrid.filterClientType = radio.value;
                            thisGrid.applyColumnsAndOtherSettings();

                            Ext.state.Manager.set(thisGrid.filterCookieId, radio.value);
                        }
                    }
                }
            }
        ]
    });

    this.tbar.add(this.radiosPanel);

    this.tbar.add(new Ext.Toolbar({
        items: [
            {
                id: this.activeCasesFieldId,
                xtype: 'checkbox',
                boxLabel: _('Active Cases Only'),
                hidden: config.panelType !== 'applicants',
                listeners: {
                    check: this.applyFilter.createDelegate(this, [true])
                }
            }, {
                html: '&nbsp',
                hidden: config.panelType !== 'applicants'
            },
            this.searchResultMenuBtn,
            {
                xtype: 'tbseparator',
                hidden: !booShowBulkChangesButton && !changeFieldsOptions['pull_from_queue']
            },
            this.filterByCaseTypeMenuBtn,
            {
                ref: '../../bulkChangesButton',
                text: '<i class="las la-user-edit"></i>' + _('Bulk Changes'),
                tooltip: _('Make changes to more than one selected ' + clientOrContactLabel + ' at the same time.'),
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
                ref: '../../pullCaseButton',
                text: '<i class="las la-user-edit"></i>' + _('Pull a Case'),
                tooltip: _('Pull a Case.'),
                hidden: !changeFieldsOptions['pull_from_queue'],
                scope: this,
                handler: this.pullFromQueue.createDelegate(this)
            }, {
                xtype: 'tbseparator',
                hidden: !booShowBulkChangesButton && !changeFieldsOptions['pull_from_queue']
            }, {
                xtype: 'button',
                text: '<i class="las la-print"></i>' + _('Print'),
                tooltip: _('Print all visible ' + clientOrContactLabel + 's.'),
                ref: '../../printButton',
                width: 70,
                hidden: !arrApplicantsSettings.access.queue['print'],
                handler: function () {
                    printTable('#' + thisGrid.getId() + ' div.x-grid3', _('Search Result'));
                }
            }, {
                xtype: 'button',
                text: '<i class="las la-file-export"></i>' + _('Export All'),
                tooltip: config.panelType === 'contacts' ? _('Create a spreadsheet of all your contacts.') : _('Create a spreadsheet of all your client files.'),
                ref: '../../exportAllButton',
                hidden: !arrApplicantsSettings.access.queue['export'],
                scope: this,
                menu: {
                    cls: 'no-icon-menu',
                    items: [
                        {
                            text: _('Export All to Excel (XLS)'),
                            handler: function () {
                                thisGrid._exportToExcel(thisGrid, thisGrid.exportAllButton, 'xls');
                            }
                        }, {
                            text: _('Export All to Excel (CSV)'),
                            handler: function () {
                                thisGrid._exportToExcel(thisGrid, thisGrid.exportAllButton, 'csv');
                            }
                        }
                    ]
                }
            }, {
                text: '<i class="las la-mail-bulk"></i>' + _('Mass Email'),
                tooltip: _('Email more than one selected ' + clientOrContactLabel + ' at the same time.'),
                ref: '../../emailTemplateButton',
                hidden: config.booHideMassEmailing || !allowedPages.has('email') || !allowedPages.has('templates-view'),
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
                    var contextId = config.panelType === 'contacts' ? 'contacts-list' : 'clients-list';
                    showHelpContextMenu(this.getEl(), contextId);
                }
            }
        ]
    }));

    this.bbar = new Ext.PagingToolbar({
        hidden: config.booHideGridToolbar ? true : false,
        pageSize: this.clientsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: _('Displaying records {0} - {1} of {2}'),
        emptyMsg: _('No records to display')
    });

    ApplicantsQueueGrid.superclass.constructor.call(this, {
        cls: 'search-result',
        loadMask: {msg: _('Loading...')},
        sm: sm,
        autoWidth: true,
        height: initPanelSize() - 35,
        stripeRows: true,

        view: new Ext.grid.GroupingView({
            deferEmptyText: _('There are no records to show.'),
            emptyText: _('There are no records to show.'),
            forceFit: false, // use the static column width, so users can resize and the scroller will be shown
            showGroupName: false,
            groupTextTpl: '<div class="employer-name employer-id-{gvalue}">{text}</div>'
        }),

        listeners: {
            afterrender: function () {
                var oView = this.view;
                var oBody = oView.mainBody;
                Ext.EventManager.removeListener(oBody, 'mousedown', oView.interceptMouse);
                oBody.on('mousedown', function (e) {
                    var hd = e.getTarget('.x-grid-group-hd', oBody);
                    if (hd) {
                        e.stopEvent();

                        // Check if the click was on the employer name or on the plus/minus icon
                        var employerLink = e.getTarget('.employer-name', oBody);
                        if (employerLink) {
                            var exploded = $(employerLink).attr('class').split(' ');
                            $.each(exploded, function (index, value) {
                                if (/^employer-id-\d+/.test(value)) {
                                    // Successful match
                                    var arrExplodedClass = value.split('employer-id-');
                                    var employerId = parseInt(arrExplodedClass[1], 10);

                                    thisGrid.getStore().each(function (oRecord) {
                                        if (oRecord.data.applicant_id == employerId) {
                                            thisGrid.openMemberTab(oRecord, true);
                                            return false;
                                        }
                                    });
                                }
                            });
                        } else {
                            oView.toggleGroup(hd.parentNode);
                        }
                    }
                }, oView);
            }
        }
    });

    this.on('cellclick', this.onThisCellClick.createDelegate(this));
};

Ext.extend(ApplicantsQueueGrid, Ext.grid.GridPanel, {
    fixThisGridHeight: function () {
        var thisGrid = this;

        // Update grid's height in relation to the visible items in the toolbar
        setTimeout(function () {
            var newHeight = initPanelSize() - thisGrid.radiosPanel.getHeight() - 10;
            if (!Ext.getCmp(thisGrid.selectedQueueFieldId).isVisible()) {
                newHeight += thisGrid.tbar.getHeight() - 125;
            }
            thisGrid.setHeight(newHeight);
        }, 50);
    },

    openChangeOptionsDialog: function (booAllRecords) {
        var grid = this,
            arrSelectedClients = grid.getSelectionModel().getSelections();

        if ((!booAllRecords && arrSelectedClients.length <= 0) || (booAllRecords && this.store.reader.jsonData.all_ids.length == 0)) {
            Ext.simpleConfirmation.warning('No clients are selected.<br/>Please select at least one client.');
        } else {
            var arrSelectedClientIds = [];
            if (booAllRecords) {
                arrSelectedClientIds = this.store.reader.jsonData.all_ids;
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
                panelType: grid.panelType,
                arrSelectedClientIds: arrSelectedClientIds,
                onSuccessUpdate: function (selectedOption, arrCheckedCheckboxesLabels) {
                    var msg = String.format(
                        '{0} successfully pushed to {1} {2}',
                        arrSelectedClientIds.length > 1 ? arrSelectedClientIds.length + ' clients were' : '1 client was',
                        arrCheckedCheckboxesLabels.join(', '),
                        arrCheckedCheckboxesLabels.length > 1 ? arrApplicantsSettings.office_label + 's' : arrApplicantsSettings.office_label
                    );
                    Ext.simpleConfirmation.msg(_('Success'), msg, 1500);

                    grid.refreshOnSuccess();
                }
            }, this);
            wnd.show();
            wnd.center();
        }
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

        options.params = options.params || {};

        var arrCaseTypes = [];
        Ext.each(this.filterByCaseTypeMenuBtn.menu.items.items, function (item) {
            if (item.checked) {
                arrCaseTypes.push(item.caseTypeId);
            }
        });

        var oParams = this.getFieldValues();
        oParams.caseTypes = Ext.encode(arrCaseTypes);
        oParams.panelType = Ext.encode(this.panelType);

        Ext.apply(store.baseParams, oParams);
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
        this.bulkChangesButton.setDisabled(booDisable);
        this.printButton.setDisabled(booDisable);
        this.exportAllButton.setDisabled(booDisable);

        // Turn on/off the grouping + Employer column
        var employerColumnIndex = thisGrid.getColumnModel().getIndexById(thisGrid.employerColumnId);
        if (this.filterClientType === 'individual') {
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

        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    },

    sortByName: function (a, b) {
        var aName = a.field_name.toLowerCase();
        var bName = b.field_name.toLowerCase();
        return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
    },

    // Update columns in relation to the selected option in the 'Search for' combo
    applyColumns: function (arrColumnsList) {
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
                hidden: thisGrid.panelType === 'contacts',
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

        var arrReaderFields = [
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

        if (arrColumnsList === undefined) {
            arrColumnsList = [];
        }

        // Try to load from cookie
        var arrSavedSettings = Ext.state.Manager.get(thisGrid.columnsCookieId);
        var arrSavedColumnsWidth = [];
        var oSortingSettings = {};
        try {
            arrSavedColumnsWidth = arrSavedSettings['arrWidth'];
            oSortingSettings = arrSavedSettings['sort'];
        } catch (e) {
        }

        // If not default columns list is not provided - try to load from cookies
        if (!arrColumnsList.length) {
            var arrSaved = [];
            try {
                arrSaved = arrSavedSettings['ids'];
            } catch (e) {
            }
            if (arrSaved && arrSaved.length) {
                arrColumnsList = arrSaved;
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
                        thisGrid.applyFilter(true);
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
                    for (var templateId in arrApplicantsSettings.case_group_templates) {
                        if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                            Ext.each(arrApplicantsSettings.case_group_templates[templateId], function (group) {
                                Ext.each(group.fields, function (field) {
                                    if (!arrGroupedFieldNames.has(field['field_name'])) {
                                        field.field_grouped_id = oClientType.search_for_id + '_' + field.field_unique_id;
                                        arrGroupedFields.push(field);
                                        arrGroupedFieldNames.push(field['field_name']);
                                    }
                                });
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
                    var booShowColumn = arrColumnsList.length ? arrColumnsList.has(colIndex) : arrGroupedFields[j]['field_column_show'];

                    // Load column's width from cookies
                    var columnWidth = thisGrid.defaultColumnWidth;
                    if (arrSavedColumnsWidth.length) {
                        for (var i = 0; i < arrSavedColumnsWidth.length; i++) {
                            if (!empty(arrSavedColumnsWidth[i]) && arrSavedColumnsWidth[i]['idx'] == colIndex) {
                                columnWidth = arrSavedColumnsWidth[i]['width'];
                                break;
                            }
                        }
                    }

                    // Show only allowed columns.
                    // Column will be showed if:
                    // 1. It is saved in cookie (for default blank searches)
                    // 2. It is saved in search details (saved search)
                    // 3. For default searches, if this is 'default column'
                    arrColumnsToSort.push({
                        header: arrGroupedFields[j]['field_name'],
                        hidden: !booShowColumn,
                        width: columnWidth,
                        sortable: true,
                        dataIndex: colIndex,
                        renderer: function (name) {
                            if (this.dataIndex.match(/^tag_percentage_/) && !empty(name)) {
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
        thisGrid.searchResultMenuBtn.menu = newMenu;

        // Filter by 'case type' menu
        // Use saved list of case types (if saved)
        var cookieName = 'filter_by_case_type_menu';
        var saved = Ext.state.Manager.get(cookieName);
        if (empty(saved) || !Array.isArray(saved)) {
            saved = null;
        }

        var filterByCaseTypeMenuItems = [];
        if (arrApplicantsSettings.case_templates.length) {
            Ext.each(arrApplicantsSettings.case_templates, function (caseTemplate) {
                filterByCaseTypeMenuItems.push(new Ext.menu.CheckItem({
                    text: caseTemplate.case_template_name,
                    caseTypeId: caseTemplate.case_template_id,
                    enableScrolling: false,
                    checked: empty(saved) ? true : saved.has(caseTemplate.case_template_id),
                    hideOnClick: false,
                    checkHandler: function (item, e) {
                        // Save selected case types and use this list the next time
                        var arrChecked = [];
                        thisGrid.filterByCaseTypeMenuBtn.menu.items.each(function (oItem) {
                            if (oItem.getXType() === 'menucheckitem' && oItem.checked) {
                                arrChecked.push(oItem.caseTypeId);
                            }
                        });

                        if (arrChecked.length && arrChecked.length != arrApplicantsSettings['case_templates'].length) {
                            Ext.state.Manager.set(cookieName, arrChecked);
                        } else {
                            Ext.state.Manager.clear(cookieName);
                        }
                    }
                }));
            });
        }

        var filterBtn = new Ext.Button({
            text: '<i class="las la-filter"></i>' + _('Filter'),
            cls: 'blue-btn'
        });
        filterByCaseTypeMenuItems.push(filterBtn);

        thisGrid.filterByCaseTypeMenuBtn.menu = new Ext.menu.Menu({
            enableScrolling: false,
            items: filterByCaseTypeMenuItems,
            listeners: {
                'show': function () {
                    // Use the full available width
                    var newWidth = Math.max(thisGrid.filterByCaseTypeMenuBtn.menu.getWidth(), 200);
                    filterBtn.setWidth(newWidth);

                    // Apply click listener only once
                    $('#' + filterBtn.id + ':not(.bound)').addClass('bound').click(function () {
                        thisGrid.filterByCaseType()
                    });
                }
            }
        });

        // Sort records as they were saved before (e.g. if moved)
        function sortColumnsByCorrectOrder(a, b) {
            // Checkbox, move to the top
            if (empty(a.dataIndex)) {
                return -1;
            }

            if (arrColumnsList.has(a.dataIndex) && arrColumnsList.has(b.dataIndex)) {
                return arrColumnsList.indexOf(a.dataIndex) < arrColumnsList.indexOf(b.dataIndex) ? -1 : 1;
            } else if (arrColumnsList.has(a.dataIndex) && !arrColumnsList.has(b.dataIndex)) {
                return -1;
            } else if (!arrColumnsList.has(a.dataIndex) && arrColumnsList.has(b.dataIndex)) {
                return 1;
            }

            return 0;
        }

        // Sort all columns by name
        arrColumns = arrColumns.concat(arrColumnsToSort.sort(sortColumnsByCorrectOrder));

        var newColModel = new Ext.grid.ColumnModel({
            columns: arrColumns,
            defaultSortable: true,

            defaults: {
                menuDisabled: true
            },

            listeners: {
                'columnmoved': function () {
                    thisGrid.updateDefaultColumnsList();
                }
            }
        });

        var defaultSortField = this.panelType === 'contacts' ? 'contact_last_name' : 'case_file_number';
        var defaultSortDirection = this.panelType === 'contacts' ? 'ASC' : 'DESC';
        if (!empty(oSortingSettings) && oSortingSettings.hasOwnProperty('field') && !empty(oSortingSettings.field)) {
            defaultSortField = oSortingSettings.field;
            defaultSortDirection = oSortingSettings.direction;
        }

        var store = new Ext.data.GroupingStore({
            url: thisGrid.runQueryUrl,
            autoLoad: false,
            remoteSort: true,
            groupField: 'employer_member_id',

            sortInfo: {
                field: defaultSortField,
                direction: defaultSortDirection
            },

            baseParams: {
                start: 0,
                limit: thisGrid.clientsOnPage
            },

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count'
            }, arrReaderFields)
        });

        store.on('beforeload', thisGrid.applyParams.createDelegate(thisGrid));
        store.on('load', thisGrid.checkLoadedResult.createDelegate(thisGrid));
        store.on('loadexception', thisGrid.checkLoadedResult.createDelegate(thisGrid));

        thisGrid.getBottomToolbar().bind(store);
        thisGrid.reconfigure(store, newColModel);

        // When column is resized - save column's width to cookies, so will be loaded later
        thisGrid.on('columnresize', thisGrid.updateDefaultColumnsList.createDelegate(thisGrid));
        thisGrid.on('sortchange', thisGrid.updateDefaultColumnsList.createDelegate(thisGrid));
    },

    updateDefaultColumnsList: function () {
        if (empty(this.savedSearchId)) {
            Ext.state.Manager.set(this.columnsCookieId, {
                ids: this.getColumnIds(),
                arrWidth: this.getShowedColumnsWidth(),
                sort: this.getSortingSettings()
            });
        }

        if (typeof this.saveQueueTabSettings !== 'undefined') {
            var arrSelectedColumns = this.getColumnIds();
            if (this.filterClientType === 'individual') {
                this.saveQueueTabSettings(arrSelectedColumns);
            } else {
                this.saveQueueTabSettings(null, arrSelectedColumns);
            }
        }
    },

    onThisCellClick: function (grid, rowIndex, col) {
        // click on checkbox doesn't have to open tab ;)
        if (col === 0) {
            return;
        }

        this.openMemberTab(grid.getStore().getAt(rowIndex), false);
    },

    openMemberTab: function (rec, booForceToOpenEmployer) {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');

        if (!empty(rec.data.case_id) && !booForceToOpenEmployer) {
            var oSettings = {
                applicantId: rec.data.applicant_id,
                applicantName: rec.data.applicant_name,
                memberType: rec.data.applicant_type,
                caseId: rec.data.case_id,
                caseName: rec.data.case_name,
                caseType: rec.data.case_type
            };

            if (rec.data.applicant_type == 'employer') {
                oSettings['caseEmployerId'] = rec.data.applicant_id;
                oSettings['caseEmployerName'] = rec.data.applicant_name;
            } else if (!empty(rec.data.employer_id)) {
                oSettings['caseEmployerId'] = rec.data.employer_id;
                oSettings['caseEmployerName'] = rec.data.employer_name;
            }

            tabPanel.openApplicantTab(oSettings, 'profile');
        } else {
            tabPanel.openApplicantTab({
                applicantId: rec.data.applicant_id,
                applicantName: rec.data.applicant_name,
                memberType: rec.data.applicant_type
            }, 'profile');
        }
    },

    refreshOnSuccess: function () {
        // Reload current grid
        this.getStore().load();

        // Reload left panel(s)
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        tabPanel.refreshClientsList(this.panelType, 0, 0, false);
    },

    filterByCaseType: function () {
        var arrCaseTypes = [];
        Ext.each(this.filterByCaseTypeMenuBtn.menu.items.items, function (item) {
            if (item.checked) {
                arrCaseTypes.push(item.caseTypeId);
            }
        });
        this.getStore().load({params: {caseTypes: Ext.encode(arrCaseTypes)}});
    },

    pullFromQueue: function () {
        // Show dialog
        var wnd = new ApplicantsQueuePullFromWindow({
            onSuccessUpdate: this.refreshOnSuccess.createDelegate(this)
        }, this);
        wnd.show();
        wnd.center();
    },

    saveQueueTabSettings: function (arrIndividualColumnIds, arrEmployerColumnIds, arrOfficesIds, booShowIndividualActiveCases, booShowEmployerActiveCases) {
        var thisGrid = this;
        if (thisGrid.panelType !== 'applicants') {
            return;
        }

        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/queue/save-settings',
            params: {
                arrIndividualColumnIds: Ext.encode(arrIndividualColumnIds),
                arrEmployerColumnIds: Ext.encode(arrEmployerColumnIds),
                arrOfficesIds: Ext.encode(arrOfficesIds),
                booShowIndividualActiveCases: Ext.encode(booShowIndividualActiveCases),
                booShowEmployerActiveCases: Ext.encode(booShowEmployerActiveCases)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (!resultData.success) {
                    Ext.simpleConfirmation.error(resultData.message);
                } else {
                    // Save in js too - to prevent additional requests
                    if (!empty(arrIndividualColumnIds)) {
                        arrApplicantsSettings.queue_settings.queue_individual_columns = arrIndividualColumnIds;
                    }

                    // Save in js too - to prevent additional requests
                    if (!empty(arrEmployerColumnIds)) {
                        arrApplicantsSettings.queue_settings.queue_employer_columns = arrEmployerColumnIds;
                    }

                    if (!empty(arrOfficesIds)) {
                        arrApplicantsSettings.queue_settings.queue_selected = arrOfficesIds;

                        var grid = Ext.getCmp(thisGrid.panelType + '-panel').ApplicantsQueueLeftSectionPanel.ApplicantsQueueLeftSectionGrid;
                        if (grid) {
                            grid.updateFavoriteOfficeClientsCount(arrOfficesIds.split(','));
                        }
                    }

                    if (booShowIndividualActiveCases !== null) {
                        arrApplicantsSettings.queue_settings.queue_individual_show_active_cases = booShowIndividualActiveCases;
                    }

                    if (booShowEmployerActiveCases !== null) {
                        arrApplicantsSettings.queue_settings.queue_employer_show_active_cases = booShowEmployerActiveCases;
                    }
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Settings cannot be saved. Please try again later.'));
            }
        });
    },

    loadSettings: function () {
        // Set main params
        var combo = Ext.getCmp(this.selectedQueueFieldId);
        var comboLabel = Ext.getCmp(this.selectedQueueFieldLabelId);
        var officesDisplayField = Ext.getCmp(this.selectedQueueDisplayFieldId);

        if (combo) {
            combo.setDisabled(false);
            combo.getStore().loadData(arrApplicantsSettings.queue_settings.queue_allowed);

            var arrDefaultRecords = Ext.getCmp(this.panelType + '-panel').getDefaultOffices();
            if (empty(arrDefaultRecords) || (arrDefaultRecords.length === 1 && arrDefaultRecords[0] === 'favourite')) {
                combo.setValue(arrApplicantsSettings.queue_settings.queue_selected);
            } else {
                // If "All" is selected - check all offices
                if (arrDefaultRecords.length === 1 && arrDefaultRecords[0] === 0) {
                    arrDefaultRecords = [];
                    combo.store.each(function (rec) {
                        arrDefaultRecords.push(rec.data[combo['valueField']]);
                    });
                }

                combo.setValue(arrDefaultRecords);

                officesDisplayField.setVisible(true);
                comboLabel.setVisible(false);
                combo.setVisible(false);
            }

            this.applyOfficeComboSelectedValues();
        }

        this.applyColumnsAndOtherSettings();
    },

    applyColumnsAndOtherSettings: function () {
        // Refresh columns list
        var arrColumns = [];
        if (this.panelType === 'applicants') {
            var defaultStatusCheckboxValue;
            if (this.filterClientType === 'individual') {
                arrColumns = arrApplicantsSettings.queue_settings.queue_individual_columns;
                defaultStatusCheckboxValue = arrApplicantsSettings.queue_settings.queue_individual_show_active_cases;
            } else {
                arrColumns = arrApplicantsSettings.queue_settings.queue_employer_columns;
                defaultStatusCheckboxValue = arrApplicantsSettings.queue_settings.queue_employer_show_active_cases;
            }

            var activeCasesCheckbox = Ext.getCmp(this.activeCasesFieldId);
            if (activeCasesCheckbox) {
                // Don't try to reload the grid twice
                activeCasesCheckbox.suspendEvents();
                activeCasesCheckbox.setValue(defaultStatusCheckboxValue);
                activeCasesCheckbox.resumeEvents();
            }
        } else {
            arrColumns = arrApplicantsSettings.queue_settings.queue_contacts_columns;
        }

        this.applyColumns(arrColumns);
        this.store.removeAll(true);

        // Run search!
        this.applyFilter(false);
    },

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

    getShowedColumnsWidth: function () {
        var cols = [];
        var columns = this.getColumnModel().config;
        var defaultWidth = this.defaultColumnWidth;
        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex) && columns[i].width != defaultWidth) {
                cols.push({
                    'idx': columns[i].dataIndex,
                    'width': columns[i].width
                });
            }
        }

        return cols;
    },

    getSortingSettings: function () {
        return this.getStore().getSortState();
    },

    getAllChildren: function (panel, container) {
        /*Get children of passed panel or an empty array if it doesn't have them.*/
        var children = panel.items ? panel.items.items : [];
        /*For each child get their children and concatenate to result.*/
        Ext.each(children, function (child) {
            children = children.concat(container.getAllChildren(child, container));
        });
        return children;
    },

    checkIsValidSearch: function () {
        var booIsValidForm = true;
        var fields = this.getAllChildren(this, this);
        Ext.each(fields, function (item) {
            if (typeof item.isValid !== 'undefined') {
                if (!item.isValid()) {
                    booIsValidForm = false;
                }
            }
        });

        return booIsValidForm;
    },

    applyOfficeComboSelectedValues: function () {
        var officesCombo = Ext.getCmp(this.selectedQueueFieldId);
        var officesDisplayField = Ext.getCmp(this.selectedQueueDisplayFieldId);

        if (officesDisplayField && officesCombo) {
            var showingLabel = '';
            var strSelectedOffices = '';
            var arrCheckedOffices = officesCombo.getCheckedValue().split(officesCombo.separator);
            if (arrCheckedOffices.length > 1 && arrCheckedOffices.length === officesCombo.store.getCount()) {
                showingLabel = arrApplicantsSettings.office_label + '(s):';
                strSelectedOffices = _('All');
            } else {
                showingLabel = arrApplicantsSettings.office_label + ':';
                strSelectedOffices = officesCombo.getCheckedDisplay();
            }

            strSelectedOffices = String.format(
                '<a href="#" onclick="return false;" title="{0}" class="not-real-link-big" /><span class="not-real-link-big-label">{1}</span>{2}</a>',
                showingLabel + strSelectedOffices,
                showingLabel,
                strSelectedOffices
            )
            officesDisplayField.setValue(strSelectedOffices);


            // Calculate the width of the entered text (selected offices)
            var metrics = Ext.util.TextMetrics.createInstance(officesCombo.getEl());
            var newComboWidth = metrics.getWidth(officesCombo.getCheckedDisplay()) + 60;

            // The width of the combo should not be less than the minimum width
            newComboWidth = Ext.max([newComboWidth, officesCombo.minWidth]);

            // And cannot be wider than max allowed width (of the tab - width of the label - paddings)
            var oLabel = Ext.getCmp(this.selectedQueueFieldLabelId);
            var maxWidth = this.owner.width - oLabel.getEl().getWidth() - 41;
            newComboWidth = Ext.min([newComboWidth, maxWidth]);

            officesCombo.setWidth(newComboWidth);
            officesCombo.getResizeEl().setWidth(newComboWidth);
        }
    },

    applyFilter: function (booSaveSettings) {
        if (this.checkIsValidSearch()) {
            if (booSaveSettings && Ext.getCmp(this.selectedQueueFieldId).isVisible()) {
                // Save selected offices only if there is at least one office selected
                var selectedOffices = Ext.getCmp(this.selectedQueueFieldId).getValue();
                if (!empty(selectedOffices)) {
                    var arrParams = [];
                    if (this.filterClientType === 'individual') {
                        arrParams = [null, null, selectedOffices, Ext.getCmp(this.activeCasesFieldId).getValue(), null];
                    } else {
                        arrParams = [null, null, selectedOffices, null, Ext.getCmp(this.activeCasesFieldId).getValue()];
                    }

                    this.store.on('load', this.saveQueueTabSettings.createDelegate(this, arrParams), this, {single: true});
                }
            }

            this.store.load();
        }
    },

    getVisibleColumnFields: function () {
        var arrUsedFieldIds = [];
        var cmModel = this.getColumnModel().config;
        for (var i = 0; i < cmModel.length; i++) {
            if (!cmModel[i].hidden && !empty(cmModel[i].dataIndex)) {
                arrUsedFieldIds.push(cmModel[i].dataIndex);
            }
        }

        return arrUsedFieldIds;
    },

    getFieldValues: function () {
        var arrCaseTypes = [];
        if (arrApplicantsSettings.case_templates.length) {
            Ext.each(arrApplicantsSettings.case_templates, function (caseTemplate) {
                arrCaseTypes.push(caseTemplate.case_template_id);
            });
        }

        return {
            arrColumns: Ext.encode(this.getVisibleColumnFields()),
            arrOffices: Ext.encode(Ext.getCmp(this.selectedQueueFieldId).getValue()),
            booShowActiveCases: Ext.encode(Ext.getCmp(this.activeCasesFieldId).checked),
            caseTypes: Ext.encode(arrCaseTypes),
            clientType: Ext.encode(this.filterClientType)
        };
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
        // Get visible columns
        var cm = [],
            arrUsedFieldIds = [];
        var cmModel = this.getColumnModel().config;
        // @NOTE: we skip first column because it is a checkbox
        for (var i = 1; i < cmModel.length; i++) {
            if (!cmModel[i].hidden) {
                cm.push({
                    id: cmModel[i].dataIndex,
                    name: cmModel[i].header,
                    width: cmModel[i].width
                });

                arrUsedFieldIds.push(cmModel[i].dataIndex);
            }
        }

        // Prepare all params (fields + sort info)
        var store = this.getStore();
        var allParams = store.baseParams;
        var arrSortInfo = {
            'sort': store.sortInfo.field,
            'dir': store.sortInfo.direction
        };
        Ext.apply(allParams, arrSortInfo);

        submit_hidden_form(baseUrl + '/applicants/queue/export-to-excel', {
            format: Ext.encode(format),
            arrColumns: Ext.encode(cm),
            arrStoreParams: Ext.encode(allParams),
            exportStart: Ext.encode(exportStart)
        });
    },

    applyNewOffices: function (arrQueuesToShow) {
        // Restore the radio state
        var thisGrid = this;
        var radios = thisGrid.radiosPanel.find('name', 'filter_client_type_radio');
        if (radios.length) {
            if (thisGrid.filterClientType == 'employer') {
                radios[0].setValue(false);
                radios[1].setValue(true);
            } else {
                radios[0].setValue(true);
                radios[1].setValue(false);
            }
        }

        var combo = Ext.getCmp(this.selectedQueueFieldId);
        var comboLabel = Ext.getCmp(this.selectedQueueFieldLabelId);
        var officesDisplayField = Ext.getCmp(this.selectedQueueDisplayFieldId);

        var newValue = '';
        if (arrQueuesToShow.length === 1 && arrQueuesToShow[0] === 'favourite') {
            comboLabel.setVisible(true);
            combo.setVisible(true);
            officesDisplayField.setVisible(false);

            newValue = arrApplicantsSettings.queue_settings.queue_selected;
        } else {
            officesDisplayField.setVisible(true);
            comboLabel.setVisible(false);
            combo.setVisible(false);

            // If "all" was passed - collect ALL office ids
            if (arrQueuesToShow.length === 1 && arrQueuesToShow[0] === 0) {
                arrQueuesToShow = [];
                combo.store.each(function (rec) {
                    arrQueuesToShow.push(rec.data[combo['valueField']]);
                });
            }

            newValue = implode(',', arrQueuesToShow);
        }

        combo.setValue(newValue);

        var r = combo.findRecord(combo.valueField, newValue);
        combo.fireEvent('select', combo, r, combo.store.indexOf(r));
    }
});