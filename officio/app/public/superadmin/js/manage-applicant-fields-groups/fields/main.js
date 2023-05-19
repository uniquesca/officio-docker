var field_types_store;
var fieldInfo = Ext.data.Record.create([
    {name: 'member_type_id', type: 'int'},
    {name: 'group_id', type: 'int'},
    {name: 'field_id', type: 'int'},
    {name: 'field_company_id', type: 'string'},
    {name: 'field_type', type: 'int'},
    {name: 'field_type_name', type: 'string'},
    {name: 'field_label', type: 'string'},
    {name: 'field_default_value', type: 'string'},
    {name: 'field_encrypted', type: 'boolean'},
    {name: 'field_required', type: 'boolean'},
    {name: 'field_required_for_submission', type: 'boolean'},
    {name: 'field_disabled', type: 'boolean'},
    {name: 'field_use_full_row', type: 'boolean'},
    {name: 'field_skip_access_requirements', type: 'boolean'},
    {name: 'field_multiple_values', type: 'boolean'},
    {name: 'field_can_edit_in_gui', type: 'boolean'},
    {name: 'field_maxlength', type: 'int'},
    {name: 'field_custom_height', type: 'int'},
    {name: 'field_options'},
    {name: 'field_boo_with_maxlength', type: 'boolean'},
    {name: 'field_boo_with_custom_height', type: 'boolean'},
    {name: 'field_boo_with_options', type: 'boolean'},
    {name: 'field_boo_with_default', type: 'boolean'},
    {name: 'field_default_access'}
]);

// Load order information about the field
var getFieldDetails = function (field_id) {
    var field = $('#' + field_id);

    return {
        field_col: field.closest('td')[0].cellIndex,
        field_row: field.closest('tr')[0].rowIndex,
        field_use_full_row: field.hasClass('field_use_full_row') ? 1 : 0,
        field_skip_access_requirements: field.hasClass('field_skip_access_requirements') ? 1 : 0,
        field_multiple_values: field.hasClass('field_multiple_values') ? 1 : 0,
        field_can_edit_in_gui: field.hasClass('field_can_edit_in_gui') ? 1 : 0,
        field_id: field_id,
        group_id: field.parents(".portlet:first")[0].id
    };
};

// Collect order info for all fields
var getFieldsOrder = function () {
    var arrFields = [];
    $(".field_container").each(function () {
        var field_id = this.id;
        arrFields[arrFields.length] = getFieldDetails(field_id);
    });
    return arrFields;
};

var removeFieldFromHtml = function (blockId, fieldId) {
    $('#block_column_' + blockId).find('.field_container').each(function () {
        var match = $(this).attr('id').match(/^field_([\d]{1,})_([\d]{1,})$/i);
        if (match != null && match[2] == fieldId) {
            $(this).remove();
        }
    });
};

var addFieldToHtml = function (groupId, fieldId, fieldName, isFieldEncrypted, isFieldRequired, isFieldDisabled, isFieldBlocked, booUseFullRow, booSkipAccessRequirements) {
    var arrClasses = ['field_container'];
    if (isFieldBlocked) {
        arrClasses.push('field_blocked');
    } else {
        arrClasses.push('field_container_edit');
    }

    if (isFieldEncrypted)
        arrClasses.push('field_encrypted');

    if (isFieldRequired)
        arrClasses.push('field_required');

    if (isFieldDisabled)
        arrClasses.push('field_disabled');

    if (booUseFullRow)
        arrClasses.push('field_use_full_row');

    if (booSkipAccessRequirements)
        arrClasses.push('field_skip_access_requirements');

    var newFieldHtml = '<div class="' + implode(' ', arrClasses) + '" id="field_' + groupId + '_' + fieldId + '">' +
        '<span class="group_field_name">' + fieldName + '</span>' +
        '</div>';

    // Search for the first empty td
    var $emptyTd = '';
    $("#fields_group_" + groupId).find("td").each(function (i, el) {
        if ($(el).html() === '' && empty($emptyTd)) {
            $emptyTd = $(el);
        }
    });

    // If there are no empty TDs - create a new row and use the first TD
    if (empty($emptyTd)) {
        var $tr = $('#fields_group_' + groupId).find('tr:last');
        if ($tr) {
            var $clone = $tr.clone();
            $clone.find('td').empty();
            $tr.after($clone);
            $emptyTd = $clone.find('td:first');
            makeFieldsSortable();
        }
    }

    if (!empty($emptyTd)) {
        $emptyTd.append(newFieldHtml);
        initFieldsActions();
    }
};

var setFieldName = function (fieldId, newName) {
    $('#' + fieldId).find('.group_field_name:first').html(newName);
};

var updateFieldClass = function (fieldId, booEncrypted, booRequired, booDisabled, booUseFullRow, booSkipAccessRequirements) {
    if (booEncrypted) {
        $('#' + fieldId).addClass("field_encrypted");
    } else {
        $('#' + fieldId).removeClass("field_encrypted");
    }

    if (booRequired) {
        $('#' + fieldId).addClass("field_required");
    } else {
        $('#' + fieldId).removeClass("field_required");
    }

    if (booDisabled) {
        $('#' + fieldId).addClass("field_disabled");
    } else {
        $('#' + fieldId).removeClass("field_disabled");
    }

    if (booUseFullRow) {
        $('#' + fieldId).addClass("field_use_full_row");
    } else {
        $('#' + fieldId).removeClass("field_use_full_row");
    }

    if (booSkipAccessRequirements) {
        $('#' + fieldId).addClass("field_skip_access_requirements");
    } else {
        $('#' + fieldId).removeClass("field_skip_access_requirements");
    }
};


var deleteField = function (blockId, groupId, fieldId, fieldName) {
    Ext.MessageBox.buttonText.yes = "Yes, delete this field";
    Ext.MessageBox.buttonText.no = 'No';
    Ext.MessageBox.confirm('Please confirm', 'Are you sure you want to delete field <span style="color: green; font-style: italic;">' + fieldName + '</span> ?', function (btn) {
        if (btn == 'yes') {
            body = Ext.getBody();
            body.mask('Deleting...');

            Ext.Ajax.request({
                url: submissionUrl + "/delete-field",
                params: {
                    company_id: companyId,
                    member_type: Ext.encode(memberType),
                    group_id: Ext.encode(groupId),
                    field_id: Ext.encode(fieldId)
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (!resultData.error) {

                        // Remove this group from html
                        removeFieldFromHtml(blockId, fieldId);


                        // Show a confirmation
                        body.mask('Done !');
                        fixManageFieldsPageHeight();
                        setTimeout(function () {
                            body.unmask();
                        }, confirmationTimeOut);

                    } else {
                        body.unmask();
                        Ext.simpleConfirmation.error(resultData.message);
                    }
                },

                failure: function () {
                    body.unmask();
                    Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                }
            });
        }
    });
};

var loadFieldInfo = function (groupId, fieldId) {
    var body = Ext.getBody();
    body.mask('Loading...');

    Ext.Ajax.request({
        url: submissionUrl + '/get-field-info',
        params: {
            company_id: companyId,
            field_id: Ext.encode(fieldId)
        },

        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                var thisFieldInfo = resultData.field_info;

                // Assign field info
                var newField = new fieldInfo({
                    member_type_id: thisFieldInfo.member_type_id,
                    group_id: groupId,
                    field_id: fieldId,
                    field_type: thisFieldInfo.type,
                    field_company_id: thisFieldInfo.company_field_id,
                    field_type_name: thisFieldInfo.type_label,
                    field_label: thisFieldInfo.label,
                    field_encrypted: thisFieldInfo.encrypted === 'Y',
                    field_required: thisFieldInfo.required === 'Y',
                    field_required_for_submission: thisFieldInfo.required_for_submission === 'Y',
                    field_disabled: thisFieldInfo.disabled === 'Y',
                    field_use_full_row: thisFieldInfo.use_full_row === 'Y',
                    field_skip_access_requirements: thisFieldInfo.skip_access_requirements === 'Y',
                    field_multiple_values: thisFieldInfo.multiple_values === 'Y',
                    field_can_edit_in_gui: thisFieldInfo.can_edit_in_gui === 'Y',
                    field_maxlength: thisFieldInfo.maxlength,
                    field_custom_height: thisFieldInfo.custom_height,
                    field_options: thisFieldInfo.default_val,
                    field_blocked: thisFieldInfo.blocked === 'Y',
                    field_default_access: thisFieldInfo.field_default_access,

                    field_boo_with_maxlength: thisFieldInfo.booWithMaxLength,
                    field_boo_with_options: thisFieldInfo.booWithOptions,
                    field_boo_with_default: thisFieldInfo.booWithDefaultValue,
                    field_boo_with_custom_height: thisFieldInfo.booWithCustomHeight
                });

                // Show edit field dialog
                showAddEditFieldDialog(newField);

                body.unmask();
            } else {
                body.unmask();
                Ext.simpleConfirmation.error(resultData.message);
            }
        },

        failure: function () {
            body.unmask();
            Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
        }
    });
};

var showAddEditFieldDialog = function (fieldInfo) {
    // Default values
    var groupId = fieldInfo.data.group_id;
    var fieldId = 0;
    var fieldCompanyId = 'new_field';
    var fieldLabel = 'New field';
    var fieldEncrypted = 0;
    var fieldRequired = 0;
    var fieldRequiredForSubmission = 0;
    var fieldDisabled = 0;
    var fieldUseFullRow = 0;
    var fieldSkipAccessRequirements = 0;
    var fieldMultipleValues = 0;
    var fieldCanEditInGui = 0;
    var fieldSize = '';
    var fieldCustomHeight = '';
    var fieldDefaultValue = '';
    var fieldOptions = [];

    var wndTitle = 'Create new field';
    var btnSubmitTitle = 'Create field';

    var booHideDefaultValue = true;
    var booHideMultipleValues = true;
    var booHideCanEditInGui = true;
    var booHideCustomHeight = true;
    var booHideOptionsGrid = true;
    var booHideImageField = true;

    var booFieldCompanyIdDisabled = false;

    if (fieldInfo.data.field_id !== 0) {
        fieldId = fieldInfo.data.field_id;

        if (fieldInfo.data.field_type === '13') {
            fieldRequired = 1;
        }

        if (fieldInfo.data.field_type === '42') {
            booHideMultipleValues = false;
        }

        if (fieldInfo.data.field_type === '3' || fieldInfo.data.field_type === '40') {
            booHideCanEditInGui = false;
        }

        fieldCompanyId = fieldInfo.data.field_company_id;
        booFieldCompanyIdDisabled = true;

        booHideImageField = fieldInfo.data.field_type != '16';
        booHideOptionsGrid = !(fieldInfo.data.field_boo_with_options && booHideImageField);
        booHideDefaultValue = !fieldInfo.data.field_boo_with_default;
        booHideCustomHeight = !fieldInfo.data.field_boo_with_custom_height;

        wndTitle = 'Edit field details';
        btnSubmitTitle = 'Update field';
    }

    if (!field_types_store) {
        field_types_store = new Ext.data.Store({
            data: fieldTypesList,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
                    {
                        name: 'id',
                        type: 'int'
                    }, {
                        name: 'label',
                        type: 'string'
                    }, {
                        name: 'booCanBeEncrypted',
                        type: 'boolean'
                    }, {
                        name: 'booWithMaxLength',
                        type: 'boolean'
                    }, {
                        name: 'booWithCustomHeight',
                        type: 'boolean'
                    }, {
                        name: 'booWithOptions',
                        type: 'boolean'
                    }, {
                        name: 'booWithDefaultValue',
                        type: 'boolean'
                    }
                ]
            ))
        });
    }

    var fields_list = new Ext.form.ComboBox({
        id: 'field_type',
        mode: 'local',
        fieldLabel: 'Field Type',
        emptyText: 'Please select a type...',
        store: field_types_store,
        displayField: 'label',
        valueField: 'id',
        triggerAction: 'all',
        selectOnFocus: true,
        searchContains: true,
        required: true,
        disabled: fieldInfo.data.field_blocked,
        width: 230,
        listeners: {
            beforeselect: function (n, selRecord) {

                if (fieldInfo.data.field_id === 0) {
                    if (selRecord.data.id == '39') {
                        Ext.getCmp('field_company_id').setDisabled(true);
                        Ext.getCmp('field_company_id').setValue('applicant_internal_id');
                    } else {
                        Ext.getCmp('field_company_id').setDisabled(false);
                    }
                }

                // Enable/disable encryption checkbox
                if (!selRecord.data.booCanBeEncrypted) {
                    Ext.getCmp('field_encrypted').setValue(false);
                }
                Ext.getCmp('field_encrypted').setDisabled(!selRecord.data.booCanBeEncrypted);

                // Show/hide max size textbox
                Ext.getCmp('field_max_length').setVisible(selRecord.data.booWithMaxLength);

                // Show/hide custom height field
                Ext.getCmp('field_custom_height').setVisible(selRecord.data.booWithCustomHeight);

                // Show/hide default value textbox
                Ext.getCmp('field_default_value').setVisible(selRecord.data.booWithDefaultValue);

                Ext.getCmp('field_multiple_values').setVisible(selRecord.data.id == '42');
                if (selRecord.data.id == '3' || selRecord.data.id == '40') {
                    Ext.getCmp('field_can_edit_in_gui').setVisible(true);
                } else {
                    Ext.getCmp('field_can_edit_in_gui').setVisible(false);
                    Ext.getCmp('field_can_edit_in_gui').setValue(false);
                }

                // Show/hide options grid
                Ext.getCmp('image_field').hide();
                Ext.getCmp('field_options').hide();
                if (selRecord.data.booWithOptions) {
                    if (selRecord.data.id == '16') {
                        Ext.getCmp('image_field').show();
                    } else {
                        Ext.getCmp('field_options').show();
                    }
                }

                Ext.getCmp('edit-field-window').syncShadow();

            }
        }
    });

    function title_img(val) {
        return 'Place <i>' + val + '</i> here';
    }

    var selectedRow = 0;

    function moveOption(booUp, selectedRow) {
        var sm = options_grid.getSelectionModel();
        var last_selected = sm.last;
        var rows = sm.getSelections();

        // Move option only if:
        // 1. It is not the first, if moving up
        // 2. It is not the last, if moving down
        var booMove = false;
        if (options_store.getCount() > 0) {
            if (booUp && selectedRow > 0) {
                cindex = selectedRow - 1;
                booMove = true;
            } else if (!booUp && selectedRow < options_store.getCount() - 1) {
                cindex = selectedRow + 1;
                booMove = true;
            }
        }

        if (sm.hasSelection() && booMove) {

            for (i = 0; i < rows.length; i++) {
                options_store.remove(options_store.getById(rows[i].id));
                options_store.insert(cindex, rows[i]);
            }

            // Update order for each of transaction
            for (i = 0; i < options_store.getCount(); i++) {
                rec = options_store.getAt(i);

                if (rec.data.option_order != i) {
                    rec.beginEdit();
                    rec.set("option_order", i);

                    // Mark as dirty
                    var oldName = rec.data.option_name;
                    rec.set("option_name", oldName + ' ');
                    rec.set("option_name", oldName);
                    rec.endEdit();
                }
            }

            var movedRow = options_store.getAt(cindex);
            sm.selectRecords(movedRow);
        }

        if (booUp) {
            last_selected = last_selected - 1;
            if (Ext.isIE) {
                sm.selectRow(last_selected);
            } else {
                sm.selectPrevious.defer(1, sm);
            }
        } else {
            last_selected = last_selected + 1;
            if (Ext.isIE) {
                sm.selectRow(last_selected);
            } else {
                sm.selectNext.defer(1, sm);
            }
        }
    }


    var action = new Ext.ux.grid.RowActions({
        header: 'Order',
        keepSelection: true,

        actions: [{
            iconCls: 'move_option_up',
            tooltip: 'Move option Up'
        }, {
            iconCls: 'move_option_down',
            tooltip: 'Move option Down'
        }],

        callbacks: {
            'move_option_up': function (grid, record, action, row) {
                moveOption(true, row);
            },
            'move_option_down': function (grid, record, action, row) {
                moveOption(false, row);
            }
        }
    });

    var cm = new Ext.grid.ColumnModel({
        columns: [
            {
                header: "Option Name",
                dataIndex: 'option_name',
                width: 320,
                editor: new Ext.form.TextField({
                    allowBlank: false
                })
            },
            action
        ],
        defaultSortable: true
    });

    var arrOptionFields = [
        {name: 'option_id', type: 'int'},
        {name: 'option_name', type: 'string'},
        {name: 'option_order', type: 'int'}
    ];

    var sm = new Ext.grid.RowSelectionModel({
        singleSelect: true,
        listeners: {
            beforerowselect: function (sm, i, ke, row) {
                options_grid.ddText = title_img(row.data.option_name);
            },

            rowselect: function (sm, rowIndex) {
                selectedRow = rowIndex;
            }
        }
    });

    var Option = Ext.data.Record.create(arrOptionFields);

    var options_store = new Ext.data.SimpleStore({
        fields: arrOptionFields,
        baseParams: {
            withoutOther: true
        },

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, Option)
    });

    var imageFieldSet = new Ext.form.FieldSet({
        id: 'image_field',
        height: 22,
        checkboxToggle: false,
        style: 'border:none; padding:0; ',
        hidden: booHideImageField,
        items: {
            layout: 'table',
            layoutConfig: {columns: 4},
            items: [{
                html: '<span style="color: #000">Image width: &nbsp;</span>'
            }, {
                id: 'field_image_width',
                xtype: 'numberfield',
                name: 'width',
                allowDecimals: false,
                allowNegative: false
            }, {
                html: '<span style="color: #000; padding-left: 80px;">&nbsp; Image height: &nbsp;</span>'
            }, {
                id: 'field_image_height',
                xtype: 'numberfield',
                name: 'height',
                allowDecimals: false,
                allowNegative: false
            }]
        }
    });

    // create the editor grid
    var options_grid = new Ext.grid.EditorGridPanel({
        id: 'field_options',
        store: options_store,
        cm: cm,
        sm: sm,
        plugins: [action],
        ddGroup: 'types-grid-dd',
        ddText: 'Place this row.',
        enableDragDrop: true,
        cls: 'extjs-grid-thin',
        width: 540,
        height: 200,
        viewConfig: {
            forceFit: true
        },

        clicksToEdit: 2,
        hidden: booHideOptionsGrid,

        tbar: [{
            text: '<i class="las la-plus"></i>' + _('Add Option'),
            handler: function () {
                var p = new Option({
                    option_id: 0,
                    option_name: '',
                    option_order: options_store.data.length
                });
                options_grid.stopEditing();
                options_store.insert(options_store.data.length, p);

                // Hack to make it marked as dirty
                var record = options_store.getAt(options_store.data.length - 1);
                record.beginEdit();
                record.set("option_name", 'New option');
                record.endEdit();
                options_grid.startEditing(options_store.data.length - 1, 0);
            }
        }, {
            text: '<i class="las la-trash"></i>' + _('Delete Option'),
            handler: function () {
                var thisGrid = Ext.getCmp('field_options');
                var selected = thisGrid.getSelectionModel().getSelected();

                if (selected) {
                    Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete selected option?',
                        function (btn) {
                            if (btn == 'yes') {
                                thisGrid.store.remove(selected);
                            }
                        }
                    );
                }
            }
        }],
        listeners: {
            mouseover: function (e, t) {
                var row;
                if ((row = this.getView().findRowIndex(t)) !== false) {
                    this.getView().addRowClass(row, "x-grid3-row-over");
                }
            },

            mouseout: function (e, t) {
                var row;
                if ((row = this.getView().findRowIndex(t)) !== false && row !== this.getView().findRowIndex(e.getRelatedTarget())) {
                    this.getView().removeRowClass(row, "x-grid3-row-over");
                }
            },

            render: function () {
                new Ext.dd.DropTarget(this.getView().mainBody, {
                    ddGroup: 'types-grid-dd',
                    notifyDrop: function (dd, e) {

                        var sm = options_grid.getSelectionModel();
                        var rows = sm.getSelections();
                        var cindex = dd.getDragData(e).rowIndex;
                        if (sm.hasSelection()) {
                            for (i = 0; i < rows.length; i++) {
                                options_store.remove(options_store.getById(rows[i].id));
                                options_store.insert(cindex, rows[i]);
                            }

                            // Update order for each of transaction
                            for (i = 0; i < options_store.getCount(); i++) {
                                rec = options_store.getAt(i);

                                if (rec.data.option_order != i) {
                                    rec.beginEdit();
                                    rec.set("option_order", i);

                                    // Mark as dirty
                                    var oldName = rec.data.option_name;
                                    rec.set("option_name", oldName + ' ');
                                    rec.set("option_name", oldName);
                                    rec.endEdit();
                                }
                            }

                            sm.selectRecords(rows);
                        }

                    }
                });
            }
        }
    });

    // trigger the data store load
    options_store.loadData(fieldOptions);

    var frm = new Ext.FormPanel({
        title: 'Properties',
        labelWidth: 150, // label settings here cascade unless overridden
        bodyStyle: 'padding:5px;',
        autoWidth: true,
        autoHeight: true,
        defaultType: 'textfield',

        items: [
            {
                id: 'field_company_id',
                name: 'field_company_id',
                fieldLabel: 'Field Name',
                value: fieldCompanyId,
                width: 230,
                disabled: booFieldCompanyIdDisabled,
                allowBlank: false,
                maskRe: /[A-Za-z0-9_\-]/,
                regex: /^[a-zA-Z0-9_\-]*$/,
                regexText: 'Only alphanumeric, hyphen and underscore symbols are only allowed'
            }, {
                id: 'field_label',
                name: 'field_label',
                fieldLabel: 'Field Label',
                value: fieldLabel,
                width: 230,
                allowBlank: false
            },
            fields_list,
            {
                id: 'field_encrypted',
                name: 'field_encrypted',
                fieldLabel: 'Encrypted',
                xtype: 'checkbox',
                value: fieldEncrypted
            }, {
                id: 'field_required',
                name: 'field_required',
                fieldLabel: 'Required',
                xtype: 'checkbox',
                value: fieldRequired
            }, {
                id: 'field_required_for_submission',
                name: 'field_required_for_submission',
                fieldLabel: 'Required for Submission',
                xtype: 'checkbox',
                value: fieldRequiredForSubmission,
                hidden: !booIsAuthorisedAgentsManagementEnabled
            }, {
                id: 'field_disabled',
                name: 'field_disabled',
                fieldLabel: 'Disabled',
                xtype: 'checkbox',
                value: fieldDisabled
            }, {
                id: 'field_use_full_row',
                name: 'field_use_full_row',
                fieldLabel: 'Use full row',
                xtype: 'checkbox',
                value: fieldUseFullRow
            }, {
                id: 'field_skip_access_requirements',
                name: 'field_skip_access_requirements',
                fieldLabel: 'Skip Access Requirements',
                xtype: 'checkbox',
                value: fieldSkipAccessRequirements
            }, {
                id: 'field_multiple_values',
                name: 'field_multiple_values',
                fieldLabel: 'Multiple Values',
                xtype: 'checkbox',
                value: fieldMultipleValues,
                hidden: booHideMultipleValues
            }, {
                id: 'field_can_edit_in_gui',
                name: 'field_can_edit_in_gui',
                fieldLabel: _('Admin - custom options'),
                xtype: 'checkbox',
                value: fieldCanEditInGui,
                hidden: booHideCanEditInGui
            }, {
                id: 'field_max_length',
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: false,
                name: 'field_max_length',
                fieldLabel: 'Max Length',
                width: 50,
                value: empty(fieldSize) ? null : fieldSize,
                hidden: true
            }, {
                id: 'field_default_value',
                name: 'field_default_value',
                fieldLabel: 'Default Value',
                value: fieldDefaultValue,
                width: 230,
                hidden: booHideDefaultValue
            }, {
                id: 'field_custom_height',
                name: 'field_custom_height',
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: false,
                fieldLabel: 'Custom Height',
                width: 50,
                value: empty(fieldCustomHeight) ? null : fieldCustomHeight,
                hidden: booHideCustomHeight
            },
            options_grid,
            imageFieldSet
        ]
    });

    var arrRoleRadios = [];
    arrRoleRadios.push({
        xtype: 'box',
        width: 200,
        autoEl: {
            tag: 'div',
            style: 'font-size: 14px; font-weight: bold',
            html: 'Roles'
        }
    });
    arrRoleRadios.push({
        xtype: 'box',
        colspan: 3,
        autoEl: {
            tag: 'div',
            style: 'font-size: 14px; font-weight: bold; width: 100px; margin: 0 auto;',
            html: 'Access Level'
        }
    });

    // We cannot change the original array
    var arrRolesCloned = arrRoles.slice(0);
    arrRolesCloned.push({
        'role_id': 0,
        'role_name': 'All Future Roles'
    });

    for (var i = 0; i < arrRolesCloned.length; i++) {
        arrRoleRadios.push({
            xtype: 'box',
            width: 200,
            cellCls: i === arrRolesCloned.length - 1 ? 'access-rights-cell-highlight' : 'access-rights-cell',
            autoEl: {
                tag: 'div',
                html: arrRolesCloned[i]['role_name']
            }
        });

        var access = '';
        for (var j = 0; j < fieldInfo.data.field_default_access.length; j++) {
            fieldInfo.data.field_default_access[j]['role_id'] = empty(fieldInfo.data.field_default_access[j]['role_id']) ? 0 : fieldInfo.data.field_default_access[j]['role_id'];
            if (fieldInfo.data.field_default_access[j]['role_id'] === arrRolesCloned[i]['role_id']) {
                access = fieldInfo.data.field_default_access[j]['access'];
            }
        }

        arrRoleRadios.push({
            xtype: 'radio',
            boxLabel: 'No Access',
            name: 'role_' + arrRolesCloned[i]['role_id'] + '_default_access',
            inputValue: '',
            checked: access === '',
            cellCls: i === arrRolesCloned.length - 1 ? 'access-rights-cell-highlight' : 'access-rights-cell'
        });

        arrRoleRadios.push({
            xtype: 'radio',
            boxLabel: 'Read',
            name: 'role_' + arrRolesCloned[i]['role_id'] + '_default_access',
            inputValue: 'R',
            checked: access === 'R',
            cellCls: i === arrRolesCloned.length - 1 ? 'access-rights-cell-highlight' : 'access-rights-cell'
        });

        arrRoleRadios.push({
            xtype: 'radio',
            boxLabel: 'Read & Write',
            name: 'role_' + arrRolesCloned[i]['role_id'] + '_default_access',
            inputValue: 'F',
            checked: access === 'F',
            cellCls: i === arrRolesCloned.length - 1 ? 'access-rights-cell-highlight' : 'access-rights-cell'
        });
    }

    var accessRightsForm = new Ext.form.FormPanel({
        title: 'Access Rights',
        xtype: 'form',
        layout: 'table',
        cls: 'x-table-layout-cell-top-align x-table-layout-hover',

        layoutConfig: {
            columns: 4,
            tableAttrs: {
                style: {
                    width: '100%'
                }
            }
        },

        items: arrRoleRadios
    });

    var win = new Ext.Window({
        id: 'edit-field-window',
        title: wndTitle,
        width: 580,
        autoHeight: true,
        plain: true,
        modal: true,
        resizable: false,

        items: {
            xtype: 'tabpanel',
            plain: true,
            border: false,
            activeTab: 0,
            deferredRender: false,
            defaults: {bodyStyle: 'padding:10px'},

            items: [
                frm,
                accessRightsForm
            ],

            listeners: {
                tabchange: function () {
                    win.syncShadow();
                }
            }
        },


        listeners: {
            'beforeshow': function () {
                if (fieldInfo.data.field_id !== 0) {
                    if (fieldInfo.data.field_label) {
                        Ext.getCmp('field_label').setValue(fieldInfo.data.field_label);
                    }

                    if (fieldInfo.data.field_type) {
                        Ext.getCmp('field_type').setValue(fieldInfo.data.field_type);
                    }

                    if (fieldInfo.data.field_encrypted) {
                        Ext.getCmp('field_encrypted').setValue(true);
                    }

                    if (fieldInfo.data.field_required) {
                        Ext.getCmp('field_required').setValue(true);
                    }

                    if (fieldInfo.data.field_disabled) {
                        Ext.getCmp('field_disabled').setValue(true);
                    }

                    if (fieldInfo.data.field_use_full_row) {
                        Ext.getCmp('field_use_full_row').setValue(true);
                    }

                    if (fieldInfo.data.field_skip_access_requirements) {
                        Ext.getCmp('field_skip_access_requirements').setValue(true);
                    }

                    if (fieldInfo.data.field_type == '42') {
                        Ext.getCmp('field_multiple_values').show();
                        if (fieldInfo.data.field_multiple_values) {
                            Ext.getCmp('field_multiple_values').setValue(true);
                        }
                    }

                    if (fieldInfo.data.field_type == '3' || fieldInfo.data.field_type == '40') {
                        Ext.getCmp('field_can_edit_in_gui').show();
                        if (fieldInfo.data.field_can_edit_in_gui) {
                            Ext.getCmp('field_can_edit_in_gui').setValue(true);
                        }
                    } else {
                        Ext.getCmp('field_can_edit_in_gui').setValue(false);
                    }

                    if (fieldInfo.data.field_boo_with_options) {
                        if (fieldInfo.data.field_options) {
                            options_store.loadData(fieldInfo.data.field_options);
                            Ext.getCmp('field_options').hide();
                            Ext.getCmp('image_field').hide();
                            if (fieldInfo.data.field_type == 16) {
                                Ext.getCmp('image_field').show();
                            } else {
                                Ext.getCmp('field_options').show();
                            }
                        }
                    } else if (fieldInfo.data.field_boo_with_default) {
                        if (fieldInfo.data.field_options && fieldInfo.data.field_options[0][1]) {
                            Ext.getCmp('field_default_value').setValue(fieldInfo.data.field_options[0][1]);
                        }
                    }

                    if (fieldInfo.data.field_boo_with_maxlength) {
                        Ext.getCmp('field_max_length').show();
                        var max_length = fieldInfo.data.field_maxlength;
                        if (!empty(max_length)) {
                            Ext.getCmp('field_max_length').setValue(max_length);
                        }
                    }

                    if (fieldInfo.data.field_boo_with_custom_height) {
                        Ext.getCmp('field_custom_height').show();
                        var custom_height = fieldInfo.data.field_custom_height;
                        if (!empty(custom_height)) {
                            Ext.getCmp('field_custom_height').setValue(custom_height);
                        }
                    }

                    // If this is a 'divisions' field - set up its default settings
                    if (fieldInfo.data.field_type == '13' || fieldInfo.data.field_type == '24') {
                        Ext.getCmp('field_required').setValue(true);

                        // Ext.getCmp('field_label').setDisabled(true);
                        Ext.getCmp('field_type').setDisabled(true);
                        Ext.getCmp('field_required').setDisabled(true);
                        Ext.getCmp('field_disabled').hide();
                        Ext.getCmp('field_default_value').hide();
                    }

                    if (fieldInfo.data.field_type == '16' && fieldInfo.data.field_options) {
                        Ext.getCmp('field_image_width').setValue(fieldInfo.data.field_options[0][1]);
                        Ext.getCmp('field_image_height').setValue(fieldInfo.data.field_options[1][1]);
                    }

                    if (booIsAuthorisedAgentsManagementEnabled) {
                        Ext.getCmp('field_required_for_submission').show();
                        if (fieldInfo.data.field_required_for_submission) {
                            Ext.getCmp('field_required_for_submission').setValue(true);
                        }
                    }

                }

            }
        },


        buttons: [{
            text: btnSubmitTitle,
            disabled: !empty(fieldId) && fieldInfo.data.member_type_id != memberTypeId,
            tooltip: !empty(fieldId) && fieldInfo.data.member_type_id != memberTypeId ? 'You cannot change this field' : '',
            handler: function () {
                var booError = false;
                if (frm.getForm().isValid()) {
                    var oFieldType = Ext.getCmp('field_type');
                    var fieldType = oFieldType.getValue();
                    if (fieldType === '') {
                        oFieldType.markInvalid('Please select field type');
                        booError = true;
                    }

                    if (!booError) {
                        var oSelFieldType = field_types_store.getById(fieldType);

                        // Check if there are some options entered
                        if (oSelFieldType.data.booWithOptions) {
                            if (oSelFieldType.data.id == 16) {
                                var fieldImageWidth = parseInt(Ext.get('field_image_width').getValue(), 10);
                                var fieldImageHeight = parseInt(Ext.get('field_image_height').getValue(), 10);
                                if (fieldImageWidth < 1 || fieldImageHeight < 1) {
                                    Ext.simpleConfirmation.msg('Error', 'Please specify correct image size');
                                    booError = true;
                                }
                            } else if (options_store.getCount() === 0) {
                                Ext.simpleConfirmation.msg('Error', 'Please create at least one option');
                                booError = true;
                            }
                        }

                        if (!booError) {
                            var fieldLabel = Ext.getCmp('field_label').getValue();
                            var fieldEncrypted = Ext.getCmp('field_encrypted').getValue();
                            var fieldRequired = Ext.getCmp('field_required').getValue();
                            var fieldRequiredForSubmission = Ext.getCmp('field_required_for_submission').getValue();
                            var fieldDisabled = Ext.getCmp('field_disabled').getValue();
                            var fieldUseFullRow = Ext.getCmp('field_use_full_row').getValue();
                            var fieldSkipAccessRequirements = Ext.getCmp('field_skip_access_requirements').getValue();
                            var fieldMultipleValues = Ext.getCmp('field_multiple_values').getValue();
                            var fieldCanEditInGui = Ext.getCmp('field_can_edit_in_gui').getValue();

                            // Collect data and send to server
                            var arrOptions = [];
                            Ext.getCmp('field_options').store.each(function (r) {
                                arrOptions[arrOptions.length] = {id: r.data.option_id, name: r.data.option_name, order: r.data.option_order};
                            });

                            win.getEl().mask('Saving...');
                            Ext.Ajax.request({
                                url: submissionUrl + '/edit-field',
                                params: {
                                    company_id: companyId,
                                    member_type: Ext.encode(memberType),
                                    group_id: Ext.encode(groupId),
                                    field_id: Ext.encode(fieldId),
                                    field_company_id: Ext.encode(Ext.getCmp('field_company_id').getValue()),
                                    field_label: Ext.encode(fieldLabel),
                                    field_type: fieldType,
                                    field_encrypted: Ext.encode(fieldEncrypted),
                                    field_required: Ext.encode(fieldRequired),
                                    field_required_for_submission: Ext.encode(fieldRequiredForSubmission),
                                    field_disabled: Ext.encode(fieldDisabled),
                                    field_use_full_row: Ext.encode(fieldUseFullRow),
                                    field_skip_access_requirements: Ext.encode(fieldSkipAccessRequirements),
                                    field_multiple_values: Ext.encode(fieldMultipleValues),
                                    field_can_edit_in_gui: Ext.encode(fieldCanEditInGui),
                                    field_maxlength: Ext.getCmp('field_max_length').getValue(),
                                    field_custom_height: Ext.getCmp('field_custom_height').getValue(),
                                    field_default_value: Ext.encode(Ext.getCmp('field_default_value').getValue()),
                                    field_options: Ext.encode(arrOptions),
                                    field_image_width: fieldImageWidth,
                                    field_image_height: fieldImageHeight,
                                    field_default_access: Ext.encode(accessRightsForm.getForm().getValues())
                                },

                                success: function (result) {
                                    var resultData = Ext.decode(result.responseText);
                                    if (!resultData.error) {
                                        // Show a confirmation
                                        win.getEl().mask('Done !');
                                        setTimeout(function () {
                                            if (fieldId === 0) {
                                                var data = resultData.additional_info;
                                                addFieldToHtml(data.group_id, data.field_id, data.field_name, data.field_encrypted, data.field_required, data.field_disabled, false, fieldUseFullRow, fieldSkipAccessRequirements);
                                                fixManageFieldsPageHeight(500);
                                            } else {
                                                // Update field name and class
                                                setFieldName(fieldId, fieldLabel);
                                                updateFieldClass(fieldId, fieldEncrypted, fieldRequired, fieldDisabled, fieldUseFullRow, fieldSkipAccessRequirements);
                                            }

                                            win.close();
                                        }, confirmationTimeOut);
                                    } else {
                                        win.getEl().unmask();
                                        Ext.simpleConfirmation.error(resultData.message);
                                    }
                                },

                                failure: function () {
                                    win.getEl().unmask();
                                    Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                                }
                            });
                        }
                    }
                }
            }
        }, {
            text: 'Cancel',
            handler: function () {
                win.close();
            }
        }]
    });

    win.show();
    win.center();
};