//simple little message box function
Ext.simpleConfirmation = function () {
    var minDialogWidth = 600;
    var defaultTimeout = 1500;
    var timeoutId;
    return {
        msg: function (title, text, timeout, btnOk) {
            var booAutoHide = timeout !== 0;

            Ext.Msg.show({
                title: title,
                msg: text,
                minWidth: minDialogWidth,
                modal: true,
                buttonAlign: 'right',
                buttons: btnOk ? Ext.Msg.OK : false,
                icon: Ext.Msg.INFO,
                closable: !booAutoHide,
                fn: function () {
                    clearTimeout(timeoutId);
                }
            });

            if (booAutoHide) {
                timeoutId = setTimeout(function () {
                    Ext.Msg.hide();
                }, (timeout ? timeout : defaultTimeout));
            }
        },

        setMinWidth: function (newWidth) {
            minDialogWidth = newWidth;

            return this;
        },

        info: function (text, title, fn) {
            title = title || 'Information';
            this.show(title, text, Ext.Msg.INFO, Ext.Msg.OK, fn);
        },

        success: function (text, title, fn, y) {
            title = title || 'Success';
            this.show(title, text, 'ext-mb-success', Ext.Msg.OK, fn, y);
        },

        error: function (text, title, fn, y) {
            title = title || 'Error';
            this.show(title, text, Ext.Msg.ERROR, Ext.Msg.OK, fn, y);
        },

        warning: function (text, title, fn) {
            title = title || 'Warning';
            this.show(title, text, Ext.Msg.WARNING, Ext.Msg.OK, fn);
        },

        show: function (title, text, icon, buttons, fn, y) {
            var dlg = Ext.Msg.show({
                title: title,
                msg: text,
                fn: fn,
                minWidth: minDialogWidth,
                modal: true,
                buttonAlign: 'right',
                buttons: buttons,
                icon: icon
            });

            if (!empty(y)) {
                var xy = dlg.getDialog().el.getAlignToXY(dlg.getDialog().container, 'c-c');
                dlg.getDialog().setPagePosition(xy[0], y);
            } else {
                dlg.getDialog().center();
            }

            dlg.getDialog().syncSize();
        }
    }
}();