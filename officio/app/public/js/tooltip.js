$(document).ready(function(){
    var showTT = function(x,y) {

        var dx = 20;
        var dy = 20;
        var pW = parseInt($('#preview').outerWidth(), 10);
        var left = (pW + x + dx) > parseInt($(window).width(), 10) ? x - pW - dx : x + dx;
        var top = y + dy;

        $("#preview").css("top", top + "px")
                     .css("left", left + "px");
    };

    $("a.preview").hover(function(e){
        $("body").append("<p id='preview'><img src='"+ this.href +"' alt='' /></p>");
        $("#preview").css("z-index", 1000)
                     .css("position", "absolute");
        showTT(e.pageX, e.pageY);
    }, function(){
        $("#preview").remove();
    });

    $("a.preview").mousemove(function(e){
        showTT(e.pageX, e.pageY);
    });
});