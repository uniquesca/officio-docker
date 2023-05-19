var ApplicantsProfileVevoCheckDialog = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var usersCombo = new Ext.form.ComboBox({
        id: 'membersCombo',
        width: 250,
        style: 'margin-bottom: 5px;',
        fieldLabel: 'Logging in as',
        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'option_id'
            }, [
                {name: 'option_id'},
                {name: 'option_name'}
            ]),
            data: arrApplicantsSettings.vevo_members_list
        },
        displayField: 'option_name',
        valueField: 'option_id',
        mode: 'local',
        searchContains: true,
        forceSelection: true,
        triggerAction: 'all',
        allowBlank: false,
        selectOnFocus: true
    });

    var notification = new Ext.Panel({
        style:  'font-size: 12px; color: red; margin-bottom: 5px;',
        hidden: thisWindow.booCorrectValue,
        html:   '<span style="padding-bottom: 3px; display:inline-block;">Country ' + thisWindow.countryFieldValue + ' does not correspond to a valid country on VEVO. </span></br>' +
        '<span>Please choose The Country of Passport from the list below:</span>'
    });

    var countriesCombo = new Ext.form.ComboBox({
        id: 'countriesCombo',
        width: 250,
        style: 'margin-bottom: 5px;',
        hidden: thisWindow.booCorrectValue,
        fieldLabel: 'Country of Passport',
        store: {
            xtype: 'store',
            reader: new Ext.data.JsonReader({
                id: 'option_id'
            }, [
                {name: 'option_id'},
                {name: 'option_name'}
            ]),
            data: thisWindow.countrySuggestions
        },
        displayField: 'option_name',
        valueField: 'option_id',
        mode: 'local',
        searchContains: true,
        forceSelection: true,
        triggerAction: 'all',
        allowBlank: false,
        selectOnFocus: true
    });

    var text = new Ext.Panel({
        bodyStyle: 'font-size: 12px; padding: 5px 5px 5px 2px;',
        html: 'By clicking "Yes", you confirm that you hold client\'s permission to perform this check'
    });

    if (arrApplicantsSettings.vevo_members_list.length == 1) {
        usersCombo.setValue(arrApplicantsSettings.vevo_members_list[0].option_id);
    }

    if (thisWindow.countrySuggestions.length == 1) {
        countriesCombo.setValue(thisWindow.countrySuggestions[0].option_id);
    }

    ApplicantsProfileVevoCheckDialog.superclass.constructor.call(this, {
        id: 'vevo-check-dialog',
        layout: 'form',
        resizable: false,
        labelWidth: thisWindow.booCorrectValue ? 100: 120,
        bodyStyle: 'padding: 5px; background-color:white;',
        buttonAlign: 'right',
        title: 'VEVO',
        autoHeight: true,
        autoWidth: true,
        modal: true,
        items: [
            usersCombo,
            notification,
            countriesCombo,
            text
        ],

        buttons: [
            {
                text: 'Cancel',
                handler: function () {
                    thisWindow.close();
                }
            },
            {
                text: 'Yes',
                cls: 'orange-btn',
                handler: function () {
                    var memberId = Ext.getCmp('membersCombo').getValue();
                    var countrySuggestion = '';

                    if (thisWindow.booCorrectValue) {
                        countrySuggestion = thisWindow.countryFieldValue;
                    } else {
                        countrySuggestion = Ext.getCmp('countriesCombo').getValue();
                    }

                    thisWindow.getEl().mask('Loading...');

                    ApplicantsProfileVevoSender.getVevoInfo(
                        thisWindow.clientId,
                        memberId,
                        countrySuggestion
                    );

                }
            }
        ]
    });
};

Ext.extend(ApplicantsProfileVevoCheckDialog, Ext.Window, {
    //getVevoInfo: function () {
    //    var win = this;
    //    win.getEl().mask('Loading...');
    //
    //    var memberId = Ext.getCmp('membersCombo').getValue();
    //
    //    Ext.Ajax.request({
    //        url: topBaseUrl + '/applicants/profile/get-vevo-info',
    //        params: {
    //            client_id: Ext.encode(this.clientId),
    //            member_id: Ext.encode(memberId)
    //        },
    //
    //        success: function(f) {
    //            var resultData = Ext.decode(f.responseText);
    //            if (resultData.success) {
    //                win.getEl().unmask();
    //                win.close();
    //
    //                var vevoInfoDialog = new ApplicantsProfileVevoInfoDialog({
    //                    client_id: win.clientId,
    //                    fields: resultData.vevo_info,
    //                    file_id: resultData.file_id
    //                }, this);
    //                vevoInfoDialog.show();
    //                vevoInfoDialog.center();
    //
    //            } else if (resultData.boo_empty_fields) {
    //                win.getEl().unmask();
    //                win.close();
    //
    //                var arrEmptyFields = [];
    //
    //                $.each(resultData.fields, function(key, field) {
    //                    if (empty(field.value)) {
    //                        arrEmptyFields.push(field.field_name);
    //                        if (win.owner.owner.mainForm.getForm().findField(field.full_field_id + "[]")) {
    //                            win.owner.owner.mainForm.getForm().findField(field.full_field_id + "[]").markInvalid();
    //                        }
    //                    }
    //                });
    //
    //                if (arrEmptyFields.length > 0) {
    //                    var strError = 'The following information is needed to perform a VEVO check.' + '<br />' +
    //                        'Please complete the required fields and try again.' + '<br />' + arrEmptyFields.join('<br />');
    //
    //                    Ext.simpleConfirmation.error(strError, 'Error');
    //                    return false;
    //                }
    //            } else {
    //                win.getEl().unmask();
    //                Ext.simpleConfirmation.error(resultData.message);
    //            }
    //        },
    //
    //        failure: function () {
    //            Ext.simpleConfirmation.error('Error happened. Please try again later.');
    //            win.getEl().unmask();
    //        }
    //    });
    //}
});