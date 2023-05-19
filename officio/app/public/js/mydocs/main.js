function loadMyDocs() {
    var myDocsHeight = 0;
    if (initPanelSize() < 613) {
        myDocsHeight = 637;
    } else {
        if (typeof is_superadmin !== 'undefined' && is_superadmin) {
            myDocsHeight = initPanelSize() - 40;
        } else {
            myDocsHeight = initPanelSize();
        }
    }

    $('#mydocs-tab').height(myDocsHeight);
    if (empty(Ext.getCmp('mydocs-files-tab'))) {
        //create tab panel
        var menuTabId = Ext.id();
        var mydocsTabs = new Ext.TabPanel({
            id: 'mydocs-files-tab',
            renderTo: 'mydocs-content',
            hideMode: 'visibility',
            autoWidth: true,
            enableTabScroll: true,
            autoHeight: true,
            deferredRender: false,
            plain: true,
            cls: 'clients-tab-panel',
            defaults: {
                autoHeight: true,
                autoScroll: true
            },

            plugins: [
                new Ext.ux.TabCloseMenu()
            ],

            items: [{
                id: menuTabId,
                text:      '&nbsp;',
                iconCls:   'main-navigation-icon',
                listeners: {
                    'render': function (oThisTab) {
                        var oMenuTab = new Ext.ux.TabUniquesNavigationMenu({
                            navigationTab: Ext.get(oThisTab.tabEl)
                        });

                        oMenuTab.initNavigationTab(oMenuTab);
                    }
                }
            }],

            listeners: {
                'beforetabchange': function (oTabPanel, newTab) {
                    if (newTab.id === menuTabId) {
                        return false;
                    }
                }
            }
        });

        $('#mydocs-files-tab').height(myDocsHeight - 12);

        //load default sub tabs
        if (allowedMyDocsSubTabs.has('documents')) {
            if (!Ext.getCmp('mydocs-docs-sub-tab')) {
                mydocsTabs.add({
                    id: 'mydocs-docs-sub-tab',
                    title: _('My Documents'),
                    html: '<div id="mydocs-docs"></div>',
                    booLoaded: false,

                    listeners: {
                        activate: function () {
                            if (!this.booLoaded) {
                                var previewPanelWidth = booPreviewFilesInNewBrowser ? 0 : mydocsTabs.getWidth() / 2;
                                var treeWidth = mydocsTabs.getWidth() - previewPanelWidth;

                                new DocumentsPanel({
                                    treeId: Ext.id(),
                                    treeWidth: treeWidth,
                                    treeHeight: myDocsHeight,
                                    panelType: 'mydocs',
                                    memberId: 0,
                                    renderTo: 'mydocs-docs'
                                });

                                this.booLoaded = true;
                            }

                            $('#mydocs-tab').height(myDocsHeight + 55);
                            $('#mydocs-files-tab').height(myDocsHeight + 55);
                            Ext.getCmp('mydocs-tab').hash = setUrlHash('#mydocs/docs');
                        }
                    }
                });
            }

            //set hash
            var hash = parseUrlHash(location.hash);
            if (hash[1] == 'docs' || !hash[1]) {
                Ext.getCmp('mydocs-docs-sub-tab').show();

                $('#mydocs-tab').height(myDocsHeight + 55);
                $('#mydocs-files-tab').height(myDocsHeight + 55);
            }
        }
    }
}

function closeMydocsTab(tabId) {
    Ext.getCmp('mydocs-files-tab').remove(tabId);
}