Ext.onReady(function() {
    Ext.QuickTips.init();

    new GeneratePasswordButton({
        renderTo: 'generatePassword',
        passwordField: 'newPassword'
    });
});