$(window).on('load', function () {

    // Slider
    $('#slides').slides({
        effect: 'fade',
        fadeSpeed: 700,
        play: 4000,
        pause: 4000,
        generateNextPrev: false,
        generatePagination: true,
        crossfade: true,
        hoverPause: true,
        animationStart: function () {
            $('.caption').animate({opacity: 0});
        },
        animationComplete: function () {
            $('.caption').animate({opacity: 1});
        },
        slidesLoaded: function () {
            $('.caption').animate({opacity: 1});
        }
    });

    // Contact form
    var contactForm = $('#contact-form');
    var nameField = contactForm.find('.name input');
    var emailField = contactForm.find('.email input');
    var messageField = contactForm.find('.message textarea');

    var contactDefaults = [];
    contactDefaults['name'] = nameField.val();
    contactDefaults['email'] = emailField.val();
    contactDefaults['message'] = messageField.val();

    contactForm
        .find('.name input, .email input, .message textarea')
        .blur(function () {
            if ($(this).val() == '') {
                $(this).val(contactDefaults[$(this).attr('name')]);
            }
            return false;
        }).focus(function () {
        if ($(this).val() == contactDefaults[$(this).attr('name')]) {
            $(this).val('');
        }
        return false;
    });

    nameField.keyup(function () {
        contactForm.find('#name-empty').hide();
    });

    emailField.keyup(function () {
        contactForm.find('#email-empty').hide();
        contactForm.find('#email-incorrect').hide();
    });

    messageField.keyup(function () {
        contactForm.find('#message-empty').hide();
    });

    // reset button
    contactForm.find('a[data-type=reset]').click(function () {
        nameField.val(contactDefaults['name']);
        emailField.val(contactDefaults['email']);
        messageField.val(contactDefaults['message']);
        return false;
    });

    // send button
    contactForm.find('a[data-type=submit]').click(function () {

        var booError = false;

        // hide status message
        contactForm.find('message').hide();

        // validate name field
        if (nameField.val() == '' || nameField.val() == contactDefaults['name']) {
            contactForm.find('#name-empty').show();
            booError = true;
        }

        // validate email field
        if (emailField.val() == '' || emailField.val() == contactDefaults['email']) {
            contactForm.find('#email-empty').show();
            booError = true;
        } else {
            var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            if (!re.test(emailField.val())) {
                contactForm.find('#email-incorrect').show();
                booError = true;
            }
        }

        // validate message
        if (messageField.val() == '' || messageField.val() == contactDefaults['message']) {
            contactForm.find('#message-empty').show();
            booError = true;
        }

        // send message
        if (!booError) {
            $.ajax({
                url: contactForm.attr('action'),
                type: 'POST',
                dataType: 'json',
                data: {
                    name: nameField.val(),
                    email: emailField.val(),
                    message: messageField.val()
                },
                success: function (response) {
                    if (response && response.success) {
                        contactForm.find('.status').html(response.msg).show();
                        contactForm.find('a[data-type=reset]').click();

                    } else if (response && response.error) {
                        contactForm.find('.status').html(response.error).show();
                    } else {
                        contactForm.find('.status').html(_('Cannot contact with a company')).show();
                    }
                }, failure: function () {
                    contactForm.find('.status').html(_('Cannot send an email')).show();
                }
            });
        }

        return false;
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