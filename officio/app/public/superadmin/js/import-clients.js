$(document).ready(function(){
    $('#import-clients-content').css('min-height', getSuperadminPanelHeight() - 21 + 'px');

    $('#check_all_fields').click(function(){
        $('.columns-mapping td input[type=checkbox]').trigger('click');
    });

    $('.show_details').mouseover(function(){
        $('.import_errors_details').hide();
        $(this).parent().find('.import_errors_details').show();
    });

    $('.import_errors_details').mouseleave(function(){
        $(this).hide();
    });

    // Listen for click on client type radios and automatically refresh case templates in relation to this
    $('input[name=import_client_type]').change(function () {
        var clientType = $(this).val(),
            caseTypeCombo = $('#import_case_type');

        caseTypeCombo.find('option').remove();
        caseTypeCombo.append(new Option('-- Please select --', ''));
        $.each(arrCaseTemplates, function (index, oTemplateInfo) {
            if (oTemplateInfo.case_template_type_names.has(clientType)) {
                caseTypeCombo.append(new Option(oTemplateInfo.case_template_name, oTemplateInfo.case_template_id));
            }
        });
    });
});

function step4(page) {
    Ext.Ajax.request({
        url: baseUrl + '/import-clients/step4',
        params: {
            page: page
        },
        success: function (f) {
            var resultData = Ext.decode(f.responseText);

            $('#import-clients-log').append(resultData.log);

            var new_height = $('#import-clients-content').height() + 82;
            $("#admin_section_frame", top.document).height(new_height + 'px');

            var percent = resultData.percent;
            var progressSpan = $('#import-clients-percent-progress-span');

            if (percent >= 100) {
                progressSpan.css('left', '30%');
                progressSpan.html('100% Successfully completed!');
            } else {
                progressSpan.css('left', '46%');
                progressSpan.html(percent + '%');
            }

            if (percent > 50) {
                progressSpan.css('color', 'white');
            } else {
                progressSpan.css('color', '#006400');
            }

            $('#import-clients-percent-progress-div').css('width', Math.round(400 * percent / 100));

            if (resultData.booContinue) {
                step4(resultData.page);
            }
        },

        failure: function () {
            Ext.simpleConfirmation.error(_('Internal Error.'));
        }
    });
}
