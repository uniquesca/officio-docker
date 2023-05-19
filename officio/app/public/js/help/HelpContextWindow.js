var HelpContextWindow = function (config) {
    Ext.apply(this, config);

    var thisHelpArticlesWindow = this;
    var articleColId           = Ext.id();
    this.minGridPanelHeight    = 50;

    this.ModuleDescription = new Ext.form.DisplayField({
        cls: 'article-module-description',
        hideLabel: true,
        value: ''
    });

    this.RelatedHelpTopics = new Ext.BoxComponent({
        'autoEl': {
            'tag':   'div',
            'class': 'article-header',
            style: 'padding-top: 10px',
            html: _('Related Help Topics:')
        }
    });

    this.HelpArticles = new Ext.grid.GridPanel({
        autoWidth:        true,
        height:           this.minGridPanelHeight,
        autoScroll:       true,
        hideHeaders:      true,
        loadMask:         {msg: _('Loading...')},
        autoExpandColumn: articleColId,
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
                url: topBaseUrl + '/help/index/get-help-context'
            }),

            reader: new Ext.data.JsonReader({
                root:          'items',
                totalProperty: 'count',
                idProperty:    'faq_id',

                fields: [
                    'faq_id',
                    'question',
                    'content_type',
                    'section_type',
                    'inlinemanual_topic_id'
                ]
            }),

            listeners: {
                'beforeload': function (store, options) {
                    options.params = options.params || {};

                    var params = {
                        context_id: Ext.encode(thisHelpArticlesWindow.contextId)
                    };

                    Ext.apply(options.params, params);
                },

                'load': function (store) {
                    if (!empty(store.reader.jsonData.module_description)) {
                        thisHelpArticlesWindow.ModuleDescription.setValue(store.reader.jsonData.module_description);
                    } else {
                        thisHelpArticlesWindow.RelatedHelpTopics.setVisible(false);
                        thisHelpArticlesWindow.ModuleDescription.setValue('<div class="article-header">' + _('Related Help Topics:') + '</div>');
                    }
                },

                'loadexception': function (e, store, response) {
                    var resultData   = Ext.decode(response.responseText);
                    var errorMessage = resultData && resultData.msg ? resultData.msg : 'Error during data loading.';

                    var msg = String.format('<span style="color: red">{0}</span>', errorMessage);
                    thisHelpArticlesWindow.HelpArticles.getEl().mask(msg);
                    setTimeout(function () {
                        thisHelpArticlesWindow.HelpArticles.getEl().unmask();
                    }, 2000);
                }
            }
        }),

        columns: [
            {
                id:        articleColId,
                dataIndex: 'question',
                renderer:  function (val, p, record) {
                    return String.format(
                        '<a href="#" class="article_details {1}" onclick="return false;">{0}</a>',
                        val,
                        'article-cell-icon-' + record.data['content_type']
                    );
                }
            }
        ]
    });
    this.HelpArticles.on('cellclick', this.onCellClick, this);
    this.HelpArticles.getSelectionModel().on('beforerowselect', function () {
        return false;
    }, this);
    this.HelpArticles.getStore().on('load', this.fixGridHeight.createDelegate(this), this);

    HelpContextWindow.superclass.constructor.call(this, {
        layout:     'fit',
        closable:   false,
        resizable:  false,
        plain:      true,
        border:     false,
        width:      600,
        autoHeight: true,
        frame:      false,

        items: [
            {
                xtype:        'panel',
                layout:       'table',
                autoHeight:   true,
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width:   '100%',
                            padding: '5px 5px 0'
                        }
                    },
                    columns:    3
                },

                items: [
                    this.ModuleDescription,

                    {
                        xtype:     'box',
                        cellCls:   'applicant-button applicant-button-top',
                        autoEl:    {
                            tag:    'img',
                            src:    topBaseUrl + '/images/default/close-button-dark-blue.png',
                            width:  20,
                            height: 20,
                            style: {
                                margin: '0 5px',
                                cursor: 'pointer'
                            }
                        },
                        listeners: {
                            scope:  this,
                            render: function (c) {
                                c.getEl().on('click', thisHelpArticlesWindow.hideThisWindow.createDelegate(this), this, {stopEvent: true});
                            }
                        }
                    }
                ]
            },

            this.RelatedHelpTopics,

            this.HelpArticles
        ]
    });

    this.on('render', this.initClickListener.createDelegate(this));
    this.on('beforedestroy', this.removeClickListener.createDelegate(this));
};

Ext.extend(HelpContextWindow, Ext.Window, {
    initClickListener: function () {
        var thisDialog = this;
        setTimeout(function () {
            Ext.getBody().on('click', thisDialog.listenForClick, thisDialog);
        }, 200);
    },

    removeClickListener: function () {
        Ext.getBody().removeListener('click', this.listenForClick, this);
    },

    listenForClick: function (e, t) {
        // Automatically hide this window when click outside of it
        // E.g. when we switched to another tab
        var el = this.getEl();
        if (!(el.dom === t || el.contains(t)) && this.isVisible() && t !== this.parentLink) {
            this.hideThisWindow();
        }
    },

    hideThisWindow: function () {
        this.close();
    },

    applyNewContextIdAndRefresh: function (newContextId, parentLink) {
        this.show();

        if (this.contextId !== newContextId) {
            this.contextId  = newContextId;
            this.parentLink = parentLink;
            this.refreshHelpArticles();
        }
    },

    refreshHelpArticles: function () {
        this.HelpArticles.store.reload();
        this.syncShadow();
    },

    fixGridHeight: function (store, records) {
        var gridView  = this.HelpArticles.getView();
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

        this.HelpArticles.setHeight(newHeight);
        this.syncShadow();

        var scroller = Ext.select('#' + this.HelpArticles.getId() + ' .x-grid3-scroller').elements[0];
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

    onCellClick: function (grid, rowIndex, columnIndex, e) {
        if ($(e.getTarget()).hasClass('article_details')) {
            var rec = grid.getStore().getAt(rowIndex);

            if (rec.data['content_type'] == 'walkthrough') {
                if (typeof inline_manual_player === 'undefined') {
                    Ext.simpleConfirmation.error(_('The walkthrough feature is not enabled.  Please inform the website support if this error persists.'));
                } else {
                    inline_manual_player.activateTopic(rec.data['inlinemanual_topic_id']);
                }
            } else {
                openHelpArticle(rec.data['section_type'], rec.data['faq_id']);
            }

            // Hide window instead of close - to avoid data loading
            this.hideThisWindow();
        }
    }
});

var showHelpContextMenu = function (parentLink, contextId) {
    var windowId = 'help-context-window';

    var wnd = Ext.getCmp(windowId);
    if (wnd && wnd.isVisible()) {
        if (wnd.contextId === contextId) {
            wnd.hideThisWindow();
        } else {
            wnd.applyNewContextIdAndRefresh(contextId, parentLink);
            wnd.alignTo(Ext.get(parentLink), 'tl-bl?', [0, 0]);
        }
    } else if (!wnd || wnd.contextId !== contextId) {
        wnd = new HelpContextWindow({
            id:        windowId,
            contextId: contextId,
            parentLink: parentLink
        });

        wnd.show();
        wnd.alignTo(Ext.get(parentLink), 'tl-bl?', [0, 0]);
    }
};