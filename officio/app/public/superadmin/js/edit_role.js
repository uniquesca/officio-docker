function disableChildCheckboxes(parentId){
    var genId = '.subRule_' + parentId;
    if ($('#rule_' + parentId).is(':checked')) {
        $(genId).prop('checked', true);
        $(genId).removeAttr('disabled');  
    } else {
        $(genId).prop('checked', false);
        $(genId).attr('disabled', 'disabled'); 
    }
}

function checkAllChild(parentId) {
    $.each($('#section_'+parentId+' :checkbox'), function(num, chk) {
        if($(chk).is(':checked') && $(chk).is(':disabled')) {
            // skip
            var booSkip = true;
        } else {
            if ($('#top_rule_' + parentId).is(':checked')) {
                // mark as checked
                $(chk).prop('checked', true);
                $(chk).removeAttr('disabled');
            } else {
                // mark as unchecked
                $(chk).prop('checked', false);

                if(chk.id.substr(0, 5) == 'rule_') {
                    var id = chk.id.substr(5, chk.id.length - 5);
                    disableChildCheckboxes(id);
                }
            }
        }
    });
}


function hideShowSectionViewFields(strSectionId, parent_section_id) {
    var MasSectionId = strSectionId.split(",");
    var ii;
    if (!$("#parent_view_" + parent_section_id).is(':checked')) {
        $("#parent_full_accees_" + parent_section_id).prop('checked', false);
        for (ii = 0; ii < MasSectionId.length; ii++) {

            if (MasSectionId[ii] !== '') {
                $("#field_view_" + MasSectionId[ii]).prop('checked', false);
                $("#field_full_" + MasSectionId[ii]).prop('checked', false);
                $("#field_view_" + MasSectionId[ii]).attr('disabled', 'disabled');
                $("#field_full_" + MasSectionId[ii]).attr('disabled', 'disabled');
            }
        }
    } else {
        for (ii = 0; ii < MasSectionId.length; ii++) {
            if (MasSectionId[ii] !== '') {
                $("#field_view_" + MasSectionId[ii]).prop('checked', true);
                $("#field_view_" + MasSectionId[ii]).removeAttr('disabled');
            }
        }
    }
}

function hideShowSectionFullFields(strSectionId, parent_section_id) {
    var MasSectionId = strSectionId.split(",");
    var ii;
    if (!$("#parent_full_accees_" + parent_section_id).is(':checked')) {
        for (ii = 0; ii < MasSectionId.length; ii++) {
            if (MasSectionId[ii] !== '') {
                $("#field_full_" + MasSectionId[ii]).prop('checked', false);
                $("#field_full_" + MasSectionId[ii]).attr('disabled', 'disabled');
            }
        }
    } else {
        $("#parent_view_" + parent_section_id).prop('checked', true);
        for (ii = 0; ii < MasSectionId.length; ii++) {
            if (MasSectionId[ii] !== '') {
                $("#field_full_" + MasSectionId[ii]).prop('checked', true);
                $("#field_view_" + MasSectionId[ii]).prop('checked', true);
                $("#field_view_" + MasSectionId[ii]).removeAttr('disabled');
                $("#field_full_" + MasSectionId[ii]).removeAttr('disabled');
            }
        }
    }
}

function hideShowSectionData(sectionId) {
    $('#section_'+sectionId).toggle();
    updateFrameHeight(0);
}

function hideGroupFields(obj)
{
    var group_id = $(obj).attr('id').replace('group-', '');
    var checked = $(obj).is(':checked');

    if(checked) {
        $('.tr-group-' + group_id).show();
    } else {
        $('.tr-group-' + group_id).hide();

        //set fields to 'Not Allowed'
        $('.tr-group-' + group_id + ' input[value="0"]').each(function(){
            $(this).prop('checked', true);
        });
    }
    updateFrameHeight(0);
}

function updateFrameHeight(timeout) {
    setTimeout(function(){
        var manageRolesPanel = $('#manage-roles-content');

        var new_height;
        if (manageRolesPanel.height() <= $('#admin-left-panel').outerHeight()) {
            new_height = $('#admin-left-panel').outerHeight();
        } else {
            new_height = manageRolesPanel.height() + 82;
        }

        $("#admin_section_frame", top.document).height((new_height) + 'px');
    }, timeout);
}

$(document).ready(function() {
    $('#manage-roles-content').css('min-height', getSuperadminPanelHeight() + 'px');
    $("#edit_role_access_container").tabs();
    $("#fields-level-access-by-type-cases").tabs();
    $(".with_tabs").tabs();

    // Automatically set the same access right for the field in all other groups
    $("#fields-level-access-by-type-cases input[type='radio']").click(function () {
        var currentRadioName = $(this).attr('name');

        // Search for radios for the same field, but in other groups
        var match = currentRadioName.match(/field_(\d+)_(\d+)/);
        if (match != null) {
            var fieldVal = $(this).val();
            var regex    = new RegExp('field_(\\d+)_' + match[2]);

            $("#fields-level-access-by-type-cases input[type='radio']").each(function () {
                if ($(this).attr('name') !== currentRadioName && regex.test($(this).attr('name')) && fieldVal === $(this).val()) {
                    // Check such radios with the same value
                    $(this).prop('checked', true);
                }
            });
        }
    });

    // Check/Uncheck 'child' checkbox in relation to this
    $('.field_full_check').click(function () {
        if ($("#field_full_" + $(this).val()).is(':checked')) {
            $("#field_view_" + $(this).val()).prop('checked', true);
        }
    });
    
    // Uncheck 'parent' checkbox in relation to this
    $('.field_view_check').click(function(){
        if(!$(this).is(':checked')) {
            $( "#field_full_" + $(this).val()).prop('checked', false);
        }
    });

    //hide/show group fields by clicking on group checkbox
    $('.group-checkbox').click(function(){
        hideGroupFields(this);
        updateFrameHeight(0);
    }).each(function(){
        // hide unchecked groups
        hideGroupFields(this);
    });
    
    $('.main-role-tabs').click(function(){
        //update height
        updateFrameHeight(200);
        
        //update action hash
        var form = $('#editRoleForm');
        var act = form.attr('action');
        if(act.indexOf('#') != -1) {
            act = act.substr(0, act.indexOf('#'));
        }
        form.attr('action', act + $(this).attr('href'));
        
    });


    // validate signup form on keyup and submit
    var validator = $("#editRoleForm").validate({
        rules: {
            roleTextId: {
                required: true,
                minlength: 4,
                remote: baseUrl + "/roles/check-role"
            },
            roleName: {
                required: true,
                minlength: 4
            }
        },
        messages: {
            roleTextId: {
                required: "Please enter a Role Id",
                minlength: jQuery.validator.format("Please enter at least {0} characters"),
                remote: jQuery.validator.format("<span style='font-style: italic; font-size: 13px;'>{0}</span> is already in use")
            },
            roleName: {
                required: "Please enter a Role Name",
                rangelength: jQuery.validator.format("Please enter at least {0} characters")
            }
        },
        
        // set this class to error-labels to indicate valid fields
        success: function (label) {
            // set   as text for IE
            label.html(" ").addClass("checked");

            // disable all child checkboxes if parent checkbox is not checked
            $('.parentrule').each(function () {
                    var parentRuleId = this.id;
                    var isChecked = $('#' + parentRuleId).is(':checked');
                    if (!isChecked) {
                        var parentClassId = '.main' + parentRuleId;
                        $(parentClassId).attr('disabled', 'disabled');
                    }
                });
        }
    });
    
    //move to the end of page if hash exists
    if(document.location.href.indexOf('#') != -1) {
        document.location.href = "#updateRoleBtn";
    }

    $('.check_all_radio').click(function(){
        var table = $(this).closest('table');
        table.find('.group-checkbox').each(function () {
            $(this)[0].checked = true;
        });

        table.find('.group-checkbox').each(function () {
            hideGroupFields(this);
            updateFrameHeight(0);
        });

        // Check all radios that are in the same td/column and uncheck in others of the same table
        var td_index = $(this).closest('td').index();
        table.find('td').each(function () {
            var parent_td = $(this).closest('td');
            parent_td.find('input[type=radio]:visible').prop('checked', parent_td.index() === td_index).change();
        });
    });

    var toggleGroupNamesCheckbox = $('#toggle_grouped_names');
    if (!booCanEditRole) {
        $(':input').attr('disabled', true);
        toggleGroupNamesCheckbox.attr('disabled', false);
    }

    var toggleCaseTypeAndGroupNameColumn = function (booShow) {
        $('.case_type_and_group_name_column').toggle(booShow);
        updateFrameHeight(0);
    };

    toggleGroupNamesCheckbox.click(function () {
        toggleCaseTypeAndGroupNameColumn($(this).is(':checked'));
    });

    // The checkbox is unchecked by default
    toggleGroupNamesCheckbox.prop('checked', false);
    toggleCaseTypeAndGroupNameColumn(false);
});