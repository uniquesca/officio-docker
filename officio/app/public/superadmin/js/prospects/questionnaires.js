var copyToClipboard = function () {
    var tabs = Ext.getCmp('prospects-questionnaires-tabs');
    var tab = tabs.getActiveTab();
    
    // Get active tab id, find link with same id and copy its href to clipboard
    var exploded = tab.id.split('q_tab_');
    var qnr_id = parseInt(exploded[1], 10);
    var url = $('#qnr_url_' + qnr_id).attr('href');

    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val(decodeURIComponent(url)).select();
    document.execCommand("copy");
    $temp.remove();

    Ext.simpleConfirmation.info('Url was successfully copied to clipboard');
};

var saveChangesOnProspectQuestionnaire = null;

var editField = function(field_id, q_id, field_type) {
    var booShowOnlyOne   = (q_id === 1);
    var currentHelpValue = '';

    // Labels can have different ids in relation to the field unique id
    var arrPossibleLabelIds = [
        'q_' + q_id + '_field_' + field_id + '_label',
        'q_' + q_id + '_employer_field_' + field_id + '_label',
        'q_' + q_id + '_spouse_employer_field_' + field_id + '_label'
    ];

    var currentLabel = '';
    for (var i = 0; i < arrPossibleLabelIds.length; i++) {
        var labelDom = Ext.getDom(arrPossibleLabelIds[i]);
        if (labelDom !== null) {
            currentLabel = labelDom.innerHTML;
            break;
        }
    }

    // Labels can have different ids in relation to the field unique id too
    var arrPossibleProspectLabelIds = [
        'prospect_' + 'q_' + q_id + '_field_' + field_id,
        'prospect_' + 'q_' + q_id + '_employer_field_' + field_id,
        'prospect_' + 'q_' + q_id + '_spouse_employer_field_' + field_id
    ];
    var currentProspectFieldLabel = '';
    for (i = 0; i < arrPossibleProspectLabelIds.length; i++) {
        var prospectLabelDom = Ext.getDom(arrPossibleLabelIds[i]);
        if (prospectLabelDom !== null) {
            currentProspectFieldLabel = prospectLabelDom.innerHTML;
            break;
        }
    }

    var booHideOptionsGrid = (field_type != 'combo' && field_type != 'combo_custom' && field_type != 'checkbox' && field_type != 'radio');

    // ORDER
    function moveOption(booUp, selectedRow) {
        var sm = options_grid.getSelectionModel();
        var rows = sm.getSelections();

        // Move option only if:
        // 1. It is not the first, if moving up
        // 2. It is not the last, if moving down
        var booMove = false;
        var index;
        if (options_store.getCount() > 0) {
            if (booUp && selectedRow > 0) {
                index = selectedRow - 1;
                booMove = true;
            } else if (!booUp && selectedRow < options_store.getCount() - 1) {
                index = selectedRow + 1;
                booMove = true;
            }
        }

        if (sm.hasSelection() && booMove) {
            for (var i = 0; i < rows.length; i++) {
                options_store.remove(options_store.getById(rows[i].id));
                options_store.insert(index, rows[i]);
            }

            // Update order for each of transaction
            for (i = 0; i < options_store.getCount(); i++) {
                var rec = options_store.getAt(i);

                if (rec.data.option_order != i) {
                    rec.beginEdit();
                    rec.set('option_order', i);

                    // Mark as dirty
                    var oldName = rec.data.option_name;
                    rec.set('option_name', oldName + ' ');
                    rec.set('option_name', oldName);
                    rec.endEdit();
                }
            }

            var movedRow = options_store.getAt(index);
            sm.selectRecords(movedRow);
        }

        if (index !== null) {
            sm.selectRow(index);
        }
    }

    var action = new Ext.ux.grid.RowActions({
        header:'Order',
        hidden: field_type != 'combo_custom',
        keepSelection: true,

        actions:[{
            iconCls:'move_option_up',
            tooltip:'Move Option Up'
        }, {
            iconCls:'move_option_down',
            tooltip:'Move Option Down'
        }],

        callbacks:{
            'move_option_up': function(grid, record, action, row) {
                sm.selectRow(row);
                moveOption(true, row);
            },

            'move_option_down': function(grid, record, action, row) {
                sm.selectRow(row);
                moveOption(false, row);
            }
        }
    });


    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header: 'Original wording',
                dataIndex: 'option_original_name',
                hidden: field_type == 'combo_custom',
                width: 200,
                renderer: function(value){ return '<span style="font-style: italic;">' + value + '</span>'; }
            }, {
               header: field_type == 'combo_custom' ? 'Option Label' : 'Your wording',
               dataIndex: 'option_name',
               width: 200,
               editor: new Ext.form.TextField({
                   allowBlank: false
               })
            }, {
                xtype: 'booleancolumn',
                header: 'Visible',
                dataIndex: 'option_visible',
                align: 'center',
                width: 60,
                trueText: 'Yes',
                falseText: 'No',
                editor: {
                    xtype: 'checkbox'
                }
            },
            action
        ],

        defaults: {
            sortable: false,
            menuDisabled: true
        }
    });

    var arrOptionFields = [
        {name: 'option_id',            type: 'int'},
        {name: 'option_original_name', type: 'string'},
        {name: 'option_name',          type: 'string'},
        {name: 'option_selected',      type: 'bool'},
        {name: 'option_order',         type: 'int'},
        {name: 'option_visible',       type: 'bool'}
    ];
    var Option = Ext.data.Record.create(arrOptionFields);

    var sm = new Ext.grid.RowSelectionModel({
        singleSelect: true,
        listeners: {
            // On selection change, set enabled state of the deleteOptionBtn
            // which was placed into the GridPanel using the ref config
            'selectionchange': function (sm) {
                if (options_grid['deleteOptionBtn']) {
                    if (sm.getCount()) {
                        options_grid['deleteOptionBtn'].enable();
                    } else {
                        options_grid['deleteOptionBtn'].disable();
                    }
                }
            }
        }
    });

    var options_store = new Ext.data.Store({
        url: baseUrl + '/manage-company-prospects/get-field-options',
        autoLoad: true,

        baseParams: {
            'q_id':       Ext.encode(q_id),
            'q_field_id': Ext.encode(field_id)
        },

        reader: new Ext.data.JsonReader({
                root:'rows',
                totalProperty:'totalCount'
            }, Option
        ),
        listeners: {
            beforeload: function() {
                updateFieldWindow.getEl().mask('Loading...');
            },

            load: function() {
                // IE fix
                updateFieldWindow.setSize(updateFieldWindow.getWidth() + 1, updateFieldWindow.getHeight() + 1);

                // Additional info
                if(Ext.getCmp('default-field-name') && this.reader.jsonData['defaultName']) {
                    Ext.getCmp('default-field-name').setValue(this.reader.jsonData['defaultName']);
                }

                currentHelpValue = this.reader.jsonData['fieldHelp'];
                if(Ext.getCmp('new-field-help') && currentHelpValue) {
                    Ext.getCmp('new-field-help').setValue(currentHelpValue);
                }

                if(Ext.getCmp('new-field-help-show') && this.reader.jsonData['fieldHelpShow'] !== null) {
                    var showHelp = this.reader.jsonData['fieldHelpShow'] ? 'yes' : 'no';
                    Ext.getCmp('new-field-help-show').setValue(showHelp);
                }

                if(Ext.getCmp('new-field-hidden') && this.reader.jsonData['fieldHidden']) {
                    Ext.getCmp('new-field-hidden').setValue(this.reader.jsonData['fieldHidden']);
                }

                updateFieldWindow.getEl().unmask();
            }
        }
    });

    var options_toolbar = [];
    if (field_type == 'combo_custom') {
        options_toolbar = [{
            text: '<i class="las la-plus"></i>' + _('Add Option'),
            handler : function(){
                var p = new Option({
                    'option_id': 0,
                    'option_original_name': '',
                    'option_name': '',
                    'option_selected': false,
                    'option_order': options_store.data.length,
                    'option_visible': true
                });
                options_grid.stopEditing();
                options_store.insert(options_store.data.length, p);

                // Hack to make it marked as dirty
                var record = options_store.getAt(options_store.data.length - 1);
                record.beginEdit();
                record.set('option_name', 'New option');
                record.endEdit();
                options_grid.startEditing(options_store.data.length - 1, 1);
            }
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Option'),
            ref: '../deleteOptionBtn',
            disabled: true,
            handler : function(){
                var selected = options_grid.getSelectionModel().getSelected();
                if (selected) {
                    
                    var question = String.format(
                        'Are you sure you want to delete <span style="font-style: italic;">{0}</span> option?',
                        selected.data.option_name
                    );

                    Ext.Msg.confirm('Please confirm', question,
                        function(btn){
                            if(btn == 'yes'){
                                options_grid.store.remove(selected);
                            }
                        }
                    );
                }
            }
        }];
    }

    var options_grid = new Ext.grid.EditorGridPanel({
        title: 'Options',

        store: options_store,
        plugins: [action],
        cm: cm,
        sm : sm,

        tbar: options_toolbar,

        anchor: '100% 70%',
        viewConfig: {
            scrollOffset: 5, // hide scrollbar "column" in Grid
            forceFit: true
        },
        clicksToEdit: 2
    });

    // Mark selected option in bold
    options_grid.getView().getRowClass = function(record){
        return (record.data.option_selected ? 'bold-row' : '');
    };

    var simplified = Ext.getCmp('qnr_simplified_'+q_id).getValue();

    var fieldList = [
        {
            id: 'default-field-name',
            xtype: 'textfield',
            fieldLabel: 'Original wording',
            readOnly: true,
            height: 120,
            width: 770,
            hidden: booShowOnlyOne,
            value: ''
        }, {// Spacer
            xtype:'label',
            text: '',
            hidden: booShowOnlyOne,
            html: '<div style="margin-bottom: 20px;"></div>'
        }, {
            id: 'new-field-name',
            xtype: 'textfield',
            fieldLabel: booShowOnlyOne ? 'Label' : 'Your wording',
            height: 120,
            width: 770,
            allowBlank: false,
            value: currentLabel
        }, {// Spacer
            xtype:'label',
            text: '',
            hidden: booShowOnlyOne,
            html: '<div style="margin-bottom: 20px;"></div>'
        }, {
            id: 'prospect-field-name',
            xtype: 'froalaeditor',
            booUseSimpleFroalaPanel: true,
            fieldLabel: 'Prospect field label',
            height: 120,
            width: 770,
            hidden: !booShowOnlyOne,
            value: currentProspectFieldLabel
        }
    ];

    if (simplified) {
        fieldList.push(
            {
                id: 'new-field-hidden',
                xtype: 'checkbox',
                checked: false,
                boxLabel: 'Hide Field'
            }
        );
    }


    var labelForm = new Ext.form.FormPanel({
        title: 'Label',
        baseCls: 'x-plain',
        labelAlign: 'top',
        bodyStyle:'padding:5px;',

        items: fieldList
    });

    var arrTabs = [labelForm];
    if(!booHideOptionsGrid) {
        arrTabs[arrTabs.length] = options_grid;
    }


    // If 'no' is selected - disable the editor
    var updateHelpRadios = function() {
        var value = Ext.getCmp('new-field-help-show').getValue();
        Ext.getCmp('new-field-help').setReadOnly(!value || value.getGroupValue() == 'no');
        if(currentHelpValue) {
            Ext.getCmp('new-field-help').setValue(currentHelpValue);
        }
    };


    arrTabs[arrTabs.length] = new Ext.form.FormPanel({
        title: 'Help description',
        baseCls: 'x-plain',
        bodyStyle:'padding:5px;',
        labelWidth: 260,

        items: [
            {
                id: 'new-field-help-show',
                xtype: 'radiogroup',
                fieldLabel: 'Would you like this help to be shown?',
                labelSeparator: '',
                width: 150,
                items: [
                    {boxLabel: 'Yes', name: 'q_section_help', inputValue: 'yes'},
                    {boxLabel: 'No', name: 'q_section_help', inputValue: 'no'}
                ],
                listeners: {
                    change: function () {
                        updateHelpRadios();
                    }
                }
            }, {
                id: 'new-field-help',
                xtype: 'froalaeditor',
                booUseSimpleFroalaPanel: true,
                booBasicEdition: true,
                hideLabel: true,
                height: 250,
                heightDifference: 50,
                width: 800,
                value: '',

                listeners: {
                    render: function() {
                        updateHelpRadios();
                    }
                }
            }
        ],

        listeners: {
            activate: function () {
                setTimeout(function () {
                    updateHelpRadios();
                }, 100);
            }
        }
    });


    var updateFieldTabPanel = new Ext.TabPanel({
        enableTabScroll:true,
        autoWidth: true,
        frame: false,
        border: false,
        height: 400,
        defaults:{autoHeight: true},
        activeTab: 0,
        items: arrTabs
    });

    var updateFieldWindow = new Ext.Window({
        title: '<i class="las la-edit"></i>' + _('Update field'),
        width: 805,
        height: 470,

        layout: 'fit',
        resizable: false,
        modal: true,
        buttonAlign:'center',
        items: updateFieldTabPanel,

        buttons: [{
            text: 'Cancel',
            handler: function() {
                updateFieldWindow.close();
            }
        },
            {
            text: '<i class="las la-save"></i>' + _('Update'),
            cls:  'orange-btn',
            handler: function() {
                if(!labelForm.getForm().isValid()) {
                    return false;
                }

                var arrOptions = [];
                if(!booHideOptionsGrid) {
                    // Check if there is at least one option
                    if(empty(options_store.getCount())) {
                        Ext.simpleConfirmation.error('At least one option must be created for the field. Please add and try again later.');
                        return false;
                    }

                    // Check if there is at least one option visible
                    var booIsVisible = false;
                    var order = 0;
                    options_store.each(function(rec) {
                        rec.data.option_order = order;
                        arrOptions[arrOptions.length] = rec.data;
                        if(rec.data.option_visible) {
                            booIsVisible = true;
                        }
                        order++;
                    });

                    if(!booIsVisible) {
                        Ext.simpleConfirmation.error('At least one option must be marked as visible. Please mark and try again later.');
                        return false;
                    }
                }

                updateFieldWindow.getEl().mask('Updating...');

                // We need activate the last tab and activate just viewed
                var activeTabId = updateFieldTabPanel.getActiveTab().id;
                updateFieldTabPanel.setActiveTab(updateFieldTabPanel.items.length-1);
                updateFieldTabPanel.setActiveTab(activeTabId);

                var newFieldName     = Ext.getCmp('new-field-name').getValue();
                var newFieldHidden   = Ext.getCmp('new-field-hidden') ? Ext.getCmp('new-field-hidden').getValue() : false;
                var newFieldHelp     = Ext.getCmp('new-field-help').getValue();
                var newFieldHelpShow = Ext.getCmp('new-field-help-show').getValue().getGroupValue();
                var prospectFieldName     = Ext.getCmp('prospect-field-name').getValue();

                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/qnr-update-field',
                    params: {
                        'q_id':              Ext.encode(q_id),
                        'q_field_id':        Ext.encode(field_id),
                        'q_field_name':      Ext.encode(newFieldName),
                        'q_field_hidden':    Ext.encode(newFieldHidden),
                        'q_field_help':      Ext.encode(newFieldHelp),
                        'q_field_help_show': Ext.encode(newFieldHelpShow),
                        'q_field_options':   Ext.encode(arrOptions),
                        'q_field_prospect_profile_label':   Ext.encode(prospectFieldName)
                    },
                    
                    success: function(result) {
                        updateFieldWindow.getEl().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            if (saveChangesOnProspectQuestionnaire) {
                                saveChangesOnProspectQuestionnaire(false);
                            } else {
                                // Refresh the tab
                                Ext.getCmp('q_tab_' + q_id).doAutoLoad();
                            }

                            Ext.simpleConfirmation.info('Field was successfully updated');
                            updateFieldWindow.close();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    
                    failure: function()  {
                        updateFieldWindow.getEl().unmask();
                        Ext.simpleConfirmation.error('Field cannot be updated. Please try again later.');
                    }
                });
            }
        }]
    });

    updateFieldWindow.show();
    updateFieldWindow.center();
};


/**
    Show edit section dialog
*/
var changeSectionLabel = function(sectionId, qId) {
    // If 'no' is selected - disable the editor
    var updateHelpRadios = function() {
        var value = Ext.getCmp('qnr-new-section-help-show').getValue();
        Ext.getCmp('qnr-new-section-help').setReadOnly(!value || value.getGroupValue() === 'no');

        if(currentHelpValue) {
            Ext.getCmp('qnr-new-section-help').setValue(currentHelpValue);
        }
    };

    var booShowOnlyOne = (qId === 1);
    var originalName = '';
    var currentHelpValue = '';
    var firstSection = false;
    Ext.each(arrDefaultSections, function(item) {
        if(item.q_section_id == sectionId) {
            originalName = item['q_section_template_name'];
            firstSection = item['q_section_step'] == '1' && item['q_section_order'] == '0';
        }
    });

    var simplified = Ext.getCmp('qnr_simplified_'+qId).getValue();

    var fieldList = [
        {
            fieldLabel: 'Original wording',
            width: 780,
            disabled: true,
            hidden: booShowOnlyOne,
            value: originalName
        }, {
            id: 'qnr-new-section-name',
            fieldLabel: !booShowOnlyOne ? 'Your wording' : 'Section label',
            width: 780,
            height: 200,
            allowBlank: false,
            msgTarget: 'qtip'
        }
    ];

    if (simplified && !firstSection) {
        fieldList.push({
            id: 'qnr-new-section-hidden',
            xtype: 'checkbox',
            checked: false,
            boxLabel: 'Hide Section',
            height: 22
        });
    }
    
    var sectionLabelForm = new Ext.form.FormPanel({
        title: 'Label',
        baseCls: 'x-plain',
        defaultType: 'textfield',
        bodyStyle:'padding:5px;',
        labelAlign: 'top',
        
        items: fieldList
    });
    
    var sectionHelpForm = new Ext.form.FormPanel({
        title: 'Help description',
        baseCls: 'x-plain',
        bodyStyle:'padding:5px;',
        labelWidth: 250,
        
        items: [{
                id: 'qnr-new-section-help-show',
                xtype: 'radiogroup',
                fieldLabel: 'Would you like this help to be shown?',
                width: 120,
                items: [
                    {boxLabel: 'Yes', name: 'q_section_help', inputValue: 'yes'},
                    {boxLabel: 'No', name: 'q_section_help', inputValue: 'no'}
                ],
                listeners: {
                    change: function() {
                        updateHelpRadios();
                    }
                }
            }, {
                id: 'qnr-new-section-help',
                xtype: 'froalaeditor',
                booUseSimpleFroalaPanel: true,
                booBasicEdition: true,
                hideLabel: true,
                height: 150,
                width: 810,
                value: '',
                
                listeners: {
                    render: function() {
                        updateHelpRadios();
                    }
                }
            }
        ]
    });
    
    
    var updateSectionTabs = new Ext.TabPanel({
        enableTabScroll:true,
        width: 800,
        height: 300,
        defaults:{autoHeight: true},
        activeTab: 0,
        items: [sectionLabelForm, sectionHelpForm]
    });
    

    var updateSectionWindow = new Ext.Window({
        title: '<i class="las la-edit"></i>' + _('Update section label'),
        autoWidth: true,
        autoHeight: true,
        layout: 'fit',
        plain:true,
        modal: true,
        bodyStyle:'padding:5px;',
        buttonAlign:'center',
        items: updateSectionTabs,

        buttons: [{
            text: 'Cancel',
            handler: function() {
                updateSectionWindow.close();
            }
        },
            {
            text: 'Update',
            cls: 'orange-btn',
            handler: function() {
                if(!sectionLabelForm.getForm().isValid() || !sectionHelpForm.getForm().isValid()) {
                    return false;
                }

                // We need activate the last tab and activate just viewed
                var activeTabId = updateSectionTabs.getActiveTab().id;
                updateSectionTabs.setActiveTab(updateSectionTabs.items.length-1);
                updateSectionTabs.setActiveTab(activeTabId);
                
                var newSectionName   = Ext.getCmp('qnr-new-section-name').getValue();
                var newSectionHidden = Ext.getCmp('qnr-new-section-hidden') ? Ext.getCmp('qnr-new-section-hidden').getValue() : false;
                updateSectionWindow.getEl().mask('Updating...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/qnr-update-section',
                    params: {
                        'q_id':                Ext.encode(qId),
                        'q_section_id':        Ext.encode(sectionId),
                        'q_section_name':      Ext.encode(newSectionName),
                        'q_section_hidden':    Ext.encode(newSectionHidden),
                        'q_section_help_show': Ext.encode(Ext.getCmp('qnr-new-section-help-show').getValue().getGroupValue()),
                        'q_section_help':      Ext.encode(Ext.getCmp('qnr-new-section-help').getValue())
                    },
                    success: function(result) {
                        updateSectionWindow.getEl().unmask();
                    
                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.getDom('qnr_' + qId + 'section_' + sectionId).innerHTML = newSectionName;
                        
                            Ext.simpleConfirmation.info('Section was successfully updated');
                            if (saveChangesOnProspectQuestionnaire) {
                                saveChangesOnProspectQuestionnaire(false);
                            }
                            updateSectionWindow.close();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    
                    failure: function()  {
                        updateSectionWindow.getEl().unmask();
                        Ext.simpleConfirmation.error('Section <span style="font-style: italic;">' + newSectionName + '</span> cannot be updated. Please try again later.');
                    }
                });

            }
        }],
        
        listeners: {
            show: function() {
                updateSectionWindow.getEl().mask('Loading...');
                
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/get-section-details',
                    params: {
                        q_id:         Ext.encode(qId),
                        q_section_id: Ext.encode(sectionId)
                    },
                    success: function(result) {
                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.getCmp('qnr-new-section-name').setValue(resultDecoded['arrInfo'].name);
                            
                            var showHelp = resultDecoded['arrInfo']['help_show'] ? 'yes' : 'no';
                            Ext.getCmp('qnr-new-section-help-show').setValue(showHelp);
                            currentHelpValue = resultDecoded['arrInfo']['help'] || '';
                            Ext.getCmp('qnr-new-section-help').setValue(currentHelpValue);
                            if (Ext.getCmp('qnr-new-section-hidden')) {
                                Ext.getCmp('qnr-new-section-hidden').setValue(resultDecoded['arrInfo']['hidden']);
                            }
                            
                            updateSectionWindow.getEl().unmask();
                            updateHelpRadios();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                            updateSectionWindow.close();
                        }
                    },
                    failure: function()  {
                        Ext.simpleConfirmation.error('Cannot load section details. Please try again later.');
                        updateSectionWindow.close();
                    }
                });
            }
        }

    });

    updateSectionWindow.show();
    updateSectionWindow.center();
};

var q_store = new Ext.data.Store({
    url: baseUrl + '/manage-company-prospects/qnr-list',
    autoLoad: true,
    reader: new Ext.data.JsonReader({
        root: 'rows',
        totalProperty: 'totalCount',
        id: 'q_id'
    }, [
       {name: 'q_id'},
       {name: 'q_name'},
       {name: 'q_noc'},
       {name: 'q_author'},
       {name: 'q_created_on', type: 'date', dateFormat: dateFormatFull},
       {name: 'q_updated_on', type: 'date', dateFormat: dateFormatFull}
    ]),

    listeners: {}
});


var showQuestionnairesSection = function() {
    if(Ext.getCmp('prospects-questionnaires-tabs')) {
        return;
    }
    
    var qnrAdd = function() {
    
        var defaultQnrsStore = new Ext.data.Store({
            data: arrDefaultQnrs,
            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                id: 'q_id'
            }, [{name: 'q_id'}, {name: 'q_name'}])
        });
    
        var officeStore = new Ext.data.Store({
            data: arrCompanyOffices,
            reader: new Ext.data.JsonReader({
                root: 'rows',
                totalProperty: 'totalCount',
                id: 'office_id'
            }, [{name: 'office_id'}, {name: 'office_name'}])
        });

        var form = new Ext.form.FormPanel({
            baseCls: 'x-plain',
            defaultType: 'textfield',
            labelAlign: 'top',

            items: [
                {
                    id: 'new_qnr_name',
                    fieldLabel: 'Questionnaire name',
                    allowBlank: false,
                    width: 350
                }, {
                    id: 'new_qnr_noc',
                    xtype: 'radiogroup',
                    fieldLabel: 'NOC',
                    hidden: site_version == 'australia',
                    items: [
                        {boxLabel: 'English', name: 'q_noc', inputValue: 'en', checked: true},
                        {boxLabel: '<span style="color: gray">French</span>', name: 'q_noc', inputValue: 'fr'}
                    ]
                }, {
                    id: 'new_qnr_template_id',
                    xtype: 'combo',
                    fieldLabel: 'Template',
                    width: 350,
                    store: defaultQnrsStore,
                    displayField: 'q_name',
                    valueField: 'q_id',
                    mode: 'local',
                    typeAhead: false,
                    editable: false,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    allowBlank: false,
                    emptyText: 'Select a template...',
                    value: 1
                }, {
                    id: 'new_qnr_office_id',
                    xtype: 'combo',
                    fieldLabel: office_label,
                    width: 350,
                    store: officeStore,
                    displayField: 'office_name',
                    valueField: 'office_id',
                    mode: 'local',
                    typeAhead: false,
                    editable: false,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    allowBlank: empty(arrCompanyOffices.rows.length),
                    emptyText: 'Please select an office...',
                    hidden: empty(arrCompanyOffices.rows.length),
                    value: arrCompanyOffices.rows.length == 1 ? arrCompanyOffices.rows[0]['office_id'] : undefined
                }, {
                    id: 'new_qnr_office_simplified',
                    xtype: 'checkbox',
                    checked: false,
                    boxLabel: 'Simplified Questionnaire' + (site_version === 'australia' ? '' : ' - No Assessment')
                }
            ]
        });

        var new_qnr_wnd = new Ext.Window({
            id: 'new_qnr_dialog',
            title: '<i class="las la-plus"></i>' + _('New questionnaire'),
            autoWidth: true,
            autoHeight: true,
            layout: 'fit',
            plain: true,
            border: false,
            modal: true,
            bodyStyle: 'padding:5px;',
            items: form,

            buttons: [{
                text: 'Cancel',
                handler: function () {
                    new_qnr_wnd.close();
                }
            },
                {
                    text: 'Create',
                cls:  'orange-btn',
                handler: function() {
                    if(form.getForm().isValid()) {
                        new_qnr_wnd.getEl().mask('Creating...');
                        var new_qnr_name        = Ext.getCmp('new_qnr_name').getValue();
                        var new_qnr_noc         = Ext.getCmp('new_qnr_noc').getValue().getGroupValue();
                        var new_qnr_template_id = Ext.getCmp('new_qnr_template_id').getValue();

                        var officeField       = Ext.getCmp('new_qnr_office_id');
                        var new_qnr_office_id = officeField.isVisible() ? officeField.getValue() : 0;
                        var new_qnr_office_id = officeField.isVisible() ? officeField.getValue() : 0;

                        var simplified = Ext.getCmp('new_qnr_office_simplified').getValue();

                        Ext.Ajax.request({
                            url: baseUrl + '/manage-company-prospects/qnr-add',
                            params: {
                                'q_name':        Ext.encode(new_qnr_name),
                                'q_noc':         Ext.encode(new_qnr_noc),
                                'q_template_id': Ext.encode(new_qnr_template_id),
                                'q_office_id':   Ext.encode(new_qnr_office_id),
                                'q_simplified':   Ext.encode(simplified)
                            },
                            success: function(result) {
                                new_qnr_wnd.getEl().unmask();

                                var resultDecoded = Ext.decode(result.responseText);
                                if(resultDecoded.success) {
                                    new_qnr_wnd.close();
                                    q_store.reload();
                                    qnrEdit(resultDecoded.q_id, new_qnr_name);
                                } else {
                                    // Show error message
                                    Ext.simpleConfirmation.error(resultDecoded.message);
                                }
                            },
                            failure: function()  {
                                new_qnr_wnd.getEl().unmask();
                                Ext.simpleConfirmation.error('Questionnaire <span style="font-style: italic;">' + new_qnr_name + '</span> cannot be created. Please try again later.');
                            }
                        });
                    }
                }
            }]
        });

        new_qnr_wnd.show();
        new_qnr_wnd.center();
    };

    // Show edit dialog for selected QNR
    var qnrEdit = function(q_id, q_name) {
        var tab_id = 'q_tab_' + q_id;
        
        if(Ext.getCmp(tab_id)) {
            tabs.activate(tab_id);
        } else {
            tabs.add({
                id:       tab_id,
                title:    '<i class="las la-pen"></i>' + q_name,
                closable: true,
                
                autoLoad: {
                    url: baseUrl + '/manage-company-prospects/qnr-edit?q_id=' + q_id,
                    scripts: true,
                    callback: function() {
                        initQnrSettingsFields(q_id);
                        setTimeout(function(){
                            updateSuperadminIFrameHeight('#prospects-questionnaires', true);
                        }, 200);
                    }
                }
            }).show();
        }
    };

    // Delete selected QNR
    var qnrDelete = function() {
        var qnr = grid.getSelectionModel().getSelected();
        var qnrName = qnr.data.q_name;
        Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete <span style="font-style: italic;">' + qnrName + '</span>?', function(btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/qnr-delete',
                    params: {
                        q_id: Ext.encode(qnr.data.q_id)
                    },
                    success: function(result) {
                        Ext.getBody().unmask();
                        
                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            Ext.simpleConfirmation.info(resultDecoded.message);
                            q_store.reload();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function()  {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Questionnaire <span style="font-style: italic;">' + qnrName + '</span> cannot be deleted. Please try again later.');
                    }
                });
            }
        });
    };

    // Create a duplicate of selected QNR
    var qnrDuplicate = function() {
        var qnr = grid.getSelectionModel().getSelected();
        var qnrName = '<span style="font-style: italic;">' + qnr.data.q_name + '</span>';

        Ext.Msg.confirm('Please confirm', 'Are you sure you want to duplicate ' + qnrName + '?', function(btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Duplicating...');
                Ext.Ajax.request({
                    url: baseUrl + '/manage-company-prospects/qnr-duplicate',
                    params: {
                        q_id: Ext.encode(qnr.data.q_id)
                    },
                    success: function(result) {
                        Ext.getBody().unmask();

                        var resultDecoded = Ext.decode(result.responseText);
                        if(resultDecoded.success) {
                            grid.getStore().reload();
                            qnrEdit(resultDecoded.q_id, resultDecoded.q_name);
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },
                    failure: function()  {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Questionnaire ' + qnrName + ' cannot be duplicated. Please try again later.');
                    }
                });
            }
        });
    };

    // Update toolbar buttons in relation to the selection in the grid
    var updateToolbarButtons = function() {
        var sel = grid.getSelectionModel().getSelections();
        var booIsSelectedOne = sel.length === 1;

        grid.editQnrBtn.setDisabled(!booIsSelectedOne);
        grid.deleteQnrBtn.setDisabled(!booIsSelectedOne);
        grid.duplicateQnrBtn.setDisabled(!booIsSelectedOne);
    };


    // Create the Grid
    var grid = new Ext.grid.GridPanel({
        id: 'questionnaires-grid',
        title: '<i class="las la-table"></i>' + _('Questionnaires'),
        store: q_store,
        cm: new Ext.grid.ColumnModel({
            defaults: {
                sortable: true
            },
            columns: [
                {id: 'q_name', dataIndex: 'q_name', header: _('Name'), width: 160},
                {dataIndex: 'q_noc', header: _('NOC'), width: 60, hidden: site_version == 'australia'},
                {dataIndex: 'q_updated_on', header: _('Last Updated'), width: 130, renderer: Ext.util.Format.dateRenderer(dateFormatFull)},
                {dataIndex: 'q_created_on', header: _('Created On'), width: 130, renderer: Ext.util.Format.dateRenderer(dateFormatFull), hidden: true}
            ]
        }),
        sm: new Ext.grid.RowSelectionModel({singleSelect:true}),

        stripeRows: true,
        autoExpandColumn: 'q_name',
        autoWidth: true,
        autoHeight: true,
        loadMask: true,
        
        viewConfig: {
            emptyText: 'There are no questionnaires',
            deferEmptyText: false
        },
        
        tbar: [{
            text: '<i class="las la-plus"></i>' + _('New Questionnaire'),
            cls: 'main-btn',
            handler: function () {
                qnrAdd();
            }
        }, {
            text: '<i class="las la-edit"></i>' + _('Edit Questionnaire'),
            ref: '../editQnrBtn',
            disabled: true,
            handler: function () {
                var sel = grid.getSelectionModel().getSelected();
                qnrEdit(sel.data.q_id, sel.data.q_name);
            }
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Questionnaire'),
            ref: '../deleteQnrBtn',
            disabled: true,
            handler: function () {
                qnrDelete();
            }
        }, '-', {
            text: '<i class="las la-copy"></i>' + _('Duplicate Questionnaire'),
            ref: '../duplicateQnrBtn',
            disabled: true,
            handler: function () {
                qnrDuplicate();
            }
        },
        '->',
        {
            text: '<i class="las la-undo-alt"></i>',
            handler: function() {
                q_store.reload();
            }
        }],
        
        listeners: {
            dblclick: function() {
                var sel = grid.getSelectionModel().getSelected();
                qnrEdit(sel.data.q_id, sel.data.q_name);
            }
        }
    });
    grid.getSelectionModel().on('selectionchange', updateToolbarButtons, this);

    var tabs = new Ext.TabPanel({
        id: 'prospects-questionnaires-tabs',
        renderTo: 'prospects-questionnaires',
        cls: 'clients-tab-panel',
        autoWidth: true,
        autoHeight: true,
        activeTab: 0,
        frame:false,
        defaults:{autoHeight: true},
        items:[grid],
        
        listeners: {
            tabchange: function() {
                setTimeout(function(){
                    updateSuperadminIFrameHeight('#prospects-questionnaires', true);
                }, 200);
            },
            afterrender: function() {
                $('#prospects-questionnaires').css('min-height', (getSuperadminPanelHeight() - $('#prospects-questionnaires').outerHeight() + $('#prospects-questionnaires').height() - $('#manage_company_prospects_container').outerHeight()) + 'px');
                setTimeout(function(){
                    updateSuperadminIFrameHeight('#prospects-questionnaires', true);
                }, 200);

            }
        }
    });
    $('#prospects-questionnaires-tabs').css('min-height', (getSuperadminPanelHeight() - $('#manage_company_prospects_container').outerHeight() - $('#prospects-questionnaires').outerHeight() + $('#prospects-questionnaires').height()) + 'px');
};


var initQnrSettingsFields = function(q_id) {
    initQnrFieldTips();
    
    // Initialize search fields
    initQnrSearch(q_id, '');
    
    
    var qnr_name = new Ext.form.TextField({
        id: 'qnr_name_' + q_id,
        applyTo: 'qnr_name_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter questionnaire name'
    });
    
    if(Ext.get('qnr_office_' + q_id)) {
        var qnr_office = new Ext.form.ComboBox({
            id: 'qnr_officeid_' + q_id,
            typeAhead: false,
            editable: false,
            triggerAction: 'all',
            transform: 'qnr_office_' + q_id,
            width: 300,
            forceSelection:true
        });
    }
    
    if(Ext.get('qnr_agent_' + q_id)) {
        var qnr_agent = new Ext.form.ComboBox({
            id: 'qnr_agentid_' + q_id,
            typeAhead: false,
            editable: false,
            triggerAction: 'all',
            transform: 'qnr_agent_' + q_id,
            width: 300,
            forceSelection:true
        });
    }
    
    var qnr_preferred_language = new Ext.form.TextField({
        id: 'qnr_preferred_language_' + q_id,
        applyTo: 'qnr_preferred_language_' + q_id,
        width: 300
    });
    
    var qnr_simplified = new Ext.form.Checkbox({
        id: 'qnr_simplified_' + q_id,
        applyTo: 'qnr_simplified_' + q_id,
        handler:   function (obj, checked) {
            saveChanges(false);
        }
    });
    
    var qnr_logo_on_top = new Ext.form.Checkbox({
        id: 'qnr_logo_on_top_' + q_id,
        applyTo: 'qnr_logo_on_top_' + q_id
    });
    
    var qnr_applicant_name = new Ext.form.TextField({
        id: 'qnr_applicant_name_' + q_id,
        applyTo: 'qnr_applicant_name_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter applicant name'
    });
    
    var qnr_noc_en = new Ext.form.Radio({
        id: 'q_noc_en_' + q_id,
        fieldLabel:     '',
        labelSeparator: '',
        boxLabel:       'English',
        name:           'qnr_noc',
        inputValue:     'en',
        hidden:         site_version == 'australia',
        applyTo:        'q_noc_en_' + q_id
    });
    
    var qnr_noc_fr = new Ext.form.Radio({
        id: 'q_noc_fr_' + q_id,
        fieldLabel:     '',
        labelSeparator: '',
        boxLabel:       'French',
        name:           'qnr_noc',
        inputValue:     'fr',
        hidden:         site_version == 'australia',
        applyTo:        'q_noc_fr_' + q_id
    });
    
    var qnr_rtl_no = new Ext.form.Radio({
        id: 'q_rtl_no_' + q_id,
        fieldLabel:     '',
        labelSeparator: '',
        boxLabel:       'Left-to-right',
        name:           'qnr_rtl',
        inputValue:     'N',
        applyTo:        'q_rtl_no_' + q_id
    });
    
    var qnr_rtl_yes = new Ext.form.Radio({
        id: 'q_rtl_yes_' + q_id,
        fieldLabel:     '',
        labelSeparator: '',
        boxLabel:       'Right-to-left',
        name:           'qnr_rtl',
        inputValue:     'Y',
        applyTo:        'q_rtl_yes_' + q_id
    });
    
    var qnr_please_select = new Ext.form.TextField({
        id: 'qnr_please_select_' + q_id,
        applyTo: 'qnr_please_select_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });
    
    var qnr_please_answer_all = new Ext.form.TextField({
        id: 'qnr_please_answer_all_' + q_id,
        applyTo: 'qnr_please_answer_all_' + q_id,
        width: 300,
        allowBlank: true
    });
    
    var qnr_please_press_next = new Ext.form.TextField({
        id: 'qnr_please_press_next_' + q_id,
        applyTo: 'qnr_please_press_next_' + q_id,
        width: 300,
        allowBlank: true
    });
    
    var qnr_next_page_button = new Ext.form.TextField({
        id: 'qnr_next_page_button_' + q_id,
        applyTo: 'qnr_next_page_button_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });
    
    
    var qnr_prev_page_button = new Ext.form.TextField({
        id: 'qnr_prev_page_button_' + q_id,
        applyTo: 'qnr_prev_page_button_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });
    
    var qnr_step1 = new Ext.form.TextField({
        id: 'qnr_step1_' + q_id,
        applyTo: 'qnr_step1_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });

    var qnr_step2 = new Ext.form.TextField({
        id: 'qnr_step2_' + q_id,
        applyTo: 'qnr_step2_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });

    var qnr_step3 = new Ext.form.TextField({
        id: 'qnr_step3_' + q_id,
        applyTo: 'qnr_step3_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });

    var qnr_step4 = new Ext.form.TextField({
        id: 'qnr_step4_' + q_id,
        applyTo: 'qnr_step4_' + q_id,
        width: 300,
        allowBlank: false,
        emptyText: 'Please enter the text'
    });
    
    var arrCustomColors = [
        "000000", "993300", "333300", "003300", "003366", "000080", "333399", "333333",
        "800000", "FF6600", "808000", "008000", "008080", "0000FF", "666699", "808080",
        "FF0000", "FF9900", "99CC00", "339966", "33CCCC", "4C83C5", "800080", "969696",
        "FF00FF", "FFCC00", "FFFF00", "00FF00", "00FFFF", "00CCFF", "993366", "C0C0C0",
        "FF99CC", "FFCC99", "FFFF99", "CCFFCC", "CCFFFF", "99CCFF", "CC99FF", "FFFFFF"
    ];
    
    var selectedColor = Ext.get('qnr_bg_title_hidden_' + q_id).getValue();
    selectedColor = empty(selectedColor) ? '4C83C5' : selectedColor;
    var qnr_bg_title = new Ext.ColorPalette({
        id: 'qnr_title_bg_' + q_id,
        renderTo: 'qnr_bg_title_'+ q_id,
        value: selectedColor,
        colors: arrCustomColors,
        listeners: {
            select: function(cp, color){
                selectedColor = color;
            }
        }
    });
    
    
    var selectedTextColor = Ext.get('qnr_color_title_hidden_' + q_id).getValue();
    selectedTextColor = empty(selectedTextColor) ? 'FFFFFF' : selectedTextColor;
    var qnr_color_title = new Ext.ColorPalette({
        id: 'qnr_title_color_' + q_id,
        renderTo: 'qnr_color_title_'+ q_id,
        value: selectedTextColor,
        colors: arrCustomColors,
        allowReselect: true,
        listeners: {
            select: function(cp, color){
                selectedTextColor = color;
            }
        }
    });
    
    
    var buttonColor = Ext.get('qnr_button_color_hidden_' + q_id).getValue();
    buttonColor = empty(buttonColor) ? 'FFFFFF' : buttonColor;
    var qnr_button_color = new Ext.ColorPalette({
        id: 'qnr_button_color_' + q_id,
        renderTo: 'qnr_button_color_'+ q_id,
        value: buttonColor,
        colors: arrCustomColors,
        allowReselect: true,
        listeners: {
            select: function(cp, color){
                buttonColor = color;
            }
        }
    });

    
    
    var ds = new Ext.data.Store({
        url: baseUrl + '/manage-company-prospects/get-templates-list',
        autoLoad: false,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount',
            id: 'templateId'
        }, [{name: 'templateId'}, {name: 'templateName'}])
    });
    
    // Load templates list from saved array
    ds.loadData(arrProspectTemplates);
    
    
    var els=Ext.select("input.q_template_combo",true);
    var arrTemplateIds = [];
    els.each(function(el){
        var newId = 'qnr_template_combo_' + el.id;
        arrTemplateIds[arrTemplateIds.length] = newId;
        new Ext.form.ComboBox({
            id: newId,
            store: ds,
            mode: 'local',
            displayField: 'templateName',
            valueField: 'templateId',
            typeAhead: false,
            editable: false,
            triggerAction: 'all',
            selectOnFocus: true,
            allowBlank: false,
            applyTo: el.id,
            emptyText: 'Please select template',
            width: 225
        });

        $('#'+el.id).removeClass('q_template_combo');
    });
    
    
    if (!qnr_simplified.getValue()) {
        var template_negative = new Ext.form.ComboBox({
            id: 'qnr_template_negative' + q_id,
            store: ds,
            mode: 'local',
            displayField: 'templateName',
            valueField: 'templateId',
            typeAhead: false,
            editable: false,
            triggerAction: 'all',
            selectOnFocus: true,
            emptyText: 'Please select template',
            width: 225,
            allowBlank: false,
            applyTo: 'template_negative_' + q_id
        });
    }
    
    var template_thank_you = new Ext.form.ComboBox({
        id: 'qnr_template_thank_you' + q_id,
        store: ds,
        mode: 'local',
        displayField: 'templateName',
        valueField: 'templateId',
        typeAhead: false,
        editable: false,
        triggerAction: 'all',
        selectOnFocus: true,
        emptyText: 'Please select template',
        width: 225,
        allowBlank: false,
        applyTo: 'template_thank_you_' + q_id
    });

    var saveChanges = function(notifyUpdate=true) {
        var booValid = true;
        var arrCheckComponents = [
            'qnr_name_' + q_id,
            'qnr_please_select_' + q_id,
            'qnr_please_answer_all_' + q_id,
            'qnr_please_press_next_' + q_id,
            'qnr_next_page_button_' + q_id,
            'qnr_prev_page_button_' + q_id,
            'qnr_officeid_' + q_id,
            'qnr_agentid_' + q_id,
            'qnr_preferred_language_' + q_id,
            'qnr_simplified_' + q_id,
            'qnr_logo_on_top_' + q_id,
            'qnr_applicant_name_' + q_id,
            'qnr_template_negative' + q_id,
            'qnr_template_thank_you' + q_id
        ];

        var allComponentsCheck = arrCheckComponents.concat(arrTemplateIds);

        // Check all fields and if one of them is incorrect - disallow settings update
        for (var i = 0; i < allComponentsCheck.length; i++) {
            if(Ext.getCmp(allComponentsCheck[i]) && !Ext.getCmp(allComponentsCheck[i]).isValid()) {
                booValid = false;
            }
        }

        if(!booValid) {
            return false;
        }


        // Collect templates combos and selected values
        var arrQnrCategoryTemplates = [];
        for (i = 0; i < arrTemplateIds.length; i++) {
            if(Ext.getCmp(arrTemplateIds[i])) {
                // Get category id from combobox id
                var exploded = arrTemplateIds[i].split('qnr_template_combo_q_' + q_id + 'template_category_');
                var category_id = parseInt(exploded[1], 10);

                arrQnrCategoryTemplates[arrQnrCategoryTemplates.length] = {
                    'cat_id': category_id,
                    'template_id': Ext.getCmp(arrTemplateIds[i]).getValue()
                };
            }
        }

        var param = {
            'q_id':                 Ext.encode(q_id),
            'q_name':               Ext.encode(qnr_name.getValue()),
            'q_noc':                Ext.encode(qnr_noc_en.getValue() ? 'en' : 'fr'),
            'q_rtl':                Ext.encode(qnr_rtl_yes.getValue() ? 'Y' : 'N'),
            'q_applicant_name':     Ext.encode(qnr_applicant_name.getValue()),
            'q_office_id':          Ext.encode(qnr_office ? qnr_office.getValue() : ''),
            'q_agent_id':           Ext.encode(qnr_agent ? qnr_agent.getValue() : ''),
            'q_preferred_language': Ext.encode(qnr_preferred_language.getValue()),
            'q_simplified':         Ext.encode(qnr_simplified.getValue()),
            'q_logo_on_top':        Ext.encode(qnr_logo_on_top.getValue()),
            'q_please_select':      Ext.encode(qnr_please_select.getValue()),
            'q_please_answer_all':  Ext.encode(qnr_please_answer_all.getValue()),
            'q_please_press_next':  Ext.encode(qnr_please_press_next.getValue()),
            'q_next_page_button':   Ext.encode(qnr_next_page_button.getValue()),
            'q_prev_page_button':   Ext.encode(qnr_prev_page_button.getValue()),

            'q_step1':              Ext.encode(qnr_step1.getValue()),
            'q_step2':              Ext.encode(qnr_step2.getValue()),
            'q_step3':              Ext.encode(qnr_step3.getValue()),
            'q_step4':              Ext.encode(qnr_step4.getValue()),

            'q_section_bg_color':   Ext.encode(selectedColor),
            'q_section_text_color': Ext.encode(selectedTextColor),
            'q_button_color':       Ext.encode(buttonColor),

            'q_category_templates':      Ext.encode(arrQnrCategoryTemplates),
            'q_template_thank_you':      Ext.encode(template_thank_you.getValue()),
            'q_script_google_analytics': Ext.encode($('#qnr_script_google_analytics_' + q_id).val()),
            'q_script_facebook_pixel':   Ext.encode($('#qnr_script_facebook_pixel_' + q_id).val()),
            'q_script_analytics_on_completion': Ext.encode($('#qnr_script_analytics_on_completion_' + q_id).val())
        };

        if (!qnr_simplified.getValue() && template_negative) {
            param['q_template_negative'] = Ext.encode(template_negative.getValue());
        }


        // Submit request to save changes
        Ext.getBody().mask('Saving...');
        Ext.Ajax.request({
            url: baseUrl + '/manage-company-prospects/qnr-update-settings',
            params: param,

            success: function(result) {
                Ext.getBody().unmask();

                var resultDecoded = Ext.decode(result.responseText);
                if(resultDecoded.success) {
                    // Update tab name
                    Ext.getCmp('q_tab_' + q_id).setTitle(qnr_name.getValue());

                    // Refresh the tab
                    Ext.getCmp('q_tab_' + q_id).doAutoLoad();

                    if (notifyUpdate) {
                        Ext.simpleConfirmation.msg('Information','Settings were successfully updated.', 3000);
                    }
                } else {
                    // Show error message
                    Ext.simpleConfirmation.error(resultDecoded.message);
                }
            },

            failure: function()  {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Settings cannot be updated. Please try again later.');
            }
        });

    };

    saveChangesOnProspectQuestionnaire = saveChanges;

    var buttons = Ext.select('.save_button_container_' + q_id, true);

    buttons.each(function(el) {
        new Ext.Button({
            text: '<i class="las la-save"></i>' + _('Save changes'),
            cls: 'orange-btn',
            style: 'margin: 20px auto;',
            scale: 'medium',
            renderTo: el,

            handler: saveChanges
        });
    });
};


Ext.onReady(function(){
    $('#prospects-questionnaires-link').click(function(){
        showQuestionnairesSection();
    });
});