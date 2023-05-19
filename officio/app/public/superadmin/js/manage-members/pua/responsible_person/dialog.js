var PUAResponsiblePersonDialog = function (parentGrid, oPUARecord) {
    var thisDialog  = this;
    this.parentGrid = parentGrid;

    this.buttons = [
        {
            text:    '<i class="las la-download"></i>' + _('Download Designation Form'),
            cls:     'orange-btn',
            tooltip: 'Click to save and download the current form.',
            hidden:  empty(oPUARecord['data']['pua_id']),
            handler: this.saveChanges.createDelegate(this, [oPUARecord, true])
        }, {
            text:    'Close',
            scope:   this,
            handler: function () {
                this.close();
            }
        },
        {
            text:    'Save',
            cls:     'orange-btn',
            handler: this.saveChanges.createDelegate(this, [oPUARecord, false])
        },
    ];

    PUAResponsiblePersonDialog.superclass.constructor.call(this, {
        title:   empty(oPUARecord['data']['pua_id']) ? '<i class="las la-plus"></i>' + _('New Designated Authorized Representative/Responsible Person') : '<i class="las la-edit"></i>' + _('Edit Designated Authorized Representative/Responsible Person'),

        y:           10,
        width:       1000,
        height:      600,
        autoScroll:  true,
        closeAction: 'close',
        plain:       true,
        modal:       true,
        buttonAlign: 'center',

        autoLoad: {
            url:      baseUrl + '/manage-members-pua/get-designation-form',
            params:   {pua_id: oPUARecord['data'].pua_id},
            callback: function () {
                thisDialog.validator = $('#designation_form_content').validate({
                    errorPlacement: function () {

                    }
                });
            }
        }
    });
};

Ext.extend(PUAResponsiblePersonDialog, Ext.Window, {
    saveChanges: function (oPUARecord, booDownloadForm) {
        var thisDialog = this;

        if (thisDialog.validator.form()) {
            thisDialog.getEl().mask('Saving...');

            // Fix issue with JQuery 1.5 with json
            jQuery.ajaxSetup({
                jsonp:         null,
                jsonpCallback: null
            });

            //submit form
            $('#designation_form_content').ajaxSubmit({
                url:  baseUrl + '/manage-members-pua/manage',
                type: "post",

                data: {
                    pua_id:   oPUARecord['data']['pua_id'],
                    pua_type: oPUARecord['data']['pua_type']
                },

                success: function (responseText) {
                    var result = Ext.decode(responseText);

                    if (result.success) {
                        thisDialog.parentGrid.store.reload();

                        thisDialog.getEl().mask(empty(result.message) ? 'Done!' : result.message);
                        setTimeout(function () {
                            if (booDownloadForm) {
                                thisDialog.parentGrid.exportToPdfPUARecord(false, oPUARecord['data']['pua_id']);
                            }
                            thisDialog.close();
                        }, 750);
                    } else {
                        thisDialog.getEl().unmask();
                        Ext.simpleConfirmation.error(result.message);
                    }
                },

                error: function () {
                    Ext.simpleConfirmation.error('Error happened during data submitting. Please try again later.');
                    thisDialog.getBody().unmask();
                }
            });
        }
    }
});