var prospectsAdvancedSearchForm = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisForm = this;
    this.arrQNRFields = [];
    this.arrSearchFilters = [];

    this.fieldsStore = new Ext.data.Store({
        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
            'q_field_help',
            'q_field_help_show',
            'q_field_id',
            'q_field_label',
            'q_field_order',
            'q_field_prospect_profile_label',
            'q_field_required',
            'q_field_show_in_prospect_profile',
            'q_field_type',
            'q_field_unique_id',
            'q_section_help',
            'q_section_help_show',
            'q_section_id',
            'q_section_prospect_profile',
            'q_section_step',
            'q_section_template_name',
            'options'
        ])),
        data: []
    });

    this.options_store = new Ext.data.Store({
        reader: new Ext.data.JsonReader({id: 0}, Ext.data.Record.create([
            'q_field_option_id',
            'q_field_id',
            'q_field_option_unique_id',
            'q_field_option_selected',
            'q_field_option_order',
            'q_id',
            'q_field_option_label',
            'q_field_option_visible'
        ])),
        data: []
    });

    this.currentRowsCount = 0;
    Ext.Ajax.request({
        url: baseUrl + '/prospects/index/get-adv-search-fields',
        params: {
            panel_type : owner.panelType
        },
        success: function (f) {
            var response = Ext.decode(f.responseText);

            thisForm.arrQNRFields = response['fields'];
            thisForm.arrSearchFilters = response['filters'];

            thisForm.fieldsStore.loadData(thisForm.arrQNRFields);
        }
    });

    this.operatorsStore = new Ext.data.ArrayStore({
        fields: ['operator_id', 'operator_name'],
        data:   [['and', 'AND'], ['or', 'OR']]
    });

    prospectsAdvancedSearchForm.superclass.constructor.call(this, {
        cls: 'extjs-panel-with-border',
        bodyStyle: 'padding: 5px; background-color: #E8E9EB;',
        buttonAlign: 'left',
        items: [
            {
                id: 'advanced-search-form',
                xtype: 'panel',
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px',
                items: [{
                    xtype: 'hidden',
                    id: 'max_rows_count',
                    value: 0
                }]
            }, {
                xtype: 'container',
                style: 'background-color: #E8E9EB; margin-bottom: 5px',
                items: {
                    id: 'advanced-search-add-row-button',
                    xtype:   'button',
                    text:    '<i class="las la-plus"></i>' + _('Add Filtering Criteria'),
                    handler: this.addNewRow.createDelegate(this, [false])
                }
            }, {
                xtype: 'container',
                style: 'background-color: #E8E9EB;',
                layout: 'column',
                items: [
                    {
                        xtype: 'container',
                        cls: 'active-clients-checkbox',
                        style: 'margin-top: 8px; margin-right: 155px;',
                        items: {
                            id: this.searchActiveProspectsCheckboxId,
                            xtype: 'checkbox',
                            name: 'active-prospects',
                            checked: true,
                            boxLabel: 'Search only among Active Prospects'
                        }
                    }, {
                        xtype: 'button',
                        text: _('Reset'),
                        style: 'margin-top: 10px;',
                        width: 80,
                        handler: this.resetFilter.createDelegate(this)
                    }, {
                        xtype: 'button',
                        text: '<i class="las la-search"></i>' + _('Search'),
                        cls: 'orange-btn',
                        handler: this.applyFilter.createDelegate(this)

                    }
                ]
            }
        ]
    });

    this.on('render', this.addNewRow.createDelegate(this, [true]), this);
};

Ext.extend(prospectsAdvancedSearchForm, Ext.Panel, {
    getAllChildren: function (panel, container) {
        /*Get children of passed panel or an empty array if it doesn't have them.*/
        var children = panel.items ? panel.items.items : [];
        /*For each child get their children and concatenate to result.*/
        Ext.each(children, function (child) {
            children = children.concat(container.getAllChildren(child, container));
        });
        return children;
    },

    resetFilter: function() {
        var thisPanel= this;
        var fields = this.getAllChildren(this, this);

        // Remove all rows except of the first one
        Ext.each(fields, function (item) {
            if (/^remove_row_\d+$/i.test(item.name) && item.name !== 'remove_row_1') {
                thisPanel.removeRow(item);
            }
        });

        Ext.each(fields, function (item) {
            if(typeof item.reset !== 'undefined') {
                item.reset();
            }
        });

        this.applyFilter();
    },

    getFieldValues: function() {
        var fields = this.getAllChildren(this, this);
        var o = {},
            n,
            key,
            val;
        Ext.each(fields, function (f) {
            if (typeof f.disabled !== 'undefined' && typeof f.getName !== 'undefined' && !f.disabled) {
                n = f.getName();
                key = o[n];
                val = f.getValue();

                if(Ext.isDefined(key)){
                    if(Ext.isArray(key)){
                        o[n].push(val);
                    }else{
                        o[n] = [key, val];
                    }
                }else{
                    o[n] = val;
                }
            }
        });
        return o;
    },

    applyFilter: function() {
        var booIsValidForm = true;
        var fields = this.getAllChildren(this, this);
        Ext.each(fields, function (item) {
            if(typeof item.isValid !== 'undefined') {
                if(!item.isValid()) {
                    booIsValidForm = false;
                }
            }
        });

        if (booIsValidForm) {
            this.owner.runSearch();
        }
    },

    showFilterOptions: function(combo, filterComboRec, index, fieldsComboRec, fired) {
        var thisForm  = this;
        var form      = combo.ownerCt.ownerCt;
        var formItems = form.items;

        var fieldsCombo  = formItems.item(1).items.item(0);
        var textField    = formItems.item(3).items.item(0);
        var optionsField = formItems.item(4).items.item(0);
        var dateField    = formItems.item(5).items.item(0);

        if(!fieldsComboRec) {
            var fieldsComboVal = fieldsCombo.getValue();

            var idx = fieldsCombo.store.find(fieldsCombo.valueField, fieldsComboVal);
            if (idx !== -1) {
                fieldsComboRec = fieldsCombo.store.getAt(idx);
            }
        }

        if(fieldsComboRec && filterComboRec) {
            var booShowDateFrom = false;
            var booShowText     = false;

            var filterVal = filterComboRec.data.filter_id;
            switch (fieldsComboRec.data.q_field_type) {
                case 'yes_no':
                case 'billing_frequency':
                    break;

                case 'short_date':
                case 'date':
                case 'full_date':
                    if(['is', 'is_not', 'is_before', 'is_after', 'is_between_today_and_date', 'is_between_date_and_today'].has(filterVal)) {
                        booShowDateFrom = true;
                    }
                    break;

                case 'float':
                case 'number':
                    booShowText = true;
                    break;

                // case 'text':
                default:
                    if(['contains', 'does_not_contain', 'is', 'is_not', 'starts_with', 'ends_with'].has(filterVal)) {
                        booShowText = true;
                    }
                    break;
            }

            var booShowOptions = !empty(fieldsComboRec.data.options) && fieldsComboRec.data.options.length && !empty(fieldsComboRec.data.q_field_unique_id) && fieldsComboRec.data.q_field_unique_id != 'qf_referred_by';
            booShowText = booShowText && !booShowOptions;

            // Show only required fields, hide others
            textField.ownerCt.setVisible(booShowText);
            textField.setVisible(booShowText);
            optionsField.ownerCt.setVisible(booShowOptions);
            optionsField.setVisible(booShowOptions);

            // Disable other fields - for correct form validation
            textField.setDisabled(!booShowText);
            optionsField.setDisabled(!booShowOptions);

            dateField.ownerCt.setVisible(booShowDateFrom);
            dateField.setVisible(booShowDateFrom);
            dateField.setDisabled(!booShowDateFrom);

            if (booShowOptions)
            {
                thisForm.options_store.loadData(fieldsComboRec.data.options);

                if (fired) {
                    optionsField.setValue('');
                }
            }
        }
    },

    showFilter: function(fieldsCombo, fieldsComboRec) {
        var form = fieldsCombo.ownerCt.ownerCt;
        var formItems = form.items;
        var filterCombo = formItems.item(2).items.items[0];
        var fieldType = formItems.item(6);

        var filterComboData = [];
        fieldType.setValue(fieldsComboRec.data.q_field_type);

        var filterComboWidth = 200;
        switch (fieldsComboRec.data.q_field_type) {
            case 'float':
            case 'number':
                filterComboWidth = 170;
                filterComboData = this.arrSearchFilters['number'];
                break;

            case 'combo':
            case 'assessment':
            case 'office':
            case 'agent':
            case 'language':
            case 'radio':
            case 'seriousness':
                filterComboWidth = 170;
                filterComboData = this.arrSearchFilters['combo'];
                break;


            case 'date':
            case 'full_date':
                filterComboWidth = 170;
                filterComboData = this.arrSearchFilters['date'];
                break;

            case 'status':
                filterComboWidth = 170;
                filterComboData = this.arrSearchFilters['status'];
                break;

            // case 'textfield':
            default:
                filterComboWidth = 170;
                filterComboData = this.arrSearchFilters['text'];
                break;
        }

        filterCombo.store.loadData(filterComboData);

        var filterValIndex = 0;
        var filterValue  = filterComboData[filterValIndex][0];
        var filterRecord = filterCombo.store.getAt(filterValIndex);

        filterCombo.setValue(filterValue);
        filterCombo.fireEvent('beforeselect', filterCombo, filterRecord, filterValue, fieldsComboRec, true);
        filterCombo.show();
        filterCombo.ownerCt.show();

        // Update combo width in relation to the filter type
        filterCombo.setWidth(filterComboWidth);
    },

    removeRow: function(btn) {
        var thisForm = this;
        thisForm.currentRowsCount -= 1;
        btn.ownerCt.ownerCt.hide();
        btn.ownerCt.ownerCt.removeAll();
        Ext.getCmp('advanced-search-add-row-button').setDisabled(false);
        thisForm.denyRemoveSingleRow();

        var tabPanel = Ext.getCmp(thisForm.owner.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }

        var advSearchPanel = Ext.getCmp(thisForm.owner.panelType + '-advanced-search');
        if (advSearchPanel) {
            advSearchPanel.prospectsAdvancedSearchGrid.fixProspectsAdvancedSearchGridHeight();
        }
    },

    addNewRow: function(booFirst) {
        var form = Ext.getCmp('advanced-search-form');
        var filterStore = new Ext.data.ArrayStore({
            fields: ['filter_id', 'filter_name'],
            data: []
        });

        // Generate row id + save max index
        var maxRowsCountField = Ext.getCmp('max_rows_count');
        var maxRowsCount = parseInt(maxRowsCountField.getValue(), 10);

        if(empty(maxRowsCount)) {
            maxRowsCount = 1;
        } else {

            var fields = this.getAllChildren(this, this);

            var booFound = false;
            do {
                booFound = false;
                Ext.each(fields, function (item) {
                    if(typeof item.getName !== 'undefined') {
                        if(item.getName() === 'operator_' + maxRowsCount) {
                            booFound = true;
                        }
                    }
                });

                if (booFound) {
                    maxRowsCount += 1;
                }
            } while (booFound);
        }

        if (this.currentRowsCount >= advancedSearchRowsMaxCount) {
            return false;
        }

        maxRowsCountField.setValue(maxRowsCount);
        this.currentRowsCount += 1;

        var newEl = [
            {
                width: 115,
                bodyStyle: 'background-color: #E8E9EB;',
                items: [
                    {
                        xtype: 'combo',
                        name: 'operator_' + maxRowsCount,
                        hiddenName: 'operator_' + maxRowsCount,
                        store: this.operatorsStore,
                        displayField: 'operator_name',
                        valueField:   'operator_id',
                        mode: 'local',
                        value: 'and',
                        hidden: booFirst,
                        width: 100,
                        forceSelection: true,
                        triggerAction: 'all',
                        selectOnFocus:true,
                        typeAhead: false
                    }, {
                        xtype: 'label',
                        text: _('Search Criteria'),
                        style: 'display: block; margin-top: 10px; font-size: 16px',
                        hidden: !booFirst
                    }
                ]
            }, {
                width: 260,
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                items: {
                    xtype: 'combo',
                    name: 'field_' + maxRowsCount,
                    width: 250,
                    listWidth: 250,
                    allowBlank: false,
                    store: this.fieldsStore,
                    displayField: 'q_field_prospect_profile_label',
                    valueField:   'q_field_id',
                    hiddenName: 'field_' + maxRowsCount,
                    typeAhead: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    emptyText:'Select option...',
                    searchContains: true,
                    selectOnFocus:true,
                    listeners: {
                        beforeselect: this.showFilter.createDelegate(this),
                        afterrender: function() {
                            this.reset();
                        }
                    }
                }
            }, {
                width: 180,
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                hidden: true,
                items: {
                    xtype: 'combo',
                    name: 'filter_' + maxRowsCount,
                    hiddenName: 'filter_' + maxRowsCount,
                    bodyStyle: 'background-color:white;',
                    allowBlank: false,
                    store: filterStore,
                    displayField: 'filter_name',
                    valueField:   'filter_id',
                    hidden: true,
                    typeAhead: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus: true,
                    listeners: {
                        beforeselect: this.showFilterOptions.createDelegate(this),
                        afterrender: function() {
                            this.reset();
                        }
                    },
                    width: 170
                }
            }, {
                width: 210,
                style: 'padding-left: 5px;',
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                hidden: true,
                items: {
                    xtype: 'textfield',
                    name: 'text_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 190
                }
            }, {
                width: 210,
                style: 'padding-left: 5px;',
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                hidden: true,
                items: {
                    xtype: 'combo',
                    name: 'options_' + maxRowsCount,
                    hiddenName: 'options_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 190,
                    store: this.options_store,
                    displayField: 'q_field_option_label',
                    valueField:   'q_field_option_id',
                    typeAhead: false,
                    mode: 'local',
                    triggerAction: 'all',
                    editable: true
                }
            }, {
                width: 130,
                bodyStyle: 'background-color: #E8E9EB; margin-bottom: 5px;',
                hidden: true,
                items: {
                    xtype: 'datefield',
                    name: 'date_' + maxRowsCount,
                    hiddenName: 'date_' + maxRowsCount,
                    format: dateFormatFull,
                    altFormats: dateFormatFull + '|' + dateFormatShort,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 120
                }
            }, {
                xtype: 'hidden',
                name:  'field_type_' + maxRowsCount
            }, {
                bodyStyle: 'background-color: #E8E9EB;',
                items: {
                    xtype: 'button',
                    cls: 'applicant-advanced-search-hide-row',
                    text: '<i class="las la-times"></i>',
                    hideMode: 'visibility',
                    disabled: booFirst,
                    name: 'remove_row_' + maxRowsCount,
                    tooltip: 'Remove this row',
                    handler: this.removeRow.createDelegate(this)
                }
            }
        ];

        form.add({
            xtype: 'panel',
            layout: 'column',
            bodyStyle: 'background-color: #E8E9EB; font-size: 12px;',
            items: newEl
        });

        form.doLayout();

        if (this.currentRowsCount >= advancedSearchRowsMaxCount) {
            Ext.getCmp('advanced-search-add-row-button').setDisabled(true);
        }

        var tabPanel = Ext.getCmp(this.owner.panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }

        var advSearchPanel = Ext.getCmp(this.owner.panelType + '-advanced-search');
        if (advSearchPanel) {
            advSearchPanel.prospectsAdvancedSearchGrid.fixProspectsAdvancedSearchGridHeight();
        }

        this.denyRemoveSingleRow();
    },

    denyRemoveSingleRow: function() {
        var thisPanel = this;
        var form = Ext.getCmp('advanced-search-form');
        Ext.each(form.items.items, function (rec) {
            if (rec.getXType() == 'panel' && rec.items.items.length) {
                rec.items.items[7].items.items[0].setVisible(thisPanel.currentRowsCount > 1);
                rec.items.items[7].items.items[0].setDisabled(thisPanel.currentRowsCount == 1);
                rec.items.items[0].items.items[0].setVisible(false);
                rec.items.items[0].items.items[1].setVisible(true);
                return false;
            }
        });
    },
});
