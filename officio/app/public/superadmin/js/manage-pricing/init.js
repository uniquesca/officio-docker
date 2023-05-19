Ext.onReady(function () {
    Ext.QuickTips.init();

    $('#pricing_container').css('min-height', getSuperadminPanelHeight() + 'px');

    new ManagePricingGrid({
        renderTo: 'pricing_container',
        height:   getSuperadminPanelHeight(),
        width:    '100%'
    });

    updateSuperadminIFrameHeight('#pricing_container');
});