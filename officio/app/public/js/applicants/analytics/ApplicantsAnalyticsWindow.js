ApplicantsAnalyticsWindow = function (config, owner) {
    this.owner = owner;
    Ext.apply(this, config);

    var thisAnalyticsWindow = this;
    var subjectColId        = Ext.id();
    this.minGridPanelHeight = 50;

    this.SavedAnalyticsGrid = new Ext.grid.GridPanel({
        autoWidth:        true,
        height:           this.minGridPanelHeight,
        autoScroll:       true,
        hideHeaders:      true,
        loadMask:         {msg: _('Loading...')},
        autoExpandColumn: subjectColId,
        stripeRows:       false,
        cls:              'hidden-bottom-scroll',
        viewConfig:       {
            scrollOffset: 2,
            emptyText:    _('There are no records to show.')
        },

        store: new Ext.data.Store({
            autoLoad:   true,
            remoteSort: true,

            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/applicants/analytics/load-list'
            }),

            baseParams: {
                analytics_type: Ext.encode(config.panelType)
            },

            reader: new Ext.data.JsonReader({
                root:          'items',
                totalProperty: 'count',
                idProperty:    'analytics_id',

                fields: [
                    'analytics_id',
                    'analytics_name',
                    'analytics_params'
                ]
            }),

            listeners: {
                'loadexception': function (e, store, response) {
                    var resultData   = Ext.decode(response.responseText);
                    var errorMessage = resultData && resultData.msg ? resultData.msg : 'Error during data loading.';

                    var msg = String.format('<span style="color: red">{0}</span>', errorMessage);
                    thisAnalyticsWindow.SavedAnalyticsGrid.getEl().mask(msg);
                    setTimeout(function () {
                        thisAnalyticsWindow.SavedAnalyticsGrid.getEl().unmask();
                    }, 2000);
                }
            }
        }),

        columns: [
            {
                id:        subjectColId,
                dataIndex: 'analytics_name',
                renderer:  function (val) {
                    return String.format(
                        '<a href="#" class="applicant_saved_analytics_details" onclick="return false;">{0}</a>',
                        val
                    );
                }
            }, {
                dataIndex: 'analytics_id',
                width:     70,
                align:     'right',
                renderer:  function () {
                    var show = String.format(
                        '<a href="#" class="applicant_saved_analytics_details" onclick="return false;">{0}</a>',
                        _('(detail)')
                    );

                    return '<div class="gray_txt">' + show + '</div>';
                }
            }, {
                dataIndex: 'analytics_id',
                width:     20,
                align:     'right',
                hidden:    !hasAccessToRules(config.panelType, 'analytics', 'delete'),
                renderer:  function () {
                    var show = String.format(
                        '<a href="#" class="applicant_saved_analytics_delete" onclick="return false;">&nbsp;&nbsp;&nbsp;&nbsp;</a>'
                    );

                    return '<div class="gray_txt">' + show + '</div>';
                }
            }
        ]
    });
    this.SavedAnalyticsGrid.on('cellclick', this.onCellClick, this);
    this.SavedAnalyticsGrid.getSelectionModel().on('beforerowselect', function () {
        return false;
    }, this);
    this.SavedAnalyticsGrid.getStore().on('load', this.fixGridHeight.createDelegate(this), this);

    ApplicantsAnalyticsWindow.superclass.constructor.call(this, {
        layout:     'fit',
        closable:   false,
        resizable:  false,
        plain:      true,
        border:     false,
        width:      300,
        autoHeight: true,
        frame:      false,
        items:      [

            {
                xtype:        'panel',
                layout:       'table',
                autoHeight:   true,
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width:   '100%',
                            padding: '5px 5px 10px'
                        }
                    },
                    columns:    3
                },

                items: [
                    {
                        cls:  'gray_txt',
                        html: _('Saved Analytics:')
                    }, {
                        xtype:     'box',
                        cellCls:   'applicant-button',
                        autoEl:    {
                            tag:    'img',
                            src:    topBaseUrl + '/images/refresh12.png',
                            width:  12,
                            height: 12
                        },
                        listeners: {
                            scope:  this,
                            render: function (c) {
                                c.getEl().on('click', thisAnalyticsWindow.refreshSavedAnalyticsList.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }, {
                        xtype:     'box',
                        cellCls:   'applicant-button',
                        autoEl:    {
                            tag:    'img',
                            src:    topBaseUrl + '/images/default/close-button.gif',
                            width:  11,
                            height: 11
                        },
                        listeners: {
                            scope:  this,
                            render: function (c) {
                                c.getEl().on('click', thisAnalyticsWindow.hideThisWindow.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }
                ]
            },

            this.SavedAnalyticsGrid
        ]
    });

    // Automatically hide this window when click outside of it
    // E.g. when we switched to another tab
    Ext.getBody().on('click', function (e, t) {
        var el = thisAnalyticsWindow.getEl();

        if (!(el.dom === t || el.contains(t))) {
            thisAnalyticsWindow.hideThisWindow();
        }
    });
};

Ext.extend(ApplicantsAnalyticsWindow, Ext.Window, {
    hideThisWindow: function () {
        this.hide();
    },

    refreshSavedAnalyticsList: function () {
        this.SavedAnalyticsGrid.store.reload();
        this.syncShadow();
    },

    fixGridHeight: function (store, records) {
        var gridView  = this.SavedAnalyticsGrid.getView();
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

        this.SavedAnalyticsGrid.setHeight(newHeight);
        this.syncShadow();

        var scroller = Ext.select('#' + this.SavedAnalyticsGrid.getId() + ' .x-grid3-scroller').elements[0];
        if (scroller.clientWidth === scroller.offsetWidth) {
            // no scroller
            gridView.scrollOffset = 2;
        } else {
            // there is a scroller
            gridView.scrollOffset = 19;
        }
        // Update columns width - in relation to the changed width
        gridView.fitColumns(false);
    },

    deleteSavedAnalytics: function (analytics_id, analytics_type) {
        var win = this;

        win.getEl().mask(_('Processing...'));
        Ext.Ajax.request({
            url: topBaseUrl + '/applicants/analytics/delete',

            params: {
                analytics_type: Ext.encode(analytics_type),
                analytics_id:   Ext.encode(analytics_id)
            },

            success: function (f) {
                var resultData = Ext.decode(f.responseText);

                var showMsgTime = 2000;
                if (resultData.success) {
                    showMsgTime = 1000;
                    var idx     = win.SavedAnalyticsGrid.store.find('analytics_id', analytics_id);
                    if (idx != -1) { // Element exists - remove
                        win.SavedAnalyticsGrid.store.removeAt(idx);
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
                Ext.simpleConfirmation.error(_('Selected record cannot be deleted. Please try again later.'));
                win.getEl().unmask();
            }
        });
    },

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        var wnd = this;
        var rec = grid.getStore().getAt(rowIndex);

        switch (e.getTarget().getAttribute('class')) {
            case 'applicant_saved_analytics_delete':
                var question = String.format(
                    _('Are you sure you want to delete <i>{0}</i>?'),
                    rec.data.analytics_name
                );

                Ext.Msg.confirm(_('Please confirm'), question, function (btn) {
                    if (btn === 'yes') {
                        wnd.deleteSavedAnalytics(rec.data.analytics_id, wnd.panelType);
                    }
                });

                break;

            case 'applicant_saved_analytics_details':
                this.owner.openAnalyticsTab(rec.data);

                // Hide window instead of close - to avoid data loading
                this.hideThisWindow();
                break;

            default:
        }
    }
});