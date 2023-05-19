Ext.ns('Ext.ux');
Ext.ux.TabCustomRightSection = Ext.extend(Object, {
    arrComponentsToRender: [],

    constructor: function (config) {
        config = config || {};
        Ext.apply(this, config);
    },

    init: function (tabPanel) {
        var thisRef = this;
        if (tabPanel instanceof Ext.TabPanel) {
            tabPanel.on('render', thisRef.initCustomRightSection.createDelegate(tabPanel, [thisRef]));
        }
    },

    initCustomRightSection: function (thisRef) {
        var containerId = Ext.id();

        thisRef.additionalCls = thisRef.additionalCls || '';
        this.header.insertFirst({
            cls:  'x-tab-tabmenu-right-section ' + thisRef.additionalCls,
            html: '<div id="' + containerId + '"></div>'
        });

        if (thisRef.arrComponentsToRender.length) {
            new Ext.Container({
                renderTo: containerId,
                layout:   'table',
                items:    thisRef.arrComponentsToRender
            });
        }
    }
});
