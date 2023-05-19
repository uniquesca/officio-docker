var stepsCount;
var listActiveSteps = [];
var current_state;

$(document).ready(function(){
    $('[data-toggle="popover"]').popover();   
});

var adjustSteps = function() {
    $('.completed-step').find('a').click(function() {
        var id = $(this).attr('id')
        var newStatus = parseInt(id.replace(/(wizard-steps-)/, ''), 10);
        load_state(newStatus);
    });
};

// Listen clicks on Next or Prev buttons
function load_state(stepNumber) {
    calcSteps();
    // // reset the wizardcontent to hidden
    $('.steps').hide();

    //disable all buttons while loading the state
    $('#previous').hide();

    //load the content for this state into the wizard content div and fade in
    $('#step_' + stepNumber).fadeIn('slow');

    //set the wizard class to current state for next iteration
    $('#wizard').attr('class', 'container step_' + stepNumber);
    var lastStepNumber = getLastStepNumber();

    // depending on the state, enable the correct buttons
    switch (parseInt(stepNumber, 10)) {
        case 1:
            $('#previous').hide();
            $('#next').text('Next');
            $('#next').show();
            $('.q_please_press_next_message').show();
            break;
        case lastStepNumber:
            $('#previous').show();
            $('#next').text('Submit');
            $('.q_please_press_next_message').hide();
            break;
        default:
            $('#previous').show();
            $('#next').text('Next');
            $('#next').show();
            $('.q_please_press_next_message').show();
            break;
    }
    $('html,body').animate({scrollTop:0},0);

    // Adjust Dropdown Step
    var currentStepName = arrSteps && arrSteps.length>=stepNumber ? arrSteps[stepNumber-1] : `Step ${stepNumber}`;
    $('#dropdownMenuStep').html(currentStepName);
    
    // Adjust copyright position
    setTimeout(() => {
        if(typeof(window.centerBottom) == 'function') {
            centerBottom(".bottom_copyright");
        }
    }, 1000);
    return true;
}

function isElementReallyHidden(el) {
    return $(el).is(":hidden") || $(el).css("visibility") == "hidden" || $(el).css('opacity') == 0;
}

var correctEnteredInfo = function (validator) {
    var booCorrectForm = validator.form();

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

    return booCorrectForm;
};

function notEmptyStep(eStep) {
    return (eStep.find('.qnr-section:not([style*="display: none"])').length && eStep.find('.card-block>.row:not([style*="display: none"])').length);
}

/**
 * Hide steps if they don't have any visible sections
 */
function toggleSteps() {
    var newHtmlSteps = '',
        newDropdown = '',
        characterNumber = 0;
    $('div.steps').each(function () {
        if (notEmptyStep($(this))) {
            let i = parseInt($(this).attr('id').replace('step_',''), 10) - 1;
            characterNumber += arrSteps[i].length;

            // The first step is always 'completed'
            var cls = i ? '' : 'class="completed-step"';
            newHtmlSteps += '<div ' + cls + '><a href="#" id="wizard-steps-'+(i+1)+'" class="mt-2"><h5>' + arrSteps[i] + '</h5></a></div>';

            var disabled = i ? 'disabled' : '';
            newDropdown += `<button class="dropdown-item" type="button" value="${(i+1)}" ${disabled}>${arrSteps[i]}</button>`;

        }
        
    });

    var wizardStepsElement = $('.wizard-steps');
    wizardStepsElement.removeClass('wizard-steps-long');
    if (characterNumber > 45) {
        wizardStepsElement.addClass('wizard-steps-long');
    }
    wizardStepsElement.html(newHtmlSteps);
    
    if (newDropdown) {
        $('.step-dropdown').show();
        $('.dropdown-menu').html(newDropdown);
    }

    $('.dropdown-item').click(function() {
        var val = $(this).val();
        load_state(val);
    });
}

function update_step_by_step() {
    toggleSteps();

    // Mark passed steps
    $('.wizard-steps div').each(function()
    {
        let stepNumber = parseInt($(this).find('a').attr('id').replace('wizard-steps-',''), 10);
        $(this).removeClass(['completed-step', 'current-step']);

        if (stepNumber <= current_state) {
            $(this).addClass('completed-step');
        }
        if (stepNumber == current_state) {
            $(this).addClass('current-step');
        }
    });

    // Mark passed steps on Select
    $('.dropdown-menu button').each(function()
    {
        $(this).prop('disabled', true);
        let stepNumber = $(this).val();
        if (stepNumber <= current_state) {
            $(this).prop('disabled', false);
        }
    });

    adjustSteps();
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

function calcSteps() {
    listActiveSteps = [];
    $('.steps').each(function(id, el) {
        if (notEmptyStep($(this))) {
            var thisId = $(el).attr('id');
            var stepNumber = parseInt(thisId.split('step_')[1], 10);
            listActiveSteps.push(stepNumber);
        }
    });
    stepsCount = listActiveSteps.length;
}

function getLastStepNumber() {
    return listActiveSteps?listActiveSteps[listActiveSteps.length-1]:0;
}

function getStateIndex(stepNumber) {
    return listActiveSteps.indexOf(stepNumber);
}
function getNextState(stepNumber) {
    let currentIndex = getStateIndex(stepNumber);
    return currentIndex != -1? listActiveSteps[currentIndex+1]: 1;
}

function getPreviousState(stepNumber) {
    let currentIndex = getStateIndex(stepNumber);
    return currentIndex > 0? listActiveSteps[currentIndex-1]: 1;
}

$(document).ready(function() {
    // Hide all spouse fields and listen for changes in 'Marital status' combo
    initMaritalStatus('');

    //Hide all current address fields and listen for changes in 'Country of current Residence' combo
    initCurrentCountry('');

    // Hide  field 'Visa Refused Or Cancelled' and listen for changes in 'Applied For Visa Before' radio
    initVisaRefusedOrCancelled('',false);

    // Hide  fields of "SPONSORSHIP BY ELIGIBLE RELATIVE IN DESIGNATED AREA"
    initDesignatedAreaFields('');

    initEducationalQualifications('');

    initSpouseEducationalQualifications('');

    // Hide fields in the Professional Programs block
    initProfessionalProgramsBlock('');

    // Hide  field 'Is Partner Resident Of New Zealand' and listen for changes in 'Spouse is Resident of Australia' radio
    initIsPartnerResidentOfNewZealand('');

    // Hide all sections and listen for changes in 'Area of Interest' checkbox
    initAreaOfInterest('');

    //Hide all other fields and listen for changes in 'Other' point of 'Area of Interest' checkbox
    initOthers('');

    //Hide 'Bachelor degree' fields and listen for changes in 'Highest Qualification' radio
    initHighestQualification('');

    //Hide 'Duration of Course' and 'Was Course in Regional' fields and listen for changes in 'Country of Education' combo
    initCountryOfEducation('');

    //Display Spouse Education fields after Main Applicant fields
    initSpouseEducationFields();

    //Hide 'Score' fields and listen for changes in 'Have Taken IELTS' radio
    initLanguage();

    initSpouseLanguageFields();

    //Hide field 'Was The Turnover Greater' and listen for changes in 'Have Experience In Managing' radio
    initExperienceBusiness('');

    // Hide spouse job section + radio
    initSpouseJobSection('', false);

    // Hide not needed Children fields
    initChildrenFields('');

    // Toggle NOC field
    initNocField('');

    // Toggle Post secondaries field
    initPostSecondariesField('');

    // Hide not needed Job fields
    initJobFields('');

    // Initialize sections/fields
    initQnrFields();

    $(".thousand-separator").on("change", function(event) {
        var number = addCommas($(this).val());
        $(this).val(number);
    });

    // Calculate steps count
    stepsCount = 1;
    calcSteps();

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

    $.validator.addMethod("equalToIgnoreCase", function (value, element, param) {
        return this.optional(element) ||
            (value.toLowerCase() === $(param).val().toLowerCase());
    }, "Please enter the same value again.");

    $.validator.addMethod("moneyField", function (value, element, param) {
        value = value.replace(',', ''); // Sanitize the values.
        return this.optional(element) || /^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/.test(value);
    }, "Please enter a valid number.");

    $.validator.addMethod("minMoneyField", function (value, element, param) {
        value = value.replace(',', ''); // Sanitize the values.
        return this.optional(element) || value >= param;
    }, jQuery.validator.format("Please enter a value greater than or equal to {0}."));

    $.validator.addMethod("maxMoneyField", function (value, element, param) {
        value = value.replace(',', ''); // Sanitize the values.
        return this.optional(element) || value <= param;
    }, jQuery.validator.format("Please enter a value less than or equal to {0}."));

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
            label.appendTo(element.closest('.field-wrapper').find('.error-validation'));
        },
        focusInvalid: false,
        invalidHandler: function(form, validator) {

            if (!validator.numberOfInvalids())
                return;

            $('html, body').animate({
                scrollTop: $(validator.errorList[0].element).offset().top - 30
            }, 1000);

        }
    });
    
    // Initialize custom fields
    var q_id = $('[name=q_id]').val();
    initQnrSearch(q_id, validator);

    initDatePickers(validator);

    initCombo(validator);

    initQnrReferred(false);


    $('a.move').click(function() {
        calcSteps();
        current_state = $('#wizard').attr('class');
        //we only want the number, converted to an int
        current_state = parseInt(current_state.replace(/(container step_)/, ''), 10);

        var booIsNext = ($(this).attr('id') == 'next');
        var lastStepNumber = getLastStepNumber();

        if (booIsNext) {
            // Check form and only if all is correct - continue
            if (!correctEnteredInfo(validator)) {
                //showConfirmationMsg('Please fill all the required fields correctly and try again.', true);
                return false;
            }

            if (current_state != lastStepNumber) {
                // If this is not the last step - simply show the step
                current_state = getNextState(current_state);
                load_state(current_state);
            } else {
                // This is the last step - submit data
                var options = {
                    url: baseUrl + '/qnr/index/save',
                    type: 'post',
                    dataType: 'json',

                    success: function(res) {
                        // Show submission result
                        if (res.success) {
                            if(site_version == 'australia') {
                                $('#wizard_top_section').hide();
                            }

                            $('#wizard').hide();
                            $('#confirmation_message').html(res.msg).fadeIn();
                            centerBottom(".bottom_copyright");
                            // setTimeout(res.q_script_analytics_on_completion, 1);
                        } else {
                            $('#confirmation_message').html(res.msg).fadeIn();
                            showConfirmationMsg(res.msg, true);
                            centerBottom(".bottom_copyright");

                            // Enable buttons
                            $('#next').show();
                            $('#previous').show();
                        }
                    },

                    error: function() {
                        showConfirmationMsg('Error happened during data submitting. Please try again later.', true);

                        // Enable buttons
                        $('#next').show();
                        $('#previous').show();
                    },


                    beforeSubmit: function(formData) {
                        // Disable buttons
                        $('#next').hide();
                        $('#previous').hide();

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
                            'qf_education_studied_in_canada_period',
                            'qf_work_offer_of_employment',
                            'qf_work_noc',

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

                        $('.thousand-separator').each(function() {
                            var number = $(this).val().replace(',', '');
                            $(this).val(number);
                        });

                        // Reset fields
                        jQuery.each(formData, function(index, elm) {
                            for (var j = 0; j < arrFieldsReset.length; j++) {
                                if (arrFieldsReset[j] == elm.name) {
                                    formData[index].value = '';
                                }
                            }
                        });
                    }
                };

                // Fix issue with JQuery 1.5 with json
                jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

                document.getElementById("saveQnrForm").method = "post";
                $('#saveQnrForm').ajaxSubmit(options);
            }
        } else {
            current_state = getPreviousState(current_state);
            load_state(current_state);
        }

        // step by step
        update_step_by_step();

        centerBottom(".bottom_copyright");
    });

    centerBottom(".bottom_copyright");
});
