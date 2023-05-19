var prospectsAdvancedSearchGrid = function(config, owner) {
    var thisGrid = this;
    this.owner = owner;
    Ext.apply(this, config);

    var recordsOnPage = 50;

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            new Ext.grid.CheckboxSelectionModel()
        ],
        defaultSortable: true
    });

    this.thisStoreReader = new Ext.data.JsonReader({
        root: 'rows',
        totalProperty: 'totalCount',
        fields: this.getStoreReaderFields()
    });

    this.store = new Ext.data.Store({
        url: baseUrl + '/prospects/index/get-prospects-list',
        remoteSort: false,

        sortInfo: {
            field:     'qf_last_name',
            direction: 'ASC'
        },

        baseParams: {
            start: 0,
            limit: recordsOnPage,
            panelType: config.panelType,
            booLoadAllIds: config.panelType !== 'marketplace'
        },
        reader: this.thisStoreReader
    });

    this.searchResultMenuBtn = new Ext.Button({
        text: '<i class="las la-columns"></i>' + _('Select Columns'),
        menu: []
    });

    this.booIsDisabledMassEmail = !allowedPages.has('email') || !arrProspectSettings.arrAccess[config.panelType].tabs.advanced_search.mass_email;
    this.tbar = new Ext.Toolbar({
        items: [
            this.searchResultMenuBtn,

            {
                xtype: 'button',
                text: '<i class="las la-print"></i>' + _('Print'),
                ref: '../prospectsPrintButton',
                width: 70,
                hidden: !arrProspectSettings.arrAccess[config.panelType].tabs.advanced_search.print_all,
                handler: function () {
                    printTable('#' + thisGrid.getId() + ' div.x-panel-body', 'Search Result');
                }
            }, {
                xtype: 'button',
                ref: '../prospectsExportButton',
                text: '<i class="las la-file-export"></i>' + _('Export All to Excel'),
                disabled: true,
                hidden: !arrProspectSettings.arrAccess[config.panelType].tabs.advanced_search.export_all,
                handler: function() {
                    if (thisGrid.store.totalLength > arrProspectSettings.exportRange) {
                        var scrollMenu = new Ext.menu.Menu();
                        var start = 0;
                        var end   = 0;
                        var rangeCount = Math.ceil(thisGrid.store.totalLength / arrProspectSettings.exportRange);
                        for (var i = 0; i < rangeCount; ++i){
                            start = i * arrProspectSettings.exportRange + 1;
                            end   = (i+1) * arrProspectSettings.exportRange;
                            if (i == (rangeCount - 1)) {
                                end = thisGrid.store.totalLength;
                            }
                            scrollMenu.add({
                                text: 'Export ' + (start) + ' - ' + (end) + ' records',
                                listeners:{
                                    click: thisGrid.exportProspectsToExcel.createDelegate(thisGrid, [start - 1])
                                }
                            });
                        }

                        if (30 * i > Ext.getBody().getHeight()) {
                            scrollMenu.showAt([this.getEl().getX() + this.getWidth(), 0]);
                        } else {
                            scrollMenu.show(this.getEl())
                        }
                    } else {
                        thisGrid.exportProspectsToExcel(0);
                    }
                }
            }, {
                text: '<i class="las la-mail-bulk"></i>' + _('Mass Email'),
                ref: '../emailTemplateButton',
                hidden: this.booIsDisabledMassEmail,
                scope: this,
                handler: this.openMassMailsDialog.createDelegate(this, [true])
            }
        ]
    });

    this.bbar = new Ext.PagingToolbar({
        pageSize: recordsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: 'Displaying prospects {0} - {1} of {2}',
        emptyMsg: 'No prospects to display'
    });

    prospectsAdvancedSearchGrid.superclass.constructor.call(this, {
        sm: new Ext.grid.CheckboxSelectionModel(),
        split: true,
        stripeRows: true,
        loadMask: true,
        buttonAlign: 'left',
        autoScroll: true,
        cls: 'search-result',
        viewConfig: {
            deferEmptyText: false,
            emptyText: 'No prospects found.',
            forceFit: true
        }
    });

    this.store.on('beforeload', this.applyParams.createDelegate(this));
    this.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.store.on('loadexception', this.checkLoadedResult.createDelegate(this));
    this.on('cellclick', this.onCellClick.createDelegate(this), this);
    this.on('afterrender', this.applyColumns.createDelegate(this), this);
};

Ext.extend(prospectsAdvancedSearchGrid, Ext.grid.GridPanel, {
    onCellClick: function(grid, rowIndex, col) {
        // click on checkbox doesn't have to open tab ;)
        if (col === 0) {
            return;
        }

        var node = grid.getSelectionModel().getSelected();
        if(node) {
            var rec = grid.getStore().getAt(rowIndex);
            var tabPanel = Ext.getCmp(grid.panelType + '-tab-panel');
            tabPanel.loadProspectsTab({
                tab: 'prospect',
                tabId: grid.panelType + '-ptab-' + rec.data.prospect_id,
                pid: rec.data.prospect_id,
                title: rec.data.fName + ' ' + rec.data.lName
            });
        }
    },

    applyParams: function(store, options) {
        options.params = options.params || {};

        var params = {
            advanced_search_params: Ext.encode(this.owner.prospectsAdvancedSearchForm.getFieldValues()),
            advanced_search_fields: Ext.encode(this.getColumnIds())
        };

        Ext.apply(options.params, params);
    },

    getProspectSearchSettingsCookieName: function() {
        return 'prospects_adv_srch_cols';
    },

    getStoreDefaultFields: function() {
        return [
            'prospect_id',
            {
                name: 'qf_first_name', sortType: Ext.data.SortTypes.asUCString
            }, {
                name: 'qf_last_name', sortType: Ext.data.SortTypes.asUCString
            }, {
                name: 'qf_email', sortType: Ext.data.SortTypes.asUCString
            },
            'qf_create_date',
            'qf_update_date',
            'fName',
            'lName'
        ];
    },

    getStoreReaderFields: function() {
        var arrReaderFields = this.getStoreDefaultFields();

        arrProspectSettings.arrAdvancedSearchFields.map( function (oField) {
            var colIndex = oField['q_field_unique_id'];

            if (oField['q_field_type'] == 'full_date') {
                arrReaderFields.push({name: colIndex, type: 'date', dateFormat: Date.patterns.ISO8601Long});
            } else if (oField['q_field_type'] == 'date') {
                arrReaderFields.push({name: colIndex, type: 'date', dateFormat: Date.patterns.ISO8601Short});
            } else {
                arrReaderFields.push({name: colIndex, sortType: Ext.data.SortTypes.asUCString});
            }
        });

        return arrReaderFields;
    },

    applyColumns: function() {
        var thisGrid = this;

        var arrDefaultFields = this.getStoreDefaultFields();

        var arrColumns = [this.getSelectionModel()];

        // Try to load from cookie
        var arrColumnsList = [];
        var arrSaved = Ext.state.Manager.get(this.getProspectSearchSettingsCookieName());
        if(arrSaved && arrSaved.length) {
            arrColumnsList = arrSaved;
        }

        // Update Menu items too
        var newMenu = new Ext.menu.Menu({
            enableScrolling: false,
            refreshProspectsOnClose: false,

            listeners: {
                'hide': function () {
                    if (this.refreshProspectsOnClose) {
                        thisGrid.owner.prospectsAdvancedSearchForm.applyFilter();
                        this.refreshProspectsOnClose = false;
                    }
                }
            }
        });

        var arrGroups = {};
        arrProspectSettings.arrAdvancedSearchFields.map( function (oField) {
            var fieldLabel = empty(oField['q_field_prospect_profile_label']) ? oField['q_field_label'] : oField['q_field_prospect_profile_label'];
            var colIndex = oField['q_field_unique_id'];
            var booShowColumn = arrColumnsList.length ? arrColumnsList.has(colIndex) : (arrDefaultFields.length ? arrDefaultFields.has(colIndex) : false);

            // Group fields by groups
            var oCheckbox = new Ext.menu.CheckItem({
                text: fieldLabel,
                field_grouped_id: colIndex,
                checked: booShowColumn,
                hideOnClick: false,
                checkHandler: function(item, e) {
                    thisGrid.getColumnModel().setHidden(thisGrid.getColumnModel().findColumnIndex(item.field_grouped_id), !e);
                    thisGrid.updateDefaultColumnsList();

                    newMenu.refreshProspectsOnClose = true;
                }
            });

            if (oField['q_section_prospect_profile'] in arrGroups) {
                arrGroups[oField['q_section_prospect_profile']].push(oCheckbox);
            } else {
                arrGroups[oField['q_section_prospect_profile']] = [oCheckbox];
            }

            var oColumn = {
                header: fieldLabel,
                hidden: !booShowColumn,
                width: 150,
                sortable: true,
                dataIndex: colIndex
            };

            if (oField['q_field_type'] == 'full_date') {
                oColumn.width = 90;
                oColumn.renderer = Ext.util.Format.dateRenderer(dateFormatFull);
            } else if (oField['q_field_type'] == 'date') {
                oColumn.width = 90;
                oColumn.renderer = Ext.util.Format.dateRenderer(dateFormatFull);
            }

            // Show only allowed columns.
            // Column will be showed if:
            // 1. It is saved in cookie (for default blank searches)
            // 2. For default searches, if this is 'default column'
            arrColumns.push(oColumn);
        });

        for(var currentGroupName in arrGroups){
            if (arrGroups.hasOwnProperty(currentGroupName)) {
                // Add group and insert items
                newMenu.addMenuItem({
                    enableScrolling: false,
                    text: currentGroupName,
                    menu: arrGroups[currentGroupName]
                });
            }
        }

        thisGrid.searchResultMenuBtn.menu = newMenu;

        var newColModel = new Ext.grid.ColumnModel({
            columns: arrColumns,
            defaultSortable: true,
            listeners: {
                'columnmoved': function() {
                    thisGrid.updateDefaultColumnsList();
                }
            }
        });

        thisGrid.reconfigure(thisGrid.store, newColModel);
    },

    getColumnIds: function() {
        var cols = [];
        var columns = this.getColumnModel().config;
        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex)) {
                cols.push(columns[i].dataIndex);
            }
        }

        return cols;
    },

    updateDefaultColumnsList: function() {
        Ext.state.Manager.set(this.getProspectSearchSettingsCookieName(), this.getColumnIds());
    },

    exportProspectsToExcel: function(exportStart) {
        var resGrid = this;
        if (resGrid) {
            //get visible columns
            var cm = [];
            var cmModel = resGrid.getColumnModel().config;
            // @NOTE: we skip first column because it is a checkbox
            for (var i = 1; i < cmModel.length; i++) {
                if (!cmModel[i].hidden) {
                    cm.push({id: cmModel[i].dataIndex, name: cmModel[i].header, width: cmModel[i].width});
                }
            }

            // Prepare all params (fields + sort info)
            var store       = resGrid.getStore();
            var allParams   = store.baseParams;
            var arrSortInfo = {
                'sort': store.sortInfo.field,
                'dir':  store.sortInfo.direction
            };
            Ext.apply(allParams, arrSortInfo);

            var oAllParams = {
                cm:                     Ext.encode(cm),
                advanced_search_params: Ext.encode(resGrid.owner.prospectsAdvancedSearchForm.getFieldValues()),
                exportStart:            Ext.encode(exportStart),
                exportRange:            Ext.encode(arrProspectSettings.exportRange)
            };

            for (i in allParams) {
                if (allParams.hasOwnProperty(i)) {
                    oAllParams[i] = allParams[i];
                }
            }

            submit_hidden_form(baseUrl + '/prospects/index/export-to-excel', oAllParams);
        }
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

    checkLoadedResult: function() {
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

        var booDisableButtons = thisGrid.store.getCount() < 1;
        thisGrid.searchResultMenuBtn.setDisabled(booDisableButtons);
        thisGrid.prospectsPrintButton.setDisabled(booDisableButtons);
        thisGrid.prospectsExportButton.setDisabled(booDisableButtons);
        thisGrid.emailTemplateButton.setDisabled(booDisableButtons);

        thisGrid.fixProspectsAdvancedSearchGridHeight();

        var tabPanel = Ext.getCmp(thisGrid.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    },

    fixProspectsAdvancedSearchGridHeight: function () {
        var thisGrid = this;
        // Don't try to fix the height if the grid is hidden
        if (!thisGrid.isVisible()) {
            return;
        }

        var newGridHeight = initPanelSize() -
            $('#' + thisGrid.owner.backToProspectsButton.id).outerHeight() -
            $('#' + thisGrid.owner.prospectsAdvancedSearchFormContainer.id).outerHeight() -
            70;

        var oneRowRecordHeight = 39;
        // Show at least 10 records if we have more than 5 records in the result
        var recordsCount = Ext.min([thisGrid.store.getCount(), 5]);

        // At least show 2 records (so "no records" will be correctly visible too)
        recordsCount = Ext.max([recordsCount, 2]);

        // At the same time, if we have more space - we can use it
        newGridHeight = Ext.max([(recordsCount * oneRowRecordHeight) + 80, newGridHeight]);

        thisGrid.setHeight(newGridHeight);
        thisGrid.doLayout();
    }
});
