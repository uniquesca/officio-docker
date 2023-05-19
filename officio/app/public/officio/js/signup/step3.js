var gt = new Gettext();

// create a shortcut for gettext
function _(msgid) {
    return gt.gettext(msgid);
}

function createProspect() {
    $('#loadingImage').show();
    $('#nextBtn').hide();
    $('#divError').hide();

    $.ajax({
        type: 'POST',
        url: baseUrl + '/signup/index/create-prospect',
        data: $('#newCompanyForm').serialize(),
        success: function (parsedResponse) {
            if (!parsedResponse.success) {
                showErrorMsg(parsedResponse.message);
                $('#loadingImage').hide();
                $('#nextBtn').show();
            } else {
                window.location.href = baseUrl + '/signup/index/step4?pkey=' + parsedResponse.pkey;
            }
        },

        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $('#loadingImage').hide();
            showErrorMsg(textStatus);
            $('#nextBtn').show();
        }
    });
}

function showErrorMsg(msg) {
    $('#divError').html(msg).show();
}

$(document).ready(function () {
    // add * to required field labels
    $('label.required').not('.required_not_mark').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

    jQuery.validator.addMethod('pageRequired', function (value, element) {
        return !this.optional(element);
    }, $.validator.messages.required);

    jQuery.validator.addMethod('emailUniques', function (email, element) {
        email = email.replace(/\s+/g, '');
        return email.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
    }, _('Please enter a valid Email.'));

    jQuery.validator.addMethod('phoneUniques', function (phone_number, element) {
        phone_number = phone_number.replace(/\s+/g, '');
        return phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
    }, _('Please enter a valid Phone.'));

    jQuery.validator.messages.remote = _('This username is already used.');

    // validate signup form on keyup and submit
    var validator = $('#newCompanyForm').validate({
        debug: true,

        rules: {
            // Company info
            companyName: {
                pageRequired: true
            },

            companyPhone: {
                pageRequired: true,
                phoneUniques: true
            },

            // Admin info
            firstName: {
                pageRequired: true
            },

            lastName: {
                pageRequired: true
            },

            emailAddress: {
                pageRequired: true,
                emailUniques: true
            },

            username: {
                pageRequired: true
            },

            password: {
                pageRequired: true
            }
        },

        // the errorPlacement has to take the table layout into account
        errorPlacement: function (label, element) {
            label.appendTo(element.closest('.form-group'));
        },

        // set this class to error-labels to indicate valid fields
        success: function (label) {
            // set as text for IE
            label.html('&nbsp;').addClass('valid');
        },

        invalidHandler: function (form, validator) {
            if (!validator.numberOfInvalids())
                return;

            var el = $(validator.errorList[0].element);

            $('html, body').animate({
                scrollTop: el.offset().top - 150
            }, 1000, function () {
                // Focus the field after we scrolled to it
                el.focus();
            });
        }
    });

    $('#previousBtn').click(function (event) {
        $('#price_submit').submit();
        return false;
    });

    $('#nextBtn').click(function (event) {
        if (validator.form()) {
            createProspect()
        }
        return false;
    });

    $('#accept_terms').change(function () {
        if ($(this).is(':checked')) {
            $('#nextBtn').prop('disabled', false);
        } else {
            $('#nextBtn').prop('disabled', true);
        }
    });

    $('.inline_footer').colorbox({width: '80%'});
});

