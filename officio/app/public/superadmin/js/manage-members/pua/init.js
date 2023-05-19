Ext.onReady(function () {

    var oContainer = $('#pua-tab');
    if (oContainer.length) {
        Ext.QuickTips.init();

        // Clear the content
        oContainer.html('');

        var designatedPersonsGrid = new PUAGrid({
            id: 'designated-persons-grid',
            pua_type: 'designated_person',
            height: 300
        });

        var businessContactsGrid = new PUAGrid({
            id: 'business-contacts-grid',
            pua_type: 'business_contact',
            height: 300
        });

        new Ext.Container({
            renderTo: 'pua-tab',
            items:    [
                {
                    xtype:      'fieldset',
                    title:      _('Step 1: Designated Authorized Representative(s)/Responsible Person(s)'),
                    bodyStyle:  'padding: 10px 0',
                    cls:        'applicants-profile-fieldset',
                    autoHeight: true,

                    items: [
                        {
                            xtype:  'box',
                            autoEl: {
                                style: 'padding-bottom: 10px; font-size: 14px;',
                                html:  _('Click on the "Add Designated Representative/Responsible Person" button to populate the Designation form on behalf of the Designated Authorized Representative/Responsible Person and to add him/her to the list below.<br><br>Once done:<br>1) click on "Not uploaded" under the new entry,<br>2) click "Download Designation Form",<br>3) review the form details,<br>4) have both parties sign the PDF, and<br>5) click "Upload" under the new entry to upload the Designation form to Officio.<div style="font-style: italic; padding-top: 10px">NOTE: Uploading the Designation form does not share the form with anyone else.</div>')
                            }
                        }, designatedPersonsGrid
                    ]
                }, {
                    xtype:      'fieldset',
                    title:      _('Step 2: Business Contacts and/or Service Providers'),
                    bodyStyle:  'padding: 10px 0',
                    cls:        'applicants-profile-fieldset',
                    style:      'margin-top: 20px;',
                    autoHeight: true,
                    items:      [
                        {
                            xtype:  'box',
                            autoEl: {
                                style: 'padding-bottom: 10px; font-size: 14px;',
                                html:  _('Click on the "Add Business Contact and/or Service Provider" button to record any business contact (e.g. lawyer, accountant, etc.) or service provider (e.g., landlord, utility provider, internet service provider, e-mail provider, etc.) in the list below.')
                            }
                        }, businessContactsGrid
                    ]
                }
            ]
        });
    }
});