$(function () {

    // IPad/IPhone
    var viewportmeta = document.querySelector && document.querySelector('meta[name="viewport"]'),
        ua = navigator.userAgent,

        gestureStart = function () {
            viewportmeta.content = "width=device-width, minimum-scale=0.25, maximum-scale=1.6";
        },

        scaleFix = function () {
            if (viewportmeta && /iPhone|iPad/.test(ua) && !/Opera Mini/.test(ua)) {
                viewportmeta.content = "width=device-width, minimum-scale=1.0, maximum-scale=1.0";
                document.addEventListener("gesturestart", gestureStart, false);
            }
        };

    scaleFix();
    // Menu Android
    var userag = navigator.userAgent.toLowerCase();
    var isAndroid = userag.indexOf("android") > -1;
    if (isAndroid) {
        $('.sf-menu').responsiveMenu({autoArrows: true});
    }
});

$(function () {

    // slides
    $('#slides').slides({
        effect: 'fade',
        fadeSpeed: 700,
        play: 7000,
        pause: 4000,
        generatePagination: true,
        crossfade: true,
        hoverPause: true
    });

    // login form
    $('#login-button').click(function () {

        $.ajax({
            url: $('#login').attr('action'),
            type: 'POST',
            data: {
                username: $('#login input[name=username]').val(),
                password: $('#login input[name=password]').val()
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    window.location.replace(response.redirect);
                } else {
                    $('#login .error').text(response.error);
                }
            }
        });

        return false;
    });

    $('#login input[type=password]').keyup(function (e) {
        if (e.keyCode == 13) {
            $('#login-button').click();
        }
        return false;
    });
});