$(document).ready(function(){
    $('#import-bcpnp-content').css('min-height', getSuperadminPanelHeight() - 21 + 'px');

    $('#check_all_fields').click(function(){
        $('.columns-mapping td input[type=checkbox]').trigger('click');
    });
});

function step3 (page) {
    Ext.Ajax.request({
        url: baseUrl + '/import-bcpnp/step3',
        params: {
            page: page
        },
        success: function (f) {
            var resultData = Ext.decode(f.responseText);

            $('#import-bcpnp-log').append(resultData.log);

            var new_height = $('#import-bcpnp-content').height() + 82;
            $("#admin_section_frame", top.document).height(new_height + 'px');

            var percent = resultData.percent;
            var progressSpan = $('#import-bcpnp-percent-progress-span');

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

            $('#import-bcpnp-percent-progress-div').css('width', Math.round(400*percent/100));

            if (resultData.booContinue) {
                step3(resultData.page);
            }
        },

        failure: function () {
            Ext.simpleConfirmation.error(_('Internal Error.'));
        }
    });
}
