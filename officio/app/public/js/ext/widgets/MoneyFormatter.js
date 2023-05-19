Ext.util.Format.br2nl = function (v) {
    return v === undefined || v === null ? '' : v.replace(/<br\s*\/?>/gi, '\n');
};

Ext.util.Format.stripTagsLeaveBr = function (v) {
    return Ext.util.Format.stripTags(Ext.util.Format.br2nl(v)).replaceAll('\n', '<br>');
};

if (Ext.util.Format && !Ext.util.Format.CurrencyFactory) {
    Ext.util.Format.CurrencyFactory = function(hasSpace, dp, dSeparator, tSeparator, symbol, rightPosition) {
        return function(n) {

            var spaceText = hasSpace?" ":"";
            dp = Math.abs(dp) + 1 ? dp : 2;
            dSeparator = dSeparator || ".";
            tSeparator = tSeparator === '' ? '' : tSeparator || ",";
            symbol = symbol === '' ? '' : symbol || "$";
            rightPosition = rightPosition || false;

            var m = /(\d+)(?:(\.\d+)|)/.exec(n + ""),
            x = m[1].length > 3 ? m[1].length % 3 : 0;

            var v = (n < 0? '-' : '') // preserve minus sign
            + (x ? m[1].substr(0, x) + tSeparator : "")
            + m[1].substr(x).replace(/(\d{3})(?=\d)/g, "$1" + tSeparator)
            + (dp? dSeparator + (+m[2] || 0).toFixed(dp).substr(2) : "");

            return rightPosition?v+spaceText+symbol:symbol+spaceText+v;
        };
    };
}

var formatMoney = function (currency, val, booShowEmpty, booShowFullCurrency) {
    if (typeof booShowEmpty === "undefined" || booShowEmpty === null) {
        booShowEmpty = false;
    }

    if (typeof booShowFullCurrency === "undefined" || booShowFullCurrency === null) {
        booShowFullCurrency = false;
    }

    val = val || 0;

    if (val === 0 && !booShowEmpty) {
        return '';
    }

    var formatter;
    var currencyLabel = currency.toUpperCase();
    switch (currency.toLowerCase()) {
        case 'aud':
            formatter = Ext.util.Format.CurrencyFactory();
            break;

        case 'cad':
            currencyLabel = 'CDN';
            formatter     = Ext.util.Format.CurrencyFactory();
            break;

        case 'usd':
            currencyLabel = 'US';
            formatter     = Ext.util.Format.CurrencyFactory();
            break;

        case 'eur':
            formatter = Ext.util.Format.CurrencyFactory(false, 2, ",", ".", "\u20ac", true);
            break;

        case 'rur':
            formatter = Ext.util.Format.CurrencyFactory(true, 2, ",", ".", 'RUR', true);
            break;

        default :
            formatter = Ext.util.Format.CurrencyFactory(false, 2, "", "", '', true);
            break;
    }

    return booShowFullCurrency ? currencyLabel + formatter(val) : formatter(val);
};