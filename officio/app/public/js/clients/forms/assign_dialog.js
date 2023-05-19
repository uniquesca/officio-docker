var FormsAssignDialog = function (member_id) {
    var wndAssign = this;

    this.member_id = member_id;
    this.selectedForms = [];

    wndAssign.tree = new Ext.tree.TreePanel({
        title: _('Categorical Search'),
        useArrows: true,
        animate: true,
        autoScroll: true,
        containerScroll: true,
        rootVisible: false,


        root: new Ext.tree.AsyncTreeNode({
            text: '<i class="las la-folder"></i>' + _('Folders'),
            singleClickExpand: true,
            id: 'source'
        }),

        loader: new Ext.tree.TreeLoader({
            dataUrl: baseUrl + '/forms/forms-folders/list',
            baseParams: {
                member_id: member_id,
                version: Ext.encode('latest')
            },

            preloadChildren: true,

            listeners: {
                beforeload: {
                    buffer: 100,
                    fn: function () {
                        wndAssign.getEl().mask(_('Loading...'));
                    }
                },

                load: function () {
                    if (wndAssign.tree.getRootNode().firstChild !== null) {
                        wndAssign.tree.expandAll();
                        wndAssign.tree.getRootNode().firstChild.select();
                    }

                    wndAssign.getEl().unmask();
                },

                loadexception: function () {
                    wndAssign.getEl().unmask();
                }
            }
        }),
        width: 350,
        height: 305,
        style: 'border: 1px solid #99BBE8; border-top: none;'
    });

    // add a tree sorter in folder mode
    new Ext.tree.TreeSorter(wndAssign.tree, {folderSort: true});

    wndAssign.tree.getSelectionModel().on('beforeselect', function (sm, node) {
        var newDescription = '';

        // Load description for selected pdf form
        if (node.isLeaf()) {
            newDescription = node.attributes.description;
        }

        // Show new description
        wndAssign.setDescription(newDescription);

        //check all form that have been selected before
        $('.pform').each(function () {
            if (wndAssign.selectedForms.has(this.id)) {
                $('#' + this.id).prop('checked', true);
            }
        });

        wndAssign.setEventForCheckboxes();

        return true;
    });

    this.FamilyMembersStore = new Ext.data.Store({
        url: baseUrl + '/forms/index/get-family-members',
        baseParams: {member_id: member_id},

        listeners: {
            'load': function (store, records) {
                var applicantCombo = Ext.getCmp('assign-form-fm');
                if (!empty(applicantCombo) && records.length) {
                    applicantCombo.setValue(records[0]['data'][applicantCombo.valueField]);
                }
            }
        },

        reader: new Ext.data.JsonReader(
            {
                id: 'id',
                root: 'rows',
                totalProperty: 'totalCount'
            }, [
                {name: 'id'},
                {name: 'value'},
                {name: 'lName'},
                {name: 'fName'},
                {name: 'order'}
            ])
    });

    var pan = new Ext.Panel({
        layout: 'form',
        autoWidth: true,
        items: [
            {
                layout: 'table',
                layoutConfig: {
                    columns: 3
                },
                items: [
                    {
                        layout: 'form',
                        labelWidth: 290,
                        items: [
                            {
                                id: 'assign-form-fm',
                                xtype: 'combo',
                                width: 250,
                                fieldLabel: _('Select the family member the form(s) are for'),
                                labelStyle: 'padding-top: 13px; width: auto;',
                                store: this.FamilyMembersStore,
                                displayField: 'value',
                                valueField: 'id',
                                mode: 'local',
                                lazyInit: false,
                                typeAhead: false,
                                editable: false,
                                forceSelection: true,
                                triggerAction: 'all',
                                allowBlank: false,
                                selectOnFocus: true,
                                listeners: {
                                    select: function (c, r) {
                                        var booIsOther = r.data.id == 'other1';

                                        Ext.getCmp('assign-form-other').setVisible(booIsOther);
                                        Ext.getCmp('assign-form-label').setVisible(!booIsOther);
                                    }
                                }
                            }
                        ]
                    },
                    {
                        id: 'assign-form-label',
                        xtype: 'displayfield',
                        style: 'padding:0 0 7px 7px;',
                        value: '<a href="#" class="bluelink" onclick="return false;">' + _("Can't find a family member in this list?") + '</a>'
                    },
                    {
                        id: 'assign-form-other',
                        xtype: 'textfield',
                        width: 314,
                        hidden: true,
                        style: 'margin: 0 0 16px 5px;',
                        emptyText: _("Other's descriptions")
                    }
                ]
            },
            {
                layout: 'table',
                id: 'forms_table',
                layoutConfig: {
                    columns: 2
                },
                items: [
                    wndAssign.tree,
                    {
                        title: _('Select form(s) to add:'),
                        style: 'margin:0px 5px; border:1px solid #99BBE8; border-top:none; background:#DFE8F6;',
                        html: '<div id="assign-form-description" class="form-description" style="padding:5px; height:268px; overflow:auto;"></div>',
                        width: 550,
                        height: 305
                    }, {
                        xtype: 'form',
                        labelWidth: 115,
                        height: 55,
                        style: 'padding:7px 0px 0px 6px; border:1px solid #99BBE8; border-top:none;',
                        items: [
                            {
                                id: 'search-form',
                                xtype: 'textfield',
                                fieldLabel: _('Keyword Search'),
                                labelStyle: 'background:url(' + baseUrl + '/images/orange-arrow.gif) no-repeat; padding-left:10px; padding-top: 13px; background-position:0 66%; width: 115px;',
                                emptyText: site_version == 'australia' ? _('eg. 457, 80, etc...') : _('eg. 5406, 1344, etc...'),
                                width: 185,
                                enableKeyEvents: true,
                                listeners: {
                                    keyup: {
                                        buffer: 400, // We need this delay - to prevent too quick submission
                                        fn: function (field, e) {
                                            wndAssign.runSearchForm();
                                        }
                                    }
                                }
                            },
                            {
                                xtype: 'button',
                                cls: 'search-button',
                                icon: baseUrl + '/images/icons/find.png',
                                handler: function () {
                                }
                            }
                        ]
                    },

                    {
                        xtype: 'form',
                        width: 550,
                        align: 'right',
                        style: 'padding:7px 0 5px 6px; margin:0px 5px; border:1px solid #99BBE8; border-top:none;',
                        layout: 'column',

                        items: [
                            {
                                id: 'assign-form-version-combo',
                                xtype: 'combo',
                                hideLabel: true,
                                store: new Ext.data.SimpleStore({
                                    fields: ['show_id', 'show_name'],
                                    data: [
                                        ['all', _('Show All versions of forms')],
                                        ['latest', _('Show only the LATEST version of forms')]
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
                                width: 335,

                                listeners: {
                                    'beforeselect': function (combo, rec) {
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
                            }, {
                                xtype: 'container',
                                layout: 'column',
                                style: 'padding: 10px; float: right;',
                                width: 200,

                                items: [
                                    {
                                        xtype: 'box',
                                        hidden: site_version != 'australia',
                                        autoEl: {
                                            tag: 'a',
                                            target: '_blank',
                                            'class': 'blulinkunb',
                                            style: 'padding: 2px 3px; float: left; outline: none;',
                                            html: _('CC'),
                                            title: _('Creative Commons'),
                                            href: 'https://creativecommons.org/licenses/by/3.0/au/legalcode'
                                        }
                                    }, {
                                        xtype: 'box',
                                        hidden: site_version != 'australia',
                                        autoEl: {
                                            tag: 'span',
                                            style: 'float: left',
                                            html: ' - '
                                        }
                                    }, {
                                        xtype: 'box',
                                        hidden: site_version != 'australia',
                                        autoEl: {
                                            tag: 'a',
                                            target: '_blank',
                                            'class': 'blulinkunb',
                                            style: 'padding: 2px 3px; float: left; outline: none; margin-right: 15px;',
                                            html: _('DIBP'),
                                            title: _('Department of Immigration and Border Protection'),
                                            href: 'https://www.border.gov.au/about/corporate/information/forms/pdf-numerical'
                                        }
                                    }, {
                                        html: String.format(
                                            '<img src="{0}/images/icons/help.png" align="middle" alt="" />',
                                            topBaseUrl
                                        )
                                    }, {
                                        xtype: 'box',
                                        autoEl: {
                                            tag: 'a',
                                            href: '#',
                                            'class': 'blulinkunb',
                                            style: 'padding: 2px 3px; float: left;',
                                            title: _('Click to open the disclaimer dialog'),
                                            html: _('Disclaimer')
                                        },
                                        listeners: {
                                            scope: this,
                                            render: function (c) {
                                                c.getEl().on('click', wndAssign.showDisclaimerDialog.createDelegate(wndAssign), this, {stopEvent: true});
                                            }
                                        }
                                    }
                                ]
                            }
                        ]
                    }
                ]
            }
        ]
    });

    this.buttons = [
        {
            id: 'assign-form-cancel-btn',
            text: _('Cancel'),
            animEl: 'forms-btn-add',
            scope: this,
            handler: function () {
                this.close();
            }
        }, {
            id: 'assign-form-submit-btn',
            animEl: 'forms-btn-add',
            cls: 'orange-btn',
            disabled: true,
            text: '<i class="las la-plus"></i>' + _('New form'),
            handler: this.sendRequestAddOrAssignForm.createDelegate(this)
        }
    ];


    this.helpToolTip = new Ext.ToolTip({
        html: _('If you cannot find the name of the spouse or other dependants in this list, please go to Case Details tab and introduce these family members under the Dependants section.'),
        title: _("Can't find a family member in this list?"),
        autoHide: false,
        closable: true,
        anchor: 'top',
        anchorOffset: 80
    });

    FormsAssignDialog.superclass.constructor.call(this, {
        title: '<i class="las la-plus"></i>' + _('New Form'),
        width: 930,
        height: 540,

        plain: false,
        modal: true,
        resizable: false,

        defaults: {
            // applied to each contained panel
            bodyStyle: 'vertical-align: top;'
        },

        items: pan
    });

    this.on('beforeshow', this.loadFamilyMembersStore, this);
    this.on('show', this.createToolTip.createDelegate(this));
    this.on('close', this.closeToolTip.createDelegate(this));
};


Ext.extend(FormsAssignDialog, Ext.Window, {
    showDisclaimerDialog: function () {
        var text = _('We endeavour to bring the forms used in Officio up-to-date as quickly as possible; ' +
            'however, we are not the original author of these forms. ' +
            'The user is responsible for checking that the form is the current version of the official form.<br/><br/>' +
            'Furthermore, automatic completion of forms in Officio is based on the information inputted by the user. ' +
            'The user is responsible for inputting information accurately into Officio and reviewing the content of each form prior to submission.');

        Ext.Msg.show({
            title: 'Disclaimer',
            msg: text,
            minWidth: 525,
            modal: true,
            buttons: false,
            icon: Ext.Msg.INFO
        });
    },

    loadFamilyMembersStore: function () {
        this.FamilyMembersStore.load();
    },

    createToolTip: function () {
        var wnd = this;
        Ext.get('assign-form-label').on('click', function () {
            wnd.helpToolTip.showBy(Ext.getCmp('assign-form-label').id);
        });
    },

    closeToolTip: function () {
        this.helpToolTip.hide();
    },

    setDescription: function (newDescription) {
        var descriptionContainer = Ext.get('assign-form-description');
        if (descriptionContainer) {
            Ext.DomHelper.overwrite(descriptionContainer, newDescription, false);
        }
    },

    runSearchForm: function (sel_version) {
        var wndAssign = this;
        var search_field = Ext.getCmp('search-form');
        var addFormPanel = Ext.get('assign-form-description');
        if (!empty(search_field)) {
            wndAssign.tree.getSelectionModel().clearSelections();
            var search_form = search_field.getValue().trim();
            if (!empty(search_form)) {
                // Clear description
                wndAssign.setDescription('');

                // Run search
                addFormPanel.mask(_('Searching...'));

                // Disable button
                var btnClose = Ext.getCmp('assign-form-cancel-btn');

                if (!empty(btnClose)) btnClose.disable();

                sel_version = sel_version || Ext.getCmp('assign-form-version-combo').getValue();
                Ext.Ajax.request({
                    url: baseUrl + '/forms/index/search',

                    params: {
                        member_id: wndAssign.member_id,
                        search_form: Ext.encode(search_form),
                        version: Ext.encode(sel_version)
                    },

                    success: function (result) {
                        addFormPanel.unmask();
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

                    failure: function () {
                        addFormPanel.unmask();
                        if (!empty(btnClose)) btnClose.enable();
                        Ext.simpleConfirmation.error(_('Cannot find the form. Please try again later.'));
                    }
                });
            } else {
                wndAssign.setDescription('');
                // Mark 'text field' as invalid
                search_field.markInvalid(_('Please enter a keyword to search for'));
            }
        }
    },


    setEventForCheckboxes: function () {
        var wndAssign = this;

        // Listen for click on Form checkbox
        $('.pform').click(function () {
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


    sendRequestAddOrAssignForm: function () {
        var wndAssign = this;

        // Collect info and submit to server
        if (wndAssign.selectedForms.length > 0) {
            // Check if there is assigned form [by version] and family member for this client
            var selFamilyMemberId = Ext.getCmp('assign-form-fm').getValue();

            wndAssign.getEl().mask(_('Saving...'));

            // Send request
            Ext.Ajax.request({
                url: baseUrl + '/forms/index/assign',
                params:
                    {
                        member_id: wndAssign.member_id,
                        forms: Ext.encode(wndAssign.selectedForms),
                        family_member_id: Ext.encode(selFamilyMemberId),
                        other: Ext.encode(Ext.getCmp('assign-form-other').getValue())
                    },

                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        // Show confirmation
                        wndAssign.getEl().mask(_('Done!'));

                        setTimeout(function () {
                            wndAssign.getEl().unmask();

                            // Refresh main list
                            Ext.getCmp('forms-main-grid' + wndAssign.member_id).store.reload();

                            // Close this window
                            wndAssign.close();
                        }, 750);
                    } else {
                        // Show error message
                        wndAssign.getEl().unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function () {
                    // Some issues with network?
                    Ext.simpleConfirmation.error(_('Form was not assigned. Please try again later.'));
                    wndAssign.getEl().unmask();
                }
            });
        } else {
            Ext.simpleConfirmation.warning(_('Please select a pdf form'));
        }
    }
});