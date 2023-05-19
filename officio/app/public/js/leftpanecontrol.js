var divClientsTab_minwidth = 0;
var divClientsTab_maxwidth = 0;

function hideleft(parent) {
    syncClientsSize(parent);

    if (divClientsTab_minwidth > 0) {
        $(parent + ' .div-sub-tab-content').width(divClientsTab_minwidth);
    }

    if (divClientsTab_maxwidth === 0) {
        divClientsTab_maxwidth = $(parent + ' .div-sub-tab-content').width();
    }

    $(parent + ' .show-left-p').hide();
    $(parent + ' .hide-left-p').html('<a href="#" onClick="showleft(\'' + parent + '\'); return false;"><img src="' + baseUrl + '/images/arrow-button-exted.gif" alt="" /></a>');
}

function showleft(parent) {
    syncClientsSize(parent);

    if (divClientsTab_minwidth === 0) {
        divClientsTab_minwidth = $(parent + ' .div-sub-tab-content').width();
    }

    if (divClientsTab_maxwidth > 0) {
        $(parent + ' .div-sub-tab-content').width(divClientsTab_maxwidth);
    }

    $(parent + ' .show-left-p').show();
    $(parent + ' .hide-left-p').html('<a href="#" onClick="hideleft(\'' + parent + '\'); return false;"><img src="' + baseUrl + '/images/arrow-button.gif" alt="" /></a>');
}

function syncClientsSize(parent) {

    $(parent + ' .x-panel, ' + parent + ' .x-tab-panel').each(function () {
        Ext.getCmp($(this).attr('id')).syncSize();
    });

    setTimeout(function () {
        $(parent + ' .x-grid-panel').each(function () {
            $(this).css('width', 'auto');
            Ext.getCmp($(this).attr('id')).syncSize();
        });
    }, 500);
}
