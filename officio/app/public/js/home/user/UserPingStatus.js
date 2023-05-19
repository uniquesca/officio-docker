/**
 * $.pinger
 *
 * If your page runs into an iframe hosted by another domain, you may want to keep the session open.
 * This plugin automates the "ping URL" process and provides some options.
 *
 * The pinger will ask the given URL every 'interval' minutes if it detects
 * some activity by listening to the events listed in 'listen' parameter.
 *
 * Have a look to the 'defaults' variable below for further details about available parameters and default values.
 *
 * Example:
 * Ping Google Logo every 5 minutes and launch the first ping right now:
 *    $.pinger({
 *      interval: 5 * 60
 *      url: "http://www.google.co.uk/images/logos/ps_logo2.png",
 *      pingNow: true
 *    });
 */
(function ($) {

    var defaults = {
        interval: 10 * 60,    // pings the given URL every 'interval' MINUTES. Set to 0 for manual ping only
        url: null,       // the URL to ping

        listen: [
            "click",
            "keydown"
        ],  // events to listen for updating activity

        pingNow: false,  // If true, sends a ping request just after init
        beforeSend: null,   // Callback function, called before ping (should return true. false will cancel ping query)
        callback_success: false,  // Success callback function, called after ping query callback received
        callback_error: false   // Error callback function, called after ping query callback received
    };

    var options = {};
    var lastUpdate, checkInterval, iTime, _pingerLogs = false;

    /* Public methods */
    var methods = {
        init: function (settings) {
            options = $.extend(true, defaults, settings);

            if (!options.url) {
                $.error('jQuery.pinger: url parameter is mandatory');
                return;
            }

            log("$.pinger.init:", options);
            if (options.interval > 0) {

                lastUpdate = 0;
                iTime = (options.interval * 1000);

                checkInterval = setInterval(function () {
                    ping('interval');
                }, iTime);

                $(document).on(options.listen.join('.pinger '), function (event) {
                    update(event.type);
                });

                if (options.pingNow) {
                    ping('init');
                }
            }
        }, // Manual activity update

        now: function (param) {
            (options.interval && options.interval > 0) ? update(param) : ping(param);
        },

        destroy: function () {
            stop('destroy');
        }
    };

    /* Private Methods */
    function update(param) {
        log("$.pinger: activity update -", param);
        lastUpdate = (new Date()).getTime();
    }

    function ping(param) {
        log("$.pinger: Ping to", options.url, "(", param, ")");
        if (!options.beforeSend || options.beforeSend.apply(this, arguments)) {
            $.ajax({
                type: 'POST',
                url: options.url + "?" + (new Date().getTime()),

                success: function () {
                    // Success callback
                    log("$.pinger: Ping callback success", arguments);
                    if (options.callback_success) {
                        options.callback_success.apply(this, arguments);
                    }
                },

                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    // Error callback
                    log("$.pinger: Ping callback error", arguments);
                    if (options.callback_error) {
                        options.callback_error.apply(this, arguments);
                    }
                }
            });
        }
    }

    function stop(param) {
        log("$.pinger: Stopped -", param);
        $(document).off(options.listen.join('.pinger '));
        clearInterval(checkInterval);
    }

    function log() {
        if (_pingerLogs && console && console.log) {
            if (console.log.apply) {
                console.log.apply(console, arguments);
            } else {
                // console.log doesn't seem to be a "real" function in IE so apply can't be used
                console.log((Array.prototype.slice.call(arguments)).join(" "));
            }
        }
    }

    /* Plugin entry point */
    $.pinger = function (method) {
        // Method calling logic
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method ' + method + ' does not exist on jQuery.pinger');
            return this;
        }
    };
})(jQuery);

var UserPingStatus = function () {
    var showWarningMessage = function (type, message) {
        Ext.Msg.show({
            title: ucfirst(type),
            msg: message,
            icon: type === 'warning' ? Ext.MessageBox.WARNING : Ext.MessageBox.ERROR,
            buttons: {yes: 'OK'},
            closable: false,

            fn: function (btn) {
                if (btn === 'yes') {
                    window.location = baseUrl;
                }
            }
        });
    };

    var initPinger = function (booPingNow) {
        $.pinger({
            interval: 60,
            url: baseUrl + '/api/remote/isonline',
            pingNow: booPingNow,
            listen: [],

            callback_success: function (result) {
                // Do nothing - we expect that user will be logged out
                var match = result.match(/^Expiration in ([\d]+) (.*)/);
                if (match != null) {
                    // Don't ping anymore
                    $.pinger('destroy');

                    // Show the dialog if not shown before
                    var wnd = Ext.getCmp('expiration-dialog');
                    if (wnd) {
                        return;
                    }

                    var secondsLeft = match[1];
                    var totalSeconds = match[1];

                    wnd = new Ext.Window({
                        id: 'expiration-dialog',
                        title: _('Session timeout'),
                        layout: 'form',
                        modal: true,
                        resizable: false,
                        plain: false,
                        autoHeight: true,
                        autoWidth: true,
                        labelAlign: 'top',

                        items: [
                            {
                                xtype: 'container',
                                layout: 'column',
                                items: [
                                    {
                                        xtype: 'displayfield',
                                        style: 'font-size: 24px;',
                                        ref: '../timeLeftIcon',
                                        value: '<i class="las la-hourglass-start"></i>'
                                    }, {
                                        xtype: 'displayfield',
                                        style: 'font-size: 18px; padding-top: 3px;',
                                        value: _('Your online session will expire in')
                                    },
                                ]
                            }, {
                                xtype: 'displayfield',
                                ref: 'timeLeftField',
                                style: 'text-align: center; font-size: 24px;',
                                value: ''
                            }, {
                                xtype: 'displayfield',
                                style: 'font-size: 18px;',
                                value: _('Please click "Continue" to keep working<br>or click "Logout" to end your session now.')
                            }
                        ],

                        buttons: [
                            {
                                text: _('Logout'),
                                handler: function () {
                                    if (wnd.intervalID) {
                                        clearInterval(wnd.intervalID);
                                    }

                                    window.location = baseUrl + '/auth/logout';
                                }
                            }, {
                                text: _('Continue'),
                                cls: 'orange-btn',
                                handler: function () {
                                    if (wnd.intervalID) {
                                        clearInterval(wnd.intervalID);
                                    }

                                    // Send a request to extend the session
                                    Ext.Ajax.request({
                                        url: baseUrl + '/api/remote/extend-session',

                                        success: function (result) {
                                            initPinger(false);
                                            wnd.close();
                                        }
                                    });
                                }
                            }
                        ],

                        secondsToHumanReadable: function (seconds) {
                            var levels = [
                                [Math.floor(seconds / 31536000), 'years'],
                                [Math.floor((seconds % 31536000) / 86400), 'days'],
                                [Math.floor(((seconds % 31536000) % 86400) / 3600), 'hours'],
                                [Math.floor((((seconds % 31536000) % 86400) % 3600) / 60), 'minutes'],
                                [(((seconds % 31536000) % 86400) % 3600) % 60, 'seconds'],
                            ];

                            var returntext = '';
                            for (var i = 0, max = levels.length; i < max; i++) {
                                if (levels[i][0] === 0) {
                                    continue;
                                }

                                returntext += ' ' + levels[i][0] + ' ' + (levels[i][0] === 1 ? levels[i][1].substr(0, levels[i][1].length - 1) : levels[i][1]);
                            }

                            return returntext.trim();
                        },

                        updateTimeLeft: function () {
                            // Show minutes/seconds left in the human-readable format
                            wnd.timeLeftField.setValue(wnd.secondsToHumanReadable(secondsLeft));

                            // Show different icons in relation to the % of the time is left
                            var icon = '';
                            if (secondsLeft >= totalSeconds * 0.75) {
                                icon = '<i class="las la-hourglass-start"></i>';
                            } else if (secondsLeft >= totalSeconds * 0.5) {
                                icon = '<i class="las la-hourglass-half"></i>';
                            } else if (secondsLeft >= totalSeconds * 0.25) {
                                icon = '<i class="las la-hourglass-end"></i>';
                            } else {
                                icon = '<i class="las la-hourglass"></i>';
                            }
                            wnd.timeLeftIcon.setValue(icon);

                            secondsLeft -= 1;
                            if (secondsLeft <= 0) {
                                clearInterval(wnd.intervalID);
                                initPinger(true);
                                wnd.close();
                            }
                        },

                        listeners: {
                            'show': function () {
                                // Update ticker each second
                                wnd.intervalID = setInterval(wnd.updateTimeLeft, 1000);
                                wnd.updateTimeLeft();
                            }
                        }
                    });

                    wnd.show();
                }
            },

            callback_error: function (jqXHR) {
                if (jqXHR.status === 401) {
                    showWarningMessage('warning', jqXHR.responseText);
                }

                $.pinger('destroy');
            }
        });
    };

    initPinger(false);
};