$(window).on('load', function () {
    $('.help-loading').hide();
    $('.help-content').show();

    // Automatically open a correct category/question
    parseHashAndOpenQuestionOrCategory();

    // Listen for changes in the browser hash - e.g. when clicked on the link or used Back/Forward History button
    $(window).on('popstate', function(event) {
        parseHashAndOpenQuestionOrCategory();
    });

    // If we click on the category and it is active already - try to force to expand/collapse it
    $(document).on('click', 'a[href*="#c"]', function () {
        if (window.location.hash === $(this).attr('href')) {
            parseHashAndOpenQuestionOrCategory();
        }
    });

    $("input[data-remote-load]").each(function () {
        setHelpSearchFieldListener($(this));
    });

    initFancybox();
    initLazyIframes();
});