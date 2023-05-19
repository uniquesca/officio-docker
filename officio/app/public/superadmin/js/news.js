Ext.onReady(function () {
    $('#announcements-content').css('min-height', getSuperadminPanelHeight() + 'px');
});

function news(params) {
    if (params.action != 'delete') //add or edit action
    {
        var win = new Ext.Window({
            y: 10,
            title: params.action === 'add' ? _('Add News') : _('Edit News'),
            layout: 'form',
            bodyStyle: 'background-color:#fff; padding: 10px;',
            labelAlign: 'top',
            modal: true,
            autoWidth: true,
            autoHeight: true,
            resizable: false,

            items: [{
                id: 'news-add-title',
                xtype: 'textfield',
                fieldLabel: _('Title'),
                width: 300
            }, {
                id: 'news-add-content',
                xtype: 'froalaeditor',
                booAllowImagesUploading: true,
                width: 800,
                height: 200,
                fieldLabel: _('Content')
            }, {
                xtype: 'container',
                layout: 'table',
                hidden: !booSpecialAnnouncementEnabled,
                layoutConfig: {
                    tableAttrs: {
                        style: {
                            width: '100%',
                            height: '45px'
                        }
                    },
                    columns: 2
                },

                items: [{
                    id: 'news-is-special-announcement-checkbox',
                    xtype: 'checkbox',
                    boxLabel: _('Banner Area Message'),
                    hideLabel: true,
                    listeners: {
                        'check': function (checkbox, booChecked) {
                            Ext.getCmp('news-creation-datetime-container').setVisible(booChecked);

                            var bodyArea = Ext.getCmp('news-add-content').injectStyle(
                                String.format(
                                    'body{background-color: {0} !important; color: {1} !important}',
                                    booChecked ? '#4C83C5' : '#FFF',
                                    booChecked ? '#FFF' : '#000'
                                )
                            );
                        }
                    }
                }, {
                    xtype: 'container',
                    id: 'news-creation-datetime-container',
                    hidden: params.action === 'add',
                    items: [{
                        id: 'news-creation-datetime',
                        xtype: 'displayfield',
                        value: '' // Will be set after data will be loaded
                    }, {
                        xtype: 'button',
                        text: _('Set timestamp to NOW'),
                        width: 150,
                        disabled: params.action === 'add',
                        handler: function () {
                            win.getEl().mask(_('Updating...'));

                            Ext.Ajax.request({
                                url: baseUrl + '/news/set-time',
                                params: {
                                    news_id: params.news_id
                                },

                                success: function (f) {
                                    var resultData = Ext.decode(f.responseText);
                                    if (!resultData.success) {
                                        win.getEl().unmask();
                                        Ext.simpleConfirmation.error(resultData.message);
                                    } else {
                                        win.getEl().mask(_('Done!'));

                                        Ext.getCmp('news-creation-datetime').setValue(resultData.create_date);

                                        reloadNews();

                                        setTimeout(function () {
                                            win.getEl().unmask();
                                        }, 750);
                                    }
                                },

                                failure: function () {
                                    Ext.simpleConfirmation.error(_('Saving Error'));
                                    win.getEl().unmask();
                                }
                            });
                        }
                    }, {
                        xtype: 'button',
                        text: _('Preview Banner'),
                        style: 'margin-top: 10px',
                        width: 150,
                        handler: function () {
                            if (window === window.parent) {
                                notif({
                                    message: Ext.getCmp('news-add-content').getValue(),
                                    delayBeforeShow: 0
                                });
                            } else {
                                window.parent.showTopBanner(Ext.getCmp('news-add-content').getValue());
                            }
                        }
                    }]
                }]
            }],

            buttons: [{
                text: _('Cancel'),
                handler: function () {
                    win.close();
                }
            }, {
                text: params.action === 'add' ? _('Add') : _('Save'),
                cls: 'orange-btn',
                handler: function () {
                    var title = Ext.getCmp('news-add-title').getValue();
                    var content = Ext.getCmp('news-add-content').getValue();

                    if (title === '') {
                        Ext.getCmp('news-add-title').markInvalid();
                        return false;
                    }

                    if (content === '') {
                        Ext.getCmp('news-add-content').markInvalid();
                        return false;
                    }

                    var chkSpecialAnnouncement = Ext.getCmp('news-is-special-announcement-checkbox');

                    //save msg
                    win.getEl().mask(_('Saving...'));

                    //save
                    Ext.Ajax.request({
                        url: baseUrl + '/news/' + params.action,
                        params: {
                            news_id: params.news_id,
                            title: Ext.encode(title),
                            content: Ext.encode(content),
                            is_special_announcement: chkSpecialAnnouncement.getValue() ? 'Y' : 'N',
                            show_on_the_homepage: chkSpecialAnnouncement.getValue() ? 'N' : 'Y'
                        },

                        success: function (f) {
                            var resultData = Ext.decode(f.responseText);
                            if (!resultData.success) {
                                win.getEl().unmask();
                                Ext.simpleConfirmation.error(resultData.error);
                            } else {
                                win.getEl().mask(_('Done!'));

                                reloadNews();

                                setTimeout(function () {
                                    win.close();
                                }, 750);
                            }
                        },

                        failure: function () {
                            Ext.simpleConfirmation.error(_('Saving Error'));
                            win.getEl().unmask();
                        }
                    });
                }
            }]
        });

        win.show();

        //if edit action set default values
        if (params.action === 'edit') {
            win.getEl().mask(_('Loading...'));

            //get news detail info
            Ext.Ajax.request({
                url: baseUrl + '/news/get-news',
                params: {
                    news_id: params.news_id
                },
                success: function (result) {
                    var news = Ext.decode(result.responseText).news;

                    Ext.getCmp('news-add-title').setValue(news.title);
                    Ext.getCmp('news-add-content').setValue(news.content);
                    Ext.getCmp('news-creation-datetime').setValue(news.create_date);

                    var chkIsSpecialAnnouncement = Ext.getCmp('news-is-special-announcement-checkbox');
                    if (news.is_special_announcement === 'Y') {
                        chkIsSpecialAnnouncement.setValue(news.is_special_announcement === 'Y');
                    } else {
                        // Hide the section if unchecked
                        chkIsSpecialAnnouncement.fireEvent('check', chkIsSpecialAnnouncement, false);
                    }

                    fixNewsPageHeight();
                    win.getEl().unmask();
                },
                failure: function () {
                    Ext.simpleConfirmation.error(_('Can\'t load news information'));
                    win.getEl().unmask();
                }
            });
        }
    } else //delete action
    {
        Ext.Msg.confirm(_('Please confirm'), _('Are you sure you want to delete this news?'), function (btn) {
            if (btn === 'yes') {
                Ext.Ajax.request({
                    url: baseUrl + '/news/delete',
                    params: {
                        news_id: params.news_id
                    },
                    success: function () {
                        reloadNews();
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error(_('This news can\'t be deleted. Please try again later.'));
                    }
                });
            }
        });
    }
}

function reloadNews() {
    $('#admin-news').html(_('Loading...'));
    $('#admin-news').load(baseUrl + '/news/get-news-html');
    fixNewsPageHeight();
}

var fixNewsPageHeight = function () {
    setTimeout(function () {
        updateSuperadminIFrameHeight('#announcements-content');
    }, 500);
};
