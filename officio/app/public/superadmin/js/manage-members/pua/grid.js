var PUAGrid = function (config) {
    Ext.apply(this, config);

    var thisGrid  = this;
    var puaRecord = Ext.data.Record.create([
        {
            name: 'pua_id'
        }, {
            name: 'pua_type'
        }, {
            name: 'pua_designated_person_type'
        }, {
            name: 'pua_designated_person_full_name'
        }, {
            name: 'pua_designated_person_form'
        }, {
            name: 'pua_business_contact_name'
        }, {
            name: 'pua_business_contact_phone'
        }, {
            name: 'pua_business_contact_email'
        }, {
            name: 'pua_business_contact_or_service'
        }, {
            name: 'pua_business_contact_username'
        }, {
            name: 'pua_business_contact_password'
        }, {
            name: 'pua_business_contact_instructions'
        }, {
            name: 'pua_created_by'
        }, {
            name:       'pua_created_on',
            type:       'date',
            dateFormat: Date.patterns.ISO8601Long
        }, {
            name: 'pua_updated_by'
        }, {
            name: 'pua_updated_on',
            type: 'date',
            dateFormat: Date.patterns.ISO8601Long
        }
    ]);

    this.store = new Ext.data.Store({
        url: baseUrl + '/manage-members-pua/list',
        remoteSort: true,
        autoLoad: true,

        sortInfo: {
            field: 'pua_created_on',
            direction: 'DESC'
        },

        baseParams: {
            pua_type: config.pua_type
        },

        // the return will be Json, so lets set up a reader
        reader: new Ext.data.JsonReader({
            id:            'pua_id',
            root:          'rows',
            totalProperty: 'totalCount'
        }, puaRecord)
    });

    this.sm = new Ext.grid.CheckboxSelectionModel();

    this.bbar = new Ext.PagingToolbar({
        hidden:      false,
        pageSize:    25,
        store:       this.store,
        displayInfo: true,
        displayMsg:  _('Records {0} - {1} of {2}'),
        emptyMsg:    _('No records to display')
    });

    this.tbar = [
        {
            text:    config.pua_type === 'designated_person' ? '<i class="las la-plus"></i>' + _('Add Designated Representative/Responsible Person') : '<i class="las la-plus"></i>' + _('Add Business Contact or Service Provider'),
            cls:     'orange-btn',
            scope:   this,
            handler: function () {
                var oPUARecord = new puaRecord({
                    'pua_id':                     0,
                    'pua_type':                   config.pua_type,
                    'pua_designated_person_type': _('Designated Responsible Person')
                });

                var wnd;
                if (config.pua_type === 'designated_person') {
                    wnd = new PUAResponsiblePersonDialog(thisGrid, oPUARecord);
                } else {
                    wnd = new PUABusinessContactDialog(thisGrid, oPUARecord);
                }

                wnd.show();
            }
        }, {
            text:     '<i class="las la-edit"></i>' + _('Edit'),
            disabled: true,
            ref:      '../editPUARecordButton',
            scope:    this,
            handler:  thisGrid.editPUARecord.createDelegate(thisGrid)
        }, {
            text:     '<i class="las la-trash"></i>' + _('Delete'),
            disabled: true,
            ref:      '../deletePUARecordButton',
            handler:  this.deletePUARecord.createDelegate(this)
        }, {
            text:    '<i class="las la-file-pdf"></i>' + _('Export ALL to pdf'),
            ref:     '../exportAllToPdfPUARecordButton',
            hidden:  config.pua_type === 'designated_person',
            handler: this.exportToPdfPUARecord.createDelegate(this, [true])
        }, '->', {
            text: '<i class="las la-undo-alt"></i>',
            scope:   this,
            handler: function () {
                thisGrid.store.reload();
            }
        }
    ];

    this.mainColumnId = Ext.id();

    this.cm = new Ext.grid.ColumnModel({
        columns: [
            this.sm, {
                id:        config.pua_type === 'designated_person' ? this.mainColumnId : Ext.id(),
                header:    _('Person Name'),
                width:     120,
                hidden:    config.pua_type !== 'designated_person',
                dataIndex: 'pua_designated_person_full_name'
            }, {
                header:    _('Type'),
                width:     250,
                hidden:    config.pua_type !== 'designated_person',
                dataIndex: 'pua_designated_person_type',
                renderer:  this.renderDesignatedPersonTypeColumn.createDelegate(this),
                fixed:     true
            }, {
                header:    _('Designation Form'),
                width:     200,
                hidden:    config.pua_type !== 'designated_person',
                dataIndex: 'pua_designated_person_form',
                renderer:  this.renderFormColumn.createDelegate(this),
                fixed:     true
            }, {
                id:        config.pua_type !== 'designated_person' ? this.mainColumnId : Ext.id(),
                header:    _('Contact Name'),
                width:     250,
                hidden:    config.pua_type === 'designated_person',
                dataIndex: 'pua_business_contact_name'
            }, {
                header:    _('Type or service'),
                width:     300,
                hidden:    config.pua_type === 'designated_person',
                dataIndex: 'pua_business_contact_or_service',
                fixed:     true
            }, {
                header:    _('Created On'),
                width:     190,
                renderer:  Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
                dataIndex: 'pua_created_on',
                fixed:     true
            }, {
                header:    _('Updated On'),
                width:     190,
                renderer:  Ext.util.Format.dateRenderer(Date.patterns.UniquesLong),
                dataIndex: 'pua_updated_on',
                fixed:     true
            }
        ],

        defaultSortable: true
    });


    PUAGrid.superclass.constructor.call(this, {
        loadMask: true,
        cls: 'extjs-grid',

        autoExpandColumn: this.mainColumnId,
        autoExpandMin: 120,

        viewConfig: {
            deferEmptyText: false,
            emptyText: _('No records found.'),
            forceFit: true
        }
    });

    this.on('rowdblclick', this.editPUARecord.createDelegate(this));
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(PUAGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();

        this.editPUARecordButton.setDisabled(sel.length !== 1);
        this.deletePUARecordButton.setDisabled(sel.length !== 1);
        this.exportAllToPdfPUARecordButton.setDisabled(false);
    },

    editPUARecord: function () {
        var oPUARecord = this.getSelectedPUARecord();

        var wnd;
        if (this.pua_type === 'designated_person') {
            wnd = new PUAResponsiblePersonDialog(this, oPUARecord);
        } else {
            wnd = new PUABusinessContactDialog(this, oPUARecord);
        }
        wnd.show();
    },

    exportToPdfPUARecord: function (booAll, PUARecordId) {
        var url = baseUrl + '/manage-members-pua/export?type=pdf';
        if (!booAll) {
            if (empty(PUARecordId)) {
                var oPUARecord = this.getSelectedPUARecord();
                PUARecordId = oPUARecord['data']['pua_id'];
            }

            url += '&pua_id=' + PUARecordId;
        }

        window.open(url);
    },

    renderDesignatedPersonTypeColumn: function (val) {
        var result;
        switch (val) {
            case 'responsible_person':
                result = _('Responsible Person');
                break;

            case 'authorized_representative':
                result = _('Authorized Representative');
                break;

            default:
                result = _('Unknown');
                break;
        }

        return result;
    },

    renderFormColumn: function (val, p, record) {
        var grid      = this;
        var strResult = '';

        if (empty(record['data']['pua_designated_person_form'])) {
            var uploadButtonFormId = Ext.id();
            var btnUploadForm      = new Ext.form.FormPanel({
                fileUpload:  true,
                cls:         'extjs-grid-noborder',
                frame:       false,
                width:       55,
                bodyBorder:  false,
                hideBorders: true,
                border:      false,

                items: [
                    {
                        xtype: 'hidden',
                        name:  'pua_id',
                        value: record['data']['pua_id']
                    }
                ]
            });

            var btnUpload = new Ext.ux.form.FileUploadField({
                name: 'pua_designated_person_form_file',
                itemCls: 'no-margin-bottom',
                buttonText: String.format('<span style="font-weight: bold; color: #0E457A;">{0}</span>', _('Upload')),
                hideLabel: true,
                width: 55,

                tooltip: {
                    width: 200,
                    text:  _('Click to upload a new form.')
                },

                buttonOnly: true,
                scope:      this,
                listeners:  {
                    'fileselected': grid.uploadPUAForm.createDelegate(grid, [btnUploadForm])
                }
            });
            btnUploadForm.add(btnUpload);
            btnUploadForm.render.defer(1, btnUploadForm, [uploadButtonFormId]);

            strResult = String.format('<div style="float: left; color: red; padding-top: 5px;">{0}</div><div style="float: right" id={1}></div>', _('Not uploaded'), uploadButtonFormId);
        } else {
            var downloadLinkId = Ext.id();

            var downloadLink = new Ext.BoxComponent({
                autoEl: {
                    tag:     'a',
                    href:    '#',
                    'class': 'bluelink',
                    style: 'color: green;',
                    title: 'Click to download ' + record['data']['pua_designated_person_form'],
                    html:    _('Uploaded successfully')
                },

                listeners: {
                    scope:  this,
                    render: function (c) {
                        c.getEl().on('click', grid.downloadPUAForm.createDelegate(grid, [record['data']['pua_id']]), this, {stopEvent: true});
                    }
                }
            });
            downloadLink.render.defer(1, downloadLink, [downloadLinkId]);

            var deleteButtonId = Ext.id();
            var btnDelete      = new Ext.Button({
                tooltip: {
                    width: 200,
                    text:  '<i class="las la-trash"></i>' + _('Click to delete ') + record['data']['pua_designated_person_form']
                },

                scope:   this,
                handler: grid.deletePUAForm.createDelegate(grid, [record['data']['pua_id'], record['data']['pua_designated_person_form']])
            });
            btnDelete.render.defer(1, btnDelete, [deleteButtonId]);

            strResult = String.format('<div style="float: left; padding-top: 5px;" id="{0}"></div><div style="float: right" id={1}></div>', downloadLinkId, deleteButtonId);
        }

        return strResult;
    },

    downloadPUAForm: function (puaRecordId) {
        window.open(baseUrl + '/manage-members-pua/download-designation-form?pua_id=' + puaRecordId);
    },

    uploadPUAForm: function (formToUpload) {
        var thisGrid = this;
        thisGrid.getEl().mask(_('Saving...'));

        formToUpload.getForm().submit({
            url: baseUrl + '/manage-members-pua/upload-designation-form',

            success: function (form, action) {

                thisGrid.getEl().mask(empty(action.result.message) ? _('Done!') : action.result.message);
                setTimeout(function () {
                    thisGrid.getEl().unmask();
                    thisGrid.store.reload();
                }, 750);
            },

            failure: function (form, action) {
                thisGrid.getEl().unmask();

                if (!empty(action.result.message)) {
                    Ext.simpleConfirmation.error(action.result.message);
                } else {
                    Ext.simpleConfirmation.error(_('Cannot save info.'));
                }
            }
        });
    },

    deletePUAForm: function (puaRecordId, designationFormName) {
        var thisGrid = this;

        var msg = String.format(_('Are you sure you want to delete <i>{0}</i>?'), designationFormName);
        Ext.Msg.confirm(_('Please confirm'), msg, function (btn) {
            if (btn === 'yes') {
                thisGrid.getEl().mask(_('Deleting...'));

                Ext.Ajax.request({
                    url: baseUrl + '/manage-members-pua/delete-designation-form/',

                    params: {
                        pua_id: puaRecordId
                    },

                    success: function (result) {
                        thisGrid.getEl().unmask();

                        var resultData = Ext.decode(result.responseText);
                        if (resultData.success) {
                            var msg = String.format(_('<i>{0}</i> was deleted successfully.'), designationFormName);
                            Ext.simpleConfirmation.success(msg);

                            thisGrid.store.reload();
                        } else {
                            Ext.simpleConfirmation.error(resultData.message);
                        }
                    },
                    failure: function () {
                        thisGrid.getEl().unmask();

                        var msg = String.format(_('<i>{0}</i> cannot be deleted. Please try again later.'), designationFormName);
                        Ext.simpleConfirmation.error(msg);
                    }
                });
            }
        });
    },

    getSelectedPUARecord: function () {
        var sel = this.getSelectionModel().getSelections();
        return sel.length > 0 ? sel[0] : false;
    },

    deletePUARecord: function () {
        var sel = this.getSelectedPUARecord();
        if (sel === false) {
            return;
        }

        var grid     = this;
        var name     = sel['data']['pua_type'] === 'designated_person' ? sel['data']['pua_designated_person_full_name'] : sel['data']['pua_business_contact_name'];
        var question = String.format(_('Are you sure want to delete "<i>{0}</i>" record?'), name);

        Ext.MessageBox.buttonText.yes = 'Yes, delete';
        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: baseUrl + '/manage-members-pua/delete',

                    params: {
                        ids: Ext.encode([sel['data']['pua_id']])
                    },

                    success: function (f) {
                        var result = Ext.decode(f.responseText);
                        if (result.success) {
                            grid.store.reload();
                            Ext.simpleConfirmation.success(_('Record was deleted successfully.'));
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                        }

                        Ext.getBody().unmask();
                    },

                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot delete. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        });
    }
});