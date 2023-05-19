var first_step;
var current;
var validator;
var stepsCount;

$(document).ready(function(){

    $('.inline_footer').colorbox({
        width: '80%',
        height: '400px',
    });

    // Handle page error message if available
    if(strError){
        $('#pageTitle').hide()
        $('#mainNav').hide();
        $('#progressbar').hide();
        $('#wizardcontentwrap').hide();
        $('#pageError').html('<p class="mb-0">'+ strError +'</p>');
        $('#pageError').show();
        return;
    }

    // Calculate steps count
    stepsCount = parseInt($('#stepsCount').val(), 10);

    // Init all fields, sections
    initAll();

    // all content starts hidden
    $('.wizardcontent').hide();

    // initialize the wizard state
    current = parseInt($('#start_step').val(), 10);

    first_step = (current == 3) ? 3 : 1;

    load_state(current);

    // validate signup form on keyup and submit
    if (maxUsersCount <= 1) {
        maxUsersCount = 1;
    }
    validator = $("#newCompanyForm").validate({
        rules: {
        
            // Company details
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
        var booIsPrev = ($(this).attr('id') == 'previous');

        if (booIsNext
            && (current_state <= 2 || validator.form())) {
            var booAllowNext = false;
            
            switch(current_state){
                case 1:
                    // Disable buttons
                    $('#next').attr("disabled","disabled");
                    $('#previous').attr("disabled","disabled");

                    $('#loadingImage').show();

                    // Check Key info
                    $.ajax({
                        url: baseUrl + '/companywizard/index/check-prospect-key',
                        dataType : "json",
                        type: 'post',

                        data: {
                            key: $.toJSON($('#key').val())
                        },

                        success: function (data, textStatus) {
                            if(data !== null && data.success) {

                                location.href = location.href.substring(0, location.href.indexOf('?')) + '?key=' + $('#key').val() + '&step=2';
                                return;

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

                    //$('#loadingImage').show();
                    // location.href = location.href.substring(0, location.href.indexOf('?')) + '?key=' + $('#key').val() + '&step=2';
                    // return false;
                    // break;
                    
                case 2:
                    if($('#fName1').val() === '') {
                        $('#fName1').val($('#prospectName').val());
                    }
                    
                    if($('#lName1').val() === '') {
                        $('#lName1').val($('#prospectLastName').val());
                    }
                    
                    if($('#emailAddress1').val() === '') {
                        $('#emailAddress1').val($('#companyEmail').val());
                    }
                
                    if($('#username1').val() === '') {
                        $('#username1').val($('#companyEmail').val());
                        $("#newCompanyForm").validate().element('#username1');
                    }
                    booAllowNext = true;
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
                        // *** We hardcode the first user to be admin now.
                        //alert("At least one user must be an admin. Please set a user's role to admin.");
                        //return false;
                    }
                    
                    // Check if username is unique
                    var usersCount = $('#users_count').val();
                    var arrUsernames = [];
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

        } else if (booIsPrev) {
            //reset the wizardcontent to hidden
            $('.wizardcontent').hide();

            current_state--;
            current = current_state;
            load_state(current_state);
        }
        
        return false;
    });
});