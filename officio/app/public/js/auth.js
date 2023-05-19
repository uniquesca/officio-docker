var booLoginInProcess = false;

$(document).ready(function() {
    $('#login_container').show();

    //focus
    if ($('#username_login').val() !== '') {
        $('#password_login').focus();
    } else {
        $('#username_login').focus();
    }

    // If the website is opened in the iframe - open a new browser's tab and show a redirection message in the first tab
    // This is needed because of session cookies - they will be blocked
    var booInIframe;
    try {
        booInIframe = window.self !== window.top;
    } catch (e) {
        booInIframe = true;
    }

    $('input[id=username_login],input[id=password_login],input[id=site_version]').keydown(function (event) {
        switch (event.keyCode) {
            case 13:
                login(booInIframe);
                break;

            default:
                break;
        }
    });

    $('#login_button').click(function () {
        login(booInIframe);
    });

    $('#retrieve-email-dlg').find('form').attr("autocomplete", "off");
});

function showError(msg) {
    $('#errorDescription').html(msg);
    $('#loadingImage').hide();
    $('#divError').show('slow');
}

function showRetrievePass() {
    $('#retrieve').overlay({
        // custom top position
        top: 173,

        // some expose tweaks suitable for facebox-looking dialogs
        expose: {

            // you might also consider a "transparent" color for the mask
            color: '#000',

            // load mask a little faster
            loadSpeed: 200,

            // highly transparent
            opacity: 0.5
        },

        // disable this for modal dialog-type of overlays
        closeOnClick: false,

        // we want to use the programming API
        api: true

    // load it immediately after the construction
    }).load();

    // set focus
    $('#retrieve-input').focus();
    $('#retrieve-error-msg').hide();

    updateCaptchaText();
}

function updateCaptchaText()
{
    $('#captcha-content').html(
        '<img src="' + baseUrl + '/images/loading.gif" alt="Loading" /> Loading...'
    );

    $.ajax({
        type: 'POST',
        url: baseUrl + '/auth/retrieve-password-form',
        success: function(content) {
        $('#captcha-input').val('');
            $('#captcha-content').html(content);
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            showError(textStatus);
        }
      });
}

function retrievePass()
{
    $('#retrieve-error-msg').hide();

    var email = $('#retrieve-input').val();
    var captchaInput = $('#captcha-input').val();
    var captchaId = $('#captcha-id').val();

    if (email === '') {
        $('#retrieve-error-msg').html('Please provide email address').show();
    }
    else if (captchaInput === '') {
        $('#retrieve-error-msg').html('Please provide a captcha text').show();
    }
    else {
        $.ajax({
            type: 'POST',
            url: baseUrl + '/auth/retrieve-password',
            data: {email: email,
                   captchaInput: captchaInput,
                   captchaId: captchaId},
            success: function(msg) {
                 switch (msg) {
                     case 'invalid_email' :
                        updateCaptchaText();
                        $('#retrieve-error-msg').html(
                            'We could not locate an account ' +
                            'with the email address you provided.'
                        ).show();
                        break;

                     case 'invalid_captcha' :
                        updateCaptchaText();
                        $('#retrieve-error-msg').html(
                            'Please enter the valid 4 letter text'
                        ).show();
                        break;

                     default:
                         $('#retrieve-result-msg').html(msg);
                         $('#retrieve-result-dlg').show();
                         $('#retrieve-email-dlg').hide();
                         break;
                 }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                showError(textStatus);
            }
          });
    }
}

function restoreRetrieveData()
{
    $('#retrieve-result-dlg').hide();
    $('#retrieve-input').val('');
    $('#captcha-input').val('');

    $('#retrieve').overlay().close();

    setTimeout(function() {$('#retrieve-email-dlg').show();}, 1000);
}

function login(booOpenInNewTab)
{
    if (booLoginInProcess) {
        return false;
    }

    booLoginInProcess = true;

    var name = $('#username_login').val();
    var pass = $('#password_login').val();

    $('#divError').hide();

    if (name === '' || pass === '') {
        showError('Please enter username and password.');
        booLoginInProcess = false;
        return false;
    }

    var oData = {
        username: name,
        password: pass
    };

    if (booOpenInNewTab) {
        $('#loginTable').hide();
        $('#divRedirection').show();
        $('#divRedirection img').hide();
        $('#divRedirection div').html('Officio will open in a new browser tab...');

        booLoginInProcess = false;

        var f = $("<form target='_blank' method='POST' style='display:none;'></form>").attr({
            action: baseUrl + '/auth/login'
        }).appendTo(document.body);

        // Make sure that after login we'll have a redirect to the home page
        oData['redirect_iframe'] = 1;

        for (var i in oData) {
            if (oData.hasOwnProperty(i)) {
                $('<input type="hidden" />').attr({
                    name: i,
                    value: oData[i]
                }).appendTo(f);
            }
        }

        f.submit();
        f.remove();
    } else {
        $('#loadingImage').show();

        $.ajax({
            type: 'POST',
            url: baseUrl + '/auth/login',
            data: oData,

            success: function (error) {
                booLoginInProcess = false;
                if (error !== '') {
                    showError(error);
                } else {

                    // Hide login form and show confirmation message
                    $('#loadingImage').hide();
                    $('#loginTable').hide();
                    $('#divRedirection').show();

                    if (typeof googleTagManagerLogin !== 'undefined') {
                        googleTagManagerLogin();

                        // Give time for Google Tag Manager
                        setTimeout(function () {
                            location.href = baseUrl;
                        }, 500);
                    } else {
                        location.href = baseUrl;
                    }
                }
            },

            error: function (XMLHttpRequest, textStatus, errorThrown) {
                booLoginInProcess = false;
                showError(textStatus);
            }
        });
    }

    return false;
}