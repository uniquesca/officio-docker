var confirmationTimeOut = 750;

var initToolbarActions = function() {
    $('.add-contact-block-link').click(function(){
        addBlock(true, 'Contacts', false, 3);
        return false;
    });

    $('.add-group-link').click(function(){
        showEditGroupDialog(0, 0, 'New group', 3, false);
        return false;
    });
};

var initBlockActions = function() {
    $(".block_column").each(function() {
        if (!$(this).find('.block_actions').length) {
            $(this).prepend('<div class="block_actions">' +
                                '<div class="ui-icon block_add_group" title="Add Group"></div>' +
                                '<div class="ui-icon block_edit" title="Edit Block Details"></div>' +
                                '<div class="ui-icon block_move_up" title="Move Block Up"></div>' +
                                '<div class="ui-icon block_move_down" title="Move Block Down"></div>' +
                                '<div class="ui-icon block_delete" title="Delete Block"></div>' +
                             '</div>');

            var booIsContactBlock = $(this).hasClass('contact_block');

            // Show/hide block actions
            $(this).hover(
                function () {
                    $(this).find('.block_actions').show();

                    // Disallow add groups for non-contact blocks
                    if (!booIsContactBlock) {
                        $(this).find('.block_add_group').hide();
                    }
                },
                function () {
                    $(this).find('.block_actions').hide();
                }
            );

            // Unbind previous events
            $(this).find(".block_actions div").off('click');

            $(this).find(".block_actions .block_add_group").click(function() {
                var blockId = $(this).parents(".block_column:first")[0].id;
                showEditGroupDialog(blockId.replace('block_column_', ''), 0, 'New group', 3, false);
            });

            $(this).find(".block_actions .block_edit").click(function() {
                var block   = $(this).parents(".block_column:first")[0];
                var blockId = block.id.replace('block_column_', '');
                var isBlockContact = $(block).hasClass('contact_block');
                var isBlockRepeatable = $(block).hasClass('repeatable_block');
                showEditBlockDialog(blockId, isBlockContact, isBlockRepeatable);
            });

            $(this).find(".block_actions .block_move_up").click(function() {
                var block = $(this).parents(".block_column:first");
                block.insertBefore(block.prev());
            });

            $(this).find(".block_actions .block_move_down").click(function() {
                var block = $(this).parents(".block_column:first");
                block.insertAfter(block.next());
            });

            $(this).find(".block_actions .block_delete").click(function() {
                var blockId = $(this).parents(".block_column:first")[0].id;

                Ext.MessageBox.buttonText.yes = "Yes, delete this block";
                Ext.MessageBox.buttonText.no = "No";
                Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this block?', function (btn) {
                    if (btn == 'yes') {
                        removeBlock(blockId.replace('block_column_', ''));
                    }
                });

            });
        }
    });
};

var initGroupActions = function() {
    $(".portlet-header").each(function() {
        if (!$(this).find('.field_group_minus').length) {
            $(this).prepend('<span class="ui-icon field_group_add" title="Add Field to this Group"></span>')
                   .prepend('<span class="ui-icon field_group_edit" title="Edit Group Info"></span>')
                   .prepend('<span class="ui-icon field_group_delete" title="Delete Group"></span>')
                   .prepend('<span class="ui-icon field_group_minus" title="Collapse/Expand Group Fields"></span>');

            // Show/hide groups editing options
            $(this).hover(
                function () {
                    $(this).find('.field_group_add').show();
                    $(this).find('.field_group_edit').show();
                    $(this).find('.field_group_delete').show();
                },
                function () {
                    $(this).find('.field_group_add').hide();
                    $(this).find('.field_group_edit').hide();
                    $(this).find('.field_group_delete').hide();
                }
            );

            // Expand/collapse group click
            $(this).find('.field_group_minus').off('click').click(function() {
                $(this).toggleClass("field_group_plus");
                $(this).parents(".portlet:first").find(".portlet-content").toggle();
                fixManageFieldsPageHeight();
            });

            // Click on delete group
            $(this).find('.field_group_delete').off('click').click(function() {
                var groupId = $(this).parents(".portlet:first")[0].id;
                var groupName = $('#' + groupId).find('.group_name:first').html();
                deleteGroup(groupId.replace('fields_group_', ''), groupName);
            });

            // Click on edit group
            $(this).find('.field_group_edit').off('click').click(function() {
                var groupId = $(this).parents(".portlet:first")[0].id;
                var groupNameSpan = $('#' + groupId).find('.group_name:first');
                var groupName = groupNameSpan.html();

                var groupColsCount = 3;
                var classList = groupNameSpan.attr('class').split(/\s+/);
                $.each( classList, function(index, item){
                    var match = item.match(/^group_columns_count_([\d]{1,})$/);
                    if (match != null) {
                        groupColsCount = parseInt(match[1]);
                    }
                });


                var blockId = $(this).parents(".block_column:first")[0].id.replace('block_column_', '');
                showEditGroupDialog(blockId, groupId.replace('fields_group_', ''), groupName, groupColsCount, groupNameSpan.hasClass('group_collapsed'));
            });

            // Click on add group
            $(this).find('.field_group_add').off('click').click(function() {
                var groupId = $(this).parents(".portlet:first")[0].id.replace('fields_group_', '');

                var block = $(this).parents(".block_column:first")[0];
                var isBlockContact = $(block).hasClass('contact_block');
                var blockId = block.id.replace('block_column_', '');

                if (isBlockContact) {
                    var win = new ContactFieldsDialog({
                        groupId: groupId,
                        blockId: blockId
                    });
                    win.show();
                    win.center();
                } else {
                    loadFieldInfo(groupId, 0);
                }
            });

        }
    });
};

var makeFieldsSortable = function() {
    $(".fields_column").sortable({
        connectWith: ['.fields_column'],
        items: '.field_container',
        handle: '.group_field_name',
        revert: true,
        cursor:"move"
    });
};

var initFieldsActions = function() {
    $('.field_container').each(function() {
        if (!$(this).find('.edit_field_action').length) {
            if ($(this).hasClass('field_container_edit')) {
                $(this).append('<span class="ui-icon edit_field_action" title="Edit Field Info">&nbsp;</span>')
                       .append('<span class="ui-icon delete_field_action" title="Delete Field">&nbsp;</span>');
            } else if($(this).hasClass('field_blocked')) {
                $(this).append('<span class="ui-icon edit_field_action" title="Edit Field Info">&nbsp;</span>');
            } else if($(this).hasClass('field_can_be_deleted')) {
                $(this).append('<span class="ui-icon delete_field_action" title="Delete Field">&nbsp;</span>');
            }

            // Show/hide fields editing options
            $(this).hover(
                function () {
                    $(this).find('.edit_field_action').show();
                    $(this).find('.delete_field_action').show();
                },
                function () {
                    $(this).find('.edit_field_action').hide();
                    $(this).find('.delete_field_action').hide();
                }
            );

            // Click on edit field
            $(this).find('.edit_field_action').off('click').click(function() {
                var fieldId = $(this).parents(".field_container:first")[0].id;
                var groupId = $(this).parents(".portlet:first")[0].id;
                loadFieldInfo(groupId, fieldId);
            });

            // Click on delete field
            $(this).find('.delete_field_action').off('click').click(function() {
                var fieldId = $(this).parents(".field_container:first")[0].id;
                var fieldName = $('#' + fieldId).find('.group_field_name:first').html();
                var match = fieldId.match(/^field_([\d]{1,})_([\d]{1,})$/i);

                var block = $(this).parents(".block_column:first")[0];
                var blockId = block ? block.id.replace('block_column_', '') : 0;

                var groupId = $(this).parents(".portlet:first")[0].id.replace('fields_group_', '');
                deleteField(blockId, groupId, match[2], fieldName);
            });
        }
    });
};

/**
 * Send order info to server and show result
 *
 * @param blocksOrder
 * @param fieldsOrder
 */
var saveGroupsAndFieldsOrder = function(blocksOrder, fieldsOrder) {
    Ext.MessageBox.show({
       msg: 'Saving...',
       progressText: 'Saving...',
       width:300,
       wait:true,
       waitConfig: {interval:200},
       icon:'ext-mb-download',
       animEl: 'button_submit'
   });

    Ext.Ajax.request({
        url: submissionUrl + '/save-order',
        params: {
            company_id:        companyId,
            applicant_type_id: applicantTypeId,
            member_type:       Ext.encode(memberType),
            blocks_order:      Ext.encode(blocksOrder),
            fields_order:      Ext.encode(fieldsOrder)
        },

        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                // Show confirmation message
                Ext.MessageBox.hide();
                Ext.simpleConfirmation.success('Done', resultData.message);
            } else {
                Ext.MessageBox.hide();
                Ext.simpleConfirmation.error('<span style="color: red;">' + resultData.message + '</span>');
            }
        },

        failure: function () {
            Ext.MessageBox.hide();
            Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
        }
    });
};

Ext.onReady(function() {
    Ext.QuickTips.init();
    Ext.form.Field.prototype.msgTarget = 'side';

    initToolbarActions();

    initBlockActions();

    initGroupActions();

    makeFieldsSortable();
    initFieldsActions();

    // Collect and save order info for groups and fields
    $('#button_submit').click(function(){
        var blocksOrder = getBlocksOrder();
        var fieldsOrder = getFieldsOrder();
        saveGroupsAndFieldsOrder(blocksOrder, fieldsOrder);
    });
});
var fixManageFieldsPageHeight = function(timeout) {
    if (!timeout) {
        timeout = 0;
    }
    setTimeout(function(){
        updateSuperadminIFrameHeight('#manage-fields-content');
    }, timeout);
};