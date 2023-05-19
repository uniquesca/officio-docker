var current;
var validator;
var stepsCount;

$(document).ready(function(){
    // Calculate steps count
    stepsCount = getStepsCount();
    
    // Init all fields, sections
    initAll();

    // all content starts hidden
    $('.wizardcontent').hide();

    // initialize the wizard state
    current = $('#start_step').val();
    load_state(current);
    if (current == '2'){
        $('#mainNav li:first').hide();
    }
    
    // validate signup form on keyup and submit
    validator = $("#newCompanyForm").validate({
        rules: {
            // CC info
            ccType: {
                pageRequired: true
            },
            
            ccName: {
                ccNameUniques: function(){ return $('#ccName').val(); }
            },
            
            ccNumber: {
                ccpageRequiredNumUniques: function(){ return $('#ccType').val(); },
                ccNumUniques: function(){ return $('#ccType').val(); }
            },
            
            ccCVN: {
                ccCVNUniques: function(){ return $('#ccCVN').val(); }
            },

            ccExpMonth: {
                ccExpMonthUniques: function(){ return $('#ccExpMonth').val(); }
            },
            
            ccExpYear: {
                ccExpYearUniques: function(){ return $('#ccExpYear').val(); }
            },
            
        
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
                case 2:
                    // Disable buttons
                    $('#next').attr("disabled","disabled");
                    $('#previous').attr("disabled","disabled");

                    // Fix issue with JQuery 1.5 with json
                    jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

                    // Send Company info
                    $.ajax({
                        url: baseUrl + '/wizard/index/send',
                        dataType : "json",
                        type: 'post',
                        data: {
                            action: 'companyInfo',
                            companyInfo: $.toJSON({
                                companyName:     $('#companyName').val(),
                                company_abn:     $('#company_abn').val(),
                                companyTimeZone: $('#companyTimeZone').val(),
                                companyEmail:    $('#companyEmail').val(),
                                companyAddress:  $('#address').val(),
                                companyCity:     $('#city').val(),
                                companyState:    $('#state').val(),
                                companyCountry:  $("#country option:selected").text(),
                                companyZip:      $('#zip').val(),
                                companyPhone1:   $('#phone1').val(),
                                companyPhone2:   $('#phone2').val(),
                                companyFax:      $('#fax').val()
                            })
                        },
                        success: function (data, textStatus) {
                            if(data.success) {
                                //reset the wizardcontent to hidden
                                $('.wizardcontent').hide();

                                current_state++;
                                current = current_state;
                                load_state(current_state);
                            } else {
                                // Show error message
                                alert(data.message);
                            }
                            
                            return true;
                        }
                    });                    
                    break;
                case 3:
                    // Disable buttons
                    $('#next').attr("disabled","disabled");
                    $('#previous').attr("disabled","disabled");

                    // Fix issue with JQuery 1.5 with json
                    jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

                    // Send CC info
                    $.ajax({
                        url: baseUrl + '/wizard/index/send',
                        dataType : "json",
                        type: 'post',
                        data: {
                            action: 'ccInfo',
                            companyInfo: $.toJSON({
                                companyName:     $('#companyName').val(),
                                company_abn:     $('#company_abn').val(),
                                companyTimeZone: $('#companyTimeZone').val(),
                                companyEmail:    $('#companyEmail').val(),
                                companyAddress:  $('#address').val(),
                                companyCity:     $('#city').val(),
                                companyState:    $('#state').val(),
                                companyCountry:  $("#country option:selected").text(),
                                companyZip:      $('#zip').val(),
                                companyPhone1:   $('#phone1').val(),
                                companyPhone2:   $('#phone2').val(),
                                companyFax:      $('#fax').val()
                            }),
                            ccInfo: $.toJSON({
                                ccType:     $('#ccType').val(),
                                ccName:     $('#ccName').val(),
                                ccNumber:   $('#ccNumber').val(),
                                ccCVN:      $('#ccCVN').length ? $('#ccCVN').val() : '',
                                ccExpMonth: $('#ccExpMonth').val(),
                                ccExpYear:  $('#ccExpYear').val()
                            })
                        },
                        success: function (data, textStatus) {
                            if(data.success) {
                                //reset the wizardcontent to hidden
                                $('.wizardcontent').hide();

                                current_state++;
                                current = current_state;
                                load_state(current_state);
                            } else {
                                // Show error message
                                alert(data.message);
                            }
                            return true;
                        }
                    });                    
                    break;
                case 4: 
                    if($('#offices_count').val() == 1) {
                        // Check all offices checkboxes
                        $("input[name^='user_office']").prop('checked', true);
                    }
                    
                    booAllowNext = true;
                    
                    break;
                case 5:
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

            if (($('#start_step').val() == '2') && (current==1)){
                $('#form_officio').submit();
            }
            load_state(current_state);
        }
        
        return false;
    });
});