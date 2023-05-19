Ext.onReady(function(){
    var currentPage = 1;
    var $navBar = $('<div class="nav-bar">' +
                        '<div class="controlls">' +
                            '<button id="save_form_button" name=save_form_button onclick="submitInfo();">Save</button>' +
                            '<div class="nav-back-btn nav-button" title="Previous Page"></div>' +
                            '<span class="page-counter">1/' + pagesCount + '</span>' +
                            '<div class="nav-forward-btn nav-button" title="Next Page"></div>' +
                            '<button id="print_form_button" name=print_form_button onclick="print();">Print</button>' +
                            '<label><input id="highlight_fields" type="checkbox" name="highlight_fields" value="1" checked="checked"/>&nbsp;Highlight fields</label>' +
                        '</div>' +
                    '</div>');
    $('.main').prepend($navBar);
    var $counter = $navBar.find('.page-counter');

    if (pagesCount == 1) {
        $navBar.find('button[name=forward]').attr('disabled', 'disabled');
    }

    function hideCurrentShowNextPage(order) {
        // hide current page
        $('.page-' + currentPage).hide();
        // increment or decrement current page counter
        currentPage = order > 0 ? currentPage + 1 : currentPage - 1;
        // show next page
        $('.page-' + currentPage).show();
        // update counter span
        $counter.text(currentPage + '/' + pagesCount);
        setValidTabIndex();
    }

    $navBar.find('#highlight_fields').click(function () {
        if($(this).is(':checked')) {
            $('input, textarea, select').addClass('highlighted_field');
        } else {
            $('input, textarea, select').removeClass('highlighted_field');
        }
    }).click().prop('checked', true);

    $navBar.find('.nav-back-btn').click(function () {
        if (currentPage > 1) {
            $navBar.find('.nav-forward-btn').removeAttr('disabled');
            hideCurrentShowNextPage(-1);
        }
        if (currentPage == 1) {
            $(this).attr('disabled', 'disabled');
        }
    });

    $navBar.find('.nav-forward-btn').click(function () {
        if (currentPage < pagesCount) {
            $navBar.find('.nav-back-btn').removeAttr('disabled');
            hideCurrentShowNextPage(1);
        }
        if (currentPage == pagesCount) {
            $(this).attr('disabled', 'disabled');
        }
    });

});