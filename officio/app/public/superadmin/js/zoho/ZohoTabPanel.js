var ZohoTabPanel = function (config) {
    Ext.apply(this, config);

    ZohoTabPanel.superclass.constructor.call(this, {
        autoHeight: true,
        activeTab: 0,
        frame: false,
        plain: true,
        cls: 'tabs-second-level',
        items: [
            new ZohoKeysGrid({
                title: '<i class="las la-key"></i>' + _('Zoho Keys')
            }, this)
        ]
    });
};

Ext.extend(ZohoTabPanel, Ext.TabPanel, {});