var CasesTemplatesTabPanel = function(config) {
    Ext.apply(this, config);

    CasesTemplatesTabPanel.superclass.constructor.call(this, {
        autoHeight: true,
        activeTab: 0,
        frame: false,
        plain: true,
        cls: 'tabs-second-level',
        items: [
            {
                xtype: 'panel',
                title: _('Manage') + ' ' + case_type_field_label_plural,
                items: new CasesTemplatesGrid({
                    listeners: {
                        activate: function () {
                            updateSuperadminIFrameHeight('#case-templates-container');
                        }
                    }
                }, this)
            }
        ]
    });
};

Ext.extend(CasesTemplatesTabPanel, Ext.TabPanel, {
    caseTemplateEdit: function(template_id, template_name) {
        var thisTabPanel = this;
        var tab_id = 'case_template_tab_' + template_id;

        if(Ext.getCmp(tab_id)) {
            this.activate(tab_id);
        } else {
            var title = '';
            if (empty(template_id)) {
                title = '<i class="las la-plus"></i>' + template_name;
            } else {
                title = '<i class="las la-edit"></i>' + template_name;
            }

            var newTab = this.add({
                id: tab_id,
                xtype: 'iframepanel',
                title: title,
                closable: true,
                loadMask: true,
                frameConfig: {
                    autoLoad: {
                        width: '100%'
                    },
                    style: 'height: ' + (getSuperadminPanelHeight() - 30) + 'px;'
                },

                listeners: {
                    activate: function (tab) {
                        this.checkInterval = setInterval(function () {
                            var iFrame = $('#' + tab_id + ' iframe');
                            var curHeight = iFrame.contents().find('html').height() + 20;
                            iFrame.height(curHeight);
                            tab.setHeight(curHeight);
                            updateSuperadminIFrameHeight('#' + thisTabPanel.id);
                        }, 500);
                    },

                    deactivate: function () {
                        if (this.checkInterval) {
                            clearInterval(this.checkInterval);
                        }
                    },

                    destroy: function () {
                        if (this.checkInterval) {
                            clearInterval(this.checkInterval);
                        }
                    }
                }

            }).show();
            newTab.setSrc(baseUrl + '/manage-fields-groups/index?template_id=' + template_id);
        }
    }
});