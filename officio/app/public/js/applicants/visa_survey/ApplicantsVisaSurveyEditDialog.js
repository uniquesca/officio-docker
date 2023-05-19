var ApplicantsVisaSurveyEditDialog = function (config, parentGrid) {
    var thisWindow        = this;
    thisWindow.parentGrid = parentGrid;
    Ext.apply(this, config);

    this.mainFormPanel = new Ext.FormPanel({
        labelWidth: 120,
        bodyStyle:  'padding:5px',

        items: [
            {
                xtype: 'hidden',
                name:  'caseId',
                value: config.caseId
            },
            {
                xtype: 'hidden',
                name:  'dependentId',
                value: empty(config.dependentId) ? 0 : config.dependentId
            },
            {
                xtype: 'hidden',
                name:  'visa_survey_id',
                value: config.oVisaRecord.visa_survey_id
            },
            {
                fieldLabel: _('Third Country Visa'),
                xtype:      'combo',
                name:       'visa_country_id',
                hiddenName: 'visa_country_id',
                value:      config.oVisaRecord.visa_country_id,

                store: new Ext.data.Store({
                    data:   config.arrCountries,
                    reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                        {name: 'countries_id'},
                        {name: 'countries_name'}
                    ]))
                }),

                displayField:   'countries_name',
                valueField:     'countries_id',
                mode:           'local',
                triggerAction:  'all',
                emptyText:      _('Select Country...'),
                selectOnFocus:  true,
                forceSelection: true,
                typeAhead:      true,
                searchContains: true,
                allowBlank:     false,
                width:          220
            },
            {
                fieldLabel: _('Visa Number'),
                name:       'visa_number',
                value:      config.oVisaRecord.visa_number,
                xtype:      'textfield',
                allowBlank: false,
                width:      220
            },
            {
                fieldLabel: _('Visa Issue Date'),
                name:       'visa_issue_date',
                value:      config.oVisaRecord.visa_issue_date,
                xtype:      'datefield',
                format:     dateFormatFull,
                allowBlank: false,
                width:      115
            },
            {
                fieldLabel: _('Visa Expiry Date'),
                name:       'visa_expiry_date',
                value:      config.oVisaRecord.visa_expiry_date,
                xtype:      'datefield',
                format:     dateFormatFull,
                allowBlank: false,
                width:      115
            }
        ]
    });


    ApplicantsVisaSurveyEditDialog.superclass.constructor.call(this, {
        title:       empty(config.oVisaRecord.visa_survey_id) ? _('Add Visa') : _('Edit Visa'),
        iconCls:     empty(config.oVisaRecord.visa_survey_id) ? 'visa-survey-icon-add' : 'visa-survey-icon-edit',
        layout:      'form',
        buttonAlign: 'right',
        resizable:   false,
        autoHeight:  true,
        autoWidth:   true,
        modal:       true,

        items: [
            this.mainFormPanel
        ],

        buttons: [
            {
                text:    _('Cancel'),
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text:    _('Save'),
                cls:     'orange-btn',
                handler: thisWindow.submitVisaSurveyRecord.createDelegate(thisWindow)
            }
        ]
    });

    thisWindow.on('show', function () {
        thisWindow.mainFormPanel.getForm().clearInvalid();
    });
};

Ext.extend(ApplicantsVisaSurveyEditDialog, Ext.Window, {
    submitVisaSurveyRecord: function () {
        var thisWindow = this;

        if (!thisWindow.mainFormPanel.getForm().isValid()) {
            return false;
        }

        this.mainFormPanel.getForm().submit({
            url:              baseUrl + '/applicants/index/save-visa-survey-record',
            waitMsg:          _('Saving...'),
            clientValidation: false,

            success: function () {
                thisWindow.parentGrid.getStore().load();
                thisWindow.close();
            },

            failure: function (form, action) {
                var msg = action && action.result && action.result.message ? action.result.message : _('Internal error.');
                Ext.simpleConfirmation.error(msg);
            }
        });
    }
});