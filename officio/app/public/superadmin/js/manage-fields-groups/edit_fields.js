// Used to disallow send two requests at one time
var booSendInProgress = false;
var confirmationTimeOut = 750;

var fieldInfo = Ext.data.Record.create([
    {name: 'group_id', type: 'int'},
    {name: 'field_id', type: 'int'},
    {name: 'field_parent_field_id', type: 'int'},
    {name: 'field_company_id', type: 'string'},
    {name: 'field_type', type: 'int'},
    {name: 'field_type_text_id', type: 'string'},
    {name: 'field_type_name', type: 'string'},
    {name: 'field_label', type: 'string'},
    {name: 'field_default_value', type: 'string'},
    {name: 'field_encrypted', type: 'boolean'},
    {name: 'field_required', type: 'boolean'},
    {name: 'field_required_for_submission', type: 'boolean'},
    {name: 'field_disabled', type: 'boolean'},
    {name: 'field_use_full_row', type: 'boolean'},
    {name: 'field_skip_access_requirements', type: 'boolean'},
    {name: 'field_sync_with_default', type: 'string'},
    {name: 'field_multiple_values', type: 'boolean'},
    {name: 'field_can_edit_in_gui', type: 'boolean'},
    {name: 'field_maxlength', type: 'int'},
    {name: 'field_custom_height', type: 'int'},
    {name: 'field_min_value', type: 'int'},
    {name: 'field_max_value', type: 'int'},
    {name: 'field_options'},
    {name: 'field_boo_with_maxlength', type: 'boolean'},
    {name: 'field_boo_with_custom_height', type: 'boolean'},
    {name: 'field_boo_with_options', type: 'boolean'},
    {name: 'field_boo_with_default', type: 'boolean'},
    {name: 'field_default_access'}
]);

var makeFieldsSortable = function () {
    var oSortable = $('.fields_column').sortable({
        connectWith: ['.fields_column'],
        items: '.field_container',
        handle: '.group_field_name',
        revert: true,
        cursor: 'move'
    }).disableSelection();
};

var updateClicksOnFields = function () {
    // Unbind previous events
    $(".field_container .edit_field_action").off('click');
    $(".field_container .delete_field_action").off('click');

    // Click on edit field
    $(".field_container .edit_field_action").click(function () {
        var fieldId = $(this).parents(".field_container:first")[0].id;
        var groupId = $(this).parents(".portlet:first")[0].id;
        loadFieldInfo(groupId, fieldId);
    });

    // Click on delete field
    $(".field_container .delete_field_action").click(function () {
        var fieldId = $(this).parents(".field_container:first")[0].id;
        deleteField(fieldId);
    });
};

var updateClicksOnGroups = function () {
    // Expand/collapse group click
    $(".portlet-header .field_group_minus").off('click').click(function () {
        $(this).toggleClass("field_group_plus");
        $(this).parents(".portlet:first").find(".portlet-content").toggle();
    });

    // Click on delete group
    $(".portlet-header .field_group_delete").off('click').click(function () {
        var groupId = $(this).parents(".portlet:first")[0].id;
        deleteGroup(groupId);
    });

    // Click on edit group
    $(".portlet-header .field_group_edit").off('click').click(function () {
        var groupId = $(this).parents(".portlet:first")[0].id;
        showEditGroupDialog(groupId);
    });

    $(".portlet-header .field_group_add").off('click').click(function () {
        var groupId = $(this).parents(".portlet:first")[0].id;
        loadFieldInfo(groupId, 0);
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
    var fieldSyncWithDefault = 'Yes';
    var fieldMultipleValues = 0;
    var fieldCanEditInGui = 0;
    var fieldSize = '';
    var fieldDefaultValue = '';
    var fieldCustomHeight = '';
    var fieldMinValue = '';
    var fieldMaxValue = '';
    var fieldOptions = [];

    var wndTitle = _('Create new field');
    var btnSubmitTitle = _('Create field');

    var booHideDefaultValue = true;
    var booHideOptionsGrid = true;
    var booHideImageField = true;
    var booHideCustomHeight = true;
    var booHideMinMaxValues = true;
    var booHideMultipleValues = true;
    var booHideCanEditInGui = true;

    var booFieldCompanyIdDisabled = false;

    if (fieldInfo.data.field_id !== 0) {
        fieldId = fieldInfo.data.field_id;

        if (fieldInfo.data['field_type_text_id'] === 'office' || fieldInfo.data['field_type_text_id'] === 'office_multi') {
            fieldRequired = 1;
        }

        fieldCompanyId = fieldInfo.data.field_company_id;
        booFieldCompanyIdDisabled = true;

        booHideImageField = fieldInfo.data['field_type_text_id'] !== 'photo';
        booHideMinMaxValues = fieldInfo.data['field_type_text_id'] !== 'auto_calculated';
        booHideCustomHeight = !fieldInfo.data.field_boo_with_custom_height;
        booHideOptionsGrid = !(fieldInfo.data.field_boo_with_options && booHideImageField);
        booHideDefaultValue = !fieldInfo.data.field_boo_with_default;

        if (fieldInfo.data['field_type_text_id'] === 'reference') {
            booHideMultipleValues = false;
        }

        if (fieldInfo.data['field_type_text_id'] === 'combo' || fieldInfo.data['field_type_text_id'] === 'multiple_combo') {
            booHideCanEditInGui = false;
        }

        wndTitle = _('Edit field details');
        btnSubmitTitle = _('Update field');
    }

    var toggleFieldsBasedOnFieldType = function (selRecord) {
        var booDisableConditionalFields;
        if (empty(fieldInfo.data.field_id)) {
            if (selRecord.data['text_id'] === 'case_internal_id' || selRecord.data['text_id'] === 'applicant_internal_id') {
                Ext.getCmp('field_company_id').setDisabled(true);

                if (selRecord.data['text_id'] === 'case_internal_id') {
                    Ext.getCmp('field_company_id').setValue('case_internal_id');
                } else {
                    Ext.getCmp('field_company_id').setValue('applicant_internal_id');
                }

            } else {
                Ext.getCmp('field_company_id').setDisabled(false);
            }

            booDisableConditionalFields = true;
        } else {
            booDisableConditionalFields = !['combo', 'multiple_combo', 'radio', 'checkbox', 'categories', 'case_status'].has(selRecord.data['text_id']);
        }
        conditionalFieldsPanel.setDisabled(booDisableConditionalFields);

        // Enable/disable encryption checkbox
        if (!selRecord.data.booCanBeEncrypted) {
            Ext.getCmp('field_encrypted').setValue(false);
        }
        var booDisabled = !selRecord.data.booCanBeEncrypted || Ext.getCmp('field_sync_with_default').getValue() !== 'No';
        Ext.getCmp('field_encrypted').setDisabled(booDisabled);

        // Show/hide max size textbox
        Ext.getCmp('field_max_length').setVisible(selRecord.data.booWithMaxLength);

        // Show/hide custom height field
        Ext.getCmp('field_custom_height').setVisible(selRecord.data.booWithCustomHeight);

        // Show/hide default value textbox
        Ext.getCmp('field_default_value').setVisible(selRecord.data.booWithDefaultValue);

        // Show "multiple values" for the reference field type
        Ext.getCmp('field_multiple_values').setVisible(selRecord.data['text_id'] === 'reference');
        if (selRecord.data['text_id'] === 'combo' || selRecord.data['text_id'] === 'multiple_combo') {
            Ext.getCmp('field_can_edit_in_gui').setVisible(true);
        } else {
            Ext.getCmp('field_can_edit_in_gui').setVisible(false);
            Ext.getCmp('field_can_edit_in_gui').setValue(false);
        }

        // Show min/max fields for "auto calculated" field type
        Ext.getCmp('field_min_value').setVisible(selRecord.data['text_id'] === 'auto_calculated');
        Ext.getCmp('field_max_value').setVisible(selRecord.data['text_id'] === 'auto_calculated');

        Ext.getCmp('image_field').setVisible(selRecord.data['text_id'] === 'photo' && selRecord.data.booWithOptions);

        // Show/hide options grid
        Ext.getCmp('field_options').setVisible(selRecord.data['text_id'] !== 'photo' && selRecord.data.booWithOptions);

        Ext.getCmp('edit-field-window').syncShadow();
    };

    var fields_list = new Ext.form.ComboBox({
        id: 'field_type',
        mode: 'local',
        fieldLabel: 'Field Type',
        emptyText: 'Please select a type...',

        store: new Ext.data.Store({
            data: fieldTypesList,
            reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([{
                name: 'id',
                type: 'int'
            }, {
                name: 'label',
                type: 'string'
            }, {
                name: 'text_id',
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
            }]))
        }),

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
                toggleFieldsBasedOnFieldType(selRecord);
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
                header: _('Option Name'),
                dataIndex: 'option_name',
                width: 320,
                renderer: function (val, p, record) {
                    return String.format(
                        '<span style="{0}">{1}</span>',
                        record.data.option_deleted ? 'text-decoration: line-through red;' : '',
                        val
                    );
                },
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
        {name: 'option_order', type: 'int'},
        {name: 'option_deleted', type: 'bool'}
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
            text: 'Add Option',
            iconCls: 'add-option',
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
            text: 'Delete Option',
            iconCls: 'delete-option',
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
        title: _('Properties'),
        labelWidth: 170, // label settings here cascade unless overridden
        bodyStyle: 'padding:5px;',
        autoWidth: true,
        autoHeight: true,
        defaultType: 'textfield',

        items: [
            {
                id: 'field_company_id',
                name: 'field_company_id',
                fieldLabel: _('Field Name'),
                value: fieldCompanyId,
                width: 230,
                disabled: booFieldCompanyIdDisabled,
                allowBlank: false,
                maskRe: /[A-Za-z0-9_\-]/,
                regex: /^[a-zA-Z0-9_\-]*$/,
                regexText: _('Only alphanumeric, hyphen and underscore symbols are only allowed')
            }, {
                id: 'field_label',
                name: 'field_label',
                fieldLabel: _('Field Label'),
                value: fieldLabel,
                width: 230,
                allowBlank: false
            },
            fields_list,
            {
                id: 'field_sync_with_default',
                name: 'field_sync_with_default',
                fieldLabel: _('Sync'),
                xtype: 'combo',
                store: {
                    xtype: 'arraystore',
                    fields: ['sync_id', 'sync_name'],
                    data: [
                        ['No', _("Don't sync field with default")],
                        ['Yes', _('Sync field with default')],
                        ['Label', _('Sync field except the field label')]
                    ]
                },
                displayField: 'sync_name',
                valueField: 'sync_id',
                mode: 'local',
                value: fieldSyncWithDefault,
                width: 230,
                hidden: empty(fieldId) || empty(fieldInfo.data.field_parent_field_id),
                forceSelection: true,
                editable: false,
                triggerAction: 'all',
                selectOnFocus: true,
                typeAhead: false,
                listeners: {
                    'beforeselect': function (combo, record) {
                        var arrGlobalFields = [
                            'field_type', 'field_label', 'field_encrypted', 'field_required', 'field_required_for_submission', 'field_disabled', 'field_use_full_row',
                            'field_skip_access_requirements', 'field_multiple_values', 'field_can_edit_in_gui', 'field_min_value', 'field_max_value', 'field_max_length',
                            'field_default_value', 'field_custom_height', 'field_options', 'image_field'
                        ];

                        for (var i = 0; i < arrGlobalFields.length; i++) {
                            var booDisabled = true;
                            switch (arrGlobalFields[i]) {
                                case 'field_label':
                                    // Label will be enabled if "Label" or "No" is selected
                                    booDisabled = record.data.sync_id !== 'No' && record.data.sync_id !== 'Label';
                                    break;

                                case 'field_encrypted':
                                    // Depends on the "field type" field
                                    var combo = Ext.getCmp('field_type');
                                    var selValue = combo.getValue();
                                    var booCanBeEncrypted = true;
                                    if (!empty(selValue)) {
                                        var thisRecord = combo.store.getById(selValue);
                                        booCanBeEncrypted = thisRecord.data.booCanBeEncrypted;
                                    }

                                    booDisabled = !booCanBeEncrypted || record.data.sync_id !== 'No';
                                    break;

                                default:
                                    // All other fields will be disabled if "Yes" or "Label" is selected
                                    booDisabled = record.data.sync_id !== 'No';
                                    break;
                            }

                            Ext.getCmp(arrGlobalFields[i]).setDisabled(booDisabled);
                        }
                    }
                }
            }, {
                id: 'field_encrypted',
                name: 'field_encrypted',
                fieldLabel: _('Encrypted'),
                xtype: 'checkbox',
                value: fieldEncrypted
            }, {
                id: 'field_required',
                name: 'field_required',
                fieldLabel: _('Required'),
                xtype: 'checkbox',
                value: fieldRequired
            }, {
                id: 'field_required_for_submission',
                name: 'field_required_for_submission',
                fieldLabel: _('Required for Submission'),
                xtype: 'checkbox',
                value: fieldRequiredForSubmission,
                hidden: !booIsAuthorisedAgentsManagementEnabled
            }, {
                id: 'field_disabled',
                name: 'field_disabled',
                fieldLabel: _('Disabled'),
                xtype: 'checkbox',
                value: fieldDisabled
            }, {
                id: 'field_use_full_row',
                name: 'field_use_full_row',
                fieldLabel: _('Use full row'),
                xtype: 'checkbox',
                value: fieldUseFullRow
            }, {
                id: 'field_skip_access_requirements',
                name: 'field_skip_access_requirements',
                fieldLabel: _('Skip Access Requirements'),
                xtype: 'checkbox',
                value: fieldSkipAccessRequirements
            }, {
                id: 'field_multiple_values',
                name: 'field_multiple_values',
                fieldLabel: _('Multiple Values'),
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
                id: 'field_min_value',
                name: 'field_min_value',
                fieldLabel: _('Min Value'),
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: true,
                value: empty(fieldMinValue) ? null : fieldMinValue,
                hidden: booHideMinMaxValues
            }, {
                id: 'field_max_value',
                name: 'field_max_value',
                fieldLabel: _('Max Value'),
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: true,
                value: empty(fieldMaxValue) ? null : fieldMaxValue,
                hidden: booHideMinMaxValues
            }, {
                id: 'field_max_length',
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: false,
                name: 'field_max_length',
                fieldLabel: _('Max Length'),
                width: 50,
                value: empty(fieldSize) ? null : fieldSize,
                hidden: true
            }, {
                id: 'field_default_value',
                name: 'field_default_value',
                fieldLabel: _('Default Value'),
                value: fieldDefaultValue,
                width: 230,
                hidden: booHideDefaultValue
            }, {
                id: 'field_custom_height',
                name: 'field_custom_height',
                xtype: 'numberfield',
                allowDecimals: false,
                allowNegative: false,
                fieldLabel: _('Custom height'),
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
            html: _('Roles')
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

    var oConditionalFieldsPanel;
    if (!empty(fieldInfo.data.field_id)) {
        oConditionalFieldsPanel = new ConditionalFieldsPanel({
            field_id: fieldInfo.data.field_id,
            field_options: fieldInfo.data.field_options,
            field_type: fieldInfo.data.field_type_text_id,
            case_template_id: caseTemplateId
        });
    }
    var conditionalFieldsPanel = new Ext.Panel({
        title: _('Conditional Fields'),
        disabled: true,
        items: oConditionalFieldsPanel,
        booTabActivated: false,
        listeners: {
            'activate': function () {
                if (!this.booTabActivated) {
                    oConditionalFieldsPanel.conditionsGrid.getStore().load();
                    this.booTabActivated = true;
                }
            }
        }
    });

    function save() {
        var booError = false;
        if (frm.getForm().isValid()) {
            var oFieldType = Ext.getCmp('field_type');
            var fieldType = oFieldType.getValue();
            if (fieldType === '') {
                oFieldType.markInvalid('Please select field type');
                booError = true;
            }

            if (!booError) {
                var oSelFieldType = oFieldType.store.getById(fieldType);

                // Check if there are some options entered
                if (oSelFieldType.data.booWithOptions) {
                    if (oSelFieldType.data['text_id'] === 'photo') {
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

                if (parseInt(Ext.getCmp('field_max_value').getValue()) < parseInt(Ext.getCmp('field_min_value').getValue())) {
                    Ext.getCmp('field_min_value').markInvalid('Min value must be less than or equal to max value');
                    booError = true;
                }

                if (!booError) {

                    var fieldLabel = Ext.getCmp('field_label').getValue();
                    var fieldEncrypted = Ext.getCmp('field_encrypted').getValue();
                    var fieldRequired = Ext.getCmp('field_required').getValue();
                    var fieldRequiredForSubmission = Ext.getCmp('field_required_for_submission').getValue();
                    var fieldDisabled = Ext.getCmp('field_disabled').getValue();
                    var fieldUseFullRow = Ext.getCmp('field_use_full_row').getValue();
                    var fieldSkipAccessRequirements = Ext.getCmp('field_skip_access_requirements').getValue();
                    var fieldSyncWithDefault = Ext.getCmp('field_sync_with_default').getValue();
                    var fieldMultipleValues = Ext.getCmp('field_multiple_values').getValue();
                    var fieldCanEditInGui = Ext.getCmp('field_can_edit_in_gui').getValue();

                    // Collect data and send to server
                    var arrOptions = [];
                    Ext.getCmp('field_options').store.each(function (r) {
                        arrOptions[arrOptions.length] = {id: r.data.option_id, name: r.data.option_name, order: r.data.option_order};
                    });

                    win.getEl().mask('Saving...');
                    Ext.Ajax.request({
                        url: submissionUrl + "/edit-field/",
                        timeout: 10 * 60 * 1000, // 10 minutes

                        params: {
                            company_id: Ext.get('fieldsCompanyId').getValue(),
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
                            field_sync_with_default: Ext.encode(fieldSyncWithDefault),
                            field_multiple_values: Ext.encode(fieldMultipleValues),
                            field_can_edit_in_gui: Ext.encode(fieldCanEditInGui),
                            field_maxlength: Ext.getCmp('field_max_length').getValue(),
                            field_custom_height: Ext.getCmp('field_custom_height').getValue(),
                            field_min_value: Ext.getCmp('field_min_value').getValue(),
                            field_max_value: Ext.getCmp('field_max_value').getValue(),
                            field_default_value: Ext.encode(Ext.getCmp('field_default_value').getValue()),
                            field_options: Ext.encode(arrOptions),
                            field_image_width: fieldImageWidth,
                            field_image_height: fieldImageHeight,
                            field_default_access: Ext.encode(accessRightsForm.getForm().getValues())
                        },

                        success: function (result) {
                            var resultData = Ext.decode(result.responseText);
                            if (resultData.success) {
                                // Show a confirmation
                                win.getEl().mask('Done !');
                                setTimeout(function () {
                                    if (empty(fieldId)) {
                                        // This is a new field, create it in html
                                        var $group = $("#fields_group_" + resultData.additional_info.group_id + ' .portlet-content');
                                        if (!empty($group)) {
                                            var newFieldId = resultData.additional_info.field_id;
                                            var newFieldName = resultData.additional_info.field_name;
                                            var newFieldEncrypted = resultData.additional_info.field_encrypted;
                                            var newFieldRequired = resultData.additional_info.field_required;
                                            var newFieldDisabled = resultData.additional_info.field_disabled;

                                            var classEncrypted = (newFieldEncrypted) ? ' field_encrypted' : '';
                                            var classRequired = (newFieldRequired) ? ' field_required' : '';
                                            var classDisabled = (newFieldDisabled) ? ' field_disabled' : '';
                                            var classUseFullRow = (fieldUseFullRow) ? ' field_use_full_row' : '';
                                            var classSkipAccessRequirements = (fieldSkipAccessRequirements) ? ' field_skip_access_requirements' : '';

                                            var newFieldHtml =
                                                '<div class="field_container' + classEncrypted + classRequired + classDisabled + classUseFullRow + classSkipAccessRequirements + '" id="field_' + newFieldId + '">' +
                                                '<span class="group_field_name">' + newFieldName + '</span>' +
                                                '</div>';

                                            $group.append(newFieldHtml);

                                            // Add 'field buttons'
                                            $("#field_" + newFieldId)
                                                .append('<span class="ui-icon edit_field_action" title="Edit Field Info">&nbsp;</span>')
                                                .append('<span class="ui-icon delete_field_action" title="Delete Field">&nbsp;</span>')
                                                .end();

                                            updateClicksOnFields();
                                            updateHoverFields();
                                            updateNotUsedFieldsGroupHeight();
                                        }
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

    var win = new Ext.Window({
        id: 'edit-field-window',
        title: wndTitle,
        autoWidth: true,
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
                accessRightsForm,
                conditionalFieldsPanel
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
                        var combo = Ext.getCmp('field_type');
                        var record = combo.store.getById(fieldInfo.data.field_type);
                        if (record) {
                            combo.fireEvent('beforeselect', combo, record, fieldInfo.data.field_type);
                        }
                        combo.setValue(fieldInfo.data.field_type);
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

                    if (fieldInfo.data.field_sync_with_default) {
                        var combo = Ext.getCmp('field_sync_with_default');
                        var index = combo.store.find(combo.valueField, fieldInfo.data.field_sync_with_default);
                        if (index !== -1) {
                            var record = combo.store.getAt(index);
                            combo.fireEvent('beforeselect', combo, record, fieldInfo.data.field_sync_with_default);
                        }
                        combo.setValue(fieldInfo.data.field_sync_with_default);
                    }

                    if (fieldInfo.data.field_boo_with_options) {
                        if (fieldInfo.data.field_options) {
                            options_store.loadData(fieldInfo.data.field_options);
                        }
                    } else if (fieldInfo.data.field_boo_with_default) {
                        if (fieldInfo.data.field_options && fieldInfo.data.field_options[0][1]) {
                            Ext.getCmp('field_default_value').setValue(fieldInfo.data.field_options[0][1]);
                        }
                    }

                    if (fieldInfo.data.field_boo_with_maxlength) {
                        var max_length = fieldInfo.data.field_maxlength;
                        if (!empty(max_length)) {
                            Ext.getCmp('field_max_length').setValue(max_length);
                        }
                    }

                    if (fieldInfo.data.field_boo_with_custom_height) {
                        var custom_height = fieldInfo.data.field_custom_height;
                        if (!empty(custom_height)) {
                            Ext.getCmp('field_custom_height').setValue(custom_height);
                        }
                    }

                    // If this is a 'divisions' field - set up its default settings
                    if (fieldInfo.data['field_type_text_id'] === 'office' || fieldInfo.data['field_type_text_id'] === 'office_multi') {
                        Ext.getCmp('field_required').setValue(true);
                        Ext.getCmp('field_label').setDisabled(true);
                        Ext.getCmp('field_type').setDisabled(true);
                        Ext.getCmp('field_required').setDisabled(true);
                        Ext.getCmp('field_disabled').hide();
                        Ext.getCmp('field_default_value').hide();
                    }

                    if (fieldInfo.data['field_type_text_id'] === 'photo' && fieldInfo.data.field_options) {
                        Ext.getCmp('field_image_width').setValue(fieldInfo.data.field_options[0][1]);
                        Ext.getCmp('field_image_height').setValue(fieldInfo.data.field_options[1][1]);
                    }

                    if (fieldInfo.data['field_type_text_id'] === 'auto_calculated') {
                        Ext.getCmp('field_min_value').setValue(fieldInfo.data.field_min_value);
                        Ext.getCmp('field_max_value').setValue(fieldInfo.data.field_max_value);
                    }

                    if (fieldInfo.data['field_type_text_id'] === 'reference') {
                        if (fieldInfo.data.field_multiple_values) {
                            Ext.getCmp('field_multiple_values').setValue(true);
                        }
                    }

                    if (fieldInfo.data['field_type_text_id'] === 'combo' || fieldInfo.data['field_type_text_id'] === 'multiple_combo') {
                        if (fieldInfo.data.field_can_edit_in_gui) {
                            Ext.getCmp('field_can_edit_in_gui').setValue(true);
                        }
                    } else {
                        Ext.getCmp('field_can_edit_in_gui').setValue(false);
                    }

                    if (booIsAuthorisedAgentsManagementEnabled) {
                        if (fieldInfo.data.field_required_for_submission) {
                            Ext.getCmp('field_required_for_submission').setValue(true);
                        }
                    }
                }
            }
        },


        buttons: [
            {
                text: _('Cancel'),
                handler: function () {
                    win.close();
                }
            }, {
                text: btnSubmitTitle,
                handler: function () {
                    save();
                }
            }
        ]
    });

    win.show();
    win.center();
};

var loadFieldInfo = function (groupId, fieldId) {
    var body = Ext.getBody();
    body.mask('Loading...');

    Ext.Ajax.request({
        url: submissionUrl + "/get-field-info/",
        params: {
            company_id: Ext.get('fieldsCompanyId').getValue(),
            template_id: caseTemplateId,
            field_id: Ext.encode(fieldId)
        },

        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            if (!resultData.error) {
                var thisFieldInfo = resultData.additional_info;

                // Assign field info
                var newField = new fieldInfo({
                    group_id: groupId,
                    field_id: fieldId,
                    field_parent_field_id: thisFieldInfo.parent_field_id,
                    field_type: thisFieldInfo.type,
                    field_type_text_id: thisFieldInfo.field_type_text_id,
                    field_company_id: thisFieldInfo.company_field_id,
                    field_type_name: thisFieldInfo.type_label,
                    field_label: thisFieldInfo.label,
                    field_encrypted: thisFieldInfo.encrypted === 'Y',
                    field_required: thisFieldInfo.required === 'Y',
                    field_required_for_submission: thisFieldInfo.required_for_submission === 'Y',
                    field_disabled: thisFieldInfo.disabled === 'Y',
                    field_use_full_row: thisFieldInfo.use_full_row === 'Y',
                    field_skip_access_requirements: thisFieldInfo.skip_access_requirements === 'Y',
                    field_sync_with_default: thisFieldInfo.sync_with_default,
                    field_multiple_values: thisFieldInfo.multiple_values === 'Y',
                    field_can_edit_in_gui: thisFieldInfo.can_edit_in_gui === 'Y',
                    field_maxlength: thisFieldInfo.maxlength,
                    field_min_value: thisFieldInfo.min_value,
                    field_max_value: thisFieldInfo.max_value,
                    field_custom_height: thisFieldInfo.custom_height,
                    field_options: thisFieldInfo.default_val,
                    field_blocked: thisFieldInfo.blocked === 'Y',
                    field_default_access: thisFieldInfo.field_default_access,

                    field_boo_with_maxlength: thisFieldInfo.booWithMaxLength,
                    field_boo_with_custom_height: thisFieldInfo.booWithCustomHeight,
                    field_boo_with_options: thisFieldInfo.booWithOptions,
                    field_boo_with_default: thisFieldInfo.booWithDefaultValue
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

var getGroupName = function (groupId) {
    return $('#' + groupId).find('.group_name:first').text();
};

var getGroupColsCount = function (groupId) {
    var groupColsCount = 3;
    var groupNameSpan = $('#' + groupId).find('.group_name:first');
    var classList = groupNameSpan.attr('class').split(/\s+/);
    $.each(classList, function (index, item) {
        var match = item.match(/^group_columns_count_([\d]+)$/);
        if (match != null) {
            groupColsCount = parseInt(match[1]);
        }
    });
    return groupColsCount;
};

var isGroupRepeatable = function (groupId) {
    return $('#' + groupId).find('.group_name:first').hasClass('group_repeatable');
};

var isGroupCollapsed = function (groupId) {
    return $('#' + groupId).find('.group_name:first').hasClass('group_collapsed');
};

var isGroupTitleVisible = function (groupId) {
    return $('#' + groupId).find('.group_name:first').hasClass('group_show_title');
};

var setGroupName = function (groupId, newName) {
    $('#' + groupId).find('.group_name:first').html(newName);
};

var setGroupColsCount = function (groupId, newColsCount) {
    var oldColsCount = 0;
    var groupNameSpan = $('#' + groupId).find('.group_name:first');
    var classList = groupNameSpan.attr('class').split(/\s+/);
    $.each(classList, function (index, item) {
        var match = item.match(/^group_columns_count_([\d]+)$/);
        if (match != null) {
            oldColsCount = match[1];
            groupNameSpan.removeClass(item);
        }
    });
    groupNameSpan.addClass('group_columns_count_' + newColsCount);

    if (oldColsCount != newColsCount) {
        var groupContent = $('#' + groupId).find('.portlet-content:first');
        groupContent.removeClass('group_columns_count_' + oldColsCount);
        groupContent.addClass('group_columns_count_' + newColsCount);
    }
};

var setGroupRepeatable = function (groupId, booIsGroupRepeatable) {
    var group = $('#' + groupId).find('.group_name:first');
    if (booIsGroupRepeatable) {
        group.addClass('group_repeatable');
    } else {
        group.removeClass('group_repeatable');
    }
};

var setGroupCollapsed = function (groupId, booIsGroupCollapsed) {
    var group = $('#' + groupId).find('.group_name:first');
    if (booIsGroupCollapsed) {
        group.addClass('group_collapsed');
    } else {
        group.removeClass('group_collapsed');
    }
};

var setGroupTitleVisible = function (groupId, booIsTitleVisible) {
    var group = $('#' + groupId).find('.group_name:first');
    if (booIsTitleVisible) {
        group.addClass('group_show_title');
    } else {
        group.removeClass('group_show_title');
    }
};


var getFieldName = function (fieldId) {
    return $('#' + fieldId).find('.group_field_name:first').html();
};

var setFieldName = function (fieldId, newName) {
    $('#' + fieldId).find('.group_field_name:first').html(newName);
};

var updateFieldClass = function (fieldId, booFieldEncrypted, booRequired, booDisabled, booUseFullRow, booSkipAccessRequirements) {
    if (booFieldEncrypted) {
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

var showEditGroupDialog = function (groupId) {
    var groupName = 'New group';
    var groupColsCount = 3;
    var groupIsRepeatable = false;
    var groupIsCollapsed = false;
    var groupShowTitle = true;

    var wndTitle = 'Create new group';
    var btnSubmitTitle = _('Create group');

    if (typeof ((groupId)) == "undefined") {
        // New group
        groupId = 0;
    } else {
        // Load info about the group
        groupName = getGroupName(groupId);
        groupColsCount = getGroupColsCount(groupId);
        groupIsRepeatable = isGroupRepeatable(groupId);
        groupIsCollapsed = isGroupCollapsed(groupId);
        groupShowTitle = isGroupTitleVisible(groupId);

        wndTitle = 'Edit group details';
        btnSubmitTitle = _('Update group');
    }

    var frm = new Ext.FormPanel({
        labelWidth: 85, // label settings here cascade unless overridden
        bodyStyle: 'padding:5px 5px 0',
        autoWidth: true,
        autoHeight: true,
        defaults: {width: 230},
        defaultType: 'textfield',

        items: [
            {
                id: 'group_name',
                name: 'group_name',
                fieldLabel: 'Group Name',
                value: groupName,
                allowBlank: false,
                disabled: groupName === 'Dependants',
            }, {
                id: 'group_cols_count',
                name: 'group_cols_count',
                xtype: 'numberfield',
                fieldLabel: 'Columns count',
                width: 30,
                value: groupColsCount,
                allowNegative: false,
                allowDecimals: false,
                minValue: 1,
                maxValue: 5,
                allowBlank: false
            }, {
                id: 'group_repeatable',
                name: 'group_repeatable',
                xtype: 'checkbox',
                fieldLabel: 'Is repeatable',
                hidden: !empty(caseTemplateId),
                checked: groupIsRepeatable
            }, {
                id: 'group_collapsed',
                name: 'group_collapsed',
                xtype: 'checkbox',
                hideLabel: true,
                boxLabel: 'Is collapsed',
                checked: groupIsCollapsed
            }, {
                id: 'group_show_title',
                name: 'group_show_title',
                xtype: 'checkbox',
                hideLabel: true,
                boxLabel: 'Show group title',
                checked: groupShowTitle
            }
        ]
    });

    var win = new Ext.Window({
        title: wndTitle,
        width: 580,
        plain: false,
        modal: true,
        items: frm,
        resizable: false,
        buttons: [{
            text: 'Cancel',
            handler: function () {
                win.close();
            }
        }, {
            text: btnSubmitTitle,
            handler: function () {
                if (frm.getForm().isValid()) {
                    var newGroupName = Ext.getCmp('group_name').getValue();
                    var newGroupColsCount = Ext.getCmp('group_cols_count').getValue();
                    var isGroupRepeatable = Ext.getCmp('group_repeatable').getValue();
                    var booIsGroupCollapsed = Ext.getCmp('group_collapsed').getValue();
                    var booIsGroupTitleVisible = Ext.getCmp('group_show_title').getValue();
                    var companyId = Ext.get('fieldsCompanyId').getValue();

                    win.getEl().mask('Saving...');

                    Ext.Ajax.request({
                        url: submissionUrl + "/ajax/",
                        params: {
                            doAction: Ext.encode('create_update_group'),
                            company_id: companyId,
                            template_id: caseTemplateId,
                            group_id: Ext.encode(groupId),
                            group_name: Ext.encode(newGroupName),
                            group_cols_count: Ext.encode(newGroupColsCount),
                            group_repeatable: Ext.encode(isGroupRepeatable),
                            group_collapsed: Ext.encode(booIsGroupCollapsed),
                            group_show_title: Ext.encode(booIsGroupTitleVisible)
                        },

                        success: function (result) {
                            var resultData = Ext.decode(result.responseText);
                            if (!resultData.error) {
                                // Show confirmation message
                                // Show the confirmation message
                                win.getEl().mask(resultData.message);

                                setTimeout(function () {
                                    win.getEl().unmask();

                                    var newGroupId = resultData.additional_info.new_group_id;
                                    var newGroupName = resultData.additional_info.new_group_name;

                                    if (groupId === 0) {
                                        // Generate html for new group and show it on the page

                                        var additionalCls = isGroupRepeatable ? ' group_repeatable' : '';
                                        additionalCls += booIsGroupCollapsed ? ' group_collapsed' : '';
                                        additionalCls += booIsGroupTitleVisible ? ' group_show_title' : '';
                                        additionalCls += ' group_columns_count_' + newGroupColsCount;

                                        var portletHeaderCls = 'portlet-header';
                                        var fieldsContainer = '<div class="portlet-content group_columns_count_' + newGroupColsCount + ' fields_column"></div>';
                                        if (newGroupName === 'Dependants') {
                                            portletHeaderCls += ' portlet-fields-adding-not-allowed';
                                            fieldsContainer = '';
                                        }

                                        var newGroupHtml =
                                            '<div class="portlet" id="fields_group_' + newGroupId + '">' +
                                            '<div class="' + portletHeaderCls + '">' +
                                            '<span class="group_name ' + additionalCls + '">' + newGroupName + '</span>' +
                                            '</div>' +
                                            fieldsContainer +
                                            '</div>';

                                        $("#groups_column").append(newGroupHtml);

                                        // Add 'groups buttons'
                                        $("#fields_group_" + newGroupId).find(".portlet-header")
                                            .prepend('<span class="field_group_add" title="Add Field to this Group"><i class="las la-plus"></i>New Field</span>')
                                            .prepend('<span class="field_group_edit" title="Edit Group Info"><i class="las la-pen"></i>Edit Group</span>')
                                            .prepend('<span class="field_group_delete" title="Delete Group"><i class="las la-trash"></i>Delete Group</span>')
                                            .prepend('<span class="ui-icon field_group_minus" title="Collapse/Expand Group Fields"></span>')
                                            .end()
                                            .find(".portlet-content");

                                        updateClicksOnGroups();
                                        updateHoverGroups();

                                        makeFieldsSortable();
                                    } else {
                                        // Update group info on the page
                                        setGroupName(groupId, newGroupName);
                                        setGroupColsCount(groupId, newGroupColsCount);
                                        setGroupRepeatable(groupId, isGroupRepeatable);
                                        setGroupCollapsed(groupId, booIsGroupCollapsed);
                                        setGroupTitleVisible(groupId, booIsGroupTitleVisible);
                                    }

                                    updateNotUsedFieldsGroupHeight();

                                    win.close();
                                }, confirmationTimeOut);

                            } else {
                                win.getEl().unmask();
                                Ext.simpleConfirmation.error(resultData.message);
                                booSendInProgress = false;
                            }
                        },

                        failure: function () {
                            win.getEl().unmask();
                            Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                            booSendInProgress = false;
                        }
                    });
                }
            }
        }]
    });

    win.show();
    win.center();
};

var deleteField = function (fieldId) {
    var fieldName = getFieldName(fieldId);

    if (fieldId == 'field_division') {
        Ext.simpleConfirmation.error(_('You cannot delete divisions field.'));
        return;
    }

    Ext.MessageBox.buttonText.yes = _("Yes, delete this field");
    Ext.MessageBox.confirm('Please confirm', 'Are you sure you want to delete field <span style="color: green; font-style: italic;">' + fieldName + '</span> ?', function (btn) {
        if (btn == 'yes') {
            body = Ext.getBody();
            body.mask('Deleting...');

            var companyId = Ext.get('fieldsCompanyId').getValue();

            Ext.Ajax.request({
                url: submissionUrl + "/delete-field",
                params: {
                    company_id: Ext.encode(companyId),
                    field_id: Ext.encode(fieldId)
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {

                        // Remove this group from html
                        $("#" + fieldId).remove();


                        // Show a confirmation
                        body.mask('Done !');
                        setTimeout(function () {
                            updateNotUsedFieldsGroupHeight();
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

var deleteGroup = function (groupId) {
    // It is not possible to delete a group if it contains a field(s)
    var arrFelds = $('#' + groupId).find('.field_container');
    if (arrFelds.length > 0) {
        // This group contains fields
        Ext.simpleConfirmation.error('Please move fields to other groups, save changes and try again.');
        return;
    }

    var groupName = getGroupName(groupId);

    Ext.MessageBox.buttonText.yes = _("Yes, delete this group");
    Ext.MessageBox.confirm('Please confirm', 'Are you sure you want to delete group <span style="color: green; font-style: italic;">' + groupName + '</span> ?', function (btn) {
        if (btn == 'yes') {
            body = Ext.getBody();
            body.mask('Deleting...');

            var companyId = Ext.get('fieldsCompanyId').getValue();

            Ext.Ajax.request({
                url: submissionUrl + "/delete-group/",
                params: {
                    company_id: Ext.encode(companyId),
                    group_id: Ext.encode(groupId)
                },

                success: function (result) {
                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {

                        // Remove this group from html
                        $("#" + groupId).remove();

                        // Show a confirmation
                        body.mask('Done !');
                        setTimeout(function () {
                            updateNotUsedFieldsGroupHeight();
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

var updateHoverFields = function () {
    // Show/hide fields editing options
    $(".field_container").hover(
        function () {
            $(this).find('.edit_field_action').show();
            $(this).find('.delete_field_action').show();
        },
        function () {
            $(this).find('.edit_field_action').hide();
            $(this).find('.delete_field_action').hide();
        }
    );
};

var updateHoverGroups = function () {
    // Don't show "Add field" if this is not allowed for the group
    $('.portlet-fields-adding-not-allowed .field_group_add').hide();
};

// Load order information about the field
var getFieldDetails = function (field_id) {
    var fieldInfo = $('#' + field_id);
    var parentGroupId = fieldInfo.parents(".portlet:first")[0].id;
    var parentTd = fieldInfo.parent();

    var col = parentTd[0].cellIndex;

    var row = 0;
    parentTd.find('.field_container').each(function () {
        if (this.id == field_id) {
            return false;
        } else {
            row++;
        }
    });

    return {
        field_col: col,
        field_row: row,
        field_use_full_row: fieldInfo.hasClass('field_use_full_row') ? 1 : 0,
        field_skip_access_requirements: fieldInfo.hasClass('field_skip_access_requirements') ? 1 : 0,
        field_multiple_values: fieldInfo.hasClass('field_multiple_values') ? 1 : 0,
        field_can_edit_in_gui: fieldInfo.hasClass('field_can_edit_in_gui') ? 1 : 0,
        field_id: field_id,
        group_id: parentGroupId
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

// This function send order info to server and show result
var saveGroupsAndFieldsOrder = function (groupsOrder, fieldsOrder) {
    if (!booSendInProgress) {
        booSendInProgress = true;

        Ext.MessageBox.show({
            msg: 'Saving...',
            progressText: 'Saving...',
            width: 300,
            wait: true,
            waitConfig: {interval: 200},
            icon: 'ext-mb-download',
            animEl: 'button_submit'
        });

        Ext.Ajax.request({
            url: submissionUrl + '/ajax/',
            timeout: 10 * 60 * 1000, // 10 minutes

            params: {
                doAction: Ext.encode('update_order'),
                groups_order: Ext.encode(groupsOrder),
                fields_order: Ext.encode(fieldsOrder),
                template_id: caseTemplateId,
                company_id: Ext.get('fieldsCompanyId').getValue()
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (!resultData.error) {
                    // Show confirmation message
                    Ext.MessageBox.hide();
                    Ext.simpleConfirmation.success('Done', resultData.message);
                    booSendInProgress = false;

                    updateNotUsedFieldsGroupHeight();
                } else {
                    Ext.MessageBox.hide();
                    Ext.simpleConfirmation.error('<span style="color: red;">' + resultData.message + '</span>');
                    booSendInProgress = false;
                }
            },

            failure: function () {
                Ext.MessageBox.hide();
                Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                booSendInProgress = false;
            }
        });
    }
};

var showGroupsAndFieldsReadableConditions = function () {
    $('.field_tooltip').remove();
    Ext.each(Object.keys(arrReadableConditions['fields']), function (fieldId) {
        var oField = $('#field_' + fieldId + ' .group_field_name');
        if (oField.length) {
            var tooltipFieldId = Ext.id();
            oField.prepend(
                String.format(
                    '<i class="field_tooltip las la-project-diagram" id="{0}"></i>',
                    tooltipFieldId
                )
            );

            new Ext.ToolTip({
                target: tooltipFieldId,
                html: arrReadableConditions['fields'][fieldId]
            });
        }
    });


    $('.group_tooltip').remove();
    Ext.each(Object.keys(arrReadableConditions['groups']), function (groupId) {
        var oGroup = $('#fields_group_' + groupId + ' .group_name');
        if (oGroup.length) {
            var tooltipGroupId = Ext.id();
            oGroup.prepend(
                String.format(
                    '<i class="group_tooltip las la-project-diagram" id="{0}"></i>',
                    tooltipGroupId
                )
            );

            new Ext.ToolTip({
                target: tooltipGroupId,
                html: arrReadableConditions['groups'][groupId]
            });
        }
    });
};

var updateNotUsedFieldsGroupHeight = function () {
    // Limit the height of the Available Fields section
    $('#fields_groups_not_used_container .portlet').height($('#groups_column').height());
};


$(document).ready(function () {
    Ext.QuickTips.init();

    // turn on validation errors beside the field globally
    Ext.form.Field.prototype.msgTarget = 'side';

    $('.superadmin-iframe-header').hide();

    updateNotUsedFieldsGroupHeight();
    showGroupsAndFieldsReadableConditions();

    if (!booCreatedFromDefaultTemplate) {
        // Make possible sort, drag and drop groups and fields in them
        $("#groups_column").sortable({
            cancel: '.field_container',
            //cursor:"move",
            revert: true
        });

        makeFieldsSortable();

        // Add 'required' icon for required fields
        $(".field_container").find(".required")
            .prepend('<span class="ui-icon field_required" title="This is a required field"></span>')
            .end()
            .find(".portlet-content");


        // Add 'Edit', 'Delete' and 'Expand/Collapse' buttons for groups manipulating
        $(".portlet").find(".portlet-header")
            .prepend('<span class="field_group_add" title="Add Field to this Group"><i class="las la-plus"></i>New Field</span>')
            .prepend('<span class="field_group_edit" title="Edit Group Info"><i class="las la-pen"></i>Edit Group</span>')
            .prepend('<span class="field_group_delete" title="Delete Group"><i class="las la-trash"></i>Delete Group</span>')
            .prepend('<span class="ui-icon field_group_minus" title="Collapse/Expand Group Fields"></span>')
            .end()
            .find(".portlet-content");

        // Add 'Edit' and 'Delete' buttons for fields manipulating
        $(".field_container_edit")
            .append('<span class="ui-icon edit_field_action" title="Edit Field Info">&nbsp;</span>')
            .append('<span class="ui-icon delete_field_action" title="Delete Field">&nbsp;</span>')
            .end();

        // Add 'Edit' button for fields manipulating
        $(".field_blocked")
            .append('<span class="ui-icon edit_field_action" title="Edit Field Info">&nbsp;</span>')
            .end();

        updateClicksOnFields();
        updateClicksOnGroups();


        // Redirect to new company groups
        $('#fieldsCompanyId').change(function () {
            if ($(this).val() === '') {
                window.location = submissionUrl + '/';
            } else {
                window.location = submissionUrl + '/index/company_id/' + $(this).val();
            }
        });

        updateHoverFields();
        updateHoverGroups();

        // Collect and save order info for groups and fields
        $('#button_submit, .save-changes-link').click(function () {
            var groupOrder = $("#groups_column").sortable("serialize");
            var fieldsOrder = getFieldsOrder();
            saveGroupsAndFieldsOrder(groupOrder, fieldsOrder);
            return false;
        });


        // Click on 'Add new group'
        $('.add-group-link').click(function () {
            showEditGroupDialog();
            return false;
        });

        // Click on 'Add new field' in header
        $('.add-field-link').click(function () {
            var groupId = $('#fields_groups_not_used_container').find(".portlet:first")[0].id;
            loadFieldInfo(groupId, 0);

            return false;
        });
    } else {
        $(".portlet-header").addClass('field_container_cannot_move');
        $(".field_container").addClass('field_container_cannot_move');
        $('.buttons').hide();
    }
});