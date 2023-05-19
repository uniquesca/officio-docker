var ApplicantsProfileVevoInfoDialog = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisWindow = this;

    var arrFields = [];
    var rowData, booDisabledCheckbox;
    var booCanUpdateFields = false;

    $.each(this.fields, function(key, field) {
        if (field.save) {
            booCanUpdateFields = true;
            return false;
        }
    });

    $.each(this.fields, function(key, field) {
        booDisabledCheckbox = !field.save;
        rowData = {
            layout: 'column',
            width: 600,
            items: [
                {
                    columnWidth: 0.05,
                    xtype: 'checkbox',
                    id: 'update_' + field.name,
                    name: 'update_' + field.name,
                    checked: !booDisabledCheckbox,
                    disabled: booDisabledCheckbox,
                    hidden: !booCanUpdateFields
                }, {
                    columnWidth: 0.3,
                    xtype: 'label',
                    style: 'padding: 3px 5px 5px 5px; font-size: 12px;',
                    forId: field.name,
                    text: field.label + ':'
                }, {
                    columnWidth: 0.65,
                    xtype: 'displayfield',
                    style: 'padding-top: 3px; font-size: 12px;',
                    id: field.name,
                    value: field.value,
                    type: field.type
                }
            ]
        };

        arrFields.push(rowData);
    });

    ApplicantsProfileVevoInfoDialog.superclass.constructor.call(this, {
        id: 'vevo-info-panel',
        layout: 'form',
        resizable: false,
        bodyStyle: 'padding: 5px 7px 7px 5px; background-color:white;',
        buttonAlign: 'right',
        title: 'VEVO Fields',
        autoHeight: true,
        autoWidth: true,
        modal: true,
        items: [
            arrFields
        ],

        buttons: [
            {
                text: 'Close',
                handler: function () {
                    thisWindow.close();
                }
            }, {
                text: 'Save PDF to Documents',
                handler: this.savePDF.createDelegate(this)
            }, {
                text: 'Update Selected',
                cls: 'orange-btn',
                hidden: !booCanUpdateFields,
                handler: this.updateSelected.createDelegate(this)
            }
        ]
    });
};

Ext.extend(ApplicantsProfileVevoInfoDialog, Ext.Window, {
    updateSelected: function () {
        var win = this;

        var arrUpdateFields = [];

        var regExp = /update_(.*)/;
        var match;

        var arrCheckedFields = $('#vevo-info-panel').find('input:checked');

        if (!arrCheckedFields.length) {
            Ext.simpleConfirmation.warning('Please select fields.');
            return;
        }

        arrCheckedFields.each(function() {
            if ((match = regExp.exec($(this).attr('id'))) !== null) {
                arrUpdateFields.push({
                    unique_field_id: match[1],
                    field_value: Ext.getCmp(match[1]).getValue(),
                    field_type: Ext.getCmp(match[1]).type
                });
            }
        });

        win.getEl().mask('Loading...');

        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/profile/update-vevo-info',
            params: {
                client_id: this.client_id,
                arr_update_fields: Ext.encode(arrUpdateFields)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {

                    var profileTabId = win.owner.owner.owner.items.items[0].id;
                    var profileTab = $("#" + profileTabId);

                    if (profileTab.length) {
                        $.each(resultData.fields, function(key, field) {
                            if (profileTab.find("[name='" + field.full_field_id + "[]']").length) {
                                profileTab.find("[name='" + field.full_field_id + "[]']").val(field.value);
                            }
                        });
                    }

                    win.getEl().unmask();
                    Ext.simpleConfirmation.success('Selected fields were successfully updated.');
                } else {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                win.getEl().unmask();
            }
        });
    },

    savePDF: function () {
        var win = this;
        win.getEl().mask('Loading...');

        Ext.Ajax.request({
            url: topBaseUrl + '/documents/index/save-file-to-client-documents',
            params: {
                member_id: this.client_id,
                file_id: this.file_id
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);
                if (resultData.success) {
                    Ext.simpleConfirmation.success('File was successfully saved to Documents/Correspondence folder.');
                    win.close();
                } else {
                    win.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                Ext.simpleConfirmation.error('Error happened. Please try again later.');
                win.getEl().unmask();
            }
        });
    }

});