var addGroup = function(block_id, group_id, group_name, group_collapsed, columnsCount) {
    var columnsTd = '';
    for (var i = 0; i < columnsCount; i++) {
        columnsTd += '<td class="fields_column"></td>';
    }

    var additionalCls = group_collapsed ? ' group_collapsed' : '';
    additionalCls += ' group_columns_count_' + columnsCount;
    var newGroupHtml =
        '<div class="portlet" id="fields_group_' + group_id + '">' +
            '<div class="portlet-header">' +
                '<span class="group_name ' + additionalCls + '">' + group_name + '</span>' +
            '</div>' +
            '<table class="portlet-content"><tr>' + columnsTd + '</tr></table>' +
        '</div>';

    $("#block_column_" + block_id).append(newGroupHtml);

    initGroupActions();
};

var setGroupColsCount = function(groupId, newColsCount) {
    var oldColsCount = 0;
    var groupNameSpan = $('#' + groupId).find('.group_name:first');
    if (groupNameSpan.attr('class') != undefined) {
        var classList = groupNameSpan.attr('class').split(/\s+/);
        $.each(classList, function (index, item) {
            var match = item.match(/^group_columns_count_([\d]{1,})$/);
            if (match != null) {
                oldColsCount = match[1];
                groupNameSpan.removeClass(item);
            }
        });
    }
    groupNameSpan.addClass('group_columns_count_' + newColsCount);

    if(oldColsCount != newColsCount) {
        Ext.simpleConfirmation.warning(
            'For changes in the number of fields in the group to take effect, ' +
            'you first must save and close the template & then reopen it.'
        );
    }
};

var updateGroupProperties = function(groupId, groupName, booIsGroupCollapsed, newColsCount) {
    var group = $('#fields_group_' + groupId).find('.group_name:first');
    group.html(groupName);
    if (booIsGroupCollapsed) {
        group.addClass('group_collapsed');
    } else {
        group.removeClass('group_collapsed');
    }

    setGroupColsCount(groupId, newColsCount);
};

var addUpdateGroupRequest = function(blockId, groupId, groupName, groupColumnsCount, booIsGroupCollapsed, win) {
    win.getEl().mask('Saving...');

    Ext.Ajax.request({
        url:    empty(groupId) ? submissionUrl + '/add-group' : submissionUrl + '/edit-group',
        params: {
            member_type:      Ext.encode(memberType),
            company_id:       companyId,
            block_id:         Ext.encode(blockId),
            group_id:         Ext.encode(groupId),
            group_name:       Ext.encode(groupName),
            group_collapsed:  Ext.encode(booIsGroupCollapsed),
            group_cols_count: Ext.encode(groupColumnsCount)
        },

        success: function(result){
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                // Show the confirmation message
                win.getEl().mask(resultData.message);

                setTimeout(function(){
                    if(empty(groupId)) {
                        addGroup(blockId, resultData.group_id, groupName, booIsGroupCollapsed, groupColumnsCount);
                    } else {
                        // Update group info on the page
                        updateGroupProperties(groupId, groupName, booIsGroupCollapsed, groupColumnsCount);
                    }

                    win.close();
                    fixManageFieldsPageHeight(500);
                }, confirmationTimeOut);

            } else {
                win.getEl().unmask();
                Ext.simpleConfirmation.error(resultData.message);
            }
        },

        failure: function(){
            win.getEl().unmask();
            Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
        }
    });
};

var showEditGroupDialog = function(blockId, groupId, groupName, groupColsCount, groupIsCollapsed) {
    var wndTitle = 'Create new group';
    var btnSubmitTitle = 'Create group';

    if(empty(groupId)) {
        // New group
        groupId = 0;
    } else {
        // Load info about the group
        wndTitle = 'Edit group details';
        btnSubmitTitle = 'Update group';
    }

    var frm = new Ext.FormPanel({
        labelWidth:  100, // label settings here cascade unless overridden
        bodyStyle:   'padding:5px 5px 0',
        autoWidth:   true,
        autoHeight:  true,
        defaults:    {width: 230},
        defaultType: 'textfield',

        items: [
            {
                id:         'group_name',
                name:       'group_name',
                fieldLabel: 'Group Name',
                value:      htmlspecialchars_decode(groupName),
                allowBlank: false
            }, {
                id:            'group_cols_count',
                name:          'group_cols_count',
                xtype:         'numberfield',
                fieldLabel:    'Columns count',
                width:         30,
                value:         groupColsCount,
                allowNegative: false,
                allowDecimals: false,
                minValue:      1,
                maxValue:      5,
                allowBlank:    false
            }, {
                id:        'group_collapsed',
                name:      'group_collapsed',
                xtype:     'checkbox',
                hideLabel: true,
                boxLabel:  'Is collapsed',
                checked:   groupIsCollapsed
            }
        ]
    });

    var win = new Ext.Window({
        title:     wndTitle,
        width:     400,
        plain:     false,
        modal:     true,
        items:     frm,
        resizable: false,
        buttons: [{
            text     : btnSubmitTitle,
            handler  : function(){
                if(frm.getForm().isValid()){
                    var newGroupName = Ext.getCmp('group_name').getValue();
                    var newGroupColsCount = Ext.getCmp('group_cols_count').getValue();
                    var booIsGroupCollapsed = Ext.getCmp('group_collapsed').getValue();

                    if (empty(blockId)) {
                        addBlock(false, newGroupName, booIsGroupCollapsed, newGroupColsCount, win);
                    } else {
                        addUpdateGroupRequest(blockId, groupId, newGroupName, newGroupColsCount, booIsGroupCollapsed, win);
                    }
                }
            }
        },{
            text     : 'Cancel',
            handler  : function(){
                win.close();
            }
        }]
    });

    win.show();
    win.center();
};

function htmlspecialchars_decode(str) {
    return str.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&apos;/g, '\'');
}

var deleteGroup = function(groupId, groupName) {
    // It is not possible to delete a group if it contain a field(s)
    var arrFields = $('#fields_group_' + groupId).find('.field_container');
    if (arrFields.length > 0) {
        // This group contains fields
        Ext.simpleConfirmation.warning('Please move fields to other groups, save changes and try again.');
        return;
    }

    Ext.MessageBox.buttonText.yes = "Yes, delete this group";
    Ext.MessageBox.buttonText.no = "No";
    Ext.MessageBox.confirm('Please confirm', 'Are you sure you want to delete group <span style="color: green; font-style: italic;">' + groupName + '</span> ?', function(btn) {
        if (btn == 'yes') {
            var body = Ext.getBody();
            body.mask('Deleting...');

            Ext.Ajax.request({
                url: submissionUrl + '/delete-group',
                params: {
                    company_id: Ext.encode(companyId),
                    group_id: Ext.encode(groupId)
                },

                success: function(result){
                    var resultData = Ext.decode(result.responseText);
                    if (!resultData.error) {
                        var group = $("#fields_group_" + groupId);
                        var block = group.parents(".block_column:first")[0];
                        var allBlockGroups = $(block).find('.portlet');
                        if (allBlockGroups.length == 1) {
                            $(block).remove();
                        } else {
                            // Remove this group from html
                            group.remove();
                        }

                        // Show a confirmation
                        body.mask('Done !');
                        setTimeout(function(){
                            fixManageFieldsPageHeight();
                            body.unmask();
                        }, confirmationTimeOut);

                    } else {
                        body.unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function(){
                    body.unmask();
                    Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                }
            });
        }
    });
};
