var ProspectsSearchPanel = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);
    var thisPanel = this;

    // Save current search - will be sued in the grid
    this.quickSearchField = new Ext.form.TextField({
        hideLabel:       true,
        width:           '100%',
        height: 30,
        style: 'border-radius: 5px',
        enableKeyEvents: true,
        emptyText: 'Search...',

        listeners:       {
            'keypress': function (field, event) {
                if (event.getKey() == event.ENTER && field.getValue().length >= 2) {
                    thisPanel.refreshProspectsSearchList(true);
                }
            },
            'valid':    function (field) {
                if (field.getValue().length === 1) {
                    field.markInvalid('Please enter at least 2 characters');
                } else {
                    field.getEl().removeClass('x-form-invalid');
                }
            }
        }
    });

    ProspectsSearchPanel.superclass.constructor.call(this, {
        id:           config.panelType + '_quick_search_panel',
        style:        'padding: 5px;',
        layout:       'table',
        cls:          'garytxt',
        defaults:     {
            width: '100%'
        },
        layoutConfig: {
            tableAttrs: {
                style: {
                    width: '100%'
                }
            },
            columns:    2
        },

        items: [
            this.quickSearchField, {
                xtype:   'button',
                cellCls: 'search-button-container',
                style:   'padding-left: 10px',
                iconCls: 'icon-prospects-search',
                height: 30,
                handler: function () {
                    var searchVal = trim(thisPanel.quickSearchField.getValue());
                    if (searchVal.length >= 2) {
                        thisPanel.refreshProspectsSearchList(true);
                    }
                }
            }
        ]
    });
};

Ext.extend(ProspectsSearchPanel, Ext.Panel, {
    openAdvancedSearchTab: function () {
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        tabPanel.loadProspectsTab({tab: 'advanced-search'});
    },

    refreshProspectsSearchList: function (booQuickSearch) {
        if (booQuickSearch && !this.quickSearchField.isValid()) {
            return;
        }

        var val = this.quickSearchField.getValue().trim();
        // Strip tags
        val     = val.replace(/<\/?[\^>]+>/gi, '');

        if (booQuickSearch && val.length < this.quickSearchField.minLength) {
            this.quickSearchField.markInvalid();
            return;
        }

        // Switch to 'All prospects' tab and run search
        var tabPanel = Ext.getCmp(this.panelType + '-tab-panel');
        tabPanel.loadProspectsTab({
            tab:          'search-prospects',
            doNotLoadTab: true
        });

        // Apply query
        var prospectsStore = Ext.getCmp(this.panelType + '-grid').getStore();
        if (prospectsStore) {
            prospectsStore.setBaseParam('filter', Ext.encode(val)); //add search query to base params
            prospectsStore.load(); //reload prospects grid
        }
    }
});