var totalSearchRows = 0;
var CompaniesAdvancedSearchForm = function() {
    var advancedSearchForm = this;
    this.fieldsStore = new Ext.data.ArrayStore({
        fields: ['field_id', 'field_name', 'field_type'],
        data: arrSearchFields
    });

    this.operatorsStore = new Ext.data.ArrayStore({
        fields: ['operator_id', 'operator_name'],
        data:   [['and', 'AND'], ['or', 'OR']]
    });


    var savedSearchesComboStore = new Ext.data.Store({
        autoLoad: true,
        proxy: new Ext.data.HttpProxy({
            url: baseUrl + '/advanced-search/get-list',
            method: 'post'
        }),

        reader: new Ext.data.JsonReader({
            root: 'rows',
            totalProperty: 'totalCount'
        }, [
            {name: 'savedSearchId'},
            {name: 'savedSearchName'},
            {name: 'savedSearchQuery'}
        ])
    });

    var resultTpl = new Ext.XTemplate(
        '<tpl for=".">',
            '<tpl if="savedSearchId == 0">',
                '<div class="x-combo-list-item" style="font-weight: bold">{savedSearchName}</div>',
            '</tpl>',
            '<tpl if="savedSearchId &gt; 0">',
                '<div class="x-combo-list-item">' +
                    '<span style="float: left; overflow: hidden; width: 160px;">{savedSearchName}</span>' +
                    '<span style="float: right;">' +
                        '<a id={[this.getDeleteLinkId(values)]} href="#" title="Click to delete this search">' +
                            '<img src="' + topBaseUrl + '/images/icons/delete.png" alt="Delete" />' +
                        '</a>',
                    '</span>' +
                    '<span style="float: right">' +
                        '<a id={[this.getEditLinkId(values)]} href="#" title="Click to rename this search">' +
                            '<img src="' + topBaseUrl + '/images/icons/pencil.png" alt="Edit" />' +
                        '</a>',
                    '</span>' +
                    '<br style="clear:both"/>' +
                '</div>',
            '</tpl>',
        '</tpl>',
        {
            getEditLinkId: function(data) {
                var result = Ext.id();
                this.addListener.defer(1, this, [result, 'edit', data]);
                return result;
            },

            getDeleteLinkId: function(data) {
                var result = Ext.id();
                this.addListener.defer(1, this, [result, 'delete', data]);
                return result;
            },

            addListener: function(id, action, data) {
                Ext.get(id).on('click', function(e){
                    e.stopEvent();
                    if (action === 'edit') {
                        advancedSearchForm.renameSearch(data);
                    } else {
                        advancedSearchForm.deleteSearch(data);
                    }
                })
            }
        }
    );

    this.savedSearchesCombo = new Ext.form.ComboBox({
        store: savedSearchesComboStore,
        width: 200,
        listWidth: 200,
        mode: 'local',
        hideLabel: true,
        displayField: 'savedSearchName',
        valueField: 'savedSearchId',
        typeAhead: true,
        forceSelection: true,
        triggerAction: 'all',
        emptyText: 'Save as new search',
        selectOnFocus: true,
        editable: false,
        tpl: resultTpl,
        style: 'text-align: left;',
        listeners: {
            'select' : function(combo, record) {
                // Skip the first one
                if (!record.data.savedSearchId) {
                    return;
                }

                try {
                    var form = Ext.getCmp('advanced-search-form');

                    // Delete already created rows...
                    form.items.each(function(item) {
                        if (item.getXType() == 'panel' && item.items.length) {
                            var btn = item.items.itemAt(0).items.itemAt(0);
                            if (btn.isVisible()) {
                                btn.getEl().dom.click();
                            }
                        }
                    });

                    // Extract saved data
                    var result = Ext.decode(record.data.savedSearchQuery);

                    // Create new rows and fill with data
                    var currentRow = 1;
                    var realRowNumber = 0;
                    for (i = 1; i <= result.max_rows_count; i++) {
                        if (typeof(result['operator_' + i]) != 'undefined') {
                            if (currentRow > 1) {
                                advancedSearchForm.addNewRow(false);
                            }

                            var rowsCount = 0;
                            form.items.each(function(item) {
                                if (item.getXType() == 'panel' && item.items.length && rowsCount < currentRow) {
                                    realRowNumber = item.rowNumber;
                                    rowsCount++;
                                }
                            });

                            var operatorField = form.getForm().findField('operator_' + realRowNumber);
                            operatorField.setValue(result['operator_' + i]);

                            var valueField = form.getForm().findField('field_' + realRowNumber);
                            valueField.setValue(result['field_' + i]);
                            var filterRecordIndex = valueField.store.findExact('field_id', result['field_' + i]);
                            var filterRecord = valueField.store.getAt(filterRecordIndex);
                            valueField.fireEvent('beforeselect', valueField, filterRecord, result['field_' + i], 0);

                            var filterField = form.getForm().findField('filter_' + realRowNumber);
                            filterField.setValue(result['filter_' + i]);
                            filterRecordIndex = filterField.store.findExact('filter_id', result['filter_' + i]);
                            filterRecord = filterField.store.getAt(filterRecordIndex);
                            filterField.fireEvent('beforeselect', filterField, filterRecord, result['filter_' + i], 0);

                            var textField = form.getForm().findField('text_' + realRowNumber);
                            textField.setValue(result['text_' + i]);

                            if (!empty(result['date_from_' + i])) {
                                var dateFromField = form.getForm().findField('date_from_' + realRowNumber);
                                dateFromField.setValue(new Date(result['date_from_' + i]));
                            }

                            if (!empty(result['date_to_' + i])) {
                                var dateToField = form.getForm().findField('date_to_' + realRowNumber);
                                dateToField.setValue(new Date(result['date_to_' + i]));
                            }

                            currentRow++;
                        }
                    }

                    // And update max rows count - used on server
                    Ext.getCmp('max_rows_count').setValue(realRowNumber);
                } catch (err) {
                }
            }
        }
    });

    CompaniesAdvancedSearchForm.superclass.constructor.call(this, {
        cls: 'extjs-panel-with-border',
        id: 'extjs-panel-with-border',
        bodyStyle: 'padding: 5px;',
        buttonAlign: 'left',
        layout: 'border',
        height: 165,
        items: [
            {
                xtype: 'panel',
                region: 'center',
                items: [
                    {
                        style: 'padding: 5px; text-align: left; color: #677788; font-size: 12px;',
                        html: 'Enter the criteria for the advanced search:'
                    }, {
                        id: 'advanced-search-form',
                        xtype: 'form',
                        bodyStyle: 'padding: 5px;',
                        items: [{
                            xtype: 'hidden',
                            id: 'max_rows_count',
                            value: 0
                        }]
                    }
                ]
            }, {
                xtype: 'panel',
                region: 'east',
                buttonAlign: 'center',
                width: 205,
                items: [
                    {
                        xtype: 'form',
                        items: [
                            {
                                style: 'padding: 5px 0 10px; text-align: left; color: #677788; font-size: 12px;',
                                html: 'Saved advanced searches:'
                            }, this.savedSearchesCombo, {
                                xtype: 'button',
                                text: '<i class="lar la-save"></i>' + _('Save current search'),
                                style: 'padding: 5px 0 0 0;',
                                handler: this.saveFilter.createDelegate(this)
                            }
                        ]
                    }
                ]
            }
        ],

        buttons: [
            {
                xtype: 'label',
                style: 'padding-left: 93px;',
                html: '&nbsp;'
            }, {
                id: 'advanced-search-add-row',
                text: '<i class="las la-plus"></i>' +_('Add search row'),
                handler: this.addNewRow.createDelegate(this, [false])
            }, {
                text: '<i class="las la-search"></i>' + _('Search'),
                handler: this.applyFilter.createDelegate(this)

            }, {
                text: '<i class="las la-undo-alt"></i>' + _('Reset'),
                handler: this.resetFilter.createDelegate(this)
            }
        ]
    });

    this.on('render', this.addNewRow.createDelegate(this, [true]), this);
};

Ext.extend(CompaniesAdvancedSearchForm, Ext.Panel, {
    resetFilter: function() {
        Ext.getCmp('advanced-search-form').getForm().reset();

        var companiesForm = Ext.getCmp('companies-grid');
        var params = companiesForm.store.baseParams || {};
        Ext.apply(params, {advanced_search_params: ''});

        companiesForm.store.reload();
    },

    renameSearch: function(data) {
        var searchForm = this;
        Ext.Msg.prompt('Enter the name of the saved search', 'New name:', function(btn, text){
            if(btn == 'ok' && text){
                Ext.getBody().mask('Processing...');
                Ext.Ajax.request({
                    url: baseUrl + '/advanced-search/rename',
                    params: {
                        search_id: data.savedSearchId,
                        search_name: text
                    },

                    success: function (res) {
                        Ext.getBody().unmask();

                        var result = Ext.decode(res.responseText);
                        if (result.success) {
                            searchForm.savedSearchesCombo.store.reload();
                            searchForm.savedSearchesCombo.setValue(0);
                            Ext.simpleConfirmation.success(result.msg);
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Cannot rename. Please try again later.');
                    }
                });
            }
        }, this, false, data.savedSearchName);

    },

    deleteSearch: function(data) {
        var searchForm = this;

        var question = String.format(
            'You are about to delete <i>{0}</i> search. Are you sure you want to proceed?',
            data.savedSearchName
        );

        Ext.Msg.confirm('Please confirm', question, function (btn) {
            if (btn === 'yes') {
                Ext.getBody().mask('Processing...');
                Ext.Ajax.request({
                    url: baseUrl + '/advanced-search/delete',
                    params: {
                        search_id: data.savedSearchId
                    },

                    success: function (res) {
                        Ext.getBody().unmask();

                        var result = Ext.decode(res.responseText);
                        if (result.success) {
                            searchForm.savedSearchesCombo.store.reload();
                            searchForm.savedSearchesCombo.setValue(0);
                            Ext.simpleConfirmation.success(result.msg);
                        } else {
                            Ext.simpleConfirmation.error(result.msg);
                        }
                    },

                    failure: function () {
                        Ext.getBody().unmask();
                        Ext.simpleConfirmation.error('Cannot delete. Please try again later.');
                    }
                });
            }
        });
    },

    saveFilterAction: function(params) {
        var searchForm = this;

        Ext.getBody().mask('Saving...');
        Ext.Ajax.request({
            url: baseUrl + '/advanced-search/save',
            params: params,

            success: function (res) {
                Ext.getBody().unmask();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    searchForm.savedSearchesCombo.store.reload();
                    Ext.simpleConfirmation.success(result.msg);
                } else {
                    Ext.simpleConfirmation.error(result.msg);
                }
            },

            failure: function () {
                Ext.getBody().unmask();
                Ext.simpleConfirmation.error('Cannot save information. Please try again later.');
            }
        });
    },

    saveFilter: function() {
        var searchForm = this;
        var form = Ext.getCmp('advanced-search-form').getForm();

        if(form.isValid()) {
            var selectedSearchId = searchForm.savedSearchesCombo.getValue();
            var params = {
                search_id:    selectedSearchId,
                search_name:  '',
                search_query: Ext.encode(form.getFieldValues())
            };


            if (empty(selectedSearchId)) {
                Ext.Msg.prompt('Enter the name of the saved search', 'Name:', function(btn, text){
                    if(btn == 'ok' && text){
                        params.search_name = text;
                        searchForm.saveFilterAction(params);
                    }
                }, this, false, params.search_name);
            } else {
                params.search_name = searchForm.savedSearchesCombo.getRawValue();
                searchForm.saveFilterAction(params);
            }
        }
    },

    applyFilter: function() {
        var form = Ext.getCmp('advanced-search-form').getForm();

        if(form.isValid()) {
            var companiesForm = Ext.getCmp('companies-grid');
            var params = companiesForm.store.baseParams || {};
            Ext.apply(params, {advanced_search_params: Ext.encode(form.getFieldValues())});

            companiesForm.store.reload();
        }
    },

    showFilterOptions: function(combo, filterComboRec, index, fieldsComboRec) {
        var form      = combo.ownerCt;
        var formItems = form.items;

        var fieldsCombo      = formItems.item(2).items.item(0);
        var dateFromField    = formItems.item(4).items.item(0);
        var dateToFieldLabel = formItems.item(5).items.item(0);
        var dateToField      = formItems.item(5).items.item(1);
        var textField        = formItems.item(6).items.item(0);

        if(!fieldsComboRec) {
            var fieldsComboVal = fieldsCombo.getValue();

            var idx = fieldsCombo.store.find(fieldsCombo.valueField, fieldsComboVal);
            if (idx !== -1) {
                fieldsComboRec = fieldsCombo.store.getAt(idx);
            }
        }

        if(fieldsComboRec && filterComboRec) {
            var booShowDateFrom = false;
            var booShowDateTo   = false;
            var booShowText     = false;

            var filterVal = filterComboRec.data.filter_id;
            switch (fieldsComboRec.data.field_type) {
                case 'yes_no':
                case 'billing_frequency':
                case 'company_status':
                    break;
                
                case 'short_date':
                case 'date':
                    if(['is', 'is_not', 'is_before', 'is_after', 'is_between_2_dates', 'is_between_today_and_date', 'is_between_date_and_today'].has(filterVal)) {
                        booShowDateFrom = true;
                    }

                    if(['is_between_2_dates'].has(filterVal)) {
                        booShowDateTo = true;
                    }

                    if(['is_in_next_days', 'is_in_next_months', 'is_in_next_years'].has(filterVal)) {
                        booShowText = true;
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

            // Show only required fields, hide others
            dateFromField.setVisible(booShowDateFrom);
            dateToField.setVisible(booShowDateTo);
            dateToFieldLabel.setVisible(booShowDateTo);
            textField.setVisible(booShowText);

            // Disable other fields - for correct form validation
            dateFromField.setDisabled(!booShowDateFrom);
            dateToField.setDisabled(!booShowDateTo);
            textField.setDisabled(!booShowText);
        }
    },

    showFilter: function(fieldsCombo, fieldsComboRec) {
        /*
            Example:
            Show all companies that:
              Trial = Yes
              and last logged in < 2011-07-10
              and last Client Account uploaded < 4 months ago
         */
        var form = fieldsCombo.ownerCt.ownerCt;
        var formItems = form.items;
        var filterCombo = formItems.item(3);
        var fieldType   = formItems.item(7);
        
        var filterComboData = [];
        fieldType.setValue(fieldsComboRec.data.field_type);

        var filterComboWidth = 200;
        switch (fieldsComboRec.data.field_type) {
            case 'yes_no':
                filterComboWidth = 80;
                filterComboData = arrSearchFilters['yes_no'];
                break;

            case 'billing_frequency':
                filterComboWidth = 130;
                filterComboData = arrSearchFilters['billing_frequency'];
                break;

            case 'company_status':
                filterComboWidth = 130;
                filterComboData = arrSearchFilters['company_status'];
                break;

            case 'short_date':
            case 'date':
                filterComboWidth = 230;
                filterComboData = arrSearchFilters['date'];
                break;

            case 'float':
            case 'number':
                filterComboWidth = 80;
                filterComboData = arrSearchFilters['number'];
                break;

            // case 'text':
            default:
                filterComboWidth = 230;
                filterComboData = arrSearchFilters['text'];
                break;
        }

        filterCombo.store.loadData(filterComboData);

        var filterValIndex = 0;
        var filterValue  = filterComboData[filterValIndex][0];
        var filterRecord = filterCombo.store.getAt(filterValIndex);

        filterCombo.setValue(filterValue);
        filterCombo.fireEvent('beforeselect', filterCombo, filterRecord, filterValue, fieldsComboRec);
        filterCombo.show();

        // Update combo width in relation to the filter type
        filterCombo.setWidth(filterComboWidth);
        filterCombo.getResizeEl().setWidth(filterComboWidth);
    },

    removeRow: function() {
        totalSearchRows--;
        this.ownerCt.ownerCt.removeAll();

        if (totalSearchRows < 3) {
            Ext.getCmp('advanced-search-add-row').setDisabled(false);
        }

        Ext.getCmp('extjs-panel-with-border').fixParentGridHeight();
    },

    addNewRow: function(booFirst) {
        if (totalSearchRows >= 3) {
            return;
        }

        var form = Ext.getCmp('advanced-search-form');
        var filterStore = new Ext.data.ArrayStore({
            fields: ['filter_id', 'filter_name'],
            data: []
        });

        // Generate row id + save max index
        var maxRowsCountField = Ext.getCmp('max_rows_count');
        var maxRowsCount = parseInt(maxRowsCountField.getValue(), 10);

        if(empty(maxRowsCount) || form.getForm().findField('operator_' + maxRowsCount)) {
           maxRowsCount = parseInt(maxRowsCount,10) + 1;
        }
        maxRowsCountField.setValue(maxRowsCount);

        var newEl = [
            {
                width: 30,
                items: {
                    xtype: 'button',
                    ref: '../../advanced_search_button_' + maxRowsCount,
                    cls: 'companies-advanced-search-hide-row',
                    text: '<i class="las la-times"></i>',
                    hidden: booFirst,
                    hideMode: booFirst ? 'visibility' : 'display',
                    rowNumber: maxRowsCount,
                    handler: this.removeRow
                }
            }, {
                width: 80,
                items: {
                    xtype: 'combo',
                    name: 'operator_' + maxRowsCount,
                    store: this.operatorsStore,
                    displayField: 'operator_name',
                    valueField:   'operator_id',
                    mode: 'local',
                    value: 'and',
                    hidden: booFirst,
                    hideMode: booFirst ? 'visibility' : 'display',
                    width: 85,
                    forceSelection: true,
                    triggerAction: 'all',
                    selectOnFocus:true,
                    typeAhead: false
                }
            }, {
                width: 230,
                items: {
                    xtype: 'combo',
                    name: 'field_' + maxRowsCount,
                    width: 225,
                    listWidth: 225,
                    allowBlank: false,
                    store: this.fieldsStore,
                    style: 'margin-bottom: 5px;',
                    displayField:'field_name',
                    valueField:   'field_id',
                    typeAhead: false,
                    mode: 'local',
                    forceSelection: true,
                    triggerAction: 'all',
                    emptyText:'Select option...',
                    selectOnFocus:true,
                    listeners: {
                        beforeselect: this.showFilter.createDelegate(this)
                    }
                }
            }, {
                xtype: 'combo',
                name: 'filter_' + maxRowsCount,
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
                    beforeselect: this.showFilterOptions.createDelegate(this)
                }
            }, {
                width: 140,
                style: 'padding-left: 5px;',
                items: {
                    xtype: 'datefield',
                    name: 'date_from_' + maxRowsCount,
                    allowBlank: false,
                    disabled: true,
                    hidden: true
                }
            }, {
                width: 155,
                style: 'padding-left: 5px;',
                layout: 'column',
                items: [
                    {
                        xtype: 'label',
                        html:  '&amp;',
                        style: 'padding: 5px 0 5px 0;',
                        hidden: true,
                        width: 15
                    }, {
                        name: 'date_to_' + maxRowsCount,
                        xtype: 'datefield',
                        width: 140,
                        allowBlank: false,
                        hidden: true,
                        disabled: true
                    }
                ]
            }, {
                width: 210,
                style: 'padding-left: 5px;',
                items: {
                    xtype: 'textfield',
                    name: 'text_' + maxRowsCount,
                    allowBlank: false,
                    hidden: true,
                    disabled: true,
                    width: 200
                }
            }, {
                xtype: 'hidden',
                name: 'field_type_' + maxRowsCount
            }
        ];


        form.add({
            xtype: 'panel',
            layout: 'column',
            rowNumber: maxRowsCount,
            items: newEl
        });
        form.doLayout();

        totalSearchRows++;

        if (totalSearchRows >= 3) {
            Ext.getCmp('advanced-search-add-row').setDisabled(true);
        }

        this.fixParentGridHeight();
    },

    fixParentGridHeight: function () {
        // Change this panel's height
        // Minimum height is 165px,
        // and in relation to the shown rows (42px for 1) and additionl padding 70 (for labels, toolbar)
        this.setHeight(Math.max(totalSearchRows * 42 + 70, 165));

        // Change parent grid's height
        var grid = Ext.getCmp('companies-grid');
        if (grid) {
            grid.updatedThisGridHeight();
        }
    }
});

var CompaniesAdvancedSearch = function() {
    CompaniesAdvancedSearch.superclass.constructor.call(this, {
        title:       '<i class="las la-search"></i>' + _('Advanced Search'),
        applyTo:     'companies_advanced_search_container',
        collapsible: true,
        collapsed:   false,
        items: new CompaniesAdvancedSearchForm(),

        listeners: {
            'collapse': function() {
                // Change parent grid's height
                var grid = Ext.getCmp('companies-grid');
                if (grid) {
                    grid.updatedThisGridHeight();
                }
            },

            'expand': function() {
                // Change parent grid's height
                var grid = Ext.getCmp('companies-grid');
                if (grid) {
                    grid.updatedThisGridHeight();
                }
            }
        }
    });
};

Ext.extend(CompaniesAdvancedSearch, Ext.Panel, {
});