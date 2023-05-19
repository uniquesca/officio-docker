var calendlyWindow = null;
var calendlyInsertLinkBtnId = null;

Ext.ns('Ext.ux');
Ext.ux.CalendlyButton = Ext.extend(Ext.Button, {
    editor: null,
    calendlyLinks: [],
    calendlyMenu: null,

    constructor: function (config) {
        var self = this;

        self.editor = config.editor;
        self.calendlyMenu = new Ext.menu.Menu({
            items: [],
            hidden: true
        });

        self.config = config || {};
        config.id = Ext.id();
        config.listeners = config.listeners || {};
        config.menu = self.calendlyMenu;
        config.handler = self.onButtonClick.createDelegate(self);

        calendlyInsertLinkBtnId = config.id;

        Ext.ux.CalendlyButton.superclass.constructor.call(self, config);
    },

    onButtonClick: function () {
        var self = this;

        self.calendlyLinks = [];
        self.calendlyMenu.removeAll();
        var maskItem = self.calendlyMenu.add({
            text: 'Loading...',
            disabled: true
        });

        // Try to get links
        Ext.Ajax.request({
            url: topBaseUrl + '/mailer/index/calendly-links',
            success: function (res) {
                self.calendlyMenu.removeAll();

                var result = Ext.decode(res.responseText);
                if (result.success) {
                    // Show links
                    if (result.calendly_links.length > 0) {
                        for (var i = 0; i < result.calendly_links.length; i++) {
                            var obj = result.calendly_links[i];
                            var item = self.calendlyMenu.add({
                                text: obj.name
                            });
                            item.on('click', self.onCalendlyItemClick.createDelegate(self));

                            self.calendlyLinks[item.id] = obj;
                        }
                    } else {
                        self.calendlyMenu.add({
                            text: 'No Calendly event types available',
                            disabled: true
                        });
                    }
                } else {
                    if (result.init_login) {
                        // Show Calendly login button
                        var item = self.calendlyMenu.add({
                            text: 'Authenticate with Calendly'
                        });
                        item.on('click', self.onCalendlyLoginClick.createDelegate(self));
                    } else {
                        Ext.simpleConfirmation.error('An error occurred when connecting to Calendly');
                    }
                }
            },

            failure: function () {
                self.calendlyMenu.removeAll();
                Ext.simpleConfirmation.error('An error occurred with Calendly');
            }
        });
    },

    onCalendlyItemClick: function (item) {
        var self = this;
        self.editor.insertAtCursor('<p><a href="' + self.calendlyLinks[item.id].scheduling_url + '" target="_blank">' + self.calendlyLinks[item.id].scheduling_url + '</a></p>');
    },

    calendlyOpenPopupWindow: function (url, windowName, win, w, h) {
        // Center window in parent window
        var y = win.top.outerHeight / 2 + win.top.screenY - (h / 2);
        var x = win.top.outerWidth / 2 + win.top.screenX - (w / 2);

        return win.open(url, windowName, 'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=no, resizable=no, copyhistory=no, width=' + w + ', height=' + h + ', top=' + y + ', left=' + x);
    },

    onCalendlyLoginClick: function (item) {
        var self = this;

        if (calendlyWindow) {
            calendlyWindow.close()
        }

        calendlyWindow = self.calendlyOpenPopupWindow(topBaseUrl + '/mailer/index/calendly-authorize', 'calendlyWindow', window, 750, 650);
        return;
    }
});

Ext.reg('calendlybutton', Ext.ux.CalendlyButton);


// This method will be called from the html
function closeCalendlyPopup(success) {
    if (calendlyWindow) {
        calendlyWindow.close()
    }

    if (success) {
        $('#' + calendlyInsertLinkBtnId).find('button').trigger('click');
    } else {
        Ext.simpleConfirmation.error('An error occurred with Calendly login.');
    }
}