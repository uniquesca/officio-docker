Ext.onReady(function () {
    Ext.QuickTips.init();

    var storageProvider = new Ext.ux.state.LocalStorageProvider({prefix: 'uniques-'});
    if (storageProvider.getStorageObject() === false) {
        // Use cookie storage if local storage is not available
        storageProvider = new Ext.state.CookieProvider({
            expires: new Date(new Date().getTime() + (1000 * 60 * 60 * 24 * 365)) //1 year from now
        })
    }

    var membersContainer = $('#members_container');
    if (membersContainer.length) {
        membersContainer.css('min-height', getSuperadminPanelHeight() + 'px');

        new ManageMembersPanel({
            renderTo: 'members_container'
        });

        if (!membersContainer.parents('#manage-company-content').length) {
            updateSuperadminIFrameHeight('#members_container');
        }
    }
});