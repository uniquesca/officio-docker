$(document).ready(function() {
    $(".cws-create").click(function () {
        document.getElementsByClassName("cws-create")[0].disabled = true;
        $.ajax({
            url: baseUrl + "/company-website/create-website",
            data: {
                // option: option
            },
            method: "POST",
            success: function () {
                document.location.reload(true);
                document.getElementsByClassName("cws-create")[0].disabled = false;
            },
            error: function (error) {
                document.getElementsByClassName("cws-create")[0].disabled = false;
                Ext.simpleConfirmation.error(error);
            }
        });
    });

    $(".switch-builder").click(function () {
        $(this).attr("disabled", true);
        var self = this;
        var switchToOldBuilder = $(this).hasClass("new-on");
        $.ajax({
            url: baseUrl + "/company-website/switch-builder",
            data: {
                switchToOldBuilder: switchToOldBuilder
            },
            type: "POST",
            success: function () {
                $(self).attr("disabled", false);
                var href = window.location.href;
                window.location.href = href.replace('#', '');
            },
            error: function (error) {
                $(self).attr("disabled", false);
                Ext.simpleConfirmation.error(JSON.parse(error.responseText).error);
            }
        });
    });

    $(".cws-save-builder").click(function () {
        document.getElementsByClassName("cws-save-builder")[0].disabled = true;
        var form = document.getElementById("websites-builder-form");
        if (form.checkValidity()) {
            $.post(
                baseUrl + "/company-website/edit-page",
                $("#websites-builder-form").serialize()
            )
                .done(function(response) {
                Ext.simpleConfirmation.success(response.msg);
            })
                .fail(function(response) {
                    console.log(response);
                    Ext.simpleConfirmation.error(response.responseText);
                })
                .always(function() {
                    document.getElementsByClassName(
                        "cws-save-builder"
                    )[0].disabled = false;
                });
        }
    });

    // change template
    $(".cws-builder-template").click(function() {
        var input = $(this)
            .closest(".cws-builder-template")
            .find("input[type=radio]");

        // mark as active
        $(".cws-builder-template").removeClass("active");
        $(this).addClass("active");
        input.prop("checked", "checked");

        // get template id
        var templateId = parseInt(input.val(), 10);

        // hide templates and show loading
        $("#cws-templates-list").hide();
        $("#cws-builder-template-loading").show();

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null });

        $.ajax({
            url: baseUrl + "/company-website/switch-template",
            dataType: "json",
            type: "POST",
            data: {
                templateId: templateId
            },
            success: function(response) {
                if (response) {
                    if (response.success) {
                        document.location.reload(true);

                    } else {
                        Ext.simpleConfirmation.error(response.msg);
                    }
                }
            },
            failure: function() {
                Ext.simpleConfirmation.error(
                    "Cannot get template info. Please try again later."
                );
            }
        });

        return false;
    });

    // old code begin
    // Prevent form auto submit on enter press
    $(window).keydown(function (event) {
        if (event.keyCode == 13 || event.keyCode == 169) {
            event.preventDefault();
            return false;
        }
    });

    // rich editor
    $('textarea.cws-rich-editor').each(function(){
        var defaultConfig = {
            attribution: false,
            useClasses: false,

            toolbarButtons: {
                'moreText': {
                    'buttons': ['bold', 'italic', 'underline', 'strikeThrough', 'subscript', 'superscript', 'fontFamily', 'fontSize', 'textColor', 'backgroundColor', 'inlineClass', 'inlineStyle', 'clearFormatting']
                },
                'moreParagraph': {
                    'buttons': ['alignLeft', 'alignCenter', 'formatOLSimple', 'alignRight', 'alignJustify', 'formatOL', 'formatUL', 'paragraphFormat', 'paragraphStyle', 'lineHeight', 'outdent', 'indent', 'quote']
                },
                'moreRich': {
                    'buttons': ['insertLink', 'insertImage', 'insertVideo', 'insertTable', 'emoticons', 'fontAwesome', 'specialCharacters', 'embedly', 'insertFile', 'insertHR']
                },
                'moreMisc': {
                    // Please note that a custom button is used here: generatePdfCustom
                    'buttons': ['undo', 'redo', 'print', 'generatePdfCustom', 'selectAll', 'html', 'help'],
                    'align': 'right',
                    'buttonsVisible': 2
                }
            },

            pluginsEnabled: ['align', 'colors', 'draggable', 'embedly', 'emoticons', 'fontAwesome', 'fontFamily', 'fontSize', 'fullscreen', 'image', 'lineHeight', 'link', 'lists', 'paragraphFormat', 'paragraphStyle', 'print', 'quote', 'table', 'url', 'video', 'specialCharacters', 'wordPaste', 'help'],
            imageInsertButtons: ['imageBack', '|', 'imageByURL'],
            imageEditButtons: ['imageReplace', 'imageAlign', 'imageCaption', 'imageRemove', '|', 'imageLink', 'linkOpen', 'linkEdit', 'linkRemove', '-', 'imageDisplay', 'imageStyle', 'imageAlt', 'imageSize'],
            videoInsertButtons: ['videoBack', '|', 'videoByURL', 'videoEmbed']
        };

        // Use the license key if is provided
        if (typeof FROALA_SETTINGS !== 'undefined' && typeof FROALA_SETTINGS['key'] !== 'undefined') {
            defaultConfig['key'] = FROALA_SETTINGS['key'];
        }

        new FroalaEditor('#' + $(this).attr('id'), defaultConfig);
    });

    $("#cws").css("min-height", getSuperadminPanelHeight() + "px");

    // generate tabs
    $("#cws-tab").tabs({
        //selected: 0,
        cookie: { expires: 30 }
    });

    // generate tabs
    $("#cws-menu-items").tabs({
        selected: 0
    });

    // select template
    $('.cws-template').click(function(){

        var input = $(this).closest('.cws-template').find('input[type=radio]');

        // mark as active
        $('.cws-template').removeClass('active');
        $(this).addClass('active');
        input.prop('checked', 'checked');

        // show loading
        $('#cws-template-loading').show();
        $('#cws-template-settings').hide();

        // load template options
        var templateId = parseInt(input.val(), 10);

        // Fix issue with JQuery 1.5 with json
        jQuery.ajaxSetup({ jsonp: null, jsonpCallback: null});

        $.ajax({
            url: baseUrl + '/company-website/template-options?id=' + templateId,
            dataType: 'json',
            type: 'POST',
            success: function(response) {
                if(response) {
                    if(response.success) {

                        // hide loading
                        $('#cws-template-loading').hide();

                        // show settings
                        if(!empty(response.settingsPage)) {
                            $('#cws-template-settings').html(response.settingsPage).show();
                        }

                    } else {
                        Ext.simpleConfirmation.error(response.message);
                    }
                }
            },
            failure: function() {
                Ext.simpleConfirmation.error('Cannot get template info. Please try again later.');
            }
        });

        return false;

    });

    // validate submit
    $('.cws-save').click(function(){

        if(empty($('#cws-templates-list').find('input[name=templateId]').val())) {
            Ext.simpleConfirmation.error('You must select template before saving website details.');
            return false;
        }

        $('#company-websites-form').submit();
    });

    // default selection of template
    $('.cws-template.active input[type=radio]').click();

    // external links
    $('#add-link').click(function(){

        var name = $('#new-link-name').val();
        var link = $('#new-link-url').val();

        if(empty(name)) {
            Ext.simpleConfirmation.error('Please enter non-empty link name');
            return false;
        } else if(empty(link)) {
            Ext.simpleConfirmation.error('External link cannot be empty');
            return false;
        }

        var text = '<div class="cws-link">Name: <input type="text" name="external_links_name[]" maxlength="80" value="' + name + '" /> Link: <input type="text" name="external_links_url[]" maxlength="1024" value="' + link + '" /> <button class="remove-link">remove</button></div>';
        $('#external-links').append(text);
        $('#new-link-name, #new-link-url').val('');

        return false;
    });

    // remove options
    $('.remove-option').on('click', function(){
        var _this = $(this);
        var option = $(this).attr('data-option');
        if(option.length > 0) {
            $.ajax({
                url: baseUrl + '/company-website/remove-image',
                data: {
                    option: option
                },
                method: 'POST',
                success: function() {
                    _this.closest('.cws-item').remove();
                }
            })
        }
        return false;
    });

    // remove external links
    $(document).on('click', '.remove-link', function(){
        $(this).closest('.cws-link').remove();
        return false;
    });

    $('#external_links_on').change(function(){
        if($(this).is(':checked')) {
            $('#external-links-table').find('tr.cws-el').show();
        } else {
            $('#external-links-table').find('tr.cws-el').hide();
        }
        return false;
    });

    var error = $('#error');
    if(error.length > 0) {
        Ext.simpleConfirmation.error(error.find('p').html());
    } else if($('#success').length > 0) {
        Ext.simpleConfirmation.success($('#success').text());
    }

    /* Height fix */
    setInterval(function(){
        updateSuperadminIFrameHeight('#cws');
    }, 500);

    /* Next page button */
    $('button.cws-next').click(function(){
        $('#cws-tab').find('li.ui-tabs-active').next().find('a').click();
        return false;
    });

    /* Google Map */

    var map;

    var contactMap = $('#contact_map');
    contactMap.change(function(){

        if($(this).is(':checked')) {
            $('#coords').show();
            initMap();
        } else {
            $('#coords').hide();
        }

        return false;
    });

    $('#gmaps-address-btn').click(function(){
        mapByAddress($('#gmap-address').val());
        return false;
    });

    $('#gmap-address').keyup(function(e){
        if(e.keyCode == 13 || e.keyCode == 169) {
            $('#gmaps-address-btn').click();
        }
    });

    var mapByAddress = function(address) {
        if(!empty(address)) {
            GMaps.geocode({
                address: address,
                callback: function(results, status) {
                    if (status == 'OK') {
                        var latlng = results[0].geometry.location;
                        showMap(latlng.lat(), latlng.lng());
                    } else {
                        showMap(0,0);
                    }
                }
            });
        } else {
            showMap(0,0);
        }
    };

    function isFloatOrInt(n) {
        return !isNaN(n) && n.toString().match(/^-?\d*(\.\d+)?$/);
    }

    var showMap = function(lat, lng) {
        lat = isFloatOrInt(lat) ? lat : 0;
        lng = isFloatOrInt(lng) ? lng : 0;

        // show/update map
        if(map == null) {
            map = new GMaps({
                div: '#gmap',
                lat: lat,
                lng: lng,
                click: function() {
                    addMarker(this.getCenter().lat(), this.getCenter().lng());
                }
            });
        } else {
            map.setCenter(lat, lng);
        }

        // add marker
        if(!empty(lat) && !empty(lng)) {
            addMarker(lat, lng);
        }

        // save coords
        $('#contact_map_coords-x').val(lat);
        $('#contact_map_coords-y').val(lng);
    };

    var addMarker = function(lat, lng) {
        lat = lat == 0 ? 0.00001 : lat;
        lng = lng == 0 ? 0.00001 : lng;
        map.removeMarkers();
        map.addMarker({
            lat: lat,
            lng: lng,
            draggable: true,
            mouseup: function(e) {
                showMap(e.latLng.lat(), e.latLng.lng());
            }
        });
    };

    var initMap = function() {

        var lat = $('#contact_map_coords-x').val(),
            lng = $('#contact_map_coords-y').val();

        if(empty(lat) && empty(lng)) {
            mapByAddress($('#gmap-address').val());
        } else {
            showMap(lat, lng);
        }
    };

    if (contactMap.is(':checked')) {
        $('#coords').show();
        initMap();
    }

    // color buttons
    $(document).on('click', '.cws-color-item', function () {
        $('.cws-color-item').find('input').removeAttr('checked');
        $(this).find('input').prop('checked', true);
        $(this).find('input').trigger('change');
    });

    // color picker
    // TODO Update to another newer colorpicker
    $.fn.colorPicker.defaults.colors = [
        'FFFFFF', 'FFCCCC', 'FFCC99', 'FFFF99', 'FFFFCC', '99FF99', '99FFFF', 'CCFFFF', 'CCCCFF', 'FFCCFF',
        'CCCCCC', 'FF6666', 'FF9966', 'FFFF66', 'FFFF33', '66FF99', '33FFFF', '66FFFF', '9999FF', 'FF99FF',
        'BBBBBB', 'FF0000', 'FF9900', 'FFCC66', 'FFFF00', '33FF33', '66CCCC', '33CCFF', '6666CC', 'CC66CC',
       '999999', 'CC0000', 'FF6600', 'FFCC33', 'FFCC00', '33CC00', '00CCCC', '3366FF', '6633FF', 'CC33CC',
       '666666', '990000', 'CC6600', 'CC9933', '999900', '009900', '339999', '3333FF', '6600CC', '993399',
       '333333', '660000', '993300', '996633', '666600', '006600', '336666', '000099', '333399', '663366',
       '000000', '330000', '663300', '663333', '333300', '003300', '003333', '000066', '330099', '330033',
       'transparent'
    ];
    $('.colorPicker').colorPicker();
    $('.colorPicker-palette').css('width', '181px');
});