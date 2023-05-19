var ContextIdsTabPanel = function (config) {
    Ext.apply(this, config);

    ContextIdsTabPanel.superclass.constructor.call(this, {
        autoHeight: true,
        activeTab: 0,
        frame: false,
        plain: true,
        cls: 'tabs-second-level',

        items: [
            {
                xtype: 'panel',
                title: '<i class="las la-table"></i>' + _('Context Ids'),
                items: new ContextIdsGrid({}, this)
            }, {
                xtype: 'panel',
                title:   '<i class="las la-tags"></i>' + _('Help Tags'),
                items: new HelpTagsGrid({}, this)
            }
        ],

        listeners: {
            tabchange: function () {
                setTimeout(function () {
                    updateSuperadminIFrameHeight('#help_context_ids_container');
                }, 200);
            },

            afterrender: function () {
                setTimeout(function () {
                    updateSuperadminIFrameHeight('#help_context_ids_container');
                }, 200);

            }
        }
    });
};

Ext.extend(ContextIdsTabPanel, Ext.TabPanel, {});