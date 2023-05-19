var MarketplaceGrid = function (config, owner) {
    var thisGrid = this;
    Ext.apply(this, config);
    this.owner = owner;

    var recordsOnPage = 20;

    this.store = new Ext.data.Store({
        url:        baseUrl + '/marketplace/get-marketplace-profiles-list/',
        autoLoad:   true,
        remoteSort: true,
        baseParams: {
            start:      0,
            limit:      recordsOnPage,
            company_id: config.company_id
        },

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([
            {name: 'marketplace_profile_id'},
            {name: 'marketplace_profile_name'},
            {name: 'marketplace_profile_status'},
            {name: 'marketplace_profile_url'}
        ]))
    });

    var expandCol = Ext.id();

    this.sm = new Ext.grid.RowSelectionModel();
    this.cm = new Ext.grid.ColumnModel({
        columns: [
            {
                id:        expandCol,
                header:    'Profile name',
                dataIndex: 'marketplace_profile_name'
            }, {
                header:    'Status',
                dataIndex: 'marketplace_profile_status',
                renderer:  this.formatMPStatus.createDelegate(this),
                width:     400
            }, {
                header:    'Actions',
                dataIndex: 'marketplace_profile_name',
                align:     'center',
                width:     280,
                renderer:  this.renderActions.createDelegate(this)
            }
        ],

        defaultSortable: false
    });

    this.tbar = new Ext.Toolbar({
        items: [
            {
                text: '<i class="las la-plus"></i>' + _('Create new profile'),
                cls: 'main-btn',
                tooltip: arrMarketplaceAccessRights.create_new_profile ? _('Create new marketplace profile on MP web site') : _('Possible only for active companies'),
                disabled: !arrMarketplaceAccessRights.create_new_profile,
                handler: thisGrid.addProfile.createDelegate(thisGrid)
            }, '->', {
                text: '<i class="las la-undo-alt"></i>',
                tooltip: _('Refresh list of saved marketplace profiles'),
                handler: function () {
                    thisGrid.store.reload();
                }
            }
        ]
    });

    this.bbar = new Ext.PagingToolbar({
        pageSize: recordsOnPage,
        store: this.store,
        displayInfo: true,
        displayMsg: _('Displaying records {0} - {1} of {2}'),
        emptyMsg: _('No records to display')
    });

    MarketplaceGrid.superclass.constructor.call(this, {
        height:           getSuperadminPanelHeight() - 30,
        split:            true,
        stripeRows:       true,
        loadMask:         true,
        autoExpandColumn: expandCol,
        cls:              'extjs-grid',
        viewConfig:       {
            deferEmptyText: false,
            emptyText:      _('No records found.')
        }
    });

    this.on('contextmenu', function (e) {
        e.stopEvent();
    });

    // Reload store every 45 seconds
    var task   = {
        run: function () {
            thisGrid.store.load();
        },

        interval: 45000
    };
    var runner = new Ext.util.TaskRunner();
    runner.start(task);
};

Ext.extend(MarketplaceGrid, Ext.grid.GridPanel, {
    renderActions: function (val, p, record) {
        var strResult = '';

        var booAllowEdit = true;
        if (booAllowEdit) {
            var editButtonId = Ext.id();
            var oEditBtn     = {
                text:    '<i class="las la-pen"></i>' + _('View/Edit'),
                tooltip: 'View/Edit marketplace profile',
                style:   'float: left; margin-right: 10px;',
                width:   150,
                handler: this.editProfile.createDelegate(this, [this, record])
            };
            this.createGridButton.defer(1, this, [editButtonId, oEditBtn]);
            strResult += '<div id="' + editButtonId + '"></div>';
        }

        // Don't allow to add/changes status if status is suspended
        var booAllowChangeStatus = record.data.marketplace_profile_status != 'suspended';
        if (booAllowChangeStatus) {
            var toggleStatusButtonId = Ext.id();
            var booActive            = record.data.marketplace_profile_status == 'active';
            var oStatusBtn           = {
                text:    booActive ? _('Hide the Profile') : _('Publish the Profile'),
                tooltip: booActive ? _('Click to hide marketplace profile') : _('Click to publish marketplace profile'),
                width:   150,
                handler: this.toggleProfileStatus.createDelegate(this, [this, record])
            };
            this.createGridButton.defer(1, this, [toggleStatusButtonId, oStatusBtn]);
            strResult += '<div id="' + toggleStatusButtonId + '"></div>';
        }

        return empty(strResult) ? '-' : strResult;
    },

    createGridButton: function (contentId, oButtonConfig) {
        new Ext.Button(oButtonConfig).render(document.body, contentId);
    },

    formatMPStatus: function (status) {
        var strStatus;

        switch (status) {
            case 'active':
                strStatus = _('Profile visible on Marketplace');
                break;

            case 'inactive':
                strStatus = _('Profile hidden on Marketplace');
                break;

            case 'suspended':
                strStatus = _('Profile suspended on Marketplace');
                break;

            default:
                strStatus = _('Profile status is unknown.');
                break;
        }

        return strStatus;
    },

    addProfile: function () {
        var url = this.getStore().reader.jsonData.marketplace_new_profile_url;
        if (empty(url)) {
            Ext.simpleConfirmation.warning(_('Marketplace URL is empty. Please make sure that a correct setting is set in the config file.'));
        } else {
            window.open(url);
        }
    },

    editProfile: function (grid, record) {
        var url = record.data.marketplace_profile_url;
        if (empty(url)) {
            Ext.simpleConfirmation.warning(_('Marketplace URL is empty. Please make sure that a correct setting is set in the config file.'));
        } else {
            window.open(url);
        }
    },

    toggleProfileStatus: function (grid, record) {
        var thisGrid = this;

        Ext.getBody().mask('Processing...');
        Ext.Ajax.request({
            url:     baseUrl + '/marketplace/toggle-marketplace-profile-status',
            params:  {
                company_id:             Ext.encode(thisGrid.company_id),
                marketplace_profile_id: Ext.encode(record.data.marketplace_profile_id)
            },
            success: function (result) {
                Ext.getBody().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if (resultDecoded.success) {
                    Ext.simpleConfirmation.success('Done.');
                    thisGrid.getStore().reload();
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultDecoded.message);
                }
            },
            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Status cannot be changed. Please try again later.');
            }
        });
    }
});

Ext.reg('AppMarketplaceGrid', MarketplaceGrid);