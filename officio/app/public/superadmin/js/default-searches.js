function showSearchForm(panelType) {
    var title = empty(searchId) ? _('New Advanced Search') : searchName + _(' - search');
    var panel = new ApplicantsAdvancedSearchPanel({
        title:                     '<div class="tab-title">' + title + '</div> ',
        panelType:                 panelType,
        searchId:                  searchId,
        savedSearchName:           searchName,
        autoWidth:                 true,
        booHideBackButton:         true,
        booShowBackSuperadminButton: true,
        booHideSearchFavoriteButton: true,
        booAlwaysShowName:         true,
        booAlwaysShowGrid:         true,
        booHideSearchButton:       true,
        booForceShowAnalytics:     true,
        booHideMassEmailing:       true,
        booHideGridToolbar:        true,
        booAlwaysHidePanelToolbar: true,
        height:                    565,

        oOpenTabParams: {
            tabType:                   'advanced_search',
            searchId:                   searchId,
            savedSearchName:            searchName,
            booShowAnalyticsTab:        false
        }
    }, {
        panelType: panelType,

        fixParentPanelHeight: function () {
            updateSuperadminIFrameHeight('#admin-default-searches');
        },

        getGroupedFields: function (type, notAllowedFieldTypes, booSkipRepeatableGroups) {
            var arrGroupedFields = [];
            notAllowedFieldTypes = empty(notAllowedFieldTypes) ? [] : notAllowedFieldTypes;

            if (type == 'all' || type == 'profile') {
                var arrSearchTypes = [];
                if (this.panelType === 'contacts') {
                    arrSearchTypes.push('contact');
                } else {
                    arrSearchTypes = ['individual'];
                    if (arrApplicantsSettings.access.employers_module_enabled) {
                        arrSearchTypes.unshift('employer');
                    }
                }

                arrSearchTypes.forEach(function(currentType) {
                    Ext.each(arrApplicantsSettings.groups_and_fields[currentType][0]['fields'], function(group) {
                        if (booSkipRepeatableGroups && group.group_repeatable === 'Y') {
                            return;
                        }

                        Ext.each(group.fields, function (field) {
                            if (field.field_encrypted == 'N' && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                                field.field_template_id = 0;
                                field.field_template_name = '';
                                field.field_group_id = group.group_id;
                                field.field_group_name = group.group_title;
                                field.field_client_type = currentType;
                                arrGroupedFields.push(field);
                            }
                        });
                    });
                });
            }

            if (this.panelType !== 'contacts' && (type == 'all' || type == 'case')) {
                var arrGroupedCaseFields = [];

                var arrFieldIds = [];
                for (var templateId in arrApplicantsSettings.case_group_templates) {
                    if (arrApplicantsSettings.case_group_templates.hasOwnProperty(templateId)) {
                        Ext.each(arrApplicantsSettings.case_group_templates[templateId], function(group){
                            Ext.each(group.fields, function(field){
                                if (field.field_encrypted == 'N' && !arrFieldIds.has(field.field_unique_id) && notAllowedFieldTypes.indexOf(field.field_type) == -1) {
                                    arrFieldIds.push(field.field_unique_id);
                                    field.field_client_type = 'case';
                                    field.field_group_name = empty(field.field_group_name) ? 'Case Details' : field.field_group_name;
                                    arrGroupedCaseFields.push(field);
                                }
                            });
                        });
                    }
                }
                arrGroupedCaseFields.sort(this.sortFieldsByName);

                arrGroupedFields.push.apply(arrGroupedFields, arrGroupedCaseFields);

                // Add special fields to the top of the list
                if (notAllowedFieldTypes.indexOf('date') == -1) {
                    arrGroupedFields.unshift({
                        field_id:          'created_on',
                        field_unique_id:   'created_on',
                        field_name:        'Created On',
                        field_type:        'date',
                        field_client_type: 'case'
                    });
                }

                if (notAllowedFieldTypes.indexOf('special') == -1) {
                    arrGroupedFields.unshift({
                        field_id:          'ob_total',
                        field_unique_id:   'ob_total',
                        field_name:        'Cases who owe money',
                        field_type:        'special',
                        field_client_type: 'case'
                    });

                    arrGroupedFields.unshift({
                        field_id:          'ta_total',
                        field_unique_id:   'ta_total',
                        field_name:        'Available Total',
                        field_type:        'special',
                        field_client_type: 'case'
                    });
                }
            }

            return arrGroupedFields;
        },

        generateSearchTabTitle: function (searchId, savedSearchName) {
            var title = savedSearchName + _(' - search');
            return '<div class="tab-title">' + title + '</div>';
        },

        fireEvent: function () {
            // do nothing
        }
    });

    panel.render('advanced_search_panel');
}

function showSearches(panelType)
{
    function addSearch()
    {
        Ext.getBody().mask('Loading...');
        location.href = baseUrl + "/default-searches/get-view?search_type=" + searchTypeCombo.getValue();
    }

    function editSearch()
    {
        var node = grid.getSelectionModel().getSelected();
        if(node) {
            Ext.getBody().mask('Loading...');
            location.href = baseUrl + "/default-searches/get-view?search_id=" + node.data.search_id;
        }
    }

    function deleteSearch()
    {
        var node = grid.getSelectionModel().getSelected();
        if(node)
        {
            Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete this Search?', function(btn)
            {
                if(btn == 'yes')
                {
                    Ext.getBody().mask('Deleteing...');

                    Ext.Ajax.request({
                        url: baseUrl + '/default-searches/delete',
                        params: {
                            search_id: node.data.search_id
                        },
                        success: function()
                        {
                            grid.store.reload();
                            Ext.simpleConfirmation.msg('Info', 'Done');
                            Ext.getBody().unmask();
                        },
                        failure: function()
                        {
                            Ext.Msg.alert('Status', 'This Search can\'t be deleted. Please try again later.');
                            Ext.getBody().unmask();
                        }
                    });
                }
            });
        }
    }

    var searchTypeCombo = new Ext.form.ComboBox({
        fieldLabel: 'Search Type',
        value: panelType,

        store: new Ext.data.SimpleStore({
            fields: ['searchTypeId', 'searchTypeName'],
            data: [
                ['clients', 'Clients'], ['contacts', 'Contacts']
            ]
        }),

        mode:           'local',
        width:          200,
        valueField:     'searchTypeId',
        displayField:   'searchTypeName',
        triggerAction:  'all',
        lazyRender:     true,
        editable: false,

        listeners: {
            'select': function () {
                store.load();
            }
        }
    });

    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                id: 'title',
                header: "Search title",
                dataIndex: 'title',
                sortable: true,
                width: 300
            }
        ],
        defaultSortable: true
    });

    var store = new Ext.data.Store({
        url:        baseUrl + '/default-searches/get-searches',
        autoLoad:   true,
        remoteSort: false,
        reader:     new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount'
        }, Ext.data.Record.create([{name: 'search_id'}, {name: 'title'}])),

        listeners: {
            'beforeload': function (store, options) {
                options.params = options.params || {};

                var params = {
                    search_type: searchTypeCombo.getValue()
                };

                Ext.apply(options.params, params);
            },

            'load': function () {
                updateSuperadminIFrameHeight('#admin-default-searches');
            }
        }
    });

    var grid = new Ext.grid.GridPanel({
        store: store,
        cm: cm,
        sm: new Ext.grid.RowSelectionModel({singleSelect: true}),
        autoHeight: true,
        split: true,
        stripeRows: true,
        autoExpandColumn: 'title',
        loadMask: true,
        autoScroll: true,
        cls: 'extjs-grid',
        viewConfig: { emptyText: 'No searches found.' },
        listeners: {
            afterrender: function () {
                updateSuperadminIFrameHeight('#admin-default-searches');
            }
        },

        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('Add'),
                handler: function () {
                    addSearch();
                }
            }, {
                text:    '<i class="las la-edit"></i>' + _('Edit'),
                handler: function () {
                    editSearch();
                }
            }, {
                text:    '<i class="las la-trash"></i>' + _('Delete'),
                handler: function () {
                    deleteSearch();
                }
            }
        ]
    });

    new Ext.Panel({
        renderTo: 'admin-default-searches',
        items: [
            {
                xtype: 'container',
                layout: 'form',
                style: 'padding: 10px',
                items: searchTypeCombo
            },

            grid
        ]
    });
}