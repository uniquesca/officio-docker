var CompaniesTabPanel = function () {
    var containerHtml = String.format(
        '<div style="padding: 20px 20px 0; background-color: #fff;">' +
            '<div id="companies_advanced_search_container" style="padding-bottom: 10px; clear: both;"></div>' +
            '<div id="companies_list_container"></div>' +
        '</div>'
    );

    var menuTabId = Ext.id();
    CompaniesTabPanel.superclass.constructor.call(this, {
        id: 'companies-tab-panel',
        renderTo: 'companies-container',
        deferredRender: false,
        autoHeight: true,
        autoWidth: true,
        plain: true,
        activeTab: 1,
        enableTabScroll: true,
        minTabWidth: 200,
        cls: 'clients-tab-panel',

        items: [
            {
                id: menuTabId,
                text:      '&nbsp;',
                iconCls:   'main-navigation-icon',
                listeners: {
                    'afterrender': function (oThisTab) {
                        var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                            navigationTab: Ext.get(oThisTab.tabEl)
                        });

                        oMenuTab.initNavigationTab(oMenuTab);
                    }
                }
            }, {
                title: _('Companies'),
                xtype: 'container',

                items: {
                    html: containerHtml,

                    listeners: {
                        'afterrender': function () {
                            // Init company quick search
                            if (arrAccessRights['companies_search']) {
                                initCompanyQuickSearch();
                            } else {
                                $('#company_search').hide();
                            }

                            // Init company advanced search
                            if (arrAccessRights['advanced_search']) {
                                new CompaniesAdvancedSearch();
                            }

                            // Create a grid
                            new CompaniesGrid();
                        }
                    }
                }
            }
        ],

        listeners: {
            'beforetabchange': function (oTabPanel, newTab) {
                if (newTab.id === menuTabId) {
                    return false;
                }
            }
        }
    });
};

Ext.extend(CompaniesTabPanel, Ext.TabPanel, {
});