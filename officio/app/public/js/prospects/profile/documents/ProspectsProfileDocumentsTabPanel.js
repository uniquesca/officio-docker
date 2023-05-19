var ProspectsProfileDocumentsTabPanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    this.documentsTree = null;
    this.renderToId = config.panelType + '-files-tab-' + config.prospectId;
    ProspectsProfileDocumentsTabPanel.superclass.constructor.call(this, {
        hideMode: 'visibility',
        autoWidth: true,
        autoHeight: true,
        plain: true,
        enableTabScroll: true,
        defaults: {
            autoScroll: true,
            autoHeight: true
        },
        plugins: new Ext.ux.TabCloseMenu(),
        style: 'text-align:left',
        cls: 'clients-sub-tab-panel clients-sub-sub-tab-panel extjs-grid-noborder strip-hidden',
        activeTab: 0,
        items: [
            {
                title: 'Files & Folders',
                html: '<div id="' + this.renderToId + '"></div>'
            }
        ]
    });

    this.on('tabchange', this.onAfterRender.createDelegate(this));
};

Ext.extend(ProspectsProfileDocumentsTabPanel, Ext.TabPanel, {
    onAfterRender: function () {
        var thisPanel = this;

        if (empty(thisPanel.documentsTree)) {
            var previewPanelWidth = booPreviewFilesInNewBrowser ? 0 : thisPanel.getWidth() / 2;
            var treeWidth = thisPanel.getWidth() - previewPanelWidth;

            new DocumentsPanel({
                treeId: Ext.id(),
                treeWidth: treeWidth,
                treeHeight: initPanelSize() - 10,
                panelType: thisPanel.panelType,
                memberId: thisPanel.prospectId,
                renderTo: thisPanel.renderToId
            });
        }

        var divId = thisPanel.panelType + '-prospectDocumentsTabForm-' + thisPanel.prospectId;
        var el = $('#' + divId);
        el.height(initPanelSize() - 10);
    }
});
