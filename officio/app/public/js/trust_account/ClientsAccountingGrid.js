var ClientsAccountingGrid = function (config) {
    Ext.apply(this, config);

    var oRecord = Ext.data.Record.create([{
        name: 'tabId'
    }, {
        name: 'title'
    }, {
        name: 'currency_label'
    }, {
        name: 'last_reconcile',
        type: 'date',
        dateFormat: 'Y-m-d'
    }, {
        name: 'last_reconcile_iccrc',
        type: 'date',
        dateFormat: 'Y-m-d'
    }]);

    this.store = new Ext.data.Store({
        data: arrTATabs,
        reader: new Ext.data.JsonReader({id: 0}, oRecord)
    });

    this.columns = [{
        header: arrApplicantsSettings.ta_label + ' ' + _('Name'),
        dataIndex: 'title',
        sortable: true,
        width: 350
    }, {
        header: _('Currency'),
        dataIndex: 'currency_label',
        sortable: true,
        align: 'center',
        width: 50
    }, {
        header: _('Reconciled for end of'),
        dataIndex: 'last_reconcile',
        sortable: true,
        align: 'center',
        renderer: this.formatDate.createDelegate(this),
        width: 80
    }];

    if (site_version !== 'australia') {
        this.columns.push({
            header: _('CICC Reconciled for end of'),
            dataIndex: 'last_reconcile_iccrc',
            sortable: true,
            align: 'center',
            renderer: this.formatDate.createDelegate(this),
            width: 120
        });
    }

    this.tbar = new Ext.Toolbar({
        height: 30,
        items: [
            '->', {
                xtype: 'button',
                ctCls: 'x-toolbar-cell-no-right-padding',
                text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                hidden: !allowedPages.has('help'),
                handler: function () {
                    showHelpContextMenu(this.getEl(), 'trust-account-list');
                }
            }
        ]
    });

    ClientsAccountingGrid.superclass.constructor.call(this, {
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        cls: 'search-result',
        loadMask: {msg: _('Loading...')},
        stripeRows: true,
        viewConfig: {
            forceFit: true,
            deferEmptyText: _('No records found.'),
            emptyText: _('No records found.')
        }
    });

    this.on('cellclick', this.onCellClick.createDelegate(this));
    this.getSelectionModel().on('beforerowselect', function () {
        return false;
    }, this);
};

Ext.extend(ClientsAccountingGrid, Ext.grid.GridPanel, {
    formatDate: function (value) {
        return value ? value.dateFormat('F Y') : '-';
    },

    onCellClick: function (grid, rowIndex) {
        var rec = grid.getStore().getAt(rowIndex);

        this.openAccountingTab(rec.data['tabId'])
    },

    openAccountingTab: function (tabId) {
        var oTabInfo = null;
        for (var i = 0; i < arrTATabs.length; i++) {
            if (arrTATabs[i]['tabId'] === tabId) {
                oTabInfo = arrTATabs[i];
                break;
            }
        }

        if (oTabInfo === null) {
            return;
        }

        var oTabPanel = Ext.getCmp('ta-tab-panel');
        var oTabOpened = Ext.getCmp(oTabInfo.tabId);
        if (!oTabOpened) {
            var oTab = {
                id: oTabInfo.tabId,
                hash: '#trustac/' + oTabInfo.company_ta_id,
                title: oTabInfo.title + ' (' + oTabInfo.currency_label + ')',
                closable: true,
                scripts: true,
                height: initPanelSize() - 5,
                style: 'padding: 10px',

                autoLoad: {
                    url: baseUrl + '/trust-account/index/show',
                    params: {
                        company_ta_id: oTabInfo.company_ta_id
                    },

                    callback: function () {
                        showTransactionsGrid(oTabInfo.company_ta_id, oTabInfo.currency);

                        // We need this to avid double refreshing
                        $('#filter_td_type_conatiner_' + oTabInfo.company_ta_id).css('visibility', 'visible');
                        $('#filter_td_client_conatiner_' + oTabInfo.company_ta_id).css('visibility', 'visible');

                        loadDatePicker();
                    }
                },

                listeners: {
                    activate: function () {
                        setUrlHash(this.hash, 0);
                    }
                }
            };

            oTabPanel.add(oTab);
        }

        oTabPanel.setActiveTab(oTabInfo.tabId);
    }
});
