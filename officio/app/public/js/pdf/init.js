var baseUrl = '';
Ext.BLANK_IMAGE_URL = baseUrl + '/js/ext/resources/images/default/s.gif';

// Determine whether a variable is empty
function empty(mixed_var) {
    return (typeof(mixed_var) === 'undefined' || mixed_var === '' ||
    mixed_var === 0 || mixed_var === '0' || mixed_var === null ||
    mixed_var === false);
}

// Convert string to boolean
var stringToBoolean = function(mixed_var) {
    if (typeof(mixed_var) === 'undefined' || mixed_var === null) {
        return false;
    }
    switch (mixed_var.toLowerCase()) {
        case 'true': case 'yes': case '1': return true;
        case 'false': case 'no': case '0': return false;
        default: return Boolean(mixed_var);
    }
};

/**
 * Submit fields from html form to server
 */
function submitInfo() {
    $('input[type=checkbox]').each(function() {
        var booInsertHiddenField = true;
        $('input[type=checkbox][name=' + this.name + ']').each(function() {
            if ($(this).is(':checked')) {
                booInsertHiddenField = false;
            }
        });
        if (booInsertHiddenField) {
            $(this).closest('form').find('input[type="hidden"][name="'+ this.name +'"]').remove();
            $(this).closest('form').append("<input type='hidden' name='" + this.name + "' value='' />");
        }
    });
    // Collect all info from all forms
    var arrParams = $('form')
        .map(function () {
            return $(this).serializeArray();
        })
        .get();

    $("input[type='hidden']").remove();

    var arrIds = window.location.href.match('pdf[\/]([0-9]*)[\/][\?]assignedId=([0-9]*)');
    var formVersionId = arrIds[1];
    var assignedId = arrIds[2];

    Ext.getBody().mask('Saving...');
    Ext.Ajax.request({
        url: baseUrl + '/forms/sync/save-data-from-html',
        params: {
            assignedId: assignedId,
            formVersionId: formVersionId,
            arrFormFields: Ext.encode(arrParams)
        },
        success: function (result) {
            var resultData = Ext.decode(result.responseText);
            if (resultData.success) {
                Ext.simpleConfirmation.info(resultData.message);
                Ext.each(resultData.data, function (oField) {
                    fillFieldValue(oField.field_id, oField.field_val, formVersionId);
                });
            } else {
                Ext.simpleConfirmation.error('An error occurred:\n<br/>' + '<span style="color: red;">' + resultData.message + '</span>');
            }
            Ext.getBody().unmask();
        },
        failure: function () {
            Ext.simpleConfirmation.error('Cannot save information. Please try again later.');
            Ext.getBody().unmask();
        }
    });

}

/**
 * Submit fields from html form to server, save in the temp file, merge data and get pdf file
 */
function print() {
    var arrIds = window.location.href.match('assignedId=([0-9]*)');
    var assignedId = arrIds[1];

    var arrParams = $('form')
        .map(function () {
            return $(this).serializeArray();
        })
        .get();

    Ext.Ajax.request({
        url: baseUrl + '/forms/sync/print',
        params: {
            assignedId: assignedId,
            arrParams: Ext.encode(arrParams)
        },
        success: function (result) {
            Ext.getBody().unmask();
            var resultDecoded = Ext.decode(result.responseText);
            if (!resultDecoded.error) {
                window.open(baseUrl + '/templates/index/view-pdf?file=' + escape(resultDecoded.filename) + '&check_id=' + assignedId + '&check_type=form');
            } else {
                Ext.simpleConfirmation.error(resultDecoded.error);
            }
        },
        failure: function () {
            Ext.getBody().unmask();
            Ext.simpleConfirmation.error('Can\'t create Document.');
        }
    });
}

function addPage(nextPageNumber) {
    var nextPage = $('#page' + nextPageNumber);
    if (nextPage.hasClass('active')) {
        return;
    }
    var currentPageNumber = --nextPageNumber;
    var currentPage = $('#page' + currentPageNumber);
    nextPage.removeClass('hidden');
    nextPage.addClass('active');
    nextPage.addClass('visible');
    currentPage.find('input[name=add_page_button]').hide();
    currentPage.find('input[name=remove_page_button]').hide();
    updatePageNumber();
}

function removePage(currentPageNumber) {
    var currentPage = $('#page' + currentPageNumber);
    var previousPageNumber = --currentPageNumber;
    var previousPage = $('#page' + previousPageNumber);
    currentPage.find('form').trigger('reset');
    currentPage.removeClass('visible');
    currentPage.addClass('hidden');
    currentPage.removeClass('active');
    previousPage.find('input[name=add_page_button]').show();
    previousPage.find('input[name=remove_page_button]').show();
    updatePageNumber();
}

function updatePageNumber() {
    var pageNumberCombo = $('#goBtn');
    var currentPageNumber = pageNumberCombo.val();
    var num = 1;
    for (var i = 1;  i < 27; i++) {
        var page = $('#page' + i);
        var pageClass = page.attr('class').match('(num[0-9]*)');
        if (page.hasClass('active visible')) {
            if(pageClass != null) {
                page.attr('class', page.attr('class').replace(pageClass[1], 'num' + num));
            } else {
                page.addClass('num' + num);
            }
            num++;
        } else {
            if(pageClass != null) {
                page.removeClass(pageClass[1]);
            }
        }
    }
    pageNumberCombo.find('option').remove();
    for (var j = 1; j < num; j++) {
        $("#goBtn").append('<option value=' + j + '>'+ j + '</option>');
    }
    num--;
    $('#pgCount').html('/' + num);
    pageNumberCombo.val(currentPageNumber);
}

function clearAllFields() {
    // iterate over all of the inputs for the form
    // element that was passed in
    $('input').each(function () {
        var input = this;
        var type = input.type;
        var tag = input.tagName.toLowerCase(); // normalize case
        // it's ok to reset the value attr of text inputs,
        // password inputs, and textareas
        if (type == 'text' || type == 'password' || tag == 'textarea') {
            input.value = "";
        }
        // checkboxes and radios need to have their checked state cleared
        // but should *not* have their 'value' changed
        else if (type == 'checkbox' || type == 'radio') {
            input.checked = false;
        }
        // select elements need to have their 'selectedIndex' property set to -1
        // (this works for both single and multiple select elements)
        else if (tag == 'select') {
            input.selectedIndex = -1;
        }
    });
}

function fillFieldValue(fieldId, fieldVal, formId) {

    //Check dependant's checkboxes and show them on separate pages
    if (formId == '96') {
        var expression = new RegExp("^syncA.*_s$|^PV_QNR.*_s$");
        var result = expression.exec(fieldId);
        if (result != null && fieldVal != null && fieldVal != '' && fieldVal != ' ') {
            setTimeout(function () {
                $('#ap_partner1').prop('checked', true);
                var activePages = $('.partner.active');
                activePages.toggleClass('hidden', false);
                activePages.toggleClass('visible', true);
                updatePageNumber();
            }, 100);
            if (parseInt(result[1]) > 1) {
                addPage(4 + parseInt(result[1]));
            }
        }

        expression = new RegExp("^syncA.*_c([1-6])$|^PV_QNR.*_c([1-6])$");
        result = expression.exec(fieldId);
        if (result != null && fieldVal != null && fieldVal != '' && fieldVal != ' ') {
            setTimeout(function () {
                $('#ap_children8').prop('checked', true);
                var activePages = $('.child.active');
                activePages.toggleClass('hidden', false);
                activePages.toggleClass('visible', true);
                updatePageNumber();
            }, 100);
            if (parseInt(result[1]) > 1) {
                addPage(4 + parseInt(result[1]));
            }
        }

        expression = new RegExp("^syncA.*_p([1-4])$|^PV_QNR.*_p([1-4])$");
        result = expression.exec(fieldId);
        if (result != null && fieldVal != null && fieldVal != '' && fieldVal != ' ') {
            setTimeout(function () {
                $('#ap_parents4').prop('checked', true);
                var activePages = $('.parent.active');
                activePages.toggleClass('hidden', false);
                activePages.toggleClass('visible', true);
                updatePageNumber();
            }, 100);
            if (parseInt(result[1]) > 1) {
                addPage(10 + parseInt(result[1]));
            }
        }

        expression = new RegExp("^syncA.*_sb([1-5])$|^PV_QNR.*_sb([1-5])$");
        result = expression.exec(fieldId);
        if (result != null && fieldVal != null && fieldVal != '' && fieldVal != ' ') {
            setTimeout(function () {
                $('#ap_siblings3').prop('checked', true);
                var activePages = $('.sibling.active');
                activePages.toggleClass('hidden', false);
                activePages.toggleClass('visible', true);
                updatePageNumber();
            }, 100);
            if (parseInt(result[1]) > 1) {
                addPage(14 + parseInt(result[1]));
            }
        }

        expression = new RegExp("^syncA.*_f([1,2])$|^PV_QNR.*_f([1,2])$");
        result = expression.exec(fieldId);
        if (result != null && fieldVal != null && fieldVal != '' && fieldVal != ' ') {
            setTimeout(function () {
                $('#ap_family_member2').prop('checked', true);
                var activePages = $('.family_member.active');
                activePages.toggleClass('hidden', false);
                activePages.toggleClass('visible', true);
                updatePageNumber();
            }, 100);
            if (parseInt(result[1]) > 1) {
                addPage(19 + parseInt(result[1]));
            }
        }
    }


    if (fieldId == 'server_locked_form' && fieldVal == 1) {
        Ext.simpleConfirmation.error('The forms are locked by the office. If you need to make any changes, please contact them for assistance.');
        $('#save_form_button').hide();
        $('#print_form_button').hide();
    }
    var arrFields = $('[name="' + fieldId + '"]');
    if (arrFields.length) {
        $.each(arrFields, function (index, oField) {
            var type = oField.type;
            var tag = oField.tagName.toLowerCase(); // normalize case
            if (tag == 'select' || type == 'text' || type == 'password' || tag == 'textarea') {
                $(oField).val(fieldVal);
            } else if (type == 'checkbox' || type == 'radio') {
                if ($(oField).val() == fieldVal) {
                    $(oField).trigger('click');
                }
            }
        });
    }
}

Ext.onReady(function () {

    var arrIds = window.location.href.match('pdf[\/]([0-9]*)[\/][\?]assignedId=([0-9]*)');
    var formVersionId = arrIds[1];
    var assignedId = arrIds[2];

    var submitButtons = $('input[name=server_submit_button]');
    // Disable all submit buttons
    // to prevent incorrect data submission
    submitButtons.attr('disabled', 'disabled');
    submitButtons.remove();

    if (assignedId != null) {
        var saveButton = '<input id="save_form_button" type="button" name=save_form_button onclick="submitInfo();"/>';
        var printButton = '<input id="print_form_button" type="button" name=print_form_button onclick="print();"/>';
        $('#btnPrev').before(saveButton);
        $('#btnZoomIn').after(printButton);
    } else {
        Ext.getBody().mask('Not correct incoming data...');
    }
    if (formVersionId == '96') {
        $("#goBtn").change(function() {
            IDRViewer.goToPage($(this).val());
        });
        for (var i = 1; i < 27; i++) {
            var page = $('#page' + i);
            if (i > 3 && i < 22) {
                page.css('position', '');
                page.addClass('hidden');
            } else {
                page.addClass('active visible');
                page.addClass('num' + i);
            }
            switch (i) {
                case 4:
                    page.addClass('partner');
                    page.addClass('active');
                    break;
                case 5:
                    page.addClass('child');
                    page.addClass('active');
                    page.find('form').append(String.format('<input type="button" id="add_children_form_button"  name="add_page_button" onclick="addPage({0});" style="left: 395px;"/>', i + 1));
                    break;
                case 6:
                case 7:
                case 8:
                case 9:
                    page.addClass('child');
                    page.find('form').append(String.format('<input type="button" id="add_children_form_button"  name="add_page_button" onclick="addPage({0});"/>', i + 1));
                    page.find('form').append(String.format('<input type="button" id="remove_child_form_button"  name="remove_page_button" onclick="removePage({0});"/>', i));
                    break;
                case 10:
                    page.addClass('child');
                    page.find('form').append(String.format('<input type="button" id="remove_child_form_button"  name="remove_page_button" onclick="removePage({0});" style="left: 395px;"/>', i));
                    break;
                case 11:
                    page.addClass('parent');
                    page.addClass('active');
                    page.find('form').append(String.format('<input type="button" id="add_parents_form_button"  name="add_page_button" onclick="addPage({0});" style="left: 395px;"/>', i + 1));
                    break;
                case 12:
                case 13:
                    page.addClass('parent');
                    page.find('form').append(String.format('<input type="button" id="add_parents_form_button"  name="add_page_button" onclick="addPage({0});"/>', i + 1));
                    page.find('form').append(String.format('<input type="button" id="remove_parent_form_button"  name="remove_page_button" onclick="removePage({0});"/>', i));
                    break;
                case 14:
                    page.addClass('parent');
                    page.find('form').append(String.format('<input type="button" id="remove_parent_form_button"  name="remove_page_button" onclick="removePage({0});" style="left: 395px;"/>', i));
                    break;
                case 15:
                    page.addClass('sibling');
                    page.addClass('active');
                    page.find('form').append(String.format('<input type="button" id="add_siblings_form_button"  name="add_page_button" onclick="addPage({0});" style="left: 395px;"/>', i + 1));
                    break;
                case 16:
                case 17:
                case 18:
                    page.addClass('sibling');
                    page.find('form').append(String.format('<input type="button" id="add_siblings_form_button"  name="add_page_button" onclick="addPage({0});"/>', i + 1));
                    page.find('form').append(String.format('<input type="button" id="remove_sibling_form_button"  name="remove_page_button" onclick="removePage({0});"/>', i));
                    break;
                case 19:
                    page.addClass('sibling');
                    page.find('form').append(String.format('<input type="button" id="remove_sibling_form_button"  name="remove_page_button" onclick="removePage({0});" style="left: 395px;"/>', i));
                    break;
                case 20:
                    page.addClass('family_member');
                    page.addClass('active');
                    page.find('form').append(String.format('<input type="button" id="add_family_members_form_button"  name="add_page_button" onclick="addPage({0});" style="left: 395px;"/>', i + 1));
                    break;
                case 21:
                    page.addClass('family_member');
                    page.find('form').append(String.format('<input type="button" id="remove_family_member_form_button"  name="remove_page_button" onclick="removePage({0});" style="left: 395px;"/>', i));
                    break;
            }
        }

        $("input[name='ap_partner']").change(function() {
            setTimeout(function() {
                var booChecked = $('#ap_partner1').is(':checked');
                var activePages = $('.partner.active');
                activePages.toggleClass('hidden', !booChecked);
                activePages.toggleClass('visible', booChecked);
                updatePageNumber();
            }, 100);
        });

        $("input[name='ap_children']").change(function() {
            setTimeout(function() {
                var booChecked = $('#ap_children8').is(':checked');
                var activePages = $('.child.active');
                activePages.toggleClass('hidden', !booChecked);
                activePages.toggleClass('visible', booChecked);
                updatePageNumber();
            }, 100);
        });

        $("input[name='ap_parents']").change(function() {
            setTimeout(function() {
                var booChecked = $('#ap_parents4').is(':checked');
                var activePages = $('.parent.active');
                activePages.toggleClass('hidden', !booChecked);
                activePages.toggleClass('visible', booChecked);
                updatePageNumber();
            }, 100);
        });

        $("input[name='ap_siblings']").change(function() {
            setTimeout(function() {
                var booChecked = $('#ap_siblings3').is(':checked');
                var activePages = $('.sibling.active');
                activePages.toggleClass('hidden', !booChecked);
                activePages.toggleClass('visible', booChecked);
                updatePageNumber();
            }, 100);
        });

        $("input[name='ap_family_member']").change(function() {
            setTimeout(function() {
                var booChecked = $('#ap_family_member2').is(':checked');
                var activePages = $('.family_member.active');
                activePages.toggleClass('hidden', !booChecked);
                activePages.toggleClass('visible', booChecked);
                updatePageNumber();
            }, 100);
        });
    }

    // Reset all fields
    clearAllFields();

    // Remove unnecessary things
    $('#pg1Overlay').remove();

    $('input[type=checkbox]')
        // Show checkboxes, instead of images
        .each(function(){
            $('img#' + $(this).attr('id')).remove();
            $(this).show();
        })

        // Don't allow to check several checkboxes with the same name
        .change(function(){
            $('input[type=checkbox][name=' + $(this).attr('name') + ']').not(this).each(function(idx, el){
                el.checked = false;
            });
        });

    var date = new Date();
    var year = date.getFullYear() + 10;

    $(".datepicker_month_year").datepicker({
        dateFormat: 'mm-yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '1900:' + year,
        showButtonPanel: true
    }).focus(function() {
        var thisCalendar = $(this);
        $('.ui-datepicker-calendar').detach();
        $('.ui-datepicker-close').click(function() {
            var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
            var year = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
            thisCalendar.datepicker('setDate', new Date(year, month, 1));
        });
    });

    $(".datepicker_date").datepicker({
        dateFormat: 'dd-mm-yy',
        changeMonth: true,
        changeYear: true,
        yearRange: '1900:' + year
    });

    if (assignedId != null) {
        // Run request to load data from server
        Ext.getBody().mask('Loading...');
        Ext.Ajax.request({
            url: baseUrl + '/forms/sync/get-data-for-html', params: {
                assignedId: assignedId
            }, success:                                             function (result) {
                Ext.getBody().unmask();

                var resultData = Ext.decode(result.responseText);
                if (resultData.success) {
                    Ext.each(resultData.data, function (oField) {
                        fillFieldValue(oField.field_id, oField.field_val, formVersionId);
                    });
                } else {
                    Ext.simpleConfirmation.error('An error occurred:\n<br/>' + '<span style="color: red;">' + resultData.message + '</span>');
                }
            }, failure:                                             function () {
                Ext.simpleConfirmation.error('Cannot load information. Please try again later.');
                Ext.getBody().unmask();
            }
        });
    }
});