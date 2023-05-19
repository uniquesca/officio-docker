var DocumentsPreviewPanel = function (config, owner) {
    var thisDocumentsPreviewPanel = this;
    this.owner = owner;
    Ext.apply(this, config);

    this.tabsHeaderHeight = 42;
    this.titleBlockHeight = 48;

    this.previewTabPanel = new Ext.TabPanel({
        autoWidth: true,
        autoHeight: true,
        border: false,
        cls: 'tabs-second-level-small',
        items: [],

        resizeTabs: true, // turn on tab resizing
        minTabWidth: 175,
        tabWidth: 175,
        enableTabScroll: true,
        defaults: {autoScroll: true},

        listeners: {
            'remove': function () {
                thisDocumentsPreviewPanel.onTabAddedOrRemoved();
            },

            'resize': function (thisTabPanel) {
                setTimeout(function () {
                    var panelWidth = thisTabPanel.getWidth();
                    var panelHeight = thisDocumentsPreviewPanel.getPreviewPanelCalculatedHeight();
                    thisTabPanel.items.each(function (tab) {
                        var component = Ext.getCmp(tab.id + '-component');
                        if (component) {
                            component.setWidth(panelWidth);
                            component.setHeight(panelHeight);

                            $('#' + component.id + ' iframe').height(panelHeight);
                        }
                    });
                }, 50)
            }
        }
    });

    DocumentsPreviewPanel.superclass.constructor.call(this, {
        hasPreview: false,
        booIsPreviewExpanded: config.booIsPreviewExpanded,
        cls: config.booIsPreviewExpanded ? 'panel-expanded' : 'panel-not-expanded',
        items: this.previewTabPanel
    });


    this.on('afterrender', this.showPreviewPanel.createDelegate(this), this);
};

Ext.extend(DocumentsPreviewPanel, Ext.Panel, {
    onTabAddedOrRemoved: function () {
        var tabsCount = this.previewTabPanel.items.getCount();
        if (empty(tabsCount)) {
            if (!this.hasPreview) {
                this.showPreviewPanel();
                this.toggleExpandedMode(true);
            }
        }
    },

    showPreviewPanel: function () {
        this.previewTabPanel.removeAll();

        this.previewTabPanel.add({
            xtype: 'box',
            title: 'Preview!',
            closable: true,
            'autoEl': {
                'tag': 'div',
                'class': 'files-preview-container',
                'html': '<div style="padding: 20px;">' + _('Please select a file to see its details') + '</div>'
            }
        });

        this.previewTabPanel.setActiveTab(0);

        this.previewTabPanel.addClass('document-preview-tabpanel');

        this.hasPreview = true;
    },

    getFileTabId: function (fileHash) {
        return btoa('files-tab-' + fileHash).slice(0, -2);
    },

    addNewComponentTab: function (filename, fileHash, tabXType, booCanBeChanged) {
        var thisPanel = this;

        if (thisPanel.hasPreview) {
            thisPanel.previewTabPanel.remove(thisPanel.previewTabPanel.getActiveTab(), true);
            thisPanel.previewTabPanel.removeClass('document-preview-tabpanel');
            thisPanel.hasPreview = false;
        }

        var tab_id = thisPanel.getFileTabId(fileHash);
        var newComponent = Ext.getCmp(tab_id + '-component');
        if (!newComponent) {
            var previewHeight = thisPanel.getPreviewPanelCalculatedHeight();
            newComponent = new Ext.ComponentMgr.create({
                id: tab_id + '-component',
                header: false,
                xtype: tabXType,
                loadMask: true,
                cls: 'document-preview-tabpanel',
                appliedHeight: previewHeight,
                autoWidth: true,
                frameConfig: {
                    style: 'width: 100%; height: ' + previewHeight + 'px; background-color: white;'
                }
            });

            var container = new Ext.Container({
                id: tab_id,

                title: filename,
                closable: true,
                booCanBeChanged: booCanBeChanged,

                height: previewHeight,
                items: [
                    {
                        xtype: 'container',
                        layout: 'table',
                        height: thisPanel.titleBlockHeight,
                        cls: 'document-preview-table',

                        layoutConfig: {
                            tableAttrs: {
                                style: {
                                    width: '100%'
                                }
                            },
                            columns: 3
                        },

                        items: [
                            {
                                xtype: 'box',
                                cellCls: 'document-preview-table-first-td',

                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    html: '<i class="las la-download"></i>',
                                    title: _('Toggle Date and Size columns and use that space for the file preview area')
                                },

                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            if (thisPanel.owner.documentsTree.isVisible()) {
                                                thisPanel.booIsPreviewExpanded = !thisPanel.booIsPreviewExpanded;
                                                thisPanel.owner.documentsTree.toggleColumns(thisPanel.booIsPreviewExpanded);

                                                if (thisPanel.booIsPreviewExpanded) {
                                                    thisPanel.addClass('panel-expanded');
                                                    thisPanel.removeClass('panel-not-expanded');
                                                } else {
                                                    thisPanel.removeClass('panel-expanded');
                                                    thisPanel.addClass('panel-not-expanded');
                                                }

                                                // Save for the future
                                                Ext.state.Manager.set(thisPanel.panelType + '-preview-expanded', thisPanel.booIsPreviewExpanded);
                                            }
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }, {
                                cellCls: 'document-preview-table-second-td',
                                xtype: 'box',
                                autoEl: {
                                    html: filename
                                }
                            }, {
                                cellCls: 'document-preview-table-third-td',
                                xtype: 'box',

                                autoEl: {
                                    tag: 'a',
                                    href: '#',
                                    html: '<i class="las la-expand-arrows-alt"></i>',
                                    title: _('Expand/collapse file preview area.')
                                },

                                listeners: {
                                    scope: this,
                                    render: function (c) {
                                        c.getEl().on('click', function () {
                                            var booIsVisible = thisPanel.owner.toolbarPanel.ownerCt.isVisible();
                                            thisPanel.toggleExpandedMode(!booIsVisible);
                                        }, this, {stopEvent: true});
                                    }
                                }
                            }
                        ]
                    },
                    newComponent
                ]
            });

            thisPanel.previewTabPanel.add(container);
        } else {
            // Don't try to update the iframe
            newComponent = false;
        }

        thisPanel.previewTabPanel.setActiveTab(tab_id);

        return newComponent;
    },

    toggleExpandedMode: function (booShowAllPanels) {
        if (booShowAllPanels) {
            this.removeClass('panel-maximized');
        } else {
            this.addClass('panel-maximized');
        }

        this.owner.toolbarPanel.ownerCt.setVisible(booShowAllPanels);
        this.owner.documentsTree.setVisible(booShowAllPanels);
        this.owner.doLayout();
    },

    getPreviewPanelCalculatedHeight: function () {
        var thisPanel = this;
        var panelHeight = thisPanel.owner.getHeight() - thisPanel.titleBlockHeight - thisPanel.tabsHeaderHeight;
        if (thisPanel.owner.toolbarPanel.isVisible()) {
            panelHeight -= thisPanel.owner.toolbarPanel.getHeight();
        } else {
            // top padding above the tab headers section
            panelHeight -= 10;
        }

        return panelHeight;
    },

    closePreviews: function (arrFilesIds) {
        var thisPanel = this;

        Ext.each(arrFilesIds, function (fileId) {
            var tab = Ext.getCmp(thisPanel.getFileTabId(fileId));
            if (tab) {
                thisPanel.previewTabPanel.remove(tab);
            }
        });
    },

    // Check if at least one file is opened in the preview
    // And if this file can be changed
    areFilesOpenedThatCanBeChanged: function (arrFilesHashes) {
        var thisPanel = this;
        var booOpened = false;

        Ext.each(arrFilesHashes, function (fileHash) {
            var tab = Ext.getCmp(thisPanel.getFileTabId(fileHash));
            if (tab && tab.booCanBeChanged) {
                booOpened = true;

                return false;
            }
        });

        return booOpened;
    }
});
