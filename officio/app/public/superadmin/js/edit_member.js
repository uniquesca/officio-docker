Ext.onReady(function() {
    Ext.QuickTips.init();
    $('#manage-members-content').css('min-height', getSuperadminPanelHeight() + 'px');

    setTimeout(function(){
        updateSuperadminIFrameHeight('#manage-members-content');
    }, 200);

    if (booCanChangePassword) {
        new GeneratePasswordButton({
            renderTo: 'generatePassword',
            passwordField: 'password'
        });
    }

    var emailAddressField = $('#emailAddress');
    var usernameField = $('#username');
    if (empty(edit_member_id)) {
        emailAddressField.blur(function() {
            if (!empty(emailAddressField.val()) && empty(usernameField.val())) {
                $.ajax({
                    type: "POST",
                    data: { field: "email"},
                    url: baseUrl + '/manage-members/check-is-user-exists?username=' + emailAddressField.val(),
                    success: function(result){
                        var resultData = Ext.decode(result);
                        if (empty(resultData.strError) && !empty(resultData.username)) {
                            usernameField.val(resultData.username);
                        }
                    }
                });
            }
        });
    }

    usernameField.change(function() {
        if ($("#username").valid() && !empty(usernameField.val())) {
            $.ajax({
                type: "POST",
                data: { field: "username"},
                url: baseUrl + '/manage-members/check-is-user-exists?username=' + usernameField.val(),
                success: function(result){
                    var resultData = Ext.decode(result);
                    if (!empty(resultData.strError) ) {
                        Ext.simpleConfirmation.error(_(resultData.strError));
                    }
                }
            });
        }
    });

    // When we'll change the IDIR -> automatically reset the GUID.
    // Show different messages if that's true
    var idirField = $('#oauth_idir');
    var savedIDIR = idirField.val();
    var guidField = $('#oauth_guid');
    var savedGUID = guidField.html();
    if (empty(savedGUID)) {
        guidField.html('it will be set on the first successfull login');
    }

    idirField.change(function () {
        var updatedGUID = savedGUID;
        if (idirField.val() != savedIDIR || empty(savedGUID)) {
            updatedGUID = empty(savedGUID) ? 'it will be set on the first successfull login' : 'it will reset on save';
        }

        guidField.html(updatedGUID);
    });

    var divisionsAccessTo;
    if (arrDivisions.length) {
        // Check/uncheck a related "select all" checkbox on combo's options selection change
        // "Select all" will be checked if all options are selected in the combo, otherwise will be unchecked
        function toggleSelectAllCheckbox(combo, record) {
            var arrSelectedOptions = [];
            var strSelected = combo.getValue();
            if (!empty(strSelected)) {
                arrSelectedOptions = strSelected.split(combo.separator);
            }

            var checkboxId = '';
            switch (combo.id) {
                case 'divisions_access_to_lovcombo_id':
                    checkboxId = 'userDivisionsAccessToCheckbox';
                    break;

                case 'divisions_pull_from_combo':
                    checkboxId = 'userDivisionsPullFromCheckbox';
                    break;

                case 'divisions_push_to_combo':
                    checkboxId = 'userDivisionsPushToCheckbox';
                    break;

                case 'personal_offices_combo':
                    checkboxId = 'userDivisionsResponsibleForCheckbox';
                    break;

                default:
                    break;
            }

            if (!empty(checkboxId)) {
                var checkbox = $('#' + checkboxId);
                if (checkbox) {
                    if (arrSelectedOptions.length === combo.getStore().getCount()) {
                        checkbox.prop('checked', true);
                    } else {
                        checkbox.prop('checked', false);
                    }
                }
            }
        }

        divisionsAccessTo = new Ext.ux.form.LovCombo({
            id: 'divisions_access_to_lovcombo_id',
            store: new Ext.data.Store({
                data: arrDivisions,
                reader: new Ext.data.JsonReader({id: 'division_id'}, Ext.data.Record.create([
                    {name: 'division_id'},
                    {name: 'name'}
                ]))
            }),
            mode:          'local',
            displayField:  'name',
            valueField:    'division_id',
            name:          'divisions_access_to',
            hiddenName:    'divisions_access_to',
            xtype:         'lovcombo',
            width:         280,
            triggerAction: 'all',
            useSelectAll:  false,
            allowBlank:    false,
            renderTo:      'userDivisionsAccessTo',
            value:         arrMemberDivisionsAccessTo.join(','),

            listeners: {
                select: toggleSelectAllCheckbox,
                render: toggleSelectAllCheckbox
            }
        });

        // Automatically select Office during user creation if offices count is 1
        if (empty(edit_member_id) && arrDivisions.length === 1 && arrMemberDivisionsAccessTo.length === 0) {
            divisionsAccessTo.setValue(arrDivisions[0]['division_id']);
        }

        var divisionsResponsibleFor = new Ext.ux.form.LovCombo({
            id: 'personal_offices_combo',
            store: new Ext.data.Store({
                data: arrDivisions,
                reader: new Ext.data.JsonReader({id: 'division_id'}, Ext.data.Record.create([
                    {name: 'division_id'},
                    {name: 'name'}
                ]))
            }),
            mode:          'local',
            displayField:  'name',
            valueField:    'division_id',
            name:          'divisions_responsible_for',
            hiddenName:    'divisions_responsible_for',
            xtype:         'lovcombo',
            width:         280,
            triggerAction: 'all',
            useSelectAll:  false,
            renderTo:      'userDivisionsResponsibleFor',
            value:         arrMemberDivisionsResponsibleFor.join(','),

            listeners: {
                select: toggleSelectAllCheckbox,
                render: toggleSelectAllCheckbox
            }
        });

        var divisionsPullFrom = new Ext.ux.form.LovCombo({
            id: 'divisions_pull_from_combo',
            store: new Ext.data.Store({
                data: arrDivisions,
                reader: new Ext.data.JsonReader({id: 'division_id'}, Ext.data.Record.create([
                    {name: 'division_id'},
                    {name: 'name'}
                ]))
            }),
            mode:          'local',
            displayField:  'name',
            valueField:    'division_id',
            name:          'divisions_pull_from',
            hiddenName:    'divisions_pull_from',
            xtype:         'lovcombo',
            width:         280,
            triggerAction: 'all',
            useSelectAll:  false,
            renderTo:      'userDivisionsPullFrom',
            value:         arrMemberDivisionsPullFrom.join(','),

            listeners: {
                select: toggleSelectAllCheckbox,
                render: toggleSelectAllCheckbox
            }
        });

        var divisionsPushTo = new Ext.ux.form.LovCombo({
            id: 'divisions_push_to_combo',
            store: new Ext.data.Store({
                data: arrDivisions,
                reader: new Ext.data.JsonReader({id: 'division_id'}, Ext.data.Record.create([
                    {name: 'division_id'},
                    {name: 'name'}
                ]))
            }),
            mode:          'local',
            displayField:  'name',
            valueField:    'division_id',
            name:          'divisions_push_to',
            hiddenName:    'divisions_push_to',
            xtype:         'lovcombo',
            width:         280,
            triggerAction: 'all',
            useSelectAll:  false,
            renderTo:      'userDivisionsPushTo',
            value:         arrMemberDivisionsPushTo.join(','),

            listeners: {
                select: toggleSelectAllCheckbox,
                render: toggleSelectAllCheckbox
            }
        });

        this.divisionsAccessTo       = divisionsAccessTo;
        this.divisionsResponsibleFor = divisionsResponsibleFor;
        this.divisionsPullFrom       = divisionsPullFrom;
        this.divisionsPushTo         = divisionsPushTo;
    }

    if (site_version == 'australia') {
        var vevoLoginField = $("#vevo_login");

        var arrUpdatedUsersList = [];

        Ext.each(arrActiveUsers, function(oData){
            if (parseInt(oData['status']) == 1 || arrVevoMemberIds.indexOf(oData['option_id']) != -1) {
                arrUpdatedUsersList.push({
                    option_id: oData['option_id'],
                    option_name: oData['option_name']
                });
            }
        });

        var vevoUsers = new Ext.ux.form.LovCombo({
            store: new Ext.data.Store({
                data: arrUpdatedUsersList,
                reader: new Ext.data.JsonReader({id: 'option_id'}, Ext.data.Record.create([
                    {name: 'option_id'},
                    {name: 'option_name'}
                ]))
            }),
            mode:          'local',
            displayField:  'option_name',
            valueField:    'option_id',
            name:          'vevo_members',
            hiddenName:    'vevo_members',
            xtype:         'lovcombo',
            width:         280,
            triggerAction: 'all',
            useSelectAll:  false,
            renderTo:      'vevoUsers',
            disabled: empty(vevoLoginField.val()),
            value: !empty(vevoLoginField.val()) ? arrVevoMemberIds.join(',') : ''
        });

        vevoLoginField.keyup(function() {
            var booDisabled = false;
            if (empty($(this).val())) {
                booDisabled = true;
                vevoUsers.setValue();
            }
            vevoUsers.setDisabled(booDisabled);
        });

        $(".show_vevo_password:checkbox").click(function() {
            var $input = $("#vevo_password");
            var change = $(this).is(":checked") ? "text" : "password";
            var rep = $("<input type='" + change + "' />")
                .attr("id", $input.attr("id"))
                .attr("name", $input.attr("name"))
                .attr('class', $input.attr('class'))
                .val($input.val())
                .insertBefore($input);
            $input.remove();
        });

        if (booCanEditMember) {
            var checkButton = new Ext.Button({
                text: _('Check'),
                width: 70,
                tooltip: {
                    width: 230,
                    text:  _('Use this button to check ImmiAccount.')
                },
                renderTo: 'checkAccount',
                listeners: {
                    click: function() {
                        var vevoLogin = vevoLoginField.val();
                        var vevoPass = $("#vevo_password").val();

                        Ext.getBody().mask('Checking ImmiAccount...');

                        Ext.Ajax.request({
                            url: baseUrl + '/manage-members/check-vevo-account',
                            params: {
                                member_id: edit_member_id,
                                login: Ext.encode(vevoLogin),
                                password: Ext.encode(vevoPass)
                            },
                            success: function (result) {
                                Ext.getBody().unmask();

                                var resultData = Ext.decode(result.responseText);

                                if (resultData.success) {
                                    Ext.simpleConfirmation.success(resultData.message);
                                } else {
                                    // Show error message
                                    Ext.simpleConfirmation.error(resultData.message);
                                }
                            },
                            failure: function () {
                                Ext.getBody().unmask();
                                Ext.simpleConfirmation.error('Cannot check ImmiAccount. Please try later');
                            }
                        });
                    }
                }
            });

            var changeButton = new Ext.Button({
                text: _('Change'),
                width: 70,
                tooltip: {
                    width: 230,
                    text:  _('Use this button to change ImmiAccount credentials.')
                },
                renderTo: 'changeAccount',
                listeners: {
                    click: function(){
                        var wnd = new ChangeVevoCredentialsDialog({
                            username: vevoLoginField.val(),
                            memberId: edit_member_id
                        });

                        wnd.show();
                        wnd.center();
                    }
                }
            });

            var clearButton = new Ext.Button({
                text: _('Clear'),
                width: 70,
                tooltip: {
                    width: 230,
                    text:  _('Use this button to clear ImmiAccount credentials.')
                },
                renderTo: 'clearAccount',
                listeners: {
                    click: function(){

                        Ext.Msg.confirm('Please confirm', 'Are you sure you want to clear ImmiAccount credentials?', function (btn) {
                            if (btn == 'yes') {
                                Ext.getBody().mask('Clearing ImmiAccount credentials...');

                                Ext.Ajax.request({
                                    url: baseUrl + '/manage-members/change-vevo-credentials',
                                    params: {
                                        member_id: edit_member_id,
                                        login: Ext.encode(''),
                                        password: Ext.encode('')
                                    },
                                    success: function (result) {
                                        Ext.getBody().unmask();

                                        var resultData = Ext.decode(result.responseText);

                                        if (resultData.success) {
                                            var vevoPasswordField = $("#vevo_password");
                                            vevoLoginField.val('');
                                            vevoLoginField.show();
                                            vevoPasswordField.val('');
                                            vevoPasswordField.show();

                                            $(".vevo-change-field").hide();
                                            $(".show_vevo_password").show();
                                            vevoUsers.setValue();
                                            vevoUsers.setDisabled(true);

                                            Ext.simpleConfirmation.success('Credentials were cleared successfully');
                                        } else {
                                            // Show error message
                                            Ext.simpleConfirmation.error(resultData.message);
                                        }
                                    },
                                    failure: function () {
                                        Ext.getBody().unmask();
                                        Ext.simpleConfirmation.error('Cannot clear ImmiAccount credentials. Please try later');
                                    }
                                });
                            }
                        });
                    }
                }
            });
        }
    }



    if (!booCanEditMember) {
        var id = "#member-details";
        $(id).find(':input').attr("disabled", true);
        $(id).find(':input').closest('tr').addClass('disabled');
        $(id).find('button').removeAttr('disabled');
    }
    $("#manage-members-content").tabs();

    // Need to be sure that inner fields cannot be checked/edited if parent checkbox is not checked
    // Also mark label in gray color
    var checkTrackerDependency = function(){
        var arrFieldsToCheck = ['time_tracker_disable_popup', 'time_tracker_rate', 'time_tracker_round_up'];

        if(!$("#time_tracker_enable").is(":checked")) {
            $.each(arrFieldsToCheck, function(index, value) {
                var field = $('#' + value);
                field.attr("disabled", true);
                field.closest('tr').addClass('disabled');
                if(field.is(':checkbox')) {
                    field.removeAttr("checked");
                }
            });
        } else {
            $.each(arrFieldsToCheck, function(index, value) {
                var field = $('#' + value);
                if (booCanEditMember) {
                    field.removeAttr("disabled");
                    field.closest('tr').removeClass('disabled');
                }
            });
        }
    };
    checkTrackerDependency();

    $("#time_tracker_enable").click(checkTrackerDependency);


    var checkRMADependency = function(){
        var arrFieldsToCheck = ['user_migration_number'];

        if(!$("#user_is_rma").is(":checked")) {
            $.each(arrFieldsToCheck, function(index, value) {
                var field = $('#' + value);
                field.attr("disabled", true);
                field.closest('tr').addClass('hidden');
                if(field.is(':checkbox')) {
                    field.removeAttr("checked");
                }
            });
        } else {
            $.each(arrFieldsToCheck, function(index, value) {
                var field = $('#' + value);
                if (booCanEditMember) {
                    field.removeAttr("disabled");
                }
                field.closest('tr').removeClass('hidden');
            });
        }
    };
    checkRMADependency();

    $("#user_is_rma").click(checkRMADependency);

    $('.main-members-tabs').click(function(){
        setTimeout(function(){
            var new_height;
            if ($('#manage-members-content').height() <= $('#admin-left-panel').outerHeight()) {
                new_height = $('#admin-left-panel').outerHeight();
            } else {
                new_height = $('#manage-members-content').height() + 82;
            }
            $("#admin_section_frame", top.document).height(new_height + 'px');
        }, 200);
    });
    
    // Copy email field value [User details section] to
    // email field [Email Account section]
    var main_email = $("#emailAddress");
    var account_email = $(".email_account");
    if(account_email.length > 0) {
        var primary_email_account_id = account_email[0].id;
        
        $('#'+primary_email_account_id).keyup(function () {
            main_email.val($(this).val());
        });
        
        main_email.keyup(function () {
            $('#'+primary_email_account_id).val($(this).val());
        });
    }

    $.validator.addMethod("usernameUniques",function(value,element){
        var pattern = new RegExp(/^[a-zA-Z0-9_.@\-àÀâÂäÄáÁéÉèÈêÊëËìÌîÎïÏòÒôÔöÖùÙûÛüÜçÇ’ñ]{3,64}$/, "i");
        return this.optional(element) || pattern.test(value);
    }, "<br/>3-64 characters (letters, digits and _@.- symbols).");

    $.validator.addMethod("passwordValidation",function(value,element){
        var pattern = new RegExp(passwordValidationRegexp); // @see var passwordValidationRegexp;  layouts/*/*.phtml
        return this.optional(element) || pattern.test(value);
    }, passwordValidationRegexpMessage);

    // Turn off browser's auto complete functionality
    var membersForm = $("#editMemberForm");
    membersForm.attr("autocomplete","off");

    // validate signup form on keyup and submit
    membersForm.validate({
        rules: {
            companyID: {
                required: true
            },
            userType: {
                required: true
            },
            oauth_idir: {
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
            emailAddress: {
                required: true,
                email: true
            },
            activationCode: {
                required: true,
                minlength: 4
            },
            fName: {
                required: true,
                minlength: 2
            },
            lName: {
                required: true,
                minlength: 2
            },
            'arrRoles[]': {
                required: true
            },
            time_tracker_rate: {
                min: 0,
                number: true
            }
        },


        messages: {
            companyID: {
                required: "Please select a Company"
            },
            userType: {
                required: "Please select User Type"
            },
            username: {
                required: "Please enter a Username",
                minlength: jQuery.validator.format("Please enter at least {0} characters"),
                remote: jQuery.validator.format("<span style='font-style: italic; font-size: 13px;'>{0}</span> is already in use")
            },
            password: {
                rangelength: jQuery.validator.format("Please enter at least {0} characters")
            },
            emailAddress: {
                required: "Please enter Email",
                email: "Please enter a valid Email"
            },
            activationCode: {
                required: "Please enter Activation Code",
                rangelength: jQuery.validator.format("Please enter at least {0} characters")
            },
            oauth_idir: {
                required: "This is a required field",
            }
        },

        // the errorPlacement has to take the table layout into account
        errorPlacement: function (label, element) {
            // position error label after generated textarea
            if (element.is(":checkbox")) {
                label.appendTo(element.parents('td').eq(1));
            } else {
                label.insertAfter(element);
            }
        },

        submitHandler: function(form) {
            // Make sure that "divisions" field was entered
            // If not - scroll to it
            if (divisionsAccessTo && !divisionsAccessTo.isValid()) {
                $('html, body').animate({
                    scrollTop: divisionsAccessTo.getEl().getX()
                }, 1000);
                return false;
            }

            var booSubmit = true;
            var account_email = $(".email_account");
            if(account_email.length > 0) {
                var primary_email_account_id = account_email[0].id;
                
                var emailBeforeEdit = $('#'+primary_email_account_id+'_previous').val();
                var emailAfterEdit  = $('#'+primary_email_account_id).val();

                if (emailBeforeEdit !== '' && emailBeforeEdit != emailAfterEdit) {
                    booSubmit = false;
                    
                    Ext.MessageBox.buttonText.yes = "Yes, create new account";
                    Ext.Msg.confirm('Please confirm', 'Email address was changed. So previously created email account<br/> will be disabled and created a new one.<br/> Are you sure you want to proceed?', function(btn){
                        if(btn == 'yes') {
                            form.submit();
                        }
                    });
                }
            }
            
            if(booSubmit) {
                form.submit();

                if (typeof window.top.refreshSettings == 'function') {
                    window.top.refreshSettings('users');
                }
            }
        },

        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("checked");
        }
    });
    


    // Ext js initialization
    var acc = $('#email-accounts');
    if(acc.length) {
        //clear content
        acc.empty();

        var oSettings = new MailSettingsGrid({
            autoLoadData: false,
            hideBackButton: true,
            dontShowWarning: true
        });

        // Show the grid
        oSettings.render('email-accounts');


        // Automatically load notes/tasks list on first click on the tab
        $("a[href='#email-accounts']").one("click", function () {
            if (oSettings) {
                // Don't reload immediately - let show the grid (and init dimensions)
                var reloadFn = function () {
                    oSettings.store.load();
                };
                reloadFn.defer(100, this);
            }
        });
    }

    $('.adminCheckbox').each(function(){
        $(this).click(function(){
            customizeOfficesField(false);
        });

    });

    customizeOfficesField(true);

    if (!booCanEditMember) {
        this.divisionsAccessTo.setDisabled(true);
        this.divisionsResponsibleFor.setDisabled(true);
        this.divisionsPullFrom.setDisabled(true);
        this.divisionsPushTo.setDisabled(true);

        var idsToDisable = ['userDivisionsAccessToCheckbox', 'userDivisionsPullFromCheckbox', 'userDivisionsPushToCheckbox', 'userDivisionsResponsibleForCheckbox'];
        idsToDisable.forEach(function(el){
            $('#' + el).attr("disabled", true);
        });
    }

});

function customizeOfficesField(booFirst) {
    var booAdminChecked = false;
    var offices = Ext.getCmp('divisions_access_to_lovcombo_id');
    $('.adminCheckbox').each(function(){
        if ($(this).prop('checked')) {
            booAdminChecked = true;
            if (offices) {
                if (!booFirst) {
                    var arrIds = [];
                    offices.store.each(function(rec){
                        arrIds.push(rec.data.division_id);
                    });

                    offices.setValue(arrIds.join(','));
                }

                offices.disable();
                $('#userDivisionsAccessToCheckbox').attr("disabled", true);
                $('#userDivisionsAccessToMessage').show();
                $('#userDivisionsAccessToCheckbox').hide();
                $('label[for=userDivisionsAccessToCheckbox]').hide();
            }

            return false;
        }
    });

    if (!booAdminChecked && offices) {
        offices.enable();
        $('#userDivisionsAccessToCheckbox').attr("disabled", false);
        $('#userDivisionsAccessToMessage').hide();
        $('#userDivisionsAccessToCheckbox').show();
        $('label[for=userDivisionsAccessToCheckbox]').show();
    }
}

function getFocusedNode(grid)
{
    if(grid) {
        var node = grid.getSelectionModel().getSelected();
        if(node) {
            return node;
        }
    }

    return false;
}

function enableLMSUser() {
    Ext.getBody().mask(_('Processing...'));
    Ext.Ajax.request({
        url: baseUrl + '/manage-members/enable-lms-user/',

        params: {
            member_id: Ext.encode(edit_member_id)
        },

        success: function (f) {
            var result = Ext.decode(f.responseText);
            if (result.success) {
                Ext.getBody().mask(_('Done!'));

                setTimeout(function () {
                    Ext.getBody().unmask();
                }, 750);
            } else {
                Ext.simpleConfirmation.error(result.message);
                Ext.getBody().unmask();
            }
        },

        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error(_('Internal error.'));
        }
    });
}

function selectAllLovCombo(el){
    if (!empty(el)) {
        var booChecked = $(el).is(":checked");
        var checkboxId = $(el).attr('id');
        var lovCombo;

        if (!empty(checkboxId)) {
            switch (checkboxId) {
                case 'userDivisionsAccessToCheckbox' :
                    lovCombo = this.divisionsAccessTo;
                    break;

                case 'userDivisionsPullFromCheckbox' :
                    lovCombo = this.divisionsPullFrom;
                    break;

                case 'userDivisionsPushToCheckbox' :
                    lovCombo = this.divisionsPushTo;
                    break;

                case 'userDivisionsResponsibleForCheckbox' :
                    lovCombo = this.divisionsResponsibleFor;
                    break;
            }
        }

        if (!empty(lovCombo)) {
            var arrIds = [];
            if (booChecked) {
                lovCombo.store.each(function(rec){
                    arrIds.push(rec.data.division_id);
                });
            }

            lovCombo.setValue(arrIds.join(','));
        }
    }
}