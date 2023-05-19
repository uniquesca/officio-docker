var ApplicantsProfileEditOptionsDialog = function (config) {
    var thisDialog = this;
    Ext.apply(this, config);

    var arrOptionFields = [
        {name: 'option_id', type: 'int'},
        {name: 'option_name', type: 'string'},
        {name: 'option_order', type: 'int'},
        {name: 'option_deleted', type: 'bool'}
    ];

    this.optionRecord = Ext.data.Record.create(arrOptionFields);

    var options_store = new Ext.data.Store({
        fields: arrOptionFields,
        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, this.optionRecord)
    });

    var action = new Ext.ux.grid.RowActions({
        header: _('Order'),
        widthIntercept: 30,
        keepSelection: true,

        actions: [{
            iconCls: 'move_option_up',
            tooltip: _('Move option Up')
        }, {
            iconCls: 'move_option_down',
            tooltip: _('Move option Down')
        }],

        callbacks: {
            'move_option_up': thisDialog.moveOption.createDelegate(this),
            'move_option_down': thisDialog.moveOption.createDelegate(this)
        }
    });

    var sm = new Ext.grid.CheckboxSelectionModel();

    var cm = new Ext.grid.ColumnModel({
        columns: [
            sm,
            {
                header: _('Option Name'),
                dataIndex: 'option_name',
                width: 320,
                renderer: function (val, p, record) {
                    var res = val;
                    if (record.data.option_deleted) {
                        res = String.format(
                            '<span style="{0}" ext:qtip="{1}">{2}</span>',
                            'text-decoration: line-through red;',
                            _('This option is marked as deleted. It will be automatically removed when it will be not used by any client/case.'),
                            val
                        );
                    }

                    return res;
                },
                editor: new Ext.form.TextField({
                    allowBlank: false
                })
            },
            action
        ],
        defaultSortable: false
    });

    this.optionsGrid = new Ext.grid.GridPanel({
        store: options_store,
        cm: cm,
        sm: sm,
        plugins: [action],
        width: 540,
        height: 350,
        cls: 'extjs-grid',
        viewConfig: {
            forceFit: true
        },

        bbar: new Ext.Toolbar({
            items: [
                {
                    xtype: 'box',
                    style: 'padding-top: 10px; float: left; font-size: 14px;',
                    autoEl: {
                        tag: 'div',
                        html: _('To apply the changes please click on the "Save" button.')
                    }
                }
            ]
        }),

        tbar: [
            {
                text: '<i class="las la-plus"></i>' + _('Add Option'),
                handler: function () {
                    thisDialog.addEditOption(0, {});
                }
            }, {
                text: '<i class="lar la-edit"></i>' + _('Rename Option'),
                disabled: true,
                ref: '../renameOptionBtn',
                handler: thisDialog.renameOption.createDelegate(thisDialog)
            }, {
                text: '<i class="las la-trash"></i>' + _('Delete Option'),
                disabled: true,
                ref: '../deleteOptionBtn',
                handler: function () {
                    var sel = thisDialog.optionsGrid.getSelectionModel().getSelections();
                    if (sel.length) {
                        var question = String.format(
                            gt.ngettext(
                                'Are you sure you want to delete <i>{0}</i>?',
                                'Are you sure you want to delete these {1} selected options?',
                                sel.length
                            ),
                            sel[0]['data']['option_name'],
                            sel.length
                        );

                        Ext.Msg.confirm(_('Please confirm'), question,
                            function (btn) {
                                if (btn === 'yes') {
                                    for (var i = 0; i < sel.length; i++) {
                                        thisDialog.optionsGrid.store.remove(sel[i]);
                                    }
                                }
                            }
                        );
                    }
                }
            }
        ],
    });

    ApplicantsProfileEditOptionsDialog.superclass.constructor.call(this, {
        title: '<i class="las la-pen"></i>' + _('Edit Options'),
        modal: true,
        autoHeight: true,
        autoWidth: true,
        resizable: false,
        items: this.optionsGrid,

        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    thisDialog.close();
                }
            }, {
                text: '<i class="las la-save"></i>' + _('Save'),
                cls: 'orange-btn',
                handler: thisDialog.saveOptions.createDelegate(thisDialog)
            }
        ]
    });

    thisDialog.optionsGrid.getSelectionModel().on('selectionchange', thisDialog.updateToolbarButtons.createDelegate(this));
    thisDialog.on('show', thisDialog.loadOptionsData.createDelegate(this));
    thisDialog.optionsGrid.on('dblclick', thisDialog.renameOption, thisDialog);
};

Ext.extend(ApplicantsProfileEditOptionsDialog, Ext.Window, {
    loadOptionsData: function () {
        var thisDialog = this;
        thisDialog.optionsGrid.store.loadData({
            rows: thisDialog.options,
            totalCount: thisDialog.options.length
        });
    },

    updateToolbarButtons: function () {
        var thisDialog = this;

        var sel = thisDialog.optionsGrid.getSelectionModel().getSelections();
        var booIsSelectedAtLeastOne = sel.length >= 1;
        var booIsSelectedOnlyOne = sel.length === 1;

        thisDialog.optionsGrid['renameOptionBtn'].setDisabled(!booIsSelectedOnlyOne);
        thisDialog.optionsGrid['deleteOptionBtn'].setDisabled(!booIsSelectedAtLeastOne);
    },

    moveOption: function (grid, record, action, selectedRow) {
        var thisDialog = this;
        var sm = thisDialog.optionsGrid.getSelectionModel();
        var optionsStore = thisDialog.optionsGrid.store;
        var rows = sm.getSelections();

        // Move option only if:
        // 1. It is not the first, if moving up
        // 2. It is not the last, if moving down
        var index;
        var booMove = false;
        var booUp = action === 'move_option_up';
        if (optionsStore.getCount() > 0) {
            if (booUp && selectedRow > 0) {
                index = selectedRow - 1;
                booMove = true;
            } else if (!booUp && selectedRow < optionsStore.getCount() - 1) {
                index = selectedRow + 1;
                booMove = true;
            }
        }

        if (booMove) {
            var row = optionsStore.getAt(selectedRow);
            optionsStore.removeAt(selectedRow);
            optionsStore.insert(index, row);

            // Update order for each record
            for (i = 0; i < optionsStore.getCount(); i++) {
                var rec = optionsStore.getAt(i);

                if (rec.data.option_order != i) {
                    rec.beginEdit();
                    rec.set('option_order', i);
                    rec.endEdit();
                }
            }

            var movedRow = optionsStore.getAt(index);
            sm.selectRecords(movedRow);
        }
    },

    renameOption: function () {
        var thisDialog = this;
        var selected = thisDialog.optionsGrid.getSelectionModel().getSelected();

        if (selected) {
            thisDialog.addEditOption(selected.data.option_id, selected);
        }
    },

    addEditOption: function (optionId, rec) {
        var thisDialog = this;
        var sm = thisDialog.optionsGrid.getSelectionModel();
        var optionsStore = thisDialog.optionsGrid.store;

        var wnd = new Ext.Window({
            title: empty(optionId) ? '<i class="las la-plus"></i>' + _('Add Option') : '<i class="lar la-edit"></i>' + _('Rename Option'),
            layout: 'form',
            modal: true,
            resizable: false,
            plain: false,
            autoHeight: true,
            autoWidth: true,
            labelAlign: 'top',
            rec: rec,

            items: {
                xtype: 'textfield',
                fieldLabel: _('Option Name'),
                emptyText: _('Please enter the name...'),
                ref: 'tmpOptionName',
                allowBlank: false,
                width: 400,
                value: empty(optionId) ? '' : rec.data.option_name
            },

            buttons: [
                {
                    text: _('Cancel'),
                    handler: function () {
                        wnd.close();
                    }
                }, {
                    text: _('Save'),
                    cls: 'orange-btn',
                    handler: function () {
                        if (wnd['tmpOptionName'].isValid()) {
                            if (empty(optionId)) {
                                // Create a new record, add it to the bottom
                                var maxOrder = 0;
                                for (var i = 0; i < optionsStore.getCount(); i++) {
                                    var rec = optionsStore.getAt(i);
                                    maxOrder = Math.max(maxOrder, rec.data['option_order']);
                                }

                                var newRecord = new thisDialog.optionRecord({
                                    option_id: 0,
                                    option_name: wnd['tmpOptionName'].getValue(),
                                    option_deleted: false,
                                    option_order: maxOrder + 1
                                });

                                optionsStore.insert(optionsStore.getCount(), newRecord);
                            } else {
                                wnd.rec.beginEdit();
                                wnd.rec.set('option_name', wnd['tmpOptionName'].getValue());
                                wnd.rec.endEdit();
                                wnd.rec.commit();
                            }
                            wnd.close();
                        }
                    }
                }
            ],

            listeners: {
                'show': function () {
                    wnd['tmpOptionName'].clearInvalid();
                    wnd['tmpOptionName'].focus(false, 50);
                }
            }
        });

        wnd.show();
    },

    saveOptions: function () {
        var thisDialog = this;
        var options = [];
        thisDialog.optionsGrid.store.each(function (rec) {
            options.push(rec.data)
        });

        thisDialog.getEl().mask('Saving...');
        Ext.Ajax.request({
            url: thisDialog.booCaseField ? baseUrl + '/superadmin/manage-fields-groups/manage-options' : baseUrl + '/superadmin/manage-applicant-fields-groups/manage-options',
            params: {
                field_id: Ext.encode(thisDialog.fieldId),
                options_list: Ext.encode(options)
            },

            success: function (f) {
                var result = Ext.decode(f.responseText);

                thisDialog.getEl().unmask();
                if (result.success) {
                    if (result.refresh) {
                        Ext.simpleConfirmation.info(_('Changes were saved successfully. Please refresh the page to see the latest changes.'));
                    }

                    thisDialog.close();
                } else {
                    var showMsg = empty(result.message) ? _('Changes cannot be saved. Please try again later.') : result.message;
                    Ext.simpleConfirmation.error(showMsg);
                }
            },
            failure: function () {
                thisDialog.getEl().unmask();
                Ext.simpleConfirmation.error(_('Options cannot be changed. Please try again later.'));
            }
        });
    }
});