function checkall(objForm){
    for (var i = 0; i < objForm.elements.length; i++) {
        if (objForm.elements[i].type=='checkbox') {
            objForm.elements[i].checked = objForm.check_all.checked;
        }
    }
}

function submit_form(objForm, action){
    var arrCheckedCheckboxes = $("input[name^='delIDs']:checked");
    var checked = arrCheckedCheckboxes.length;
    
    var booCheckOneItem;
    var checkName = '';
    var checkNameSeveral = '';
    var checkNameId = '';
    switch(objForm.name)
    {
        case 'roles_form' :
            booCheckOneItem = false;
            checkName = 'role';
            checkNameSeveral = (checked == 1 ? 'role' : 'roles');
            checkNameId = 'role_id_';
            break;
        case 'members_form' :
            booCheckOneItem = true;
            checkName = 'user';
            checkNameSeveral = (checked == 1 ? 'user' : 'users');
            checkNameId = 'member_id_';
            break;
        case 'admin_users_form' :
            booCheckOneItem = true;
            checkName = 'superadmin';
            checkNameSeveral = (checked == 1 ? 'superadmin' : 'superadmins');
            checkNameId = 'superadmin_id_';
            break;
        default:
            booCheckOneItem = true;
            checkName = 'company';
            checkNameSeveral = (checked == 1 ? 'company' : 'companies');
            checkNameId = 'company_id_';
            break;
    }
    
    if(action == 'Delete' && objForm.name == 'members_form') {
        Ext.simpleConfirmation.warning('Delete user is not allowed because users are referenced in various notes, tasks and other history of records.');
        return false;
    }
    
    if (checked === 0){
        var alert_msg = String.format(
            'Please select {0} {1} to perform an action.',
            booCheckOneItem ? 'one' : 'at least one',
            checkName
        );
        
        Ext.simpleConfirmation.warning(alert_msg);
        return false;
    }
    
    switch(action)
    {
        case 'Delete':
            if(booCheckOneItem && checked > 1) {
                Ext.simpleConfirmation.warning('Please select only one '+checkName+' to delete');
                return false;
            }
        
            // Get selected item(s) name
            var checkedItemName = '';
            for(var i=0; i<checked; i++) {
                var checkedItemId = $(arrCheckedCheckboxes[i]).val();
                checkedItemName += $('#' + checkNameId + checkedItemId).html();
                if(i<checked-1)
                    checkedItemName += '<br/>';
            }
            
            Ext.Msg.show({
               title:'Please confirm',
               msg: 'Are you sure you want to delete '+checkNameSeveral+' <br/><i>' + checkedItemName + '</i>?',
               buttons: {yes: 'Delete', no: 'Cancel'},
               minWidth: 300,
               modal: true,
               fn: function(btn){
                    if(btn == 'yes'){
                        objForm.listingAction.value = 'delete';
                        objForm.submit();
                    }
               },
               icon: Ext.MessageBox.WARNING
            });
            break;
            
        case 'Suspend':
            objForm.listingAction.value = 'suspend';
            objForm.submit();
            break;
            
        case 'Deactivate':
            objForm.listingAction.value = 'deactivate';
            objForm.submit();
            break;
            
        case 'Activate':
            objForm.listingAction.value = 'activate';
            objForm.submit();
            break;

        default:
            break;
    }
    
    return false;
}
