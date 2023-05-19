$(document).ready(function () {
    $('#import-client-notes-container').css('min-height', getSuperadminPanelHeight() - 21 + 'px');
});

function step4(page) {
    Ext.Ajax.request({
        url: baseUrl + '/import-client-notes/step4',
        params: {
            page: page
        },
        success: function (f) {
            var resultData = Ext.decode(f.responseText);

            $('#import-notes-log').append(resultData.additionalOutput);

            var new_height = $('#import-client-notes-container').height() + 82;
            $("#admin_section_frame", top.document).height(new_height + 'px');

            var percent = resultData.percent;
            var progressSpan = $('#import-notes-percent-progress-span');

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

            $('#import-notes-percent-progress-div').css('width', Math.round(400 * percent / 100));

            if (resultData.booContinue) {
                step4(resultData.page);
            }
        },

        failure: function () {
            Ext.simpleConfirmation.error(_('Internal Error.'));
        }
    });
}
