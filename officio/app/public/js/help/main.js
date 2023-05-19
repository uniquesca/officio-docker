var initFancybox = function () {
    $('#answer_to_question img').wrap(function() {
        var caption = typeof $(this).attr('alt') === 'undefined' ? '' : $(this).attr('alt');
        return '<a href="' + $(this).attr('src') + '" data-fancybox data-caption="' + caption + '" data-small-btn="true"></div>'
    });
};

var initLazyIframes = function () {
    $('#answer_to_question iframe.lazyload').each(function(){
        $(this).prop('src', $(this).attr('data-src')).removeClass('lazyload');
    });
};

function addHelpPage() {
    var tabPanel = getActiveMainTabPanel();
    tabPanel.setActiveTab('help-tab');
}

function addIlearnPage() {
    var tabPanel = getActiveMainTabPanel();
    tabPanel.setActiveTab('ilearn-tab');
}

function openHelpArticle(sectionType, articleId) {
    var url = topBaseUrl + '/help/index/index?type=' + sectionType + '&' + (new Date()).getTime() + '#q' + articleId;

    var tabPanel = typeof getActiveMainTabPanel === 'function' ? getActiveMainTabPanel() : null;
    var tabId    = sectionType + '-tab'; // help-tab or ilearn-tab
    var tab      = Ext.getCmp(tabId);

    if (tabPanel && tab) {
        tabPanel.setActiveTab(tabId);
        tab.getActiveTab().setSrc(url);
    } else {
        window.open(url, '_blank');
    }
}

function expand_category(category_id) {
    var this_cat_link     = $('#faq-section-id-' + category_id);
    var this_cat_nav_link = $('#nav-link-category-' + category_id);

    if (this_cat_link.length === 0 || this_cat_nav_link.hasClass('active'))
        return false;

    $('.nav-link').removeClass('active');
    this_cat_nav_link.addClass('active');

    if (helpType === 'public') {
        $('.faq-section-wrap').hide();
        this_cat_link.parents('.faq-section-wrap:last').show();
        this_cat_link.parents('.faq-section-wrap').find('.faq-section-wrap').show();
        this_cat_link.parents('.faq-section-wrap').find('.faq-section-content .faq-section-name').show();
    }

    // expand this category, don't update the hash - pass an extra param
    var category   = this_cat_link.closest('.faq-section-wrap').find('.faq-section-content:first');

    var parent_section    = null;
    var parent_section_id = null;
    if (this_cat_link.hasClass('faq-section-leaf')) {
        parent_section    = this_cat_link.parents('.faq-section-wrap:last').find('.faq-section-content:first');
        parent_section_id = this_cat_link.parents('.faq-section-wrap:last').find('.faq-section-name:first A.faq-section-link').attr('id').replace('faq-section-id-', '');
    }

    // collapse all other categories except this and it's parent
    $('.faq-section-content').each(function () {
        if (!$(this).hasClass('faq-section-content-heading')) {
            if ($(this).get(0) !== category.get(0) && (parent_section === null || $(this).get(0) !== parent_section.get(0))) {
                $(this).slideUp(300);
            }
        }
    });

    $('.faq-section-name').removeClass('expanded');
    this_cat_link.parents('.faq-section-name').toggleClass('expanded', category.is(":hidden"));

    // Make sure that all parent categories will be expanded and visible
    this_cat_link.parents('.faq-section-name.expanded').parents('.faq-section-content').show();

    if (this_cat_link.hasClass('faq-section-title')) {
        return false;
    }

    // collapse all questions
    $('.faq-question a').removeClass('active');
    $('#answer_to_question').hide();
    $('.faq-answer').slideUp(300);

    // expand this category
    category.slideToggle(300, function () {
    });

    return true;
}

function expand_question(question_id, is_regular_question) {
    var this_question_link = $(is_regular_question ? '#faq-question-id-' + question_id : '#faq-featured-question-id-' + question_id);

    if (this_question_link.length === 0) {
        return false;
    }

    // Expand all parent categories
    var booExpandedCategory = this_question_link.closest('.faq-section-wrap').find('.faq-section-name:first').hasClass('expanded');
    if (!booExpandedCategory) {
        var category_link = this_question_link.closest('.faq-section-wrap').find('.faq-section-name:first A.faq-section-link');
        if (category_link.length) {
            var category_id   = category_link.attr('id').replace('faq-section-id-', '');
            expand_category(category_id);
        } else {
            this_question_link.parents('.faq-section-wrap').each(function (i, container) {
                var category_link = $(container).find('.faq-section-name:first A.faq-section-link');
                if (category_link.length) {
                    var category_id = category_link.attr('id').replace('faq-section-id-', '');
                    expand_category(category_id);
                }
            });
        }
    }

    // open this question
    var answer = this_question_link.parent().parent().find('.faq-answer');
    var category_id = this_question_link.attr('top_category_id');

    $('.faq-question a').removeClass('active');
    this_question_link.addClass('active');

    $('.nav-link').removeClass('active');
    $('#nav-link-category-' + category_id).addClass('active');

    $('#answer_to_question').hide().html('<h3>' + this_question_link.html() + '</h3>' + answer.html()).fadeIn(500, 'swing', function () {
        initFancybox();
        initLazyIframes();
    });

    return true;
}

function setHelpSearchFieldListener(field) {
    field.attr('autocomplete', 'off');
    var what       = field.data('remote-load');
    var type       = field.data('section-type');
    var cachedData = [];
    var timeout;

    $(field).typeahead({
        highlight: true
    }, {
        display: 'value',
        limit: 5,
        source:  function (query, syncResults, asyncResults) {
            if (timeout) {
                clearTimeout(timeout);
            }

            // Need a delay before sending request to server
            timeout = setTimeout(function () {
                $.get(baseUrl + '/help/public/search?what=' + what + '&type=' + type + '&query=' + query, function (data) {
                    cachedData = data;
                    asyncResults(data);
                });
            }, 300);
        }
    }).on('change blur', function () {
        // Make sure that typed text was found in the list
        var booFound  = false;
        var typedText = $(this).val();
        $.each(cachedData, function (index, obj) {
            if (obj.value === typedText) {
                booFound = true;
            }
        });

        if (!booFound) {
            $(this).val('');
        }
    }).on('typeahead:selected', function (obj, data) {
        switch (data['type']) {
            case '':
                break;

            case 'article':
            default:
                window.location.hash = '#q' + data['id'];
                break;
        }
    });
}

function parseHashAndOpenQuestionOrCategory() {
    // if we have category_id or question_id in hash, let's open that category or question!
    // examples of hash:
    // - a regular question: #q123,
    // - a featured question: #f456,
    // - a category: #c3
    var hashRegexp = /^#([cqf])(\d+)$/;
    var match      = hashRegexp.exec(window.location.hash);
    if (match != null) {
        switch (match[1]) {
            case 'q': // a regular question
            case 'f': // a featured question
                if (!expand_question(match[2], match[1] == 'q')) {
                    // Ext.simpleConfirmation.error("The item you were looking for is not found, please view the complete help list.");
                }
                break;

            case 'c':
                if (!expand_category(match[2])) {
                    // Ext.simpleConfirmation.error("The item you were looking for is not found, please view the complete help list.");
                }
                break;

            default:
        }
    } else if (typeof defaultCategoryToOpen !== 'undefined' && defaultCategoryToOpen != '') {
        expand_category(defaultCategoryToOpen);
    }
}