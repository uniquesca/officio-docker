var ApplicantsVisaSurveyDialog = function (config) {
    var thisWindow = this;
    Ext.apply(this, config);

    if (config.booHasEditAccess) {
        this.tbar = [
            {
                xtype: 'buttongroup',
                items: [
                    {
                        text: '<i class="las la-plus"></i>' + _('Add Visa'),
                        handler: thisWindow.addVisaSurveyRecord.createDelegate(this)
                    }
                ]
            },
            {
                xtype: 'buttongroup',
                items: [
                    {
                        text: '<i class="las la-edit"></i>' + _('Edit Visa'),
                        disabled: true,
                        ref:      '../../toolbarButtonEditVisa',
                        handler:  thisWindow.editVisaSurveyRecord.createDelegate(this)
                    }
                ]
            },
            {
                xtype: 'buttongroup',
                items: [
                    {
                        text: '<i class="las la-trash"></i>' + _('Delete Visa'),
                        disabled: true,
                        ref:      '../../toolbarButtonDeleteVisa',
                        handler:  thisWindow.deleteVisaSurveyRecord.createDelegate(this)
                    }
                ]
            },
            '->',
            {
                xtype: 'buttongroup',
                items: [
                    {
                        text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                        cls:     'x-btn-icon',
                        handler: function () {
                            thisWindow.store.load();
                        }
                    }
                ]
            }
        ];
    }

    this.arrFields = [
        {name: 'visa_survey_id'},
        {name: 'visa_country_id'},
        {name: 'visa_number'},
        {
            name:       'visa_issue_date',
            type:       'date',
            dateFormat: 'Y-m-d'
        },
        {
            name:       'visa_expiry_date',
            type:       'date',
            dateFormat: 'Y-m-d'
        }
    ];

    this.store = new Ext.data.Store({
        autoLoad:   true,
        remoteSort: false,
        baseParams: {
            caseId:      config.caseId,
            dependentId: config.dependentId
        },

        sortInfo: {
            field:     'visa_country_id',
            direction: 'ASC'
        },

        proxy: new Ext.data.HttpProxy({
            url: topBaseUrl + '/applicants/index/get-visa-survey-records'
        }),

        reader: new Ext.data.JsonReader({
            root:          'items',
            totalProperty: 'count',
            idProperty:    'visa_survey_id',

            fields: this.arrFields
        })
    });

    var expandCol = Ext.id();
    this.columns  = [
        {
            id:        expandCol,
            header:    _('Third Country Visa'),
            width:     200,
            sortable:  true,
            dataIndex: 'visa_country_id'
        },
        {
            header:    _('Visa Number'),
            width:     200,
            sortable:  true,
            dataIndex: 'visa_number'
        },
        {
            header:    _('Visa Issue Date'),
            width:     100,
            sortable:  true,
            renderer:  Ext.util.Format.dateRenderer(dateFormatFull),
            dataIndex: 'visa_issue_date'
        },
        {
            header:    _('Visa Expiry Date'),
            width:     100,
            sortable:  true,
            renderer:  Ext.util.Format.dateRenderer(dateFormatFull),
            dataIndex: 'visa_expiry_date'
        }
    ];

    this.visaRecordsGrid           = new Ext.grid.GridPanel({
        border:           false,
        loadMask:         {msg: _('Loading...')},
        sm:               new Ext.grid.RowSelectionModel({singleSelect: true}),
        columns:          this.columns,
        store:            this.store,
        width:            700,
        height:           300,
        stripeRows:       true,
        autoExpandColumn: expandCol,
        viewConfig:       {
            deferEmptyText: _('There are no records to show.'),
            emptyText:      _('There are no records to show.')
        }
    });
    this.visaRecordsGrid.arrFields = this.arrFields;
    this.visaRecordsGrid.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
    this.visaRecordsGrid.on('rowdblclick', this.editVisaSurveyRecord.createDelegate(this), this);

    ApplicantsVisaSurveyDialog.superclass.constructor.call(this, {
        title:       _('Visa Survey'),
        iconCls:     'visa-survey-icon-main',
        layout:      'form',
        buttonAlign: 'right',
        resizable:   false,
        autoHeight:  true,
        autoWidth:   true,
        modal:       true,

        items: [
            this.visaRecordsGrid
        ],

        buttons: [
            {
                text:    _('Close'),
                handler: function () {
                    thisWindow.close();
                }
            }
        ]
    });

    this.on('close', this.onThisDialogClose.createDelegate(this));
};

Ext.extend(ApplicantsVisaSurveyDialog, Ext.Window, {
    onThisDialogClose: function() {
        if (this.oFieldGroup) {
            this.oFieldGroup.resetValueIfNoRecordsCreated(this.visaRecordsGrid.getStore().getCount());
        }
    },

    updateToolbarButtons: function () {
        var sel                  = this.visaRecordsGrid.getSelectionModel().getSelections();
        var booIsSelectedOnlyOne = sel.length === 1;

        this.toolbarButtonEditVisa.setDisabled(!booIsSelectedOnlyOne);
        this.toolbarButtonDeleteVisa.setDisabled(!booIsSelectedOnlyOne);
    },

    addVisaSurveyRecord: function () {
        var arrCountries = this.visaRecordsGrid.getStore().reader.jsonData['countries'];

        var addDialog = new ApplicantsVisaSurveyEditDialog({
            caseId:       this.caseId,
            dependentId:  this.dependentId,
            oVisaRecord:  {
                visa_survey_id: 0
            },
            arrCountries: arrCountries
        }, this.visaRecordsGrid);

        addDialog.show();
        addDialog.center();
    },

    editVisaSurveyRecord: function () {
        var arrCountries = this.visaRecordsGrid.getStore().reader.jsonData['countries'];
        var selRecord    = this.visaRecordsGrid.getSelectionModel().getSelected();
        if (!selRecord) {
            return;
        }

        var addDialog = new ApplicantsVisaSurveyEditDialog({
            caseId:       this.caseId,
            dependentId:  this.dependentId,
            oVisaRecord:  selRecord['data'],
            arrCountries: arrCountries
        }, this.visaRecordsGrid);

        addDialog.show();
        addDialog.center();
    },

    deleteVisaSurveyRecord: function () {
        var thisWindow = this;

        var selRecord = this.visaRecordsGrid.getSelectionModel().getSelected();
        if (!selRecord) {
            return;
        }

        var msg = String.format(_('Are you sure you want to delete selected record? <div style="font-style: italic;">(Visa Number: {0})</div>'), selRecord['data']['visa_number']);

        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                thisWindow.getEl().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url:    topBaseUrl + '/applicants/index/delete-visa-survey-record',
                    params: {
                        caseId:       thisWindow.caseId,
                        dependentId:  thisWindow.dependentId,
                        visaSurveyId: selRecord['data']['visa_survey_id']
                    },

                    success: function (result) {
                        thisWindow.getEl().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if (resultDecoded.success) {
                            Ext.simpleConfirmation.info(resultDecoded.message);
                            thisWindow.visaRecordsGrid.getStore().load();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },

                    failure: function () {
                        thisWindow.getEl().unmask();
                        Ext.simpleConfirmation.error(_('Selected record cannot be deleted. Please try again later.'));
                    }
                });
            }
        });
    }
});