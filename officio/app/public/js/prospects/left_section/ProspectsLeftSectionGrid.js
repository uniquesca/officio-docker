var ProspectsLeftSectionGrid = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.store = new Ext.data.Store({
        autoLoad: false,
        remoteSort: true,

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/queue/load-queues-with-count'
        }),

        baseParams: {
            panelType: this.panelType
        },

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'queueId',
            fields: ['queueId', 'queueName', 'queueClientsCount']
        })
    });
    this.store.setDefaultSort('queueName', 'DESC');
    this.store.on('load', this.preselectDefaultOffices.createDelegate(this), this, {single: true});

    var subjectColId = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [sm, {
        id: subjectColId,
        header: _('Name'),
        dataIndex: 'queueName',
        sortable: true,
        width: 300,
        renderer: function (val, a, row) {
            var name = row.data.queueName;
            if (!empty(row.data.queueClientsCount)) {
                name += ' (' + row.data.queueClientsCount + ')';
            }

            var nameAndIcon = name;
            // if (empty(row.data['queueId']) || row.data['queueId'] === 'favourite') {
            //     nameAndIcon = '<i class="lar la-star"></i>' + nameAndIcon;
            // }

            return String.format('<a href="#" class="blulinkun norightclick" onclick="return false;" title="{0}" />{1}</a>', name, nameAndIcon);
        }
    }];

    ProspectsLeftSectionGrid.superclass.constructor.call(this, {
        sm: sm,
        cls: 'no-borders-grid no-selection-grid',
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,

        viewConfig: {
            emptyText: _('There are no records to show.'),
            getRowClass: this.applyRowClass,

            //  hack will make sure that there is no blank space if there is no scroller:
            scrollOffset: 18,
            onLayout: function () {
                // store the original scrollOffset
                if (!this.orgScrollOffset) {
                    this.orgScrollOffset = this.scrollOffset;
                }

                var scroller = Ext.select('#' + config.id + ' .x-grid3-scroller').elements[0];
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

    this.getSelectionModel().on('selectionchange', this.onSelectionChange, this);
    this.getSelectionModel().on('beforerowselect', this.onBeforeRowSelect, this);

    this.on('render', function (thisGrid) {
        // Load the store only when the grid was rendered -
        // fixes the issue with data preselecting
        thisGrid.store.load();
    });
};

Ext.extend(ProspectsLeftSectionGrid, Ext.grid.GridPanel, {
    // Apply custom class for "View All" row
    applyRowClass: function (record) {
        var cls = '';
        switch (record.data['queueId']) {
            case 'favourite':
                cls = 'row-top-favourite';
                break;

            case '':
            case 0:
                cls = 'row-top-padding';
                break;

            default:
                cls = '';
        }

        return cls;
    },

    onBeforeRowSelect: function (sm, rowIndex, keepExisting, record) {
        // Prevent firing unnecessary events
        // So we'll not try to refresh the grid several times
        sm.suspendEvents();

        // If All or Favorite filter is selected - only one this filter will be selected, all others - unselected
        // Uncheck All or Favorite filter if now we selected other filters
        var arrSelectedRows = sm.getSelections();
        var store = this.getStore();
        Ext.each(arrSelectedRows, function (oSelectedRow) {
            var booDeselect;
            if (!empty(record.id) && record.id !== 'favourite') {
                booDeselect = empty(oSelectedRow.id) || oSelectedRow.id === 'favourite';
            } else {
                booDeselect = oSelectedRow.id !== record.id;
            }

            if (booDeselect) {
                sm.deselectRow(store.indexOfId(oSelectedRow.id));
            }
        });

        sm.resumeEvents();
    },

    onSelectionChange: function (sm) {
        var arrSelected = [];
        var arrSelectedRows = sm.getSelections();
        Ext.each(arrSelectedRows, function (oSelectedRow) {
            if (empty(oSelectedRow.id)) {
                arrSelected = [0];
                return false;
            } else {
                arrSelected.push(oSelectedRow.id);
            }
        });

        if (arrSelected.length) {
            // Save to cookies
            Ext.state.Manager.set(this.panelType + '_checked_offices', arrSelected);

            var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
            if (tabPanel) {
                if (arrSelected[0] === 'favourite') {
                    // For favorites - we want to load the list from the combo
                    var oProspectPanel = Ext.getCmp(this.panelType + '-grid-panel');
                    var strSelected = oProspectPanel.activeFilterQueueCombo.getValue();

                    if (strSelected === '') {
                        // If nothing is checked - check all
                        oProspectPanel.activeFilterQueueCombo.selectAll();
                        oProspectPanel.activeFilterQueueCombo.clearInvalid();
                        strSelected = oProspectPanel.activeFilterQueueCombo.getValue();
                    }

                    arrSelected = strSelected.split(oProspectPanel.activeFilterQueueCombo.separator);
                }

                tabPanel.loadProspectsTab({tab: 'office', offices: arrSelected});
            }
        }
    },

    preselectDefaultOffices: function (store, records) {
        var arrRecordsToSelect = [];
        var arrDefaultRecords = [];
        if (this.arrQueuesToShow.length) {
            arrDefaultRecords = this.arrQueuesToShow;
        } else {
            // Load from cookies - previously what was checked
            arrDefaultRecords = this.owner.getDefaultOffices();
        }

        if (arrDefaultRecords.length && records.length) {
            Ext.each(records, function (oStoreRecord) {
                var booFound = false;
                Ext.each(arrDefaultRecords, function (defaultRecordId) {
                    if (defaultRecordId === oStoreRecord.id) {
                        booFound = true;
                        return false;
                    }
                });

                if (booFound) {
                    arrRecordsToSelect.push(oStoreRecord);
                }
            });
        }

        if (arrRecordsToSelect.length) {
            try {
                var sm = this.getSelectionModel();
                sm.suspendEvents();
                sm.selectRecords(arrRecordsToSelect);
                sm.resumeEvents();
            } catch (e) {
            }
        }
    },

    // Make sure that clients count is correct for the Favorite Offices record
    updateFavoriteOfficeClientsCount: function (arrFavoriteOffices) {
        var oFavoriteRecord = null;
        var favoriteOfficesClientsCount = 0;
        this.getStore().each(function (oRecord) {
            if (oRecord.id === 'favourite') {
                oFavoriteRecord = oRecord;
            } else if (!empty(oRecord.id) && arrFavoriteOffices.has(oRecord.id)) {
                favoriteOfficesClientsCount += parseInt(oRecord['data']['queueClientsCount'], 10);
            }
        });

        if (oFavoriteRecord && parseInt(oFavoriteRecord['data']['queueClientsCount'], 10) !== favoriteOfficesClientsCount) {
            oFavoriteRecord.set('queueClientsCount', favoriteOfficesClientsCount);
            oFavoriteRecord.commit();
        }
    },

    getSelectedOfficesLabels: function () {
        var arrSelected = [];
        var arrSelectedRows = this.getSelectionModel().getSelections();
        Ext.each(arrSelectedRows, function (oSelectedRow) {
            if (empty(oSelectedRow.id)) {
                arrSelected = [_('All')];
                return false;
            } else {
                arrSelected.push(oSelectedRow.data.queueName);
            }
        });

        return arrSelected.join(', ');
    },

    isFavoriteChecked: function () {
        var booIsFavoriteChecked = false;
        Ext.each(this.getSelectionModel().getSelections(), function (oSelectedRow) {
            if (oSelectedRow.id == 'favourite') {
                booIsFavoriteChecked = true;
            }
        });

        return booIsFavoriteChecked;
    }
});