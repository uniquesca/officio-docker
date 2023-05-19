var ApplicantsQueueChangeOptionsWindow = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var arrRadios = [];

    var changeFieldsOptions = config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['queue']['change'] : arrApplicantsSettings['access']['queue']['change'];
    Object.keys(changeFieldsOptions).forEach(function(key) {
        var booAccess = changeFieldsOptions[key];
        if (booAccess) {
            var label = '';
            switch (key) {
                case 'push_to_queue':
                    label = 'Push to ' + arrApplicantsSettings.office_label;
                    break;

                case 'file_status':
                    label = 'Change ' + arrApplicantsSettings.file_status_label;
                    break;

                case 'assigned_staff':
                    label = 'Change Assigned Staff';
                    break;

                case 'visa_subclass':
                    label = 'Change Visa Subclass';
                    break;

                default:
            }
            if (label) {
                var radio = new Ext.form.Radio({
                    fieldLabel:     label,
                    name:           'change-field-radio',
                    inputValue:     key,
                    labelSeparator: '',
                    hideLabel:      true,
                    boxLabel:       label
                });
                arrRadios.push(radio);
            }
        }
    });

    var thisWindow = this;
    ApplicantsQueueChangeOptionsWindow.superclass.constructor.call(this, {
        title:       'Bulk Changes',
        iconCls:     'icon-applicant-queue-bulk-changes',
        layout:      'fit',
        resizable:   false,
        bodyStyle:   'padding: 5px 10px 5px 10px; background-color:white;',
        buttonAlign: 'right',
        modal:       true,
        autoWidth:   true,
        labelAlign:  'right',

        items: {
            xtype: 'form',
            id:    'change-field-value-form',
            items: [
                arrRadios
            ]
        },

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: 'OK',
                cls: 'orange-btn',
                iconCls: this.iconCls,
                handler: this.openApplyChangesDialog.createDelegate(this)
            }
        ]
    });

    this.on('show', function () {
        // If there is only one value in the dialog - automatically select it and press on the "OK" button
        if (arrRadios.length === 1) {
            arrRadios[0].setValue(true);
            this.openApplyChangesDialog.defer(100, this);
        }
    });
};

Ext.extend(ApplicantsQueueChangeOptionsWindow, Ext.Window, {
    openApplyChangesDialog: function() {
        var strFieldType = $('input[name="change-field-radio"]:checked').val();

        if (empty(strFieldType)) {
            Ext.simpleConfirmation.warning('Please choose an option.', 'Warning');
            return false;
        }

        var wnd = new ApplicantsQueueApplyChangesWindow({
            strFieldType: strFieldType,
            arrSelectedClientIds: this.arrSelectedClientIds,
            onSuccessUpdate: this.onSuccessUpdate
        }, this);

        wnd.show();
        wnd.center();

        this.close();
    }
});