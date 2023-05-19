$(document).ready(function () {
    $(".lightbox-image").append("<span></span>");

    $('.lightbox-image')
        .on('mouseenter', function () {
            $(this).find("span").stop()
                .animate({top: 0}, {duration: 500, easing: 'easeOutQuart'});
        })
        .on('mouseleave', function () {
            $(this).find("span").stop()
                .animate({top: '-100%'}, {duration: 500, easing: 'easeOutQuart'});
        });
});