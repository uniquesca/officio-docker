Ext.ux.Popup = function () {
    return {
        openedUrl: null,
        fn: null,
        buttons: Ext.Msg.OK,
        icon: Ext.Msg.INFO,
        warningMsg: "Opening a new browser tab was blocked by your browser's pop-up blocker.<br>Please add Officio to your safe website list and try again.",

        check: function (popup_window, url) {
            var _scope = this;
            this.openedUrl = url;

            if (popup_window) {
                if (/chrome/.test(navigator.userAgent.toLowerCase())) {
                    setTimeout(function () {
                        _scope._is_popup_blocked(_scope, popup_window);
                    }, 200);
                } else {
                    popup_window.onload = function () {
                        _scope._is_popup_blocked(_scope, popup_window);
                    };
                }
            } else {
                _scope._displayError(_scope);
            }
        },

        _is_popup_blocked: function (_scope, popup_window) {
            if ((popup_window.innerHeight > 0) == false) {
                _scope._displayError(_scope);
            }
        },

        _displayError: function (_scope) {
            var msg = String.format(
                '<div style="margin: 10px; font-size: 16px">{0}</div>',
                this.warningMsg
            );

            var dlg = Ext.Msg.show({
                title: 'Warning',
                msg: msg,
                fn: _scope.fn,
                minWidth: 650,
                modal: true,
                buttonAlign: 'right',
                buttons: _scope.buttons,
                icon: _scope.icon
            });
        },

        show: function (url, booCheckIfBlocked, fileName, loadingMessage) {
            var popup = window.open('about:blank');
            if (booCheckIfBlocked) {
                this.check(popup, url);
            }

            if (popup) {
                var loadingDiv = String.format(
                    '<div style="margin: 80px auto; width: 210px; text-align: center">' +
                    (empty(loadingMessage) ? '' : '<div style="margin-bottom: 5px">' + loadingMessage + '</div>') +
                    '<img src="{0}/images/loadingAnimation.gif" alt="Loading..." />' +
                    '</div>',
                    topBaseUrl
                );

                if (!empty(fileName)) {
                    var iframeContent = String.format(
                        '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/strict.dtd">\n' +
                        '<html>\n' +
                        '<head>\n' +
                        '<title>' + fileName + '</title>\n' +
                        '</head>' +
                        '<body style="padding: 0; margin: 0;">\n' +
                        '<iframe src="{0}" style="border:none; width: 100%; height: 100vh;" frameborder="0" scrolling="auto">{1}</iframe>' + '\n' +
                        '</body>\n' +
                        '</html>',
                        url,
                        loadingDiv
                    );

                    popup.document.write(iframeContent);
                } else {
                    popup.document.write(loadingDiv);

                    if (url !== 'about:blank') {
                        // Need a delay to show that rendered loading image
                        setTimeout(function () {
                            popup.location.href = url;
                        }, 200);
                    }
                }
            }

            return popup;
        }
    }
}();