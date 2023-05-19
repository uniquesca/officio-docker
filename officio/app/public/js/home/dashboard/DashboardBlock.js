var DashboardBlock = function (oBlockInfo, config) {
    this.oBlockInfo = oBlockInfo;
    Ext.apply(this, config);

    var help = '';
    if (oBlockInfo['block_help']) {
        help = String.format(
            "<i class='las la-info-circle' ext:qtip='{0}' ext:qwidth='350' style='cursor: help; margin-left: 10px; vertical-align: text-bottom'></i>",
            _(oBlockInfo['block_help'])
        );
    }

    var thisBlockId = Ext.id();
    var thisToggleId = Ext.id();
    DashboardBlock.superclass.constructor.call(this, {
        id: thisBlockId,

        autoEl: {
            tag: 'div',
            'class': 'block ' + 'dashboard-' + oBlockInfo['block_id'],
            style: String.format('grid-column: span {0}; grid-row: span {1};', oBlockInfo['block_width'], oBlockInfo['block_height']),
            html: String.format(
                '<div class="block_top_section">' +
                '<a class="title {1}" href="#" onclick="Ext.getCmp(\'{0}\').openDashboardItemBlock(\'{2}\'); return false;">{3}</a>' +
                '<div class="spacer {4}">' +
                '<a href="#" class="{1}" onclick="Ext.getCmp(\'{0}\').openDashboardItemBlock(\'{2}\'); return false;"><i class="{5}"></i></a>' +
                '</div>' +
                '<div class="block_tbar">' +
                '<div class="block_tbar_left block_top_section_toggle">' +
                '<div class="toggle-label">{9}</div>' +
                '<div style="float: left"><div class="button b2"><input id="{8}" type="checkbox" class="checkbox"><div class="knobs"></div><div class="layer"></div></div></div>' +
                '</div>' +
                '<div class="block_tbar_right">{7}</div>' +
                '</div>' +
                '</div>' +
                '<div class="items {6}"></div>',
                thisBlockId,
                empty(oBlockInfo['block_link']) ? 'not-real-link' : '',
                oBlockInfo['block_link'],
                oBlockInfo['block_title'] + help,
                oBlockInfo['block_color'],
                oBlockInfo['block_icon'],
                oBlockInfo['block_width'] > 1 ? 'two_columns' : '',
                empty(oBlockInfo['block_tbar_left']) ? '' : oBlockInfo['block_tbar_left'],
                thisToggleId,
                empty(oBlockInfo['block_toggle_label']) ? '' : oBlockInfo['block_toggle_label']
            )
        }
    });

    this.on('render', this.loadInfo.createDelegate(this, [0]));
};

Ext.extend(DashboardBlock, Ext.BoxComponent, {
    loadInfo: function (start) {
        var thisBlock = this;
        var oBlockInfo = this.oBlockInfo;
        thisBlock.start = start;

        var oBlock = $('#' + thisBlock.id);
        var itemsContainer = oBlock.find('.items');
        var tbarContainer = oBlock.find('.block_tbar');
        var tbarRightContainer = oBlock.find('.block_tbar_right');
        var tbarLeftContainer = oBlock.find('.block_tbar_left');
        var toggleInput = oBlock.find('.block_top_section_toggle input');

        if (empty(thisBlock.start)) {
            itemsContainer.html('');
        }

        itemsContainer.find('.loading_link').remove();
        itemsContainer.find('.load_more_link').remove();
        itemsContainer.append('<div class="loading_link"><img src="' + baseUrl + '/images/loading.gif" alt="Loading" width="16" height="16" /> ' + _('Loading...') + '</div>');

        Ext.Ajax.request({
            url: baseUrl + '/homepage/index/get-dashboard-block-items',
            params: {
                block_id: oBlockInfo['block_id'],
                start: thisBlock.start
            },

            success: function (result) {
                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    if (empty(thisBlock.start)) {
                        itemsContainer.html('');
                    } else {
                        itemsContainer.find('.loading_link').remove();
                    }

                    if (resultData.is_html) {
                        itemsContainer.append(resultData.block_items);
                    } else {
                        itemsContainer.append(thisBlock.generateSubItems(resultData.block_items));
                    }

                    if (resultData.show_more) {
                        var linkHtml = String.format(
                            '<div class="load_more_link"><a href="#" onclick="Ext.getCmp(\'{0}\').loadInfo({1}); return false;" title="{2}">{3}</a></div>',
                            thisBlock.id,
                            thisBlock.start + 1,
                            _('Click to load more ') + oBlockInfo['block_title'].toLowerCase(),
                            _('Load more...')
                        );
                        itemsContainer.append(linkHtml);
                    }

                    var booShowToggle = !empty(thisBlock.oBlockInfo['block_show_toggle']);
                    oBlock.toggleClass('block-with-tbar', resultData.show_right_tbar || booShowToggle);

                    tbarContainer.toggle(resultData.show_right_tbar || booShowToggle);
                    tbarRightContainer.toggle(resultData.show_right_tbar);
                    tbarLeftContainer.toggle(booShowToggle);
                    oBlock.toggleClass('block-with-toggle', booShowToggle);
                    if (booShowToggle) {
                        // Show the toggle + listen for clicks on it
                        thisBlock.toggleDailyNotifications();

                        toggleInput.prop('checked', resultData.checked_toggle);
                    }

                    // Intercept clicks on links
                    thisBlock.interceptStudioLinks();
                } else {
                    itemsContainer.html('<div class="error">' + resultData.msg + '</div>');
                }
            },

            failure: function () {
                itemsContainer.html('<div class="error">' + _('Cannot load information. Please try again later.') + '</div>');
            }
        });
    },

    generateSubItems: function (arrSubItems) {
        var subItems = [];
        var thisBlock = this;
        Ext.each(arrSubItems, function (oSubItem) {
            var whatCls = empty(oSubItem.id) ? 'no-click' : '';
            if (!empty(oSubItem.what_cls)) {
                whatCls += ' ' + oSubItem.what_cls;
            }
            subItems.push(
                String.format(
                    '<div class="item">' +
                    '<div class="when">{0}</div>' +
                    '<div class="row">' +
                    '<span class="number">{1}</span>' +
                    '<span class="direction {2}"></span>' +
                    '<a href="#" class="what {6}" onclick="Ext.getCmp(\'{4}\').openDashboardItemLink(\'{5}\'); return false;">{3}</a>{7}' +
                    '</div>' +
                    '</div>',

                    oSubItem.when,
                    oSubItem.number,
                    oSubItem.direction,
                    oSubItem.what,
                    thisBlock.id,
                    oSubItem.id,
                    whatCls,
                    empty(oSubItem.what_details) ? '' : oSubItem.what_details
                )
            );
        });

        return subItems.join('');
    },

    openDashboardItemBlock: function (hash) {
        if (!empty(hash)) {
            setUrlHash('#' + hash);
            setActivePage();
        }
    },

    openDashboardItemLink: function (itemId) {
        switch (this.oBlockInfo['block_id']) {
            case 'clients':
                switch (itemId) {
                    case 'clients_completed_forms':
                    case 'clients_uploaded_documents':
                    case 'clients_have_payments_due':
                    case 'today_clients':
                        this.openDashboardItemBlock('applicants/advanced_search/' + itemId);
                        break;

                    default:
                }
                break;

            case 'prospects':
                if (!empty(itemId)) {
                    this.openDashboardItemBlock('prospects/' + itemId);
                }
                break;

            case 'client_accounting':
                if (!empty(itemId)) {
                    this.openDashboardItemBlock('trustac/' + itemId);

                    var grid = Ext.getCmp('ta-tab-panel-grid');
                    if (!empty(grid)) {
                        grid.openAccountingTab('ta_tab_' + itemId);
                    }
                }
                break;

            case 'tasks':
                this.openDashboardItemBlock('tasks/' + itemId);
                break;

            default:
                break;
        }
    },

    toggleDailyNotifications: function () {
        var thisBlock = this;
        var toggleContainer = $('#' + thisBlock.id + ' .block_top_section_toggle');

        // Listen for the click on the "Daily Email Notification" toggle,
        // rollback and show a message on error
        var booDailyEmailNotificationUpdateInProgress = false;
        toggleContainer.on('click', 'input', function () {
            if (!booDailyEmailNotificationUpdateInProgress) {
                booDailyEmailNotificationUpdateInProgress = true;

                var input = $(this);
                var booChecked = input.is(':checked');

                Ext.Ajax.request({
                    url: baseUrl + '/profile/index/toggle-daily-notifications',

                    params: {
                        enable: booChecked ? 1 : 0
                    },

                    success: function (result) {
                        booDailyEmailNotificationUpdateInProgress = false;

                        var resultDecoded = Ext.decode(result.responseText);
                        if (!resultDecoded.success) {
                            input.prop('checked', !booChecked);
                            Ext.simpleConfirmation.error(resultDecoded.message);
                        }
                    },

                    failure: function () {
                        booDailyEmailNotificationUpdateInProgress = false;

                        input.prop('checked', !booChecked);
                        Ext.simpleConfirmation.error(_('Cannot save changes. Please try again later'));
                    }
                });
            }
        });

        if (!empty(thisBlock.oBlockInfo['block_toggle_help'])) {
            var oBlock = $('#' + thisBlock.id);
            oBlock.toggleClass('block-with-toggle-help', true);

            new Ext.ToolTip({
                target: toggleContainer.get(0),
                width: 380,
                dismissDelay: 0,
                trackMouse: true,
                mouseOffset: [-50, 0],
                html: thisBlock.oBlockInfo['block_toggle_help']
            });
        }
    },

    interceptStudioLinks: function () {
        var thisBlock = this;
        var arrLMSLinks = $('#' + thisBlock.id + ' .items a[href*="' + lmsSettings.url + '"]');
        if (arrLMSLinks.length) {
            if (allowedPages.has('lms')) {
                arrLMSLinks.click(function (e) {
                    loginLms($(this).attr('href'));

                    e.preventDefault();
                    return false;
                });
            } else {
                arrLMSLinks.each(function () {
                    // remove links
                    $(this).attr('href', '#');

                    // show a warning on the link click
                    $(this).click(function (e) {
                        Ext.simpleConfirmation.warning(_('There is no access to Officio Studio Module.'));

                        e.preventDefault();
                        return false;
                    });
                });
            }
        }
    }
});