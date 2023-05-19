var stepsCount;
var current_state;

function load_state(current_state) {
    // reset the wizardcontent to hidden
    $('.steps').hide();

    //disable all buttons while loading the state
    $('#previous').attr('disabled', 'disabled');

    //load the content for this state into the wizard content div and fade in
    $('#step_' + current_state).fadeIn('slow');
    toggleExtjsJobFields(true);

    //set the wizard class to current state for next iteration
    $('#wizard').attr('class', 'step_' + current_state);

    // depending on the state, enable the correct buttons
    switch (parseInt(current_state, 10)) {
        case 1:
            $('#previous').attr('disabled', 'disabled');
            $('#next').removeAttr('disabled');
            break;
        case stepsCount:
            $('#previous').removeAttr('disabled');
            break;
        default:
            $('#previous').removeAttr('disabled');
            $('#next').removeAttr('disabled');
            break;
    }

    return true;
}

function isElementReallyHidden(el) {
    return $(el).is(":hidden") || $(el).css("visibility") == "hidden" || $(el).css('opacity') == 0;
}

var correctEnteredInfo = function (validator) {
    var booCorrectForm      = validator.form();
    var booCorrectJobCombos = true;

    // Check if relationship combo is visible + if "None" is selected -> show an error
    var comboRelationship = $('.qf_family_relationship select');
    if (comboRelationship.length && comboRelationship.hasClass('field_required')) {
        var booElementReallyShowed = !isElementReallyHidden(comboRelationship);
        $(comboRelationship).parents().each(function () {
            if (isElementReallyHidden(this)) {
                booElementReallyShowed = false;
            }
        });

        if (booElementReallyShowed && comboRelationship.find('option:selected').attr('data-val') === 'none') {
            var err = {};

            err[comboRelationship.attr('id')] = 'This field is required.';
            validator.showErrors(err);

            booCorrectForm = false;
        }
    }

    for (var i = 0; i < arrCustomExtjsFields.length; i++) {
        var combo = Ext.getCmp(arrCustomExtjsFields[i]);
        if (combo) {
            var parent  = combo.getEl().findParent('.steps');
            var parent2 = combo.getEl().findParent('.qnr-section');
            if (!empty(parent2) && !empty(parent2) && $(parent2).css('display') != 'none' && $(parent).css('display') != 'none') {
                // Current step is active, so field is visible
                if (!combo.isValid() || (empty(combo.getValue()) && combo.getEl().hasClass('field_required'))) {
                    combo.markInvalid();
                    booCorrectJobCombos = false;
                }
            }
        }
    }


    return booCorrectForm && booCorrectJobCombos;
};

/**
 * Hide steps if they don't have any visible sections
 */
function toggleSteps() {
    var i = 0,
        newHtmlSteps = '';
    $('div.steps').each(function () {
        var booVisible = false;
        $(this).find('table.qnr-section').each(function () {
            if ($(this).css('display') != 'none') {
                booVisible = true;
            }
        });

        if (booVisible) {
            // The first step is always 'completed'
            var cls = i ? '' : 'class="completed-step"';
            newHtmlSteps += '<div ' + cls + '><a href="#" id="wizard-steps-'+(i+1)+'">' + arrSteps[i] + '</a></div>';
            i++;
        }
    });
    $('.wizard-steps').html(newHtmlSteps);
}

function update_step_by_step() {
    toggleSteps();

    // Mark passed steps
    var i = 1;
    $('.wizard-steps div').each(function()
    {
        $(this).removeClass('completed-step');

        if (i <= current_state) {
            $(this).addClass('completed-step');
        }

        i++;
    });
}

function centerBottom(selector) {
    var hasVScroll = $('.wrapper').height()>$(window).height();

    if (!hasVScroll)
        $(selector).css({
            'position': 'absolute',
            'bottom': '0'
        });
    else
        $(selector).css({
            'position': 'static'
        });
}

$(document).ready(function() {

    // Add styles to fields with radiobuttons for showing without word wrapping
    initRadioButtons();

    initializeAllProspectMainFields('', false);

    //Hide field 'Was The Turnover Greater' and listen for changes in 'Have Experience In Managing' radio
    initExperienceBusiness('', false);

    // Hide spouse job section + radio
    initSpouseJobSection(false, '', false);

    // Hide not needed Job fields
    initJobFields('');

    // Initialize tooltips
    initQnrFieldTips();

    // Initialize sections/fields
    initQnrFields();

    // Calculate steps count
    stepsCount = 1;
    $('.steps').each(function(id, el) {
        var thisId = $(el).attr('id');
        var exploded = thisId.split('step_');
        if (parseInt(exploded[1], 10) > stepsCount) {
            stepsCount = parseInt(exploded[1], 10);
        }
    });

    // Initialize the wizard state
    load_state(1);

    var checkFieldIsOnPage = function(element) {
        if($(element).css('display')=='none' || $(element).is(":hiddenByParent")) {
            return false;
        }

        for (var index = 0; index <= stepsCount; index++) {
            if (current_state == index && $(element).parents('#step_' + (index)).length) {
                return true;
            }
        }
        return false;
    };

    $.validator.addMethod('pageRequired', function(value, element) {
            if (checkFieldIsOnPage(element)) {
                return !this.optional(element);
            }

            return 'dependency-mismatch';
        }, $.validator.messages.required);

    $.validator.addMethod("postcode", function (value, element) {
        if (checkFieldIsOnPage(element)) {
            var pattern = /^\d+$/;
            return $(element).val().length <= 4 && pattern.test($(element).val());
        }
        return 'dependency-mismatch';

    }, "Postcode should be a number that is consist of maximum 4 digits");

    var validator = $('#saveQnrForm').validate({
        // Show error in specific position - related to the field's type
        errorPlacement: function(label, element) {
            // position error label under the radio, checkboxes or date field
            if (element.is(':checkbox,:radio,.x-form-field')) {
                label.appendTo(element.parent().parent());
                label.css('padding-left', '0'); // avoid left padding
            } else {
                label.appendTo(element.parent());
            }
        }
    });
    
    // Initialize custom fields
    var q_id = $('[name=q_id]').val();
    initQnrSearch(q_id, validator);
    initQnrReferred(false);

    // Apply datepicker for such fields
    loadDatePicker();


    // Listen clicks on Next or Prev buttons
    $('#wizard-steps .completed-step a').click(function() {
        var id = $(this).attr('id')
        var newStatus = parseInt(id.replace(/(wizard-steps-)/, ''), 10);
        load_state(newStatus);
    });

    
    $('#wizard .buttons button').click(function() {
        current_state = $('#wizard').attr('class');
        //we only want the number, converted to an int
        current_state = parseInt(current_state.replace(/(step_)/, ''), 10);

        var booIsNext = ($(this).attr('id') == 'next');

        if (booIsNext) {
            // Check form and only if all is correct - continue
            if (!correctEnteredInfo(validator)) {
                showConfirmationMsg('Please fill all the required fields correctly and try again.', true);
                return false;
            }

            // Skip empty steps
            if (current_state != stepsCount) {
                var booVisible = false;
                while (!booVisible) {
                    $('#step_' + (current_state + 1)).find('table.qnr-section').each(function () {
                        if ($(this).css('display') != 'none') {
                            booVisible = true;
                        }
                    });

                    if (!booVisible) {
                        current_state++;
                    }

                    if (current_state == stepsCount){
                        booVisible = true;
                    }
                }
            }

            if (current_state != stepsCount) {
                // If this is not the last step - simply show the step
                current_state++;
                load_state(current_state);
            } else {
                // This is the last step - submit data
                var options = {
                    url: baseUrl + '/qnr/index/save',
                    type: 'post',
                    dataType: 'json',

                    success: function(res) {
                        Ext.getBody().unmask();

                        // Show submission result
                        if (res.success) {
                            $('#wizard_top_section').hide();
                            $('#wizard').hide();
                            $('#confirmation_message').html(res.msg).fadeIn();
                        } else {
                            showConfirmationMsg(res.msg, true);

                            // Enable buttons
                            $('#next').removeAttr('disabled');
                            $('#previous').removeAttr('disabled');
                        }
                    },

                    error: function() {
                        showConfirmationMsg('Error happened during data submitting. Please try again later.', true);

                        // Enable buttons
                        $('#next').removeAttr('disabled');
                        $('#previous').removeAttr('disabled');
                        Ext.getBody().unmask();
                    },


                    beforeSubmit: function(formData) {
                        // Disable buttons
                        $('#next').attr('disabled', 'disabled');
                        $('#previous').attr('disabled', 'disabled');

                        // Reset fields if they are hidden
                        var arrCheckFields = [
                            // Children
                            'qf_children_count',
                            'qf_children_age_1',
                            'qf_children_age_2',
                            'qf_children_age_3',
                            'qf_children_age_4',
                            'qf_children_age_5',
                            'qf_children_age_6',

                            // Work
                            'qf_work_temporary_worker',
                            'qf_work_years_worked',
                            'qf_work_currently_employed',
                            'qf_work_leave_employment',
                            'qf_study_previously_studied',
                            'qf_work_offer_of_employment',

                            // Family Relations
                            'qf_family_have_blood_relative',
                            'qf_family_relationship',
                            'qf_family_relative_wish_to_sponsor',
                            'qf_family_sponsor_age',
                            'qf_family_employment_status',
                            'qf_family_sponsor_financially_responsible',
                            'qf_family_sponsor_income',
                            'qf_family_currently_fulltime_student',
                            'qf_family_been_fulltime_student',

                            /* BUSINESS/FINANCE */
                            'qf_cat_net_worth',
                            'qf_cat_have_experience',
                            'qf_cat_managerial_experience',
                            'qf_cat_staff_number',
                            'qf_cat_own_this_business',
                            'qf_cat_percentage_of_ownership',
                            'qf_cat_annual_sales',
                            'qf_cat_annual_net_income',
                            'qf_cat_net_assets'
                        ];

                        // Collect all fields we want reset
                        var arrFieldsReset = [];
                        for (var i = 0; i < arrCheckFields.length; i++) {
                            var element = $('.' + arrCheckFields[i]);
                            if(element && (element.css('display') == 'none')) {
                                var input = element.find('input,select').first();
                                if(input) {
                                    arrFieldsReset.push(input.attr('name'));
                                }
                            }
                        }
                        
                        // Reset fields
                        jQuery.each(formData, function(index, elm) {
                            for (var j = 0; j < arrFieldsReset.length; j++) {
                                if (arrFieldsReset[j] == elm.name) {
                                    formData[index].value = '';
                                }
                            }
                        });


                        Ext.getBody().mask('Submitting...');
                    }
                };

                // Fix issue with JQuery 1.5 with json
                jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

                document.getElementById("saveQnrForm").method = "post";
                $('#saveQnrForm').ajaxSubmit(options);
            }
        } else {
            // Skip empty steps
            if (current_state > 1) {
                var booVisibleSection = false;
                while (!booVisibleSection) {
                    $('#step_' + (current_state - 1)).find('table.qnr-section').each(function () {
                        if ($(this).css('display') != 'none') {
                            booVisibleSection = true;
                        }
                    });

                    if (!booVisibleSection) {
                        current_state--;
                    }

                    if (current_state == 1){
                        booVisibleSection = true;
                    }
                }
            }

            current_state--;
            load_state(current_state);
        }

        // step by step
        update_step_by_step();

        centerBottom(".bottom_copyright");
    });

    centerBottom(".bottom_copyright");
});
