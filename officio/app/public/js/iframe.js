var adjustHeight = function () {
    var checkInterval;
    var resizeIFrame = function (iframe) {
        // Set inline style to equal the body height of the iframed content.
        var doc = iframe.contentDocument ? iframe.contentDocument : iframe.contentWindow.document;
        if (doc.body.offsetHeight > 0) {
            $(iframe).css({
                height: doc.body.offsetHeight
            });

            clearInterval(checkInterval);
        } else {
            checkInterval = setTimeout(function () {
                resizeIFrame(iframe);
            }, 500);
        }
    };

    $('.admin_frame').on('load', function () {
        var iframe = this;
        setTimeout(function () {
            resizeIFrame(iframe);
        }, 500);
    });
}