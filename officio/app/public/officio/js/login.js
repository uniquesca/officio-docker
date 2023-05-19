var booLoginInProcess = false;

jQuery(document).ready(function(){
    var div = jQuery("#officio_login_div");
    div.load("login.html");
    div.find("input[id!=login_button]").on("keydown", function(event){
        switch (event.keyCode) {
            case 13: login();
        }
    });
    
    
    jQuery("#officio_login_open").click(function () {
        window.location = 'https://secure.officio.ca/auth/login';
    });
    
    jQuery("#login_button").on("click", function(){
        login();
    });

    jQuery(".inline_footer").colorbox({width:"55%"});
});


function showError(msg) {
    jQuery('#errorDescription').html(msg);
    jQuery('#loadingImage').hide();
    jQuery('#divError').show('slow');
}

function login()
{
    if(booLoginInProcess)
        return false;

    var name  = jQuery('#username1').val();
    var pass  = jQuery('#password1').val();
    var https = jQuery('#checkboxssl').prop("checked") ? 1 : 0;
    
    if(name === '' || pass === '') {
        showError('Please enter username and password.');
        booLoginInProcess = false;
        return false;
    }
    
    booLoginInProcess = true;
    
    jQuery('#loadingImage').show();
    jQuery('#divError').hide();

    jQuery.ajax({
        type: "POST",
        url: "proxy.php",
        data: { url: https, username: name, password: pass },
        success: function (error) {
            booLoginInProcess = false;
            if (error !== '') {
                showError(error);
            } else {
                // Hide login form and show confirmation message
                jQuery('#loadingImage').hide();
                jQuery('#loginTable').hide();
                jQuery('#divRedirection').show();

                jQuery('#username').prop('value', name);
                jQuery('#password').prop('value', pass);

                var submitUrl = https ? 'https://secure.officio.ca/auth/login' : 'http://secure.officio.ca/auth/login';
                var form = jQuery('#finish_submit');
                form.attr('action', submitUrl);
                form.submit();
            }
        },
        error: function (XMLHttpRequest, textStatus) {
            booLoginInProcess = false;
            showError(textStatus);
        }
    });

    return true;
}