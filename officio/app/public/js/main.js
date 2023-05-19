var gt = new Gettext();
// create a shortcut for gettext
function _ (msgid) { return gt.gettext(msgid); }

//fix combobox for EditorGrid
Ext.namespace('Ext.ux');
Ext.ux.comboBoxRenderer = function (combo) {
    return function (value) {
        var idx = combo.store.find(combo.valueField, value);
        if (idx !== -1) {
            var rec = combo.store.getAt(idx);
            return rec.get(combo.displayField);
        } else {
            return value;
        }
    };
};

// Fix error message in IE
(function(){
    if (typeof console == 'undefined' || typeof console.log == 'undefined') {
        console = { log: function() {} };
    }
})();

/*global Ext, Application */
Ext.BLANK_IMAGE_URL = topBaseUrl + '/js/ext/resources/images/default/s.gif';
Ext.ns('UniquesVO');

// add multi lang:
var url = String.format(topBaseUrl+"/js/ext/locale/ext-lang-{0}.js", Cookies.get('lang') || 'en');
Ext.Ajax.request({
    url: url,
    success: function (response, opts) {
        eval(response.responseText);
    },
    failure: function () {
        Ext.Msg.alert('Failure', 'Failed to load locale file.');
    },
    scope: this
});

// Fix to use the input maxWidth field -
// so that overtyping results in chopped data
Ext.form.TextField.prototype.initValue = function() {
    if (this.value !== undefined) {
        this.setValue(this.value);
    }else if (this.el.dom.value.length > 0) {
        this.setValue(this.el.dom.value);
    }
    if (!isNaN(this.maxLength) &&
        (this.maxLength * 1) > 0 &&
        (this.maxLength != Number.MAX_VALUE)) {
        this.el.dom.maxLength = this.maxLength * 1;
    }
};

// Don't show shadow by default
Ext.Window.prototype.floating = {shadow: false};

// Default width for all dialogs
Ext.Msg.minWidth = 600;

//Fix wrong label position
Ext.override(Ext.form.DisplayField, {
    labelStyle: 'padding: 0 0 0 0'
});


// Override the default width of the checkbox column
// So we'll have a bigger left padding
Ext.override(Ext.grid.CheckboxSelectionModel, {
    width: 30
});


// Find all invalid fields in the form
// Please check http://goo.gl/5iZeg
Ext.override(Ext.form.BasicForm, {
    findInvalid: function() {
        var result = [], it = this.items.items, l = it.length, i, f;
        for (i = 0; i < l; i++) {
            if(!(f = it[i]).disabled && f.boxReady) {
                if(f.el.hasClass(f.invalidClass)){
                    result.push(f);
                }
            }
        }
        return result;
    }
});

// Ensures that a Component is visible by walking up its ownerCt chain and activating any parent Container.
// Please check http://goo.gl/5iZeg
Ext.override(Ext.Component, {
    ensureVisible: function(stopAt) {
        var p;
        this.ownerCt.bubble(function(c) {
            if (p = c.ownerCt) {
                if (p instanceof Ext.TabPanel) {
                    p.setActiveTab(c);
                } else if (p instanceof Ext.form.FieldSet) {
                    p.expand(false);
                } else if (p.layout.setActiveItem) {
                    p.layout.setActiveItem(c);
                }
            }
            return (c !== stopAt);
        });

        return this;
    }
});

//FIX: Hide ExtJS element including label
Ext.override(Ext.layout.FormLayout, {
    renderItem: function(c, position, target) {
        if (c && !c.rendered && (c.isFormField || c.fieldLabel) &&
            c.inputType != 'hidden') {
            var args = this.getTemplateArgs(c);
            if (typeof position == 'number') {
                    position = target.dom.childNodes[position] || null;
            }
            if (position) {
                c.itemCt = this.fieldTpl.insertBefore(position, args, true);
            }else {
                    c.itemCt = this.fieldTpl.append(target, args, true);
            }
            c.actionMode = 'itemCt';
            c.render('x-form-el-' + c.id);
            c.container = c.itemCt;
            c.actionMode = 'container';
        }else {
            Ext.layout.FormLayout.superclass.renderItem.apply(this, arguments);
        }
    }
});
Ext.override(Ext.form.Field, {
        getItemCt: function() {
                return this.itemCt;
        }
});

// Always show decimals, e.g. 1.00 -> 1.00
// Previously was showed 1.00 -> 1
Ext.override(Ext.form.NumberField, {
    setValue: function(v) {
        if (typeof v != 'number') {
            v = String(v).replace(this.decimalSeparator, '.');
        }
        v = isNaN(v) ? '' : String(v).replace('.', this.decimalSeparator);
        return Ext.form.NumberField.superclass.setValue.call(this, v);
    },
    fixPrecision: function(value) {
        var nan = isNaN(value);
        if (!this.allowDecimals ||
            this.decimalPrecision == -1 ||
            nan ||
            !value) {
           return nan ? '' : value;
        }
        return parseFloat(value).toFixed(this.decimalPrecision);
    }
});

// Implements the default empty TriggerField.onTriggerClick function to display the DatePicker
// Change: adds a 'defaultValue' option to be used if the picker is opened on an empty field
Ext.override(Ext.form.DateField, {
    onTriggerClick : function(){
        if(this.disabled){
            return;
        }
        if(this.menu == null){
            this.menu = new Ext.menu.DateMenu({
                hideOnClick: false
            });
        }
        this.onFocus();
        Ext.apply(this.menu.picker,  {
            minDate : this.minValue,
            maxDate : this.maxValue,
            disabledDatesRE : this.disabledDatesRE,
            disabledDatesText : this.disabledDatesText,
            disabledDays : this.disabledDays,
            disabledDaysText : this.disabledDaysText,
            format : this.format,
            showToday : this.showToday,
            minText : String.format(this.minText, this.formatDate(this.minValue)),
            maxText : String.format(this.maxText, this.formatDate(this.maxValue))
        });

        if( typeof this.defaultValue == 'string' ) {
            this.defaultValue = Date.parseDate( this.defaultValue, this.format );
        }

        this.menu.picker.setValue(this.getValue() || this.defaultValue || new Date());
        this.menu.show(this.el, "tl-bl?");
        this.menuEvents('on');
    }
});

// Allow to filter the dropdown list with "contains" instead of "startswith"
Ext.override(Ext.form.ComboBox, {
        doQuery: function(q, forceAll) {
                q = Ext.isEmpty(q) ? '' : q;
                var qe = {
                        query: q,
                        forceAll: forceAll,
                        combo: this,
                        cancel: false
                };
                if (this.fireEvent('beforequery', qe) === false || qe.cancel) {
                        return false;
                }
                q = qe.query;
                forceAll = qe.forceAll;
                if (forceAll === true || (q.length >= this.minChars)) {
                        if (this.lastQuery !== q) {
                                this.lastQuery = q;
                                if (this.mode == 'local') {
                                        this.selectedIndex = -1;
                                        if (forceAll) {
                                                this.store.clearFilter();
                                        }else {
                                                // @NOTE: added new parameter
                                                var booSearchContains = this.searchContains || false;
                                                this.store.filter(this.displayField, q, booSearchContains);
                                        }
                                        this.onLoad();
                                }else {
                                        this.store.baseParams[this.queryParam] = q;
                                        this.store.load({
                                                params: this.getParams(q)
                                        });
                                        this.expand();
                                }
                        }else {
                                this.selectedIndex = -1;
                                this.onLoad();
                        }
                }
        }
});

Ext.override(Ext.Window, {
    //constrain the window within its containing element
    constrain: true,
    //fix function center()
    center: function() {
        var xy = this.el.getAlignToXY(this.container, 'c-c');
        xy[1] = xy[1] < 0 ? 0 : xy[1]; //NEW
        this.setPagePosition(xy[0], xy[1]);
        return this;
    }
});

// Fix: IE9 issue with combobox list height
if (typeof Range !== "undefined" && typeof Range.prototype.createContextualFragment == "undefined") {
    Range.prototype.createContextualFragment = function(html) {
        var startNode = this.startContainer;
        var doc = startNode.nodeType == 9 ? startNode : startNode.ownerDocument;
        var container = doc.createElement("div");
        container.innerHTML = html;
        var frag = doc.createDocumentFragment(), n;
        while ( (n = container.firstChild) ) {
            frag.appendChild(n);
        }
        return frag;
    };
}

// Automatically resize list width to the max item length in the combo
(function(){
    var originalOnLoad = Ext.form.ComboBox.prototype.onLoad;
    Ext.form.ComboBox.prototype.onLoad = function(){
        var ret = originalOnLoad.apply(this,arguments);
        if (!this.doNotAutoResizeList) {
            var padding = 25;
            var max = Math.max(this.minListWidth || 0, this.el.getWidth());
            Ext.each(this.view.getNodes(), function(node){
                if(node.scrollWidth){ max = Math.max(max,node.scrollWidth+padding); }
            });

            if (max > 0 && max - padding + 2 != this.list.getWidth(true)) {
                this.list.setWidth(max);
                this.innerList.setWidth(max - this.list.getFrameWidth('lr'));
            }
        }

        return ret;
    };

    // Automatically show the list when combo gets a focus
    var originalOnFocus = Ext.form.ComboBox.prototype.initEvents;
    Ext.form.ComboBox.prototype.initEvents = function () {
        originalOnFocus.apply(this, arguments);

        var combo = this;
        if (combo.editable) {
            combo.on('focus', function () {
                if (!combo.isExpanded()) {
                    combo.restrictHeight();
                    combo.expand();
                }
            });
        }
    };
})();


//fix htmleditor (second time body not activated)
Ext.override(Ext.form.HtmlEditor, {
    // Update: allow to have more than 2 font sizes when using Chrome or Safari
    adjustFont: function(btn){
        var adjust = btn.itemId == 'increasefontsize' ? 1 : -1;

        var v = parseInt(this.doc.queryCommandValue('FontSize') || 2, 10);
        if(Ext.isSafari){ // safari
            adjust *= 2;
        }
        v = Math.max(1, v+adjust) + (Ext.isSafari ? 'px' : 0);
        this.execCmd('FontSize', v);
    },

    // Fix: when adding a form on the landing page, if using chrome, some strange characters appear.
    insertAtCursor : function(text){
        if(!this.activated){
            return;
        }
        if(Ext.isIE){
            this.win.focus();
            var r = this.doc.selection.createRange();
            if(r){
                r.collapse(true);
                r.pasteHTML(text);
                this.syncValue();
                this.deferFocus();
            }
        }else if(Ext.isGecko || Ext.isOpera || Ext.isWebKit){
            this.win.focus();
            this.execCmd('InsertHTML', text);
            this.deferFocus();
        }
    },

    // Fix issue with scroll in FF
    getDocMarkup: function() {
        return '<html><head>' +
               '<style type="text/css">' +
               'body{border:0;margin:0;padding:3px;cursor:text;}' +
               '</style></head><body></body></html>';
    },

    setReadOnly: function(readOnly) {
            if (readOnly) {
                this.syncValue();
                var roMask = this.wrap.mask();
                roMask.dom.style.filter = 'alpha(opacity=0.5);'; //IE
                roMask.dom.style.opacity = '0.5'; //Mozilla
                roMask.dom.style.background = 'white';
                roMask.dom.style.overflow = 'auto';
                roMask.dom.style.zIndex = 10;
                this.el.dom.readOnly = true;
            } else {
                if (this.rendered) {
                    this.wrap.unmask();
                }
                this.el.dom.readOnly = false;
            }
    },

   // private
    onRender: function(ct, position) {
        Ext.form.HtmlEditor.superclass.onRender.call(this, ct, position);
        this.el.dom.style.border = '0 none';
        this.el.dom.setAttribute('tabIndex', -1);
        this.el.addClass('x-hidden');
        if (Ext.isIE) { // fix IE 1px bogus margin
            this.el.applyStyles('margin-top:-1px;margin-bottom:-1px;');
        }
        this.wrap = this.el.wrap({
            cls: 'x-html-editor-wrap', cn: {cls: 'x-html-editor-tb'}
        });

        this.createToolbar(this);

        this.tb.items.each(function(item) {
           if (item.itemId != 'sourceedit') {
                item.disable();
            }
        });

        var iframe = document.createElement('iframe');
        iframe.name = Ext.id();
        iframe.frameBorder = 'no';

        iframe.src = (Ext.SSL_SECURE_URL || 'javascript:false');

        this.wrap.dom.appendChild(iframe);

        this.iframe = iframe;

        if (Ext.isIE) {
            iframe.contentWindow.document.designMode = 'on';
            this.doc = iframe.contentWindow.document;
            this.win = iframe.contentWindow;
        } else {
            this.doc = (iframe.contentDocument || window.frames[iframe.name].document);
            this.win = window.frames[iframe.name];
            this.doc.designMode = 'on';
        }
        this.doc.open();
        this.doc.write(this.getDocMarkup());
        this.doc.close();

        var task = { // must defer to wait for browser to be ready
            run: function() {
                if (this.doc.body || this.doc.readyState == 'complete') {
                    Ext.TaskMgr.stop(task);
                    this.doc.designMode = 'on';
                    this.initEditor.defer(10, this);
                }
            },
            interval: 10,
            duration: 10000,
            scope: this
        };
        Ext.TaskMgr.start(task);

        if (!this.width) {
            this.setSize(this.el.getSize());
        }

        this.setReadOnly(this.readOnly);

    }
});

// multi-drag-and-drop-fix start (dance with buben required)
// also, automatically uncheck the "check all" checkbox if not all rows are selected
Ext.grid.CheckboxSelectionModel.override({
    initEvents: function () {
        var gridObject = this;
        Ext.grid.CheckboxSelectionModel.superclass.initEvents.call(gridObject);
        if (gridObject.grid.enableDragDrop || gridObject.grid.enableDrag) {
            gridObject.grid.events['rowclick'].clearListeners();
        }

        gridObject.grid.on('render', function () {
            var view = gridObject.grid.getView();

            view.mainBody.on('mousedown', function (e, t) {
                this.onMouseDown(e, t);

                if (gridObject.grid) {
                    // Use a delay to get the count of currently checked items
                    setTimeout(function () {
                        var mappingChecker = $('#' + gridObject.grid.id + ' .x-grid3-header .x-grid3-hd-checker')[0];
                        if (gridObject.grid.getSelectionModel().getCount() == gridObject.grid.getStore().getCount()) {
                            $(mappingChecker).addClass('x-grid3-hd-checker-on');
                        } else {
                            $(mappingChecker).removeClass('x-grid3-hd-checker-on');
                        }
                    }, 50);
                }
            }, this);
            Ext.fly(view.innerHd).on('mousedown', this.onHdMouseDown, this);
        }, gridObject);
    }
});

Ext.grid.GridDragZone.override({
    handleMouseDown: function (e, t) {
        if (t.className == 'x-grid3-row-checker')
            return false;
        Ext.grid.GridDragZone.superclass.handleMouseDown.apply(this, arguments);
    }
});
// multi-drag-and-drop-fix end

// Show log out message
// Note the same method is in the /superadmin/js/init_extjs.js file
var showLoggedOutMessage = function (msg) {
    var booShowMsg = false;
    var showMsg = 'You are logged out from this session.';

    try {
        if (msg == 'You have been logged out because you logged in on another computer or browser.' ||
            msg == 'You have been logged out because session has timed out.' ||
           msg == 'Access is denied during non-office hours.<br/>Please try again later.' ||
           msg == 'We are undergoing a regular system upgrade. The system will be available shortly.' ||
           msg == 'Insufficient access rights.' ||
           msg == 'You have been logged out from this session.') {
            booShowMsg = true;
            showMsg = msg;
        }
    } catch (e) {
        // Do nothing
    }

    if (booShowMsg) {
        Ext.Msg.show({
            title: 'Information',
            msg: showMsg,
            icon: Ext.MessageBox.WARNING,
            buttons: {yes: 'OK'},
            closable: false,
            fn: function(btn) {
                if (btn == 'yes') {
                    window.location = baseUrl;
                }
            }
        });
    }

    return booShowMsg;
};

// Listen for 'log out' message
// For ajax requests sent by extjs
Ext.util.Observable.observeClass(Ext.data.Connection);
Ext.data.Connection.on('requestexception', function(conn, response, options) {
    if (response.status === 401 && showLoggedOutMessage(response.responseText)) {
        // Don't process response in ajax's callback methods
        delete options.failure;
        delete options.callback;
    }
});

// For ajax requests sent by jquery
$.ajaxSetup({
    timeout: 90000,
    error:   function (jqXHR) {
        if (jqXHR.status === 401) {
            showLoggedOutMessage(jqXHR.responseText);
        }
    }
});

//application main entry point
Ext.onReady(function() {
    Ext.Ajax.timeout = 90000;

    // Init tooltips
    Ext.QuickTips.init();
    // Don't hide tooltips automatically
    Ext.apply(Ext.QuickTips.getQuickTip(), {
        dismissDelay: 0
    });
});

// make it so that no error is thrown if bgIframe plugin isn't included (allows you to use conditional
// comments to only include bgIframe where it is needed in IE without breaking this plugin).
if ($.fn.bgIframe === undefined) {
    $.fn.bgIframe = function() {return this;};
}

// On page load
$(document).ready(function() {
    //800x600
    if (getWindowWidth() <= 800) {

        //user name position
        $('.user-name').css('top', '-40px');

        //main body
        $('body').css('padding-left', '2px');
    }
});

// Allow select row's content
if (!Ext.grid.GridView.prototype.templates) {
        Ext.grid.GridView.prototype.templates = {};
}

Ext.grid.GridView.prototype.templates.cell = new Ext.Template(
    '<td class="x-grid3-col x-grid3-cell x-grid3-td-{id} x-selectable {css}" style="{style}" tabIndex="0" {cellAttr}>',
    '<div class="x-grid3-cell-inner x-grid3-col-{id}" {attr}>{value}</div>',
    '</td>'
);

//////        GLOBAL FUNCTION

function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : event.keyCode;
        return !(charCode > 31 && (charCode < 48 || charCode > 57));
}

/**
 * Case-insensitive strstr()
*/
function stristr(haystack, needle, bool ) {
    var pos = haystack.toLowerCase().indexOf(needle.toLowerCase());
    if (pos == -1) {
        return false;
    } else {
        if (bool) {
            return haystack.substr(0, pos);
        } else {
            return haystack.slice(pos);
        }
    }
}

/**
 *  Joins array elements placing glue string between items and return one string
 *
 *  example 1: implode(' ', ['Kevin', 'van', 'Zonneveld']);
 *  returns 1: 'Kevin van Zonneveld'
 *  example 2: implode(' ', {first:'Kevin', last: 'van Zonneveld'});
 *  returns 2: 'Kevin van Zonneveld'
*/
function implode(glue, pieces) {
    var i = '', retVal = '', tGlue = '';
    if (arguments.length === 1) {
        pieces = glue;
        glue = '';
    }
    if (typeof(pieces) === 'object') {
        if (pieces instanceof Array) {
            return pieces.join(glue);
        }
        else {
            for (i in pieces) {
                if (pieces.hasOwnProperty(i)) {
                    retVal += tGlue + pieces[i];
                    tGlue = glue;
                }
            }
            return retVal;
        }
    }
    else {
        return pieces;
    }
}

function preg_quote(str) {
    // *     example 1: preg_quote("$40");
    // *     returns 1: '\$40'
    // *     example 2: preg_quote("*RRRING* Hello?");
    // *     returns 2: '\*RRRING\* Hello\?'
    // *     example 3: preg_quote("\\.+*?[^]$(){}=!<>|:");
    // *     returns 3: '\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:'
    var regexp = /([\\\.\+\*\?\[\^\]\$\(\)\{\}\=\!\<\>\|\:])/g;
    return (str + '').replace(regexp, '\\$1');
}


// Determine whether a variable is empty
function empty(mixed_var) {
    return (typeof(mixed_var) === 'undefined' || mixed_var === '' ||
            mixed_var === 0 || mixed_var === '0' || mixed_var === null ||
            mixed_var === false);
}

// Convert string to boolean
var stringToBoolean = function(mixed_var) {
    if (typeof(mixed_var) === 'undefined' || mixed_var === null) {
        return false;
    }
    switch (mixed_var.toLowerCase()) {
        case 'true': case 'yes': case '1': return true;
        case 'false': case 'no': case '0': return false;
        default: return Boolean(mixed_var);
    }
};


// Used to play with float numbers
function toFixed(value, precision) {
    var power = Math.pow(10, precision || 0);
    return Math.round(value * power) / power;
}


function getComboBoxIndex(combobox)
{
    var value = combobox.getValue();
    var record = combobox.findRecord(combobox.valueField || combobox.displayField, value);
    return combobox.store.indexOf(record);
}

function generatePassword() {
    function checkPunc(num) {
        var s = ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'p', 'a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'z', 'x', 'c', 'v', 'b', 'n', 'm', 'Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'P', 'A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'Z', 'X', 'C', 'V', 'B', 'N', 'M', '2', '3', '4', '5', '6', '7', '8', '9', '_'];
        return s.has(String.fromCharCode(num));
    }

    function getRandomNum() {
        var rndNum = Math.random();
        rndNum = parseInt(rndNum * 1000, 10);
        return (rndNum % 94) + 33;
    }

    var sPassword = '';

    var length = Math.random();
    length = parseInt(length * 100, 10);
    length = (length % 5) + 6;

    for (var i = 0; i < length; i++) {
        var numI = getRandomNum();
        while (!checkPunc(numI)) {
            numI = getRandomNum();
        }

        sPassword = sPassword + String.fromCharCode(numI);
    }

    return sPassword;
}

//JavaScript function, Equal PHP function in_array()
Array.prototype.has = function(v, i) {
    for (var j = 0; j < this.length; j++) {
        if (this[j] == v) return (!i ? true : j);
    }
    return false;
};

//remove value from array
Array.prototype.remove = function(v) {
    return this.splice(this.indexOf(v), 1);
};

function loadDatePicker()
{
    var els = Ext.select('input.datepicker', true);
    els.each(function(el) {
        var val = $('#' + el.id).val();
        var newId = 'datepicker_' + el.id;
        var disabled = $('#' + el.id).is('[disabled]') || $('#' + el.id).parent().is('[disabled]');

            var df = new Ext.form.DateField({
                id: newId,
                format: dateFormatFull,
                maxLength: 12, // Fix issue with date entering in 'full' format
                altFormats: dateFormatFull + '|' + dateFormatShort
            });
            df.applyToMarkup(el);

            // Chrome fix - invalid date on applying to entered date
            if (!empty(val) && Ext.isChrome) {
                try {
                    var dt = new Date(val);
                    df.setValue(dt);
                } catch(err) {
                }
            }

        $('#' + el.id).removeClass('datepicker');
        if (disabled) {
            $('#' + el.id).parent().find('.x-form-date-trigger').css('pointer-events', 'none');
        }
    });
}

function loadNumberField() {
    var els = Ext.select('input.profile-number', true);
    var meta;
    $.metadata.setType( "class" );
    els.each(function (el) {
        meta = $('#' + el.id).metadata();
        var val = $('#' + el.id).val();
        var df  = new Ext.ux.form.NormalNumberField({
            id:    'profile_number_' + el.id,
            value: val,
            forceDecimalPrecision : false,
            minValue: !empty(meta.min) ? meta.min : undefined,
            maxValue: !empty(meta.max) ? meta.max : undefined,
            allowDecimals: !meta.integer,
            decimalPrecision: !meta.integer ? 1 : 0
        });
        df.applyToMarkup(el);

        $('#' + el.id).removeClass('profile-number');
    });
}

function initComboMultiple() {
    $('.combo-multiple').each(function (index, el) {
        // Remove this class, to prevent try to generate combo again
        $('#' + el.id).removeClass('combo-multiple');

        var strSelected = '';
        $(this).find('option:selected').each(function() {
            if (strSelected != '') {
                strSelected += ',';
            }
            strSelected += $(this).val();
        });

        // Search if separator is used in the option
        // If yes - search for another, not used
        var arrSeparators = [',', ';', '·'];
        var separator     = '';
        for (var i = 0; i < arrSeparators.length; i++) {
            var booSeparatorFound = false;
            $(this).find('option').each(function () {
                if ($(this).text().indexOf(arrSeparators[i]) !== -1) {
                    booSeparatorFound = true;
                }
            });

            if (!booSeparatorFound) {
                separator = arrSeparators[i];
                break;
            }
        }

        var converted = new Ext.ux.form.LovCombo({
            typeAhead:      true,
            triggerAction:  'all',
            cls:            $(this).attr('class'),
            transform:      $(this).attr('id'),
            separator:      separator,
            forceSelection: true
        });

        if (strSelected != '') {
            converted.setValue(strSelected);
        }
    });
}

function toggleDiv(panelType, tab_id, group_id, booShowOnlyOneGroup)
{
    var link = $('#arw' + tab_id + group_id);
    var group = $('#dv' + tab_id + group_id);

    // In some cases we need collapse other groups
    if (booShowOnlyOneGroup) {
        // Hide all expanded groups
        $('.' + tab_id + 'profile_section_content').hide();
        $('#' + tab_id + 'profile .blue-arrow-down').each(function(i, el) {
            if ($(el).hasClass('blue-arrow-down')) {
                $(el).addClass('blue-arrow-up');
                $(el).removeClass('blue-arrow-down');
            }
        });

        //show "Save" button only beside open group
        $('#save' + tab_id + group_id).show();
    }

    // Update icon for this link + show related group
    var spouseLanguageDiv = $('#dv' + tab_id + group_id + 'spouse');
    if (link.hasClass('blue-arrow-down')) {
        link.removeClass('blue-arrow-down');
        link.addClass('blue-arrow-up');
        group.hide();
        if (spouseLanguageDiv) {
            spouseLanguageDiv.hide();
        }
    } else {
        link.addClass('blue-arrow-down');
        link.removeClass('blue-arrow-up');
        group.show();
        if (spouseLanguageDiv) {
            spouseLanguageDiv.show();
        }
    }

    if (!empty(panelType)) {
        var tabPanel = Ext.getCmp(panelType + '-tab-panel');
        if (tabPanel) {
            tabPanel.fixParentPanelHeight();
        }
    }
}

function HtmlTagSearch(text_for_search) {
    return (text_for_search.match(/<\/?[^<>]*>/i));
}

function calculateGridHeight(store)
{
    var new_grid_height = store.getCount();
    if (empty(new_grid_height)) {
        new_grid_height = 32;
    } else {
        new_grid_height = new_grid_height * 22;
    }
    return new_grid_height + 77;
}

function getWindowWidth()
{
    var x = 0;
    if (self.innerHeight) {
        x = self.innerWidth;
    } else if (document.documentElement && document.documentElement.clientHeight) {
        x = document.documentElement.clientWidth;
    } else if (document.body) {
        x = document.body.clientWidth;
    }
    return x;
}

function parseUrlHash(hash) {
        return hash.replace('#', '').split('/');
}

function setUrlHash(hash, firstLoad) {
    if (firstLoad) {
        var new_hash = parseUrlHash(hash);
        var cur_hash = parseUrlHash(location.hash);

        location.hash = (cur_hash[0] == new_hash[0] ? location.hash : hash);
    } else {
        location.hash = (hash != location.hash ? hash : location.hash);
    }

    return location.hash;
}

function decodeHTML(html) {
    return $("<div/>").html(html).text();
}

function submit_post_via_hidden_form(url, params) {
    var f = $("<form target='_blank' method='POST' class='hidden_form' style='display:none;'></form>").attr({
        action: url
    }).appendTo(document.body);

    for (var i in params) {
        if (params.hasOwnProperty(i)) {
            $('<input type="hidden" />').attr({
                name: i,
                value: params[i]
            }).appendTo(f);
        }
    }

    f.submit();

    f.remove();
}

function submit_hidden_form(url, params) {
    $.fileDownload(url, {
        httpMethod: 'POST',
        modal:      false,
        data:       params,

        failCallback: function () {
            var msg = 'There was a problem, please try again.';
            if (Ext) {
                Ext.simpleConfirmation.error(msg);
            } else {
                alert(msg);
            }
        }
    });
}

function ucfirst(str)
{
    var f=str.charAt(0).toUpperCase();
    return f+str.substr(1, str.length-1);
}

function trim(string)
{
    return string.replace(/(^\s+)|(\s+$)/g, "");
}

// Generate panel size in relation to the user's screen resolution
function initPanelSize(booWithoutPadding) {
    var height = Ext.getBody().getViewSize().height - $('.head-td').outerHeight();
    return booWithoutPadding ? height : height - 57;
}

function print(content, title) {
    var printWindow = Ext.ux.Popup.show('about:blank', true);
    if (printWindow) {
        var printContent = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"\n' +
            '"http://www.w3.org/TR/html4/strict.dtd">\n' +
            '<html>\n' +
            '<head>\n' +
            '<title>' + title + '</title>\n' +
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">\n' +
            '<link href="' + baseUrl + '/styles/main.css" rel="stylesheet" type="text/css" />' +
            '<link href="' + baseUrl + '/styles/themes/default.css" rel="stylesheet" type="text/css" />' +
            '<link href="' + baseUrl + '/styles/print.css?' + (new Date()).getTime() + '" rel="stylesheet" type="text/css" />' +
            '</head>' +
            '<body>\n' +
            content + '\n' +
            '</body>\n' +
            '</html>';

        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();

        // Since we are performing the ExtJS check whether popup is blocked, we need to
        // defer further actions until check is complete.
        // That check takes for Chrome 200ms, so we do 300. This value
        // might be tuned if needed.
        setTimeout(function () {
            if (printWindow.document.readyState === "complete") {
                printWindow.print();
                printWindow.close();
            } else {
                printWindow.addEventListener('onload', function () {
                    printWindow.print();
                    printWindow.close();
                })
            }
        }, 300);
    }
}

function extractFileName(data) {
    data = data.replace(/^\s|\s$/g, "");

    var m;
    if (/\.\w+$/.test(data)) {
        m = data.match(/([^\/\\]+)\.(\w+)$/);
        if (m)
            return {filename: m[1], ext: m[2]};
        else
            return {filename: "", ext:null};
    } else {
        m = data.match(/([^\/\\]+)$/);
        if (m)
            return {filename: m[1], ext: null};
        else
            return {filename: "", ext:null};
    }
}

function validateEmailField(field) {
    var booValid = false,
        value = field.getValue();

    if (value === '') {
        booValid = true;
    } else if (Ext.form.VTypes.email(value)) {
        booValid = true;
    } else {
        var match = value.match(/^(.*)"(.*)"(.*)$/);
        if (match != null) {
            if (Ext.form.VTypes.email(match[2])) {
                match[1] = empty(match[1]) ? '' : trim(match[1]);
                match[3] = empty(match[3]) ? '' : trim(match[3]);

                if (!empty(match[1]) || !empty(match[3])) {
                    booValid = true;
                }
            }
        }
    }

    if (!booValid) {
        field.markInvalid(
            'Valid values are:<br\>' +
            '1. Empty value (email address will be used from user\'s default email account)<br\>' +
            '2. <b>email@address.com</b> <br\>' +
            '3. <b>Some Name "email@address.com"</b> OR <br\>' +
               '<b>"email@address.com" Some Name</b> OR <br\>' +
               '<b>Some "email@address.com" Name</b>'
        );
    } else {
        field.getEl().removeClass('x-form-invalid');
    }
}

/**
 * Refresh settings
 * @param selector - what to refresh, e.g. "contact_sales_agent" - criteria for contact_sales_agent
 */
function refreshSettings(selector){
    if (typeof arrApplicantsSettings === 'undefined') {
        return;
    }

    Ext.Ajax.request({
        url: baseUrl + '/applicants/index/refresh-settings',
        params: {
            selector: Ext.encode(selector)
        },
        success: function (f) {
            var resultData = Ext.decode(f.responseText);
            if (resultData.success) {
                switch (selector) {
                    case 'all':
                        arrApplicantsSettings.options['general'] = resultData.arrSettings['options']['general'];
                        arrApplicantsSettings.options['case'] = resultData.arrSettings['options']['case'];
                        break;

                    case 'agents':
                        arrApplicantsSettings.options['general']['contact_sales_agent'] = resultData.arrSettings['contact_sales_agent'];
                        arrApplicantsSettings.options['general']['agents'] = resultData.arrSettings['agents'];
                        break;

                    case 'office':
                        arrApplicantsSettings.options['general']['office'] = resultData.arrSettings;
                        break;

                    case 'users':
                        arrApplicantsSettings.options['general']['active_users'] = resultData.arrSettings['active_users'];
                        arrApplicantsSettings.options['general']['staff_responsible_rma'] = resultData.arrSettings['staff_responsible_rma'];
                        arrApplicantsSettings.options['general']['assigned_to'] = resultData.arrSettings['assigned_to'];
                        break;

                    case 'immigration_office':
                    case 'visa_office':
                        arrApplicantsSettings.options['general']['visa_office'] = resultData.arrSettings;
                        break;

                    case 'employer_settings':
                        arrApplicantsSettings.options['general']['employee'] = resultData.arrSettings['employee'];
                        arrApplicantsSettings.options['general']['employer_contacts'] = resultData.arrSettings['employer_contacts'];
                        break;

                    case 'list_of_occupations':
                        arrApplicantsSettings.options['general']['list_of_occupations'] = resultData.arrSettings;
                        break;

                    case 'authorized_agents':
                        arrApplicantsSettings.options['general']['authorized_agents'] = resultData.arrSettings;
                        break;

                    case 'categories':
                        arrApplicantsSettings.options['general']['categories'] = resultData.arrSettings;
                        break;

                    case 'case_status':
                        arrApplicantsSettings.options['general']['case_statuses'] = resultData.arrSettings;
                        break;

                    case 'accounting':
                        arrApplicantsSettings.accounting = resultData.arrSettings;
                        break;

                    default:
                        break;
                }
            }
        }
    });
}

function hasAccessToRules(panelType, mainRule, subRule) {
    var booHasAccess = false;

    try {
        if (typeof arrApplicantsSettings != 'undefined' && typeof arrApplicantsSettings['access'] != 'undefined') {
            booHasAccess = panelType === 'contacts' ? arrApplicantsSettings['access']['contact'][mainRule][subRule] : arrApplicantsSettings['access'][mainRule][subRule];
        }
    } catch (e) {
    }

    return booHasAccess;
}

// A helper method to get the list of elements we want to disable
function getOverlayItems() {
    var arrElements = [];

    var el = $('.head-td');
    if (el.length) {
        arrElements.push(el[0]);
    }

    $('#main-tab-panel .x-tab-panel-header:visible').each(function () {
        arrElements.push(this);
    });

    return arrElements;
}

// Open the overlay (used by embedded Angular application in an iframe)
function openOverlay(clickable, click_callback) {
    var arrElements = getOverlayItems();

    // Show mask for all elements that we want to disable
    for (var i = 0; i < arrElements.length; i++) {
        Ext.get(arrElements[i]).mask();
    }

    if (clickable) {
        for (var i = 0; i < arrElements.length; i++) {
            // Listen for a click event on each element
            $(arrElements[i]).on('click', function () {
                // Remove events for all elements (we don't need them anymore)
                for (var j = 0; j < arrElements.length; j++) {
                    $(arrElements[j]).unbind('click');
                }

                // Enable elements
                closeOverlay();

                // Call the callback method if provided
                if (!empty(click_callback)) {
                    click_callback();
                }
            });
        }
    }
}

// Close the overlay (used by embedded Angular application in an iframe)
function closeOverlay() {
    var arrElements = getOverlayItems();
    for (var i = 0; i < arrElements.length; i++) {
        Ext.get(arrElements[i]).unmask();
    }
}