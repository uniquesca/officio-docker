$(document).ready(function(){
    $('#country').change(function() {
        if($(this).val() == defaultCountryId) {
            $('#provinces-div').show();
            $('#state-div').hide();
        } else {
            $('#provinces-div').hide();
            $('#state-div').show();
        }
    });


    // add * to required field labels
    $('label.required').not('.required_not_mark').append('&nbsp;<em class="required" title="This is a required field">*</em>&nbsp;');
    $('label:not(.required)').append('&nbsp;&nbsp;&nbsp;&nbsp;');
    $('#country').val(defaultCountryId);


    jQuery.validator.addMethod("checkState", function(val, element) {
        return !($('#state-div').css('display') != 'none' && val == '');
    }, "Please enter a valid Province/State.");

    jQuery.validator.addMethod("checkProvince", function(val, element) {
        return !($('#provinces-div').css('display') != 'none' && val == '');
    }, "Please select a Province/State.");


    jQuery.validator.addMethod("pageRequired", function(value, element) {
            return !this.optional(element);
        }, $.validator.messages.required);

    jQuery.validator.addMethod("pageRequiredMinLength", function(value, element, param) {
        return this.getLength(value.trim(), element) >= param;
    }, "Please select at least 1 item");



    jQuery.validator.addMethod("emailUniques", function(email, element) {
            email = email.replace(/\s+/g, "");
            return email.match(/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i);
        }, "Please enter a valid Email.");


    jQuery.validator.addMethod("postalUniques", function(postal, element) {
            postal = postal.replace(/\s+/g, "");
            return this.optional(element) || postal.match(/^[0-9A-Za-z]+([\s\-]{1}[0-9A-Za-z]+)?$/i);
        }, "Please enter a valid Zip.");

    jQuery.validator.addMethod("phoneUniques", function(phone_number, element) {
            phone_number = phone_number.replace(/\s+/g, "");
            return phone_number.match(/^(\(?\+?[0-9]*\)?)?[0-9_\- ()]*$/i);
        }, "Please enter a valid Phone.");

    jQuery.validator.addMethod("ccExpMonthUniques", function(exp_month, element) {
            if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888' || $("#ccExpMonth option:selected").text() != 'Month'){
              return true;
            }
            else{
              return false;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccExpYearUniques", function(exp_year, element) {
            if ($('#ccName').val() === '8888' || $('#ccNumber').val() === '8888'  || $("#ccExpYear option:selected").text() != 'Year'){
              return true;
            }
            else{
              return false;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccCVNUniques", function(value, element) {
            if ($('#ccNumber').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccNameUniques", function(value, element) {
            if ($('#ccNumber').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }
        }, "This field is required.");

    jQuery.validator.addMethod("ccpageRequiredNumUniques", function(value, element) {
            if ($('#ccName').val()!=='8888' && value===''){
              return false;
            }
            else{
              return true;
            }

        }, "This field is required.");

    jQuery.validator.addMethod("ccNumUniques", function(value, element, param) {

            if(value==='8888' || $('#ccName').val()==='8888' ){
              return true;
            }

            var cardName = param;

            var cards = [];
            cards [0] = {cardName: "Visa", lengths: "13,16", prefixes: "4", checkdigit: true};
            cards [1] = {cardName: "MasterCard", lengths: "16", prefixes: "51,52,53,54,55", checkdigit: true};
            cards [2] = {cardName: "DinersClub", lengths: "14,16", prefixes: "300,301,302,303,304,305,36,38,55", checkdigit: true};
            cards [3] = {cardName: "CarteBlanche", lengths: "14", prefixes: "300,301,302,303,304,305,36,38", checkdigit: true};
            cards [4] = {cardName: "AmEx", lengths: "15", prefixes: "34,37", checkdigit: true};
            cards [5] = {cardName: "Discover", lengths: "16", prefixes: "6011,650", checkdigit: true};
            cards [6] = {cardName: "JCB", lengths: "15,16", prefixes: "3,1800,2131", checkdigit: true};
            cards [7] = {cardName: "enRoute", lengths: "15", prefixes: "2014,2149", checkdigit: true};
            cards [8] = {cardName: "Solo", lengths: "16,18,19", prefixes: "6334, 6767", checkdigit: true};
            cards [9] = {cardName: "Switch", lengths: "16,18,19", prefixes: "4903,4905,4911,4936,564182,633110,6333,6759", checkdigit: true};
            cards [10] = {cardName: "Maestro", lengths: "16,18", prefixes: "5020,6", checkdigit: true};
            cards [11] = {cardName: "VisaElectron", lengths: "16", prefixes: "417500,4917,4913", checkdigit: true};

            var cardType = -1;
            for (var i=0; i<cards.length; i++) {
                    if (cardName.toLowerCase() == cards[i].cardName.toLowerCase()) {
                            cardType = i;
                            break;
                    }
            }
            if (cardType == -1) { return false; } // card type not found

            value = value.replace (/[\s-]/g, ""); // remove spaces and dashes
            if (value.length === 0) { return false; } // no length

            var cardNo = value;
            var cardexp = /^[0-9]{13,19}$/;
            if (!cardexp.exec(cardNo)) { return false; } // has chars or wrong length

            cardNo = cardNo.replace(/\D/g, ""); // strip down to digits

            if (cards[cardType].checkdigit){
                    var checksum = 0;
                    var j = 1;

                    var calc;
                    for (i = cardNo.length - 1; i >= 0; i--) {
                            calc = Number(cardNo.charAt(i)) * j;
                            if (calc > 9) {
                                    checksum = checksum + 1;
                                    calc = calc - 10;
                            }
                            checksum = checksum + calc;
                            if (j ==1) {j = 2;} else {j = 1;}
                    }

                    if (checksum % 10 !== 0) { return false; } // not mod10
            }

            var lengthValid = false;
            var prefixValid = false;

            var prefix = cards[cardType].prefixes.split(",");
            for (i=0; i<prefix.length; i++) {
                    var exp = new RegExp ("^" + prefix[i]);
                    if (exp.test (cardNo)) prefixValid = true;
            }
            if (!prefixValid) { return false; } // invalid prefix

            var lengths = cards[cardType].lengths.split(",");
            for (j=0; j<lengths.length; j++) {
                    if (cardNo.length == lengths[j]) lengthValid = true;
            }
            if (!lengthValid) { return false; } // wrong length

            return true;
        }, "Please enter a valid Credit Card Number.");

    // validate signup form on keyup and submit
    var validator = $("#newCompanyForm").validate({
        rules: {
            // CC info
            firstName: {
                    pageRequired: true
            },
            lastName: {
                    pageRequired: true
            },
            ccType: {
                pageRequired: true
            },

            ccCVN: {
                ccCVNUniques: function(){ return $('#ccCVN').val(); }
            },

            ccName: {
                ccNameUniques: function(){ return $('#ccName').val(); }
            },

            ccNumber: {
                ccpageRequiredNumUniques: function(){ return $('#ccType').val(); },
                ccNumUniques: function(){ return $('#ccType').val(); }
            },

            ccExpMonth: {
                ccExpMonthUniques: function(){ return $('#ccExpMonth').val(); }
            },

            ccExpYear: {
                ccExpYearUniques: function(){ return $('#ccExpYear').val(); }
            },


            // Company details
            users_count: {
                pageRequired: true,
                digits: true,
                min: 1,
                max: 5
            },
            extra_users_count: {
                pageRequired: true,
                digits: true,
                min: 0,
                max: 10
            },
            companyName: {
                pageRequired: true
            },
            company_abn: {
                pageRequired: booShowABN
            },
            companyEmail: {
                pageRequired: true,
                emailUniques: true
            },

            address: {
                    pageRequired: true
            },
            city: {
                    pageRequired: true
            },
            state: {
                checkState: true
            },
            province: {
                checkProvince: true
            },
            country: {
                    pageRequired: true
            },
            phone1: {
                    pageRequired: true,
                    phoneUniques: true
            },
            phone2: {
                    phoneUniques: true
            },
            fax: {
               phoneUniques: true
            },
            zip: {
                pageRequired: true,
                postalUniques: true
            },

            office: {
                pageRequired: true
            }
        },

        // the errorPlacement has to take the table layout into account
        errorPlacement: function(label, element) {
                // position error label after generated textarea
                if (element.is(":checkbox")) {
                    label.appendTo( element.parent().parent() );
                } else {
                    label.insertAfter(element);
                }
        },


        messages: {
        },

        // set this class to error-labels to indicate valid fields
        success: function(label) {
            // set   as text for IE
            label.html(" ").addClass("valid");
        }
    });

    $('#ccType').change(function(){
        $("#newCompanyForm").validate().element('#ccNumber');
    });

    $('#ccExpMonth').change(function(){
       $("#newCompanyForm").validate().element('#ccExpMonth');
    });
    $('#ccExpYear').change(function(){
       $("#newCompanyForm").validate().element('#ccExpYear');
    });
    $('#country').change(function(){
       $("#newCompanyForm").validate().element('#country');
    });

});

