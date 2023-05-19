var booSendingVevoInProgress = false;

var ApplicantsProfileVevoSender = {

    getVevoInfo: function(clientId, memberId, countrySuggestion) {
        var oParams = {
            client_id:         Ext.encode(clientId),
            member_id:         Ext.encode(memberId),
            countrySuggestion: Ext.encode(countrySuggestion)
        };

        var src = topBaseUrl + '/applicants/profile/get-vevo-info/?' + $.param(oParams);

        var iframeId = 'iframe_get_vevo_info';

        booSendingVevoInProgress = true;

        // Create a hidden frame
        var frame = Ext.getCmp('vevo-check-dialog').getEl().createChild({
            id: iframeId,
            name: iframeId,
            src: src,
            tag: 'iframe',
            cls: 'x-hidden'
        });

        Ext.EventManager.on(frame.dom, 'load', this.onIFrameLoad, this);
    },

    onIFrameLoad: function() {
        if (booSendingVevoInProgress) {
            // Frame wasn't fully loaded -
            // Show error message
            Ext.getCmp('vevo-check-dialog').getEl().unmask();
            Ext.simpleConfirmation.error('Internal Error', 'Error');
            booSendingVevoInProgress = false;
        }
    },

    outputResult: function(result) {
        var win = Ext.getCmp('vevo-check-dialog');
        var owner = win.owner;
        if (result.status) {
            win.getEl().mask(result.status);
        } else {
            if (result.success) {
                win.getEl().unmask();
                win.close();
                booSendingVevoInProgress = false;

                var vevoInfoDialog = new ApplicantsProfileVevoInfoDialog({
                    client_id: win.clientId,
                    fields: result.vevo_info,
                    file_id: result.file_id
                }, owner);
                vevoInfoDialog.show();
                vevoInfoDialog.center();

            } else if (result.boo_empty_fields) {
                win.getEl().unmask();
                win.close();
                booSendingVevoInProgress = false;

                var arrEmptyFields = [];

                $.each(result.fields, function(key, field) {
                    if (empty(field.value)) {
                        arrEmptyFields.push(field.field_name);
                    }
                });

                if (arrEmptyFields.length > 0) {
                    var strError = 'The following information is needed to perform a VEVO check.' + '<br />' +
                        'Please complete the required fields and try again.' + '<br />' + arrEmptyFields.join('<br />');

                    Ext.simpleConfirmation.error(strError, 'Error');
                    return false;
                }
            } else {
                win.getEl().unmask();
                booSendingVevoInProgress = false;
                Ext.simpleConfirmation.error(result.message);
            }
        }
    }
};