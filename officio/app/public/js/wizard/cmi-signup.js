var current;
var validator;
var stepsCount;

$(document).ready(function(){
    // Calculate steps count
    stepsCount = getStepsCount();
    
    // Init all fields, sections
    initAll(true);

    // All content starts hidden
    $('.wizardcontent').hide();

    // Initialize the wizard state
    current = 1;
    load_state(current);
    

    // Validate signup form on keyup and submit
    validator = $("#newCompanyForm").validate({
        rules: {
            // Company details
            users_count: {
                pageRequired: true,
                digits: true,
                min: 1,
                max: 5
            },

            companyName: {
                pageRequired: true,
                companyUniques: true
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
                pageRequired: true
            },
            stateCombo: {
                pageRequired: true
            },
            country: {
                pageRequired: true
            },
            phone1: {
                pageRequired: true,
                phoneUniques: true
            },
            phone2: {
                phoneUniques: true
            },
            fax: {
               phoneUniques: true
            },
            zip: {
               postalUniques: true
            },

            office: {
                pageRequired: true
            }
        },
        
        // the errorPlacement has to take the table layout into account
        errorPlacement: function(label, element) {
                // position error label after generated textarea
                if (element.is(":checkbox")) {
                    label.appendTo( element.parent().parent() );
                } else {
                    label.insertAfter(element);
                }
        },


        messages: {
        },

        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("valid");
        }
    });
    
    // loads new state based on button clicked
    $(':submit').click(function(){
        $('#divError').hide();
        $('#thankYouMsg').hide();

        var current_state = $('#wizard').attr('class');
        //we only want the number, converted to an int
        current_state = parseInt(current_state.replace(/(step_)/, ""), 10);

        var booIsNext = ($(this).attr('id') == 'next');

        if (booIsNext && validator.form()) {
            var booAllowNext = false;
            switch(current_state){
                case 1:
                    // Disable buttons
                    $('#next').attr("disabled","disabled");
                    $('#previous').attr("disabled","disabled");
                    
                    $('#loadingImage').show();

                    // Fix issue with JQuery 1.5 with json
                    jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

                    // Check CMI info
                    $.ajax({
                        url: baseUrl + '/cmi-signup/index/check-cmi',
                        dataType : "json",
                        type: 'post',
                        
                        data: {
                            cmi_id: $.toJSON($('#cmi_id').val()),
                            reg_id: $.toJSON($('#reg_id').val())
                        },
                        
                        success: function (data, textStatus) {
                            if(data !== null && data.success) {
                                // reset the wizardcontent to hidden
                                $('.wizardcontent').hide();

                                current_state++;
                                current = current_state;
                                load_state(current_state);
                            } else {
                                showErrorMessage(data.msg);
                                $('#next').removeAttr("disabled");
                            }
                            
                            $('#loadingImage').hide();
                            
                            return true;
                        }, 
                        
                        error: function (XMLHttpRequest, textStatus, errorThrown) {
                            showErrorMessage('Error happened during information checking. Please try again later.');
                            $('#next').removeAttr("disabled");
                        }
                    });                    
                    break;

                case 3:
                    // Check if at least one Admin role is checked
                    var booIsChecked = false;
                    var arrRolesList = $.evalJSON(arrRoles);
                    
                    $("input[name^='user_role']:checked").each(function(id, txt) {
                        var currentCheckedRoleId = $(txt).val();
                        if(arrRolesList.length > 0) {
                            for(var i=0; i<arrRolesList.length; i++) {
                                if(arrRolesList[i].role_type == 'admin' && currentCheckedRoleId == arrRolesList[i].role_id) {
                                    booIsChecked = true;
                                    break;
                                }
                            }
                        }
                    });
                    
                    if(!booIsChecked) {
                        alert("At least one user must be an admin. Please set a user's role to admin.");
                        return false;
                    }
                    
                    // Check if username is unique
                    var usersCount = $('#users_count').val();
                    var arrUsernames = new Array();
                    for(var i=1; i<=usersCount; i++) {
                        arrUsernames[i-1] = $('#username' + i).val();
                    }
                    
                    if(arrUsernames.length > 0) {
                        for(i=0; i<arrUsernames.length; i++) {
                            var checkUsername = arrUsernames[i];
                            var count = 0;
                            
                            for(var j=0; j<arrUsernames.length; j++) {
                                if(checkUsername == arrUsernames[j]) {
                                    count++;
                                }
                            }
                            
                            if(count > 1) {
                                alert("Each user must have unique username.\nUsername "+checkUsername+" was used "+count+" times.");
                                return false;
                            }
                        }
                    }
                    
                    
                    booAllowNext = true;
                    break;
                    
                case stepsCount:
                    addCompany();
                    return false;
                    break;
                    
                default:
                    booAllowNext = true;
                    break;
            }
            
            if(booAllowNext) {
                //reset the wizardcontent to hidden
                $('.wizardcontent').hide();
                current_state++;
                current = current_state;
                load_state(current_state);
            }

        } else if (!booIsNext) {
            //reset the wizardcontent to hidden
            $('.wizardcontent').hide();

            current_state--;
            current = current_state;
            load_state(current_state);
        }
        
        return false;
    });
});