Date.patterns = {
    ISO8601Long: "Y-m-d H:i:s",
    ISO8601Short: "Y-m-d",
    ShortDate: "n/j/Y",
    LongDate: "l, F d, Y",
    FullDateTime: "l, F d, Y g:i:s A",
    MonthDay: "F d",
    ShortTime: "g:i A",
    LongTime: "g:i:s A",
    SortableDateTime: "Y-m-d\\TH:i:s",
    UniversalSortableDateTime: "Y-m-d H:i:sO",
    YearMonth: "F, Y",
    UniquesShort: 'M d, Y',
    UniquesLong: 'M d, Y H:i:s',
    UniquesTime: 'H:i:s'
};

Ext.apply(Ext.form.VTypes, {
    'email' : function(v){
        var email = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return email.test(v);
    },

    'emailMask' : /[a-z0-9_\.\-\+\'@]/i,

    'daterange' : function(val, field) {
        var date = field.parseDate(val);

        if(!date){
            return false;
        }
        if (field.startDateField && (!this.dateRangeMax || (date.getTime() != this.dateRangeMax.getTime()))) {
            var start = Ext.getCmp(field.startDateField);
            start.setMaxValue(date);
            start.validate();
            this.dateRangeMax = date;
        }
        else if (field.endDateField && (!this.dateRangeMin || (date.getTime() != this.dateRangeMin.getTime()))) {
            var end = Ext.getCmp(field.endDateField);
            end.setMinValue(date);
            end.validate();
            this.dateRangeMin = date;
        }
        /*
         * Always return true since we're only using this vtype to set the
         * min/max allowed values (these are tested for after the vtype test)
         */
        return true;
    },

    'cc_cvn': function(ccCVN) {
        var ccCheckRegExp = /^\d{3,4}$/;
        return ccCheckRegExp.test(ccCVN);
    },
    'cc_CVNText' : 'Please enter a correct CVN',

    'cc_number' : function(ccnum)
    {
        var ccCheckRegExp = /[^\d\s-]/;
        var isValid = !ccCheckRegExp.test(ccnum);
        var i;

        if (isValid) {
            var cardNumbersOnly = ccnum.replace(/[\s-]/g,"");
            var cardNumberLength = cardNumbersOnly.length;

            var arrCheckTypes = ['visa', 'mastercard', 'amex', 'discover', 'dinners', 'jcb'];
            for(i=0; i<arrCheckTypes.length; i++) {
                var lengthIsValid = false;
                var prefixIsValid = false;
                var prefixRegExp;

                switch (arrCheckTypes[i]) {
                    case "mastercard":
                        lengthIsValid = (cardNumberLength === 16);
                        prefixRegExp = /^5[1-5]/;
                        break;

                    case "visa":
                        lengthIsValid = (cardNumberLength === 16 || cardNumberLength === 13);
                        prefixRegExp = /^4/;
                        break;

                    case "amex":
                        lengthIsValid = (cardNumberLength === 15);
                        prefixRegExp = /^3([47])/;
                        break;

                    case "discover":
                        lengthIsValid = (cardNumberLength === 15 || cardNumberLength === 16);
                        prefixRegExp = /^(6011|5)/;
                        break;

                    case "dinners":
                        lengthIsValid = (cardNumberLength === 14);
                        prefixRegExp = /^(300|301|302|303|304|305|36|38)/;
                        break;

                    case "jcb":
                        lengthIsValid = (cardNumberLength === 15 || cardNumberLength === 16);
                        prefixRegExp = /^(2131|1800|35)/;
                        break;

                    default:
                        prefixRegExp = /^$/;
                }

                prefixIsValid = prefixRegExp.test(cardNumbersOnly);
                isValid = prefixIsValid && lengthIsValid;

                // Check if we found a correct one
                if(isValid) {
                    break;
                }
            }
        }

        if (!isValid) {
            return false;
        }

        // Remove all dashes for the checksum checks to eliminate negative numbers
        ccnum = ccnum.replace(/[\s-]/g,"");
        // Checksum ("Mod 10")
        // Add even digits in even length strings or odd digits in odd length strings.
        var checksum = 0;
        for (i = (2 - (ccnum.length % 2)); i <= ccnum.length; i += 2) {
            checksum += parseInt(ccnum.charAt(i - 1));
        }

        // Analyze odd digits in even length strings or even digits in odd length strings.
        for (i = (ccnum.length % 2) + 1; i < ccnum.length; i += 2) {
            var digit = parseInt(ccnum.charAt(i - 1)) * 2;
            if (digit < 10) {
                checksum += digit;
            } else {
                checksum += (digit - 9);
            }
        }

        return (checksum % 10) === 0;
    },
    'cc_numberText' : 'Please enter a correct credit card number',

    /**
     * The function used to validated multiple email addresses on a single line
     * @param {String} v The email addresses - separated by a comma or semi-colon
     */
    'multiemail' : function(v) {
        var array = v.replace(/,/g, ";").split(';');

        var valid = true;
        Ext.each(array, function(value) {
            if (!this.email(value)) {
                valid = false;
                return false;
            }
        }, this);

        return valid;
    },

    /**
     * The error text to display when the multi email validation function returns false
     * @type String
     */
    'multiemailText' : 'This field should be an e-mail address, or a list of email addresses separated by commas(,) in the format "user@domain.com,test@test.com"',

    /**
     * The keystroke filter mask to be applied on multi email input
     * @type RegExp
     */
    'multiemailMask' : /[a-z0-9_.-@,;]/i,

    'multiemailSpecial' : function(v) {
        var array = v.replace(/,/g, ";").split(';');

        var valid = true;
        Ext.each(array, function(value) {
            var field_template = new RegExp('^<%.*%>$');
            var clearTemplate = value.trim();
            if (!field_template.test(clearTemplate)) {
                if (!this.email(value)) {
                    valid = false;
                    return false;
                }
            }
        }, this);

        return valid;
    },

    /**
     * The error text to display when the  SPECIAL multi email validation function returns false
     * @type String
     */
    'multiemailSpecialText' : 'This field should be an e-mail address or field like <%emailFieldName%>, or combination of them separated by commas(,) e.g. in the format "user@domain.com,test@test.com", "<%emailFieldName%>,test@test.com"',

    'multiemailSpecialMask' : /[a-z0-9_.-@,;]/i


});