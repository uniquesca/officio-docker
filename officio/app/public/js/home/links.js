function showLinks()
{
    $('#linksForm').html('<img src="' + imagesUrl + '/loading.gif" alt="loading" />').load(baseUrl + "/links/index/get-links-list");
}

function qLinks(params)
{
    if(params.action !== 'delete') //add or edit action
    {
        var linkName = new Ext.form.TextField({
            fieldLabel: 'Label',
            width: 300
        });
        
        var linkUrl = new Ext.form.TextField({
            fieldLabel: 'URL',
            value: 'https://',
            width: 300
        });

        var sharedBookmark = new Ext.form.Checkbox({
            hideLabel:  true,
            boxLabel:   'Shared Bookmark',
            listeners: {
                'check': function (checkbox, booChecked) {
                    if (!booChecked) {
                        shareToRoleCombo.setValue('');
                        shareToRoleCombo.clearInvalid();
                    }

                    shareToRoleCombo.setDisabled(!booChecked);
                }
            }
        });

        var shareToRoleCombo = new Ext.ux.form.LovCombo({
            width:      200,
            hideLabel:  true,
            disabled:   true,
            allowEmpty: false,

            store: {
                reader: new Ext.data.JsonReader({
                    id: 'role_id'
                }, [
                    {name: 'role_id'},
                    {name: 'role_name'}
                ])
            },

            displayField:  'role_name',
            valueField:    'role_id',
            typeAhead:     true,
            editable:      false,
            mode:          'local',
            triggerAction: 'all',
            selectOnFocus: true,
            emptyText:     'Please select roles...',
            useSelectAll:  false,
            allowBlank:    false
        });

        var sharingBlock = new Ext.Container({
            layout: 'column',
            hidden: true,

            items: [
                {
                    xtype: 'container',
                    layout: 'form',
                    width: 150,
                    style: 'padding-top: 10px',
                    items: sharedBookmark
                }, {
                    style: 'margin-right: 10px; margin-top: 15px',
                    html: String.format(
                        _('<img src="{0}/images/icons/help.png" width="16" height="16" alt="Help" ext:qtip="All admin users can view and edit the shared bookmarks. Non-admin users can only view the shared bookmarks assigned to their role." />'),
                        topBaseUrl
                    )
                }, {
                    xtype:  'container',
                    layout: 'form',
                    items:  shareToRoleCombo
                }
            ]
        });

        var pan = new Ext.FormPanel({
            layout:     'form',
            style:      'background-color:#fff; padding:5px;',
            labelWidth: 73,
            items:      [linkName, linkUrl, sharingBlock]
        });
        
        var saveBtn = new Ext.Button({
            text: params.action === 'add' ? 'Add' : 'Save',
            cls: 'orange-btn',

            handler: function()
            {
                var title = linkName.getValue();
                var url = linkUrl.getValue();
                                
                if (empty(title))    {
                    linkName.markInvalid();
                    return;
                }
                                
                if (HtmlTagSearch(title)){                                
                    linkName.markInvalid('Html tags are not allowed');
                    return;
                }
                                
                if (!url.match(/^(?:^((http|https|ftp)\:\/\/)?[a-zA-Z0-9\-.]+\.[a-zA-Z]{2,3}(:[a-zA-Z0-9]*)?\/?([a-zA-Z0-9\-._?,'\/\\+&amp;%$#=~])*$)$/i)) {
                    linkUrl.markInvalid();
                    return;
                }

                if (sharedBookmark.getValue() && !shareToRoleCombo.isValid()) {
                    return;
                }
                win.getEl().mask('Saving...');

                //save
                Ext.Ajax.request({
                    url:    baseUrl + "/links/index/" + params.action,
                    params: {
                        link_id:         params.link_id,
                        member_id:       params.member_id,
                        title:           Ext.encode(title),
                        url:             Ext.encode(url),
                        shared_to_roles: sharedBookmark.getValue() ? Ext.encode(shareToRoleCombo.getValue()) : ''
                    },
                    
                    success:function(result) {
                        var resultDecoded = Ext.decode(result.responseText);
                        if(!resultDecoded.success) {
                            Ext.simpleConfirmation.error('Saving Error');
                            win.getEl().unmask();
                        } else {
                            win.getEl().mask('Done!');
                            showLinks();
                                            
                            setTimeout(function(){
                                win.getEl().unmask();
                                win.close();
                            }, 750);
                        }
                    
                    },
                    
                    failure:function() {
                        Ext.simpleConfirmation.error('Saving Error');
                        win.getEl().unmask();
                    }
                });
            }
        });
        
        var closeBtn = new Ext.Button({
            text :'Cancel',
            handler: function() {
                win.close(); 
            }
        });

        var win = new Ext.Window({
            title: params.action === 'add' ? 'Add bookmark' : 'Edit bookmark',
            layout: 'form',
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,
            items: pan,
            buttons: [closeBtn, saveBtn]
        });
        
        win.show();

        //if edit action set default values
        if (params.action === 'add' || params.action === 'edit') {
            win.getEl().mask('Loading...');

            //get link detail info
            Ext.Ajax.request({
                url: baseUrl + '/links/index/get-link',

                params: {
                    link_id: params.link_id
                },

                success: function (result) {
                    var oResult = Ext.decode(result.responseText);
                    var link    = oResult.link;

                    linkName.setRawValue(link.title);
                    linkUrl.setRawValue(link.url);

                    sharingBlock.setVisible(oResult.showSharingBlock);
                    shareToRoleCombo.store.loadData(oResult.arrRoles);

                    if (!empty(link.shared_to_roles)) {
                        sharedBookmark.setValue(true);
                        shareToRoleCombo.setValue(link.shared_to_roles);
                    }

                    win.getEl().unmask();
                },

                failure: function() {
                    Ext.simpleConfirmation.error('Can\'t load bookmark information');
                    win.getEl().unmask();
                }
            });
        }
    }
    else //delete action
    {
        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this bookmark?', function(btn)
        {
            if (btn === 'yes')
            {
                Ext.getBody().mask('Deleting...');
                
                Ext.Ajax.request({
                    url: baseUrl + '/links/index/delete',    
                    params: 
                    {
                        link_id: params.link_id
                    },
                    success: function(result)
                    {
                        var resultDecoded = Ext.decode(result.responseText);
                        if(!resultDecoded.success) {
                            Ext.simpleConfirmation.error('This bookmark can\'t be deleted. Please try again later.');
                        } else {
                            showLinks();
                        }
                    
                        Ext.getBody().unmask();
                    },                                
                    failure: function()
                    {
                        Ext.simpleConfirmation.error('Status', 'This bookmark can\'t be deleted. Please try again later.');
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    }
}
