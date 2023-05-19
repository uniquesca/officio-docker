var FilesPreviewDialog = function (config, owner) {
    Ext.apply(this, config);
    this.owner = owner;

    this.tabPanel = new Ext.TabPanel({
        autoWidth: true,
        autoHeight: true,
        border: false
    });
    this.tabPanel.on('add', this.onTabAddedOrRemoved.createDelegate(this));
    this.tabPanel.on('remove', this.onTabAddedOrRemoved.createDelegate(this));

    FilesPreviewDialog.superclass.constructor.call(this, {
        modal: false,
        stateful: false,
        resizable: true,
        cls: 'no-padding-window',
        items: this.tabPanel
    });

    this.on('move', this.onThisDialogMove.createDelegate(this));
    this.on('resize', this.onThisDialogResize.createDelegate(this));
};

Ext.extend(FilesPreviewDialog, Ext.Window, {
    showPreviewDialog: function () {
        var thisDialog = this;

        var dialogWidth = 0;
        var dialogHeight = 0;
        var dialogPositionX = null;
        var dialogPositionY = null;

        // Load from cookies, if set
        var arrSavedSettings = Ext.state.Manager.get('preview_dialog_settings');
        if (arrSavedSettings) {
            dialogWidth = arrSavedSettings[0];
            dialogHeight = arrSavedSettings[1];
            dialogPositionX = arrSavedSettings[2];
            dialogPositionY = arrSavedSettings[3];
        } else {
            // default values
            dialogWidth = thisDialog.owner.getWidth() - 400;
            dialogHeight = initPanelSize() - 5;
        }

        // Make sure that dimensions are not less than min and no more than max
        dialogWidth = Ext.max([dialogWidth, 100]);
        dialogHeight = Ext.max([dialogHeight, 100]);
        dialogWidth = Ext.min([dialogWidth, Ext.getBody().getViewSize().width]);
        dialogHeight = Ext.min([dialogHeight, Ext.getBody().getViewSize().height]);

        thisDialog.show();

        thisDialog.suspendEvents();
        thisDialog.setHeight(dialogHeight);
        thisDialog.setWidth(dialogWidth);

        // Place dialog in the same position as it was before
        // If not moved - in the default place
        if (dialogPositionX === null || dialogPositionY === null) {
            thisDialog.anchorTo(thisDialog.owner.getEl(), 'tr-tr', [-15, 0]);
        } else {
            thisDialog.setPosition(dialogPositionX, dialogPositionY);
        }

        thisDialog.resumeEvents();
    },

    onThisDialogMove: function (wnd, positionX, positionY) {
        Ext.state.Manager.set('preview_dialog_settings', [wnd.getWidth(), wnd.getHeight(), positionX, positionY])
    },

    onThisDialogResize: function (wnd, width, height) {
        var thisDialog = this;
        var availableHeight = $('#' + thisDialog.id + ' .x-window-body').outerHeight();
        if (thisDialog.tabPanel.items.getCount() > 1) {
            // The tab headers panel is visible
            availableHeight -= $('#' + thisDialog.id + ' .x-tab-panel-header').outerHeight();
        }

        thisDialog.tabPanel.items.each(function (oTab) {
            oTab.setHeight(availableHeight);
            $('#' + oTab.id + ' iframe').css('height', availableHeight)
        });

        var position = thisDialog.getPosition();
        Ext.state.Manager.set('preview_dialog_settings', [width, height, position[0], position[1]])
    },

    addNewComponentTab: function (title, tab_id, tab_xtype) {
        var thisDialog = this;

        var newComponent = Ext.getCmp(tab_id);
        if (!newComponent) {
            var previewHeight = $('#' + thisDialog.id + ' .x-window-body').outerHeight();
            newComponent = new Ext.ComponentMgr.create({
                id: tab_id,
                title: title,
                closable: true,
                header: false,
                xtype: tab_xtype,
                loadMask: true,
                cls: 'document-preview-tabpanel',
                appliedHeight: previewHeight,
                frameConfig: {
                    style: 'width: 100%; height: ' + previewHeight + 'px; background-color: white;'
                }
            });

            thisDialog.tabPanel.add(newComponent);
        }

        thisDialog.tabPanel.setActiveTab(tab_id);

        return newComponent;
    },

    onTabAddedOrRemoved: function (tabPanel) {
        var thisDialog = this;
        var tabsCount = tabPanel.items.getCount();
        if (empty(tabsCount)) {
            thisDialog.close();
        } else {
            if (tabsCount === 1) {
                thisDialog.setTitle(tabPanel.items.get(0).title);
                tabPanel.addClass('document-preview-tabpanel');
            } else {
                thisDialog.setTitle(_('Files Preview'));
                tabPanel.removeClass('document-preview-tabpanel');
            }

            setTimeout(function () {
                thisDialog.onThisDialogResize(thisDialog, thisDialog.getWidth(), thisDialog.getHeight());
            }, 100);
        }
    }
});