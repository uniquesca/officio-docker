var DashboardContainer = function (config) {
    Ext.apply(this, config);

    var thisContainer = this;
    var arrBlocks = [];

    // Calculate columns count we want to show
    var columnsCount = 0;
    if (allowedPages.has('homepage-announcements')) {
        columnsCount++;
    }

    if (allowedPages.has('applicants')) {
        columnsCount++;
    }

    if (allowedPages.has('tasks')) {
        columnsCount++;
    }

    if (allowedPages.has('homepage-rss')) {
        columnsCount++;
    }

    // if (mail_settings.show_email_tab) {
    if (false) {
        columnsCount++;
    }

    if (allowedPages.has('prospects')) {
        columnsCount++;
    }

    if (allowedPages.has('trustac')) {
        columnsCount++;
    }

    // If only 4 or fewer columns are visible -
    // use 2 rows for each block
    var rowsCount = columnsCount > 4 ? 1 : 2;
    if (allowedPages.has('homepage-announcements')) {
        var oBlockConfig = {
            'block_id': 'announcements',
            'block_title': arrHomepageSettings['announcements']['label'],
            'block_help': arrHomepageSettings['announcements']['help'],
            'block_color': 'dark-orange',
            'block_icon': 'las la-newspaper',
            'block_width': 1,
            'block_height': 2,
            'block_link': '',
            'block_tbar_left': '<a href="#" onclick="Ext.getCmp(\'dashboard-container\').markAsReadAnnouncements(); return false;">' + _('Mark As Read') + '</a>'
        };

        if (!allowedPages.has('tasks')) {
            oBlockConfig['block_show_toggle'] = arrHomepageSettings['announcements']['show_toggle'];
            oBlockConfig['block_toggle_label'] = arrHomepageSettings['announcements']['toggle_label'];
            oBlockConfig['block_toggle_help'] = arrHomepageSettings['announcements']['toggle_help'];
        }

        arrBlocks.push(new DashboardBlock(oBlockConfig));
    }

    if (allowedPages.has('applicants')) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'clients',
            'block_title': _('Clients'),
            'block_color': 'blue',
            'block_icon': 'fas fa-users',
            'block_width': 1,
            'block_height': rowsCount,
            'block_link': 'applicants'
        }));
    }

    if (allowedPages.has('tasks')) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'tasks',
            'block_title': _('Tasks'),
            'block_color': 'light-green',
            'block_icon': 'fas fa-tasks',
            'block_width': 1,
            'block_height': rowsCount,
            'block_link': 'tasks',

            'block_show_toggle': arrHomepageSettings['announcements']['show_toggle'],
            'block_toggle_label': arrHomepageSettings['announcements']['toggle_label'],
            'block_toggle_help': arrHomepageSettings['announcements']['toggle_help']
        }));
    }

    if (allowedPages.has('homepage-rss')) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'rss',
            'block_title': arrHomepageSettings['news']['label'],
            'block_help': arrHomepageSettings['news']['help'],
            'block_color': 'dark-blue',
            'block_icon': 'fas fa-rss',
            'block_width': 1,
            'block_height': 2,
            'block_link': ''
        }));
    }

    // Note: Temporary disabled as we don't show/load any email info
    // if (mail_settings.show_email_tab) {
    if (false) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'email',
            'block_title': _('Email'),
            'block_color': 'pink',
            'block_icon': 'far fa-envelope',
            'block_width': 1,
            'block_height': rowsCount,
            'block_link': 'email'
        }));
    }

    if (allowedPages.has('prospects')) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'prospects',
            'block_title': _('Prospects'),
            'block_color': 'orange',
            'block_icon': 'fas fa-search-dollar',
            'block_width': 1,
            'block_height': rowsCount,
            'block_link': 'prospects'
        }));
    }

    if (allowedPages.has('trustac')) {
        arrBlocks.push(new DashboardBlock({
            'block_id': 'client_accounting',
            'block_title': _(arrApplicantsSettings.ta_label),
            'block_color': 'purple',
            'block_icon': 'fas fa-coins',
            'block_width': 1,
            'block_height': rowsCount,
            'block_link': 'trustac'
        }));
    }

    var arrDashboardItems = [];

    var helpIconBlockHeight = 0;
    if (allowedPages.has('help')) {
        arrDashboardItems.push({
            xtype: 'container',
            items: [
                {
                    xtype: 'button',
                    style: 'float: right',
                    text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                    handler: function () {
                        showHelpContextMenu(this.getEl(), 'dashboard');
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        style: 'clear: both'
                    }
                }
            ]
        });

        helpIconBlockHeight = 30;
    }

    var studioBlockHeight = 0;
    if (allowedPages.has('lms')) {
        var timer = 0;
        var delay = 200;
        var prevent = false;

        studioBlockHeight = 140;
        arrDashboardItems.push({
            id: 'dashboard-studio-container',
            cls: allowedPages.has('help') ? 'dashboard-studio-container-with-help' : '',
            xtype: 'container',
            layout: 'column',
            height: studioBlockHeight - 30,

            items: [
                {
                    xtype: 'box',
                    autoEl: {
                        tag: 'a',
                        href: '#',
                        html: '<img style="height: 55px; width: 206px; margin-top: 10px" src="' + baseUrl + '/images/default/logo_officio_studio.png" alt="Studio Learning Platform">'
                    },
                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                loginLms();
                            }, this, {stopEvent: true});
                        }
                    }
                }, {
                    xtype: 'button',
                    text: '<i class="las la-question-circle help-icon" title="' + _('View the related help topics.') + '"></i>',
                    hidden: !allowedPages.has('help'),
                    handler: function () {
                        showHelpContextMenu(this.getEl(), 'officio-studio');
                    }
                }, {
                    id: 'dashboard-studio-scroll-left',
                    hidden: true,
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        html: '<i class="las la-angle-left"></i>'
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                // A hack to prevent firing a single click and double click
                                timer = setTimeout(function () {
                                    if (!prevent) {
                                        thisContainer.scrollStudioContent(false, true);
                                    }
                                    prevent = false;
                                }, delay);
                            }, this, {stopEvent: true});

                            c.getEl().on('dblclick', function () {
                                clearTimeout(timer);
                                prevent = true;
                                thisContainer.scrollStudioContent(false, true, true);
                            }, this, {stopEvent: true});
                        }
                    }
                }, {
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        'class': 'dashboard-studio-block'
                    },

                    listeners: {
                        afterrender: this.loadStudioBlockInfo.createDelegate(this)
                    }
                }, {
                    id: 'dashboard-studio-scroll-right',
                    hidden: true,
                    xtype: 'box',
                    autoEl: {
                        tag: 'div',
                        html: '<i class="las la-angle-right"></i>'
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                // A hack to prevent firing a single click and double click
                                timer = setTimeout(function () {
                                    if (!prevent) {
                                        thisContainer.scrollStudioContent(true, true);
                                    }
                                    prevent = false;
                                }, delay);
                            }, this, {stopEvent: true});

                            c.getEl().on('dblclick', function () {
                                clearTimeout(timer);
                                prevent = true;
                                thisContainer.scrollStudioContent(true, true, true);
                            }, this, {stopEvent: true});
                        }
                    }
                }
            ]
        });
    }

    // Row height depends on columns count (if more than 4 columns - 2 rows)
    var minRowHeight = 300;
    var bottomPadding = 55 + studioBlockHeight + helpIconBlockHeight;
    var maxRowHeight = columnsCount >= 4 ? Math.max(minRowHeight, (initPanelSize() - bottomPadding - 10) / 2) : Math.max(minRowHeight, initPanelSize() - bottomPadding);
    arrDashboardItems.push({
        id: 'dashboard-inner-container',
        xtype: 'container',
        style: String.format(
            'min-height: {0}px; grid-auto-rows: minmax({1}px, {2}px); grid-template-columns: repeat({3}, 1fr)',
            initPanelSize() - bottomPadding,
            minRowHeight,
            maxRowHeight,
            columnsCount >= 4 ? 4 : columnsCount
        ),
        items: arrBlocks
    });
    this.arrBlocks = arrBlocks;

    DashboardContainer.superclass.constructor.call(this, {
        id: 'dashboard-container',
        items: arrDashboardItems
    });
};

Ext.extend(DashboardContainer, Ext.Container, {
    getBlockById: function (block_id) {
        var oBlock;
        Ext.each(this.arrBlocks, function (oBlockInfo) {
            if (oBlockInfo['oBlockInfo']['block_id'] === block_id) {
                oBlock = oBlockInfo;
            }
        });

        return oBlock;
    },

    reloadBlockInfo: function (block_id) {
        var oBlockInfo = this.getBlockById(block_id);
        if (!empty(oBlockInfo)) {
            oBlockInfo.loadInfo();
        }

        return false;
    },

    markAsReadAnnouncements: function () {
        var thisContainer = this;
        var oBlockInfo = this.getBlockById('announcements');
        if (!empty(oBlockInfo)) {
            oBlockInfo.getEl().mask(_('Processing...'));
            Ext.Ajax.request({
                url: baseUrl + '/news/index/mark-news-as-read',

                success: function (result) {
                    oBlockInfo.getEl().unmask();

                    var resultDecoded = Ext.decode(result.responseText);
                    if (resultDecoded.success) {
                        oBlockInfo.getEl().mask(_('Done!'));
                        setTimeout(function () {
                            oBlockInfo.getEl().unmask();
                        }, 750);


                        thisContainer.reloadBlockInfo('announcements');
                    } else {
                        // Show error message
                        Ext.simpleConfirmation.error(resultDecoded.message);
                    }
                },
                failure: function () {
                    oBlockInfo.getEl().unmask();
                    Ext.simpleConfirmation.error(_('News cannot be marked as read. Please try again later.'));
                }
            });
        }

        return;
    },

    loadStudioBlockInfo: function () {
        var thisContainer = this;
        var oBlock = $('#dashboard-studio-container .dashboard-studio-block');
        oBlock.html(
            String.format(
                '<div style="display: table; width: 100%; height: {0}">' +
                '<div style="display: table-cell; text-align: center; vertical-align: middle; cursor: progress;">' +
                '<img src="{1}/images/loading.gif" alt="{2}" width="16" height="16" style="margin-right: 5px; vertical-align: sub" />' +
                '{2}' +
                '</div>' +
                '</div>',
                $('#dashboard-studio-container').height() + 'px',
                baseUrl,
                _('Loading...')
            )
        );

        Ext.Ajax.request({
            url: baseUrl + '/homepage/index/get-dashboard-block-items',
            params: {
                block_id: 'lms'
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    var subItems = [];
                    var thisBlock = this;
                    Ext.each(resultData.block_items, function (oSubItem) {
                        subItems.push(String.format(
                                '<a class="item" href="{0}" draggable="false" target="_blank">' +
                                '<div class="image_container">' +
                                '<img src="{1}" draggable="false" alt="Officio Studio Image" />' +
                                '<span class="cpd_hours {2}">{3}</span>' +
                                '</div>' +
                                '<span class="title">{4}</span>' +
                                '<span class="message">{5}</span>' +
                                '</a>',

                                oSubItem.link,
                                oSubItem.image,
                                empty(oSubItem.cpd_hours) ? 'hidden' : '',
                                oSubItem.cpd_hours + ' ' + gt.ngettext('CPD Hour', 'CPD Hours', oSubItem.cpd_hours),
                                oSubItem.title,
                                oSubItem.message,
                            )
                        );
                    });

                    Ext.getCmp('dashboard-studio-scroll-left').setVisible(true);
                    Ext.getCmp('dashboard-studio-scroll-right').setVisible(true);
                    oBlock.html('<div style="width: max-content;">' + subItems.join('') + '</div>').hide().animate({width: 'toggle'}, 500);

                    var startX;
                    var scrollLeft;
                    var isDown = false;
                    oBlock
                        .off('mousedown mouseleave mouseup mousemove')
                        .on('mousedown', (e) => {
                            isDown = true;
                            startX = e.pageX - oBlock.offset().left;
                            scrollLeft = oBlock.scrollLeft();
                        })
                        .on('mouseleave', () => {
                            isDown = false;
                        })
                        .on('mouseup', () => {
                            isDown = false;
                        })
                        .on('mousemove', (e) => {
                            if (!isDown) return;
                            e.preventDefault();
                            const x = e.pageX - oBlock.offset().left;
                            const walk = (x - startX) * 3; //scroll-fast
                            oBlock.scrollLeft(scrollLeft - walk);
                        });

                    oBlock.find('a.item').on('click', function () {
                        if (!isDown) {
                            loginLms(this.href);
                        }

                        return false;
                    });
                } else {
                    oBlock.html('<div class="error">' + resultData.msg + '</div>');
                }
                thisContainer.scrollStudioContent(false, false);

                // Scroll the content on mouse wheel scroll
                var timeoutVar;
                oBlock.off('mousewheel DOMMouseScroll').on('mousewheel DOMMouseScroll', function (event) {
                    var delta = Math.max(-1, Math.min(1, (event.originalEvent.wheelDelta || -event.originalEvent.detail)));

                    if (timeoutVar) {
                        // if this event is fired with in 50ms, previous setTimeout will be cancelled
                        clearTimeout(timeoutVar);
                    }

                    timeoutVar = setTimeout(function () {
                        thisContainer.scrollStudioContent(delta > 0, true);
                    }, 50);
                    event.preventDefault();
                });
            },

            failure: function () {
                oBlock.html('<div class="error">' + _('Cannot load information. Please try again later.') + '</div>');
                thisContainer.scrollStudioContent(false, false);
            }
        });
    },

    scrollStudioContent: function (booRight, booDelay, booSuperScroll) {
        var scrollPixels = booSuperScroll ? 100000 : 380;
        var amount = booRight ? '+=' + scrollPixels : '-=' + scrollPixels;
        var delay = booDelay ? 500 : 0;

        var el = $('#dashboard-studio-container .dashboard-studio-block');
        var innerEl = $('#dashboard-studio-container .dashboard-studio-block > div');
        el.animate({scrollLeft: amount}, delay, function () {
            var booDisableLeft = empty($(this).scrollLeft());
            var booDisableRight = $(this).scrollLeft() + $(this).width() >= innerEl.width();

            if (!booDelay) {
                Ext.getCmp('dashboard-studio-scroll-left').setVisible(!booDisableRight);
                Ext.getCmp('dashboard-studio-scroll-right').setVisible(!booDisableRight);
            }

            $('#dashboard-studio-scroll-left').toggleClass('disabled', booDisableLeft);
            $('#dashboard-studio-scroll-right').toggleClass('disabled', booDisableRight);
        });
    }
});
