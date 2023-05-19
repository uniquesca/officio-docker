function reloadHomepageRss() {
    var rssForm = $('#rssHomepageForm');
    if (rssForm.length) {
        rssForm.html('<img src="' + imagesUrl + '/loading.gif" alt="loading" />').load(baseUrl + '/rss/index/get');
    }
}

function disclaimer()
{
    var text;
    switch (site_version) {
        case 'australia':
            text = 'The immigration news feed is provided to you as a value-added service to keep you informed of what ' +
                'is reported globally on Australian immigration matters. Officio processes all the news feed ' +
                'automatically from the DIBP and various other Australian and international news RSS feeds using ' +
                'automated software without any human intervention or screening.<br/><br/>' +

                'The news articles reported in this section do not represent our values and Officio Pty Ltd is not ' +
                'responsible for the tone, validity, and accuracy of this content.<br/><br/>' +

                'If you wish the news feed not to be displayed in your account, please go to "Admin | Define Roles" ' +
                'and disable the News option for selected users and roles. If you need any assistance, please do not ' +
                'hesitate to contact our support line.';
            break;

        case 'canada':
        default:
            text = 'The Immigration news feed is provided to you as a value-added service to keep you informed of what ' +
                'is reported globally on Canada Immigration matters. Officio processes all the news feed automatically from ' +
                'the CIC and various other Canadian and International News RSS feeds using automated software without any ' +
                'human intervention or screening.<br/><br/>' +

                'The news articles reported in this section do not represent our values and Uniques Software is not ' +
                'responsible for the tone, validity, and accuracy of this content.<br/><br/>' +

                'If you wish the news feed not to be displayed in your account, please go to "Admin | Define Roles" ' +
                'and disable the News option for selected users and roles. If you need any assistance, please do not ' +
                'hesitate to contact our support line.';
    }

    Ext.Msg.show({
        title: 'Disclaimer',
        msg: text,
        minWidth: 525,
        modal: true,
        buttons: false,
        icon: Ext.Msg.INFO
    });
}

$(document).ready(function() {
    $(document).on('click', '#rssHomepageForm a', function() {
        $(this).attr('target', '_blank');
    });
});