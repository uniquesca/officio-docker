var GeneratePasswordButton = function(config) {
    Ext.apply(this, config);

    GeneratePasswordButton.superclass.constructor.call(this, {
        text: _('Generate'),
        width: 70,
        icon: topBaseUrl + '/images/refresh.png',
        tooltip: {
            width: 230,
            text:  _('Use this button to generate a new password.')
        },
        handler: this.generatePassword.createDelegate(this)
    });
};

Ext.extend(GeneratePasswordButton, Ext.Button, {
    highlightEl: function(fieldEl) {
        // Prevent highlight several times
        if(!fieldEl.hasActiveFx()) {
            fieldEl.highlight("FF8432", { attr: 'color', duration: 1 });
        }
    },

    isCorrectPassword: function(strPassword) {
        // @see var passwordValidationRegexp; e.g. in layouts/main/main.phtml
        var passRegex = new RegExp(passwordValidationRegexp);
        return passRegex.test(strPassword);
    },

    generatePassword: function() {
        var thisButton = this;

        var newPassword = '';
        do {
            newPassword = generatePassword();
        } while (!thisButton.isCorrectPassword(newPassword));

        var btnEl = Ext.get(thisButton.passwordField);
        $('#' + thisButton.passwordField).val(newPassword).keyup();

        thisButton.highlightEl(btnEl);
    }
});