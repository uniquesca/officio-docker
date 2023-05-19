jQuery(function () {

    // slider
    $('#slides').slides({
        effect: 'fade',
        fadeSpeed: 700,
        play: 7000,
        pause: 4000,
        generateNextPrev: true,
        next: "next",
        prev: "prev",
        generatePagination: false,
        crossfade: true,
        hoverPause: true,
        animationStart: function (current) {
            $('.caption').fadeOut(200);
        },
        animationComplete: function (current) {
            $('.caption').fadeIn(300);
        },
        slidesLoaded: function () {
            $('.caption').fadeIn(300);
        }
    });

    jQuery('#superfish-1').supersubs({minWidth: 12, maxWidth: 27, extraWidth: 1}).superfish({
        animation: {opacity: 'show', height: 'show'},
        speed: 'fast',
        autoArrows: false,
        dropShadows: true
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