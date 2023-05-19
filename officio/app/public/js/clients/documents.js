function showDocuments(member_id) {
    //get main tab
    var mainTab = Ext.getDom('folders-tree-' + member_id);
    if (!mainTab) {
        return false;
    }

    //reset tab content
    mainTab.innerHTML = '';

    //render folders sub tabs
    var dfTabs = new Ext.TabPanel({
        renderTo: 'folders-tree-' + member_id,
        clientId: member_id,
        hideMode: 'visibility',
        autoWidth: true,
        autoHeight: true,
        activeTab: 0,
        enableTabScroll: true,
        plain: true,
        plugins: new Ext.ux.TabCloseMenu(),
        style: 'text-align:left',
        cls: 'clients-sub-tab-panel clients-sub-sub-tab-panel extjs-grid-noborder strip-hidden',
        defaults: {
            autoHeight: true,
            autoScroll: true
        },

        items: [
            {
                title: 'Files & Folders',
                html: '<div id="docs-files-tab-' + member_id + '"></div>'
            }
        ]
    });

    var previewPanelWidth = booPreviewFilesInNewBrowser ? 0 : dfTabs.getWidth() / 2;
    var treeWidth = dfTabs.getWidth() - previewPanelWidth;

    new DocumentsPanel({
        treeId: 'docs-tree-' + member_id,
        treeWidth: treeWidth,
        treeHeight: initPanelSize(),
        panelType: 'clients',
        memberId: member_id,
        renderTo: 'docs-files-tab-' + member_id
    });
}

function checkIfCanEdit()
{
     var applicantPanel = Ext.getCmp('applicants-tab-panel');

     var booCanEdit = true;
     if (applicantPanel) {
        booCanEdit = applicantPanel.getActiveTab().items.items[0].booCanEdit;
     }

     return booCanEdit;
}


