Ext.ns('Ext.ux');
Ext.ux.TabCustomButton = Ext.extend(Object, {
    linkText: '',
    linkIconCls: null,
    linkCtCls: null,
    linkWidth: 110,
    linkHandler: null,

    constructor: function (config) {
        config = config || {};
        Ext.apply(this, config);
    },

    init: function (tabPanel) {
        var thisRef = this;
        tabPanel.tabCustomButton = this;
        tabPanel.on({
            afterrender: {
                scope:  tabPanel,
                single: true,
                fn:     function () {
                    tabPanel.setActiveTab = tabPanel.setActiveTab.createSequence(thisRef.createPanelsMenuHeader, this);
                }
            }
        });
    },

    createPanelsMenuHeader: function () {
        var thisTabPanel = this;

        // Check if button was already created
        if (thisTabPanel.header.child('.x-tab-tabmenu-right')) {
            return;
        }

        // Move the right menu item to the left 18px
        var rtScrBtn = this.header.dom.firstChild;

        setTimeout(function(){
            Ext.fly(rtScrBtn).applyStyles({
                width: (thisTabPanel.getWidth() - thisTabPanel.tabCustomButton.linkWidth) + 'px'
            });
        }, 100);

        Ext.util.CSS.createStyleSheet(
            '.x-tab-tabmenu-right {' +
                'position: absolute;' +
                'width: ' + thisTabPanel.tabCustomButton.linkWidth + 'px;' +
                'top: 0;' +
                'right: 0;' +
                'z-index: 99;' +
                'height: 23px;' +
                'border-bottom: 1px solid #000;' +
            '}',
            'tabMenu'
        );


        var btnId = Ext.id();
        var tabMenu = thisTabPanel.header.insertFirst({
            cls: 'x-tab-tabmenu-right',
            width: thisTabPanel.tabCustomButton.linkWidth,
            html: '<div id="' + btnId + '"></div>'
        });

        new Ext.Button({
            renderTo: btnId,
            iconCls:  thisTabPanel.tabCustomButton.linkIconCls,
            ctCls:    thisTabPanel.tabCustomButton.linkCtCls,
            text:     thisTabPanel.tabCustomButton.linkText,
            width:    thisTabPanel.tabCustomButton.linkWidth,
            handler:  thisTabPanel.tabCustomButton.linkHandler
        });
    }
});