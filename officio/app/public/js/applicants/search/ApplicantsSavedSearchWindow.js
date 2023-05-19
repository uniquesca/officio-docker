ApplicantsSavedSearchWindow = function(config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisSearchWindow = this;
    var subjectColId = Ext.id();
    this.minGridPanelHeight = 50;
    this.SavedSearchesGrid = new Ext.grid.GridPanel({
        autoWidth: true,
        height: this.minGridPanelHeight,
        autoScroll: true,
        hideHeaders: true,
        loadMask: {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows: false,
        cls: 'hidden-bottom-scroll',
        viewConfig: {
            scrollOffset: 2,
            emptyText: _('There are no searches to show.')
        },

        store: new Ext.data.Store({
            autoLoad: true,
            remoteSort: true,

            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/applicants/search/get-saved-searches'
            }),

            baseParams: {
                search_type: Ext.encode(config.panelType)
            },

            reader: new Ext.data.JsonReader({
                root: 'items',
                totalProperty: 'count',
                idProperty: 'search_id',
                fields: [
                    'search_id',
                    'search_type',
                    'search_name',
                    'search_can_be_set_default',
                    'search_default'
                ]
            })
        }),

        columns: [
            {
                id: subjectColId,
                dataIndex: 'search_name',
                renderer: function(val, i, rec) {
                    return String.format(
                        '<a href="#" class="applicant_saved_search_name" {0} onclick="return false;">{1}</a>',
                        rec.data.search_type == 'system' ? 'style="font-weight: bold;"' : '',
                        val
                    );
                }
            }, {
                dataIndex: 'search_type',
                width: 90,
                align: 'right',
                renderer: function(val, i, rec) {
                    var show = '';
                    if (rec.data.search_type == 'system') {
                        if (rec.data.search_can_be_set_default) {
                            show = rec.data.search_default ?
                                'default' :
                                '<a href="#" class="applicant_saved_search_set_as_default" onclick="return false;">' +
                                    _('(set as default)') +
                                    '</a>';
                        }
                    } else {
                        show = String.format(
                            '<a href="#" class="applicant_saved_search_details las la-pen" onclick="return false;">&nbsp;</a>' +
                            '<a href="#" class="applicant_saved_search_delete las la-trash" onclick="return false;">&nbsp;</a>',
                            topBaseUrl
                        );
                    }
                    return '<div class="gray_txt">' + show + '</div>';
                }
            }
        ]
    });
    this.conflictSearchField = new Ext.form.TextField({
        id: 'conflict_search_from',
        xtype: 'textfield',
        labelStyle: 'background:url(' + topBaseUrl + '/images/orange-arrow.gif) no-repeat; padding-left:10px; background-position:0 50%; width: 95px;',
        width: 155,
        enableKeyEvents: true,
        hideLabel: true,
        listeners: {
            'keypress': function(field, event){
                if (event.getKey() == event.ENTER && field.getValue().length>=2){
                    thisSearchWindow.owner.conflictSearchFieldValue = this.getValue();
                    thisSearchWindow.owner.refreshApplicantsSearchList(true);
                    thisSearchWindow.hideThisWindow();
                }
            },
            'valid': function(field){
                if (field.getValue().length===1) {
                    field.markInvalid('Please enter at least 2 characters');
                } else {
                    field.getEl().removeClass('x-form-invalid');
                }
            }
        }
    });
    this.SavedSearchesGrid.on('cellclick', this.onCellClick, this);
    this.SavedSearchesGrid.getSelectionModel().on('beforerowselect', function(){ return false;}, this);
    this.SavedSearchesGrid.getStore().on('load', this.fixGridHeight.createDelegate(this), this);

    ApplicantsSavedSearchWindow.superclass.constructor.call(this, {
        layout: 'fit',
        closable: false,
        resizable: false,
        plain: true,
        border: false,
        width: 300,
        autoHeight: true,
        frame: false,
        items: [

            {
                xtype: 'panel',
                layout: 'table',
                autoHeight: true,
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%',
                            padding: '5px 5px 10px'
                        }
                    },
                    columns: 3
                },

                items: [
                    {
                        cls: 'gray_txt',
                        html: _('Saved Searches:')
                    }, {
                        xtype: 'box',
                        cellCls: 'applicant-button',
                        autoEl: {tag: 'img', src: topBaseUrl + '/images/refresh12.png', width: 12, height: 12},
                        listeners: {
                            scope: this,
                            render: function(c){
                                c.getEl().on('click', thisSearchWindow.refreshSavedSearchesList.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }, {
                        xtype: 'box',
                        cellCls: 'applicant-button',
                        autoEl: {tag: 'img', src: topBaseUrl + '/images/default/close-button.gif', width: 11, height: 11},
                        listeners: {
                            scope: this,
                            render: function(c){
                                c.getEl().on('click', thisSearchWindow.hideThisWindow.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }
                ]
            },

            this.SavedSearchesGrid, {
                xtype: 'panel',
                layout: 'table',
                autoHeight: true,
                hidden: !(config.panelType === 'contacts' ? arrApplicantsSettings['access']['contact']['search']['show_conflict_of_interest'] : arrApplicantsSettings['access']['search']['show_conflict_of_interest']),

                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%',
                            padding: '5px 5px 10px'
                        }
                    },
                    columns: 4
                },

                items: [
                    {
                        cls: 'gray_txt',
                        html: '<img src="' + topBaseUrl + '/images/orange-arrow.gif" width="7" height="8" /> ' +
                            '<span>' + _('Conflict of Interest Search:') + '</span>'
                    }, this.conflictSearchField, {
                        xtype: 'button',
                        style: 'padding-left: 5px',
                        text: '<i class="las la-search"></i>',
                        handler: function() {
                            var searchVal = trim(thisSearchWindow.conflictSearchField.getValue());
                            if (searchVal.length >= 2) {
                                thisSearchWindow.owner.conflictSearchFieldValue = searchVal;
                                thisSearchWindow.owner.refreshApplicantsSearchList(true);
                                thisSearchWindow.hideThisWindow();
                            }
                        }
                    }
                ]
            }
        ]
    });

    // Automatically hide this window when click outside of it
    // E.g. when we switched to another tab
    Ext.getBody().on('click', function(e, t){
        var el = thisSearchWindow.getEl();

        if (!(el.dom === t || el.contains(t))) {
            thisSearchWindow.hideThisWindow();
        }
    });
};

Ext.extend(ApplicantsSavedSearchWindow, Ext.Window, {
    hideThisWindow: function() {
        this.hide();
    },

    refreshSavedSearchesList: function() {
        this.SavedSearchesGrid.store.reload();
        this.syncShadow();
    },

    fixGridHeight: function (store, records) {
        var gridView  = this.SavedSearchesGrid.getView();
        var rowHeight = records.length ? $(gridView.getRow(0)).outerHeight() : 0;
        var maxHeight = Ext.getBody().getHeight() - 270;
        var newHeight = rowHeight * records.length + 5;

        // Cannot be less than XX pixels - to be sure that "Loading" will be visible
        if (newHeight < this.minGridPanelHeight) {
            newHeight = this.minGridPanelHeight;
        }

        if (newHeight > maxHeight) {
            newHeight = maxHeight;
        }

        this.SavedSearchesGrid.setHeight(newHeight);
        this.syncShadow();

        var scroller = Ext.select('#' + this.SavedSearchesGrid.getId() + ' .x-grid3-scroller').elements[0];
        if (scroller.clientWidth === scroller.offsetWidth)
        {
          // no scroller
            gridView.scrollOffset = 2;
        } else {
          // there is a scroller
            gridView.scrollOffset = 19;
        }
        // Update columns width - in relation to the changed width
        gridView.fitColumns(false);
    },

    deleteSavedSearch: function(search_id) {
        var win = this;

        win.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/search/delete-saved-search',
            params: {
                search_id: search_id
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                var showMsgTime = 2000;
                if (resultData.success) {
                    showMsgTime = 1000;
                    var idx = win.SavedSearchesGrid.store.find('search_id', search_id);
                    if (idx != -1) { // Element exists - remove
                        win.SavedSearchesGrid.store.removeAt(idx);
                        win.syncShadow();
                    }
                }

                var msg = String.format(
                    '<span style="color: {0}">{1}</span>',
                    resultData.success ? 'black' : 'red',
                    resultData.success ? _('Done!') : resultData.message
                );

                win.getEl().mask(msg);
                setTimeout(function () {
                    win.getEl().unmask();
                }, showMsgTime);
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Selected search cannot be deleted. Please try again later.'));
                win.getEl().unmask();
            }
        });
    },

    setDefaultSavedSearch: function(search_id) {
        var win = this;

        win.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/search/set-default',
            params: {
                search_id: search_id
            },
            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                var showMsgTime = 2000;
                if (resultData.success) {
                    showMsgTime = 1000;
                    var idx = win.SavedSearchesGrid.store.find('search_id', search_id);
                    if (idx != -1) { // Element exists - mark it as default
                        win.SavedSearchesGrid.store.each(function(record, index){
                            record.beginEdit();
                            record.set('search_default', index == idx);
                            record.endEdit();
                        });
                        win.SavedSearchesGrid.store.commitChanges();

                        // And update global variable
                        if (win.panelType === 'contacts') {
                            arrApplicantsSettings['access']['contact']['search']['active_saved_search'] = search_id;
                        } else {
                            arrApplicantsSettings.active_saved_search = search_id;
                        }
                    }
                }

                var msg = String.format(
                    '<span style="color: {0}">{1}</span>',
                    resultData.success ? 'black' : 'red',
                    resultData.success ? _('Done!') : resultData.message
                );

                win.getEl().mask(msg);
                setTimeout(function () {
                    win.getEl().unmask();
                }, showMsgTime);
            },

            failure: function () {
                Ext.simpleConfirmation.error(_('Selected search cannot be set as default. Please try again later.'));
                win.getEl().unmask();
            }
        });
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var wnd = this;
        var rec = grid.getStore().getAt(rowIndex);

        var target = $(e.getTarget());
        if (target.hasClass('applicant_saved_search_name')) {
            // Update currently selected search
            this.owner.selectedSavedSearch = rec.data.search_id;
            this.owner.selectedSavedSearchName = rec.data.search_name;

            // And apply new search
            this.owner.refreshApplicantsSearchList(false);

            // Hide window instead of close - to avoid data loading
            this.hideThisWindow();
        } else if (target.hasClass('applicant_saved_search_set_as_default')) {
            this.setDefaultSavedSearch(rec.data.search_id);
        } else if (target.hasClass('applicant_saved_search_delete')) {
            var question = String.format(
                'Are you sure you want to delete <i>{0}</i>?',
                rec.data.search_name
            );

            Ext.Msg.confirm('Please confirm', question, function (btn) {
                if (btn === 'yes') {
                    wnd.deleteSavedSearch(rec.data.search_id);
                }
            });
        } else if (target.hasClass('applicant_saved_search_details')) {
            this.owner.openAdvancedSearchTab(rec.data.search_id, rec.data.search_name);

            // Hide window instead of close - to avoid data loading
            this.hideThisWindow();
        }
    }
});