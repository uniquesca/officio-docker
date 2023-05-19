var showAccountSuspendedWindow = function() {
    var msg = 'Your account has been suspended. Please contact ' + site_company_phone + ' to restore your account.';
    Ext.Msg.show({
        title: 'Warning',
        msg: msg,
        modal: true,
        closable: false,
        buttons:Ext.Msg.OK,
        fn: function(){
            window.location = baseUrl + '/auth/logout';
        },
        icon: Ext.Msg.WARNING
    });

    var tabsContainer = $('#main-tabs');
    if (tabsContainer) {
        tabsContainer.html(msg);
    }
};
