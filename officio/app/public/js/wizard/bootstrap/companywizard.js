var booSubmissionInProgress = false;
$.metadata.setType("attr", "validate");

function showErrorMsg(msg) {
    $('#modalDialogContent').html(msg);
    $('#modalDialogTitle').text('Error');
    $('#modalDialogTitle').css('color', 'red');
    $('#modalDialog').modal('show');
}

function initCombo(validator) {
    $('select.combo').each(function () {
        $(this).select2();
        $(this).on("change", function (e) {
            validator.element(this);
        });
        $('.select2-container').css("width", "100%");
    });
}

$(document).ready(function () {

    // add * to required field labels
    $('label.required').not('.required_not_mark').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');

    jQuery.validator.addMethod("checkState", function (val, element) {
        return !($('#state-div').css('display') != 'none' && val == '');
    }, "Please enter a valid Province/State.");

    jQuery.validator.addMethod("checkProvince", function (val, element) {
        return !($('#provinces-div').css('display') != 'none' && val == '');
    }, "Please select a Province/State.");


    jQuery.validator.addMethod("pageRequired", function (value, element) {
        return !this.optional(element);
    }, $.validator.messages.required);

    jQuery.validator.addMethod("pageRequiredMinLength", function (value, element, param) {
        return this.getLength(value.trim(), element) >= param;
    }, "Please select at least 1 item");


    jQuery.validator.addMethod("emailUniques", function (email, element) {
        email = email.replace(/\s+/g, "");
        return email.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
    }, "Please enter a valid Email.");


    jQuery.validator.addMethod("postalUniques", function (postal, element) {
        postal = postal.replace(/\s+/g, "");
        return this.optional(element) || postal.match(/^[0-9A-Za-z]+([\s\-]{1}[0-9A-Za-z]+)?$/i);
    }, "Please enter a valid Zip.");

    jQuery.validator.addMethod("phoneUniques", function (phone_number, element) {
        phone_number = phone_number.replace(/\s+/g, "");
        return phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
    }, "Please enter a valid Phone.");

    // validate signup form on keyup and submit
    var validator = $("#newCompanyForm").validate({
        rules: {
            // CC info

            // Company details
            companyName: {
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
                pageRequired: true,
                postalUniques: true
            },

            office: {
                pageRequired: true
            }
        },

        // the errorPlacement has to take the table layout into account
        errorPlacement: function (label, element) {
            label.appendTo(element.closest('.form-group'));
        },

        messages: {},

        // set this class to error-labels to indicate valid fields
        success: function (label) {
        }
    });

    initCombo(validator);

    var addUserInfo = function(user_id) {
        var strRoles = '';
        var arrRolesList = $.evalJSON(arrRoles);
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
                strRoles += '<div class="form-check">' +
                    '<input type="checkbox" '+checkedAndDisabledRole+' class="form-check-input" id="'+strRoleId+'" value="'+arrRolesList[i].role_id+'" name="user_role'+user_id+'[]" '+roleValidate+' />' +
                    '<label for="'+strRoleId+'" class="user_role form-check-label">' +
                    arrRolesList[i].role_name +
                    '</label>' +
                    '</div>';
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

        var newContent = '<fieldset title="User info" id="user_info_' + user_id + '" class="user_info border p-2">' +
            '<legend class="w-auto">Admin info</legend>' +
            '<div class="row">' +
            '<div class="form-group col-12 col-md-6 col-sm-6">' +
            '<label for="fName' + user_id + '" class="required">First Name:</label>' +
            '<input type="text" class="form-control pageRequired" id="fName' + user_id + '" name="fName' + user_id + '" value="' + prospectName + '" />' +
            '</div>' +
            '<div class="form-group col-12 col-md-6 col-sm-6">' +
            '<label for="lName' + user_id + '" class="required">Last Name:</label>' +
            '<input type="text" class="form-control pageRequired" id="lName' + user_id + '" name="lName' + user_id + '" value="' + prospectLastName + '" />' +
            '</div>' +
            '</div>' +
            '<div class="row">' +
            '<div class="form-group col-12 col-md-6 col-sm-6">' +
            '<label for="emailAddress' + user_id + '" class="required">Email:</label>' +
            '<input type="text" class="form-control pageRequired" id="emailAddress' + user_id + '" name="emailAddress' + user_id + '" value="' + prospectEmail + '" />' +
            '</div>' +
            '</div>' +
            '<div class="row">' +
            '<div class="form-group col-12 col-md-6 col-sm-6">' +
            '<label for="username' + user_id + '" class="required">Username:</label>' +
            '<input type="text" class="form-control pageRequired" id="username' + user_id + '" name="username' + user_id + '" value="' + prospectEmail + '" remote="' + baseUrl + '/api/index/check-username" />' +
            '</div>' +
            '<div class="form-group col-12 col-md-6 col-sm-6">' +
            '<label for="password' + user_id + '" class="required">Password:</label>' +
            '<input type="text" class="form-control pageRequired" id="password'+user_id+'" name="password'+user_id+'" minlength="'+passwordMinLength+'" maxlength="'+passwordMaxLength+'" />' +
            '</div>' +
            '</div>' +
            '<div class="form-group" style="display: none;">' +
            '<label class="required">Role :</label>' +
            strRoles +
            '<div style="padding: 10px; border: 1px solid #ccc;">You can define the access level you want to provide to this user.<br/><br/>Admin is the highest role and users who are Admin can have full access.<br/><br/>You can customize access rights for each role once you are in the program.<br/>A user can have multiple roles. If not sure, you can check all boxes.</div>' +
            '</div>' +
            '<div class="form-group" style="display: none;">' +
            '<label class="required">Office :</label>' +
            '<div class="user_office_container">' + strOffices +'</div>' +
            '<div style="padding: 10px; border: 1px solid #ccc;">Clients of which office(s) would you like this user to have access to?<br/>Select all office(s) if you would like this user to have access to all offices.</div>' +
            '</div>' +
            '</fieldset>';

        $('#users_container').append(newContent);

        // add * to required field labels
        $('#user_info_'+user_id+' label.required').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
        $('#user_info_'+user_id+' label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');


        $('#emailAddress'+user_id).change(function() {
            $('#username'+user_id).val($('#emailAddress'+user_id).val());
            validator.form();
        });

    };

    addUserInfo(1);


    $("#nextBtn").click(function (event) {
        if (validator.form()) {
            addCompany();
        } else {
            console.log('pizda');
        }
        return false;
    });

    $('#country').change(function () {
        if ($(this).val() == defaultCountryId) {
            $('#provinces-div').show();
            $('#state-div').hide();
        } else {
            $('#provinces-div').hide();
            $('#state-div').show();
        }

        $("#newCompanyForm").validate().element('#country');
        $('#country_normal').prop('value', $('#country option:selected').text());
    });

    $('#country_normal').prop('value', $('#country option:selected').text());
});

function addCompany() {
    if(booSubmissionInProgress)
        return false;

    booSubmissionInProgress = true;

    $('#loadingImage').show();
    $('#previous').hide();
    $('#next').hide();

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
    var usersCount = 1;
    for(var i=1; i<=usersCount; i++) {
        var arrCheckedRoles = [];
        $("input[name^='user_role"+i+"']:checked").each(function(id, txt) {
            arrCheckedRoles.push($(txt).val());
        });

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
    for(i=1; i<=taCount; i++) {
        var taInfo = {
            name:     $('#ta_name'+i).val(),
            currency: $('#ta_currency'+i).val(),
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
    if($('#country').val() == defaultCountryId && $('#province').length) {
        state = $('#province :selected').text();
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
    $('#nextBtn').hide();

    $.ajax({
        type: "POST",
        url: baseUrl + "/api/index/add-company",
        data:  {
            submitInfo: $.toJSON(submitData)
        },
        success: function(error)
        {
            booSubmissionInProgress = false;
            if(error !== '') {
                $('#loadingImage').hide();
                showErrorMsg(error);
                $('#nextBtn').show();
            }
            else
            {
                // Show all done :)
                $('#loadingImage').hide();
                $('#wizard_content').hide();
                $('#thankYouMsg').show();
            }
        },

        error: function (XMLHttpRequest, textStatus) {
            booSubmissionInProgress = false;
            showErrorMsg(textStatus);
            $('#nextBtn').show();
        }
    });
    return true;
}
