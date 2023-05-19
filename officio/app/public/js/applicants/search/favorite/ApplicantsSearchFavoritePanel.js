var ApplicantsSearchFavoritePanel = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);
    var thisPanel = this;

    this.ApplicantsSearchFavoriteGrid = new ApplicantsSearchFavoriteGrid({
        region: 'center',
        height: config.height,
        panelType: config.panelType
    }, this);


    ApplicantsSearchFavoritePanel.superclass.constructor.call(this, {
        id: config.panelType + '_favorite_search_panel',
        title: '<span title="' + _('Search for particular cases quickly') + '">' + this.getPanelTitleBasedOnType(this.ApplicantsSearchFavoriteGrid.booLoadFavoritesOnly, true) + '</span>',
        layout: 'border',

        items: [
            this.ApplicantsSearchFavoriteGrid
        ],

        listeners: {
            render: function (p) {
                // Append the Panel to the click handler's argument list.
                p.getEl().on('click', thisPanel.handlePanelClick.createDelegate(this));
            }
        }
    });
};

Ext.extend(ApplicantsSearchFavoritePanel, Ext.Panel, {
    refreshList: function () {
        this.ApplicantsSearchFavoriteGrid.getStore().load();
    },

    getPanelTitleBasedOnType: function (booLoadFavoritesOnly, booWithIcon) {
        var title = booLoadFavoritesOnly ? _('Favourite Searches') : _('All Saved Searches');

        if (booWithIcon) {
            title += '<i class="down"></i>'
        }

        return title;
    },

    handlePanelClick: function (e) {
        if ($(e.target).hasClass('x-panel-header-text') || $(e.target).parent().hasClass('x-panel-header-text') || $(e.target).parent().parent().hasClass('x-panel-header-text')) {
            var thisPanel = this;

            var contextMenuId = 'applicants-searches-menu';
            var menu = Ext.getCmp(contextMenuId);
            if (menu) {
                menu.destroy();
            } else {
                // Context menu
                var contextMenuFT = new Ext.menu.Menu({
                    id: contextMenuId,
                    enableScrolling: false,
                    cls: 'no-icon-menu',
                    items: [
                        {
                            text: this.getPanelTitleBasedOnType(!this.ApplicantsSearchFavoriteGrid.booLoadFavoritesOnly, false),
                            handler: function () {
                                thisPanel.ApplicantsSearchFavoriteGrid.booLoadFavoritesOnly = thisPanel.ApplicantsSearchFavoriteGrid.booLoadFavoritesOnly ? 0 : 1;
                                thisPanel.refreshList();

                                var title = '<span title="' + _('Search for particular cases quickly') + '">' + thisPanel.getPanelTitleBasedOnType(thisPanel.ApplicantsSearchFavoriteGrid.booLoadFavoritesOnly, true) + '</span>';
                                thisPanel.setTitle(title);
                            }
                        }
                    ]
                });

                var target = $(e.target).hasClass('down') ? $(e.target).parent().get(0) : $(e.target).get(0);
                contextMenuFT.show(Ext.get(target));
            }
        }
    }
});
