/**
 * Initialize quicktips
 */
var arrCustomExtjsFields;

function initQnrFieldTips() {
    var obj = Ext.query('.field-help-tip');
    for (var i = 0; i < obj.length; i++) {
        new Ext.ToolTip({
            target: obj[i].id,
            width: 500,
            cls: 'not-bold-header',
            dismissDelay: 0,
            header: true,
            trackMouse: true,
            contentEl: 'content_' + obj[i].id
        });
    }
    Ext.QuickTips.init();

    $(document).on('click', '.x-tip a', function () {
        window.open($(this).attr('href'));
        return false;
    });
}

/**
 * Initialize select fields
 */
function initSelectFields(booReset) {
    booReset = booReset || false;
    $('.profile-select.replace-select').each(function () {
        var el = $(this);
        var elId = el.attr('id');
        var val = $('#' + elId).val();
        var disabled = el.is('[disabled]');
        if (disabled) {
            el.parent().css('pointer-events', 'none');
        }

        var cls = el.attr('class').replace(/\s*replace-select\s*/, ' replaced-select ');

        var converted = new Ext.form.ComboBox({
            typeAhead: true,
            triggerAction: disabled ? 'query' : 'all',
            editable: !disabled,
            cls: cls,
            transform: el.attr('id'),
            forceSelection: true,
            listeners: {
                select: function () {
                    var this_jquery = $(Ext.get(this.getEl()).dom);
                    var hidden = this_jquery.siblings('input[type="hidden"]');
                    hidden.trigger('change');
                    this_jquery.trigger('change');
                }
            }

        });

        if (val != '') {
            converted.setValue(val);
        }

        if (booReset) {
            converted.reset();
        }
    });
}


/**
 * Initialize 'referred by' fields
 */
function initQnrReferred(booUseDefaultValue) {
    var els = Ext.select('select.profile-referred-by', true);

    els.each(function (el) {
        var newId = 'referred_by_' + el.id;

        var referred = new Ext.form.ComboBox({
            id: newId,
            cls: el.dom.className,
            width: 300,
            typeAhead: true,
            emptyText: 'Type or select from the list...',
            triggerAction: 'all',
            transform: el.id
        });

        // Reset value for QNR page
        if (!booUseDefaultValue) {
            referred.setValue('');
        }

        // Save in global array - to use it during checking
        arrCustomExtjsFields[arrCustomExtjsFields.length] = newId;
    });
}

/**
 * Initialize search fields
 */
function _initJobSearchFields(booProspect) {
    if (empty(Ext.ux.NOCSearchField)) {
        return [];
    }

    var searchFieldPrefix = booProspect ? 'job_search_p_' : 'job_search_q_';
    $('.qf_job_noc input:not([disabled]), .qf_job_spouse_noc input:not([disabled])').each(function () {
        var el = $(this);
        var elId = el.attr('id');

        var newNocId = 'job_search_' + elId;
        el.removeAttr('disabled');

        new Ext.ux.NOCSearchField({
            id: newNocId,
            applyTo: elId,
            appliedToId: elId,

            NOCSearchFieldOnRecordClick: function (record) {
                // Update this combobox value
                this.setValue(record.data['code']);

                // Update 'job title' combobox value
                if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.jobSearchFieldId) {
                    var parentTd = el.parents('td:first');
                    var checkId = parentTd.hasClass('qf_job_spouse_noc') ? arrProspectSettings.jobSpouseSearchFieldId : arrProspectSettings.jobSearchFieldId;

                    var match = newNocId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)$', "i"));
                    var match2 = newNocId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)_(\\d+)$', "i"));

                    var jobSearchField;
                    if (match !== null) {
                        jobSearchField = Ext.getCmp(searchFieldPrefix + match[1] + '_field_' + checkId);
                        if (jobSearchField) {
                            jobSearchField.setValue(record.data['title']);
                        }
                    } else if (match2 !== null) {
                        jobSearchField = Ext.getCmp(searchFieldPrefix + match2[1] + '_field_' + checkId + '_' + match2[3]);
                        if (jobSearchField) {
                            jobSearchField.setValue(record.data['title']);
                        }
                    }
                }
            },

            NOCSearchFieldOnAfterRender: function () {
                var this_jquery = $(Ext.get(this.getEl()).dom);
                var td = this_jquery.closest('td');

                if (!td.hasClass('has_external_links')) {
                    var links = [];

                    links.push('<a href="#" class="external_noc_info noc_url_details bluelink" target="_blank">' + _('Details') + '</a>');
                    links.push('<a href="#" class="external_noc_info noc_url_education_job_requirements bluelink" target="_blank">' + _('Prevailing wages') + '</a>');
                    links.push('<a href="#" class="external_noc_info noc_url_jobs bluelink" target="_blank">' + _('Jobs') + '</a>');
                    links.push('<a href="#" class="external_noc_info noc_url_outlook bluelink" target="_blank">' + _('Outlook') + '</a>');
                    links.push('<a href="#" class="external_noc_info noc_url_prevailing bluelink" target="_blank">' + _('Education & Job Requirements') + '</a>');

                    td.addClass('has_external_links');
                    td.append('<div style="width:450px; padding-top:2px;">&nbsp;&nbsp;' + links.join('&nbsp;&nbsp;') + '</div>');

                    td.find('a.external_noc_info').on('click', function () {
                        var type = '';
                        if ($(this).hasClass('noc_url_details')) {
                            type = 'details';
                        } else if ($(this).hasClass('noc_url_education_job_requirements')) {
                            type = 'wages';
                        } else if ($(this).hasClass('noc_url_jobs')) {
                            type = 'jobs';
                        } else if ($(this).hasClass('noc_url_outlook')) {
                            type = 'outlook';
                        } else if ($(this).hasClass('noc_url_prevailing')) {
                            type = 'job_requirements';
                        }

                        if (!empty(type)) {
                            var job;
                            if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.jobSearchFieldId) {
                                var parentTd = el.parents('td:first');
                                var checkId = parentTd.hasClass('qf_job_spouse_noc') ? arrProspectSettings.jobSpouseSearchFieldId : arrProspectSettings.jobSearchFieldId;

                                var match = newNocId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)$', "i"));
                                var match2 = newNocId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)_(\\d+)$', "i"));

                                var jobSearchField;
                                if (match !== null) {
                                    jobSearchField = Ext.getCmp(searchFieldPrefix + match[1] + '_field_' + checkId);
                                } else if (match2 !== null) {
                                    jobSearchField = Ext.getCmp(searchFieldPrefix + match2[1] + '_field_' + checkId + '_' + match2[3]);
                                }

                                if (jobSearchField) {
                                    job = jobSearchField.getValue();
                                }
                            }

                            var noc = Ext.getCmp(newNocId).getValue();
                            Ext.Ajax.request({
                                url: baseUrl + '/qnr/index/get-noc-url-by-code',
                                params: {
                                    type: Ext.encode(type),
                                    noc: Ext.encode(noc),
                                    job: Ext.encode(job)
                                },

                                success: function (f) {
                                    var result = Ext.decode(f.responseText);
                                    if (result.success) {
                                        Ext.ux.Popup.show(result.url, true);
                                    } else {
                                        Ext.getBody().unmask();
                                        Ext.simpleConfirmation.error(result.msg);
                                    }
                                },

                                failure: function () {
                                    Ext.getBody().unmask();
                                    Ext.simpleConfirmation.error(_("Cannot generate NOC url."));
                                }
                            });
                        }

                        return false;
                    });

                    this.NOCSearchFieldOnKeyUp();
                }
            },

            NOCSearchFieldOnKeyUp: function () {
                var noc = this.getValue();
                var this_jquery = $(Ext.get(this.getEl()).dom);
                var td = this_jquery.closest('td');

                if (noc != '' && typeof links != 'undefined') {
                    td.find('a.external_noc_info').show();
                } else {
                    td.find('a.external_noc_info').hide();
                }
            }
        });

        el.removeClass('qf_job_noc');
        el.addClass('qf_job_noc_replaced');
    });


    var arrCustomExtjsFields = [];
    $('input.job_search:not([disabled])').each(function () {
        var el = $(this);
        var elId = el.attr('id');


        var newId = 'job_search_' + elId;
        el.removeAttr('disabled');

        var jobTitle = new Ext.ux.NOCSearchField({
            id: newId,
            applyTo: elId,
            appliedToId: elId,
            NOCSearchFieldParamNameIsNOC: 0,
            NOCSearchFieldWidth: 300,
            NOCSearchFieldDisplayField: 'title',
            NOCSearchFieldEmptyText: 'Type to search for job title...',
            NOCSearchFieldEmptyTextOnHover: 'Type to search for job title...',

            getLinkedNOCField: function () {
                var jobNocField;

                if (typeof arrProspectSettings != 'undefined' && arrProspectSettings.jobNocSearchFieldId) {
                    var parentTd = el.parents('td:first');
                    var checkId = parentTd.hasClass('qf_job_spouse_title') ? arrProspectSettings.jobSpouseNocSearchFieldId : arrProspectSettings.jobNocSearchFieldId;

                    var match = newId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)$', "i"));
                    var match2 = newId.match(new RegExp('^' + searchFieldPrefix + '(\\d+)_field_(\\d+)_(\\d+)$', "i"));

                    if (match !== null) {
                        jobNocField = Ext.getCmp(searchFieldPrefix + match[1] + '_field_' + checkId);
                    } else if (match2 !== null) {
                        jobNocField = Ext.getCmp(searchFieldPrefix + match2[1] + '_field_' + checkId + '_' + match2[3]);
                    }
                }

                return jobNocField;
            },

            NOCSearchFieldOnRecordClick: function (record) {
                // Update this field's value
                this.setValue(record.data['title']);

                // Update NOC field's value
                var jobNocField = this.getLinkedNOCField();
                if (jobNocField) {
                    jobNocField.setValue(record.data['code']);
                }
            }
        });

        if (site_version == 'australia') {
            jobTitle.el.dom.style.backgroundColor = "#FFF200";
            jobTitle.el.dom.style.backgroundImage = "none";
        } else if (!booProspect) {
            jobTitle.el.dom.style.backgroundColor = "#FFFA96";
            jobTitle.el.dom.style.backgroundImage = "none";
        }

        el.removeClass('job_search');
        el.removeClass('job_spouse_search');
        el.addClass('job_search_replaced');

        arrCustomExtjsFields[arrCustomExtjsFields.length] = newId;
    });

    // Also init 'job + noc' fields
    var arrJobAndNocFields = initJobAndNocFields();
    return arrCustomExtjsFields.concat(arrJobAndNocFields);
}

function initJobAndNocFields() {
    var arrJobAndNocFields = [];
    var nocReader = new Ext.data.JsonReader({
        root: 'rows',
        totalProperty: 'totalCount',
        id: 'code'
    }, [
        {name: 'code', mapping: 'noc_code'},
        {name: 'title', mapping: 'noc_job_title'},
        {name: 'job_and_code', mapping: 'noc_job_and_code'}
    ]);

    $('input.job_and_noc_search').each(function () {
        var el = $(this);
        var elId = el.attr('id');


        var newId = 'job_and_noc_search_' + elId;
        el.removeAttr('disabled');

        var ds = new Ext.data.Store({
            baseParams: {
                booSearchByCodeAndJob: 1
            },
            proxy: new Ext.data.HttpProxy({
                url: topBaseUrl + '/qnr/index/search',
                method: 'post'
            }),

            reader: nocReader
        });

        // Custom rendering Template
        var resultTpl = new Ext.XTemplate(
            '<tpl for=".">',
            '<div class="x-combo-list-item search-item">' +
            '{job_and_code:this.highlightSearch}' +
            '</div>',
            '</tpl>', {
                highlightSearch: function (highlightedRow) {
                    var data = ds.reader.jsonData;
                    for (var i = 0, len = data.search.length; i < len; i++) {
                        var val = data.search[i];
                        highlightedRow = highlightedRow.replace(
                            new RegExp('(' + preg_quote(val) + ')', 'gi'),
                            "<b style='background-color: #FFFF99;'>$1</b>"
                        );
                    }
                    return highlightedRow;
                }
            }
        );

        var jobTitle = new Ext.form.ComboBox({
            id: newId,
            applyTo: elId,
            store: ds,
            displayField: 'job_and_code',
            typeAhead: false,
            emptyText: 'Type to search for job title...',
            loadingText: 'Searching...',
            cls: 'profile-job-search with-right-border',
            width: 400,
            listWidth: 550,
            listClass: 'no-pointer',
            pageSize: 10,
            minChars: 1,
            hideTrigger: true,
            tpl: resultTpl,
            itemSelector: 'div.x-combo-list-item',
            onSelect: function (record) {
                // Update this combobox value
                this.setValue(record.data['job_and_code']);

                // Hide the search list
                this.collapse();
            }
        });

        jobTitle.el.dom.style.backgroundColor = "#FFF200";
        jobTitle.el.dom.style.backgroundImage = "none";

        el.removeClass('job_and_noc_search');
        el.removeClass('job_and_noc_spouse_search');
        el.addClass('job_and_noc_search_replaced');

        arrJobAndNocFields[arrJobAndNocFields.length] = newId;
    });

    return arrJobAndNocFields;
}

var showConfirmationMsg = function (msg, booError) {
    if (booError) {
        Ext.simpleConfirmation.error(msg);
    } else {
        Ext.simpleConfirmation.success(msg);
    }
};

var checkJobSearchValid = function (formId) {
    var booCorrect = true;

    // Find all job search fields for current form
    var els = Ext.select('#' + formId + ' .profile-job-search', true);
    els.each(function (el) {
        var jobField = Ext.getCmp('job_search_' + el.id);
        if (jobField && jobField.isVisible() && $('#' + el.id).parents(':hidden').length === 0 && empty(jobField.getValue())) {
            // Mark as invalid
            jobField.markInvalid();
            booCorrect = false;
        }
    });

    $('#' + formId + ' .qf_job_province select, #' + formId + ' .qf_job_spouse_province select').each(
        function (index, el) {
            var booAddErrorClass = false;
            if ($(el).is(':visible') && $(el).parents(':hidden').length === 0 && empty($(el).val())) {
                booCorrect = false;
                booAddErrorClass = true;
            }

            $(el).toggleClass('x-form-invalid', booAddErrorClass);
        }
    );

    return booCorrect;
};

function initJobSectionDate(where) {
    var els = Ext.select(where + ' input.datepicker', true);
    els.each(function (el) {
        var val = $('#' + el.id).val();
        var newId = 'datepicker_' + el.id;

        var df = new Ext.form.DateField({
            id: newId,
            format: dateFormatFull,
            maxLength: 12, // Fix issue with date entering in 'full' format
            altFormats: dateFormatFull + '|' + dateFormatShort
        });
        df.applyToMarkup(el);

        // Chrome fix - invalid date on applying to entered date
        if (!empty(val) && Ext.isChrome) {
            try {
                var dt = new Date(val);
                df.setValue(dt);
            } catch (err) {
            }
        }
    });
}

function initQnrSearch(q_id, validator, panelType) {
    // Listen for 'close job section' click
    $(document).on('click', '.jobs_section_hide', function () {
        $(this).parents('.job_section_table').slideUp("normal", function () {
            // Remove previously created search boxes
            var input = $(this).find('.job_search_replaced');
            var combo = Ext.getCmp('job_search_' + $(input).attr('id'));
            if (combo) {
                combo.destroy();
            }

            $(this).empty().remove();

            if (typeof (window.centerBottom) == 'function') {
                centerBottom(".bottom_copyright");
            }

            if (!empty(panelType)) {
                var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.fixParentPanelHeight();
                }
            }
        });
    });

    var tabId = 'ptab-' + q_id;
    if (!empty(panelType)) {
        tabId = panelType + '-' + tabId;
    }

    var formId = tabId + '-occupations-prospects';

    var where = empty(validator) ? '#' + formId + ' .job-section-' + qnrJobSectionId + ' tr.job_section' : '.job-section-' + qnrJobSectionId + ' tr.job_section';

    // Get whole section we need duplicate
    var jobSearchTrs = '';
    var tr = Ext.select(where, true);
    tr.each(function (el) {
        // Use classes
        var trClasses = $('#' + el.id).attr('class');
        var clearedClasses = trClasses.replace(/(job_section)/, "");
        jobSearchTrs += '<tr class="' + clearedClasses + '">' + $('#' + el.id).html() + '</tr>';
    });

    var whereSpouse = empty(validator) ? '#' + formId + ' .job-section-' + qnrSpouseJobSectionId + ' tr.job_section' : '.job-section-' + qnrSpouseJobSectionId + ' tr.job_section';

    // Get whole section we need duplicate
    var jobSpouseSearchTrs = '';
    tr = Ext.select(whereSpouse, true);
    tr.each(function (el) {
        // Use classes
        var trClasses = $('#' + el.id).attr('class');
        var clearedClasses = trClasses.replace(/(job_section)/, "");
        jobSpouseSearchTrs += '<tr class="' + clearedClasses + '">' + $('#' + el.id).html() + '</tr>';
    });

    // Init search field
    arrCustomExtjsFields = _initJobSearchFields(!empty(panelType));

    // Don't show fields in 'duplicated tables'
    $('.job_section_table').find('.field_do_not_duplicate').closest('tr').hide();

    if ($('.q_job_add_' + q_id).length !== 0) {
        // Init 'add new job section' button
        $('.q_job_add_' + q_id).each(function () {
            var buttonId = $(this).attr('id');
            var parentTableClass = $(this).parents('.qnr-section').hasClass('job-section-' + qnrJobSectionId) ? 'job-section-' + qnrJobSectionId : 'job-section-' + qnrSpouseJobSectionId;

            new Ext.Button({
                text: site_version == 'australia' ? '<i class="las la-plus"></i>' + _('Add further employment') : '<i class="las la-plus"></i>' + _('Add More Work Experience'),
                style: 'margin: 0 auto;', // center it
                scale: 'medium',
                renderTo: buttonId,
                hidden: panelType == 'marketplace',

                handler: function () {
                    /*
                    // 1. Check if form is correct (all fields are filled)
                    // All combos and whole form must be valid
                    // to proceed to next step
                    var booCorrect = !empty(validator) ?
                                     correctEnteredInfo(validator) :
                                     checkJobSearchValid(formId);
                    if(!booCorrect) {
                        showConfirmationMsg(
                            'Please fill all fields correctly and try again.',
                            true
                        );
                        return false;
                    }
                    */


                    // 2. Duplicate the section

                    // Find the last 'tr'
                    var where2 = empty(validator) ? '#' + formId + ' .' + parentTableClass + ' tr.job_section' : '.' + parentTableClass + ' tr.job_section';
                    var allTrs = $(where2).nextAll();
                    var lastTr = empty(allTrs.length) ? where2 : allTrs[allTrs.length - 1];

                    // Check how many blocks already added
                    var jobSectionsCount = 0;
                    $('.' + parentTableClass + ' .job_section_table').each(function () {
                        var exploded = $(this).attr("class").split(' ');
                        $.each(exploded, function (index, value) {
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
                    var updatedJobSearchTrs = parentTableClass == 'job-section-' + qnrJobSectionId ? jobSearchTrs : jobSpouseSearchTrs;


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
                            if (!empty(nameToReplace) && !arrReplaced.has(nameToReplace)) {
                                var newName = nameToReplace + '_' + (jobSectionsCount + 1);
                                updatedJobSearchTrs = updatedJobSearchTrs.replace(new RegExp(nameToReplace, "g"), newName);
                                arrReplaced[arrReplaced.length] = nameToReplace;
                            }
                        }
                    }

                    var newTableClass = 'section_num_' + (jobSectionsCount + 1);
                    var copyTable =
                        '<table class="job_section_table ' + newTableClass + '">' +
                        '<tr>' +
                        '<td colspan="2" class="job_section_header" style="background-color: unset;">' +
                        (panelType != 'marketplace' ? '<img class="jobs_section_hide" width="11" height="11" title="Close" alt="Close" src="' + topBaseUrl + '/images/default/close-button-gray.png" style="float: right; padding-right: 0px; width: 20px; height: 20px;">' : "") +
                        '<a href="#" class="bluetxtdark blue-arrow-down blue-arrow-down-simple">' + (site_version == 'australia' ? 'Employer' : 'Previous Job') + '</a>' +
                        '</td>' +
                        '</tr>' +
                        updatedJobSearchTrs +
                        '<table>';

                    // Create the new 'tr' after the last one
                    $(lastTr).after('<tr><td colspan="2">' + copyTable + '</td></tr>');

                    // Hide fields we don't need to duplicate
                    $(lastTr).next().find('.field_do_not_duplicate').parents('tr:first').hide();

                    // Reset fields in this new 'tr'
                    if (empty(validator)) {
                        $(lastTr).next()
                            .find(':input')
                            .not(':button, :submit, :reset, :hidden')
                            .val('')
                            .removeAttr('checked')
                            .removeAttr('selected')
                        ;
                    }

                    // Reinit just added field
                    var arrNewSearchFields = _initJobSearchFields(!empty(panelType));
                    arrCustomExtjsFields = arrCustomExtjsFields.concat(arrNewSearchFields);

                    if (empty(validator)) {
                        initJobFields('#' + formId, '.' + newTableClass, panelType);
                    } else {
                        initJobFields('', '.' + newTableClass, panelType);
                    }

                    // move button
                    $('#' + buttonId)
                        .insertAfter('.' + parentTableClass + ' .job_section_table:last')
                        .parent()
                        .hide()
                        .fadeIn(1500);

                    // Init date fields
                    initJobSectionDate('.job_section_table.' + newTableClass);

                    // Show new fields and make them enabled
                    var jobSpouseSectionClass = 'job-section-' + qnrSpouseJobSectionId;
                    if (parentTableClass == jobSpouseSectionClass && !empty(validator)) {
                        toggleSpouseFields(true, '.' + parentTableClass + ' .' + newTableClass, panelType);
                    }

                    if (typeof (window.centerBottom) == 'function') {
                        centerBottom(".bottom_copyright");
                    }

                    if (!empty(panelType)) {
                        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                        if (tabPanel) {
                            tabPanel.fixParentPanelHeight();
                        }
                    }
                }
            });
        });
    }
    toggleExtjsJobFields(false);
}


function toggleSpouseFields(booShow, whereOriginal, panelType) {
    var where = whereOriginal;
    if (empty(where)) {
        where = '.spouse_field';
    } else if ((where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        where = '#' + where + ' .spouse_field';
    } else {
        where += ' .spouse_field';
    }


    if (booShow) {
        if ($(where).is(":visible")) {
            // Show only once
            if (panelType != 'marketplace') {
                $(where + ' :input').removeAttr('disabled');
            }
            return;
        }

        $(where).show();
        if (panelType != 'marketplace') {
            $(where + ' :input').removeAttr('disabled');
        }


        // Highlight just showed fields
        var els = Ext.select(where, true);
        els.each(function (el) {
            el.highlight("033876", {attr: 'color', duration: 2});
        });

        initSpouseJobSection(booShow, whereOriginal);
        initIsPartnerResidentOfNewZealand(whereOriginal, panelType);
        toggleSpouseBachelorDegree(whereOriginal, panelType);

        if (site_version == 'australia') {
            toggleSpouseLanguageFields(whereOriginal, panelType);
            initSpouseEducationalQualifications(whereOriginal);
        } else {
            toggleSpouseLanguageEnglishFields(whereOriginal, panelType);
            toggleSpouseLanguageFrenchFields(whereOriginal, panelType);
        }
    } else {
        $(where).hide();
        if (panelType != 'marketplace') {
            $(where + ' :input').attr('disabled', 'disabled');
        }


        initSpouseJobSection(booShow, whereOriginal);
    }
}

function toggleSpouseJobSection(booShow, where, panelType) {
    where = where + ' .job-section-' + qnrSpouseJobSectionId;
    if (booShow) {
        $(where).slideDown(400, function () {
            if (!empty(panelType)) {
                var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.fixParentPanelHeight();
                }
            }
        });
    } else {
        $(where).slideUp(400, function () {
            if (!empty(panelType)) {
                var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.fixParentPanelHeight();
                }
            }
        });
    }

    if (booShow) {
        if (panelType != 'marketplace') {
            $(where + ' :input').removeAttr('disabled');
        }

        toggleExtjsJobFields(true);
    } else {
        if (panelType != 'marketplace') {
            $(where + ' :input').attr('disabled', 'disabled');
        }
        toggleExtjsJobFields(false);
    }

    if (typeof (window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initSpouseJobSection(booForceShow, where, panelType) {
    var whereField = where + ' .qf_job_spouse_has_experience';

    // Hide or show fields in relation to
    // current selected value in the radio
    var booShow = $(whereField + ' input:first').is(':checked');
    toggleSpouseJobSection(booShow && booForceShow, where, panelType);

    $(whereField + ' input').each(function () {
        $(this).change(function () {
            var booShow = $(whereField + ' input:first').is(':checked');
            toggleSpouseJobSection(booShow && booForceShow, where, panelType);
        });
    });
}

function toggleChildrenFields(where, panelType) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var val;
    if (empty(panelType)) {
        val = parseInt($(strPrefix + '.qf_children_count select option:selected').text(), 10);
    } else {
        val = parseInt($(strPrefix + '.qf_children_count :input.profile-select').val(), 10);
    }

    $(strPrefix + '.qf_children_age_1').toggle(val >= 1);

    if (empty(strPrefix)) {
        $(strPrefix + '.qf_children_age_2').toggle(val >= 2);
    } else {
        $(strPrefix + '.qf_children_age_2').css('visibility', (!val || val < 2) ? 'hidden' : 'visible');
    }

    $(strPrefix + '.qf_children_age_3').toggle(val >= 3);
    $(strPrefix + '.qf_children_age_4').toggle(val >= 4);
    $(strPrefix + '.qf_children_age_5').toggle(val >= 5);
    $(strPrefix + '.qf_children_age_6').toggle(val >= 6);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initChildrenFields(where, panelType) {
    toggleChildrenFields(where, panelType);

    var strPrefix = empty(where) ? '' : '#' + where + ' ';

    $(strPrefix + '.qf_children_count ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
        toggleChildrenFields(where, panelType);
    });
}


function toggleNocField(where, panelType) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var el = $(strPrefix + ' .qf_work_offer_of_employment :input' + (!empty(panelType) ? '.profile-select' : '') + ':first');

    if (!$(el).length) {
        return;
    }

    var booShowNOC = false;

    if (!empty(panelType)) {
        booShowNOC = $(el).val().toLowerCase() === 'yes';
    } else {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowNOC = selText === 'yes';
        } else {
            booShowNOC = $(el).is(':checked');
        }
    }

    $(strPrefix + ' [class*="qf_work_noc"]').toggle(booShowNOC);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initNocField(where, panelType) {
    toggleNocField(where, panelType);

    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    $(strPrefix + '.qf_work_offer_of_employment :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        toggleNocField(where, panelType);
    });
}

function togglePostSecondariesField(where, panelType) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var el = $(strPrefix + ' .qf_study_previously_studied :input' + (!empty(panelType) ? '.profile-select' : '') + ':first');

    var booShowField = false;

    if (!$(el).length) {
        return;
    }

    if (empty(panelType)) {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowField = selText === 'yes';
        } else {
            booShowField = $(el).is(':checked');
        }
    } else {
        booShowField = $(el).val().toLowerCase() === 'yes';
    }

    $(strPrefix + ' [class*="qf_education_studied_in_canada_period"]').toggleClass('hidden_via_visibility', !booShowField);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleSpousePostSecondariesField(where, panelType) {
    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    var el = $(strPrefix + ' .qf_education_spouse_previously_studied :input' + (!empty(panelType) ? '.profile-select' : '') + ':first');

    var booShowField = false;

    if (!$(el).length) {
        return;
    }

    if (!empty(panelType)) {
        booShowField = $(el).val().toLowerCase() === 'yes';
    } else {
        if ($(el).prop("tagName").toLowerCase() === 'select') {
            var selText = $(el).find('option:selected').attr('data-val');
            booShowField = selText === 'yes';
        } else {
            booShowField = $(el).is(':checked');
        }
    }

    $(strPrefix + ' [class*="qf_education_spouse_studied_in_canada_period"]').toggleClass('hidden_via_visibility', !booShowField);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initPostSecondariesField(where, panelType) {
    togglePostSecondariesField(where, panelType);
    toggleSpousePostSecondariesField(where, panelType);

    var strPrefix = empty(where) ? '' : '#' + where + ' ';
    $(strPrefix + '.qf_study_previously_studied :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        togglePostSecondariesField(where, panelType);
    });

    $(strPrefix + '.qf_education_spouse_previously_studied :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        toggleSpousePostSecondariesField(where, panelType);
    });
}

function initWorkFields(where) {
    $(where + ' .qf_work_temporary_worker').show();
    $(where + ' .qf_work_years_worked').hide();
    $(where + ' .qf_work_currently_employed').hide();
    $(where + ' .qf_work_leave_employment').hide();
    $(where + ' .qf_study_previously_studied').show();
    $(where + ' .qf_education_studied_in_canada_period').show();
    $(where + ' .qf_work_offer_of_employment').show();

    $(where + ' .qf_work_temporary_worker input').each(function () {
        $(this).change(function () {
            var booChecked = $(where + ' .qf_work_temporary_worker input.profile-select').val() == 'Yes';
            $(where + ' .qf_work_years_worked').toggle(booChecked);
            $(where + ' .qf_work_currently_employed').toggle(booChecked);

            if (booChecked) {
                $(where + ' .qf_work_leave_employment').toggle(
                    $(where + ' .qf_work_currently_employed input.profile-select').val() != 'Yes'
                );
            } else {
                $(where + ' .qf_work_leave_employment').hide();
            }
        });
    });

    $(where + ' .qf_work_currently_employed input[type="hidden"]').each(function () {
        $(this).change(function () {
            $(where + ' .qf_work_leave_employment').toggle(
                $(where + ' .qf_work_currently_employed input.profile-select').val() != 'Yes'
            );
        });
    });
}

function toggleFamilyRelations(where) {
    var combo = $(where + ' .qf_family_relationship input[type="hidden"]');

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
    $(where + ' .qf_family_relative_wish_to_sponsor').toggle(booShowRelativeWish);


    $(where + ' .qf_family_currently_fulltime_student').toggle(booShowFullTimeStudent);
    // Check also qf_family_currently_fulltime_student
    $(where + ' .qf_family_been_fulltime_student').toggle(
        $(where + ' .qf_family_currently_fulltime_student input.profile-select').val() == 'Yes'
    );

    // Check also qf_family_relative_wish_to_sponsor
    if (booShowOtherFields) {
        if ($(where + ' .qf_family_relative_wish_to_sponsor input.profile-select').val() != 'Yes') {
            booShowOtherFields = false;
        }
    }
    $(where + ' .qf_family_sponsor_age').toggle(booShowOtherFields);
    $(where + ' .qf_family_employment_status').toggle(booShowOtherFields);
    $(where + ' .qf_family_sponsor_financially_responsible').toggle(booShowOtherFields);
    $(where + ' .qf_family_sponsor_income').toggle(booShowOtherFields);
}

function initFamilyRelations(where) {
    if ($(where + ' .qf_family_have_blood_relative').length) {
        /* A */
        $(where + ' .qf_family_have_blood_relative').show();
        /* B */
        $(where + ' .qf_family_relationship').hide();
    }
    /* C */
    $(where + ' .qf_family_relative_wish_to_sponsor').hide();
    /* D */
    $(where + ' .qf_family_sponsor_age').hide();
    /* E */
    $(where + ' .qf_family_employment_status').hide();
    /* F */
    $(where + ' .qf_family_sponsor_financially_responsible').hide();
    /* G */
    $(where + ' .qf_family_sponsor_income').hide();
    /* H */
    $(where + ' .qf_family_currently_fulltime_student').hide();
    /* I */
    $(where + ' .qf_family_been_fulltime_student').hide();

    // Listen for main radio change
    $(where + ' .qf_family_have_blood_relative input[type="hidden"]').each(function () {
        $(this).change(function () {
            var booChecked = $(where + ' .qf_family_have_blood_relative input.profile-select').val() == 'Yes';
            $(where + ' .qf_family_relationship').toggle(booChecked);

            if (!booChecked) {
                // Hide all
                /* B */
                $(where + ' .qf_family_relationship').hide();
                /* C */
                $(where + ' .qf_family_relative_wish_to_sponsor').hide();
                /* D */
                $(where + ' .qf_family_sponsor_age').hide();
                /* E */
                $(where + ' .qf_family_employment_status').hide();
                /* F */
                $(where + ' .qf_family_sponsor_financially_responsible').hide();
                /* G */
                $(where + ' .qf_family_sponsor_income').hide();
                /* H */
                $(where + ' .qf_family_currently_fulltime_student').hide();
                /* I */
                $(where + ' .qf_family_been_fulltime_student').hide();
            } else {
                toggleFamilyRelations(where);
            }
        });
    });

    // Listen for combo changes
    $(where + ' .qf_family_relationship input[type="hidden"]').change(function () {
        toggleFamilyRelations(where);
    });

    $(where + ' .qf_family_currently_fulltime_student input[type="hidden"]').each(function () {
        $(this).change(function () {
            $(where + ' .qf_family_been_fulltime_student').toggle(
                $(where + ' .qf_family_currently_fulltime_student input.profile-select').val() == 'Yes'
            );
        });
    });


    $(where + ' .qf_family_relative_wish_to_sponsor input[type="hidden"]').each(function () {
        $(this).change(function () {
            var booCheckedYes = $(where + ' .qf_family_relative_wish_to_sponsor input.profile-select').val() == 'Yes';

            var comboVal = $(where + ' .qf_family_relationship input[type="hidden"]').val();

            var booShowOtherFields = false;
            if (booCheckedYes && (comboVal == '139' || comboVal == '143')) {
                booShowOtherFields = true;
            }

            $(where + ' .qf_family_sponsor_age').toggle(booShowOtherFields);
            $(where + ' .qf_family_employment_status').toggle(booShowOtherFields);
            $(where + ' .qf_family_sponsor_financially_responsible').toggle(booShowOtherFields);
            $(where + ' .qf_family_sponsor_income').toggle(booShowOtherFields);
        });
    });
}

function toggleAssessment(where) {
    var booCheckedYes = $(where + ' .qf_cat_have_experience').length == 0 || $(where + ' .qf_cat_have_experience input[type="text"]').val() == 'Yes';
    $(where + ' .qf_cat_managerial_experience').toggle(booCheckedYes);
    $(where + ' .qf_cat_staff_number').toggle(booCheckedYes);
    $(where + ' .qf_cat_own_this_business').toggle(booCheckedYes);
    $(where + ' .qf_cat_annual_sales').toggle(booCheckedYes);
    $(where + ' .qf_cat_annual_net_income').toggle(booCheckedYes);
    $(where + ' .qf_cat_net_assets').toggle(booCheckedYes);

    if (typeof (window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
    toggleAssessmentPercentageOwnership(where);
}

function toggleAssessmentPercentageOwnership(where) {
    var booCheckedYes = $(where + ' .qf_cat_own_this_business input[type="text"]').val() == 'Yes';
    $(where + ' .qf_cat_percentage_of_ownership').toggle(booCheckedYes);
}

function initAssessment(where) {
    /* A */
    $(where + ' .qf_cat_net_worth').show();
    /* B */
    $(where + ' .qf_cat_have_experience').hide();
    /* C */
    $(where + ' .qf_cat_managerial_experience').hide();
    /* D */
    $(where + ' .qf_cat_staff_number').hide();
    /* E */
    $(where + ' .qf_cat_own_this_business').hide();
    /* F */
    $(where + ' .qf_cat_percentage_of_ownership').hide();
    /* G */
    $(where + ' .qf_cat_annual_sales').hide();
    /* H */
    $(where + ' .qf_cat_annual_net_income').hide();
    /* I */
    $(where + ' .qf_cat_net_assets').hide();

    // Listen for combo changes
    $(where + ' .qf_cat_net_worth input[type="hidden"]').change(function () {
        netWorthChange(where, $(this).val());
    });

    $(where + ' .qf_cat_have_experience input[type="hidden"]').each(function () {
        $(this).change(function () {
            toggleAssessment(where);
        });
    });

    $(where + ' .qf_cat_own_this_business input[type="hidden"]').each(function () {
        $(this).change(function () {
            toggleAssessmentPercentageOwnership(where);
        });
    });
    netWorthChange(where);
}

function netWorthChange(where) {
    var value = $(where + ' .qf_cat_net_worth input[type="hidden"]').val();
    var booShowExperienceRadio = false;
    switch (value) {
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

    $(where + ' .qf_cat_have_experience').toggle(booShowExperienceRadio);
    if (booShowExperienceRadio) {
        toggleAssessment(where);
    } else {
        $(where + ' .qf_cat_managerial_experience').hide();
        $(where + ' .qf_cat_staff_number').hide();
        $(where + ' .qf_cat_own_this_business').hide();
        $(where + ' .qf_cat_percentage_of_ownership').hide();
        $(where + ' .qf_cat_annual_sales').hide();
        $(where + ' .qf_cat_annual_net_income').hide();
        $(where + ' .qf_cat_net_assets').hide();
    }
    return booShowExperienceRadio;
}

function toggleJobFields(strPrefix, panelType) {
    var val = parseInt($(strPrefix + '.qf_job_location' + (!empty(panelType) ? ' :input[type="hidden"]' : 'select option:selected')).val(), 10);

    // Show provinces if Canada option is selected
    $(strPrefix + '.qf_job_province').toggle(val == 175);
}

function toggleSpouseJobFields(strPrefix, panelType) {
    var val = parseInt($(strPrefix + '.qf_job_spouse_location' + (!empty(panelType) ? ' :input[type="hidden"]' : 'select option:selected')).val(), 10);

    // Show provinces if Canada option is selected
    $(strPrefix + '.qf_job_spouse_province').toggle(val == 343);
}

function initJobFields(prefix, suffix, panelType) {
    initSelectFields(true);

    var strPrefix = '';
    var strPrefix2 = '';
    if (empty(prefix) && empty(suffix)) {
        strPrefix = '.job-section-' + qnrJobSectionId + ' .job_section';
        strPrefix2 = '.job-section-' + qnrSpouseJobSectionId + ' .job_section';
    } else if (empty(suffix)) {
        strPrefix = prefix + ' .job-section-' + qnrJobSectionId + ' ';
        strPrefix2 = prefix + ' .job-section-' + qnrSpouseJobSectionId + ' ';
    } else {
        strPrefix = prefix + ' .job-section-' + qnrJobSectionId + ' ' + suffix + ' ';
        strPrefix2 = prefix + ' .job-section-' + qnrSpouseJobSectionId + ' ' + suffix + ' ';
    }

    toggleJobFields(strPrefix, panelType);
    $(strPrefix + '.qf_job_location ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
        toggleJobFields(strPrefix, panelType);
    });

    $(strPrefix + '.qf_job_province ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
        $(this).toggleClass('x-form-invalid', empty($(this).val()));
    });

    toggleSpouseJobFields(strPrefix2, panelType);
    $(strPrefix2 + '.qf_job_spouse_location ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
        toggleSpouseJobFields(strPrefix2, panelType);
    });

    $(strPrefix2 + '.qf_job_spouse_province ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
        $(this).toggleClass('x-form-invalid', empty($(this).val()));
    });

    // Enable all previously disabled fields
    if ($(strPrefix).is(":visible")) {
        $(strPrefix).find(':input').removeAttr('disabled');
    }
}


// This method is very similar to 2 above, but we parse all job sections at once
function initJobFieldsByTable(tabId, panelType) {
    // Find all job sections
    $('#' + tabId + ' .job_container').each(function (index, el) {

        // Hide all province field if country is not Canada
        var val = parseInt($(el).find('.qf_job_location' + (!empty(panelType) ? ' :input[type="hidden"]' : 'select option:selected')).val(), 10);

        $(el).find('.qf_job_province').toggle(val == 175);

        // Listen for country field change
        $(el).find('.qf_job_location ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
            $(el).find('.qf_job_province').toggle($(this).val() == 175);

            if (!empty(panelType)) {
                var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.fixParentPanelHeight();
                }
            }
        });

        $(el).find('.qf_job_province ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
            $(this).toggleClass('x-form-invalid', empty($(this).val()));
        });

        // Hide all province field if country is not Canada
        var val = parseInt($(el).find('.qf_job_spouse_location' + (!empty(panelType) ? ' :input[type="hidden"]' : '') + ' select option:selected').val(), 10);

        $(el).find('.qf_job_spouse_province').toggle(val == 343);


        // Listen for country field change
        $(el).find('.qf_job_spouse_location ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
            $(el).find('.qf_job_spouse_province').toggle($(this).val() == 343);

            if (!empty(panelType)) {
                var tabPanel = Ext.getCmp(panelType + '-tab-panel');
                if (tabPanel) {
                    tabPanel.fixParentPanelHeight();
                }
            }
        });

        // Listen for province field change
        $(el).find('.qf_job_spouse_province ' + (!empty(panelType) ? ':input[type="hidden"]' : 'select')).change(function () {
            $(this).toggleClass('x-form-invalid', empty($(this).val()));
        });

    });

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}


function initQnrFields(tabId) {
    var where = empty(tabId) ? '' : '#' + tabId + ' ';
    jQuery.expr[':'].hiddenByParent = function (a) {
        return jQuery(a).is(':hidden') && jQuery(a).css('display') != 'none';
    };

    initWorkFields(where);
    initFamilyRelations(where);
}

function toggleCurrentAddressFields(booShow, whereOriginal, panelType) {
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

        // Highlight just showed fields
        var els = Ext.select(where, true);
        els.each(function (el) {
            el.highlight("033876", {attr: 'color', duration: 2});
        });

        initSpouseJobSection(booShow, whereOriginal, panelType);
    } else {
        $(where).hide();
        $(where + ' :input').attr('disabled', 'disabled');

        initSpouseJobSection(booShow, whereOriginal, panelType);
    }
}

function checkCountryValue(selOption) {
    return site_version == 'australia' ? selOption != '' : selOption == 38;
}

function initCurrentCountry(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    var whereField = wherePrefix + ' .qf_country_of_residence';
    // Hide or show fields in relation to
    // current selected value in the combobox
    var selOption = $(whereField + ' :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val();
    toggleCurrentAddressSection(checkCountryValue(selOption), wherePrefix);
    toggleCurrentAddressFields(checkCountryValue(selOption), wherePrefix, panelType);

    // Listen for changes in 'Country of current Residence' combo
    $(whereField + ' :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        var selOption = $(this).val();
        toggleCurrentAddressSection(checkCountryValue(selOption), wherePrefix);
        toggleCurrentAddressFields(checkCountryValue(selOption), wherePrefix, panelType);
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

    if (typeof (window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}


function initVisaRefusedOrCancelled(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    // Hide or show fields in relation to

    if (empty(panelType)) {
        $(wherePrefix + ' .qf_visa_refused_or_cancelled').hide();
    } else {
        var selOption = $(wherePrefix + ' .qf_applied_for_visa_before :input[type="hidden"]').val();
        $(wherePrefix + ' .qf_visa_refused_or_cancelled').toggle(selOption == '1');
    }

    $(wherePrefix + ' .qf_applied_for_visa_before :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        var booShow;
        if (empty(panelType)) {
            if ($(this).prop("tagName").toLowerCase() == 'select') {
                booShow = $(this).val() == '1';
            } else {
                booShow = $(wherePrefix + ' .qf_applied_for_visa_before input:first').is(':checked');
            }
            $(wherePrefix + ' .qf_visa_refused_or_cancelled').toggle(booShow);
        } else {
            booShow = $(this).val() == '1';
            $(wherePrefix + ' .qf_visa_refused_or_cancelled').toggle(booShow);

            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }

        }
    });

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initDesignatedAreaFields(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;

    $(wherePrefix + ' .qf_relationship_nature').hide();
    $(wherePrefix + ' .qf_relative_postcode').hide();

    $(wherePrefix + ' .qf_relative_designated_area :input' + (!empty(panelType) ? '.profile-select' : '')).change(function () {
        toggleDesignatedAreaFields($(this), wherePrefix, panelType);
    });

    toggleDesignatedAreaFields($(wherePrefix + ' .qf_relative_designated_area :input' + (!empty(panelType) ? '.profile-select' : '')), wherePrefix, panelType);
}

function toggleDesignatedAreaFields(elem, wherePrefix, panelType) {
    var booShow = false;
    if (empty(panelType)) {
        if (elem.length && elem.prop("tagName").toLowerCase() == 'select') {
            booShow = elem.children(':selected').attr('data-val') == 'yes';
        } else {
            booShow = $(wherePrefix + ' .qf_relative_designated_area input:first').is(':checked');
        }
    } else {
        booShow = elem.length && (elem.val().toLowerCase() == 'yes');
    }

    $(wherePrefix + ' .qf_relationship_nature').toggle(booShow);
    $(wherePrefix + ' .qf_relative_postcode').toggle(booShow);
}

function initDNAField(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .prospect_status').change(function () {
        var booCheckedActive = $(wherePrefix + ' .prospect_status').is(':checked');
        $(wherePrefix + ' .editable-combo-dna').closest('td').toggle(!booCheckedActive);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });

    var booCheckedActive = $(wherePrefix + ' .prospect_status').is(':checked');
    $(wherePrefix + ' .editable-combo-dna').closest('td').toggle(!booCheckedActive);
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

function initOthers(where, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = empty(where) ? '' : '#' + where;
    // Hide or show fields at loading that depends on Other checkbox

    if ($(wherePrefix + ' input[data-val="other"]').is(':checked')) {
        $(wherePrefix + ' .qf_area_of_interest_other1').show();
    } else {
        $(wherePrefix + ' .qf_area_of_interest_other1').hide();
    }

    $(wherePrefix + ' input[data-val="other"]').change(function () {
        var booShow = $(wherePrefix + ' input[data-val="other"]').is(':checked');
        $(wherePrefix + ' .qf_area_of_interest_other1').toggle(booShow);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });
}

function initMarks() {
    // remove mark for invalid fields
    $('input, select, textarea').change(function () {
        if ($(this).hasClass('error-field')) {
            $(this).removeClass('error-field');
            $('#' + this.name + '-el').remove();
        }
    });
}

function initEditableCombo(panelType) {
    $('.editable-combo').each(function () {
        var parentWidth = $(this).parent().width() * 85 / 100;
        var data = Ext.decode($(this).html());
        var options = [];
        for (var opt in data.options) {
            options.push([opt, data.options[opt]]);
        }

        $(this).replaceWith('<div id="c-' + data.id + '"' + '></div>');

        new Ext.form.ComboBox({
            id: data.id,
            name: data.id,
            store: new Ext.data.SimpleStore({
                fields: ['referred_id', 'referred_name'],
                data: options
            }),
            mode: 'local',
            displayField: 'referred_name',
            valueField: 'referred_id',
            triggerAction: 'all',
            selectOnFocus: true,
            renderTo: 'c-' + data.id,
            value: data['default'],
            width: empty(panelType) ? 220 : parentWidth,
            disabled: panelType == 'marketplace'
        });

    });
}

function initProfessionalProgramsBlock(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;

    function toggleProfessionalProgramsBlock() {
        var booShow = false;

        var el = $(wherePrefix + ' .qf_programs_completed_professional_year :input' + (!empty(panelType) ? '.profile-select' : ''));

        if ($(el).length) {
            if (empty(panelType)) {
                if ($(el).prop("tagName").toLowerCase() === 'select') {
                    booShow = $(el).find('option:selected').attr('data-val') === 'yes';
                } else {
                    booShow = $(el).first().is(':checked');
                }
            } else {
                booShow = $(el).val().toLowerCase() === 'yes';
            }
        }

        $(wherePrefix + ' .qf_programs_name').toggle(booShow);
        $(wherePrefix + ' .qf_programs_year_completed').toggle(booShow);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
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
    if (site_version == 'australia') {
        return (selOption == 7 || selOption == 8 || selOption == 356);
    } else {
        return (selOption == 7 || selOption == 12);
    }
}

function initMaritalStatus(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    var whereField = wherePrefix + ' .qf_marital_status';
    // Hide or show fields in relation to
    // current selected value in the combobox
    var selOption = $(whereField + ' :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val();
    toggleSpouseFields(checkMaritalComboValue(selOption), wherePrefix, panelType);
    toggleSpousePersonalSection(checkMaritalComboValue(selOption), wherePrefix);

    // Listen for changes in 'Marital Status' combo
    $(whereField + ' :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        var selOption = parseInt($(this).val(), 10);
        toggleSpouseFields(checkMaritalComboValue(selOption), wherePrefix, panelType);
        toggleSpousePersonalSection(checkMaritalComboValue(selOption), wherePrefix);
        toggleAreaOfInterestSections(wherePrefix, panelType);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });
}

function toggleSpousePersonalSection(booShow, where) {
    if (site_version != 'australia') {
        return;
    }

    where = where + ' .job-section-3';
    $(where).toggle(booShow);

    if (booShow) {
        $(where + ' :input').removeAttr('disabled');
    } else {
        $(where + ' :input').attr('disabled', 'disabled');
    }

    if (typeof (window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }
}

function initIsPartnerResidentOfNewZealand(where, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = '';
    if (!empty(where) && (where.charAt(0) != '.') && (where.charAt(0) != '#')) {
        wherePrefix = '#' + where;
    } else {
        wherePrefix = where;
    }

    function togglePartnerResidentOfNewZealand() {
        var booShow = false;

        var el = $(wherePrefix + ' .qf_spouse_is_resident_of_australia :input' + (!empty(panelType) ? '.profile-select' : ''));

        if ($(el).length) {
            if (empty(panelType)) {
                if ($(el).prop("tagName").toLowerCase() === 'select') {
                    booShow = $(el).find('option:selected').attr('data-val') === 'no';
                } else {
                    booShow = $(el).last().is(':checked');
                }
            } else {
                booShow = $(el).val().toLowerCase() === 'no';
            }
            $(wherePrefix + ' .qf_spouse_is_resident_of_new_zealand').toggle(booShow);
        }

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    }

    // Hide or show fields in relation to
    $(wherePrefix + ' .qf_spouse_is_resident_of_new_zealand').hide();
    $(wherePrefix + ' .qf_spouse_is_resident_of_australia :input').each(function () {
        $(this).change(function () {
            togglePartnerResidentOfNewZealand();
        });
    });
    togglePartnerResidentOfNewZealand();
}

function toggleAreaOfInterestSections(where, panelType) {
    if (site_version != 'australia') {
        var areaOfInterestChecked = $(where + ' .qf_area_of_interest :checked');
        var booShow = false;
        areaOfInterestChecked.each(function () {
            if ($(this).attr('data-readable-value') == 'study') {
                booShow = true;
            }
        });

        $(where + ' .qf_study_have_admission').toggle(booShow);

        return;
    }

    var selOption = parseInt($(where + ' .qf_marital_status :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val(), 10),
        booFirstCheckboxChecked = $(where + ' input[data-val="skilled_independent_visa"]').is(':checked'),
        booStudentVisaChecked = $(where + ' input[data-val="student_visa"]').is(':checked');

    var arrToCheck = [
        [where + ' .job-section-5', booFirstCheckboxChecked || booStudentVisaChecked],
        [where + ' .job-section-6', booFirstCheckboxChecked || booStudentVisaChecked],
        [where + ' .job-section-7', booFirstCheckboxChecked],
        [where + ' .job-section-8', booFirstCheckboxChecked && checkMaritalComboValue(selOption)],
        [where + ' .job-section-11', $(where + ' input[data-val="employer_sponsored_visa"]').is(':checked')],
        [where + ' .job-section-4', $(where + ' input[data-val="parent_visa"]').is(':checked')],
        [where + ' .job-section-9', $(where + ' input[data-val="business_investment_visa"]').is(':checked')],
        [where + ' .job-section-10', $(where + ' input[data-val="state_sponsorship_visa"]').is(':checked')],
        [where + ' .job-section-12', booFirstCheckboxChecked],
        [where + ' .job-section-13', booFirstCheckboxChecked],
        [where + ' .qf_date_permanent_residency_obtained', $(where + ' input[data-val="citizenship"]').is(':checked')],
        [where + ' .qf_area_of_interest_other', $(where + ' input[data-val="other"]').is(':checked')]
    ];
    for (var i = 0; i < arrToCheck.length; i++) {
        $(arrToCheck[i][0]).toggle(arrToCheck[i][1]);
        if (arrToCheck[i][1]) {
            $(arrToCheck[i][0] + ' :input').removeAttr('disabled');
        } else {
            $(arrToCheck[i][0] + ' :input').attr('disabled', 'disabled');
        }
    }

    toggleExtjsJobFields(false);
    if (typeof toggleSteps != 'undefined') {
        toggleSteps();
    }

    if (typeof (window.centerBottom) == 'function') {
        centerBottom(".bottom_copyright");
    }

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleExtjsJobFields(booEnableOnly) {
    if (typeof arrCustomExtjsFields == 'undefined') {
        return;
    }

    for (var i = 0; i < arrCustomExtjsFields.length; i++) {
        var combo = Ext.getCmp(arrCustomExtjsFields[i]);
        if (combo) {
            var parent = combo.getEl().findParent('.steps');
            var parent2 = combo.getEl().findParent('.qnr-section');
            var booDisable = $(parent2).css('display') == 'none' || $(parent).css('display') == 'none';

            if (booDisable && booEnableOnly) {
                // Do nothing
            } else {
                combo.setDisabled(booDisable);
            }
        }
    }
}


function initAreaOfInterest(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_area_of_interest input').each(function () {
        $(this).change(function () {
            toggleAreaOfInterestSections(wherePrefix, panelType);
        });
    });
    toggleAreaOfInterestSections(wherePrefix, panelType);
}

function checkHighestQualification(selOption) {
    return (selOption == 21 || selOption == 22 || selOption == 38 || selOption == 39);
}

function initHighestQualification(where, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_education_name_bachelor_degree').hide();
    $(wherePrefix + ' .qf_education_spouse_name_bachelor_degree').hide();

    $(wherePrefix + ' .qf_education_highest_qualification :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        toggleMainApplicantBachelorDegree(wherePrefix, panelType);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });

    $(wherePrefix + ' .qf_education_spouse_highest_qualification :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        toggleSpouseBachelorDegree(wherePrefix, panelType);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });

    toggleMainApplicantBachelorDegree(wherePrefix, panelType);
    toggleSpouseBachelorDegree(wherePrefix, panelType);
}

function toggleMainApplicantBachelorDegree(wherePrefix, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var selOption = parseInt($(wherePrefix + ' .qf_education_highest_qualification :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val(), 10);
    $(wherePrefix + ' .qf_education_name_bachelor_degree').toggle(checkHighestQualification(selOption));
}

function toggleSpouseBachelorDegree(wherePrefix, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var selOptionMaritalStatus = parseInt($(wherePrefix + ' .qf_marital_status :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val(), 10);
    var selOption = parseInt($(wherePrefix + ' .qf_education_spouse_highest_qualification :input' + (!empty(panelType) ? '[type="hidden"]' : '')).val(), 10);
    var booShow = checkMaritalComboValue(selOptionMaritalStatus) && checkHighestQualification(selOption);
    $(wherePrefix + ' .qf_education_spouse_name_bachelor_degree').toggle(booShow);
}

function initCountryOfEducation(where, panelType) {
    if (site_version != 'australia') {
        return;
    }

    var wherePrefix = empty(where) ? '' : '#' + where;
    $(wherePrefix + ' .qf_education_duration_of_course').hide();
    $(wherePrefix + ' .qf_education_was_course_in_regional').hide();

    // Listen for changes in 'Country of Education' combo
    $(wherePrefix + ' .qf_education_country_of_education :input' + (!empty(panelType) ? '[type="hidden"]' : '')).change(function () {
        var selOption = parseInt($(this).val(), 10);
        $(wherePrefix + ' .qf_education_duration_of_course').toggle(checkCountryValue(selOption));
        $(wherePrefix + ' .qf_education_was_course_in_regional').toggle(checkCountryValue(selOption));

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    });

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initLanguage(where, panelType) {
    var wherePrefix = empty(where) ? '' : '#' + where;

    if (site_version != 'australia') {
        $(wherePrefix + ' .qf_language_english_done :input').each(function () {
            $(this).change(function () {
                toggleLanguageEnglishFields(wherePrefix, panelType);
            });
        });
        toggleLanguageEnglishFields(wherePrefix, panelType);

        $(wherePrefix + ' .qf_language_french_done :input').each(function () {
            $(this).change(function () {
                toggleLanguageFrenchFields(wherePrefix, panelType);
            });
        });
        toggleLanguageFrenchFields(wherePrefix, panelType);

        $(wherePrefix + ' .qf_language_spouse_english_done :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageEnglishFields(wherePrefix, panelType);
            });
        });
        toggleSpouseLanguageEnglishFields(wherePrefix, panelType);

        $(wherePrefix + ' .qf_language_spouse_french_done :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageFrenchFields(wherePrefix, panelType);
            });
        });
        toggleSpouseLanguageFrenchFields(wherePrefix, panelType);
    } else {
        // Hide or show fields in relation to
        $(wherePrefix + ' .language').hide();
        $(wherePrefix + ' .qf_language_have_taken_test_on_english :input').each(function () {
            $(this).change(function () {
                toggleLanguageFields(wherePrefix, panelType);
            });
        });
        $(wherePrefix + ' .qf_language_spouse_have_taken_test_on_english :input').each(function () {
            $(this).change(function () {
                toggleSpouseLanguageFields(wherePrefix, panelType);
            });
        });
        toggleLanguageFields(wherePrefix, panelType);
        toggleSpouseLanguageFields(wherePrefix, panelType);
    }
}

function toggleLanguageEnglishFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_english_done :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShowIELTS = false,
        booShowCELPIP = false,
        booShowGeneral = false;

    if (el && el.length) {
        if (empty(panelType)) {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                var selText = $(el).find('option:selected').attr('data-val');
                booShowIELTS = selText === 'ielts';
                booShowCELPIP = selText === 'celpip';
                booShowGeneral = selText === 'no';
            } else {
                booShowIELTS = $(el).is(':checked');
            }
        } else {
            var selText = $(el).val().toLowerCase();
            booShowIELTS = selText === 'ielts';
            booShowCELPIP = selText === 'celpip';
            booShowGeneral = selText === 'no';
        }
    }
    $(wherePrefix + ' [class*="qf_language_english_ielts"]').toggle(booShowIELTS);
    $(wherePrefix + ' [class*="qf_language_english_celpip"]').toggle(booShowCELPIP);
    $(wherePrefix + ' [class*="qf_language_english_general"]').toggle(booShowGeneral);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleSpouseLanguageEnglishFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_spouse_english_done :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShowIELTS = false,
        booShowCELPIP = false,
        booShowGeneral = false;

    if (el && el.length) {
        if (!empty(panelType)) {
            var selText = $(el).val().toLowerCase();
            booShowIELTS = selText === 'ielts';
            booShowCELPIP = selText === 'celpip';
            booShowGeneral = selText === 'no';
        } else {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                var selText = $(el).find('option:selected').attr('data-val');
                booShowIELTS = selText === 'ielts';
                booShowCELPIP = selText === 'celpip';
                booShowGeneral = selText === 'no';
            } else {
                booShowIELTS = $(el).is(':checked');
            }
        }
    }
    $(wherePrefix + ' [class*="qf_language_spouse_english_ielts"]').toggle(booShowIELTS);
    $(wherePrefix + ' [class*="qf_language_spouse_english_celpip"]').toggle(booShowCELPIP);
    $(wherePrefix + ' [class*="qf_language_spouse_english_general"]').toggle(booShowGeneral);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleLanguageFrenchFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_french_done :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShowTEF = false,
        booShowGeneral = false;

    if (el && el.length) {
        if (!empty(panelType)) {
            var selText = $(el).val().toLowerCase();
            booShowTEF = selText === 'yes';
            booShowGeneral = selText === 'no' || selText === 'not_sure';
        } else {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                var selText = $(el).find('option:selected').attr('data-val');
                booShowTEF = selText === 'yes';
                booShowGeneral = selText === 'no' || selText === 'not_sure';
            } else {
                booShowTEF = $(el).is(':checked');
            }
        }
    }
    $(wherePrefix + ' [class*="qf_language_french_tef"]').toggle(booShowTEF);
    $(wherePrefix + ' [class*="qf_language_french_general"]').toggle(booShowGeneral);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleSpouseLanguageFrenchFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_spouse_french_done :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShowTEF = false,
        booShowGeneral = false;

    if (el && el.length) {
        if (!empty(panelType)) {
            var selText = $(el).val().toLowerCase();
            booShowTEF = selText === 'yes';
            booShowGeneral = selText === 'no' || selText === 'not_sure';
        } else {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                var selText = $(el).find('option:selected').attr('data-val');
                booShowTEF = selText === 'yes';
                booShowGeneral = selText === 'no' || selText === 'not_sure';
            } else {
                booShowTEF = $(el).is(':checked');
            }
        }
    }
    $(wherePrefix + ' [class*="qf_language_spouse_french_tef"]').toggle(booShowTEF);
    $(wherePrefix + ' [class*="qf_language_spouse_french_general"]').toggle(booShowGeneral);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleLanguageFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_have_taken_test_on_english :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShow = false;

    if (el && el.length) {
        if (empty(panelType)) {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                booShow = $(el).find('option:selected').attr('data-val') === 'yes';
            } else {
                booShow = $(el).is(':checked');
            }
        } else {
            booShow = $(el).val().toLowerCase() === 'yes';
        }
    }
    $(wherePrefix + ' .main.language').toggle(booShow);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function toggleSpouseLanguageFields(wherePrefix, panelType) {
    var el = $(wherePrefix + ' .qf_language_spouse_have_taken_test_on_english :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShow = false;

    if (el && el.length) {
        if (empty(panelType)) {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                booShow = $(el).find('option:selected').attr('data-val') === 'yes';
            } else {
                booShow = $(el).is(':checked');
            }
        } else {
            booShow = $(el).val().toLowerCase() === 'yes';
        }
    }

    $(wherePrefix + ' .spouse_field.language').toggle(booShow);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initAssessmentFields(pid, where, panelType) {
    $(where + ' .assessment_section :input').each(function () {
        $(this).change(function () {
            toggleAssessmentFields(pid, where, panelType);
        });
    });
    toggleAssessmentFields(pid, where, panelType);
}

function toggleAssessmentFields(pid, where, panelType) {
    var el = $(where + ' .assessment_section :input:last'),
        booShow = $(el).is(':checked');

    $('#p_' + pid + '_field_visa').parents('.field_visa_container').toggle(booShow);

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function initExperienceBusiness(where, panelType) {
    $(where + ' .qf_have_experience_in_managing :input').each(function () {
        $(this).change(function () {
            toggleExperienceCombo(where, panelType);
        });
    });
    toggleExperienceCombo(where, panelType);
}

function toggleExperienceCombo(where, panelType) {
    var el = $(where + ' .qf_have_experience_in_managing :input' + (!empty(panelType) ? '.profile-select' : '') + ':first'),
        booShow = false;

    if (el.length) {
        if (!empty(panelType)) {
            booShow = $(el).val().toLowerCase() === 'yes';
        } else {
            if ($(el).prop("tagName").toLowerCase() === 'select') {
                booShow = $(el).find('option:selected').attr('data-val') === 'yes';
            } else {
                booShow = $(el).is(':checked');
            }
        }

        $(where + ' .qf_was_the_turnover_greater').toggle(booShow);

        if (!empty(panelType)) {
            var tabPanel = Ext.getCmp(panelType + '-tab-panel');
            if (tabPanel) {
                tabPanel.fixParentPanelHeight();
            }
        }
    }
}

function initRadioButtons() {

    $('td.qf_language_have_taken_test_on_english').eq(1).css("padding-left", "41px");
    $('tr.qf_spouse_is_resident_of_australia td').css("padding-bottom", "0px");
    $('tr.qf_spouse_is_resident_of_new_zealand td').css("padding-bottom", "0px");
    $('tr.qf_language_spouse_have_taken_test_on_english table td').css("padding-bottom", "0px");
    $('tr.qf_was_the_turnover_greater table td table td').css("padding-top", "5px");
    $('td.qf_prepared_to_invest').eq(1).css("padding-left", "41px");
    $('td.qf_have_experience_in_managing').eq(1).css("padding-left", "41px");
    $('td.qf_is_your_net_worth').eq(1).css("padding-left", "41px");
    $('tr.qf_have_you_completed_any_qualification table td').css("padding-bottom", "0px");
}


function initEditableComboDNA(panelType) {
    $('.combo-dna').each(function () {
        var data = Ext.decode($(this).html());
        var options = [];
        for (var opt in data.options) {
            options.push([opt, data.options[opt]]);
        }

        $(this).replaceWith('<div class="editable-combo-dna" id="c-' + data.id + '"' + '></div>');
        new Ext.form.ComboBox({
            id: data.id,
            name: data.id,
            store: new Ext.data.SimpleStore({
                fields: ['dna_id', 'dna_name'],
                data: options
            }),
            mode: 'local',
            displayField: 'dna_name',
            valueField: 'dna_id',
            triggerAction: 'all',
            selectOnFocus: true,
            renderTo: 'c-' + data.id,
            value: data['default'],
            width: empty(panelType) ? 220 : 240
        });
    });
}


function initializeAllProspectMainFields(tabId, panelType) {
    // Hide all spouse fields and listen for changes in 'Marital status' combo
    initMaritalStatus(tabId, panelType);

    //Hide all current address fields and listen for changes in 'Country of current Residence' combo
    initCurrentCountry(tabId, panelType);

    // Hide  field 'Visa Refused Or Cancelled' and listen for changes in 'Applied For Visa Before' radio
    initVisaRefusedOrCancelled(tabId, panelType);

    // Hide  fields of "SPONSORSHIP BY ELIGIBLE RELATIVE IN DESIGNATED AREA"
    initDesignatedAreaFields(tabId, panelType);

    initDNAField(tabId, panelType);

    initEducationalQualifications(tabId, panelType);

    initSpouseEducationalQualifications(tabId, panelType);

    // Hide fields in the Professional Programs block
    initProfessionalProgramsBlock(tabId, panelType);

    // Hide  field 'Is Partner Resident Of New Zealand' and listen for changes in 'Spouse is Resident of Australia' radio
    initIsPartnerResidentOfNewZealand(tabId, panelType);

    // Hide all sections and listen for changes in 'Area of Interest' checkbox
    initAreaOfInterest(tabId, panelType);

    //Hide all other fields and listen for changes in 'Other' point of 'Area of Interest' checkbox
    initOthers(tabId, panelType);

    //Hide 'Bachelor degree' fields and listen for changes in 'Highest Qualification' radio
    initHighestQualification(tabId, panelType);

    //Hide 'Duration of Course' and 'Was Course in Regional' fields and listen for changes in 'Country of Education' combo
    initCountryOfEducation(tabId, panelType);

    //Hide 'Score' fields and listen for changes in 'Have Taken English test' radio
    initLanguage(tabId, panelType);

    // Hide not needed Children fields
    initChildrenFields(tabId, panelType);

    // Init rules to hide or show fields
    initQnrFields(tabId);
    $('.x-form-field-wrap.x-form-field-trigger-wrap[style="width: 0px;"]').css('width', '');
}