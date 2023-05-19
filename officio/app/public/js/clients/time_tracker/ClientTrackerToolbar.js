var ClientTrackerToolbar = function(viewer, config) {
    this.viewer = viewer;
    this.booCompanies = viewer.booCompanies;
    Ext.apply(this, config);

    ClientTrackerToolbar.superclass.constructor.call(this, {
        enableOverflow: true,

        items: [
            {
                text: '<i class="las la-plus"></i>' + _('New Time Entry'),
                tooltip: _('Add a time entry for this client.'),
                cls: 'main-btn',
                hidden: !this.hasAccess('add'),
                ref: 'trackerCreateBtn',
                scope: this,
                handler: this.createTimeTracker
            }, {
                text: '<i class="lar la-edit"></i>' + _('Edit'),
                tooltip: _('Edit selected time entry.'),
                hidden: !this.hasAccess('edit'),
                ref: 'trackerEditBtn',
                scope: this,
                disabled: true,
                handler: this.editTimeTracker
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                tooltip: _('Delete selected time entry.'),
                hidden: !this.hasAccess('delete'),
                ref: 'trackerDeleteBtn',
                scope: this,
                disabled: true,
                handler: this.deleteTimeTracker
            }, {
                xtype: 'tbseparator',
                hidden: !this.hasAccess('add') && !this.hasAccess('edit') && !this.hasAccess('delete')
            },  {
                text: '<i class="las la-print"></i>' + _('Print'),
                tooltip: _('Print all time entries.'),
                ref: 'trackerPrintBtn',
                scope: this,
                disabled: true,
                handler: this.printTimeTracker
            }, {
                xtype: 'tbseparator',
                hidden: this.booCompanies || is_client
            }, {
                text: '<i class="las la-check"></i>' + _('Mark as Billed'),
                tooltip: _('Mark selected time entry as billed.'),
                hidden: this.booCompanies || is_client,
                ref: 'trackerMarkBtn',
                disabled: true,
                scope: this,
                handler: this.markAsBilledTimeTracker
            }, '->', {
                text: '<i class="las la-filter"></i>' + _('Filter'),
                tooltip: _('Organize by "Billed Time", "User", or "Date Range".'),
                ref: 'trackerFilterBtn',
                scope: this,
                handler: this.toggleClientTrackerFilter
            }, {
                text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
                xtype: 'button',
                scope: this,
                handler: this.refreshClientTrackerGrid
            }, {
                xtype: 'button',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: typeof allowedPages === 'undefined' || !allowedPages.has('help'),
                handler: function () {
                    showHelpContextMenu(this.getEl(), 'clients-time-log');
                }
            }
        ]
    });
};

Ext.extend(ClientTrackerToolbar, Ext.Toolbar, {
    /**
     * Check access rights passed from server
     * @param action string we need to check
     * @return boolean true if current user has access rights
     */
    hasAccess: function(action) {
        return typeof arrTimeTrackerSettings != 'undefined' && arrTimeTrackerSettings.access.has(action);
    },

    refreshClientTrackerGrid: function() {
        this.viewer.ClientTrackerGrid.store.reload();
    },

    toggleClientTrackerFilter: function() {
        this.viewer.ClientTrackerFilterPanel.toggleCollapse(true);
    },

    updateToolbarButtons: function () {
        var viewer = this.viewer;
        var sel = viewer.ClientTrackerGrid.getSelectionModel().getSelections();
        var booIsSelectedOne = sel.length === 1;
        var booIsSelectedAtLeastOne = sel.length >= 1;
        var booAreLoadedRecords = viewer.ClientTrackerGrid.store.getCount() > 0;
        var booEnableMarkBtn = false;
        for (var i = 0; i < sel.length; i++) {
            if (sel[i].data.track_billed !== 'Y') {
                booEnableMarkBtn = true;
            }
        }

        if (viewer.ClientTrackerToolbar['trackerPrintBtn']) {
            viewer.ClientTrackerToolbar['trackerPrintBtn'].setDisabled(!booAreLoadedRecords);
        }

        if (viewer.ClientTrackerToolbar['trackerEditBtn']) {
            viewer.ClientTrackerToolbar['trackerEditBtn'].setDisabled(!booIsSelectedOne);
        }

        if (viewer.ClientTrackerToolbar['trackerDeleteBtn']) {
            viewer.ClientTrackerToolbar['trackerDeleteBtn'].setDisabled(!booIsSelectedAtLeastOne);
        }

        if (viewer.ClientTrackerToolbar['trackerMarkBtn']) {
            viewer.ClientTrackerToolbar['trackerMarkBtn'].setDisabled(!booEnableMarkBtn);
        }
    },

    createTimeTracker: function() {
        var dialog = new ClientTrackerAddDialog(
            {
                action:     'add',
                timeActual: 0,
                clientId:   this.viewer.clientId,
                panelType:  this.viewer.panelType,
                companyId:  this.viewer.companyId
            },
            this.viewer, this.booCompanies
        );

        dialog.show();
        dialog.center();
        dialog.syncShadow();
    },

    editTimeTracker: function() {
        // populate dialog with data from row
        var sel = this.viewer.ClientTrackerGrid.getSelectionModel().getSelections();

        if (sel.length>0)
        {
            var row_data = sel[0].data;
            var dialog = new ClientTrackerAddDialog(
                {
                    action:    'edit',
                    timeActual: row_data.track_time_actual,
                    clientId:   this.viewer.clientId,
                    panelType:  this.viewer.panelType,
                    companyId:  this.viewer.companyId,
                    trackInfo:  row_data
                },
                this.viewer, this.booCompanies
            );

            dialog.show();
            dialog.center();
            dialog.syncShadow();
        }
    },

    deleteTimeTracker: function() {
        var viewer = this.viewer;

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete selected items?', function (btn, text) {
            if (btn == 'yes') {
                var sel = viewer.ClientTrackerGrid.getSelectionModel().getSelections();

                var track_ids=[];
                Ext.each(sel, function(item){
                    track_ids.push(item.data.track_id);
                });

                Ext.getBody().mask('Deleting...');

                Ext.Ajax.request({
                    url: topBaseUrl+'/clients/time-tracker/delete/',
                    params: {
                        track_ids: Ext.encode(track_ids)
                    },
                    success: function(f) {
                        var resultData = Ext.decode(f.responseText);

                        if (resultData.success)
                        {
                            Ext.simpleConfirmation.msg('Info', 'Successfully deleted');
                            viewer.ClientTrackerGrid.store.reload();
                        }
                        else
                            Ext.simpleConfirmation.error(resultData.msg);

                        Ext.getBody().unmask();
                    },
                    failure: function() {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Internal error. Please, try again later');
                    }
                });
            }
        });
    },

    printTimeTracker: function() {
        var store=this.viewer.ClientTrackerGrid.store;

        var track_ids = store.reader.jsonData.allIds;

        var columns = this.viewer.ClientTrackerGrid.getColumnModel().config;
        var visibleColumns = [];

        for (var i = 0; i < columns.length; i++) {
            if (!columns[i].hidden && !empty(columns[i].dataIndex)) {
                visibleColumns.push(columns[i].dataIndex);
            }
        }

        Ext.getBody().mask('Generating print file...');

        var tabId = this.booCompanies ? 'company-tab-'+store.baseParams.companyId : 'ctab-client-'+store.baseParams.clientId;
        var tab=Ext.getCmp(tabId);

        var clientcompanyName='';
        if (tab)
            clientcompanyName=tab.title;
        else if (this.booCompanies && companyDetails)
            clientcompanyName=companyDetails.company_name;

        Ext.Ajax.request({
            url: topBaseUrl+'/clients/time-tracker/print/',
            params: {
                columns: Ext.encode(visibleColumns),
                track_ids: Ext.encode(track_ids),
                sort_where: store.sortInfo.direction,
                sort: store.sortInfo.field,
                title: Ext.encode(clientcompanyName)
            },
            success: function(f) {
                var resultData = f.responseText;
                print(resultData, 'Time Log');

                Ext.getBody().unmask();
            },
            failure: function() {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Internal error. Please, try again later');
            }
        });
    },

    markAsBilledTimeTracker: function() {
        var sel=this.viewer.ClientTrackerGrid.getSelectionModel().getSelections();
        var has_ta=false;
        Ext.each(sel, function(item){
            if (item.data.track_billed==='N' && item.data.ta_ids.length>0)
                has_ta=true;
        });

        // show window only, if client has TAs
        if (has_ta)
        {
            var markDialog = new ClientTrackerMarkDialog(this.viewer.ClientTrackerGrid);
            markDialog.processDialog();
        }
        else
            Ext.simpleConfirmation.error('Accounting record is not defined. Please go to Accounting tab to define your accounts.');
    }
});

Ext.reg('appClientTrackerToolbar', ClientTrackerToolbar);