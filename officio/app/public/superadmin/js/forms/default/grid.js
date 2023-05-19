var FormsAssignDialog = function(member_id) {
    this.member_id = member_id;
    this.selectedForms = [];

    // shorthand
    var Tree = Ext.tree;
    var treePanel = new Tree.TreePanel({
        title: 'Categorical Search',
        useArrows: true,
        animate: true,
        autoScroll: true,
        containerScroll: true,
        rootVisible: false,

        root: new Ext.tree.AsyncTreeNode({
            text: 'Folders',
            singleClickExpand: true,
            id: 'source',
            cls: 'folder-icon'
        }),

        loader: new Tree.TreeLoader({
            dataUrl: topBaseUrl + '/forms/forms-folders/list',
            baseParams: {
                member_id: member_id,
                version: Ext.encode('latest')
            },

            preloadChildren: true,

            listeners: {
                load: function(){
                    if (treePanel.getRootNode().firstChild!==null) {
                        treePanel.getRootNode().firstChild.expand();
                        treePanel.getRootNode().firstChild.select();
                    }
            }
        }
        }),
        width: 300,
        height: 305,
        style: 'border: 1px solid #99BBE8; border-top: none;'
    });

    this.tree = treePanel;

    // add a tree sorter in folder mode
    new Tree.TreeSorter(this.tree, {folderSort: true});

    this.tree.getRootNode().reload();

    var sm = this.tree.getSelectionModel();
    var wndAssign = this;
    sm.on('beforeselect', function(sm, node) {
        var newDescription = '';

        // Load description for selected pdf form
        if (node.isLeaf()) {
            newDescription = node.attributes.description;
        }

        // Show new description
        wndAssign.setDescription(newDescription);

        //check all form that have been selected before
        $('.pform').each(function() {
            if (wndAssign.selectedForms.has(this.id)) {
                $('#' + this.id).prop('checked', true);
            }
        });

        wndAssign.setEventForCheckboxes();

        return true;
    });

    var pan = new Ext.Panel({
        layout: 'form',
        autoWidth: true,
        items: [
            {
                layout: 'table',
                id: 'forms_table',
                layoutConfig: {
                    columns: 2
                },
                items: [
                    this.tree,
                    {
                        title: 'Select form(s) to add:',
                        style: 'margin:0px 5px; border:1px solid #99BBE8; border-top:none; background:#DFE8F6;',
                        html: '<div id="assign-form-description" class="form-description" style="padding:5px; height:268px; overflow:auto;"></div>',
                        width: 520,
                        height: 305
                    }, {
                        xtype: 'form',
                        labelWidth: 95,
                        height: 66,
                        style: 'padding:7px 0px 0px 6px; border:1px solid #99BBE8; border-top:none;',
                        items: [
                            {
                                id: 'search-form',
                                xtype: 'textfield',
                                fieldLabel: 'Keyword Search',
                                labelStyle: 'background:url(' + baseUrl + '/images/orange-arrow.gif) no-repeat; padding-left:10px; background-position:0 50%; width: 95px;',
                                emptyText: site_version == 'australia' ? 'eg. 457, 80, etc...' : 'eg. 5406, 1344, etc...',
                                width: 155,
                                enableKeyEvents: true,
                                listeners: {
                                    keyup: function(field, e) {
                                        if (e.getKey() == Ext.EventObject.ENTER) {
                                            wndAssign.runSearchForm();
                                        }
                                    }
                                }
                            },
                            {
                                xtype: 'button',
                                cls: 'search-button',
                                icon: baseUrl + '/images/icons/find.png',
                                handler: function() {
                                    wndAssign.runSearchForm();
                                }
                            }
                        ]
                    },

                    {
                        xtype: 'form',
                        width: 520,
                        align: 'right',
                        style: 'padding:7px 0 2px 6px; margin:0px 5px; border:1px solid #99BBE8; border-top:none;',

                        items: [
                            {
                                id: 'assign-form-version-combo',
                                xtype: 'combo',
                                hideLabel: true,
                                store: new Ext.data.SimpleStore({
                                    fields: ['show_id', 'show_name'],
                                    data: [
                                        ['all', 'Show All versions of forms'],
                                        ['latest', 'Show only the LATEST version of forms']
                                    ]
                                }),
                                displayField: 'show_name',
                                valueField: 'show_id',
                                mode: 'local',
                                typeAhead: false,
                                editable: false,
                                forceSelection: true,
                                triggerAction: 'all',
                                selectOnFocus: true,
                                value: 'latest',
                                width: 260,

                                listeners: {
                                    'beforeselect': function(combo, rec) {
                                        // Check what is now active (search or tree)
                                        var selectedLandingPage = wndAssign.tree.getSelectionModel().getSelectedNode();
                                        var search_form = '';
                                        var search_field = Ext.getCmp('search-form');
                                        if (!empty(search_field)) {
                                            search_form = search_field.getValue();
                                        }

                                        if (empty(selectedLandingPage) && !empty(search_form)) {
                                            // Run search with new params
                                            wndAssign.runSearchForm(rec.data.show_id);
                                        } else {
                                            // Refresh landing pages list

                                            // Change base parameters
                                            var loader = wndAssign.tree.getLoader();
                                            loader.baseParams = loader.baseParams || {};

                                            var params = {version: Ext.encode(rec.data.show_id)};
                                            Ext.apply(loader.baseParams, params);

                                            // Reload tree list
                                            wndAssign.tree.getRootNode().reload();

                                            // Clear description
                                            wndAssign.setDescription('');
                                        }
                                    }
                                }
                            }
                        ]
                    }
                ]
            }
        ]
    });

    this.buttons = [{
        id: 'assign-form-cancel-btn',
        text: 'Cancel',
        animEl: 'forms-btn-add',
        scope: this,
        handler: function() {
            this.close();
        }
    },
        {
            id: 'assign-form-submit-btn',
            animEl: 'forms-btn-add',
            disabled: true,
            text: 'New form',
            cls: 'orange-btn',
            handler: this.sendRequestAddOrAssignForm.createDelegate(this)
        }];


    this.helpToolTip = new Ext.ToolTip({
        html: 'If you cannot find the name of the spouse or other dependants in this list, please go to Case Details tab and introduce these family members under the Dependants section.',
        title: 'Can\'t find a family member in this list?',
        autoHide: false,
        closable: true,
        anchor: 'top',
        anchorOffset: 80
    });

    FormsAssignDialog.superclass.constructor.call(this, {
        id: 'assign-form-window',
        title: 'Add form(s) to default forms library',
        width: 850,
        height: 450,

        plain: false,
        bodyStyle: 'padding:5px; background-color:#fff;',
        buttonAlign: 'center',
        modal: true,
        resizable: false,

        defaults: {
            // applied to each contained panel
            bodyStyle: 'vertical-align: top;'
        },

        items: pan
    });

    this.on('show', this.createToolTip.createDelegate(this));
    this.on('close', this.closeToolTip.createDelegate(this));
};


Ext.extend(FormsAssignDialog, Ext.Window, {
    createToolTip: function() {
        var wnd = this;
    },

    closeToolTip: function() {
        this.helpToolTip.hide();
    },

    setDescription: function(newDescription) {
        var descriptionContainer = Ext.get('assign-form-description');
        if (descriptionContainer) {
            Ext.DomHelper.overwrite(descriptionContainer, newDescription, false);
        }
    },

    runSearchForm: function(sel_version) {
        var wndAssign = this;
        var search_field = Ext.getCmp('search-form');
        if (!empty(search_field)) {
            wndAssign.tree.getSelectionModel().clearSelections();
            var search_form = search_field.getValue();
            if (!empty(search_form)) {
                // Clear description
                wndAssign.setDescription('');

                // Run search
                wndAssign.body.mask('Searching...');

                // Disable button
                var btnClose = Ext.getCmp('assign-form-cancel-btn');

                if (!empty(btnClose)) btnClose.disable();

                sel_version = sel_version || Ext.getCmp('assign-form-version-combo').getValue();
                Ext.Ajax.request({
                    url: topBaseUrl + '/forms/index/search',

                    params: {
                        search_form: Ext.encode(search_form),
                        version:     Ext.encode(sel_version)
                    },

                    success: function(result) {
                        wndAssign.body.unmask();
                        if (!empty(btnClose)) btnClose.enable();

                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            // Show result
                            wndAssign.setDescription(resultData.search_result);

                            wndAssign.setEventForCheckboxes();
                        } else {
                            // Show error message
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function() {
                        wndAssign.body.unmask();
                        if (!empty(btnClose)) btnClose.enable();
                        Ext.simpleConfirmation.error('Cannot find the form. Please try again later.');
                    }
                });
            } else {
                // Mark 'text field' as invalid
                search_field.markInvalid('Please enter a keyword to search for');
            }
        }
    },


    setEventForCheckboxes: function() {
        var wndAssign = this;

        // Listen for click on Form checkbox
        $('.pform').click(function() {
            //save form in array if checked / or remove
            if (this.checked) {
                if (!wndAssign.selectedForms.has(this.id)) {
                    wndAssign.selectedForms.push(this.id);
                }
            } else if (wndAssign.selectedForms.has(this.id)) {
                wndAssign.selectedForms.remove(this.id);
            }

            //at least one form is selected
            Ext.getCmp('assign-form-submit-btn').setDisabled(wndAssign.selectedForms.length === 0);
        });
    },


    sendRequestAddOrAssignForm: function() {
        var wndAssign = this;

        // Collect info and submit to server
        if (wndAssign.selectedForms.length > 0) {
            // Check if there is assigned form [by version] and family member for this client
            //var selFamilyMemberId = Ext.getCmp('assign-form-fm').getValue();

            wndAssign.getEl().mask('Saving...');

            // Send request
            Ext.Ajax.request({
                    url: topBaseUrl + '/superadmin/forms-default/add/',
                    params:
                    {
                        forms: Ext.encode(wndAssign.selectedForms)
                    },

                    success: function(f) {
                        var resultData = Ext.decode(f.responseText);

                        if (resultData.success) {
                            // Show confirmation
                            wndAssign.getEl().mask('Done');

                            setTimeout(function() {
                                wndAssign.getEl().unmask();

                                // Refresh main list
                                Ext.getCmp('forms-grid').store.reload();

                               // Close this window
                               wndAssign.close();
                            }, 750);
                        } else {
                            // Show error message
                            wndAssign.getEl().unmask();
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },

                    failure: function() {
                        // Some issues with network?
                        Ext.simpleConfirmation.error('Form was not assigned. Please try again later.');
                        wndAssign.getEl().unmask();
                    }
            });
        } else {
            Ext.simpleConfirmation.warning('Please select a pdf form');
        }
    }
});

var DefaultFormsGrid = function() {
    var defaultForm = Ext.data.Record.create([
        {name: 'default_form_id'},
        {name: 'default_form_name'},
        {name: 'default_form_type'},
        {name: 'default_form_updated_by'},
        {name: 'default_form_updated_on', type: 'date', dateFormat: Date.patterns.ISO8601Long}
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/forms-default/list',
        remoteSort: true,
        sortInfo: {field: 'default_form_id', direction: 'ASC'},
        autoLoad: true,

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
            {
                id: 'default_form_id',
                root: 'rows',
                totalProperty: 'totalCount'
            }, defaultForm
        )
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();

    this.bbar = new Ext.PagingToolbar({
        hidden: false,
        pageSize: forms_perpage,
        store: this.store,
        displayInfo: true,
        displayMsg: _('Forms {0} - {1} of {2}'),
        emptyMsg: _('No forms to display')
    });

    this.tbar = [
        {
            text: '<i class="las la-plus"></i>' + _('New Form'),
            tooltip: _('Assign a form to the case'),
            cls: 'main-btn',
            scope: this,
            handler: function () {
                var wndAssign = new FormsAssignDialog(this.member_id);
                wndAssign.show();
                wndAssign.center();
            }
        }, {
            id: 'default-forms-edit',
            text: '<i class="las la-edit"></i>' + _('Edit Form'),
            disabled: true,
            tooltip: _('Edit selected form'),
            scope: this,
            handler: function () {
                this.openForm();
            }
        }, {
            id: 'default-forms-delete',
            text: '<i class="las la-trash"></i>' + _('Delete Form'),
            disabled: true,
            tooltip: _('Delete the selected form'),
            handler: this.deleteForm.createDelegate(this)
        }, '->', {
            text: String.format(
                "<i class='las la-question-circle' ext:qtip='{0}' ext:qwidth='480' style='cursor: help; margin-left: 10px; vertical-align: text-bottom'></i>",
                _('Use this space to add default values to your forms. This data will automatically populate common fields in other forms.<br><br><i>Note: These defaults will not impact existing cases, but will only apply to new cases.</i>')
            )
        }, {
            text: '<i class="las la-undo-alt"></i>',
            scope: this,
            handler: function() {
                this.store.reload();
            }
        }
    ];

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.sm,
            {
                id: 'default-forms-grid-column-filename',
                header: 'Form Name',
                width: 250,
                dataIndex: 'default_form_name'
            }, {
                header: 'Last Update',
                width: 75,
                renderer: Ext.util.Format.dateRenderer('M d, Y'),
                dataIndex: 'default_form_updated_on'
            }, {
                header: 'Updated By',
                width: 110,
                dataIndex: 'default_form_updated_by'
            }
        ],
        defaultSortable: true
    });


    DefaultFormsGrid.superclass.constructor.call(this, {
        height: getSuperadminPanelHeight(),
        loadMask: true,
        cls: 'extjs-grid',
        id: 'forms-grid',
        renderTo: 'forms_default_container',

        autoExpandColumn: 'default-forms-grid-column-filename',
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText: 'No forms found.',
            forceFit: true
        }
    });

    this.on('rowcontextmenu', this.showContextMenu, this);
    this.on('rowdblclick', this.openForm.createDelegate(this));
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons.createDelegate(this));
};

Ext.extend(DefaultFormsGrid, Ext.grid.GridPanel, {
    openForm: function(record) {
        record = (record && record.data) ? record : this.getSelectionModel().getSelected();

        if (empty(record)) {
            Ext.simpleConfirmation.warning('Please select a form');
            return;
        }

        switch (record.data['default_form_type']) {
            case 'bar':
                Ext.simpleConfirmation.error('You cannot save default values in barcoded forms.');
                break;

            case 'xod':
                window.open(topBaseUrl + '/xod/index.html?p=' + record.data['default_form_id'] + '&df=1');
                break;

            default:
                var open_pdf_url = String.format(
                    '{0}/forms-default/open-pdf?formId={1}#FDF={0}/forms-default/open-xfdf?formId={1}',
                    baseUrl,
                    record.data['default_form_id']
                );

                window.open(open_pdf_url);
                break;
        }
    },

    deleteForm: function() {
        var grid=this;

        Ext.Msg.confirm('Please confirm', 'Are you sure want to delete this form(s)?', function(btn, text)
        {
            if (btn === 'yes')
            {
                grid.getEl().mask('Deleting...');

                var ids = [];
                for (var i=0; i<grid.getSelectionModel().getSelections().length; i++)
                    ids.push(grid.getSelectionModel().getSelections()[i].data.default_form_id);

                Ext.Ajax.request({
                    url: baseUrl+'/forms-default/delete',
                    params:
                    {
                        ids: Ext.encode(ids)
                    },
                    success: function(result)
                    {
                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            grid.store.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                        grid.getEl().unmask();
                    },
                    failure: function(form)
                    {
                        grid.getEl().unmask();
                        Ext.simpleConfirmation.error('Cannot delete form(s). Please try again later.');
                    }
                });
            }
        });
    },
    
    updateToolbarButtons: function() {
        var sel = this.view.grid.getSelectionModel().getSelections();

        Ext.getCmp('default-forms-edit').setDisabled(sel.length!=1);
        Ext.getCmp('default-forms-delete').setDisabled(sel.length==0);
    },

    showContextMenu: function(grid, rowIndex, e) {
        var record = grid.getStore().getAt(rowIndex);
        this.menu = null;
        this.menu = new Ext.menu.Menu({
            items: [{
                text: '<i class="las la-edit"></i>' + _('Edit'),
                scope: this,
                disabled: record.data['default_form_type'] === 'bar',
                handler: grid.openForm.createDelegate(grid)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete'),
                scope: this,
                handler: grid.deleteForm.createDelegate(grid)
            }, {
                text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                scope: this,
                handler: function() {
                    this.store.reload();
                }
            }]
        });

        //Mark row as selected
        grid.getView().grid.getSelectionModel().selectRow(rowIndex);

        // Show menu
        e.stopEvent();
        this.menu.showAt(e.getXY());
    }
});