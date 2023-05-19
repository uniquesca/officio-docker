function notif(options) {
    /**
     * extend obj function
     */
    function extend(a, b) {
        for (var key in b) {
            if (b.hasOwnProperty(key)) {
                a[key] = b[key];
            }
        }
        return a;
    }

    // Default settings
    var defaultOptions = {
        // element to which the notification will be appended
        // defaults to the document.body
        wrapper: document.body,

        message: '',

        delayBeforeShow: 2000,
        autoHideDelay: 10000,

        onClose: null,
        onContentClick: null
    }

    options = extend(defaultOptions, options);

    // txt, onclose, onContentClick
    var dismissTTL;
    var active = false;

    var a = document.createElement('div');
    $(a)
        .attr('class', 'notif')
        .css('display', 'none');

    var b = document.createElement('p');
    var strClasses = 'alert';
    if (options.onContentClick !== null) {
        strClasses += ' clickable';
    }
    $(b).attr('class', strClasses);
    $(b).html(options.message);
    $(a).append(b);

    var d = document.createElement('div');
    $(d).attr('class', 'close');
    $(d).html('&times;');
    $(a).append(d);

    $(options.wrapper).append(a);

    // Show with delay + effect
    setTimeout(function () {
        $(a).slideDown({
            duration: 1000,
            start: function () {
                $(this).css({
                    display: 'flex'
                })
            },

            complete: function () {
                active = true;

                if (options.autoHideDelay > 0) {
                    // Auto close in 5 seconds
                    dismissTTL = setTimeout(function () {
                        if (active) {
                            dismiss();
                        }
                    }, options.autoHideDelay);
                }
            }
        });
    }, options.delayBeforeShow);

    var dismiss = function () {
        active = false;
        clearTimeout(dismissTTL);

        var div = $(a);
        div.slideUp(1000, function () {
            if (options.onClose !== null) {
                options.onClose();
            }
            div.remove();
        });
    }

    // Click on the close button
    $(d).click(function () {
        dismiss();
    });

    // Click on the content
    if (options.onContentClick !== null) {
        $(b).click(function () {
            options.onContentClick();
            return false;
        });
    }
}