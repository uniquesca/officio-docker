Ext.onReady(function() {
    Ext.QuickTips.init();

    $('#manage-admin-users-content').css('min-height', getSuperadminPanelHeight() + 'px');

    new GeneratePasswordButton({
        renderTo: 'generatePassword',
        passwordField: 'password'
    });

    $.validator.addMethod("usernameUniques",function(value,element){
        var pattern = new RegExp("^[a-zA-Z0-9_.@\-àÀâÂäÄáÁéÉèÈêÊëËìÌîÎïÏòÒôÔöÖùÙûÛüÜçÇ’ñ]{3,64}$", "i");
        return this.optional(element) || pattern.test(value);
    }, "<br/>3-64 characters (letters, digits and _@.- symbols).");

    $.validator.addMethod("passwordValidation",function(value,element){
        var pattern = new RegExp(passwordValidationRegexp); // @see var passwordValidationRegexp;  layouts/*/*.phtml
        return this.optional(element) || pattern.test(value);
    }, passwordValidationRegexpMessage);

    var mainForm = $("#editAdminUserForm");
    mainForm.attr("autocomplete","off");

    // validate signup form on keyup and submit
    mainForm.validate({
        rules: {
            roleID: {
                required: true
            },
            username: {
                required: true,
                usernameUniques: true,
                minlength: 3,
                maxlength: 64
            },
            password: {
                minlength: passwordMinLength,
                maxlength: passwordMaxLength,
                passwordValidation: true
            },
            fName: {
                required: true
            },
            lName: {
                required: true
            },
            emailAddress: {
                required: true,
                email: true
            }
        },


        messages: {
            roleID: {
                    required: "Please select a Role"
            },
            username: {
                required: "Please enter a Username",
                minlength: jQuery.validator.format("Please enter at least {0} characters"),
                remote: jQuery.validator.format("<span style='font-style: italic; font-size: 13px;'>{0}</span> is already in use")
            },
            password: {
                rangelength: jQuery.validator.format("Please enter at least {0} characters")
            },
            fName: {
                required: "Please enter a First Name"
            },
            lName: {
                required: "Please enter a Last Name"
            },
            emailAddress: {
                required: "Please enter Email",
                email: "Please enter a valid Email"
            }

        },

        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("checked");
        }
    });
});
