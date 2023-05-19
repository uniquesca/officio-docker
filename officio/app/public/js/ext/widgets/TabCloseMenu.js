// Ext.ux.TabCloseMenu.js for 3.0-rc1

// Very simple plugin for adding a close context menu to tabs

Ext.ux.TabCloseMenu = function() {
    var tabs, menu, ctxItem;
    this.init = function(tp) {
        tabs = tp;
        tabs.on('contextmenu', onContextMenu);
    }

    function onContextMenu(tabPanel, tab, e) {
        var items = [];
        if (tab.closable) {
            items.push({
                text: 'Close Tab',
                handler: function() {
                    tabPanel.remove(tab);
                }
            });
        }
        var canCloseOthers = false;
        tabPanel.items.each(function() {
            if (this != tab && this.closable) {
                canCloseOthers = true;
                return false;
            }
            return true;
        });
        if (canCloseOthers) {
            items.push({
                text: 'Close Other Tabs',
                handler: function() {
                    tabPanel.items.each(function() {
                        if (this != tab && this.closable) {
                            tabPanel.remove(this);
                        }
                    })
                }
            })
        }
        var menu = new Ext.menu.Menu({
            items: items
        });
        menu.showAt(e.getXY());
    }
};