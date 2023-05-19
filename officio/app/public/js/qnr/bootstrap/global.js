/**
 * Initialize datepickers
 */

function initDatePickers(validator) {
    $('.datepicker').each(function () {
        var datepicker = $(this).datepicker({
            uiLibrary: 'bootstrap4',
            autoclose: true,
            changeMonth: true,
            changeYear: true,
            format: dateFormatFull == 'M d, Y' ? 'mmm dd, yyyy' : 'dd mmm yyyy',
            onClose: function () {
                $(this).trigger('blur');
                validator.element(this);
            },
            change: function (e) {
                var val = this.value;
                if (!empty(val)) {
                    try {
                        var df = new Ext.form.DateField({
                            format: dateFormatFull,
                            maxLength: 12,
                            altFormats: dateFormatFull + '|' + dateFormatShort
                        });

                        var dt = new Date(val);
                        df.setValue(dt);
                        this.value = df.getRawValue() || val;
                    } catch(err) {
                    }
                }
                validator.element(this);
            }
        });
        $(this).on("click", function() {
            datepicker.open();
            return false;
        });
    });
}

function initCombo(validator) {
    $('select.combo').each(function () {
        $(this).select2();
        $(this).on("change", function (e) {
            validator.element(this);
        });

    });
    $('.select2-container').css("width", "100%");
}

/**
 * Initialize 'referred by' fields
 */
function initQnrReferred(booUseDefaultValue) {
    $(".profile-referred-by").select2({
        tags: true,
        placeholder: "Type or select from the list...",
        createTag: function (params) {
            return {
                id: params.term,
                text: params.term,
                newOption: true
            }
        },
        templateResult: function (data) {
            var $result = $("<span></span>");

            $result.text(data.text);

            if (data.newOption) {
                $result.append(" <em>(new)</em>");
            }

            return $result;
        }
    });

    // if (!booUseDefaultValue) {
    //     $(".profile-referred-by").val('');
    // }
}

/**
 * Initialize search fields
 */
function _initJobSearchFields(q_id) {
    $('input.job_search.ajax-typeahead').typeahead({
        delay: 500,
        minLength: 2,
        source: function(query, process) {
            return $.ajax({
                url: topBaseUrl + '/qnr/index/search',
                type: 'post',
                data: {query: query, q_id: q_id, lang: qnrLang},
                dataType: 'json',
                success: function(json) {
                    if (typeof json.rows == 'undefined') {
                        return false;
                    } else {
                        var objects = [];

                        $.each(json.rows, function(i, object) {
                            var newObject = [];
                            newObject.id = object.noc_code;
                            newObject.name = object.noc_job_title;
                            // {name: 'code', mapping: 'noc_code'},
                            // {name: 'title', mapping: 'noc_job_title'}

                            objects.push(newObject);
                        });
                        process(objects);
                    }
                },
                error: function (xhr,status,error) {
                }
            });
        }
    });

    // Also init 'job + noc' fields
    initJobAndNocFields(q_id);
}

function initJobAndNocFields(q_id) {
    $('input.job_and_noc.ajax-typeahead, input.job_and_noc_search.ajax-typeahead').typeahead({
        source: function(query, process) {
            return $.ajax({
                url: topBaseUrl + '/qnr/index/search',
                type: 'post',
                data: {booSearchByCodeAndJob: 1, query: query, q_id: q_id, lang: qnrLang},
                dataType: 'json',
                success: function(json) {
                    if (typeof json.rows == 'undefined') {
                        return false;
                    } else {
                        var objects = [];

                        $.each(json.rows, function(i, object) {
                            var newObject = [];
                            newObject.id = object.noc_code;
                            newObject.name = object.noc_job_and_code;
                            objects.push(newObject);
                        });
                        process(objects);
                    }
                },
                error: function (xhr,status,error) {
                }
            });
        }
    });
}

var showConfirmationMsg = function(msg, booError) {
    $('#modalDialogContent').html(msg);
    if (booError) {
        $('#modalDialogTitle').text('Error');
        $('#modalDialogTitle').css('color', 'red');
    } else {
        $('#modalDialogTitle').text('Success');
        $('#modalDialogTitle').css('color', 'blue');
    }
    $('#modalDialog').modal('show');
};

function initJobSectionDate(where) {
    $(where + '.datepicker').each(function () {
        var datepicker = $(this).datepicker({
            uiLibrary: 'bootstrap4',
            autoclose: true,
            changeMonth: true,
            changeYear: true,
            format: dateFormatFull == 'M d, Y' ? 'mmm dd, yyyy' : 'dd mmm yyyy',
            onClose: function () {
                $(this).trigger('blur');
                validator.element(this);
            },
            change: function (e) {
                validator.element(this);
            }
        });
        $(this).on("click", function() {
            datepicker.open();
            return false;
        });
    });
}

function initQnrSearch(q_id, validator) {
    // Listen for 'close job section' click
    $(document).on("click", ".jobs_section_hide", function() {
        $(this).parents('.job_section_table').slideUp("normal", function() {
            $(this).find('select').select2("destroy");
            $(this).empty().remove();

            if(typeof(window.centerBottom) == 'function') {
                centerBottom(".bottom_copyright");
            }
        });
        return false;
    });

    var tabId = 'ptab-' + q_id;
    var formId = tabId + '-occupations-prospects';

    var where = empty(validator) ? '#' + formId + ' .job-section-' + qnrJobSectionId + ' div.job_section' : '.job-section-' + qnrJobSectionId + ' div.job_section';

    // Get whole section we need duplicate
    var jobSearchTrs = '';
    var tr = $(where);

    tr.each(function() {
        // Use classes
        var trClasses = $(this).attr('class');
        var clearedClasses = trClasses.replace(/(job_section)/, "");
        jobSearchTrs += '<div class="'+clearedClasses+'">' + $(this).html() + '</div>';
    });

    var whereSpouse = empty(validator) ? '#' + formId + ' .job-section-' + qnrSpouseJobSectionId + ' div.job_section' : '.job-section-' + qnrSpouseJobSectionId + ' div.job_section';

    // Get whole section we need duplicate
    var jobSpouseSearchTrs = '';
    tr = $(whereSpouse);
    tr.each(function (el) {
        // Use classes
        var trClasses = $(this).attr('class');
        var clearedClasses = trClasses.replace(/(job_section)/, "");
        jobSpouseSearchTrs += '<div class="' + clearedClasses + '">' + $(this).html() + '</div>';
    });

    // Init search field
    _initJobSearchFields(q_id);

    // Don't show fields in 'duplicated tables'
    $('.job_section_table').find('.field_do_not_duplicate').closest('tr').hide();


    if($('.q_job_add_'+ q_id).length !== 0) {
        // Init 'add new job section' button
        $('.q_job_add_'+ q_id).each(function() {
            var containerButtonId = $(this).attr('id');
            var parentTableClass = $(this).parents('.qnr-section').hasClass('job-section-' + qnrJobSectionId) ? 'job-section-' + qnrJobSectionId : 'job-section-' + qnrSpouseJobSectionId;
            var booMainApplicant = parentTableClass == 'job-section-' + qnrJobSectionId;

            var addButtonId = booMainApplicant ? 'add-employment' : 'add-spouse-employment';

            var addButton =
                '<div class="row add-employment' + (!booMainApplicant ? ' spouse_field' : '') + '">' +
                '<div class="col-xl-12">' +
                '<a href="#" id="' + addButtonId + '" class="btn btn-primary my-2">' +
                '<i class="fas fa-plus"></i>' + (site_version == 'australia' ? ' Add further employment' : ' Add More Work Experience') +
                '</a>' +
                '</div>' +
                '</div>';
            $("#" + containerButtonId).append(addButton);

            $("#" + addButtonId).on("click", function() {
                // Find the last tr
                var where2 = empty(validator) ? '#' + formId + ' .' + parentTableClass + ' div.job_section' : '.' + parentTableClass + ' div.job_section';
                var allTrs = $(where2).nextAll();
                var lastTr = empty(allTrs.length) ? where2 : allTrs[allTrs.length - 1];

                // Check how many blocks already added
                var jobSectionsCount = 0;
                $('.' + parentTableClass + ' .job_section_table').each(function(){
                    var exploded = $(this).attr("class").split(' ');
                    $.each(exploded, function(index, value) {
                        if (/^section_num_\d+/.test(value)) {
                            // Successful match
                            var exploded_class = value.split('section_num_');
                            var sectionNum = parseInt(exploded_class[1], 10);
                            jobSectionsCount = jobSectionsCount < sectionNum ? sectionNum : jobSectionsCount;
                        }
                    });
                });

                // Replace ids
                var arrReplaced = [];
                var updatedJobSearchTrs = booMainApplicant ? jobSearchTrs : jobSpouseSearchTrs;

                var found = $(where2).find('[id^=q_],[name^=q_],[id^=p_],[name^=p_]');
                if (found.length > 0) {
                    for (var i = 0; i < found.length; i++) {
                        // Replace id
                        var idToReplace = $(found[i]).attr('id');
                        var newId = idToReplace + '_' + (jobSectionsCount + 1);
                        updatedJobSearchTrs = updatedJobSearchTrs.replace(new RegExp(idToReplace, "g"), newId);
                        arrReplaced[arrReplaced.length] = idToReplace;

                        // Replace name
                        var nameToReplace = $(found[i]).attr('name');
                        if(!empty(nameToReplace) && !arrReplaced.has(nameToReplace)) {
                            var newName = nameToReplace + '_' + (jobSectionsCount + 1);
                            updatedJobSearchTrs = updatedJobSearchTrs.replace(new RegExp(nameToReplace, "g"), newName);
                            arrReplaced[arrReplaced.length] = nameToReplace;
                        }
                    }
                }

                var newTableClass = 'section_num_' + (jobSectionsCount + 1);

                var removeButton =
                    '<div class="row remove-employment' + (!booMainApplicant ? ' spouse_field' : '') + '">' +
                    '<div class="col-xl-12">' +
                    '<a href="#" id="' + addButtonId + '" class="btn btn-danger my-2 jobs_section_hide">' +
                    '<i class="fas fa-trash-alt"></i>' + (site_version == 'australia' ? ' Remove employment' : ' Remove Work Experience') +
                    '</a>' +
                    '</div>' +
                    '</div>';

                var copyTable =
                    '<div class="job_section_table ' + newTableClass + '">' +
                    '<hr>' +
                    '<div class="row job_section_header' + (!booMainApplicant ? ' spouse_field' : '') + '">' +
                    '<div class="col-xl-12 name-employment"><span>' + (site_version == 'australia' ? 'Employer' : 'Previous Job') + '</span></div>' +
                    '</div>' +
                    updatedJobSearchTrs +
                    removeButton +
                    '</div>';

                // Create the new tr after the last one
                $(lastTr).after('<div>' + copyTable + '</div>');

                // Hide fields we don't need to duplicate
                $(lastTr).next().find('.field_do_not_duplicate').parents('div.row:first').hide();

                //Reset fields in this new tr
                if(empty(validator)) {
                    $(lastTr).next()
                        .find(':input')
                        .not(':button, :submit, :reset, :hidden')
                        .val('')
                        .removeAttr('checked')
                        .removeAttr('selected')
                    ;
                }

                // Reinit just added field
                _initJobSearchFields(q_id);

                if (empty(validator)) {
                    initJobFields('#' + formId, '.' + newTableClass);
                } else {
                    initJobFields('', '.' + newTableClass);
                }

                // move button
                $('#'+ containerButtonId).insertAfter('.' + parentTableClass +' .job_section_table:last');

                // Init date fields
                initJobSectionDate('.job_section_table.' + newTableClass);

                $('.job_section_table.' + newTableClass).find('select.combo').each(function () {
                    $(this).select2();
                    $(this).on("change", function (e) {
                        validator.element(this);
                    });
                });

                $('.select2-container').css("width", "100%");

                // Show new fields and make them enabled
                var jobSpouseSectionClass = 'job-section-' + qnrSpouseJobSectionId;
                if (parentTableClass == jobSpouseSectionClass && !empty(validator)) {
                    toggleSpouseFields(true, '.' + parentTableClass + ' .' + newTableClass);
                }

                if(typeof(window.centerBottom) == 'function') {
                    centerBottom(".bottom_copyright");
                }
                return false;
            });
        });
    }
}


function toggleSpouseFields(booShow, whereOriginal) {
    var where = whereOriginal;
    if (empty(where)) {
        where = '.spouse_field';
    } else if ((where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        where = '#' + where + ' .spouse_field';
    } else {
        where += ' .spouse_field';
    }

    if(booShow) {
        if(  $(where).is(":visible")){
            // Show only once
            $(where + ' :input').removeAttr('disabled');
            return;
        }

        $(where).show();
        $(where + ' :input').removeAttr('disabled');


        // Highlight just showed fields
        // var els = Ext.select(where, true);
        // els.each(function(el) {
        //     el.highlight("033876", { attr: 'color', duration: 2 });
        // });

        initSpouseJobSection(booShow, whereOriginal);
        initIsPartnerResidentOfNewZealand(whereOriginal);

        toggleBachelorDegree(whereOriginal);
        toggleSpouseLanguageEnglishFields(whereOriginal);
        toggleSpouseLanguageFrenchFields(whereOriginal);
        toggleSpousePostSecondariesField(whereOriginal);
        toggleSpouseJobFields(whereOriginal);
    } else {
        $(where).hide();
        $(where + ' :input').attr('disabled', 'disabled');

        initSpouseJobSection(booShow, whereOriginal);
    }
}

function toggleSpouseJobSection(booShow, where) {
    where = where + ' .job-section-' + qnrSpouseJobSectionId;
    $(where).toggle(booShow);

    if (booShow) {
        $(where + ' :input').removeAttr('disabled');
    } else {
        $(where + ' :input').attr('disabled', 'disabled');
    }

    if(typeof(window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}

function initSpouseJobSection(booForceShow, where) {
    var whereField = where + ' .qf_job_spouse_has_experience';

    $('.qf_job_spouse_has_experience').closest('.qnr-section').toggle(booForceShow);

    // Hide or show fields in relation to
    // current selected value in the radio
    var booShow = $(whereField + ' input:first').is(':checked');
    toggleSpouseJobSection(booShow && booForceShow, where);

    $(whereField + ' input').each(function() {
        $(this).change(function() {
            var booShow = $(whereField + ' input:first').is(':checked');
            toggleSpouseJobSection(booShow && booForceShow, where);
        });
    });
}

function toggleChildrenFields(where) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var val = parseInt($(strPrefix + '.qf_children_count select option:selected').text(), 10);

    $(strPrefix + '.qf_children_age_1').toggle(val >= 1);

    if (empty(strPrefix)) {
        $(strPrefix + '.qf_children_age_2').toggle(val >= 2);
    } else {
        $(strPrefix + '.qf_children_age_2').css('visibility', (!val || val<2) ? 'hidden' : 'visible');
    }

    $(strPrefix + '.qf_children_age_3').toggle(val >= 3);
    $(strPrefix + '.qf_children_age_4').toggle(val >= 4);
    $(strPrefix + '.qf_children_age_5').toggle(val >= 5);
    $(strPrefix + '.qf_children_age_6').toggle(val >= 6);
}

function initChildrenFields(where) {
    toggleChildrenFields(where);

    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    $(strPrefix + '.qf_children_count select').change(function() {
        toggleChildrenFields(where);
    });
}
function toggleNocField(where) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var el = $(strPrefix + ' .qf_work_offer_of_employment :input:first');
    var booShowNOC = false;

    if (!$(el).length) {
        return;
    }

    if ($(el).prop("tagName").toLowerCase() === 'select') {
        var selText  = $(el).find('option:selected').attr('data-val');
        booShowNOC = selText === 'yes';
    } else {
        booShowNOC = $(el).is(':checked');
    }

    $(strPrefix + ' [class*="qf_work_noc"]').toggle(booShowNOC);
}

function initNocField(where) {
    toggleNocField(where);

    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    $(strPrefix + '.qf_work_offer_of_employment :input').change(function() {
        toggleNocField(where);
    });
}

function togglePostSecondariesField(where) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var el = $(strPrefix + ' .qf_study_previously_studied :input:first');
    var booShowField = false;

    if (!$(el).length) {
        return;
    }

    if ($(el).prop("tagName").toLowerCase() === 'select') {
        var selText  = $(el).find('option:selected').attr('data-val');
        booShowField = selText === 'yes';
    } else {
        booShowField = $(el).is(':checked');
    }

    $(strPrefix + ' [class*="qf_education_studied_in_canada_period"]').toggle(booShowField);
}

function toggleSpousePostSecondariesField(where) {
    var strPrefix = '';
    if (!empty(where) && (where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        strPrefix = '#' + where;
    } else {
        strPrefix = where;
    }

    var el = $(strPrefix + ' .qf_education_spouse_previously_studied :input:first');
    var booShowField = false;

    if (!$(el).length) {
        return;
    }

    if ($(el).prop("tagName").toLowerCase() === 'select') {
        var selText  = $(el).find('option:selected').attr('data-val');
        booShowField = selText === 'yes';
    } else {
        booShowField = $(el).is(':checked');
    }

    $(strPrefix + ' [class*="qf_education_spouse_studied_in_canada_period"]').toggle(booShowField);
}

function initPostSecondariesField(where) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';

    togglePostSecondariesField(where);

    $(strPrefix + '.qf_study_previously_studied :input').change(function() {
        togglePostSecondariesField(where);
    });

    toggleSpousePostSecondariesField(where);

    $(strPrefix + '.qf_education_spouse_previously_studied :input').change(function() {
        toggleSpousePostSecondariesField(where);
    });
}

function initWorkFields() {
    $('.qf_work_temporary_worker').show();
    $('.qf_work_years_worked').hide();
    $('.qf_work_currently_employed').hide();
    $('.qf_work_leave_employment').hide();
    $('.qf_study_previously_studied').show();
    $('.qf_work_offer_of_employment').show();
    
    $('.qf_work_temporary_worker input').each(function() {
        $(this).change(function() {
            var booChecked = $('.qf_work_temporary_worker input:first').is(':checked');
            $('.qf_work_years_worked').toggle(booChecked);
            $('.qf_work_currently_employed').toggle(booChecked);

            if(booChecked) {
                $('.qf_work_leave_employment').toggle(
                    $('.qf_work_currently_employed input:last').is(':checked')
                );
            } else {
                $('.qf_work_leave_employment').hide();
            }
        });
    });

    $('.qf_work_currently_employed input').each(function() {
        $(this).change(function() {
            $('.qf_work_leave_employment').toggle(
                $('.qf_work_currently_employed input:last').is(':checked')
            );
        });
    });
}

function toggleFamilyRelations() {
    var combo = $('.qf_family_relationship select');

    var booShowRelativeWish = false;
    var booShowFullTimeStudent = false;
    var booShowOtherFields = false;
    switch ($(combo).val()) {
        case '138': // mother_or_father
            booShowRelativeWish = true;
            booShowFullTimeStudent = true;
            break;

        case '139': // daughter_or_son
            booShowRelativeWish = true;
            booShowOtherFields = true;
            break;

        case '140': // sister_or_brother
            break;

        case '141': // niece_or_nephew
            break;

        case '142': // grandmother
            break;

        case '143': // granddaughter
            booShowRelativeWish = true;
            booShowOtherFields = true;
            break;

        case '144': // aunt
            break;

        case '145': // spouse
            booShowRelativeWish = true;
            break;

        default:
            // None
    }
    $('.qf_family_relative_wish_to_sponsor').toggle(booShowRelativeWish);


    $('.qf_family_currently_fulltime_student').toggle(booShowFullTimeStudent);
    // Check also qf_family_currently_fulltime_student
    $('.qf_family_been_fulltime_student').toggle(
        $('.qf_family_currently_fulltime_student input:first').is(':checked')
    );

    // Check also qf_family_relative_wish_to_sponsor
    if(booShowOtherFields) {
        if(!$('.qf_family_relative_wish_to_sponsor input:first').is(':checked')) {
            booShowOtherFields = false;
        }
    }
    $('.qf_family_sponsor_age').toggle(booShowOtherFields);
    $('.qf_family_employment_status').toggle(booShowOtherFields);
    $('.qf_family_sponsor_financially_responsible').toggle(booShowOtherFields);
    $('.qf_family_sponsor_income').toggle(booShowOtherFields);
}

function initFamilyRelations() {
    /* A */ $('.qf_family_have_blood_relative').show();
    /* B */ $('.qf_family_relationship').hide();
    /* C */ $('.qf_family_relative_wish_to_sponsor').hide();
    /* D */ $('.qf_family_sponsor_age').hide();
    /* E */ $('.qf_family_employment_status').hide();
    /* F */ $('.qf_family_sponsor_financially_responsible').hide();
    /* G */ $('.qf_family_sponsor_income').hide();
    /* H */ $('.qf_family_currently_fulltime_student').hide();
    /* I */ $('.qf_family_been_fulltime_student').hide();

    // Listen for main radio change
    $('.qf_family_have_blood_relative input').each(function() {
        $(this).change(function() {
            var booChecked = $('.qf_family_have_blood_relative input:first').is(':checked');
            $('.qf_family_relationship').toggle(booChecked);

            if(!booChecked) {
                // Hide all
                /* B */ $('.qf_family_relationship').hide();
                /* C */ $('.qf_family_relative_wish_to_sponsor').hide();
                /* D */ $('.qf_family_sponsor_age').hide();
                /* E */ $('.qf_family_employment_status').hide();
                /* F */ $('.qf_family_sponsor_financially_responsible').hide();
                /* G */ $('.qf_family_sponsor_income').hide();
                /* H */ $('.qf_family_currently_fulltime_student').hide();
                /* I */ $('.qf_family_been_fulltime_student').hide();
            } else {
                toggleFamilyRelations();
            }
        });
    });

    // Listen for combo changes
    $('.qf_family_relationship select').change(function() {
        toggleFamilyRelations();
    });

    $('.qf_family_currently_fulltime_student input').each(function() {
        $(this).change(function() {
            $('.qf_family_been_fulltime_student').toggle(
                $('.qf_family_currently_fulltime_student input:first').is(':checked')
            );
        });
    });


    $('.qf_family_relative_wish_to_sponsor input').each(function() {
        $(this).change(function() {
            var booCheckedYes = $('.qf_family_relative_wish_to_sponsor input:first').is(':checked');

            var comboVal = $('.qf_family_relationship select').val();

            var booShowOtherFields = false;
            if(booCheckedYes && (comboVal == '139' || comboVal == '143')) {
                booShowOtherFields = true;
            }

            $('.qf_family_sponsor_age').toggle(booShowOtherFields);
            $('.qf_family_employment_status').toggle(booShowOtherFields);
            $('.qf_family_sponsor_financially_responsible').toggle(booShowOtherFields);
            $('.qf_family_sponsor_income').toggle(booShowOtherFields);
        });
    });
}

function toggleAssessment() {
    var booCheckedYes = $('.qf_cat_have_experience input:first').is(':checked');
    $('.qf_cat_managerial_experience').toggle(booCheckedYes);
    $('.qf_cat_staff_number').toggle(booCheckedYes);
    $('.qf_cat_own_this_business').toggle(booCheckedYes);
    $('.qf_cat_annual_sales').toggle(booCheckedYes);
    $('.qf_cat_annual_net_income').toggle(booCheckedYes);
    $('.qf_cat_net_assets').toggle(booCheckedYes);
    toggleAssessmentPercentageOwnership();

    if(typeof(window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}

function toggleAssessmentPercentageOwnership() {
    var booCheckedYes = $('.qf_cat_own_this_business input:first').is(':checked');
    $('.qf_cat_percentage_of_ownership').toggle(booCheckedYes);
}

function initAssessment() {
    /* A */ $('.qf_cat_net_worth').show();
    /* B */ $('.qf_cat_have_experience').hide();
    /* C */ $('.qf_cat_managerial_experience').hide();
    /* D */ $('.qf_cat_staff_number').hide();
    /* E */ $('.qf_cat_own_this_business').hide();
    /* F */ $('.qf_cat_percentage_of_ownership').hide();
    /* G */ $('.qf_cat_annual_sales').hide();
    /* H */ $('.qf_cat_annual_net_income').hide();
    /* I */ $('.qf_cat_net_assets').hide();

    // Listen for combo changes
    $('.qf_cat_net_worth select').change(function() {
        var booShowExperienceRadio = false;
        switch ($(this).val()) {
            case '185': // 300000_to_499999
            case '186': // 500000_to_799999
            case '187': // 800000_to_999999
            case '313': // 1000000_to_1599999
            case '314': // 1600000_and_more
                booShowExperienceRadio = true;
                break;

            default:
                // None
        }

        $('.qf_cat_have_experience').toggle(booShowExperienceRadio);
        if(booShowExperienceRadio) {
            toggleAssessment();
        } else {
            $('.qf_cat_managerial_experience').hide();
            $('.qf_cat_staff_number').hide();
            $('.qf_cat_own_this_business').hide();
            $('.qf_cat_percentage_of_ownership').hide();
            $('.qf_cat_annual_sales').hide();
            $('.qf_cat_annual_net_income').hide();
            $('.qf_cat_net_assets').hide();
        }
    });

    $('.qf_cat_have_experience input').each(function() {
        $(this).change(function() {
            toggleAssessment();
        });
    });

    $('.qf_cat_own_this_business input').each(function() {
        $(this).change(function() {
            toggleAssessmentPercentageOwnership();
        });
    });
}

function toggleJobFields(strPrefix) {
    var val = parseInt($(strPrefix + '.qf_job_location select option:selected').val(), 10);

    // Show provinces if Canada option is selected
    $(strPrefix + '.qf_job_province').toggle(val == 175);
}

function toggleSpouseJobFields(strPrefix) {
    var val = parseInt($(strPrefix + '.qf_job_spouse_location select option:selected').val(), 10);

    // Show provinces if Canada option is selected
    $(strPrefix + '.qf_job_spouse_province').toggle(val == 343);
}

function initJobFields(prefix, suffix) {
    var strPrefix = '';
    var strPrefix2 = '';
    if (empty(prefix) && empty(suffix)) {
        strPrefix = '.job-section-' + qnrJobSectionId + ' .job_section';
        strPrefix2 = '.job-section-' + qnrSpouseJobSectionId + ' .job_section';
    } else if(empty(suffix)) {
        strPrefix = prefix + ' .job-section-' + qnrJobSectionId + ' ';
        strPrefix2 = prefix + ' .job-section-' + qnrSpouseJobSectionId + ' ';
    } else {
        strPrefix = prefix + ' .job-section-' + qnrJobSectionId + ' ' + suffix + ' ';
        strPrefix2 = prefix + ' .job-section-' + qnrSpouseJobSectionId + ' ' + suffix + ' ';
    }

    toggleJobFields(strPrefix);
    $(strPrefix + '.qf_job_location select').change(function() {
        toggleJobFields(strPrefix);
    });

    $(strPrefix + '.qf_job_province select').change(function() {
        $(this).toggleClass('x-form-invalid', empty($(this).val()));
    });

    toggleSpouseJobFields(strPrefix2);
    $(strPrefix2 + '.qf_job_spouse_location select').change(function() {
        toggleSpouseJobFields(strPrefix2);
    });

    $(strPrefix2 + '.qf_job_spouse_province select').change(function() {
        $(this).toggleClass('x-form-invalid', empty($(this).val()));
    });

    // Enable all previously disabled fields
    if ($(strPrefix).is(":visible")) {
        $(strPrefix).find(':input').removeAttr('disabled');
    }
}


// This method is very similar to 2 above, but we parse all job sections at once
function initJobFieldsByTable(tabId) {
    // Find all job sections
    $('#' + tabId + ' .job_container').each(function(index, el) {
        // Hide all province field if country is not Canada
        var val = parseInt($(el).find('.qf_job_location select option:selected').val(), 10);
        $(el).find('.qf_job_province').toggle(val == 175);

        // Listen for country field change
        $(el).find('.qf_job_location select').change(function() {
            $(el).find('.qf_job_province').toggle($(this).val() == 175);
        });

        // Listen for province field change
        $(el).find('.qf_job_province select').change(function() {
            $(this).toggleClass('x-form-invalid', empty($(this).val()));
        });


        // Hide all province field if country is not Canada
        val = parseInt($(el).find('.qf_job_spouse_location select option:selected').val(), 10);
        $(el).find('.qf_job_spouse_province').toggle(val == 343);

        // Listen for country field change
        $(el).find('.qf_job_spouse_location select').change(function() {
            $(el).find('.qf_job_spouse_province').toggle($(this).val() == 343);
        });

        // Listen for province field change
        $(el).find('.qf_job_spouse_province select').change(function() {
            $(this).toggleClass('x-form-invalid', empty($(this).val()));
        });

    });
}

function initQnrFields() {
    jQuery.expr[':'].hiddenByParent = function(a) {
       return jQuery(a).is(':hidden') && jQuery(a).css('display') != 'none';
    };

    initWorkFields();
    initFamilyRelations();
    initAssessment();
}

function toggleCurrentAddressFields(booShow, whereOriginal) {
    var where = whereOriginal;
    if (empty(where)) {
        where = '.current_address';
    } else if ((where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        where = '#' + where + ' .current_address';
    } else {
        where += ' .current_address';
    }


    if (booShow) {
        if ($(where).is(":visible")) {
            // Show only once
            return;
        }

        $(where).show();
        $(where + ' :input').removeAttr('disabled');
    } else {
        $(where).hide();
        $(where + ' :input').attr('disabled', 'disabled');
    }
}

function checkCountryValue(selOption) {
    return site_version == 'australia' ? selOption != '' : selOption == 38;
}

function initCurrentCountry() {
    var wherePrefix = '';
    var whereField = wherePrefix + ' .qf_country_of_residence';
    // Hide or show fields in relation to
    // current selected value in the combobox
    var selOption = $(whereField + ' :input').val();
    toggleCurrentAddressSection(checkCountryValue(selOption), wherePrefix);
    toggleCurrentAddressFields(checkCountryValue(selOption), wherePrefix);

    // Listen for changes in 'Country of current Residence' combo
    $(whereField + ' :input').change(function () {
        var selOption = $(this).val();
        toggleCurrentAddressSection(checkCountryValue(selOption), wherePrefix);
        toggleCurrentAddressFields(checkCountryValue(selOption), wherePrefix);
    });
}

function toggleCurrentAddressSection(booShow, where) {
    if (site_version != 'australia') {
        return;
    }

    where = where + ' .job-section-2';
    $(where).toggle(booShow);

    if (booShow) {
        $(where + ' :input').removeAttr('disabled');
    } else {
        $(where + ' :input').attr('disabled', 'disabled');
    }

    if (typeof(window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}


function initVisaRefusedOrCancelled(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    // Hide or show fields in relation to
    $(wherePrefix + ' .qf_visa_refused_or_cancelled').hide();

    $(wherePrefix + ' .qf_applied_for_visa_before :input').change(function () {
        var booShow;
        if ($(this).prop("tagName").toLowerCase() == 'select') {
            booShow = $(this).val() == '1';
            $(wherePrefix + ' .qf_visa_refused_or_cancelled').toggle(booShow);
        } else {
            booShow = $(wherePrefix + ' .qf_applied_for_visa_before input:first').is(':checked');
            $(wherePrefix + ' .qf_visa_refused_or_cancelled').toggle(booShow);
        }
    });
}


function initDesignatedAreaFields(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_relationship_nature').hide();
    $(wherePrefix + ' .qf_relative_postcode').hide();

    $(wherePrefix + ' .qf_relative_designated_area :input').change(function () {
        toggleDesignatedAreaFields($(this), wherePrefix);
    });

    toggleDesignatedAreaFields($(wherePrefix + ' .qf_relative_designated_area :input'), wherePrefix);
}

function toggleDesignatedAreaFields(elem, wherePrefix) {
    var booShow = false;
    if (elem.length && elem.prop("tagName").toLowerCase() == 'select') {
        booShow = elem.children(':selected').attr('data-val') == 'yes';
    } else {
        booShow = $(wherePrefix + ' .qf_relative_designated_area input:first').is(':checked');
    }

    $(wherePrefix + ' .qf_relationship_nature').toggle(booShow);
    $(wherePrefix + ' .qf_relative_postcode').toggle(booShow);
}

function initEducationalQualifications(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_education_additional_qualification_list').hide();


    $(wherePrefix + ' .qf_education_additional_qualification :input').change(function () {
        toggleEducationalQualifications($(this), wherePrefix);
    });

    toggleEducationalQualifications($(wherePrefix + ' .qf_education_additional_qualification :input'), wherePrefix);
}

function toggleEducationalQualifications(elem, wherePrefix) {
    var booShow = $(wherePrefix + ' .qf_education_additional_qualification input').is(':checked');
    $(wherePrefix + ' .qf_education_additional_qualification_list').toggle(booShow);
}

function initSpouseEducationalQualifications(where) {
    var wherePrefix = where;
    if (wherePrefix.charAt(0) != '#') {
        wherePrefix = empty(where) ? '' : '#' + where;
    }

    if ($(wherePrefix + ' .qf_education_spouse_additional_qualification_list').length) {
        $(wherePrefix + ' .qf_education_spouse_additional_qualification_list').hide();
    }

    $(wherePrefix + ' .qf_education_spouse_additional_qualification :input').change(function () {
        toggleSpouseEducationalQualifications($(this), wherePrefix);
    });

    toggleSpouseEducationalQualifications($(wherePrefix + ' .qf_education_additional_qualification :input'), wherePrefix);
}

function toggleSpouseEducationalQualifications(elem, wherePrefix) {
    var booShow = $(wherePrefix + ' .qf_education_spouse_additional_qualification input').is(':checked');
    $(wherePrefix + ' .qf_education_spouse_additional_qualification_list').toggle(booShow);
}

function initOthers(where){
    var wherePrefix = empty(where) ? '' : '#' + where;
    // Hide or show fields at loading that depends on Other checkbox

    if($(wherePrefix + ' input[data-val="other"]').is(':checked')){
        $(wherePrefix + ' .qf_area_of_interest_other1').show();
    }else{
        $(wherePrefix + ' .qf_area_of_interest_other1').hide();
    }

    $(wherePrefix + ' input[data-val="other"]').change(function (){
        var booShow = $(wherePrefix + ' input[data-val="other"]').is(':checked');
        $(wherePrefix + ' .qf_area_of_interest_other1').toggle(booShow);
    });
}

function initProfessionalProgramsBlock(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;

    function toggleProfessionalProgramsBlock () {
        var booShow = false;
        var el = $(wherePrefix + ' .qf_programs_completed_professional_year :input');
        if ($(el).prop("tagName").toLowerCase() == 'select') {
            booShow = $(el).find('option:selected').attr('data-val') == 'yes';
        } else {
            booShow = $(el).first().is(':checked')
        }
        $(wherePrefix + ' .qf_programs_name').toggle(booShow);
        $(wherePrefix + ' .qf_programs_year_completed').toggle(booShow);
    }

    if (!$(wherePrefix + ' .qf_programs_completed_professional_year').length) {
        return;
    }

    // Hide or show fields in relation to
    $(wherePrefix + ' .qf_programs_completed_professional_year :input').each(function () {
        $(this).change(function () {
            toggleProfessionalProgramsBlock();
        });
    });
    toggleProfessionalProgramsBlock();
}

function checkMaritalComboValue(selOption) {
    if(site_version == 'australia') {
        return (selOption == 7 || selOption == 8 || selOption == 356);
    } else {
        return (selOption == 7 || selOption == 12);
    }
}

function initMaritalStatus() {
    var wherePrefix = '';
    var whereField = wherePrefix + ' .qf_marital_status';
    // Hide or show fields in relation to
    // current selected value in the combobox
    var selOption = $(whereField + ' :input').val();

    toggleSpouseFields(checkMaritalComboValue(selOption), wherePrefix);
    toggleSpousePersonalSection(checkMaritalComboValue(selOption), wherePrefix);

    // Listen for changes in 'Marital Status' combo
    $(whereField + ' :input').change(function () {
        var selOption = parseInt($(this).val(), 10);
        toggleSpouseFields(checkMaritalComboValue(selOption), wherePrefix);
        toggleSpousePersonalSection(checkMaritalComboValue(selOption), wherePrefix);
        toggleAreaOfInterestSections(wherePrefix);
    });
}

function toggleSpousePersonalSection(booShow, where) {
    where = where + ' .job-section-3';
    $(where).toggle(booShow);

    if (booShow) {
        $(where + ' :input').removeAttr('disabled');
    } else {
        $(where + ' :input').attr('disabled', 'disabled');
    }

    if (typeof(window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}

function initIsPartnerResidentOfNewZealand(where) {
    if(site_version != 'australia') {
        return;
    }

    var wherePrefix = '';
    if (!empty(where) && (where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        wherePrefix = '#' + where;
    } else {
        wherePrefix = where;
    }

    function togglePartnerResidentOfNewZealand () {
        var booShow = false;
        var el = $(wherePrefix + ' .qf_spouse_is_resident_of_australia :input');
        if ($(el).length > 0) {
            if ($(el).prop("tagName").toLowerCase() == 'select') {
                booShow = $(el).find('option:selected').attr('data-val') == 'no';
            } else {
                booShow = $(el).last().is(':checked')
            }
        }
        $(wherePrefix + ' .qf_spouse_is_resident_of_new_zealand').toggle(booShow);
    }

    // Hide or show fields in relation to
    $(wherePrefix + ' .qf_spouse_is_resident_of_new_zealand').hide();
    $(wherePrefix + ' .qf_spouse_is_resident_of_australia :input').each(function () {
        $(this).change(function () {
            togglePartnerResidentOfNewZealand();
        });
    });
    togglePartnerResidentOfNewZealand ();
}

function toggleAreaOfInterestSections(where) {
    if (site_version != 'australia') {
        var areaOfInterestChecked = $(where + ' .qf_area_of_interest :checked');
        var booShow = false;
        areaOfInterestChecked.each(function() {
            if ($(this).attr('data-readable-value') == 'study') {
                booShow = true;
            }
        });

        $(where + ' .qf_study_have_admission').toggle(booShow);

        return;
    }

    var selOption = parseInt($(where + ' .qf_marital_status :input').val(), 10),
        booFirstCheckboxChecked = $(where + ' .qf_area_of_interest input').eq(0).is(':checked'),
        arrToCheck = [];

    if ($(where + ' .qf_area_of_interest').length) {
        arrToCheck = [
            [where + ' .job-section-5', booFirstCheckboxChecked],
            [where + ' .job-section-6', booFirstCheckboxChecked],
            [where + ' .job-section-7', booFirstCheckboxChecked],
            [where + ' .job-section-8', booFirstCheckboxChecked && checkMaritalComboValue(selOption)],
            [where + ' .job-section-11', $(where + ' .qf_area_of_interest input').eq(1).is(':checked')],
            [where + ' .job-section-4', $(where + ' .qf_area_of_interest input').eq(3).is(':checked')],
            [where + ' .job-section-9', $(where + ' .qf_area_of_interest input').eq(5).is(':checked')],
            [where + ' .job-section-10', $(where + ' .qf_area_of_interest input').eq(6).is(':checked')],
            [where + ' .job-section-12', booFirstCheckboxChecked],
            [where + ' .qf_date_permanent_residency_obtained', $(where + ' .qf_area_of_interest input').eq(7).is(':checked')],
            [where + ' .qf_area_of_interest_other'      , $(where + ' .qf_area_of_interest input').eq(8).is(':checked')]
        ];
    }

    for (var i = 0; i < arrToCheck.length; i++) {
        $(arrToCheck[i][0]).toggle(arrToCheck[i][1]);
        if (arrToCheck[i][1]) {
            $(arrToCheck[i][0] + ' :input').removeAttr('disabled');
        } else {
            $(arrToCheck[i][0] + ' :input').attr('disabled', 'disabled');
        }
    }

    if (typeof toggleSteps != 'undefined') {
        toggleSteps();
    }

    if (typeof(window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}

function initAreaOfInterest(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_area_of_interest input').each(function () {
        $(this).change(function () {
            toggleAreaOfInterestSections(wherePrefix);
        });
    });
    toggleAreaOfInterestSections(wherePrefix);
}

function checkHighestQualification(selOption) {
    return (selOption == 21 || selOption == 22 || selOption == 38 || selOption == 39);
}

function initHighestQualification(where) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_education_name_bachelor_degree').hide();
    $(wherePrefix + ' .qf_education_spouse_name_bachelor_degree').hide();

    $(wherePrefix + ' .qf_education_highest_qualification :input').change(function () {
        var selOption = parseInt($(this).val(), 10);
        $(wherePrefix + ' .qf_education_name_bachelor_degree').toggle(checkHighestQualification(selOption));
    });

    $(wherePrefix + ' .qf_education_spouse_highest_qualification :input').change(function () {
        toggleBachelorDegree(wherePrefix);
    });
}

function toggleBachelorDegree(wherePrefix) {
    if (site_version != 'australia') {
        return;
    }

    var selOption = parseInt($(wherePrefix + ' .qf_education_spouse_highest_qualification :input').val(), 10);
    $(wherePrefix + ' .qf_education_spouse_name_bachelor_degree').toggle(checkHighestQualification(selOption));
}

function initCountryOfEducation(where) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_education_duration_of_course').hide();
    $(wherePrefix + ' .qf_education_was_course_in_regional').hide();

    // Listen for changes in 'Country of Education' combo
    $(wherePrefix + ' .qf_education_country_of_education :input').change(function () {
        var selOption = parseInt($(this).val(), 10);
        $(wherePrefix + ' .qf_education_duration_of_course').toggle(checkCountryValue(selOption));
        $(wherePrefix + ' .qf_education_was_course_in_regional').toggle(checkCountryValue(selOption));
    });
}

function initSpouseEducationFields() {
    // Don't modify the place of the fields
    // $('.education.spouse_field').insertAfter('div.education.main:last');
}

function initSpouseLanguageFields() {
    // Don't modify the place of the fields
    // $('.lang.spouse_field').insertAfter('div.lang.main:last');
}

function initLanguage(where) {
    var wherePrefix = empty(where) ? '' : '#' + where;

    if (site_version != 'australia') {
        $(wherePrefix + ' .qf_language_english_done :input').each(function () {
            $(this).change(function () {
                toggleLanguageEnglishFields(wherePrefix);
            });
        });
        toggleLanguageEnglishFields(wherePrefix);

        $(wherePrefix + ' .qf_language_french_done :input').each(function () {
            $(this).change(function () {
                toggleLanguageFrenchFields(wherePrefix);
            });
        });
        toggleLanguageFrenchFields(wherePrefix);

        $(wherePrefix + ' .qf_language_spouse_english_done :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageEnglishFields(wherePrefix);
            });
        });
        toggleSpouseLanguageEnglishFields(wherePrefix);

        $(wherePrefix + ' .qf_language_spouse_french_done :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageFrenchFields(wherePrefix);
            });
        });
        toggleSpouseLanguageFrenchFields(wherePrefix);
    } else {
        // Hide or show fields in relation to
        $(wherePrefix + ' .language').hide();
        $(wherePrefix + ' .qf_language_have_taken_test_on_english :input').each(function () {
            $(this).change(function () {
                toggleLanguageFields(wherePrefix);
            });
        });
        $(wherePrefix + ' .qf_language_spouse_have_taken_test_on_english :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageFields(wherePrefix);
            });
        });
        toggleLanguageFields(wherePrefix);
        toggleSpouseLanguageFields(wherePrefix);
    }
}

function toggleLanguageEnglishFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_english_done :input:first'),
        booShowIELTS = false,
        booShowCELPIP = false,
        booShowGeneral = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowIELTS = selText === 'ielts';
            booShowCELPIP = selText === 'celpip';
            booShowGeneral = selText === 'no';
        } else {
            booShowIELTS = $(el).is(':checked');
        }
    }
    $(wherePrefix + ' [class*="qf_language_english_ielts"]').toggle(booShowIELTS);
    $(wherePrefix + ' [class*="qf_language_english_celpip"]').toggle(booShowCELPIP);
    $(wherePrefix + ' [class*="qf_language_english_general"]').toggle(booShowGeneral);
}
function toggleSpouseLanguageEnglishFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_spouse_english_done :input:first'),
        booShowIELTS = false,
        booShowCELPIP = false,
        booShowGeneral = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowIELTS = selText === 'ielts';
            booShowCELPIP = selText === 'celpip';
            booShowGeneral = selText === 'no';
        } else {
            booShowIELTS = $(el).is(':checked');
        }
    }
    $(wherePrefix + ' [class*="qf_language_spouse_english_ielts"]').toggle(booShowIELTS);
    $(wherePrefix + ' [class*="qf_language_spouse_english_celpip"]').toggle(booShowCELPIP);
    $(wherePrefix + ' [class*="qf_language_spouse_english_general"]').toggle(booShowGeneral);
}

function toggleLanguageFrenchFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_french_done :input:first'),
        booShowTEF = false,
        booShowGeneral = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowTEF = selText === 'yes';
            booShowGeneral = selText === 'no' || selText === 'not_sure';
        } else {
            booShowTEF = $(el).is(':checked');
        }
    }
    $(wherePrefix + ' [class*="qf_language_french_tef"]').toggle(booShowTEF);
    $(wherePrefix + ' [class*="qf_language_french_general"]').toggle(booShowGeneral);
}

function toggleSpouseLanguageFrenchFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_spouse_french_done :input:first'),
        booShowTEF = false,
        booShowGeneral = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowTEF = selText === 'yes';
            booShowGeneral = selText === 'no' || selText === 'not_sure';
        } else {
            booShowTEF = $(el).is(':checked');
        }
    }
    $(wherePrefix + ' [class*="qf_language_spouse_french_tef"]').toggle(booShowTEF);
    $(wherePrefix + ' [class*="qf_language_spouse_french_general"]').toggle(booShowGeneral);
}

function toggleLanguageFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_have_taken_test_on_english :input:first'),
        booShow = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() == 'select') {
            booShow = $(el).find('option:selected').attr('data-val') == 'yes';
        } else {
            booShow = $(el).is(':checked')
        }
    }
    $(wherePrefix + ' .main.language').toggle(booShow);
}

function toggleSpouseLanguageFields(wherePrefix) {
    var el = $(wherePrefix + ' .qf_language_spouse_have_taken_test_on_english :input:first'),
        booShow = false;

    if (el && el.length) {
        if ($(el).prop("tagName").toLowerCase() == 'select') {
            booShow = $(el).find('option:selected').attr('data-val') == 'yes';
        } else {
            booShow = $(el).is(':checked')
        }
    }

    $(wherePrefix + ' .spouse_field.language').toggle(booShow);
}

function initAssessmentFields(pid, where) {
    $(where + ' .assessment_section :input').each(function () {
        $(this).change(function () {
            toggleAssessmentFields(pid, where)
        });
    });
    toggleAssessmentFields(pid, where);
}

function toggleAssessmentFields(pid, where) {
    var el = $(where + ' .assessment_section :input:last'),
        booShow = $(el).is(':checked');

    $('#p_' + pid + '_field_visa').parents('.field_visa_container').toggle(booShow);
}

function initExperienceBusiness(where) {
    $(where + ' .qf_have_experience_in_managing :input').each(function () {
        $(this).change(function () {
            toggleExperienceCombo(where)
        });
    });
    toggleExperienceCombo(where);
}

function toggleExperienceCombo(where) {
    var el = $(where + ' .qf_have_experience_in_managing :input:first'),
        booShow = false;

    if (el.length) {
        if ($(el).prop("tagName").toLowerCase() == 'select') {
            booShow = $(el).find('option:selected').attr('data-val') == 'yes';
        } else {
            booShow = $(el).is(':checked')
        }

        $(where + ' .qf_was_the_turnover_greater').toggle(booShow);
    }
}

function addCommas(n) {
    var parts=n.toString().split(".");
    return parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",") + (parts[1] ? "." + parts[1] : "");
}
