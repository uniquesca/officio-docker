var mappedFieldsStore = new Ext.data.Store({
    // load using HTTP
    url: baseUrl + '/forms-maps/index',
    autoLoad: true,
    remoteSort: true,

    sortInfo: {
        field: 'from_family_member_name',
        direction: 'ASC'
    },

    baseParams: {
        start: 0,
        limit: 20
    },

    // the return will be Json, so lets set up a reader
    reader: new Ext.data.JsonReader(
        {
            id: 'map_id',
            root:'rows',
            totalProperty:'totalCount'
        }, [
               {name: 'map_id', type: 'int'},
               {name: 'bidirectional', type: 'bool'},
               {name: 'from_family_member_id'},
               {name: 'from_family_member_name'},
               {name: 'from_profile_field_id'},
               {name: 'from_field_name'},
               
               {name: 'to_family_member_id'},
               {name: 'to_family_member_name'},
               {name: 'to_profile_field_id'},
               {name: 'to_field_name'},

               {name: 'profile_field_member'},
               {name: 'profile_field_name'},
               {name: 'profile_mapping_type'}
            ]
    )
});


var chkSm = new Ext.grid.CheckboxSelectionModel();

var mappingPagingBar = new Ext.PagingToolbar({
    pageSize: 20,
    store: mappedFieldsStore,
    displayInfo: true,
    displayMsg: 'Displaying mapped fields {0} - {1} of {2}',
    emptyMsg: "No mapped fields to display"
});

var customDirection = function(val, p, record) {
    var img, text;
    if(record.data.bidirectional) {
        img = 'arrow_ew.png';
        text = 'Bidirectional';
    } else {
        img = 'arrow_right.png';
        text = 'One way';
    }

    return '<img src="' + topBaseUrl + '/images/icons/' + img + '" style="vertical-align:top;" /> ' + text;
};

var _showNewMapDialog = function() {
    var allFamilyMembersStore = new Ext.data.Store({
        url: baseUrl + '/forms-maps/family-members-list',
        autoLoad: true,
        reader: new Ext.data.JsonReader(
            {
                id: 'id',
                root:'rows',
                totalProperty:'totalCount'
            }, [
               {name: 'id'},
               {name: 'value'}
            ]
        )
    });

    var allFieldsStore = new Ext.data.Store({
        url: baseUrl + '/forms-maps/fields-list',
        autoLoad: true,
        reader: new Ext.data.JsonReader(
            {
                id: 'id',
                root:'rows',
                totalProperty:'totalCount'
            }, [
               {name: 'id'},
               {name: 'value'}
            ]
        )
    });
    
    var toProfileSyncFieldsStore = new Ext.data.Store({
        url: baseUrl + '/forms-maps/profile-fields-list',
        autoLoad: false,
        reader: new Ext.data.JsonReader(
            {
                id: 'id',
                root:'rows',
                totalProperty:'totalCount'
            }, [
               {name: 'id'},
               {name: 'value'},
               {name: 'group'}
            ]
        ),
        
        listeners: {
            load: function() {
                Ext.getCmp('maps-to-profile-field').enable();
            }
        }
    });

    var toProfileMappingTypeStore = new Ext.data.Store({
        url: baseUrl + '/forms-maps/profile-mapping-types',
        autoLoad: true,
        reader: new Ext.data.JsonReader(
            {
                id: 'id',
                root:'rows',
                totalProperty:'totalCount'
            }, [
               {name: 'id'},
               {name: 'value'}
            ]
        ),

        listeners: {
               load: function() {
                   Ext.getCmp('maps-to-profile-type').enable();
               }
           }
    });

    var wndNewMap = new Ext.Window({
        id: 'wnd-new-map',
        title: '<i class="las la-plus"></i>' + _('New Map'),
        closeAction: 'close',
        width: 750,
        autoHeight: true,
        plain:false,
        modal: true,
        resizable: false,
        
        border: true,
        items:
            new Ext.FormPanel({
                id: 'maps-form',
                
                bodyStyle:'padding:5px;',
                defaults: {
                    // applied to each contained panel
                    bodyStyle:'vertical-align: top; padding:5px;',
                    labelAlign: 'top'
                },

                layout:'table',
                layoutConfig: {
                    columns: 5
                },
                
                items: [
                    {
                        xtype:'fieldset',
                        title: 'Mapping From',
                        autoHeight: true,
                        items: [
                            {
                                id: 'maps-from-member',
                                fieldLabel: 'Family Member',
                                xtype: 'combo',
                                store: allFamilyMembersStore,
                                displayField:'value',
                                valueField:'id',
                                width: 150,
                                listWidth: 150,
                                mode: 'local',
                                typeAhead: false,
                                editable: false,
                                triggerAction: 'all',
                                emptyText:'Please select...',
                                allowBlank: false,
                                selectOnFocus:true
                            }, {
                                id: 'maps-from-field',
                                fieldLabel: 'PDF Form Field',
                                xtype: 'combo',
                                store: allFieldsStore,
                                displayField:'value',
                                valueField:'id',
                                width: 150,
                                listWidth: 350,
                                mode: 'local',
                                typeAhead: false,
                                editable: false,
                                triggerAction: 'all',
                                emptyText:'Please select...',
                                allowBlank: false,
                                selectOnFocus:true
                            }
                        ]
                    },
                    {
                        xtype:'label',
                        style: 'padding: 5px 15px;',
                        text: 'Map With'
                    },
                    {
                        xtype:'fieldset',
                        title: 'Mapping To',
                        autoHeight: true,
                        items: [
                            {
                                id: 'maps-to-member',
                                fieldLabel: 'Family Member',
                                xtype: 'combo',
                                store: allFamilyMembersStore,
                                displayField:'value',
                                valueField:'id',
                                width: 150,
                                listWidth: 150,
                                mode: 'local',
                                typeAhead: false,
                                editable: false,
                                triggerAction: 'all',
                                emptyText:'Please select...',
                                allowBlank: false,
                                selectOnFocus:true
                            }, {
                                id: 'maps-to-field',
                                fieldLabel: 'PDF Form Field',
                                xtype: 'combo',
                                store: allFieldsStore,
                                displayField:'value',
                                valueField:'id',
                                width: 150,
                                listWidth: 350,
                                mode: 'local',
                                typeAhead: false,
                                editable: false,
                                triggerAction: 'all',
                                emptyText:'Please select...',
                                allowBlank: false,
                                selectOnFocus:true
                            }
                        ]
                    }, {
                        xtype: 'label',
                        style: 'padding: 5px 15px;',
                        text: 'And'
                    }, {
                        xtype: 'fieldset',
                        title: 'Update Profile Field',
                        autoHeight: true,
                        items: [
                            {
                                id:            'maps-to-profile-member',
                                fieldLabel:    'Family Member',
                                xtype:         'combo',
                                store:         allFamilyMembersStore,
                                displayField:  'value',
                                valueField:    'id',
                                width:         150,
                                listWidth:     150,
                                mode:          'local',
                                typeAhead:     true,
                                editable:      true,
                                triggerAction: 'all',
                                emptyText:     'Please select...',
                                allowBlank:    true,
                                selectOnFocus: true,
                                listeners:     {
                                    beforeselect: function (n, selRecord) {
                                        if (selRecord.data.value !== '') {
                                            Ext.getCmp('maps-to-profile-field').disable();
                                            Ext.getCmp('maps-to-profile-field').setValue();
                                            Ext.getCmp('maps-to-profile-type').setValue();

                                            Ext.apply(toProfileSyncFieldsStore.baseParams, {family_member_id: Ext.encode(selRecord.data.id)});
                                            toProfileSyncFieldsStore.load();
                                        }
                                    },

                                    blur: function (combo) {
                                        if (combo.getValue() == '') {
                                            Ext.getCmp('maps-to-profile-field').disable();
                                            Ext.getCmp('maps-to-profile-field').setValue();
                                            Ext.getCmp('maps-to-profile-type').setValue();
                                        }
                                    }
                                }
                            }, {
                                id:            'maps-to-profile-field',
                                fieldLabel:    'Profile Field',
                                xtype:         'combo',
                                store:         toProfileSyncFieldsStore,
                                tpl: new Ext.XTemplate(
                                    '<tpl for=".">',
                                        '<tpl if="this.group != values.group">',
                                            '<tpl exec="this.group = values.group"></tpl>',
                                            '<h1 style="padding: 2px 5px;">{group}</h1>',
                                        '</tpl>',
                                        '<div class="x-combo-list-item" style="padding-left: 20px;">{value}</div>',
                                    '</tpl>'
                                ),
                                displayField:  'value',
                                valueField:    'id',
                                disabled:      true,
                                width:         150,
                                listWidth:     350,
                                mode:          'local',
                                typeAhead:     true,
                                editable:      true,
                                triggerAction: 'all',
                                emptyText:     'Please select...',
                                allowBlank:    true,
                                selectOnFocus: true
                            }, {
                                id:            'maps-to-profile-type',
                                fieldLabel:    'Mapping type',
                                xtype:         'combo',
                                store:         toProfileMappingTypeStore,
                                displayField:  'value',
                                valueField:    'id',
                                disabled:      true,
                                width:         150,
                                listWidth:     350,
                                mode:          'local',
                                typeAhead:     true,
                                editable:      true,
                                triggerAction: 'all',
                                emptyText:     'Please select...',
                                allowBlank:    true,
                                selectOnFocus: true
                            }
                        ]
                    },
                    {
                        xtype:'fieldset',
                        style: 'padding: 0; margin: 0; border: none;',
                        autoHeight: true,
                        labelAlign: 'left',
                        colspan: 3,
                        items: [
                            {
                                id: 'maps-two-directional',
                                xtype: 'checkbox',
                                checked: true,
                                hideLabel: true,
                                boxLabel: 'Two Directional'
                            }
                        ]
                    }
                ]
            }),
        
        buttons: [{
            text: 'Cancel',
            animEl: 'mapping-btn-add',
            handler: function(){
                wndNewMap.close();
            }
        },{
            id: 'form-submit-btn',
            animEl: 'mapping-btn-add',
            text: 'Create new map',
            cls: 'orange-btn',
            handler: function(){
                if(Ext.getCmp('maps-form').getForm().isValid()) {
                    _addEditMap('add');
                }
            }
        }]
    });
    
    wndNewMap.show();
};
    

var autoExpandMappingColumnId = 'mapping-grid-column-from-fm';
var mappingGrid = new Ext.grid.GridPanel({
        id: 'mapping-main-grid',
        autoWidth: true,
        height: getSuperadminPanelHeight() - 153,

        store: mappedFieldsStore,
        
        cm: new Ext.grid.ColumnModel([
            chkSm,
            {id: autoExpandMappingColumnId, width: 100, header: "Family Member", sortable: true, dataIndex: 'from_family_member_name'},
            {header: "Field Name", width: 150, sortable: true, dataIndex: 'from_field_name'},
            {header: "Updates", width: 80, sortable: false, renderer: customDirection, dataIndex: 'bidirectional'},
            {header: "Family Member", width: 100, sortable: true, dataIndex: 'to_family_member_name'},
            {header: "Field Name", width: 150, sortable: true, dataIndex: 'to_field_name'},
            {header: "Profile Member", width: 100, sortable: true, dataIndex: 'profile_field_member', hidden: true},
            {header: "Profile Field", width: 100, sortable: true, dataIndex: 'profile_field_name', hidden: true},
            {header: "Profile Mapping Type", width: 100, sortable: true, dataIndex: 'profile_mapping_type', hidden: true}
        ]),
        sm: chkSm,

        viewConfig: {
            forceFit: true,
            emptyText: 'No mapped fields found.',
            deferEmptyText: 'No mapped fields found.'
        },
        
        autoExpandColumn: autoExpandMappingColumnId,
        
        tbar:[{
                id: 'mapping-btn-add',
                text: ' <i class="las la-plus"></i>' + _('New Map'),
                tooltip:'Create new map between fields ',
                handler: function(){
                    _showNewMapDialog();
                }
                
            }, {
                id: 'mapping-btn-delete',
                text: '<i class="las la-trash"></i>' + _('Delete Map'),
                tooltip:'Remove selected map',
                handler: function(){
                    var arrSelected = _getSelectedMapIds();
                    if (arrSelected.length > 0) {
                        // There are selected forms
                        if( arrSelected.length == 1) {
                            title = 'Delete selected map?';
                            msg = 'Selected map will be deleted. Are you sure to delete it?';
                        } else {
                            title = 'Delete selected maps?';
                            msg = 'Selected maps will be deleted. Are you sure to delete them?';
                        }
                        
                        Ext.Msg.show({
                           title: title,
                           msg: msg,
                           buttons: Ext.Msg.YESNO,
                           fn: function(btn){
                                if (btn == 'yes'){
                                    _deleteMap();
                                }
                           },
                           animEl: 'mapping-btn-delete'
                        });
                    } else {
                        Ext.simpleConfirmation.msg('Info', 'Please select at least one map');
                    }
                }
                
            }
        ],

        
        // paging bar on the bottom
        bbar: mappingPagingBar,
        
        bodyStyle: 'padding: 0px; background-color: #fff;'
        
});


var _getSelectedMapIds = function() {
    var arrSelectedMapIds = [];
    
    if (mappingGrid) {
        var s = mappingGrid.getSelectionModel().getSelections();
        if(s.length > 0){
            for(var i=0; i<s.length; i++) {
                arrSelectedMapIds[arrSelectedMapIds.length] = s[i].data.map_id;
            }
        }
    }
    
    return arrSelectedMapIds;
};

var _addEditMap = function(action) {
    var requestUrl = baseUrl + "/forms-maps/manage";
    var confirmationTimeout = 750;
    
    switch (action) {
        case 'add':
            map_id = 0;
            loadingMsg = 'Saving...';
            failureMsg = 'Map was not created. Please try again later.';
            break;
            
        default:
            // Incorrect action
            return;
    }
    
    
    
    var body = Ext.getBody();
    body.mask(loadingMsg);
    
    Ext.Ajax.request({
            url: requestUrl,
            params:
            {
                map_id:      Ext.encode(map_id),
                
                from_member: Ext.encode(Ext.getCmp('maps-from-member').getValue()),
                from_field:  Ext.encode(Ext.getCmp('maps-from-field').getValue()),
                
                to_member:   Ext.encode(Ext.getCmp('maps-to-member').getValue()),
                to_field:    Ext.encode(Ext.getCmp('maps-to-field').getValue()),
                
                to_profile_member: Ext.encode(Ext.getCmp('maps-to-profile-member').getValue()),
                to_profile_field:  Ext.encode(Ext.getCmp('maps-to-profile-field').getValue()),
                to_profile_type:  Ext.encode(Ext.getCmp('maps-to-profile-type').getValue()),

                two_directional:  Ext.encode(Ext.getCmp('maps-two-directional').getValue())
                
            },
            success:function(f)
            {
                var resultData = Ext.decode(f.responseText);
                
                if(resultData.success) {
                    Ext.getCmp('wnd-new-map').close();
                
                    // Refresh maps list
                    mappingGrid.store.reload();
                    
                    // Show confirmation
                    body.mask('Done !');
                    
                    // Hide a confirmation for a second
                    setTimeout(function(){
                        body.unmask();
                    }, confirmationTimeout);
                } else {
                    // Show error message
                    body.unmask();
                    Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' +resultData.message + '</span>');
                }
            },
            
            failure: function(){
                // Some issues with network?
                Ext.Msg.alert('Status', failureMsg);
                body.unmask();
            }
    });
};

var _deleteMap = function() {

    var arrSelectedMapIds = _getSelectedMapIds();
    if (arrSelectedMapIds.length === 0) {
        // Do nothing because no any maps are selected
        return;
    }

    var requestUrl = baseUrl + "/forms-maps/delete/";
    var confirmationTimeout = 750;
    
    var strForm = (arrSelectedMapIds.length > 1) ? 'Maps' : 'Map';
    var loadingMsg = 'Deleting...';
    var failureMsg = 'Selected ' + strForm + ' cannot be deleted. Please try again later.';
    

    // Send ajax request to make some action with selected forms
    mappingGrid.body.mask(loadingMsg);
    
    Ext.Ajax.request({
            url: requestUrl,
            params:
            {
                arr_map_id: Ext.encode(arrSelectedMapIds)
            },
            success:function(f)
            {
                var resultData = Ext.decode(f.responseText);
                
                if(resultData.success) {
                    // Refresh forms list
                    mappingGrid.store.reload();
                    
                    // Show confirmation
                    var msg = 'Done !';
                    mappingGrid.body.mask(msg);
                    
                    // Hide a confirmation for a second
                    setTimeout(function(){
                        mappingGrid.body.unmask();
                    }, confirmationTimeout);
                } else {
                    // Show error message
                    mappingGrid.body.unmask();
                    Ext.Msg.alert('Error', 'An error occurred:\n<br/>' + '<span style="color: red;">' +resultData.message + '</span>');
                }
            },
            
            failure: function(){
                // Some issues with network?
                Ext.Msg.alert('Status', failureMsg);
                mappingGrid.body.unmask();
            }
    });
};