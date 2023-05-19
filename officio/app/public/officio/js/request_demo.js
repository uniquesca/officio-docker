$(document).ready(function(){
    // add * to required field labels
    $('label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

    jQuery.validator.addMethod("phoneUniques", function(phone_number, element) {
            phone_number = phone_number.replace(/\s+/g, "");
            return phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
    }, "Please enter a valid Phone.");
    
    jQuery.validator.messages.required = "";
    $("#newCompanyForm").validate({
        invalidHandler: function(e, validator) {
                    var errors = validator.numberOfInvalids();
                    if (errors) {
                        var message = errors == 1
                            ? 'You missed 1 field. It has been highlighted above'
                            : 'You missed ' + errors + ' fields.  They have been highlighted above';
                        $("div.error span").html(message);
                        $("div.error").show();
                    } else {
                        $("div.error").hide();
                    }
        },
        
        rules: {
            phone1: {
                required: true,
                phoneUniques: true
            }
        }
    });
    
    /*
    $('#newCompanyForm .required').change(function(){
        if($("#newCompanyForm").valid()) {
            $("div.error").hide();
        }
    });
    */
});