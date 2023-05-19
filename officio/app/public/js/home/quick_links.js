var updateQuickLinks = function () {
    var thisTabPanel = Ext.getCmp('main-tab-panel');
    var activeTab = thisTabPanel.getActiveTab();
    var tabId = activeTab.id;

    // For a Queue and Advanced Search tabs try to select the correct quick menu option
    var currentHash = parseUrlHash(location.hash);
    if (currentHash[0] === 'applicants' && currentHash[1] === 'advanced_search') {
        var booTabExists = false;
        $('.officio-quick-link').each(function () {
            if ($(this).attr('officio-quick-link-tab-id') === 'applicants-advanced-search-tab') {
                booTabExists = true;
                return;
            }
        });

        if (booTabExists) {
            tabId = 'applicants-advanced-search-tab';
        }
    } else if (currentHash[0] === 'applicants' && currentHash[1] === 'queue') {
        var booTabExists = false;
        $('.officio-quick-link').each(function () {
            if ($(this).attr('officio-quick-link-tab-id') === 'applicants-offices-tab') {
                booTabExists = true;
                return;
            }
        });

        if (booTabExists) {
            tabId = 'applicants-offices-tab';
        }
    }

    $('.officio-quick-link').each(function () {
        $(this).toggleClass('active', tabId === $(this).attr('officio-quick-link-tab-id'));
    });
};

var saveQuickMenuSettings = function (type, arrSettings) {
    Ext.Ajax.request({
        url: topBaseUrl + '/profile/index/save-quick-menu-settings',
        params: {
            type: type,
            settings: Ext.encode(arrSettings)
        },

        success: function (f) {
            var resultData = Ext.decode(f.responseText);

            if (!resultData.success) {
                Ext.simpleConfirmation.error(resultData.message);
            } else {
                arrHomepageSettings.settings[type] = arrSettings;
            }
        },

        failure: function () {
            Ext.simpleConfirmation.error('Quick links cannot be saved. Please try again later.');
        }
    });
};

var renderQuickLinks = function (arrItems, booSave) {
    var arrQuickLinks = [];
    var thisTabPanel = Ext.getCmp('main-tab-panel');

    var i = 0;
    var activeTab = thisTabPanel.getActiveTab();

    arrItems.forEach(function (officioTabId) {
        thisTabPanel.items.each(function (tabItem) {
            if (tabItem.id === officioTabId) {
                var active = activeTab.id === tabItem.id ? ' active ' : '';
                var spacer = i === arrItems.length - 1 ? '' : '<span style="padding-left: 10px">|</span>';
                i++;

                arrQuickLinks.push({
                    xtype: 'box',
                    autoEl: {
                        tag: 'a',
                        href: '#',
                        'class': 'officio-quick-link officio-quick-link-text-only' + active,
                        html: '<span>' + (tabItem.titleQuickLink ? tabItem.titleQuickLink : tabItem.title) + '</span>' + spacer,

                        'officio-quick-link-tab-id': tabItem.id
                    },

                    listeners: {
                        scope: this,
                        render: function (c) {
                            c.getEl().on('click', function () {
                                thisTabPanel.setActiveTab(tabItem.id);

                                updateQuickLinks();
                            }, this, {stopEvent: true});
                        }
                    }
                });
            }
        });
    });

    $('#officio-quick-links').html('');
    new Ext.Container({
        applyTo: 'officio-quick-links',
        layout: 'table',

        layoutConfig: {
            columns: arrQuickLinks.length
        },

        items: arrQuickLinks
    });

    if (booSave) {
        if (typeof (userProfileSettings) === "undefined" || !userProfileSettings.can_edit_profile) {
            Ext.simpleConfirmation.error('Your role does not permit editing user profile information. The quick menu changes will only be visible while you are logged in. <br><br>Please ask your admin to give you access to "Edit Profile" in your role.');
        } else {
            saveQuickMenuSettings('quick_links', arrItems);
        }
    }
};

var saveMouseOverSettings = function (wnd) {
    if (typeof (userProfileSettings) === "undefined" || !userProfileSettings.can_edit_profile) {
        Ext.simpleConfirmation.error('Your role does not permit editing user profile information. Mouse-over settings will only be used while you are logged in. <br><br>Please ask your admin to give you access to "Edit Profile" in your role.');
    } else {
        var arrSettings = [];
        wnd.items.get(0).items.each(function (checkbox) {
            if (checkbox.checked && !empty(checkbox.mouseOverItem)) {
                arrSettings.push(checkbox.mouseOverItem);
            }
        });

        saveQuickMenuSettings('mouse_over_settings', arrSettings);
    }
};

var showQuickLinksSettingsDialog = function () {
    var wnd;
    var thisTabPanel = Ext.getCmp('main-tab-panel');
    var tabsCount = thisTabPanel.items.getCount();

    var arrQuickLinks = [];
    if (tabsCount) {
        thisTabPanel.items.each(function (item) {
            if (item.booHideInQuickLinks) {
                return;
            }

            arrQuickLinks.push({
                xtype: 'checkbox',
                officioTabId: item.id,
                hideLabel: true,
                boxLabel: item.titleQuickLink ? item.titleQuickLink : item.title,
                checked: arrHomepageSettings.settings.quick_links.includes(item.id),

                listeners: {
                    'check': function () {
                        var arrItems = [];
                        wnd.items.get(0).items.each(function (checkbox) {
                            if (checkbox.checked && !empty(checkbox.officioTabId)) {
                                arrItems.push(checkbox.officioTabId);
                            }
                        });

                        renderQuickLinks(arrItems, true);
                    }
                }
            });
        });
    }

    // Mouse over settings
    arrQuickLinks.push({
        xtype: 'box',
        autoEl: {
            tag: 'div',
            style: 'border-top: 1px solid #E0E0E0; margin-top: 10px; padding-top: 10px; width: 310px',
            html: _('On mouse-over open:')
        }
    });

    arrQuickLinks.push({
        xtype: 'checkbox',
        hideLabel: true,
        boxLabel: _('Application Menu'),
        checked: arrHomepageSettings.settings.mouse_over_settings.includes('application-menu'),
        mouseOverItem: 'application-menu',
        listeners: {
            'check': function () {
                saveMouseOverSettings(wnd);
            }
        }
    });

    arrQuickLinks.push({
        xtype: 'checkbox',
        hideLabel: true,
        boxLabel: _('Recently Viewed Menu'),
        checked: arrHomepageSettings.settings.mouse_over_settings.includes('recently-viewed-menu'),
        mouseOverItem: 'recently-viewed-menu',
        listeners: {
            'check': function () {
                saveMouseOverSettings(wnd);
            }
        }
    });

    arrQuickLinks.push({
        xtype: 'checkbox',
        hideLabel: true,
        boxLabel: _('Search Menu'),
        checked: arrHomepageSettings.settings.mouse_over_settings.includes('search-menu'),
        mouseOverItem: 'search-menu',
        listeners: {
            'check': function () {
                saveMouseOverSettings(wnd);
            }
        }
    });

    wnd = new Ext.Window({
        title: _('Quick Access Menu'),
        id: 'officio-quick-links-dialog',
        resizable: false,
        autoHeight: true,
        width: 350,
        y: 40,
        stateful: false,

        anim: {
            endOpacity: 1,
            easing: 'easeIn',
            duration: .3
        },

        items: [{
            xtype: 'container',
            layout: 'table',
            cls: 'links-container',

            layoutConfig: {
                columns: 1
            },

            items: arrQuickLinks
        }],

        buttons: [{
            text: _('Close'),
            cls: 'orange-btn',
            handler: function () {
                wnd.close();
            }
        }]
    });

    wnd.show();
}