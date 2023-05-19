var ApplicantsProfileClientReferralsGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid = this;
    owner = thisGrid.owner;

    this.autoRefreshReferralsList = true;

    // The list of Compensation Agreements will be loaded during the grid data loading
    // Will be used in the ApplicantsProfileClientReferralsDialog
    this.arrCompensationAgreements = [];

    this.store = new Ext.data.Store({
        remoteSort: false,
        baseParams: {
            applicantId: config.applicantId
        },

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/profile/get-assigned-client-referrals'
        }),

        reader: new Ext.data.JsonReader({
            root: 'items',
            totalProperty: 'count',
            idProperty: 'referral_id',
            fields: [
                'referral_id',
                'referral_client_name',
                'referral_client_id',
                'referral_client_type',
                'referral_client_real_type',
                'referral_compensation_arrangement',
                'referral_is_paid'
            ]
        })
    });
    this.store.on('load', this.checkLoadedResult.createDelegate(this));
    this.store.on('loadexception', this.checkLoadedResult.createDelegate(this));

    this.store.setDefaultSort('referral_client_name', 'ASC');

    var expandCol = Ext.id();
    var sm = new Ext.grid.CheckboxSelectionModel();
    this.columns = [
        sm, {
            header: _('Referral'),
            dataIndex: 'referral_client_type',
            sortable: true,
            width: 100,
            renderer: function (v) {
                return (v === 'client') ? _('Client') : _('Prospect');
            }
        }, {
            id: expandCol,
            header: _('Name'),
            dataIndex: 'referral_client_name',
            sortable: true,
            width: 200,
            renderer: function (val, p, record) {
                return String.format(
                    "<a href='#' class='blklink open_client_tab' onclick='return false;'>{0}</a>",
                    val
                );
            }
        }, {
            header: _('Compensation Arrangement'),
            dataIndex: 'referral_compensation_arrangement',
            sortable: true,
            width: 300
        }, {
            header: _('Paid'),
            dataIndex: 'referral_is_paid',
            align: 'center',
            sortable: true,
            width: 80,
            renderer: function (v) {
                return (v === 'Y') ? '<img src="' + baseUrl + '/images/icons/tick.png" />' : '';
            }
        }
    ];

    this.tbar = [
        {
            text: '<i class="las la-user-plus"></i>' + _('Add Referral'),
            ref: '../addReferralBtn',
            disabled: config.booReadOnly,
            handler: this.addReferral.createDelegate(this)
        }, {
            text: '<i class="las la-user-edit"></i>' + _('Edit Referral'),
            ref: '../editReferralBtn',
            disabled: true,
            handler: this.editReferral.createDelegate(this)
        }, {
            text: '<i class="las la-user-minus"></i>' + _('Delete Referral'),
            ref: '../deleteReferralBtn',
            disabled: true,
            handler: this.deleteReferral.createDelegate(this)
        }, '->', {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            handler: thisGrid.refreshReferralsList.createDelegate(thisGrid)
        }
    ];

    ApplicantsProfileClientReferralsGrid.superclass.constructor.call(this, {
        border: false,
        loadMask: {msg: _('Loading...')},
        sm: sm,
        columns: this.columns,
        store: this.store,
        height: 250,
        stripeRows: true,
        autoExpandColumn: expandCol,
        viewConfig: {
            deferEmptyText: _('There are no records to show.'),
            emptyText: _('There are no records to show.')
        }
    });

    thisGrid.on('cellclick', thisGrid.onCellClick.createDelegate(this));
    thisGrid.on('rowdblclick', thisGrid.editReferral.createDelegate(this));
    thisGrid.on('afterrender', thisGrid.onGridShow.createDelegate(this));
    thisGrid.getSelectionModel().on('selectionchange', thisGrid.updateToolbarButtons, thisGrid);
};

Ext.extend(ApplicantsProfileClientReferralsGrid, Ext.grid.GridPanel, {
    onGridShow: function () {
        if (this.rendered && this.autoRefreshReferralsList) {
            this.getStore().load();
        }
        this.autoRefreshReferralsList = false;
    },

    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();
        var booIsSelectedAtLeastOne = sel.length >= 1;
        var booIsSelectedOnlyOne = sel.length === 1;

        this.editReferralBtn.setDisabled(!booIsSelectedOnlyOne || this.booReadOnly);
        this.deleteReferralBtn.setDisabled(!booIsSelectedAtLeastOne || this.booReadOnly);
    },

    addReferral: function () {
        var thisGrid = this;

        var oDialog = new ApplicantsProfileClientReferralsDialog({
            panelType: thisGrid.panelType,
            applicantId: thisGrid.applicantId,
            arrCompensationAgreements: thisGrid.arrCompensationAgreements,
            oReferral: {
                referral_id: 0
            }
        }, thisGrid);

        oDialog.show();
        oDialog.center();
    },

    editReferral: function () {
        var thisGrid = this;

        var selRecord = this.getSelectionModel().getSelected();
        var oDialog = new ApplicantsProfileClientReferralsDialog({
            panelType: thisGrid.panelType,
            applicantId: thisGrid.applicantId,
            arrCompensationAgreements: thisGrid.arrCompensationAgreements,
            oReferral: selRecord['data']
        }, thisGrid);

        oDialog.show();
        oDialog.center();
    },

    deleteReferral: function () {
        var thisGrid = this;
        var sel = thisGrid.getSelectionModel().getSelections();
        if (sel.length) {
            var question = String.format(
                _('Are you sure you want to delete {0}?'),
                sel.length == 1 ? '<i>' + sel[0].data.referral_client_name + '</i>' + _(' referral') : sel.length + _(' referrals')
            );

            Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                if (btn == 'yes') {
                    var arrClientReferrals = [];
                    for (var i = 0; i < sel.length; i++) {
                        arrClientReferrals.push(sel[i].data.referral_id);
                    }

                    thisGrid.getEl().mask(_('Processing...'));
                    Ext.Ajax.request({
                        url: topBaseUrl + '/applicants/profile/remove-client-referrals',
                        params: {
                            arrClientReferrals: Ext.encode(arrClientReferrals)
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);

                            if (resultData.success) {
                                thisGrid.getEl().mask(_('Done!'));

                                setTimeout(function () {
                                    thisGrid.getEl().unmask();
                                    thisGrid.refreshReferralsList();
                                }, 1500);
                            } else {
                                thisGrid.getEl().unmask();
                                Ext.simpleConfirmation.error(resultData.msg);
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error(_('Case cannot be deleted. Please try again later.'));
                            thisGrid.getEl().unmask();
                        }
                    });
                }
            });
        }
    },

    refreshReferralsList: function () {
        this.store.reload();
    },

    checkLoadedResult: function () {
        var thisGrid = this;
        if (thisGrid.store.reader.jsonData && thisGrid.store.reader.jsonData.msg && !thisGrid.store.reader.jsonData.success) {
            var msg = String.format('<span style="color: red">{0}</span>', this.store.reader.jsonData.msg);
            thisGrid.getEl().mask(msg);
        } else {
            thisGrid.getEl().unmask();
            thisGrid.arrCompensationAgreements = this.store.reader.jsonData.arrCompensationAgreements;
        }

        thisGrid.updateToolbarButtons();
    },

    makeReadOnly: function () {
        this.addReferralBtn.setDisabled(true);
        this.deleteReferralBtn.setDisabled(true);
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        if ($(e.getTarget()).hasClass('open_client_tab')) {
            e.stopEvent();

            var rec = grid.getStore().getAt(rowIndex);
            if (rec.data.referral_client_type === 'client') {
                setUrlHash('#applicants');
                setActivePage();

                var thisTabPanel = Ext.getCmp('applicants-tab-panel');
                thisTabPanel.openApplicantTab({
                    applicantId: rec.data.referral_client_id,
                    applicantName: rec.data.referral_client_name,
                    memberType: rec.data.referral_client_real_type
                }, 'profile');
            } else {
                if (allowedPages.has('prospects')) {
                    setUrlHash(String.format(
                        '#prospects/prospect/{0}/profile',
                        rec.data.referral_client_id
                    ));
                    setActivePage();
                }
            }
        }
    }
});

Ext.reg('ApplicantsProfileClientReferralsGrid', ApplicantsProfileClientReferralsGrid);
