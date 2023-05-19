var ApplicantTypesTabPanel = function(config) {
    Ext.apply(this, config);

    ApplicantTypesTabPanel.superclass.constructor.call(this, {
        autoHeight: true,
        activeTab: 0,
        items: [
            new ApplicantTypesGrid({
                title: 'Manage Contacts Types'
            }, this)
        ]
    });
};

Ext.extend(ApplicantTypesTabPanel, Ext.TabPanel, {
    applicantTypeEdit: function(template_id, template_name) {
        var tab_id = 'applicant_type_tab_' + template_id;

        if(Ext.getCmp(tab_id)) {
            this.activate(tab_id);
        } else {
            var newTab = this.add({
                id:       tab_id,
                xtype:    'iframepanel',
                title:    template_name,
                iconCls:  empty(template_id) ? 'applicant_type_add' : 'applicant_type_edit',
                closable: true,
                loadMask: true,
                frameConfig: {
                    autoLoad: {
                        width: '100%'
                    },
                    style: 'height: ' + (getSuperadminPanelHeight() - 30) + 'px;'
                }
            }).show();
            newTab.setSrc(baseUrl + '/manage-applicant-fields-groups/index?member_type=contacts&template_id=' + template_id);
        }
    }
});