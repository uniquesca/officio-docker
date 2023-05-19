Ext.onReady(function(){
    //company select
    Ext.EventManager.on('fieldsCompanyId', 'change', function(obj, elm){
        document.location = baseUrl + '/trust-account-settings/index?company_id=' + elm.value;
    });
});