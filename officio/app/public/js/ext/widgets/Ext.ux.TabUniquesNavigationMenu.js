Ext.ns('Ext.ux');
Ext.ux.TabUniquesNavigationMenu = Ext.extend(Object, {
    mainTabPanel:    null,
    navigationTab:   null,
    navigationPopup: null,

    constructor: function (config) {
        config = config || {};
        Ext.apply(this, config);
    },

    init: function (tp) {
        var thisRef = this;
        if (tp instanceof Ext.TabPanel) {
            tp.on('render', thisRef.initNavigationTab.createDelegate(tp, [thisRef]));
        }
    },

    initNavigationTab: function (thisRef) {
        if (empty(thisRef.mainTabPanel)) {
            thisRef.mainTabPanel = Ext.getCmp('main-tab-panel');
        }

        var tabsCount = 0;
        thisRef.mainTabPanel.items.each(function (item) {
            if (item.booHideInMainMenu) {
                return;
            }

            tabsCount++;
        });

        if (tabsCount <= 1) {
            // don't show if there is only 1 item
            return;
        }

        // In some cases we already generated the Tab
        if (empty(thisRef.navigationTab)) {
            thisRef.navigationTab = this.itemTpl.insertFirst(this.getEl().child('.x-tab-strip'), {
                text: '&nbsp;',
                iconCls: 'main-navigation-icon'
            }, true);
        }

        $(thisRef.navigationTab.query('.main-navigation-icon')[0]).prop('title', _("Navigate Officio's modules using the Menu bar"));

        thisRef.navigationTab.on({
            mousedown: {
                fn: thisRef.onMouseClick,
                scope: thisRef
            },

            mouseover: {
                fn: thisRef.onMouseOver,
                scope: thisRef
            },

            mouseout: {
                fn: thisRef.onMouseOut,
                scope: thisRef
            }
        });

        thisRef.initNavigationPopup(this);
    },

    initNavigationPopup: function () {
        var thisRef = this;
        var thisTabPanel = this.mainTabPanel;
        var tabsCount = thisTabPanel.items.getCount();

        var arrButtons = [];
        if (tabsCount) {
            var previousParentTitle = '';
            var activeTab = thisTabPanel.getActiveTab();
            thisTabPanel.items.each(function (item) {
                if (item.booHideInMainMenu) {
                    return;
                }

                if (item.mainMenuItemWithPadding) {
                    arrButtons.push({
                        xtype: 'box',
                        cellCls: 'x-table-layout-cell-spacer',

                        autoEl: {
                            tag: 'div',
                            html: '&nbsp;'
                        }
                    });
                }

                var style = '';
                if (!empty(item.parentTitle)) {
                    if (empty(previousParentTitle)) {
                        arrButtons.push({
                            xtype: 'box',
                            autoEl: {
                                tag: 'a',
                                href: '#',
                                html: item.parentTitle
                            },

                            listeners: {
                                scope:  this,
                                render: function (c) {
                                    c.getEl().on('click', function () {
                                        var oActiveTab = thisTabPanel.getActiveTab();
                                        if (oActiveTab.id != item.id) {
                                            thisTabPanel.setActiveTab(item.id);
                                        } else {
                                            item.fireEvent('activate');
                                        }

                                        // Fire the 'click' event, so if we showed some popup (e.g. Help Context Window) -> will be closed
                                        $('body').trigger('click');

                                        wnd.hide();
                                    }, this, {stopEvent: true});
                                }
                            }
                        });
                    }

                    style = 'margin-left: 20px';
                }
                previousParentTitle = item.parentTitle;

                var active = activeTab.id === item.id ? 'active ' : '';
                var cellCls = activeTab.id === item.id ? 'x-table-layout-cell-active ' : (item.cellCls ? item.cellCls : '');

                arrButtons.push({
                    xtype: 'box',
                    'cellCls': cellCls,

                    autoEl: {
                        tag: 'a',
                        href: '#',
                        'class': active,
                        style: style,
                        html:    item.title
                    },

                    listeners: {
                        scope:  this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                thisRef.toggleMask(false);
                                var oActiveTab = thisTabPanel.getActiveTab();
                                if (oActiveTab.id != item.id) {
                                    thisTabPanel.setActiveTab(item.id);
                                } else {
                                    item.fireEvent('activate');
                                }

                                // Fire the 'click' event, so if we showed some popup (e.g. Help Context Window) -> will be closed
                                $('body').trigger('click');

                                wnd.hide();
                            }, this, {stopEvent: true});
                        }
                    }
                });
            });
        }

        var wnd = new Ext.Window({
            layout:     'fit',
            closable:   false,
            resizable:  false,
            plain:      true,
            border:     false,
            autoHeight: true,
            frame:      false,

            cls: 'officio-menu-dialog',

            items: {
                xtype:  'container',
                layout: 'table',
                width: 210,

                layoutConfig: {
                    columns: 1
                },

                items: arrButtons
            },

            listeners: {
                show: function (w) {
                    wnd.alignTo(thisRef.navigationTab, 'bl', [0, 0]);
                    wnd.getEl().slideIn('l', {
                        stopFx: true,
                        easing: 'easeOut',
                        duration: .2
                    });

                    thisRef.toggleMask(true);
                },

                'hide': function () {
                    thisRef.toggleMask(false);
                }
            }
        });

        this.navigationPopup = wnd;
    },

    toggleMask: function (booShow) {
        var oTab = this.mainTabPanel.getActiveTab();
        if (oTab) {
            var el = oTab.getEl().child('.x-tab-panel-bwrap');
            if (el) {
                if (booShow) {
                    el.mask();
                } else {
                    el.unmask();
                }
            }
        }
    },

    hideWithEffect: function () {
        var wnd = this.navigationPopup;
        wnd.getEl().slideOut('l', {
            easing:   'easeOut',
            duration: .2,
            stopFx:   true,
            callback: function () {
                wnd.hide();
            }
        });
    },

    onMouseClick: function (e) {
        e.stopEvent();

        if (arrHomepageSettings.settings.mouse_over_settings.includes('application-menu')) {
            return;
        }

        clearTimeout(this.timeoutId);

        var wnd = this.navigationPopup;
        if (wnd) {
            if (wnd.isVisible()) {
                this.hideWithEffect();
            } else {
                Ext.menu.MenuMgr.hideAll();
                wnd.show();
            }
        }
    },

    onMouseOver: function () {
        clearTimeout(this.timeoutId);

        if (arrHomepageSettings.settings.mouse_over_settings.includes('application-menu')) {
            var wnd = this.navigationPopup;
            if (wnd && !wnd.isVisible()) {
                Ext.menu.MenuMgr.hideAll();
                wnd.show();
            }
        }
    },

    onMouseOut: function () {
        var thisRef = this;

        clearTimeout(this.timeoutId);

        thisRef.timeoutId = setTimeout(function () {
            thisRef.checkIsMouseOutsideDialog();
        }, 300);
    },

    // Don't hide the popup if cursor is above the icon or this popup
    checkIsMouseOutsideDialog: function () {
        var thisRef = this;
        var wnd = thisRef.navigationPopup;

        if (wnd && wnd.isVisible() && !$('#' + wnd.id).is(':hover') && !$('#' + thisRef.navigationTab.id).is(':hover')) {
            clearTimeout(thisRef.timeoutId);
            this.hideWithEffect();
        } else {
            thisRef.timeoutId = setTimeout(function () {
                thisRef.checkIsMouseOutsideDialog();
            }, 300);
        }
    }
});