Ext.onReady(function() {
    Ext.QuickTips.init();
    if ($('#generatePassword').length) {
        new GeneratePasswordButton({
            renderTo: 'generatePassword',
            passwordField: 'password'
        });
    }

    var company_id = Ext.get('company_id') ? Ext.get('company_id').getValue() : null;
    if (Ext.get('company_export_container')) {
        // Use the same BG color as this is for current row
        var bgColor = $('#company_export_container').closest('tr').css('backgroundColor');
        new ExportCompanyPanel(company_id, bgColor);
    }

    $('textarea.cws-rich-editor').each(function () {
        var defaultConfig = {
            iframe: true,
            iframeStyle: 'body{padding:10px}',
            attribution: false,
            useClasses: false,
            heightMin: 200,
            heightMax: 200,

            toolbarButtons: {
                'moreText': {
                    'buttons': ['bold', 'italic', 'underline', 'strikeThrough', 'subscript', 'superscript'],
                    'buttonsVisible': 6
                },
                'moreMisc': {
                    // Please note that a custom button is used here: generatePdfCustom
                    'buttons': ['undo', 'redo'],
                    'align': 'right',
                    'buttonsVisible': 2
                }
            },

            pluginsEnabled: [],
        };

        // Use the license key if is provided
        if (typeof FROALA_SETTINGS !== 'undefined' && typeof FROALA_SETTINGS['key'] !== 'undefined') {
            defaultConfig['key'] = FROALA_SETTINGS['key'];
        }

        new FroalaEditor('#' + $(this).attr('id'), defaultConfig);
    });


    if ($('#edit-company-admin').length) {
        new Ext.Button({
            text: _('Edit'),
            width: 70,
            tooltip: {
                width: 230,
                text: _('Use this button to edit selected company admin\'s info.')
            },
            renderTo: 'edit-company-admin',
            listeners: {
                click: function() {
                    var memberId = $('#company-admins-combo').val();
                    window.open(baseUrl + '/manage-members/edit?member_id=' + memberId, '_blank');
                }
            }
        });
    }

    if ($('#set-as-default-company-admin').length) {
        new Ext.Button({
            text: _('Set as default'),
            cls: 'default-action-btn',
            width: 70,
            tooltip: {
                width: 230,
                text: _('Use this button to set selected company admin as default.')
            },
            renderTo: 'set-as-default-company-admin',
            hidden: $('select#company-admins-combo option').length < 2,
            listeners: {
                click: function() {
                    var companyAdminsCombo = $('#company-admins-combo');
                    var memberId = companyAdminsCombo.val();
                    Ext.Ajax.request({
                        url: baseUrl + '/manage-company/update-default-company-admin',
                        params: {
                            company_id: company_id,
                            member_id: memberId
                        },

                        success: function (result) {
                            var res = Ext.decode(result.responseText);
                            if (res.success) {
                                Ext.simpleConfirmation.success('Default company admin was succesfully updated.', '', '', 200);
                                companyAdminsCombo.val(memberId);
                            } else {
                                Ext.simpleConfirmation.error(res.message, '', '', 200);
                            }

                        },
                        failure: function(){
                            Ext.simpleConfirmation.error('Cannot update default company admin: internal error.');
                        }
                    });
                }
            }
        });
    }

    getPackagesInfo(companyDetails);
    var tabs = $("#manage-company-content");
    tabs.tabs({
        selected: 0
    });

    $("#allow_decision_rationale_tab").change(function() {
        if (this.checked) {
            $('#decision_rationale_tab_name').removeAttr('disabled');
        } else {
            $('#decision_rationale_tab_name').attr('disabled', 'disabled');
        }
    });

    if(location.href.indexOf('#company-packages') != -1) {
        tabs.tabs("select" , $('.ui-tabs-nav a[href="#company-packages"]').parent().index());
    }

    // Automatically load notes/tasks list on first click on the tab
    $('.main-company-tickets-tab a').one("click", function() {
        if (ticketsGrid) {
            // Don't reload immediately - let show the grid (and init dimensions)
            var reloadFn = function() {
                ticketsGrid.store.load();
            };
            reloadFn.defer(100, this);
        }
    });

    // Automatically load notes/tasks list on first click on the tab
    $('.main-company-users-tab a').one("click", function() {
        var membersPanel = Ext.getCmp('manage-members-panel');
        if (membersPanel) {
            // Don't reload immediately - let show the grid (and init dimensions)
            var reloadFn = function() {
                // membersPanel.membersGrid.store.load();
                membersPanel.membersGrid.doLayout();
                membersPanel.membersFilterForm.doLayout();
                membersPanel.doLayout();
            };
            reloadFn.defer(100, this);
        }
    });

    // Automatically load time log list on first click on the tab
    $('.main-company-time-log-tab a').one("click", function (){
        // Don't reload immediately - let show the grid (and init dimensions)
        var reloadFn=function ()
        {
            // Init Time Log grid
            if ($('#time-log').length)
            {
                Ext.getDom('time-log').innerHTML='';

                new ClientTrackerPanel({
                    booCompanies: true,
                    companyId: company_id,
                    renderTo: 'time-log',
                    autoWidth: true,
                    height: 600
                });
            }
        };
        reloadFn.defer(100, this);
    });

    // Turn off browser's auto complete functionality
    var companyForm = $("#editCompanyForm");
    companyForm.attr("autocomplete","off");

    var manageCompanyContentPanel = $('#manage-company-content');
    var leftNavigationPanel = $('#admin-left-panel');

    manageCompanyContentPanel.css('min-height', getSuperadminPanelHeight() + 'px');

    var fixCompanyPageHeight = function () {
        var maxHeight = manageCompanyContentPanel.height();
        if (leftNavigationPanel.length) {
            maxHeight = Math.max(leftNavigationPanel.height(), maxHeight);
        }

        manageCompanyContentPanel.css('min-height', getSuperadminPanelHeight() + 'px');
        var new_height;
        if ($("#edit_company_frame_" + company_id, top.document).is(":visible")) {
            new_height = maxHeight + 82 + $('.superadmin-iframe-header').outerHeight();
            $("#edit_company_frame_" + company_id, top.document).height(new_height + 'px');
        }

        if ($("#admin_section_frame", top.document).is(":visible")) {
            new_height = maxHeight + 82;
            $("#admin_section_frame", top.document).height(new_height + 'px');
        }
    };

    if (manageCompanyContentPanel.is(":visible")) {
        setTimeout(function(){
            fixCompanyPageHeight();
        }, 200);
    }

        
    $('.main-company-tabs a').click(function(){
        setTimeout(function(){
            fixCompanyPageHeight();
        }, 200);
    });

    // Init Tickets grid
    var el = $('#company-tickets');
    var ticketsGrid;
    if (el.length) {
        Ext.getDom('company-tickets').innerHTML = '';
        ticketsGrid = new TicketsGrid({
            useTopUrl: true,
            renderTo: 'company-tickets',
            company_id: company_id,
            ticketsOnPage: 20
        });
    }

    var logoImage = window.parent.$("#companyImageLogo");
    if(logoImage) {
        if(booShowCompanyLogo) {
            logoImage.show();
            // Reload logo
            var tmp = new Date();
            var logoPath = companyLogoUrl + "?" + tmp.getTime();
            logoImage.attr("src", logoPath);
        } else {
            logoImage.hide();
        }
    }

    jQuery.validator.addMethod("postalUniques", function(postal, element) {
        postal = postal.replace(/\s+/g, "");
        return this.optional(element) || postal.match(/^[0-9A-Za-z]+([\s\-]{1}[0-9A-Za-z]+)?$/i);
    }, "Please enter a valid Zip.");

    jQuery.validator.addMethod("phoneUniques", function(phone_number, element) {
        phone_number = phone_number.replace(/\s+/g, "");
        return this.optional(element) || phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
    }, "Please enter a valid Phone.");

    jQuery.validator.addMethod("emailUniques", function(email, element) {
        email = email.replace(/\s+/g, "");
        return this.optional(element) || email.match(/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i);
    }, "Please enter a valid Email.");

    jQuery.validator.addMethod("checkState", function(val) {
        return !($('#state-div').css('display') !== 'none' && val === '');
    }, "Please enter a valid Province/State.");

    jQuery.validator.addMethod("advancedSearchRowsMaxCount", function(count, element) {
        count = count.replace(/\s+/g, "");
        if (count.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i) && (parseInt(count) >= 1 && parseInt(count) <= 100)) {
            return true;
        }
        return this.optional(element);
    }, "Please enter a number between 1 and 100.");

    jQuery.validator.addMethod("invoiceNumberFormat", function(invoiceNumber) {
        return /.*\{sequence_number\}.*/.test(invoiceNumber);
    }, "Please make sure that {sequence_number} variable is used.");

    jQuery.validator.addMethod("startNumberFromValid", function(count, element) {
        var booValid = false;
        count = count.replace(/\s+/g, "");
        if (count.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i) && (parseInt(count) >= 1)) {
            booValid = true;
        } else {
            booValid = this.optional(element);
        }

        var oWarning = $('#' + $(element).attr('id') + '_warning');
        if (oWarning) {
            var booShowWarning = false;
            if (booValid && parseInt(count, 10) < parseInt($(element).data('min-value'), 10)) {
                booShowWarning = true;
            }
            oWarning.toggle(booShowWarning);
        }

        return booValid;
    }, "Please enter a number more than 0.");

    jQuery.validator.addMethod("clientProfileIdFormat", function (invoiceNumber) {
        return /.*\{client_id_sequence\}.*/.test(invoiceNumber);
    }, "Please make sure that {client_id_sequence} variable is used.");


    $("#myform").validate({
        rules: {
            field: {
                required: true,
                phoneUS: true
            }
        }
    });

    jQuery.validator.addMethod("passwordValidation",function(value,element){
        var pattern = new RegExp(passwordValidationRegexp); // @see var passwordValidationRegexp;  layouts/*/*.phtml
        return this.optional(element) || pattern.test(value);
    }, passwordValidationRegexpMessage);

    // validate signup form on keyup and submit
    companyForm.validate({
        rules: {
            fName: {
                required: true
            },
            lName: {
                required: true
            },
            username: {
                required: true,
                minlength: 4
            },
            password: {
                required: empty(company_id),
                minlength: passwordMinLength,
                maxlength: passwordMaxLength,
                passwordValidation: true
            },
            emailAddress: {
                required: true,
                emailUniques: true
            },
            companyName: {
                required: true
            },
            address: {
                required: true
            },
            city: {
                required: true
            },
            state: {
                checkState: true
            },
            provinces: {
                required: true
            },
            country: {
                required: true
            },
            phone1: {
                required: true,
                phoneUniques: true
            },
            phone2: {
                phoneUniques: true
            },
            companyEmail: {
                required: true,
                emailUniques: true
            },
            fax: {
                phoneUniques: true
            },
            zip: {
                postalUniques: true
            },
            advanced_search_rows_max_count: {
                advancedSearchRowsMaxCount: true
            },
            invoice_number_format: {
                invoiceNumberFormat: true
            },
            invoice_number_start_from: {
                startNumberFromValid: true
            },
            client_profile_id_start_from: {
                startNumberFromValid: true
            },
            client_profile_id_format: {
                clientProfileIdFormat: true
            }
        },


        messages: {
            emailAddress: {
                required: "Please enter Email",
                email: "Please enter a valid Email"
            },
            fax: {
                phoneUniques: "Please enter a valid fax"
            }
        },

        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("checked");
        }
    });


    // Init company logo functionality
    $('.company-image-edit-cancel').click(function() {
        $('.company-image-edit').hide();
        $('#companyLogo').val('');
        $('.company-image-view').show();
        fixCompanyPageHeight();
        return false;
    });

    $('.company-image-edit-change').click(function() {
        $('.company-image-edit').show();
        $('.company-image-view').hide();
        $('.company-image-edit-default').hide();
        fixCompanyPageHeight();
        return false;
    });

    $('.company-image-edit-remove').click(function() {
        Ext.Msg.show({
            title: 'Please confirm',
            msg: 'Are you sure you want to remove this image?',
            buttons: {yes: 'Yes', no: 'Cancel'},
            minWidth: 300,
            modal: true,
            icon: Ext.MessageBox.WARNING,
            fn: function (btn) {
                if (btn == 'yes') {
                    // send request to remove picture in "silent" mode
                    var company_id = Ext.get('company_id') ? Ext.get('company_id').getValue() : null,
                        booSuccess = false,
                        msg = 'Cannot remove company logo: internal error';
                    Ext.Ajax.request({
                        url: baseUrl + '/manage-company/remove-company-logo?company_id=' + company_id,

                        success: function (result) {
                            try {
                                var res = Ext.decode(result.responseText);
                                booSuccess = res.success;
                                msg = res.msg;
                            } catch (e) {
                            }

                            if (!booSuccess) {
                                Ext.simpleConfirmation.error(msg);

                                $('.company-image-view').show();
                                $('.company-image-edit').hide();
                                $('.company-image-edit-cancel').show();
                                fixCompanyPageHeight();
                            } else {
                                $('.company-image-edit-default').show();
                                fixCompanyPageHeight();
                                var logoImage = window.parent.$("#companyImageLogo");
                                if(logoImage) {
                                    logoImage.hide();
                                }
                            }
                        },

                        failure: function(){
                            Ext.simpleConfirmation.error(msg);

                            $('.company-image-view').show();
                            $('.company-image-edit').hide();
                            $('.company-image-edit-cancel').show();
                            fixCompanyPageHeight();
                        }
                    });

                    // remove image
                    $('.company-image-view').hide();
                    $('.company-image-edit').show();
                    $('.company-image-edit-cancel').hide();
                    fixCompanyPageHeight();
                }
            }
        });
        return false;
    });

    var copyLoginUrlToClipboard = function () {
        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val($('#login-url').text()).select();
        document.execCommand("copy");
        $temp.remove();

        Ext.simpleConfirmation.msg(_('Info'), _('Login url copied to clipboard'));
    };

    $('#login-url').click(function () {
        copyLoginUrlToClipboard();
        return false;
    });

    $('.button-copy-url').click(function () {
        copyLoginUrlToClipboard();
    });

    // Disable/enable Client Profile ID fields if checkbox is unchecked/checked
    var toggleClientProfileIdFields = function (booShowWarning) {
        var booChecked = $('#client_profile_id_enabled').is(':checked');
        $('#client_profile_id_start_from').prop('disabled', !booChecked);
        $('#client_profile_id_format').prop('disabled', !booChecked);

        if (booShowWarning) {
            $('#client_profile_id_warning').toggle(booChecked);
            fixCompanyPageHeight();
        }
    };

    $('#client_profile_id_enabled').change(function () {
        toggleClientProfileIdFields(true);
    });
    toggleClientProfileIdFields(false);
});

function print(content, title)
{
    var printContent = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"\n'+
    '"http://www.w3.org/TR/html4/strict.dtd">\n'+
    '<html>\n'+
    '<head>\n'+
    '<title>' + title + '</title>\n'+
    '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">\n'+
    '<link href="' + baseUrl + '/styles/print.css" rel="stylesheet" type="text/css" />' +
    '</head>'+
    '<body>\n'+
    content + '\n'+
    '</body>\n'+
    '</html>';
    
    var windowUrl = 'about:blank';
    var windowName = 'Print';
    var printWindow = window.open(windowUrl, windowName, 'width=800,height=600');
    if (printWindow) {
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        var userAgent = navigator.userAgent;
        var chrome = (userAgent.indexOf('Chrome') != -1);
        if (chrome) {
            setTimeout(function(){printWindow.print(); printWindow.close();}, 300);
        } else {
            printWindow.print();
            printWindow.close();
        }
    }

}

function getInvoicePDF(invoice_num) {
    var company_id = Ext.get('company_id') ? Ext.get('company_id').getValue() : null;
    window.open(baseUrl + '/manage-company/show-invoice-pdf?invoiceId=' + invoice_num + '&companyId=' + company_id);
}

function removeInvoice(cid) {
    Ext.Msg.confirm('Please confirm', 'Are you sure you want to delete selected invoice (id#' + cid + ')?',
        function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Deleting...');
                Ext.Ajax.request({
                    url: baseUrl + "/manage-company/delete-invoice",
                    params: {
                        company_invoice_id: cid
                    },
                    success: function (f, o) {
                        Ext.getBody().mask('Done.');
                        $('#tr-' + cid).remove();
                        setTimeout(function () {
                            Ext.getBody().unmask();
                        }, 750);
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                        Ext.getBody().unmask();
                    }
                });
            }
        }
    );
}

function chargeInvoice(cid) {
    var question = String.format(
        _('Are you sure you want to charge the selected invoice (id#{0})?'),
        cid
    );

    Ext.Msg.confirm(_('Please confirm'), question,
        function (btn) {
            if (btn == 'yes') {
                Ext.getBody().mask('Charging...');

                var company_id = Ext.get('company_id') ? Ext.get('company_id').getValue() : null;
                Ext.Ajax.request({
                    url: baseUrl + "/manage-company/run-charge",
                    params: {
                        company_id: Ext.encode(company_id),
                        invoice_id: Ext.encode(cid),
                        booSpecialCharge: Ext.encode(true),
                        booInvoiceChargeOnly: Ext.encode(true)
                    },

                    success: function (result) {
                        var resultData = Ext.decode(result.responseText);
                        if (!resultData.error) {
                            // Show confirmation message
                            Ext.Msg.show({
                                title: _('Invoice Confirmation'),
                                msg: _('Charge successful.'),
                                minWidth: 400,
                                modal: true,
                                buttons: Ext.Msg.OK,
                                icon: Ext.Msg.INFO,

                                fn: function () {
                                    Ext.getBody().mask(_('Page reloading...'));

                                    // Reload invoices list
                                    var currentTime = new Date();
                                    var url = baseUrl + '/manage-company/edit?company_id=' + company_id + '&time=' + currentTime.getTime() + '#company-invoices';
                                    window.location.assign(url);
                                }
                            });
                        } else {
                            var title = resultData.booPTError ? _('Credit Card Process Error') : _('Error');
                            Ext.simpleConfirmation.error(resultData.error_message, title);
                            Ext.getBody().unmask();
                        }
                    },
                    failure: function () {
                        Ext.simpleConfirmation.error(_('Cannot send information. Please try again later.'));
                        Ext.getBody().unmask();
                    }
                });
            }
        }
    );
}

function _runCharge(booSpecialCharge) {
    var net = new Ext.form.NumberField({
        fieldLabel: 'Net',
        allowNegative: !booSpecialCharge, // must be positive for Special Charge
        allowBlank: false,
        value: 0,
        width: 205
    });

    var tax = new Ext.form.NumberField({
        fieldLabel: 'Tax',
        allowNegative: !booSpecialCharge, // must be positive for Special Charge
        allowBlank: false,
        value: 0,
        width: 205
    });

    var amount = new Ext.form.NumberField({
        fieldLabel: 'Total',
        allowNegative: !booSpecialCharge, // must be positive for Special Charge
        allowBlank: false,
        value: 0,
        width: 205
    });

    var notes = new Ext.form.TextField({
        fieldLabel: 'Description',
        allowBlank: false,
        width: 205
    });

    var dateOfInvoice = new Ext.form.DateField({
        width: 205,
        allowBlank: false,
        fieldLabel: 'Date of Invoice',
        value: new Date(),
        format: dateFormatFull,
        maxLength: 12, // Fix issue with date entering in 'full' format
        altFormats: dateFormatFull + '|' + dateFormatShort
    });


    var pan = new Ext.FormPanel({
        bodyStyle: 'padding:5px',
        labelWidth: 90,
        items: [net, tax, amount, notes, dateOfInvoice]
    });

    var okBtn = new Ext.Button({
        text: 'OK',
        cls: 'orange-btn',
        handler: function(){
            var company_id = Ext.get('company_id') ? Ext.get('company_id').getValue() : null;

            var enteredNet    = parseFloat(net.getValue());
            var enteredTax    = parseFloat(tax.getValue());
            var enteredAmount = parseFloat(amount.getValue());
            var enteredNote   = notes.getValue();
            var enteredDate   = dateOfInvoice.getValue();

            var booCorrectForm = pan.getForm().isValid();
            var booCorrectSum = true;
            if(toFixed(enteredNet + enteredTax, 2) != enteredAmount) {
                amount.markInvalid('The sum of Net and Tax fields must be equal to Total field.');
                booCorrectSum = false;
            }

            if(booCorrectForm && booCorrectSum) {

                var winEl = win.getEl();
                winEl.mask('Please wait...');


                // 1. Send request to parse template
                Ext.Ajax.request({
                    url: baseUrl + "/manage-company/generate-invoice-template",
                    params: {
                        company_id:       Ext.encode(company_id),
                        net:              Ext.encode(enteredNet),
                        tax:              Ext.encode(enteredTax),
                        amount:           Ext.encode(enteredAmount),
                        notes:            Ext.encode(enteredNote),
                        date_of_invoice:  Ext.encode(enteredDate),
                        booSpecialCharge: Ext.encode(booSpecialCharge)
                    },

                    success: function(result, request) {
                        var resultData = Ext.decode(result.responseText);
                        if (!resultData.error) {
                            winEl.unmask();
                            win.close();

                            // 2. Receive parsed template, make possible to edit it
                            var parsedTemplate = new Ext.form.HtmlEditor({
                                hideLabel: true,
                                enableSourceEdit: false,
                                plugins: Ext.ux.form.HtmlEditor.IncludeAllPlugins(),
                                enableLinks: true,
                                height: 350,
                                width: 650,
                                allowBlank: false,

                                value: resultData.template_body
                            });

                            var templateNet = new Ext.form.NumberField({
                                fieldLabel: 'Net',
                                allowNegative: !booSpecialCharge, // must be positive for Special Charge
                                allowBlank: false,
                                value: enteredNet,
                                width: 205
                            });

                            var templateTax = new Ext.form.NumberField({
                                fieldLabel: 'Tax',
                                allowNegative: !booSpecialCharge, // must be positive for Special Charge
                                allowBlank: false,
                                value: enteredTax,
                                width: 205
                            });

                            var templateAmount = new Ext.form.NumberField({
                                fieldLabel: 'Total',
                                allowNegative: !booSpecialCharge, // must be positive for Special Charge
                                allowBlank: false,
                                value: enteredAmount,
                                width: 205
                            });

                            var templateNotes = new Ext.form.TextField({
                                fieldLabel: 'Description',
                                value: enteredNote,
                                allowBlank: false,
                                width: 205
                            });

                            var templateDateOfInvoice = new Ext.form.DateField({
                                width: 120,
                                allowBlank: false,
                                fieldLabel: 'Date of Invoice',
                                value: enteredDate,
                                format: dateFormatFull,
                                maxLength: 12, // Fix issue with date entering in 'full' format
                                altFormats: dateFormatFull + '|' + dateFormatShort
                            });


                            var templatePanel = new Ext.FormPanel({
                                bodyStyle: 'padding:5px',
                                labelWidth: 90,
                                items: [
                                    parsedTemplate,
                                    {
                                        layout:'column',
                                        items: [
                                            {
                                                columnWidth: 0.5,
                                                layout: 'form',
                                                items: [templateNet, templateNotes, templateDateOfInvoice]
                                            }, {
                                                columnWidth: 0.5,
                                                layout: 'form',
                                                labelWidth: 50,
                                                items: [templateTax, templateAmount]
                                            }
                                        ]
                                    }
                                ]
                            });

                            var sendInvoiceRequest = function(booSpecialCharge) {
                                var booCorrectForm = templatePanel.getForm().isValid();
                                var booCorrectSum = true;
                                if(toFixed(parseFloat(templateNet.getValue()) + parseFloat(templateTax.getValue()), 2) != parseFloat(templateAmount.getValue())) {
                                    templateAmount.markInvalid('The sum of Net and Tax fields must be equal to Total field.');
                                    booCorrectSum = false;
                                }


                                if(booCorrectForm && booCorrectSum) {
                                    // Show window mask
                                    var templateWinEl = templateWin.getEl();
                                    templateWinEl.mask('Please wait...');


                                    // 3. Send request to PT and create invoice
                                    Ext.Ajax.request({
                                        url: baseUrl + "/manage-company/run-charge",
                                        params: {
                                            company_id: Ext.encode(company_id),
                                            net: Ext.encode(templateNet.getValue()),
                                            tax: Ext.encode(templateTax.getValue()),
                                            amount: Ext.encode(templateAmount.getValue()),
                                            notes: Ext.encode(templateNotes.getValue()),
                                            date_of_invoice: Ext.encode(templateDateOfInvoice.getValue()),
                                            template_body: Ext.encode(parsedTemplate.getValue()),
                                            template_subject: Ext.encode(resultData.template_subject),
                                            invoice_id: Ext.encode(resultData.invoice_id),
                                            booSpecialCharge: Ext.encode(booSpecialCharge),
                                            booInvoiceChargeOnly: Ext.encode(false)
                                        },
                                        success: function(result) {
                                            var resultData = Ext.decode(result.responseText);
                                            if (!resultData.error) {
                                                // Show confirmation message
                                                Ext.Msg.show({
                                                        title: 'Invoice Confirmation',
                                                        msg: booSpecialCharge ? 'Charge successful.  Invoice created.' : 'Invoice created.',
                                                        minWidth: 400,
                                                        modal: true,
                                                        buttons: Ext.Msg.OK,
                                                        fn: function(){
                                                            templateWinEl.mask('Page reloading...');

                                                            // Reload invoices list
                                                            var currentTime = new Date();
                                                            var url = baseUrl + '/manage-company/edit?company_id=' + company_id + '&time='+currentTime.getTime()+'#company-packages';
                                                            window.location.assign(url);
                                                        },

                                                        icon: Ext.Msg.INFO
                                                });
                                            } else {
                                                var title = resultData.booPTError ? 'Credit Card Process Error' : 'Error';
                                                Ext.simpleConfirmation.error(resultData.error_message, title);
                                                templateWinEl.unmask();
                                            }
                                        },
                                        failure: function() {
                                            Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                                            templateWinEl.unmask();
                                        }
                                    });
                                }
                            };


                            var arrButtons = [];
                            arrButtons[arrButtons.length] = new Ext.Button({
                                text: 'Create Invoice',
                                handler: function(){
                                    sendInvoiceRequest(false);
                                }
                            });


                            if(booSpecialCharge) {
                                arrButtons[arrButtons.length] = new Ext.Button({
                                    text: 'Charge And Create Invoice',
                                    handler: function(){
                                        sendInvoiceRequest(true);
                                    }
                                });
                            }

                            arrButtons[arrButtons.length] = new Ext.Button({
                                text: 'Cancel',
                                handler: function(){ templateWin.close(); }
                            });

                            var templateWin = new Ext.Window({
                                title: resultData.template_subject,
                                modal: true,
                                autoHeight: true,
                                autoWidth: true,
                                resizable: false,
                                layout: 'form',
                                items: templatePanel,
                                buttons: arrButtons
                            });

                            templateWin.show();
                            templateWin.center();


                        } else {
                            Ext.simpleConfirmation.error(resultData.error_message);
                            winEl.unmask();
                        }
                    },
                    failure: function() {
                        Ext.simpleConfirmation.error('Cannot send information. Please try again later.');
                        winEl.unmask();
                    }
                });
            }
        }
    });

    var closeBtn = new Ext.Button({
        text: 'Cancel',
        handler: function(){ win.close(); }
    });

    var win = new Ext.Window({
        title: booSpecialCharge ? 'Special CC Charge' : 'Notes/Special Invoice',
        modal: true,
        autoHeight: true,
        resizable: false,
        width: 330,
        layout: 'form',
        items: pan,
        buttons: [closeBtn, okBtn]
    });

    win.show();
    win.center();
}

function runCharge(booSpecialCharge) {
    checkForChangesAndAskUser(_runCharge, booSpecialCharge);
}

function updateProvinceField(val) {
    if(val == defaultCountryId) {
        $('#provinces-div').show();
        $('#state-div').hide();
    } else {
        $('#provinces-div').hide();
        $('#state-div').show();
    }
}