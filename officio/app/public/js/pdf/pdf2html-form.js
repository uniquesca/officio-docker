var baseUrl = '';
Ext.BLANK_IMAGE_URL = baseUrl + '/js/ext/resources/images/default/s.gif';

// TODO: fix access rights
//+ TODO: checkbox redesign (fix size)
// TEST URL: http://officio_employers/pdf/1/?assignedId=1

// Determine whether a variable is empty
function empty(mixed_var) {
    return (typeof(mixed_var) === 'undefined' || mixed_var === '' ||
    mixed_var === 0 || mixed_var === '0' || mixed_var === null ||
    mixed_var === false);
}


function getOffset(el) {
    var _x = 0;
    var _y = 0;
    while (el && !isNaN(el.offsetLeft) && !isNaN(el.offsetTop)) {
        _x += el.offsetLeft - el.scrollLeft;
        _y += el.offsetTop - el.scrollTop;
        el = el.offsetParent;
    }
    return {
        top:  _y,
        left: _x
    };
}

function setValidTabIndex() {
    var elems = [];
    // collect all form fields in array with their positions
    $('form input, form textarea, form select').each(function () {
        var $self = $(this);
        if ($self.is(':visible')) {
            elems.push({
                'obj':      $self,
                'position': getOffset($self[0])
            });
        }
    });

    // sort as 2-dimensional array, by TOP firstly, then by LEFT offset value.
    var sorted = elems.sort(function(a, b){
        if (a.position.top < b.position.top)
            return -1;
        else if (a.position.top > b.position.top)
            return 1;
        else return a.position.left > b.position.left ;
    });
    $(sorted).each(function(idx, elm) {
        elm.obj.attr('tabindex', idx+1);
    });


}

function fillFieldValue(fieldId, fieldVal) {

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

function clearAllFields() {
    // iterate over all of the inputs for the form
    // element that was passed in
    $('form input, form textarea, form select').each(function () {
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

Ext.onReady(function(){

    var arrIds = window.location.href.match('pdf[\/]([0-9]*)[\/][\?]assignedId=([0-9]*)');

    // Reset all fields
    clearAllFields();

    setValidTabIndex();

    $('form input, form textarea').each(function () {
        var $self = $(this);
        if ($self.attr("type") == "checkbox" || $self.attr("type") == "radio") {
            return;
        }

        // add extra css rules to "textarea" field:
        if ($self[0].nodeName.toLowerCase() == "textarea") {
            var h = $self.height() - 10 + "px";
            $self.css({
                "padding-top":    "5px",
                "padding-bottom": "5px",
                "height":         h
            });
        }
        // add side padding for text fields
        var w = $self.width() - 10 + "px";
        $self.css({
            "padding-left":  "5px",
            "padding-right": "5px",
            "width":         w
        });
    });

    if(arrIds && arrIds.length>0) {
        var formVersionId = arrIds[1];
        var assignedId    = arrIds[2];

        $('form input[type=checkbox]').change(function () { // Don't allow to check several checkboxes with the same name
            $('input[type=checkbox][name=' + $(this).attr('name') + ']').not(this).each(function (idx, el) {
                el.checked = false;
            });
        });

        var date = new Date();
        var year = date.getFullYear() + 10;

        $(".datepicker_date").each(function () {
            var $this         = $(this);
            var pdfDateFormat = $this.data('format').toLowerCase();
            if (pdfDateFormat == 'mmm-yyyy') {
                $this.datepicker({
                    dateFormat:      'mm-yy',
                    changeMonth:     true,
                    changeYear:      true,
                    yearRange:       '1900:' + year,
                    showButtonPanel: true
                }).focus(function () {
                    var thisCalendar = $(this);
                    $('.ui-datepicker-calendar').detach();
                    $('.ui-datepicker-close').click(function () {
                        var month = $("#ui-datepicker-div .ui-datepicker-month :selected").val();
                        var year  = $("#ui-datepicker-div .ui-datepicker-year :selected").val();
                        thisCalendar.datepicker('setDate', new Date(year, month, 1));
                    });
                });
            } else { // 'dd-mmm-yyyy' and others
                $this.datepicker({
                    dateFormat:  'dd-mm-yy',
                    changeMonth: true,
                    changeYear:  true,
                    yearRange:   '1900:' + year
                });
            }

        });

        if (assignedId != null) {
            // Run request to load data from server
            Ext.getBody().mask('Loading...');
            Ext.Ajax.request({
                url:     baseUrl + '/forms/sync/get-data-for-html',
                params:  {
                    assignedId: assignedId
                },
                success: function (result) {
                    Ext.getBody().unmask();

                    var resultData = Ext.decode(result.responseText);
                    if (resultData.success) {
                        Ext.each(resultData.data, function (oField) {
                            fillFieldValue(oField.field_id, oField.field_val);
                        });
                    } else {
                        Ext.simpleConfirmation.error('An error occurred:\n<br/>' + '<span style="color: red;">' + resultData.message + '</span>');
                    }
                },
                failure: function () {
                    Ext.simpleConfirmation.error('Cannot load information. Please try again later.');
                    Ext.getBody().unmask();
                }
            });
        } else {
            Ext.getBody().mask('Not correct incoming data...');
        }
    } else {
        //Ext.getBody().mask('Not correct incoming data...');
    }
});