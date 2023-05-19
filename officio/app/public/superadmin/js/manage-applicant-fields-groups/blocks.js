var addBlock = function(booContactBlock, groupName, isGroupCollapsed, groupColumnsCount, win) {
    var body = win ? win.getEl() : Ext.getBody();

    body.mask('Processing...');
    Ext.Ajax.request({
        url: submissionUrl + '/add-block',
        params: {
            company_id:       companyId,
            member_type:      Ext.encode(memberType),
            block_type:       Ext.encode(booContactBlock ? 'contact' : 'general'),
            group_name:       Ext.encode(groupName),
            group_collapsed:  Ext.encode(isGroupCollapsed),
            group_cols_count: Ext.encode(groupColumnsCount)
        },

        success: function(result){
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                var newBlockHtml = String.format(
                    '<div class="block_column {0}" id="block_column_{1}"></div>',
                    booContactBlock ? 'contact_block' : 'general_block',
                    resultData.block_id
                );

                $("#blocks_column").append(newBlockHtml);
                initBlockActions();

                // Create a group inside of this block
                addGroup(resultData.block_id, resultData.group_id, groupName, isGroupCollapsed, groupColumnsCount);

                // Show a confirmation
                body.mask('Done !');
                setTimeout(function(){
                    body.unmask();
                    fixManageFieldsPageHeight(500);

                    if (win) {
                        win.close();
                    }
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
};

var removeBlock = function(blockId) {
    var body = Ext.getBody();

    body.mask('Processing...');
    Ext.Ajax.request({
        url: submissionUrl + '/remove-block',
        params: {
            company_id:     Ext.encode(companyId),
            member_type: Ext.encode(memberType),
            block_id:       Ext.encode(blockId)
        },

        success: function(result){
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                $("#block_column_" + blockId).remove();

                // Show a confirmation
                body.mask('Done !');
                setTimeout(function(){
                    body.unmask();
                    fixManageFieldsPageHeight();
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
};

var showEditBlockDialog = function(blockId, isBlockContact, isBlockRepeatable) {
    var wndTitle = 'Create new block';
    var btnSubmitTitle = 'Create block';

    if(empty(blockId)) {
        // New block
        blockId = 0;
    } else {
        wndTitle = 'Edit block details';
        btnSubmitTitle = 'Update block';
    }

    var frm = new Ext.FormPanel({
        labelWidth:  75,
        bodyStyle:   'padding:5px 5px 0',
        autoWidth:   true,
        autoHeight:  true,
        defaults:    {width: 230},

        items: [
            {
                id:        'block_repeatable',
                name:      'block_repeatable',
                xtype:     'checkbox',
                hideLabel: true,
                boxLabel:  'Is repeatable',
                checked:   isBlockRepeatable
            }
        ]
    });

    var win = new Ext.Window({
        title        : wndTitle,
        width       : 500,
        plain       : false,
        modal        : true,
        items       : frm,
        resizable : false,
        buttons: [{
            text     : btnSubmitTitle,
            handler  : function(){
                if(frm.getForm().isValid()){
                    var booIsBlockRepeatable = Ext.getCmp('block_repeatable').getValue();
                    var body = Ext.getBody();

                    body.mask('Processing...');
                    Ext.Ajax.request({
                        url: submissionUrl + '/edit-block',
                        params: {
                            company_id:       companyId,
                            member_type:   Ext.encode(memberType),
                            block_id:         Ext.encode(blockId),
                            block_type:       Ext.encode(isBlockContact ? 'contact' : 'general'),
                            block_repeatable: Ext.encode(booIsBlockRepeatable)
                        },

                        success: function(result){
                            var resultData = Ext.decode(result.responseText);
                            if (!resultData.error) {
                                var block = $('#block_column_' + blockId);
                                block.toggleClass('repeatable_block', booIsBlockRepeatable);

                                // Show a confirmation
                                body.mask('Done !');
                                setTimeout(function(){
                                    body.unmask();
                                }, confirmationTimeOut);

                                win.close();
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


var getBlocksOrder = function() {
    var arrBlocksOrder = [];
    var row = 0;
    $(".block_column").each(function(){
        var block_id = this.id.replace('block_column_', '');
        arrBlocksOrder.push({
            block_id: block_id,
            row: row++
        });
    });
    return arrBlocksOrder;
};