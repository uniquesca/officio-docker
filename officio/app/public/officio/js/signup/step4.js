var gt = new Gettext();

// create a shortcut for gettext
function _(msgId) {
    return gt.gettext(msgId);
}

function sendCCInfo() {
    $('#loadingImage').show();
    $('#nextBtn').hide();
    $('#divError').hide();

    $.ajax({
        type: 'POST',
        url: baseUrl + '/signup/index/charge-and-create-company',
        data: $('#newCompanyForm').serialize(),
        success: function (response) {
            if (!response.success) {
                showErrorMsg(response.message);
                $('#loadingImage').hide();
                $('#nextBtn').show();

                if (response.company_charged) {
                    $('#nextBtn').html(_('Create a Company'));
                } else {
                    $('#nextBtn').html(_('Pay and Create a Company'));
                }

                if (booRecaptchaEnabled) {
                    grecaptcha.reset();
                }
            } else {
                $('#previousBtn').addClass('disabled');
                $('#confirm').addClass('active');
                $('.wizardcontent').hide();
                $('#success_message').show();
            }
        },

        error: function (XMLHttpRequest, textStatus, errorThrown) {
            $('#loadingImage').hide();
            showErrorMsg(textStatus);
            $('#nextBtn').show();

            if (booRecaptchaEnabled) {
                grecaptcha.reset();
            }
        }
    });
}

function showErrorMsg(msg) {
    $('#divError').html(msg).show();
}

function initCombo(validator) {
    $('select.combo').each(function () {
        $(this).select2();
        $(this).on('change', function (e) {
            validator.element(this);
        });
    });
}

$(document).ready(function () {
    // add * to required field labels
    $('label.required').not('.required_not_mark').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

    jQuery.validator.addMethod('checkState', function (val, element) {
        return !($('#state-div').css('display') != 'none' && val == '');
    }, _('Please enter a valid Province/State.'));

    jQuery.validator.addMethod('checkProvince', function (val, element) {
        return !($('#provinces-div').css('display') != 'none' && val == '');
    }, _('Please select a Province/State.'));

    jQuery.validator.addMethod('pageRequired', function (value, element) {
        return !this.optional(element);
    }, $.validator.messages.required);

    jQuery.validator.addMethod('emailUniques', function (email, element) {
        email = email.replace(/\s+/g, '');
        return email.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
    }, _('Please enter a valid Email.'));

    jQuery.validator.addMethod('postalUniques', function (postal, element) {
        postal = postal.replace(/\s+/g, '');
        return this.optional(element) || postal.match(/^[0-9A-Za-z]+([\s\-]{1}[0-9A-Za-z]+)?$/i);
    }, _('Please enter a valid Zip.'));

    jQuery.validator.addMethod('ccExpMonthUniques', function (exp_month, element) {
        if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888' || $('#ccExpMonth option:selected').text() != 'Month') {
            return true;
        } else {
            return false;
        }
    }, _('This field is required.'));

    jQuery.validator.addMethod('ccExpYearUniques', function (exp_year, element) {
        if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888' || $('#ccExpYear option:selected').text() != 'Year') {
            return true;
        } else {
            return false;
        }
    }, _('This field is required.'));

    jQuery.validator.addMethod('ccCVNUniques', function (value, element) {
        if ($('#ccNumber').val() !== '8888' && value === '') {
            return false;
        } else {
            return true;
        }
    }, _('This field is required.'));

    jQuery.validator.addMethod('ccNameUniques', function (value, element) {
        if ($('#ccNumber').val() !== '8888' && value === '') {
            return false;
        } else {
            return true;
        }
    }, _('This field is required.'));

    jQuery.validator.addMethod('ccpageRequiredNumUniques', function (value, element) {
        if ($('#ccName').val() !== '8888' && value === '') {
            return false;
        } else {
            return true;
        }

    }, _('This field is required.'));

    jQuery.validator.addMethod('ccNumUniques', function (value, element, param) {
        if (value === '8888' || $('#ccName').val() === '8888') {
            $('#ccNumber').attr('class', 'form-control valid custom');
            return true;
        }

        var booValid = false;
        $('#ccNumber').validateCreditCard(function (result) {
            if (result.card_type != undefined && result.card_type.name != undefined) {
                $(this).attr('class', 'form-control ' + result.card_type.name);
            } else {
                $(this).attr('class', 'form-control');
            }

            if (result.valid != undefined && result.valid) {
                booValid = true;
                $('#ccType').val(result.card_type.name);
            } else {
                $('#ccType').val();
            }
        }, {accept: ['visa', 'mastercard']});

        return booValid;
    }, _('Please enter a valid Credit Card Number.'));

    // validate signup form on keyup and submit
    var validator = $('#newCompanyForm').validate({
        debug: true,

        rules: {
            // Company info
            salutation: {
                pageRequired: true
            },

            firstName: {
                pageRequired: true
            },

            lastName: {
                pageRequired: true
            },

            company_abn: {
                pageRequired: booShowABN
            },

            companyEmail: {
                pageRequired: true,
                emailUniques: true
            },

            address: {
                pageRequired: true
            },

            city: {
                pageRequired: true
            },

            state: {
                checkState: true
            },

            province: {
                checkProvince: true
            },

            country: {
                pageRequired: true
            },

            zip: {
                postalUniques: true
            },

            // CC info
            ccNumber: {
                ccpageRequiredNumUniques: function () {
                    return $('#ccNumber').val();
                },
                ccNumUniques: function () {
                    return $('#ccNumber').val();
                }
            },

            ccName: {
                ccNameUniques: function () {
                    return $('#ccName').val();
                }
            },

            ccExpMonth: {
                ccExpMonthUniques: function () {
                    return $('#ccExpMonth').val();
                }
            },

            ccExpYear: {
                ccExpYearUniques: function () {
                    return $('#ccExpYear').val();
                }
            },

            ccCVN: {
                ccCVNUniques: function () {
                    return $('#ccCVN').val();
                }
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

    initCombo(validator);

    $('#nextBtn').click(function (event) {
        if (validator.form()) {
            if (booRecaptchaEnabled) {
                grecaptcha.execute();
            } else {
                sendCCInfo();
            }
        }
        return false;
    });

    function toggleProvincesCombo() {
        if ($('#country').val() == defaultCountryId) {
            $('#provinces-div').show();
            $('#state-div').hide();
        } else {
            $('#provinces-div').hide();
            $('#state-div').show();
        }
        $('#country_normal').prop('value', $('#country option:selected').text());
    }

    $('#country').change(function () {
        toggleProvincesCombo();

        $('#newCompanyForm').validate().element('#country');

    });
    toggleProvincesCombo();

    $('#ccNumber').keyup(function () {
        var foo = $(this).val().split('-').join(''); // remove hyphens
        if (foo.length > 0) {
            foo = foo.match(new RegExp('.{1,4}', 'g')).join('-');
        }
        $(this).val(foo);
    });

    $('#ccExpMonth').change(function () {
        $('#newCompanyForm').validate().element('#ccExpMonth');
    });

    $('#ccExpYear').change(function () {
        $('#newCompanyForm').validate().element('#ccExpYear');
    });

    $('.inline_footer').colorbox({width: '80%'});
});

