var prospectsAdvancedSearchPanel = function(config) {
    var thisPanel = this;
    Ext.apply(this, config);

    // Hide Grid and bottom panel by default
    var booHiddenByDefault = true;

    this.prospectsAdvancedSearchForm = new prospectsAdvancedSearchForm({}, this);
    this.prospectsAdvancedSearchGrid = new prospectsAdvancedSearchGrid({
        hidden: booHiddenByDefault,
        panelType: config.panelType,
        height: initPanelSize() - 235
    }, this);

    this.backToProspectsButton = new Ext.Button({
        xtype: 'button',
        text: '<i class="las la-arrow-left"></i>' + _('Back to Prospects'),
        style: 'margin-bottom: 15px',
        handler: function () {
            Ext.getCmp(thisPanel.panelType + '-tab-panel').loadProspectsTab({tab: 'all-prospects'});
        }
    });

    this.prospectsAdvancedSearchFormContainer = new Ext.Container({
        xtype: 'container',
        cls: 'whole-search-criteria-container',
        items: [
            this.prospectsAdvancedSearchForm
        ]
    });

    prospectsAdvancedSearchPanel.superclass.constructor.call(this, {
        id: config.panelType + '-advanced-search',
        autoHeight: true,
        collapsible: false,
        style: 'margin: 20px',
        items: [
            this.backToProspectsButton,
            this.prospectsAdvancedSearchFormContainer,
            this.prospectsAdvancedSearchGrid
        ]
    });
};

Ext.extend(prospectsAdvancedSearchPanel, Ext.Panel, {
    runSearch: function() {
        this.prospectsAdvancedSearchGrid.setVisible(true);
        this.prospectsAdvancedSearchGrid.store.load();
    }
});
