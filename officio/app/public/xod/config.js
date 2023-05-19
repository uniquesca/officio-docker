var overlayMessage;

// Load Jquery
function loadScript(script) {
    var scriptTag = document.createElement('script');
    scriptTag.src = script;
    scriptTag.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(scriptTag);
}

// Load Css
function loadCss(css) {
    var fileref = document.createElement("link");
    fileref.rel = "stylesheet";
    fileref.type = "text/css";
    fileref.href = css;
    document.getElementsByTagName("head")[0].appendChild(fileref)
}

loadCss('/assets/plugins/@fortawesome/fontawesome-free/css/all.min.css');
loadScript('/assets/plugins/jquery/dist/jquery.min.js');

function closeDialog() {
    overlayMessage.addClass('closed').removeClass('open');
}

function disableSubmitButtons() {
    $('.officio-save-btn').addClass('disabled').attr('disabled', 'disabled');
}

function enableSubmitButtons() {
    $('.officio-save-btn').removeClass('disabled').removeAttr('disabled')
}

function isResponseBad(response) {
    try {
        if (response === 'You have been logged out because you logged in on another computer or browser.' ||
            response === 'You have been logged out because session has timed out.' ||
            response === 'Access is denied during non-office hours.<br/>Please try again later.' ||
            response === 'We are undergoing a regular system upgrade. The system will be available shortly.' ||
            response === 'Insufficient access rights.' ||
            response === 'You have been logged out from this session.'
        ) {
            return true;
        }
    } catch (e) {
        // Do nothing
    }

    return false;
}

function showMessage(message, msgType) {

    switch (msgType) {
        case 'info':
            message = '<i class="fas fa-info-circle" style="color: #5EA9DD;" aria-hidden="true"></i>&nbsp;' + message;
            break;

        case 'warning':
            message = '<i class="fas fa-exclamation-triangle" style="color: #FFD600;" aria-hidden="true"></i>&nbsp;' + message;
            break;

        case 'error':
            message = '<i class="fas fa-exclamation-triangle" style="color: #FF0000;" aria-hidden="true"></i>&nbsp;' + message;
            break;

        case 'success':
            message = '<i class="fas fa-check-circle" style="color: #1D9E74;" aria-hidden="true"></i>&nbsp;' + message;
            break;

        case 'spin':
            message = '<i class="fas fa-cog fa-spin fa-fw" style="color: #5EA9DD;" aria-hidden="true"></i>&nbsp;' + message;
            break;

        default:
            break;
    }

    overlayMessage.find('.container').html(message);
    overlayMessage.addClass('open').removeClass('closed');
}

(exports => {
    window.addEventListener('documentLoaded', () => {
        defer(configEditor);
    });
})(window);


function defer(method) {
    if (window.jQuery) {
        method();
    } else {
        setTimeout(function () { defer(method) }, 10);
    }
}

function configEditor() {
    const { annotationManager } = instance.Core;
    const custom = JSON.parse(instance.UI.getCustomData());

    overlayMessage = $('[data-element="errorModal"]');
    $('[data-element="progressModal"]').addClass('closed').removeClass('open');

    // Send request to server to load data
    showMessage('Loading. Please wait...', 'spin');
    $.ajax({
        url: custom.openXfdfUrl,
        cache: false,

        success: function (data) {
            // Apply loaded data
            var importedAnnotations = annotationManager.importAnnotations(data);
            // annotationManager.drawAnnotationsFromList(importedAnnotations);

            // Check xfdf status, show error message and disable submit buttons if needed.
            var xfdfStatus = $(data).find("field[name='server_xfdf_loaded'] value").text();
            var xfdfServerConfirmation = $(data).find("field[name='server_confirmation'] value").text();

            var xfdfUserName = $(data).find("field[name='server_user_name'] value").text();
            if (xfdfUserName !== '') {
                annotationManager.setCurrentUser(xfdfUserName);
            }

            var booIsAdmin = parseInt($(data).find("field[name='server_is_admin'] value").text(), 10) === 1;
            if (booIsAdmin) {
                annotationManager.promoteUserToAdmin();
            }

            switch (parseInt(xfdfStatus, 10)) {
                case 1:
                    // Xfdf received, form can be submitted
                    enableSubmitButtons();

                    if (xfdfServerConfirmation !== '') {
                        showMessage(xfdfServerConfirmation, 'info');
                        setTimeout(function () {
                            closeDialog();
                        }, 5000);
                    } else {
                        closeDialog();
                    }
                    break;

                case 2:
                    // Xfdf received, but form cannot be submitted
                    disableSubmitButtons();
                    showMessage('The forms are locked by the office. If you need to make any changes, please contact them for assistance.', 'warning');
                    setTimeout(function () {
                        closeDialog();
                    }, 5000);
                    break;

                default:
                    disableSubmitButtons();
                    closeDialog();
                    break;
            }
        },

        error: function (jqXHR) {
            var message = 'Previously saved information was not properly loaded. Please check your Internet connection and try again.';
            if (isResponseBad(jqXHR.responseText)) {
                message = jqXHR.responseText;
            }

            showMessage(message, 'error');
            setTimeout(function () {
                closeDialog();
            }, 5000);
        },

        dataType: 'xml'
    });


    var saveButton = $('<button class="btn officio-save-btn"> <i class="far fa-save"></i> <span class="full-text">Save Form</span><span class="short-text">Save</span></button>').on('click', function () {
        if ($(this).hasClass('disabled')) {
            return false;
        }

        showMessage('Saving to Officio. Please wait...', 'spin');
        annotationManager.exportAnnotations({ links: false, widgets: false }).then(xfdfString => {
            $.ajax({
                type: 'POST',
                url: custom.saveXodUrl,
                data: {
                    pdfId: custom.pdfId,
                    xfdf: xfdfString
                },

                success: function (data, status, jqXHR) {
                    var xfdf = '';
                    var jsonResponse = JSON.parse(jqXHR.responseText);
                    for (var fieldName in jsonResponse.arrFieldsToUpdate) {
                        if (jsonResponse.arrFieldsToUpdate.hasOwnProperty(fieldName)) {
                            xfdf += '<field name="' + fieldName + '"><value>' + jsonResponse.arrFieldsToUpdate[fieldName] + '</value></field>';
                        }
                    }
                    annotationManager.importAnnotations('<?xml version="1.0" encoding="UTF-8" ?><xfdf xmlns="http://ns.adobe.com/xfdf/" xml:space="preserve"><fields>' + xfdf + '</fields></xfdf>');

                    if (parseInt(jsonResponse.arrFieldsToUpdate.server_xfdf_loaded, 10) === 2) {
                        disableSubmitButtons();
                        showMessage('The forms are locked by the office. If you need to make any changes, please contact them for assistance.', 'warning');
                        setTimeout(function () {
                            closeDialog();
                        }, 5000);
                    } else {
                        showMessage(jsonResponse.message, 'success');
                        setTimeout(function () {
                            closeDialog();
                        }, 2000);
                    }
                },

                error: function (jqXHR) {
                    var jsonResponse = JSON.parse(jqXHR.responseText);
                    showMessage(jsonResponse.message, 'error');
                    setTimeout(function () {
                        closeDialog();
                    }, 5000);
                }
            });
        })
        return false;

    });

    // Add save annotations button
    instance.UI.setHeaderItems(header => {
        const printCurrentButton = {
            dataElement: "actionButton",
            // hidden: ['small-mobile', 'mobile', 'tablet', 'desktop'],
            img: "icon-header-print-line",
            onClick: () => {
                instance.UI.print();
            },
            hidden: ['small-mobile', 'mobile'],
            title: "Print",
            type: "actionButton"
        };
        const printLinkButton = {
            dataElement: "printLinkButton",
            img: "icon-header-print-line",
            onClick: () => {
                window.open(custom.printXodUrl);
                return false;
            },
            hidden: ['small-mobile', 'mobile'],
            title: "View/print as read-only PDF",
            type: "actionButton"
        };

        var helpButton = '';
        // Show a help button only if there is a help link provided
        if (custom.helpAricleUrl != '') {
            helpButton = {
                dataElement: "helpButton",
                onClick: () => {
                    window.open(custom.helpAricleUrl);
                    return false;
                },
                hidden: ['small-mobile', 'mobile'],
                title: "Help",
                type: "actionButton"
            };
        }

        const items = header.getItems();
        const removeTypes = ['zoomOverlayButton', 'divider'];
        const itemZoom = items.filter(item => item.dataElement === 'zoomOverlayButton');
        itemZoom[0].hiddenOnMobileDevice = false;
        const itemsOrganized = items.filter(item => !removeTypes.includes(item.dataElement) && !removeTypes.includes(item.type));

        header.update(
            [].concat(
                [
                    {
                        type: 'customElement',
                        render: () => {
                            const logo = document.createElement('img');
                            logo.src = '/images/default/officio_logo_small.png';
                            logo.classList.add('officio-logo');
                            return logo;
                        }
                    },
                    {
                        type: 'spacer'
                    },
                    {
                        type: 'customElement',
                        render: () => {
                            return saveButton.get(0);
                        },
                    }
                ],
                itemsOrganized,
                [
                    printCurrentButton,
                    printLinkButton,
                    {type: "divider", hidden: ['small-mobile', 'mobile']}
                ],
                itemZoom,
                [
                    {type: "divider", hidden: ['small-mobile', 'mobile']},
                    helpButton
                ]
            )
        );

        header.getHeader('toolbarGroup-Annotate').push(
            {
                type: 'toolGroupButton',
                toolGroup: 'arrowTools',
                dataElement: 'arrowToolGroupButton',
                title: 'annotation.arrow',
            },
            {
                type: 'toolGroupButton',
                toolGroup: 'rubberStampTools',
                dataElement: 'rubberStampToolGroupButton',
                title: 'annotation.rubberStamp',
            },
            {
                type: 'toolGroupButton',
                toolGroup: 'calloutTools',
                dataElement: 'calloutToolGroupButton',
                title: 'annotation.callout',
            },
        );

        header.getHeader('toolbarGroup-FillAndSign').delete('freeTextToolGroupButton');
        header.getHeader('toolbarGroup-FillAndSign').delete('rubberStampToolGroupButton');
        
    });

    Core.Annotations.ChoiceWidgetAnnotation.FORCE_SELECT = true;

    // Fix: apply bigger padding for each field -
    // so we'll see 2 lines instead of 3 (as in the original pdf)
    var oldRefresh = Core.Annotations.TextWidgetAnnotation.prototype.refresh;
    Core.Annotations.TextWidgetAnnotation.prototype.refresh = function (e) {
        // check if it's a mulitline before adjusting the border width
        if (this.Height > 3 * this.font.size && !this.fieldFlags.get('Multiline')) {
            this.border.width = 5;
        }
        return oldRefresh.apply(this, arguments);
    };

    // Fix: automatically switch to "one line" if field's height is less than X
    var createInnerElement = Core.Annotations.TextWidgetAnnotation.prototype.createInnerElement;
    Core.Annotations.TextWidgetAnnotation.prototype.createInnerElement = function () {
        // do not change multiline textbox into non multiline textbox
        if (this.Height < 31 && !this.fieldFlags.get('Multiline')) {
            this.fieldFlags.set('Multiline', false);
        }

        return createInnerElement.apply(this, arguments);
    };

    // Rename Ribbon Items
    $('button[data-element="toolbarGroup-View"]').text('Edit');
    $('button[data-element="dropdown-item-toolbarGroup-View"]').text('Edit');
    $('button[data-element="toolbarGroup-FillAndSign"]').text('eSign');
    $('button[data-element="dropdown-item-toolbarGroup-FillAndSign"]').text('eSign');

    // Add Button Icon
    $('[data-element="helpButton"]').html('<i class="far fa-question-circle"></i>');
    $('[data-element="printLinkButton"]').html('<i class="fas fa-file-pdf"></i>');

    // Display Header after custom
    for (let el of document.querySelectorAll('.HeaderItems')) {
        el.style.visibility = 'unset';
    }

}
