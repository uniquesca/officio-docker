var __booEditFormForSafari = Ext.isMac;
var __booMergeDataOnServer = false;

var editFormAlias = function (member_id, assignedPdfFormId) {
    var grid = Ext.getCmp('forms-main-grid' + member_id);
    if (grid) {
        grid.editFormAlias(assignedPdfFormId);
    }
};

var FormsGrid = function (member_id, booStoreAutoLoad, panelType) {
    this.member_id = member_id;
    this.panelType = panelType;
    this.uploadColumnId = Ext.id();

    // By default, access is locked
    // real access we'll load during forms list loading
    var oLocked = this.getLockDetails(true);
    var toolbarBtnLockTitle = oLocked.title;
    var toolbarBtnLockTooltip = oLocked.tooltip;


    // Check if we need hide any button in toolbar
    var arrShowToolbarOptions = [];
    if (!empty(arrFormShowToolbarOptions)) {
        arrShowToolbarOptions = arrFormShowToolbarOptions;
    }

    // Private access rights
    this.access = {
        booHiddenFormsAssign: !arrShowToolbarOptions.has('booHiddenFormsAssign'),
        booHiddenFormsEdit: !arrShowToolbarOptions.has('booHiddenFormsNew'),
        booHiddenFormsDelete: !arrShowToolbarOptions.has('booHiddenFormsDelete'),
        booHiddenFormsComplete: !arrShowToolbarOptions.has('booHiddenFormsComplete'),
        booHiddenFormsQuestionnaire: !arrShowToolbarOptions.has('booShowFormsQuestionnaire'),
        booHiddenFormsFinalize: !arrShowToolbarOptions.has('booHiddenFormsFinalize'),
        booHiddenFormsLockUnlock: !arrShowToolbarOptions.has('booHiddenFormsLockUnlock'),
        booHiddenFormsEmail: is_client,
        booDisableFormsComplete: false,
        booDisableFormsQuestionnaire: false
    };


    var assignedForm = Ext.data.Record.create([
        {name: 'locked', type: 'int'},
        {name: 'client_form_id', type: 'int'},
        'client_form_type',
        'client_form_version_latest',
        'client_form_format',
        'client_form_pdf_exists',
        {name: 'client_form_annotations', type: 'int'},
        {name: 'client_form_help_article_id', type: 'int'},
        {name: 'client_form_version_id', type: 'int'},
        {name: 'family_member_id'},
        {name: 'family_member_type'},
        {name: 'family_member_alias'},
        {name: 'family_member_lname'},
        {name: 'family_member_fname'},
        {name: 'file_name'},
        {name: 'file_name_stripped'},
        {name: 'date_assigned', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'date_completed', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'date_finalized', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'updated_by'},
        {name: 'updated_on', type: 'date', dateFormat: Date.patterns.ISO8601Long},
        {name: 'file_size'},
        {name: 'use_revision'},
        {name: 'arr_revisions'}
    ]);

    this.store = new Ext.data.Store({
        // load using HTTP
        url: baseUrl + '/forms/index/list',
        baseParams: {member_id: member_id},
        autoLoad: booStoreAutoLoad ? true : false,
        remoteSort: true,
        sortInfo: {field: 'family_member_type', direction: 'ASC'},

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader(
            {
                id: 'client_form_id',
                root: 'rows',
                totalProperty: 'totalCount'
            }, assignedForm
        )
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.sm,
            {
                header: _('Family Member'),
                width: 140,
                dataIndex: 'family_member_type',
                renderer: this.rendererCustomName.createDelegate(this)
            }, {
                id: 'forms-grid-column-filename' + member_id,
                header: _('Form Name'),
                width: 250,
                dataIndex: 'file_name',
                renderer: function (val, p, record) {
                    val = String.format(
                        '{0} {1}',
                        record.data.client_form_type === 'bar' ? '<i class="las la-barcode" title="A barcoded form"></i>' : '',
                        val
                    );

                    return val.trim();
                }
            }, {
                id: this.uploadColumnId,
                header: _('Upload'),
                width: 55,
                sortable: false,
                dataIndex: 'arr_revisions',
                scope: 'this',
                renderer: this.rendererUploadBtn.createDelegate(this),
                hidden: false // show by default, will be hidden for clients with locaked access (after the list of forms will be laoded)
            }, {
                header: _('Last Completed'),
                width: 75,
                renderer: Ext.util.Format.dateRenderer('M d, Y'),
                dataIndex: 'date_completed',
                hidden: this.access.booHiddenFormsComplete
            }, {
                header: site_version == 'australia' ? _('Finalised') : _('Finalized'),
                width: 75,
                renderer: Ext.util.Format.dateRenderer('M d, Y'),
                dataIndex: 'date_finalized'
            }, {
                header: _('Last Update'),
                width: 75,
                renderer: Ext.util.Format.dateRenderer('M d, Y'),
                dataIndex: 'updated_on'
            }, {
                header: _('Updated By'),
                width: 110,
                dataIndex: 'updated_by'
            }, {
                header: _('File Size'),
                width: 60,
                hidden: true,
                sortable: false,
                dataIndex: 'file_size'
            }
        ],
        defaultSortable: true
    });

    this.tbar = [
        {
            id: 'forms-btn-assign' + member_id,
            text: '<i class="las la-plus"></i>' + _('New Form'),
            cls: 'main-btn',
            tooltip: 'Assign a form to the case.',
            hidden: this.access.booHiddenFormsAssign,
            scope: this,
            handler: function () {
                var wndAssign = new FormsAssignDialog(this.member_id);
                wndAssign.show();
                wndAssign.center();
            }
        }, {
            id: 'forms-btn-edit' + member_id,
            text: '<i class="las la-pencil-alt"></i>' + _('Edit Form'),
            hidden: this.access.booHiddenFormsEdit,
            tooltip: 'Edit selected form.',
            scope: this,
            handler: function () {
                var arrSelected = this.getSelectionModel().getSelections();
                if (arrSelected.length !== 1) {
                    var strMsg;
                    if (arrSelected.length === 0) {
                        strMsg = _('Please select a form to edit.');
                    } else {
                        strMsg = _('Please select only one form to edit.');
                    }
                    Ext.simpleConfirmation.msg(_('Info'), strMsg);
                } else {
                    for (var i = 0; i < arrSelected.length; i++) {
                        this.showEditForm(arrSelected[i], arrSelected[i].data.client_form_format);
                    }
                }
            }
        }, {
            id: 'forms-btn-delete' + member_id,
            text: '<i class="las la-trash"></i>' + _('Delete Form'),
            hidden: this.access.booHiddenFormsDelete,
            tooltip: _('Delete selected form.'),
            handler: this.deleteForm.createDelegate(this)
        }, {
            id: 'forms-btn-print' + member_id,
            text: '<i class="las la-print"></i>' + _('Print'),
            tooltip: 'Print selected form.',
            handler: this.printForm.createDelegate(this)
        }, {
            id: 'forms-btn-email' + member_id,
            text: '<i class="lar la-envelope"></i>' + _('Email'),
            hidden: this.access.booHiddenFormsEmail || !allowedPages.has('email'),
            tooltip: 'Email selected form.',
            handler: this.sendEmail.createDelegate(this)
        }, '-', {
            text: '<i class="las la-anchor"></i>' + (site_version == 'australia' ? _('Finalise for Submission') : _('Finalize for Submission')),
            tooltip: site_version == 'australia' ? 'Finalise selected form for submission.' : 'Finalize selected form for submission.',
            id: 'forms-btn-finalise-submission' + member_id,
            hidden: this.access.booHiddenFormsFinalize,
            handler: this.finalizeForm.createDelegate(this, ['finalize', true])
        }, {
            id: 'forms-btn-lock' + member_id,
            text: toolbarBtnLockTitle,
            tooltip: toolbarBtnLockTooltip,
            hidden: this.access.booHiddenFormsLockUnlock,
            handler: this.sendRequest.createDelegate(this, ['lock', false, false])
        }, {
            text: '<i class="las la-check"></i>' + _('Complete'),
            id: 'forms-btn-complete' + member_id,
            tooltip: _('Mark selected form as complete.'),
            disabled: this.access.booDisableFormsComplete,
            hidden: this.access.booHiddenFormsComplete,
            handler: this.sendRequest.createDelegate(this, ['complete', true])
        }, {
            text: '<i class="lab la-wpforms"></i>' + _("Client's Questionnaire"),
            hidden: true, // @Note: uncomment when will be ready
            handler: this.openClientQuestionnaire.createDelegate(this, [member_id])
        }, '->', {
            text: String.format('<i class="las la-undo-alt" title="{0}"></i>', _('Refresh the screen.')),
            scope: this,
            handler: function () {
                this.store.reload();
            }
        }, {
            xtype: 'button',
            text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
            hidden: !allowedPages.has('help'),
            handler: function () {
                showHelpContextMenu(this.getEl(), 'clients-forms');
            }
        }
    ];
    if (site_version == 'australia') {
        this.tbar.splice(9, 0, {
            text: '<i class="las la-plus"></i>' + _('Add Questionnaire'),
            id: 'forms-btn-questionnaire' + member_id,
            tooltip: 'Add a new questionnaire',
            disabled: this.access.booDisableFormsQuestionnaire,
            hidden: this.access.booHiddenFormsQuestionnaire,
            handler: this.addQuestionnaire.createDelegate(this)
        });
    }

    this.bbar = new Ext.PagingToolbar({
        pageSize: 100,
        store: this.store,
        displayInfo: true,
        displayMsg: _('Displaying forms {0} - {1} of {2}'),
        emptyMsg: _('No forms to display')
    });

    FormsGrid.superclass.constructor.call(this, {
        id: 'forms-main-grid' + member_id,
        stateId: 'forms-main-grid',
        height: initPanelSize() - 20,
        loadMask: true,
        cls: 'extjs-grid',
        stripeRows: true,

        autoExpandColumn: 'forms-grid-column-filename' + member_id,
        autoExpandMin: 250,

        viewConfig: {
            deferEmptyText: false,
            scrollOffset: 2, // hide scroll bar "column" in Grid
            emptyText: _('No forms found.'),
            forceFit: true
        }
    });

    this.store.on('load', this.onStoreLoad.createDelegate(this), this);
    this.on('rowcontextmenu', this.showContextMenu, this);
    this.on('render', this.updateGridHeight.createDelegate(this));
    this.on('rowdblclick', function (grid, rowIndex) {
        grid.showEditForm(grid.getStore().getAt(rowIndex), grid.getStore().getAt(rowIndex).data.client_form_format);
    });

    // Load previously received data
    if (typeof arrForms != 'undefined' && !empty(arrForms)) {
        this.store.loadData(arrForms);
    }
};

Ext.extend(FormsGrid, Ext.grid.GridPanel, {
    onStoreLoad: function (store) {
        var thisGrid = this;
        var booLocked = false;
        if (store.reader.jsonData && typeof store.reader.jsonData.booLocked != 'undefined') {
            booLocked = store.reader.jsonData.booLocked;
        }
        this.updateLockBtn(booLocked);

        // Hide the 'Upload' column for client with locked access
        if (booLocked && is_client) {
            var uploadColumnIndex = thisGrid.getColumnModel().getIndexById(thisGrid.uploadColumnId);
            thisGrid.getColumnModel().setHidden(uploadColumnIndex, true);
        }

        var hasOfficioForm = false;
        if (store.reader.jsonData && typeof store.reader.jsonData.hasOfficioForm != 'undefined') {
            hasOfficioForm = store.reader.jsonData.hasOfficioForm;
        }
        this.updateAddQnrBtn(hasOfficioForm);

        this.updateGridHeight();
    },

    updateGridHeight: function () {
        this.doLayout();
    },

    // Custom family member's name
    rendererCustomName: function (val, p, record) {
        var grid = this;
        var strName;

        if (record.data.family_member_type === 'Other') {
            var nameAlias = record.data.family_member_alias;
            var prefix = '';
            var linkTitle = '';
            if (empty(nameAlias)) {
                linkTitle = record.data.family_member_type;
            } else {
                prefix = record.data.family_member_type + ': ';
                linkTitle = nameAlias;
            }
            var suffix = is_client ? linkTitle : '<a href="#" title="' + _('Update label for Other') + ' onclick="editFormAlias(' + grid.member_id + ', ' + record.data.client_form_id + '); return false;">' + linkTitle + '</a>';

            strName = prefix + suffix;
        } else {
            strName = record.data.family_member_type;
            if (!empty(record.data.family_member_lname) && !empty(record.data.family_member_fname)) {
                strName += ': <span style="color:#666666;">' + record.data.family_member_fname + '</span>';
            }
        }
        return strName;
    },

    rendererUploadBtn: function (val, p, record) {
        var grid = this;
        var strResult = '';

        // Generate the Upload button only for:
        // - barcoded forms
        // - for all users or for clients with unlocked forms
        var booIsLocked = is_client ? !empty(record.data.locked) : false;
        if (record.data.use_revision === 'Y' && !booIsLocked) {
            var id = Ext.id();
            var btn = new Ext.Button({
                text: '<span style="font-weight: bold; color: #0E457A;">' + _('Upload') + '</span>',
                tooltip: {
                    width: 200,
                    text: _('Use this button to Upload your latest changes to the barcoded form.')
                },
                scope: this,
                handler: function () {
                    var wndUpload = new FormsUploadDialog(record, grid);
                    wndUpload.show();
                    wndUpload.center();
                }
            });
            btn.render.defer(1, btn, [id]);
            strResult = '<div  id=' + id + '></div>';
        }

        return strResult;
    },


    showContextMenu: function (g, rowIndex, e) {
        var grid = this;
        var r = grid.getStore().getAt(rowIndex);

        // Generate revisions menu
        var arrRevisions = [];
        var booUseRevisions = false;
        var booThereAreRevisions = false;

        if (r && r.data.use_revision && r.data.use_revision === 'Y') {
            booUseRevisions = true;

            // Load revisions list
            if (r.data.arr_revisions && r.data.arr_revisions.length) {
                booThereAreRevisions = true;
                for (var i = 0; i < r.data.arr_revisions.length; i++) {
                    var revision = r.data.arr_revisions[i];
                    arrRevisions.push({
                        text: '<i class="las la-save"></i>' + _(revision.name),
                        rev_id: revision.id,
                        handler: function (rec) {
                            grid.downloadRevision(r.data.client_form_id, rec.rev_id, r.data.file_name_stripped);
                        }
                    });
                }
            } else {
                arrRevisions.push({
                    text: _('There are no revisions'),
                    disabled: true
                });
            }
        }

        this.menu = new Ext.menu.Menu({
            items: [
                {
                    text: '<i class="las la-edit"></i>' + _('Edit in new window'),
                    scope: this,
                    hidden: booUseRevisions,
                    handler: function () {
                        grid.showEditForm(r, r.data.client_form_format);
                    }
                }, {
                    text: '<i class="las la-file-pdf"></i>' + _('Open in PDF'),
                    scope: this,
                    hidden: !r.data.client_form_pdf_exists || r.data.client_form_format === 'pdf',
                    handler: function () {
                        grid.showEditForm(r, 'pdf');
                    }
                }, {
                    text: '<i class="las la-trash"></i>' + _('Delete'),
                    hidden: grid.access.booHiddenFormsDelete,
                    scope: this,
                    handler: grid.deleteForm
                }, {
                    text: '<i class="las la-paste"></i>' + _('Revisions'),
                    hidden: !booUseRevisions,
                    menu: arrRevisions
                }, {
                    text: booUseRevisions && booThereAreRevisions ? '<i class="lar la-save"></i>' + _('Download latest revision') : '<i class="lar la-save"></i>' + _('Download form'),
                    hidden: !booUseRevisions && r.data.client_form_type === 'bar',
                    scope: this,
                    handler: function () {
                        if (r) {
                            if (booUseRevisions) {
                                this.downloadRevision(r.data.client_form_id, 0, r.data.file_name_stripped);
                            } else if (r.data.client_form_type === 'bar') {
                                Ext.simpleConfirmation.warning(_('Barcoded forms are not supported.'));
                            } else {
                                this.downloadForm(r.data.client_form_id, r.data.file_name_stripped);
                            }
                        }
                    }
                }, '-', {
                    text: _('Merge data on server'),
                    checked: __booMergeDataOnServer,
                    scope: this,
                    checkHandler: function () {
                        __booMergeDataOnServer = !__booMergeDataOnServer;
                    }
                }, {
                    text: _('Mac user (with Safari)'),
                    checked: __booEditFormForSafari,
                    scope: this,
                    hidden: false,
                    hideOnClick: false,
                    checkHandler: function () {
                        __booEditFormForSafari = !__booEditFormForSafari;
                    }
                }, {
                    text: '<i class="las la-undo-alt"></i>' + _('Refresh'),
                    scope: this,
                    handler: function () {
                        this.store.reload();
                    }
                }]
        });

        if (r && r.data && r.data.client_form_type === 'officio-form') {
            this.menu.addItem({
                text: '<i class="las la-edit"></i>' + _('Edit settings'),
                scope: this,
                handler: grid.editSettings.createDelegate(this, [r.data.client_form_id])
            });
        }

        //Mark row as selected
        grid.getView().grid.getSelectionModel().selectRow(rowIndex);

        // Show menu
        e.stopEvent();

        if (this.ownerCt.ownerCt.ownerCt.booCanEdit) {
            this.menu.showAt(e.getXY());
        }
    },


    getSelectedFormIds: function () {
        var grid = this;
        var arrSelectedFormIds = [];
        var s = grid.getSelectionModel().getSelections();
        if (s.length > 0) {
            for (var i = 0; i < s.length; i++) {
                arrSelectedFormIds.push(s[i].data.client_form_id);
            }
        }

        return arrSelectedFormIds;
    },

    getLockDetails: function (booLocked) {
        var oLock;
        if (booLocked) {
            oLock = {
                title: '<i class="las la-lock"></i>' + _("Client's access is locked. Click to unlock."),
                tooltip: _('Click to allow client to edit forms.')
            };
        } else {
            oLock = {
                title: '<i class="las la-key"></i>' + _("Client's access is enabled. Click to lock."),
                tooltip: _('Click to prevent client from editing forms.')
            };
        }
        return oLock;
    },


    updateLockBtn: function (thisClientLocked) {
        var grid = this;
        var btnId = 'forms-btn-lock' + grid.member_id;
        var btn = Ext.getCmp(btnId);
        if (btn) {
            var oLocked = grid.getLockDetails(thisClientLocked);

            // Update tooltip
            var btnEl = btn.getEl().child(btn.buttonSelector);
            btnEl.dom[btn.tooltipType] = oLocked.tooltip;

            // Update title and icon
            btn.setText(oLocked.title);
        }
    },

    updateAddQnrBtn: function (hasOfficioForm) {
        var grid = this;
        var btnId = 'forms-btn-questionnaire' + grid.member_id;
        var btn = Ext.getCmp(btnId);
        if (btn && hasOfficioForm) {
            btn.hide();
        } else if (btn) {
            btn.show();
        }
    },

    viewClientQuestionnaire: function () {
        var iframe_win = new Ext.Window({
            title: _('Questionnaire'),
            modal: true,
            resizable: false,
            width: 1000,
            height: 600,
            items: [
                {
                    html: '<iframe width="975" height="575" style="border:0;" src="https://secure.officio.ca/qnr?id=16&hash=aa16dde1dcc969ded667172ba4297948">'
                }
            ]
        });

        iframe_win.show();
    },

    openClientQuestionnaire: function (member_id) {
        var show_popup_checkbox = new Ext.form.Checkbox({
            hideLabel: true,
            boxLabel: _('When a client login, show the questionnaire in a pop up dialog.')
        });

        var arrQuestionnaires = [
            {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: _("No Questionnaire"),
                checked: true
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Employer Nomination Questionnaire <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Partner Visa Questionnaire <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Permanent Residence Visa Questionnaire <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Standard Business Sponsorship Information <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Student Visa Questionnaire <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }, {
                xtype: 'radio',
                hideLabel: true,
                name: 'questionnaire_type',
                boxLabel: "Temporary Visa Questionnaire <A href='#' class='bluelink2' target='_blank' onclick='var fg=Ext.getCmp(\"forms-main-grid" + member_id + "\"); fg.viewClientQuestionnaire(); return false;'>(view)</A>"
            }
        ];

        var win = new Ext.Window({
            title: _("Manage Client's Questionnaire"),
            modal: true,
            autoHeight: true,
            resizable: false,
            width: 425,
            items: new Ext.FormPanel({
                bodyStyle: 'padding:5px;',
                labelWidth: 90,
                layout: 'form',
                items: [
                    {
                        bodyStyle: 'padding:5px; margin-bottom:15px; font-size:12px;',
                        html: _('Which questionnaire do you wish the client to complete:')
                    }, {
                        xtype: 'container',
                        layout: 'form',
                        style: 'margin-left: 23px;',
                        items: arrQuestionnaires
                    }, {
                        html: '<br>'
                    },
                    show_popup_checkbox
                ]
            }),
            buttons: [
                {
                    text: 'Cancel',
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: 'Save',
                    cls: 'orange-btn',
                    handler: function () {
                        win.close();
                    }
                }
            ]
        });

        win.show();
        win.center();
    },

    sendRequest: function (action, booReloadGrid, booCheckForms, txtFn) {
        var requestUrl = baseUrl + '/forms/index/';
        var loadingMsg = '';
        var failureMsg = '';
        var pleaseSelectMsg = '';
        var confirmationTimeout = 750;
        var booFinalizeReplace = 0;

        var arrSelectedFormIds = this.getSelectedFormIds();
        var strForm = (arrSelectedFormIds.length > 1) ? _('Forms') : _('Form');

        switch (action) {
            case 'delete':
                requestUrl += 'delete';
                loadingMsg = _('Deleting...');
                failureMsg = _('Selected ' + strForm + ' cannot be deleted. Please try again later.');
                pleaseSelectMsg = _('Please select at least one form to delete');
                break;

            case 'complete':
                requestUrl += 'complete';
                loadingMsg = _('Processing...');
                failureMsg = _('Selected ' + strForm + ' cannot be marked as complete. Please try again later.');
                pleaseSelectMsg = _('Please select at least one form to mark it as complete');
                break;

            case 'finalize':
                if (txtFn) {
                    booFinalizeReplace = 1;
                }
                requestUrl += 'finalize';
                loadingMsg = _('Processing...');
                failureMsg = site_version == 'australia' ? _('Selected ' + strForm + ' cannot be marked as finalised. Please try again later.') : _('Selected ' + strForm + ' cannot be marked as finalized. Please try again later.');
                pleaseSelectMsg = site_version == 'australia' ? _('Please select at least one form to mark it as finalised') : _('Please select at least one form to mark it as finalized');
                break;

            case 'lock':
                requestUrl += 'lock-and-unlock';
                loadingMsg = _('Processing...');
                failureMsg = _('Case cannot be locked/unlocked. Please try again later.');
                confirmationTimeout = 2000;
                break;

            default:
                // Incorrect action
                return;
        }

        if (booCheckForms !== false) {
            if (arrSelectedFormIds.length === 0) {
                // Show message that please select ...
                Ext.simpleConfirmation.msg(_('Info'), pleaseSelectMsg);
                return;
            }
        }


        // Send ajax request to make some action with selected forms
        var grid = this;
        grid.getEl().mask(loadingMsg);

        Ext.Ajax.request({
            url: requestUrl,
            params: {
                member_id: grid.member_id,
                arr_form_id: Ext.encode(arrSelectedFormIds),
                booFinalizeReplace: booFinalizeReplace
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                if (resultData.success) {
                    // Refresh forms list
                    if (booReloadGrid) {
                        grid.store.reload();
                    }

                    // Show confirmation and refresh required sections
                    var msg = '';
                    switch (action) {
                        case 'lock':
                            grid.updateLockBtn(resultData.locked);
                            if (resultData.locked) {
                                msg = _('Forms are being locked and this case will not be able to edit the forms.');
                            } else {
                                msg = _('Forms are being unlocked to allow case to edit the forms.');
                            }
                            break;

                        case 'finalize':
                            var docsTree = Ext.getCmp('docs-tree-' + grid.member_id);
                            if (docsTree) {
                                // Refresh client documents list
                                docsTree.getRootNode().reload();
                            }

                            grid.getEl().unmask();
                            var msgInfo = site_version == 'australia' ? _('Your finalised document(s) can be found under Documents/Submissions folder.') : _('Your finalized document(s) can be found under Documents/Submissions folder.');
                            Ext.simpleConfirmation.info(msgInfo);
                            break;

                        default:
                            break;
                    }

                    if (!empty(msg)) {
                        grid.getEl().mask(msg);
                    }

                    // Hide a confirmation for a second
                    setTimeout(function () {
                        grid.getEl().unmask();
                    }, confirmationTimeout);
                } else {
                    // Show error message
                    grid.getEl().unmask();
                    Ext.simpleConfirmation.error(resultData.message);
                }
            },

            failure: function () {
                // Some issues with network?
                Ext.simpleConfirmation.error(failureMsg);
                grid.getEl().unmask();
            }
        });
    },

    showEditForm: function (r, strFormFormat) {
        var thisGrid = this;
        if (r) {
            var pdf_id = r.data.client_form_id;

            var msg = String.format(
                _('Since the last time you worked on this form, a newer version has become available.<br/><br/>') +
                _('Would you like Officio to transfer your data from the old form to the new form?')
            );

            // Check if there is NOT installed Adobe Acrobat PDF plugin
            // Show different messages for different cases
            var booPluginInstalled = (PluginDetect.isMinVersion('AdobeReader', '0') >= 0);

            if (strFormFormat === 'pdf' && r.data.client_form_format != 'pdf' && !booPluginInstalled) {
                var msg1 = String.format(
                    _('Your browser is not configured properly to open PDF forms.<br/>') +
                    _('Your form will now open in HTML mode.')
                );

                Ext.Msg.show({
                    title: _('Please confirm'),
                    msg: msg1,
                    buttons: {yes: _('Ok'), no: _('Cancel')},
                    minWidth: 300,
                    modal: true,
                    icon: Ext.MessageBox.WARNING,
                    fn: function (btn) {
                        if (btn === 'yes') {
                            switch (r.data.client_form_format) {
                                case 'xod':
                                    var oParams = {
                                        p: pdf_id,
                                        m: thisGrid.member_id,
                                        a: r.data.client_form_annotations,
                                        h: r.data.client_form_help_article_id
                                    };

                                    if (r.data.client_form_version_latest) {
                                        Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                                    } else {
                                        Ext.MessageBox.minWidth = 540;
                                        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
                                            var latest = btn === 'yes' ? '1' : '0';

                                            oParams['l'] = latest;
                                            Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                                            if (!empty(latest)) {
                                                setTimeout(function () {
                                                    thisGrid.store.reload();
                                                }, (2000));
                                            }
                                        });
                                    }
                                    break;

                                case 'officio-form':
                                    window.open(baseUrl + '/new-prototype?versionId=' + r.data.client_form_version_id + '&assignedId=' + pdf_id);
                                    break;

                                default:
                                    if (is_client && r.data.locked && r.data.client_form_format === 'angular') {
                                        window.open(baseUrl + '/pdf/' + r.data.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
                                    } else {
                                        window.open(baseUrl + '/pdf/' + r.data.client_form_version_id + '/?assignedId=' + pdf_id);
                                    }
                            }
                        }
                    }
                });

                return false;
            }

            switch (strFormFormat) {
                case 'xod':
                    var oParams = {
                        p: pdf_id,
                        m: thisGrid.member_id,
                        a: r.data.client_form_annotations,
                        h: r.data.client_form_help_article_id
                    };

                    if (r.data.client_form_version_latest) {
                        Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                    } else {
                        Ext.MessageBox.minWidth = 540;
                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                            var latest = btn === 'yes' ? '1' : '0';

                            oParams['l'] = latest;
                            Ext.ux.Popup.show(baseUrl + '/xod/index.html?' + $.param(oParams), true);
                            if (!empty(latest)) {
                                setTimeout(function () {
                                    thisGrid.store.reload();
                                }, (2000));
                            }
                        });
                    }
                    break;

                case 'pdf':
                    var pdf_name = r.data.file_name_stripped;

                    if (r.data.use_revision && r.data.use_revision === 'Y') {
                        this.downloadRevision(pdf_id, 0, pdf_name);
                        return;
                    }

                    var msgError;
                    var linkStyle = 'style="color: #16429B; font-size: 12px;"';

                    if (!booPluginInstalled) {
                        if (Ext.isChrome) {
                            msgError = String.format(
                                _('Your browser is not configured properly.<br/>') +
                                _(' As a result, Officio forms cannot function properly.<br/><br/>') +
                                _(' To configure Chrome to operate with Officio forms,<br/>please click here: <a href="{0}" target="_blank" {1}>{0}</a>.') +
                                _(' Alternatively, you can use a different browser.'),
                                site_version == 'australia' ? 'https://secure.officio.com.au/help/public/#q16' : 'http://uniques.ca/officio_support/chrome',
                                linkStyle
                            );
                        } else {
                            msgError = String.format(
                                _('Adobe Acrobat/Reader does not exist, or it is not set as your default plugin for your browser.') +
                                _(' As a result, your forms cannot function properly.<br/><br/>') +
                                _(' Please install Adobe Reader by clicking here: <a href="{0}" target="_blank" {1}>{0}</a>.') +
                                _(' Once Adobe Reader is installed, close your browser, and open it again.'),
                                'http://get.adobe.com/reader',
                                linkStyle
                            );
                        }
                    }

                    // Show a warning message and don't allow to open a form
                    if (!empty(msgError)) {
                        Ext.simpleConfirmation.warning(msgError);
                        return;
                    }

                    var oParams = {
                        member_id: this.member_id,
                        pdf_id: pdf_id,
                        pdf_name: pdf_name,
                        pdf_data: r.data,
                        use_latest_version: false
                    };

                    var booInNewTab = false;
                    if (r.data.client_form_type === 'bar' || r.data.client_form_version_latest) {
                        this.openPdfForm(oParams, booInNewTab);
                    } else {
                        Ext.MessageBox.minWidth = 540;
                        Ext.Msg.confirm('Please confirm', msg, function (btn) {
                            if (btn === 'yes') {
                                // Update record in the grid - we don't want refresh it again
                                r.data.client_form_version_latest = true;
                                r.commit();

                                // And open the latest form
                                oParams.use_latest_version = true;
                                thisGrid.openPdfForm(oParams, booInNewTab);
                            } else {
                                thisGrid.openPdfForm(oParams, booInNewTab);
                            }
                        });
                    }
                    break;

                case 'officio-form':
                    window.open(baseUrl + '/new-prototype?versionId=' + r.data.client_form_version_id + '&assignedId=' + pdf_id);
                    break;

                default:
                    if (is_client && r.data.locked && r.data.client_form_format === 'angular') {
                        window.open(baseUrl + '/pdf/' + r.data.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
                    } else {
                        window.open(baseUrl + '/pdf/' + r.data.client_form_version_id + '/?assignedId=' + pdf_id);
                    }
                    break;
            }
        }
    },

    openPdfForm: function (oParams, booInNewTab) {
        var tab_container_id = 'forms-tab-' + oParams.member_id;
        var tab_id = tab_container_id + '_pdf' + oParams.pdf_id;

        // Check the form type (to generate the url)
        var open_pdf_url = '';
        if (oParams.pdf_data.client_form_type === 'bar') {
            open_pdf_url = baseUrl + '/forms/index/open-xdp?pdfid=' + oParams.pdf_id + '&' + oParams.pdf_name + '.xdp';
        } else {
            var latest = oParams.use_latest_version ? '1' : '0';
            var pdf_url = baseUrl + '/forms/index/open-assigned-pdf?pdfid=' + oParams.pdf_id + '&latest=' + latest;
            if (__booMergeDataOnServer) {
                open_pdf_url = pdf_url + '/merge/1/file/' + oParams.pdf_name + '.pdf';
            } else {
                var mergeXfdf = __booEditFormForSafari ? '&merge=1' : '';
                var xfdf_url = baseUrl + '/forms/index/open-assigned-xfdf?pdfid=' + oParams.pdf_id + mergeXfdf;
                open_pdf_url = pdf_url + '#FDF=' + xfdf_url;
            }
        }

        // Check if we need to show in new window or tab
        if (booInNewTab !== false) {
            // Generate tab title
            var tab_title;
            if (empty(oParams.pdf_data.family_member_lname) && empty(oParams.pdf_data.family_member_fname)) {
                tab_title = oParams.pdf_data.file_name_stripped;
            } else {
                tab_title = oParams.pdf_data.family_member_lname;
                if (!empty(tab_title) && !empty(oParams.pdf_data.family_member_fname)) {
                    tab_title += ', ';
                }

                tab_title += oParams.pdf_data.family_member_fname + ' - ' + oParams.pdf_name;
            }

            // Show in the tab
            var tabPanel = Ext.getCmp(tab_container_id);

            // Open new or activate existing tab
            var newTab = Ext.getCmp(tab_id);
            if (!newTab) {
                newTab = tabPanel.add({
                    id: tab_id,
                    xtype: 'iframepanel',
                    title: tab_title,
                    closable: true,
                    deferredRender: false,
                    defaultSrc: open_pdf_url,
                    frameConfig: {
                        autoLoad: {
                            id: 'assignedpdf-iframe-' + this.member_id + '-' + oParams.pdf_id,
                            width: '100%'
                        },
                        style: 'height: 555px;'
                    }
                });
            }

            tabPanel.doLayout();  //if TabPanel is already rendered
            tabPanel.setActiveTab(newTab);
        } else {
            // Show in new window
            window.open(open_pdf_url);
        }
    },

    deleteForm: function () {
        var grid = this;
        var arrSelected = this.getSelectedFormIds();
        if (arrSelected.length > 0) {
            // There are selected forms
            var title;
            var msg;
            if (arrSelected.length === 1) {
                title = _('Delete selected form?');
                msg = _('Selected form will be deleted. Are you sure to delete it?');
            } else {
                title = _('Delete selected forms?');
                msg = _('Selected forms will be deleted. Are you sure to delete them?');
            }

            Ext.Msg.show({
                title: title,
                msg: msg,
                buttons: Ext.Msg.YESNO,
                fn: function (btn) {
                    if (btn === 'yes') {
                        grid.sendRequest('delete', true);
                    }
                },
                animEl: 'forms-btn-delete' + grid.member_id
            });
        } else {
            Ext.simpleConfirmation.msg(_('Info'), _('Please select at least one form to delete it.'));
        }
    },

    printForm: function () {
        var arrSelected = this.getSelectionModel().getSelections();
        if (arrSelected.length === 1) {
            var record = arrSelected[0];
            var pdf_id = record.data.client_form_id;
            var pdf_name = record.data.file_name_stripped;

            if (record.data.use_revision && record.data.use_revision === 'Y') {
                this.downloadRevision(pdf_id, 0, pdf_name);
            } else if (record.data.client_form_format === 'angular' || record.data.client_form_format === 'html') {
                window.open(baseUrl + '/pdf/' + record.data.client_form_version_id + '/?assignedId=' + pdf_id + '&print');
            } else {
                window.open(baseUrl + '/forms/index/print?member_id=' + this.member_id + '&pdfid=' + pdf_id);
            }
        } else {
            Ext.simpleConfirmation.info(_('Please select one pdf form and try again.'));
        }
    },

    generatePDFForEmail: function (memberId, arrPdf) {
        var grid = this;
        Ext.getBody().mask(_('Creating PDF file(s)...'));
        Ext.Ajax.request({
            url: baseUrl + '/forms/index/email',
            timeout: 300000, // 5 minutes
            params: {
                memberId: memberId,
                arrPdf: Ext.encode(arrPdf)
            },

            success: function (result) {
                Ext.getBody().unmask();

                var arrResult = Ext.decode(result.responseText);
                if (!empty(arrResult.error)) {
                    Ext.simpleConfirmation.error(arrResult.error);
                } else {
                    var files = arrResult.files;
                    if (files) {
                        var attachments = [];
                        for (var i = 0; i < files.length; i++) {
                            var link = '';
                            if (files[i].use_revision === 'Y') {
                                link = grid.generateRevisionUrl(files[i].pdf_id, 0, files[i].filename);
                            } else if (!empty(files[i].file)) {
                                link = topBaseUrl + '/mailer/index/download-attach?type=phantomjs&name=' + files[i].filename + '&attach_id=' + files[i].file;
                            } else {
                                link = baseUrl + '/forms/index/open-pdf-and-xfdf?pdfid=' + files[i].pdf_id;
                            }


                            attachments.push({
                                name: files[i].filename,
                                link: link,
                                size: files[i].filesize,
                                file_id: files[i].file,
                                path: files[i].file
                            });
                        }

                        var email_for_client = '';
                        if (files[0]['email']) {
                            email_for_client = files[0]['email'];
                        }

                        show_email_dialog({member_id: memberId, attach: attachments, email: email_for_client});
                    }
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error(_('Cannot create PDF file(s)'));
            }
        });
    },

    sendEmail: function () {
        var grid = this;
        var sm = grid.getSelectionModel();
        if (sm.hasSelection()) {
            var s = sm.getSelections();
            var arrRadioItems = [];
            var arrSelectedFormIds = [];
            var booShowConfirmDialog = false;

            for (var i = 0; i < s.length; i++) {
                arrSelectedFormIds.push({
                    pdfId: s[i].data.client_form_id,
                    mode: 'read-only'
                });
                booShowConfirmDialog = booShowConfirmDialog || s[i].data.client_form_format === 'pdf' || s[i].data.client_form_format === 'html' || s[i].data.client_form_format === 'xod';

                arrRadioItems.push({
                    xtype: 'radiogroup',
                    labelSeparator: '',
                    fieldLabel: s[i].data.file_name,

                    items: [
                        {
                            boxLabel: _('Read only'),
                            name: 'form-' + s[i].data.client_form_id,
                            inputValue: 'read-only',
                            checked: true
                        }, {
                            boxLabel: _('Fillable'),
                            name: 'form-' + s[i].data.client_form_id,
                            inputValue: 'fillable',
                            style: 'margin-left: 10px;',
                            hidden: s[i].data.client_form_format === 'angular'
                        }
                    ]
                });
            }

            if (booShowConfirmDialog) {
                var fp = new Ext.FormPanel({
                    bodyStyle: 'padding:5px;',
                    labelWidth: 320,
                    items: arrRadioItems
                });

                var confirmationWnd = new Ext.Window({
                    title: '<i class="lar la-envelope"></i>' + _('Select the format of the attachment'),
                    modal: true,
                    width: 550,
                    autoHeight: true,
                    resizable: false,

                    items: new Ext.Container({
                        items: [
                            {
                                layout: 'table',
                                defaults: {
                                    // applied to each contained panel
                                    bodyStyle: 'padding: 5px; font-size: 13px;'
                                },

                                layoutConfig: {
                                    tableAttrs: {
                                        style: {
                                            width: '100%'
                                        }
                                    },
                                    columns: 2
                                },

                                items: [
                                    {
                                        width: 350,
                                        html: _('Selected forms:')
                                    }, {
                                        width: 200,
                                        style: 'text-align: center;',
                                        html: _('Attach as:')
                                    }
                                ]
                            }, fp
                        ]
                    }),

                    buttons: [
                        {
                            text: _('Cancel'),
                            handler: function () {
                                confirmationWnd.close();
                            }
                        }, {
                            text: _('Attach'),
                            cls: 'orange-btn',
                            handler: function () {
                                var oSelected = fp.getForm().getValues();
                                arrSelectedFormIds = [];
                                for (var i = 0; i < s.length; i++) {
                                    arrSelectedFormIds.push({
                                        pdfId: s[i].data.client_form_id,
                                        mode: oSelected['form-' + s[i].data.client_form_id]
                                    });
                                }

                                confirmationWnd.close();
                                grid.generatePDFForEmail(grid.member_id, arrSelectedFormIds);
                            }
                        }
                    ]
                });

                confirmationWnd.show();
                confirmationWnd.center();
            } else {
                this.generatePDFForEmail(grid.member_id, arrSelectedFormIds);
            }
        } else {
            Ext.simpleConfirmation.info(_('Please select at least one form to email it.'));
        }
    },

    addQuestionnaire: function () {
        var grid = this;


        grid.getEl().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/forms/index/load-setting',

            success: function (f) {
                grid.getEl().unmask();
                var resultData = Ext.decode(f.responseText);
                if (resultData && resultData['form-setting']) {

                    var fp = new Ext.FormPanel({
                        bodyStyle: 'padding:5px;',
                        labelWidth: 0,
                        items: [
                            {
                                xtype: 'label',
                                text: _('Select the type of the questionnaire:'),
                                style: 'display: block; margin-bottom: 10px; font-size: 16px'
                            },
                            {
                                xtype: 'radiogroup',
                                labelSeparator: '',
                                hideLabel: '',
                                columns: 1,
                                vertical: true,

                                items: Object.keys(resultData['form-setting']).map(qnrTitle => {
                                    return {
                                        boxLabel: qnrTitle,
                                        name: 'form-questionnaire-type',
                                        inputValue: qnrTitle,
                                        style: 'margin-right: 10px;'
                                    }
                                })
                            }
                        ]
                    });

                    var windowQnr = new Ext.Window({
                        title: '<i class="las la-plus"></i>' + _('Add new questionnaire'),
                        modal: true,
                        width: 550,
                        autoHeight: true,
                        resizable: false,

                        items: new Ext.Container({
                            items: [
                                fp
                            ]
                        }),

                        buttons: [
                            {
                                text: _('Cancel'),
                                handler: function () {
                                    windowQnr.close();
                                }
                            }, {
                                text: _('Add'),
                                cls: 'orange-btn',
                                handler: function () {
                                    var oSelected = fp.getForm().getValues();
                                    grid.addQuestionnaireForm(oSelected, windowQnr);
                                }
                            }
                        ]
                    });

                    windowQnr.show();
                    windowQnr.center();
                } else {
                    Ext.simpleConfirmation.error(_('Can\'t load questionnaire types'));
                }
            },

            failure: function () {
                grid.getEl().unmask();
                Ext.simpleConfirmation.error(_('Can\'t load questionnaire types'));
            }
        });
    },

    addQuestionnaireForm: function (oSelected, wndAssign) {
        var grid = this;

        if (oSelected['form-questionnaire-type']) {
            wndAssign.getEl().mask(_('Saving...'));

            // Send request
            Ext.Ajax.request({
                url: baseUrl + '/forms/index/assignOfficioForm',
                params:
                    {
                        member_id: grid.member_id,
                        form_questionnaire_type: oSelected['form-questionnaire-type']
                    },

                success: function (f) {
                    var resultData = Ext.decode(f.responseText);

                    if (resultData.success) {
                        // Show confirmation
                        wndAssign.getEl().mask(_('Done!'));

                        setTimeout(function () {
                            wndAssign.getEl().unmask();

                            // Refresh main list
                            Ext.getCmp('forms-main-grid' + grid.member_id).store.reload();

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
                    Ext.simpleConfirmation.error(_('Questionnaire was not added. Please try again later.'));
                    wndAssign.getEl().unmask();
                }
            });
        } else {
            Ext.simpleConfirmation.warning(_('Please select a questionnaire type'));
        }
    },

    editSettings: function (clientFormId) {
        var grid = this;

        grid.getEl().mask(_('Loading...'));
        Ext.Ajax.request({
            url: baseUrl + '/forms/index/load-form-setting',
            params: {
                client_form_id: clientFormId
            },

            success: function (f) {
                grid.getEl().unmask();
                var resultData = Ext.decode(f.responseText);
                var titleFields = [];

                if (resultData.form_settings.form_type) {
                    titleFields.push({
                        xtype: 'label',
                        text: _(resultData.form_settings.form_type),
                        style: 'display: block; font-size: 16px'
                    });
                }
                titleFields.push({
                    xtype: 'label',
                    text: _('Select the sections to appear in the questionnaire:'),
                    style: 'display: block; margin-top: 10px; font-size: 16px'
                });

                var fp = new Ext.FormPanel({
                    bodyStyle: 'padding:5px;',
                    labelWidth: 0,
                    items: titleFields.concat(
                        resultData.form_template.map(section => {
                            return {
                                xtype: 'container',
                                style: 'padding: 10px;',
                                items: [
                                    {
                                        id: section.id,
                                        xtype: 'checkbox',
                                        name: section.id,
                                        checked: resultData.form_settings[section.id] || false,
                                        boxLabel: _(section.name)
                                    },
                                    {
                                        xtype: 'container',
                                        style: 'padding-left: 30px; padding-top: 5px;',
                                        items: section.tabs.map(tab => {
                                            return {
                                                id: tab.id,
                                                xtype: 'checkbox',
                                                name: tab.id,
                                                checked: resultData.form_settings[tab.id] || false,
                                                boxLabel: _(tab.name)
                                            }
                                        })
                                    }
                                ]
                            };
                        })
                    )
                });

                var windowQnr = new Ext.Window({
                    title: '<i class="las la-edit"></i>' + _('Edit questionnaire settings'),
                    modal: true,
                    width: 550,
                    autoHeight: true,
                    resizable: false,

                    items: new Ext.Container({
                        items: [
                            fp
                        ]
                    }),

                    buttons: [
                        {
                            text: _('Cancel'),
                            handler: function () {
                                windowQnr.close();
                            }
                        }, {
                            text: _('Save'),
                            cls: 'orange-btn',
                            handler: function () {
                                var oSelected = fp.getForm().getValues();
                                windowQnr.getEl().mask('Saving...');

                                Ext.Ajax.request({
                                    url: baseUrl + '/forms/index/save-form-setting',
                                    params: {
                                        member_id: grid.member_id,
                                        client_form_id: clientFormId,
                                        settings: Ext.encode(oSelected)
                                    },

                                    success: function () {
                                        windowQnr.close();
                                    },

                                    failure: function () {
                                        windowQnr.getEl().unmask();
                                        Ext.simpleConfirmation.error(_('Can\'t edit Form Name'));
                                    }
                                });
                            }
                        }
                    ]
                });

                windowQnr.show();
                windowQnr.center();
            },

            failure: function () {
                grid.getEl().unmask();
                Ext.simpleConfirmation.error_(('Can\'t load settings'));
            }
        });
    },


    finalizeForm: function () {
        var grid = this;
        var booNeedConfirm = false;
        var msg = '';
        var title = '';
        var s = grid.getSelectionModel().getSelections();
        if (s.length > 0) {
            for (var i = 0; i < s.length; i++) {
                if (!empty(s[i].data.date_finalized)) {
                    booNeedConfirm = true;
                }
            }

            title = (s.length === 1) ? _('Replace file?') : _('Replace files?');
            msg = site_version == 'australia' ? _('A finalised copy of the selected form(s) already exist.<br/> Would you like to replace them?') : _('A finalized copy of the selected form(s) already exist.<br/> Would you like to replace them?');
        }


        if (booNeedConfirm) {
            Ext.Msg.show({
                title: title,
                msg: msg,
                icon: Ext.MessageBox.QUESTION,
                buttonAlign: 'right',
                buttons: {
                    yes: _('Yes, Replace'),
                    no: _('Add a Copy'),
                    cancel: _('Cancel')
                },

                fn: function (btn) {
                    switch (btn) {
                        case 'yes':
                            grid.sendRequest('finalize', true, true, true);
                            break;

                        case 'no':
                            grid.sendRequest('finalize', true, true, false);
                            break;

                        // case 'cancel':
                        default:
                            break;
                    }
                },

                animEl: 'forms-btn-delete' + grid.member_id
            });
        } else {
            grid.sendRequest('finalize', true, true, false);
        }
    },


    downloadForm: function (pdf_id, pdf_name) {
        var pdf_url = baseUrl + '/forms/index/open-assigned-pdf?pdfid=' + pdf_id + '&merge=1&download=1&file=' + pdf_name + '.pdf';
        window.open(pdf_url);
    },

    generateRevisionUrl: function (pdf_id, pdf_revision, pdf_name, booWithExtension) {
        if (booWithExtension) {
            pdf_name += '.pdf';
        }
        return baseUrl + '/forms/index/download-revision?pdfid=' + pdf_id + '&revision=' + pdf_revision + '&' + pdf_name;
    },

    runDownloadRevision: function (pdf_id, pdf_revision, pdf_name) {
        window.open(this.generateRevisionUrl(pdf_id, pdf_revision, pdf_name, true));
    },

    downloadRevision: function (pdf_id, pdf_revision, pdf_name) {
        var grid = this;
        var cookieName = 'barcoded_forms_download';
        if (!empty(Ext.state.Manager.get(cookieName))) {
            grid.runDownloadRevision(pdf_id, pdf_revision, pdf_name);
        } else {
            var doNotShowCheckbox = new Ext.form.Checkbox({
                hideLabel: true,
                style: 'vertical-align: top;',
                boxLabel: _("Don't show this message again.")
            });

            var infoIcon = String.format(
                '<img src="{0}/js/ext/resources/images/default/window/icon-info.gif" style="padding: 100px 15px 60px 15px;" align="left" width="32" height="32" />',
                baseUrl
            );
            var formPanel = new Ext.form.FormPanel({
                items: [
                    {
                        xtype: 'label',
                        style: 'font-size: 16px;',
                        html: infoIcon +
                            _('You are about to open a barcoded form. ') +
                            _('This form contains a special feature that requires to be populated and saved locally on your computer.<br/><br/>') +

                            _('Simply:<br/>') +
                            '<ul>' +
                            '<li>' + _(' - Fill out this form.') + '</li>' +
                            '<li>' + _(' - Save it locally in a location that you are comfortable with.') + '</li>' +
                            '<li>' + _(' - Click on <span style="color: blue;">Upload</span> to save a copy of this form to Officio.') + '</li>' +
                            '</ul>'
                    }, {
                        style: 'padding: 5px;',
                        html: '&nbsp;'
                    },
                    doNotShowCheckbox
                ]
            });

            var wnd = new Ext.Window({
                title: _('Warning'),
                plain: false,
                modal: true,
                resizable: false,
                closable: false,
                width: 520,

                items: formPanel,

                buttons: [
                    {
                        text: _('Cancel'),
                        handler: function () {
                            wnd.close();
                        }
                    }, {
                        cls: 'orange-btn',
                        text: _('OK'),
                        handler: function () {
                            if (doNotShowCheckbox.getValue()) {
                                // Set cookie to prevent this dialog showing in the future
                                // Don't send it to server
                                Ext.state.Manager.set(cookieName, 'lets dance!!! ;)')
                            }

                            grid.runDownloadRevision(pdf_id, pdf_revision, pdf_name);
                            wnd.close();
                        }
                    }
                ]
            });
            wnd.show();
        }
    },

    editFormAlias: function (assignedPdfFormId) {
        var grid = this;
        var selRecord = grid.store.getById(assignedPdfFormId);
        var selAlias = empty(selRecord) ? '' : selRecord.data.family_member_alias;

        var memberAlias = new Ext.form.TextField({
            fieldLabel: _('Label for Other'),
            width: 230,
            value: selAlias
        });

        var win = new Ext.Window({
            title: _('Label for Other'),
            modal: true,
            width: 350,
            autoHeight: true,
            resizable: false,
            items: new Ext.FormPanel({
                bodyStyle: 'padding:5px;',
                labelWidth: 90,
                layout: 'form',
                items: memberAlias
            }),
            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        win.close();
                    }
                }, {
                    text: _('Save'),
                    cls: 'orange-btn',
                    handler: function () {
                        win.getEl().mask(_('Saving...'));

                        Ext.Ajax.request({
                            url: baseUrl + '/forms/index/edit-alias',
                            params: {
                                member_id: grid.member_id,
                                client_form_id: Ext.encode(assignedPdfFormId),
                                alias: Ext.encode(memberAlias.getValue())
                            },

                            success: function () {
                                win.getEl().mask(_('Done!'));

                                // Refresh main list
                                grid.store.reload();

                                setTimeout(function () {
                                    win.getEl().unmask();
                                    win.close();
                                }, 750);
                            },

                            failure: function () {
                                Ext.simpleConfirmation.error(_('Can\'t edit Form Name'));
                            }
                        });
                    }
                }
            ]
        });

        win.show();
        win.center();
    },

    makeReadOnly: function () {
        var arrIdsToDisable = [
            'forms-btn-assign',
            'forms-btn-edit',
            'forms-btn-delete',
            'forms-btn-email',
            'forms-btn-finalise-submission',
            'forms-btn-lock',
            'forms-btn-complete',
            'forms-btn-questionnaire'
        ];

        for (var i = 0; i < arrIdsToDisable.length; i++) {
            var button = Ext.getCmp(arrIdsToDisable[i] + this.member_id);
            if (button) {
                button.disable();
            }
        }
    }
});