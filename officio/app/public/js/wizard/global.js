var booSubmissionInProgress = false;
$.metadata.setType("attr", "validate");


// Show error message :)
function showErrorMessage(msg) {
    $('#errorDescription').html(msg);
    $('#divError').show();
    $('#divWarning').hide();
    $('#loadingImage').hide();
}

//JavaScript function, Equal PHP function in_array()
Array.prototype.has = function (v, i) {
    for (var j = 0; j < this.length; j++) {
        if (this[j] == v) return (!i ? true : j);
    }
    return false;
};

function load_state(current_state){
    //disable all buttons while loading the state
    $('#previous').attr("disabled","disabled");

    //load the content for this state into the wizard content div and fade in
    $('#step_' + current_state).fadeIn("slow");

    //set the wizard class to current state for next iteration
    $('#wizard').attr('class','step_'+ current_state);
    var iterator = 1;
    var iterator2 = 1;
    if(first_step == 3){
        iterator = iterator2 = 3;
    }

    // the state heading h3. removing is no biggie
    //$('#wizard').find('h3').text("Step " + current_state);

    // loop through the list items and set classes for css coloring
    $('#mainNav').find('li').each(function(){
        var step = $(this);
        if (iterator == current_state){ step.attr('class','current'); }
        else if (current_state - iterator == 1){ step.attr('class','lastDone'); }
        else if (current_state - iterator > 1){ step.attr('class','done'); }
        else{ step.attr('class',''); }

        // special case for last step because it doesn't have bacground image
        if (iterator == stepsCount){ step.addClass('mainNavNoBg'); }

        iterator++;
    });

    // loop for new progress bar
    $('#progressbar').find('li').each(function(){
        var step = $(this);
        if (iterator2 == current_state){ step.attr('class','active'); }
        else if (current_state - iterator2 >= 1){ step.attr('class','done'); }
        else{ step.attr('class',''); }

        iterator2++;
    });
    
    // depending on the state, enable the correct buttonss
    switch(parseInt(current_state, 10)){
        case first_step:
            $('#next').removeAttr("disabled");
            $('#next>span').text('Next');
            $('#next').removeClass("orange-btn");
            break;
        case stepsCount:
            $('#previous').removeAttr("disabled");
            $('#next>span').text('Submit');
            $('#next').addClass("orange-btn");
            break;
        default:
            $('#previous').removeAttr("disabled");
            $('#next').removeAttr("disabled");
            $('#next>span').text('Next');
            $('#next').removeClass("orange-btn");
            break;
    }
    
    return true;
}

function getStepsCount() {
    stepsCount = 1;
    $('.wizardcontent').each(function(id, txt) {
        var thisId = $(txt).attr("id");
        var exploded = thisId.split('step_');
        if(parseInt(exploded[1], 10) > stepsCount) {
            stepsCount = parseInt(exploded[1], 10);
        }
    });
    
    return stepsCount;
}

function displayRemoveUserBtn(){
    var countUsers = $('#users_count').val();
    var btns = $('.remove_user_btn');
    btns.hide();
    btns.eq(parseInt(countUsers, 10) - 2).show();
}

function addCompany() {

    var arrPackages = [];
    $("input[name^='arrPackages']:checked").each(function(id, chk) {
        arrPackages.push($(chk).val());
    });

    var arrOffices = [];
    $("input[name^='office']").each(function(id, txt) {
        var officeInfo = {
            officeId: id+1,
            officeName: $(txt).val()
        };
    
        arrOffices.push(officeInfo);
    });

    var arrUsers = [];
    var usersCount = $('#users_count').val();
    for(var i=1; i<=usersCount; i++) {
        var arrCheckedRoles = [];

        // Hardcode first user as Admin
        if(i === 1){
            var arrRolesList = JSON.parse(arrRoles);
            if(arrRolesList.length > 0) {
                for (var j = 0; j < arrRolesList.length; j++) {
                    arrCheckedRoles.push(arrRolesList[j].role_id);
                }
            }
        } else {
            $("input[name^='user_role" + i + "']:checked").each(function (id, txt) {
                arrCheckedRoles.push($(txt).val());
            });
        }
        
        var arrUserOffices = [];
        $("input[name^='user_office"+i+"']:checked").each(function(id, txt) {
            arrUserOffices.push($(txt).val());
        });
        
        
    
        var userInfo = {
            arrRoles: arrCheckedRoles,
            arrUserOffices: arrUserOffices,
            fName: $('#fName'+i).val(),
            lName: $('#lName'+i).val(),
            emailAddress: $('#emailAddress'+i).val(),
            username: $('#username'+i).val(),
            password: $('#password'+i).val()
        };

        arrUsers.push(userInfo);
    }


    var arrTa = [];
    var taCount = $('#ta_count').val();
    for(j=1; j<=taCount; j++) {
        var taInfo = {
            name:     $('#ta_name'+j).val(),
            currency: $('#ta_currency'+j).val(),
            balance:  0
        };

        arrTa.push(taInfo);
    }
    
    var arrCMI = {};
    if($('#cmi_id').length && $('#reg_id').length) {
        arrCMI = {
            cmi_id: $('#cmi_id').val(),
            reg_id: $('#reg_id').val()
        };
    }

    var arrFreeTrial = {};
    if($('#freetrial_key').length) {
        arrFreeTrial = {
            freetrial_key: $('#freetrial_key').val()
        };
    }

    // For Cananda we'll use option from the Provinces list
    var state = $('#state').val();
    if($('#country').val() == defaultCountryId && $('#stateCombo').length) {
        state = $('#stateCombo :selected').text();
    }

    var submitData = {
        prospectId: $('#prospectId').length ? $('#prospectId').val() : 0,
        companyName: $('#companyName').val(),
        company_abn: $('#company_abn').length ? $('#company_abn').val() : '',
        address: $('#address').val(),
        city: $('#city').val(),
        state: state,
        country: $('#country').val(),
        zip: $('#zip').val(),
        phone1: $('#phone1').val(),
        phone2: $('#phone2').val(),
        companyEmail: $('#companyEmail').val(),
        fax: $('#fax').val(),
        companyTimeZone: $('#companyTimeZone').val(),
        arrPackages: arrPackages,
        arrOffices: arrOffices,
        arrUsers: arrUsers,
        arrTa: arrTa,
        arrCMI: arrCMI,
        arrFreeTrial: arrFreeTrial
    };

    if(booSubmissionInProgress)
        return false;

    booSubmissionInProgress = true;
    $('#next').attr("disabled","disabled");
    $('#previous').attr("disabled","disabled");

    $('#loadingImage').show();
    $('#divError').hide();
    $('#divWarning').show();

    //return;

    $.ajax({
        type: "POST",
        url: baseUrl + "/api/index/add-company",
        data:  {
            submitInfo: $.toJSON(submitData)
        },
        success: function(error)
        {
           booSubmissionInProgress = false;
           $('#next').removeAttr("disabled");
           $('#previous').removeAttr("disabled");
           if(error !== '') {
                showErrorMessage(error);
           }
           else
           {
                // Show all done :)
                $('#loadingImage').hide();
                $('#divWarning').hide();
                $('#wizardcontentwrap').hide();
                $('#thankYouMsg').show();
           }
        },
        
        error: function (XMLHttpRequest, textStatus) {
            booSubmissionInProgress = false;
            $('#next').removeAttr("disabled");
            $('#previous').removeAttr("disabled");
            showErrorMessage(textStatus);
        }
    });
    return true;
}

var checkFieldIsOnPage = function(element) {
    if($(element).css('display')=='none') {
        return false;
    }
    
    function match(index) {
        return current == index && $(element).parents("#step_" + (index)).length;
    }
    
    for(var i=1; i<=stepsCount; i++) {
        if(match(i)) {
            return true;
        }
    }
    return false;
};


var initAll = function() {


    jQuery.validator.addMethod("pageRequired", function(value, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            return !this.optional(element);
        }, $.validator.messages.required);
    
    jQuery.validator.addMethod("pageRequiredMinLength", function(value, element, param) {
        if(!checkFieldIsOnPage(element)) {
            return "dependency-mismatch";
        }

        return this.getLength(value.trim(), element) >= param;
    }, "Please select at least 1 item");

    jQuery.validator.addMethod("passwordUniques", function(value, element) {

        if(!checkFieldIsOnPage(element)) {
            return "dependency-mismatch";
        }

        if(passwordHighSecurity){

            var str = value.trim();
            return str.match(/(?=.*[0-9])(?=.*[a-z])(?=.*[A-Z])/);

        } else {
            return true;
        }

    }, "Password must contain numbers and mix case characters.");

    jQuery.validator.addMethod("companyUniques", function(company, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            company = company.replace(/\s+/g, "");
            return true;
        }, "Please enter a valid Name.");

    jQuery.validator.addMethod("emailUniques", function(email, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            email = email.replace(/\s+/g, "");
            return email.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
        }, "Please enter a valid Email.");
        

    jQuery.validator.addMethod("postalUniques", function(postal, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            postal = postal.replace(/\s+/g, "");
            return this.optional(element) || postal.match(/^[0-9A-Za-z]+([\s\-]{1}[0-9A-Za-z]+)?$/i);
        }, "Please enter a valid Zip.");

    jQuery.validator.addMethod("phoneUniques", function(phone_number, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            phone_number = phone_number.replace(/\s+/g, "");
            return phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
        }, "Please enter a valid Phone.");

    jQuery.validator.addMethod("ccExpMonthUniques", function(exp_month, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }
            if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888' || $("#ccExpMonth option:selected").text() != 'Month'){
              return true;
            }
            else{
              return false;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccExpYearUniques", function(exp_year, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }
            if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888' || $("#ccExpYear option:selected").text() != 'Year'){
              return true;
            }
            else{
              return false;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccCVNUniques", function(value, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }
            if ($('#ccNumber').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccNameUniques", function(value, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }
            if ($('#ccNumber').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccpageRequiredNumUniques", function(value, element) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }
            if ($('#ccName').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }

        }, "This field is required.");

    jQuery.validator.addMethod("ccNumUniques", function(value, element, param) {
            if(!checkFieldIsOnPage(element)) {
                return "dependency-mismatch";
            }

            if(value==='8888' || $('#ccName').val()==='8888' ){
              return true;
            }

            var cardName = param;

            var cards = [];
            cards [0] = {cardName: "Visa", lengths: "13,16", prefixes: "4", checkdigit: true};
            cards [1] = {cardName: "MasterCard", lengths: "16", prefixes: "51,52,53,54,55", checkdigit: true};
            cards [2] = {cardName: "DinersClub", lengths: "14,16", prefixes: "300,301,302,303,304,305,36,38,55", checkdigit: true};
            cards [3] = {cardName: "CarteBlanche", lengths: "14", prefixes: "300,301,302,303,304,305,36,38", checkdigit: true};
            cards [4] = {cardName: "AmEx", lengths: "15", prefixes: "34,37", checkdigit: true};
            cards [5] = {cardName: "Discover", lengths: "16", prefixes: "6011,650", checkdigit: true};
            cards [6] = {cardName: "JCB", lengths: "15,16", prefixes: "3,1800,2131", checkdigit: true};
            cards [7] = {cardName: "enRoute", lengths: "15", prefixes: "2014,2149", checkdigit: true};
            cards [8] = {cardName: "Solo", lengths: "16,18,19", prefixes: "6334, 6767", checkdigit: true};
            cards [9] = {cardName: "Switch", lengths: "16,18,19", prefixes: "4903,4905,4911,4936,564182,633110,6333,6759", checkdigit: true};
            cards [10] = {cardName: "Maestro", lengths: "16,18", prefixes: "5020,6", checkdigit: true};
            cards [11] = {cardName: "VisaElectron", lengths: "16", prefixes: "417500,4917,4913", checkdigit: true};

            var cardType = -1;
            for (var i=0; i<cards.length; i++) {
                    if (cardName.toLowerCase() == cards[i].cardName.toLowerCase()) {
                            cardType = i;
                            break;
                    }
            }
            if (cardType == -1) { return false; } // card type not found

            value = value.replace (/[\s-]/g, ""); // remove spaces and dashes
            if (value.length === 0) { return false; } // no length

            var cardNo = value;
            var cardexp = /^[0-9]{13,19}$/;
            if (!cardexp.exec(cardNo)) { return false; } // has chars or wrong length

            cardNo = cardNo.replace(/\D/g, ""); // strip down to digits

            if (cards[cardType].checkdigit){
                    var checksum = 0;
                    var j = 1;

                    var calc;
                    for (i = cardNo.length - 1; i >= 0; i--) {
                            calc = Number(cardNo.charAt(i)) * j;
                            if (calc > 9) {
                                    checksum = checksum + 1;
                                    calc = calc - 10;
                            }
                            checksum = checksum + calc;
                            if (j ==1) {j = 2;} else {j = 1;}
                    }

                    if (checksum % 10 !== 0) { return false; } // not mod10
            }

            var lengthValid = false;
            var prefixValid = false;

            var prefix = cards[cardType].prefixes.split(",");
            for (i=0; i<prefix.length; i++) {
                    var exp = new RegExp ("^" + prefix[i]);
                    if (exp.test (cardNo)) prefixValid = true;
            }
            if (!prefixValid) { return false; } // invalid prefix

            var lengths = cards[cardType].lengths.split(",");
            for (j=0; j<lengths.length; j++) {
                    if (cardNo.length == lengths[j]) lengthValid = true;
            }
            if (!lengthValid) { return false; } // wrong length

            return true;
        }, "Please enter a valid Credit Card Number.");
    
    jQuery.validator.messages.remote = "Please choose another username.";
 
 
    // add * to required field labels
    $('label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

     // Set default Time Zone
    $('#companyTimeZone').val(defaultTimeZone);

    // Set default Country
    $('#country').val(defaultCountryId);
    
    var toggleStatesCombo = function(selVal) {
        // In some cases provinces list is absent
        if($('#stateCombo').length) {
            if(selVal == defaultCountryId) {
                $('#stateCombo').show();
                $('#state').hide();
            } else {
                $('#stateCombo').hide();
                $('#state').show();
            }
        }
    };
    
    $('#country').change(function() {
        // Check if Canada is selected
        // Then show provinces combo
        // Otherwise show states edit box
        toggleStatesCombo($(this).val());
    });
    toggleStatesCombo($('#country').val());
    
    


    // Generate offices number options
    var maxOfficeCount = 10;
    for(var i=1; i<=maxOfficeCount; i++) {
        $('#offices_count').append($('<option></option>').val(i).html(i));
    }

    // Create office field if it doesn't exists
    var addOffice = function(i) {
        var checkId = 'office_' + i;
        if($('#' + checkId).length === 0) {
            var strComment = i == 1 ? '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;e.g. Toronto, Dubai, Manila, etc...' : '';
            $('#offices_container').append('<div id="'+checkId+'" style="display: none;"><label class="required">Office ' + i + ':</label><input id="txt_'+checkId+'" name="office'+i+'[]" class="office pageRequired" type="text">'+strComment+'</div>');

            // add * to required field labels
            $('#'+checkId+' label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
            $('#'+checkId+' label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

            // Show new field with effects
            $('#'+checkId).slideDown("normal");
            
            // Add to user's section
            $('.user_office_container').each(function(id, div) {
                var lblId = i;
                var arrFieldset = $(div).parents("fieldset[id^='user_info_']" );
                
                if(arrFieldset.length > 0) {
                    var fieldsetId = $(arrFieldset[0]).attr("id");
                    var exploded = fieldsetId.split('user_info_');
                    var user_id = parseInt(exploded[1],10);
                    
                    var strUserOfficeId = 'user_office_' + user_id + "_" + lblId;
                    $(div).append(  
                        '<label for="'+strUserOfficeId+'" class="user_office label_user_office'+lblId+'" >' + 
                        '<input type="checkbox" class="checkbox" id="'+strUserOfficeId+'" value="'+lblId+'" name="user_office'+user_id+'[]" />' + 
                        '<span></span>' +
                        '</label>'
                    );
                }
            });
            
            
            // Listen for changes
            $('#txt_'+checkId).change(function() {
                var newOfficeName = $(this).val();
                $(".label_user_office" + i + ' span').each(function(id, txt) {
                    $(this).html(newOfficeName).show();
                });
                
                $(".label_user_office" + i + ' > input').each(function(id, txt) {
                    $(this).prop('checked', true);
                });
            });
        }
    };
    
    if($('#offices_container').length) {
        addOffice(1);
    }

    // Listen changes in 'offices number' combo
    $('#offices_count').change(function() {
        var count = $(this).val();

        for(var i=1; i<=count; i++) {
            addOffice(i);
        }

        // Delete previously created
        $(".office").each(function(id, txt) {
            var parent_id = $(txt).parent().attr("id");
            var exploded = parent_id.split('office_');
            var officeId = parseInt(exploded[1],10);
            if(officeId > count) {
                // Remove checking mechanism for this field
                $(txt).rules("remove");

                // Remove field (with parent div)
                $('#' + parent_id).slideUp("normal", function() { $(this).remove(); } );
                
                $('.label_user_office' + officeId).remove();
            }
        });
    });


    // Generate users number options
    if (typeof maxUsersCount === 'undefined') {
        maxUsersCount = 5;
    }

    if(maxUsersCount <= 1){
        $('#additional_users_options').hide();
    }

    var addUserInfo = function(user_id) {
        var strRoles = '';
        var arrRolesList = JSON.parse(arrRoles);
        if(arrRolesList.length > 0) {
            var isShowedValidate = false;
            for(var i=0; i<arrRolesList.length; i++) {
                var checkedAndDisabledRole = 'checked="checked"';
                var roleValidate = '';
                var strRoleId = 'role_' + user_id + '_' + i;
                
                if(!isShowedValidate) {
                    roleValidate = 'validate="pageRequiredMinLength:1"';
                    isShowedValidate = true;
                }
                strRoles += '<label for="'+strRoleId+'" class="user_role">' + 
                                '<input type="checkbox" '+checkedAndDisabledRole+' class="checkbox" id="'+strRoleId+'" value="'+arrRolesList[i].role_id+'" name="user_role'+user_id+'[]" '+roleValidate+' />' + 
                                arrRolesList[i].role_name + 
                            '</label>';
            }
        }
        
        isShowedValidate = false;
        var strOffices = '';
        
        var arrOffices = $("input[name^='office']");
        var booFirstChecked = (arrOffices.length === 1 && $(arrOffices[0]).val() !== '');
        arrOffices.each(function(id, txt) {
            var lblId = (parseInt(id,10) + 1);
            var strUserOfficeId = 'user_office_' + user_id + '_' + lblId;
            if(!isShowedValidate) {
                officeValidate = 'validate="pageRequiredMinLength:1"';
                isShowedValidate = true;
            }
            
            strOffices +=   '<label for="'+strUserOfficeId+'" class="user_office label_user_office'+lblId+'" >' + 
                                '<input type="checkbox" checked="checked" class="checkbox" id="'+strUserOfficeId+'" value="'+lblId+'" name="user_office'+user_id+'[]" '+officeValidate + ' />' + 
                                '<span>' + $(txt).val() + '</span>' +
                            '</label>';
        });

        if(strOffices === '') {
            strOffices = '<label>Main</label>';
        }
        
        var newContent = '<fieldset title="User info" id="user_info_'+user_id+'" class="user_info" style="display: none;">' +
                                '<legend>'+(user_id === 1 ? 'Company Admin user' : 'User '+user_id+' info')+'</legend>' +

                                '<table class="add-user-table" border="0" cellspacing="1" cellpadding="4" align="center">' +
                                    '<tr>' +
                                        '<td align="right" class="field_name"><label for="fName'+user_id+'" class="required">First Name :</label></td>' +
                                        '<td align="left"><input id="fName'+user_id+'" name="fName'+user_id+'" type="text" size="50" class="form-control pageRequired" /></td>' +
                                    '</tr>' +
                                    '<tr>' +
                                        '<td align="right" class="field_name"><label for="lName'+user_id+'" class="required">Last Name :</label></td>' +
                                        '<td align="left"><input id="lName'+user_id+'" name="lName'+user_id+'" type="text" size="50" class="form-control pageRequired" /></td>' +
                                    '</tr>' +
                                    '<tr>' +
                                        '<td align="right" class="field_name"><label for="emailAddress'+user_id+'" class="required">Email :</label></td>' +
                                        '<td align="left"><input id="emailAddress'+user_id+'" name="emailAddress'+user_id+'" type="text" size="50" class="form-control pageRequired emailUniques" /></td>' +
                                    '</tr>' +
                                    '<tr>' +
                                        '<td align="right" class="field_name"><label for="username'+user_id+'" class="required">Username :</label></td>' +
                                        '<td align="left"><input id="username'+user_id+'" name="username'+user_id+'" type="text" size="50" class="form-control pageRequired" remote="'+baseUrl+'/api/index/check-username" /></td>' +
                                    '</tr>' +
                                    '<tr>' +
                                        '<td align="right" class="field_name"><label for="password'+user_id+'" class="required">Password :</label></td>' +
                                        '<td align="left"><input id="password'+user_id+'" name="password'+user_id+'" type="text" size="50" class="form-control pageRequired passwordUniques" minlength="'+passwordMinLength+'" maxlength="'+passwordMaxLength+'" /></td>' +
                                    '</tr>' +
                                    
                                    '<tr>' +
                                        '<td width="120" align="right" class="field_name" style="vertical-align: top;padding-top: 15px;"><label class="required">Role :</label></td>';

        if(user_id === 1){
                      newContent += '<td align="left" style="padding-top: 15px;"><label>Admin</label></td>';
        } else {
                      newContent +=  ('<td align="left">'+
                                            '<table border="0" cellspacing="0" cellpadding="0"><tr>' +
                                                '<td style="width: 310px; padding-top: 5px;;">'+strRoles+'</td>' +
                                                '<td style=""><div style="padding: 10px; border: 1px solid #ccc;">Please choose the role that best reflects the function of this user in your business.<br><br>Don\'t worry you can always change roles once you are in the program.<br><br>But, remember, <span style="color: red; font-weight: 500;">Admin</span> is the <span style="color: red; font-weight: 500;">highest role</span> and the users who are Admin can have full access.</div></td>' +
                                            '</tr></table><button type="button" class="btn btn-secondary remove_user_btn" style="margin-top: 30px;">Remove User</button>' +
                                        '</td>');
        }
                      newContent += '</tr>' +
                                    '<tr hidden="true">' +
                                        '<td width="120" align="right" class="field_name"><label class="required">Office :</label></td>' +
                                        '<td align="left">'+
                                        
                                            '<table border="0" cellspacing="0" cellpadding="0"><tr>' +
                                                '<td style="width: 200px; padding: 0;"><div class="user_office_container">' + strOffices +'</div></td>' +
                                                '<td style="width: 450px;"><div style="padding: 10px; border: 1px solid #ccc;">Clients of which office(s) would you like this user to have access to?<br/>Select all office(s) if you would like this user to have access to all offices.</div></td>' +
                                            '</tr></table>' + 
                                        
                                        '</td>' +
                                    '</tr>' +
                                    
                                '</table>' +
                            '</fieldset>';

        $('#users_container').append(newContent);
        $('#user_info_'+user_id).slideDown("normal");

        // add * to required field labels
        $('#user_info_'+user_id+' label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
        $('#user_info_'+user_id+' label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');


        $('#emailAddress'+user_id).change(function() {
            $('#username'+user_id).val($('#emailAddress'+user_id).val());
            validator.form();
        });

        displayRemoveUserBtn();
    };

    addUserInfo(1);

    // Listen changes in 'users number' combo
    $('#add_user_btn').click(function() {
        var countUsers = $('#users_count').val();

        if(countUsers >= maxUsersCount){
            return;
        }

        $('#users_count').val(++countUsers);

        if(countUsers == maxUsersCount){
            $('#add_user_btn').hide();
            $('#add_user_btn_help').show();
        }

        for(var i=1; i<=countUsers; i++) {
            var checkId = 'user_info_' + i;
            if($('#' + checkId).length === 0) {
                // create
                addUserInfo(i);
            }
        }

    });

    $('.remove_user_btn').live('click', function() {
        var countUsers = $('#users_count').val();

        if(countUsers <= 1){
            return;
        }

        $('#users_count').val(--countUsers);

        if(countUsers < maxUsersCount){
            $('#add_user_btn').show();
            $('#add_user_btn_help').hide();
        }

        displayRemoveUserBtn();

        // Delete previously created
        $(".user_info").each(function(id, txt) {
            thisId = $(txt).attr("id");

            var exploded = thisId.split('user_info_');
            if(parseInt(exploded[1],10) > countUsers) {
                // Remove checking mechanism for this field
                $(txt).rules("remove");

                // Remove field (with parent div)
                $('#' + thisId).slideUp("normal", function() {
                    $(this).remove();
                } );
            }
        });

    });

    // Generate users number options
    var maxTACount = 1; // 5
    for(i=2; i<=maxTACount; i++) {
        $('#ta_count').append($('<option></option>').val(i).html(i));
    }
    
    
    var addNewTA = function(countTA) {
        for(var i=1; i<=countTA; i++) {
            var checkId = 'ta_info' + i;
            if($('#' + checkId).length === 0) {
                // create
                var newContent = '<fieldset title="TA info" id="ta_info'+i+'" class="ta_info" style="display: none;">' +
                                        '<legend>Client/Trust Account</legend>' +

                                        '<table border="0" cellspacing="1" cellpadding="4" align="center">' +
                                            '<tr>' +
                                                '<td width="25%" align="right" class="field_name" style="vertical-align: top;padding-top: 15px;"><label for="ta_name'+i+'" class="required">Bank Name :</label></td>' +
                                                '<td align="left">' +
                                                    '<input id="ta_name'+i+'" name="ta_name'+i+'" type="text" class="form-control pageRequired" size="45" />' +
                                                    '<div style="padding: 5px 0;"><p style="font-size: smaller; margin-bottom: 0;">This refers to the bank name that is holding your Client/Trust Account<br>e.g. ' + (booAustralia ? 'NAB, CBA, Westpac, ANZ.' : 'RBC, BMO, TD, Scotia, HSBC.') + '</p><p style="color: red; font-size: smaller;">Do NOT include the account number.<br>If you don\'t want to add a Client/Trust Account, simply enter NA in the field.</p></div>' +
                                                '</td>' +
                                            '</tr>' +
                                            '<tr>' +
                                                '<td align="right" class="field_name"><label for="ta_currency'+i+'" class="required">Currency :</label></td>' +
                                                '<td align="left">' +
                                                     '<select id="ta_currency'+i+'" name="ta_currency'+i+'" style="width: 110px;" class="form-control pageRequired"></select>' +
                                                '</td>' +
                                            '</tr>' +
                                        '</table>';
                $('#ta_container').append(newContent);

                var arrCurrencies = ['cad','usd','aed','afn','all','amd','ang','aoa','ars','aud','awg','azn','bam','bbd','bdt','bgn','bhd','bif','bmd','bnd','bob','brl','bsd','btn','bwp','byr','bzd','cdf','chf','clp','cny','cop','crc','cup','cve','cyp','czk','djf','dkk','dop','dzd','eek','egp','ern','etb','eur','fjd','fkp','gbp','gel','ggp','ghs','gip','gmd','gnf','gtq','gyd','hkd','hnl','hrk','htg','huf','idr','ils','imp','inr','iqd','irr','isk','jep','jmd','jod','jpy','kes','kgs','khr','kmf','kpw','krw','kwd','kyd','kzt','lak','lbp','lkr','lrd','lsl','ltl','lvl','lyd','mad','mdl','mga','mkd','mmk','mnt','mop','mro','mtl','mur','mvr','mw','mxn','myr','mzn','nad','ngn','nio','nok','npr','nzd','omr','pab','pen','pgk','php','pkr','pln','pyg','qar','ron','rsd','rub','rwf','sar','sbd','scr','sdg','sek','sgd','shp','sll','sos','spl','srd','std','svc','syp','szl','thb','tjs','tmm','tnd','top','try','ttd','tvd','twd','tzs','uah','ugx','uyu','uzs','veb','vef','vnd','vuv','wst','xaf','xag','xau','xcd','xdr','xof','xpd','xpf','xpt','yer','zar','zmk','zwd'];

                for(var j=0; j<arrCurrencies.length; j++) {
                    var currencyLabel =  (arrCurrencies[j] == 'aud' || arrCurrencies[j] == 'cad' || arrCurrencies[j] == 'usd') ? '$ ' : '';
                    currencyLabel += arrCurrencies[j].toUpperCase();
                    $('#ta_currency' + i).append($('<option></option>').val(arrCurrencies[j]).html(currencyLabel));
                }

                if (arrCurrencies.has(site_currency)) {
                    $('#ta_currency' + i).val(site_currency);
                }

                // add * to required field labels
                $('#ta_info'+i+' label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
                $('#ta_info'+i+' label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

                $('#'+checkId).slideDown("normal");
            }
        }

        // Delete previously created
        $(".ta_info").each(function(id, txt) {
            var thisId = $(txt).attr("id");
            var exploded = thisId.split('ta_info');
            if(parseInt(exploded[1],10) > countTA) {
                // Remove checking mechanism for this field
                $(txt).rules("remove");

                // Remove field (with parent div)
                $('#' + thisId).slideUp("normal", function() { $(this).remove(); } );
            }
        });
    };
    
    addNewTA(1);
    $('#ta_count').val('1');

    // Listen changes in 'offices number' combo
    $('#ta_count').change(function() {
        var countTA = $(this).val();
        addNewTA(countTA);
    });
    
    $('#ccType').change(function(){
        $("#newCompanyForm").validate().element('#ccNumber');
    });
    
    $('#ccExpMonth').change(function(){
        $("#newCompanyForm").validate().element('#ccExpMonth');
    });

    $('#ccExpYear').change(function(){
        $("#newCompanyForm").validate().element('#ccExpYear');
    });

    $('#divError').hide();
    $('#divWarning').hide();
};