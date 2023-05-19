var ConditionalFieldsGrid = function (config) {
    Ext.apply(this, config);
    var thisGrid = this;

    this.store = new Ext.data.Store({
        remoteSort: false,
        autoLoad:   false,

        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/conditional-fields/list'
        }),

        baseParams: {
            field_id:         thisGrid.field_id,
            case_template_id: thisGrid.case_template_id
        },

        reader: new Ext.data.JsonReader({
            root:          'rows',
            totalProperty: 'totalCount',
            idProperty:    'field_condition_id',

            fields: [
                'field_condition_id',
                'field_option_id',
                'field_option_label',
                'field_condition_hidden_groups_and_fields',
                'field_condition_hidden_fields',
                'field_condition_hidden_groups'
            ]
        })
    });
    this.store.setDefaultSort('field_option_label', 'ASC');

    var sm        = new Ext.grid.CheckboxSelectionModel();
    var mainColId = Ext.id();
    this.columns  = [
        sm, {
            header:    _('Option'),
            dataIndex: 'field_option_label',
            sortable:  true,
            width:     200,
            renderer: function (val) {
                return String.format(
                    "<div ext:qtip='{0}'>{0}</div>",
                    val.replaceAll("'", "&#39;")
                );
            }
        }, {
            id:        mainColId,
            header:    _('Hidden Groups/Fields'),
            dataIndex: 'field_condition_hidden_groups_and_fields',
            width:     300,
            sortable:  true,
            renderer: function (val) {
                return String.format(
                    "<div ext:qtip='{0}'>{0}</div>",
                    val.replaceAll("'", "&#39;")
                );
            }
        }
    ];

    ConditionalFieldsGrid.superclass.constructor.call(this, {
        sm: sm,

        cls:              'extjs-grid-thin-no-specific-height',
        height:           300,
        autoExpandColumn: mainColId,
        autoExpandMin:    150,

        viewConfig: {
            emptyText: _('There are no records to show.'),
            forceFit:  true
        },

        loadMask:   {msg: _('Loading...')},
        stripeRows: true,

        tbar: [
            {
                xtype: 'buttongroup',
                items: {
                    text:    '<i class="las la-plus"></i>' + _('Add Condition'),
                    handler: this.addCondition.createDelegate(this)
                }
            }, {
                xtype: 'buttongroup',
                items: {
                    text:     '<i class="las la-edit"></i>' + _('Edit Condition'),
                    ref:      '../../editConditionBtn',
                    disabled: true,
                    handler:  this.editCondition.createDelegate(this)
                }
            }, {
                xtype: 'buttongroup',
                items: {
                    text:     '<i class="las la-trash"></i>' + _('Delete Condition'),
                    ref:      '../../deleteConditionBtn',
                    disabled: true,
                    handler:  this.deleteRecords.createDelegate(this)
                }
            }, '->', {
                icon:    baseUrl + '/images/refresh.png',
                cls:     'x-btn-icon',
                handler: function () {
                    thisGrid.store.reload();
                }
            }
        ]
    });

    this.on('rowdblclick', this.editCondition.createDelegate(this), this);
    this.getSelectionModel().on('selectionchange', this.updateToolbarButtons, this);
};

Ext.extend(ConditionalFieldsGrid, Ext.grid.GridPanel, {
    updateToolbarButtons: function () {
        var sel = this.getSelectionModel().getSelections();

        var booIsSelectedAtLeastOne = sel.length >= 1;

        this.editConditionBtn.setDisabled(!booIsSelectedAtLeastOne);
        this.deleteConditionBtn.setDisabled(!booIsSelectedAtLeastOne);
    },

    addCondition: function () {
        var thisGrid = this;

        var wnd = new ConditionalFieldsDialog({
            mode: 'add',
            caseTemplateId: thisGrid.case_template_id,
            conditionFieldId: thisGrid.field_id,
            fieldOptions: thisGrid.field_options,
            fieldType: thisGrid.field_type,
            arrSelectedOptions: [],
            arrGroupedFields: thisGrid.store.reader.jsonData.arrGroupedFields,
            parentGrid: thisGrid
        });

        wnd.showDialog();
    },

    editCondition: function () {
        var thisGrid = this;

        var wnd = new ConditionalFieldsDialog({
            mode: 'edit',
            caseTemplateId: thisGrid.case_template_id,
            conditionFieldId: thisGrid.field_id,
            fieldOptions: thisGrid.field_options,
            fieldType: thisGrid.field_type,
            arrSelectedOptions: thisGrid.getSelectionModel().getSelections(),
            arrGroupedFields: thisGrid.store.reader.jsonData.arrGroupedFields,
            parentGrid: thisGrid
        });

        wnd.showDialog();
    },

    deleteRecords: function () {
        var thisGrid = this;
        var arrSelectedRecords = this.getSelectionModel().getSelections();

        var question = '';
        if (arrSelectedRecords.length > 1) {
            question = String.format(
                _('Are you sure you want to delete these {0} selected records?'),
                arrSelectedRecords.length
            );
        } else {
            question = String.format(
                _('Are you sure you want to delete <i>{0}</i>?'),
                arrSelectedRecords[0].data['field_option_label']
            );
        }

        Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
            if (btn === 'yes') {
                var arrRecordsIds = [];
                for (var i = 0; i < arrSelectedRecords.length; i++) {
                    arrRecordsIds.push(arrSelectedRecords[i].data['field_condition_id']);
                }

                var win = Ext.getCmp('edit-field-window');
                win.getEl().mask(_('Deleting...'));
                Ext.Ajax.request({
                    url: baseUrl + '/conditional-fields/delete',

                    params: {
                        records:          Ext.encode(arrRecordsIds),
                        case_template_id: thisGrid.case_template_id,
                        field_id:         thisGrid.field_id
                    },

                    success: function (res) {
                        win.getEl().unmask();
                        thisGrid.store.reload();

                        var result = Ext.decode(res.responseText);
                        arrReadableConditions = result.conditions;
                        showGroupsAndFieldsReadableConditions();
                    },

                    failure: function (response) {
                        win.getEl().unmask();

                        var errorMessage = _('Internal error. Please try again later.');
                        try {
                            var resultData = Ext.decode(response.responseText);
                            if (resultData && resultData.message) {
                                errorMessage = resultData.message;
                            }
                        } catch (e) {
                        }

                        Ext.simpleConfirmation.error(errorMessage);
                    }
                });
            }
        });
    }
});
